<?php

declare(strict_types=1);
//= cmd:unbreakable|breakable,Sub_Commands
//: Control blocks that can/cannot be broken
//> usage: /wp  _[world]_ **breakable|unbreakable** _[block-ids]_
//:
//: Manages which blocks can or can not be broken in a given world.
//: You can get a list of blocks currently set to **unbreakable**
//: if you do not specify any _[block-ids]_.  Otherwise these are
//: added or removed from the list.
//:
//= features
//: * Unbreakable blocks
namespace aliuly\worldprotect;

use aliuly\worldprotect\common\ItemName;
use aliuly\worldprotect\common\mc;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\StringToItemParser;
use function count;
use function is_string;

class Unbreakable extends BaseWp implements Listener{
	public function __construct(Main $plugin){
		parent::__construct($plugin);
		$this->owner->getServer()->getPluginManager()->registerEvents($this, $this->owner);
		$this->enableSCmd("unbreakable", ["usage" => mc::_("[id] [id]"),
			"help" => mc::_("Set block to unbreakable status"),
			"permission" => "wp.cmd.unbreakable",
			"aliases" => ["ubab"]]);
		$this->enableSCmd("breakable", ["usage" => mc::_("[id] [id]"),
			"help" => mc::_("Remove unbreakable status from block"),
			"permission" => "wp.cmd.unbreakable",
			"aliases" => ["bab"]]);
	}

	public function onSCommand(CommandSender $c, Command $cc, string $scmd, mixed $world, array $args) : bool{
		if(($scmd !== "breakable" && $scmd !== "unbreakable") || !is_string($world)) return false;
		if(count($args) === 0){
			$ids = $this->owner->getCfg($world, "unbreakable", []);
			if(count($ids) === 0){
				$c->sendMessage(mc::_("[WP] No unbreakable blocks in %1%", $world));
			}else{
				$ln = mc::_("[WP] Blocks(%1%):", (string) count($ids));
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
		$ids = $this->owner->getCfg($world, "unbreakable", []);
		if($scmd === "breakable"){
			foreach($args as $i){
				$item = StringToItemParser::getInstance()->parse($i) ?? LegacyStringToItemParser::getInstance()->parse($i);
				if(isset($ids[$item->getName()])){
					unset($ids[$item->getName()]);
					++$cc;
				}
			}
		}elseif($scmd === "unbreakable"){
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
			$c->sendMessage(mc::_("No blocks updated"));
			return true;
		}
		if(count($ids) > 0){
			$this->owner->setCfg($world, "unbreakable", $ids);
		}else{
			$this->owner->unsetCfg($world, "unbreakable");
		}
		$c->sendMessage(mc::_("Blocks changed: %1%", (string) $cc));
		return true;
	}

	public function onBlockBreak(BlockBreakEvent $ev) : void{
		if($ev->isCancelled()) return;
		$bl = $ev->getBlock();
		$world = $bl->getPosition()->getWorld()->getFolderName();
		if(!isset($this->wcfg[$world])) return;
		if(!isset($this->wcfg[$world][$bl->getName()])) return;
		$pl = $ev->getPlayer();
		$pl->sendMessage(mc::_("It can not be broken!"));
		$ev->cancel();
	}
}
