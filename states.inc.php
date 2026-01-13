<?php
declare(strict_types=1);
/*
 * THIS FILE HAS BEEN AUTOMATICALLY GENERATED. ANY CHANGES MADE DIRECTLY MAY BE OVERWRITTEN.
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * DevilsDice implementation : © Brook Elf Nichols brookelfnichols@gmail.com
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

/**
 * TYPE CHECKING ONLY, this function is never called.
 * If there are any undefined function errors here, you MUST rename the action within the game states file, or create the function in the game class.
 * If the function does not match the parameters correctly, you are either calling an invalid function, or you have incorrectly added parameters to a state function.
 */
if (false) {
	/** @var devilsdice $game */
	$game->stChallengeWindow();
	$game->stResolveChallenge();
	$game->stResolveAction();
	$game->stCheckWin();
	$game->stRolloff();
	$game->stChooseDiceOverflowFace();
}

$machinestates = array(
	1 => array(
		'name' => 'gameSetup',
		'description' => '',
		'type' => 'manager',
		'action' => 'stGameSetup',
		'transitions' => array(
			'playerTurn' => 2,
		),
	),
	2 => array(
		'name' => 'playerTurn',
		'description' => clienttranslate('${actplayer} must choose an action'),
		'descriptionmyturn' => clienttranslate('${you} must choose an action'),
		'type' => 'activeplayer',
		'possibleactions' => ['raiseHell', 'harvestSkulls', 'extort', 'reapSoul', 'pentagram', 'impsSet', 'satansSteal'],
		'transitions' => array(
			'challengeWindow' => 3,
			'resolveAction' => 5,
			'endGame' => 99,
		),
	),
	3 => array(
		'name' => 'challengeWindow',
		'description' => clienttranslate('Players may challenge ${actplayer}s claim'),
		'type' => 'multipleactiveplayer',
		'action' => 'stChallengeWindow',
		'descriptionmyturn' => clienttranslate('Other players may challenge your claim'),
		'possibleactions' => ['challenge', 'pass'],
		'transitions' => array(
			'resolveChallenge' => 4,
			'blockWindow' => 6,
			'resolveAction' => 5,
		),
	),
	4 => array(
		'name' => 'resolveChallenge',
		'description' => '',
		'type' => 'game',
		'action' => 'stResolveChallenge',
		'transitions' => array(
			'playerTurn' => 2,
			'blockWindow' => 6,
			'resolveAction' => 5,
		),
	),
	5 => array(
		'name' => 'resolveAction',
		'description' => '',
		'type' => 'game',
		'action' => 'stResolveAction',
		'transitions' => array(
			'checkWin' => 7,
			'chooseDiceOverflowFace' => 9,
		),
	),
	6 => array(
		'name' => 'blockWindow',
		'description' => clienttranslate('${target_player} may block this action'),
		'type' => 'activeplayer',
		'descriptionmyturn' => clienttranslate('${you} may block this action'),
		'possibleactions' => ['block', 'pass'],
		'transitions' => array(
			'challengeBlock' => 3,
			'resolveAction' => 5,
		),
	),
	7 => array(
		'name' => 'checkWin',
		'description' => '',
		'type' => 'game',
		'action' => 'stCheckWin',
		'transitions' => array(
			'rolloff' => 8,
			'playerTurn' => 2,
			'endGame' => 99,
			'chooseDiceOverflowFace' => 9,
		),
	),
	8 => array(
		'name' => 'rolloff',
		'description' => clienttranslate('Rolloff between tied players'),
		'type' => 'game',
		'action' => 'stRolloff',
		'transitions' => array(
			'endGame' => 99,
		),
	),
	9 => array(
		'name' => 'chooseDiceOverflowFace',
		'description' => clienttranslate('${actplayer} must choose a face to place in Satans pool'),
		'descriptionmyturn' => clienttranslate('${you} must choose a face to place in Satans pool instead of gaining a die'),
		'type' => 'multipleactiveplayer',
		'action' => 'stChooseDiceOverflowFace',
		'args' => 'argChooseDiceOverflowFace',
		'possibleactions' => ['chooseDiceOverflowFace'],
		'transitions' => array(
			'checkWin' => 7,
		),
	),
	99 => array(
		'name' => 'gameEnd',
		'description' => clienttranslate('End of game'),
		'type' => 'manager',
		'action' => 'stGameEnd',
		'args' => 'argGameEnd',
	),
);