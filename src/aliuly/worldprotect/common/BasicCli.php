<?php

declare(strict_types=1);
//= api-features
//: - Paginated output
//: - Command and sub command dispatchers

namespace aliuly\worldprotect\common;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\utils\TextFormat;
use function array_pop;
use function array_shift;
use function count;
use function get_class;
use function intval;
use function is_array;
use function is_numeric;
use function sprintf;
use function str_contains;
use function strlen;

/**
 * Implements Basic CLI common functionality.  It is useful for plugins
 * that implement multiple commands or sub-commands
 */
abstract class BasicCli{
	/**
	 * @param BasicPlugin $owner - Plugin that owns this module
	 */
	public function __construct(protected BasicPlugin $owner){ }

	/**
	 * @param string[]      $args
	 */
	abstract function onSCommand(CommandSender $c, Command $cc, string $scmd, mixed $data, array $args) : bool;

	/**
	 * Register this class as a sub-command.  See BasicPlugin for details.
	 *
	 * @param string  $cmd - sub-command to register
	 * @param mixed[] $opts - additional options for registering sub-command
	 */
	public function enableSCmd(string $cmd, array $opts) : void{
		$this->owner->registerScmd($cmd, [$this, "onSCommand"], $opts);
	}

	/**
	 * Register this class as a command.
	 *
	 * @param string  $cmd - command to register
	 * @param mixed[] $yaml - options for command
	 */
	public function enableCmd(string $cmd, array $yaml) : void{
		$newCmd = new PluginCommand($cmd, $this->owner, $this->owner);
		if(isset($yaml["description"]))
			$newCmd->setDescription($yaml["description"]);
		if(isset($yaml["usage"]))
			$newCmd->setUsage($yaml["usage"]);
		if(isset($yaml["aliases"]) && is_array($yaml["aliases"])){
			$aliasList = [];
			foreach($yaml["aliases"] as $alias){
				if(str_contains($alias, ":")){
					$this->owner->getLogger()->info("Unable to load alias $alias");
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
		$newCmd->setExecutor($this->owner);
		$cmdMap = $this->owner->getServer()->getCommandMap();
		$cmdMap->register($this->owner->getDescription()->getName(), $newCmd);
	}

	/**
	 * Use for paginated output implementation.
	 * This gets the player specified page number that we want to Display
	 *
	 * @param string[] $args - Passed arguments
	 *
	 * @return int page number
	 */
	protected function getPageNumber(array &$args) : int{
		$pageNumber = 1;
		if(count($args) > 0 && is_numeric($args[count($args) - 1])){
			$pageNumber = (int) array_pop($args);
			if($pageNumber <= 0) $pageNumber = 1;
		}
		return $pageNumber;
	}

	/**
	 * Use for paginaged output implementation.
	 * Shows a bunch of line in paginated output.
	 *
	 * @param CommandSender $sender - entity that we need to display text to
	 * @param int           $pageNumber - page that we need to display
	 * @param string[]      $txt - Array containing one element per output line
	 *
	 * @return bool true
	 */
	protected function paginateText(CommandSender $sender, int $pageNumber, array $txt) : bool{
		$hdr = array_shift($txt);
		if($sender instanceof ConsoleCommandSender){
			$sender->sendMessage(TextFormat::GREEN . $hdr . TextFormat::RESET);
			foreach($txt as $ln) $sender->sendMessage($ln);
			return true;
		}
		$pageHeight = 5;
		$lineCount = count($txt);
		$pageCount = intval($lineCount / $pageHeight) + ($lineCount % $pageHeight > 0 ? 1 : 0);
		$hdr = TextFormat::GREEN . $hdr . TextFormat::RESET;
		if($pageNumber > $pageCount){
			$sender->sendMessage($hdr);
			$sender->sendMessage("Only $pageCount pages available");
			return true;
		}
		$hdr .= TextFormat::RED . " ($pageNumber of $pageCount)";
		$sender->sendMessage($hdr);
		for($ln = ($pageNumber - 1) * $pageHeight; $ln < $lineCount && $pageHeight-- > 0; ++$ln){
			$sender->sendMessage($txt[$ln]);
		}
		return true;
	}

	/**
	 * Use for paginated output implementation.
	 * Formats and paginates a table
	 *
	 * @param CommandSender $sender - entity that we need to display text to
	 * @param int           $pageNumber - page that we need to display
	 * @param string[][]    $tab - Array containing one element per output line
	 *
	 * @return bool true
	 */
	protected function paginateTable(CommandSender $sender, int $pageNumber, array $tab) : bool{
		$cols = [];
		for($i = 0; $i < count($tab[0]); $i++) $cols[$i] = strlen($tab[0][$i]);
		foreach($tab as $row){
			for($i = 0; $i < count($row); $i++){
				if(($l = strlen($row[$i])) > $cols[$i]) $cols[$i] = $l;
			}
		}
		$txt = [];
		$fmt = "";
		foreach($cols as $c){
			if(strlen($fmt) > 0) $fmt .= " ";
			$fmt .= "%-" . $c . "s";
		}
		foreach($tab as $row){
			$txt[] = sprintf($fmt, ...$row);
		}
		return $this->paginateText($sender, $pageNumber, $txt);
	}

	//////////////////////////////////////////////////////////////////////

	/**
	 * Entry point for BasicPlugin state functionality.  This makes it module
	 * specific.
	 * Retrieves the state.
	 *
	 * @param CommandSender $player - entity that we need state from
	 * @param mixed         $default - Default value to return if no state found
	 *
	 * @return mixed $state
	 */
	public function getState(CommandSender $player, mixed $default) : mixed{
		return $this->owner->getState(get_class($this), $player, $default);
	}

	/**
	 * Entry point for BasicPlugin state functionality.  This makes it module
	 * specific.
	 * Sets the state.
	 *
	 * @param CommandSender $player - entity that we need to set state
	 * @param mixed         $val - Value to use for the state
	 */
	public function setState(CommandSender $player, mixed $val) : void{
		$this->owner->setState(get_class($this), $player, $val);
	}

	/**
	 * Entry point for BasicPlugin state functionality.  This makes it module
	 * specific.
	 * UnSets the state.
	 *
	 * @param CommandSender $player - entity that we need to unset state
	 */
	public function unsetState(CommandSender $player) : void{
		$this->owner->unsetState(get_class($this), $player);
	}
}
