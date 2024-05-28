<?php

declare(strict_types=1);
//= api-features
//: - Translations

namespace aliuly\worldprotect\common;

use pocketmine\plugin\PluginBase;
use function array_shift;
use function count;
use function file_exists;
use function file_get_contents;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function stripcslashes;
use function strtr;

/**
 * Simple translation class in the style of **gettext**.
 *
 * You can actually use **gettext** tools to process these files.
 * For example, to create/update a message catalogue use:
 *
 * `xgettext --no-wrap [-j] [-o file]`
 *
 * Where -j is used to join an existing catalague.
 * -o file is the output file.
 *
 * Basic usage:
 *
 * * mc::load("messages.po|messages.ini");
 * * mc::plugin_init($plugin,$plugin->getFile());
 * * mc::_("string to translate\n")
 * * mc::_("string to translate %1% %2%\n",$arg1,$arg2)
 * * mc::n(mc::\_("singular form"),mc::\_("Plural form"),$count)
 */
abstract class mc{
	/** @var string[] $txt Message translations */
	public static array $txt = [];

	/** Main translation function
	 *
	 * This translates strings.  The naming of "_" is to make it compatible
	 * with gettext utilities.  The string can contain "%1%", "%2%, etc...
	 * These are inserted from the following arguments.  Use "%%" to insert
	 * a single "%".
	 *
	 * @param string ...$args - messages
	 *
	 * @return string translated string
	 */
	public static function _(string ...$args) : string{
		if(count($args) > 0){
			$fmt = array_shift($args);
			if(isset(self::$txt[$fmt]))
				$fmt = self::$txt[$fmt];

			$vars = ["%%" => "%"];
			$i = 1;
			foreach($args as $j){
				$vars["%$i%"] = $j;
				++$i;
			}
			$fmt = strtr($fmt, $vars);
		}
		return $fmt ?? "";
	}

	/**
	 * Plural and singular forms.
	 *
	 * @param string $a - Singular form
	 * @param string $b - Plural form
	 * @param int    $c - the number to test to select between $a or $b
	 *
	 * @return string - Either plural or singular forms depending on the value of $c
	 */
	public static function n(string $a, string $b, int $c){
		return $c == 1 ? $a : $b;
	}

	/**
	 * Load a message file for a PocketMine plugin.  Only uses .ini files.
	 *
	 * @param PluginBase $plugin - owning plugin
	 * @param string     $path - output of $plugin->getFile()
	 *
	 * @return int|false - false on error or the number of messages loaded
	 */
	public static function plugin_init(PluginBase $plugin, string $path){
		if(file_exists($plugin->getDataFolder() . "messages.ini")){
			return self::load($plugin->getDataFolder() . "messages.ini");
		}
		$msgs = $path . "resources/messages/" . $plugin->getServer()->getLanguage()->getLang() . ".ini";
		if(!file_exists($msgs)) return false;
		return self::load($msgs);
	}

	/**
	 * Load the specified message catalogue.
	 * Can read .ini or .po files.
	 *
	 * @param string $f - Filename to load
	 *
	 * @return int|false - returns the number of strings loaded or false on error
	 */
	public static function load(string $f) : int|false{
		$potxt = "\n" . file_get_contents($f) . "\n";
		if(preg_match('/\nmsgid\s/', $potxt) !== false){
			$potxt = preg_replace(
				'/\\\\n"\n"/',
				"\\n",
				preg_replace('/\s+""\s*\n\s*"/', " \"", $potxt) ?? ""
			) ?? $potxt;
		}
		foreach(['/\nmsgid "(.+)"\nmsgstring "(.+)"\n/',
			'/^\s*"(.+)"\s*=\s*"(.+)"\s*$/m'] as $re){
			$c = preg_match_all($re, $potxt, $mm);
			if($c !== false){
				for($i = 0; $i < $c; ++$i){
					if($mm[2][$i] == "") continue;
					$a = stripcslashes($mm[1][$i]);
					$b = stripcslashes($mm[2][$i]);
					self::$txt[$a] = $b;
				}
				return $c;
			}
		}
		return false;
	}
}
