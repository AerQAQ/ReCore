<?php

namespace pocketmine\block;

use pocketmine\level\Level;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\Player;

class Sponge extends Solid{

    protected $id = self::SPONGE;

    public function __construct($meta = 0){
        $this->meta = $meta;
    }

    public function getHardness(){
        return 0.6;
    }

    public function getName() : string{
        return "Sponge";
    }

    /* -------- 放置时吸一次 -------- */
    public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null){
        parent::place($item, $block, $target, $face, $fx, $fy, $fz, $player);
        $this->absorbWater();
    }

    /* -------- 邻居更新时再吸 -------- */
    public function onUpdate($type){
        if($type === Level::BLOCK_UPDATE_NORMAL){
            $this->absorbWater();
        }
    }

    /* ------------------------------------------------------------------
     * 真正吸水逻辑：7×7×7 范围，静止水 + 流动水全部干掉
     * ------------------------------------------------------------------ */
    private function absorbWater(){
        $level  = $this->getLevel();
        $center = new Vector3($this->x, $this->y, $this->z);
        $radius = 3;          // 3 格半径 → 7×7×7
        $absorbed = 0;

        for($dx = -$radius; $dx <= $radius; $dx++){
            for($dy = -$radius; $dy <= $radius; $dy++){
                for($dz = -$radius; $dz <= $radius; $dz++){
                    $block = $level->getBlock($center->add($dx, $dy, $dz));
                    // 只要是水（静止或流动）都干掉
                    if($block->getId() === Block::WATER){
                        $level->setBlock($block, new Air(), true, false);
                        $absorbed++;
                    }
                }
            }
        }

        // 只要吸掉至少 1 格就播放一次效果
        if($absorbed > 0){
            $level->addSound(new FizzSound($center->add(0.5, 0.5, 0.5), 2.5 + mt_rand(0, 1000) / 1000 * 0.8));
            for($i = 0; $i < 8; $i++){
                $level->addParticle(new SmokeParticle($center->add(
                    mt_rand(10, 90) / 100,
                    mt_rand(10, 90) / 100,
                    mt_rand(10, 90) / 100
                )));
            }
        }
    }
}