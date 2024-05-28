<?php

declare(strict_types=1);

namespace aliuly\worldprotect\common;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use function array_shift;
use function count;
use function ksort;
use function strtolower;

/**
 * Sub Command dispatcher
 */
final class SubCommandMap{
	/** @var callable[] $executors */
	public array $executors = [];
	/** @var string[] $help */
	public array $help = [];
	/** @var string[] $usage */
	public array $usage = [];
	/** @var string[] $aliases */
	public array $aliases = [];
	/** @var string[] $permission */
	public array $permission = [];

	/**
	 * Returns the number of commands configured
	 */
	public function getCommandCount() : int{
		return count($this->executors);
	}

	/**
	 * Dispatch commands using sub command table
	 */
	public function dispatchSCmd(CommandSender $sender, Command $cmd, array $args, mixed $data = null) : bool{
		if(count($args) === 0){
			$sender->sendMessage(mc::_("No sub-command specified"));
			return false;
		}
		$scmd = strtolower(array_shift($args));
		if(isset($this->aliases[$scmd])){
			$scmd = $this->aliases[$scmd];
		}
		if(!isset($this->executors[$scmd])){
			$sender->sendMessage(mc::_("Unknown sub-command %2% (try /%1% help)", $cmd->getName(), $scmd));
			return false;
		}
		if(isset($this->permission[$scmd])){
			if(!$sender->hasPermission($this->permission[$scmd])){
				$sender->sendMessage(mc::_("You are not allowed to do this"));
				return true;
			}
		}
		$callback = $this->executors[$scmd];
		if($callback($sender, $cmd, $scmd, $data, $args)) return true;
		if(isset($this->executors["help"])){
			$callback = $this->executors["help"];
			return $callback($sender, $cmd, $scmd, $data, ["usage"]);
		}
		return false;
	}

	/**
	 * Register a sub command
	 *
	 * @param string              $cmd - sub command
	 * @param callable            $callable - callable to execute
	 * @param string[]|string[][] $opts - additional options
	 */
	public function registerSCmd(string $cmd, callable $callable, array $opts) : void{
		$cmd = strtolower($cmd);
		$this->executors[$cmd] = $callable;

		if(isset($opts["help"])){
			$this->help[$cmd] = $opts["help"];
			ksort($this->help);
		}
		if(isset($opts["usage"])) $this->usage[$cmd] = $opts["usage"];
		if(isset($opts["permission"])) $this->permission[$cmd] = $opts["permission"];
		if(isset($opts["aliases"])){
			foreach($opts["aliases"] as $alias){
				$this->aliases[$alias] = $cmd;
			}
		}
	}

	public function getUsage(string $scmd) : ?string{
		return $this->usage[$scmd] ?? null;
	}

	public function getAlias(string $scmd) : ?string{
		return $this->aliases[$scmd] ?? null;
	}

	public function getHelpMsg(string $scmd) : ?string{
		return $this->help[$scmd] ?? null;
	}

	/**
	 * @return string[]
	 */
	public function getHelp() : array{
		return $this->help;
	}

	/**
	 * @return string[]
	 */
	public function getAliases() : array{
		return $this->aliases;
	}
}
