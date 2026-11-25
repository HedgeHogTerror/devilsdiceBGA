/// <amd-module name="bgagame/animator"/>

/**
 * GameObjectAnimator - Unified animation system for dice and token transfers
 *
 * Handles all animations for game objects moving between:
 * - Bank → Players
 * - Players ↔ Players
 * - Players ↔ Satan's Pool
 * - Players → Bank (payment)
 */

export interface TransferConfig {
    type: 'token' | 'die';
    source: string;        // Element ID or location identifier
    target: string;        // Element ID or location identifier
    count: number;
    face?: string | undefined; // For dice: face to show on the moving die (default: blank)
    delay?: number;        // Stagger delay for multiple objects (default: 100ms)
    duration?: number;     // Animation duration per object (default: 600ms)
    callback?: () => void; // Called after all animations complete
}

// Timings kept within BGA guidelines (~0.5s typical, 0.8s max per animation)
export const DIE_MOVE_MS = 600;
export const TOKEN_MOVE_MS = 500;
export const DIE_TUMBLE_MS = 450; // must match the die-tumble keyframes duration in the SCSS

export class GameObjectAnimator {
    private gameThemeUrl: string;
    private animationQueue: Promise<void> = Promise.resolve();
    private isActive: () => boolean;

    /**
     * @param isActive returns false when BGA wants no animations (fast replay, animations turned off,
     *                 tab hidden) - every animation then completes instantly
     */
    constructor(gameThemeUrl: string, isActive: () => boolean = () => true) {
        this.gameThemeUrl = gameThemeUrl;
        this.isActive = isActive;
    }

    /**
     * Get dice symbol HTML for a given face
     */
    private getDiceSymbol(face: string): string {
        const symbols: Record<string, string> = {
            flame: `<img src="${this.gameThemeUrl}img/flame_icon.svg" alt="Flame" class="dice-icon" />`,
            pentagram: `<img src="${this.gameThemeUrl}img/pentagram_icon.svg" alt="Pentagram" class="dice-icon" />`,
            scythe: `<img src="${this.gameThemeUrl}img/scythe_icon.svg" alt="Scythe" class="dice-icon" />`,
            trident: `<img src="${this.gameThemeUrl}img/trident_icon.svg" alt="Trident" class="dice-icon" />`,
            skull: `<img src="${this.gameThemeUrl}img/skull_icon.svg" alt="Skull" class="dice-icon" />`,
            imp: `<img src="${this.gameThemeUrl}img/imp_icon.svg" alt="Imp" class="dice-icon" />`,
        };

        return symbols[face] || '?';
    }

    /**
     * Main animation method - transfers game objects from source to target
     */
    async animateTransfer(config: TransferConfig): Promise<void> {
        const delay = config.delay ?? 100;
        const duration = config.duration ?? TOKEN_MOVE_MS;

        if (!this.isActive()) {
            config.callback?.();
            return Promise.resolve();
        }

        // Queue this animation to prevent overlapping animations
        this.animationQueue = this.animationQueue.then(async () => {
            const animations: Promise<void>[] = [];

            for (let i = 0; i < config.count; i++) {
                // Stagger each object's animation
                const staggerDelay = i * delay;

                animations.push(
                    new Promise<void>((resolve) => {
                        setTimeout(() => {
                            this.animateSingleObject(config, duration).then(resolve);
                        }, staggerDelay);
                    })
                );
            }

            // Wait for all objects to finish animating
            await Promise.all(animations);

            // Execute callback if provided
            if (config.callback) {
                config.callback();
            }
        });

        return this.animationQueue;
    }

    /**
     * Animate a single object from source to target
     */
    private async animateSingleObject(config: TransferConfig, duration: number): Promise<void> {
        return new Promise<void>((resolve) => {
            // Get source and target positions
            const sourceElement = this.getElement(config.source);
            const targetElement = this.getElement(config.target);

            if (!sourceElement || !targetElement) {
                console.warn(`Animation failed: source or target not found`, config);
                resolve();
                return;
            }

            const sourceRect = sourceElement.getBoundingClientRect();
            const targetRect = targetElement.getBoundingClientRect();

            // Create animated object
            const animatedObject = this.createAnimatedObject(config.type, config.face);

            // Position at source
            animatedObject.style.position = 'fixed';
            animatedObject.style.left = `${sourceRect.left + sourceRect.width / 2}px`;
            animatedObject.style.top = `${sourceRect.top + sourceRect.height / 2}px`;
            animatedObject.style.transform = 'translate(-50%, -50%)';
            animatedObject.style.zIndex = '1000';
            animatedObject.style.pointerEvents = 'none';
            animatedObject.style.transition = `all ${duration}ms ease-in-out`;

            // Add to DOM
            document.body.appendChild(animatedObject);

            // Trigger animation on next frame (allow browser to render initial position)
            requestAnimationFrame(() => {
                // Animate to target
                animatedObject.style.left = `${targetRect.left + targetRect.width / 2}px`;
                animatedObject.style.top = `${targetRect.top + targetRect.height / 2}px`;

                // Add a slight scale effect
                animatedObject.style.transform = 'translate(-50%, -50%) scale(1.2)';

                // Remove after animation completes
                setTimeout(() => {
                    animatedObject.style.opacity = '0';
                    animatedObject.style.transform = 'translate(-50%, -50%) scale(0.5)';

                    setTimeout(() => {
                        document.body.removeChild(animatedObject);
                        resolve();
                    }, 150);
                }, duration);
            });
        });
    }

    /**
     * Create the animated DOM element (token or die)
     */
    private createAnimatedObject(type: 'token' | 'die', face?: string): HTMLElement {
        const element = document.createElement('div');
        element.className = `animated-${type}`;

        if (type === 'token') {
            // Create skull token image
            const img = document.createElement('img');
            img.src = `${this.gameThemeUrl}img/SkullToken1.png`;
            img.style.width = '32px';
            img.style.height = '32px';
            img.style.background = '#000000';
            img.style.borderRadius = '50%';
            img.style.padding = '4px';
            img.style.boxSizing = 'content-box';
            img.style.filter = 'drop-shadow(0 4px 8px rgba(0, 0, 0, 0.5))';
            element.appendChild(img);
        } else {
            // Create die element
            element.style.width = '45px';
            element.style.height = '45px';
            element.style.background = '#000000';
            element.style.border = '3px solid #333';
            element.style.borderRadius = '6px';
            element.style.display = 'flex';
            element.style.alignItems = 'center';
            element.style.justifyContent = 'center';
            element.style.fontSize = '20px';
            element.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.5)';
            if (face) {
                element.innerHTML = this.getDiceSymbol(face);
            }
        }

        return element;
    }

    /**
     * Get DOM element by ID or special location identifier
     */
    private getElement(identifier: string): HTMLElement | null {
        // Handle special identifiers
        if (identifier === 'bank') {
            return document.getElementById('bank');
        }
        if (identifier === 'satans-pool') {
            return document.getElementById('satans-pool-container');
        }

        // Check if it's a player wealth area
        if (identifier.startsWith('player-')) {
            const playerId = identifier.replace('player-', '');
            return document.getElementById(`wealth_${playerId}`);
        }

        // Check if it's a player dice area
        if (identifier.startsWith('dice-')) {
            const playerId = identifier.replace('dice-', '');
            return document.getElementById(`dice_${playerId}`);
        }

        // Otherwise, treat as direct element ID
        return document.getElementById(identifier);
    }

    /**
     * Helper: Animate tokens from bank to player
     */
    async animateFromBank(target: string, count: number): Promise<void> {
        return this.animateTransfer({
            type: 'token',
            source: 'bank',
            target,
            count
        });
    }

    /**
     * Helper: Animate dice from bank to player
     */
    async animateDiceFromBank(target: string, count: number): Promise<void> {
        return this.animateTransfer({
            type: 'die',
            source: 'bank',
            target,
            count,
            duration: DIE_MOVE_MS
        });
    }

    /**
     * Helper: Animate dice to Satan's Pool
     */
    async animateToSatansPool(source: string, count: number): Promise<void> {
        return this.animateTransfer({
            type: 'die',
            source,
            target: 'satans-pool',
            count,
            duration: DIE_MOVE_MS
        });
    }

    /**
     * Helper: Animate objects between players
     */
    async animateBetweenPlayers(
        source: string,
        target: string,
        type: 'token' | 'die',
        count: number
    ): Promise<void> {
        const config: TransferConfig = {
            type,
            source,
            target,
            count
        };

        if (type === 'die') {
            config.duration = DIE_MOVE_MS;
        }

        return this.animateTransfer(config);
    }

    /**
     * Helper: Animate tokens to bank (payment)
     */
    async animateToBank(source: string, count: number): Promise<void> {
        return this.animateTransfer({
            type: 'token',
            source,
            target: 'bank',
            count
        });
    }

    /**
     * Helper: Animate dice from Satan's Pool to player
     */
    async animateFromSatansPool(target: string, count: number, source: string = 'satans-pool', face?: string): Promise<void> {
        return this.animateTransfer({
            type: 'die',
            source,
            target,
            count,
            face,
            duration: DIE_MOVE_MS
        });
    }

    /**
     * Animate dice reroll - each die does one quick tumble with its face hidden, then reveals
     * its final face (no rapid face flicker, per BGA accessibility guidelines)
     */
    async animateDiceReroll(
        containerId: string,
        finalDiceData: Record<string, string>,
        startDelay: number = 0
    ): Promise<void> {
        const animate = this.isActive();

        return new Promise<void>((resolve) => {
            setTimeout(() => {
                const container = document.getElementById(containerId);
                if (!container) {
                    console.warn(`Dice container not found: ${containerId}`);
                    resolve();
                    return;
                }

                const tumbleClass = animate ? ' tumbling' : '';
                let html = '';
                for (const diceId in finalDiceData) {
                    const finalFace = this.getFaceValue(finalDiceData[diceId]);
                    html += `<div class="die my-die${tumbleClass}" data-face="${finalFace}" data-dice-id="${diceId}">
                        ${this.getDiceSymbol(finalFace)}
                    </div>`;
                }
                container.innerHTML = html;

                if (!animate) {
                    resolve();
                    return;
                }

                setTimeout(() => {
                    container.querySelectorAll('.die.tumbling').forEach((el) => el.classList.remove('tumbling'));
                    resolve();
                }, DIE_TUMBLE_MS);
            }, animate ? startDelay : 0);
        });
    }

    /** Dice data may be a face string or a {face}/{dice_face}/{value} object */
    private getFaceValue(value: unknown): string {
        if (typeof value === 'string') return value;
        if (typeof value === 'object' && value !== null) {
            const v = value as any;
            return v.face || v.dice_face || v.value || '';
        }
        return '';
    }

    /**
     * Composite animation: Dice transfer followed by reroll (sequential)
     */
    async animateDiceTransferWithReroll(config: {
        source: string;
        target: string;
        count: number;
        playerId: string;
        newDiceData: Record<string, string>;
    }): Promise<void> {
        // First, play transfer animation
        await this.animateTransfer({
            type: 'die',
            source: config.source,
            target: config.target,
            count: config.count,
            duration: DIE_MOVE_MS
        });

        // Then, play reroll animation
        await this.animateDiceReroll(
            `dice_${config.playerId}`,
            config.newDiceData,
            0 // No delay - starts immediately after transfer
        );
    }
}
