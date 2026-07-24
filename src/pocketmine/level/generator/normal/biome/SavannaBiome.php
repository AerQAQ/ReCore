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

use pocketmine\level\generator\populator\TallGrass;
use pocketmine\level\generator\populator\Grass;
use pocketmine\level\generator\populator\AcaciaTree;
use pocketmine\block\Block;


class SavannaBiome extends GrassyBiome{

	public function __construct(){
		parent::__construct();
		$tree = new AcaciaTree();
		$tree->setBaseAmount(1);
		$Grass = new Grass();
		$Grass->setBaseAmount(20);
		$tallGrass = new TallGrass();
		$tallGrass->setBaseAmount(20);	
		$this->addPopulator($tree);
		$this->addPopulator($tallGrass);
		$this->addPopulator($Grass);

		$this->setElevation(62, 68);

		$this->temperature = 1.2;
		$this->rainfall = 0;
	}

	public function getName() : string{
		return "Savanna";
	}
	
	public function getColor(){
		return 0xbfb755;
	}
}
