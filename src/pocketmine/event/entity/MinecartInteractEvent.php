<?php

/*
* __________ _________  
* \______   \\_   ___ \ 
*  |       _//    \  \/ 
*  |    |   \\     \____
*  |____|_  / \______  /
*         \/         \/ 
*/

namespace pocketmine\event\entity;

use pocketmine\entity\Entity;
use pocketmine\Player;
use pocketmine\Event;
use pocketmine\event\Cancellable;

class MinecartInteractEvent extends EntityEvent implements Cancellable{
	public static $handlerList = null;

	public function __construct(Entity $entity, Player $player){
		$this->entity = $entity;
		$this->player = $player;
	}
}