<?php

declare(strict_types=1);
//= api-features
//: - API version checking
//: - Misc shorcuts and pre-canned routines

namespace aliuly\worldprotect\common;

use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\VersionInfo;
use function class_exists;
use function fclose;
use function intval;
use function is_array;
use function is_callable;
use function property_exists;
use function str_contains;
use function stream_get_contents;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use function version_compare;

/**
 * My PocketMine Utils class
 */
abstract class MPMU{
	/** @var string[] $items Nice names for items */
	static protected array $items = [];
	/** @const string VERSION plugin version string */
	const VERSION = "1.92.0";

	/**
	 * libcommon library version.  If a version is provided it will check
	 * the version using apiCheck.
	 *
	 * @param string $version Version to check
	 */
	static public function version(string $version = "") : string|bool{
		if($version == "") return self::VERSION;
		return self::apiCheck(self::VERSION, $version);
	}

	/**
	 * Used to check the PocketMine API version
	 *
	 * @param string $version Version to check
	 */
	static public function apiVersion(string $version = "") : string|bool{
		if($version == "") return VersionInfo::BASE_VERSION;
		return self::apiCheck(VersionInfo::BASE_VERSION, $version);
	}

	/**
	 * Checks API compatibility from $api against $version.  $version is a
	 * string containing the version.  It can contain the following operators:
	 *
	 * >=, <=, <> or !=, =, !|~, <, >
	 *
	 * @param string $api Installed API version
	 * @param string $version API version to compare against
	 */
	static public function apiCheck(string $api, string $version) : bool{
		switch(substr($version, 0, 2)){
			case ">=":
				return version_compare($api, trim(substr($version, 2))) >= 0;
			case "<=":
				return version_compare($api, trim(substr($version, 2))) <= 0;
			case "<>":
			case "!=":
				return version_compare($api, trim(substr($version, 2))) != 0;
		}
		switch(substr($version, 0, 1)){
			case "=":
				return version_compare($api, trim(substr($version, 1))) == 0;
			case "!":
			case "~":
				return version_compare($api, trim(substr($version, 1))) != 0;
			case "<":
				return version_compare($api, trim(substr($version, 1))) < 0;
			case ">":
				return version_compare($api, trim(substr($version, 1))) > 0;
		}
		if(intval($api) != intval($version)) return false;
		return version_compare($api, $version) >= 0;
	}

	/**
	 * Returns a localized string for the gamemode
	 */
	static public function gamemodeStr(int $mode) : string{
		if(class_exists(__NAMESPACE__ . "\\mc", false)){
			return match ($mode) {
				0 => mc::_("Survival"),
				1 => mc::_("Creative"),
				2 => mc::_("Adventure"),
				3 => mc::_("Spectator"),
				default => mc::_("%1%-mode", (string) $mode),
			};
		}
		return match ($mode) {
			0 => "Survival",
			1 => "Creative",
			2 => "Adventure",
			3 => "Spectator",
			default => "$mode-mode",
		};
	}

	/**
	 * Check's player or sender's permissions and shows a message if appropriate
	 *
	 * @param bool $msg If false, no message is shown
	 */
	static public function access(CommandSender $sender, string $permission, bool $msg = true) : bool{
		if($sender->hasPermission($permission)) return true;
		if($msg)
			$sender->sendMessage(mc::_("You do not have permission to do that."));
		return false;
	}

	/**
	 * Check's if $sender is a player in game
	 *
	 * @param bool $msg If false, no message is shown
	 */
	static public function inGame(CommandSender $sender, bool $msg = true) : bool{
		if(!($sender instanceof Player)){
			if($msg) $sender->sendMessage(mc::_("You can only do this in-game"));
			return false;
		}
		return true;
	}

	/**
	 * Takes a player and creates a string suitable for indexing
	 *
	 * @param Player|string $player - Player to index
	 */
	static public function iName(CommandSender|string $player) : string{
		if($player instanceof CommandSender){
			$player = strtolower($player->getName());
		}
		return $player;
	}

	/**
	 * Like file_get_contents but for a Plugin resource
	 */
	static public function getResourceContents(PluginBase $plugin, string $filename) : string|false|null{
		$fp = $plugin->getResource($filename);
		if($fp === null){
			return null;
		}
		$contents = stream_get_contents($fp);
		fclose($fp);
		return $contents;
	}

	/**
	 * Call a plugin's function.
	 *
	 * If the $plug parameter is given a string, it will simply look for that
	 * plugin.  If an array is provided, it is assumed to be of the form:
	 *
	 *   [ "plugin", "version" ]
	 *
	 * So then it will check that the plugin exists, and the version number
	 * matches according to the rules from **apiCheck**.
	 *
	 * Also, if plugin contains an **api** property, it will use that as
	 * the class for method calling instead.
	 *
	 * @param Server       $server - pocketmine server instance
	 * @param string|array $plug - plugin to call
	 * @param string       $method - method to call
	 * @param array        $args - arguments to pass to method
	 * @param mixed        $default - If the plugin does not exist or it is not enable, this value is returned
	 */
	static public function callPlugin(Server $server, string|array $plug, string $method, array $args, mixed $default = null) : mixed{
		$v = null;
		if(is_array($plug)) [$plug, $v] = $plug;
		if(($plugin = $server->getPluginManager()->getPlugin($plug)) === null
			|| !$plugin->isEnabled()) return $default;

		if($v !== null && !self::apiCheck($plugin->getDescription()->getVersion(), $v)) return $default;
		if(property_exists($plugin, "api")){
			$fn = [$plugin->api, $method];
		}else{
			$fn = [$plugin, $method];
		}
		if(!is_callable($fn)) return $default;
		return $fn(...$args);
	}

	/**
	 * Register a command
	 *
	 * @param PluginBase      $plugin - plugin that "owns" the command
	 * @param CommandExecutor $executor - object that will be called onCommand
	 * @param string          $cmd - Command name
	 * @param array           $yaml - Additional settings for this command.
	 *
	 * @deprecated Moved to Cmd class
	 */
	static public function addCommand(PluginBase $plugin, CommandExecutor $executor, string $cmd, array $yaml) : void{
		$newCmd = new PluginCommand($cmd, $plugin, $executor);
		if(isset($yaml["description"]))
			$newCmd->setDescription($yaml["description"]);
		if(isset($yaml["usage"]))
			$newCmd->setUsage($yaml["usage"]);
		if(isset($yaml["aliases"]) && is_array($yaml["aliases"])){
			$aliasList = [];
			foreach($yaml["aliases"] as $alias){
				if(str_contains($alias, ":")){
					continue;
				}
				$aliasList[] = $alias;
			}
			$newCmd->setAliases($aliasList);
		}
		if(isset($yaml["permission"]))
			$newCmd->setPermission($yaml["permission"]);
		if(isset($yaml["permission-message"]))
			$newCmd->setPermissionMessage($yaml["permission-message"]);
		$newCmd->setExecutor($executor);
		$cmdMap = $plugin->getServer()->getCommandMap();
		$cmdMap->register($plugin->getDescription()->getName(), $newCmd);
	}

	/**
	 * Unregisters a command
	 *
	 * @param string $cmd - Command name to remove
	 *
	 * @deprecated Moved to Cmd class
	 */
	static public function rmCommand(Server $srv, string $cmd) : bool{
		$cmdMap = $srv->getCommandMap();
		$oldCmd = $cmdMap->getCommand($cmd);
		if($oldCmd === null) return false;
		$oldCmd->setLabel($cmd . "_disabled");
		$oldCmd->unregister($cmdMap);
		return true;
	}

	/**
	 * Send a PopUp, but takes care of checking if there are some
	 * plugins that might cause issues.
	 *
	 * Currently only supports SimpleAuth and BasicHUD.
	 */
	static public function sendPopup(Player $player, string $msg) : void{
		$pm = $player->getServer()->getPluginManager();
		if(($sa = $pm->getPlugin("SimpleAuth")) !== null){
			// SimpleAuth also has a HUD when not logged in...
			if($sa->isEnabled() && !$sa->isPlayerAuthenticated($player)) return;
		}
		if(($hud = $pm->getPlugin("BasicHUD")) !== null){
			// Send pop-ups through BasicHUD
			$hud->sendPopup($player, $msg);
			return;
		}
		$player->sendPopup($msg);
	}

	/**
	 * Check prefixes
	 *
	 * @param string $txt - input text
	 * @param string $tok - keyword to test
	 */
	static public function startsWith(string $txt, string $tok) : ?string{
		$ln = strlen($tok);
		if(strtolower(substr($txt, 0, $ln)) != $tok) return null;
		return trim(substr($txt, $ln));
	}

	/**
	 * Look-up player
	 */
	static public function getPlayer(CommandSender $c, string $n) : ?Player{
		$pl = $c->getServer()->getPlayerByPrefix($n);
		if($pl === null) $c->sendMessage(mc::_("%1% not found", $n));
		return $pl;
	}

}
