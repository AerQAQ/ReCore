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
use pocketmine\block\Flower as FlowerBlock;
use pocketmine\level\generator\populator\Flower;
use pocketmine\level\generator\populator\Tree;
use pocketmine\level\generator\populator\LilyPad;
use pocketmine\level\generator\normal\populator\SwampHut;
use pocketmine\block\Sapling;

class SwampBiome extends GrassyBiome{

	public function __construct(){
		parent::__construct();

		$flower = new Flower();
		$flower->setBaseAmount(8);
		$flower->addType([Block::RED_FLOWER, FlowerBlock::TYPE_BLUE_ORCHID]);

		$this->addPopulator($flower);
		
		$Tree = new Tree(Sapling::OAK);
		$Tree->setBaseAmount(1);
		$this->addPopulator($Tree);
		
		$LilyPad = new LilyPad();
		$LilyPad->setBaseAmount(4);
		$this->addPopulator($LilyPad);
		
		$SwampHut = new SwampHut();
		$this->addPopulator($SwampHut);

		$this->setElevation(62, 63);

		$this->temperature = 0.8;
		$this->rainfall = 0.9;
	}

	public function getName() : string{
		return "Swamp";
	}

	public function getColor(){
		return 0x6a7039;
	}
}