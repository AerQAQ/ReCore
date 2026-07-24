<?php

namespace pocketmine\level\generator\normal\biome;

use pocketmine\block\Block;
use pocketmine\level\generator\populator\Mushroom;

class MushroomIsland extends NormalBiome{
	public function __construct(){
		$this->setGroundCover([
			Block::get(Block::MYCELIUM, 0),
			Block::get(Block::DIRT, 0),
			Block::get(Block::DIRT, 0),
			Block::get(Block::DIRT, 0),
		]);
		
		$Mushroom = new Mushroom();
		$Mushroom->setBaseAmount(1);
		$this->addPopulator($Mushroom);
		
		$this->setElevation(60, 70);
	}
	
	public function getName() : string{
		return "MushroomIsland";
	}
}