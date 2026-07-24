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

namespace pocketmine\level\generator\populator;

use pocketmine\block\Block;
use pocketmine\block\Sapling;
use pocketmine\level\ChunkManager;
use pocketmine\level\generator\object\Tree as ObjectTree;
use pocketmine\utils\Random;

class AcaciaTree extends Populator{
	/** @var ChunkManager */
	private $level;
	private $randomAmount;
	private $baseAmount;

	private $type;

	public function __construct($type = Sapling::ACACIA){
		$this->type = $type;
	}

	public function setRandomAmount($amount){
		$this->randomAmount = $amount;
	}

	public function setBaseAmount($amount){
		$this->baseAmount = $amount;
	}

	public function populate(ChunkManager $level, $chunkX, $chunkZ, Random $random){
		$this->level = $level;
		$amount = $random->nextRange(0, $this->randomAmount + 1) + $this->baseAmount;
		for($i = 0; $i < $amount; ++$i){
			$x = $random->nextRange($chunkX << 4, ($chunkX << 4) + 15);
			$z = $random->nextRange($chunkZ << 4, ($chunkZ << 4) + 15);
			$y = $this->getHighestWorkableBlock($x, $z);
			if($y === -1){
				continue;
			}
			ObjectTree::growTree($this->level, $x, $y, $z, $random, $this->type);
		}
	}

	private function getHighestWorkableBlock($x, $z){
		for($y = 127; $y > 0; --$y){
			$b = $this->level->getBlockIdAt($x, $y, $z);
			if($b === Block::DIRT or $b === Block::GRASS or $b === Block::PODZOL){
				break;
			}elseif($b !== 0 and $b !== Block::SNOW_LAYER){
				return -1;
			}
		}

		return ++$y;
	}
}
