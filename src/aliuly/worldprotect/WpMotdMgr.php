<?php

declare(strict_types=1);
//= cmd:/motd,Main_Commands
//: Shows the world's *motd* text
//> usage: /motd  _[world]_
//:
//: Shows the *motd* text of a _world_.  This can be used to show
//:   rules around a world.
//= cmd:motd,Sub_Commands
//: Modifies the world's *motd* text.
//> usage: /wp _[world]_ **motd** _<text>_
//:
//: Let's you modify the world's *motd* text.  The command only
//: supports a single line, however you can modify the *motd* text
//: by editing the **wpcfg.yml** file that is stored in the **world**
//: folder.  For example:
//: - [CODE]
//:   - motd:
//:     - line 1
//:     - line 2
//:     - line 3
//:     - line 4... etc
//: - [/CODE]
//= features
//: * Automatically displayed/per world MOTD

//= docs
//: Show a text file when players enter a world.  To explain players
//: what is allowed (or not allowed) in specific worlds.  For example
//: you could warn players when they are entering a PvP world.
//:

namespace aliuly\worldprotect;

use aliuly\worldprotect\common\mc;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use function array_shift;
use function count;
use function implode;
use function is_array;
use function is_string;

class WpMotdMgr extends BaseWp implements Listener, CommandExecutor{
	protected int $ticks = 15;
	protected bool $auto = true;

	static public function defaults() : array{
		return [
			//= cfg:motd
			"# ticks" => "line delay when showing multi-line motd texts.",
			"ticks" => 15,
			"# auto-motd" => "Automatically shows motd when entering world",
			"auto-motd" => true,
		];
	}

	public function __construct(Main $plugin, array $cfg){
		parent::__construct($plugin);
		Server::getInstance()->getPluginManager()->registerEvents($this, $this->owner);
		$this->ticks = $cfg["ticks"];
		$this->auto = $cfg["auto-motd"];
		$this->enableSCmd("motd", ["usage" => mc::_("[text]"),
			"help" => mc::_("Edits world motd text"),
			"permission" => "wp.cmd.wpmotd"]);

		$this->enableCmd("motd",
			["description" => mc::_("Shows world motd text"),
				"usage" => "/motd [world]",
				"permission" => "worldprotect.motd"]);
	}

	public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool{
		if($cmd->getName() != "motd") return false;
		if($sender instanceof Player){
			$world = $sender->getWorld()->getFolderName();
		}else{
			$world = $this->owner->getServer()->getWorldManager()->getDefaultWorld()?->getFolderName();
		}
		if(isset($args[0]) && $this->owner->getServer()->getWorldManager()->isWorldGenerated($args[0])){
			$world = array_shift($args);
		}
		if($world === null){
			$sender->sendMessage(mc::_("[WP] Must specify a world"));
			return false;
		}
		if(count($args) != 0) return false;
		$this->showMotd($sender, $world);
		return true;
	}

	public function onSCommand(CommandSender $c, Command $cc, $scmd, mixed $world, array $args) : bool{
		if($scmd != "motd" || !is_string($world)) return false;
		if(count($args) == 0){
			$this->owner->unsetCfg($world, "motd");
			$c->sendMessage(mc::_("[WP] motd for %1% removed", $world));
			return true;
		}
		$this->owner->setCfg($world, "motd", implode(" ", $args));
		$c->sendMessage(mc::_("[WP] motd for %1% updated", $world));
		return true;
	}

	private function showMotd(CommandSender $c, string $world) : void{
		if(!$c->hasPermission("worldprotect.motd")) return;

		$motd = $this->owner->getCfg($world, "motd", null);
		if($motd === null) return;
		if(is_array($motd)){
			if($c instanceof Player){
				$ticks = $this->ticks;
				foreach($motd as $ln){
					$this->owner->getScheduler()->scheduleDelayedTask(new ClosureTask(static fn() => $c->sendMessage($ln)), $ticks);
					$ticks += $this->ticks;
				}
			}else{
				foreach($motd as $ln){
					$c->sendMessage($ln);
				}
			}
		}else{
			$c->sendMessage($motd);
		}
	}

	public function onJoin(PlayerJoinEvent $ev) : void{
		if(!$this->auto) return;
		$pl = $ev->getPlayer();
		$this->showMotd($pl, $pl->getWorld()->getFolderName());
	}

	public function onLevelChange(EntityTeleportEvent $ev) : void{
		if($ev->getFrom()->getWorld()->getFolderName() !== $ev->getTo()->getWorld()->getFolderName()){

			if(!$this->auto) return;
			$pl = $ev->getEntity();
			if(!($pl instanceof Player)) return;
			$level = $ev->getEntity()->getWorld()->getFolderName();
			$this->showMotd($pl, $level);

		}
	}
}
