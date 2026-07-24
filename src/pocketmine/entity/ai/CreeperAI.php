<?php

namespace pocketmine\entity\ai;

use pocketmine\entity\ai\AIHolder;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\entity\Entity;
use pocketmine\entity\Creeper;
use pocketmine\scheduler\CallbackTask;
use pocketmine\network\protocol\SetEntityMotionPacket;
use pocketmine\level\Explosion;
use pocketmine\level\Position;
use pocketmine\event\entity\CreeperPowerEvent;

class CreeperAI {
    private $AIHolder;
    private $difficulty = 0;
    private $explosionPower = 3; // 默认爆炸威力
    public $hatredRadius = 8; // 仇恨半径
    public $chaseSpeed = 1.7; // 仇恨模式下的行走速度
    
    // 状态常量
    const STATE_IDLE = 0;
    const STATE_CHASING = 1;
    const STATE_EXPLODING = 2;
    const STATE_FLASHING = 3; // 闪烁状态（爆炸前）
    
    // 配置常量
    const RANDOM_WALK_INTERVAL = 15;
    const HATE_FINDER_INTERVAL = 10;
    const ARRAY_CLEAR_INTERVAL = 100;
    
    // 爆炸相关常量（根据Minecraft Wiki）
    const EXPLOSION_FUSE_TICKS = 30; // 1.5秒融合时间
    const EXPLOSION_RADIUS = 3; // 爆炸半径
    const MAX_EXPLOSION_POWER = 6; // 闪电苦力怕的最大威力

    public function __construct(AIHolder $AIHolder) {
        $this->AIHolder = $AIHolder;
        $server = $this->AIHolder->getServer();
        
        // 合并定时器，减少任务数量
        $server->getScheduler()->scheduleRepeatingTask(
            new CallbackTask([$this, "tickAI"]), 
            1
        );
        
        $server->getScheduler()->scheduleRepeatingTask(
            new CallbackTask([$this, "clearInvalidEntities"]), 
            self::ARRAY_CLEAR_INTERVAL
        );
    }

    /**
     * 主AI tick方法
     */
    public function tickAI() {
        $this->difficulty = $this->AIHolder->getServer()->getDifficulty();
        
        static $randomWalkCounter = 0;
        static $hateFinderCounter = 0;
        
        $randomWalkCounter++;
        $hateFinderCounter++;
        
        // 随机行走逻辑（每15tick执行一次）
        if ($randomWalkCounter >= self::RANDOM_WALK_INTERVAL) {
            $this->updateRandomWalk();
            $randomWalkCounter = 0;
        }
        
        // 仇恨检测逻辑（每10tick执行一次）
        if ($hateFinderCounter >= self::HATE_FINDER_INTERVAL) {
            $this->updateHateTargets();
            $hateFinderCounter = 0;
        }
        
        // 每tick都执行的逻辑
        $this->updateMovements();
        $this->updateExplosions();
    }

    /**
     * 清理无效实体
     */
    public function clearInvalidEntities() {
        if (empty($this->AIHolder->Creeper)) {
            return;
        }
        
        foreach ($this->AIHolder->Creeper as $eid => $info) {
            $entityFound = false;
            foreach ($this->AIHolder->getServer()->getLevels() as $level) {
                if ($level->getEntity($eid) instanceof Creeper) {
                    $entityFound = true;
                    break;
                }
            }
            
            if (!$entityFound) {
                unset($this->AIHolder->Creeper[$eid]);
            }
        }
    }

    /**
     * 苦力怕随机行走逻辑
     */
    private function updateRandomWalk() {
        foreach ($this->AIHolder->getServer()->getLevels() as $level) {
            foreach ($level->getEntities() as $entity) {
                if ($entity instanceof Creeper && $this->AIHolder->willMove($entity)) {
                    $this->processCreeperAI($entity);
                }
            }
        }
    }

    /**
     * 处理单个苦力怕的AI
     */
    private function processCreeperAI(Creeper $creeper) {
        $eid = $creeper->getId();
        
        // 初始化苦力怕数据
        if (!isset($this->AIHolder->Creeper[$eid])) {
            $this->initCreeperData($creeper, $eid);
        }
        
        $creeperData = &$this->AIHolder->Creeper[$eid];
        
        // 如果正在爆炸，不处理移动逻辑
        if ($creeperData['state'] == self::STATE_EXPLODING) {
            return;
        }
        
        // 根据状态执行不同逻辑
        switch ($creeperData['state']) {
            case self::STATE_IDLE:
                $this->handleIdleState($creeper, $creeperData);
                break;
            case self::STATE_CHASING:
                $this->handleChasingState($creeper, $creeperData);
                break;
            case self::STATE_FLASHING:
                $this->handleFlashingState($creeper, $creeperData);
                break;
        }
    }

    /**
     * 初始化苦力怕数据
     */
    private function initCreeperData(Creeper $creeper, int $eid) {
        // 检查是否为闪电苦力怕（威力更大）
        $isPowered = $creeper->getDataProperty(19) ?? false; // DATA_POWERED
        $explosionPower = $isPowered ? self::MAX_EXPLOSION_POWER : $this->explosionPower;
        
        $this->AIHolder->Creeper[$eid] = [
            'ID' => $eid,
            'IsChasing' => false,
            'boomed' => false,
            'targetPlayer' => null,
            'motionx' => 0,
            'motiony' => 0,
            'motionz' => 0,
            'hurt' => 10,
            'time' => 10,
            'x' => $creeper->getX(),
            'y' => $creeper->getY(),
            'z' => $creeper->getZ(),
            'oldv3' => $creeper->getLocation(),
            'yup' => 20,
            'up' => 0,
            'yaw' => $creeper->yaw,
            'pitch' => 0,
            'level' => $creeper->getLevel()->getName(),
            'xxx' => 0,
            'zzz' => 0,
            'gotimer' => 10,
            'swim' => 0,
            'jump' => 0.01,
            'canjump' => true,
            'drop' => false,
            'canAttack' => 0,
            'knockBack' => false,
            'fuseTime' => 0, // 爆炸引信时间
            'maxFuseTime' => self::EXPLOSION_FUSE_TICKS,
            'explosionRadius' => self::EXPLOSION_RADIUS,
            'explosionPower' => $explosionPower,
            'isPowered' => $isPowered,
            'state' => self::STATE_IDLE,
            'flashCount' => 0 // 闪烁计数器
        ];
    }

    /**
     * 处理空闲状态
     */
    private function handleIdleState(Creeper $creeper, array &$data) {
        // 平滑改变移动方向
        $data['motionx'] = $this->smoothChange($data['motionx'], mt_rand(-5, 5) / 10, 0.6);
        $data['motionz'] = $this->smoothChange($data['motionz'], mt_rand(-5, 5) / 10, 0.6);
        
        // 行走计时器逻辑
        $data['gotimer'] += 0.5;
        if ($data['gotimer'] >= 22) {
            $data['gotimer'] = 0;
        }
        
        // 在行走周期末期停止移动
        if ($data['gotimer'] >= 20 && $data['gotimer'] <= 24) {
            $data['motionx'] = 0;
            $data['motionz'] = 0;
        }
        
        $this->updateMovement($creeper, $data);
    }

    /**
     * 平滑改变数值
     */
    private function smoothChange(float $current, float $target, float $maxChange): float {
        $difference = $target - $current;
        if (abs($difference) > $maxChange) {
            return $current + ($difference > 0 ? $maxChange : -$maxChange);
        }
        return $target;
    }

    /**
     * 更新移动逻辑
     */
    private function updateMovement(Creeper $creeper, array &$data) {
        $data['yup'] = 0;
        $data['up'] = 0;
        
        $targetPos = new Vector3(
            $data['x'] + $data['motionx'], 
            floor($creeper->getY()) + 1, 
            $data['z'] + $data['motionz']
        );
        
        $groundY = $this->AIHolder->ifjump($creeper->getLevel(), $targetPos);
        
        if ($groundY === false) {
            // 前方不可前进
            $currentPos = new Vector3($data['x'], $data['y'], $data['z']);
            if ($this->AIHolder->ifjump($creeper->getLevel(), $currentPos) === false) {
                // 原坐标悬空，下降
                $data['up'] = 1;
                $newPos = new Vector3($data['x'], $data['y'] - 1, $data['z']);
            } else {
                // 转向180度
                $data['motionx'] = -$data['motionx'];
                $data['motionz'] = -$data['motionz'];
                $newPos = $currentPos;
            }
        } else {
            // 可以前进
            $newPos = new Vector3(
                $data['x'] + $data['motionx'], 
                $groundY - 1, 
                $data['z'] + $data['motionz']
            );
            $data['up'] = ($newPos->y - $data['y'] < 0) ? 1 : 0;
        }
        
        // 更新朝向
        if ($data['motionx'] != 0 || $data['motionz'] != 0) {
            $data['yaw'] = $this->AIHolder->getyaw($data['motionx'], $data['motionz']);
            $creeper->setRotation($data['yaw'], 0);
        }
        
        // 更新位置
        if (!$data['knockBack']) {
            $data['x'] = $newPos->x;
            $data['z'] = $newPos->z;
            $data['y'] = $newPos->y;
            $creeper->setPosition($newPos);
        }
        
        $data['motiony'] = $newPos->y - $creeper->getY();
    }

    /**
     * 处理追逐状态
     */
    private function handleChasingState(Creeper $creeper, array &$data) {
        $player = $data['targetPlayer'];
        
        if (!($player instanceof Player) || !$player->isAlive() || $player->getGamemode() != 0) {
            $this->resetToIdleState($creeper, $data);
            return;
        }
        
        $distance = $creeper->distance($player);
        
        // 如果玩家离开仇恨半径，返回空闲状态
        if ($distance > $this->hatredRadius) {
            $this->resetToIdleState($creeper, $data);
            return;
        }
        
        $this->chasePlayer($creeper, $player, $data);
    }

    /**
     * 重置到空闲状态
     */
    private function resetToIdleState(Creeper $creeper, array &$data) {
        $data['IsChasing'] = false;
        $data['targetPlayer'] = null;
        $data['state'] = self::STATE_IDLE;
        $data['fuseTime'] = 0;
        $creeper->setSwelled(false);
    }

    /**
     * 追逐玩家逻辑
     */
    private function chasePlayer(Creeper $creeper, Player $player, array &$data) {
        $dx = $player->getX() - $creeper->getX();
        $dz = $player->getZ() - $creeper->getZ();
        $distance = sqrt($dx * $dx + $dz * $dz);
        
        // 计算移动方向
        $speed = $this->chaseSpeed;
        
        if ($distance > 0) {
            $data['xxx'] = ($dx / $distance) * $speed;
            $data['zzz'] = ($dz / $distance) * $speed;
        }
        
        // 靠近玩家时准备爆炸（根据Wiki，苦力怕在1.5格内开始爆炸）
        if ($distance <= 1.5) {
            $this->startExplosionCountdown($creeper, $data);
        } else {
            $this->updateChaseMovement($creeper, $player, $data);
        }
    }

    /**
     * 开始爆炸倒计时
     */
    private function startExplosionCountdown(Creeper $creeper, array &$data) {
        $data['state'] = self::STATE_FLASHING;
        $data['fuseTime'] = $data['maxFuseTime'];
        $data['flashCount'] = 0;
        $creeper->setSwelled(true);
    }

    /**
     * 处理闪烁状态（爆炸前）
     */
    private function handleFlashingState(Creeper $creeper, array &$data) {
        $player = $data['targetPlayer'];
        
        if (!($player instanceof Player) || !$player->isAlive()) {
            $this->cancelExplosion($creeper, $data);
            return;
        }
        
        $distance = $creeper->distance($player);
        
        // 如果玩家离开爆炸范围，取消爆炸
        if ($distance > 7) { // 比爆炸半径稍大，给玩家逃跑机会
            $this->cancelExplosion($creeper, $data);
            return;
        }
        
        // 更新引信时间
        $data['fuseTime']--;
        $data['flashCount']++;
        
        // 闪烁效果：每5tick改变一次大小
        if ($data['flashCount'] % 5 == 0) {
            $creeper->setSwelled(!$creeper->isSwelled());
        }
        
        // 引信结束，爆炸
        if ($data['fuseTime'] <= 0) {
            $data['state'] = self::STATE_EXPLODING;
            $this->executeExplosion($creeper, $data);
        } else {
            // 继续追逐玩家，但速度减慢
            $this->updateChaseMovementDuringFuse($creeper, $player, $data);
        }
    }

    /**
     * 取消爆炸
     */
    private function cancelExplosion(Creeper $creeper, array &$data) {
        $data['state'] = self::STATE_CHASING;
        $data['fuseTime'] = 0;
        $data['flashCount'] = 0;
        $creeper->setSwelled(false);
    }

    /**
     * 在引信期间更新追逐移动
     */
    private function updateChaseMovementDuringFuse(Creeper $creeper, Player $player, array &$data) {
        $dx = $player->getX() - $creeper->getX();
        $dz = $player->getZ() - $creeper->getZ();
        $distance = sqrt($dx * $dx + $dz * $dz);
        
        // 引信期间移动速度减半
        $speed = $this->chaseSpeed * 0.5;
        
        if ($distance > 0) {
            $data['xxx'] = ($dx / $distance) * $speed;
            $data['zzz'] = ($dz / $distance) * $speed;
        }
        
        $this->updateChaseMovement($creeper, $player, $data);
    }

    /**
     * 更新追逐移动
     */
    private function updateChaseMovement(Creeper $creeper, Player $player, array &$data) {
        $targetPos = new Vector3(
            $creeper->getX() + $data['xxx'],
            $creeper->getY() + 1,
            $creeper->getZ() + $data['zzz']
        );
        
        $groundY = $this->AIHolder->ifjump($creeper->getLevel(), $targetPos, true);
        
        if ($groundY === false) {
            $this->handleObstacle($creeper, $data);
        } else {
            $newPos = new Vector3(
                $creeper->getX() + $data['xxx'],
                $groundY - 1,
                $creeper->getZ() + $data['zzz']
            );
            $creeper->setPosition($newPos);
        }
        
        // 更新朝向
        $data['yaw'] = $this->AIHolder->getyaw($data['xxx'], $data['zzz']);
        $creeper->setRotation($data['yaw'], 0);
        
        if (!$data['knockBack']) {
            $data['x'] = $creeper->getX();
            $data['y'] = $creeper->getY();
            $data['z'] = $creeper->getZ();
        }
    }

    /**
     * 处理障碍物
     */
    private function handleObstacle(Creeper $creeper, array &$data) {
        $currentPos = new Vector3($creeper->getX(), $creeper->getY() + 1, $creeper->getZ());
        
        if ($this->AIHolder->ifjump($creeper->getLevel(), $currentPos, false) === false) {
            // 自由落体
            if ($data['drop'] === false) {
                $data['drop'] = 0;
            }
            $newPos = new Vector3(
                $creeper->getX(),
                $creeper->getY() - ($data['drop'] / 2 + 1.25),
                $creeper->getZ()
            );
        } else {
            $data['drop'] = false;
            // 尝试绕开障碍物
            $newPos = $this->findAlternativePath($creeper, $data);
        }
        
        $creeper->setPosition($newPos);
    }

    /**
     * 寻找替代路径
     */
    private function findAlternativePath(Creeper $creeper, array &$data): Vector3 {
        $level = $creeper->getLevel();
        
        // 尝试X轴移动
        if ($this->AIHolder->ifjump($level, new Vector3(
            $creeper->getX() + $data['xxx'], 
            $creeper->getY() + 1, 
            $creeper->getZ()
        ), true) !== false) {
            return new Vector3(
                $creeper->getX() + $data['xxx'],
                floor($creeper->getY()),
                $creeper->getZ()
            );
        }
        
        // 尝试Z轴移动
        if ($this->AIHolder->ifjump($level, new Vector3(
            $creeper->getX(), 
            $creeper->getY() + 1, 
            $creeper->getZ() + $data['zzz']
        ), true) !== false) {
            return new Vector3(
                $creeper->getX(),
                floor($creeper->getY()),
                $creeper->getZ() + $data['zzz']
            );
        }
        
        // 后退
        return new Vector3(
            $creeper->getX() - $data['xxx'] / 5,
            floor($creeper->getY()),
            $creeper->getZ() - $data['zzz'] / 5
        );
    }

    /**
     * 更新仇恨目标
     */
    private function updateHateTargets() {
        if ($this->difficulty == 0) return;
        
        foreach ($this->AIHolder->getServer()->getLevels() as $level) {
            foreach ($level->getEntities() as $entity) {
                if ($entity instanceof Creeper && isset($this->AIHolder->Creeper[$entity->getId()])) {
                    $this->updateCreeperHateTarget($entity);
                }
            }
        }
    }

    /**
     * 更新单个苦力怕的仇恨目标
     */
    private function updateCreeperHateTarget(Creeper $creeper) {
        $data = &$this->AIHolder->Creeper[$creeper->getId()];
        
        // 如果正在爆炸，不更新仇恨目标
        if ($data['state'] == self::STATE_FLASHING || $data['state'] == self::STATE_EXPLODING) {
            return;
        }
        
        $nearestPlayer = null;
        $minDistance = $this->hatredRadius;
        
        $creeperPos = $creeper->getPosition();
        
        foreach ($creeper->getViewers() as $player) {
            if ($player->getGamemode() == 0 && $player->isAlive()) {
                $distance = $player->distance($creeperPos);
                if ($distance <= $minDistance) {
                    $minDistance = $distance;
                    $nearestPlayer = $player;
                }
            }
        }
        
        if ($nearestPlayer !== null) {
            $data['IsChasing'] = $nearestPlayer->getName();
            $data['targetPlayer'] = $nearestPlayer;
            $data['state'] = self::STATE_CHASING;
        } else {
            $data['IsChasing'] = false;
            $data['targetPlayer'] = null;
            $data['state'] = self::STATE_IDLE;
        }
    }

    /**
     * 更新移动数据包
     */
    private function updateMovements() {
        foreach ($this->AIHolder->getServer()->getLevels() as $level) {
            foreach ($level->getEntities() as $entity) {
                if ($entity instanceof Creeper && isset($this->AIHolder->Creeper[$entity->getId()])) {
                    $this->updateCreeperMovement($entity);
                }
            }
        }
    }

    /**
     * 更新单个苦力怕的移动
     */
    private function updateCreeperMovement(Creeper $creeper) {
        $data = &$this->AIHolder->Creeper[$creeper->getId()];
        
        if ($data['canAttack'] > 0) {
            $data['canAttack']--;
        }
        
        // 处理自由落体
        $this->handleFalling($creeper, $data);
        
        if ($data['state'] == self::STATE_EXPLODING) {
            return;
        }
        
        if ($data['IsChasing'] !== false) {
            $this->sendChaseMotionPacket($creeper, $data);
            $this->handlePlayerAttack($creeper, $data);
        } else {
            $this->sendIdleMotionPacket($creeper, $data);
        }
    }

        /**
     * 处理下落逻辑
     */
    private function handleFalling(Creeper $creeper, array &$data) {
        if ($data['drop'] !== false) {
            $oldDrop = $data['drop'];
            $data['drop'] += 0.5;
            $dropY = $creeper->getY() - ($oldDrop * 0.05 + 0.0125);
            
            $fallPos = new Vector3($creeper->getX(), $dropY, $creeper->getZ());
            $blockBelow = $creeper->getLevel()->getBlock(
                new Vector3(floor($creeper->getX()), floor($dropY), floor($creeper->getZ()))
            );
            
            if ($this->AIHolder->whatBlock($creeper->getLevel(), $blockBelow->getPosition()) == "air") {
                $creeper->setPosition($fallPos);
            } else {
                $this->handleFallDamage($creeper, $data);
            }
        }
    }

    /**
     * 处理摔落伤害
     */
    private function handleFallDamage(Creeper $creeper, array &$data) {
        $fallHeight = $data['drop'];
        $damage = ($fallHeight * $fallHeight / 20) - 3;
        
        if ($damage > 0) {
            $creeper->setHealth($creeper->getHealth() - $damage);
        }
        
        $data['drop'] = false;
    }

    /**
     * 发送追逐移动数据包
     */
    private function sendChaseMotionPacket(Creeper $creeper, array &$data) {
        $motionY = -$data['swim'] / 100 + $data['jump'];
        if ($data['drop'] !== false) {
            $motionY -= $data['drop'];
        }
        
        $pk = new SetEntityMotionPacket();
        $pk->entities = [[
            $creeper->getId(),
            $data['xxx'] / 10,
            $motionY,
            $data['zzz'] / 10
        ]];
        
        foreach ($creeper->getViewers() as $viewer) {
            $viewer->dataPacket($pk);
        }
        
        // 更新游泳状态
        if ($this->AIHolder->whatBlock($creeper->getLevel(), $creeper->getPosition()) == "water") {
            $data['swim'] = ($data['swim'] + 1) % 20;
        } else {
            $data['swim'] = 0;
        }
        
        // 跳跃逻辑
        $this->updateJumpState($creeper, $data);
    }

    /**
     * 更新跳跃状态
     */
    private function updateJumpState(Creeper $creeper, array &$data) {
        if (abs($creeper->getY() - $data['oldv3']->y) == 1 && $data['canjump']) {
            $data['canjump'] = false;
            $data['jump'] = 0.5;
        } else {
            $data['jump'] = max(0.01, $data['jump'] - 0.1);
        }
        
        $data['oldv3'] = $creeper->getLocation();
    }

    /**
     * 处理玩家攻击
     */
    private function handlePlayerAttack(Creeper $creeper, array &$data) {
        $player = $data['targetPlayer'];
        
        if (!($player instanceof Player)) {
            return;
        }
        
        $distance = $player->distance($creeper->getPosition());
        
        if ($distance <= 1.5 && $data['canAttack'] == 0) {
            $data['canAttack'] = 20;
            $player->knockBack($creeper, 0, $data['xxx'] / 10, $data['zzz'] / 10);
            
            // 苦力怕的火焰点燃人类
            if ($creeper->isOnFire()) {
                $player->setOnFire(5);
            }
        }
    }

    /**
     * 发送空闲移动数据包
     */
    private function sendIdleMotionPacket(Creeper $creeper, array &$data) {
        $pk = new SetEntityMotionPacket();
        $pk->entities = [[
            $creeper->getId(),
            $data['motionx'] / 10,
            0,
            $data['motionz'] / 10
        ]];
        
        foreach ($creeper->getViewers() as $viewer) {
            $viewer->dataPacket($pk);
        }
    }

    /**
     * 更新爆炸逻辑
     */
    private function updateExplosions() {
        foreach ($this->AIHolder->getServer()->getLevels() as $level) {
            foreach ($level->getEntities() as $entity) {
                if ($entity instanceof Creeper && isset($this->AIHolder->Creeper[$entity->getId()])) {
                    $this->processCreeperExplosion($entity);
                }
            }
        }
    }

    /**
     * 处理苦力怕爆炸
     */
    private function processCreeperExplosion(Creeper $creeper) {
        $data = &$this->AIHolder->Creeper[$creeper->getId()];
        
        if ($data['state'] != self::STATE_EXPLODING) {
            return;
        }
        
        $this->executeExplosion($creeper, $data);
    }

    /**
     * 执行爆炸
     */
    private function executeExplosion(Creeper $creeper, array &$data) {
        $level = $creeper->getLevel();
        $explosionPos = new Position(
            $creeper->getX(),
            $creeper->getY(),
            $creeper->getZ(),
            $level
        );
        
        // 根据是否为闪电苦力怕设置不同的爆炸威力
        $explosionPower = $data['isPowered'] ? self::MAX_EXPLOSION_POWER : $this->explosionPower;
        
        $explosion = new Explosion($explosionPos, $explosionPower);
        
        if ($this->AIHolder->getServer()->aiConfig["creeperexplode"] ?? true) {
            $explosion->explode();
        } else {
            $explosion->explodeB();
        }
        
        $level->removeEntity($creeper);
        unset($this->AIHolder->Creeper[$creeper->getId()]);
    }

    /**
     * 处理苦力怕被闪电击中（变为闪电苦力怕）
     */
    public function onStruckByLightning(Creeper $creeper) {
        $eid = $creeper->getId();
        
        if (isset($this->AIHolder->Creeper[$eid])) {
            $data = &$this->AIHolder->Creeper[$eid];
            $data['isPowered'] = true;
            $data['explosionPower'] = self::MAX_EXPLOSION_POWER;
            
            // 更新苦力怕的外观（闪电效果）
            $creeper->setDataProperty(19, 1); // DATA_POWERED
        }
    }

    /**
     * 处理苦力怕被玩家攻击
     */
    public function onHurtByPlayer(Creeper $creeper, Player $attacker) {
        $eid = $creeper->getId();
        
        if (isset($this->AIHolder->Creeper[$eid])) {
            $data = &$this->AIHolder->Creeper[$eid];
            
            // 如果苦力怕没有被激怒，设置攻击者为仇恨目标
            if ($data['state'] == self::STATE_IDLE) {
                $data['IsChasing'] = $attacker->getName();
                $data['targetPlayer'] = $attacker;
                $data['state'] = self::STATE_CHASING;
            }
        }
    }

    /**
     * 处理苦力怕死亡
     */
    public function onDeath(Creeper $creeper) {
        $eid = $creeper->getId();
        
        // 清理数据
        if (isset($this->AIHolder->Creeper[$eid])) {
            unset($this->AIHolder->Creeper[$eid]);
        }
    }

    /**
     * 获取苦力怕的当前状态
     */
    public function getCreeperState(Creeper $creeper): int {
        $eid = $creeper->getId();
        
        if (isset($this->AIHolder->Creeper[$eid])) {
            return $this->AIHolder->Creeper[$eid]['state'];
        }
        
        return self::STATE_IDLE;
    }

    /**
     * 获取苦力怕的爆炸倒计时
     */
    public function getFuseTime(Creeper $creeper): int {
        $eid = $creeper->getId();
        
        if (isset($this->AIHolder->Creeper[$eid])) {
            return $this->AIHolder->Creeper[$eid]['fuseTime'];
        }
        
        return 0;
    }

    /**
     * 强制苦力怕开始爆炸（用于外部触发）
     */
    public function forceExplode(Creeper $creeper) {
        $eid = $creeper->getId();
        
        if (isset($this->AIHolder->Creeper[$eid])) {
            $data = &$this->AIHolder->Creeper[$eid];
            $data['state'] = self::STATE_EXPLODING;
            $this->executeExplosion($creeper, $data);
        }
    }

    /**
     * 取消苦力怕的爆炸（用于外部干预）
     */
    public function cancelExplosionExternal(Creeper $creeper) {
        $eid = $creeper->getId();
        
        if (isset($this->AIHolder->Creeper[$eid])) {
            $data = &$this->AIHolder->Creeper[$eid];
            $this->cancelExplosion($creeper, $data);
        }
    }
}
