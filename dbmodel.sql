-- ------
-- BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
-- DevilsDice implementation : © hedgehogterror, brookelfnichols@gmail.com
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- dbmodel.sql

-- This is the file where you are describing the database schema of your game
-- Basically, you just have to export from PhpMyAdmin your table structure and copy/paste
-- this export here.
-- Note that the database itself and the standard tables ("global", "stats", "gamelog" and "player") are
-- already created and must not be created here

-- Note: The database schema is created from this file when the game starts
--       If you modify this file, you will have to restart a game to see your changes in database

-- Player dice (hidden from other players except owner)
CREATE TABLE IF NOT EXISTS `player_dice` (
  `dice_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `player_id` int(10) unsigned NOT NULL,
  `face` varchar(20) NOT NULL,
  `location` varchar(20) NOT NULL DEFAULT 'hand',
  PRIMARY KEY (`dice_id`),
  KEY `player_id` (`player_id`),
  KEY `location` (`location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

-- Player tokens (skull tokens)
CREATE TABLE IF NOT EXISTS `player_tokens` (
  `player_id` int(10) unsigned NOT NULL,
  `skull_tokens` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Satan's pool dice (public to all players)
CREATE TABLE IF NOT EXISTS `satans_pool` (
  `dice_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `face` varchar(20) NOT NULL,
  PRIMARY KEY (`dice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

-- Game state tracking for challenges and actions
CREATE TABLE IF NOT EXISTS `game_actions` (
  `action_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `player_id` int(10) unsigned NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `target_player_id` int(10) unsigned DEFAULT NULL,
  `action_data` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`action_id`),
  KEY `player_id` (`player_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
