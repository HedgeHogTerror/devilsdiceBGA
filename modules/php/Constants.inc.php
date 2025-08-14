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
    const RAISE_HELL = 'raise_hell';
    const HARVEST_SKULLS = 'harvest_skulls';
    const EXTORT = 'extort';
    const REAP_SOUL = 'reap_soul';
    const PENTAGRAM = 'pentagram';
    const IMPS_SET = 'imps_set';
    const SATANS_STEAL = 'satans_steal';
    const CHALLENGE = 'challenge';
    const BLOCK = 'block';
}
