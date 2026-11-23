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
    delay?: number;        // Stagger delay for multiple objects (default: 100ms)
    duration?: number;     // Animation duration per object (default: 600ms)
    callback?: () => void; // Called after all animations complete
}

export class GameObjectAnimator {
    private gameThemeUrl: string;
    private animationQueue: Promise<void> = Promise.resolve();

    constructor(gameThemeUrl: string) {
        this.gameThemeUrl = gameThemeUrl;
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
        const duration = config.duration ?? 600;

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
            const animatedObject = this.createAnimatedObject(config.type);

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
                    }, 200);
                }, duration);
            });
        });
    }

    /**
     * Create the animated DOM element (token or die)
     */
    private createAnimatedObject(type: 'token' | 'die'): HTMLElement {
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
            element.textContent = '🎲';
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
            duration: 1000
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
            duration: 1000
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

        // Set dice duration to 1000ms
        if (type === 'die') {
            config.duration = 1000;
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
    async animateFromSatansPool(target: string, count: number): Promise<void> {
        return this.animateTransfer({
            type: 'die',
            source: 'satans-pool',
            target,
            count,
            duration: 1000
        });
    }

    /**
     * Animate dice reroll - cycles through all faces before showing final result
     */
    async animateDiceReroll(
        containerId: string,
        finalDiceData: Record<string, string>,
        startDelay: number = 0
    ): Promise<void> {
        return new Promise<void>((resolve) => {
            setTimeout(() => {
                const container = document.getElementById(containerId);
                if (!container) {
                    console.warn(`Dice container not found: ${containerId}`);
                    resolve();
                    return;
                }

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
                            resolve();
                        }, 100);
                    }
                }, 100); // 100ms between each face
            }, startDelay);
        });
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
            duration: 1000
        });

        // Then, play reroll animation
        await this.animateDiceReroll(
            `dice_${config.playerId}`,
            config.newDiceData,
            0 // No delay - starts immediately after transfer
        );
    }
}
