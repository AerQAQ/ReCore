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

use pocketmine\item\Item as ItemItem;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\entity\ai\behavior\{StrollBehavior, RandomLookaroundBehavior, AttackEnemyBehavior, ShootPlayerBehavior};

class Blaze extends Monster{
    const NETWORK_ID = 43;

    public $width = 0.3;
    public $length = 0.9;
    public $height = 1.8;

    private $hurt = 6; // 烈焰人攻击伤害
    private $fireballCooldown = 0; // 火球冷却时间
    
    /**
     * @return string
     */
    public function getName() : string{
        return "Blaze";
    }
    
    public function initEntity(){
        $this->setMaxHealth(20);
        
        // 移植女巫的行为模式：远程攻击 + 闲逛 + 随机环顾
        $this->addBehavior(new ShootPlayerBehavior($this, 85)); // 修改为火球的NETWORK_ID
        $this->addBehavior(new AttackEnemyBehavior($this, [20], true)); // 近战攻击行为
        $this->addBehavior(new StrollBehavior($this));
        $this->addBehavior(new RandomLookaroundBehavior($this));
        
        parent::initEntity();
    }
    
    // 添加火球冷却相关方法
    public function canShootFireball() : bool{
        return $this->fireballCooldown <= 0;
    }
    
    public function setFireballCooldown(int $cooldown){
        $this->fireballCooldown = $cooldown;
    }
    
    public function decreaseFireballCooldown(){
        if($this->fireballCooldown > 0){
            $this->fireballCooldown--;
        }
    }
    
    public function getHurt(){
        return $this->hurt;
    }
    
    public function setHurt($hurt){
        $this->hurt = $hurt;
    }

    /**
     * 移植女巫的经验掉落系统
     * @return int
     */
    public function getXpDropAmount(): int{
        return 10;
    }

    /**
     * @return array
     */
    public function getDrops() : array{
        $drops = [];
        // 烈焰棒掉落（0-1个）
        if(mt_rand(1, 2) === 1){ // 50%几率掉落
            $drops[] = ItemItem::get(ItemItem::BLAZE_ROD, 0, mt_rand(0, 1));
        }
        return $drops;
    }
    
    // 在实体更新时处理冷却
    public function entityBaseTick($tickDiff = 1){
        $hasUpdate = parent::entityBaseTick($tickDiff);
        $this->decreaseFireballCooldown();
        return $hasUpdate;
    }
    
    public function spawnTo(Player $player){
        $pk = new AddEntityPacket();
        $pk->eid = $this->getId();
        $pk->type = self::NETWORK_ID;
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
