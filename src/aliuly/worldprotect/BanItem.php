<?php

declare(strict_types=1);
//= cmd:banitem|unbanitem,Sub_Commands
//: Control itmes that can/cannot be used
//> usage: /wp  _[world]_ **banitem|unbanitem** _[Item-ids]_
//:
//: Manages which Items can or can not be used in a given world.
//:  You can get a list of items currently banned
//:  if you do not specify any _[item-ids]_.  Otherwise these are
//:  added or removed from the list.
//:
//= features
//: * Ban specific items in a world
//
//= docs
//: Some items are able to modify a world by being consume (i.e. do not
//: need to be placed).  For example, _bonemeal_, _water or lava buckets_.
//: To prevent this type of griefing, you can use the **banitem**
//: feature.
//:

namespace aliuly\worldprotect;

use aliuly\worldprotect\common\ItemName;
use aliuly\worldprotect\common\mc;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\StringToItemParser;
use function count;
use function is_string;

class BanItem extends BaseWp implements Listener{
	public function __construct(Main $plugin){
		parent::__construct($plugin);
		$this->owner->getServer()->getPluginManager()->registerEvents($this, $this->owner);
		$this->enableSCmd("banitem", ["usage" => mc::_("[id] ..."),
			"help" => mc::_("Ban an item"),
			"permission" => "wp.cmd.banitem"]);
		$this->enableSCmd("unbanitem", ["usage" => mc::_("[id] ..."),
			"help" => mc::_("Unban item"),
			"permission" => "wp.cmd.banitem"]);
	}

	public function onSCommand(CommandSender $c, Command $cc, string $scmd, mixed $world, array $args) : bool{
		if(($scmd != "banitem" && $scmd != "unbanitem") || !is_string($world)) return false;
		if(count($args) === 0){
			$ids = $this->owner->getCfg($world, "banitem", []);
			if(count($ids) === 0){
				$c->sendMessage(mc::_("[WP] No banned items in %1%", $world));
			}else{
				$ln = mc::_("[WP] Items(%1%):", (string) count($ids));
				$q = "";
				foreach($ids as $id => $n){
					$ln .= "$q $n($id)";
					$q = ",";
				}
				$c->sendMessage($ln);
			}
			return true;
		}
		$cc = 0;

		$ids = $this->owner->getCfg($world, "banitem", []);
		if($scmd === "unbanitem"){
			foreach($args as $i){
				$item = StringToItemParser::getInstance()->parse($i) ?? LegacyStringToItemParser::getInstance()->parse($i);
				if(isset($ids[$item->getName()])){
					unset($ids[$item->getName()]);
					++$cc;
				}
			}
		}elseif($scmd === "banitem"){
			foreach($args as $i){
				$item = StringToItemParser::getInstance()->parse($i) ?? LegacyStringToItemParser::getInstance()->parse($i);
				if(isset($ids[$item->getName()])) continue;
				$ids[$item->getName()] = ItemName::str($item);
				++$cc;
			}
		}else{
			return false;
		}
		if($cc <= 0){
			$c->sendMessage(mc::_("No items updated"));
			return true;
		}
		if(count($ids) > 0){
			$this->owner->setCfg($world, "banitem", $ids);
		}else{
			$this->owner->unsetCfg($world, "banitem");
		}
		$c->sendMessage(mc::_("Items changed: %1%", (string) $cc));
		return true;
	}

	public function onInteract(PlayerInteractEvent $ev) : void{
		if($ev->isCancelled()) return;
		$pl = $ev->getPlayer();
		if($pl->hasPermission("wp.banitem.exempt")) return;
		$world = $pl->getWorld()->getFolderName();
		if(!isset($this->wcfg[$world])) return;
		$item = $ev->getItem();
		if(!isset($this->wcfg[$world][$item->getName()])) return;
		$pl->sendMessage(mc::_("You can not use that item here!"));
		$ev->cancel();
	}

	public function onConsume(PlayerItemConsumeEvent $ev) : void{
		if($ev->isCancelled()) return;
		$pl = $ev->getPlayer();
		if($pl->hasPermission("wp.banitem.exempt")) return;
		$world = $pl->getWorld()->getFolderName();
		if(!isset($this->wcfg[$world])) return;
		$item = $ev->getItem();
		if(!isset($this->wcfg[$world][$item->getName()])) return;
		$pl->sendMessage(mc::_("You can not use that item here!"));
		$ev->cancel();
	}

	public function onBlockPlace(BlockPlaceEvent $ev) : void{
		if($ev->isCancelled()) return;
		$pl = $ev->getPlayer();
		if($pl->hasPermission("wp.banitem.exempt")) return;
		$world = $pl->getWorld()->getFolderName();
		if(!isset($this->wcfg[$world])) return;
		$item = $ev->getItem();
		if(!isset($this->wcfg[$world][$item->getName()])) return;
		$pl->sendMessage(mc::_("You can not use that item here!"));
		$ev->cancel();
	}
}
