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

use pocketmine\block\Wool;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;
use pocketmine\item\Item as ItemItem;
use pocketmine\level\format\FullChunk;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\entity\ai\behavior\{StrollBehavior, RandomLookaroundBehavior, LookAtPlayerBehavior, PanicBehavior, findFoodBehavior, inLoveBehavior, eatGrassBehavior};

class Sheep extends Animal implements Colorable{
	const NETWORK_ID = 13;


	public $width = 0.625;
	public $length = 1.4375;
	public $height = 0;
	
	public function getName() : string{
		return "Sheep";
	}
	
	public function initEntity(){
		$this->setMaxHealth(8);
		
		$this->addBehavior(new inLoveBehavior($this));
		$this->addBehavior(new PanicBehavior($this, 0.25, 2.0));
		$this->addBehavior(new eatGrassBehavior($this));
		$this->addBehavior(new findFoodBehavior($this, 296));
		$this->addBehavior(new StrollBehavior($this));
		$this->addBehavior(new LookAtPlayerBehavior($this));
		$this->addBehavior(new RandomLookaroundBehavior($this));
		
		parent::initEntity();
	}

	public function __construct(FullChunk $chunk, CompoundTag $nbt){
		if(!isset($nbt->Color)){
			$nbt->Color = new ByteTag("Color", self::getRandomColor());
		}
		parent::__construct($chunk, $nbt);

		$this->sethasWool(true);
	}

	public static function getRandomColor() : int{
		$rand = "";
		// 基于 Minecraft Wiki 的羊自然生成概率（近似百分比）调整权重
		// 白色 (White): ~81.836%
		$rand .= str_repeat(Wool::WHITE . " ", 819);
		// 棕色 (Brown): ~5%
		$rand .= str_repeat(Wool::BROWN . " ", 50);
		// 黑色 (Black): ~2.5%
		$rand .= str_repeat(Wool::BLACK . " ", 25);
		// 灰色 (Gray): ~2.5%
		$rand .= str_repeat(Wool::GRAY . " ", 25);
		// 浅灰色 (Light Gray): ~2.5%
		$rand .= str_repeat(Wool::LIGHT_GRAY . " ", 25);
		// 其余颜色（粉色、橙色等）合计：~5.664%，进行近似分配
		$rand .= str_repeat(Wool::PINK . " ", 15);
		$rand .= str_repeat(Wool::ORANGE . " ", 15);
		$rand .= str_repeat(Wool::MAGENTA . " ", 10);
		$rand .= str_repeat(Wool::LIME . " ", 10);
		$rand .= str_repeat(Wool::LIGHT_BLUE . " ", 10);
		$rand .= str_repeat(Wool::YELLOW . " ", 10);
		$rand .= str_repeat(Wool::CYAN . " ", 5);
		$rand .= str_repeat(Wool::PURPLE . " ", 5);
		$rand .= str_repeat(Wool::BLUE . " ", 5);
		$rand .= str_repeat(Wool::GREEN . " ", 5);
		$rand .= str_repeat(Wool::RED . " ", 5);
		
		$arr = explode(" ", trim($rand));
		return (int) $arr[mt_rand(0, count($arr) - 1)];
	}

	public function getColor() : int{
		return (int) $this->namedtag["Color"];
	}

	public function setColor(int $color){
		$this->namedtag->Color = new ByteTag("Color", $color);
	}
	
	public function sethasWool(bool $resting){
		if($resting){
			$this->setDataProperty(self::DATA_COLOR_INFO, self::DATA_TYPE_BYTE, $this->getColor());
		}else{
			$this->setDataProperty(self::DATA_COLOR_INFO, self::DATA_TYPE_BYTE, 16);
		}
	}
	
	public function hasWool(){
		return $this->getDataProperty(self::DATA_COLOR_INFO) !== 16;
	}
	
	public function spawnTo(Player $player){
		$pk = new AddEntityPacket();
		$pk->eid = $this->getId();
		$pk->type = Sheep::NETWORK_ID;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->speedX = $this->motionX;
		$pk->speedY = $this->motionY;
		$pk->speedZ = $this->motionZ;
		$pk->yaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$pk->metadata = $this->dataProperties;
		$player->dataPacket($pk);

		parent::spawnTo($player);
	}
	
	public function getDrops(){
		if (!$this->hasWool()) {
			return []; // 无毛时不掉落任何物品
		}
		return [
			ItemItem::get(ItemItem::WOOL, $this->getColor(), 1)
		];
	}
}