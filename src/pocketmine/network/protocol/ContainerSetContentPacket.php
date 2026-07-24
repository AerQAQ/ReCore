<?php

namespace pocketmine\network\protocol;

#include <rules/DataPacket.h>


class ContainerSetContentPacket extends DataPacket{
	const NETWORK_ID = Info::CONTAINER_SET_CONTENT_PACKET;

	const SPECIAL_INVENTORY = 0;
	const SPECIAL_ARMOR = 0x78;
	const SPECIAL_CREATIVE = 0x79;

	public $windowid;
	public $slots = [];
	public $hotbar = [];
	public $player = null;

	public function clean(){
		$this->slots = [];
		$this->hotbar = [];
		$this->player = null;
		return parent::clean();
	}

	public function decode(){
		$this->windowid = $this->getByte();
		$count = $this->getShort();
		for($s = 0; $s < $count and !$this->feof(); ++$s){
			$this->slots[$s] = $this->getSlot();
		}
		if($this->windowid === self::SPECIAL_INVENTORY){
			$count = $this->getShort();
			for($s = 0; $s < $count and !$this->feof(); ++$s){
				$this->hotbar[$s] = $this->getInt();
			}
		}
	}

	public function encode(){
		$this->reset();
		$this->putByte($this->windowid);
		
		// 🥵 老客户端：简化所有容器的内容
		if($this->player !== null && $this->player->isOldClient){
			// 简化物品列表：只保留基础物品，去掉NBT
			$simpleSlots = [];
			foreach($this->slots as $item){
				// 创建一个新的简单物品（不带NBT）
				$simpleItem = Item::get($item->getId(), $item->getDamage(), $item->getCount());
				$simpleSlots[] = $simpleItem;
				if(count($simpleSlots) >= 54){
					break;
				}
			}
			
			$this->putShort(count($simpleSlots));
			foreach($simpleSlots as $slot){
				$this->putSlot($slot);
			}
			// 老客户端不需要 hotbar 数据
			$this->putShort(0);
		}else{
			// 新客户端正常发送
			$this->putShort(count($this->slots));
			foreach($this->slots as $slot){
				$this->putSlot($slot);
			}
			if($this->windowid === self::SPECIAL_INVENTORY and count($this->hotbar) > 0){
				$this->putShort(count($this->hotbar));
				foreach($this->hotbar as $slot){
					$this->putInt($slot);
				}
			}else{
				$this->putShort(0);
			}
		}
	}

}