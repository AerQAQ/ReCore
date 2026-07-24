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

class Chicken extends Animal{
    const NETWORK_ID = 10;

    public $width = 0.6;
    public $length = 0.6;
    public $height = 0;

    public $dropExp = [1, 3];
    
    /** @var int 下一次生鸡蛋的时间戳 */
    private $nextLayEggTime = 0;
    /** @var int 生鸡蛋的最小间隔时间（秒） */
    private $layEggIntervalMin = 300; // 5分钟
    /** @var int 生鸡蛋的最大间隔时间（秒） */
    private $layEggIntervalMax = 600; // 10分钟
    
    public function getName() : string{
        return "Chicken";
    }
    
    public function initEntity(){
        $this->setMaxHealth(4);
        
        $this->addBehavior(new inLoveBehavior($this));
        $this->addBehavior(new PanicBehavior($this, 0.25, 2.0));
        $this->addBehavior(new findFoodBehavior($this, 296));
        $this->addBehavior(new StrollBehavior($this));
        $this->addBehavior(new LookAtPlayerBehavior($this));
        $this->addBehavior(new RandomLookaroundBehavior($this));
        
        // 初始化生鸡蛋时间
        $this->scheduleNextLayEgg();
        
        parent::initEntity();
    }
    
    /**
     * 安排下一次生鸡蛋的时间
     */
    private function scheduleNextLayEgg(){
        $interval = mt_rand($this->layEggIntervalMin, $this->layEggIntervalMax);
        $this->nextLayEggTime = time() + $interval;
    }
    
    /**
     * 实体更新时的处理
     */
    public function onUpdate($currentTick){
        if($this->closed){
            return false;
        }
        
        // 检查是否到了生鸡蛋的时间
        if($this->isAlive() && time() >= $this->nextLayEggTime){
            $this->layEgg();
            $this->scheduleNextLayEgg();
        }
        
        return parent::onUpdate($currentTick);
    }
    
    /**
     * 鸡生鸡蛋
     */
    private function layEgg(){
        if($this->closed){
            return;
        }
        
        // 在鸡的位置生成鸡蛋物品
        $egg = ItemItem::get(ItemItem::EGG, 0, 1);
        $this->getLevel()->dropItem($this, $egg);
        
        // 可以添加一些效果或动画
        // $this->getLevel()->addParticle(...);
    }
    
    public function spawnTo(Player $player){
        $pk = new AddEntityPacket();
        $pk->eid = $this->getId();
        $pk->type = Chicken::NETWORK_ID;
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
    // 1. 羽毛：掉落0-2个，不受火焰影响
    $featherCount = mt_rand(0, 2);
    if ($featherCount > 0) {
        $drops[] = ItemItem::get(ItemItem::FEATHER, 0, $featherCount);
    }
    
    // 2. 鸡肉：固定1个，火焰影响类型，增加抢夺附魔
    $lootingLevel = 0; // 默认0，需根据实际获取方式调整
    $chickenCount = 1; // 固定1个
    
    // 根据是否着火决定掉落生/熟鸡肉
    if($this->isOnFire()){
        $drops[] = ItemItem::get(ItemItem::COOKED_CHICKEN, 0, $chickenCount);
    } else {
        $drops[] = ItemItem::get(ItemItem::RAW_CHICKEN, 0, $chickenCount);
    }
    
    return $drops;
}
    
    /**
     * 获取下一次生鸡蛋的时间
     * @return int
     */
    public function getNextLayEggTime() : int{
        return $this->nextLayEggTime;
    }
    
    /**
     * 设置下一次生鸡蛋的时间（用于外部控制）
     * @param int $time
     */
    public function setNextLayEggTime(int $time){
        $this->nextLayEggTime = $time;
    }
}