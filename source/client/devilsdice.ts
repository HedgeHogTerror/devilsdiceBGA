/// <amd-module name="bgagame/devilsdice"/>

import 'ebg/counter';
import * as GameGui from 'ebg/core/gamegui';
import * as dom from 'dojo/dom';
import {GameObjectAnimator} from './animator';

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
    playerNewTokens?: number;
    targetNewTokens?: number;
    face?: string;
    pentagrams?: number;
    imps?: number;
    satansPool?: Record<string, string>;
    rolledSatansPool?: Record<string, string>;
    gainedDiceId?: number | string | null;
    response?: 'pass' | 'challenge' | 'block';
    winnerId?: string;
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
    SATANS_STEAL_DECISION: 'satansStealDecision',
    CHOOSE_DICE_OVERFLOW_FACE: 'chooseDiceOverflowFace',
    ROLLOFF: 'rolloff',
    GAME_WON: 'gameWon',
    GAME_SETUP_COMPLETE: 'gameSetupComplete',
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

    // Animation system
    private animator!: GameObjectAnimator;
    private pendingTransferAnimation: Promise<void> | null = null;

    // UI elements cache
    private uiElements: {
        myDice?: HTMLElement | null;
        satansPool?: HTMLElement | null;
    } = {};

    constructor() {
        super();
    }

    setup(gamedatas: GameData): void {
        console.log('Starting game setup', gamedatas);

        // Initialize animator
        this.animator = new GameObjectAnimator(g_gamethemeurl);

        // Store game data with proper typing and convert format
        this.playerTokens = {};
        if (gamedatas.playerTokens) {
            // Handle both formats: direct key-value pairs or array of objects
            for (const [key, entry] of Object.entries(gamedatas.playerTokens)) {
                if (typeof entry === 'object' && entry !== null && 'player_id' in entry && 'skull_tokens' in entry) {
                    // Format: {player_id: "123", skull_tokens: "1"}
                    this.playerTokens[entry['player_id']] = parseInt(entry['skull_tokens']);
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

        // Convert playerDiceCounts from array format to simple key-value mapping
        this.playerDiceCounts = {};
        if (gamedatas.playerDiceCounts) {
            console.log('Raw playerDiceCounts data:', gamedatas.playerDiceCounts);

            // Handle both formats: direct key-value pairs or array of objects
            for (const [key, entry] of Object.entries(gamedatas.playerDiceCounts)) {
                if (typeof entry === 'object' && entry !== null && 'player_id' in entry && 'dice_count' in entry) {
                    // Format: {player_id: "123", dice_count: "2"}
                    this.playerDiceCounts[entry.player_id] = parseInt(entry.dice_count);
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

        this.myDice = gamedatas.myDice || {};

        // Convert Satan's pool data from backend format to frontend format
        this.satansPool = {};
        if (gamedatas.satansPool) {
            console.log("Satan's pool data from backend:", gamedatas.satansPool);

            // Handle both formats: direct key-value pairs or array of objects
            for (const [key, entry] of Object.entries(gamedatas.satansPool)) {
                if (typeof entry === 'object' && entry !== null && 'dice_id' in entry && 'face' in entry) {
                    // Format: {dice_id: "1", face: "imp"}
                    const diceEntry = entry as {dice_id: string; face: string};
                    this.satansPool[diceEntry.dice_id] = diceEntry.face;
                    console.log(`Converted Satan's pool die ${diceEntry.dice_id}: ${diceEntry.face}`);
                } else if (typeof entry === 'string') {
                    // Format: {"1": "imp"}
                    this.satansPool[key] = entry;
                    console.log(`Direct Satan's pool die ${key}: ${entry}`);
                }
            }
        }

        console.log("Processed Satan's pool:", this.satansPool);

        // Initialize UI
        this.createGameBoard();
        this.cacheUIElements();
        this.createActionPanel(); // Populate action panel on initial load
        this.updateDisplay();
        this.setupNotifications();
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
        console.log(`=== onUpdateActionButtons DEBUG START ===`);
        console.log(`Updating action buttons for state: ${stateName}`, args);
        console.log(`Current player ID: ${this['player_id']}`);
        console.log(`isCurrentPlayerActive(): ${this['isCurrentPlayerActive']()}`);

        // CRITICAL: Check what BGA thinks about current player active status
        const gamedatas = this['gamedatas'];
        const gamestate = gamedatas?.gamestate;
        console.log(`Game state info:`, gamestate);
        console.log(`Multiactive players:`, gamestate?.multiactive);
        console.log(`Active player:`, gamestate?.active_player);

        if (!this['isCurrentPlayerActive']()) {
            console.log('EARLY RETURN: Player is not active according to BGA framework');
            return;
        }

        switch (stateName) {
            case GAME_STATES.PLAYER_TURN:
                console.log('Adding player turn buttons');
                this.addPlayerTurnButtons();
                break;
            case GAME_STATES.CHALLENGE_WINDOW:
                // Only show challenge buttons if this player is NOT the action player
                console.log('=== CHALLENGE WINDOW BUTTON LOGIC ===');
                console.log('Args for challenge window:', args);
                const isActionPlayer = this.isActionPlayer(args);
                console.log(`isActionPlayer result: ${isActionPlayer}`);

                if (!isActionPlayer) {
                    console.log('✅ Player is NOT action player - showing challenge buttons');
                    this.addChallengeButtons();
                } else {
                    console.log('❌ Player IS action player - NOT showing challenge buttons');
                }
                break;
            case GAME_STATES.BLOCK_WINDOW:
                console.log('Adding block buttons');
                this.addBlockButtons();
                break;
            case GAME_STATES.CHOOSE_DICE_OVERFLOW_FACE:
                console.log('Adding dice overflow buttons');
                this.addDiceOverflowButtons();
                break;
        }
        console.log(`=== onUpdateActionButtons DEBUG END ===`);
    }

    ///////////////////////////////////////////////////
    //// State Handling

    private enteringState_playerTurn(args: any): void {
        this.highlightCurrentPlayer();
        this.updateActionAvailability();
    }

    private enteringState_challengeWindow(args: any): void {
        this.showChallengePrompt();

        // Hourglass over everyone who may challenge: all players except the claimant, if they have dice
        const stateArgs = args?.args ?? args;
        const actionPlayerId = String(stateArgs?.action_player_id ?? '');
        const responders = Object.keys(this['gamedatas']?.players || {}).filter(
            (playerId) => playerId !== actionPlayerId && (this.playerDiceCounts[playerId] || 0) > 0
        );
        responders.forEach((playerId) => this.showWaitingBubble(playerId));
    }

    private enteringState_blockWindow(args: any): void {
        this.showBlockPrompt();

        // Hourglass over the target, who may block (server auto-passes a target with no dice)
        const stateArgs = args?.args ?? args;
        const targetId = String(stateArgs?.target_player_id ?? '');
        if (targetId && (this.playerDiceCounts[targetId] || 0) > 0) {
            this.showWaitingBubble(targetId);
        }
    }

    private leavingState_challengeWindow(): void {
        this.hideChallengePrompt();
        this.clearWaitingBubbles();
    }

    private leavingState_blockWindow(): void {
        this.hideBlockPrompt();
        this.clearWaitingBubbles();
    }

    ///////////////////////////////////////////////////
    //// Response bubbles (challenge / block windows)

    private bubbleTimers: Record<string, number> = {};

    /**
     * Create (or replace) the speech bubble above a player's placemat
     */
    private setResponseBubble(playerId: string, contentHTML: string, waiting: boolean): HTMLElement | null {
        const placemat = dom.byId(`placemat_${playerId}`);
        if (!placemat) return null;

        this.removeResponseBubble(playerId);

        placemat.insertAdjacentHTML(
            'beforeend',
            `<div id="response-bubble-${playerId}" class="response-bubble${waiting ? ' waiting' : ''}">${contentHTML}</div>`
        );
        return dom.byId(`response-bubble-${playerId}`);
    }

    private removeResponseBubble(playerId: string): void {
        const timer = this.bubbleTimers[playerId];
        if (timer !== undefined) {
            clearTimeout(timer);
            delete this.bubbleTimers[playerId];
        }
        dom.byId(`response-bubble-${playerId}`)?.remove();
    }

    /** Fade a bubble out after `delay` ms, then remove it */
    private fadeResponseBubble(playerId: string, delay: number): void {
        const existing = this.bubbleTimers[playerId];
        if (existing !== undefined) clearTimeout(existing);

        this.bubbleTimers[playerId] = window.setTimeout(() => {
            const bubble = dom.byId(`response-bubble-${playerId}`);
            if (!bubble) return;
            bubble.classList.add('fading');
            this.bubbleTimers[playerId] = window.setTimeout(() => this.removeResponseBubble(playerId), 400);
        }, delay);
    }

    private showWaitingBubble(playerId: string): void {
        this.setResponseBubble(playerId, '<span class="hourglass">⏳</span>', true);
    }

    /** Window closed: fade out anyone who never got to respond (e.g. someone else challenged first) */
    private clearWaitingBubbles(): void {
        document.querySelectorAll<HTMLElement>('.response-bubble.waiting').forEach((bubble) => {
            const playerId = bubble.id.replace('response-bubble-', '');
            this.fadeResponseBubble(playerId, 0);
        });
    }

    notification_playerResponded = (notif: {args: NotificationArgs}): void => {
        const playerId = notif.args.playerId !== undefined ? String(notif.args.playerId) : '';
        if (!playerId) return;

        let content = '';
        switch (notif.args.response) {
            case 'pass':
                content = '<span class="response-icon">👍</span>';
                break;
            case 'challenge':
                content = '<span class="response-icon">🚫</span>';
                break;
            case 'block': {
                const face = notif.args.face || '';
                content = face
                    ? `<div class="die my-die" data-face="${face}">${this.getDiceSymbol(face)}</div>`
                    : '<span class="response-icon">🚫</span>';
                break;
            }
            default:
                return;
        }

        this.setResponseBubble(playerId, content, false);
        this.fadeResponseBubble(playerId, 3000);
    };

    ///////////////////////////////////////////////////
    //// Action Button Management

    private createActionPanel(): void {
        const currentPlayerId = this['player_id'];
        const currentPlayerTokens = this.playerTokens[currentPlayerId] || 0;
        const currentPlayerDiceCount = this.playerDiceCounts[currentPlayerId] || 0;
        const isMyTurn = this['isCurrentPlayerActive']();

        // Check if we're in the playerTurn state (state 2) where actions can be declared
        const gamedatas = this['gamedatas'] as GameData;
        const currentStateName = gamedatas?.gamestate?.name;
        const canDeclareAction = isMyTurn && currentStateName === 'playerTurn';

        // Players with 0 dice can only use Imp's Set or Satan's Steal
        const hasDice = currentPlayerDiceCount > 0;

        // Icons for action descriptions
        const skullIcon = `<img src="${g_gamethemeurl}img/SkullToken1.png" alt="Skull" class="skull-token-icon" />`;
        const dieIcon = '🎲';     // Die emoji

        const actions = [
            {id: 'raiseHell', label: _('Raise Hell'), icon: 'flame_icon.svg', handler: 'onRaiseHell', enabled: canDeclareAction && hasDice, requiresCost: false, requiresDice: true, cost: '', description: `Gain 1 ${skullIcon} & Reroll`},
            {id: 'harvestSkulls', label: _('Harvest Skulls'), icon: 'skull_icon.svg', handler: 'onHarvestSkulls', enabled: canDeclareAction && hasDice, requiresCost: false, requiresDice: true, cost: '', description: `Gain 2 ${skullIcon}`},
            {id: 'extort', label: _('Extort'), icon: 'trident_icon.svg', handler: 'onExtort', enabled: canDeclareAction && hasDice, requiresCost: false, requiresDice: true, cost: '', description: `Steal 3 ${skullIcon}`},
            {id: 'reapSoul', label: _('Reap Soul'), icon: 'scythe_icon.svg', handler: 'onReapSoul', enabled: canDeclareAction && hasDice && currentPlayerTokens >= 2, requiresCost: true, requiresDice: true, hasEnoughTokens: currentPlayerTokens >= 2, cost: `Pay 2 ${skullIcon}`, description: `Steal 1 ${dieIcon}`},
            {id: 'pentagram', label: _('Pentagram'), icon: 'pentagram_icon.svg', handler: 'onPentagram', enabled: canDeclareAction && hasDice, requiresCost: false, requiresDice: true, cost: '', description: `Reroll Satan Pool`},
            {id: 'impsSet', label: _("Imp's Set"), icon: 'imp_icon.svg', handler: 'onImpsSet', enabled: canDeclareAction, requiresCost: false, requiresDice: false, cost: '', description: `Gain 1 ${dieIcon}`},
            {id: 'satansSteal', label: _("Satan's Steal"), icon: 'satanssteal.png', handler: 'onSatansSteal', enabled: canDeclareAction && currentPlayerTokens >= 6, requiresCost: true, requiresDice: false, hasEnoughTokens: currentPlayerTokens >= 6, cost: `Pay 6 ${skullIcon}`, description: `Steal 1 ${dieIcon}`},
        ];

        const actionIconsContainer = dom.byId('action-icons');
        if (!actionIconsContainer) return;

        // Clear existing content
        actionIconsContainer.innerHTML = '';

        actions.forEach((action) => {
            const buttonId = `${action.id}_icon_btn`;

            // Determine disabled state and lock icon
            // Show lock icon and grayed out for:
            // 1. Cost-based actions where player doesn't have enough tokens
            // 2. Dice-requiring actions where player has no dice
            const insufficientTokens = action.requiresCost && !action.hasEnoughTokens;
            const noDice = action.requiresDice && !hasDice;
            const isLocked = insufficientTokens || noDice;
            const disabledClass = action.enabled ? '' : 'disabled';
            const lockedClass = isLocked ? 'insufficient-tokens' : '';
            const lockIcon = isLocked ? '<span class="lock-icon">🔒</span>' : '';

            // Show icon for all actions
            const iconHTML = `<img src="${g_gamethemeurl}img/${action.icon}" alt="${action.label}" class="action-icon" />`;

            const buttonHTML = `
                <button id="${buttonId}" class="action-icon-btn ${disabledClass} ${lockedClass}">
                    ${lockIcon}
                    ${iconHTML}
                    <div class="action-content">
                        <div class="action-label">${action.label}</div>
                        ${action.cost ? `<div class="action-cost">${action.cost}</div>` : ''}
                        <div class="action-description">${action.description}</div>
                    </div>
                </button>
            `;

            actionIconsContainer.insertAdjacentHTML('beforeend', buttonHTML);

            // Add click handler
            const button = dom.byId(buttonId);
            if (button && action.enabled) {
                button.addEventListener('click', () => {
                    this[action.handler]();
                });
            }
        });
    }

    private addPlayerTurnButtons(): void {
        // Action panel is always visible, just update which buttons are clickable
        this.createActionPanel();
    }

    private addChallengeButtons(): void {
        console.log('🚨 addChallengeButtons: ADDING CHALLENGE BUTTONS TO UI');
        console.log('🚨 Current player:', this['player_id']);
        console.log('🚨 Stack trace:', new Error().stack);

        const currentPlayerId = this['player_id'];
        const currentPlayerDiceCount = this.playerDiceCounts[currentPlayerId] || 0;

        // If player has 0 dice, automatically pass
        if (currentPlayerDiceCount === 0) {
            console.log('🚨 Player has 0 dice, auto-passing challenge');
            this.showMessage(_('You have no dice - automatically passing'), 'info');
            // Auto-pass after a short delay so player can see the message
            setTimeout(() => {
                this.onPass();
            }, 1000);
            return;
        }

        this['addActionButton']('challenge_btn', _('Challenge'), 'onChallenge');
        this['addActionButton']('pass_btn', _('Pass'), 'onPass');

        console.log('🚨 Challenge buttons added successfully');
    }

    private addBlockButtons(): void {
        const currentPlayerId = this['player_id'];
        const currentPlayerDiceCount = this.playerDiceCounts[currentPlayerId] || 0;

        // If player has 0 dice, automatically pass
        if (currentPlayerDiceCount === 0) {
            console.log('Player has 0 dice, auto-passing block');
            this.showMessage(_('You have no dice - automatically passing'), 'info');
            // Auto-pass after a short delay so player can see the message
            setTimeout(() => {
                this.onPass();
            }, 1000);
            return;
        }

        this['addActionButton']('block_btn', _('Block'), 'onBlock');
        this['addActionButton']('pass_btn', _('Pass'), 'onPass');
    }

    /**
     * Show the dice overflow face picker as a dialog above Satan's pool
     * (2 rows of 3 dice, drawn like the dice on the player mats)
     */
    private addDiceOverflowButtons(): void {
        this.hideDiceOverflowDialog();

        const wrapper = document.querySelector<HTMLElement>('.satans-pool-wrapper');
        if (!wrapper) return;

        const faces = [
            {id: 'flame', label: _('Flame')},
            {id: 'skull', label: _('Skull')},
            {id: 'trident', label: _('Trident')},
            {id: 'scythe', label: _('Scythe')},
            {id: 'pentagram', label: _('Pentagram')},
            {id: 'imp', label: _('Imp')},
        ];

        const buttonsHTML = faces
            .map(
                (face) => `
                <button type="button" class="overflow-face-btn" id="overflow_${face.id}_btn" data-face="${face.id}" title="${face.label}" aria-label="${face.label}">
                    <div class="die my-die" data-face="${face.id}">${this.getDiceSymbol(face.id)}</div>
                </button>`
            )
            .join('');

        wrapper.insertAdjacentHTML('beforeend', `<div id="overflow-dialog" class="overflow-dialog" role="dialog">${buttonsHTML}</div>`);
        wrapper.classList.add('overflow-open');

        const dialog = dom.byId('overflow-dialog');
        dialog?.querySelectorAll<HTMLButtonElement>('.overflow-face-btn').forEach((button) => {
            button.addEventListener('click', () => {
                const face = button.getAttribute('data-face');
                if (!face) return;

                // Prevent double submits; re-enable if the server rejects the action
                dialog.querySelectorAll<HTMLButtonElement>('.overflow-face-btn').forEach((b) => (b.disabled = true));
                Promise.resolve(this['bgaPerformAction']('chooseDiceOverflowFace', {face}))
                    .then(() => this.hideDiceOverflowDialog())
                    .catch(() => {
                        dialog.querySelectorAll<HTMLButtonElement>('.overflow-face-btn').forEach((b) => (b.disabled = false));
                    });
            });
        });
    }

    private hideDiceOverflowDialog(): void {
        dom.byId('overflow-dialog')?.remove();
        document.querySelector('.satans-pool-wrapper')?.classList.remove('overflow-open');
    }

    private leavingState_chooseDiceOverflowFace(): void {
        this.hideDiceOverflowDialog();
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
        const currentPlayerId = this['player_id'];
        const currentPlayerTokens = this.playerTokens[currentPlayerId] || 0;

        if (currentPlayerTokens < 2) {
            this.showMessage(_('You need 2 skull tokens to use Reap Soul'), 'error');
            return;
        }

        if (this.validateAction('reapSoul')) {
            // Reap Soul can only target players with 2 or more dice
            this.selectTarget(
                (playerId: string) => {
                    this['bgaPerformAction']('reapSoul', {targetPlayerId: parseInt(playerId)});
                },
                (playerId: string) => {
                    const diceCount = this.playerDiceCounts[playerId] || 0;
                    if (diceCount <= 1) {
                        return {valid: false, reason: `${diceCount} dice`};
                    }
                    return {valid: true};
                }
            );
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
        const currentPlayerId = this['player_id'];
        const currentPlayerTokens = this.playerTokens[currentPlayerId] || 0;

        if (currentPlayerTokens < 6) {
            this.showMessage(_("You need 6 skull tokens to use Satan's Steal"), 'error');
            return;
        }

        if (this.validateAction('satansSteal')) {
            // Check how many other players are available
            const availablePlayers = Object.keys(this['gamedatas']?.players || {}).filter((id) => id !== this['player_id'].toString());

            if (availablePlayers.length === 0) {
                console.error("No available targets for Satan's Steal");
                return;
            } else if (availablePlayers.length === 1) {
                // Only one opponent, skip target selection and go directly to decision
                const targetId = availablePlayers[0];
                if (targetId) {
                    this.showSatansStealDecision(parseInt(targetId));
                }
            } else {
                // Multiple opponents, show target selection first
                this.showSatansStealTargetSelection();
            }
        }
    }

    private showSatansStealTargetSelection(): void {
        // Clear existing action buttons
        this['removeActionButtons']();

        // Get available players
        const availablePlayers = Object.keys(this['gamedatas']?.players || {}).filter((id) => id !== this['player_id'].toString());

        // Add target selection buttons
        availablePlayers.forEach((playerId) => {
            const playerName = this['gamedatas']?.players?.[playerId]?.name || `Player ${playerId}`;
            this['addActionButton'](`target_${playerId}_btn`, playerName, () => {
                this.showSatansStealDecision(parseInt(playerId));
            });
        });

        // Add a back button to return to main actions
        this['addActionButton']('back_to_actions_btn', _('← Back'), () => {
            this['removeActionButtons']();
            this.addPlayerTurnButtons();
        });
    }

    private showSatansStealDecision(targetPlayerId: number): void {
        // Clear existing action buttons
        this['removeActionButtons']();

        // Add decision buttons
        this['addActionButton']('keepDie_btn', _('Keep the stolen die'), () => {
            this['bgaPerformAction']('satansSteal', {
                targetPlayerId: targetPlayerId,
                putInPool: false,
                poolFace: '',
            });
        });

        this['addActionButton']('putInPool_btn', _("Put die in Satan's pool"), () => {
            this.showFaceSelection(targetPlayerId);
        });
    }

    private showFaceSelection(targetPlayerId: number): void {
        // Clear existing action buttons
        this['removeActionButtons']();

        // Add face selection buttons
        const faces = [
            {id: 'flame', label: _('Flame'), icon: '🔥'},
            {id: 'skull', label: _('Skull'), icon: '💀'},
            {id: 'trident', label: _('Trident'), icon: '🔱'},
            {id: 'scythe', label: _('Scythe'), icon: '⚰️'},
            {id: 'pentagram', label: _('Pentagram'), icon: '⭐'},
            {id: 'imp', label: _('Imp'), icon: '👹'},
        ];

        faces.forEach((face) => {
            this['addActionButton'](`face_${face.id}_btn`, `${face.icon} ${face.label}`, () => {
                // This is the final decision - execute Satan's Steal with all parameters
                this['bgaPerformAction']('satansSteal', {
                    targetPlayerId: targetPlayerId,
                    putInPool: true,
                    poolFace: face.id,
                });
            });
        });

        // Add a back button to return to the main decision
        this['addActionButton']('back_btn', _('← Back'), () => {
            this.showSatansStealDecision(targetPlayerId);
        });
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

        // In BGA, notifications are automatically handled if the notification_* methods exist
        // We just need to make sure the methods are properly bound

        // Manually bind notification methods to ensure they're accessible
        const methods = Object.getOwnPropertyNames(Object.getPrototypeOf(this));
        methods
            .filter((method) => method.startsWith('notification_'))
            .forEach((method) => {
                // Explicitly bind the method to this instance
                if (typeof this[method] === 'function') {
                    this[method] = this[method].bind(this);
                }
            });

        // Also try manual registration with dojo.subscribe as a fallback
        try {
            if (typeof dojo !== 'undefined' && dojo.subscribe) {
                // Register all notifications with dojo.subscribe for maximum reliability
                const notificationList = [
                    'message',
                    'diceRerolled',
                    'diceCountUpdate',
                    'tokensChanged',
                    'gameSetupComplete',
                    'challengeSuccessful',
                    'challengeFailed',
                    'diceStolen',
                    'diceToSatansPool',
                    'raiseHell',
                    'harvestSkulls',
                    'extort',
                    'reapSoul',
                    'pentagram',
                    'impsSet',
                    'satansSteal',
                    'rolloffWinner',
                    'gameWon',
                    'playerResponded',
                ];

                notificationList.forEach((notifName) => {
                    dojo.subscribe(notifName, this, `notification_${notifName}`);
                });
            }
        } catch (e) {
            console.log('dojo.subscribe not available:', e);
        }

        console.log('Notifications setup completed - methods should be auto-detected');
    }

    // Add a simple message notification handler for testing
    notification_message(notif: {args: any}): void {
        console.log('Message notification received:', notif);
        // This should show the test message in the game
    }

    notification_diceCountUpdate = (notif: {args: NotificationArgs}): void => {
        const playerId = notif.args.playerId;
        const diceCount = notif.args.diceCount;

        if (playerId && diceCount !== undefined) {
            this.playerDiceCounts[playerId] = diceCount;
            this.updatePlayersInfo();
            // Update the dice display for this specific player
            this.updatePlayerDice(playerId);
        }
    };

    notification_tokensChanged = (notif: {args: NotificationArgs}): void => {
        const playerId = notif.args.playerId;
        const tokens = notif.args.tokens;

        if (playerId && tokens !== undefined) {
            console.log(`tokensChanged: Updating player ${playerId} to ${tokens} tokens`);
            this.playerTokens[playerId] = tokens;
            this.updatePlayersInfo();
        }
    };

    notification_gameSetupComplete = (notif: {args: NotificationArgs}): void => {
        console.log('Game setup complete, refreshing display');
        this.updateDisplay();
    };

    notification_challengeSuccessful = (notif: {args: NotificationArgs}): void => {
        console.log('Challenge successful', notif);
        this.updateDisplay();
    };

    notification_challengeFailed = (notif: {args: NotificationArgs}): void => {
        console.log('Challenge failed', notif);
        this.updateDisplay();
    };

    notification_diceStolen = (notif: {args: NotificationArgs}): void => {
        console.log('Dice stolen', notif);
        const stealerId = notif.args.stealerId;
        const victimId = notif.args.victimId;

        if (stealerId && victimId) {
            // Store the transfer animation promise so diceRerolled can wait for it
            this.pendingTransferAnimation = this.animator.animateBetweenPlayers(`dice-${victimId}`, `dice-${stealerId}`, 'die', 1).then(() => {
                // Update other UI elements (but not dice - that's handled by diceRerolled)
                this.updatePlayersInfo();
                this.updateSatansPool();
                this.updateAllPlayerDice(); // Update victim's dice count
                this.pendingTransferAnimation = null; // Clear after completion
            });
        } else {
            this.updateDisplay();
        }
    };

    notification_diceToSatansPool = (notif: {args: NotificationArgs}): void => {
        console.log("Dice to Satan's pool", notif);
        const face = notif.args.face;
        const playerId = notif.args.playerId;
        const dice = notif.args.dice;
        const diceCount = notif.args.diceCount;
        const satansPool = notif.args.satansPool; // Check if full pool state is included

        // Update game state immediately
        if (playerId && diceCount !== undefined) {
            this.playerDiceCounts[playerId] = diceCount;
        }

        if (playerId && face) {
            // Update game state data first
            if (String(playerId) === String(this['player_id']) && dice) {
                this.myDice = dice;
            }

            // Store transfer animation promise
            this.pendingTransferAnimation = this.animator.animateToSatansPool(`dice-${playerId}`, 1).then(() => {
                // Update Satan's pool data
                if (satansPool) {
                    console.log('Full Satan\'s pool state provided in diceToSatansPool, using it');
                    // Convert Satan's pool data from full state
                    const convertedSatansPool: Record<string, string> = {};
                    for (const [key, entry] of Object.entries(satansPool)) {
                        if (typeof entry === 'object' && entry !== null && 'dice_id' in entry && 'face' in entry) {
                            const diceEntry = entry as {dice_id: string; face: string};
                            convertedSatansPool[diceEntry.dice_id] = diceEntry.face;
                        } else if (typeof entry === 'string') {
                            convertedSatansPool[key] = entry;
                        }
                    }
                    this.satansPool = convertedSatansPool;
                } else {
                    console.log('No full pool state - adding single die to pool');
                    // Add just the new die to the existing pool
                    const newDiceId = `pool_${Date.now()}_${Math.random()}`;
                    this.satansPool[newDiceId] = face;
                }

                // After transfer, animate reroll of remaining dice if current player
                if (String(playerId) === String(this['player_id']) && dice) {
                    this.animator.animateDiceReroll(`dice_${this['player_id']}`, dice).then(() => {
                        // Always update Satan's pool visual after animation
                        this.updateSatansPool();
                        this.updatePlayersInfo();
                        this.updateAllPlayerDice();
                        this.pendingTransferAnimation = null;
                    });
                } else {
                    // For opponents, just update displays after transfer animation
                    this.updateSatansPool();
                    this.updatePlayersInfo();
                    this.updateAllPlayerDice();
                    this.pendingTransferAnimation = null;
                }
            });
        } else {
            if (face) {
                const newDiceId = `pool_${Date.now()}_${Math.random()}`;
                this.satansPool[newDiceId] = face;
            }
            this.updateDisplay();
        }
    };

    notification_raiseHell = (notif: {args: NotificationArgs}): void => {
        const playerId = notif.args.playerId;
        const tokens = notif.args.tokens;
        const diceCount = notif.args.diceCount;
        const previousTokens = playerId ? (this.playerTokens[playerId] || 0) : 0;

        console.log(`Raise Hell - Player ${playerId}: tokens=${tokens}, diceCount=${diceCount}`);

        // Update game state immediately (for action panel)
        if (playerId && tokens !== undefined) {
            this.playerTokens[playerId] = tokens;
        }
        if (playerId && diceCount !== undefined) {
            this.playerDiceCounts[playerId] = diceCount;
        }

        // Then animate visually
        if (playerId && tokens !== undefined && tokens > previousTokens) {
            const tokensGained = tokens - previousTokens;
            this.animator.animateFromBank(`player-${playerId}`, tokensGained).then(() => {
                this.updatePlayersInfo();
            });
        } else {
            this.updatePlayersInfo();
        }

        // Update display for dice changes
        this.updateAllPlayerDice();
    };

    notification_diceRerolled = (notif: {args: NotificationArgs}): void => {
        console.log('Dice rerolled notification received:', notif);
        const playerId = notif.args.playerId;
        const dice = notif.args.dice || {};
        const diceCount = notif.args.diceCount;

        // Update the dice count for the specific player (state only, don't render yet)
        if (playerId && diceCount !== undefined) {
            this.playerDiceCounts[playerId] = diceCount;
        }

        // If this is the current player, update their dice display with animation
        // Convert both to strings for comparison to handle type mismatches
        if (String(playerId) === String(this['player_id'])) {
            this.myDice = dice;

            // Wait for any pending transfer animation before starting reroll
            const startReroll = () => {
                if (Object.keys(dice).length === 0) {
                    // No dice left - nothing to animate, just clear the mat
                    this.updatePlayersInfo();
                    this.updateAllPlayerDice();
                    return;
                }

                this.animator.animateDiceReroll(`dice_${this['player_id']}`, dice).then(() => {
                    console.log('My dice updated successfully from diceRerolled with animation');
                    // Update displays after animation completes
                    this.updatePlayersInfo();
                    this.updateAllPlayerDice();
                });
            };

            if (this.pendingTransferAnimation) {
                console.log('Waiting for transfer animation to complete before reroll...');
                this.pendingTransferAnimation.then(startReroll);
            } else {
                startReroll();
            }
        } else {
            // For opponents, don't update display immediately - wait for any pending animations
            console.log('NOT current player - waiting for any pending animations before updating');
            if (this.pendingTransferAnimation) {
                this.pendingTransferAnimation.then(() => {
                    if (playerId) {
                        this.updatePlayerDice(playerId);
                    }
                    this.updatePlayersInfo();
                });
            } else {
                // No pending animations, update immediately
                if (playerId) {
                    this.updatePlayerDice(playerId);
                }
                this.updatePlayersInfo();
            }
        }
    };

    notification_harvestSkulls = (notif: {args: NotificationArgs}): void => {
        console.log('Harvest Skulls action notification received:', notif);
        const playerId = notif.args.playerId;
        const tokens = notif.args.tokens;
        const previousTokens = playerId ? (this.playerTokens[playerId] || 0) : 0;

        // Update game state immediately (for action panel)
        if (playerId && tokens !== undefined) {
            this.playerTokens[playerId] = tokens;
        }

        // Then animate visually
        if (playerId && tokens !== undefined && tokens > previousTokens) {
            const tokensGained = tokens - previousTokens;
            this.animator.animateFromBank(`player-${playerId}`, tokensGained).then(() => {
                this.updatePlayersInfo();
            });
        } else {
            this.updatePlayersInfo();
        }
    };

    notification_extort = (notif: {args: NotificationArgs}): void => {
        console.log('Extort action notification received:', notif);
        const playerId = notif.args.playerId;
        const targetId = notif.args.targetId;
        const playerNewTokens = notif.args.playerNewTokens;
        const targetNewTokens = notif.args.targetNewTokens;
        const previousTargetTokens = targetId ? (this.playerTokens[targetId] || 0) : 0;

        console.log(`Extort - Player ${playerId} steals from Target ${targetId}`);

        // Update game state immediately (for action panel)
        if (playerId && playerNewTokens !== undefined) {
            this.playerTokens[playerId] = playerNewTokens;
        }
        if (targetId && targetNewTokens !== undefined) {
            this.playerTokens[targetId] = targetNewTokens;
        }

        // Then animate visually
        if (playerId && targetId && playerNewTokens !== undefined && targetNewTokens !== undefined) {
            const tokensStolen = previousTargetTokens - targetNewTokens;
            this.animator.animateBetweenPlayers(`player-${targetId}`, `player-${playerId}`, 'token', tokensStolen).then(() => {
                this.updatePlayersInfo();
            });
        } else {
            this.updatePlayersInfo();
        }
    };

    notification_reapSoul = (notif: {args: NotificationArgs}): void => {
        console.log('Reap Soul action', notif);
        const playerId = notif.args.playerId;
        const playerNewTokens = notif.args.playerNewTokens;
        const previousTokens = playerId ? (this.playerTokens[playerId] || 0) : 0;

        // Update game state immediately (for action panel)
        if (playerId && playerNewTokens !== undefined) {
            this.playerTokens[playerId] = playerNewTokens;
        }

        // Then animate visually
        if (playerId && playerNewTokens !== undefined && playerNewTokens < previousTokens) {
            const tokensPaid = previousTokens - playerNewTokens;
            this.animator.animateToBank(`player-${playerId}`, tokensPaid).then(() => {
                this.updatePlayersInfo();
                this.updateDisplay();
            });
        } else {
            this.updateDisplay();
        }
    };

    notification_pentagram = (notif: {args: NotificationArgs}): void => {
        console.log('Pentagram action', notif);
        console.log('Pentagram notification args:', notif.args);

        // Sequence: reroll Satan's pool -> rolled pentagram flies to the player's hand.
        // The player's hand update arrives as a separate diceRerolled notification,
        // which waits on pendingTransferAnimation so it plays after the transfer.
        const playerId = notif.args.playerId;
        const gainedDiceId = notif.args.gainedDiceId;
        const rolledPool = notif.args.rolledSatansPool || notif.args.satansPool;
        const finalPool = notif.args.satansPool;

        const run = (): Promise<void> => {
            if (!rolledPool) {
                this.updateDisplay();
                return Promise.resolve();
            }

            return this.animateSatansPoolReroll(rolledPool).then(() => {
                const showFinalPool = () => {
                    if (finalPool) {
                        this.satansPool = this.convertSatansPool(finalPool);
                    }
                    this.updateSatansPool();
                };

                if (gainedDiceId === null || gainedDiceId === undefined || !playerId) {
                    showFinalPool();
                    return;
                }

                // Lift the pentagram die out of the pool and fly it to the player's hand
                const sourceId = `pool-die-${gainedDiceId}`;
                const transfer = this.animator.animateFromSatansPool(`dice-${playerId}`, 1, sourceId, DICE_FACES.PENTAGRAM);
                const sourceEl = document.getElementById(sourceId);
                if (sourceEl) {
                    sourceEl.style.visibility = 'hidden';
                }
                return transfer.then(showFinalPool);
            });
        };

        const previous = this.pendingTransferAnimation;
        const chain: Promise<void> = (previous ? previous.then(run) : run()).then(() => {
            if (this.pendingTransferAnimation === chain) {
                this.pendingTransferAnimation = null;
            }
        });
        this.pendingTransferAnimation = chain;
    };

    notification_impsSet = (notif: {args: NotificationArgs}): void => {
        console.log("Imp's Set action", notif);
        console.log("Imp's Set notification args:", notif.args);
        const playerId = notif.args.playerId;
        const dice = notif.args.dice;

        if (playerId) {
            // Check if this is the current player
            const isCurrentPlayer = String(playerId) === String(this['player_id']);

            if (isCurrentPlayer && dice) {
                // Current player with dice data - use transfer + reroll animation
                this.myDice = dice;

                this.animator.animateDiceTransferWithReroll({
                    source: 'bank',
                    target: `dice-${playerId}`,
                    count: 1,
                    playerId: String(playerId),
                    newDiceData: this.myDice
                }).then(() => {
                    this.updatePlayersInfo();
                    this.updateSatansPool();
                });
            } else {
                // Other player or no dice data - just transfer animation
                this.animator.animateDiceFromBank(`dice-${playerId}`, 1).then(() => {
                    this.updateDisplay();
                });
            }
        } else {
            this.updateDisplay();
        }
    };

    notification_satansSteal = (notif: {args: NotificationArgs}): void => {
        console.log("Satan's Steal action notification received:", notif);
        const playerId = notif.args.playerId;
        const playerNewTokens = notif.args.playerNewTokens;
        const previousTokens = playerId ? (this.playerTokens[playerId] || 0) : 0;

        // Update game state immediately (for action panel)
        if (playerId && playerNewTokens !== undefined) {
            this.playerTokens[playerId] = playerNewTokens;
        }

        // Then animate visually (only payment - dice stealing is handled by diceStolen notification)
        if (playerId && playerNewTokens !== undefined && playerNewTokens < previousTokens) {
            const tokensPaid = previousTokens - playerNewTokens;
            this.animator.animateToBank(`player-${playerId}`, tokensPaid).then(() => {
                this.updatePlayersInfo();
                this.updateDisplay();
            });
        } else {
            this.updatePlayersInfo();
            this.updateDisplay();
        }
    };

    notification_rolloffWinner = (notif: {args: NotificationArgs}): void => {
        console.log('Rolloff winner', notif);
        this.showMessage(_('Rolloff completed!'), 'info');
    };

    notification_gameWon = (notif: {args: NotificationArgs}): void => {
        console.log('Game won notification received:', notif);
        const winnerId = notif.args.winnerId;

        // Trigger confetti animation
        this['playConfettiAnimation']();

        // Show win message
        this.showMessage(_('Game Over!'), 'success');

        // Set a 5 second delay before moving to end game screen
        this['notifqueue'].setSynchronousDuration(5000);
    };

    ///////////////////////////////////////////////////
    //// UI Management

    private createGameBoard(): void {
        const gameArea = dom.byId('game_play_area');
        if (!gameArea) return;

        const gamedatas = this['gamedatas'] as GameData;
        const players = gamedatas.players;
        const playerCount = Object.keys(players).length;
        const currentPlayerId = this['player_id'];

        // Build action panel HTML
        const actionPanelHTML = `
            <div id="myactions">
                <div class="actionrowhead"><span>${_('Actions on your turn')}:</span></div>
                <div id="action-icons"></div>
            </div>
        `;

        // Build circular layout HTML with player positions
        let playerPositionsHTML = '';
        let playerIndex = 0;

        // Sort players to put current player first
        const sortedPlayers = Object.values(players).sort((a, b) => {
            if (a.id === currentPlayerId) return -1;
            if (b.id === currentPlayerId) return 1;
            return 0;
        });

        sortedPlayers.forEach((player) => {
            const isCurrentPlayer = player.id === currentPlayerId;
            const playerClass = isCurrentPlayer ? 'mine active' : '';

            playerPositionsHTML += `
                <div class="oncircle player-${playerIndex}">
                    <div id="placemat_${player.id}" class="placemat player-${playerIndex} ${playerClass}"
                         style="color: #${player.color}" data-player="${player.id}">
                        <div class="playerhead">
                            <div id="balloon_${player.id}" class="balloon"></div>
                            <div id="wealth_${player.id}" class="wealth"></div>
                            <div class="player-name">${player.name}</div>
                        </div>
                        <div id="dice_${player.id}" class="dice"></div>
                    </div>
                </div>
            `;
            playerIndex++;
        });

        const circleHTML = `
            <div id="circle-wrapper">
                <div id="bank">
                    <img src="${g_gamethemeurl}img/bag.png" alt="Bank" class="bank-image" />
                </div>
                <div id="circle" class="circle-${playerCount}">
                    <div class="oncircle satans-pool-wrapper">
                        <span class="satans-pool-title">${_("Satan's Pool")}</span>
                        <div id="satans-pool-deck" data-deck="satans-pool">
                            <div id="satans-pool-container">
                                <div id="satans-pool" class="pool-dice-area"></div>
                            </div>
                        </div>
                    </div>
                    ${playerPositionsHTML}
                </div>
            </div>
        `;

        gameArea.insertAdjacentHTML(
            'beforeend',
            `
            <div id="game-wrap">
                ${actionPanelHTML}
                ${circleHTML}
            </div>
            `
        );
    }

    private cacheUIElements(): void {
        this.uiElements = {
            satansPool: dom.byId('satans-pool'),
        };
    }

    private updateDisplay(): void {
        this.updatePlayersInfo();
        this.updateAllPlayerDice();
        this.updateSatansPool();
    }

    private updatePlayersInfo(): void {
        if (!this['gamedatas']?.players) return;

        // Update each placemat with current tokens
        for (const playerId in this['gamedatas'].players) {
            const tokens = this.playerTokens[playerId] || 0;
            this.renderTokenStack(playerId, tokens);
        }
    }

    /**
     * Render visual token stack for a player
     * Shows up to 10 overlapping tokens, then shows "+N" for overflow
     */
    private renderTokenStack(playerId: string, count: number): void {
        const wealthElement = dom.byId(`wealth_${playerId}`);
        if (!wealthElement) return;

        // Clear existing content
        wealthElement.innerHTML = '';

        // Add count label first (on the left)
        const countLabel = document.createElement('span');
        countLabel.className = 'token-count-label';

        const maxVisible = 10;
        const visibleTokens = Math.min(count, maxVisible);

        if (count > maxVisible) {
            countLabel.textContent = `${maxVisible}+${count - maxVisible}`;
        } else {
            countLabel.textContent = String(count);
        }

        wealthElement.appendChild(countLabel);

        // Create token stack container (on the right)
        const stackContainer = document.createElement('div');
        stackContainer.className = 'token-stack';

        const overlapOffset = 8; // pixels

        // Create visible tokens
        for (let i = 0; i < visibleTokens; i++) {
            const tokenImg = document.createElement('img');
            tokenImg.src = `${g_gamethemeurl}img/SkullToken1.png`;
            tokenImg.className = 'skull-token-stacked';
            tokenImg.style.left = `${i * overlapOffset}px`;
            tokenImg.alt = 'Skull Token';
            stackContainer.appendChild(tokenImg);
        }

        wealthElement.appendChild(stackContainer);
    }

    private updateAllPlayerDice(): void {
        if (!this['gamedatas']?.players) return;

        for (const playerId in this['gamedatas'].players) {
            this.updatePlayerDice(playerId);
        }
    }

    private updatePlayerDice(playerId: string | number): void {
        const diceContainer = dom.byId(`dice_${playerId}`);
        if (!diceContainer) return;

        const currentPlayerId = this['player_id'];
        const isCurrentPlayer = String(playerId) === String(currentPlayerId);
        const diceCount = this.playerDiceCounts[playerId] || 0;

        // Clear existing dice
        diceContainer.innerHTML = '';

        if (isCurrentPlayer) {
            // Show actual dice faces for current player
            const playerDice = this.myDice || {};
            this.setDiceDisplay(diceContainer, playerDice);
        } else {
            // Show blank dice for opponents
            for (let i = 0; i < diceCount; i++) {
                const blankDie = document.createElement('div');
                blankDie.className = 'die blank-die';
                blankDie.setAttribute('data-face', 'hidden');
                diceContainer.appendChild(blankDie);
            }
        }
    }

    private updateMyDice(animate: boolean = false): void {
        console.log('updateMyDice: myDice data structure:', this.myDice);

        const myDiceArea = dom.byId(`dice_${this['player_id']}`);
        if (!myDiceArea) return;

        if (animate) {
            // Animate dice rolling before showing final faces
            this.animateDiceRoll(myDiceArea, this.myDice);
        } else {
            // Direct update without animation
            this.setDiceDisplay(myDiceArea, this.myDice);
        }
    }

    private setDiceDisplay(container: HTMLElement, diceData: Record<string, string>): void {
        let html = '';
        for (const diceId in diceData) {
            const diceValue = diceData[diceId];
            // Handle both string face values and object structures
            let face: string;
            if (typeof diceValue === 'string') {
                face = diceValue;
            } else if (typeof diceValue === 'object' && diceValue !== null) {
                // If it's an object, look for common face property names
                face = (diceValue as any).face || (diceValue as any).dice_face || (diceValue as any).value || '';
            } else {
                face = '';
            }

            if (face) {
                html += `<div class="die my-die" data-face="${face}" data-dice-id="${diceId}">
                    ${this.getDiceSymbol(face)}
                </div>`;
            }
        }

        container.innerHTML = html;
    }

    private animateDiceRoll(container: HTMLElement, finalDiceData: Record<string, string>): void {
        const faces = ['flame', 'skull', 'trident', 'scythe', 'pentagram', 'imp'];
        const diceElements: Record<string, HTMLElement> = {};

        // Create initial dice elements
        let html = '';
        for (const diceId in finalDiceData) {
            html += `<div class="die my-die rolling" data-face="flame" data-dice-id="${diceId}">
                ${this.getDiceSymbol('flame')}
            </div>`;
        }
        container.innerHTML = html;

        // Cache dice elements
        const diceNodes = container.querySelectorAll('.die');
        const diceIds = Object.keys(finalDiceData);
        diceNodes.forEach((element, index) => {
            if (index < diceIds.length && diceIds[index]) {
                diceElements[diceIds[index]] = element as HTMLElement;
            }
        });

        // Animate through all faces
        let currentFaceIndex = 0;
        const animationInterval = setInterval(() => {
            const currentFace = faces[currentFaceIndex];
            if (!currentFace) return;

            // Update all dice to show current face
            for (const diceId in diceElements) {
                const element = diceElements[diceId];
                if (element) {
                    element.setAttribute('data-face', currentFace);
                    element.innerHTML = this.getDiceSymbol(currentFace);
                }
            }

            currentFaceIndex++;

            // After cycling through all faces, show final results
            if (currentFaceIndex >= faces.length) {
                clearInterval(animationInterval);

                // Show final faces after a brief delay
                setTimeout(() => {
                    for (const diceId in diceElements) {
                        const element = diceElements[diceId];
                        const finalDiceValue = finalDiceData[diceId];

                        if (!element || !finalDiceValue) continue;

                        // Handle both string and object formats
                        let finalFace: string;
                        if (typeof finalDiceValue === 'string') {
                            finalFace = finalDiceValue;
                        } else if (typeof finalDiceValue === 'object' && finalDiceValue !== null) {
                            finalFace = (finalDiceValue as any).face || (finalDiceValue as any).dice_face || (finalDiceValue as any).value || '';
                        } else {
                            finalFace = '';
                        }

                        if (finalFace) {
                            element.setAttribute('data-face', finalFace);
                            element.innerHTML = this.getDiceSymbol(finalFace);
                            element.classList.remove('rolling');
                        }
                    }
                }, 100);
            }
        }, 100); // 100ms between each face
    }

    private updateSatansPool(): void {
        const satansPoolArea = this.uiElements.satansPool;
        if (!satansPoolArea) return;

        const stacks = this.getPoolStackIndexes(this.satansPool);
        let html = '';
        for (const diceId in this.satansPool) {
            const face = this.satansPool[diceId];
            if (face) {
                html += `<div class="die pool-die placed" id="pool-die-${diceId}" data-face="${face}" data-dice-id="${diceId}" style="--stack: ${stacks[diceId] ?? 0}">
                    ${this.getDiceSymbol(face)}
                </div>`;
            }
        }

        satansPoolArea.innerHTML = html;
    }

    /**
     * For each pool die, its index among dice showing the same face (used to fan out a slot's stack)
     */
    private getPoolStackIndexes(pool: Record<string, string>): Record<string, number> {
        const perFace: Record<string, number> = {};
        const result: Record<string, number> = {};
        for (const diceId in pool) {
            const face = pool[diceId] || '';
            const index = perFace[face] ?? 0;
            result[diceId] = index;
            perFace[face] = index + 1;
        }
        return result;
    }

    /**
     * Convert Satan's pool data from backend format ({dice_id, face} rows or {id: face}) to {id: face}
     */
    private convertSatansPool(pool: Record<string, unknown>): Record<string, string> {
        const converted: Record<string, string> = {};
        for (const [key, entry] of Object.entries(pool || {})) {
            if (typeof entry === 'object' && entry !== null && 'dice_id' in entry && 'face' in entry) {
                const diceEntry = entry as {dice_id: string; face: string};
                converted[diceEntry.dice_id] = diceEntry.face;
            } else if (typeof entry === 'string') {
                converted[key] = entry;
            }
        }
        return converted;
    }

    private animateSatansPoolReroll(newSatansPool: Record<string, string>): Promise<void> {
        console.log("Animating Satan's pool reroll with new data:", newSatansPool);

        const satansPoolArea = this.uiElements.satansPool;
        if (!satansPoolArea) {
            console.error("Satan's pool area not found");
            return Promise.resolve();
        }

        const convertedSatansPool = this.convertSatansPool(newSatansPool);
        const previousPool = this.satansPool;
        const previousStacks = this.getPoolStackIndexes(previousPool);

        // Update our internal data
        this.satansPool = convertedSatansPool;

        // If there are no dice in Satan's pool, just update normally
        if (Object.keys(convertedSatansPool).length === 0) {
            this.updateSatansPool();
            return Promise.resolve();
        }

        return new Promise<void>((resolve) => {
            const faces = ['flame', 'skull', 'trident', 'scythe', 'pentagram', 'imp'];
            const diceElements: Record<string, HTMLElement> = {};
            const diceIds = Object.keys(convertedSatansPool);

            // Create dice where they currently sit (their old slot, or the center if new)
            let html = '';
            for (const diceId of diceIds) {
                const oldFace = previousPool[diceId];
                const startFace = oldFace || 'flame';
                const placedClass = oldFace ? ' placed' : '';
                html += `<div class="die pool-die${placedClass}" id="pool-die-${diceId}" data-face="${startFace}" data-dice-id="${diceId}" style="--stack: ${previousStacks[diceId] ?? 0}">
                    ${this.getDiceSymbol(startFace)}
                </div>`;
            }
            satansPoolArea.innerHTML = html;

            // Cache dice elements
            satansPoolArea.querySelectorAll<HTMLElement>('.pool-die').forEach((element) => {
                const diceId = element.getAttribute('data-dice-id');
                if (diceId) {
                    diceElements[diceId] = element;
                }
            });

            // Force layout so the starting positions register before transitioning
            void satansPoolArea.offsetWidth;

            // Gather the dice into a compact grid in the middle of the board while they roll
            const firstDie = diceElements[diceIds[0] || ''];
            const spacing = (firstDie?.offsetWidth || 35) + 3;
            const cols = Math.ceil(Math.sqrt(diceIds.length));
            const rows = Math.ceil(diceIds.length / cols);
            diceIds.forEach((diceId, index) => {
                const element = diceElements[diceId];
                if (!element) return;
                const dx = ((index % cols) - (cols - 1) / 2) * spacing;
                const dy = (Math.floor(index / cols) - (rows - 1) / 2) * spacing;
                element.classList.remove('placed');
                element.classList.add('rolling');
                element.style.setProperty('--stack', '0');
                element.style.left = `calc(50% + ${dx}px)`;
                element.style.top = `calc(50% + ${dy}px)`;
            });

            const finalStacks = this.getPoolStackIndexes(convertedSatansPool);

            // Animate through all faces
            let currentFaceIndex = 0;
            const animationInterval = setInterval(() => {
                const currentFace = faces[currentFaceIndex];
                if (!currentFace) return;

                // Update all dice to show current face
                for (const diceId in diceElements) {
                    const element = diceElements[diceId];
                    if (element) {
                        element.setAttribute('data-face', currentFace);
                        element.innerHTML = this.getDiceSymbol(currentFace);
                    }
                }

                currentFaceIndex++;

                // After cycling through all faces, show final results
                if (currentFaceIndex >= faces.length) {
                    clearInterval(animationInterval);

                    // Show final faces after a brief delay
                    setTimeout(() => {
                        for (const diceId in diceElements) {
                            const element = diceElements[diceId];
                            const finalFace = convertedSatansPool[diceId];

                            if (element && finalFace) {
                                element.setAttribute('data-face', finalFace);
                                element.innerHTML = this.getDiceSymbol(finalFace);
                                element.classList.remove('rolling');

                                // Release the gather position so the die glides to its symbol's slot
                                element.style.setProperty('--stack', String(finalStacks[diceId] ?? 0));
                                element.style.left = '';
                                element.style.top = '';
                                element.classList.add('placed');
                            }
                        }
                        // Resolve once the slide into place (0.5s CSS transition) has finished
                        setTimeout(resolve, 550);
                    }, 100);
                }
            }, 100); // 100ms between each face
        });
    }

    ///////////////////////////////////////////////////
    //// Utility Functions

    private getDiceSymbol(face: string): string {
        const symbols: DiceSymbols = {
            flame: `<img src="${g_gamethemeurl}img/flame_icon.svg" alt="Flame" class="dice-icon" />`,
            pentagram: `<img src="${g_gamethemeurl}img/pentagram_icon.svg" alt="Pentagram" class="dice-icon" />`,
            scythe: `<img src="${g_gamethemeurl}img/scythe_icon.svg" alt="Scythe" class="dice-icon" />`,
            trident: `<img src="${g_gamethemeurl}img/trident_icon.svg" alt="Trident" class="dice-icon" />`,
            skull: `<img src="${g_gamethemeurl}img/skull_icon.svg" alt="Skull" class="dice-icon" />`,
            imp: `<img src="${g_gamethemeurl}img/imp_icon.svg" alt="Imp" class="dice-icon" />`,
        };

        const symbol = symbols[face] || '?';
        return symbol;
    }

    private selectTarget(
        callback: (playerId: string) => void,
        isValidTarget?: (playerId: string) => {valid: boolean; reason?: string}
    ): void {
        const availablePlayers = Object.keys(this['gamedatas']?.players || {}).filter((id) => id !== this['player_id'].toString());

        if (availablePlayers.length === 0) {
            console.error('No available targets');
            return;
        }

        // Filter to valid targets if validator provided
        const validPlayers = isValidTarget
            ? availablePlayers.filter(id => isValidTarget(id).valid)
            : availablePlayers;

        if (validPlayers.length === 0) {
            this.showMessage(_('No valid targets available'), 'error');
            return;
        }

        if (validPlayers.length === 1 && availablePlayers.length === 1) {
            // Only one opponent and they're valid - execute directly
            const playerId = validPlayers[0];
            if (playerId) {
                callback(playerId);
            }
            return;
        }

        // Show target selection UI - clear existing buttons and add target buttons
        this['removeActionButtons']();

        availablePlayers.forEach((playerId) => {
            const playerName = this['gamedatas']?.players?.[playerId]?.name || `Player ${playerId}`;
            const validation = isValidTarget ? isValidTarget(playerId) : {valid: true};

            if (validation.valid) {
                // Valid target - add clickable button
                this['addActionButton'](`target_${playerId}_btn`, playerName, () => {
                    callback(playerId);
                });
            } else {
                // Invalid target - add disabled button with reason
                const buttonLabel = validation.reason
                    ? `${playerName} (${validation.reason})`
                    : playerName;
                this['addActionButton'](`target_${playerId}_btn`, buttonLabel, () => {
                    // Do nothing - button is disabled
                }, null, false, 'gray');
            }
        });

        // Add a back button to return to main actions
        this['addActionButton']('back_to_actions_btn', _('← Back'), () => {
            this['removeActionButtons']();
            this.addPlayerTurnButtons();
        });

        this.showMessage(_('Select a target player'), 'info');
    }

    private validateAction(action: string): boolean {
        // Add client-side validation logic here
        // For now, just return true
        return true;
    }

    private isActionPlayer(stateArgs?: any): boolean {
        // Check if the current player is the one who declared the action
        // IMPORTANT: Always prioritize stateArgs over cached gamedatas to get current state
        const gamedatas = this['gamedatas'] as GameData;

        console.log('isActionPlayer: Full gamedatas object:', gamedatas);
        console.log('isActionPlayer: currentActionPlayer value:', gamedatas?.currentActionPlayer);
        console.log('isActionPlayer: type of currentActionPlayer:', typeof gamedatas?.currentActionPlayer);
        console.log('isActionPlayer: stateArgs:', stateArgs);

        // Get action player from current state args (most up-to-date)
        let actionPlayerId: string | number | undefined;

        // Check multiple possible property names in stateArgs
        if (stateArgs && typeof stateArgs === 'object') {
            actionPlayerId = stateArgs.currentActionPlayer ||
                           stateArgs.actionPlayerId ||
                           stateArgs.current_action_player ||
                           stateArgs.actionPlayer;
            console.log('isActionPlayer: Found actionPlayerId in stateArgs:', actionPlayerId);
        }

        // If stateArgs doesn't have it, fallback to gamedatas (but this might be stale)
        if (actionPlayerId === undefined || actionPlayerId === null) {
            if (gamedatas && gamedatas.currentActionPlayer !== undefined && gamedatas.currentActionPlayer !== null) {
                actionPlayerId = gamedatas.currentActionPlayer;
                console.log('isActionPlayer: Using fallback actionPlayerId from gamedatas:', actionPlayerId);
            } else {
                console.log('isActionPlayer: No currentActionPlayer found - treating as no action player');
                // If we can't find an action player, assume current player is NOT the action player
                // This prevents the bug where everyone gets challenge buttons
                return false;
            }
        }

        // If actionPlayerId is 0 or empty, current player is not the action player
        if (!actionPlayerId || actionPlayerId === 0 || actionPlayerId === '0') {
            console.log('isActionPlayer: actionPlayerId is 0/empty - current player is NOT action player');
            return false;
        }

        const currentPlayerId = String(this['player_id']);
        const actionPlayerIdStr = String(actionPlayerId);
        const isAction = currentPlayerId === actionPlayerIdStr;

        console.log(`isActionPlayer check: currentPlayer=${currentPlayerId}, actionPlayer=${actionPlayerIdStr}, isActionPlayer=${isAction}`);
        return isAction;
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

    ///////////////////////////////////////////////////
    //// Confetti Animation

    private playConfettiAnimation(): void {
        console.log('Playing confetti animation!');

        // Create confetti container
        const confettiContainer = document.createElement('div');
        confettiContainer.id = 'confetti-container';
        confettiContainer.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
            overflow: hidden;
        `;
        document.body.appendChild(confettiContainer);

        // Create multiple confetti pieces
        const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#feca57', '#ff9ff3', '#54a0ff'];
        const confettiCount = 100;

        for (let i = 0; i < confettiCount; i++) {
            setTimeout(() => {
                this.createConfettiPiece(confettiContainer, colors);
            }, i * 50); // Stagger the creation
        }

        // Remove confetti container after animation
        setTimeout(() => {
            if (confettiContainer && confettiContainer.parentNode) {
                confettiContainer.parentNode.removeChild(confettiContainer);
            }
        }, 5000);
    }

    private createConfettiPiece(container: HTMLElement, colors: string[]): void {
        const confetti = document.createElement('div');
        const color = colors[Math.floor(Math.random() * colors.length)];
        const size = Math.random() * 10 + 5; // 5-15px
        const startX = Math.random() * window.innerWidth;
        const rotation = Math.random() * 360;
        const duration = Math.random() * 3000 + 2000; // 2-5 seconds

        confetti.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            background-color: ${color};
            top: -10px;
            left: ${startX}px;
            transform: rotate(${rotation}deg);
            border-radius: ${Math.random() > 0.5 ? '50%' : '0'};
        `;

        container.appendChild(confetti);

        // Animate the confetti falling
        const fallDistance = window.innerHeight + 20;
        const horizontalDrift = (Math.random() - 0.5) * 200; // -100 to 100px drift
        const rotationEnd = rotation + (Math.random() * 720 - 360); // Random rotation

        confetti.animate(
            [
                {
                    transform: `translate(0, 0) rotate(${rotation}deg)`,
                    opacity: 1,
                },
                {
                    transform: `translate(${horizontalDrift}px, ${fallDistance}px) rotate(${rotationEnd}deg)`,
                    opacity: 0,
                },
            ],
            {
                duration: duration,
                easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
                fill: 'forwards',
            }
        ).onfinish = () => {
            if (confetti && confetti.parentNode) {
                confetti.parentNode.removeChild(confetti);
            }
        };
    }
}

// BGA framework registration
require(['dojo/_base/declare'], function (declare) {
    declare('bgagame.devilsdice', GameGui, new DevilsDice());
});
