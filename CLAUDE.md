# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a BoardGameArena (BGA) implementation of "Devil's Dice" built using the bga-ts-template. The project uses TypeScript for client-side code and PHP for server-side game logic.

## Build and Development Commands

- `npm run build` - Build the TypeScript and SCSS files for production
- `npm run watch` - Build in watch mode for development (rebuilds on file changes)
- `npm run init` - Initialize the project (only needed once)

## Project Structure

### Client-Side (TypeScript/SCSS)
- `source/client/devilsdice.ts` - Main client-side game logic 
- `source/client/devilsdice.scss` - Styling for the game interface
- `source/client/build/` - Contains compiled output and type definitions
- `tsconfig.json` - TypeScript configuration with strict type checking

### Server-Side (PHP)
- `modules/php/Game.php` - Main server-side game logic
- `modules/php/Constants.inc.php` - Game constants and definitions
- `devilsdice.action.php` - Action handlers for player moves
- `states.inc.php` - Game state definitions (converted from JSON)

### Configuration Files
- `source/shared/gamestates.jsonc` - Complex game state machine with 10+ states including challenge/block mechanics
- `source/shared/gameinfos.jsonc` - Game metadata (2-6 players, 20min estimated duration)
- `source/shared/gameoptions.jsonc` - Game options configuration
- `source/shared/gamepreferences.jsonc` - Player preference settings
- `source/shared/stats.jsonc` - Game statistics definitions

## Game Architecture

### State Machine
The game uses a complex state machine with these key states:
- `playerTurn` (2) - Main action selection phase
- `challengeWindow` (3) - Players can challenge claims
- `resolveChallenge` (4) - Resolve challenge outcomes
- `resolveAction` (5) - Execute the chosen action
- `blockWindow` (6) - Target players can block actions
- `checkWin` (7) - Check for win conditions
- `chooseDiceOverflowFace` (9) - Handle dice overflow to Satan's pool
- `challengeBlock` (10) - Challenge blocking actions

### Player Actions
The game supports these main actions:
- `raiseHell` - Basic dice rolling action
- `harvestSkulls` - Collect skull dice
- `extort` - Take from another player
- `reapSoul` - Remove opponent's dice
- `pentagram` - Special pentagram action
- `impsSet` - Imp-related mechanics
- `satansSteal` - Satan's pool interactions

## Development Environment

### TypeScript Configuration
- Target: ES5 with AMD modules
- Strict type checking enabled
- Output bundled to `devilsdice.js`
- Source maps disabled for production

### VS Code Setup
- Uses BGA Extensions Pack
- Intelephense for PHP development
- Prettier for code formatting
- Live Sass Compiler for SCSS
- PHPUnit integration for testing

### File Watching
The project uses `tsc-watch` and Live Sass Compiler for automatic rebuilding during development.

## Testing

- PHPUnit configuration in `.phpunit.xml`
- Test directory exists but appears minimal
- Use `phpunit` commands for running tests

## Key Dependencies

- `bga-ts-template` - BGA development framework
- `typescript` - TypeScript compiler
- VS Code extensions for BGA development workflow

## Code Conventions

- PHP files use K&R brace style
- TypeScript/JavaScript uses Prettier formatting
- Game files prefixed with project name to avoid namespace collisions
- Shared configuration files use JSONC format with schema validation