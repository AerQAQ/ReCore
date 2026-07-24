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

use pocketmine\network\Network;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Item as ItemItem;
use pocketmine\entity\ai\behavior\{StrollBehavior, RandomLookaroundBehavior, AttackEnemyBehavior};

class CaveSpider extends Monster{
	const NETWORK_ID = 40;

	public $width = 1;
	public $length = 1;
	//public $height = 1.8;//0.5 为了适配Default AI

	public $dropExp = [5, 5];
	private $hurt = 4;

	public function getName() : string{
		return "Cave Spider";
	}
	
	public function initEntity(){
		$this->setMaxHealth(12);
		
		$this->addBehavior(new AttackEnemyBehavior($this, [20], true));
		$this->addBehavior(new StrollBehavior($this));
		$this->addBehavior(new RandomLookaroundBehavior($this));
		
		parent::initEntity();
	}
	
	public function getHurt(){
		return $this->hurt;
	}
	
	public function spawnTo(Player $player){
		$pk = new AddEntityPacket();
		$pk->eid = $this->getId();
		$pk->type = CaveSpider::NETWORK_ID;
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
	
	public function getDrops(){
		$drops = [];
		if(mt_rand(0, 2) < 1){
			$drops[] = ItemItem::get(ItemItem::SPIDER_EYE, 0, 1);
		}else{
			$drops[] = ItemItem::get(ItemItem::STRING, 0, 1);
		}
		return $drops;
	}
}