<?php

namespace pocketmine\item;

class Map extends Item{
	public function __construct($meta = 0, $count = 1){
		parent::__construct(self::MAP, $meta, $count, "Map");
	}

}

