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

use pocketmine\block\Block;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\Timings;
use pocketmine\item\Item as ItemItem;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\network\Network;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\Server;
use pocketmine\utils\BlockIterator;

abstract class Living extends Entity implements Damageable{

	protected $gravity = 0.08;
	protected $drag = 0.02;

	protected $attackTime = 0;
	
	protected $invisible = false;

	protected function initEntity(){
		parent::initEntity();

		if(isset($this->namedtag->HealF)){
			$this->namedtag->Health = new ShortTag("Health", (int) $this->namedtag["HealF"]);
			unset($this->namedtag->HealF);
		}

		if(!isset($this->namedtag->Health) or !($this->namedtag->Health instanceof ShortTag)){
			$this->namedtag->Health = new ShortTag("Health", $this->getMaxHealth());
		}
		
		if($this->namedtag["Health"] <= 0)
			$this->setHealth(20);
		else $this->setHealth($this->namedtag["Health"]);
	}

	public function setHealth($amount){
		$wasAlive = $this->isAlive();
		parent::setHealth($amount);
		if($this->isAlive() and !$wasAlive){
			$pk = new EntityEventPacket();
			$pk->eid = $this->getId();
			$pk->event = EntityEventPacket::RESPAWN;
			Server::broadcastPacket($this->hasSpawned, $pk);
		}
	}

	public function saveNBT(){
		parent::saveNBT();
		$this->namedtag->Health = new ShortTag("Health", $this->getHealth());
	}

	public abstract function getName();

		public function hasLineOfSight(Entity $entity){
		//TODO: head height
		return $this->getLevel()->rayTraceBlocks(Vector3::createVector($this->x, $this->y + $this->height, $this->z), Vector3::createVector($entity->x, $entity->y + $entity->height, $entity->z)) === null;
	}

	public function heal($amount, EntityRegainHealthEvent $source){
		parent::heal($amount, $source);
		if($source->isCancelled()){
			return;
		}

		$this->attackTime = 0;
	}

	public function attack($damage, EntityDamageEvent $source){
		if($this->attackTime > 0 or $this->noDamageTicks > 0){
			$lastCause = $this->getLastDamageCause();
			if($lastCause !== null and $lastCause->getDamage() >= $damage){
                $source->setCancelled();
			}
		}

        parent::attack($damage, $source);

        if($source->isCancelled()){
            return;
        }

		// 暴击系统开始
		$isCritical = false;
		$attacker = null;
		
		// 获取攻击者
		if($source instanceof EntityDamageByEntityEvent){
			$attacker = $source->getDamager();
			if($source instanceof EntityDamageByChildEntityEvent){
				$attacker = $source->getChild();
			}
		}

		// 判断暴击条件：只有玩家（Player）且不在水中才能触发暴击
		if($attacker !== null){
			// 修改：只检查玩家，去掉抛射物检查，并增加不在水中的条件
			if($attacker instanceof \pocketmine\Player && !$attacker->isInsideOfWater()){ 
				if(!$attacker->onGround && ($attacker->fallDistance > 0.1 || $attacker->motionY > 0)){
					$isCritical = true;
				}
			}
		}

		// 应用暴击伤害
		if($isCritical && $attacker !== null){
			$criticalDamage = $damage * 1.5;  // 伤害增加50%
			$source->setDamage($criticalDamage);

			// 生成暴击粒子效果 - 修改为多个分散粒子
			$particleCount = 15; // 粒子数量从1个增加到15个
			for($i = 0; $i < $particleCount; $i++){
				// 扩大粒子生成范围：x和z轴从±0.5格扩大到±1.5格，y轴从0-2格扩大到0-3格
				$offsetX = mt_rand(-15, 15) / 10; // x轴偏移：-1.5 到 1.5
				$offsetY = mt_rand(0, 30) / 10;    // y轴偏移：0 到 3.0
				$offsetZ = mt_rand(-15, 15) / 10; // z轴偏移：-1.5 到 1.5
				$particlePos = new Vector3(
					$this->x + $offsetX,
					$this->y + $offsetY,
					$this->z + $offsetZ
				);
				$this->level->addParticle(new CriticalParticle($particlePos));
			}

			// 重置攻击者下落距离
			if($attacker instanceof Living){
				$attacker->fallDistance = 0;
			}
		}
		// 暴击系统结束

		if($source instanceof EntityDamageByEntityEvent){
			$e = $source->getDamager();
			if($source instanceof EntityDamageByChildEntityEvent){
				$e = $source->getChild();
			}

			if($e->isOnFire() > 0){
				$this->setOnFire(2 * $this->server->getDifficulty());
			}

			$deltaX = $this->x - $e->x;
			$deltaZ = $this->z - $e->z;
			$this->knockBack($e, $damage, $deltaX, $deltaZ, $source->getKnockBack());
		}

		$pk = new EntityEventPacket();
		$pk->eid = $this->getId();
		$pk->event = $this->getHealth() <= 0 ? EntityEventPacket::DEATH_ANIMATION : EntityEventPacket::HURT_ANIMATION; //Ouch!
		Server::broadcastPacket($this->hasSpawned, $pk);

		$this->attackTime = 10; //0.5 seconds cooldown
	}

	public function knockBack(Entity $attacker, $damage, $x, $z, $base = 0.4){
		$f = sqrt($x * $x + $z * $z);
		if($f <= 0){
			return;
		}

		$f = 1 / $f;

		$motion = new Vector3($this->motionX, $this->motionY, $this->motionZ);

		$motion->x /= 2;
		$motion->y /= 2;
		$motion->z /= 2;
		$motion->x += $x * $f * $base;
		$motion->y += $base;
		$motion->z += $z * $f * $base;

		if($motion->y > $base){
			$motion->y = $base;
		}

		$this->setMotion($motion);
	}

	public function kill(){
		if(!$this->isAlive()){
			return;
		}
		parent::kill();
		$this->server->getPluginManager()->callEvent($ev = new EntityDeathEvent($this, $this->getDrops()));
		foreach($ev->getDrops() as $item){
			$this->getLevel()->dropItem($this, $item);
		}
	}

	public function entityBaseTick($tickDiff = 1){
		Timings::$timerLivingEntityBaseTick->startTiming();

		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->isAlive()){
			if($this->isInsideOfSolid()){
				$hasUpdate = true;
				$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 1);
				$this->attack($ev->getFinalDamage(), $ev);
			}

			if(!$this->hasEffect(Effect::WATER_BREATHING) and $this->isInsideOfWater()){
				if($this instanceof WaterAnimal){
					$this->setDataProperty(self::DATA_AIR, self::DATA_TYPE_SHORT, 300);
				}else{
					$hasUpdate = true;
					$airTicks = $this->getDataProperty(self::DATA_AIR) - $tickDiff;
					if($airTicks <= -20){
						$airTicks = 0;

						$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_DROWNING, 2);
						$this->attack($ev->getFinalDamage(), $ev);
					}
					$this->setDataProperty(self::DATA_AIR, self::DATA_TYPE_SHORT, $airTicks);
				}
			}else{
				if($this instanceof WaterAnimal){
					$hasUpdate = true;
					$airTicks = $this->getDataProperty(self::DATA_AIR) - $tickDiff;
					if($airTicks <= -20){
						$airTicks = 0;

						$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 2);
						$this->attack($ev->getFinalDamage(), $ev);
					}
					$this->setDataProperty(self::DATA_AIR, self::DATA_TYPE_SHORT, $airTicks);
				}else{
					$this->setDataProperty(self::DATA_AIR, self::DATA_TYPE_SHORT, 300);
				}
			}
		}

		if($this->attackTime > 0){
			$this->attackTime -= $tickDiff;
		}

		// 耕地踩踏：玩家站在耕地上时有概率将耕地变为泥土
		if($this->onGround and $this instanceof \pocketmine\Player and !$this->isCreative()){
			$feetBlock = $this->level->getBlock($this->temporalVector->setComponents((int) floor($this->x), (int) floor($this->y - 0.5), (int) floor($this->z)));
			if($feetBlock->getId() === Block::FARMLAND and mt_rand(0, 599) === 0){
				$this->level->setBlock($feetBlock, Block::get(Block::DIRT), true);
				$hasUpdate = true;
			}
		}

		Timings::$timerLivingEntityBaseTick->stopTiming();

		return $hasUpdate;
	}

	/**
	 * @return ItemItem[]
	 */
	public function getDrops(){
		return [];
	}

	/**
	 * @param int   $maxDistance
	 * @param int   $maxLength
	 * @param array $transparent
	 *
	 * @return Block[]
	 */
	public function getLineOfSight($maxDistance, $maxLength = 0, array $transparent = []){
		if($maxDistance > 120){
			$maxDistance = 120;
		}

		if(count($transparent) === 0){
			$transparent = null;
		}

		$blocks = [];
		$nextIndex = 0;

		$itr = new BlockIterator($this->level, $this->getPosition(), $this->getDirectionVector(), $this->getEyeHeight(), $maxDistance);

		while($itr->valid()){
			$itr->next();
			$block = $itr->current();
			$blocks[$nextIndex++] = $block;

			if($maxLength !== 0 and count($blocks) > $maxLength){
				array_shift($blocks);
				--$nextIndex;
			}

			$id = $block->getId();

			if($transparent === null){
				if($id !== 0){
					break;
				}
			}else{
				if(!isset($transparent[$id])){
					break;
				}
			}
		}

		return $blocks;
	}

	/**
	 * @param int   $maxDistance
	 * @param array $transparent
	 *
	 * @return Block
	 */
	public function getTargetBlock($maxDistance, array $transparent = []){
		try{
			$block = $this->getLineOfSight($maxDistance, 1, $transparent)[0];
			if($block instanceof Block){
				return $block;
			}
		}catch (\ArrayOutOfBoundsException $e){

		}

		return null;
	}
}