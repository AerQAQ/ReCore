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
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Item as ItemItem;
use pocketmine\entity\ai\behavior\{StrollBehavior, RandomLookaroundBehavior, LookAtPlayerBehavior, PanicBehavior, findFoodBehavior, inLoveBehavior};

class Cow extends Animal{
	const NETWORK_ID = 11;

	public $width = 0.3;
	public $length = 0.9;
	public $height = 0;

	public $dropExp = [1, 3];
	
	public function getName() : string{
		return "Cow";
	}
	
	public function initEntity(){
		$this->setMaxHealth(8);
		
		$this->addBehavior(new inLoveBehavior($this));
		$this->addBehavior(new PanicBehavior($this, 0.25, 2.0));
		$this->addBehavior(new findFoodBehavior($this, 296));
		$this->addBehavior(new StrollBehavior($this));
		$this->addBehavior(new LookAtPlayerBehavior($this));
		$this->addBehavior(new RandomLookaroundBehavior($this));
		
		parent::initEntity();
	}
	
	public function spawnTo(Player $player){
		$pk = new AddEntityPacket();
		$pk->eid = $this->getId();
		$pk->type = Cow::NETWORK_ID;
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
    // 掉落皮革：数量为0-2，平均1个，掉落概率约为66.67%
    $leatherCount = mt_rand(0, 2);
    if ($leatherCount > 0) {
        $drops[] = ItemItem::get(ItemItem::LEATHER, 0, $leatherCount);
    }
    
    // 掉落牛肉：数量为1-3
    $beefCount = mt_rand(1, 3);
    if($this->isOnFire()){
        // 着火时掉落牛排
        $drops[] = ItemItem::get(ItemItem::COOKED_BEEF, 0, $beefCount);
    }else{
        // 未着火掉落生牛肉
        $drops[] = ItemItem::get(ItemItem::RAW_BEEF, 0, $beefCount);
    }
    
    return $drops;
}
}