<?php

/*
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

class LookAtPlayerBehavior extends Behavior{

    public $player;
    public $lookDistance = 0;

    public function getName() : string{
        return "看玩家";
    }

    public function __construct(Mob $entity, float $lookDistance = 6.0){
        parent::__construct($entity);

        $this->lookDistance = $lookDistance;
    }

    public function shouldStart() : bool{
        if(rand(0,10) != 0) return false;

        $players = $this->entity->level->getPlayers();

        foreach($players as $p){
            if($this->entity->distance($p) < $this->lookDistance){
                $this->player = $p;
                break;
            }
        }

        if($this->player == null) return false;

        $this->duration = 40 + mt_rand(0,40);

        return true;
    }

    public function canContinue() : bool{
		return $this->duration-- > 0;
    }

    public function onTick(){
		$this->AimPlayer($this->player, $this->entity);
		$this->swimming();
		$this->entity->level->addEntityMovement($this->entity->chunk->getX(), $this->entity->chunk->getZ(), $this->entity->getID(), $this->entity->x, $this->entity->y + $this->entity->getEyeHeight(), $this->entity->z, $this->entity->yaw, $this->entity->pitch, $this->entity->yaw);
		/*$dx = $this->player->x - $this->entity->x;
        $dz = $this->player->z - $this->entity->z;

        $tanOutput = 90 - $this->toDegree(atan($dx/$dz));
        $thetaOffset = 270;

        if($dz < 0){
            $thetaOffset = 90;
        }

        $dDiff = sqrt(($dx * $dx) + ($dz * $dz));
        $yaw = $thetaOffset + $tanOutput;
        $dy = ($this->entity->y + $this->entity->getEyeHeight()) - ($this->player->y + $this->player->getEyeHeight());
        $pitch = $this->toDegree(atan($dy/$dDiff));

        $this->entity->yaw = $yaw;
        $this->entity->pitch = $pitch;*/
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
		$this->entity->pitch = $pitch;
		$this->entity->yaw = $yaw;
		
	}

    public function onEnd(){
        $this->player = null;
        $this->entity->pitch = 0;
    }

    public function toDegree($angle){
        return $angle * (180 / pi());
    }
}