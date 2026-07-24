<?php

namespace pocketmine\level\generator\biome;

use pocketmine\level\generator\noise\Simplex;
use pocketmine\utils\Random;

class BiomeSelector{

	/** @var Biome */
	private $fallback;

	/** @var Simplex */
	private $temperature;
	/** @var Simplex */
	private $rainfall;
	
	private $river;
	
	private $ocean;
	
	private $hills;

	/** @var Biome[] */
	private $biomes = [];

	private $map = [];

	private $lookup;

	public function __construct(Random $random, callable $lookup, Biome $fallback){
		$this->fallback = $fallback;
		$this->lookup = $lookup;
		$this->temperature = new Simplex($random, 2, 1 / 8, 1 / 2048);
		$this->rainfall = new Simplex($random, 2, 1 / 8, 1 / 2048);
		$this->river = new Simplex($random, 6, 1 / 2, 1 / 1024);
		$this->ocean = new Simplex($random, 6, 1 / 2, 1 / 2048);
		$this->hills = new Simplex($random, 2, 1 / 2, 1 / 2048);
	}

	public function recalculate(){
		/*$this->map = new \SplFixedArray(64 * 64);

		for($i = 0; $i < 64; ++$i){
			for($j = 0; $j < 64; ++$j){
				$this->map[$i + ($j << 6)] = call_user_func($this->lookup, $i / 63, $j / 63);
			}
		}*/
	}
	
	

	public function addBiome(Biome $biome){
		$this->biomes[$biome->getId()] = $biome;
	}

	public function getTemperature($x, $z){
		return $this->temperature->noise2D($x, $z, true);
	}

	public function getRainfall($x, $z){
		return $this->rainfall->noise2D($x, $z, true);
	}
	
	public function getRiver($x, $z){
		return $this->river->noise2D($x, $z, true);
	}
	
	public function getOcean($x, $z){
		return $this->ocean->noise2D($x, $z, true);
	}
	
	public function getHills($x, $z){
		return $this->hills->noise2D($x, $z, true);
	}

	/**
	 * @param $x
	 * @param $z
	 *
	 * @return Biome
	 */
	public function pickBiome($x, $z){
		$temperature = $this->getTemperature($x, $z);
		$rainfall = $this->getRainfall($x, $z);
		$River = $this->getRiver($x, $z);
		$ocean = $this->getOcean($x, $z);
		$hills = $this->getHills($x, $z);

		//$biomeId = $this->map[$temperature + ($rainfall << 6)];
		$biomeId = call_user_func($this->lookup, $temperature, $rainfall, $River, $ocean, $hills);
		return isset($this->biomes[$biomeId]) ? $this->biomes[$biomeId] : $this->fallback;
	}
}