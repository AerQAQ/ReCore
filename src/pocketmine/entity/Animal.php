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

namespace pocketmine\entity;

use pocketmine\item\Item;
use pocketmine\nbt\tag\ByteTag;

abstract class Animal extends Mob implements Ageable{

    /** @var array 动物类名 => 繁殖食物物品ID列表 */
    protected static $breedFoodMap = [
        'Cow'        => [Item::WHEAT],
        'Mooshroom'  => [Item::WHEAT],
        'Sheep'      => [Item::WHEAT],
        'Pig'        => [Item::CARROT, Item::POTATO, Item::BEETROOT],
        'Chicken'    => [Item::SEEDS, Item::PUMPKIN_SEEDS, Item::MELON_SEEDS, Item::BEETROOT_SEEDS],
        'Wolf'       => [Item::BONE, Item::ROTTEN_FLESH],
        'Ocelot'     => [Item::RAW_FISH, Item::RAW_SALMON],
        'Rabbit'     => [Item::CARROT, Item::GOLDEN_CARROT, Item::DANDELION],
        'Llama'      => [Item::WHEAT],
    ];

	public function initEntity(){
		parent::initEntity();
		if(!isset($this->namedtag->IsBaby)){
			$this->namedtag->IsBaby = new ByteTag("IsBaby", 1);
			$this->setBaby(false);
		}
	}

	public function isBaby(){
		return $this->namedtag["IsBaby"] == 0 ? false : true;
	}
	
	public function setBaby(bool $resting){
		$this->setDataProperty(self::DATA_IS_BABY, self::DATA_TYPE_BYTE, $resting ? 1 : 0);
		$this->namedtag->IsBaby = new ByteTag("IsBaby", $resting ? 1 : 0);
	}
	
	public function isInLove(){
		return $this->getDataProperty(self::DATA_IN_LOVE) === 1;
	}
	
	public function setInLove(bool $resting){
		$this->setDataProperty(self::DATA_IN_LOVE, self::DATA_TYPE_BYTE, $resting ? 1 : 0);
	}

    /**
     * 检查物品是否是该动物的繁殖食物
     *
     * @param Item $item
     * @return bool
     */
    public function isBreedingItem(Item $item): bool{
        $className = (new \ReflectionClass($this))->getShortName();
        $allowed = self::$breedFoodMap[$className] ?? null;
        if ($allowed === null) {
            return false;
        }
        foreach ($allowed as $foodId) {
            if ($item->getId() === $foodId) {
                return true;
            }
        }
        return false;
    }
}