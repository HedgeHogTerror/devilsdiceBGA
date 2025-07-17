/// <amd-module name="bgagame/devilsdice"/>
/// <reference path="./types/all-bga-types.d.ts"/>

import 'ebg/counter';
import * as GameGui from 'ebg/core/gamegui';

import * as dom from 'dojo/dom';

/**
 * Client implementation of DevilsDice.
 */
export default class DevilsDice extends GameGui {
    // enable dynamic method calls
    [key: string]: any;

    constructor() {
        super();
        console.log('devilsdice constructor');
    }

    override setup(gamedatas: BGA.Gamedatas | any): void {
        console.log('Starting game setup');

        this.addBoardPieces(gamedatas);

        this.setupNotifications = () => {
            console.log('notifications subscriptions setup');

            this.bgaSetupPromiseNotifications({prefix: 'notification_'});
        };
        this.setupNotifications();

        console.log('Ending game setup');
    }

    override onEnteringState(stateName: string, args: any): void {
        console.log('Entering state: ' + stateName, args);

        var methodName = 'enteringState_' + stateName;
        if (typeof this[methodName] === 'function') {
            console.log('Calling ' + methodName);
            this[methodName](args);
        }
    }

    override onLeavingState(stateName: string): void {
        console.log('Leaving state: ' + stateName);
        var methodName = 'leavingState_' + stateName;
        if (typeof this[methodName] === 'function') {
            console.log('Calling ' + methodName);
            this[methodName]();
        }
    }

    override onUpdateActionButtons(stateName: string, args: any): void {
        console.log('onUpdateActionButtons: ' + stateName, args);
        var methodName = 'updateActionButtons_' + stateName;
        if (typeof this[methodName] === 'function') {
            console.log('Calling ' + methodName);
            this[methodName](args);
        }
    }

    ///////////////////////////////////////////////////
    //// State Handling

    ///////////////////////////////////////////////////
    //// Notification Handling

    ///////////////////////////////////////////////////
    //// Utility functions
    private addBoardPieces(gamedatas: BGA.Gamedatas | any): void {
        dom.byId('game_play_area')!.insertAdjacentHTML(
            'beforeend',
            `
                <div id="board">
                </div>
            `
        );
    }
}

require(['dojo/_base/declare'], function (declare) {
    declare('bgagame.devilsdice', GameGui, new DevilsDice());
});
