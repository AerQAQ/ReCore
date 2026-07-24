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

namespace pocketmine\level\generator\normal\biome;

use pocketmine\block\Sapling;
use pocketmine\level\generator\populator\Mushroom;
use pocketmine\level\generator\populator\Grass;
use pocketmine\level\generator\populator\Tree;

class DrakOakBiome extends GrassyBiome{ //ROOFED_FOREST

	public function __construct(){
		parent::__construct();
		
		$tree3 = new Tree(Sapling::DARK_OAK);
		$tree3->setBaseAmount(8);
		$this->addPopulator($tree3);
		
		$Mushroom = new Mushroom();
		$Mushroom->setBaseAmount(1);
		$this->addPopulator($Mushroom);
		
		$Grass = new Grass();
		$Grass->setBaseAmount(10);
		
		$this->addPopulator($Grass);

		$this->setElevation(63, 81);

		$this->temperature = 0.7;
		$this->temperature = 0.8;
		
	}

	public function getName() : string{
		return "ROOFED_FOREST";
	}
	
	public function getColor(){
		return 0x507A32;
	}
}