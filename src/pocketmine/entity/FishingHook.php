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

use pocketmine\event\player\PlayerFishEvent;
use pocketmine\level\format\FullChunk;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\item\Item as ItemItem;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\level\particle\Particle;
use pocketmine\level\particle\WaterParticle;
use pocketmine\level\sound\sound;
use pocketmine\level\sound\SplashSound;
use pocketmine\level\MovingObjectPosition;
use pocketmine\event\entity\ProjectileHitEvent;

class FishingHook extends Projectile{
    const NETWORK_ID = 77;
    
    // 新增的钓鱼线显示相关常量
    const DATA_LEAD_HOLDER = 23; // 用于设置"持有鱼竿者"的实体ID
    const DATA_LEAD = 24;        // 用于设置"被牵引实体"的实体ID
    const DATA_UNKNOWN_22 = 22;  // 未知的22号数据
    const DATA_TYPE_FLOAT = 3;   // 浮点数类型
    const DATA_TYPE_LONG = 7;    // 长整型类型

    public $width = 0.25;
    public $length = 0.25;
    public $height = 0.25;

    protected $gravity = 0.1;
    protected $drag = 0.05;

    public $data = 0;
    public $attractTimer = 100;
    public $coughtTimer = 0;

    /** @var Entity|null 当前钩住的实体 */
    public $hookedEntity = null;

    public function initEntity(){
        parent::initEntity();

        if(isset($this->namedtag->Data)){
            $this->data = $this->namedtag["Data"];
        }

        // $this->setDataProperty(FallingSand::DATA_BLOCK_INFO, self::DATA_TYPE_INT, $this->getData());
    }

    /**
     * 修改构造函数，当发射者为玩家时，将初速度提高 1.5 倍，使抛射更有力
     */
    public function __construct(FullChunk $chunk, CompoundTag $nbt, Entity $shootingEntity = null){
        parent::__construct($chunk, $nbt, $shootingEntity);
        if ($shootingEntity instanceof Player) {
            $multiplier = 1.5; // 速度倍率，可根据需要调整
            $this->motionX *= $multiplier;
            $this->motionY *= $multiplier;
            $this->motionZ *= $multiplier;
        }
    }

    public function setData($id){
        $this->data = $id;
    }

    public function getData(){
        return $this->data;
    }

    /**
     * 重写 onUpdate，实现钩住实体的逻辑
     */
    public function onUpdate($currentTick){
        if($this->closed){
            return false;
        }

        $this->timings->startTiming();

        $tickDiff = $currentTick - $this->lastUpdate;
        if($tickDiff <= 0 and !$this->justCreated){
            $this->timings->stopTiming();
            return true;
        }
        $this->lastUpdate = $currentTick;

        $hasUpdate = $this->entityBaseTick($tickDiff);

        if($this->isAlive()){

            // 如果已经钩住实体
            if($this->hookedEntity !== null){
                // 检查实体是否仍然有效
                if(!$this->hookedEntity->isAlive() || $this->hookedEntity->closed){
                    $this->hookedEntity = null;
                    $this->motionX = $this->motionY = $this->motionZ = 0;
                }else{
                    // 将鱼钩位置附着在实体上（稍微偏上一点，类似钩在眼睛高度）
                    $pos = $this->hookedEntity->getPosition();
                    $this->setPosition($pos->add(0, $this->hookedEntity->getEyeHeight() * 0.9, 0));
                    $this->updateMovement();
                }
                $this->timings->stopTiming();
                return true;
            }

            // 正常运动逻辑（复制自 Projectile 并修改碰撞处理）
            if(!$this->isCollided){
                $this->motionY -= $this->gravity;
            }

            $moveVector = new Vector3($this->x + $this->motionX, $this->y + $this->motionY, $this->z + $this->motionZ);

            $list = $this->getLevel()->getCollidingEntities($this->boundingBox->addCoord($this->motionX, $this->motionY, $this->motionZ)->expand(1, 1, 1), $this);

            $nearDistance = PHP_INT_MAX;
            $nearEntity = null;

            foreach($list as $entity){
                if(($entity === $this->shootingEntity and $this->ticksLived < 5)){
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
                // 钩住实体
                $this->hookedEntity = $nearEntity;
                // 停止运动
                $this->motionX = $this->motionY = $this->motionZ = 0;
                // 将鱼钩位置设置为实体位置
                $pos = $nearEntity->getPosition();
                $this->setPosition($pos->add(0, $nearEntity->getEyeHeight() * 0.9, 0));
                $this->updateMovement();
                $this->hadCollision = true;
                // 触发命中事件（可选）
                $this->server->getPluginManager()->callEvent(new ProjectileHitEvent($this));
                $this->timings->stopTiming();
                return true;
            }

            $this->move($this->motionX, $this->motionY, $this->motionZ);

            if($this->isCollided && !$this->hadCollision){
                $this->hadCollision = true;
                $this->motionX = 0;
                $this->motionY = 0;
                $this->motionZ = 0;
                $this->server->getPluginManager()->callEvent(new ProjectileHitEvent($this));
            }elseif(!$this->isCollided && $this->hadCollision){
                $this->hadCollision = false;
            }

            if(!$this->onGround || abs($this->motionX) > 0.00001 || abs($this->motionY) > 0.00001 || abs($this->motionZ) > 0.00001){
                $f = sqrt(($this->motionX ** 2) + ($this->motionZ ** 2));
                $this->yaw = (atan2($this->motionX, $this->motionZ) * 180 / M_PI);
                $this->pitch = (atan2($this->motionY, $f) * 180 / M_PI);
                $hasUpdate = true;
            }

            $this->updateMovement();

            // 原有水中吸引鱼逻辑
            if($this->isCollidedVertically && $this->isInsideOfWater()){
                $this->motionX = 0;
                $this->motionY += 0.01;
                $this->motionZ = 0;
                $this->motionChanged = true;
                $hasUpdate = true;
                
                // --- 读取饵钓附魔等级 ---
                $lureLevel = 0;
                if($this->shootingEntity instanceof Player){
                    $rod = $this->shootingEntity->getInventory()->getItemInHand();
                    if($rod->getId() === ItemItem::FISHING_ROD && $rod->hasEnchantments()){
                        foreach($rod->getEnchantments() as $ench){
                            if($ench->getId() === Enchantment::TYPE_FISHING_LURE){
                                $lureLevel = $ench->getLevel();
                                break;
                            }
                        }
                    }
                }
                // 饵钓附魔：每级减少 5 秒等待时间（原版 100 tick = 5秒）
                // attractTimer 基值：300~1000 tick (15~50秒)，每级饵钓减 100 tick
                // coughtTimer 基值：100~200 tick (5~10秒)，每级饵钓减 20 tick
                
                if($this->attractTimer === 0 && mt_rand(0, 200) <= 20){ // chance, that a fish bites
                    $baseCoughtMin = 5 * 20;  // 100 tick
                    $baseCoughtMax = 10 * 20; // 200 tick
                    $lureReduction = $lureLevel * 20; // 每级减 20 tick
                    $this->coughtTimer = max(20, mt_rand($baseCoughtMin - $lureReduction, $baseCoughtMax - $lureReduction));
                    
                    $baseAttractMin = 30 * 20;  // 600 tick
                    $baseAttractMax = 100 * 20; // 2000 tick
                    $this->attractTimer = max(100, mt_rand($baseAttractMin - $lureReduction * 2, $baseAttractMax - $lureReduction * 2));
                    $this->reeline();
                }elseif($this->attractTimer > 0){
                    $this->attractTimer--;
                }
                if($this->coughtTimer > 0){
                    $this->coughtTimer--;
                }
            }elseif($this->isCollided && $this->keepMovement === true){
                $this->motionX = 0;
                $this->motionY = 0;
                $this->motionZ = 0;
                $this->motionChanged = true;
                $this->keepMovement = false;
                $hasUpdate = true;
            }
        }

        $this->timings->stopTiming();

        return $hasUpdate;
    }

    /**
     * 拉回钩住的实体（由玩家收杆时调用）
     * 动量 = 距离 * 0.1 米/秒
     *
     * @param Player $player
     */
    public function pullHookedEntity(Player $player){
        if($this->hookedEntity === null){
            return;
        }
        $entity = $this->hookedEntity;
        // 计算从实体指向玩家的方向向量
        $dx = $player->x - $entity->x;
        $dy = $player->y - $entity->y;
        $dz = $player->z - $entity->z;
        $distance = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
        if($distance > 0){
            $force = $distance * 0.35; // 动量值 = 距离 * 1/10
            // 归一化方向向量并乘以力
            $entity->setMotion(new Vector3(
                ($dx / $distance) * $force,
                ($dy / $distance) * $force,
                ($dz / $distance) * $force
            ));
        }
    }

    /**
     * 原有的收杆钓物品逻辑（保留）
     */
    public function reeline(){
        if($this->shootingEntity instanceof Player){
            $pos = new Vector3($this->x + 0.2, $this->y + 1, $this->z);
            $this->shootingEntity->getLevel()->addParticle(new WaterParticle($pos)); //水花粒子
            $pos = new Vector3($this->x - 0.2, $this->y + 1, $this->z);
            $this->shootingEntity->getLevel()->addParticle(new WaterParticle($pos)); //水花粒子
            $pos = new Vector3($this->x, $this->y + 1, $this->z + 0.2);
            $this->shootingEntity->getLevel()->addParticle(new WaterParticle($pos)); //水花粒子
            $pos = new Vector3($this->x, $this->y + 1, $this->z - 0.2);
            $this->shootingEntity->getLevel()->addParticle(new WaterParticle($pos)); //水花粒子
            $pos = new Vector3($this->x + 0.2, $this->y + 1, $this->z + 0.2);
            $this->shootingEntity->getLevel()->addParticle(new WaterParticle($pos)); //水花粒子
            $pos = new Vector3($this->x - 0.2, $this->y + 1, $this->z - 0.2);
            $this->shootingEntity->getLevel()->addParticle(new WaterParticle($pos)); //水花粒子
            $pos = new Vector3($this->x - 0.2, $this->y + 1, $this->z + 0.2);
            $this->shootingEntity->getLevel()->addParticle(new WaterParticle($pos)); //水花粒子
            $pos = new Vector3($this->x + 0.2, $this->y + 1, $this->z - 0.2);
            $this->shootingEntity->getLevel()->addParticle(new WaterParticle($pos)); //水花粒子
                
            $pos = new Vector3($this->x, $this->y, $this->z);
            $this->shootingEntity->getLevel()->addSound(new SplashSound($pos, 5)); //水声音
        }

        // --- 读取玩家钓鱼竿附魔等级 ---
        $lureLevel = 0;           // 饵钓等级
        $luckOfTheSeaLevel = 0;   // 海之眷顾等级
        if($this->shootingEntity instanceof Player){
            $rod = $this->shootingEntity->getInventory()->getItemInHand();
            if($rod->getId() === ItemItem::FISHING_ROD && $rod->hasEnchantments()){
                $enchantments = $rod->getEnchantments();
                foreach($enchantments as $ench){
                    if($ench->getId() === Enchantment::TYPE_FISHING_LURE){
                        $lureLevel = $ench->getLevel();
                    }elseif($ench->getId() === Enchantment::TYPE_FISHING_FORTUNE){
                        $luckOfTheSeaLevel = $ench->getLevel();
                    }
                }
            }
        }

        // --- 根据 Wiki 数据重构的战利品表 ---
        // 结构: [物品ID, 物品元数据, 权重, 数量, 分类: 'junk'|'treasure'|'fish']
        $lootTable = [
            // 垃圾 (Junk)
            ['id' => ItemItem::WATER_LILY,     'meta' => 0,  'weight' => 17, 'count' => 1,  'type' => 'junk'],
            ['id' => ItemItem::BOWL,           'meta' => 0,  'weight' => 10, 'count' => 1,  'type' => 'junk'],
            ['id' => ItemItem::LEATHER,        'meta' => 0,  'weight' => 10, 'count' => 1,  'type' => 'junk'],
            ['id' => ItemItem::LEATHER_BOOTS,  'meta' => 0,  'weight' => 10, 'count' => 1,  'type' => 'junk'],
            ['id' => ItemItem::ROTTEN_FLESH,   'meta' => 0,  'weight' => 10, 'count' => 1,  'type' => 'junk'],
            ['id' => ItemItem::STICK,          'meta' => 0,  'weight' => 5,  'count' => 1,  'type' => 'junk'],
            ['id' => ItemItem::STRING,         'meta' => 0,  'weight' => 5,  'count' => 1,  'type' => 'junk'],
            ['id' => ItemItem::GLASS_BOTTLE,   'meta' => 0,  'weight' => 10, 'count' => 1,  'type' => 'junk'],
            ['id' => ItemItem::BONE,           'meta' => 0,  'weight' => 10, 'count' => 1,  'type' => 'junk'],
            ['id' => ItemItem::TRIPWIRE_HOOK,  'meta' => 0,  'weight' => 10, 'count' => 1,  'type' => 'junk'],

            // 宝藏 (Treasure)
            ['id' => ItemItem::FISHING_ROD,    'meta' => 0,  'weight' => 2,  'count' => 1,  'type' => 'treasure'],
            ['id' => ItemItem::DYE,            'meta' => 0,  'weight' => 1,  'count' => 10, 'type' => 'treasure'],

            // 鱼 (Fish)
            ['id' => ItemItem::RAW_FISH,       'meta' => 0,  'weight' => 60, 'count' => 1,  'type' => 'fish'],
            ['id' => ItemItem::RAW_SALMON,     'meta' => 0,  'weight' => 25, 'count' => 1,  'type' => 'fish'],
            ['id' => ItemItem::PUFFER_FISH,    'meta' => 0,  'weight' => 13, 'count' => 1,  'type' => 'fish'],
            ['id' => ItemItem::CLOWN_FISH,     'meta' => 0,  'weight' => 2,  'count' => 1,  'type' => 'fish'],
        ];

        // --- 海之眷顾附魔：调整权重 ---
        // 原版机制：每级减少垃圾权重约 2.5%，增加宝藏权重 2%，鱼权重增加 0.5%
        foreach($lootTable as &$loot){
            if($loot['type'] === 'junk'){
                // 海之眷顾：垃圾权重衰减，每级减少约 2.5% 原始权重
                $loot['weight'] = max(1, (int)($loot['weight'] * (1 - $luckOfTheSeaLevel * 0.025)));
            }elseif($loot['type'] === 'treasure'){
                // 海之眷顾：宝藏权重提升，每级增加约 2%
                $loot['weight'] = (int)($loot['weight'] * (1 + $luckOfTheSeaLevel * 0.02));
            }
            // 鱼的权重不受海之眷顾影响（相对占比会因垃圾减少而提高）
        }
        unset($loot);

        // 加权随机选择
        $totalWeight = 0;
        foreach ($lootTable as $loot) {
            $totalWeight += $loot['weight'];
        }

        $rand = mt_rand(1, $totalWeight);
        $currentWeight = 0;
        $selectedLoot = null;

        foreach ($lootTable as $loot) {
            $currentWeight += $loot['weight'];
            if ($rand <= $currentWeight) {
                $selectedLoot = $loot;
                break;
            }
        }

        // 创建物品实例 (ID, meta, count)
        $item = ItemItem::get($selectedLoot['id'], $selectedLoot['meta'], $selectedLoot['count']);
        
        // 特殊处理：如果物品是皮革靴子，则设置随机耐久度
        if ($selectedLoot['id'] === ItemItem::LEATHER_BOOTS) {
            $randomDamage = mt_rand(0, 40);
            $item->setDamage($randomDamage);
        }

        $this->getLevel()->getServer()->getPluginManager()->callEvent($ev = new PlayerFishEvent($this->shootingEntity, $item, $this));
        
        // --- 钓鱼竿耐久度损耗处理（考虑耐久附魔）---
        if ($this->shootingEntity instanceof Player) {
            $player = $this->shootingEntity;
            $handItem = $player->getInventory()->getItemInHand();
            if ($handItem->getId() === ItemItem::FISHING_ROD) {
                // 检查耐久附魔：减少耐久消耗几率
                $unbreakingLevel = 0;
                if($handItem->hasEnchantments()){
                    foreach($handItem->getEnchantments() as $enc){
                        if($enc->getId() === Enchantment::TYPE_MINING_DURABILITY){
                            $unbreakingLevel = $enc->getLevel();
                            break;
                        }
                    }
                }
                // 耐久附魔：每级有 1/(level+1) 的概率不消耗耐久
                $shouldDamage = true;
                if($unbreakingLevel > 0){
                    $shouldDamage = (mt_rand(1, $unbreakingLevel + 1) === 1);
                }

                if($shouldDamage){
                    $currentDamage = $handItem->getDamage();
                    $newDamage = $currentDamage + 2;
                    $maxDurability = 384;
                    if ($newDamage >= $maxDurability) {
                        $player->getInventory()->setItemInHand(ItemItem::get(ItemItem::AIR, 0, 1));
                    } else {
                        $handItem->setDamage($newDamage);
                        $player->getInventory()->setItemInHand($handItem);
                    }
                }
            }
        }
        
        $this->shootingEntity->getInventory()->addItem($item);
        $this->shootingEntity->addExperience(mt_rand(2, 12));
        
        if($this->shootingEntity instanceof Player){
            $this->shootingEntity->unlinkHookFromPlayer();
        }
        
        if(!$this->closed){
            $this->kill();
            $this->close();
        }
    }

    public function spawnTo(Player $player){		
        $pk = new AddEntityPacket();
        $pk->eid = $this->getId();
        $pk->type = FishingHook::NETWORK_ID;
        $pk->x = $this->x;
        $pk->y = $this->y;
        $pk->z = $this->z;
        $pk->speedX = $this->motionX;
        $pk->speedY = $this->motionY;
        $pk->speedZ = $this->motionZ;
        $pk->yaw = $this->yaw;
        $pk->pitch = $this->pitch;
        $pk->metadata = $this->dataProperties;
        
        // --- 新增的钓鱼线显示逻辑 ---
        // 设置固定的元数据
        $pk->metadata[self::DATA_UNKNOWN_22] = [self::DATA_TYPE_FLOAT, 0.0];
        $pk->metadata[self::DATA_LEAD] = [self::DATA_TYPE_LONG, 0];
        
        // 核心逻辑：根据玩家身份区分显示钓鱼线
        if ($this->shootingEntity instanceof Player) {
            if ($player->getId() === $this->shootingEntity->getId()) {
                // 钓鱼者自己看，不显示线（设为0）
                $pk->metadata[self::DATA_LEAD_HOLDER] = [self::DATA_TYPE_LONG, 0];
            } else {
                // 其他玩家看，显示线连着钓鱼者
                $pk->metadata[self::DATA_LEAD_HOLDER] = [self::DATA_TYPE_LONG, $this->shootingEntity->getId()];
            }
        } else {
            // 如果没有钓鱼者，默认不显示线
            $pk->metadata[self::DATA_LEAD_HOLDER] = [self::DATA_TYPE_LONG, 0];
        }
        // --- 新增结束 ---
        
        $player->dataPacket($pk);
        parent::spawnTo($player);
    }

    public function close(){
        if($this->hookedEntity !== null){
            // 解除钩住关系（实体仍然存在）
            $this->hookedEntity = null;
        }
        parent::close();
    }
}