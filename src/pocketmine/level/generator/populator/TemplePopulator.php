<?php

namespace pocketmine\level\generator\populator;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;

class TemplePopulator extends Populator{

    /** @var ChunkManager */
    private $level;

    public function populate(ChunkManager $level, $chunkX, $chunkZ, Random $random){
        $this->level = $level;

        // 1% 概率生成神殿 (每100个区块生成1个)
        if($random->nextBoundedInt(100) != 0){
            return;
        }

        $x = $random->nextRange($chunkX << 4, ($chunkX << 4) + 15);
        $z = $random->nextRange($chunkZ << 4, ($chunkZ << 4) + 15);
        $y = $this->getHighestWorkableBlock($x, $z);

        if($y > 0){
            $this->placeDesertTemple($x, $y, $z, $random);
        }
    }

    /**
     * 生成沙漠神殿
     */
    private function placeDesertTemple(int $x, int $y, int $z, Random $random){
        $level = $this->level;
        $y = $y - 1; // 向下挖一层

        $width = 15;
        $height = 7;
        $half = (int)($width / 2);

        // ====== 地板 ======
        for($dx = -$half; $dx <= $half; ++$dx){
            for($dz = -$half; $dz <= $half; ++$dz){
                $level->setBlockIdAt($x + $dx, $y, $z + $dz, Block::SANDSTONE);
                $level->setBlockDataAt($x + $dx, $y, $z + $dz, 0);
            }
        }

        // ====== 墙壁 ======
        for($dy = 1; $dy <= $height; ++$dy){
            for($dx = -$half; $dx <= $half; ++$dx){
                for($dz = -$half; $dz <= $half; ++$dz){
                    $wx = $x + $dx;
                    $wz = $z + $dz;
                    $isWall = (abs($dx) == $half || abs($dz) == $half);
                    $isCorner = (abs($dx) == $half && abs($dz) == $half);

                    if($isWall && !$isCorner){
                        $blockId = $level->getBlockIdAt($wx, $y + $dy, $wz);
                        if($blockId == Block::AIR || $blockId == Block::SAND || $blockId == Block::SANDSTONE){
                            $level->setBlockIdAt($wx, $y + $dy, $wz, Block::SANDSTONE);
                            $level->setBlockDataAt($wx, $y + $dy, $wz, 0);
                        }
                    }
                }
            }
        }

        // ====== 屋顶 ======
        for($dx = -$half; $dx <= $half; ++$dx){
            for($dz = -$half; $dz <= $half; ++$dz){
                $wx = $x + $dx;
                $wz = $z + $dz;
                $blockId = $level->getBlockIdAt($wx, $y + $height + 1, $wz);
                if($blockId == Block::AIR || $blockId == Block::SAND || $blockId == Block::SANDSTONE){
                    $level->setBlockIdAt($wx, $y + $height + 1, $wz, Block::SANDSTONE);
                    $level->setBlockDataAt($wx, $y + $height + 1, $wz, 0);
                }
            }
        }

        // ====== 四角柱子 ======
        $corners = [
            [-$half + 1, -$half + 1],
            [-$half + 1, $half - 1],
            [$half - 1, -$half + 1],
            [$half - 1, $half - 1]
        ];
        foreach($corners as $corner){
            for($dy = 1; $dy <= $height; ++$dy){
                $level->setBlockIdAt($x + $corner[0], $y + $dy, $z + $corner[1], Block::SANDSTONE);
                $level->setBlockDataAt($x + $corner[0], $y + $dy, $z + $corner[1], 0);
            }
            $level->setBlockIdAt($x + $corner[0], $y + $height + 1, $z + $corner[1], Block::SANDSTONE);
            $level->setBlockDataAt($x + $corner[0], $y + $height + 1, $z + $corner[1], 0);
        }

        // ====== 门口 ======
        for($dy = 1; $dy <= 3; ++$dy){
            for($dz = -1; $dz <= 1; ++$dz){
                $level->setBlockIdAt($x, $y + $dy, $z + $dz, Block::AIR);
            }
        }

        // ====== 内部宝藏室（在中心下方） ======
        $chestY = $y - 2;
        for($dx = -1; $dx <= 1; ++$dx){
            for($dz = -1; $dz <= 1; ++$dz){
                $level->setBlockIdAt($x + $dx, $chestY, $z + $dz, Block::SANDSTONE);
                $level->setBlockDataAt($x + $dx, $chestY, $z + $dz, 0);
            }
        }

        // 箱子
        $chestPositions = [
            [$x - 1, $chestY + 1, $z - 1],
            [$x + 1, $chestY + 1, $z - 1],
            [$x - 1, $chestY + 1, $z + 1],
            [$x + 1, $chestY + 1, $z + 1]
        ];
        foreach($chestPositions as $pos){
            if($level->getBlockIdAt($pos[0], $pos[1], $pos[2]) == Block::AIR){
                $level->setBlockIdAt($pos[0], $pos[1], $pos[2], Block::CHEST);
                $level->setBlockDataAt($pos[0], $pos[1], $pos[2], 0);
            }
        }

        // 压力板（触发陷阱）
        $level->setBlockIdAt($x, $y + 1, $z, Block::STONE_PRESSURE_PLATE);
        $level->setBlockDataAt($x, $y + 1, $z, 0);

        // TNT 陷阱
        $tntPositions = [
            [$x - 1, $y, $z - 1],
            [$x + 1, $y, $z - 1],
            [$x - 1, $y, $z + 1],
            [$x + 1, $y, $z + 1]
        ];
        foreach($tntPositions as $pos){
            if($level->getBlockIdAt($pos[0], $pos[1], $pos[2]) == Block::SANDSTONE){
                $level->setBlockIdAt($pos[0], $pos[1], $pos[2], Block::TNT);
            }
        }

        // ====== 装饰：砂岩楼梯（屋顶边缘） ======
        for($dx = -$half; $dx <= $half; ++$dx){
            for($dz = -$half; $dz <= $half; ++$dz){
                if(abs($dx) == $half || abs($dz) == $half){
                    $wx = $x + $dx;
                    $wz = $z + $dz;
                    if($level->getBlockIdAt($wx, $y + $height + 2, $wz) == Block::AIR){
                        $level->setBlockIdAt($wx, $y + $height + 2, $wz, Block::SANDSTONE_STAIRS);
                        $level->setBlockDataAt($wx, $y + $height + 2, $wz, $this->getStairMeta($dx, $dz));
                    }
                }
            }
        }
    }

    /**
     * 获取楼梯的朝向
     */
    private function getStairMeta(int $dx, int $dz): int{
        if($dx < 0) return 1;
        if($dx > 0) return 0;
        if($dz < 0) return 3;
        if($dz > 0) return 2;
        return 0;
    }

    /**
     * 获取最高可建筑方块
     */
    private function getHighestWorkableBlock(int $x, int $z): int{
        for($y = Level::Y_MAX - 1; $y > 0; --$y){
            $b = $this->level->getBlockIdAt($x, $y, $z);
            if($b == Block::SAND || $b == Block::SANDSTONE){
                return $y;
            }
        }
        return -1;
    }
}