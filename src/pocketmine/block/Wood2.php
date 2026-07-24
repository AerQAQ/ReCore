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


class Wood2 extends Wood{

	const ACACIA = 0;
	const DARK_OAK = 1;

	protected $id = self::WOOD2;

	public function getName() : string{
		static $names = [
			0 => "Acacia Wood",
			1 => "Dark Oak Wood",
			2 => "Unkonwn Wood",
			3 => "Unkonwn Wood",
			4 => "Unkonwn Wood",
			5 => "Unkonwn Wood",
			6 => "Unkonwn Wood",
			7 => "Unkonwn Wood",
			8 => "Unkonwn Wood",
			9 => "Unkonwn Wood",
			10 => "Unkonwn Wood",
			11 => "Unkonwn Wood",
			12 => "Unkonwn Wood",
			13 => "Unkonwn Wood",
			14 => "Unkonwn Wood",
			15 => "Unkonwn Wood"
		];
		return $names[$this->meta & 0x0f];
	}
}
