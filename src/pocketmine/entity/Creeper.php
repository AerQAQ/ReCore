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

use pocketmine\event\entity\CreeperPowerEvent;
use pocketmine\item\Item as ItemItem;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\Player;
use pocketmine\level\Explosion;
use pocketmine\level\Position;

class Creeper extends Monster{
	const NETWORK_ID = 33;

	const DATA_SWELL = 16;
	const DATA_POWERED = 19;
	
	public $width = 0.6;
    public $length = 0.6;
    public $height = 1.8;

	public $dropExp = [5, 5];
	
	public function getName() : string{
		return "Creeper";
	}

	public function initEntity(){
		$this->setMaxHealth(20);
		parent::initEntity();
		if(!isset($this->namedtag->powered)){
			$this->setPowered(false);
		}
	}

	public function setPowered(bool $powered, Lightning $lightning = null){
		if($lightning != null){
			$powered = true;
			$cause = CreeperPowerEvent::CAUSE_LIGHTNING;
		}else $cause = $powered ? CreeperPowerEvent::CAUSE_SET_ON : CreeperPowerEvent::CAUSE_SET_OFF;

		$this->getLevel()->getServer()->getPluginManager()->callEvent($ev = new CreeperPowerEvent($this, $lightning, $cause));

		if(!$ev->isCancelled()){
			$this->namedtag->powered = new ByteTag("powered", $powered ? 1 : 0);
			$this->setDataProperty(self::DATA_POWERED, self::DATA_TYPE_BYTE, $powered ? 1 : 0);
		}
	}

	public function isPowered() : bool{
		return $this->namedtag["powered"] == 0 ? false : true;
	}
	
	public function setSwelled(bool $swelled){
		$this->setDataProperty(self::DATA_CREPPER_SWELL_DIRECTION, self::DATA_TYPE_BYTE, $swelled ? 1 : 0);
		if(!$swelled){
			$this->setDataProperty(self::DATA_CREPPER_SWELL, self::DATA_TYPE_BYTE, 0);
			$this->setDataProperty(self::DATA_CREPPER_SWELL_2, self::DATA_TYPE_BYTE, 0);
		}
	}
	
	public function isSwelled(){
		return $this->getDataProperty(self::DATA_CREPPER_SWELL_DIRECTION) == 1;
	}
	
	public function onUpdate($currentTick){
		if($this->closed){
			return false;
		}
		
		$hasUpdate = parent::onUpdate($currentTick);
		
		if($this->isSwelled()){ //爆炸动画
			$num = $this->getDataProperty(self::DATA_CREPPER_SWELL);
			if($num < 30){
				$num++;
				$this->setDataProperty(self::DATA_CREPPER_SWELL, self::DATA_TYPE_BYTE, $num);
				$this->setDataProperty(self::DATA_CREPPER_SWELL_2, self::DATA_TYPE_BYTE, $num);
			}else{
				$this->setSwelled(false);
                $e = new Explosion(new Position($this->getX() , $this->getY() , $this->getZ() , $this->getLevel()) , 5, $this);
                if ($this->getLevel()->getServer()->aiConfig["creeperexplode"]) $e->explode();
                else $e->explodeB();
				$this->getLevel()->removeEntity($this);
			}
		}
		return $hasUpdate;
	}

	public function spawnTo(Player $player){
		$pk = new AddEntityPacket();
		$pk->eid = $this->getId();
		$pk->type = Creeper::NETWORK_ID;
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
        $drops[] = ItemItem::get(ItemItem::GUNPOWDER, 0, 1);
        return $drops;
    }
}