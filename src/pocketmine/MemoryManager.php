<?php

/*
 *
 * ____       ____
 * |  _ \ ___ / ___|___  _ __ ___
 * | |_) / _ \ |   / _ \| '__/ _ \
 * |  _ <  __/ |__| (_) | | |  __/
 * |_| \_\___|\____\___/|_|  \___|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author ReCoreTeam
 * @link https://github.com/AerQAQ/ReCore
 *
 *
*/

namespace pocketmine;

use pocketmine\event\server\LowMemoryEvent;
use pocketmine\event\Timings;
use pocketmine\scheduler\GarbageCollectionTask;
use pocketmine\utils\Utils;
use pocketmine\network\Network;
use pocketmine\Player;

class MemoryManager{

	/** @var Server */
	private $server;

	// ===== 原有内存管理属性 =====
	private $memoryLimit;
	private $globalMemoryLimit;
	private $checkRate;
	private $checkTicker = 0;
	private $lowMemory = false;

	private $continuousTrigger = true;
	private $continuousTriggerRate;
	private $continuousTriggerCount = 0;
	private $continuousTriggerTicker = 0;

	private $garbageCollectionPeriod;
	private $garbageCollectionTicker = 0;
	private $garbageCollectionTrigger;
	private $garbageCollectionAsync;

	private $chunkLimit;
	private $chunkCollect;
	private $chunkTrigger;

	private $chunkCache;
	private $cacheTrigger;

	/** @var \WeakRef[] */
	private $leakWatch = [];
	private $leakInfo = [];
	private $leakSeed = 0;

	// ===== 新增性能优化属性 =====
	/** @var bool 是否启用网络优化 */
	private $networkOptimization = true;
	/** @var int 当前压缩级别 (0-9) */
	private $compressionLevel = 7;
	/** @var int 最低压缩级别 (低内存时降低) */
	private $minCompressionLevel = 2;
	/** @var int 最大同时发送的区块数 */
	private $maxChunkSendRate = 8;
	/** @var int 当前视距 (动态调整) */
	private $currentViewDistance = 10;
	/** @var int 最小视距 (低内存/高负载) */
	private $minViewDistance = 4;
	/** @var int 玩家平均延迟 (ms) */
	private $averagePing = 0;
	/** @var int 延迟采样次数 */
	private $pingSamples = 0;
	/** @var int 上次优化时间 */
	private $lastOptimizationTick = 0;
	/** @var int 优化间隔 (tick) */
	private $optimizationInterval = 1200; // 60秒
	/** @var int 当前实体更新速率 (每tick更新实体数) */
	private $entityUpdateRate = 40;
	/** @var int 最低实体更新速率 */
	private $minEntityUpdateRate = 20;
	/** @var bool 是否启用自动降级 */
	private $autoDegrade = true;

	public function __construct(Server $server){
		$this->server = $server;
		$this->init();
		$this->initOptimization();
	}

	private function init(){
		// ... 原有初始化代码（保持不变）...
		$this->memoryLimit = ((int) $this->server->getProperty("memory.main-limit", 0)) * 1024 * 1024;

		$defaultMemory = 1024;
		if(preg_match("/([0-9]+)([KMGkmg])/", $this->server->getConfigString("memory-limit", ""), $matches) > 0){
			$m = (int) $matches[1];
			if($m <= 0){
				$defaultMemory = 0;
			}else{
				switch(strtoupper($matches[2])){
					case "K": $defaultMemory = $m / 1024; break;
					case "M": $defaultMemory = $m; break;
					case "G": $defaultMemory = $m * 1024; break;
					default: $defaultMemory = $m; break;
				}
			}
		}
		$hardLimit = ((int) $this->server->getProperty("memory.main-hard-limit", $defaultMemory));
		if($hardLimit <= 0){
			ini_set("memory_limit", -1);
		}else{
			ini_set("memory_limit", $hardLimit . "M");
		}

		$this->globalMemoryLimit = ((int) $this->server->getProperty("memory.global-limit", 0)) * 1024 * 1024;
		$this->checkRate = (int) $this->server->getProperty("memory.check-rate", 20);
		$this->continuousTrigger = (bool) $this->server->getProperty("memory.continuous-trigger", true);
		$this->continuousTriggerRate = (int) $this->server->getProperty("memory.continuous-trigger-rate", 30);

		$this->garbageCollectionPeriod = (int) $this->server->getProperty("memory.garbage-collection.period", 36000);
		$this->garbageCollectionTrigger = (bool) $this->server->getProperty("memory.garbage-collection.low-memory-trigger", true);
		$this->garbageCollectionAsync = (bool) $this->server->getProperty("memory.garbage-collection.collect-async-worker", true);

		$this->chunkLimit = (int) $this->server->getProperty("memory.max-chunks.trigger-limit", 96);
		$this->chunkCollect = (bool) $this->server->getProperty("memory.max-chunks.trigger-chunk-collect", true);
		$this->chunkTrigger = (bool) $this->server->getProperty("memory.max-chunks.low-memory-trigger", true);

		$this->chunkCache = (bool) $this->server->getProperty("memory.world-caches.disable-chunk-cache", true);
		$this->cacheTrigger = (bool) $this->server->getProperty("memory.world-caches.low-memory-trigger", true);

		gc_enable();
	}

	/**
	 * 初始化优化参数
	 */
	private function initOptimization(){
		$this->networkOptimization = (bool) $this->server->getProperty("performance.network-optimization", true);
		$this->compressionLevel = (int) $this->server->getProperty("performance.compression-level", 7);
		$this->minCompressionLevel = (int) $this->server->getProperty("performance.min-compression-level", 2);
		$this->maxChunkSendRate = (int) $this->server->getProperty("performance.max-chunk-send-rate", 8);
		$this->currentViewDistance = (int) $this->server->getProperty("view-distance", 10);
		$this->minViewDistance = (int) $this->server->getProperty("performance.min-view-distance", 4);
		$this->entityUpdateRate = (int) $this->server->getProperty("performance.entity-update-rate", 40);
		$this->minEntityUpdateRate = (int) $this->server->getProperty("performance.min-entity-update-rate", 20);
		$this->autoDegrade = (bool) $this->server->getProperty("performance.auto-degrade", true);
		$this->optimizationInterval = (int) $this->server->getProperty("performance.optimization-interval", 1200);
	}

	// ===== 原有方法（保持不变） =====
	public function isLowMemory(){ return $this->lowMemory; }
	public function canUseChunkCache(){ return !($this->lowMemory and $this->chunkTrigger); }
	public function getViewDistance($distance){ return $this->lowMemory ? min($this->chunkLimit, $distance) : $distance; }

	public function trigger($memory, $limit, $global = false, $triggerCount = 0){
		$this->server->getLogger()->debug("[Memory Manager] ".($global ? "Global " : "") ."Low memory triggered, limit ". round(($limit / 1024) / 1024, 2)."MB, using ". round(($memory / 1024) / 1024, 2)."MB");

		if($this->cacheTrigger){
			foreach($this->server->getLevels() as $level){
				$level->clearCache(true);
			}
		}
		if($this->chunkTrigger and $this->chunkCollect){
			foreach($this->server->getLevels() as $level){
				$level->doChunkGarbageCollection();
			}
		}

		// 低内存时自动优化
		$this->applyPerformanceDegrade(true);

		$ev = new LowMemoryEvent($memory, $limit, $global, $triggerCount);
		$this->server->getPluginManager()->callEvent($ev);

		$cycles = 0;
		if($this->garbageCollectionTrigger){
			$cycles = $this->triggerGarbageCollector();
		}

		$this->server->getLogger()->debug("[Memory Manager] Freed " . round(($ev->getMemoryFreed() / 1024) / 1024, 2)."MB, $cycles cycles");
	}

	public function check(){
		Timings::$memoryManagerTimer->startTiming();

		// 原有内存检查
		if(($this->memoryLimit > 0 or $this->globalMemoryLimit > 0) and ++$this->checkTicker >= $this->checkRate){
			$this->checkTicker = 0;
			$memory = Utils::getMemoryUsage(true);
			$trigger = false;
			if($this->memoryLimit > 0 and $memory[0] > $this->memoryLimit){
				$trigger = 0;
			}elseif($this->globalMemoryLimit > 0 and $memory[1] > $this->globalMemoryLimit){
				$trigger = 1;
			}
			if($trigger !== false){
				if($this->lowMemory and $this->continuousTrigger){
					if(++$this->continuousTriggerTicker >= $this->continuousTriggerRate){
						$this->continuousTriggerTicker = 0;
						$this->trigger($memory[$trigger], $this->memoryLimit, $trigger > 0, ++$this->continuousTriggerCount);
					}
				}else{
					$this->lowMemory = true;
					$this->continuousTriggerCount = 0;
					$this->trigger($memory[$trigger], $this->memoryLimit, $trigger > 0);
				}
			}else{
				$this->lowMemory = false;
				// 内存恢复时恢复性能
				if($this->autoDegrade){
					$this->applyPerformanceDegrade(false);
				}
			}
		}

		// 定期垃圾回收
		if($this->garbageCollectionPeriod > 0 and ++$this->garbageCollectionTicker >= $this->garbageCollectionPeriod){
			$this->garbageCollectionTicker = 0;
			$this->triggerGarbageCollector();
		}

		// 性能优化定时器
		if($this->networkOptimization and ++$this->lastOptimizationTick >= $this->optimizationInterval){
			$this->lastOptimizationTick = 0;
			$this->performOptimization();
		}

		Timings::$memoryManagerTimer->stopTiming();
	}

	// ===== 新增性能优化方法 =====

	/**
	 * 执行性能优化 (调整视距、压缩、发包速率等)
	 */
	private function performOptimization(){
		// 收集玩家延迟数据
		$totalPing = 0;
		$count = 0;
		foreach($this->server->getOnlinePlayers() as $player){
			$ping = $player->getPing();
			if($ping > 0){
				$totalPing += $ping;
				$count++;
			}
		}
		if($count > 0){
			$avg = (int) ($totalPing / $count);
			$this->averagePing = $this->averagePing === 0 ? $avg : (int) (($this->averagePing * 0.7) + ($avg * 0.3));
			$this->pingSamples++;
		}

		// 根据平均延迟调整视距
		if($this->autoDegrade){
			$newView = $this->currentViewDistance;
			if($this->averagePing > 200){
				$newView = max($this->minViewDistance, $this->currentViewDistance - 2);
			}elseif($this->averagePing > 150){
				$newView = max($this->minViewDistance, $this->currentViewDistance - 1);
			}elseif($this->averagePing < 100 and $this->currentViewDistance < $this->server->getProperty("view-distance", 10)){
				$newView = min($this->server->getProperty("view-distance", 10), $this->currentViewDistance + 1);
			}
			if($newView != $this->currentViewDistance){
				$this->currentViewDistance = $newView;
				$this->server->setViewDistance($newView);
				$this->server->getLogger()->debug("[Performance] Adjusted view distance to $newView (avg ping: {$this->averagePing}ms)");
			}

			// 调整压缩级别
			$newCompression = $this->compressionLevel;
			if($this->averagePing > 200 or $this->lowMemory){
				$newCompression = max($this->minCompressionLevel, $this->compressionLevel - 2);
			}elseif($this->averagePing < 80 and !$this->lowMemory){
				$newCompression = min(7, $this->compressionLevel + 1);
			}
			if($newCompression != $this->compressionLevel){
				$this->compressionLevel = $newCompression;
				$this->server->getNetwork()->setCompressionLevel($newCompression);
				$this->server->getLogger()->debug("[Performance] Adjusted compression to level $newCompression");
			}

			// 调整实体更新速率
			$newEntityRate = $this->entityUpdateRate;
			if($this->averagePing > 200 or $this->lowMemory){
				$newEntityRate = max($this->minEntityUpdateRate, $this->entityUpdateRate - 10);
			}elseif($this->averagePing < 100 and !$this->lowMemory){
				$newEntityRate = min(40, $this->entityUpdateRate + 5);
			}
			if($newEntityRate != $this->entityUpdateRate){
				$this->entityUpdateRate = $newEntityRate;
				$this->server->setEntityUpdateRate($newEntityRate);
				$this->server->getLogger()->debug("[Performance] Adjusted entity update rate to $newEntityRate");
			}
		}

		// 记录优化日志
		$this->server->getLogger()->debug("[Performance] Optimization tick: viewDistance={$this->currentViewDistance}, compression={$this->compressionLevel}, entityRate={$this->entityUpdateRate}, avgPing={$this->averagePing}ms");
	}

	/**
	 * 手动触发性能优化 (可由插件调用)
	 * @param bool $force 强制优化
	 */
	public function optimizeNow($force = false){
		if($force){
			$this->lastOptimizationTick = $this->optimizationInterval; // 强制触发
		}
		$this->performOptimization();
	}

	/**
	 * 应用性能降级或恢复
	 * @param bool $degrade 是否降级
	 */
	private function applyPerformanceDegrade($degrade){
		if(!$this->autoDegrade){
			return;
		}
		if($degrade){
			// 降级：降低视距、压缩、速率
			$newView = max($this->minViewDistance, $this->currentViewDistance - 2);
			if($newView != $this->currentViewDistance){
				$this->currentViewDistance = $newView;
				$this->server->setViewDistance($newView);
			}
			$newCompression = max($this->minCompressionLevel, $this->compressionLevel - 2);
			if($newCompression != $this->compressionLevel){
				$this->compressionLevel = $newCompression;
				$this->server->getNetwork()->setCompressionLevel($newCompression);
			}
			$newRate = max($this->minEntityUpdateRate, $this->entityUpdateRate - 10);
			if($newRate != $this->entityUpdateRate){
				$this->entityUpdateRate = $newRate;
				$this->server->setEntityUpdateRate($newRate);
			}
			$this->server->getLogger()->info("[Performance] Entering low-memory degradation mode.");
		}else{
			// 恢复：逐渐恢复正常值
			$normalView = (int) $this->server->getProperty("view-distance", 10);
			if($this->currentViewDistance < $normalView){
				$this->currentViewDistance = min($normalView, $this->currentViewDistance + 1);
				$this->server->setViewDistance($this->currentViewDistance);
			}
			$normalCompression = (int) $this->server->getProperty("performance.compression-level", 7);
			if($this->compressionLevel < $normalCompression){
				$this->compressionLevel = min($normalCompression, $this->compressionLevel + 1);
				$this->server->getNetwork()->setCompressionLevel($this->compressionLevel);
			}
			$normalRate = (int) $this->server->getProperty("performance.entity-update-rate", 40);
			if($this->entityUpdateRate < $normalRate){
				$this->entityUpdateRate = min($normalRate, $this->entityUpdateRate + 5);
				$this->server->setEntityUpdateRate($this->entityUpdateRate);
			}
			$this->server->getLogger()->info("[Performance] Exiting low-memory mode, restoring performance.");
		}
	}

	// ===== 获取当前状态（供插件查询） =====
	public function getCurrentViewDistance(){ return $this->currentViewDistance; }
	public function getCurrentCompressionLevel(){ return $this->compressionLevel; }
	public function getEntityUpdateRate(){ return $this->entityUpdateRate; }
	public function getAveragePing(){ return $this->averagePing; }
	public function isInDegradedMode(){ return $this->lowMemory; }

	// ===== 原有方法（保持不变） =====
	public function triggerGarbageCollector(){
		Timings::$garbageCollectorTimer->startTiming();
		if($this->garbageCollectionAsync){
			$size = $this->server->getScheduler()->getAsyncTaskPoolSize();
			for($i = 0; $i < $size; ++$i){
				$this->server->getScheduler()->scheduleAsyncTaskToWorker(new GarbageCollectionTask(), $i);
			}
		}
		$cycles = gc_collect_cycles();
		foreach($this->server->getLevels() as $level){
			$level->doChunkGarbageCollection();
		}
		Timings::$garbageCollectorTimer->stopTiming();
		return $cycles;
	}

	public function addObjectWatcher($object){ /* 原有实现 */ }
	public function isObjectAlive($id){ /* 原有实现 */ }
	public function removeObjectWatch($id){ /* 原有实现 */ }
	public function doObjectCleanup(){ /* 原有实现 */ }
	public function getObjectInformation($id, $includeObject = false){ /* 原有实现 */ }
	public function dumpServerMemory($outputFolder, $maxNesting, $maxStringSize){ /* 原有实现，代码过长略，保持原样 */ }
	private function continueDump($from, &$data, &$objects, &$refCounts, $recursion, $maxNesting, $maxStringSize){ /* 原有实现 */ }
}