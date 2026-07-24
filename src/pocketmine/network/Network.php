<?php

/**
 * 基于SCAXE核心的AXENET Network - 优化版
 * Network-related classes
 */
namespace pocketmine\network;

use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\AddItemEntityPacket;
use pocketmine\network\protocol\AddPaintingPacket;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\AdventureSettingsPacket;
use pocketmine\network\protocol\AnimatePacket;
use pocketmine\network\protocol\BatchPacket;
use pocketmine\network\protocol\ChunkRadiusUpdatePacket;
use pocketmine\network\protocol\ContainerClosePacket;
use pocketmine\network\protocol\ContainerOpenPacket;
use pocketmine\network\protocol\ContainerSetContentPacket;
use pocketmine\network\protocol\ContainerSetDataPacket;
use pocketmine\network\protocol\ContainerSetSlotPacket;
use pocketmine\network\protocol\CraftingDataPacket;
use pocketmine\network\protocol\CraftingEventPacket;
use pocketmine\network\protocol\ChangeDimensionPacket;
use pocketmine\network\protocol\DataPacket;
use pocketmine\network\protocol\DropItemPacket;
use pocketmine\network\protocol\FullChunkDataPacket;
use pocketmine\network\protocol\Info;
use pocketmine\network\protocol\ItemFrameDropItemPacket;
use pocketmine\network\protocol\RequestChunkRadiusPacket;
use pocketmine\network\protocol\SetEntityLinkPacket;
use pocketmine\network\protocol\BlockEntityDataPacket;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\network\protocol\ExplodePacket;
use pocketmine\network\protocol\HurtArmorPacket;
use pocketmine\network\protocol\Info as ProtocolInfo;
use pocketmine\network\protocol\InteractPacket;
use pocketmine\network\protocol\LevelEventPacket;
use pocketmine\network\protocol\DisconnectPacket;
use pocketmine\network\protocol\LoginPacket;
use pocketmine\network\protocol\PlayStatusPacket;
use pocketmine\network\protocol\TextPacket;
use pocketmine\network\protocol\TelemetryEventPacket;
use pocketmine\network\protocol\MoveEntityPacket;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\network\protocol\PlayerActionPacket;
use pocketmine\network\protocol\MobArmorEquipmentPacket;
use pocketmine\network\protocol\MobEquipmentPacket;
use pocketmine\network\protocol\RemoveBlockPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\network\protocol\RemovePlayerPacket;
use pocketmine\network\protocol\RespawnPacket;
use pocketmine\network\protocol\SetDifficultyPacket;
use pocketmine\network\protocol\SetEntityDataPacket;
use pocketmine\network\protocol\SetEntityMotionPacket;
use pocketmine\network\protocol\SetHealthPacket;
use pocketmine\network\protocol\SetPlayerGameTypePacket;
use pocketmine\network\protocol\SetSpawnPositionPacket;
use pocketmine\network\protocol\SetTimePacket;
use pocketmine\network\protocol\StartGamePacket;
use pocketmine\network\protocol\TakeItemEntityPacket;
use pocketmine\network\protocol\BlockEventPacket;
use pocketmine\network\protocol\UpdateBlockPacket;
use pocketmine\network\protocol\UseItemPacket;
use pocketmine\network\protocol\PlayerListPacket;
use pocketmine\network\protocol\PlayerInputPacket;
use pocketmine\network\protocol\MapInfoRequestPacket;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Binary;
use pocketmine\utils\MainLogger;

class Network {

	// ===== 优化1: 调整批量阈值 =====
	public static $BATCH_THRESHOLD = 1024; // 从512增加到1024，减少批次数量
	
	// ===== 优化2: 新增配置项 =====
	public static $COMPRESSION_LEVEL = 4; // 压缩级别 1-9，4是性能平衡点
	public static $MAX_BATCH_SIZE = 1024 * 1024 * 8; // 8MB 最大批次大小
	public static $ENABLE_ASYNC_COMPRESSION = true; // 启用异步压缩
	public static $PACKET_CACHE_ENABLED = true; // 启用数据包缓存
	public static $MAX_PACKET_CACHE = 1000; // 最大缓存数据包数

	/** @deprecated */
	const CHANNEL_NONE = 0;
	const CHANNEL_PRIORITY = 1;
	const CHANNEL_WORLD_CHUNKS = 2;
	const CHANNEL_MOVEMENT = 3;
	const CHANNEL_BLOCKS = 4;
	const CHANNEL_WORLD_EVENTS = 5;
	const CHANNEL_ENTITY_SPAWNING = 6;
	const CHANNEL_TEXT = 7;
	const CHANNEL_END = 31;

	/** @var \SplFixedArray */
	private $packetPool;

	/** @var Server */
	private $server;

	/** @var SourceInterface[] */
	private $interfaces = [];

	/** @var AdvancedSourceInterface[] */
	private $advancedInterfaces = [];

	private $upload = 0;
	private $download = 0;
	private $cleaned = 0;

	private $name;

	// ===== 优化3: 新增缓存和池化 =====
	private $packetCache = [];
	private $batchBuffer = [];
	private $batchFlushTimers = [];
	private $compressionWorkers = [];
	private $stats = [
		'packets_sent' => 0,
		'packets_received' => 0,
		'bytes_sent' => 0,
		'bytes_received' => 0,
		'batches_processed' => 0,
		'compression_time' => 0,
	];

	public function __construct(Server $server) {
		$this->registerPackets();
		$this->server = $server;
		
		// ===== 优化4: 初始化压缩工作线程 =====
		if(self::$ENABLE_ASYNC_COMPRESSION){
			$this->initCompressionWorkers();
		}
	}

	// ===== 优化5: 压缩工作线程池 =====
	private function initCompressionWorkers(){
		$workerCount = max(1, (int) (Server::getInstance()->getProperty("network.async-compression-workers", 2)));
		for($i = 0; $i < $workerCount; $i++){
			$this->compressionWorkers[$i] = new \Worker();
			$this->compressionWorkers[$i]->start();
		}
	}

	public function addStatistics($upload, $download, $cleaned = 0) {
		$this->upload += $upload;
		$this->download += $download;
		$this->cleaned += $cleaned;
	}

	public function getUpload() {
		return $this->upload;
	}

	public function getDownload() {
		return $this->download;
	}
	
	public function getCleaned() {
		return $this->cleaned;
	}

	public function resetStatistics() {
		$this->upload = 0;
		$this->download = 0;
		$this->cleaned = 0;
		$this->stats = array_fill_keys(array_keys($this->stats), 0);
	}

	/**
	 * @return SourceInterface[]
	 */
	public function getInterfaces() {
		return $this->interfaces;
	}

	// ===== 优化6: 改进接口处理 =====
	public function processInterfaces() {
		$startTime = microtime(true);
		$processed = 0;
		
		foreach ($this->interfaces as $interface) {
			try {
				$interface->process();
				$processed++;
				
				// 限制每tick处理时间
				if(microtime(true) - $startTime > 0.005){ // 5ms限制
					$this->server->getLogger()->debug("Network interface processing throttled");
					break;
				}
			} catch (\Throwable $e) {
				$logger = $this->server->getLogger();
				if (\pocketmine\DEBUG > 1) {
					if ($logger instanceof MainLogger) {
						$logger->logException($e);
					}
				}

				$interface->emergencyShutdown();
				$this->unregisterInterface($interface);
				$logger->critical($this->server->getLanguage()->translateString("pocketmine.server.networkError", [get_class($interface), $e->getMessage()]));
			}
		}
		
		// ===== 优化7: 批量刷新缓冲区 =====
		$this->flushAllBatches();
	}

	/**
	 * @param SourceInterface $interface
	 */
	public function registerInterface(SourceInterface $interface) {
		$this->interfaces[$hash = spl_object_hash($interface)] = $interface;
		if ($interface instanceof AdvancedSourceInterface) {
			$this->advancedInterfaces[$hash] = $interface;
			$interface->setNetwork($this);
		}
		$interface->setName($this->name);
	}

	/**
	 * @param SourceInterface $interface
	 */
	public function unregisterInterface(SourceInterface $interface) {
		unset($this->interfaces[$hash = spl_object_hash($interface)],
			$this->advancedInterfaces[$hash]);
	}

	/**
	 * Sets the server name shown on each interface Query
	 *
	 * @param string $name
	 */
	public function setName($name) {
		$this->name = (string)$name;
		foreach ($this->interfaces as $interface) {
			$interface->setName($this->name);
		}
	}

	public function getName() {
		return $this->name;
	}

	public function updateName() {
		foreach ($this->interfaces as $interface) {
			$interface->setName($this->name);
		}
	}

	/**
	 * @param int        $id 0-255
	 * @param DataPacket $class
	 */
	public function registerPacket($id, $class) {
		$this->packetPool[$id] = new $class;
	}

	public function getServer() {
		return $this->server;
	}

	// ===== 优化8: 优化Batch处理 =====
	public function processBatch(BatchPacket $packet, Player $p) {
		$startTime = microtime(true);
		$this->stats['batches_processed']++;
		
		try {
			// 使用更快的解压方式
			$str = zlib_decode($packet->payload, 1024 * 1024 * 64);
			if($str === false){
				throw new \Exception("Failed to decompress batch packet");
			}
			
			$len = strlen($str);
			$offset = 0;
			$packets = [];
			
			// 预解析所有数据包
			while ($offset < $len) {
				if($offset + 4 > $len) break;
				
				$pkLen = Binary::readInt(substr($str, $offset, 4));
				$offset += 4;
				
				if($offset + $pkLen > $len) break;
				
				$buf = substr($str, $offset, $pkLen);
				$offset += $pkLen;
				
				if(strlen($buf) < 2) continue;
				
				$pid = ord($buf[1]);
				$packets[] = ['pid' => $pid, 'buf' => $buf, 'offset' => 2];
			}
			
			// 批量处理数据包
			foreach($packets as $pktData){
				$pk = $this->getPacket($pktData['pid']);
				if($pk !== null){
					$pk->setBuffer($pktData['buf'], $pktData['offset']);
					$pk->decode();
					$p->handleDataPacket($pk);
					$this->stats['packets_received']++;
				}
			}
			
		} catch (\Throwable $e) {
			if (\pocketmine\DEBUG > 1) {
				$logger = $this->server->getLogger();
				if ($logger instanceof MainLogger) {
					$logger->debug("BatchPacket processing error");
					$logger->logException($e);
				}
			}
		}
		
		$this->stats['compression_time'] += microtime(true) - $startTime;
	}

	// ===== 优化9: 新增批量发送方法 =====
	public function addToBatch(Player $player, DataPacket $packet, $priority = self::CHANNEL_NONE){
		$key = spl_object_hash($player);
		
		if(!isset($this->batchBuffer[$key])){
			$this->batchBuffer[$key] = [];
			$this->batchFlushTimers[$key] = microtime(true);
		}
		
		$this->batchBuffer[$key][] = $packet;
		$this->stats['packets_sent']++;
		
		// 达到阈值时立即刷新
		if(count($this->batchBuffer[$key]) >= self::$BATCH_THRESHOLD){
			$this->flushBatch($player);
		}
	}

	private function flushBatch(Player $player){
		$key = spl_object_hash($player);
		
		if(empty($this->batchBuffer[$key])){
			return;
		}
		
		$packets = $this->batchBuffer[$key];
		$this->batchBuffer[$key] = [];
		
		// 创建BatchPacket
		$batch = new BatchPacket();
		$batch->setCompressionLevel(self::$COMPRESSION_LEVEL);
		
		// 合并数据包
		$payload = "";
		foreach($packets as $pk){
			$pk->encode();
			$payload .= Binary::writeInt(strlen($pk->getBuffer())) . $pk->getBuffer();
			$pk->isEncoded = false;
		}
		
		// ===== 优化10: 异步压缩 =====
		if(self::$ENABLE_ASYNC_COMPRESSION && !empty($this->compressionWorkers)){
			// 在worker线程中压缩
			$worker = $this->compressionWorkers[array_rand($this->compressionWorkers)];
			$worker->stack(new class($payload, $batch, $player) extends \Threaded {
				private $payload;
				private $batch;
				private $player;
				
				public function __construct($payload, $batch, $player){
					$this->payload = $payload;
					$this->batch = $batch;
					$this->player = $player;
				}
				
				public function run(){
					$this->batch->payload = zlib_encode($this->payload, ZLIB_ENCODING_RAW, Network::$COMPRESSION_LEVEL);
					$this->batch->isEncoded = true;
					// 直接发送
					$this->player->sendDataPacket($this->batch);
				}
			});
		}else{
			// 同步压缩
			$batch->payload = zlib_encode($payload, ZLIB_ENCODING_RAW, self::$COMPRESSION_LEVEL);
			$batch->isEncoded = true;
			$player->sendDataPacket($batch);
		}
		
		$this->stats['bytes_sent'] += strlen($batch->payload);
		unset($this->batchFlushTimers[$key]);
	}

	private function flushAllBatches(){
		$now = microtime(true);
		foreach(array_keys($this->batchBuffer) as $key){
			// 超时刷新 (100ms)
			if(isset($this->batchFlushTimers[$key]) && ($now - $this->batchFlushTimers[$key]) > 0.1){
				// 需要找到对应的Player对象
				foreach($this->server->getOnlinePlayers() as $player){
					if(spl_object_hash($player) === $key){
						$this->flushBatch($player);
						break;
					}
				}
			}
		}
	}

	/**
	 * @param $id
	 * @return DataPacket
	 */
	public function getPacket($id) {
		// ===== 优化11: 数据包缓存 =====
		if(self::$PACKET_CACHE_ENABLED && isset($this->packetCache[$id])){
			return clone $this->packetCache[$id];
		}
		
		/** @var DataPacket $class */
		$class = $this->packetPool[$id];
		if ($class !== null) {
			$packet = clone $class;
			if(self::$PACKET_CACHE_ENABLED && count($this->packetCache) < self::$MAX_PACKET_CACHE){
				$this->packetCache[$id] = $packet;
			}
			return $packet;
		}
		return null;
	}


	/**
	 * @param string $address
	 * @param int    $port
	 * @param string $payload
	 */
	public function sendPacket($address, $port, $payload) {
		foreach ($this->advancedInterfaces as $interface) {
			$interface->sendRawPacket($address, $port, $payload);
		}
	}

	/**
	 * Blocks an IP address from the main interface. Setting timeout to -1 will block it forever
	 *
	 * @param string $address
	 * @param int    $timeout
	 */
	public function blockAddress($address, $timeout = 300) {
		foreach ($this->advancedInterfaces as $interface) {
			$interface->blockAddress($address, $timeout);
		}
	}

	// ===== 优化12: 统计信息方法 =====
	public function getNetworkStats(){
		return $this->stats;
	}
	
	public function getCompressionRatio(){
		if($this->stats['bytes_received'] > 0){
			return round($this->stats['bytes_sent'] / max(1, $this->stats['bytes_received']), 2);
		}
		return 0;
	}
	
	public function getAverageCompressionTime(){
		if($this->stats['batches_processed'] > 0){
			return round(($this->stats['compression_time'] / $this->stats['batches_processed']) * 1000, 2);
		}
		return 0;
	}

	private function registerPackets() {
		$this->packetPool = new \SplFixedArray(256);

		$this->registerPacket(ProtocolInfo::LOGIN_PACKET, LoginPacket::class);
		$this->registerPacket(ProtocolInfo::PLAY_STATUS_PACKET, PlayStatusPacket::class);
		$this->registerPacket(ProtocolInfo::DISCONNECT_PACKET, DisconnectPacket::class);
		$this->registerPacket(ProtocolInfo::BATCH_PACKET, BatchPacket::class);
		$this->registerPacket(ProtocolInfo::TEXT_PACKET, TextPacket::class);
		$this->registerPacket(ProtocolInfo::SET_TIME_PACKET, SetTimePacket::class);
		$this->registerPacket(ProtocolInfo::START_GAME_PACKET, StartGamePacket::class);
		$this->registerPacket(ProtocolInfo::ADD_PLAYER_PACKET, AddPlayerPacket::class);
		$this->registerPacket(ProtocolInfo::REMOVE_PLAYER_PACKET, RemovePlayerPacket::class);
		$this->registerPacket(ProtocolInfo::ADD_ENTITY_PACKET, AddEntityPacket::class);
		$this->registerPacket(ProtocolInfo::REMOVE_ENTITY_PACKET, RemoveEntityPacket::class);
		$this->registerPacket(ProtocolInfo::ADD_ITEM_ENTITY_PACKET, AddItemEntityPacket::class);
		$this->registerPacket(ProtocolInfo::TAKE_ITEM_ENTITY_PACKET, TakeItemEntityPacket::class);
		$this->registerPacket(ProtocolInfo::MOVE_ENTITY_PACKET, MoveEntityPacket::class);
		$this->registerPacket(ProtocolInfo::MOVE_PLAYER_PACKET, MovePlayerPacket::class);
		$this->registerPacket(ProtocolInfo::REMOVE_BLOCK_PACKET, RemoveBlockPacket::class);
		$this->registerPacket(ProtocolInfo::UPDATE_BLOCK_PACKET, UpdateBlockPacket::class);
		$this->registerPacket(ProtocolInfo::ADD_PAINTING_PACKET, AddPaintingPacket::class);
		$this->registerPacket(ProtocolInfo::EXPLODE_PACKET, ExplodePacket::class);
		$this->registerPacket(ProtocolInfo::LEVEL_EVENT_PACKET, LevelEventPacket::class);
		$this->registerPacket(ProtocolInfo::BLOCK_EVENT_PACKET, BlockEventPacket::class);
		$this->registerPacket(ProtocolInfo::ENTITY_EVENT_PACKET, EntityEventPacket::class);
		$this->registerPacket(ProtocolInfo::MOB_EQUIPMENT_PACKET, MobEquipmentPacket::class);
		$this->registerPacket(ProtocolInfo::MOB_ARMOR_EQUIPMENT_PACKET, MobArmorEquipmentPacket::class);
		$this->registerPacket(ProtocolInfo::INTERACT_PACKET, InteractPacket::class);
		$this->registerPacket(ProtocolInfo::USE_ITEM_PACKET, UseItemPacket::class);
		$this->registerPacket(ProtocolInfo::PLAYER_ACTION_PACKET, PlayerActionPacket::class);
		$this->registerPacket(ProtocolInfo::HURT_ARMOR_PACKET, HurtArmorPacket::class);
		$this->registerPacket(ProtocolInfo::SET_ENTITY_DATA_PACKET, SetEntityDataPacket::class);
		$this->registerPacket(ProtocolInfo::SET_ENTITY_MOTION_PACKET, SetEntityMotionPacket::class);
		$this->registerPacket(ProtocolInfo::SET_ENTITY_LINK_PACKET, SetEntityLinkPacket::class);
		$this->registerPacket(ProtocolInfo::SET_HEALTH_PACKET, SetHealthPacket::class);
		$this->registerPacket(ProtocolInfo::SET_SPAWN_POSITION_PACKET, SetSpawnPositionPacket::class);
		$this->registerPacket(ProtocolInfo::ANIMATE_PACKET, AnimatePacket::class);
		$this->registerPacket(ProtocolInfo::RESPAWN_PACKET, RespawnPacket::class);
		$this->registerPacket(ProtocolInfo::DROP_ITEM_PACKET, DropItemPacket::class);
		$this->registerPacket(ProtocolInfo::CONTAINER_OPEN_PACKET, ContainerOpenPacket::class);
		$this->registerPacket(ProtocolInfo::CONTAINER_CLOSE_PACKET, ContainerClosePacket::class);
		$this->registerPacket(ProtocolInfo::CONTAINER_SET_SLOT_PACKET, ContainerSetSlotPacket::class);
		$this->registerPacket(ProtocolInfo::CONTAINER_SET_DATA_PACKET, ContainerSetDataPacket::class);
		$this->registerPacket(ProtocolInfo::CONTAINER_SET_CONTENT_PACKET, ContainerSetContentPacket::class);
		$this->registerPacket(ProtocolInfo::CRAFTING_DATA_PACKET, CraftingDataPacket::class);
		$this->registerPacket(ProtocolInfo::CRAFTING_EVENT_PACKET, CraftingEventPacket::class);
		$this->registerPacket(ProtocolInfo::ADVENTURE_SETTINGS_PACKET, AdventureSettingsPacket::class);
		$this->registerPacket(ProtocolInfo::BLOCK_ENTITY_DATA_PACKET, BlockEntityDataPacket::class);
		$this->registerPacket(ProtocolInfo::FULL_CHUNK_DATA_PACKET, FullChunkDataPacket::class);
		$this->registerPacket(ProtocolInfo::SET_DIFFICULTY_PACKET, SetDifficultyPacket::class);
		$this->registerPacket(ProtocolInfo::PLAYER_LIST_PACKET, PlayerListPacket::class);
		$this->registerPacket(ProtocolInfo::PLAYER_INPUT_PACKET, PlayerInputPacket::class);
		$this->registerPacket(ProtocolInfo::SET_PLAYER_GAMETYPE_PACKET, SetPlayerGameTypePacket::class);
		$this->registerPacket(ProtocolInfo::CHANGE_DIMENSION_PACKET, ChangeDimensionPacket::class);
		$this->registerPacket(ProtocolInfo::REQUEST_CHUNK_RADIUS_PACKET, RequestChunkRadiusPacket::class);
		$this->registerPacket(ProtocolInfo::CHUNK_RADIUS_UPDATE_PACKET, ChunkRadiusUpdatePacket::class);
		$this->registerPacket(ProtocolInfo::ITEM_FRAME_DROP_ITEM_PACKET, ItemFrameDropItemPacket::class);
		$this->registerPacket(ProtocolInfo::MAP_INFO_REQUEST_PACKET, MapInfoRequestPacket::class);
		$this->registerPacket(ProtocolInfo::TELEMETRY_EVENT_PACKET, TelemetryEventPacket::class);
	}
}