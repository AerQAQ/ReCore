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

class Mooshroom extends Animal{
	const NETWORK_ID = 16;

	public $width = 0.3;
	public $length = 0.9;
	public $height = 1.8;
    public $dropExp = [1, 3];
	
	public function getName() : string{
		return "Mooshroom";
	}
        
        //添加初始化实体方法，设置AI行为
        public function initEntity(){
                $this->setMaxHealth(8); // 设置与牛相同的生命值
                
                // 添加与牛完全相同的AI行为
                $this->addBehavior(new inLoveBehavior($this));
                $this->addBehavior(new PanicBehavior($this, 0.25, 2.0));
                $this->addBehavior(new findFoodBehavior($this, 296)); // 296应为小麦的物品ID
                $this->addBehavior(new StrollBehavior($this));
                $this->addBehavior(new LookAtPlayerBehavior($this));
                $this->addBehavior(new RandomLookaroundBehavior($this));
                
                parent::initEntity();
        }
	
	public function spawnTo(Player $player){
		$pk = new AddEntityPacket();
		$pk->eid = $this->getId();
		$pk->type = Mooshroom::NETWORK_ID;
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
	
        // 移植：完全替换为牛的掉落物逻辑
	public function getDrops(){
		$drops = [];
		switch (\mt_rand(0, 1)) {
					case 0:
						if($this->isOnFire()){
							$drops[] = ItemItem::get(ItemItem::COOKED_BEEF, 0, mt_rand(1,2));
						}else{
							$drops[] = ItemItem::get(ItemItem::RAW_BEEF, 0, mt_rand(1,2));
						}
						break;
					case 1:
						$drops[] = ItemItem::get(ItemItem::LEATHER, 0, mt_rand(1,2));
						break;
				}
		return $drops;
	}
}
