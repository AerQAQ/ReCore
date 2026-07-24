<?php

/*
 *
          _____   _____    ______  __   __  ______
        /  ___/  /  ___|  / ___  \ \ \ / / |  ____|
        | |___  | |      | |___| |  \ / /  | |____
        \___  \ | |      |  ___  |   / /   |  ____|
         ___| | | |____  | |   | |  / / \  | |____
        /_____/  \_____| |_|   |_| /_/ \_\ |______|
      生存斧服务器Minecraft PE 0.14.x核心.
 *
*/

namespace pocketmine\entity\ai\behavior;

use pocketmine\entity\Mob;
use pocketmine\math\Vector3;
use pocketmine\block\Air;

class SlimeBehavior extends Behavior{

    public $duration;
    public $timeLeft;
    public $speed;
    public $speedMultiplier;

    public function __construct(Mob $entity, int $duration = 80, float $speed = 0.5, float $speedMultiplier = 0.75){
        parent::__construct($entity);

        $this->duration = $duration;
        $this->speed = $speed;
        $this->speedMultiplier = $speedMultiplier;
        $this->timeLeft = $duration;
    }

    public function getName() : string{
        return "史莱姆行走";
    }

    public function shouldStart() : bool{
        return true;
    }

    public function canContinue() : bool{
        return $this->timeLeft-- > 0;
    }

    public function onTick(){
        $speedFactor = (float) ($this->speed*$this->speedMultiplier*0.7*($this->entity->isInsideOfWater() ? 0.3 : 0.4)); // 0.7 is a general mob base factor
		$level = $this->entity->getLevel();
		$coordinates = $this->entity->getPosition();
		$direction = $this->entity->getDirectionVector();
		$direction->y = 0;
		$entity = $this->entity;

		$blockDown = $level->getBlock($coordinates->add(0,-1,0));
		if ($entity->getMotion()->y < 0 and $blockDown instanceof Air)
		{
			//$this->timeLeft = 0;
			$entity->motionY -= 0.1;
			return;
		}

	    $coord = ($coordinates->add($direction->multiply($speedFactor))->add($direction->multiply(0.5)));

		$players = $entity->getViewers();

		$block = $level->getBlock($coord);
		$blockUp = $level->getBlock($coord->add(0,1,0));
		$blockUpUp = $level->getBlock($coord->add(0,2,0));

		$colliding = ($blockUpUp->isSolid() and $blockUp->isSolid());
		if (!$colliding){
			$motion = $direction->multiply($speedFactor);
			$pm = $entity->getMotion();
			if ($pm->length() < $motion->length()){
				$entity->setMotion($pm->add($motion->x - $pm->x, 0.45 * $entity->getSize(), $motion->z - $pm->z));
			}else{
				$entity->setMotion($motion);
			}
		}
		else
		{
			$entity->yaw += 180;
		}
		
		if($entity->isInsideOfWater()){ //实体游泳
			$entity->motionY = 0.5;
		}
    }

    public function onEnd(){
        $this->timeLeft = $this->duration;
        $this->entity->setMotion(new Vector3(0,0,0));
    }
}