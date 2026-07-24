<?php
namespace pocketmine\entity\ai\behavior;

use pocketmine\entity\Mob;
use pocketmine\math\Vector3;
use pocketmine\block\Air;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

class RevengeBehavior extends Behavior {

    public $speed = 0.25;
    public $speedMultiplier = 0.75;
    public $lookDistance = 16.0;
    public $timeLeft = 0;

    /** @var Mob */
    private $revengeTarget = null;

    public function __construct(Mob $entity, float $speed = 0.25, float $speedMultiplier = 0.75) {
        parent::__construct($entity);
        $this->speed = $speed;
        $this->speedMultiplier = $speedMultiplier;
    }

    public function getName(): string {
        return "复仇";
    }

    /**
     * 设置复仇目标
     */
    public function setRevengeTarget($target) {
        $this->revengeTarget = $target;
    }

    public function shouldStart(): bool {
        // 如果有复仇目标并且目标存活，则启动行为
        if ($this->revengeTarget !== null and $this->revengeTarget->isAlive()) {
            // 检查目标是否在跟踪距离内
            if ($this->entity->distance($this->revengeTarget) <= $this->lookDistance) {
                return true;
            }
        }
        // 否则不启动（或目标已无效则清除）
        $this->revengeTarget = null;
        return false;
    }

    public function canContinue(): bool {
        // 目标必须存活且在距离内
        if ($this->revengeTarget === null or !$this->revengeTarget->isAlive()) {
            return false;
        }
        if ($this->revengeTarget instanceof Player and !$this->revengeTarget->isConnected()) {
            return false;
        }
        return $this->entity->distance($this->revengeTarget) <= $this->lookDistance;
    }

    public function onTick() {
        $distance = $this->entity->distance($this->revengeTarget);
        $this->aimAtTarget($this->revengeTarget);

        if ($distance >= 1.5) {
            // 移动逻辑（与 AttackEnemyBehavior 类似）
            $speedFactor = (float) ($this->speed * $this->speedMultiplier * 0.7 * ($this->entity->isInsideOfWater() ? 0.3 : 0.4));
            $level = $this->entity->getLevel();
            $coordinates = $this->entity->getPosition();
            $direction = $this->entity->getDirectionVector();
            $direction->y = 0;

            $blockDown = $level->getBlock($coordinates->add(0, -1, 0));
            if ($this->entity->getMotion()->y < 0 and $blockDown instanceof Air) {
                return;
            }
            if ($distance < 0.5) {
                return;
            }

            $coord = $coordinates->add($direction->multiply($speedFactor))->add($direction->multiply(0.5));
            $entity = $this->entity;
            $block = $level->getBlock($coord);
            $blockUp = $level->getBlock($coord->add(0, 1, 0));
            $blockUpUp = $level->getBlock($coord->add(0, 2, 0));

            $colliding = $block->isSolid() or ($entity->height >= 1 and $blockUp->isSolid());
            if (!$colliding) {
                $motion = $direction->multiply($speedFactor);
                $pm = $entity->getMotion();
                $pm->y = 0;
                if ($pm->length() < $motion->length()) {
                    $entity->setMotion($pm->add($motion->x - $pm->x, 0, $motion->z - $pm->z));
                } else {
                    $entity->setMotion($motion);
                }
            } else {
                if (!$blockUp->isSolid() and !($entity->height > 1 and $blockUpUp->isSolid())) {
                    $entity->motionY = 0.42;
                }
            }
        } elseif ($this->timeLeft == 0) {
            // 攻击冷却结束，进行攻击
            $damage = $this->entity->getHurt();
            $this->revengeTarget->attack($damage, new EntityDamageByEntityEvent($this->entity, $this->revengeTarget, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage));
            $this->timeLeft = mt_rand(30, 40);
        }

        if ($this->timeLeft > 0) {
            --$this->timeLeft;
        }
        $this->swimming();
    }

    public function onEnd() {
        $this->entity->setMotion(new Vector3(0, 0, 0));
        $this->revengeTarget = null;
    }

    /**
     * 瞄准目标（设置 pitch 和 yaw）
     */
    private function aimAtTarget($target) {
        $entity = $this->entity;
        $x = $target->x - $entity->x;
        $y = $target->y - $entity->y;
        $z = $target->z - $entity->z;

        $a = $target->x + 0.5;
        $c = $target->z + 0.5;
        $len = sqrt($x * $x + $y * $y + $z * $z);
        $y = $y / $len;
        $pitch = asin($y) * 180 / M_PI;
        $pitch = -$pitch;
        $yaw = -atan2($a - ($entity->x + 0.5), $c - ($entity->z + 0.5)) * (180 / M_PI);
        $entity->pitch = $pitch;
        $entity->yaw = $yaw;
    }

    public function swimming() {
        parent::swimming(); // 继承基类的游泳逻辑
    }
}