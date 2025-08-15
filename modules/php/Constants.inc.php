<?php

namespace Bga\Games\DevilsDice;

class States {
    const START = 1;
    const END = 99;

    // Game flow states
    const GAME_SETUP = 10;
    const PLAYER_TURN = 20;
    const CHALLENGE_WINDOW = 21;
    const RESOLVE_CHALLENGE = 22;
    const RESOLVE_ACTION = 23;
    const BLOCK_WINDOW = 24;
    const RESOLVE_BLOCK = 25;
    const CHECK_WIN = 26;
    const ROLLOFF = 27;
}

class DiceFaces {
    const FLAME = 'flame';
    const PENTAGRAM = 'pentagram';
    const SCYTHE = 'scythe';
    const TRIDENT = 'trident';
    const SKULL = 'skull';
    const IMP = 'imp';

    public static function getAllFaces() {
        return [
            self::FLAME,
            self::PENTAGRAM,
            self::SCYTHE,
            self::TRIDENT,
            self::SKULL,
            self::IMP
        ];
    }
}

class Actions {
    const RAISE_HELL = 1;
    const HARVEST_SKULLS = 2;
    const EXTORT = 3;
    const REAP_SOUL = 4;
    const PENTAGRAM = 5;
    const IMPS_SET = 6;
    const SATANS_STEAL = 7;
    const CHALLENGE = 8;
    const BLOCK = 9;

    public static function getActionName($actionId) {
        switch ($actionId) {
            case self::RAISE_HELL:
                return 'raise_hell';
            case self::HARVEST_SKULLS:
                return 'harvest_skulls';
            case self::EXTORT:
                return 'extort';
            case self::REAP_SOUL:
                return 'reap_soul';
            case self::PENTAGRAM:
                return 'pentagram';
            case self::IMPS_SET:
                return 'imps_set';
            case self::SATANS_STEAL:
                return 'satans_steal';
            case self::CHALLENGE:
                return 'challenge';
            case self::BLOCK:
                return 'block';
            default:
                return 'unknown';
        }
    }
}
