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
use pocketmine\level\generator\populator\Tree;

class MountainsBiome extends GrassyBiome{

	public function __construct(){
		parent::__construct();

		$trees = new Tree();
		$trees->setBaseAmount(1);
		$this->addPopulator($trees);

		$Grass = new Grass();
		$Grass->setBaseAmount(30);
		
		$tallGrass = new TallGrass();
		$tallGrass->setBaseAmount(4);

		$this->addPopulator($tallGrass);
		$this->addPopulator($Grass);

		//TODO: add emerald

		$this->setElevation(63, 127);

		$this->temperature = 0.4;
		$this->rainfall = 0.5;
	}

	public function getName() : string{
		return "Mountains";
	}
}