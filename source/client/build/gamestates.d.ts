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

declare namespace BGA {

interface DefinedGameStates extends ValidateGameStates<{
	1: {
		'name': 'gameSetup',
		'description': '',
		'type': 'manager',
		'action': 'stGameSetup',
		'transitions': {
			'': 2,
		},
	},
	2: {
		'name': 'dummmy',
		'description': '${actplayer} must play a card or pass',
		'descriptionmyturn': '${you} must play a card or pass',
		'type': 'activeplayer',
		'possibleactions': ['playCard', 'pass'],
		'transitions': {
			'playCard': 2,
			'pass': 2,
		},
	},
	99: {
		'name': 'gameEnd',
		'description': 'End of game',
		'type': 'manager',
		'action': 'stGameEnd',
		'args': 'argGameEnd',
	},
}> {}

interface GameStateArgs {}

interface GameStatePossibleActions {
	'playCard': {
		'card_id': number,
	},
	'pass': {},
}

}