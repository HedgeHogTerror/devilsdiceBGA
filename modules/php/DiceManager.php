<?php
declare(strict_types=1);

namespace Bga\Games\DevilsDice;

/**
 * DiceManager - Simple utility for dice operations
 */
class DiceManager
{
    /**
     * Get dice data for a player
     */
    public static function getPlayerDice($game, $playerId): array
    {
        return $game->getCollectionFromDb(
            "SELECT dice_id, face FROM player_dice WHERE player_id = $playerId AND location = 'hand'"
        );
    }

    /**
     * Reroll all existing dice for a player and send notification
     */
    public static function rollDiceForPlayer($game, $playerId)
    {
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
        $game->debug("DevilsDice rollDiceForPlayer: Player $playerId rerolled $currentCount dice");

        $game->notifyAllPlayers('diceRerolled', '', [
            'playerId' => $playerId,
            'dice' => $playerDice,
            'diceCount' => $currentCount
        ]);
    }
}