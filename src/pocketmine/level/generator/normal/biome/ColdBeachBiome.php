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

use pocketmine\block\Block;
use pocketmine\level\generator\populator\Cactus;
use pocketmine\level\generator\populator\DeadBush;

class ColdBeachBiome extends SandyBiome {

	/**
	 * BeachBiome constructor.
	 */
	public function __construct(){
		parent::__construct();

		$this->removePopulator(Cactus::class);
		$this->removePopulator(DeadBush::class);
		$this->setGroundCover([
			Block::get(Block::SNOW_LAYER, 0),
			Block::get(Block::SAND, 0),
			Block::get(Block::SAND, 0),
			Block::get(Block::SAND, 0),
			Block::get(Block::SAND, 0),
		]);
		$this->setElevation(62, 65);
	}

	/**
	 * @return string
	 */
	public function getName() : string{
		return "ColdBeach";
	}
} 