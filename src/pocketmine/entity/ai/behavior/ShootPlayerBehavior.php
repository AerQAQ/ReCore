<?php

/*
          _____   _____    ______  __   __  ______
        /  ___/  /  ___|  / ___  \ \ \ / / |  ____|
        | |___  | |      | |___| |  \ / /  | |____
        \___  \ | |      |  ___  |   / /   |  ____|
         ___| | | |____  | |   | |  / / \  | |____
        /_____/  \_____| |_|   |_| /_/ \_\ |______|
      生存斧服务器Minecraft PE 0.14.x核心.
*/

namespace pocketmine\entity\ai\behavior;

use pocketmine\entity\Mob;
use pocketmine\math\Vector3;
use pocketmine\block\Air;
use pocketmine\Player;

use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\LongTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\entity\ThrownPotion;
use pocketmine\entity\Arrow;
use pocketmine\entity\Fireball;

class ShootPlayerBehavior extends Behavior{

    public $speed;
    public $speedMultiplier;
	
	public $lookDistance = 16.0;
	public $NetworkID;
	public $player = null;
	public $timeLeft = 0;

    public function __construct(Mob $entity, int $NetWorkID, float $speed = 0.25, float $speedMultiplier = 0.75){
        parent::__construct($entity);

        $this->speed = $speed;
        $this->speedMultiplier = $speedMultiplier;
		$this->NetworkID = $NetWorkID;
    }

    public function getName() : string{
        return "投掷类敌对实体攻击";
    }

    public function shouldStart() : bool{
        $players = $this->entity->level->getPlayers();

        $find = false;
		$MinDistance = 9999;
		foreach($players as $p){
			if($this->entity->distance($p) < $this->lookDistance){
				if($this->entity->distance($p) < $MinDistance){
					if($p->isSurvival()){
						$this->player = $p;
						$MinDistance = $this->entity->distance($p);
						$find = true;
					}
				}
			}
        }
		return $find;
		
    }

    public function canContinue() : bool{
		if($this->player->isConnected() and $this->player->isAlive()){
			return $this->entity->distance($this->player) < $this->lookDistance;
		}else{
			return false;
		}
        
    }

    public function onTick(){
		$distance = $this->entity->distance($this->player);
		$this->AimPlayer($this->player, $this->entity);
		$entity = $this->entity;
		if($this->timeLeft >= 5){
			$speedFactor = (float) ($this->speed*$this->speedMultiplier*0.7*($this->entity->isInsideOfWater() ? 0.3 : 0.4)); // 0.7 is a general mob base factor
			$level = $this->entity->getLevel();
			$coordinates = $this->entity->getPosition();
			$direction = $this->entity->getDirectionVector();
			$direction->y = 0;

			$blockDown = $level->getBlock($coordinates->add(0,-1,0));
			if ($entity->getMotion()->y < 0 and $blockDown instanceof Air)
			{
				return;
			}
			if($distance < 0.5){
				return;
			}

			$coord = ($coordinates->add($direction->multiply($speedFactor))->add($direction->multiply(0.5)));

			$players = $entity->getViewers();

			$block = $level->getBlock($coord);
			$blockUp = $level->getBlock($coord->add(0,1,0));
			$blockUpUp = $level->getBlock($coord->add(0,2,0));

			$colliding = $block->isSolid() or ($entity->height >= 1 and $blockUp->isSolid());
			if (!$colliding){
				$motion = $direction->multiply($speedFactor);
				$pm = $entity->getMotion();
				$pm->y = 0;
				if($distance < 4){
					$pm->x = -$pm->x;
					$pm->z = -$pm->z;
					
					$motion->x = -$motion->x;
					$motion->z = -$motion->z;
				}
				if ($pm->length() < $motion->length()){
					$entity->setMotion($pm->add($motion->x - $pm->x, 0, $motion->z - $pm->z));
				}else{
					$entity->setMotion($motion);
				}
			}
			else
			{
				if (!$blockUp->isSolid() and !($entity->height > 1 and $blockUpUp->isSolid())){
					$entity->motionY = 0.42;
				}
			}
			if($this->timeLeft > 0){
				--$this->timeLeft;
			}
		}elseif($distance <= 16){ // 火球攻击距离
			$this->bowAimPitch($this->player, $this->entity);
			$this->entity->level->addEntityMovement($this->entity->chunk->getX(), $this->entity->chunk->getZ(), $this->entity->getID(), $this->entity->x, $this->entity->y + $this->entity->getEyeHeight(), $this->entity->z, $this->entity->yaw, $this->entity->pitch, $this->entity->yaw);
			if($this->timeLeft <= 0){
				if($this->NetworkID == 85){ // 火球 Fireball
					// 检查是否可以发射火球
					if(method_exists($entity, 'canShootFireball') && $entity->canShootFireball()){
						$this->shootFireball($entity, $this->player);
						$this->timeLeft = 60; // 火球冷却时间（3秒）
						
						// 设置实体冷却
						if(method_exists($entity, 'setFireballCooldown')){
							$entity->setFireballCooldown(60);
						}
					}
				}elseif($this->NetworkID == 86){ //药水Potion
					if($distance >= 8){
						$Damage = 17; //缓慢 1分7秒
					}elseif($this->player->getHealth() >= 8){
						$Damage = 25; //中毒 33秒
					}elseif($distance <= 3){
						$Damage = 34; //虚弱 1分7秒
					}else{
						$Damage = 23; //瞬间伤害
					}
					
					$pitch = $entity->pitch;
					
					$nbt = new CompoundTag("", [
						"Pos" => new ListTag("Pos", [
							new DoubleTag("", $entity->x),
							new DoubleTag("", $entity->y + 1.62),
							new DoubleTag("", $entity->z)
						]),
						"Motion" => new ListTag("Motion", [
							new DoubleTag("", -sin($entity->yaw / 180 * M_PI) * cos($pitch / 180 * M_PI)),
							new DoubleTag("", -sin(($pitch) / 180 * M_PI)),
							new DoubleTag("", cos($entity->yaw / 180 * M_PI) * cos($pitch / 180 * M_PI))
						]),
						"Rotation" => new ListTag("Rotation", [
							new FloatTag("", $entity->yaw),
							new FloatTag("", $pitch)
						]),
						"PotionId" => new ShortTag("PotionId", $Damage),
					]);

					$f = 1.1;
					$thrownPotion = new ThrownPotion($entity->chunk, $nbt, $entity);
					$thrownPotion->setMotion($thrownPotion->getMotion()->multiply($f));
					$thrownPotion->spawnToAll();
					$this->timeLeft = 40; //每种药水以2秒的间隔投掷。
				}elseif($this->NetworkID == 80){ //Arrow
					$pitch = $this->bowAimPitch($this->player, $this->entity, 0.04);
					$nbt = new CompoundTag("", [
						"Pos" => new ListTag("Pos", [
							new DoubleTag("", $entity->x),
							new DoubleTag("", $entity->y + 1.62),
							new DoubleTag("", $entity->z)
						]),
						"Motion" => new ListTag("Motion", [
							new DoubleTag("", -sin($entity->yaw / 180 * M_PI) * cos($pitch / 180 * M_PI)),
							new DoubleTag("", -sin(($pitch) / 180 * M_PI)),
							new DoubleTag("", cos($entity->yaw / 180 * M_PI) * cos($pitch / 180 * M_PI))
						]),
						"Rotation" => new ListTag("Rotation", [
							new FloatTag("", $entity->yaw),
							new FloatTag("", $pitch)
						]),
						"Fire" => new ShortTag("Fire", $entity->isOnFire() ? 45 * 60 : 0)
					]);

					$f = 1.1;
					$Arrow = new Arrow($entity->chunk, $nbt, $entity);
					$Arrow->setMotion($Arrow->getMotion()->multiply($f));
					$Arrow->spawnToAll();
					$this->timeLeft = 40; //在简单和普通难度中每2秒发射一次，在困难难度中每1秒发射一次。
					
					//骷髅会主动逃离狼；狼会主动尝试攻击骷髅。
				}else{
					$this->timeLeft = 40;
				}
			}else{
				--$this->timeLeft;
			}
		}
		$this->swimming();
    }
    
    /**
     * 发射火球方法
     */
    private function shootFireball(Mob $shooter, Player $target){
        $level = $shooter->getLevel();
        $pos = $shooter->getPosition();
        
        // 计算火球方向
        $dx = $target->x - $pos->x;
        $dy = ($target->y + $target->getEyeHeight()) - ($pos->y + $shooter->getEyeHeight());
        $dz = $target->z - $pos->z;
        
        $distance = sqrt($dx * $dx + $dz * $dz);
        $yaw = atan2($dz, $dx) * 180 / M_PI - 90;
        $pitch = -atan2($dy, $distance) * 180 / M_PI;
        
        // 创建火球NBT数据
        $nbt = new CompoundTag("", [
            "Pos" => new ListTag("Pos", [
                new DoubleTag("", $pos->x),
                new DoubleTag("", $pos->y + $shooter->getEyeHeight()),
                new DoubleTag("", $pos->z)
            ]),
            "Motion" => new ListTag("Motion", [
                new DoubleTag("", -sin($yaw / 180 * M_PI) * cos($pitch / 180 * M_PI)),
                new DoubleTag("", -sin($pitch / 180 * M_PI)),
                new DoubleTag("", cos($yaw / 180 * M_PI) * cos($pitch / 180 * M_PI))
            ]),
            "Rotation" => new ListTag("Rotation", [
                new FloatTag("", $yaw),
                new FloatTag("", $pitch)
            ])
        ]);
        
        // 创建火球实体
        $fireball = new Fireball($level->getChunk($pos->x >> 4, $pos->z >> 4), $nbt, $shooter);
        $fireball->setMotion($fireball->getMotion()->multiply(1.5)); // 增加火球速度
        
        // 生成火球
        $fireball->spawnToAll();
    }
	
	public function AimPlayer($player, $entity){
		$x = $player->x - $entity->x;
		$y = $player->y - $entity->y;
		$z = $player->z - $entity->z;
		
		$a = $player->x + 0.5;
		$b = $player->y;
		$c = $player->z + 0.5;
		$len = sqrt($x * $x + $y * $y + $z * $z);
		$y = $len > 0 ? $y / $len : 0; // 防止除以0
		$pitch = asin($y);
		$pitch = $pitch * 180 / M_PI;
		$pitch = -$pitch;
		// 防止 atan2(0,0) 产生 NAN
		$dx = $a - ($entity->x + 0.5);
		$dz = $c - ($entity->z + 0.5);
		if(abs($dx) < 0.0001 && abs($dz) < 0.0001){
			$dz = 0.001; // 极小偏移，防止除零
		}
		$yaw = -atan2($dx, $dz) * (180 / M_PI);
		$entity->pitch = $pitch;
		$entity->yaw = $yaw;
		
	}

    public function onEnd(){
        $this->entity->setMotion(new Vector3(0,0,0));
    }
	
	public function bowAimPitch($player, $entity, $distance = 0.07){
		
		$gravity = 1.0;
		
		$x = $player->x - $entity->x;
		$y = $player->y - $entity->y;
		$z = $player->z - $entity->z;
		
		$horizDist = sqrt($x * $x + $z * $z);
		
		// 防止除零：水平距离太近时直接返回当前pitch
		if($horizDist < 0.0001){
			$entity->pitch = 0;
			return 0;
		}
		
		// 抛物线公式：y = x*tan(θ) - g*x²/(2v²*cos²θ)
		// 用数值方法求解射击角度
		$v = $distance;
		$g = $gravity;
		
		// 判别式，如果小于0则无法达到目标高度，直接瞄准
		$discriminant = $v * $v * $v * $v - $g * ($g * $horizDist * $horizDist + 2 * $y * $v * $v);
		
		if($discriminant < 0 || $v < 0.0001){
			// 无法计算抛物线，直接用直线瞄准
			$pitch = -atan2($y, $horizDist) * (180 / M_PI);
			$entity->pitch = $pitch;
			return $pitch;
		}
		
		$pitch = -(180 / M_PI) * atan(($v * $v - sqrt($discriminant)) / ($g * $horizDist));
		
		// 最终保险：如果结果还是 NAN，用备选方案
		if(is_nan($pitch) || is_infinite($pitch)){
			$pitch = -atan2($y, $horizDist) * (180 / M_PI);
		}
		
		$entity->pitch = $pitch;
		
		return $pitch;
	}
	
	public function swimming(){
        $entity = $this->entity;
        if($entity->isInsideOfWater()){
            $motion = $entity->getMotion();
            if($motion->y < 0.3){
                $motion->y += 0.3;
                $entity->setMotion($motion);
            }
        }
    }
}
