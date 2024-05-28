<?php

declare(strict_types=1);
//= module:gm-save-inv
//: Will save inventory contents when switching gamemodes.
//:
//: This is useful for when you have per world game modes so that
//: players going from a survival world to a creative world and back
//: do not lose their inventory.

namespace aliuly\worldprotect;

use aliuly\worldprotect\common\BasicPlugin;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerGameModeChangeEvent;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;

class SaveInventory extends BaseWp implements Listener{
	const TICKS = 10;
	const DEBUG = false;
	private bool $saveOnDeath = true;

	public function __construct(BasicPlugin $plugin){
		parent::__construct($plugin);
		$this->saveOnDeath = (bool) $plugin->getConfig()->getNested("features.death-save-inv", true);
		Server::getInstance()->getPluginManager()->registerEvents($this, $this->owner);
	}

	public function onSCommand(CommandSender $c, Command $cc, string $scmd, mixed $data, array $args) : bool{
		return false;
	}

	public function loadInv(Player $player) : void{
		$inventoryTag = $player->getServer()->getOfflinePlayerData($player->getName())->getListTag("SurvivalInventory");
		if(!isset($inventoryTag)){
			if(self::DEBUG) Server::getInstance()->getLogger()->info("[WP Inventory] SurvivalInventory Not Found");
			return;
		}
		$inventoryItems = [];
		$armorInventoryItems = [];

		/** @var CompoundTag $item */
		foreach($inventoryTag as $i => $item){
			$slot = $item->getByte("Slot");
			if($slot >= 0 && $slot < 9){ //Hotbar
				//Old hotbar saving stuff, ignore it
			}elseif($slot >= 100 && $slot < 104){ //Armor
				$armorInventoryItems[$slot - 100] = Item::nbtDeserialize($item);
			}elseif($slot >= 9 && $slot < $player->getInventory()->getSize() + 9){
				$inventoryItems[$slot - 9] = Item::nbtDeserialize($item);
			}
		}
		$player->getInventory()->setContents($inventoryItems);
		$player->getArmorInventory()->setContents($armorInventoryItems);
		$player->save();
	}

	public function saveInv(Player $player) : void{
		$inventoryTag = $player->getSaveData()->getListTag("Inventory");
		$player->getServer()->saveOfflinePlayerData(
			$player->getName(),
			$player->getSaveData()->setTag("SurvivalInventory", clone $inventoryTag)
		);
	}

	public function onGmChange(PlayerGameModeChangeEvent $ev) : void{
		if($ev->isCancelled()) return;
		$player = $ev->getPlayer();
		$newgm = $ev->getNewGamemode();
		$oldgm = $player->getGamemode();
		if(self::DEBUG) Server::getInstance()->getLogger()->info("[WP Inventory] Changing GM from " . $oldgm->name() . " to " . $newgm->name() . "...");
		if(($newgm->equals(GameMode::CREATIVE()) || $newgm->equals(GameMode::SPECTATOR())) && ($oldgm->equals(GameMode::SURVIVAL()) || $oldgm->equals(GameMode::ADVENTURE()))){// We need to save inventory
			$this->saveInv($player);
			if(self::DEBUG) Server::getInstance()->getLogger()->info("[WP Inventory] Saved Inventory from GM  " . $oldgm->name() . " to " . $newgm->name() . ".");
		}elseif(($newgm->equals(GameMode::SURVIVAL()) || $newgm->equals(GameMode::ADVENTURE())) && ($oldgm->equals(GameMode::CREATIVE()) || $oldgm->equals(GameMode::SPECTATOR()))){
			if(self::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] GM Change - Clear Player Inventory and load SurvivalInventory...");
			$player->getInventory()->clearAll();
			// Need to restore inventory (but later!)
			$this->owner->getScheduler()->scheduleDelayedTask(new ClosureTask(fn() => $this->loadInv($player)), self::TICKS);
		}
	}

	public function PlayerDeath(PlayerDeathEvent $event) : void{
		if(!$this->saveOnDeath) return;
		$player = $event->getPlayer();
		// Need to restore inventory (but later!).
		$this->owner->getScheduler()->scheduleDelayedTask(new ClosureTask(fn() => $this->loadInv($player)), self::TICKS);
		if(self::DEBUG) Server::getInstance()->getLogger()->info("[WP Inventory] Reloaded SurvivalInventory on death");
	}
}
