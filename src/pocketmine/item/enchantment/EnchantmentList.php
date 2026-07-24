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

namespace pocketmine\item\enchantment;


class EnchantmentList{

	/** @var EnchantmentEntry[] */
	private $enchantments;

	public function __construct($size){
		$this->enchantments = new \SplFixedArray($size);
	}

	/**
	 * @param $slot
	 * @param EnchantmentEntry $entry
	 */
	public function setSlot($slot, EnchantmentEntry $entry){
		$this->enchantments[$slot] = $entry;
	}

	/**
	 * @param $slot
	 * @return EnchantmentEntry
	 */
	public function getSlot($slot){
		return $this->enchantments[$slot];
	}

	public function getSize(){
		return $this->enchantments->getSize();
	}

}