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

class Ghast extends FlyingAnimal{
	const NETWORK_ID = 41;

	public $width = 6;
	public $length = 6;
	public $height = 6;
	
	public function getName() : string{
		return "Ghast";
	}

	public function initEntity(){
		$this->setMaxHealth(10);
		parent::initEntity();
	}
	
	public function spawnTo(Player $player){
		$pk = new AddEntityPacket();
		$pk->eid = $this->getId();
		$pk->type = Ghast::NETWORK_ID;
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
		// 恶魂之泪掉落（0-1个）
		if(mt_rand(1, 2) === 1){ // 50%几率掉落
			$drops[] = Item::get(Item::GHAST_TEAR, 0, mt_rand(0, 1));
		}
		return $drops;
	}
}
