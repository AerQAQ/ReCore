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

class findFoodBehavior extends Behavior{

    public $speed;
    public $speedMultiplier;
	
	public $lookDistance = 6.0;
	public $foodID;
	public $player = null;
	public $timeLeft;

    public function __construct(Mob $entity, int $foodID, float $speed = 0.25, float $speedMultiplier = 0.75){
        parent::__construct($entity);

        $this->speed = $speed;
        $this->speedMultiplier = $speedMultiplier;
		$this->foodID = $foodID;
    }

    public function getName() : string{
        return "发现食物";
    }

    public function shouldStart() : bool{
        $players = $this->entity->level->getPlayers();

        $find = false;
		foreach($players as $p){
			if($p->isConnected() and $p->isAlive()){
				if($p->getItemInHand()->getID() == $this->foodID){
					if($this->entity->distance($p) < $this->lookDistance){
						$this->player = $p;
						$find = true;
						break;
					}
				}
			}
        }
		return $find;
		
    }

    public function canContinue() : bool{
		if($this->player->isConnected() and $this->player->isAlive()){
			return $this->player->getItemInHand()->getID() == $this->foodID;
		}else{
			return false;
		}
        
    }

    public function onTick(){
		$this->AimPlayer($this->player, $this->entity);
        $speedFactor = (float) ($this->speed*$this->speedMultiplier*0.7*($this->entity->isInsideOfWater() ? 0.3 : 0.4)); // 0.7 is a general mob base factor
		$level = $this->entity->getLevel();
		$coordinates = $this->entity->getPosition();
		$direction = $this->entity->getDirectionVector();
		$direction->y = 0;
		$entity = $this->entity;

		$blockDown = $level->getBlock($coordinates->add(0,-1,0));
		if ($entity->getMotion()->y < 0 and $blockDown instanceof Air)
		{
			return;
		}
		if($this->entity->distance($this->player) < 0.5){
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
}
