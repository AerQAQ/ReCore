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

use pocketmine\nbt\tag\ByteTag;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\MobEquipmentPacket;
use pocketmine\Player;
use pocketmine\item\Item as ItemItem;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\entity\ai\behavior\{StrollBehavior, RandomLookaroundBehavior, RevengeBehavior};

class PigZombie extends Monster implements Ageable{
	const NETWORK_ID = 36;

	public $width = 0.6;
	public $length = 0.6;
	public $height = 1.8;

	public $drag = 0.2;
	public $gravity = 0.3;

	public $dropExp = [5, 5];
	
	private $hurt = 10;
	
	/** @var RevengeBehavior|null 复仇行为实例 */
	private $revengeBehavior = null;
	
	public function getName() : string{
		return "PigZombie";
	}
	
	public function initEntity(){
		$this->setMaxHealth(20);
		
		// 从Zombie移植的婴儿状态代码
		if(!isset($this->namedtag->IsBaby)){
			$this->namedtag->IsBaby = new ByteTag("IsBaby", 1);
			$this->setBaby(false);
		}
		
		// 添加复仇行为（仅在被攻击后反击，不主动攻击）
		$this->revengeBehavior = new RevengeBehavior($this);
		$this->addBehavior($this->revengeBehavior);
		
		// 只保留漫游和随机观望行为，不再添加主动攻击行为
		$this->addBehavior(new StrollBehavior($this));
		$this->addBehavior(new RandomLookaroundBehavior($this));
		
		parent::initEntity();
	}
	
	public function getHurt(){
		return $this->hurt;
	}
	
	public function setHurt($hurt){
		$this->hurt = $hurt;
	}
	
	// 僵尸猪人的正确掉落物
	public function getDrops(){
		$drops = [];
		
		// 腐肉掉落（几率较高）
		if(mt_rand(0, 99) < 85){
			$drops[] = ItemItem::get(ItemItem::ROTTEN_FLESH, 0, mt_rand(0, 1));
		}
		
		// 金粒掉落
		if(mt_rand(0, 99) < 25){
			$drops[] = ItemItem::get(ItemItem::GOLD_NUGGET, 0, mt_rand(0, 1));
		}
		
		// 低几率掉落金锭
		if(mt_rand(0, 99) < 2.5){
			$drops[] = ItemItem::get(ItemItem::GOLD_INGOT, 0, 1);
		}
		
		// 低几率掉落金剑（手持的武器）
		if(mt_rand(0, 99) < 8.5){
			$drops[] = ItemItem::get(ItemItem::GOLD_SWORD, 0, 1);
		}
		
		return $drops;
	}
	
	// 从Zombie移植的婴儿状态方法
	public function isBaby(){
		return $this->namedtag["IsBaby"] == 0 ? false : true;
	}
	
	public function setBaby(bool $resting){
		$this->setDataProperty(self::DATA_ZOMBIE_IS_BABY, self::DATA_TYPE_BYTE, $resting ? 1 : 0);
		$this->namedtag->IsBaby = new ByteTag("IsBaby", $resting ? 1 : 0);
	}
	
	/**
	 * 重写 attack 方法，实现复仇目标设定
	 */
	public function attack($damage, EntityDamageEvent $source){
		// 调用父类处理伤害并获取返回值
		$result = parent::attack($damage, $source);
		
		// 如果伤害是由实体造成且猪人存活，设置复仇目标
		if (!$source->isCancelled() and $source instanceof EntityDamageByEntityEvent) {
			$attacker = $source->getDamager();
			if ($attacker !== null and $this->isAlive()) {
				// 将攻击者设为复仇目标
				if ($this->revengeBehavior instanceof RevengeBehavior) {
					$this->revengeBehavior->setRevengeTarget($attacker);
				}
			}
		}
		return $result;
	}
	
	public function spawnTo(Player $player){
		$pk = new AddEntityPacket();
		$pk->eid = $this->getId();
		$pk->type = PigZombie::NETWORK_ID;
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
		
		$pk = new MobEquipmentPacket();
		$pk->eid = $this->getId();
		$pk->item = new ItemItem(283);
		$pk->slot = 0;
		$pk->selectedSlot = 0;

		$player->dataPacket($pk);
	}
}