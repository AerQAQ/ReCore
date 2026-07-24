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

use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\item\Item as ItemItem;
use pocketmine\network\Network;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\MobEquipmentPacket;
use pocketmine\network\protocol\MobArmorEquipmentPacket;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\entity\ai\behavior\{StrollBehavior, RandomLookaroundBehavior, AttackEnemyBehavior};

class Zombie extends Monster implements Ageable{
    const NETWORK_ID = 32;

    public $width = 0.6;
    public $length = 0.6;
    public $height = 0;
    
    public $dropExp = [5, 5];
    
    private $hurt = 8;
    
    // 工具伤害加成
    private $toolDamageBonus = [
        0 => 0, // 空手
        ItemItem::WOODEN_SWORD => 4,
        ItemItem::WOODEN_SHOVEL => 2,
        ItemItem::WOODEN_PICKAXE => 2,
        ItemItem::WOODEN_AXE => 4,
        ItemItem::STONE_SWORD => 5,
        ItemItem::STONE_SHOVEL => 3,
        ItemItem::STONE_PICKAXE => 3,
        ItemItem::STONE_AXE => 5,
        ItemItem::IRON_SWORD => 6,
        ItemItem::IRON_SHOVEL => 4,
        ItemItem::IRON_PICKAXE => 4,
        ItemItem::IRON_AXE => 6,
        ItemItem::GOLD_SWORD => 4,
        ItemItem::GOLD_SHOVEL => 2,
        ItemItem::GOLD_PICKAXE => 2,
        ItemItem::GOLD_AXE => 4,
        ItemItem::DIAMOND_SWORD => 7,
        ItemItem::DIAMOND_SHOVEL => 5,
        ItemItem::DIAMOND_PICKAXE => 5,
        ItemItem::DIAMOND_AXE => 7
    ];
    
    // 盔甲生命值加成（每件盔甲提供的额外生命值）
    private $armorHealthBonus = [
        ItemItem::LEATHER_CAP => 2,
        ItemItem::LEATHER_TUNIC => 4,
        ItemItem::LEATHER_PANTS => 3,
        ItemItem::LEATHER_BOOTS => 1,
        
        ItemItem::CHAIN_HELMET => 3,
        ItemItem::CHAIN_CHESTPLATE => 6,
        ItemItem::CHAIN_LEGGINGS => 4,
        ItemItem::CHAIN_BOOTS => 2,
        
        ItemItem::IRON_HELMET => 4,
        ItemItem::IRON_CHESTPLATE => 8,
        ItemItem::IRON_LEGGINGS => 6,
        ItemItem::IRON_BOOTS => 3,
        
        ItemItem::GOLD_HELMET => 2,
        ItemItem::GOLD_CHESTPLATE => 5,
        ItemItem::GOLD_LEGGINGS => 4,
        ItemItem::GOLD_BOOTS => 1,
        
        ItemItem::DIAMOND_HELMET => 6,
        ItemItem::DIAMOND_CHESTPLATE => 12,
        ItemItem::DIAMOND_LEGGINGS => 8,
        ItemItem::DIAMOND_BOOTS => 4,
    ];
    
    // 根据Wiki概率分布定义工具和概率
    private $toolProbabilities = [
        // 空手概率: 约86.5%
        0 => 8650, // 空手（无物品）
        
        // 铁质工具: 各2.5% (总共10%)
        ItemItem::IRON_SHOVEL => 250,    // 铁锹锹锹锹锹锹锹锹
        ItemItem::IRON_PICKAXE => 250,   // 铁镐镐镐镐镐镐镐镐
        ItemItem::IRON_AXE => 250,       // 铁斧
        ItemItem::IRON_SWORD => 250,     // 铁剑
        
        // 木质工具: 各0.5% (总共2%)
        ItemItem::WOODEN_SHOVEL => 50,   // 木锹锹锹锹锹锹锹锹
        ItemItem::WOODEN_PICKAXE => 50,  // 木镐镐镐镐镐镐镐镐
        ItemItem::WOODEN_AXE => 50,      // 木斧
        ItemItem::WOODEN_SWORD => 50,    // 木剑
        
        // 石质工具: 各0.25% (总共1%)
        ItemItem::STONE_SHOVEL => 25,    // 石锹锹锹锹锹锹锹锹
        ItemItem::STONE_PICKAXE => 25,   // 石镐镐镐镐镐镐镐镐
        ItemItem::STONE_AXE => 25,       // 石斧
        ItemItem::STONE_SWORD => 25,     // 石剑
    ];
    
    // 工具的最大耐久度
    private $toolDurability = [
        ItemItem::WOODEN_SWORD => 60,
        ItemItem::WOODEN_SHOVEL => 60,
        ItemItem::WOODEN_PICKAXE => 60,
        ItemItem::WOODEN_AXE => 60,
        
        ItemItem::STONE_SWORD => 132,
        ItemItem::STONE_SHOVEL => 132,
        ItemItem::STONE_PICKAXE => 132,
        ItemItem::STONE_AXE => 132,
        
        ItemItem::IRON_SWORD => 251,
        ItemItem::IRON_SHOVEL => 251,
        ItemItem::IRON_PICKAXE => 251,
        ItemItem::IRON_AXE => 251,
    ];
    
    // 盔甲类型和耐久度
    private $armorDurability = [
        ItemItem::LEATHER_CAP => 56,     // 皮革头盔耐久度
        ItemItem::LEATHER_TUNIC => 81,   // 皮革胸甲耐久度
        ItemItem::LEATHER_PANTS => 76,   // 皮革裤子耐久度
        ItemItem::LEATHER_BOOTS => 66,   // 皮革靴子耐久度
        
        ItemItem::CHAIN_HELMET => 166,   // 锁链头盔耐久度
        ItemItem::CHAIN_CHESTPLATE => 241, // 锁链胸甲耐久度
        ItemItem::CHAIN_LEGGINGS => 226, // 锁链裤子耐久度
        ItemItem::CHAIN_BOOTS => 196,    // 锁链靴子耐久度
        
        ItemItem::IRON_HELMET => 166,    // 铁头盔耐久度
        ItemItem::IRON_CHESTPLATE => 241, // 铁胸甲耐久度
        ItemItem::IRON_LEGGINGS => 226,  // 铁裤子耐久度
        ItemItem::IRON_BOOTS => 196,     // 铁靴子耐久度
        
        ItemItem::GOLD_HELMET => 78,     // 金头盔耐久度
        ItemItem::GOLD_CHESTPLATE => 113, // 金胸甲耐久度
        ItemItem::GOLD_LEGGINGS => 106,  // 金裤子耐久度
        ItemItem::GOLD_BOOTS => 92,      // 金靴子耐久度
        
        ItemItem::DIAMOND_HELMET => 364, // 钻石头盔耐久度
        ItemItem::DIAMOND_CHESTPLATE => 529, // 钻石胸甲耐久度
        ItemItem::DIAMOND_LEGGINGS => 496, // 钻石裤子耐久度
        ItemItem::DIAMOND_BOOTS => 430,  // 钻石靴子耐久度
    ];
    
    // 盔甲材质概率（根据Minecraft原版概率调整）
    private $armorMaterialProbabilities = [
        ItemItem::LEATHER_CAP => 1500,    // 皮革
        ItemItem::CHAIN_HELMET => 1000,   // 锁链
        ItemItem::IRON_HELMET => 3000,    // 铁
        ItemItem::GOLD_HELMET => 500,     // 金
        ItemItem::DIAMOND_HELMET => 250,  // 钻石
    ];
    
    private $selectedTool = 0; // 默认为空手(0)
    private $armorItems = [null, null, null, null];  // 存储盔甲物品 [头盔, 胸甲, 护腿, 靴子]
    
    public function getName() : string{
        return "Zombie";
    }
    
    public function initEntity(){
        $baseHealth = 20; // 基础生命值
        
        if(!isset($this->namedtag->IsBaby)){
            $this->namedtag->IsBaby = new ByteTag("IsBaby", 1);
            $this->setBaby(false);
        }
        
        // 随机选择工具（根据Wiki概率）
        if(!isset($this->namedtag->SelectedTool)){
            $this->selectRandomTool();
        }else{
            // === 修复：安全地读取SelectedTool，处理多种可能的类型 ===
            $selectedTool = $this->namedtag->SelectedTool;
            if($selectedTool instanceof IntTag){
                $this->selectedTool = $selectedTool->getValue();
            }elseif(is_numeric($selectedTool)){
                $this->selectedTool = (int)$selectedTool;
            }else{
                // 如果格式异常，使用默认值并记录错误
                $this->selectedTool = 0;
                $this->getServer()->getLogger()->warning("Zombie SelectedTool格式异常，使用默认值");
            }
        }
        
        // 随机生成盔甲
        if(!isset($this->namedtag->ArmorItems)){
            $this->generateRandomArmor();
        }else{
            $this->loadArmorFromNBT();
        }
        
        // 计算总生命值加成
        $totalHealthBonus = 0;
        foreach($this->armorItems as $slot => $armorItem){
            if($armorItem !== null){
                $armorId = $armorItem->getId();
                if(isset($this->armorHealthBonus[$armorId])){
                    $totalHealthBonus += $this->armorHealthBonus[$armorId];
                }
            }
        }
        
        // 设置最终生命值（基础生命值 + 盔甲加成）
        $finalHealth = $baseHealth + $totalHealthBonus;
        $this->setMaxHealth($finalHealth);
        $this->setHealth($finalHealth);
        
        // 计算工具伤害加成
        $safeToolId = $this->getSafeToolId();
        $toolDamageBonus = 0;
        if(isset($this->toolDamageBonus[$safeToolId])){
            $toolDamageBonus = $this->toolDamageBonus[$safeToolId];
        }
        
        // 设置最终伤害（基础伤害 + 工具加成）
        $this->hurt = 8 + $toolDamageBonus;
        
        $this->addBehavior(new AttackEnemyBehavior($this, [20], true));
        $this->addBehavior(new StrollBehavior($this));
        $this->addBehavior(new RandomLookaroundBehavior($this));
        
        parent::initEntity();
    }
    
    /**
     * 安全地获取工具ID，处理多种可能的类型
     */
    private function getSafeToolId(){
        $toolId = $this->selectedTool;
        
        // 处理IntTag对象
        if($toolId instanceof IntTag){
            return $toolId->getValue();
        }
        
        // 处理其他可能的对象类型
        if(is_object($toolId) && method_exists($toolId, 'getValue')){
            $toolId = $toolId->getValue();
        }
        
        // 确保返回整数
        return (int)$toolId;
    }
    
    /**
     * 根据Wiki概率随机选择工具
     */
    private function selectRandomTool(){
        $rand = mt_rand(1, 10000); // 生成1-10000的随机数
        
        $currentProbability = 0;
        foreach($this->toolProbabilities as $toolId => $probability){
            $currentProbability += $probability;
            if($rand <= $currentProbability){
                $this->selectedTool = (int)$toolId; // 确保是整数
                break;
            }
        }
        
        // 确保保存整数到NBT
        $this->namedtag->SelectedTool = new IntTag("SelectedTool", (int)$this->selectedTool);
    }
    
    /**
     * 随机生成盔甲
     */
    private function generateRandomArmor(){
        $this->armorItems = [null, null, null, null]; // 初始化空盔甲槽
        
        // 每个部位有15%的概率生成盔甲
        $armorSlots = [
            0 => [ // 头盔
                ItemItem::LEATHER_CAP, ItemItem::CHAIN_HELMET, ItemItem::IRON_HELMET, 
                ItemItem::GOLD_HELMET, ItemItem::DIAMOND_HELMET
            ],
            1 => [ // 胸甲
                ItemItem::LEATHER_TUNIC, ItemItem::CHAIN_CHESTPLATE, ItemItem::IRON_CHESTPLATE,
                ItemItem::GOLD_CHESTPLATE, ItemItem::DIAMOND_CHESTPLATE
            ],
            2 => [ // 护腿
                ItemItem::LEATHER_PANTS, ItemItem::CHAIN_LEGGINGS, ItemItem::IRON_LEGGINGS,
                ItemItem::GOLD_LEGGINGS, ItemItem::DIAMOND_LEGGINGS
            ],
            3 => [ // 靴子
                ItemItem::LEATHER_BOOTS, ItemItem::CHAIN_BOOTS, ItemItem::IRON_BOOTS,
                ItemItem::GOLD_BOOTS, ItemItem::DIAMOND_BOOTS
            ]
        ];
        
        foreach($armorSlots as $slot => $armorTypes){
            if(mt_rand(1, 100) <= 15){ // 15%概率生成盔甲
                $rand = mt_rand(1, 6250); // 总概率为1500+1000+3000+500+250=6250
                $currentProbability = 0;
                
                foreach($this->armorMaterialProbabilities as $material => $probability){
                    $currentProbability += $probability;
                    if($rand <= $currentProbability){
                        // 找到对应槽位的盔甲
                        $materialIndex = array_search($material, [
                            ItemItem::LEATHER_CAP, ItemItem::CHAIN_HELMET, ItemItem::IRON_HELMET,
                            ItemItem::GOLD_HELMET, ItemItem::DIAMOND_HELMET
                        ]);
                        
                        $armorId = $armorTypes[$materialIndex];
                        
                        if(isset($this->armorDurability[$armorId])){
                            $maxDurability = $this->armorDurability[$armorId];
                            $damage = mt_rand(0, (int)($maxDurability * 0.9)); // 随机耐久度
                        }else{
                            $damage = 0;
                        }
                        
                        $this->armorItems[$slot] = ItemItem::get($armorId, $damage, 1);
                        break;
                    }
                }
            }
        }
        
        $this->saveArmorToNBT();
    }
    
    /**
     * 从NBT加载盔甲
     */
    private function loadArmorFromNBT(){
        if(isset($this->namedtag->ArmorItems) && $this->namedtag->ArmorItems instanceof ListTag){
            $armorList = $this->namedtag->ArmorItems;
            
            for($slot = 0; $slot < 4; $slot++){
                if(isset($armorList[$slot]) && $armorList[$slot] instanceof CompoundTag){
                    $tag = $armorList[$slot];
                    
                    // 检查必需的标签是否存在
                    $hasId = false;
                    $hasDamage = false;
                    
                    foreach($tag->getValue() as $namedTag){
                        if($namedTag instanceof ShortTag && $namedTag->getName() === "id"){
                            $hasId = true;
                        }
                        if($namedTag instanceof ShortTag && $namedTag->getName() === "Damage"){
                            $hasDamage = true;
                        }
                    }
                    
                    if($hasId && $hasDamage){
                        $id = $tag->getShort("id");
                        $damage = $tag->getShort("Damage");
                        $count = $tag->getByte("Count", 1);
                        $this->armorItems[$slot] = ItemItem::get($id, $damage, $count);
                    }
                }
            }
        }
    }
    
    /**
     * 保存盔甲到NBT
     */
    private function saveArmorToNBT(){
        $armorList = [];
        
        for($slot = 0; $slot < 4; $slot++){
            $item = $this->armorItems[$slot];
            if($item !== null){
                // 手动创建NBT标签
                $tag = new CompoundTag("", [
                    new ShortTag("id", $item->getId()),
                    new ShortTag("Damage", $item->getDamage()),
                    new ByteTag("Count", $item->getCount()),
                    new ByteTag("Slot", $slot)
                ]);
                $armorList[] = $tag;
            }else{
                $armorList[] = new CompoundTag("", []);
            }
        }
        
        $this->namedtag->ArmorItems = new ListTag("ArmorItems", $armorList);
    }
    
    /**
     * 获取当前选择的工具ID
     */
    public function getSelectedTool(){
        return $this->selectedTool;
    }
    
    public function getHurt(){
        return $this->hurt;
    }
    
    public function setHurt($hurt){
        $this->hurt = $hurt;
    }

    public function spawnTo(Player $player){
        $pk = new AddEntityPacket();
        $pk->eid = $this->getId();
        $pk->type = Zombie::NETWORK_ID;
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
        
        // 发送手持工具的数据包
        // === 修复：使用getSafeToolId()确保获取整数ID ===
        $safeToolId = $this->getSafeToolId();
        if($safeToolId !== 0){
            $pk = new MobEquipmentPacket();
            $pk->eid = $this->getId();
            // === 修复：确保传入整数ID ===
            $pk->item = ItemItem::get((int)$safeToolId, 0, 1);
            $pk->slot = 0;
            $pk->selectedSlot = 0;
            $player->dataPacket($pk);
        }
        
        // 发送盔甲数据包
        $hasArmor = false;
        foreach($this->armorItems as $item){
            if($item !== null){
                $hasArmor = true;
                break;
            }
        }
        
        if($hasArmor){
            $pk = new MobArmorEquipmentPacket();
            $pk->eid = $this->getId();
            $pk->slots = [
                $this->armorItems[0] ?? ItemItem::get(ItemItem::AIR, 0, 0), // 头盔
                $this->armorItems[1] ?? ItemItem::get(ItemItem::AIR, 0, 0), // 胸甲
                $this->armorItems[2] ?? ItemItem::get(ItemItem::AIR, 0, 0), // 护腿
                $this->armorItems[3] ?? ItemItem::get(ItemItem::AIR, 0, 0)  // 靴子
            ];
            $player->dataPacket($pk);
        }
    }

    public function getDrops(){
        $drops = [];
        
        // 基础掉落物（与手持物品无关）
        if(mt_rand(0, 99) < 10){
            switch(mt_rand(0, 2)){
                case 0:
                    $drops[] = ItemItem::get(ItemItem::IRON_INGOT, 0, 1);
                    break;
                case 1:
                    $drops[] = ItemItem::get(ItemItem::CARROT, 0, 1);
                    break;
                case 2:
                    $drops[] = ItemItem::get(ItemItem::POTATO, 0, 1);
                    break;
            }
        }else{
            $drops[] = ItemItem::get(ItemItem::ROTTEN_FLESH, 0, 1);
        }
        
        // 手持工具的掉落
        // === 修复：使用getSafeToolId()确保获取整数ID ===
        $safeToolId = $this->getSafeToolId();
        if($safeToolId !== 0 && mt_rand(0, 99) < 8.5){ // 8.5%的掉落几率
            if(isset($this->toolDurability[$safeToolId])){
                $maxDurability = $this->toolDurability[$safeToolId];
                $damage = mt_rand(0, (int)($maxDurability * 0.9));
                // === 修复：确保传入整数ID ===
                $drops[] = ItemItem::get((int)$safeToolId, $damage, 1);
            }else{
                $drops[] = ItemItem::get((int)$safeToolId, 0, 1);
            }
        }
        
        // 盔甲的掉落（每个部位独立计算，几率为8.5%）
        foreach($this->armorItems as $slot => $armorItem){
            if($armorItem !== null && mt_rand(0, 99) < 8.5){
                $drops[] = $armorItem;
            }
        }
        
        return $drops;
    }
    
    public function isBaby(){
        return $this->namedtag["IsBaby"] == 0 ? false : true;
    }
    
    public function setBaby(bool $resting){
        $this->setDataProperty(self::DATA_ZOMBIE_IS_BABY, self::DATA_TYPE_BYTE, $resting ? 1 : 0);
        $this->namedtag->IsBaby = new ByteTag("IsBaby", $resting ? 1 : 0);
    }
    
    /**
     * 获取盔甲提供的总生命值加成
     */
    public function getArmorHealthBonus(){
        $totalBonus = 0;
        foreach($this->armorItems as $armorItem){
            if($armorItem !== null){
                $armorId = $armorItem->getId();
                if(isset($this->armorHealthBonus[$armorId])){
                    $totalBonus += $this->armorHealthBonus[$armorId];
                }
            }
        }
        return $totalBonus;
    }
    
    /**
     * 获取工具提供的伤害加成
     */
    public function getToolDamageBonus(){
        $safeToolId = $this->getSafeToolId();
        return isset($this->toolDamageBonus[$safeToolId]) ? $this->toolDamageBonus[$safeToolId] : 0;
    }
}
