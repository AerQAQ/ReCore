<?php

namespace pocketmine\level\generator\populator;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;

class Cave extends Populator{

    /** @var ChunkManager */
    private $level;
    /** @var Random */
    private $random;

    public function populate(ChunkManager $level, $chunkX, $chunkZ, Random $random){
        $this->level = $level;
        $this->random = $random;

        // 每个区块生成 1-3 个洞穴
        $amount = $random->nextBoundedInt(3) + 1;

        for($i = 0; $i < $amount; $i++){
            $x = $random->nextRange($chunkX << 4, ($chunkX << 4) + 15);
            $z = $random->nextRange($chunkZ << 4, ($chunkZ << 4) + 15);
            $y = $random->nextRange(10, 60);

            $this->generateCave($x, $y, $z);
        }

        // 在洞穴中生成水和岩浆
        for($i = 0; $i < $random->nextBoundedInt(5) + 3; $i++){
            $x = $random->nextRange($chunkX << 4, ($chunkX << 4) + 15);
            $z = $random->nextRange($chunkZ << 4, ($chunkZ << 4) + 15);
            $y = $random->nextRange(10, 60);

            $blockId = $this->level->getBlockIdAt($x, $y, $z);
            $above = $this->level->getBlockIdAt($x, $y + 1, $z);
            $below = $this->level->getBlockIdAt($x, $y - 1, $z);

            // 在石头中且旁边有空气
            if($blockId == Block::STONE &&
               ($this->level->getBlockIdAt($x + 1, $y, $z) == Block::AIR ||
                $this->level->getBlockIdAt($x - 1, $y, $z) == Block::AIR ||
                $this->level->getBlockIdAt($x, $y, $z + 1) == Block::AIR ||
                $this->level->getBlockIdAt($x, $y, $z - 1) == Block::AIR) &&
               $below != Block::AIR && $above != Block::AIR){

                if($y < 40 && $random->nextBoolean()){
                    $this->level->setBlockIdAt($x, $y, $z, Block::LAVA);
                }else{
                    $this->level->setBlockIdAt($x, $y, $z, Block::WATER);
                }
            }
        }
    }

    /**
     * 生成一个洞穴
     */
    private function generateCave(int $x, int $y, int $z){
        $length = $this->random->nextBoundedInt(40) + 20;
        $radius = $this->random->nextBoundedInt(3) + 2;

        $direction = $this->random->nextBoundedInt(360);
        $pitch = $this->random->nextBoundedInt(60) - 30;

        $currentX = $x;
        $currentY = $y;
        $currentZ = $z;

        for($i = 0; $i < $length; $i++){
            // 洞穴半径逐渐变化
            $currentRadius = $radius + sin($i / 10) * 0.5;

            // 挖空洞穴
            $this->carveSphere($currentX, $currentY, $currentZ, $currentRadius);

            // 随机改变方向
            $direction += $this->random->nextBoundedInt(30) - 15;
            $pitch += $this->random->nextBoundedInt(20) - 10;

            $dirVec = $this->getDirectionVector($direction, $pitch);

            $step = $this->random->nextBoundedInt(3) + 1;
            $currentX += $dirVec->x * $step;
            $currentY += $dirVec->y * $step;
            $currentZ += $dirVec->z * $step;

            // 防止洞穴太高或太低
            if($currentY < 5) $currentY = 5 + $this->random->nextBoundedInt(10);
            if($currentY > 70) $currentY = 70 - $this->random->nextBoundedInt(10);

            // 生成分支
            if($this->random->nextBoundedInt(8) == 0){
                $branchDir = $direction + $this->random->nextBoundedInt(180) - 90;
                $branchPitch = $this->random->nextBoundedInt(40) - 20;
                $branchLength = $this->random->nextBoundedInt(15) + 10;
                $branchRadius = max(1, $radius - 1);

                $this->generateBranch($currentX, $currentY, $currentZ, $branchDir, $branchPitch, $branchLength, $branchRadius);
            }
        }
    }

    /**
     * 生成分支洞穴
     */
    private function generateBranch(int $x, int $y, int $z, int $direction, int $pitch, int $length, int $radius){
        $currentX = $x;
        $currentY = $y;
        $currentZ = $z;

        for($i = 0; $i < $length; $i++){
            $currentRadius = max(1, $radius - ($i / $length) * 2);

            $this->carveSphere($currentX, $currentY, $currentZ, $currentRadius);

            $direction += $this->random->nextBoundedInt(40) - 20;
            $pitch += $this->random->nextBoundedInt(20) - 10;

            $dirVec = $this->getDirectionVector($direction, $pitch);

            $step = $this->random->nextBoundedInt(2) + 1;
            $currentX += $dirVec->x * $step;
            $currentY += $dirVec->y * $step;
            $currentZ += $dirVec->z * $step;

            if($currentY < 5 || $currentY > 70){
                break;
            }
        }
    }

    /**
     * 挖出一个球体
     */
    private function carveSphere(int $x, int $y, int $z, float $radius){
        $radius = (int)ceil($radius);
        for($xx = -$radius; $xx <= $radius; $xx++){
            for($yy = -$radius; $yy <= $radius; $yy++){
                for($zz = -$radius; $zz <= $radius; $zz++){
                    if($xx*$xx + $yy*$yy + $zz*$zz <= $radius * $radius){
                        $wx = $x + $xx;
                        $wy = $y + $yy;
                        $wz = $z + $zz;

                        $blockId = $this->level->getBlockIdAt($wx, $wy, $wz);
                        if($blockId == Block::STONE || $blockId == Block::DIRT || $blockId == Block::GRASS){
                            $this->level->setBlockIdAt($wx, $wy, $wz, Block::AIR);
                        }
                    }
                }
            }
        }
    }

    /**
     * 获取方向向量
     */
    private function getDirectionVector(int $yaw, int $pitch): Vector3{
        $y = -sin(deg2rad($pitch));
        $xz = cos(deg2rad($pitch));
        $x = -$xz * sin(deg2rad($yaw));
        $z = $xz * cos(deg2rad($yaw));

        return (new Vector3($x, $y, $z))->normalize();
    }
}