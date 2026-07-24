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

namespace pocketmine\block;

use pocketmine\item\Item;
use pocketmine\item\Tool;

class WoodStairs extends Stair{

	protected $id = self::WOOD_STAIRS;

	public function __construct($meta = 0){
		$this->meta = $meta;
	}

	public function getName() : string{
		return "Wood Stairs";
	}

	public function getToolType(){
		return Tool::TYPE_AXE;
	}

	public function getDrops(Item $item) : array {
		return [
			[$this->id, 0, 1],
		];
	}

	public function getHardness() {
		return 2;
	}
}