<?php

declare(strict_types=1);

namespace Bga\Games\DevilsDice;

/**
 * ActionExecutor - Handles execution of all game actions ok v2
 */
class ActionExecutor {
    /**
     * Execute Raise Hell action
     */
    public static function executeRaiseHell($game, $playerId) {
        // Add 1 skull token
        $newTokenCount = TokenManager::addSkullTokens($game, $playerId, 1);

        $game->notifyAllPlayers(
            'raiseHell',
            clienttranslate('${player} raises hell and rerolls their dice'),
            [
                'player' => $game->getPlayerNameById($playerId),
                'playerId' => $playerId,
                'tokens' => $newTokenCount,
                'diceCount' => $game->getUniqueValueFromDB("SELECT COUNT(*) FROM player_dice WHERE player_id = $playerId AND location = 'hand'")
            ]
        );

        // Reroll all of player's current dice
        DiceManager::rollDiceForPlayer($game, $playerId);
    }

    /**
     * Execute Harvest Skulls action
     */
    public static function executeHarvestSkulls($game, $playerId) {
        // Add 2 skull tokens
        $newTokenCount = TokenManager::addSkullTokens($game, $playerId, 2);

        $game->notifyAllPlayers(
            'harvestSkulls',
            clienttranslate('${player} harvests 2 skull tokens'),
            [
                'player' => $game->getPlayerNameById($playerId),
                'playerId' => $playerId,
                'tokens' => $newTokenCount
            ]
        );
    }

    /**
     * Execute Extort action
     */
    public static function executeExtort($game, $playerId, $targetId) {
        // Transfer 3 skull tokens from target to player
        $targetTokens = $game->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $targetId");
        $tokensToSteal = min(3, $targetTokens);

        $game->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens - $tokensToSteal WHERE player_id = $targetId");
        $game->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens + $tokensToSteal WHERE player_id = $playerId");

        // Get updated token counts for both players
        $playerNewTokens = $game->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $playerId");
        $targetNewTokens = $game->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $targetId");

        $game->notifyAllPlayers(
            'extort',
            clienttranslate('${player} extorts ${tokens} skull tokens from ${target}'),
            [
                'player' => $game->getPlayerNameById($playerId),
                'target' => $game->getPlayerNameById($targetId),
                'playerId' => $playerId,
                'targetId' => $targetId,
                'tokens' => $tokensToSteal,
                'playerNewTokens' => $playerNewTokens,
                'targetNewTokens' => $targetNewTokens
            ]
        );
    }

    /**
     * Execute Reap Soul action
     */
    public static function executeReapSoul($game, $playerId, $targetId) {
        // Pay 2 skull tokens
        $game->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens - 2 WHERE player_id = $playerId");

        // Steal a die from target
        $game->stealDiceFromPlayer($playerId, $targetId);

        $game->notifyAllPlayers(
            'reapSoul',
            clienttranslate('${player} reaps a soul from ${target}'),
            [
                'player' => $game->getPlayerNameById($playerId),
                'target' => $game->getPlayerNameById($targetId),
                'playerId' => $playerId,
                'targetId' => $targetId
            ]
        );
    }

    /**
     * Execute Pentagram action
     */
    public static function executePentagram($game, $playerId) {
        // Get existing dice in Satan's pool before rerolling
        $poolDice = $game->getCollectionFromDb("SELECT dice_id FROM satans_pool");
        $faces = \Bga\Games\DevilsDice\DiceFaces::getAllFaces();
        $pentagramsRolled = 0;
        $pentagramDiceIds = [];

        // Reroll each die in Satan's pool
        foreach ($poolDice as $diceRow) {
            // getCollectionFromDb returns rows as associative arrays
            $diceId = isset($diceRow['dice_id']) ? intval($diceRow['dice_id']) : null;
            if ($diceId === null) {
                continue;
            }

            $newFace = $faces[array_rand($faces)];
            $game->DbQuery("UPDATE satans_pool SET face = '$newFace' WHERE dice_id = $diceId");

            if ($newFace === \Bga\Games\DevilsDice\DiceFaces::PENTAGRAM) {
                $pentagramsRolled++;
                $pentagramDiceIds[] = $diceId;
            }
        }

        // Remove pentagram dice from Satan's pool (player gains up to 1)
        if ($pentagramsRolled > 0) {
            // Remove the first pentagram die from Satan's pool
            $diceIdToRemove = $pentagramDiceIds[0];
            $game->DbQuery("DELETE FROM satans_pool WHERE dice_id = $diceIdToRemove");

            // Add 1 die to player with pentagram face (check for overflow)
            $game->addOrRemoveDice($playerId, 1);

            // Set the new die to pentagram
            $game->DbQuery("UPDATE player_dice SET face = '" . \Bga\Games\DevilsDice\DiceFaces::PENTAGRAM . "' WHERE player_id = $playerId AND location = 'hand' ORDER BY dice_id DESC LIMIT 1");
        }

        // Get the updated Satan's pool data for the notification (after removing pentagram)
        $updatedSatansPool = $game->getCollectionFromDb("SELECT dice_id, face FROM satans_pool");

        // Send notification
        if ($pentagramsRolled > 0) {
            $game->notifyAllPlayers(
                'pentagram',
                clienttranslate('${player} rerolls Satan\'s pool and gains one pentagram dice'),
                [
                    'player' => $game->getPlayerNameById($playerId),
                    'playerId' => $playerId,
                    'pentagrams' => 1,
                    'satansPool' => $updatedSatansPool
                ]
            );
        } else {
            $game->notifyAllPlayers(
                'pentagram',
                clienttranslate('${player} rerolls Satan\'s pool and did not roll any pentagrams'),
                [
                    'player' => $game->getPlayerNameById($playerId),
                    'playerId' => $playerId,
                    'pentagrams' => 0,
                    'satansPool' => $updatedSatansPool
                ]
            );
        }
    }

    /**
     * Execute Imp's Set action
     */
    public static function executeImpsSet($game, $playerId) {
        $game->debug("🎲🎲🎲 ActionExecutor::executeImpsSet: ENTERED for player $playerId 🎲🎲🎲");

        $game->notifyAllPlayers(
            'impsSet',
            clienttranslate('${player} uses Imp\'s Set to gain a die and rerolls all dice'),
            [
                'player' => $game->getPlayerNameById($playerId),
                'playerId' => $playerId
            ]
        );
        $game->debug("🎲 executeImpsSet: Notification sent, about to call addOrRemoveDice");

        // Add 1 die and reroll all dice (check for overflow)
        $game->addOrRemoveDice($playerId, 1);
        $game->debug("🎲 executeImpsSet: addOrRemoveDice completed - FUNCTION COMPLETE");
    }

    /**
     * Execute Satan's Steal action
     */
    public static function executeSatansSteal($game, $playerId) {
        $targetId = $game->getGameStateValue('current_target_player');

        // Check if we have action data (from decision state)
        $actionDataJson = $game->getGameStateValue('action_data');
        $putInPool = false;
        $poolFace = null;

        if ($actionDataJson && is_string($actionDataJson)) {
            $actionData = json_decode($actionDataJson, true);
            $putInPool = $actionData['putInPool'] ?? false;
            $poolFace = $actionData['poolFace'] ?? null;
        }

        // Pay 6 skull tokens
        $game->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens - 6 WHERE player_id = $playerId");

        // Get updated token count for the player
        $playerNewTokens = $game->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $playerId");

        // Optionally put it in Satan's pool on chosen face
        if ($putInPool && $poolFace) {
            $game->moveDiceToSatansPool($targetId, $poolFace);
        } else {
            // Steal a die from target (correct parameter order: stealer, victim)
            $game->stealDiceFromPlayer($playerId, $targetId);
        }

        $game->notifyAllPlayers(
            'satansSteal',
            clienttranslate('${player} uses Satan\'s Steal on ${target}'),
            [
                'player' => $game->getPlayerNameById($playerId),
                'target' => $game->getPlayerNameById($targetId),
                'playerId' => $playerId,
                'targetId' => $targetId,
                'playerNewTokens' => $playerNewTokens
            ]
        );
    }
}
