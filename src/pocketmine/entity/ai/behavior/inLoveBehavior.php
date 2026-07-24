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

namespace pocketmine\entity\ai\behavior;

use pocketmine\entity\Mob;
use pocketmine\math\Vector3;
use pocketmine\block\Air;
use pocketmine\Player;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\FloatTag;

class inLoveBehavior extends Behavior{

    public $speed;
    public $speedMultiplier;
	public $timeLeft;
	
	public $inLoveEntity = null;
	public $inLovetime = 0;

    public function __construct(Mob $entity, float $speed = 0.25, float $speedMultiplier = 0.75){
        parent::__construct($entity);

        $this->speed = $speed;
        $this->speedMultiplier = $speedMultiplier;
    }

    public function getName() : string{
        return "发情";
    }

    public function shouldStart() : bool{
		if(!$this->entity->isInLove()){
			return false;
		}
		$find = false;
		foreach($this->entity->level->getNearbyEntities($this->entity->boundingBox->grow(10, 3, 10), $this->entity) as $entity){
			if(get_class($entity) == get_class($this->entity) and $entity->isInLove()){
				$find = true;
				$this->timeLeft = 200;
				$this->inLoveEntity = $entity;
			}
		}
		return $find;
		
    }

    public function canContinue() : bool{
		return $this->timeLeft-- > 0 or !$this->inLoveEntity->isAlive();
        
    }

    public function onTick(){
		$this->AimPlayer($this->inLoveEntity, $this->entity);
        $speedFactor = (float) ($this->speed*$this->speedMultiplier*0.7*($this->entity->isInsideOfWater() ? 0.3 : 0.4)); // 0.7 is a general mob base factor
		$level = $this->entity->getLevel();
		$coordinates = $this->entity->getPosition();
		$direction = $this->entity->getDirectionVector();
		$direction->y = 0;
		$entity = $this->entity;

		$blockDown = $level->getBlock($coordinates->add(0,-1,0));
		if ($entity->getMotion()->y < 0 and $blockDown instanceof Air)
		{
			return;
		}
		if($this->entity->distance($this->inLoveEntity) < 0.5){
			$this->inLovetime++;
			if($this->inLovetime >= 10){
				$nbt = new CompoundTag("", [
							"Pos" => new ListTag("Pos", [
								new DoubleTag("", 0),
								new DoubleTag("", 0),
								new DoubleTag("", 0)
							]),
							"Motion" => new ListTag("Motion", [
								new DoubleTag("", 0),
								new DoubleTag("", 0),
								new DoubleTag("", 0)
							]),
							"Rotation" => new ListTag("Rotation", [
								new FloatTag("", 0),
								new FloatTag("", 0)
							]),
						]);
				$class = get_class($this->entity);
				$entity = new $class($this->entity->chunk, $nbt);
				$entity->setBaby(true);
				$entity->setPosition($this->entity->getPosition());
				$entity->setHealth($this->entity->getMaxHealth());
				$entity->spawnToAll();
				$this->entity->setInLove(false);
				$this->inLoveEntity->setInLove(false);
				$this->inLovetime = 0;
				$this->timeLeft = 0;
			}
			return;
		}

	    $coord = ($coordinates->add($direction->multiply($speedFactor))->add($direction->multiply(0.5)));

		$players = $entity->getViewers();

		$block = $level->getBlock($coord);
		$blockUp = $level->getBlock($coord->add(0,1,0));
		$blockUpUp = $level->getBlock($coord->add(0,2,0));

		$colliding = $block->isSolid() or ($entity->height >= 1 and $blockUp->isSolid());
		if (!$colliding){
			$motion = $direction->multiply($speedFactor);
			$pm = $entity->getMotion();
			$pm->y = 0;
			if ($pm->length() < $motion->length()){
				$entity->setMotion($pm->add($motion->x - $pm->x, 0, $motion->z - $pm->z));
			}else{
				$entity->setMotion($motion);
			}
		}
		else
		{
			if (!$blockUp->isSolid() and !($entity->height > 1 and $blockUpUp->isSolid())){
				$entity->motionY = 0.42;
			}
		}
		$this->swimming();
    }
	
	public function AimPlayer($palyer, $entity){
		$x = $palyer->x - $entity->x;
		$y = $palyer->y - $entity->y;
		$z = $palyer->z - $entity->z;
		
		$a = $palyer->x + 0.5;
		$b = $palyer->y;
		$c = $palyer->z + 0.5;
		$len = sqrt($x * $x + $y * $y + $z * $z);
		$y = $y / $len;
		$pitch = asin($y);
		$pitch = $pitch * 180 / M_PI;
		$pitch = -$pitch;
		$yaw = -atan2($a - ($entity->x + 0.5), $c - ($entity->z + 0.5)) * (180 / M_PI);
		$entity->pitch = $pitch;
		$entity->yaw = $yaw;
		
	}

    public function onEnd(){
        $this->entity->setMotion(new Vector3(0,0,0));
    }
}