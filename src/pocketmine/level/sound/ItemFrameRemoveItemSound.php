<?php

namespace pocketmine\level\sound;

use pocketmine\math\Vector3;
use pocketmine\network\protocol\LevelEventPacket;

class ItemFrameRemoveItemSound extends GenericSound{
	public function __construct(Vector3 $pos, $pitch = 1){
		parent::__construct($pos, LevelEventPacket::EVENT_SOUND_ITEMFRAME_REMOVE_ITEM, $pitch);
	}
}
