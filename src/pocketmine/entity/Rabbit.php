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

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item as ItemItem;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\level\format\FullChunk;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\entity\ai\behavior\{StrollBehavior, RandomLookaroundBehavior, LookAtPlayerBehavior, PanicBehavior, findFoodBehavior, inLoveBehavior};

class Rabbit extends Animal{
    const NETWORK_ID = 18;

    const TYPE_BROWN = 0;
    const TYPE_WHITE = 1;
    const TYPE_BLACK = 2;
    const TYPE_BLACK_WHITE = 3;
    const TYPE_GOLD = 4;
    const TYPE_SALT_PEPPER = 5;
    const TYPE_KILLER_BUNNY = 99;

    public $height = 0.5;
    public $width = 0.5;
    public $length = 0.5;

    public $dropExp = [1, 3];
    
    /** @var int 下一次跳跃的时间戳 */
    private $nextJumpTime = 0;
    /** @var int 跳跃的最小间隔时间（秒） */
    private $jumpIntervalMin = 2; // 2秒
    /** @var int 跳跃的最大间隔时间（秒） */
    private $jumpIntervalMax = 5; // 5秒

    public function initEntity(){
        $this->setMaxHealth(3);
        
        // 添加兔子行为系统
        $this->addBehavior(new inLoveBehavior($this));
        $this->addBehavior(new PanicBehavior($this, 0.35, 2.5)); // 兔子跑得更快
        $this->addBehavior(new findFoodBehavior($this, 391)); // 胡萝卜
        $this->addBehavior(new StrollBehavior($this));
        $this->addBehavior(new LookAtPlayerBehavior($this));
        $this->addBehavior(new RandomLookaroundBehavior($this));
        
        // 初始化跳跃时间
        $this->scheduleNextJump();
        
        parent::initEntity();
    }
    
    /**
     * 安排下一次跳跃的时间
     */
    private function scheduleNextJump(){
        $interval = mt_rand($this->jumpIntervalMin, $this->jumpIntervalMax);
        $this->nextJumpTime = time() + $interval;
    }
    
    /**
     * 实体更新时的处理
     */
    public function onUpdate($currentTick){
        if($this->closed){
            return false;
        }
        
        // 检查是否到了跳跃的时间
        if($this->isAlive() && time() >= $this->nextJumpTime){
            $this->performJump();
            $this->scheduleNextJump();
        }
        
        return parent::onUpdate($currentTick);
    }
    
    /**
     * 兔子跳跃
     */
    private function performJump(){
        if($this->closed){
            return;
        }
        
        // 给兔子一个向上的速度来模拟跳跃
        $this->motionY = 0.5;
        
        // 可以添加一些跳跃的音效或粒子效果
        // $this->getLevel()->addSound(...);
    }

    public function __construct(FullChunk $chunk, CompoundTag $nbt){
        if(!isset($nbt->RabbitType)){
            $nbt->RabbitType = new ByteTag("RabbitType", $this->getRandomRabbitType());
        }
        parent::__construct($chunk, $nbt);

        $this->setDataProperty(self::DATA_RABBIT_TYPE, self::DATA_TYPE_BYTE, $this->getRabbitType());
    }

    public function getRandomRabbitType() : int{
        $arr = [0, 1, 2, 3, 4, 5, 99];
        return $arr[mt_rand(0, count($arr) - 1)];
    }

    public function setRabbitType(int $type){
        $this->namedtag->RabbitType = new ByteTag("RabbitType", $type);
    }

    public function getRabbitType() : int{
        return (int) $this->namedtag["RabbitType"];
    }

    public function getName() : string{
        return "Rabbit";
    }

    public function spawnTo(Player $player){
        $pk = new AddEntityPacket();
        $pk->eid = $this->getId();
        $pk->type = Rabbit::NETWORK_ID;
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
        
        // 兔皮掉落（0-2个）
        $drops[] = ItemItem::get(ItemItem::RABBIT_HIDE, 0, mt_rand(0, 2));
        
        // 肉类掉落
        if($this->getLastDamageCause() instanceof EntityDamageByEntityEvent && $this->getLastDamageCause()->getCause() === EntityDamageEvent::CAUSE_FIRE){
            $drops[] = ItemItem::get(ItemItem::COOKED_RABBIT, 0, mt_rand(1, 2));
        }else{
            $drops[] = ItemItem::get(ItemItem::RAW_RABBIT, 0, mt_rand(1, 2));
        }
        
        // 添加兔子脚掉落（10%概率）
        if(mt_rand(1, 10) === 1){ // 10%概率掉落兔子脚
            $drops[] = ItemItem::get(ItemItem::RABBIT_FOOT, 0, 1);
        }

        return $drops;
    }
    
    /**
     * 获取下一次跳跃的时间
     * @return int
     */
    public function getNextJumpTime() : int{
        return $this->nextJumpTime;
    }
    
    /**
     * 设置下一次跳跃的时间（用于外部控制）
     * @param int $time
     */
    public function setNextJumpTime(int $time){
        $this->nextJumpTime = $time;
    }
}
