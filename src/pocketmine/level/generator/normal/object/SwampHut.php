<?php

/*
 *  ______   _____    ______  __   __  ______
 * /  ___/  /  ___|  / ___  \ \ \ / / |  ____|
 * | |___  | |      | |___| |  \ / /  | |____
 * \___  \ | |      |  ___  |   / /   |  ____|
 *  ___| | | |____  | |   | |  / / \  | |____
 * /_____/  \_____| |_|   |_| /_/ \_\ |______|
 * 生存斧服务器Minecraft PE 0.14.x核心.
 * By Sunch233#3226 QQ2125696621 And KKK
 * @link https://github.com/ScaxeTeam/Scaxe/
 *
*/

namespace pocketmine\level\generator\normal\object;

use pocketmine\block\Block;
use pocketmine\block\Planks;
use pocketmine\level\ChunkManager;
use pocketmine\level\generator\object\PopulatorObject;
use pocketmine\math\Vector3;
use pocketmine\utils\BuildingUtils;
use pocketmine\utils\Random;

class SwampHut extends PopulatorObject{
	private $level;
	
    private $overridable = [
        Block::AIR => true,
        Block::SAPLING => true,
        Block::LOG => true,
        Block::LEAVES => true,
        Block::STONE => true,
        Block::DANDELION => true,
        Block::POPPY => true,
        Block::WATER => true,
		Block::STILL_WATER => true,
        Block::LOG2 => true,
        Block::LEAVES2 => true,
        Block::CACTUS => true
    ];
	
	public function canPlaceObject(ChunkManager $level, $x, $y, $z, Random $random){
        $this->level = $level;
        for ($xx = $x - 2; $xx <= $x + 2; $xx++)
            for ($yy = $y; $yy <= $y + 3; $yy++)
                for ($zz = $z - 2; $zz <= $z + 2; $zz++)
                    if (!isset($this->overridable[$level->getBlockIdAt($xx, $yy, $zz)]))
                        return false;
        return true;
	}
	
	public function placeObject(ChunkManager $level, $x, $y, $z, Random $random){
		$this->level = $level;
		$yOffset = mt_rand(1, 3);
		$firstPos = new Vector3($x, $y + $yOffset, $z);
		
		BuildingUtils::fill($level, $firstPos->add(-3, 0, -2), $firstPos->add(3, 0, 2), Block::get(Block::WOODEN_PLANK, Planks::SPRUCE)); //地板
		BuildingUtils::walls($level, $firstPos->add(-3, 1, -2), $firstPos->add(2, 3, 2), Block::get(Block::WOODEN_PLANK, Planks::SPRUCE)); //周围一圈墙
		
		BuildingUtils::fill($level, $firstPos->add(-3, 3, -2), $firstPos->add(-3, -$yOffset, -2), Block::get(Block::WOOD, Planks::SPRUCE)); //四个柱子
		BuildingUtils::fill($level, $firstPos->add(2, 3, 2), $firstPos->add(2, -$yOffset, 2), Block::get(Block::WOOD, Planks::SPRUCE));
		BuildingUtils::fill($level, $firstPos->add(2, 3, -2), $firstPos->add(2, -$yOffset, -2), Block::get(Block::WOOD, Planks::SPRUCE));
		BuildingUtils::fill($level, $firstPos->add(-3, 3, 2), $firstPos->add(-3, -$yOffset, 2), Block::get(Block::WOOD, Planks::SPRUCE));
		
		BuildingUtils::fill($level, $firstPos->add(-3, 3, -2), $firstPos->add(2, 3, 2), Block::get(Block::WOODEN_PLANK, Planks::SPRUCE)); //房顶
		
		$this->placeBlock($firstPos->x + 4, $firstPos->y, $firstPos->z + 1, Block::WOODEN_PLANK, Planks::SPRUCE); //小屋前端突出部
		$this->placeBlock($firstPos->x + 4, $firstPos->y, $firstPos->z, Block::WOODEN_PLANK, Planks::SPRUCE);
		$this->placeBlock($firstPos->x + 4, $firstPos->y, $firstPos->z - 1, Block::WOODEN_PLANK, Planks::SPRUCE);
		
		$this->placeBlock($firstPos->x + 3, $firstPos->y + 1, $firstPos->z + 2, Block::FENCE); //小屋前端栅栏
		$this->placeBlock($firstPos->x + 3, $firstPos->y + 1, $firstPos->z - 2, Block::FENCE);
		
		$this->placeBlock($firstPos->x + 2, $firstPos->y + 2, $firstPos->z + 1, Block::FENCE); //小屋前端窗户
		
		$this->placeBlock($firstPos->x + 2, $firstPos->y + 2, $firstPos->z - 1, Block::AIR); //门
		$this->placeBlock($firstPos->x + 2, $firstPos->y + 1, $firstPos->z - 1, Block::AIR);
		
		$this->placeBlock($firstPos->x - 3, $firstPos->y + 2, $firstPos->z, Block::FENCE); //小屋后端窗户
		
		$this->placeBlock($firstPos->x - 1, $firstPos->y + 2, $firstPos->z + 2, Block::FLOWER_POT_BLOCK); //小屋侧面窗户1
		$this->placeBlock($firstPos->x, $firstPos->y + 2, $firstPos->z + 2, Block::AIR);
		
		$this->placeBlock($firstPos->x - 1, $firstPos->y + 2, $firstPos->z - 2, Block::AIR); //小屋侧面窗户2
		$this->placeBlock($firstPos->x, $firstPos->y + 2, $firstPos->z - 2, Block::AIR);
		
		$this->placeBlock($firstPos->x - 2, $firstPos->y + 1, $firstPos->z, Block::CAULDRON_BLOCK); //内饰
		$this->placeBlock($firstPos->x - 2, $firstPos->y + 1, $firstPos->z - 1, Block::WORKBENCH);
		
		BuildingUtils::fill($level, $firstPos->add(-4, 3, -3), $firstPos->add(3, 3, -3), Block::get(Block::SPRUCE_WOOD_STAIRS, 2)); //四面屋檐
		BuildingUtils::fill($level, $firstPos->add(-4, 3, -3), $firstPos->add(-4, 3, 3), Block::get(Block::SPRUCE_WOOD_STAIRS, 0)); //后面
		BuildingUtils::fill($level, $firstPos->add(3, 3, -3), $firstPos->add(3, 3, 3), Block::get(Block::SPRUCE_WOOD_STAIRS, 1)); //前面
		BuildingUtils::fill($level, $firstPos->add(-4, 3, 3), $firstPos->add(3, 3, 3), Block::get(Block::SPRUCE_WOOD_STAIRS, 3)); 
	}
	
    public function placeBlock($x, $y, $z, $id = 0, $meta = 0){
        $this->level->setBlockIdAt($x, $y, $z, $id);
        $this->level->setBlockDataAt($x, $y, $z, $meta);
    }
}