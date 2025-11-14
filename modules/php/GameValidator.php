<?php
declare(strict_types=1);

namespace Bga\Games\DevilsDice;

/**
 * GameValidator - Simple utility for game state validation
 */
class GameValidator
{
    /**
     * Check for players who have achieved the winning condition
     */
    public static function checkForWinners($game): array
    {
        $players = $game->loadPlayersBasicInfos();
        $winners = [];

        foreach ($players as $playerId => $player) {
            if (self::hasAllSymbols($game, $playerId)) {
                $winners[] = $playerId;
            }
        }

        return $winners;
    }

    /**
     * Check if a player has all required symbols (winning condition)
     */
    public static function hasAllSymbols($game, $playerId): bool
    {
        // Get player's dice
        $playerDice = $game->getCollectionFromDb(
            "SELECT DISTINCT face FROM player_dice WHERE player_id = $playerId AND location = 'hand'"
        );

        // Get Satan's pool dice
        $poolDice = $game->getCollectionFromDb("SELECT DISTINCT face FROM satans_pool");

        // Combine all available faces
        $allFaces = array_merge(array_column($playerDice, 'face'), array_column($poolDice, 'face'));
        $uniqueFaces = array_unique($allFaces);

        // Check if all 6 symbols are present
        $requiredFaces = DiceFaces::getAllFaces();
        return count(array_intersect($uniqueFaces, $requiredFaces)) === 6;
    }
}