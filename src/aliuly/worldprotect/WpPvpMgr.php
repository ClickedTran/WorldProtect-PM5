<?php

declare(strict_types=1);

namespace aliuly\worldprotect;

//= cmd:pvp,Sub_Commands
//: Controls PvP in a world
//> usage: /wp  _[world]_ **pvp** _[on|off|spawn-off]_
//>   - /wp _[world]_ **pvp** **off**
//:     - no PvP is allowed.
//>   - /wp _[world]_ **pvp** **on**
//:     - PvP is allowed
//>   - /wp _[world]_ **pvp** **spawn-off**
//:     - PvP is allowed except if inside the spawn area.
//:
//= features
//: * Per World PvP

use aliuly\worldprotect\common\mc;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function count;
use function is_string;
use function strtolower;
use function substr;

class WpPvpMgr extends BaseWp implements Listener{
	public function __construct(Main $plugin){
		parent::__construct($plugin);
		$this->owner->getServer()->getPluginManager()->registerEvents($this, $this->owner);
		$this->enableSCmd("pvp", ["usage" => mc::_("[on|off|spawn-off]"),
			"help" => mc::_("Control PvP in world"),
			"permission" => "wp.cmd.pvp"]);
	}

	public function onSCommand(CommandSender $c, Command $cc, string $scmd, mixed $world, array $args) : bool{
		if($scmd != "pvp" || !is_string($world)) return false;
		if(count($args) === 0){
			$pvp = $this->owner->getCfg($world, "pvp", true);
			if($pvp === true){
				$c->sendMessage(mc::_("[WP] PvP in %1% is %2%", $world, TextFormat::RED . mc::_("ON")));
			}elseif($pvp === false){
				$c->sendMessage(mc::_("[WP] PvP in %1% is %2%", $world, TextFormat::GREEN . mc::_("OFF")));
			}else{
				$c->sendMessage(mc::_("[WP] PvP in %1% is %2%", $world, TextFormat::YELLOW . mc::_("Off in Spawn")));
			}
			return true;
		}
		if(count($args) !== 1) return false;
		switch(substr(strtolower($args[0]), 0, 2)){
			case "sp":
				$this->owner->setCfg($world, "pvp", "spawn-off");
				$this->owner->getServer()->broadcastMessage(TextFormat::YELLOW . mc::_("[WP] NO PvP in %1%'s spawn", $world));
				break;
			case "on":
			case "tr":
				$this->owner->unsetCfg($world, "pvp");
				$this->owner->getServer()->broadcastMessage(TextFormat::RED . mc::_("[WP] PvP is allowed in %1%", $world));
				break;
			case "of":
			case "fa":
				$this->owner->setCfg($world, "pvp", false);
				$this->owner->getServer()->broadcastMessage(TextFormat::GREEN . mc::_("[WP] NO PvP in %1%", $world));
				break;
			default:
				return false;
		}
		return true;
	}

	public function onPvP(EntityDamageEvent $ev) : void{
		if($ev->isCancelled()) return;
		if(!($ev instanceof EntityDamageByEntityEvent)) return;
		if(!(($pl = $ev->getEntity()) instanceof Player
			&& $ev->getDamager() instanceof Player)) return;
		$world = $pl->getWorld()->getFolderName();
		if(!isset($this->wcfg[$world])) return;
		if($this->wcfg[$world] !== false){
			$sp = $pl->getWorld()->getSpawnLocation();
			$dist = $sp->distance($pl->getPosition());
			//if ($dist > $this->owner->getServer()->getSpawnRadius()) return;
		}
		$this->owner->msg($ev->getDamager(), mc::_("You are not allowed to do that here"));
		$ev->cancel();
	}
}
