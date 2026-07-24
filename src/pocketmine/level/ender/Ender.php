<?php

namespace pocketmine\level\generator\ender;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\generator\biome\Biome;
use pocketmine\level\generator\Generator;
use pocketmine\level\generator\noise\Simplex;
use pocketmine\level\generator\populator\Populator;
use pocketmine\math\Vector3 as Vector3;
use pocketmine\utils\Random;

class Ender extends Generator
{

    /** @var Populator[] */
    private $populators = [];
    /** @var ChunkManager */
    private $level;
    /** @var Random */
    private $random;
    private $waterHeight = 0;
    private $emptyHeight = 32;
    private $emptyAmplitude = 1;
    private $density = 0.6;

    /** @var Populator[] */
    private $generationPopulators = [];
    /** @var Simplex */
    private $noiseBase;

    private static $GAUSSIAN_KERNEL = null;
    private static $SMOOTH_SIZE = 2;

    public function __construct(array $options = [])
    {
        if (self::$GAUSSIAN_KERNEL === null) {
            self::generateKernel();
        }
    }

    private static function generateKernel()
    {
        self::$GAUSSIAN_KERNEL = [];

        $bellSize = 1 / self::$SMOOTH_SIZE;
        $bellHeight = 2 * self::$SMOOTH_SIZE;

        for ($sx = -self::$SMOOTH_SIZE; $sx <= self::$SMOOTH_SIZE; ++$sx) {
            self::$GAUSSIAN_KERNEL[$sx + self::$SMOOTH_SIZE] = [];

            for ($sz = -self::$SMOOTH_SIZE; $sz <= self::$SMOOTH_SIZE; ++$sz) {
                $bx = $bellSize * $sx;
                $bz = $bellSize * $sz;
                self::$GAUSSIAN_KERNEL[$sx + self::$SMOOTH_SIZE][$sz + self::$SMOOTH_SIZE] = $bellHeight * exp(-($bx * $bx + $bz * $bz) / 2);
            }
        }
    }

    public function getName(): string
    {
        return "Ender";
    }

    public function getWaterHeight(): int
    {
        return $this->waterHeight;
    }

    public function getSettings()
    {
        return [];
    }

    public function init(ChunkManager $level, Random $random)
    {
        $this->level = $level;
        $this->random = $random;
        $this->random->setSeed($this->level->getSeed());
        $this->noiseBase = new Simplex($this->random, 4, 1 / 4, 1 / 64);
        $this->random->setSeed($this->level->getSeed());
        $pilar = new EnderPilar();
        $pilar->setBaseAmount(0);
        $pilar->setRandomAmount(0);
        $this->populators[] = $pilar;
    }

    public function generateChunk($chunkX, $chunkZ)
    {
        $this->random->setSeed(0xa6fe78dc ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->getSeed());

        $noise = Generator::getFastNoise3D($this->noiseBase, 16, 128, 16, 4, 8, 4, $chunkX * 16, 0, $chunkZ * 16);

        $chunk = $this->level->getChunk($chunkX, $chunkZ);

        for ($x = 0; $x < 16; ++$x) {
            for ($z = 0; $z < 16; ++$z) {

                $biome = Biome::getBiome(Biome::PLAINS);
                $biome->setGroundCover([
                    Block::get(Block::GRASS, 0)

                ]);
                $chunk->setBiomeId($x, $z, $biome->getId());
                $color = [0, 0, 0];
                $bColor = 2;
                $color[0] += (($bColor >> 16) ** 2);
                $color[1] += ((($bColor >> 8) & 0xff) ** 2);
                $color[2] += (($bColor & 0xff) ** 2);

                //$chunk->setBiomeColor($x, $z, $color[0], $color[1], $color[2]);

                for ($y = 0; $y < 128; ++$y) {

                    $noiseValue = (abs($this->emptyHeight - $y) / $this->emptyHeight) * $this->emptyAmplitude - $noise[$x][$z][$y];
                    $noiseValue -= 1 - $this->density;

                    $distance = new Vector3(0, 64, 0);
                    $distance = $distance->distance(new Vector3($chunkX * 16 + $x, ($y / 1.3), $chunkZ * 16 + $z));

                    if ($noiseValue < 0 && $distance < 100 or $noiseValue < -0.2 && $distance > 400) {
                        $chunk->setBlockId($x, $y, $z, Block::END_STONE);
                    }
                }
            }
        }

        foreach ($this->generationPopulators as $populator) {
            $populator->populate($this->level, $chunkX, $chunkZ, $this->random);
        }
    }

    public function populateChunk($chunkX, $chunkZ)
    {
        $this->random->setSeed(0xa6fe78dc ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->getSeed());
        foreach ($this->populators as $populator) {
            $populator->populate($this->level, $chunkX, $chunkZ, $this->random);
        }

        $chunk = $this->level->getChunk($chunkX, $chunkZ);
        $biome = Biome::getBiome($chunk->getBiomeId(7, 7));
        $biome->populateChunk($this->level, $chunkX, $chunkZ, $this->random);
    }

    public function getSpawn()
    {
        return new Vector3(0, 64, 0);
    }

}
class EnderPilar extends Populator
{
    /** @var ChunkManager */
    private $level;
    private $randomAmount;
    private $baseAmount;

    public function setRandomAmount($amount)
    {
        $this->randomAmount = $amount;
    }

    public function setBaseAmount($amount)
    {
        $this->baseAmount = $amount;
    }

    public function populate(ChunkManager $level, $chunkX, $chunkZ, Random $random)
    {
        if (mt_rand(0, 100) < 10) {
            $this->level = $level;
            $amount = $random->nextRange(0, $this->randomAmount + 1) + $this->baseAmount;
            for ($i = 0; $i < $amount; ++$i) {
                $x = $random->nextRange($chunkX * 16, $chunkX * 16 + 15);
                $z = $random->nextRange($chunkZ * 16, $chunkZ * 16 + 15);
                $y = $this->getHighestWorkableBlock($x, $z);
                if ($this->level->getBlockIdAt($x, $y, $z) == Block::END_STONE) {
                    $height = mt_rand(28, 50);
                    for ($ny = $y; $ny < $y + $height; $ny++) {
                        for ($r = 0.5; $r < 5; $r += 0.5) {
                            $nd = 360 / (2 * pi() * $r);
                            for ($d = 0; $d < 360; $d += $nd) {
                                $level->setBlockIdAt($x + (cos(deg2rad($d)) * $r), $ny, $z + (sin(deg2rad($d)) * $r), Block::OBSIDIAN);
                            }
                        }
                    }
                }
            }
        }
    }


    private function getHighestWorkableBlock($x, $z)
    {
        for ($y = 127; $y >= 0; --$y) {
            $b = $this->level->getBlockIdAt($x, $y, $z);
            if ($b == Block::END_STONE) {
                break;
            }
        }
        return $y === 0 ? -1 : $y;
    }
}
