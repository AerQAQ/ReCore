<?php

namespace pocketmine\inventory;

use pocketmine\level\Position;
use pocketmine\Player;

class AnvilInventory extends ContainerInventory{
	
	const TARGET = 0;
	const SACRIFICE = 1;
	const RESULT = 2;
	private $Customitem;
	
	public function __construct(Position $pos){
		parent::__construct(new FakeBlockMenu($this, $pos), InventoryType::get(InventoryType::ANVIL));
	}

	/**
	 * @return FakeBlockMenu
	 */
	public function getHolder(){
		return $this->holder;
	}
	
	
	public function getResultSlotIndex(){
		return self::RESULT;
	}
	
	public function finishRename(Player $player, $type){
		if(!isset($this->Customitem)){
			return;
		}
		if($this->Customitem->hasCustomName()){
			if($this->have_emoji($this->Customitem->getCustomName())){
				$item = $this->Customitem->setCustomName("§c不允许emoji");
			}
		}
		$level = $player->getXpLevel();
		$count = 1;
		if($level < $count){
			return false;
		}
		$le = $level - $count;
		$player->setExpLevel($le);
		$this->clearAll();
		$player->getInventory()->addItem($this->Customitem);
		$this->Customitem = null;
	}
	
	function have_emoji($str){
		$mat = [];
		preg_match_all('/./u', $str,$mat);
		foreach ($mat[0] as $v){
			if(strlen($v) > 3){
				return true;
			}
		}
		return false;
	}
	
	public function onRename(Player $player, $slot, $sourceItem, $resultItem){
		if(!isset($resultItem)){
			return;
		}
		if(!$resultItem->deepEquals($this->getItem(self::TARGET), true, false, true)){
			//Item does not match target item. Everything must match except the tags.
			return false;
		}
		//$this->clearAll();
		$this->Customitem = $resultItem;
		return true;
		
	}
	
	public function processSlotChange(Transaction $transaction): bool{
		if($transaction->getSlot() === $this->getResultSlotIndex()){
			return false;
		}
		return true;
	}

	public function onClose(Player $who){
		$who->updateExperience();
		parent::onClose($who);

		$this->getHolder()->getLevel()->dropItem($this->getHolder()->add(0.5, 0.5, 0.5), $this->getItem(1));
		$this->getHolder()->getLevel()->dropItem($this->getHolder()->add(0.5, 0.5, 0.5), $this->getItem(0));
		$who->usingAnvil = false;
		
		$this->clear(0);
		$this->clear(1);
		$this->clear(2);
	}

}