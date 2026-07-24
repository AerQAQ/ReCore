<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____  
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \ 
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/ 
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_| 
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 * 
 *
*/

namespace pocketmine\block;

use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\sound\FizzSound;
use pocketmine\math\Vector3;

/**
 * 液体方块抽象类，继承自透明方块
 */
abstract class Liquid extends Transparent{

    /** @var Vector3 临时向量对象，用于减少重复创建对象的开销 */
    private $temporalVector = null;

    /**
     * 是否有实体碰撞
     * @return bool
     */
    public function hasEntityCollision(){
        return true;
    }

    /**
     * 是否可以被破坏
     * @param Item $item
     * @return bool
     */
    public function isBreakable(Item $item){
        return false;
    }

    /**
     * 是否可以被替换
     * @return bool
     */
    public function canBeReplaced(){
        return true;
    }

    /**
     * 是否是固体
     * @return bool
     */
    public function isSolid(){
        return false;
    }

    /** @var int 相邻源方块数量 */
    public $adjacentSources = 0;
    /** @var array 最优流动方向标记 */
    public $isOptimalFlowDirection = [0, 0, 0, 0];
    /** @var array 流动成本 */
    public $flowCost = [0, 0, 0, 0];

    /**
     * 获取液体高度百分比
     * @return float
     */
    public function getFluidHeightPercent(){
        $d = $this->meta;
        if($d >= 8){
            $d = 0;
        }

        return ($d + 1) / 9;
    }

    /**
     * 获取指定位置的流动衰减值
     * @param Vector3 $pos
     * @return int
     */
    protected function getFlowDecay(Vector3 $pos){
        if(!($pos instanceof Block)){
            $pos = $this->getLevel()->getBlock($pos);
        }

        if($pos->getId() !== $this->getId()){
            return -1;
        }else{
            return $pos->getDamage();
        }
    }

    /**
     * 获取有效的流动衰减值（处理源方块情况）
     * @param Vector3 $pos
     * @return int
     */
    protected function getEffectiveFlowDecay(Vector3 $pos){
        if(!($pos instanceof Block)){
            $pos = $this->getLevel()->getBlock($pos);
        }

        if($pos->getId() !== $this->getId()){
            return -1;
        }

        $decay = $pos->getDamage();

        if($decay >= 8){
            $decay = 0;
        }

        return $decay;
    }

    /**
     * 获取流动向量
     * @return Vector3
     */
    public function getFlowVector(){
        $vector = new Vector3(0, 0, 0);

        if($this->temporalVector === null){
            $this->temporalVector = new Vector3(0, 0, 0);
        }

        $decay = $this->getEffectiveFlowDecay($this);

        // 检查四个方向的相邻方块
        for($j = 0; $j < 4; ++$j){
            $x = $this->x;
            $y = $this->y;
            $z = $this->z;

            if($j === 0){
                --$x;
            }elseif($j === 1){
                ++$x;
            }elseif($j === 2){
                --$z;
            }elseif($j === 3){
                ++$z;
            }
            $sideBlock = $this->getLevel()->getBlock($this->temporalVector->setComponents($x, $y, $z));
            $blockDecay = $this->getEffectiveFlowDecay($sideBlock);

            if($blockDecay < 0){
                if(!$sideBlock->canBeFlowedInto()){
                    continue;
                }

                // 检查下方方块
                $blockDecay = $this->getEffectiveFlowDecay($this->getLevel()->getBlock($this->temporalVector->setComponents($x, $y - 1, $z)));

                if($blockDecay >= 0){
                    $realDecay = $blockDecay - ($decay - 8);
                    $vector->x += ($sideBlock->x - $this->x) * $realDecay;
                    $vector->y += ($sideBlock->y - $this->y) * $realDecay;
                    $vector->z += ($sideBlock->z - $this->z) * $realDecay;
                }

                continue;
            }else{
                $realDecay = $blockDecay - $decay;
                $vector->x += ($sideBlock->x - $this->x) * $realDecay;
                $vector->y += ($sideBlock->y - $this->y) * $realDecay;
                $vector->z += ($sideBlock->z - $this->z) * $realDecay;
            }
        }

        // 处理下落情况
        if($this->getDamage() >= 8){
            $falling = false;

            // 检查周围方块是否可以流动
            if(!$this->getLevel()->getBlock($this->temporalVector->setComponents($this->x, $this->y, $this->z - 1))->canBeFlowedInto()){
                $falling = true;
            }elseif(!$this->getLevel()->getBlock($this->temporalVector->setComponents($this->x, $this->y, $this->z + 1))->canBeFlowedInto()){
                $falling = true;
            }elseif(!$this->getLevel()->getBlock($this->temporalVector->setComponents($this->x - 1, $this->y, $this->z))->canBeFlowedInto()){
                $falling = true;
            }elseif(!$this->getLevel()->getBlock($this->temporalVector->setComponents($this->x + 1, $this->y, $this->z))->canBeFlowedInto()){
                $falling = true;
            }elseif(!$this->getLevel()->getBlock($this->temporalVector->setComponents($this->x, $this->y + 1, $this->z - 1))->canBeFlowedInto()){
                $falling = true;
            }elseif(!$this->getLevel()->getBlock($this->temporalVector->setComponents($this->x, $this->y + 1, $this->z + 1))->canBeFlowedInto()){
                $falling = true;
            }elseif(!$this->getLevel()->getBlock($this->temporalVector->setComponents($this->x - 1, $this->y + 1, $this->z))->canBeFlowedInto()){
                $falling = true;
            }elseif(!$this->getLevel()->getBlock($this->temporalVector->setComponents($this->x + 1, $this->y + 1, $this->z))->canBeFlowedInto()){
                $falling = true;
            }

            if($falling){
                $vector = $vector->normalize()->add(0, -6, 0);
            }
        }

        return $vector->normalize();
    }

    /**
     * 给实体添加液体流动速度
     * @param Entity $entity
     * @param Vector3 $vector
     */
    public function addVelocityToEntity(Entity $entity, Vector3 $vector){
        $flow = $this->getFlowVector();
        $vector->x += $flow->x;
        $vector->y += $flow->y;
        $vector->z += $flow->z;
    }

    /**
     * 获取更新频率
     * @return int
     */
    public function tickRate() : int{
        if($this instanceof Water){
            return 5;
        }elseif($this instanceof Lava){
            return 30;
        }

        return 0;
    }

    /**
     * 方块更新处理
     * @param int $type
     */
    public function onUpdate($type){
        if($type === Level::BLOCK_UPDATE_NORMAL){
            $this->checkForHarden();
            $this->getLevel()->scheduleUpdate($this, $this->tickRate());
        }elseif($type === Level::BLOCK_UPDATE_SCHEDULED){
            if($this->temporalVector === null){
                $this->temporalVector = new Vector3(0, 0, 0);
            }

            $decay = $this->getFlowDecay($this);
            $multiplier = $this instanceof Lava ? 2 : 1;

            $flag = true;

            if($decay > 0){
                $smallestFlowDecay = -100;
                $this->adjacentSources = 0;
                // 检查四个方向的流动衰减
                $smallestFlowDecay = $this->getSmallestFlowDecay($this->level->getBlock($this->temporalVector->setComponents($this->x, $this->y, $this->z - 1)), $smallestFlowDecay);
                $smallestFlowDecay = $this->getSmallestFlowDecay($this->level->getBlock($this->temporalVector->setComponents($this->x, $this->y, $this->z + 1)), $smallestFlowDecay);
                $smallestFlowDecay = $this->getSmallestFlowDecay($this->level->getBlock($this->temporalVector->setComponents($this->x - 1, $this->y, $this->z)), $smallestFlowDecay);
                $smallestFlowDecay = $this->getSmallestFlowDecay($this->level->getBlock($this->temporalVector->setComponents($this->x + 1, $this->y, $this->z)), $smallestFlowDecay);

                $k = $smallestFlowDecay + $multiplier;

                if($k >= 8 or $smallestFlowDecay < 0){
                    $k = -1;
                }

                // 检查上方方块
                if(($topFlowDecay = $this->getFlowDecay($this->level->getBlock($this->level->getBlock($this->temporalVector->setComponents($this->x, $this->y + 1, $this->z))))) >= 0){
                    if($topFlowDecay >= 8){
                        $k = $topFlowDecay;
                    }else{
                        $k = $topFlowDecay | 0x08;
                    }
                }

                // 水方块特殊处理
                if($this->adjacentSources >= 2 and $this instanceof Water){
                    $bottomBlock = $this->level->getBlock($this->level->getBlock($this->temporalVector->setComponents($this->x, $this->y - 1, $this->z)));
                    if($bottomBlock->isSolid()){
                        $k = 0;
                    }elseif($bottomBlock instanceof Water and $bottomBlock->getDamage() === 0){
                        $k = 0;
                    }
                }

                // 岩浆方块特殊处理
                if($this instanceof Lava and $decay < 8 and $k < 8 and $k > 1 and mt_rand(0, 4) !== 0){
                    $k = $decay;
                    $flag = false;
                }

                if($k !== $decay){
                    $decay = $k;
                    if($decay < 0){
                        $this->getLevel()->setBlock($this, new Air(), true);
                    }else{
                        $this->getLevel()->setBlock($this, Block::get($this->id, $decay), true);
                        $this->getLevel()->scheduleUpdate($this, $this->tickRate());
                    }
                }
            }

            // 处理向下流动
            $bottomBlock = $this->level->getBlock($this->temporalVector->setComponents($this->x, $this->y - 1, $this->z));

            if($bottomBlock->canBeFlowedInto() or $bottomBlock instanceof Liquid){
                if($this instanceof Lava and $bottomBlock instanceof Water){
                    $this->getLevel()->setBlock($bottomBlock, Block::get(Item::STONE), true);
                    $this->triggerLavaMixEffects($bottomBlock);
                    return;
                }
/*
                if($decay >= 8){
                    $this->flowIntoBlock($bottomBlock, $decay);
                }else{
                    $this->flowIntoBlock($bottomBlock, $decay | 0x08);
                }*/
            }elseif($decay >= 0 and ($decay === 0 or !$bottomBlock->canBeFlowedInto())){
                // 向四周流动
                $flags = $this->getOptimalFlowDirections();

                $l = $decay + $multiplier;

                if($decay >= 8){
                    $l = 1;
                }

                if($l >= 8){
                    $this->checkForHarden();
                    return;
                }

                if($flags[0]){
                    $this->flowIntoBlock($this->level->getBlock($this->temporalVector->setComponents($this->x - 1, $this->y, $this->z)), $l);
                }

                if($flags[1]){
                    $this->flowIntoBlock($this->level->getBlock($this->temporalVector->setComponents($this->x + 1, $this->y, $this->z)), $l);
                }

                if($flags[2]){
                    $this->flowIntoBlock($this->level->getBlock($this->temporalVector->setComponents($this->x, $this->y, $this->z - 1)), $l);
                }

                if($flags[3]){
                    $this->flowIntoBlock($this->level->getBlock($this->temporalVector->setComponents($this->x, $this->y, $this->z + 1)), $l);
                }
            }

            $this->checkForHarden();
        }
    }

    /**
     * 液体流入方块
     * @param Block $block
     * @param int $newFlowDecay
     */
    private function flowIntoBlock(Block $block, $newFlowDecay){
        if($block->canBeFlowedInto()){
            if($block instanceof Lava){
                $this->triggerLavaMixEffects($block);
            }elseif($block->getId() > 0){
                $this->getLevel()->useBreakOn($block);
            }

            $this->getLevel()->setBlock($block, Block::get($this->getId(), $newFlowDecay), true);
            $this->getLevel()->scheduleUpdate($block, $this->tickRate());
        }
    }

    /**
     * 计算流动成本
     * @param Block $block
     * @param int $accumulatedCost
     * @param int $previousDirection
     * @return int
     */
    private function calculateFlowCost(Block $block, $accumulatedCost, $previousDirection){
        $cost = 1000;

        for($j = 0; $j < 4; ++$j){
            if(
                ($j === 0 and $previousDirection === 1) or
                ($j === 1 and $previousDirection === 0) or
                ($j === 2 and $previousDirection === 3) or
                ($j === 3 and $previousDirection === 2)
            ){
                $x = $block->x;
                $y = $block->y;
                $z = $block->z;

                if($j === 0){
                    --$x;
                }elseif($j === 1){
                    ++$x;
                }elseif($j === 2){
                    --$z;
                }elseif($j === 3){
                    ++$z;
                }
                $blockSide = $this->getLevel()->getBlock($this->temporalVector->setComponents($x, $y, $z));

                if(!$blockSide->canBeFlowedInto() and !($blockSide instanceof Liquid)){
                    continue;
                }elseif($blockSide instanceof Liquid and $blockSide->getDamage() === 0){
                    continue;
                }elseif($this->getLevel()->getBlock($this->temporalVector->setComponents($x, $y - 1, $z))->canBeFlowedInto()){
                    return $accumulatedCost;
                }

                if($accumulatedCost >= 4){
                    continue;
                }

                $realCost = $this->calculateFlowCost($blockSide, $accumulatedCost + 1, $j);

                if($realCost < $cost){
                    $cost = $realCost;
                }
            }
        }

        return $cost;
    }

    /**
     * 获取硬度
     * @return int
     */
     /*
    public function getHardness() {
        return 100;
    }*/

    /**
     * 获取最优流动方向
     * @return array
     */
    private function getOptimalFlowDirections(){
        if($this->temporalVector === null){
            $this->temporalVector = new Vector3(0, 0, 0);
        }

        // 计算四个方向的流动成本
        for($j = 0; $j < 4; ++$j){
            $this->flowCost[$j] = 1000;

            $x = $this->x;
            $y = $this->y;
            $z = $this->z;

            if($j === 0){
                --$x;
            }elseif($j === 1){
                ++$x;
            }elseif($j === 2){
                --$z;
            }elseif($j === 3){
                ++$z;
            }
            $block = $this->getLevel()->getBlock($this->temporalVector->setComponents($x, $y, $z));

            if(!$block->canBeFlowedInto() and !($block instanceof Liquid)){
                continue;
            }elseif($block instanceof Liquid and $block->getDamage() === 0){
                continue;
            }elseif($this->getLevel()->getBlock($this->temporalVector->setComponents($x, $y - 1, $z))->canBeFlowedInto()){
                $this->flowCost[$j] = 0;
            }else{
                $this->flowCost[$j] = $this->calculateFlowCost($block, 1, $j);
            }
        }

        // 找出最小成本
        $minCost = $this->flowCost[0];
        for($i = 1; $i < 4; ++$i){
            if($this->flowCost[$i] < $minCost){
                $minCost = $this->flowCost[$i];
            }
        }

        // 标记最优方向
        for($i = 0; $i < 4; ++$i){
            $this->isOptimalFlowDirection[$i] = ($this->flowCost[$i] === $minCost);
        }

        return $this->isOptimalFlowDirection;
    }

    /**
     * 获取最小的流动衰减值
     * @param Vector3 $pos
     * @param int $decay
     * @return int
     */
    private function getSmallestFlowDecay(Vector3 $pos, $decay){
        $blockDecay = $this->getFlowDecay($pos);

        if($blockDecay < 0){
            return $decay;
        }elseif($blockDecay === 0){
            ++$this->adjacentSources;
        }elseif($blockDecay >= 8){
            $blockDecay = 0;
        }

        return ($decay >= 0 && $blockDecay >= $decay) ? $decay : $blockDecay;
    }

    /**
     * 检查硬化（岩浆与水接触变成石头或黑曜石）
     */
    private function checkForHarden(){
        if($this instanceof Lava){
            $colliding = false;
            for($side = 0; $side <= 5 and !$colliding; ++$side){
                $colliding = $this->getSide($side) instanceof Water;
            }

            if($colliding){
                if($this->getDamage() === 0){
                    $this->getLevel()->setBlock($this, Block::get(Item::OBSIDIAN), true);
                }elseif($this->getDamage() <= 4){
                    $this->getLevel()->setBlock($this, Block::get(Item::COBBLESTONE), true);
                }
                $this->triggerLavaMixEffects($this);
            }
        }
    }

    /**
     * 获取碰撞箱
     * @return null
     */
    public function getBoundingBox(){
        return null;
    }

    /**
     * 获取掉落物
     * @param Item $item
     * @return array
     */
    public function getDrops(Item $item) : array {
        return [];
    }

    /**
     * 触发岩浆混合效果（声音和粒子效果）
     * @param Vector3 $pos
     */
    protected function triggerLavaMixEffects(Vector3 $pos){
        $this->getLevel()->addSound(new FizzSound($pos->add(0.5, 0.5, 0.5), 2.5 + mt_rand(0, 1000) / 1000 * 0.8));

        for($i = 0; $i < 8; ++$i){
            $this->getLevel()->addParticle(new SmokeParticle($pos->add(mt_rand(0, 80) / 100, 0.5, mt_rand(0, 80) / 100)));
        }
    }
}