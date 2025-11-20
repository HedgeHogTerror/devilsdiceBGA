<?php
declare(strict_types=1);

namespace Bga\Games\DevilsDice;

/**
 * PlayerActions - Handles all player action input methods
 */
class PlayerActions
{
    /**
     * Raise Hell action - player declares they want to raise hell
     */
    public static function raiseHell($game)
    {
        $game->checkAction('raiseHell');
        $playerId = intval($game->getCurrentPlayerId());

        $game->setGameStateValue('current_action', Actions::RAISE_HELL);
        $game->setGameStateValue('current_action_player', $playerId);

        $game->gamestate->nextState('challengeWindow');
    }

    /**
     * Harvest Skulls action - player declares they want to harvest skulls
     */
    public static function harvestSkulls($game)
    {
        $game->checkAction('harvestSkulls');
        $playerId = intval($game->getCurrentPlayerId());

        $game->setGameStateValue('current_action', Actions::HARVEST_SKULLS);
        $game->setGameStateValue('current_action_player', $playerId);

        $game->gamestate->nextState('challengeWindow');
    }

    /**
     * Extort action - player declares they want to extort from target
     */
    public static function extort($game, $targetPlayerId)
    {
        $game->checkAction('extort');
        $playerId = intval($game->getCurrentPlayerId());

        $game->setGameStateValue('current_action', Actions::EXTORT);
        $game->setGameStateValue('current_action_player', $playerId);
        $game->setGameStateValue('current_target_player', $targetPlayerId);

        $game->gamestate->nextState('challengeWindow');
    }

    /**
     * Reap Soul action - player declares they want to reap soul from target
     */
    public static function reapSoul($game, $targetPlayerId)
    {
        $game->checkAction('reapSoul');
        $playerId = intval($game->getCurrentPlayerId());

        // Check if player has enough skull tokens
        if (!TokenManager::hasEnoughSkullTokens($game, $playerId, 2)) {
            throw new \BgaUserException("You need 2 skull tokens to use Reap Soul");
        }

        $game->setGameStateValue('current_action', Actions::REAP_SOUL);
        $game->setGameStateValue('current_action_player', $playerId);
        $game->setGameStateValue('current_target_player', $targetPlayerId);

        $game->gamestate->nextState('challengeWindow');
    }

    /**
     * Pentagram action - player declares they want to use pentagram
     */
    public static function pentagram($game)
    {
        $game->checkAction('pentagram');
        $playerId = intval($game->getCurrentPlayerId());

        $game->setGameStateValue('current_action', Actions::PENTAGRAM);
        $game->setGameStateValue('current_action_player', $playerId);

        $game->gamestate->nextState('challengeWindow');
    }

    /**
     * Imp's Set action - player declares they want to use imp's set
     */
    public static function impsSet($game)
    {
        $game->checkAction('impsSet');
        $playerId = intval($game->getCurrentPlayerId());

        $game->setGameStateValue('current_action', Actions::IMPS_SET);
        $game->setGameStateValue('current_action_player', $playerId);

        // Check if player has 1 or 0 dice - if so, skip challenge window
        $diceCount = intval($game->getUniqueValueFromDB("SELECT COUNT(*) FROM player_dice WHERE player_id = $playerId AND location = 'hand'"));

        if ($diceCount <= 1) {
            // Skip challenge window and go directly to resolution
            $game->gamestate->nextState('resolveAction');
        } else {
            $game->gamestate->nextState('challengeWindow');
        }
    }

    /**
     * Satan's Steal action - player declares they want to use Satan's steal
     */
    public static function satansSteal($game, $targetPlayerId, $putInPool = false, $poolFace = null)
    {
        $game->checkAction('satansSteal');
        $playerId = intval($game->getCurrentPlayerId());

        // Check if player has enough skull tokens
        if (!TokenManager::hasEnoughSkullTokens($game, $playerId, 6)) {
            throw new \BgaUserException("You need 6 skull tokens to use Satan's Steal");
        }

        $game->setGameStateValue('current_action', Actions::SATANS_STEAL);
        $game->setGameStateValue('current_action_player', $playerId);
        $game->setGameStateValue('current_target_player', $targetPlayerId);

        // Store the decision parameters
        $actionData = json_encode([
            'putInPool' => $putInPool,
            'poolFace' => $poolFace
        ]);
        $game->setGameStateValue('action_data', (string)$actionData);

        // Satan's Steal cannot be challenged, go directly to resolution
        $game->gamestate->nextState('resolveAction');
    }

    /**
     * Challenge action - player challenges current action
     */
    public static function challenge($game)
    {
        $game->checkAction('challenge');
        $challengerId = intval($game->getCurrentPlayerId());

        $game->setGameStateValue('challenge_player', $challengerId);
        $game->gamestate->nextState('resolveChallenge');
    }

    /**
     * Block action - player blocks current action
     */
    public static function block($game)
    {
        $game->checkAction('block');
        $blockerId = intval($game->getCurrentPlayerId());

        $currentAction = $game->getGameStateValue('current_action');

        // Only certain actions can be blocked
        if (!in_array($currentAction, [Actions::EXTORT, Actions::REAP_SOUL])) {
            throw new \BgaUserException("This action cannot be blocked");
        }

        // Set up block challenge window
        $game->setGameStateValue('current_action_player', $blockerId);
        $game->setGameStateValue('current_action', Actions::BLOCK);

        $game->gamestate->nextState('challengeBlock');
    }

    /**
     * Pass action - player passes on challenge or block opportunity
     */
    public static function pass($game)
    {
        $game->checkAction('pass');
        $playerId = intval($game->getCurrentPlayerId());

        // DEBUG: Log current state and player info
        $currentState = $game->gamestate->state();
        $stateId = isset($currentState['id']) ? $currentState['id'] : 'unknown';
        $stateName = isset($currentState['name']) ? $currentState['name'] : 'unknown';
        $game->debug("DevilsDice pass(): Player $playerId passing in state $stateId ($stateName)");

        // Player passes on challenge or block opportunity
        // $stateName already set above with safety check

        if ($stateName === 'challengeWindow') {
            // In multipleactiveplayer state, just make this player inactive
            // The BGA framework will automatically transition when all players have acted
            $game->debug("DevilsDice pass(): Setting player $playerId non-multiactive, will transition to resolveAction");
            $game->gamestate->setPlayerNonMultiactive($playerId, 'resolveAction');
            $game->debug("DevilsDice pass(): Player $playerId set non-multiactive");
        } else if ($stateName === 'blockWindow') {
            // Single player block window - can transition immediately
            $game->debug("DevilsDice pass(): Player $playerId not blocking, transitioning to resolveAction");
            $game->gamestate->nextState('resolveAction');
            $game->debug("DevilsDice pass(): Transition to resolveAction completed");
        } else {
            $game->debug("DevilsDice pass(): Cannot pass in current state: $stateName");
            throw new \BgaUserException("Cannot pass in current state: $stateName");
        }
    }

    /**
     * Choose dice overflow face - player chooses which face to put in Satan's pool
     */
    public static function chooseDiceOverflowFace($game, $face)
    {
        $game->checkAction('chooseDiceOverflowFace');
        $playerId = intval($game->getCurrentPlayerId());

        // Validate the face
        $validFaces = \Bga\Games\DevilsDice\DiceFaces::getAllFaces();
        if (!in_array($face, $validFaces)) {
            throw new \BgaUserException("Invalid dice face");
        }

        // Get the overflow info
        $overflowPlayer = $game->getGameStateValue('dice_overflow_player');

        // Verify this is the correct player
        if ($playerId != $overflowPlayer) {
            throw new \BgaUserException("Only the player who caused the overflow can choose the face");
        }

        // Add the dice to Satan's pool instead
        $game->DbQuery("INSERT INTO satans_pool (face) VALUES ('$face')");

        // Clear overflow flags
        $game->setGameStateValue('dice_overflow_player', 0);
        $game->setGameStateValue('dice_overflow_count', 0);

        // Send notification
        $game->notifyAllPlayers(
            'diceToSatansPool',
            clienttranslate('${player} chooses ${face} to place in Satan\'s pool instead of gaining a die'),
            [
                'player' => $game->getPlayerNameById($playerId),
                'playerId' => $playerId,
                'face' => $face,
                'dice' => DiceManager::getPlayerDice($game, $playerId),
                'diceCount' => intval($game->getUniqueValueFromDB("SELECT COUNT(dice_id) FROM player_dice WHERE player_id = $playerId AND location = 'hand'"))
            ]
        );

        $game->gamestate->nextState('checkWin');
    }
}