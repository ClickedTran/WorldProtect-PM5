<?php

declare(strict_types=1);
//= cmd:gm,Sub_Commands
//: Configures per world game modes
//> usage: /wp _[world]_ gm _[value]_
//:
//: Options:
//> - /wp _[world]_ **gm**
//:   - show current gamemode
//> - /wp _[world]_ **gm** _<mode>_
//:   - Sets the world gamemode to _mode_
//> - /wp _[world]_ **gm** **none**
//:   - Removes per world game mode
//:
//= features
//: * Per world game modes
namespace aliuly\worldprotect;

use aliuly\worldprotect\common\mc;
use aliuly\worldprotect\common\MPMU;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use function array_key_last;
use function count;
use function is_string;

class GmMgr extends BaseWp implements Listener{
	public function __construct(Main $plugin){
		parent::__construct($plugin);
		$this->enableSCmd("gm", ["usage" => mc::_("[value]"),
			"help" => mc::_("Sets the world game mode"),
			"permission" => "wp.cmd.gm",
			"aliases" => ["gamemode"]]);
		$this->owner->getServer()->getPluginManager()->registerEvents($this, $this->owner);
	}

	public function onSCommand(CommandSender $c, Command $cc, $scmd, mixed $world, array $args) : bool{
		if($scmd != "gm" || !is_string($world)) return false;
		if(count($args) == 0){
			$gm = $this->owner->getCfg($world, "gamemode", null);
			if($gm === null){
				$c->sendMessage(mc::_("[WP] No gamemode for %1%", $world));
			}else{
				$c->sendMessage(mc::_("[WP] %1% Gamemode: %2%", $world,
					MPMU::gamemodeStr($gm)));
			}
			return true;
		}
		if(count($args) != 1) return false;
		$newmode = GameMode::fromString($args[0]);
		if($newmode === null){
			$this->owner->unsetCfg($world, "gamemode");
			$this->owner->getServer()->broadcastMessage(mc::_("[WP] %1% gamemode removed", $world));
		}else{
			$this->owner->setCfg($world, "gamemode", $newmode->getAliases()[array_key_last($newmode->getAliases())]);
			$this->owner->getServer()->broadcastMessage(mc::_("[WP] %1% gamemode set to %2%",
				$world,
				$newmode->getEnglishName()));
		}
		return true;
	}

	/**
	 * @priority HIGHEST
	 */
	public function onTeleport(EntityTeleportEvent $ev) : void{
		if($ev->isCancelled()) return;

		$world = $ev->getTo()->getWorld();
		$oldWorld = $ev->getFrom()->getWorld();
		if($oldWorld->getId() === $world->getId()) return;

		$pl = $ev->getEntity();
		if(!($pl instanceof Player)) return;
		if($pl->hasPermission("wp.cmd.gm.exempt")) return;

		$world = $world->getFolderName();
		$gm = $this->owner->getCfg($world, "gamemode", null);
		if($gm === null)
			$gm = $this->owner->getServer()->getGamemode();
		if(!$gm instanceof GameMode)
			$gm = GameMode::fromString($gm);
		if($gm === null) return;
		$pl->sendMessage(mc::_("Changing gamemode to %1%", $gm->getEnglishName()));

		if($pl->getGamemode()->id() == $gm->id()) return;

		$pl->setGamemode($gm);
	}
}
