<?php

declare(strict_types=1);
//= cmd:/worldprotect,Main_Commands
//: Main WorldProtect command
//> usage: /worldprotect  _[world]_ _<subcmd>_ _[options]_
//= cfg:features
//: This section you can enable/disable modules.
//: You do this in order to avoid conflicts between different
//: PocketMine-MP plugins.  It has one line per feature:
//:
//:     feature: true|false
//:
//: If **true** the feature is enabled.  if **false** the feature is disabled.
//:
namespace aliuly\worldprotect;

use aliuly\worldprotect\common\BasicPlugin;
use aliuly\worldprotect\common\mc;
use aliuly\worldprotect\common\MPMU;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\world\World;
use function array_shift;
use function count;
use function in_array;
use function is_dir;
use function is_file;
use function mkdir;
use function strtolower;
use function time;
use function unlink;

class Main extends BasicPlugin implements Listener{
	/** @var array[] $wcfg */
	protected array $wcfg = [];
	const SPAM_DELAY = 5;

	public function onEnable() : void{
		if(!is_dir($this->getDataFolder())) mkdir($this->getDataFolder());
		mc::plugin_init($this, $this->getFile());
		$cfg = $this->modConfig(__NAMESPACE__, [
			"max-players" => ["MaxPlayerMgr", false],
			"protect" => ["WpProtectMgr", true],
			"border" => ["WpBordersMgr", true],
			"pvp" => ["WpPvpMgr", true],
			"motd" => ["WpMotdMgr", false],
			"no-explode" => ["NoExplodeMgr", false],
			"unbreakable" => ["Unbreakable", false],
			"bancmds" => ["BanCmd", true],
			"banitem" => ["BanItem", true],
			"gamemode" => ["GmMgr", false],
			"gm-save-inv" => ["SaveInventory", false]
		], [
			"version" => $this->getDescription()->getVersion(),
			"motd" => WpMotdMgr::defaults(),
		], mc::_("/%s [world] %s %s"));
		$this->modules[] = new WpList($this);

		// Make sure that loaded worlds are indeed loaded...
		foreach($this->getServer()->getWorldManager()->getWorlds() as $lv){
			$this->loadCfg($lv);
		}
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

	}

	//////////////////////////////////////////////////////////////////////
	//
	// Save/Load configurations
	//
	//////////////////////////////////////////////////////////////////////
	public function loadCfg(string|World $world) : bool{
		if($world instanceof World) $world = $world->getFolderName();
		if(isset($this->wcfg[$world])) return true; // world is already loaded!
		if(!$this->getServer()->getWorldManager()->isWorldGenerated($world)) return false;
		if(!$this->getServer()->getWorldManager()->isWorldLoaded($world)){
			$path = $this->getServer()->getDataPath() . "worlds/" . $world . "/";
		}else{
			$level = $this->getServer()->getWorldManager()->getWorldByName($world);
			if($level === null) return false;
			$path = $level->getProvider()->getPath();
		}
		$path .= "wpcfg.yml";
		if(is_file($path)){
			$this->wcfg[$world] = (new Config($path, Config::YAML, []))->getAll();
			foreach($this->modules as $i => $mod){
				if(!($mod instanceof BaseWp)) continue;
				if(isset($this->wcfg[$world][$i])){
					$mod->setCfg($world, $this->wcfg[$world][$i]);
				}else{
					$mod->unsetCfg($world);
				}
			}
		}else{
			$this->wcfg[$world] = [];
			foreach($this->modules as $i => $mod){
				if(!($mod instanceof BaseWp)) continue;
				$mod->unsetCfg($world);
			}
		}
		return true;
	}

	public function saveCfg(string|World $world) : bool{
		if($world instanceof World) $world = $world->getFolderName();
		if(!isset($this->wcfg[$world])) return false; // Nothing to save!
		if(!$this->getServer()->getWorldManager()->isWorldGenerated($world)) return false;
		if(!$this->getServer()->getWorldManager()->isWorldLoaded($world)){
			$path = $this->getServer()->getDataPath() . "worlds/" . $world . "/";
		}else{
			$level = $this->getServer()->getWorldManager()->getWorldByName($world);
			if($level === null) return false;
			$path = $level->getProvider()->getPath();
		}
		$path .= "wpcfg.yml";
		if(count($this->wcfg[$world]) > 0){
			$yaml = new Config($path, Config::YAML, []);
			$yaml->setAll($this->wcfg[$world]);
			$yaml->save();
		}else{
			unlink($path);
		}
		return true;
	}

	public function unloadCfg(string|World $world): void{
		if($world instanceof World) $world = $world->getFolderName();
		if(isset($this->wcfg[$world])) unset($this->wcfg[$world]);
		foreach($this->modules as $i => $mod){
			if(!($mod instanceof BaseWp)) continue;
			$mod->unsetCfg($world);
		}
	}

	public function getCfg(string|World $world, string $key, mixed $default) : mixed{
		if($world instanceof World) $world = $world->getFolderName();
		if($this->getServer()->getWorldManager()->isWorldLoaded($world))
			$unload = false;
		else{
			$unload = true;
			if(!$this->loadCfg($world)) return $default;
		}
		if(isset($this->wcfg[$world]) && isset($this->wcfg[$world][$key])){
			$res = $this->wcfg[$world][$key];
		}else{
			$res = $default;
		}
		if($unload) $this->unloadCfg($world);
		return $res;
	}

	public function setCfg(string|World $world, string $key, mixed $value) : bool{
		if($world instanceof World) $world = $world->getFolderName();
		if($this->getServer()->getWorldManager()->isWorldLoaded($world))
			$unload = false;
		else{
			$unload = true;
			if(!$this->loadCfg($world)) return false;
		}
		if(!isset($this->wcfg[$world]) || !isset($this->wcfg[$world][$key]) ||
			$value !== $this->wcfg[$world][$key]){
			if(!isset($this->wcfg[$world])) $this->wcfg[$world] = [];
			$this->wcfg[$world][$key] = $value;
			$this->saveCfg($world);
		}
		if(isset($this->modules[$key])
			&& ($this->modules[$key] instanceof BaseWp))
			$this->modules[$key]->setCfg($world, $value);
		if($unload) $this->unloadCfg($world);
		return true;
	}

	public function unsetCfg(string|World $world, string $key) : bool{
		if($world instanceof World) $world = $world->getFolderName();
		if($this->getServer()->getWorldManager()->isWorldLoaded($world))
			$unload = false;
		else{
			$unload = true;
			if(!$this->loadCfg($world)) return false;
		}
		if(isset($this->wcfg[$world])){
			if(isset($this->wcfg[$world][$key])){
				unset($this->wcfg[$world][$key]);
				$this->saveCfg($world);
			}
		}
		if(isset($this->modules[$key])
			&& ($this->modules[$key] instanceof BaseWp))
			$this->modules[$key]->unsetCfg($world);
		if($unload) $this->unloadCfg($world);
		return true;
	}

	//////////////////////////////////////////////////////////////////////
	//
	// Event handlers
	//
	//////////////////////////////////////////////////////////////////////
	public function onLevelLoad(WorldLoadEvent $e) : void{
		$this->loadCfg($e->getWorld());
	}

	public function onLevelUnload(WorldUnloadEvent $e) : void{
		$this->unloadCfg($e->getWorld());
	}

	//////////////////////////////////////////////////////////////////////
	//
	// Command dispatcher
	//
	//////////////////////////////////////////////////////////////////////
	public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool{
		if($cmd->getName() != "worldprotect") return false;
		if($sender instanceof Player){
			$world = $sender->getWorld()->getFolderName();
		}else{
			$world = $this->getServer()->getWorldManager()->getDefaultWorld()?->getFolderName();
		}
		if(isset($args[0]) && $this->getServer()->getWorldManager()->isWorldGenerated($args[0])){
			$world = array_shift($args);
		}
		if($world === null){
			$sender->sendMessage(mc::_("[WP] Must specify a world"));
			return false;
		}
		if(!$this->isAuth($sender, $world)) return true;
		return $this->dispatchSCmd($sender, $cmd, $args, $world);
	}

	public function canPlaceBreakBlock(Player $c, string $world){
		$pname = strtolower($c->getName());
		if(isset($this->wcfg[$world]["auth"]) && count($this->wcfg[$world]["auth"]) > 0){
			// Check if user is in auth list...
			if(isset($this->wcfg[$world]["auth"][$pname])) return true;
			return false;
		}
		if($c->hasPermission("wp.cmd.protect.auth")) return true;
		return false;
	}

	public function isAuth(CommandSender $c, string $world){
		if(!($c instanceof Player)) return true;
		if(!isset($this->wcfg[$world])) return true;
		if(!isset($this->wcfg[$world]["auth"])) return true;
		if(!count($this->wcfg[$world]["auth"])) return true;

		$iusr = strtolower($c->getName());

		if(in_array($iusr, $this->wcfg[$world]["auth"], true)) return true;
		$c->sendMessage(mc::_("[WP] You are not allowed to do this"));
		return false;
	}

	public function authAdd(string $world, string $usr) : void{
		$auth = $this->getCfg($world, "auth", []);
		if(isset($auth[$usr])) return;
		$auth[$usr] = $usr;
		$this->setCfg($world, "auth", $auth);
	}

	public function authCheck(string $world, string $usr) : bool{
		$auth = $this->getCfg($world, "auth", []);
		return isset($auth[$usr]);
	}

	public function authRm(string $world, string $usr) : void{
		$auth = $this->getCfg($world, "auth", []);
		if(!isset($auth[$usr])) return;
		unset($auth[$usr]);
		if(count($auth)){
			$this->setCfg($world, "auth", $auth);
		}else{
			$this->unsetCfg($world, "auth");
		}
	}

	public function msg(Player $pl, string $txt) : void{
		if(MPMU::apiVersion("2.0.0")){
			$pl->sendTip($txt);
			return;
		}
		[$time, $otxt] = $this->getState("spam", $pl, [0, ""]);
		if(time() - $time < self::SPAM_DELAY && $otxt == $txt) return;
		$this->setState("spam", $pl, [time(), $txt]);
		$pl->sendMessage($txt);
	}

	/**
	 * @API
	 */
	public function getMaxPlayers(string $world) : ?int{
		if(isset($this->modules["max-players"]))
			return $this->modules["max-players"]->getMaxPlayers($world);
		return null;
	}
}
