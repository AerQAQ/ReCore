<?php

namespace pocketmine\level\generator\object;

use pocketmine\block\Block;
use pocketmine\block\Wood;
use pocketmine\level\ChunkManager;
use pocketmine\utils\Random;

class BrownMushroom extends Mushroom{

	public function __construct(){
		$this->type = 14;
		$this->type2 = 10;
		$this->trunkBlock = Block::BROWN_MUSHROOM_BLOCK;
		$this->leafBlock = Block::BROWN_MUSHROOM_BLOCK;
	}
	
	public function placeObject(ChunkManager $level, $x, $y, $z, Random $random){
		$this->treeHeight = $random->nextRange(0,2) + 5;
		
		$this->placeTrunk($level, $x, $y, $z, $random, $this->treeHeight - 1);
		$yyy = $this->treeHeight + $y;
		for($xx = $x - 3; $xx <= $x + 3; ++$xx){
			for($zz = $z - 3; $zz <= $z + 3; ++$zz){
				if((!Block::$solid[$level->getBlockIdAt($xx, $yyy, $zz)] || isset($this->overridable[$level->getBlockIdAt($xx, $yyy, $zz)])) && !(($xx == $x - 3 || $xx == $x + 3) && ($zz == $z - 3 || $zz == $z + 3))){
					$level->setBlockIdAt($xx, $yyy, $zz, $this->leafBlock);
					$level->setBlockDataAt($xx, $yyy, $zz, 14);
				}
			}
		}
	}
}