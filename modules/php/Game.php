<?php

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * DevilsDice implementation : © hedgehogterror, brookelfnichols@gmail.com
 * 🔥🔥🔥 FORCE REFRESH 2024-11-27 v3 🔥🔥🔥
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
 * Force refresh 2024-11-27
 */

declare(strict_types=1);

namespace Bga\Games\DevilsDice;

require_once("autoload.php");
require_once("Constants.inc.php");
require_once(__DIR__ . "/DiceValidator.php");
require_once(__DIR__ . "/GameValidator.php");
require_once(__DIR__ . "/TokenManager.php");
require_once(__DIR__ . "/DiceManager.php");
require_once(__DIR__ . "/ActionExecutor.php");
require_once(__DIR__ . "/PlayerActions.php");

class Game extends \Bga\GameFramework\Table {
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
            "action_data" => 14, // DEPRECATED - use blocked_action and blocked_action_player instead
            "dice_overflow_player" => 15,
            "dice_overflow_count" => 16,
            "blocked_action" => 17,  // The action that was blocked
            "blocked_action_player" => 18  // The player whose action was blocked
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
    private const STARTING_SKULL_TOKENS = 1;
    private const STARTING_DICE = 2;



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
        // Get highest player dice count (win condition is 6 dice with all symbols)
        $maxDiceCount = intval($this->getUniqueValueFromDB(
            "SELECT COALESCE(MAX(dice_count), 0) FROM (
                SELECT COUNT(dice_id) as dice_count
                FROM player_dice
                WHERE location = 'hand'
                GROUP BY player_id
            ) as counts"
        ));

        // Get Satan's pool size (indicates game activity)
        $satansPoolCount = intval($this->getUniqueValueFromDB("SELECT COUNT(*) FROM satans_pool"));

        // Progress is weighted: 45% from highest dice count, 45% from Satan's pool
        // Max dice is 6, max reasonable pool size is ~6 (beyond that it's unusual)
        // can't reliably predict the games end from these alone, but gives a decent estimate
        $diceProgress = min(45, ($maxDiceCount / 6) * 50);
        $poolProgress = min(45, ($satansPoolCount / 6) * 50);

        $totalProgress = intval($diceProgress + $poolProgress);

        // Ensure we return between 0-100, with minimum of 5% to show game has started
        return max(5, min(100, $totalProgress));
    }

    /**
     * Player actions
     */

    public function raiseHell() {
        PlayerActions::raiseHell($this);
    }

    public function harvestSkulls() {
        PlayerActions::harvestSkulls($this);
    }

    public function extort($targetPlayerId) {
        PlayerActions::extort($this, $targetPlayerId);
    }

    public function reapSoul($targetPlayerId) {
        PlayerActions::reapSoul($this, $targetPlayerId);
    }

    public function pentagram() {
        PlayerActions::pentagram($this);
    }

    public function impsSet() {
        PlayerActions::impsSet($this);
    }

    public function satansSteal($targetPlayerId, $putInPool = false, $poolFace = null) {
        PlayerActions::satansSteal($this, $targetPlayerId, $putInPool, $poolFace);
    }

    public function argChooseDiceOverflowFace() {
        // Provide arguments for the chooseDiceOverflowFace state
        $overflowPlayer = $this->getGameStateValue('dice_overflow_player');

        // Safety check: if overflow player is 0 (already cleared), this is likely being called
        // during a state transition after the action completed. Use active player as fallback.
        if ($overflowPlayer == 0) {
            $overflowPlayer = intval($this->getActivePlayerId());
            $this->debug("DevilsDice argChooseDiceOverflowFace: overflow player was 0, using active player $overflowPlayer as fallback");
        }

        return [
            'actplayer' => $this->getPlayerNameById($overflowPlayer),
            '_private' => [] // No private args needed
        ];
    }

    public function stChooseDiceOverflowFace() {
        // State method - called when entering chooseDiceOverflowFace state
        $this->debug("🔥🔥🔥 NEW CODE VERSION 2025-01-13-04:30 LOADED 🔥🔥🔥");
        $overflowPlayer = $this->getGameStateValue('dice_overflow_player');
        $this->debug("DevilsDice stChooseDiceOverflowFace: overflow player is $overflowPlayer");

        // Get current state info to determine how to set active player
        $state = $this->gamestate->state();
        $stateType = $state['type'];

        $this->debug("DevilsDice stChooseDiceOverflowFace: State type is $stateType");

        if ($stateType === 'multipleactiveplayer') {
            // Give the player 5 extra seconds to choose which face to sacrifice
            $this->giveExtraTime(intval($overflowPlayer), 5);

            // Simply set ONLY the overflow player as multiactive
            // The third parameter being true means EXCLUSIVE (replace existing, don't add)
            $this->gamestate->setPlayersMultiactive(array(strval($overflowPlayer)), '', true);
            $this->debug("DevilsDice stChooseDiceOverflowFace: Set player $overflowPlayer as multiactive (exclusive mode)");
        } else if ($stateType === 'activeplayer') {
            // Give the player 5 extra seconds to choose which face to sacrifice
            $this->giveExtraTime(intval($overflowPlayer), 5);
            // For activeplayer state, we need to ensure the correct player is active
            // But we CANNOT call changeActivePlayer here because we're already in the state
            // The active player must have been set before entering this state
            // If it wasn't set correctly, we have a problem - log it
            $currentActive = $this->getActivePlayerId();
            $this->debug("DevilsDice stChooseDiceOverflowFace: State is activeplayer, current active: $currentActive, should be: $overflowPlayer");

            if ($currentActive != $overflowPlayer) {
                $this->warn("DevilsDice stChooseDiceOverflowFace: WARNING - Active player mismatch! Current: $currentActive, Expected: $overflowPlayer");
                // We can't fix this here without causing an error
                // The game will have to continue with whoever is active
            }
        }
    }

    public function chooseDiceOverflowFace($face) {
        PlayerActions::chooseDiceOverflowFace($this, $face);
    }


    public function challenge() {
        PlayerActions::challenge($this);
    }

    public function block() {
        PlayerActions::block($this);
    }

    public function pass() {
        PlayerActions::pass($this);
    }

    /**
     * Game state actions
     */

    public function stGameSetup() {
        // This function is deprecated but required by bga-ts-template
        // All setup logic is in setupNewGame() which returns the initial state ID
        // This function just transitions to the first real game state
        $this->gamestate->nextState('playerTurn');
    }

    public function argChallengeWindow() {
        // Provide arguments for the challengeWindow state
        $actionPlayerId = intval($this->getGameStateValue('current_action_player'));
        $currentAction = $this->getGameStateValue('current_action');
        $targetPlayerId = intval($this->getGameStateValue('current_target_player'));

        // Get the action label for display (human-readable)
        $actionLabel = Actions::getActionLabel($currentAction);

        // Build the result
        $result = [
            'action_player_name' => $this->getPlayerNameById($actionPlayerId),
            'action_name' => $actionLabel,
            'action_id' => $currentAction,
            'action_player_id' => $actionPlayerId,
            '_private' => []
        ];

        // Add target player info if there is one
        if ($targetPlayerId > 0) {
            $result['target_player'] = $this->getPlayerNameById($targetPlayerId);
            $result['target_player_id'] = $targetPlayerId;
        }

        return $result;
    }

    public function stChallengeWindow() {
        $this->debug("🚀🚀🚀 FRESH CODE v5: stChallengeWindow - ANTI-LOOP PROTECTION 🚀🚀🚀");

        // ANTI-LOOP PROTECTION: Check if we're coming from a crashed resolveAction
        // If the game state shows we're in challengeWindow but action was already being resolved,
        // this indicates stResolveAction crashed and we need to clear the action state
        $gameStateId = $this->gamestate->state_id();
        $this->debug("🚀 stChallengeWindow: Current game state ID: $gameStateId");

        // Get action player and determine who can challenge
        $actionPlayerId = intval($this->getGameStateValue('current_action_player'));
        $currentAction = $this->getGameStateValue('current_action');

        // Safety check: if actionPlayerId is 0, something went wrong
        if ($actionPlayerId === 0) {
            $this->debug("DevilsDice stChallengeWindow: ⚠️ ERROR: actionPlayerId is 0 - skipping challenge window");
            $this->gamestate->nextState('resolveAction');
            return;
        }

        // Get all players except the action player
        $players = $this->loadPlayersBasicInfos();
        $challengePlayers = [];

        foreach ($players as $playerId => $player) {
            $playerIdInt = intval($playerId);
            if ($playerIdInt !== $actionPlayerId) {
                // Check if player has any dice - players with 0 dice can't challenge
                $diceCount = intval($this->getUniqueValueFromDB("SELECT COUNT(*) FROM player_dice WHERE player_id = $playerIdInt AND location = 'hand'"));
                if ($diceCount > 0) {
                    $challengePlayers[] = $playerIdInt; // Use integer, not string
                    $this->debug("DevilsDice stChallengeWindow: Added player $playerIdInt to challenge list (has $diceCount dice)");
                } else {
                    $this->debug("DevilsDice stChallengeWindow: Excluded player $playerIdInt - has 0 dice, auto-passing");
                }
            } else {
                $this->debug("DevilsDice stChallengeWindow: Excluded action player $playerIdInt");
            }
        }

        $this->debug("DevilsDice stChallengeWindow: Final challenge players: " . json_encode($challengePlayers));

        // Determine the next state based on the action type
        $nextState = 'resolveAction';

        // Special handling for BLOCK action
        if ($currentAction == Actions::BLOCK) {
            // If the block is not challenged, it succeeds - cancel the original action
            $this->debug("DevilsDice stChallengeWindow: Action is BLOCK - if not challenged, block succeeds");
            $nextState = 'checkWin'; // Skip resolveAction, go straight to checkWin (which will move to next player)
        } else if (in_array($currentAction, [Actions::EXTORT, Actions::REAP_SOUL])) {
            $nextState = 'blockWindow';
        }

        // CRITICAL FIX: If going to blockWindow, set the target as active player BEFORE transition
        if ($nextState === 'blockWindow') {
            $targetPlayerId = intval($this->getGameStateValue('current_target_player'));
            if ($targetPlayerId > 0) {
                $this->debug("DevilsDice stChallengeWindow: 🔧 Pre-setting active player to target $targetPlayerId for blockWindow");
                $this->gamestate->changeActivePlayer($targetPlayerId);
            } else {
                $this->debug("DevilsDice stChallengeWindow: ⚠️ WARNING: No target player for blockWindow, skipping to checkWin");
                $nextState = 'checkWin';
            }
        }

        // Set only challenge players as multiactive
        if (count($challengePlayers) > 0) {
            // Give players 5 extra seconds to decide whether to challenge
            foreach ($challengePlayers as $playerId) {
                $this->giveExtraTime(intval($playerId), 5);
            }
            $this->gamestate->setPlayersMultiactive($challengePlayers, $nextState, true);
            $this->debug("DevilsDice stChallengeWindow: Set challenge players as multiactive with exclusive=true");
        } else {
            // No challengers, proceed to next state
            $this->gamestate->nextState($nextState);
            $this->debug("DevilsDice stChallengeWindow: No challengers, proceeding to $nextState");
        }
    }

    public function argBlockWindow() {
        // Provide arguments for the blockWindow state
        $actionPlayerId = intval($this->getGameStateValue('current_action_player'));
        $currentAction = $this->getGameStateValue('current_action');
        $targetPlayerId = intval($this->getGameStateValue('current_target_player'));

        // Get the action label for display
        $actionLabel = Actions::getActionLabel($currentAction);

        $result = [
            'action_player_name' => $this->getPlayerNameById($actionPlayerId),
            'target_player_name' => $this->getPlayerNameById($targetPlayerId),
            'action_name' => $actionLabel,
            'action_id' => $currentAction,
            'action_player_id' => $actionPlayerId,
            'target_player_id' => $targetPlayerId
        ];

        return $result;
    }

    public function stBlockWindow() {
        $this->debug("🛑🛑🛑 stBlockWindow CALLED 🛑🛑🛑");

        // Set the target player as the active player for the block window
        $targetPlayerId = intval($this->getGameStateValue('current_target_player'));
        $actionPlayerId = intval($this->getGameStateValue('current_action_player'));
        $currentActivePlayer = intval($this->getActivePlayerId());

        $this->debug("DevilsDice stBlockWindow: Target player: $targetPlayerId");
        $this->debug("DevilsDice stBlockWindow: Action player: $actionPlayerId");
        $this->debug("DevilsDice stBlockWindow: Current active player BEFORE change: $currentActivePlayer");

        // Safety check
        if ($targetPlayerId === 0) {
            $this->debug("DevilsDice stBlockWindow: ⚠️ ERROR: targetPlayerId is 0 - skipping block window");
            $this->gamestate->nextState('resolveAction');
            return;
        }

        // Check if target player has 0 dice - if so, auto-pass (can't block)
        $targetDiceCount = intval($this->getUniqueValueFromDB("SELECT COUNT(*) FROM player_dice WHERE player_id = $targetPlayerId AND location = 'hand'"));
        if ($targetDiceCount === 0) {
            $this->debug("DevilsDice stBlockWindow: Target player $targetPlayerId has 0 dice, auto-passing block window");
            $this->gamestate->nextState('resolveAction');
            return;
        }

        // CRITICAL FIX: Check if we're already in the right state
        if ($currentActivePlayer === $targetPlayerId) {
            $this->debug("DevilsDice stBlockWindow: ✅ Active player is already correct: $targetPlayerId");
            return;
        }

        // If the action player is currently active (shouldn't be), this is the bug
        if ($currentActivePlayer === $actionPlayerId) {
            $this->debug("DevilsDice stBlockWindow: ⚠️ BUG DETECTED: Action player $actionPlayerId is active, should be target $targetPlayerId");
        }

        $this->debug("DevilsDice stBlockWindow: Changing active player from $currentActivePlayer to $targetPlayerId");
        $this->gamestate->changeActivePlayer($targetPlayerId);

        // Give the target player 5 extra seconds to decide whether to block
        $this->giveExtraTime($targetPlayerId, 5);

        $newActivePlayer = intval($this->getActivePlayerId());
        $this->debug("DevilsDice stBlockWindow: Current active player AFTER change: $newActivePlayer");

        if ($newActivePlayer !== $targetPlayerId) {
            $this->debug("DevilsDice stBlockWindow: ⚠️ ERROR: Active player did not change! Expected $targetPlayerId, got $newActivePlayer");
            $this->warn("DevilsDice stBlockWindow: changeActivePlayer() failed - this may cause a loop!");
        } else {
            $this->debug("DevilsDice stBlockWindow: ✅ Successfully set active player to target player $targetPlayerId");
        }
    }

    public function stResolveChallenge() {
        $challengerId = intval($this->getGameStateValue('challenge_player'));
        $actionPlayerId = intval($this->getGameStateValue('current_action_player'));
        $currentAction = $this->getGameStateValue('current_action');

        $this->debug("DevilsDice stResolveChallenge: Challenger: $challengerId, Action player: $actionPlayerId, Action: $currentAction");

        // Check if this is a challenge to a BLOCK
        $isBlockChallenge = ($currentAction == Actions::BLOCK);
        $actionData = json_decode((string)$this->getGameStateValue('action_data'), true) ?: [];

        // Get the challenged player's dice
        $playerDice = $this->getCollectionFromDb(
            "SELECT face FROM player_dice WHERE player_id = $actionPlayerId AND location = 'hand'"
        );

        $challengeSuccessful = !DiceValidator::validateActionClaim($currentAction, array_column($playerDice, 'face'), $this);

        if ($challengeSuccessful) {
            // Challenge successful - challenger steals dice from the lying player
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

            $this->stealDiceFromPlayer($challengerId, $actionPlayerId);

            // Refund reap soul cost if the action was successfully challenged
            if ($currentAction == Actions::REAP_SOUL && !$isBlockChallenge) {
                // Player was lying about reap soul and lost the challenge
                // Refund the 2 skull tokens they paid upfront
                $this->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens + 2 WHERE player_id = $actionPlayerId");
                $this->debug("💀 stResolveChallenge: Refunded 2 skull tokens to player $actionPlayerId (reap soul challenge succeeded)");

                // Notify clients of token update
                $playerNewTokens = $this->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $actionPlayerId");
                $this->notifyAllPlayers(
                    'tokensChanged',
                    '',
                    [
                        'playerId' => $actionPlayerId,
                        'tokens' => $playerNewTokens
                    ]
                );
            }

            // Special handling for BLOCK challenges
            $blockedAction = intval($this->getGameStateValue('blocked_action'));
            $blockedActionPlayer = intval($this->getGameStateValue('blocked_action_player'));

            if ($isBlockChallenge && $blockedAction > 0 && $blockedActionPlayer > 0) {
                // Block was challenged successfully - blocker was lying
                // Restore the original action and let it proceed
                $this->debug("DevilsDice stResolveChallenge: Block challenged successfully - restoring original action");
                $this->debug("DevilsDice stResolveChallenge: Restoring action $blockedAction from player $blockedActionPlayer");
                $this->setGameStateValue('current_action', $blockedAction);
                $this->setGameStateValue('current_action_player', $blockedActionPlayer);
                // Note: current_target_player should already be set correctly (the blocker who lied)
            }

            // Check for overflow before continuing
            $overflowPlayer = $this->getGameStateValue('dice_overflow_player');
            if ((int)$overflowPlayer > 0) {
                $this->debug("DevilsDice stResolveChallenge: Overflow detected for player $overflowPlayer");
                if ($isBlockChallenge && isset($actionData['blocked_action'])) {
                    // Overflow during block challenge - need to handle overflow then continue with original action
                    $this->setGameStateValue('action_data', json_encode(array_merge($actionData, ['overflow_from_challenge' => true])));
                } else {
                    // Normal challenge overflow - action is cancelled
                    $this->clearActionState();
                    $this->setGameStateValue('action_data', json_encode(['overflow_from_challenge' => true]));
                }
                $this->gamestate->nextState('chooseDiceOverflowFace');
            } else {
                if ($isBlockChallenge && $this->getGameStateValue('current_action') != Actions::BLOCK) {
                    // Block was challenged, blocker failed - continue with original action
                    $this->gamestate->nextState('resolveAction');
                } else {
                    // Normal challenge or action is now cancelled
                    $this->cancelActionAndContinue();
                }
            }
        } else {
            // Challenge failed - blocker was telling the truth, original action is cancelled
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

            if ($isBlockChallenge) {
                // Block was NOT successfully challenged - block stands, original action is cancelled
                $this->debug("DevilsDice stResolveChallenge: Block challenge failed - block succeeds, cancelling original action");
                $this->gamestate->nextState('checkWin');
            } else {
                // Normal action challenge failed - continue with the action
                if (in_array($currentAction, [Actions::EXTORT, Actions::REAP_SOUL])) {
                    $this->gamestate->nextState('blockWindow');
                } else {
                    $this->gamestate->nextState('resolveAction');
                }
            }
        }
    }

    public function stResolveAction() {
        try {
            $actionPlayerId = intval($this->getGameStateValue('current_action_player'));
            $currentAction = $this->getGameStateValue('current_action');
            $targetPlayerId = intval($this->getGameStateValue('current_target_player'));

            $actionName = Actions::getActionName($currentAction);
            $this->debug("🔧🔧 FRESH v5: stResolveAction - Player: $actionPlayerId, Action: $currentAction ($actionName), Target: $targetPlayerId");

            $this->debug("🔧 stResolveAction: About to execute action switch statement");
            switch ($currentAction) {
                case Actions::RAISE_HELL:
                    $this->debug("🔧 stResolveAction: executing RAISE_HELL");
                    ActionExecutor::executeRaiseHell($this, $actionPlayerId);
                    break;
                case Actions::HARVEST_SKULLS:
                    $this->debug("🔧 stResolveAction: executing HARVEST_SKULLS");
                    ActionExecutor::executeHarvestSkulls($this, $actionPlayerId);
                    break;
                case Actions::EXTORT:
                    $this->debug("🔧 stResolveAction: executing EXTORT");
                    ActionExecutor::executeExtort($this, $actionPlayerId, $targetPlayerId);
                    break;
                case Actions::REAP_SOUL:
                    $this->debug("🔧 stResolveAction: executing REAP_SOUL");
                    ActionExecutor::executeReapSoul($this, $actionPlayerId, $targetPlayerId);
                    break;
                case Actions::PENTAGRAM:
                    $this->debug("🔧 stResolveAction: executing PENTAGRAM");
                    ActionExecutor::executePentagram($this, $actionPlayerId);
                    break;
                case Actions::IMPS_SET:
                    $this->debug("🔧 stResolveAction: executing IMPS_SET");
                    ActionExecutor::executeImpsSet($this, $actionPlayerId);
                    break;
                case Actions::SATANS_STEAL:
                    $this->debug("🔧 stResolveAction: executing SATANS_STEAL");
                    ActionExecutor::executeSatansSteal($this, $actionPlayerId);
                    break;
                case Actions::BLOCK:
                    // BLOCK should not reach resolveAction normally - it should go to checkWin
                    // But if it does (via old PASS code path), just treat it as no-op
                    $this->debug("🔧 stResolveAction: BLOCK action - no execution needed (block succeeded)");
                    break;
                default:
                    $this->debug("🔧 ERROR: Unknown action: $currentAction ($actionName) (type: " . gettype($currentAction) . ")");
                    break;
            }
            $this->debug("🔧 stResolveAction: Action execution completed");

            // DEBUG: Log current state before transition logic
            $currentState = $this->gamestate->state();
            $stateId = isset($currentState['id']) ? $currentState['id'] : 'unknown';
            $stateName = isset($currentState['name']) ? $currentState['name'] : 'unknown';
            $stateType = isset($currentState['type']) ? $currentState['type'] : 'unknown';
            $this->debug("🔧 stResolveAction: Current state info - ID: $stateId, Name: $stateName, Type: $stateType");

            // Always transition to checkWin - overflow detection will happen there
            $this->debug("🔧 stResolveAction: About to transition to checkWin");
            $this->gamestate->nextState('checkWin');
        } catch (\Exception $e) {
            $this->debug("🔧 CRITICAL ERROR in stResolveAction: " . $e->getMessage());
            $this->debug("🔧 ERROR stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function stCheckWin() {
        $this->debug("🏁🏁🏁 DevilsDice stCheckWin: ENTERED CHECK WIN FUNCTION (v2024-11-10-FIX) 🏁🏁🏁");

        $currentActivePlayer = intval($this->getActivePlayerId());
        $actionPlayerId = intval($this->getGameStateValue('current_action_player'));
        $this->debug("🏁 stCheckWin: Current active player: $currentActivePlayer, Action player: $actionPlayerId");

        // First, check for pending overflow from action execution
        $actionDataJson = $this->getGameStateValue('action_data');
        $actionData = $actionDataJson ? json_decode((string)$actionDataJson, true) : [];
        $this->debug("🏁 stCheckWin: Checking for pending overflow - Action data: " . ($actionDataJson ? $actionDataJson : 'empty'));

        if (isset($actionData['pending_overflow'])) {
            // There was a pending overflow, set flags and transition to overflow choice
            $pendingOverflow = $actionData['pending_overflow'];
            $this->debug("DevilsDice stCheckWin: Found pending overflow - Player: " . $pendingOverflow['player'] . ", Count: " . $pendingOverflow['count']);

            $this->setGameStateValue('dice_overflow_player', $pendingOverflow['player']);
            $this->setGameStateValue('dice_overflow_count', $pendingOverflow['count']);

            // Clear pending overflow from action data
            unset($actionData['pending_overflow']);
            $this->setGameStateValue('action_data', json_encode($actionData));

            // Don't call changeActivePlayer here - let stChooseDiceOverflowFace handle it
            // This avoids the "Impossible to change active player during activeplayer type state" error
            $this->debug("DevilsDice stCheckWin: Transitioning to chooseDiceOverflowFace");
            $this->gamestate->nextState('chooseDiceOverflowFace');
            return;
        }

        // Check legacy overflow flags (for backwards compatibility)
        $overflowPlayer = $this->getGameStateValue('dice_overflow_player');
        if ((int)$overflowPlayer > 0) {
            $this->debug("DevilsDice stCheckWin: Found legacy overflow player $overflowPlayer");
            // Don't call changeActivePlayer here - let stChooseDiceOverflowFace handle it
            $this->debug("DevilsDice stCheckWin: Transitioning to chooseDiceOverflowFace");
            $this->gamestate->nextState('chooseDiceOverflowFace');
            return;
        }

        // No overflow, proceed with normal win checking
        $this->debug("DevilsDice stCheckWin: No overflow found, checking for winners");
        $winners = GameValidator::findWinners($this);

        if (count($winners) === 1) {
            // Single winner - game ends immediately
            $winnerId = $winners[0];

            $this->debug("🏆🏆🏆 stCheckWin: WINNER DETECTED - Player $winnerId 🏆🏆🏆");

            // Send win notification
            $this->notifyAllPlayers(
                'gameWon',
                clienttranslate('${player} wins the game!'),
                [
                    'player' => $this->getPlayerNameById($winnerId),
                    'winnerId' => strval($winnerId)
                ]
            );

            $this->debug("🏆 stCheckWin: Setting winner score to 1 for player $winnerId");

            // Set winner score to 1 to indicate they won
            $this->DbQuery("UPDATE player SET player_score = 1 WHERE player_id = $winnerId");

            $this->debug("🏆 stCheckWin: Score set, transitioning to gameEnd");

            // Transition to gameEnd state - framework will handle game termination
            $this->gamestate->nextState('gameEnd');
            return;
        } else if (count($winners) > 1) {
            // Tie - need rolloff
            $this->setGameStateValue('action_data', (string)json_encode($winners));
            $this->gamestate->nextState('rolloff');
        } else {
            // No winner yet, continue game - clear action state since action is complete
            $this->debug("🏁 stCheckWin: No winner found");
            $this->debug("🏁 stCheckWin: About to call cancelActionAndContinue()");
            $this->debug("🏁 stCheckWin: Current state - active: " . $this->getActivePlayerId() . ", action_player: " . $this->getGameStateValue('current_action_player') . ", action: " . $this->getGameStateValue('current_action'));
            $this->cancelActionAndContinue();
            $this->debug("🏁 stCheckWin: Returned from cancelActionAndContinue()");
        }
    }

    /**
     * Move to next player and clear action state (for normal game flow)
     * This function is no longer used - cancelActionAndContinue handles all cases
     */
    private function continueToNextPlayer() {
        // This function is deprecated - just call cancelActionAndContinue instead
        $this->debug("⚠️ continueToNextPlayer: DEPRECATED - calling cancelActionAndContinue instead");
        $this->cancelActionAndContinue();
    }

    /**
     * Clear action state and move to next player (for cancelled actions)
     */
    private function cancelActionAndContinue() {
        $currentActivePlayer = intval($this->getActivePlayerId());
        $actionPlayerId = intval($this->getGameStateValue('current_action_player'));
        $currentAction = $this->getGameStateValue('current_action');

        // Get all player IDs to show turn order
        $players = $this->loadPlayersBasicInfos();
        $playerIds = array_keys($players);
        sort($playerIds);
        $this->debug("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->debug("🚀 cancelActionAndContinue: ENTERED");
        $this->debug("🚀 Player order: " . implode(' → ', $playerIds));
        $this->debug("🚀 Current active: $currentActivePlayer");
        $this->debug("🚀 Action player (from state): $actionPlayerId");
        $this->debug("🚀 Current action: $currentAction");

        // CRITICAL FIX: Check if this was a blocked action
        $blockedAction = intval($this->getGameStateValue('blocked_action'));
        $blockedActionPlayer = intval($this->getGameStateValue('blocked_action_player'));
        $this->debug("🚀 blocked_action: $blockedAction");
        $this->debug("🚀 blocked_action_player: $blockedActionPlayer");

        if ($currentAction == Actions::BLOCK && $blockedActionPlayer > 0) {
            // This is a successful block - the original action player should have the turn end
            $this->debug("🚀 🛡️ BLOCK DETECTED!");
            $this->debug("🚀 🛡️ Original action that was blocked: $blockedAction");
            $this->debug("🚀 🛡️ Original action player: $blockedActionPlayer");
            $this->debug("🚀 🛡️ Blocker (current action_player): $actionPlayerId");
            $this->debug("🚀 🛡️ USING original action player ($blockedActionPlayer) for turn progression");
            $actionPlayerId = $blockedActionPlayer; // Use original action player for turn progression
        }

        $this->debug("🚀 Final actionPlayerId to use for progression: $actionPlayerId");

        // If the active player was changed (e.g., for block window),
        // restore it to the action player before calling activeNextPlayer
        if ($actionPlayerId > 0 && $currentActivePlayer !== $actionPlayerId) {
            $this->debug("🚀 ⚠️ Active player mismatch! Restoring active player to action player");
            $this->debug("🚀 ⚠️ Changing active player: $currentActivePlayer → $actionPlayerId");
            $this->gamestate->changeActivePlayer($actionPlayerId);
            $restoredActive = intval($this->getActivePlayerId());
            $this->debug("🚀 ✓ Active player restored to: $restoredActive");
        } else {
            $this->debug("🚀 ✓ Active player already matches action player, no restoration needed");
        }

        $this->clearActionState();
        $this->debug("🚀 ✓ Action state cleared (action_data is now empty)");

        $beforeNext = intval($this->getActivePlayerId());
        $this->debug("🚀 About to call activeNextPlayer() from player: $beforeNext");
        $this->activeNextPlayer();
        $newActivePlayer = intval($this->getActivePlayerId());
        $this->debug("🚀 ✓ activeNextPlayer() completed: $beforeNext → $newActivePlayer");

        $this->debug("🚀 About to transition to playerTurn state");
        $this->gamestate->nextState('playerTurn');

        $finalActivePlayer = intval($this->getActivePlayerId());
        $this->debug("🚀 ✓ COMPLETE - Final active player after transition: $finalActivePlayer");
        $this->debug("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    }

    public function stRolloff() {
        $winners = json_decode((string)$this->getGameStateValue('action_data'), true);

        $this->debug("🎲 ROLLOFF: Starting rolloff for tied players: " . json_encode($winners));

        // Reroll dice for all tied players
        foreach ($winners as $playerId) {
            DiceManager::rollDiceForPlayer($this, $playerId);
            $this->debug("🎲 ROLLOFF: Rerolled dice for player $playerId");
        }

        // Count imps for each player after reroll
        $rolloffResults = [];
        foreach ($winners as $playerId) {
            $impCount = GameValidator::countImpsForPlayer($this, $playerId);
            $rolloffResults[$playerId] = $impCount;
            $this->debug("🎲 ROLLOFF: Player $playerId has $impCount imps");
        }

        $maxImps = max($rolloffResults);
        $finalWinners = array_keys(array_filter($rolloffResults, function ($imps) use ($maxImps) {
            return $imps === $maxImps;
        }));

        $this->debug("🎲 ROLLOFF: Max imps: $maxImps, Players with max: " . json_encode($finalWinners));

        if (count($finalWinners) === 1) {
            // Clear winner - one player has most imps
            $winnerId = $finalWinners[0];
            $this->DbQuery("UPDATE player SET player_score = 1 WHERE player_id = $winnerId");
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

            // Transition to gameEnd state to properly end the game
            $this->gamestate->nextState('gameEnd');
        } else {
            // Still tied - reroll again
            $this->debug("🎲 ROLLOFF: Still tied with $maxImps imps, rerolling again");
            $this->notifyAllPlayers(
                'message',
                clienttranslate('Rolloff tied at ${imps} imps - rerolling'),
                [
                    'imps' => $maxImps
                ]
            );

            // Save the tied players for next rolloff
            $this->setGameStateValue('action_data', (string)json_encode($finalWinners));

            // Transition back to rolloff state to try again
            $this->gamestate->nextState('rolloff');
        }
    }

    /**
     * Helper methods
     */

    private function clearActionState() {
        // Clear action-related game state values when starting a new turn
        $oldActionPlayer = $this->getGameStateValue('current_action_player');
        $this->debug("DevilsDice clearActionState: ⚠️ CLEARING ACTION STATE - old action player was: $oldActionPlayer");

        $this->setGameStateValue('current_action', 0);
        $this->setGameStateValue('current_action_player', 0);
        $this->setGameStateValue('current_target_player', 0);
        $this->setGameStateValue('challenge_player', 0);
        $this->setGameStateValue('action_data', '');
        $this->setGameStateValue('blocked_action', 0);
        $this->setGameStateValue('blocked_action_player', 0);

        $this->debug("DevilsDice clearActionState: Action state cleared, current_action_player now = 0");
    }

    /**
     * Add or remove dice from a player and send notification
     */
    public function addOrRemoveDice($playerId, $count) {
        $this->debug("🎯🎯🎯 addOrRemoveDice: ENTERED for player $playerId, count $count 🎯🎯🎯");

        $current = intval($this->getUniqueValueFromDB("SELECT COUNT(dice_id) FROM player_dice WHERE player_id = $playerId AND location = 'hand'"));
        $this->debug("🎯 addOrRemoveDice: Player $playerId currently has $current dice");

        // Check for dice overflow when adding dice - store as pending instead of immediate flag
        if ($count > 0 && $current >= 6) {
            $this->debug("🎯 addOrRemoveDice: Dice overflow detected! Current: $current, adding: $count");
            // Player already has 6 dice, store pending overflow for later processing
            $existingData = $this->getGameStateValue('action_data');
            $actionData = $existingData ? json_decode((string)$existingData, true) : [];
            $actionData['pending_overflow'] = [
                'player' => $playerId,
                'count' => $count
            ];
            $this->setGameStateValue('action_data', json_encode($actionData));
            $this->debug("🎯 addOrRemoveDice: Player $playerId at dice limit, storing pending overflow - EARLY RETURN");
            return; // Don't add dice to player, let stResolveAction handle overflow
        }

        $newCount = max(0, $current + $count); // Ensure we don't go below 0
        $this->debug("🎯 addOrRemoveDice: Calculated newCount = $newCount (current $current + count $count)");

        // Delete existing dice
        if ($current > 0) {
            $this->debug("🎯 addOrRemoveDice: Deleting existing $current dice");
            $this->DbQuery("DELETE FROM player_dice WHERE player_id = $playerId AND location = 'hand'");
            $this->debug("🎯 addOrRemoveDice: Existing dice deleted");
        }

        // Create new dice with random faces
        $this->debug("🎯 addOrRemoveDice: About to get all dice faces");
        $faces = DiceFaces::getAllFaces();
        $this->debug("🎯 addOrRemoveDice: Got " . count($faces) . " faces, creating $newCount new dice");

        for ($i = 0; $i < $newCount; $i++) {
            $randomFace = $faces[array_rand($faces)];
            $this->debug("🎯 addOrRemoveDice: Creating die $i with face '$randomFace'");
            $this->DbQuery("INSERT INTO player_dice (player_id, face, location) VALUES ($playerId, '$randomFace', 'hand')");
        }
        $this->debug("🎯 addOrRemoveDice: All dice created");

        $this->debug("🎯 addOrRemoveDice: Player $playerId now has $newCount dice (was $current, changed by $count)");

        // Send notification
        $this->debug("🎯 addOrRemoveDice: About to call DiceManager::rollDiceForPlayer");
        DiceManager::rollDiceForPlayer($this, $playerId);
        $this->debug("🎯 addOrRemoveDice: DiceManager::rollDiceForPlayer completed - FUNCTION COMPLETE");
    }

    /**
     * Steal dice from one player to another
     */
    public function stealDiceFromPlayer($stealerId, $victimId) {
        // Check if stealer would overflow before stealing
        $stealerCurrent = intval($this->getUniqueValueFromDB("SELECT COUNT(dice_id) FROM player_dice WHERE player_id = $stealerId AND location = 'hand'"));

        $stealerOverflow = $stealerCurrent >= 6;

        if ($stealerOverflow) {
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

        // Reroll dice - but don't reroll stealer's dice if they overflowed
        if (!$stealerOverflow) {
            // Only reroll stealer's dice if they actually gained a die
            DiceManager::rollDiceForPlayer($this, $stealerId);
        } else {
            $this->debug("DevilsDice stealDiceFromPlayer: Stealer $stealerId overflowed, not rerolling their dice");
        }
        // Always reroll victim's dice since they lost a die
        DiceManager::rollDiceForPlayer($this, $victimId);
    }

    public function moveDiceToSatansPool($playerId, $face = null) {
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
                case "playerTurn":
                    // Zombie player passes their turn - just move to next player
                    $this->clearActionState();
                    $this->gamestate->nextState("challengeWindow");
                    break;

                case "blockWindow":
                    // Zombie doesn't block - pass
                    $this->gamestate->nextState("resolveAction");
                    break;
                // this case shoulnd't happen unless the player abandoned mid choice
                case "chooseDiceOverflowFace":
                    // Zombie chooses a random face for Satan's pool
                    $faces = DiceFaces::getAllFaces();
                    $randomFace = $faces[array_rand($faces)];

                    $overflowPlayer = $this->getGameStateValue('dice_overflow_player');
                    if ($activePlayer == $overflowPlayer) {
                        $this->DbQuery("INSERT INTO satans_pool (face) VALUES ('$randomFace')");
                        $this->setGameStateValue('dice_overflow_player', 0);
                        $this->setGameStateValue('dice_overflow_count', 0);

                        // Check if from challenge or normal action
                        $actionDataJson = $this->getGameStateValue('action_data');
                        $actionData = $actionDataJson ? json_decode((string)$actionDataJson, true) : [];
                        $overflowFromChallenge = isset($actionData['overflow_from_challenge']) && $actionData['overflow_from_challenge'];

                        if ($overflowFromChallenge) {
                            $this->setGameStateValue('action_data', '');
                            $this->activeNextPlayer();
                            $this->gamestate->nextState("playerTurn");
                        } else {
                            $this->gamestate->nextState("checkWin");
                        }
                    }
                    break;

                default:
                    // For any other activeplayer state, throw an error
                    throw new \feException("Zombie mode not supported at this game state: \"{$stateName}\".");
            }

            return;
        }

        if ($state["type"] === "multipleactiveplayer") {
            // For multipleactiveplayer states, zombie just passes
            switch ($stateName) {
                case "challengeWindow":
                    // Zombie doesn't challenge - pass
                    $currentAction = $this->getGameStateValue('current_action');
                    $nextState = 'resolveAction';
                    if (in_array($currentAction, [Actions::EXTORT, Actions::REAP_SOUL])) {
                        $nextState = 'blockWindow';
                    }
                    $this->gamestate->setPlayerNonMultiactive($activePlayer, $nextState);
                    break;

                case "chooseDiceOverflowFace":
                    // Zombie chooses random face (multipleactiveplayer version)
                    $faces = DiceFaces::getAllFaces();
                    $randomFace = $faces[array_rand($faces)];

                    $this->DbQuery("INSERT INTO satans_pool (face) VALUES ('$randomFace')");
                    $this->setGameStateValue('dice_overflow_player', 0);
                    $this->setGameStateValue('dice_overflow_count', 0);

                    // Check if from challenge or normal action
                    $actionDataJson = $this->getGameStateValue('action_data');
                    $actionData = $actionDataJson ? json_decode((string)$actionDataJson, true) : [];
                    $overflowFromChallenge = isset($actionData['overflow_from_challenge']) && $actionData['overflow_from_challenge'];

                    $nextState = $overflowFromChallenge ? 'playerTurn' : 'checkWin';
                    if ($overflowFromChallenge) {
                        $this->setGameStateValue('action_data', '');
                        $this->activeNextPlayer();
                    }

                    $this->gamestate->setPlayerNonMultiactive($activePlayer, $nextState);
                    break;

                default:
                    throw new \feException("Zombie mode not supported at this game state: \"{$stateName}\".");
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

        // Return the initial game state ID (playerTurn = 2)
        // This replaces the old state 1 (gameSetup) pattern
        return 2;
    }
}
