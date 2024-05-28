<?php

declare(strict_types=1);
//= cmd:bancmd|unbancmd,Sub_Commands
//: Prevents commands to be used in worlds
//> usage: /wp _[world]_ **bancmd|unbancmd** _[command]_
//:
//: If no commands are given it will show a list of banned
//: commands.   Otherwise the _command_ will be added/removed
//: from the ban list
//:
//= features
//: * Ban commands on a per world basis
namespace aliuly\worldprotect;

use aliuly\worldprotect\common\mc;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\server\CommandEvent;
use pocketmine\player\Player;
use function count;
use function implode;
use function is_string;
use function preg_split;
use function strtolower;
use function trim;

class BanCmd extends BaseWp implements Listener{
	public function __construct(Main $plugin){
		parent::__construct($plugin);
		$this->owner->getServer()->getPluginManager()->registerEvents($this, $this->owner);
		$this->enableSCmd("bancmd", ["usage" => mc::_("[command]"),
			"help" => mc::_("Bans the given command"),
			"permission" => "wp.cmd.bancmd"]);
		$this->enableSCmd("unbancmd", ["usage" => mc::_("[command]"),
			"help" => mc::_("Unbans command"),
			"permission" => "wp.cmd.bancmd"]);
	}

	public function onSCommand(CommandSender $c, Command $cc, string $scmd, mixed $world, array $args) : bool{
		if(($scmd != "bancmd" && $scmd != "unbancmd") || !is_string($world)) return false;
		if(count($args) === 0){
			$cmds = $this->owner->getCfg($world, "bancmds", []);
			if(count($cmds) === 0){
				$c->sendMessage(mc::_("[WP] No banned commands in %1%", $world));
			}else{
				$c->sendMessage(mc::_("[WP] Commands(%1%): %2%", (string) count($cmds), implode(", ", $cmds)));
			}
			return true;
		}
		$cc = 0;
		$cmds = $this->owner->getCfg($world, "bancmds", []);
		if($scmd === "unbancmd"){
			foreach($args as $i){
				$i = strtolower($i);
				if(isset($cmds[$i])){
					unset($cmds[$i]);
					++$cc;
				}
			}
		}elseif($scmd === "bancmd"){
			foreach($args as $i){
				$i = strtolower($i);
				if(isset($cmds[$i])) continue;
				$cmds[$i] = $i;
				++$cc;
			}
		}else{
			return false;
		}
		if($cc === 0){
			$c->sendMessage(mc::_("No commands updated"));
			return true;
		}
		if(count($cmds) > 0){
			$this->owner->setCfg($world, "bancmds", $cmds);
		}else{
			$this->owner->unsetCfg($world, "bancmds");
		}
		$c->sendMessage(mc::_("Commands changed: %1%", (string) $cc));
		return true;
	}

	/**
	 * @priority LOWEST
	 */
	public function onCmd(CommandEvent $ev) : void{
		if($ev->isCancelled()) return;
		$pl = $ev->getSender();
		$cmd = $ev->getCommand();
		if(!$pl instanceof Player) return;
		$world = $pl->getWorld()->getFolderName();
		if(!isset($this->wcfg[$world])) return;
		
		if(!isset($this->wcfg[$world][$cmd])) return;
		$pl->sendMessage(mc::_("That command is banned here!"));
		$ev->cancel();
	}
}
