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
require_once(__DIR__ . "/DiceValidator.php");
require_once(__DIR__ . "/GameValidator.php");
require_once(__DIR__ . "/TokenManager.php");
require_once(__DIR__ . "/DiceManager.php");
require_once(__DIR__ . "/ActionExecutor.php");

class Game extends \Table {
    /**
     * Your global variables labels:
     *
     * Here, you can assign labels to global variables you are using for this game. You can use any number of global
     * variables with IDs between 10 and 99. If your game has options (variants), you also have to associate here a
     * label to the corresponding ID in `gameoptions.inc.php`.
     *
     * NOTE: afterward, you can get/set the global variables with `getGameStateValue`, `setGameStateInitialValue` or
     * `setGameStateValue` functions
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
        if (!TokenManager::hasEnoughSkullTokens($this, $playerId, 2)) {
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
        if (!TokenManager::hasEnoughSkullTokens($this, $playerId, 6)) {
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
                'dice' => DiceManager::getPlayerDice($this, $playerId),
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

        // DEBUG: Log current state and player info
        $currentState = $this->gamestate->state();
        $stateId = isset($currentState['id']) ? $currentState['id'] : 'unknown';
        $stateName = isset($currentState['name']) ? $currentState['name'] : 'unknown';
        $this->debug("DevilsDice pass(): Player $playerId passing in state $stateId ($stateName)");

        // Player passes on challenge or block opportunity
        // $stateName already set above with safety check

        if ($stateName === 'challengeWindow') {
            // In multipleactiveplayer state, just make this player inactive
            // The BGA framework will automatically transition when all players have acted
            $this->debug("DevilsDice pass(): Setting player $playerId non-multiactive, will transition to resolveAction");
            $this->gamestate->setPlayerNonMultiactive($playerId, 'resolveAction');
            $this->debug("DevilsDice pass(): Player $playerId set non-multiactive");
        } else if ($stateName === 'blockWindow') {
            // Single player block window - can transition immediately
            $this->debug("DevilsDice pass(): Player $playerId not blocking, transitioning to resolveAction");
            $this->gamestate->nextState('resolveAction');
            $this->debug("DevilsDice pass(): Transition to resolveAction completed");
        } else {
            $this->debug("DevilsDice pass(): Cannot pass in current state: $stateName");
            throw new \BgaUserException("Cannot pass in current state: $stateName");
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

        $this->debug("DevilsDice stChallengeWindow: Setting players multiactive with next state: $nextState");
        $this->gamestate->setPlayersMultiactive($challengePlayers, $nextState);
        $this->debug("DevilsDice stChallengeWindow: setPlayersMultiactive completed");
    }

    public function stResolveChallenge() {
        $challengerId = $this->getGameStateValue('challenge_player');
        $actionPlayerId = $this->getGameStateValue('current_action_player');
        $currentAction = $this->getGameStateValue('current_action');

        // Get the challenged player's dice
        $playerDice = $this->getCollectionFromDb(
            "SELECT face FROM player_dice WHERE player_id = $actionPlayerId AND location = 'hand'"
        );

        $challengeSuccessful = !DiceValidator::validateActionClaim($currentAction, array_column($playerDice, 'face'), $this);

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
                ActionExecutor::executeRaiseHell($this, $actionPlayerId);
                break;
            case Actions::HARVEST_SKULLS:
                ActionExecutor::executeHarvestSkulls($this, $actionPlayerId);
                break;
            case Actions::EXTORT:
                ActionExecutor::executeExtort($this, $actionPlayerId, $targetPlayerId);
                break;
            case Actions::REAP_SOUL:
                ActionExecutor::executeReapSoul($this, $actionPlayerId, $targetPlayerId);
                break;
            case Actions::PENTAGRAM:
                ActionExecutor::executePentagram($this, $actionPlayerId);
                break;
            case Actions::IMPS_SET:
                ActionExecutor::executeImpsSet($this, $actionPlayerId);
                break;
            case Actions::SATANS_STEAL:
                ActionExecutor::executeSatansSteal($this, $actionPlayerId);
                break;
            default:
                $this->debug("DevilsDice: Unknown action: $currentAction ($actionName) (type: " . gettype($currentAction) . ")");
                break;
        }

        // DEBUG: Log current state before transition logic
        $currentState = $this->gamestate->state();
        $stateId = isset($currentState['id']) ? $currentState['id'] : 'unknown';
        $stateName = isset($currentState['name']) ? $currentState['name'] : 'unknown';
        $stateType = isset($currentState['type']) ? $currentState['type'] : 'unknown';
        $this->debug("DevilsDice stResolveAction: Current state info - ID: $stateId, Name: $stateName, Type: $stateType");

        // Always transition to checkWin - overflow detection will happen there
        $this->debug("DevilsDice stResolveAction: transitioning to checkWin");
        $this->gamestate->nextState('checkWin');
    }

    public function stCheckWin() {
        // First, check for pending overflow from action execution
        $actionDataJson = $this->getGameStateValue('action_data');
        $actionData = $actionDataJson ? json_decode((string)$actionDataJson, true) : [];
        $this->debug("DevilsDice stCheckWin: Checking for pending overflow - Action data: " . ($actionDataJson ? $actionDataJson : 'empty'));

        if (isset($actionData['pending_overflow'])) {
            // There was a pending overflow, set flags and transition to overflow choice
            $pendingOverflow = $actionData['pending_overflow'];
            $this->debug("DevilsDice stCheckWin: Found pending overflow - Player: " . $pendingOverflow['player'] . ", Count: " . $pendingOverflow['count']);

            $this->setGameStateValue('dice_overflow_player', $pendingOverflow['player']);
            $this->setGameStateValue('dice_overflow_count', $pendingOverflow['count']);

            // Clear pending overflow from action data
            unset($actionData['pending_overflow']);
            $this->setGameStateValue('action_data', json_encode($actionData));

            $this->debug("DevilsDice stCheckWin: Setting active player to " . $pendingOverflow['player']);
            $this->gamestate->changeActivePlayer($pendingOverflow['player']);
            $this->debug("DevilsDice stCheckWin: Transitioning to chooseDiceOverflowFace");
            $this->gamestate->nextState('chooseDiceOverflowFace');
            return;
        }

        // Check legacy overflow flags (for backwards compatibility)
        $overflowPlayer = $this->getGameStateValue('dice_overflow_player');
        if ((int)$overflowPlayer > 0) {
            $this->debug("DevilsDice stCheckWin: Found legacy overflow player $overflowPlayer");
            $this->debug("DevilsDice stCheckWin: Setting active player to $overflowPlayer");
            $this->gamestate->changeActivePlayer((int)$overflowPlayer);
            $this->debug("DevilsDice stCheckWin: Transitioning to chooseDiceOverflowFace");
            $this->gamestate->nextState('chooseDiceOverflowFace');
            return;
        }

        // No overflow, proceed with normal win checking
        $this->debug("DevilsDice stCheckWin: No overflow found, checking for winners");
        $winners = GameValidator::findWinners($this);

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
        $winners = json_decode((string)$this->getGameStateValue('action_data'), true);

        $rolloffResults = [];
        foreach ($winners as $playerId) {
            $impCount = GameValidator::countImpsForPlayer($this, $playerId);
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
     * Add or remove dice from a player and send notification
     */
    private function addOrRemoveDice($playerId, $count) {
        $current = intval($this->getUniqueValueFromDB("SELECT COUNT(dice_id) FROM player_dice WHERE player_id = $playerId AND location = 'hand'"));

        // Check for dice overflow when adding dice - store as pending instead of immediate flag
        if ($count > 0 && $current >= 6) {
            // Player already has 6 dice, store pending overflow for later processing
            $existingData = $this->getGameStateValue('action_data');
            $actionData = $existingData ? json_decode((string)$existingData, true) : [];
            $actionData['pending_overflow'] = [
                'player' => $playerId,
                'count' => $count
            ];
            $this->setGameStateValue('action_data', json_encode($actionData));
            $this->debug("DevilsDice addOrRemoveDice: Player $playerId at dice limit, storing pending overflow");
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
        DiceManager::rollDiceForPlayer($this, $playerId);
    }

    /**
     * Steal dice from one player to another
     */
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
        DiceManager::rollDiceForPlayer($this, $stealerId);
        DiceManager::rollDiceForPlayer($this, $victimId);
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
