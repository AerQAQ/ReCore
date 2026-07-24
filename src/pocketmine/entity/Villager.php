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

use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\level\format\FullChunk;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;
use pocketmine\entity\ai\behavior\{StrollBehavior, RandomLookaroundBehavior, LookAtPlayerBehavior, PanicBehavior};

class Villager extends Mob implements NPC, Ageable{
	const PROFESSION_FARMER = 0;
	const PROFESSION_LIBRARIAN = 1;
	const PROFESSION_PRIEST = 2;
	const PROFESSION_BLACKSMITH = 3;
	const PROFESSION_BUTCHER = 4;
	//const PROFESSION_GENERIC = 5;

	const NETWORK_ID = 15;

	public $width = 0.6;
	public $length = 0.6;
	//public $height = 0.8;

	public function getName() : string{
		return "Villager";
	}

	public function __construct(FullChunk $chunk, CompoundTag $nbt){
		parent::__construct($chunk, $nbt);
		
		$this->addBehavior(new PanicBehavior($this, 0.25, 2.0));
		$this->addBehavior(new StrollBehavior($this));
		$this->addBehavior(new LookAtPlayerBehavior($this));
		$this->addBehavior(new RandomLookaroundBehavior($this));

		$this->setDataProperty(self::DATA_PROFESSION_ID, self::DATA_TYPE_BYTE, $this->getProfession());
	}

	public function initEntity(){
		parent::initEntity();
		if(!isset($this->namedtag->Profession)){
			$this->setProfession(mt_rand(0, 4)); //随机生成村民职业
		}
		if(!isset($this->namedtag->IsBaby)){
			$this->namedtag->IsBaby = new ByteTag("IsBaby", 1);
			$this->setBaby(false);
		}
	}

	public function spawnTo(Player $player){
		$pk = new AddEntityPacket();
		$pk->eid = $this->getId();
		$pk->type = Villager::NETWORK_ID;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->speedX = $this->motionX;
		$pk->speedY = $this->motionY;
		$pk->speedZ = $this->motionZ;
		$pk->yaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$pk->metadata = $this->dataProperties;
		$player->dataPacket($pk);

		parent::spawnTo($player);
	}

	/**
	 * Sets the villager profession
	 *
	 * @param int $profession
	 */
	public function setProfession(int $profession){
		$this->namedtag->Profession = new ByteTag("Profession", $profession);
	}

	public function getProfession() : int{
		$pro = (int) $this->namedtag["Profession"];
		return min(4, max(0, $pro));
	}

	public function isBaby(){
		return $this->namedtag["IsBaby"] == 0 ? false : true;
	}
	
	public function setBaby(bool $resting){
		$this->setDataProperty(self::DATA_VILLAGER_IS_BABY, self::DATA_TYPE_BYTE, $resting ? 1 : 0);
		$this->namedtag->IsBaby = new ByteTag("IsBaby", $resting ? 1 : 0);
	}
}
