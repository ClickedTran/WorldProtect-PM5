<?php

declare(strict_types=1);

namespace aliuly\worldprotect;

//= cmd:add,Sub_Commands
//: Add player to the authorized list
//> usage: /wp _[world]_ **add** _<player>_
//= cmd:rm,Sub_Commands
//: Removes player from the authorized list
//> usage: /wp _[world]_ **rm** _<player>_
//=  cmd:unlock,Sub_Commands
//: Removes protection
//> usage: /wp _[world]_ **unlock**
//= cmd:lock,Sub_Commands
//: Locks world, not even Op can use.
//> usage: /wp _[world]_ **lock**
//= cmd:protect,Sub_Commands
//: Protects world, only certain players can build.
//> usage: /wp _[world]_ **protect**
//:
//: When in this mode, only players in the _authorized_ list can build.
//: If there is no authorized list, it will use **wp.cmd.protect.auth**
//: permission instead.
//:
//= features
//: * Protect worlds from building/block breaking
//
//= docs
//: This plugin protects worlds from griefers by restricing placing and breaking
//: blocks.  Worlds have three protection levels:
//:
//: * unlock - anybody can place/break blocks
//: * protect - players in the _authorized_ list or, if the list is empty,
//:   players with **wp.cmd.protect.auth** permission can place/break
//:   blocks.
//: * lock - nobody (even *ops*) is allowed to place/break blocks.
//:

use aliuly\worldprotect\common\mc;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerBucketFillEvent;
use pocketmine\player\Player;
use function count;
use function is_string;
use function strtolower;

class WpProtectMgr extends BaseWp implements Listener{
	public function __construct(Main $plugin){
		parent::__construct($plugin);
		$this->owner->getServer()->getPluginManager()->registerEvents($this, $this->owner);
		$this->enableSCmd("add", ["usage" => mc::_("<user>"),
			"help" => mc::_("Add <user> to authorized list"),
			"permission" => "wp.cmd.addrm"]);
		$this->enableSCmd("rm", ["usage" => mc::_("<user>"),
			"help" => mc::_("Remove <user> from authorized list"),
			"permission" => "wp.cmd.addrm"]);
		$this->enableSCmd("unlock", ["usage" => "",
			"help" => mc::_("Unprotects world"),
			"permission" => "wp.cmd.protect",
			"aliases" => ["unprotect", "open"]]);
		$this->enableSCmd("lock", ["usage" => "",
			"help" => mc::_("Locked\n\tNobody (including op) can build"),
			"permission" => "wp.cmd.protect"]);
		$this->enableSCmd("protect", ["usage" => "",
			"help" => mc::_("Only authorized (or op) can build"),
			"permission" => "wp.cmd.protect"]);
	}

	public function onSCommand(CommandSender $c, Command $cc, string $scmd, mixed $world, array $args) : bool{
		if(!is_string($world)) return false;
		switch($scmd){
			case "add":
				if(!count($args)) return false;
				foreach($args as $i){
					$player = $this->owner->getServer()->getPlayerByPrefix($i);
					if(!$player){
						$player = $this->owner->getServer()->getOfflinePlayer($i);
						if($player == null || !$player->hasPlayedBefore()){
							$c->sendMessage(mc::_("[WP] %1%: not found", $i));
							continue;
						}
					}
					$iusr = strtolower($player->getName());
					$this->owner->authAdd($world, $iusr);
					$c->sendMessage(mc::_("[WP] %1% added to %2%'s auth list", $i, $world));
					if($player instanceof Player)
						$player->sendMessage(mc::_("[WP] You have been added to\n[WP] %1%'s auth list", $world));
				}
				return true;
			case "rm":
				if(!count($args)) return false;

				foreach($args as $i){
					$iusr = strtolower($i);
					if($this->owner->authCheck($world, $iusr)){
						$this->owner->authRm($world, $iusr);
						$c->sendMessage(mc::_("[WP] %1% removed from %2%'s auth list", $i, $world));
						$player = $this->owner->getServer()->getPlayerByPrefix($i);
						$player?->sendMessage(mc::_("[WP] You have been removed from\n[WP] %1%'s auth list", $world));
					}else{
						$c->sendMessage(mc::_("[WP] %1% not known", $i));
					}
				}
				return true;
			case "unlock":
				if(count($args)) return false;
				$this->owner->unsetCfg($world, "protect");
				$this->owner->getServer()->broadcastMessage(mc::_("[WP] %1% is now OPEN", $world));
				return true;
			case "lock":
				if(count($args)) return false;
				$this->owner->setCfg($world, "protect", $scmd);
				$this->owner->getServer()->broadcastMessage(mc::_("[WP] %1% is now LOCKED", $world));
				return true;
			case "protect":
				if(count($args)) return false;
				$this->owner->setCfg($world, "protect", $scmd);
				$this->owner->getServer()->broadcastMessage(mc::_("[WP] %1% is now PROTECTED", $world));
				return true;
		}
		return false;
	}

	protected function checkBlockPlaceBreak(Player $p) : bool{
		$world = $p->getWorld()->getFolderName();
		if(!isset($this->wcfg[$world])) return true;
		if($this->wcfg[$world] != "protect") return false; // LOCKED!
		return $this->owner->canPlaceBreakBlock($p, $world);
	}

	public function onBlockBreak(BlockBreakEvent $ev) : void{
		if($ev->isCancelled()) return;
		$pl = $ev->getPlayer();
		if($this->checkBlockPlaceBreak($pl)) return;
		$this->owner->msg($pl, mc::_("You are not allowed to do that here"));
		$ev->cancel();
	}

	public function onBlockPlace(BlockPlaceEvent $ev) : void{
		if($ev->isCancelled()) return;
		$pl = $ev->getPlayer();
		if($this->checkBlockPlaceBreak($pl)) return;
		$this->owner->msg($pl, mc::_("You are not allowed to do that here"));
		$ev->cancel();
	}

	public function onBucketEmpty(PlayerBucketEmptyEvent $ev) : void{
		if($ev->isCancelled()) return;

		$pl = $ev->getPlayer();
		if($this->checkBlockPlaceBreak($pl)) return;

		$this->owner->msg($pl, mc::_("You are not allowed to do that here"));
		$ev->cancel();
	}

	public function onBucketFill(PlayerBucketFillEvent $ev) : void{
		if($ev->isCancelled()) return;

		$pl = $ev->getPlayer();
		if($this->checkBlockPlaceBreak($pl)) return;

		$this->owner->msg($pl, mc::_("You are not allowed to do that here"));
		$ev->cancel();
	}
}
