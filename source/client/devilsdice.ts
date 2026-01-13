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
    playerNewTokens?: number;
    targetNewTokens?: number;
    face?: string;
    pentagrams?: number;
    imps?: number;
    satansPool?: Record<string, string>;
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
        const currentPlayerId = this['player_id'];
        const currentPlayerTokens = this.playerTokens[currentPlayerId] || 0;

        const actions = [
            {id: 'raiseHell', label: _('Raise Hell (Flame)'), handler: 'onRaiseHell', enabled: true},
            {id: 'harvestSkulls', label: _('Harvest Skulls (Skull)'), handler: 'onHarvestSkulls', enabled: true},
            {id: 'extort', label: _('Extort (Trident)'), handler: 'onExtort', enabled: true},
            {id: 'reapSoul', label: _('Reap Soul (Scythe) - 2 skulls'), handler: 'onReapSoul', enabled: currentPlayerTokens >= 2},
            {id: 'pentagram', label: _('Pentagram'), handler: 'onPentagram', enabled: true},
            {id: 'impsSet', label: _("Imp's Set"), handler: 'onImpsSet', enabled: true},
            {id: 'satansSteal', label: _("Satan's Steal - 6 skulls"), handler: 'onSatansSteal', enabled: currentPlayerTokens >= 6},
        ];

        actions.forEach((action) => {
            const buttonId = `${action.id}_btn`;
            this['addActionButton'](buttonId, action.label, action.enabled ? action.handler : null);

            // Disable button if not enough tokens
            if (!action.enabled) {
                const button = document.getElementById(buttonId);
                if (button) {
                    button.classList.add('disabled');
                    button.style.opacity = '0.5';
                    button.style.cursor = 'not-allowed';
                    button.title = _('Not enough skull tokens');
                }
            }
        });
    }

    private addChallengeButtons(): void {
        console.log('🚨 addChallengeButtons: ADDING CHALLENGE BUTTONS TO UI');
        console.log('🚨 Current player:', this['player_id']);
        console.log('🚨 Stack trace:', new Error().stack);

        this['addActionButton']('challenge_btn', _('Challenge'), 'onChallenge');
        this['addActionButton']('pass_btn', _('Pass'), 'onPass');

        console.log('🚨 Challenge buttons added successfully');
    }

    private addBlockButtons(): void {
        this['addActionButton']('block_btn', _('Block'), 'onBlock');
        this['addActionButton']('pass_btn', _('Pass'), 'onPass');
    }

    private addDiceOverflowButtons(): void {
        // Add face selection buttons for Satan's pool
        const faces = [
            {id: 'flame', label: _('Flame'), icon: '🔥'},
            {id: 'skull', label: _('Skull'), icon: '💀'},
            {id: 'trident', label: _('Trident'), icon: '🔱'},
            {id: 'scythe', label: _('Scythe'), icon: '⚰️'},
            {id: 'pentagram', label: _('Pentagram'), icon: '⭐'},
            {id: 'imp', label: _('Imp'), icon: '👹'},
        ];

        faces.forEach((face) => {
            this['addActionButton'](`overflow_${face.id}_btn`, `${face.icon} ${face.label}`, () => {
                this['bgaPerformAction']('chooseDiceOverflowFace', {face: face.id});
            });
        });
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
            // Check how many other players are available
            const availablePlayers = Object.keys(this['gamedatas']?.players || {}).filter((id) => id !== this['player_id'].toString());

            if (availablePlayers.length === 0) {
                console.error('No available targets for Reap Soul');
                return;
            } else if (availablePlayers.length === 1) {
                // Only one opponent, execute directly
                const targetId = availablePlayers[0];
                if (targetId) {
                    this['bgaPerformAction']('reapSoul', {targetPlayerId: parseInt(targetId)});
                }
            } else {
                // Multiple opponents, show target selection
                this.showReapSoulTargetSelection();
            }
        }
    }

    private showReapSoulTargetSelection(): void {
        // Clear existing action buttons
        this['removeActionButtons']();

        // Get available players
        const availablePlayers = Object.keys(this['gamedatas']?.players || {}).filter((id) => id !== this['player_id'].toString());

        // Add target selection buttons
        availablePlayers.forEach((playerId) => {
            const playerName = this['gamedatas']?.players?.[playerId]?.name || `Player ${playerId}`;
            this['addActionButton'](`reap_target_${playerId}_btn`, playerName, () => {
                this['bgaPerformAction']('reapSoul', {targetPlayerId: parseInt(playerId)});
            });
        });

        // Add a back button to return to main actions
        this['addActionButton']('back_to_actions_btn', _('← Back'), () => {
            this['removeActionButtons']();
            this.addPlayerTurnButtons();
        });
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
        this.updateDisplay();
    };

    notification_diceToSatansPool = (notif: {args: NotificationArgs}): void => {
        console.log("Dice to Satan's pool", notif);
        const face = notif.args.face;
        const playerId = notif.args.playerId;

        if (face) {
            // Add the new die to Satan's pool with a unique ID
            const newDiceId = `pool_${Date.now()}_${Math.random()}`;
            this.satansPool[newDiceId] = face;
            console.log(`Added die to Satan's pool: ${newDiceId} = ${face}`);
        }

        this.updateMyDice(true); // true = animate the dice roll
        this.updateSatansPool();
        this.updatePlayersInfo(); // Update dice counts for all players
    };

    notification_raiseHell = (notif: {args: NotificationArgs}): void => {
        const playerId = notif.args.playerId;
        const tokens = notif.args.tokens;
        const diceCount = notif.args.diceCount;

        console.log(`Updating player ${playerId}: tokens=${tokens}, diceCount=${diceCount}`);

        // Update player tokens if provided
        if (playerId && tokens !== undefined) {
            console.log(`Before update - playerTokens[${playerId}] = ${this.playerTokens[playerId]}`);
            this.playerTokens[playerId] = tokens;
            console.log(`After update - playerTokens[${playerId}] = ${this.playerTokens[playerId]}`);
        }

        // Update dice count if provided
        if (playerId && diceCount !== undefined) {
            console.log(`Before update - playerDiceCounts[${playerId}] = ${this.playerDiceCounts[playerId]}`);
            this.playerDiceCounts[playerId] = diceCount;
            console.log(`After update - playerDiceCounts[${playerId}] = ${this.playerDiceCounts[playerId]}`);
        }

        // If this is the current player, we need to wait for the diceRolled notification
        // to get the new dice data, but let's update the display now for the token changes
        this.updateDisplay();
    };

    notification_diceRerolled = (notif: {args: NotificationArgs}): void => {
        console.log('Dice rerolled notification received:', notif);
        const playerId = notif.args.playerId;
        const dice = notif.args.dice || {};
        const diceCount = notif.args.diceCount;

        // Update the dice count for the specific player
        if (playerId && diceCount !== undefined) {
            this.playerDiceCounts[playerId] = diceCount;
            this.updatePlayersInfo(); // Refresh the display for all players
        }

        // If this is the current player, update their dice display with animation
        // Convert both to strings for comparison to handle type mismatches
        if (String(playerId) === String(this['player_id']) && dice && Object.keys(dice).length > 0) {
            this.myDice = dice;
            this.updateMyDice(true); // true = animate the dice roll
            console.log('My dice updated successfully from diceRerolled with animation');
        } else {
            console.log('NOT updating my dice because:');
            console.log(`- Player ID match: ${String(playerId) === String(this['player_id'])}`);
            console.log(`- Dice data exists: ${dice && Object.keys(dice).length > 0}`);
        }
    };

    notification_harvestSkulls = (notif: {args: NotificationArgs}): void => {
        console.log('Harvest Skulls action notification received:', notif);
        const playerId = notif.args.playerId;
        const tokens = notif.args.tokens;

        console.log(`Updating player ${playerId}: tokens=${tokens}`);

        // Update player tokens if provided
        if (playerId && tokens !== undefined) {
            console.log(`Before update - playerTokens[${playerId}] = ${this.playerTokens[playerId]}`);
            this.playerTokens[playerId] = tokens;
            console.log(`After update - playerTokens[${playerId}] = ${this.playerTokens[playerId]}`);
        }
        this.updateDisplay();
    };

    notification_extort = (notif: {args: NotificationArgs}): void => {
        console.log('Extort action notification received:', notif);
        const playerId = notif.args.playerId;
        const targetId = notif.args.targetId;
        const playerNewTokens = notif.args.playerNewTokens;
        const targetNewTokens = notif.args.targetNewTokens;

        console.log(`Updating extort - Player ${playerId}: ${playerNewTokens} tokens, Target ${targetId}: ${targetNewTokens} tokens`);

        // Update both players' token counts
        if (playerId && playerNewTokens !== undefined) {
            console.log(`Before update - playerTokens[${playerId}] = ${this.playerTokens[playerId]}`);
            this.playerTokens[playerId] = playerNewTokens;
            console.log(`After update - playerTokens[${playerId}] = ${this.playerTokens[playerId]}`);
        }

        if (targetId && targetNewTokens !== undefined) {
            console.log(`Before update - playerTokens[${targetId}] = ${this.playerTokens[targetId]}`);
            this.playerTokens[targetId] = targetNewTokens;
            console.log(`After update - playerTokens[${targetId}] = ${this.playerTokens[targetId]}`);
        }

        console.log('Calling updateDisplay() after Extort');
        this.updateDisplay();
    };

    notification_reapSoul = (notif: {args: NotificationArgs}): void => {
        console.log('Reap Soul action', notif);
        this.updateDisplay();
    };

    notification_pentagram = (notif: {args: NotificationArgs}): void => {
        console.log('Pentagram action', notif);

        // Update Satan's pool with animation if provided
        if (notif.args.satansPool) {
            this['animateSatansPoolReroll'](notif.args.satansPool);
        } else {
            this.updateDisplay();
        }
    };

    notification_impsSet = (notif: {args: NotificationArgs}): void => {
        console.log("Imp's Set action", notif);
        this.updateDisplay();
    };

    notification_satansSteal = (notif: {args: NotificationArgs}): void => {
        console.log("Satan's Steal action notification received:", notif);
        const playerId = notif.args.playerId;
        const playerNewTokens = notif.args.playerNewTokens;

        console.log(`Updating Satan's Steal - Player ${playerId}: ${playerNewTokens} tokens`);

        // Update player's token count
        if (playerId && playerNewTokens !== undefined) {
            console.log(`Before update - playerTokens[${playerId}] = ${this.playerTokens[playerId]}`);
            this.playerTokens[playerId] = playerNewTokens;
            console.log(`After update - playerTokens[${playerId}] = ${this.playerTokens[playerId]}`);
        }

        console.log("Calling updateDisplay() after Satan's Steal");
        this.updateDisplay();
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
                    <div class="player-skull-tokens"><img src="${g_gamethemeurl}img/skull_tokens.png" alt="Skulls" class="skull-token-icon" /> ${tokens}</div>
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

    private updateMyDice(animate: boolean = false): void {
        const myDiceArea = this.uiElements.myDice;
        if (!myDiceArea) return;

        console.log('updateMyDice: myDice data structure:', this.myDice);

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

    private animateSatansPoolReroll(newSatansPool: Record<string, string>): void {
        console.log("Animating Satan's pool reroll with new data:", newSatansPool);

        const satansPoolArea = this.uiElements.satansPool;
        if (!satansPoolArea) {
            console.error("Satan's pool area not found");
            return;
        }

        // Convert Satan's pool data from backend format to frontend format
        const convertedSatansPool: Record<string, string> = {};
        if (newSatansPool) {
            console.log("Raw Satan's pool data from notification:", newSatansPool);

            // Handle both formats: direct key-value pairs or array of objects
            for (const [key, entry] of Object.entries(newSatansPool)) {
                if (typeof entry === 'object' && entry !== null && 'dice_id' in entry && 'face' in entry) {
                    // Format: {dice_id: "1", face: "imp"}
                    const diceEntry = entry as {dice_id: string; face: string};
                    convertedSatansPool[diceEntry.dice_id] = diceEntry.face;
                    console.log(`Converted Satan's pool die ${diceEntry.dice_id}: ${diceEntry.face}`);
                } else if (typeof entry === 'string') {
                    // Format: {"1": "imp"}
                    convertedSatansPool[key] = entry;
                    console.log(`Direct Satan's pool die ${key}: ${entry}`);
                }
            }
        }

        // Update our internal data
        this.satansPool = convertedSatansPool;

        // If there are no dice in Satan's pool, just update normally
        if (Object.keys(convertedSatansPool).length === 0) {
            this.updateSatansPool();
            return;
        }

        const faces = ['flame', 'skull', 'trident', 'scythe', 'pentagram', 'imp'];
        const diceElements: Record<string, HTMLElement> = {};

        // Create initial dice elements for animation
        let html = '';
        for (const diceId in convertedSatansPool) {
            html += `<div class="die pool-die rolling" data-face="flame" data-dice-id="${diceId}">
                ${this.getDiceSymbol('flame')}
            </div>`;
        }
        satansPoolArea.innerHTML = html;

        // Cache dice elements
        const diceNodes = satansPoolArea.querySelectorAll('.die');
        const diceIds = Object.keys(convertedSatansPool);
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
                        const finalFace = convertedSatansPool[diceId];

                        if (element && finalFace) {
                            element.setAttribute('data-face', finalFace);
                            element.innerHTML = this.getDiceSymbol(finalFace);
                            element.classList.remove('rolling');
                        }
                    }
                }, 100);
            }
        }, 100); // 100ms between each face
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
