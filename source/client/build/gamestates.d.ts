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
			'playerTurn': 2,
		},
	},
	2: {
		'name': 'playerTurn',
		'description': '${actplayer} must choose an action',
		'descriptionmyturn': '${you} must choose an action',
		'type': 'activeplayer',
		'possibleactions': ['raiseHell', 'harvestSkulls', 'extort', 'reapSoul', 'pentagram', 'impsSet', 'satansSteal'],
		'transitions': {
			'challengeWindow': 3,
			'resolveAction': 5,
		},
	},
	3: {
		'name': 'challengeWindow',
		'description': 'Other players may challenge ${action_player_name}\\\'s claim of ${action_name}',
		'type': 'multipleactiveplayer',
		'action': 'stChallengeWindow',
		'args': 'argChallengeWindow',
		'descriptionmyturn': '${you} may challenge ${action_player_name}\\\'s claim of ${action_name}',
		'descriptionmyturninactive': 'Waiting for other players to decide...',
		'possibleactions': ['challenge', 'pass'],
		'transitions': {
			'resolveChallenge': 4,
			'blockWindow': 6,
			'resolveAction': 5,
		},
	},
	4: {
		'name': 'resolveChallenge',
		'description': '',
		'type': 'game',
		'action': 'stResolveChallenge',
		'transitions': {
			'playerTurn': 2,
			'blockWindow': 6,
			'resolveAction': 5,
			'chooseDiceOverflowFace': 9,
		},
	},
	5: {
		'name': 'resolveAction',
		'description': '',
		'type': 'game',
		'action': 'stResolveAction',
		'transitions': {
			'checkWin': 7,
			'chooseDiceOverflowFace': 9,
		},
	},
	6: {
		'name': 'blockWindow',
		'description': '${target_player_name} may block this action',
		'type': 'activeplayer',
		'action': 'stBlockWindow',
		'args': 'argBlockWindow',
		'descriptionmyturn': '${you} may block this action',
		'possibleactions': ['block', 'pass'],
		'transitions': {
			'challengeBlock': 3,
			'resolveAction': 5,
		},
	},
	7: {
		'name': 'checkWin',
		'description': '',
		'type': 'game',
		'action': 'stCheckWin',
		'transitions': {
			'rolloff': 8,
			'playerTurn': 2,
			'chooseDiceOverflowFace': 9,
		},
	},
	8: {
		'name': 'rolloff',
		'description': 'Rolloff between tied players',
		'type': 'game',
		'action': 'stRolloff',
		'transitions': {
			'playerTurn': 2,
		},
	},
	9: {
		'name': 'chooseDiceOverflowFace',
		'description': '${actplayer} must choose a face to place in Satans pool',
		'descriptionmyturn': '${you} must choose a face to place in Satans pool instead of gaining a die',
		'type': 'multipleactiveplayer',
		'action': 'stChooseDiceOverflowFace',
		'args': 'argChooseDiceOverflowFace',
		'possibleactions': ['chooseDiceOverflowFace'],
		'transitions': {
			'checkWin': 7,
			'playerTurn': 2,
		},
	},
}> {}

interface GameStateArgs {
	'argChallengeWindow': object,
	'argBlockWindow': object,
	'argChooseDiceOverflowFace': object,
}

interface GameStatePossibleActions {
	'raiseHell': {},
	'harvestSkulls': {},
	'extort': {
		'targetPlayerId': number,
	},
	'reapSoul': {
		'targetPlayerId': number,
	},
	'pentagram': {},
	'impsSet': {},
	'satansSteal': {
		'targetPlayerId': number,
		'putInPool': boolean,
		'poolFace': string,
	},
	'challenge': {},
	'pass': {},
	'block': {},
	'chooseDiceOverflowFace': {
		'face': string,
	},
}

}