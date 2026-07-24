<?php

namespace pocketmine\level\generator\normal\biome;

use pocketmine\block\Sapling;
use pocketmine\level\generator\populator\TallGrass;
use pocketmine\level\generator\populator\Grass;
use pocketmine\level\generator\populator\Tree;

class PinkForestBiome extends GrassyBiome{

	const TYPE_NORMAL = 0;

	public $type;

	public function __construct($type = self::TYPE_NORMAL){
		parent::__construct();

		$this->type = $type;

		$trees = new Tree(Sapling::PINK);
		$trees->setBaseAmount(5);
		$this->addPopulator($trees);
		
		$Grass = new Grass();
		$Grass->setBaseAmount(30);
		
		$tallGrass = new TallGrass();
		$tallGrass->setBaseAmount(3);

		$this->addPopulator($tallGrass);
		$this->addPopulator($Grass);

		$this->setElevation(63, 81);

		$this->temperature = 0.5;
		$this->rainfall = 0.5;
	}

	public function getName() : string{
		return $this->type === "Pink Forest";
	}
}