<?php

namespace pocketmine\network\protocol;

#include <rules/DataPacket.h>

class ChangeDimensionPacket extends DataPacket{
	const NETWORK_ID = Info::CHANGE_DIMENSION_PACKET;

	const DIMENSION_NORMAL = 0;
	const DIMENSION_NETHER = 1;
	const DIMENSION_END = 2;

	public $dimension;

	public function decode(){

	}

	public function encode(){
		$this->reset();
		$this->putByte($this->dimension);
		$this->putByte(0);
	}

}