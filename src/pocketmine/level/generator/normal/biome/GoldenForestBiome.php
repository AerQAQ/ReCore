<?php

namespace pocketmine\level\generator\normal\biome;

use pocketmine\block\Sapling;
use pocketmine\level\generator\populator\TallGrass;
use pocketmine\level\generator\populator\Grass;
use pocketmine\level\generator\populator\GoldenTree;
use pocketmine\level\generator\populator\Tree;

class GoldenForestBiome extends GrassyBiome{

	const TYPE_NORMAL = 0;

	public $type;

	public function __construct($type = self::TYPE_NORMAL){
		parent::__construct();

		$this->type = $type;

		$trees = new Tree(Sapling::GOLDEN);
		$trees->setBaseAmount(5);
		$this->addPopulator($trees);
		
		$Grass = new Grass();
		$Grass->setBaseAmount(50);
		
		$tallGrass = new TallGrass();
		$tallGrass->setBaseAmount(5);

		$this->addPopulator($tallGrass);
		$this->addPopulator($Grass);

		$this->setElevation(63, 81);

		$this->temperature = 1.0;
		$this->rainfall = 1.0;
	}

	public function getName() : string{
		return $this->type === "Golden Forest";
	}
}