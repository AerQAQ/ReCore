<?php

namespace pocketmine\item;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;

class FilledMap extends Item{
	public function __construct($meta = 0, $count = 1){
		parent::__construct(self::FILLED_MAP, $meta, $count, "Filled Map");
	}
}

