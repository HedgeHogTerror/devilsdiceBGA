<?php

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * DevilsDice implementation : © hedgehogterror, brookelfnichols@gmail.com
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * Full source available at https://github.com/hedgehogterror/bga-devilsdice
 *
 * -----
 *
 * Game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 */

declare(strict_types=1);

namespace Bga\Games\DevilsDice;

require_once(APP_GAMEMODULE_PATH . "module/table/table.game.php");
require_once("autoload.php");

class Game extends \Table {
    /**
     * Your global variables labels:
     *
     * Here, you can assign labels to global variables you are using for this game. You can use any number of global
     * variables with IDs between 10 and 99. If your game has options (variants), you also have to associate here a
     * label to the corresponding ID in `gameoptions.inc.php`.
     *
     * NOTE: afterward, you can get/set the global variables with `getGameStateValue`, `setGameStateInitialValue` or
     * `setGameStateValue` functions.
     */
    public function __construct() {
        parent::__construct();

        $this->initGameStateLabels([
            "current_action" => 10,
            "current_action_player" => 11,
            "current_target_player" => 12,
            "challenge_player" => 13,
            "action_data" => 14
        ]);
    }

    /**
     * Game state arguments, example content.
     *
     * This method returns some additional information that is very specific to the `playerTurn` game state.
     *
     * @return array
     * @see ./states.inc.php
     */


    /**
     * Compute and return the current game progression.
     *
     * The number returned must be an integer between 0 and 100.
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true.
     *
     * @return int
     * @see ./states.inc.php
     */
    public function getGameProgression(): int {
        return 0;
    }

    /**
     * Player actions
     */

    public function raiseHell() {
        $this->checkAction('raiseHell');
        $playerId = intval($this->getCurrentPlayerId());

        $this->setGameStateValue('current_action', Actions::RAISE_HELL);
        $this->setGameStateValue('current_action_player', $playerId);

        $this->gamestate->nextState('challengeWindow');
    }

    public function harvestSkulls() {
        $this->checkAction('harvestSkulls');
        $playerId = intval($this->getCurrentPlayerId());

        $this->setGameStateValue('current_action', Actions::HARVEST_SKULLS);
        $this->setGameStateValue('current_action_player', $playerId);

        $this->gamestate->nextState('challengeWindow');
    }

    public function extort($targetPlayerId) {
        $this->checkAction('extort');
        $playerId = intval($this->getCurrentPlayerId());

        $this->setGameStateValue('current_action', Actions::EXTORT);
        $this->setGameStateValue('current_action_player', $playerId);
        $this->setGameStateValue('current_target_player', $targetPlayerId);

        $this->gamestate->nextState('challengeWindow');
    }

    public function reapSoul($targetPlayerId) {
        $this->checkAction('reapSoul');
        $playerId = intval($this->getCurrentPlayerId());

        // Check if player has enough skull tokens
        $tokens = $this->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $playerId");
        if ($tokens < 2) {
            throw new \BgaUserException("You need 2 skull tokens to use Reap Soul");
        }

        $this->setGameStateValue('current_action', Actions::REAP_SOUL);
        $this->setGameStateValue('current_action_player', $playerId);
        $this->setGameStateValue('current_target_player', $targetPlayerId);

        $this->gamestate->nextState('challengeWindow');
    }

    public function pentagram() {
        $this->checkAction('pentagram');
        $playerId = intval($this->getCurrentPlayerId());

        $this->setGameStateValue('current_action', Actions::PENTAGRAM);
        $this->setGameStateValue('current_action_player', $playerId);

        $this->gamestate->nextState('challengeWindow');
    }

    public function impsSet() {
        $this->checkAction('impsSet');
        $playerId = intval($this->getCurrentPlayerId());

        $this->setGameStateValue('current_action', Actions::IMPS_SET);
        $this->setGameStateValue('current_action_player', $playerId);

        $this->gamestate->nextState('challengeWindow');
    }

    public function satansSteal($targetPlayerId, $putInPool = false, $poolFace = null) {
        $this->checkAction('satansSteal');
        $playerId = intval($this->getCurrentPlayerId());

        // Check if player has enough skull tokens
        $tokens = $this->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $playerId");
        if ($tokens < 6) {
            throw new \BgaUserException("You need 6 skull tokens to use Satan's Steal");
        }

        $actionData = json_encode([
            'target' => $targetPlayerId,
            'putInPool' => $putInPool,
            'poolFace' => $poolFace
        ]);

        $this->setGameStateValue('current_action', Actions::SATANS_STEAL);
        $this->setGameStateValue('current_action_player', $playerId);
        $this->setGameStateValue('current_target_player', $targetPlayerId);
        $this->setGameStateValue('action_data', $actionData);

        // Satan's Steal cannot be challenged, go directly to resolution
        $this->gamestate->nextState('resolveAction');
    }

    public function challenge() {
        $this->checkAction('challenge');
        $challengerId = intval($this->getCurrentPlayerId());

        $this->setGameStateValue('challenge_player', $challengerId);
        $this->gamestate->nextState('resolveChallenge');
    }

    public function block() {
        $this->checkAction('block');
        $blockerId = intval($this->getCurrentPlayerId());

        $currentAction = $this->getGameStateValue('current_action');

        // Only certain actions can be blocked
        if (!in_array($currentAction, [Actions::EXTORT, Actions::REAP_SOUL])) {
            throw new \BgaUserException("This action cannot be blocked");
        }

        // Set up block challenge window
        $this->setGameStateValue('current_action_player', $blockerId);
        $this->setGameStateValue('current_action', Actions::BLOCK);

        $this->gamestate->nextState('challengeBlock');
    }

    public function pass() {
        $this->checkAction('pass');
        // Player passes on challenge or block opportunity
        // Continue to next state based on current context

        $stateName = $this->gamestate->state()['name'];
        if ($stateName === 'challengeWindow') {
            $currentAction = $this->getGameStateValue('current_action');
            if (in_array($currentAction, [Actions::EXTORT, Actions::REAP_SOUL])) {
                $this->gamestate->nextState('blockWindow');
            } else {
                $this->gamestate->nextState('resolveAction');
            }
        } else if ($stateName === 'blockWindow') {
            $this->gamestate->nextState('resolveAction');
        }
    }

    /**
     * Game state actions
     */

    public function stGameSetup() {
        // Initialize player tokens first
        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $playerId => $player) {
            $this->DbQuery("INSERT INTO player_tokens (player_id, skull_tokens) VALUES ($playerId, 1)");
        }

        // Then give each player 2 starting dice
        foreach ($players as $playerId => $player) {
            $this->rollDiceForPlayer($playerId, 2);
        }

        $this->gamestate->nextState('playerTurn');
    }

    public function stChallengeWindow() {
        $actionPlayerId = $this->getGameStateValue('current_action_player');

        // Activate all players except the action player for potential challenges
        $players = $this->loadPlayersBasicInfos();
        $challengePlayers = [];
        foreach ($players as $playerId => $player) {
            if ($playerId != $actionPlayerId) {
                $challengePlayers[] = $playerId;
            }
        }

        $this->gamestate->setPlayersMultiactive($challengePlayers, 'resolveAction');
        $this->gamestate->initializePrivateStateForAllActivePlayers();
    }

    public function stResolveChallenge() {
        $challengerId = $this->getGameStateValue('challenge_player');
        $actionPlayerId = $this->getGameStateValue('current_action_player');
        $currentAction = $this->getGameStateValue('current_action');

        // Get the challenged player's dice
        $playerDice = $this->getCollectionFromDb(
            "SELECT face FROM player_dice WHERE player_id = $actionPlayerId AND location = 'hand'"
        );

        $challengeSuccessful = !$this->validateActionClaim($currentAction, $playerDice, $actionPlayerId);

        if ($challengeSuccessful) {
            // Challenge successful - challenger steals dice, action cancelled
            $this->stealDiceFromPlayer($challengerId, $actionPlayerId);
            $this->notifyAllPlayers(
                'challengeSuccessful',
                clienttranslate('${challenger} successfully challenges ${player}\'s claim'),
                [
                    'challenger' => $this->getPlayerName($challengerId),
                    'player' => $this->getPlayerName($actionPlayerId),
                    'challengerId' => $challengerId,
                    'actionPlayerId' => $actionPlayerId
                ]
            );

            $this->gamestate->nextState('playerTurn');
        } else {
            // Challenge failed - challenger loses dice to Satan's pool
            $this->moveDiceToSatansPool($challengerId);
            $this->notifyAllPlayers(
                'challengeFailed',
                clienttranslate('${challenger}\'s challenge fails against ${player}'),
                [
                    'challenger' => $this->getPlayerName($challengerId),
                    'player' => $this->getPlayerName($actionPlayerId),
                    'challengerId' => $challengerId,
                    'actionPlayerId' => $actionPlayerId
                ]
            );

            // Continue with original action
            if (in_array($currentAction, [Actions::EXTORT, Actions::REAP_SOUL])) {
                $this->gamestate->nextState('blockWindow');
            } else {
                $this->gamestate->nextState('resolveAction');
            }
        }
    }

    public function stResolveAction() {
        $actionPlayerId = $this->getGameStateValue('current_action_player');
        $currentAction = $this->getGameStateValue('current_action');
        $targetPlayerId = $this->getGameStateValue('current_target_player');

        switch ($currentAction) {
            case Actions::RAISE_HELL:
                $this->executeRaiseHell($actionPlayerId);
                break;
            case Actions::HARVEST_SKULLS:
                $this->executeHarvestSkulls($actionPlayerId);
                break;
            case Actions::EXTORT:
                $this->executeExtort($actionPlayerId, $targetPlayerId);
                break;
            case Actions::REAP_SOUL:
                $this->executeReapSoul($actionPlayerId, $targetPlayerId);
                break;
            case Actions::PENTAGRAM:
                $this->executePentagram($actionPlayerId);
                break;
            case Actions::IMPS_SET:
                $this->executeImpsSet($actionPlayerId);
                break;
            case Actions::SATANS_STEAL:
                $this->executeSatansSteal($actionPlayerId);
                break;
        }

        $this->gamestate->nextState('checkWin');
    }

    public function stCheckWin() {
        $winners = $this->checkForWinners();

        if (count($winners) === 1) {
            // Single winner
            $this->DbQuery("UPDATE player SET player_score = 1 WHERE player_id = " . $winners[0]);
            $this->gamestate->nextState('endGame');
        } else if (count($winners) > 1) {
            // Tie - need rolloff
            $this->setGameStateValue('action_data', json_encode($winners));
            $this->gamestate->nextState('rolloff');
        } else {
            // No winner yet, continue game
            $this->activeNextPlayer();
            $this->gamestate->nextState('playerTurn');
        }
    }

    public function stRolloff() {
        $winners = json_decode($this->getGameStateValue('action_data'), true);

        $rolloffResults = [];
        foreach ($winners as $playerId) {
            $impCount = $this->countImpsForPlayer($playerId);
            $rolloffResults[$playerId] = $impCount;
        }

        $maxImps = max($rolloffResults);
        $finalWinners = array_keys(array_filter($rolloffResults, function ($imps) use ($maxImps) {
            return $imps === $maxImps;
        }));

        if (count($finalWinners) === 1) {
            $this->DbQuery("UPDATE player SET player_score = 1 WHERE player_id = " . $finalWinners[0]);
            $this->notifyAllPlayers(
                'rolloffWinner',
                clienttranslate('${player} wins the rolloff with ${imps} imps'),
                [
                    'player' => $this->getPlayerName($finalWinners[0]),
                    'imps' => $maxImps,
                    'playerId' => $finalWinners[0]
                ]
            );
        } else {
            // Still tied, pick first player arbitrarily
            $winner = $finalWinners[0];
            $this->DbQuery("UPDATE player SET player_score = 1 WHERE player_id = $winner");
        }

        $this->gamestate->nextState('endGame');
    }

    /**
     * Helper methods
     */

    private function rollDiceForPlayer($playerId, $count) {
        $faces = DiceFaces::getAllFaces();

        for ($i = 0; $i < $count; $i++) {
            $randomFace = $faces[array_rand($faces)];
            $this->DbQuery("INSERT INTO player_dice (player_id, face, location) VALUES ($playerId, '$randomFace', 'hand')");
        }

        $this->notifyPlayer($playerId, 'diceRolled', '', [
            'dice' => $this->getPlayerDice($playerId)
        ]);
    }

    private function getPlayerDice($playerId) {
        return $this->getCollectionFromDb(
            "SELECT dice_id, face FROM player_dice WHERE player_id = $playerId AND location = 'hand'"
        );
    }

    private function validateActionClaim($action, $playerDice, $playerId) {
        $diceFaces = array_column($playerDice, 'face');

        switch ($action) {
            case Actions::RAISE_HELL:
                return in_array(DiceFaces::FLAME, $diceFaces);
            case Actions::HARVEST_SKULLS:
                return in_array(DiceFaces::SKULL, $diceFaces);
            case Actions::EXTORT:
                return in_array(DiceFaces::TRIDENT, $diceFaces);
            case Actions::REAP_SOUL:
                return in_array(DiceFaces::SCYTHE, $diceFaces);
            case Actions::PENTAGRAM:
                return in_array(DiceFaces::PENTAGRAM, $diceFaces);
            case Actions::IMPS_SET:
                return $this->validateImpsSet($diceFaces);
            case Actions::BLOCK:
                $currentAction = $this->getGameStateValue('current_action');
                if ($currentAction == Actions::EXTORT) {
                    return in_array(DiceFaces::TRIDENT, $diceFaces);
                } else if ($currentAction == Actions::REAP_SOUL) {
                    return in_array(DiceFaces::PENTAGRAM, $diceFaces);
                }
                return false;
        }
        return false;
    }

    private function validateImpsSet($diceFaces) {
        if (empty($diceFaces)) return false;

        // Count non-imp faces
        $nonImpFaces = array_filter($diceFaces, function ($face) {
            return $face !== DiceFaces::IMP;
        });

        // If all are imps, it's valid
        if (empty($nonImpFaces)) return true;

        // If there are non-imp faces, they must all be the same
        $uniqueNonImpFaces = array_unique($nonImpFaces);
        return count($uniqueNonImpFaces) === 1;
    }

    private function stealDiceFromPlayer($stealerId, $victimId) {
        // Get a random die from victim
        $victimDice = $this->getCollectionFromDb(
            "SELECT dice_id FROM player_dice WHERE player_id = $victimId AND location = 'hand' LIMIT 1"
        );

        if (!empty($victimDice)) {
            $diceId = array_keys($victimDice)[0];
            $this->DbQuery("UPDATE player_dice SET player_id = $stealerId WHERE dice_id = $diceId");

            $this->notifyAllPlayers(
                'diceStolen',
                clienttranslate('${stealer} steals a die from ${victim}'),
                [
                    'stealer' => $this->getPlayerName($stealerId),
                    'victim' => $this->getPlayerName($victimId),
                    'stealerId' => $stealerId,
                    'victimId' => $victimId
                ]
            );
        }
    }

    private function moveDiceToSatansPool($playerId) {
        // Get a random die from player
        $playerDice = $this->getCollectionFromDb(
            "SELECT dice_id, face FROM player_dice WHERE player_id = $playerId AND location = 'hand' LIMIT 1"
        );

        if (!empty($playerDice)) {
            $diceData = array_values($playerDice)[0];
            $diceId = $diceData['dice_id'];
            $face = $diceData['face'];

            // Remove from player and add to Satan's pool
            $this->DbQuery("DELETE FROM player_dice WHERE dice_id = $diceId");

            // Roll the die and add to Satan's pool
            $faces = DiceFaces::getAllFaces();
            $newFace = $faces[array_rand($faces)];
            $this->DbQuery("INSERT INTO satans_pool (face) VALUES ('$newFace')");

            $this->notifyAllPlayers(
                'diceToSatansPool',
                clienttranslate('A die from ${player} is rerolled into Satan\'s pool'),
                [
                    'player' => $this->getPlayerName($playerId),
                    'playerId' => $playerId,
                    'face' => $newFace
                ]
            );
        }
    }

    private function executeRaiseHell($playerId) {
        // Add 1 skull token
        $this->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens + 1 WHERE player_id = $playerId");

        // Reroll player's hand
        $this->DbQuery("DELETE FROM player_dice WHERE player_id = $playerId AND location = 'hand'");
        $this->rollDiceForPlayer($playerId, 2);

        $this->notifyAllPlayers(
            'raiseHell',
            clienttranslate('${player} raises hell and rerolls their dice'),
            [
                'player' => $this->getPlayerName($playerId),
                'playerId' => $playerId
            ]
        );
    }

    private function executeHarvestSkulls($playerId) {
        // Add 2 skull tokens
        $this->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens + 2 WHERE player_id = $playerId");

        $this->notifyAllPlayers(
            'harvestSkulls',
            clienttranslate('${player} harvests 2 skull tokens'),
            [
                'player' => $this->getPlayerName($playerId),
                'playerId' => $playerId
            ]
        );
    }

    private function executeExtort($playerId, $targetId) {
        // Transfer 3 skull tokens from target to player
        $targetTokens = $this->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $targetId");
        $tokensToSteal = min(3, $targetTokens);

        $this->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens - $tokensToSteal WHERE player_id = $targetId");
        $this->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens + $tokensToSteal WHERE player_id = $playerId");

        $this->notifyAllPlayers(
            'extort',
            clienttranslate('${player} extorts ${tokens} skull tokens from ${target}'),
            [
                'player' => $this->getPlayerName($playerId),
                'target' => $this->getPlayerName($targetId),
                'playerId' => $playerId,
                'targetId' => $targetId,
                'tokens' => $tokensToSteal
            ]
        );
    }

    private function executeReapSoul($playerId, $targetId) {
        // Pay 2 skull tokens
        $this->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens - 2 WHERE player_id = $playerId");

        // Steal a die from target
        $this->stealDiceFromPlayer($playerId, $targetId);

        $this->notifyAllPlayers(
            'reapSoul',
            clienttranslate('${player} reaps a soul from ${target}'),
            [
                'player' => $this->getPlayerName($playerId),
                'target' => $this->getPlayerName($targetId),
                'playerId' => $playerId,
                'targetId' => $targetId
            ]
        );
    }

    private function executePentagram($playerId) {
        // Reroll all dice in Satan's pool
        $this->DbQuery("DELETE FROM satans_pool");

        $poolDice = $this->getCollectionFromDb("SELECT dice_id FROM satans_pool");
        $faces = DiceFaces::getAllFaces();
        $pentagramsRolled = 0;

        foreach ($poolDice as $diceId => $dice) {
            $newFace = $faces[array_rand($faces)];
            $this->DbQuery("UPDATE satans_pool SET face = '$newFace' WHERE dice_id = $diceId");

            if ($newFace === DiceFaces::PENTAGRAM) {
                $pentagramsRolled++;
            }
        }

        // Gain up to 1 pentagram
        if ($pentagramsRolled > 0) {
            $this->rollDiceForPlayer($playerId, 1);
            // Set the new die to pentagram
            $this->DbQuery("UPDATE player_dice SET face = '" . DiceFaces::PENTAGRAM . "' WHERE player_id = $playerId AND location = 'hand' ORDER BY dice_id DESC LIMIT 1");
        }

        $this->notifyAllPlayers(
            'pentagram',
            clienttranslate('${player} rerolls Satan\'s pool and gains ${pentagrams} pentagram dice'),
            [
                'player' => $this->getPlayerName($playerId),
                'playerId' => $playerId,
                'pentagrams' => min(1, $pentagramsRolled)
            ]
        );
    }

    private function executeImpsSet($playerId) {
        // Gain 1 die from bank
        $this->rollDiceForPlayer($playerId, 1);

        $this->notifyAllPlayers(
            'impsSet',
            clienttranslate('${player} uses Imp\'s Set to gain a die'),
            [
                'player' => $this->getPlayerName($playerId),
                'playerId' => $playerId
            ]
        );
    }

    private function executeSatansSteal($playerId) {
        $actionData = json_decode($this->getGameStateValue('action_data'), true);
        $targetId = $actionData['target'];
        $putInPool = $actionData['putInPool'];
        $poolFace = $actionData['poolFace'];

        // Pay 6 skull tokens
        $this->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens - 6 WHERE player_id = $playerId");

        // Steal a die from target
        $this->stealDiceFromPlayer($playerId, $targetId);

        // Optionally put it in Satan's pool on chosen face
        if ($putInPool && $poolFace) {
            $this->DbQuery("INSERT INTO satans_pool (face) VALUES ('$poolFace')");
        }

        $this->notifyAllPlayers(
            'satansSteal',
            clienttranslate('${player} uses Satan\'s Steal on ${target}'),
            [
                'player' => $this->getPlayerName($playerId),
                'target' => $this->getPlayerName($targetId),
                'playerId' => $playerId,
                'targetId' => $targetId
            ]
        );
    }

    private function checkForWinners() {
        $players = $this->loadPlayersBasicInfos();
        $winners = [];

        foreach ($players as $playerId => $player) {
            if ($this->hasAllSymbols($playerId)) {
                $winners[] = $playerId;
            }
        }

        return $winners;
    }

    private function hasAllSymbols($playerId) {
        // Get player's dice
        $playerDice = $this->getCollectionFromDb(
            "SELECT DISTINCT face FROM player_dice WHERE player_id = $playerId AND location = 'hand'"
        );

        // Get Satan's pool dice
        $poolDice = $this->getCollectionFromDb("SELECT DISTINCT face FROM satans_pool");

        // Combine all available faces
        $allFaces = array_merge(array_column($playerDice, 'face'), array_column($poolDice, 'face'));
        $uniqueFaces = array_unique($allFaces);

        // Check if all 6 symbols are present
        $requiredFaces = DiceFaces::getAllFaces();
        return count(array_intersect($uniqueFaces, $requiredFaces)) === 6;
    }

    private function countImpsForPlayer($playerId) {
        $playerDice = $this->getCollectionFromDb(
            "SELECT face FROM player_dice WHERE player_id = $playerId AND location = 'hand'"
        );

        $poolDice = $this->getCollectionFromDb("SELECT face FROM satans_pool");

        $allDice = array_merge(array_column($playerDice, 'face'), array_column($poolDice, 'face'));

        return count(array_filter($allDice, function ($face) {
            return $face === DiceFaces::IMP;
        }));
    }

    /**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`, otherwise it will fail with a
     * "Not logged" error message.
     *
     * @param array{ type: string, name: string } $state
     * @param int $active_player
     * @return void
     * @throws feException if the zombie mode is not supported at this game state.
     */
    protected function zombieTurn(array $state, int $activePlayer): void {
        $stateName = $state["name"];

        if ($state["type"] === "activeplayer") {
            switch ($stateName) {
                default: {
                        $this->gamestate->nextState("zombiePass");
                        break;
                    }
            }

            return;
        }

        throw new \feException("Zombie mode not supported at this game state: \"{$stateName}\".");
    }

    /**
     * Migrate database.
     *
     * You don't have to care about this until your game has been published on BGA. Once your game is on BGA, this
     * method is called everytime the system detects a game running with your old database scheme. In this case, if you
     * change your database scheme, you just have to apply the needed changes in order to update the game database and
     * allow the game to continue to run with your new version.
     *
     * @param int $from_version
     * @return void
     */
    public function upgradeTableDb($from_version) {
    }

    /*
     * Gather all information about current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, i.e.:
     *
     * - when the game starts
     * - when a player refreshes the game page (F5)
     */
    protected function getAllDatas() {
        $result = [];

        // WARNING: We must only return information visible by the current player.
        $currentPlayerId = intval($this->getCurrentPlayerId());

        // Get information about players.
        $result['players'] = $this->getCollectionFromDb(
            "SELECT player_id as id, player_score as score, player_color as color FROM player"
        );

        // Get player tokens (public information)
        $result['playerTokens'] = $this->getCollectionFromDb(
            "SELECT player_id, skull_tokens FROM player_tokens"
        );

        // Get player dice counts (public information - not the faces)
        $result['playerDiceCounts'] = $this->getCollectionFromDb(
            "SELECT player_id, COUNT(*) as dice_count FROM player_dice WHERE location = 'hand' GROUP BY player_id"
        );

        // Get current player's dice (private information)
        $result['myDice'] = $this->getCollectionFromDb(
            "SELECT dice_id, face FROM player_dice WHERE player_id = $currentPlayerId AND location = 'hand'"
        );

        // Get Satan's pool dice (public information)
        $result['satansPool'] = $this->getCollectionFromDb(
            "SELECT dice_id, face FROM satans_pool"
        );

        // Get current game state information
        $result['currentAction'] = $this->getGameStateValue('current_action');
        $result['currentActionPlayer'] = $this->getGameStateValue('current_action_player');
        $result['currentTargetPlayer'] = $this->getGameStateValue('current_target_player');

        return $result;
    }

    /**
     * Returns the game name.
     *
     * IMPORTANT: Please do not modify.
     */
    protected function getGameName() {
        return "devilsdice";
    }

    /**
     * This method is called only once, when a new game is launched. In this method, you must setup the game
     *  according to the game rules, so that the game is ready to be played.
     */
    protected function setupNewGame($players, $options = []) {
        // Set the colors of the players with HTML color code. The default below is red/green/blue/orange/brown. The
        // number of colors defined here must correspond to the maximum number of players allowed for the gams.
        $gameinfos = $this->getGameinfos();
        $defaultColors = array("6f1926", "de324c", "f4895f", "f8e16f", "95cf92", "369acc", "9656a2", "cbabd1");
        shuffle($defaultColors);

        foreach ($players as $playerId => $player) {
            $color = array_shift($defaultColors);
            // Now you can access both $playerId and $player array
            $queryValues[] = vsprintf("('%s', '%s', '%s', '%s', '%s')", [
                $playerId,
                $color,
                $player["player_canal"],
                addslashes($player["player_name"]),
                addslashes($player["player_avatar"]),
            ]);
        }

        // Create players based on generic information.
        //
        // NOTE: You can add extra field on player table in the database (see dbmodel.sql) and initialize
        // additional fields directly here.
        $this->DbQuery(
            sprintf(
                "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES %s",
                implode(",", $queryValues)
            )
        );

        $this->reattributeColorsBasedOnPreferences($players, $gameinfos["player_colors"]);

        $this->reloadPlayersBasicInfos();

        // Init global values with their initial values.

        // Init game statistics.
        //
        // NOTE: statistics used in this file must be defined in your `stats.inc.php` file.

        // Activate first player once everything has been initialized and ready.
        $this->activeNextPlayer();
    }
}
