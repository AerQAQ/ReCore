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

use pocketmine\level\format\FullChunk;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\entity\ai\behavior\{StrollBehavior, RandomLookaroundBehavior, RevengeBehavior, AttackEnemyBehavior};

class Wolf extends Animal{
    const NETWORK_ID = 14;

    public $width = 0.3;
    public $length = 0.9;
    //public $height = 0.8;

    public $dropExp = [1, 3];
    
    /** @var float 每次攻击造成的伤害 */
    private $hurt = 3.0;
    
    /** @var RevengeBehavior|null 复仇行为实例 */
    private $revengeBehavior = null;
    
    public function __construct(FullChunk $chunk, CompoundTag $nbt){
        parent::__construct($chunk, $nbt);
        
        $this->addBehavior(new StrollBehavior($this));
        $this->addBehavior(new RandomLookaroundBehavior($this));
        $this->initEntity();
    }
    
    public function initEntity(){
        // 先添加复仇行为，确保优先级高于主动攻击
        $this->revengeBehavior = new RevengeBehavior($this);
        $this->addBehavior($this->revengeBehavior);

        // 再添加主动攻击羊的行为（仅在没有复仇目标时生效）
        $this->addBehavior(new AttackEnemyBehavior($this, [13], false));
        
        parent::initEntity();
    }
    
    public function getName() : string{
        return "Wolf";
    }
    
    public function getHurt() : float{
        return $this->hurt;
    }
    
    public function setHurt(float $hurt){
        $this->hurt = $hurt;
    }
    
    /**
     * 重写 attack 方法，受到攻击时设定复仇目标
     */
    public function attack($damage, EntityDamageEvent $source){
        $result = parent::attack($damage, $source);
        
        if(!$source->isCancelled() and $source instanceof EntityDamageByEntityEvent){
            $attacker = $source->getDamager();
            if($attacker !== null and $this->isAlive()){
                if($this->revengeBehavior instanceof RevengeBehavior){
                    $this->revengeBehavior->setRevengeTarget($attacker);
                }
            }
        }
        return $result;
    }
    
    public function spawnTo(Player $player){
        $pk = new AddEntityPacket();
        $pk->eid = $this->getId();
        $pk->type = Wolf::NETWORK_ID;
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
}