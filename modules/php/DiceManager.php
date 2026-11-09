<?php

declare(strict_types=1);

namespace Bga\Games\DevilsDice;

/**
 * DiceManager - Simple utility for dice operations ok
 * Force refresh 2024-11-27 v2
 */
class DiceManager {
    /**
     * Get dice data for a player
     */
    public static function getPlayerDice($game, $playerId): array {
        return $game->getCollectionFromDb(
            "SELECT dice_id, face FROM player_dice WHERE player_id = $playerId AND location = 'hand'"
        );
    }

    /**
     * Reroll all existing dice for a player and send notification
     */
    public static function rollDiceForPlayer($game, $playerId) {
        $currentCount = intval($game->getUniqueValueFromDB("SELECT COUNT(*) FROM player_dice WHERE player_id = $playerId AND location = 'hand'"));

        if ($currentCount > 0) {
            // Delete existing dice
            $game->DbQuery("DELETE FROM player_dice WHERE player_id = $playerId AND location = 'hand'");

            // Roll new dice with new faces
            $faces = \Bga\Games\DevilsDice\DiceFaces::getAllFaces();
            for ($i = 0; $i < $currentCount; $i++) {
                $randomFace = $faces[array_rand($faces)];
                $game->DbQuery("INSERT INTO player_dice (player_id, face, location) VALUES ($playerId, '$randomFace', 'hand')");
            }
        }

        // Get the new dice data and send notification
        $playerDice = self::getPlayerDice($game, $playerId);
        // Get the actual current count after reroll (not the cached $currentCount from before)
        $actualCount = count($playerDice);
        $game->debug("DevilsDice rollDiceForPlayer: Player $playerId rerolled to $actualCount dice (was $currentCount before reroll)");

        $game->notifyAllPlayers('diceRerolled', '', [
            'playerId' => $playerId,
            'dice' => $playerDice,
            'diceCount' => $actualCount
        ]);
    }
}
