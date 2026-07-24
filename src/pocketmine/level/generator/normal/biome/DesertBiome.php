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

use pocketmine\level\generator\normal\populator\Temple;
use pocketmine\level\generator\normal\populator\Well;

class DesertBiome extends SandyBiome{

	public function __construct(){
		parent::__construct();
		$this->setElevation(63, 74);
		
        $temple = new Temple();
        $well = new Well();

        
        $this->addPopulator($temple);
		$this->addPopulator($well);

		$this->temperature = 2;
		$this->rainfall = 0;
	}

	public function getName() : string{
		return "Desert";
	}
}