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
use pocketmine\entity\ai\behavior\{StrollBehavior, RandomLookaroundBehavior, ShootPlayerBehavior};
use pocketmine\item\Item;
use pocketmine\block\Block;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Position;

class SnowGolem extends Animal{
    const NETWORK_ID = 21;

    public $width = 0.3;
    public $length = 0.9;
    public $height = 1.8;
    
    // 用于控制生成雪和扣血的计时器
    private $snowTimer = 0;
    private $waterDamageTimer = 0;
    
    public function initEntity(){
        $this->setMaxHealth(4);
        
        // 添加AI行为：远程攻击、行走、随机看
        $this->addBehavior(new ShootPlayerBehavior($this, 81)); // 81是雪球的网络ID
        $this->addBehavior(new StrollBehavior($this));
        $this->addBehavior(new RandomLookaroundBehavior($this));
        
        parent::initEntity();
    }
    
    public function getName() {
        return "Snow Golem";
    }
    
    /**
     * 实体更新逻辑
     */
    public function onUpdate($currentTick){
        if($this->closed){
            return false;
        }
        
        $hasUpdate = parent::onUpdate($currentTick);
        
        // 每10tick（0.5秒）执行一次
        if($this->snowTimer-- <= 0){
            $this->generateSnowUnderneath();
            $this->snowTimer = 10;
        }
        
        // 每20tick（1秒）检查一次水伤害
        if($this->waterDamageTimer-- <= 0){
            $this->checkWaterDamage();
            $this->waterDamageTimer = 20;
        }
        
        return $hasUpdate;
    }
    
    /**
     * 在脚下生成雪
     */
    private function generateSnowUnderneath(){
        if(!$this->isAlive()){
            return;
        }
        
        $pos = $this->getPosition();
        $blockUnder = $this->level->getBlock($pos->subtract(0, 1, 0));
        
        // 检查脚下方块是否可以放置雪
        if($this->canPlaceSnowOn($blockUnder)){
            $snowLayer = Block::get(Block::SNOW_LAYER, 0, $pos);
            
            // 检查当前位置是否已经有雪
            $currentBlock = $this->level->getBlock($pos);
            if($currentBlock->getId() !== Block::SNOW_LAYER){
                // 放置一层雪（不是替换，是叠加）
                $this->level->setBlock($pos, $snowLayer, false, false);
            }
        }
    }
    
    /**
     * 检查方块是否可以放置雪
     */
    private function canPlaceSnowOn(Block $block){
        $blockId = $block->getId();
        
        // 雪可以放置在以下方块上
        $allowedBlocks = [
            Block::GRASS,
            Block::DIRT,
            Block::STONE,
            Block::COBBLESTONE,
            Block::WOODEN_PLANKS,
            Block::SAND,
            Block::GRAVEL,
            Block::SNOW_BLOCK,
            Block::ICE
        ];
        
        return in_array($blockId, $allowedBlocks) && !$block->isTransparent();
    }
    
    /**
     * 检查水伤害
     */
    private function checkWaterDamage(){
        if(!$this->isAlive()){
            return;
        }
        
        $pos = $this->getPosition();
        $blockUnder = $this->level->getBlock($pos->subtract(0, 1, 0));
        
        // 检查脚下是否是水
        if($blockUnder->getId() === Block::WATER || $blockUnder->getId() === Block::FLOWING_WATER){
            // 雪傀儡在水中会受到伤害
            $this->attack(1, new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 1));
            
            // 可以添加粒子效果或声音来表示融化
            // $this->getLevel()->addParticle(new DestroyBlockParticle($this, Block::get(Block::SNOW_BLOCK)));
        }
        
        // 额外检查：如果雪傀儡本身在水中也扣血
        $blockAt = $this->level->getBlock($pos);
        if($blockAt->getId() === Block::WATER || $blockAt->getId() === Block::FLOWING_WATER){
            $this->attack(1, new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 1));
        }
    }
    
    // 添加掉落物
    public function getDrops() : array{
        $drops = [];
        // 雪球掉落（0-15个）
        $drops[] = Item::get(Item::SNOWBALL, 0, mt_rand(0, 15));
        return $drops;
    }
    
    public function spawnTo(Player $player) {
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
