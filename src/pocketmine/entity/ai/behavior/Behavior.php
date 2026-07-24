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

namespace pocketmine\entity\ai\behavior;

use pocketmine\entity\Mob;

abstract class Behavior{

    /** @var Mob */
    public $entity;
	public $swimmingTick;

    public function __construct(Mob $entity){
        $this->entity = $entity;
    }

    public abstract function getName() : string;

    public abstract function shouldStart() : bool;

    public abstract function onTick();

    public abstract function onEnd();

    public abstract function canContinue() : bool;
	
	public function swimming(){
		if($this->entity->isInsideOfWater()){ //实体游泳
			$airTicks = $this->entity->getDataProperty(1); //DATA_AIR
			if($this->swimmingTick <= 0){
				if($airTicks <= 175){
					$this->entity->motionY = 0.3;
					$this->swimmingTick = 0;
				}else{
					$this->entity->motionY = 0.8;
					$this->swimmingTick = 10;
				}
			}else{
				--$this->swimmingTick;
			}
		}
	}
}