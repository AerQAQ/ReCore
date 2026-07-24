<?php

/*
 *  ______   _____    ______  __   __  ______
 * /  ___/  /  ___|  / ___  \ \ \ / / |  ____|
 * | |___  | |      | |___| |  \ / /  | |____
 * \___  \ | |      |  ___  |   / /   |  ____|
 *  ___| | | |____  | |   | |  / / \  | |____
 * /_____/  \_____| |_|   |_| /_/ \_\ |______|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Sunch233#3226 QQ2125696621 And KKK
 * @link https://github.com/ScaxeTeam/Scaxe/
 *
*/

namespace pocketmine\entity;

use pocketmine\item\Item;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;
use pocketmine\entity\ai\behavior\{StrollBehavior, RandomLookaroundBehavior, AttackEnemyBehavior};

class IronGolem extends Animal{
	const NETWORK_ID = 20;

	public $width = 0.3;
	public $length = 0.9;
	//public $height = 2.8;
	
	public function initEntity(){
		$this->setMaxHealth(100);
		
		$this->addBehavior(new AttackEnemyBehavior($this, [32, 33, 34, 35, 36, 40, 44], false));
		$this->addBehavior(new StrollBehavior($this));
		$this->addBehavior(new RandomLookaroundBehavior($this));
		
		parent::initEntity();
	}
	
	public function getName() {
		return "Iron Golem";
	}
	
	public function getHurt(){
		return mt_rand(7, 21);
	}
	
	public function spawnTo(Player $player) {
		$pk = new AddEntityPacket();
		$pk->eid = $this->getId();
		$pk->type = self::NETWORK_ID;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->speedX = $this->motionX;
		$pk->speedY = $this->motionY;
		$pk->speedZ = $this->motionZ;
		$pk->yaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$pk->metadata = $this->dataProperties;
		$player->dataPacket($pk);

		parent::spawnTo($player);
	}
	
	public function getDrops() : array{
		$drops = [];
		// 铁锭掉落（3-5个）
		$drops[] = Item::get(Item::IRON_INGOT, 0, mt_rand(3, 5));
		// 罂粟掉落（0-2个）
		$drops[] = Item::get(Item::POPPY, 0, mt_rand(0, 2));
		return $drops;
	}
}
