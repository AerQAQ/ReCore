<?php

namespace pocketmine\level\generator\populator;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\generator\hell\NetherFortress;
use pocketmine\utils\Random;

class NetherFortressPopulator extends Populator{
    
    /** @var ChunkManager */
    private $level;

    public function populate(ChunkManager $level, $chunkX, $chunkZ, Random $random){
        $this->level = $level;
        
        // 在 432x432 网格中生成地狱堡垒
        $fortressSize = 432;
        $gridX = floor($chunkX * 16 / $fortressSize);
        $gridZ = floor($chunkZ * 16 / $fortressSize);
        
        // 每个网格 40% 概率生成
        $random->setSeed($gridX * 341873128712 + $gridZ * 132897987541 + $level->getSeed());
        
        if($random->nextFloat() < 0.4){
            // 在网格中心附近随机偏移
            $offsetX = $random->nextInt($fortressSize / 2) - $fortressSize / 4;
            $offsetZ = $random->nextInt($fortressSize / 2) - $fortressSize / 4;
            
            $posX = $gridX * $fortressSize + $fortressSize / 2 + $offsetX;
            $posZ = $gridZ * $fortressSize + $fortressSize / 2 + $offsetZ;
            
            // 检查是否在当前区块范围内
            $startX = $chunkX * 16;
            $startZ = $chunkZ * 16;
            $endX = $startX + 16;
            $endZ = $startZ + 16;
            
            // 如果堡垒在区块附近（50格范围内），生成它
            if($posX >= $startX - 50 && $posX <= $endX + 50 && $posZ >= $startZ - 50 && $posZ <= $endZ + 50){
                $fortress = new NetherFortress();
                $fortress->placeObject($level, $chunkX, $chunkZ, $posX, $posZ, $random);
            }
        }
    }
}