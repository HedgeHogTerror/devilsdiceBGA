<?php

declare(strict_types=1);

namespace Bga\Games\DevilsDice;

/**
 * DiceValidator - Simple utility for dice validation
 */
class DiceValidator {
    /**
     * Validates if dice faces contain the required face for an action
     */
    public static function validateActionClaim($action, $diceFaces, $game): bool {
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
                return self::validateImpsSet($diceFaces);
            case Actions::BLOCK:
                $currentAction = $game->getGameStateValue('current_action');
                if ($currentAction == Actions::EXTORT) {
                    return in_array(DiceFaces::TRIDENT, $diceFaces);
                } else if ($currentAction == Actions::REAP_SOUL) {
                    return in_array(DiceFaces::PENTAGRAM, $diceFaces);
                }
                return false;
        }
        return false;
    }

    /**
     * Validates Imp's Set - all imps or all same non-imp face
     */
    private static function validateImpsSet($diceFaces): bool {
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
}
