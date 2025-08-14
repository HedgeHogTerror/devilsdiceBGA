<?php
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

class action_devilsdice extends APP_GameAction
{
	/** @var devilsdice $game */
	protected $game; // Enforces functions exist on Table class

	// Constructor: please do not modify
	public function __default()
	{
		if (self::isArg('notifwindow')) {
			$this->view = "common_notifwindow";
			$this->viewArgs['table'] = self::getArg("table", AT_posint, true);
		} else {
			$this->view = "devilsdice_devilsdice";
			self::trace("Complete reinitialization of board game");
		}
	}

	public function raiseHell()
	{
		self::setAjaxMode();

		$this->game->raiseHell(  );
		self::ajaxResponse();
	}

	public function harvestSkulls()
	{
		self::setAjaxMode();

		$this->game->harvestSkulls(  );
		self::ajaxResponse();
	}

	public function extort()
	{
		self::setAjaxMode();

		/** @var int $targetPlayerId */
		$targetPlayerId = self::getArg('targetPlayerId', AT_int, true);

		$this->game->extort( $targetPlayerId );
		self::ajaxResponse();
	}

	public function reapSoul()
	{
		self::setAjaxMode();

		/** @var int $targetPlayerId */
		$targetPlayerId = self::getArg('targetPlayerId', AT_int, true);

		$this->game->reapSoul( $targetPlayerId );
		self::ajaxResponse();
	}

	public function pentagram()
	{
		self::setAjaxMode();

		$this->game->pentagram(  );
		self::ajaxResponse();
	}

	public function impsSet()
	{
		self::setAjaxMode();

		$this->game->impsSet(  );
		self::ajaxResponse();
	}

	public function satansSteal()
	{
		self::setAjaxMode();

		/** @var int $targetPlayerId */
		$targetPlayerId = self::getArg('targetPlayerId', AT_int, true);
		/** @var bool $putInPool */
		$putInPool = self::getArg('putInPool', AT_bool, true);
		/** @var string $poolFace */
		$poolFace = self::getArg('poolFace', AT_alphanum, true);

		$this->game->satansSteal( $targetPlayerId, $putInPool, $poolFace );
		self::ajaxResponse();
	}

	public function challenge()
	{
		self::setAjaxMode();

		$this->game->challenge(  );
		self::ajaxResponse();
	}

	public function pass()
	{
		self::setAjaxMode();

		$this->game->pass(  );
		self::ajaxResponse();
	}

	public function block()
	{
		self::setAjaxMode();

		$this->game->block(  );
		self::ajaxResponse();
	}
}