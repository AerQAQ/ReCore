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


use pocketmine\event\entity\EntityCombustByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;

use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\level\format\FullChunk;
use pocketmine\level\MovingObjectPosition;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ShortTag;

abstract class Projectile extends Entity{

	/** @var Entity */
	public $shootingEntity = null;
	protected $damage = 0;

	public $hadCollision = false;

	public function __construct(FullChunk $chunk, CompoundTag $nbt, Entity $shootingEntity = null){
		$this->shootingEntity = $shootingEntity;
		if($shootingEntity !== null){
			$this->setDataProperty(self::DATA_SHOOTER_ID, self::DATA_TYPE_LONG, $shootingEntity->getId());
		}
		parent::__construct($chunk, $nbt);
	}

	public function attack($damage, EntityDamageEvent $source){
		if($source->getCause() === EntityDamageEvent::CAUSE_VOID){
			parent::attack($damage, $source);
		}
	}

	protected function initEntity(){
		parent::initEntity();

		$this->setMaxHealth(1);
		$this->setHealth(1);
		if(isset($this->namedtag->Age)){
			$this->age = $this->namedtag["Age"];
		}

	}

	public function canCollideWith(Entity $entity){
		return $entity instanceof Living and !$this->onGround;
	}

	public function saveNBT(){
		parent::saveNBT();
		$this->namedtag->Age = new ShortTag("Age", $this->age);
	}

	public function onUpdate($currentTick){
		if($this->closed){
			return false;
		}


		$tickDiff = $currentTick - $this->lastUpdate;
		if($tickDiff <= 0 and !$this->justCreated){
			return true;
		}
		$this->lastUpdate = $currentTick;

		$hasUpdate = $this->entityBaseTick($tickDiff);

		if($this->isAlive()){

			$movingObjectPosition = null;

			if(!$this->isCollided){
				$this->motionY -= $this->gravity;
			}

			$moveVector = new Vector3($this->x + $this->motionX, $this->y + $this->motionY, $this->z + $this->motionZ);

			$list = $this->getLevel()->getCollidingEntities($this->boundingBox->addCoord($this->motionX, $this->motionY, $this->motionZ)->expand(1, 1, 1), $this);

			$nearDistance = PHP_INT_MAX;
			$nearEntity = null;

			foreach($list as $entity){
				if(/*!$entity->canCollideWith($this) or */
				($entity === $this->shootingEntity and $this->ticksLived < 5)
				){
					continue;
				}

				$axisalignedbb = $entity->boundingBox->grow(0.3, 0.3, 0.3);
				$ob = $axisalignedbb->calculateIntercept($this, $moveVector);

				if($ob === null){
					continue;
				}

				$distance = $this->distanceSquared($ob->hitVector);

				if($distance < $nearDistance){
					$nearDistance = $distance;
					$nearEntity = $entity;
				}
			}

			if($nearEntity !== null){
				$movingObjectPosition = MovingObjectPosition::fromEntity($nearEntity);
			}

			if($movingObjectPosition !== null){
				if($movingObjectPosition->entityHit !== null){

					$this->server->getPluginManager()->callEvent(new ProjectileHitEvent($this));

					$motion = sqrt($this->motionX ** 2 + $this->motionY ** 2 + $this->motionZ ** 2);
					$damage = ceil($motion * $this->damage);

					if($this instanceof Arrow and $this->isCritical){
						$damage += mt_rand(0, (int) ($damage / 2) + 1);
					}

					// 从箭矢NBT读取击退附魔等级，计算击退系数
					$knockBack = 0.4;
					if(isset($this->namedtag["Knockback"])){
						$knockBackLevel = (int) $this->namedtag["Knockback"];
						$knockBack = 0.4 + 0.5 * $knockBackLevel;
					}

					if($this->shootingEntity === null){
						$ev = new EntityDamageByEntityEvent($this, $movingObjectPosition->entityHit, EntityDamageEvent::CAUSE_PROJECTILE, $damage, $knockBack);
					}else{
						$ev = new EntityDamageByChildEntityEvent($this->shootingEntity, $this, $movingObjectPosition->entityHit, EntityDamageEvent::CAUSE_PROJECTILE, $damage, $knockBack);
					}

					if($movingObjectPosition->entityHit->attack($ev->getFinalDamage(), $ev) === true){
						$ev->useArmors();
					}

					$this->hadCollision = true;

					if($this->fireTicks > 0){
						$ev = new EntityCombustByEntityEvent($this, $movingObjectPosition->entityHit, 5);
						$this->server->getPluginManager()->callEvent($ev);
						if(!$ev->isCancelled()){
							$movingObjectPosition->entityHit->setOnFire($ev->getDuration());
						}
					}

					$this->kill();
					return true;
				}
			}

			$this->move($this->motionX, $this->motionY, $this->motionZ);

			if($this->isCollided and !$this->hadCollision){
				$this->hadCollision = true;

				$this->motionX = 0;
				$this->motionY = 0;
				$this->motionZ = 0;

				$this->server->getPluginManager()->callEvent(new ProjectileHitEvent($this));
			}elseif(!$this->isCollided and $this->hadCollision){
				$this->hadCollision = false;
			}

			if(!$this->onGround or abs($this->motionX) > 0.00001 or abs($this->motionY) > 0.00001 or abs($this->motionZ) > 0.00001){
				$f = sqrt(($this->motionX ** 2) + ($this->motionZ ** 2));
				$this->yaw = (atan2($this->motionX, $this->motionZ) * 180 / M_PI);
				$this->pitch = (atan2($this->motionY, $f) * 180 / M_PI);
				$hasUpdate = true;
			}

			$this->updateMovement();

		}

		return $hasUpdate;
	}

}