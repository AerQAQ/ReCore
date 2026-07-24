<?php

namespace pocketmine\level\generator\populator;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;

class Mineshaft extends Populator{

    const DIR_XPLUS = 0;
    const DIR_XMIN = 1;
    const DIR_ZPLUS = 2;
    const DIR_ZMIN = 3;

    /** @var ChunkManager */
    private $level;
    /** @var Random */
    private $random;
    /** @var int */
    private $maxPath;

    public function populate(ChunkManager $level, $chunkX, $chunkZ, Random $random){
        $this->level = $level;
        $this->random = $random;

        // 20% 概率生成
        if($random->nextBoundedInt(5) != 0){
            return;
        }

        $x = $random->nextRange($chunkX << 4, ($chunkX << 4) + 15);
        $z = $random->nextRange($chunkZ << 4, ($chunkZ << 4) + 15);
        $y = $random->nextRange(10, 50);

        // 中心区域
        $this->fill(new Vector3($x - 6, $y, $z - 6), new Vector3($x + 6, $y + 8, $z + 6), Block::AIR);
        $this->fill(new Vector3($x - 6, $y, $z - 6), new Vector3($x + 6, $y, $z + 6), Block::DIRT);

        $this->maxPath = $random->nextBoundedInt(100) + 50;

        // 4个方向生成走廊
        $dirs = [self::DIR_XPLUS, self::DIR_XMIN, self::DIR_ZPLUS, self::DIR_ZMIN];
        foreach($dirs as $dir){
            $this->generateShaft($x, $y, $z, $dir);
        }
    }

    private function generateShaft(int $x, int $y, int $z, int $dir, int $depth = 0){
        if($this->maxPath-- < 1 || $depth > 30){
            return;
        }

        // 生成走廊
        $this->generateCorridor($x, $y, $z, $dir);

        // 继续延伸（80%概率）
        if($this->random->nextBoundedInt(5) != 0){
            $nextX = $x;
            $nextZ = $z;
            switch($dir){
                case self::DIR_XPLUS: $nextX += 5; break;
                case self::DIR_XMIN: $nextX -= 5; break;
                case self::DIR_ZPLUS: $nextZ += 5; break;
                case self::DIR_ZMIN: $nextZ -= 5; break;
            }
            $this->generateShaft($nextX, $y, $nextZ, $dir, $depth + 1);
        }

        // 生成分支（30%概率）
        if($this->random->nextBoundedInt(3) == 0){
            $branchDir = $this->random->nextBoundedInt(4);
            // 不往回走
            if(($dir == self::DIR_XPLUS && $branchDir == self::DIR_XMIN) ||
               ($dir == self::DIR_XMIN && $branchDir == self::DIR_XPLUS) ||
               ($dir == self::DIR_ZPLUS && $branchDir == self::DIR_ZMIN) ||
               ($dir == self::DIR_ZMIN && $branchDir == self::DIR_ZPLUS)){
                $branchDir = ($branchDir + 2) % 4;
            }
            $this->generateShaft($x, $y, $z, $branchDir, $depth + 1);
        }

        // 生成楼梯（10%概率）
        if($this->random->nextBoundedInt(10) == 0 && $y > 10){
            $this->generateStairs($x, $y, $z, $dir);
        }

        // 蜘蛛网
        for($i = 0; $i < $this->random->nextBoundedInt(5) + 2; $i++){
            $xx = $x + $this->random->nextBoundedInt(7) - 3;
            $yy = $y + $this->random->nextBoundedInt(4);
            $zz = $z + $this->random->nextBoundedInt(7) - 3;
            if($this->level->getBlockIdAt($xx, $yy, $zz) == Block::AIR){
                $this->level->setBlockIdAt($xx, $yy, $zz, Block::COBWEB);
            }
        }
    }

    private function generateCorridor(int $x, int $y, int $z, int $dir){
        $level = $this->level;
        $isX = ($dir == self::DIR_XPLUS || $dir == self::DIR_XMIN);

        // 清除空间
        if($isX){
            $this->fill(new Vector3($x, $y, $z - 1), new Vector3($x + 4, $y + 3, $z + 1), Block::AIR);
            $this->fillFloor(new Vector3($x, $y - 1, $z - 1), new Vector3($x + 4, $y - 1, $z + 1));
            // 铁轨
            for($i = 0; $i < 4; $i++){
                if($this->random->nextBoundedInt(3) != 0){
                    $level->setBlockIdAt($x + $i, $y, $z, Block::RAIL);
                    $level->setBlockDataAt($x + $i, $y, $z, 1);
                }
            }
        }else{
            $this->fill(new Vector3($x - 1, $y, $z), new Vector3($x + 1, $y + 3, $z + 4), Block::AIR);
            $this->fillFloor(new Vector3($x - 1, $y - 1, $z), new Vector3($x + 1, $y - 1, $z + 4));
            for($i = 0; $i < 4; $i++){
                if($this->random->nextBoundedInt(3) != 0){
                    $level->setBlockIdAt($x, $y, $z + $i, Block::RAIL);
                    $level->setBlockDataAt($x, $y, $z + $i, 0);
                }
            }
        }

        // 支柱和火把
        if($isX){
            $level->setBlockIdAt($x, $y, $z - 1, Block::FENCE);
            $level->setBlockIdAt($x, $y, $z + 1, Block::FENCE);
            $level->setBlockIdAt($x, $y + 1, $z - 1, Block::FENCE);
            $level->setBlockIdAt($x, $y + 1, $z + 1, Block::FENCE);
            $level->setBlockIdAt($x, $y + 2, $z - 1, Block::PLANK);
            $level->setBlockIdAt($x, $y + 2, $z, Block::PLANK);
            $level->setBlockIdAt($x, $y + 2, $z + 1, Block::PLANK);
            if($dir == self::DIR_XPLUS){
                $level->setBlockIdAt($x + 1, $y + 2, $z, Block::TORCH);
                $level->setBlockDataAt($x + 1, $y + 2, $z, 2);
            }else{
                $level->setBlockIdAt($x - 1, $y + 2, $z, Block::TORCH);
                $level->setBlockDataAt($x - 1, $y + 2, $z, 1);
            }
        }else{
            $level->setBlockIdAt($x - 1, $y, $z, Block::FENCE);
            $level->setBlockIdAt($x + 1, $y, $z, Block::FENCE);
            $level->setBlockIdAt($x - 1, $y + 1, $z, Block::FENCE);
            $level->setBlockIdAt($x + 1, $y + 1, $z, Block::FENCE);
            $level->setBlockIdAt($x - 1, $y + 2, $z, Block::PLANK);
            $level->setBlockIdAt($x, $y + 2, $z, Block::PLANK);
            $level->setBlockIdAt($x + 1, $y + 2, $z, Block::PLANK);
            if($dir == self::DIR_ZPLUS){
                $level->setBlockIdAt($x, $y + 2, $z + 1, Block::TORCH);
                $level->setBlockDataAt($x, $y + 2, $z + 1, 4);
            }else{
                $level->setBlockIdAt($x, $y + 2, $z - 1, Block::TORCH);
                $level->setBlockDataAt($x, $y + 2, $z - 1, 3);
            }
        }

        // 箱子（3%概率）
        if($this->random->nextBoundedInt(33) == 0){
            $this->placeChest($x, $y, $z, $dir);
        }

        // 洞穴蜘蛛刷怪笼（5%概率） 修正：MONSTER_SPAWNER
        if($this->random->nextBoundedInt(20) == 0){
            if($isX){
                $level->setBlockIdAt($x + 2, $y + 1, $z, Block::MONSTER_SPAWNER);
            }else{
                $level->setBlockIdAt($x, $y + 1, $z + 2, Block::MONSTER_SPAWNER);
            }
        }
    }

    private function generateStairs(int $x, int $y, int $z, int $dir){
        $level = $this->level;

        for($i = 0; $i < 4; $i++){
            switch($dir){
                case self::DIR_XPLUS:
                    $this->fill(new Vector3($x + $i, $y - $i - 1, $z - 2), new Vector3($x + $i, $y - $i + 3, $z + 2), Block::AIR);
                    $this->fillFloor(new Vector3($x + $i, $y - $i - 2, $z - 2), new Vector3($x + $i, $y - $i - 2, $z + 2));
                    break;
                case self::DIR_XMIN:
                    $this->fill(new Vector3($x - $i, $y - $i - 1, $z - 2), new Vector3($x - $i, $y - $i + 3, $z + 2), Block::AIR);
                    $this->fillFloor(new Vector3($x - $i, $y - $i - 2, $z - 2), new Vector3($x - $i, $y - $i - 2, $z + 2));
                    break;
                case self::DIR_ZPLUS:
                    $this->fill(new Vector3($x - 2, $y - $i - 1, $z + $i), new Vector3($x + 2, $y - $i + 3, $z + $i), Block::AIR);
                    $this->fillFloor(new Vector3($x - 2, $y - $i - 2, $z + $i), new Vector3($x + 2, $y - $i - 2, $z + $i));
                    break;
                case self::DIR_ZMIN:
                    $this->fill(new Vector3($x - 2, $y - $i - 1, $z - $i), new Vector3($x + 2, $y - $i + 3, $z - $i), Block::AIR);
                    $this->fillFloor(new Vector3($x - 2, $y - $i - 2, $z - $i), new Vector3($x + 2, $y - $i - 2, $z - $i));
                    break;
            }
        }
    }

    private function fill(Vector3 $pos1, Vector3 $pos2, int $blockId){
        for($x = min($pos1->x, $pos2->x); $x <= max($pos1->x, $pos2->x); $x++){
            for($y = min($pos1->y, $pos2->y); $y <= max($pos1->y, $pos2->y); $y++){
                for($z = min($pos1->z, $pos2->z); $z <= max($pos1->z, $pos2->z); $z++){
                    $this->level->setBlockIdAt($x, $y, $z, $blockId);
                }
            }
        }
    }

    private function fillFloor(Vector3 $pos1, Vector3 $pos2){
        for($x = min($pos1->x, $pos2->x); $x <= max($pos1->x, $pos2->x); $x++){
            for($z = min($pos1->z, $pos2->z); $z <= max($pos1->z, $pos2->z); $z++){
                $y = $pos1->y;
                if($this->level->getBlockIdAt($x, $y, $z) == Block::AIR){
                    $this->level->setBlockIdAt($x, $y, $z, Block::PLANK);
                    $this->level->setBlockDataAt($x, $y, $z, 0);
                }
            }
        }
    }

    private function placeChest(int $x, int $y, int $z, int $dir){
        $level = $this->level;
        $d = $this->random->nextBoolean() ? 1 : -1;
        $d2 = $this->random->nextBoolean() ? 2 : 4;

        switch($dir){
            case self::DIR_XPLUS:
                $level->setBlockIdAt($x + $d2, $y, $z + $d, Block::CHEST);
                break;
            case self::DIR_XMIN:
                $level->setBlockIdAt($x - $d2, $y, $z + $d, Block::CHEST);
                break;
            case self::DIR_ZPLUS:
                $level->setBlockIdAt($x + $d, $y, $z + $d2, Block::CHEST);
                break;
            case self::DIR_ZMIN:
                $level->setBlockIdAt($x + $d, $y, $z - $d2, Block::CHEST);
                break;
        }
    }
}