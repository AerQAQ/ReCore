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

namespace pocketmine\level\generator\object;

use pocketmine\block\Block;
use pocketmine\block\Wood;
use pocketmine\level\ChunkManager;
use pocketmine\utils\Random;
use pocketmine\math\Facing;

class BigJungleTree extends Tree{

	public function __construct(){
		$this->trunkBlock = Block::LOG;
		$this->leafBlock = Block::LEAVES;
		$this->type = Wood::JUNGLE;
	}
	public function canPlaceOn(Block $soil) : bool{
		$id = $soil->getId();
		return $id === Block::GRASS || $id === Block::DIRT;
	}

	public function canPlace(int $base_x, int $base_y, int $base_z, ChunkManager $world) : bool{
		for($y = $base_y; $y <= $base_y + 1 + $this->treeHeight; ++$y){
			// Space requirement
			$radius = 2; // default radius if above first block
			if($y === $base_y){
				$radius = 1; // radius at source block y is 1 (only trunk)
			}elseif($y >= $base_y + 1 + $this->treeHeight - 2){
				$radius = 2; // max radius starting at leaves bottom
			}
			// check for block collision on horizontal slices
			for($x = $base_x - $radius; $x <= $base_x + $radius; ++$x){
				for($z = $base_z - $radius; $z <= $base_z + $radius; ++$z){
					if($y >= 0 && $y < World::Y_MAX){
						// we can overlap some blocks around
						if(!array_key_exists($world->getBlockAt($x, $y, $z)->getId(), $this->overridables)){
							return false;
						}
					}else{ // height out of range
						return false;
					}
				}
			}
		}
		return true;
	}

	public function placeObject(ChunkManager $world, $source_x, $source_y, $source_z, Random $random){
		$this->treeHeight = $random->nextBoundedInt(20) + $random->nextBoundedInt(3) + 10;
		
		// generates the canopy leaves
		for($y = -2; $y <= 0; ++$y){
			$this->generateLeaves($source_x + 0, $source_y + $this->treeHeight + $y, $source_z, 3 - $y, false, $world);
		}

		// generates the branches
		$branch_height = $this->treeHeight - 2 - $random->nextBoundedInt(4);
		$height_half = intdiv($this->treeHeight, 2);
		while($branch_height > $height_half){ // branching start at least at middle height
			$x = 0;
			$z = 0;
			// generates a branch
			$d = $random->nextFloat() * M_PI * 2.0; // random direction
			for($i = 0; $i < 5; ++$i){
				// branches are always longer when facing south or east (positive X or positive Z)
				$x = (int) (cos($d) * $i + 1.5);
				$z = (int) (sin($d) * $i + 1.5);
				$world->setBlockIdAt($source_x + $x, $source_y + $branch_height - 3 + intdiv($i, 2), $source_z + $z, $this->trunkBlock);
				$world->setBlockDataAt($source_x + $x, $source_y + $branch_height - 3 + intdiv($i, 2), $source_z + $z, $this->type);
			}
			// generates leaves for this branch
			for($y = $branch_height - ($random->nextBoundedInt(2) + 1); $y <= $branch_height; ++$y){
				$this->generateLeaves($source_x + $x, $source_y + $y, $source_z + $z, 1 - ($y - $branch_height), true, $world);
			}
			$branch_height -= $random->nextBoundedInt(4) + 2;
		}

		// generates the trunk
		$this->generateTrunk($world, $source_x, $source_y, $source_z);

		// 生成可可豆
		$this->generateCocoaPods($world, $source_x, $source_y, $source_z, $random);

		// add some vines on the trunk
		$this->addVinesOnTrunk($world, $source_x, $source_y, $source_z, $random);

		// blocks below trunk are always dirt
		$this->generateDirtBelowTrunk($source_x, $source_y, $source_z, $world);
		return true;
	}

	/**
	 * 在树干上生成可可豆
	 */
	private function generateCocoaPods(ChunkManager $world, int $block_x, int $block_y, int $block_z, Random $random) : void {
		// 在树干的不同高度随机生成可可豆
		for($y = 1; $y < $this->treeHeight - 2; $y += $random->nextBoundedInt(3) + 2) {
			// 检查树干周围的四个方向是否可以放置可可豆
			$directions = [
				[1, 0, 0, 1],  // 东方向，meta=1 (可可豆朝西)
				[-1, 0, 0, 3], // 西方向，meta=3 (可可豆朝东)
				[0, 0, 1, 2],  // 南方向，meta=2 (可可豆朝北)
				[0, 0, -1, 0]  // 北方向，meta=0 (可可豆朝南)
			];
			
			foreach($directions as $dir) {
				// 降低生成概率：从1/5改为1/8
				if($random->nextBoundedInt(8) === 0) {
					$this->tryPlaceCocoaPod($world, $block_x, $block_y + $y, $block_z, $dir[0], $dir[1], $dir[2], $dir[3], $random);
				}
			}
		}
	}

	/**
	 * 尝试在指定位置放置可可豆
	 */
	private function tryPlaceCocoaPod(ChunkManager $world, int $trunk_x, int $trunk_y, int $trunk_z, int $offset_x, int $offset_y, int $offset_z, int $direction, Random $random) : void {
		// 可可豆的位置（树干旁边的空气位置）
		$cocoa_x = $trunk_x + $offset_x;
		$cocoa_y = $trunk_y + $offset_y;
		$cocoa_z = $trunk_z + $offset_z;
		
		// 检查可可豆位置是否是空气
		if($world->getBlockIdAt($cocoa_x, $cocoa_y, $cocoa_z) !== Block::AIR) {
			return;
		}
		
		// 检查可可豆依附的位置（树干）是否是丛林木
		$trunk_block = $world->getBlockIdAt($trunk_x, $trunk_y, $trunk_z);
		$trunk_meta = $world->getBlockDataAt($trunk_x, $trunk_y, $trunk_z);
		
		// 检查是否是丛林木（木头ID，且meta为3表示丛林木）
		if($trunk_block !== Block::WOOD || $trunk_meta !== 3) {
			return;
		}
		
		// 随机决定可可豆的生长阶段（0-2阶段）
		$growth_stage = $random->nextBoundedInt(3); // 0, 1, 2
		$cocoa_meta = $direction + ($growth_stage << 2); // 将生长阶段放到高2位
		
		// 放置可可豆方块
		$world->setBlockIdAt($cocoa_x, $cocoa_y, $cocoa_z, Block::COCOA_BLOCK);
		$world->setBlockDataAt($cocoa_x, $cocoa_y, $cocoa_z, $cocoa_meta);
	}

	protected function generateLeaves(int $source_x, int $source_y, int $source_z, int $radius, bool $odd, ChunkManager $world) : void{
		$n = 1;
		if($odd){
			$n = 0;
		}
		for($x = $source_x - $radius; $x <= $source_x + $radius + $n; ++$x){
			$radius_x = $x - $source_x;
			for($z = $source_z - $radius; $z <= $source_z + $radius + $n; ++$z){
				$radius_z = $z - $source_z;

				$sq_x = $radius_x * $radius_x;
				$sq_z = $radius_z * $radius_z;
				$sq_r = $radius * $radius;
				$sq_xb = ($radius_x - $n) * ($radius_x - $n);
				$sq_zb = ($radius_z - $n) * ($radius_z - $n);

				if($sq_x + $sq_z <= $sq_r || $sq_xb + $sq_zb <= $sq_r || $sq_x + $sq_zb <= $sq_r || $sq_xb + $sq_z <= $sq_r){
					$this->replaceIfAirOrLeaves($x, $source_y, $z, $this->leafBlock, $world);
				}
			}
		}
	}
	
	protected function replaceIfAirOrLeaves(int $x, int $y, int $z,  $new_material, ChunkManager $world) : void{
		$old_material = $world->getBlockIdAt($x, $y, $z);
		if($old_material === Block::AIR || $old_material === Block::LEAVES){
			$world->setBlockIdAt($x, $y, $z, $new_material);
			$world->setBlockDataAt($x, $y, $z, $this->type);
		}
	}

	protected function generateTrunk(ChunkManager $world, int $block_x, int $block_y, int $block_z) : void{
		// SELF, SOUTH, EAST, SOUTH EAST
		for($y = 0; $y < $this->treeHeight + -1; ++$y){
			$type = $world->getBlockIdAt($block_x + 0, $block_y + $y, $block_z + 0);
			if($type === Block::AIR || $type === Block::LEAVES){
				$world->setBlockIdAt($block_x + 0, $block_y + $y, $block_z, $this->trunkBlock);
				$world->setBlockDataAt($block_x + 0, $block_y + $y, $block_z, $this->type);
			}
			$type = $world->getBlockIdAt($block_x + 0, $block_y + $y, $block_z + 1);
			if($type === Block::AIR || $type === Block::LEAVES){
				$world->setBlockIdAt($block_x + 0, $block_y + $y, $block_z + 1, $this->trunkBlock);
				$world->setBlockDataAt($block_x + 0, $block_y + $y, $block_z + 1, $this->type);
			}
			$type = $world->getBlockIdAt($block_x + 1, $block_y + $y, $block_z + 0);
			if($type === Block::AIR || $type === Block::LEAVES){
				$world->setBlockIdAt($block_x + 1, $block_y + $y, $block_z, $this->trunkBlock);
				$world->setBlockDataAt($block_x + 1, $block_y + $y, $block_z, $this->type);
			}
			$type = $world->getBlockIdAt($block_x + 1, $block_y + $y, $block_z + 1);
			if($type === Block::AIR || $type === Block::LEAVES){
				$world->setBlockIdAt($block_x + 1, $block_y + $y, $block_z + 1, $this->trunkBlock);
				$world->setBlockDataAt($block_x + 1, $block_y + $y, $block_z + 1, $this->type);
			}
		}
	}

	protected function generateDirtBelowTrunk(int $block_x, int $block_y, int $block_z, ChunkManager $world) : void{
		// SELF, SOUTH, EAST, SOUTH EAST
		$dirt = Block::DIRT;
		$world->setBlockIdAt($block_x + 0, $block_y + -1, $block_z, $dirt);
		$world->setBlockIdAt($block_x + 0, $block_y + -1, $block_z + 1, $dirt);
		$world->setBlockIdAt($block_x + 1, $block_y + -1, $block_z, $dirt);
		$world->setBlockIdAt($block_x + 1, $block_y + -1, $block_z + 1, $dirt);
	}

	private function addVinesOnTrunk(ChunkManager $world, int $block_x, int $block_y, int $block_z, Random $random) : void{
		for($y = 1; $y < $this->treeHeight; ++$y){
			$this->maybePlaceVine($world, $block_x + -1, $block_y + $y, $block_z + 0, Facing::WEST, $random);
			$this->maybePlaceVine($world, $block_x + 0, $block_y + $y, $block_z + -1, Facing::NORTH, $random);
			$this->maybePlaceVine($world, $block_x + 2, $block_y + $y, $block_z + 0, Facing::EAST, $random);
			$this->maybePlaceVine($world, $block_x + 1, $block_y + $y, $block_z + -1, Facing::NORTH, $random);
			$this->maybePlaceVine($world, $block_x + 2, $block_y + $y, $block_z + 1, Facing::EAST, $random);
			$this->maybePlaceVine($world, $block_x + 1, $block_y + $y, $block_z + 2, Facing::SOUTH, $random);
			$this->maybePlaceVine($world, $block_x + -1, $block_y + $y, $block_z + 1, Facing::WEST, $random);
			$this->maybePlaceVine($world, $block_x + 0, $block_y + $y, $block_z + 2, Facing::SOUTH, $random);
		}
	}

	private function maybePlaceVine(ChunkManager $world, int $absolute_x, int $absolute_y, int $absolute_z, int $face_direction, Random $random) : void{
		
			$faces = [
				0 => 0,
				1 => 0,
				2 => 1,
				3 => 4,
				4 => 8,
				5 => 2,
			];
			
		if(
			$random->nextBoundedInt(3) !== 0 &&
			$world->getBlockIdAt($absolute_x, $absolute_y, $absolute_z) === Block::AIR
		){
			$world->setBlockIdAt($absolute_x, $absolute_y, $absolute_z, Block::VINE);
			$world->setBlockDataAt($absolute_x, $absolute_y, $absolute_z, $faces[$face_direction]);
		}
	}

}
