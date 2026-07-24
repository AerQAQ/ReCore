<?php
namespace pocketmine\entity;

use pocketmine\level\format\FullChunk;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\level\particle\FlameParticle;

class Fireball extends Projectile{
    const NETWORK_ID = 85;

    public $width = 0.5;
    public $length = 0.5;
    public $height = 0.5;

    protected $gravity = 0.05;
    protected $drag = 0.01;

    // 伤害系数（父类 Projectile 里是 protected，这里可以直接覆盖）
    protected $damage = 5;

    // 注意：不再重新声明 $fireTicks，避免覆盖父类的 public 属性导致可见性错误

    public function __construct(FullChunk $chunk, CompoundTag $nbt, Entity $shootingEntity = null){
        // 在构造时设置火焰 tick，使目标燃烧 5 秒
        $this->fireTicks = 100;

        parent::__construct($chunk, $nbt, $shootingEntity);
    }

    public function onUpdate($currentTick){
        if($this->closed){
            return false;
        }

        $this->timings->startTiming();

        // 让父类处理完整的移动、碰撞、伤害和销毁逻辑
        $hasUpdate = parent::onUpdate($currentTick);

        // 飞行时生成火焰粒子（仅当实体还存活时）
        if(!$this->closed && $this->age % 5 == 0){
            $this->level->addParticle(new FlameParticle($this->add(
                (mt_rand(-100, 100) / 2000),
                (mt_rand(-100, 100) / 2000),
                (mt_rand(-100, 100) / 2000)
            )));
        }

        // 如果实体已被父类销毁，补充碰撞/消失时的粒子效果
        if($this->closed){
            $this->level->addParticle(new FlameParticle($this->add(
                (mt_rand(-300, 300) / 1000),
                (mt_rand(-300, 300) / 1000),
                (mt_rand(-300, 300) / 1000)
            )));
            $hasUpdate = true;
        }

        $this->timings->stopTiming();

        return $hasUpdate;
    }

    public function spawnTo(Player $player){
        $pk = new AddEntityPacket();
        $pk->type = Fireball::NETWORK_ID;
        $pk->eid = $this->getId();
        $pk->x = $this->x;
        $pk->y = $this->y;
        $pk->z = $this->z;
        $pk->speedX = $this->motionX;
        $pk->speedY = $this->motionY;
        $pk->speedZ = $this->motionZ;
        $pk->metadata = $this->dataProperties;
        $player->dataPacket($pk);

        parent::spawnTo($player);
    }
}