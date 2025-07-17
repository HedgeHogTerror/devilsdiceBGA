<?php

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * DevilsDice implementation : © hedgehogterror, brookelfnichols@gmail.com
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * states.inc.php
 *
 * DevilsDice game states description
 *
 */

require_once("modules/php/Constants.inc.php");

//    !! It is not a good idea to modify this file when a game is running !!
$machinestates = [
    // The initial state. Please do not modify.
    States::START => [
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => ["" => States::DUMMY]
    ],

    States::DUMMY => [
        "name" => "getReady",
        "type" => "activeplayer",
        "description" => "",
        "descriptionmyturn" => "",
        "possibleactions" => [],
        "transition" => ["endGame" => States::END]
    ],

    // Final state.
    // Please do not modify (and do not overload action/args methods).
    States::END => [
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd"
    ],

];
