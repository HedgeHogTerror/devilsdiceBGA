<?php

declare(strict_types=1);

namespace Bga\Games\DevilsDice;

/**
 * TokenManager - Simple utility for token operations ok
 * Force refresh 2024-11-27 v2
 */
class TokenManager {
    /**
     * Get skull token count for a player
     */
    public static function getSkullTokenCount($game, $playerId): int {
        return intval($game->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $playerId"));
    }

    /**
     * Check if player has enough skull tokens
     */
    public static function hasEnoughSkullTokens($game, $playerId, $requiredAmount): bool {
        return self::getSkullTokenCount($game, $playerId) >= $requiredAmount;
    }

    /**
     * Add skull tokens to a player and return the new count
     */
    public static function addSkullTokens($game, $playerId, $amount): int {
        $game->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens + $amount WHERE player_id = $playerId");
        return self::getSkullTokenCount($game, $playerId);
    }
}
