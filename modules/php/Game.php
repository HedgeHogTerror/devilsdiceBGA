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
require_once("Constants.inc.php");

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
            "action_data" => 14,
            "dice_overflow_player" => 15,
            "dice_overflow_count" => 16
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
    private const STARTING_SKULL_TOKENS = 6;
    private const STARTING_DICE = 2;

    public function argChallengeWindow() {
        return [
            'currentActionPlayer' => $this->getGameStateValue('current_action_player'),
            'currentAction' => $this->getGameStateValue('current_action'),
            'currentTargetPlayer' => $this->getGameStateValue('current_target_player')
        ];
    }


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

        // Check if player has 1 or 0 dice - if so, skip challenge window
        $diceCount = intval($this->getUniqueValueFromDB("SELECT COUNT(*) FROM player_dice WHERE player_id = $playerId AND location = 'hand'"));

        if ($diceCount <= 1) {
            // Skip challenge window and go directly to resolution
            $this->gamestate->nextState('resolveAction');
        } else {
            $this->gamestate->nextState('challengeWindow');
        }
    }

    public function satansSteal($targetPlayerId, $putInPool = false, $poolFace = null) {
        $this->checkAction('satansSteal');
        $playerId = intval($this->getCurrentPlayerId());

        // Check if player has enough skull tokens
        $tokens = $this->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $playerId");
        if ($tokens < 6) {
            throw new \BgaUserException("You need 6 skull tokens to use Satan's Steal");
        }

        $this->setGameStateValue('current_action', Actions::SATANS_STEAL);
        $this->setGameStateValue('current_action_player', $playerId);
        $this->setGameStateValue('current_target_player', $targetPlayerId);

        // Store the decision parameters
        $actionData = json_encode([
            'putInPool' => $putInPool,
            'poolFace' => $poolFace
        ]);
        $this->setGameStateValue('action_data', (string)$actionData);

        // Satan's Steal cannot be challenged, go directly to resolution
        $this->gamestate->nextState('resolveAction');
    }

    public function stChooseDiceOverflowFace() {
        // State method - called when entering chooseDiceOverflowFace state
        $overflowPlayer = $this->getGameStateValue('dice_overflow_player');
        $this->debug("DevilsDice stChooseDiceOverflowFace: overflow player is $overflowPlayer");

        // The active player is automatically set by the framework to the overflow player
        // since this is an activeplayer state and we're transitioning from a game state
        $this->gamestate->changeActivePlayer($overflowPlayer);
    }

    public function chooseDiceOverflowFace($face) {
        $this->checkAction('chooseDiceOverflowFace');
        $playerId = intval($this->getCurrentPlayerId());

        // Validate the face
        $validFaces = DiceFaces::getAllFaces();
        if (!in_array($face, $validFaces)) {
            throw new \BgaUserException("Invalid dice face");
        }

        // Get the overflow info
        $overflowPlayer = $this->getGameStateValue('dice_overflow_player');
        $overflowCount = $this->getGameStateValue('dice_overflow_count');

        // Verify this is the correct player
        if ($playerId != $overflowPlayer) {
            throw new \BgaUserException("Only the player who caused the overflow can choose the face");
        }

        // Add the dice to Satan's pool instead
        $this->DbQuery("INSERT INTO satans_pool (face) VALUES ('$face')");

        // Clear overflow flags
        $this->setGameStateValue('dice_overflow_player', 0);
        $this->setGameStateValue('dice_overflow_count', 0);

        // Send notification
        $this->notifyAllPlayers(
            'diceToSatansPool',
            clienttranslate('${player} chooses ${face} to place in Satan\'s pool instead of gaining a die'),
            [
                'player' => $this->getPlayerNameById($playerId),
                'playerId' => $playerId,
                'face' => $face,
                'dice' => $this->getPlayerDice($playerId),
                'diceCount' => intval($this->getUniqueValueFromDB("SELECT COUNT(dice_id) FROM player_dice WHERE player_id = $playerId AND location = 'hand'"))
            ]
        );

        $this->gamestate->nextState('checkWin');
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
        $playerId = intval($this->getCurrentPlayerId());

        // Player passes on challenge or block opportunity
        $stateName = $this->gamestate->state()['name'];

        if ($stateName === 'challengeWindow') {
            // In multipleactiveplayer state, just make this player inactive
            // The BGA framework will automatically transition when all players have acted
            $this->gamestate->setPlayerNonMultiactive($playerId, 'resolveAction');
        } else if ($stateName === 'blockWindow') {
            // Single player block window - can transition immediately
            $this->gamestate->nextState('resolveAction');
        }
    }

    /**
     * Game state actions
     */

    public function stGameSetup() {
        $this->debug("DevilsDice: Starting game setup");

        // Initialize player tokens first
        $players = $this->loadPlayersBasicInfos();
        $this->debug("DevilsDice: Found " . count($players) . " players");

        foreach ($players as $playerId => $player) {
            $this->DbQuery("INSERT INTO player_tokens (player_id, skull_tokens) VALUES ($playerId, " . self::STARTING_SKULL_TOKENS . ")");
            $this->debug("DevilsDice: Created tokens for player $playerId");
        }

        // Then give each player 2 starting dice with full notification
        foreach ($players as $playerId => $player) {
            $this->debug("DevilsDice: Rolling dice for player $playerId");
            $this->addOrRemoveDice($playerId, self::STARTING_DICE);
        }

        // Send a final notification to refresh all game data
        $this->notifyAllPlayers('gameSetupComplete', '', []);
        $this->debug("DevilsDice: Game setup completed");

        $this->gamestate->nextState('playerTurn');
    }

    public function stChallengeWindow() {
        $actionPlayerId = $this->getGameStateValue('current_action_player');
        $currentAction = $this->getGameStateValue('current_action');

        $this->debug("DevilsDice stChallengeWindow: actionPlayerId = $actionPlayerId (type: " . gettype($actionPlayerId) . ")");

        // Activate all players except the action player for potential challenges
        $players = $this->loadPlayersBasicInfos();
        $challengePlayers = [];
        foreach ($players as $playerId => $player) {
            $this->debug("DevilsDice stChallengeWindow: Checking player $playerId (type: " . gettype($playerId) . ") vs actionPlayer $actionPlayerId");

            // Use strict comparison with type casting to ensure proper comparison
            // Also add string comparison as additional safety check
            if (intval($playerId) !== intval($actionPlayerId) && strval($playerId) !== strval($actionPlayerId)) {
                $challengePlayers[] = strval($playerId);
                $this->debug("DevilsDice stChallengeWindow: Added player $playerId to challenge list");
            } else {
                $this->debug("DevilsDice stChallengeWindow: Excluded action player $playerId from challenge list");
            }
        }

        $this->debug("DevilsDice stChallengeWindow: Final challenge players: " . json_encode($challengePlayers));

        // Safety check: ensure action player is not in the challenge list
        $actionPlayerIdStr = strval($actionPlayerId);
        $challengePlayers = array_filter($challengePlayers, function ($playerId) use ($actionPlayerIdStr) {
            return strval($playerId) !== $actionPlayerIdStr;
        });

        $this->debug("DevilsDice stChallengeWindow: After safety filter: " . json_encode($challengePlayers));

        // Determine the next state based on the action type
        $nextState = 'resolveAction';
        if (in_array($currentAction, [Actions::EXTORT, Actions::REAP_SOUL])) {
            $nextState = 'blockWindow';
        }

        $this->gamestate->setPlayersMultiactive($challengePlayers, $nextState);
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
            // Note: stealDiceFromPlayer already rerolls both players' dice

            $this->notifyAllPlayers(
                'challengeSuccessful',
                clienttranslate('${challenger} successfully challenges ${player}\'s claim - both players reroll their dice'),
                [
                    'challenger' => $this->getPlayerNameById($challengerId),
                    'player' => $this->getPlayerNameById($actionPlayerId),
                    'challengerId' => $challengerId,
                    'actionPlayerId' => $actionPlayerId
                ]
            );

            // Challenge successful - challenger steals dice, action cancelled
            $this->stealDiceFromPlayer($challengerId, $actionPlayerId);

            // Skip the challenged player's turn and move to next player
            $this->clearActionState();
            $this->activeNextPlayer();
            $this->gamestate->nextState('playerTurn');
        } else {
            // Challenge failed - challenger loses dice to Satan's pool
            $this->notifyAllPlayers(
                'challengeFailed',
                clienttranslate('${challenger}\'s challenge fails against ${player}'),
                [
                    'challenger' => $this->getPlayerNameById($challengerId),
                    'player' => $this->getPlayerNameById($actionPlayerId),
                    'challengerId' => $challengerId,
                    'actionPlayerId' => $actionPlayerId
                ]
            );

            $this->moveDiceToSatansPool($challengerId);

            // Continue with original action
            if (in_array($currentAction, [Actions::EXTORT, Actions::REAP_SOUL])) {
                $this->gamestate->nextState('blockWindow');
            } else {
                $this->gamestate->nextState('resolveAction');
            }
        }
    }

    public function stResolveAction() {
        $this->debug("DevilsDice stResolveAction: executing action resolution");

        $actionPlayerId = $this->getGameStateValue('current_action_player');
        $currentAction = $this->getGameStateValue('current_action');
        $targetPlayerId = $this->getGameStateValue('current_target_player');

        $actionName = Actions::getActionName($currentAction);
        $this->debug("DevilsDice: Resolving action - Player: $actionPlayerId, Action: $currentAction ($actionName), Target: $targetPlayerId");

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
            default:
                $this->debug("DevilsDice: Unknown action: $currentAction ($actionName) (type: " . gettype($currentAction) . ")");
                break;
        }

        // Check if there was a dice overflow during action execution
        $overflowPlayer = $this->getGameStateValue('dice_overflow_player');
        $this->debug("DevilsDice stResolveAction: overflow check - overflowPlayer = $overflowPlayer");

        if ($overflowPlayer > 0) {
            // There was an overflow, transition to dice overflow choice
            $this->debug("DevilsDice stResolveAction: transitioning to chooseDiceOverflowFace");
            $this->gamestate->nextState('chooseDiceOverflowFace');
        } else {
            // Normal flow, continue to check win
            $this->debug("DevilsDice stResolveAction: transitioning to checkWin");
            $this->gamestate->nextState('checkWin');
        }
    }

    public function stCheckWin() {
        $winners = $this->checkForWinners();

        if (count($winners) === 1) {
            // Single winner
            $winnerId = $winners[0];
            $this->DbQuery("UPDATE player SET player_score = 1 WHERE player_id = " . $winnerId);

            // Send win notification with confetti animation
            $this->notifyAllPlayers(
                'gameWon',
                clienttranslate('${player} wins the game!'),
                [
                    'player' => $this->getPlayerNameById($winnerId),
                    'winnerId' => strval($winnerId)
                ]
            );

            $this->gamestate->nextState('endGame');
        } else if (count($winners) > 1) {
            // Tie - need rolloff
            $this->setGameStateValue('action_data', (string)json_encode($winners));
            $this->gamestate->nextState('rolloff');
        } else {
            // No winner yet, continue game
            $this->clearActionState();
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
            $winnerId = $finalWinners[0];
            $this->DbQuery("UPDATE player SET player_score = 1 WHERE player_id = " . $winnerId);
            $this->notifyAllPlayers(
                'rolloffWinner',
                clienttranslate('${player} wins the rolloff with ${imps} imps'),
                [
                    'player' => $this->getPlayerNameById($winnerId),
                    'imps' => $maxImps,
                    'playerId' => $winnerId
                ]
            );

            // Send win notification with confetti animation
            $this->notifyAllPlayers(
                'gameWon',
                clienttranslate('${player} wins the game!'),
                [
                    'player' => $this->getPlayerNameById($winnerId),
                    'winnerId' => strval($winnerId)
                ]
            );
        } else {
            // Still tied, pick first player arbitrarily
            $winner = $finalWinners[0];
            $this->DbQuery("UPDATE player SET player_score = 1 WHERE player_id = $winner");

            // Send win notification with confetti animation
            $this->notifyAllPlayers(
                'gameWon',
                clienttranslate('${player} wins the game!'),
                [
                    'player' => $this->getPlayerNameById($winner),
                    'winnerId' => strval($winner)
                ]
            );
        }

        $this->gamestate->nextState('endGame');
    }

    /**
     * Helper methods
     */

    private function clearActionState() {
        // Clear action-related game state values when starting a new turn
        $this->setGameStateValue('current_action', 0);
        $this->setGameStateValue('current_action_player', 0);
        $this->setGameStateValue('current_target_player', 0);
        $this->setGameStateValue('challenge_player', 0);
        $this->setGameStateValue('action_data', '');
    }

    /**
     * Reroll all existing dice for a player and send notification
     */
    private function rollDiceForPlayer($playerId) {
        $currentCount = intval($this->getUniqueValueFromDB("SELECT COUNT(*) FROM player_dice WHERE player_id = $playerId AND location = 'hand'"));

        if ($currentCount > 0) {
            // Delete existing dice
            $this->DbQuery("DELETE FROM player_dice WHERE player_id = $playerId AND location = 'hand'");

            // Roll new dice with new faces
            $faces = DiceFaces::getAllFaces();
            for ($i = 0; $i < $currentCount; $i++) {
                $randomFace = $faces[array_rand($faces)];
                $this->DbQuery("INSERT INTO player_dice (player_id, face, location) VALUES ($playerId, '$randomFace', 'hand')");
            }
        }

        // Get the new dice data and send notification
        $playerDice = $this->getPlayerDice($playerId);
        $this->debug("DevilsDice rollDiceForPlayer: Player $playerId rerolled $currentCount dice");

        $this->notifyAllPlayers('diceRerolled', '', [
            'playerId' => $playerId,
            'dice' => $playerDice,
            'diceCount' => $currentCount
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

    /**
     * Add or remove dice from a player and send notification
     */
    private function addOrRemoveDice($playerId, $count) {
        $current = intval($this->getUniqueValueFromDB("SELECT COUNT(dice_id) FROM player_dice WHERE player_id = $playerId AND location = 'hand'"));

        // Check for dice overflow when adding dice
        if ($count > 0 && $current >= 6) {
            // Player already has 6 dice, set flag for overflow handling
            $this->setGameStateValue('dice_overflow_player', $playerId);
            $this->setGameStateValue('dice_overflow_count', $count);
            $this->debug("DevilsDice addOrRemoveDice: Player $playerId at dice limit, setting overflow flag");
            return; // Don't add dice to player, let stResolveAction handle overflow
        }

        $newCount = max(0, $current + $count); // Ensure we don't go below 0

        // Delete existing dice
        if ($current > 0) {
            $this->DbQuery("DELETE FROM player_dice WHERE player_id = $playerId AND location = 'hand'");
        }

        // Create new dice with random faces
        $faces = DiceFaces::getAllFaces();
        for ($i = 0; $i < $newCount; $i++) {
            $randomFace = $faces[array_rand($faces)];
            $this->DbQuery("INSERT INTO player_dice (player_id, face, location) VALUES ($playerId, '$randomFace', 'hand')");
        }

        $this->debug("DevilsDice addOrRemoveDice: Player $playerId now has $newCount dice (was $current, changed by $count)");

        // Send notification
        $this->rollDiceForPlayer($playerId);
    }

    private function stealDiceFromPlayer($stealerId, $victimId) {
        // Check if stealer would overflow before stealing
        $stealerCurrent = intval($this->getUniqueValueFromDB("SELECT COUNT(dice_id) FROM player_dice WHERE player_id = $stealerId AND location = 'hand'"));

        if ($stealerCurrent >= 6) {
            // Stealer would overflow, set flags and only remove from victim
            $this->setGameStateValue('dice_overflow_player', $stealerId);
            $this->setGameStateValue('dice_overflow_count', 1);
            $this->debug("DevilsDice stealDiceFromPlayer: Stealer $stealerId at dice limit, setting overflow flag");
            $this->addOrRemoveDice($victimId, -1); // Remove one die from victim
        } else {
            // Normal steal - add to stealer, remove from victim
            $this->addOrRemoveDice($stealerId, 1); // Add one die to stealer
            $this->addOrRemoveDice($victimId, -1); // Remove one die from victim
        }

        $this->notifyAllPlayers(
            'diceStolen',
            clienttranslate('${stealer} steals a die from ${victim}'),
            [
                'stealer' => $this->getPlayerNameById($stealerId),
                'victim' => $this->getPlayerNameById($victimId),
                'stealerId' => $stealerId,
                'victimId' => $victimId
            ]
        );

        // Reroll dice for both players - let frontend handle animation sequencing
        $this->rollDiceForPlayer($stealerId);
        $this->rollDiceForPlayer($victimId);
    }

    private function moveDiceToSatansPool($playerId, $face = null) {
        $finalFace = null;
        if ($face === null) {
            // Roll the die and add to Satan's pool
            $faces = DiceFaces::getAllFaces();
            $finalFace = $faces[array_rand($faces)];
            $this->DbQuery("INSERT INTO satans_pool (face) VALUES ('$finalFace')");
        } else {
            // Use the provided face
            $finalFace = $face;
            $this->DbQuery("INSERT INTO satans_pool (face) VALUES ('$face')");
        }
        $this->addOrRemoveDice($playerId, -1); // Remove one die from player

        $this->notifyAllPlayers(
            'diceToSatansPool',
            clienttranslate('A die from ${player} is rolled into Satan\'s pool'),
            [
                'player' => $this->getPlayerNameById($playerId),
                'playerId' => $playerId,
                'face' => $finalFace,
            ]
        );
    }

    private function executeRaiseHell($playerId) {
        // Add 1 skull token
        $this->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens + 1 WHERE player_id = $playerId");

        // Get updated token count
        $newTokenCount = $this->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $playerId");

        $this->notifyAllPlayers(
            'raiseHell',
            clienttranslate('${player} raises hell and rerolls their dice'),
            [
                'player' => $this->getPlayerNameById($playerId),
                'playerId' => $playerId,
                'tokens' => $newTokenCount,
                'diceCount' => $this->getUniqueValueFromDB("SELECT COUNT(*) FROM player_dice WHERE player_id = $playerId AND location = 'hand'")
            ]
        );

        // Reroll all of player's current dice
        $this->rollDiceForPlayer($playerId);
    }

    private function executeHarvestSkulls($playerId) {
        // Add 2 skull tokens
        $this->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens + 2 WHERE player_id = $playerId");

        // Get updated token count
        $newTokenCount = $this->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $playerId");

        $this->notifyAllPlayers(
            'harvestSkulls',
            clienttranslate('${player} harvests 2 skull tokens'),
            [
                'player' => $this->getPlayerNameById($playerId),
                'playerId' => $playerId,
                'tokens' => $newTokenCount
            ]
        );
    }

    private function executeExtort($playerId, $targetId) {
        // Transfer 3 skull tokens from target to player
        $targetTokens = $this->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $targetId");
        $tokensToSteal = min(3, $targetTokens);

        $this->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens - $tokensToSteal WHERE player_id = $targetId");
        $this->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens + $tokensToSteal WHERE player_id = $playerId");

        // Get updated token counts for both players
        $playerNewTokens = $this->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $playerId");
        $targetNewTokens = $this->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $targetId");

        $this->notifyAllPlayers(
            'extort',
            clienttranslate('${player} extorts ${tokens} skull tokens from ${target}'),
            [
                'player' => $this->getPlayerNameById($playerId),
                'target' => $this->getPlayerNameById($targetId),
                'playerId' => $playerId,
                'targetId' => $targetId,
                'tokens' => $tokensToSteal,
                'playerNewTokens' => $playerNewTokens,
                'targetNewTokens' => $targetNewTokens
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
                'player' => $this->getPlayerNameById($playerId),
                'target' => $this->getPlayerNameById($targetId),
                'playerId' => $playerId,
                'targetId' => $targetId
            ]
        );
    }

    private function executePentagram($playerId) {
        // Get existing dice in Satan's pool before rerolling
        $poolDice = $this->getCollectionFromDb("SELECT dice_id FROM satans_pool");
        $faces = DiceFaces::getAllFaces();
        $pentagramsRolled = 0;
        $pentagramDiceIds = [];

        // Reroll each die in Satan's pool
        foreach ($poolDice as $diceId => $dice) {
            $newFace = $faces[array_rand($faces)];
            $this->DbQuery("UPDATE satans_pool SET face = '$newFace' WHERE dice_id = $diceId");

            if ($newFace === DiceFaces::PENTAGRAM) {
                $pentagramsRolled++;
                $pentagramDiceIds[] = $diceId;
            }
        }

        // Remove pentagram dice from Satan's pool (player gains up to 1)
        if ($pentagramsRolled > 0) {
            // Remove the first pentagram die from Satan's pool
            $diceIdToRemove = $pentagramDiceIds[0];
            $this->DbQuery("DELETE FROM satans_pool WHERE dice_id = $diceIdToRemove");

            // Add 1 die to player with pentagram face (check for overflow)
            $this->addOrRemoveDice($playerId, 1);

            // Set the new die to pentagram
            $this->DbQuery("UPDATE player_dice SET face = '" . DiceFaces::PENTAGRAM . "' WHERE player_id = $playerId AND location = 'hand' ORDER BY dice_id DESC LIMIT 1");
        }

        // Get the updated Satan's pool data for the notification (after removing pentagram)
        $updatedSatansPool = $this->getCollectionFromDb("SELECT dice_id, face FROM satans_pool");

        // Send notification
        if ($pentagramsRolled > 0) {
            $this->notifyAllPlayers(
                'pentagram',
                clienttranslate('${player} rerolls Satan\'s pool and gains one pentagram dice'),
                [
                    'player' => $this->getPlayerNameById($playerId),
                    'playerId' => $playerId,
                    'pentagrams' => 1,
                    'satansPool' => $updatedSatansPool
                ]
            );
        } else {
            $this->notifyAllPlayers(
                'pentagram',
                clienttranslate('${player} rerolls Satan\'s pool and did not roll any pentagrams'),
                [
                    'player' => $this->getPlayerNameById($playerId),
                    'playerId' => $playerId,
                    'pentagrams' => 0,
                    'satansPool' => $updatedSatansPool
                ]
            );
        }
    }

    private function executeImpsSet($playerId) {
        $this->notifyAllPlayers(
            'impsSet',
            clienttranslate('${player} uses Imp\'s Set to gain a die and rerolls all dice'),
            [
                'player' => $this->getPlayerNameById($playerId),
                'playerId' => $playerId
            ]
        );

        // Add 1 die and reroll all dice (check for overflow)
        $this->addOrRemoveDice($playerId, 1);
    }

    private function executeSatansSteal($playerId) {
        $targetId = $this->getGameStateValue('current_target_player');

        // Check if we have action data (from decision state)
        $actionDataJson = $this->getGameStateValue('action_data');
        $putInPool = false;
        $poolFace = null;

        if ($actionDataJson && is_string($actionDataJson)) {
            $actionData = json_decode($actionDataJson, true);
            $putInPool = $actionData['putInPool'] ?? false;
            $poolFace = $actionData['poolFace'] ?? null;
        }

        // Pay 6 skull tokens
        $this->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens - 6 WHERE player_id = $playerId");

        // Get updated token count for the player
        $playerNewTokens = $this->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $playerId");

        // Optionally put it in Satan's pool on chosen face
        if ($putInPool && $poolFace) {
            $this->moveDiceToSatansPool($targetId, $poolFace);
        } else {
            // Steal a die from target (correct parameter order: stealer, victim)
            $this->stealDiceFromPlayer($playerId, $targetId);
        }

        $this->notifyAllPlayers(
            'satansSteal',
            clienttranslate('${player} uses Satan\'s Steal on ${target}'),
            [
                'player' => $this->getPlayerNameById($playerId),
                'target' => $this->getPlayerNameById($targetId),
                'playerId' => $playerId,
                'targetId' => $targetId,
                'playerNewTokens' => $playerNewTokens
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
        $this->debug("DevilsDice getAllDatas: Getting data for player $currentPlayerId");

        // Get information about players.
        $result['players'] = $this->getCollectionFromDb(
            "SELECT player_id as id, player_score as score, player_color as color FROM player"
        );

        // Get player tokens (public information)
        $result['playerTokens'] = $this->getCollectionFromDb(
            "SELECT player_id, skull_tokens FROM player_tokens"
        );
        $this->debug("DevilsDice getAllDatas: playerTokens = " . json_encode($result['playerTokens']));

        // Get player dice counts (public information - not the faces)
        $result['playerDiceCounts'] = $this->getCollectionFromDb(
            "SELECT p.player_id, COALESCE(COUNT(pd.dice_id), 0) as dice_count 
             FROM player p 
             LEFT JOIN player_dice pd ON p.player_id = pd.player_id AND pd.location = 'hand' 
             GROUP BY p.player_id"
        );
        $this->debug("DevilsDice getAllDatas: playerDiceCounts = " . json_encode($result['playerDiceCounts']));

        // Debug: Check if any dice exist at all
        $totalDice = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM player_dice");
        $this->debug("DevilsDice getAllDatas: Total dice in database = " . $totalDice);

        // Debug: Check if any tokens exist
        $totalTokens = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM player_tokens");
        $this->debug("DevilsDice getAllDatas: Total token records = " . $totalTokens);

        // Get current player's dice (private information)
        $result['myDice'] = $this->getCollectionFromDb(
            "SELECT dice_id, face FROM player_dice WHERE player_id = $currentPlayerId AND location = 'hand'"
        );
        $this->debug("DevilsDice getAllDatas: myDice = " . json_encode($result['myDice']));

        // Get Satan's pool dice (public information)
        $result['satansPool'] = $this->getCollectionFromDb(
            "SELECT dice_id, face FROM satans_pool"
        );

        // Get current game state information
        $result['currentAction'] = $this->getGameStateValue('current_action');
        $result['currentActionPlayer'] = $this->getGameStateValue('current_action_player');
        $result['currentTargetPlayer'] = $this->getGameStateValue('current_target_player');

        $this->debug("DevilsDice getAllDatas: Final result = " . json_encode($result));
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
        $this->debug("DevilsDice: setupNewGame called with " . count($players) . " players");

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

        // Initialize game-specific data (tokens and dice)
        $this->debug("DevilsDice: Initializing game data");

        // Initialize player tokens
        foreach ($players as $playerId => $player) {
            $this->DbQuery("INSERT INTO player_tokens (player_id, skull_tokens) VALUES ($playerId, " . self::STARTING_SKULL_TOKENS . ")");
            $this->debug("DevilsDice: Created tokens for player $playerId");
        }

        // Give each player 2 starting dice
        foreach ($players as $playerId => $player) {
            $this->debug("DevilsDice: Rolling dice for player $playerId");
            $this->addOrRemoveDice($playerId, self::STARTING_DICE); // Don't notify during setup
        }

        // Init global values with their initial values.

        // Init game statistics.
        //
        // NOTE: statistics used in this file must be defined in your `stats.inc.php` file.

        // Activate first player once everything has been initialized and ready.
        $this->activeNextPlayer();

        $this->debug("DevilsDice: setupNewGame completed");
    }
}
