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
use pocketmine\nbt\tag\ShortTag;
use pocketmine\Player;
use pocketmine\item\Item as ItemItem;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\math\Vector3;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\entity\ai\behavior\{
    StrollBehavior, 
    RandomLookaroundBehavior, 
    LookAtPlayerBehavior, 
    PanicBehavior,
    ShootPlayerBehavior
};

class Enderman extends Monster{
    const NETWORK_ID = 38;

    public $width = 0.3;
    public $length = 0.9;
    public $height = 1.8;

    public $dropExp = [5, 5];
    
    /** @var int 愤怒状态持续时间 */
    private $angerTime = 0;
    /** @var Player|null 愤怒目标 */
    private $angerTarget = null;
    /** @var int 攻击冷却时间 */
    private $attackCooldown = 0;
    /** @var bool 是否处于愤怒状态 */
    private $isAngry = false;
    /** @var int 水中伤害计时器 */
    private $waterDamageTimer = 0;
    /** @var int 上次传送时间 */
    private $lastTeleportTime = 0;
    
    public function getName() : string{
        return "Enderman";
    }
    
    public function initEntity(){
        if(!isset($this->namedtag->carriedData)){
            $this->namedtag->carriedData = new ShortTag("carriedData", 0);
            $this->namedtag->carried = new ShortTag("carried", 0);
        }
        
        // 设置末影人属性
        $this->setMaxHealth(40);
        $this->setHealth(40);
        
        // 初始化末影人行为
        $this->addBehavior(new ShootPlayerBehavior($this, 0, 0.3, 1.0)); // 攻击行为
        $this->addBehavior(new PanicBehavior($this, 0.3, 1.0)); // 受伤后逃跑
        $this->addBehavior(new StrollBehavior($this, 100, 0.2, 0.8)); // 随机漫步
        $this->addBehavior(new LookAtPlayerBehavior($this, 8.0)); // 看向玩家
        $this->addBehavior(new RandomLookaroundBehavior($this)); // 随机环顾
        
        parent::initEntity();
    }
    
    /**
     * 设置愤怒状态
     */
    public function setAngry(Player $target = null, int $duration = 400) : void{
        $this->isAngry = true;
        $this->angerTime = $duration;
        $this->angerTarget = $target;
        $this->setTremble(true); // 末影人生气时会颤抖
    }
    
    /**
     * 清除愤怒状态
     */
    public function clearAnger() : void{
        $this->isAngry = false;
        $this->angerTime = 0;
        $this->angerTarget = null;
        $this->setTremble(false);
    }
    
    /**
     * 检查是否处于愤怒状态
     */
    public function isAngry() : bool{
        return $this->isAngry;
    }
    
    /**
     * 获取愤怒目标
     */
    public function getAngerTarget() : ?Player{
        return $this->angerTarget;
    }
    
    /**
     * 设置颤抖状态
     */
    public function setTremble(bool $setting){
        $this->setDataProperty(self::DATA_ENDERMAN_TREMBLE, self::DATA_TYPE_BYTE, $setting ? 1 : 0);
    }
    
    /**
     * 设置手中方块
     */
    public function setBlockInHand(int $id, int $meta){
        $this->setDataProperty(self::DATA_ENDERMAN_HELD_ITEM_ID, self::DATA_TYPE_SHORT, $id);
        $this->setDataProperty(self::DATA_ENDERMAN_HELD_ITEM_DAMAGE, self::DATA_TYPE_SHORT, $meta);
        $this->namedtag->carriedData = new ShortTag("carriedData", $id);
        $this->namedtag->carried = new ShortTag("carried", $meta);
    }
    
    /**
     * 获取手中方块
     */
    public function getBlockInHand(&$id, &$meta){
        $id = $this->namedtag["carriedData"];
        $meta = $this->namedtag["carried"];
    }
    
    /**
     * 实体更新处理
     */
    public function onUpdate($currentTick){
        if($this->closed){
            return false;
        }
        
        // 水中伤害处理
        if($this->isInsideOfWater()){
            $this->waterDamageTimer++;
            if($this->waterDamageTimer >= 20){ // 每秒受到一次伤害
                $this->attack(1, new EntityDamageEvent($this, EntityDamageEvent::CAUSE_DROWNING, 1));
                $this->waterDamageTimer = 0;
                
                // 在水中时有几率尝试传送离开
                if(mt_rand(0, 3) === 0){
                    $this->teleportToSafeLocation();
                }
            }
        } else {
            $this->waterDamageTimer = 0;
        }
        
        // 更新愤怒状态
        if($this->isAngry){
            if($this->angerTime > 0){
                $this->angerTime--;
                
                // 检查目标是否仍然有效
                if($this->angerTarget !== null && 
                   (!$this->angerTarget->isOnline() || 
                    !$this->angerTarget->isAlive() || 
                    $this->distance($this->angerTarget) > 32)){
                    $this->clearAnger();
                }
            } else {
                $this->clearAnger();
            }
        }
        
        // 更新攻击冷却
        if($this->attackCooldown > 0){
            $this->attackCooldown--;
        }
        
        // 愤怒状态下的特殊行为
        if($this->isAngry && $this->angerTarget !== null){
            $this->aggressiveBehavior();
        }
        
        // 检查是否被玩家注视（非愤怒状态下）
        if(!$this->isAngry && mt_rand(0, 100) < 5){
            $this->checkPlayerStaring();
        }
        
        return parent::onUpdate($currentTick);
    }
    
    /**
     * 检查玩家是否在注视末影人
     */
    private function checkPlayerStaring() : void{
        foreach($this->level->getPlayers() as $player){
            if($player->isSurvival() && $player->isAlive() && $this->distance($player) < 8){
                if($this->isPlayerLookingAtEnderman($player)){
                    $this->onPlayerLook($player);
                    break;
                }
            }
        }
    }
    
    /**
     * 检查玩家是否在注视末影人
     */
    private function isPlayerLookingAtEnderman(Player $player) : bool{
        $playerPos = $player->getPosition();
        $endermanPos = $this->getPosition();
        
        // 计算玩家视线方向
        $yaw = deg2rad($player->yaw);
        $pitch = deg2rad($player->pitch);
        
        $lookDir = new Vector3(
            -sin($yaw) * cos($pitch),
            -sin($pitch),
            cos($yaw) * cos($pitch)
        );
        
        // 计算玩家到末影人的方向
        $toEnderman = $endermanPos->subtract($playerPos)->normalize();
        
        // 计算两个向量的点积，判断角度
        $dot = $lookDir->dot($toEnderman);
        
        // 如果夹角小于45度（cos(45°) ≈ 0.7），则认为玩家在注视末影人
        return $dot > 0.7;
    }
    
    /**
     * 传送到安全位置（用于离开水中）
     */
    private function teleportToSafeLocation() : bool{
        $currentTime = $this->server->getTick();
        if($currentTime - $this->lastTeleportTime < 20){ // 最少1秒冷却
            return false;
        }
        
        // 寻找安全的陆地位置
        for($i = 0; $i < 10; $i++){
            $x = $this->x + mt_rand(-8, 8);
            $z = $this->z + mt_rand(-8, 8);
            $y = $this->level->getHighestBlockAt($x, $z);
            
            if($y > 0 && $this->level->getBlockIdAt($x, $y - 1, $z) !== 0){
                // 检查目标位置是否安全（不在水中）
                if($this->level->getBlockIdAt($x, $y, $z) === 0 && 
                   $this->level->getBlockIdAt($x, $y + 1, $z) === 0 &&
                   $this->level->getBlockIdAt($x, $y - 1, $z) !== 8 && // 不是水
                   $this->level->getBlockIdAt($x, $y - 1, $z) !== 9){   // 不是流动的水
                   
                    $this->playTeleportSound();
                    $this->teleport(new Vector3($x, $y, $z));
                    $this->lastTeleportTime = $currentTime;
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * 播放传送音效
     */
    private function playTeleportSound() : void{
        $sound = new EndermanTeleportSound($this);
        $this->level->addSound($sound);
    }
    
    /**
     * 愤怒状态下的攻击行为
     */
    private function aggressiveBehavior() : void{
        $target = $this->angerTarget;
        if($target === null || !$target->isAlive()) return;
        
        $distance = $this->distance($target);
        
        // 如果距离较远，尝试传送靠近
        if($distance > 8 && mt_rand(0, 100) < 15){
            $this->teleportNearTarget($target);
        }
        
        // 近战攻击
        if($distance <= 3 && $this->attackCooldown <= 0){
            $this->meleeAttack($target);
        }
        
        // 愤怒状态有几率随机传送
        if(mt_rand(0, 100) < 5){
            $this->teleportNearTarget($target);
        }
    }
    
    /**
     * 传送到目标附近
     */
    private function teleportNearTarget(Player $target) : bool{
        $currentTime = $this->server->getTick();
        if($currentTime - $this->lastTeleportTime < 20){ // 最少1秒冷却
            return false;
        }
        
        $x = $target->x + mt_rand(-5, 5);
        $z = $target->z + mt_rand(-5, 5);
        $y = $target->y;
        
        // 寻找安全的位置
        for($attempt = 0; $attempt < 10; $attempt++){
            $safeY = $this->level->getHighestBlockAt($x, $z) + 1;
            if($safeY > 0){
                $pos = new Vector3($x, $safeY, $z);
                
                // 检查位置是否安全（不在水中，有站立点）
                if($this->level->getBlockIdAt($x, $safeY, $z) === 0 && 
                   $this->level->getBlockIdAt($x, $safeY + 1, $z) === 0 &&
                   $this->level->getBlockIdAt($x, $safeY - 1, $z) !== 8 && // 不是水
                   $this->level->getBlockIdAt($x, $safeY - 1, $z) !== 9){  // 不是流动的水
                   
                    $this->playTeleportSound();
                    $this->teleport($pos);
                    $this->lastTeleportTime = $currentTime;
                    return true;
                }
            }
            
            $x = $target->x + mt_rand(-5, 5);
            $z = $target->z + mt_rand(-5, 5);
        }
        return false;
    }
    
    /**
     * 近战攻击
     */
    private function meleeAttack(Player $target) : void{
        $damage = 7; // 末影人基础伤害
        
        $ev = new EntityDamageByEntityEvent($this, $target, EntityDamageByEntityEvent::CAUSE_ENTITY_ATTACK, $damage);
        $target->attack($damage, $ev);
        
        $this->attackCooldown = 20; // 1秒冷却
    }
    
    /**
     * 当被玩家看时触发愤怒
     */
    public function onPlayerLook(Player $player) : void{
        if(!$this->isAngry && mt_rand(0, 100) < 30){ // 30%几率被激怒
            $this->setAngry($player, 400); // 愤怒20秒
            $this->playTeleportSound(); // 被激怒时播放音效
        }
    }
    
    /**
     * 当受到伤害时触发
     */
    public function attack($damage, EntityDamageEvent $source){
        parent::attack($damage, $source);
        
        // 如果被玩家攻击，进入愤怒状态
        if($source instanceof EntityDamageByEntityEvent){
            $attacker = $source->getDamager();
            if($attacker instanceof Player){
                $this->setAngry($attacker, 600); // 被攻击时愤怒30秒
                $this->playTeleportSound(); // 被攻击时播放音效
            }
        }
        
        // 受到伤害时有几率传送
        if(mt_rand(0, 3) === 0){
            $this->teleportToSafeLocation();
        }
        
        return true;
    }
    
    /**
     * 死亡处理
     */
    public function kill(){
        // 播放死亡音效
        $this->playTeleportSound();
        parent::kill();
    }
    
    /**
     * 修改掉落物 - 只掉落鸡蛋
     */
    public function getDrops() : array{
        $drops = [];
        
        // 只掉落鸡蛋，数量为0-1个
        if(mt_rand(0, 1) === 0){ // 50%几率掉落
            $drops[] = ItemItem::get(ItemItem::EGG, 0, 1);
        }
        
        return $drops;
    }
    
    public function spawnTo(Player $player){
        $pk = new AddEntityPacket();
        $pk->eid = $this->getId();
        $pk->type = Enderman::NETWORK_ID;
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
