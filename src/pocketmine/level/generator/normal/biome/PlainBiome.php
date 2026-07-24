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
use pocketmine\level\generator\populator\LilyPad;
use pocketmine\level\generator\populator\WaterPit;
use pocketmine\block\Block;
use pocketmine\block\Flower as FlowerBlock;
use pocketmine\level\generator\populator\Flower;
use pocketmine\level\generator\populator\Sugarcane;
use pocketmine\level\generator\populator\TallSugarcane;

class PlainBiome extends GrassyBiome{

	public function __construct(){
		parent::__construct();

		$sugarcane = new Sugarcane();
		$sugarcane->setBaseAmount(6);
		$tallSugarcane = new TallSugarcane();
		$tallSugarcane->setBaseAmount(60);
		$Grass = new Grass();
		$Grass->setBaseAmount(40);
		$tallGrass = new TallGrass();
		$tallGrass->setBaseAmount(5);
		$waterPit = new WaterPit();
		$waterPit->setBaseAmount(9999);
		$lilyPad = new LilyPad();
		$lilyPad->setBaseAmount(8);

		$flower = new Flower();
		$flower->setBaseAmount(2);
		$flower->addType([Block::DANDELION, 0]);
		$flower->addType([Block::RED_FLOWER, FlowerBlock::TYPE_POPPY]);
		$flower->addType([Block::RED_FLOWER, FlowerBlock::TYPE_AZURE_BLUET]);
		$flower->addType([Block::RED_FLOWER, FlowerBlock::TYPE_RED_TULIP]);
		$flower->addType([Block::RED_FLOWER, FlowerBlock::TYPE_WHITE_TULIP]);
		$flower->addType([Block::RED_FLOWER, FlowerBlock::TYPE_OXEYE_DAISY]);
		

		$this->addPopulator($sugarcane);
		$this->addPopulator($tallSugarcane);
		$this->addPopulator($tallGrass);
		$this->addPopulator($Grass);
		$this->addPopulator($flower);
		$this->addPopulator($waterPit);
		$this->addPopulator($lilyPad);

		$this->setElevation(61, 68);

		$this->temperature = 0.8;
		$this->rainfall = 0.4;
	}

	public function getName() : string{
		return "Plains";
	}
}
