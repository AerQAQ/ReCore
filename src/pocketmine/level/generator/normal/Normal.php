<?php

namespace pocketmine\level\generator\normal;

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
use pocketmine\level\ChunkManager;
use pocketmine\level\generator\biome\Biome;
use pocketmine\level\generator\biome\BiomeSelector;
use pocketmine\level\generator\Generator;
use pocketmine\level\generator\noise\Perlin;
use pocketmine\level\generator\noise\Simplex;
use pocketmine\level\generator\normal\biome\NormalBiome;
use pocketmine\level\generator\object\OreType;
use pocketmine\level\generator\populator\GroundCover;
use pocketmine\level\generator\populator\Cave;
use pocketmine\level\generator\populator\Mineshaft;
use pocketmine\level\generator\populator\Ore;
use pocketmine\level\generator\populator\Populator;
use pocketmine\level\generator\populator\TallGrass;
use pocketmine\level\generator\populator\Tree;
use pocketmine\level\Level;
use pocketmine\math\Vector3 as Vector3;
use pocketmine\utils\Random;

class Normal extends Generator{
	const NAME = "Normal";

	/** @var Populator[] */
	private $populators = [];
	/** @var ChunkManager */
	private $level;
	/** @var Random */
	private $random;
	private $waterHeight = 50;
	private $bedrockDepth = 6;

	/** @var Populator[] */
	private $generationPopulators = [];
	/** @var Simplex */
	private $noiseBase;

	/** @var BiomeSelector */
	private $selector;

	private static $GAUSSIAN_KERNEL = null;
	private static $SMOOTH_SIZE = 4;

	public function __construct(array $options = []){
		if(self::$GAUSSIAN_KERNEL === null){
			self::generateKernel();
		}
	}

	private static function generateKernel(){
		self::$GAUSSIAN_KERNEL = [];

		$bellSize = 1 / self::$SMOOTH_SIZE;
		$bellHeight = 2 * self::$SMOOTH_SIZE;

		for($sx = -self::$SMOOTH_SIZE; $sx <= self::$SMOOTH_SIZE; ++$sx){
			self::$GAUSSIAN_KERNEL[$sx + self::$SMOOTH_SIZE] = [];

			for($sz = -self::$SMOOTH_SIZE; $sz <= self::$SMOOTH_SIZE; ++$sz){
				$bx = $bellSize * $sx;
				$bz = $bellSize * $sz;
				self::$GAUSSIAN_KERNEL[$sx + self::$SMOOTH_SIZE][$sz + self::$SMOOTH_SIZE] = $bellHeight * exp(-($bx * $bx + $bz * $bz) / 2);
			}
		}
	}

	public function getName() : string{
		return self::NAME;
	}

	public function getWaterHeight() : int{
		return $this->waterHeight;
	}

	public function getSettings(){
		return [];
	}

	public function getTemperature($x, $z){
		$hash = $x * 2345803 ^ $z * 9236449 ^ $this->level->getSeed();
		$hash *= $hash + 223;
		$xNoise = $hash >> 20 & 3;
		$zNoise = $hash >> 22 & 3;
		if($xNoise == 3){
			$xNoise = 1;
		}
		if($zNoise == 3){
			$zNoise = 1;
		}

		return $this->selector->getTemperature($x + $xNoise - 1, $z + $zNoise - 1);
	}

	public function pickBiome($x, $z){
		$hash = $x * 2345803 ^ $z * 9236449 ^ $this->level->getSeed();
		$hash *= $hash + 223;
		$xNoise = $hash >> 20 & 3;
		$zNoise = $hash >> 22 & 3;
		if($xNoise == 3){
			$xNoise = 1;
		}
		if($zNoise == 3){
			$zNoise = 1;
		}

		return $this->selector->pickBiome($x + $xNoise - 1, $z + $zNoise - 1);
	}

	public function init(ChunkManager $level, Random $random){
		$this->level = $level;
		$this->random = $random;
		$this->random->setSeed($this->level->getSeed());
		$this->noiseBase = new Simplex($this->random, 4, 1 / 4, 1 / 32);
		$this->random->setSeed($this->level->getSeed());
		$this->selector = new BiomeSelector($this->random, function($temperature, $rainfall, $River, $ocean, $hills){ //此算法从pnx抄袭
			if ($ocean < -0.15) {
				if ($ocean < -0.91) {
					if ($ocean < -0.92) {
						return Biome::MUSHROOM_ISLAND;
					}else{
						return Biome::MUSHROOM_ISLAND_SHORE;
					}
					
				}else{
					if($temperature < -0.4){
						return Biome::FROZEN_OCEAN;
					}else{
						if ($rainfall < 0) {
							return Biome::OCEAN;
						}else{
							return Biome::DEEP_OCEAN;
						}
						
					}
				}
			}elseif(abs($River) <= 0.02){
				if($temperature < -0.36){
					return Biome::FROZEN_RIVER;
				}else{
					return Biome::RIVER;
				}
				
			} elseif($ocean < -0.12) {
				if($temperature < -0.379){
					return Biome::COLD_BEACH;
				}else{
					return Biome::BEACH;
				}
			}else{
				if($temperature < -0.379){ //freezing
					if ($rainfall < 0) {
						if($hills < -0.1){
							return Biome::COLD_TAIGA;
						}else{
							if($hills < -0.3){
								return Biome::COLD_TAIGA_HILLS;
							}else{
								return Biome::ICE_MOUNTAINS;
							}
							
						}
					}else{
						if($hills < 0.7){
							return Biome::ICE_PLAINS;
						}else{
							//冰刺平原
							return Biome::ICE_PLAINS;
						}
					}
				}elseif($temperature < 0){ //clod
					if($hills < 0){
						return Biome::MOUNTAINS;
					}elseif($hills < 0.2){
						return Biome::TAIGA_HILLS;
					}else{
						if ($rainfall < 0.6) {
							//原始松木针叶林
							return Biome::TAIGA;
						}else{
							return Biome::TAIGA;
						}
						
					}
					
				}elseif($temperature < 0.5){ //normal
				
					if ($temperature < 0.25) {
						if ($rainfall < 0) {
							return Biome::PLAINS;
						}elseif($rainfall < 0.25){
							return Biome::FOREST;
						}elseif($rainfall < 0.35){
							return Biome::GOLDEN_FOREST;
						}else{
							return Biome::BIRCH_FOREST;
						}
					}else{
						if ($rainfall < -0.3) {
							return Biome::SWAMP;
						}elseif($rainfall > 0){
							if($hills < 0){
								return Biome::JUNGLE;
							}else{
								return Biome::JUNGLE;
							}
						}else{
							return Biome::ROOFED_FOREST;
						}
					}
				}else{ //hot
					if ($rainfall < 0) {
						return Biome::DESERT;
					}elseif($rainfall > 0.4){
						return Biome::SAVANNA;
					}else{
						return Biome::MESA;
					}
				}
			}
		}, Biome::getBiome(Biome::PLAINS));
		
		$this->selector->addBiome(Biome::getBiome(Biome::OCEAN));
		$this->selector->addBiome(Biome::getBiome(Biome::FROZEN_OCEAN));
		$this->selector->addBiome(Biome::getBiome(Biome::DEEP_OCEAN));
		$this->selector->addBiome(Biome::getBiome(Biome::PLAINS));
		$this->selector->addBiome(Biome::getBiome(Biome::DESERT));
		$this->selector->addBiome(Biome::getBiome(Biome::MOUNTAINS));
		$this->selector->addBiome(Biome::getBiome(Biome::FOREST));
		$this->selector->addBiome(Biome::getBiome(Biome::TAIGA));
		$this->selector->addBiome(Biome::getBiome(Biome::TAIGA_HILLS));
		$this->selector->addBiome(Biome::getBiome(Biome::COLD_TAIGA));
		$this->selector->addBiome(Biome::getBiome(Biome::COLD_TAIGA_HILLS));
		$this->selector->addBiome(Biome::getBiome(Biome::SWAMP));
		$this->selector->addBiome(Biome::getBiome(Biome::RIVER));
		$this->selector->addBiome(Biome::getBiome(Biome::FROZEN_RIVER));
		$this->selector->addBiome(Biome::getBiome(Biome::ICE_PLAINS));
		$this->selector->addBiome(Biome::getBiome(Biome::ICE_MOUNTAINS));
		$this->selector->addBiome(Biome::getBiome(Biome::SMALL_MOUNTAINS));
		$this->selector->addBiome(Biome::getBiome(Biome::BIRCH_FOREST));
		$this->selector->addBiome(Biome::getBiome(Biome::BEACH));
		$this->selector->addBiome(Biome::getBiome(Biome::COLD_BEACH));
		$this->selector->addBiome(Biome::getBiome(Biome::SAVANNA));
		$this->selector->addBiome(Biome::getBiome(Biome::JUNGLE));
		$this->selector->addBiome(Biome::getBiome(Biome::MESA));
		$this->selector->addBiome(Biome::getBiome(Biome::MUSHROOM_ISLAND));
		$this->selector->addBiome(Biome::getBiome(Biome::MUSHROOM_ISLAND_SHORE));
		$this->selector->addBiome(Biome::getBiome(Biome::ROOFED_FOREST));
		$this->selector->addBiome(Biome::getBiome(Biome::GOLDEN_FOREST));

		$this->selector->recalculate();

		$cover = new GroundCover();
		$this->generationPopulators[] = $cover;
		
		$Cave = new Cave();
		$this->generationPopulators[] = $Cave;
		
		$Mineshaft = new Mineshaft();
		$this->populators[] = $Mineshaft;

		$ores = new Ore();
		$ores->setOreTypes([
			new OreType(new CoalOre(), 20, 16, 0, 128),
			new OreType(New IronOre(), 20, 8, 0, 64),
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

	public function generateChunk($chunkX, $chunkZ){
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->getSeed());

		$noise = Generator::getFastNoise3D($this->noiseBase, 16, 128, 16, 4, 8, 4, $chunkX * 16, 0, $chunkZ * 16);

		$chunk = $this->level->getChunk($chunkX, $chunkZ);

		$biomeCache = [];

		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				$minSum = 0;
				$maxSum = 0;
				$weightSum = 0;

				$biome = $this->pickBiome($chunkX * 16 + $x, $chunkZ * 16 + $z);
				$chunk->setBiomeId($x, $z, $biome->getId());
				$color = [0, 0, 0];

				for($sx = -self::$SMOOTH_SIZE; $sx <= self::$SMOOTH_SIZE; ++$sx){
					for($sz = -self::$SMOOTH_SIZE; $sz <= self::$SMOOTH_SIZE; ++$sz){

						$weight = self::$GAUSSIAN_KERNEL[$sx + self::$SMOOTH_SIZE][$sz + self::$SMOOTH_SIZE];

						if($sx === 0 and $sz === 0){
							$adjacent = $biome;
						}else{
							$index = Level::chunkHash($chunkX * 16 + $x + $sx, $chunkZ * 16 + $z + $sz);
							if(isset($biomeCache[$index])){
								$adjacent = $biomeCache[$index];
							}else{
								$biomeCache[$index] = $adjacent = $this->pickBiome($chunkX * 16 + $x + $sx, $chunkZ * 16 + $z + $sz);
							}
						}

						$minSum += ($adjacent->getMinElevation() - 1) * $weight;
						$maxSum += $adjacent->getMaxElevation() * $weight;
						$bColor = $adjacent->getColor();
						$color[0] += (($bColor >> 16) ** 2) * $weight;
						$color[1] += ((($bColor >> 8) & 0xff) ** 2) * $weight;
						$color[2] += (($bColor & 0xff) ** 2) * $weight;

						$weightSum += $weight;
					}
				}

				$minSum /= $weightSum;
				$maxSum /= $weightSum;

				$chunk->setBiomeColor($x, $z, sqrt($color[0] / $weightSum), sqrt($color[1] / $weightSum), sqrt($color[2] / $weightSum));

				$solidLand = false;
				for($y = 127; $y >= 0; --$y){
					if($y <= 5 && ($y == 0 or $this->random->nextRange(1, 5) == 1)){
						$chunk->setBlockId($x, $y, $z, Block::BEDROCK);
						continue;
					}

					// A noiseAdjustment of 1 will guarantee ground, a noiseAdjustment of -1 will guarantee air.
					//$effHeight = min($y - $smoothHeight - $minSum,
					$noiseAdjustment = 2 * (($maxSum - $y) / ($maxSum - $minSum)) - 1;

					$noiseAdjustment = min($noiseAdjustment, 0.4 + ($y / 10));
					$noiseValue = $noise[$x][$z][$y] + $noiseAdjustment;

					if($noiseValue > 0){
						$chunk->setBlockId($x, $y, $z, Block::STONE);
						$solidLand = true;
					}elseif($y <= $this->waterHeight && $solidLand == false){
						if($y == $this->waterHeight){
							$ID = $adjacent->getID();
							switch($ID){
								case 10:
								case 11:
								case 12:
								case 13:
								case 26:
								case 30:
								case 31:
									$chunk->setBlockId($x, $y, $z, Block::ICE);
									break;
								default:
									$chunk->setBlockId($x, $y, $z, Block::WATER);
									break;
							}
							
						}else{
							$chunk->setBlockId($x, $y, $z, Block::WATER);
						}
					}
				}
			}
		}

		foreach($this->generationPopulators as $populator){
			$populator->populate($this->level, $chunkX, $chunkZ, $this->random);
		}
	}

	public function populateChunk($chunkX, $chunkZ){
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->getSeed());
		foreach($this->populators as $populator){
			$populator->populate($this->level, $chunkX, $chunkZ, $this->random);
		}

		$chunk = $this->level->getChunk($chunkX, $chunkZ);
		$biome = Biome::getBiome($chunk->getBiomeId(7, 7));
		$biome->populateChunk($this->level, $chunkX, $chunkZ, $this->random);
	}

	public function getSpawn(){
		return new Vector3(127.5, 128, 127.5);
	}

}