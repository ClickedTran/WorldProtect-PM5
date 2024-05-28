<?php

declare(strict_types=1);

namespace aliuly\worldprotect;

use aliuly\worldprotect\common\BasicCli;

abstract class BaseWp extends BasicCli{
	protected array $wcfg = [];

	//
	// Config look-up cache
	//
	public function setCfg(string $world, mixed $value) : void{
		$this->wcfg[$world] = $value;
	}

	public function unsetCfg(string $world) : void{
		if(isset($this->wcfg[$world])) unset($this->wcfg[$world]);
	}

	public function getCfg(string $world, mixed $default) : mixed{
		if(isset($this->wcfg[$world])) return $this->wcfg[$world];
		return $default;
	}
}
