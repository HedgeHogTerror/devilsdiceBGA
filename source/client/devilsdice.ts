/// <amd-module name="bgagame/devilsdice"/>

import 'ebg/counter';
import * as GameGui from 'ebg/core/gamegui';
import * as dom from 'dojo/dom';

// Type definitions for better type safety
interface GameData extends BGA.Gamedatas {
    playerTokens: Record<string, {player_id: string; skull_tokens: string}> | Record<string, number>;
    playerDiceCounts: Record<string, {player_id: string; dice_count: string}> | Record<string, number>;
    myDice: Record<string, string>;
    satansPool: Record<string, string>;
    currentAction?: string;
    currentActionPlayer?: number;
    currentTargetPlayer?: number;
}

interface NotificationArgs {
    playerId?: number;
    dice?: Record<string, string>;
    diceCount?: number;
    challengerId?: number;
    actionPlayerId?: number;
    stealerId?: number;
    victimId?: number;
    targetId?: number;
    tokens?: number;
    face?: string;
    pentagrams?: number;
    imps?: number;
}

interface DiceSymbols {
    [key: string]: string;
}

// Constants
const DICE_FACES = {
    FLAME: 'flame',
    PENTAGRAM: 'pentagram',
    SCYTHE: 'scythe',
    TRIDENT: 'trident',
    SKULL: 'skull',
    IMP: 'imp',
} as const;

const GAME_STATES = {
    PLAYER_TURN: 'playerTurn',
    CHALLENGE_WINDOW: 'challengeWindow',
    BLOCK_WINDOW: 'blockWindow',
} as const;

/**
 * Client implementation of DevilsDice.
 */
export default class DevilsDice extends (GameGui as any) {
    // Enable dynamic method calls for BGA framework
    [key: string]: any;

    // Game state data
    private playerTokens: Record<string, number> = {};
    private playerDiceCounts: Record<string, number> = {};
    private myDice: Record<string, string> = {};
    private satansPool: Record<string, string> = {};
    private targetSelectionCallback: ((playerId: string) => void) | null = null;

    // UI elements cache
    private uiElements: {
        myDice?: HTMLElement | null;
        satansPool?: HTMLElement | null;
    } = {};

    constructor() {
        super();
        console.log('DevilsDice constructor initialized');
    }

    setup(gamedatas: GameData): void {
        console.log('Starting game setup', gamedatas);

        // Store game data with proper typing and convert format
        this.playerTokens = {};
        if (gamedatas.playerTokens) {
            console.log('Raw playerTokens data:', gamedatas.playerTokens);

            // Handle both formats: direct key-value pairs or array of objects
            for (const [key, entry] of Object.entries(gamedatas.playerTokens)) {
                if (typeof entry === 'object' && entry !== null && 'player_id' in entry && 'skull_tokens' in entry) {
                    // Format: {player_id: "123", skull_tokens: "1"}
                    this.playerTokens[entry['player_id']] = parseInt(entry['skull_tokens']);
                    console.log(`Converted player ${entry.player_id} skull tokens: ${entry['skull_tokens']}`);
                } else if (typeof entry === 'number') {
                    // Format: {"123": 1}
                    this.playerTokens[key] = entry;
                    console.log(`Direct player ${key} skull tokens: ${entry}`);
                } else if (typeof entry === 'string') {
                    // Format: {"123": "1"}
                    this.playerTokens[key] = parseInt(entry);
                    console.log(`String player ${key} skull tokens: ${entry}`);
                }
            }
        }

        console.log('Final playerTokens:', this.playerTokens);

        // Convert playerDiceCounts from array format to simple key-value mapping
        this.playerDiceCounts = {};
        if (gamedatas.playerDiceCounts) {
            console.log('Raw playerDiceCounts data:', gamedatas.playerDiceCounts);

            // Handle both formats: direct key-value pairs or array of objects
            for (const [key, entry] of Object.entries(gamedatas.playerDiceCounts)) {
                if (typeof entry === 'object' && entry !== null && 'player_id' in entry && 'dice_count' in entry) {
                    // Format: {player_id: "123", dice_count: "2"}
                    this.playerDiceCounts[entry.player_id] = parseInt(entry.dice_count);
                    console.log(`Converted player ${entry.player_id} dice count: ${entry.dice_count}`);
                } else if (typeof entry === 'number') {
                    // Format: {"123": 2}
                    this.playerDiceCounts[key] = entry;
                    console.log(`Direct player ${key} dice count: ${entry}`);
                } else if (typeof entry === 'string') {
                    // Format: {"123": "2"}
                    this.playerDiceCounts[key] = parseInt(entry);
                    console.log(`String player ${key} dice count: ${entry}`);
                }
            }
        }

        console.log('Final playerDiceCounts:', this.playerDiceCounts);

        this.myDice = gamedatas.myDice || {};
        this.satansPool = gamedatas.satansPool || {};

        // Initialize UI
        this.createGameBoard();
        this.cacheUIElements();
        this.updateDisplay();
        this.setupNotifications();

        console.log('Game setup completed');
    }

    onEnteringState(stateName: string, args: any): void {
        console.log(`Entering state: ${stateName}`, args);

        // Call state-specific handler if it exists
        const methodName = `enteringState_${stateName}`;
        if (typeof this[methodName] === 'function') {
            this[methodName](args);
        }

        // Update UI based on state
        this.updateUIForState(stateName);
    }

    onLeavingState(stateName: string): void {
        console.log(`Leaving state: ${stateName}`);

        // Call state-specific cleanup if it exists
        const methodName = `leavingState_${stateName}`;
        if (typeof this[methodName] === 'function') {
            this[methodName]();
        }

        // Clean up any temporary UI elements
        this.cleanupStateUI();
    }

    onUpdateActionButtons(stateName: string, args: any): void {
        console.log(`Updating action buttons for state: ${stateName}`, args);

        if (!this['isCurrentPlayerActive']()) return;

        switch (stateName) {
            case GAME_STATES.PLAYER_TURN:
                this.addPlayerTurnButtons();
                break;
            case GAME_STATES.CHALLENGE_WINDOW:
                this.addChallengeButtons();
                break;
            case GAME_STATES.BLOCK_WINDOW:
                this.addBlockButtons();
                break;
        }
    }

    ///////////////////////////////////////////////////
    //// State Handling

    private enteringState_playerTurn(args: any): void {
        this.highlightCurrentPlayer();
        this.updateActionAvailability();
    }

    private enteringState_challengeWindow(args: any): void {
        this.showChallengePrompt();
    }

    private enteringState_blockWindow(args: any): void {
        this.showBlockPrompt();
    }

    private leavingState_challengeWindow(): void {
        this.hideChallengePrompt();
    }

    private leavingState_blockWindow(): void {
        this.hideBlockPrompt();
    }

    ///////////////////////////////////////////////////
    //// Action Button Management

    private addPlayerTurnButtons(): void {
        const actions = [
            {id: 'raiseHell', label: _('Raise Hell (Flame)'), handler: 'onRaiseHell'},
            {id: 'harvestSkulls', label: _('Harvest Skulls (Skull)'), handler: 'onHarvestSkulls'},
            {id: 'extort', label: _('Extort (Trident)'), handler: 'onExtort'},
            {id: 'reapSoul', label: _('Reap Soul (Scythe)'), handler: 'onReapSoul'},
            {id: 'pentagram', label: _('Pentagram'), handler: 'onPentagram'},
            {id: 'impsSet', label: _("Imp's Set"), handler: 'onImpsSet'},
            {id: 'satansSteal', label: _("Satan's Steal"), handler: 'onSatansSteal'},
        ];

        actions.forEach((action) => {
            this['addActionButton'](`${action.id}_btn`, action.label, action.handler);
        });
    }

    private addChallengeButtons(): void {
        this['addActionButton']('challenge_btn', _('Challenge'), 'onChallenge');
        this['addActionButton']('pass_btn', _('Pass'), 'onPass');
    }

    private addBlockButtons(): void {
        this['addActionButton']('block_btn', _('Block'), 'onBlock');
        this['addActionButton']('pass_btn', _('Pass'), 'onPass');
    }

    ///////////////////////////////////////////////////
    //// Player Actions

    onRaiseHell(): void {
        if (this.validateAction('raiseHell')) {
            this['bgaPerformAction']('raiseHell');
        }
    }

    onHarvestSkulls(): void {
        if (this.validateAction('harvestSkulls')) {
            this['bgaPerformAction']('harvestSkulls');
        }
    }

    onExtort(): void {
        if (this.validateAction('extort')) {
            this.selectTarget((playerId: string) => {
                this['bgaPerformAction']('extort', {targetPlayerId: parseInt(playerId)});
            });
        }
    }

    onReapSoul(): void {
        if (this.validateAction('reapSoul')) {
            this.selectTarget((playerId: string) => {
                this['bgaPerformAction']('reapSoul', {targetPlayerId: parseInt(playerId)});
            });
        }
    }

    onPentagram(): void {
        if (this.validateAction('pentagram')) {
            this['bgaPerformAction']('pentagram');
        }
    }

    onImpsSet(): void {
        if (this.validateAction('impsSet')) {
            this['bgaPerformAction']('impsSet');
        }
    }

    onSatansSteal(): void {
        if (this.validateAction('satansSteal')) {
            this.selectTarget((playerId: string) => {
                // TODO: Add UI for choosing whether to put in pool and face
                this['bgaPerformAction']('satansSteal', {
                    targetPlayerId: parseInt(playerId),
                    putInPool: false,
                    poolFace: null,
                });
            });
        }
    }

    onChallenge(): void {
        this['bgaPerformAction']('challenge');
    }

    onBlock(): void {
        this['bgaPerformAction']('block');
    }

    onPass(): void {
        this['bgaPerformAction']('pass');
    }

    ///////////////////////////////////////////////////
    //// Notification Handling

    setupNotifications(): void {
        console.log('Setting up notifications');

        // Use the BGA framework's notification setup
        this['bgaSetupPromiseNotifications']({
            diceRolled: 'notification_diceRolled',
            diceCountUpdate: 'notification_diceCountUpdate',
            gameSetupComplete: 'notification_gameSetupComplete',
            challengeSuccessful: 'notification_challengeSuccessful',
            challengeFailed: 'notification_challengeFailed',
            diceStolen: 'notification_diceStolen',
            diceToSatansPool: 'notification_diceToSatansPool',
            raiseHell: 'notification_raiseHell',
            harvestSkulls: 'notification_harvestSkulls',
            extort: 'notification_extort',
            reapSoul: 'notification_reapSoul',
            pentagram: 'notification_pentagram',
            impsSet: 'notification_impsSet',
            satansSteal: 'notification_satansSteal',
            rolloffWinner: 'notification_rolloffWinner',
        });
    }

    notification_diceRolled = (notif: {args: NotificationArgs}): void => {
        console.log('Dice rolled notification', notif);
        const playerId = notif.args.playerId;
        const dice = notif.args.dice || {};

        // Update the dice count for the specific player
        if (playerId) {
            this.playerDiceCounts[playerId] = Object.keys(dice).length;
            this.updatePlayersInfo(); // Refresh the display for all players
        }

        if (playerId === this['player_id']) {
            this.myDice = dice;
            this.updateMyDice();
        }
    };

    notification_diceCountUpdate = (notif: {args: NotificationArgs}): void => {
        console.log('Dice count update notification', notif);
        const playerId = notif.args.playerId;
        const diceCount = notif.args.diceCount;

        if (playerId && diceCount !== undefined) {
            this.playerDiceCounts[playerId] = diceCount;
            this.updatePlayersInfo();
        }
    };

    notification_gameSetupComplete = (notif: {args: NotificationArgs}): void => {
        console.log('Game setup complete, refreshing display');
        this.updateDisplay();
    };

    notification_challengeSuccessful = (notif: {args: NotificationArgs}): void => {
        console.log('Challenge successful', notif);
        this.showMessage(_('Challenge successful!'), 'info');
        this.updateDisplay();
    };

    notification_challengeFailed = (notif: {args: NotificationArgs}): void => {
        console.log('Challenge failed', notif);
        this.showMessage(_('Challenge failed!'), 'error');
        this.updateDisplay();
    };

    notification_diceStolen = (notif: {args: NotificationArgs}): void => {
        console.log('Dice stolen', notif);
        this.updateDisplay();
    };

    notification_diceToSatansPool = (notif: {args: NotificationArgs}): void => {
        console.log("Dice to Satan's pool", notif);
        this.updateSatansPool();
    };

    notification_raiseHell = (notif: {args: NotificationArgs}): void => {
        console.log('Raise Hell action', notif);
        this.updateDisplay();
    };

    notification_harvestSkulls = (notif: {args: NotificationArgs}): void => {
        console.log('Harvest Skulls action', notif);
        this.updateDisplay();
    };

    notification_extort = (notif: {args: NotificationArgs}): void => {
        console.log('Extort action', notif);
        this.updateDisplay();
    };

    notification_reapSoul = (notif: {args: NotificationArgs}): void => {
        console.log('Reap Soul action', notif);
        this.updateDisplay();
    };

    notification_pentagram = (notif: {args: NotificationArgs}): void => {
        console.log('Pentagram action', notif);
        this.updateDisplay();
    };

    notification_impsSet = (notif: {args: NotificationArgs}): void => {
        console.log("Imp's Set action", notif);
        this.updateDisplay();
    };

    notification_satansSteal = (notif: {args: NotificationArgs}): void => {
        console.log("Satan's Steal action", notif);
        this.updateDisplay();
    };

    notification_rolloffWinner = (notif: {args: NotificationArgs}): void => {
        console.log('Rolloff winner', notif);
        this.showMessage(_('Rolloff completed!'), 'info');
    };

    ///////////////////////////////////////////////////
    //// UI Management

    private createGameBoard(): void {
        const gameArea = dom.byId('game_play_area');
        if (!gameArea) return;

        gameArea.insertAdjacentHTML(
            'beforeend',
            `
            <div id="board">
                <div id="my-dice-area">
                    <h3>${_('My Dice')}</h3>
                    <div id="my-dice"></div>
                </div>
                
                <div id="satans-pool-area">
                    <h3>${_("Satan's Pool")}</h3>
                    <img src="img/Devils_Dice_board.png" alt="Satan" class="satan-image" />
                    <div id="satans-pool"></div>
                </div>
                
                <div id="game-status">
                    <div id="current-action-info"></div>
                    <div id="challenge-prompt" style="display: none;"></div>
                    <div id="block-prompt" style="display: none;"></div>
                </div>
            </div>
        `
        );
    }

    private cacheUIElements(): void {
        this.uiElements = {
            myDice: dom.byId('my-dice'),
            satansPool: dom.byId('satans-pool'),
        };
    }

    private updateDisplay(): void {
        this.updatePlayersInfo();
        this.updateMyDice();
        this.updateSatansPool();
    }

    private updatePlayersInfo(): void {
        if (!this['gamedatas']?.players) return;

        // Update BGA player panels with dice count and skull tokens
        for (const playerId in this['gamedatas'].players) {
            const tokens = this.playerTokens[playerId] || 0;
            const diceCount = this.playerDiceCounts[playerId] || 0;

            // Find the player panel
            const playerPanel = dom.byId(`player_board_${playerId}`);
            if (playerPanel) {
                // Remove existing game info if it exists
                const existingInfo = playerPanel.querySelector('.player-game-info');
                if (existingInfo) {
                    existingInfo.remove();
                }

                // Add new game info to player panel
                const gameInfo = document.createElement('div');
                gameInfo.className = 'player-game-info';
                gameInfo.innerHTML = `
                    <div class="player-skull-tokens">💀 ${tokens}</div>
                    <div class="player-dice-count">🎲 ${diceCount}</div>
                    ${this.targetSelectionCallback ? `<button class="target-btn" data-player-id="${playerId}">${_('Select')}</button>` : ''}
                `;

                // Add to player panel
                playerPanel.appendChild(gameInfo);

                // Add target selection event listener if needed
                if (this.targetSelectionCallback) {
                    const targetBtn = gameInfo.querySelector('.target-btn') as HTMLElement;
                    if (targetBtn) {
                        targetBtn.addEventListener('click', (e: Event) => {
                            const target = e.target as HTMLElement;
                            const playerId = target.dataset['playerId'];
                            if (playerId && this.targetSelectionCallback) {
                                this.targetSelectionCallback(playerId);
                                this.targetSelectionCallback = null;
                                this.updatePlayersInfo(); // Remove selection buttons
                            }
                        });
                    }
                }
            }
        }
    }

    private updateMyDice(): void {
        const myDiceArea = this.uiElements.myDice;
        if (!myDiceArea) return;

        let html = '';
        for (const diceId in this.myDice) {
            const face = this.myDice[diceId];
            if (face) {
                html += `<div class="die my-die" data-face="${face}" data-dice-id="${diceId}">
                    ${this.getDiceSymbol(face)}
                </div>`;
            }
        }

        myDiceArea.innerHTML = html;
    }

    private updateSatansPool(): void {
        const satansPoolArea = this.uiElements.satansPool;
        if (!satansPoolArea) return;

        let html = '';
        for (const diceId in this.satansPool) {
            const face = this.satansPool[diceId];
            if (face) {
                html += `<div class="die pool-die" data-face="${face}" data-dice-id="${diceId}">
                    ${this.getDiceSymbol(face)}
                </div>`;
            }
        }

        satansPoolArea.innerHTML = html;
    }

    ///////////////////////////////////////////////////
    //// Utility Functions

    private getDiceSymbol(face: string): string {
        const symbols: DiceSymbols = {
            [DICE_FACES.FLAME]: '🔥',
            [DICE_FACES.PENTAGRAM]: '⭐',
            [DICE_FACES.SCYTHE]: '⚔️',
            [DICE_FACES.TRIDENT]: '🔱',
            [DICE_FACES.SKULL]: '💀',
            [DICE_FACES.IMP]: '👹',
        };
        return symbols[face] || '?';
    }

    private selectTarget(callback: (playerId: string) => void): void {
        const availablePlayers = Object.keys(this['gamedatas']?.players || {}).filter((id) => id !== this['player_id'].toString());

        if (availablePlayers.length === 0) {
            console.error('No available targets');
            return;
        }

        if (availablePlayers.length === 1) {
            const playerId = availablePlayers[0];
            if (playerId) {
                callback(playerId);
            }
            return;
        }

        // Show target selection UI
        this.targetSelectionCallback = callback;
        this.updatePlayersInfo();
        this.showMessage(_('Select a target player'), 'info');
    }

    private validateAction(action: string): boolean {
        // Add client-side validation logic here
        // For now, just return true
        return true;
    }

    private updateUIForState(stateName: string): void {
        const board = dom.byId('board');
        if (board) {
            board.className = `state-${stateName}`;
        }
    }

    private cleanupStateUI(): void {
        this.targetSelectionCallback = null;
    }

    private highlightCurrentPlayer(): void {
        // Add visual indication of current player
        this.updatePlayersInfo();
    }

    private updateActionAvailability(): void {
        // Update which actions are available based on current game state
        // This could disable buttons based on resources, etc.
    }

    private showChallengePrompt(): void {
        const prompt = dom.byId('challenge-prompt');
        if (prompt) {
            prompt.style.display = 'block';
            prompt.innerHTML = _('You may challenge this action');
        }
    }

    private hideChallengePrompt(): void {
        const prompt = dom.byId('challenge-prompt');
        if (prompt) {
            prompt.style.display = 'none';
        }
    }

    private showBlockPrompt(): void {
        const prompt = dom.byId('block-prompt');
        if (prompt) {
            prompt.style.display = 'block';
            prompt.innerHTML = _('You may block this action');
        }
    }

    private hideBlockPrompt(): void {
        const prompt = dom.byId('block-prompt');
        if (prompt) {
            prompt.style.display = 'none';
        }
    }

    private showMessage(message: string, type: 'info' | 'error' | 'success' = 'info'): void {
        // Use BGA's built-in message system
        console.log(`${type.toUpperCase()}: ${message}`);
        // You could also use this.showMessage() if available in the BGA framework
    }
}

// BGA framework registration
require(['dojo/_base/declare'], function (declare) {
    declare('bgagame.devilsdice', GameGui, new DevilsDice());
});
