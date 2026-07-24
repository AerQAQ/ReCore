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

use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;
use pocketmine\entity\ai\behavior\{StrollBehavior, ShootPlayerBehavior, RandomLookaroundBehavior};

class Witch extends Monster {
	const NETWORK_ID = 45;

	/**
	 * @return string
	 */
	public function getName() : string{
		return "Witch";
	}

	public function initEntity(){
		$this->setMaxHealth(26);
		
		$this->addBehavior(new ShootPlayerBehavior($this, 86));//扔药水
		$this->addBehavior(new StrollBehavior($this));
		$this->addBehavior(new RandomLookaroundBehavior($this));
		
		parent::initEntity();
	}

	/**
	 * @return array
	 */
	public function getDrops(){
		$drops = [];
		if(mt_rand(1, 8) == 1){
			$drops[] = Item::get(Item::GLOWSTONE_DUST, 0, mt_rand(0, 2));
		}
		// 女巫基础掉落
		$drops[] = Item::get(Item::SUGAR, 0, mt_rand(0, 2));
		if(mt_rand(1, 6) == 1){
			$drops[] = Item::get(Item::SPIDER_EYE, 0, 1);
		}
		return $drops;
	}

    public function getXpDropAmount(): int{
        return 5;
    }
	
	public function spawnTo(Player $player){
		$pk = new AddEntityPacket();
		$pk->eid = $this->getId();
		$pk->type = Witch::NETWORK_ID;
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
}