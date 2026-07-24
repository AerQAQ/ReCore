<?php

namespace pocketmine\level\generator;

use pocketmine\block\Block;
use pocketmine\block\CoalOre;
use pocketmine\block\DiamondOre;
use pocketmine\block\Dirt;
use pocketmine\block\GoldOre;
use pocketmine\block\Gravel;
use pocketmine\block\IronOre;
use pocketmine\block\LapisOre;
use pocketmine\block\RedstoneOre;
use pocketmine\block\Stone;
use pocketmine\block\Sand;
use pocketmine\block\StillWater;
use pocketmine\level\ChunkManager;
use pocketmine\level\generator\Generator;
use pocketmine\level\generator\noise\Simplex;
use pocketmine\level\generator\object\OreType;
use pocketmine\level\generator\populator\Cave;
use pocketmine\level\generator\populator\Ore;
use pocketmine\level\generator\populator\Populator;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;

class Sea extends Generator
{
    const NAME = "Sea";
    const SEA_LEVEL = 85;
    const BEDROCK_DEPTH = 5;

    /** @var Populator[] */
    protected $populators = [];
    /** @var ChunkManager */
    protected $level;
    /** @var Random */
    protected $random;

    protected $noiseDepth;
    protected $noiseMain;
    protected $noiseDetail;

    protected $heightMap = [];

    public function __construct(array $options = [])
    {

    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getWaterHeight(): int
    {
        return self::SEA_LEVEL;
    }

    public function getSettings()
    {
        return [];
    }

    public function init(ChunkManager $level, Random $random)
    {
        $this->level = $level;
        $this->random = $random;
        $seed = $this->level->getSeed();
        $this->random->setSeed($seed);

        $this->noiseDepth = new Simplex($this->random, 2, 1/128, 1/256);
        $this->noiseMain  = new Simplex($this->random, 4, 1/64, 1/128);
        $this->noiseDetail= new Simplex($this->random, 8, 1/32, 1/64);

        $this->random->setSeed($seed);

        $cave = new Cave();
        $this->populators[] = $cave;

        $ores = new Ore();
        $ores->setOreTypes([
            new OreType(new CoalOre(), 20, 16, 0, 128),
            new OreType(new IronOre(), 20, 8, 0, 64),
            new OreType(new RedstoneOre(), 8, 7, 0, 16),
            new OreType(new LapisOre(), 1, 6, 0, 32),
            new OreType(new GoldOre(), 2, 8, 0, 32),
            new OreType(new DiamondOre(), 1, 7, 0, 16),
            new OreType(new Dirt(), 20, 32, 0, 128),
            new OreType(new Stone(Stone::GRANITE), 20, 32, 0, 128),
            new OreType(new Stone(Stone::DIORITE), 20, 32, 0, 128),
            new OreType(new Stone(Stone::ANDESITE), 20, 32, 0, 128),
            new OreType(new Gravel(), 10, 16, 0, 128)
        ]);
        $this->populators[] = $ores;
    }

    private function calculateHeight(float $worldX, float $worldZ): float
    {
        $depth = $this->noiseDepth->getNoise2D($worldX * 0.005, $worldZ * 0.005);
        $main  = $this->noiseMain->getNoise2D($worldX * 0.02, $worldZ * 0.02);
        $detail = $this->noiseDetail->getNoise2D($worldX * 0.08, $worldZ * 0.08) * 0.3;

        $baseHeight = 50 + $depth * 30;
        $variation = 15 + $main * 5;
        $noiseValue = ($main + $detail) * $variation + $baseHeight;

        if ($noiseValue > self::SEA_LEVEL - 5) {
            $extra = ($noiseValue - (self::SEA_LEVEL - 5)) * 1.5;
            $noiseValue = self::SEA_LEVEL - 5 + $extra;
        }

        return max(1, min(127, $noiseValue));
    }

    public function generateChunk($chunkX, $chunkZ)
    {
        $this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->getSeed());
        $chunk = $this->level->getChunk($chunkX, $chunkZ);

        for ($x = 0; $x < 16; ++$x) {
            for ($z = 0; $z < 16; ++$z) {
                $chunk->setBiomeId($x, $z, 0);
                $chunk->setBiomeColor($x, $z, 0, 0, 0);
            }
        }

        $baseX = $chunkX * 16;
        $baseZ = $chunkZ * 16;

        $corners = [];
        for ($gx = 0; $gx < 5; ++$gx) {
            for ($gz = 0; $gz < 5; ++$gz) {
                $wx = $baseX + $gx * 4;
                $wz = $baseZ + $gz * 4;
                $corners[$gx][$gz] = $this->calculateHeight($wx, $wz);
            }
        }

        $heights = [];
        for ($x = 0; $x < 16; ++$x) {
            for ($z = 0; $z < 16; ++$z) {
                $gx = intdiv($x, 4);
                $gz = intdiv($z, 4);
                $dx = ($x % 4) / 4.0;
                $dz = ($z % 4) / 4.0;

                $h00 = $corners[$gx][$gz];
                $h10 = $corners[$gx + 1][$gz];
                $h01 = $corners[$gx][$gz + 1];
                $h11 = $corners[$gx + 1][$gz + 1];

                $height = (1 - $dx) * (1 - $dz) * $h00
                        + $dx * (1 - $dz) * $h10
                        + (1 - $dx) * $dz * $h01
                        + $dx * $dz * $h11;

                $heights[$x][$z] = (int) round($height);
            }
        }

        for ($x = 0; $x < 16; ++$x) {
            for ($z = 0; $z < 16; ++$z) {
                $surface = $heights[$x][$z];

                $chunk->setBlockId($x, 0, $z, Block::BEDROCK);
                if ($surface > 1) {
                    $chunk->setBlockId($x, 1, $z, Block::BEDROCK);
                }


                for ($y = 2; $y < $surface; ++$y) {
                    $chunk->setBlockId($x, $y, $z, Block::STONE);
                }

                if ($surface >= 2) {
                     if ($surface >= self::SEA_LEVEL + 3) {
                        $chunk->setBlockId($x, $surface - 1, $z, Block::GRASS);
                        if ($surface - 2 > 0) $chunk->setBlockId($x, $surface - 2, $z, Block::DIRT);
                        if ($surface - 3 > 0) $chunk->setBlockId($x, $surface - 3, $z, Block::DIRT);
                    } elseif ($surface >= self::SEA_LEVEL - 5) {
                        $chunk->setBlockId($x, $surface - 1, $z, Block::SAND);
                        if ($surface - 2 > 0) $chunk->setBlockId($x, $surface - 2, $z, Block::SAND);
                        if ($surface - 3 > 0) $chunk->setBlockId($x, $surface - 3, $z, Block::GRAVEL);
                        if ($surface - 4 > 0) $chunk->setBlockId($x, $surface - 4, $z, Block::GRAVEL);
                    } else {
                        $chunk->setBlockId($x, $surface - 1, $z, Block::GRAVEL);
                        if ($surface - 2 > 0) $chunk->setBlockId($x, $surface - 2, $z, Block::GRAVEL);
                    }
                }

                if ($surface < self::SEA_LEVEL) {
                    for ($y = $surface; $y < self::SEA_LEVEL; ++$y) {
                        if ($chunk->getBlockId($x, $y, $z) === 0) {
                            $chunk->setBlockId($x, $y, $z, Block::STILL_WATER);
                        }
                    }
                }
            }
        }
    }

    public function populateChunk($chunkX, $chunkZ)
    {
        $this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->getSeed());
        foreach ($this->populators as $populator) {
            $populator->populate($this->level, $chunkX, $chunkZ, $this->random);
        }
    }

    public function getSpawn()
    {
        return new Vector3(127.5, self::SEA_LEVEL + 2, 127.5);
    }
}