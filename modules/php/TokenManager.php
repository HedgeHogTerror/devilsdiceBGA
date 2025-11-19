<?php
declare(strict_types=1);

namespace Bga\Games\DevilsDice;

/**
 * TokenManager - Simple utility for token operations
 */
class TokenManager
{
    /**
     * Get skull token count for a player
     */
    public static function getSkullTokens($game, $playerId): int
    {
        return intval($game->getUniqueValueFromDB("SELECT skull_tokens FROM player_tokens WHERE player_id = $playerId"));
    }

    /**
     * Add skull tokens to a player
     */
    public static function addSkullTokens($game, $playerId, $amount): int
    {
        $game->DbQuery("UPDATE player_tokens SET skull_tokens = skull_tokens + $amount WHERE player_id = $playerId");
        return self::getSkullTokens($game, $playerId);
    }

    /**
     * Check if player has enough skull tokens
     */
    public static function hasEnoughTokens($game, $playerId, $requiredAmount): bool
    {
        return self::getSkullTokens($game, $playerId) >= $requiredAmount;
    }
}