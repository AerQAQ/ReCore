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

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

class attackEnemyBehavior extends Behavior{

    public $speed;
    public $speedMultiplier;
	
	public $lookDistance = 16.0;
	public $NetworkID;
	public $enemy = null;
	public $timeLeft = 0;
	public $attackPlayer = false;

    public function __construct(Mob $entity, array $NetWorkID, bool $attackPlayer = false, float $speed = 0.25, float $speedMultiplier = 0.75){
        parent::__construct($entity);

        $this->speed = $speed;
        $this->speedMultiplier = $speedMultiplier;
		$this->NetworkID = $NetWorkID;
		$this->attackPlayer = $attackPlayer;
    }

    public function getName() : string{
        return "一般敌对实体攻击";
    }

    public function shouldStart() : bool{
        $entities = $this->entity->level->getEntities();

        $find = false;
		$MinDistance = 9999;
		foreach($entities as $entity){
			if(in_array($entity::NETWORK_ID, $this->NetworkID) and $this->entity->distance($entity) < $this->lookDistance){
				if($this->entity->distance($entity) < $MinDistance){
					$this->enemy = $entity;
					$MinDistance = $this->entity->distance($entity);
					$find = true;
				}
			}
        }
		if($this->attackPlayer){
			 $players = $this->entity->level->getPlayers();
			foreach($players as $p){
				if($this->entity->distance($p) < $this->lookDistance){
					if($this->entity->distance($p) < $MinDistance){
						if($p->isSurvival()){
							$this->enemy = $p;
							$MinDistance = $this->entity->distance($p);
							$find = true;
						}
					}
				}
			}
		}
		return $find;
		
    }

    public function canContinue() : bool{
		if($this->enemy->isAlive()){
			if(($this->enemy instanceof Player) and (!$this->enemy->isConnected())){
				return false;
			}
			return $this->entity->distance($this->enemy) < $this->lookDistance;
		}else{
			return false;
		}
        
    }

    public function onTick(){
		$distance = $this->entity->distance($this->enemy);
		$this->AimPlayer($this->enemy, $this->entity);
		$entity = $this->entity;
		if($distance >= 1.5){
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
		}elseif($this->timeLeft == 0){
			$damage = $entity->getHurt();
			$this->enemy->attack($damage, new EntityDamageByEntityEvent($entity, $this->enemy, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage));
			$this->timeLeft = mt_rand(30, 40);
		}
		
		if($this->timeLeft > 0){
			--$this->timeLeft;
		}
		$this->swimming();
    }
	
	public function AimPlayer($palyer, $entity){
		$x = $palyer->x - $entity->x;
		$y = $palyer->y - $entity->y;
		$z = $palyer->z - $entity->z;
		
		$a = $palyer->x + 0.5;
		$b = $palyer->y;
		$c = $palyer->z + 0.5;
		$len = sqrt($x * $x + $y * $y + $z * $z);
		$y = $y / $len;
		$pitch = asin($y);
		$pitch = $pitch * 180 / M_PI;
		$pitch = -$pitch;
		$yaw = -atan2($a - ($entity->x + 0.5), $c - ($entity->z + 0.5)) * (180 / M_PI);
		$entity->pitch = $pitch;
		$entity->yaw = $yaw;
		
	}

    public function onEnd(){
        $this->entity->setMotion(new Vector3(0,0,0));
    }
	
	public function bowAimPitch($palyer, $entity, $distance = 0.07){
		
		$_0x2bf6x17f = 1;
		
		$x = $palyer->x - $entity->x;
		$y = $palyer->y - $entity->y;
		$z = $palyer->z - $entity->z;
		
		$_0x2bf6x183 = sqrt($x * $x + $z * $z);
		$_0x2bf6x184 = $distance;
		$_0x2bf6x185 = ($_0x2bf6x17f * $_0x2bf6x17f * $_0x2bf6x17f * $_0x2bf6x17f - $_0x2bf6x184 * ($_0x2bf6x184 * ($_0x2bf6x183 * $_0x2bf6x183) + 2 * $y * ($_0x2bf6x17f * $_0x2bf6x17f)));
		$pitch = -(180 / M_PI) * (atan(($_0x2bf6x17f * $_0x2bf6x17f - sqrt($_0x2bf6x185)) / ($_0x2bf6x184 * $_0x2bf6x183)));
		if(is_nan($pitch)){
			$pitch = 0;
		}
		$entity->pitch = $pitch;
		
		return $pitch;
	}
	
	
}