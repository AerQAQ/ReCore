<?php
namespace pocketmine\entity\ai;

use pocketmine\event\entity\EntityGenerateEvent;
use pocketmine\level\Position;
use pocketmine\level\Level;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\entity\Entity;
use pocketmine\level\format\FullChunk;
use pocketmine\scheduler\CallbackTask;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\entity\Creeper;
use pocketmine\entity\Skeleton;
use pocketmine\entity\Cow;
use pocketmine\entity\Pig;
use pocketmine\entity\Sheep;
use pocketmine\entity\Spider;
use pocketmine\entity\Chicken;
use pocketmine\entity\Mooshroom;
use pocketmine\entity\Ocelot;
use pocketmine\entity\PigZombie;
use pocketmine\entity\Wolf;
use pocketmine\entity\Zombie;
use pocketmine\entity\Blaze;
use pocketmine\entity\MagmaCube;
use pocketmine\entity\Ghast;

class AIHolder {
    public $ChickenAI;
    public $CowAI;
    public $CreeperAI;
    public $PigAI;
    public $SheepAI;
    public $SkeletonAI;
    public $SpiderAI;
    public $ZombieAI;
    public $DefultAI;
    public $Zombie = [];
    public $Creeper = [];
    public $Skeleton = [];
    public $Cow = [];
    public $Pig = [];
    public $Sheep = [];
    public $Spider = [];
    public $Chicken = [];
    public $Defult = [];
    public $PigZombie = [];
    public $Blaze = [];
    public $MagmaCube = [];
    public $Ghast = [];
    public $birth_r = 30;
    public $tasks = [];
    public $server;
    
    public function getServer() {
        return $this->server;
    }
    
    public function __construct(Server $server) {
        $this->server = $server;
        if($this->server->aiConfig["mobgenerate"]) {
            $this->tasks['ZombieGenerate'] = $this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([
                            $this,
                            "MobGenerate"
                        ]), 20 * 60);
        }
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([
                    $this,
                    "TimeFix"
                ]), 20);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask ([$this, "RotationTimer"]), 2);
        /*$this->ZombieAI = new ZombieAI($this);
        $this->CowAI = new CowAI($this);
        $this->PigAI = new PigAI($this);
        $this->SheepAI = new SheepAI($this);
        $this->ChickenAI = new ChickenAI($this);
        $this->SpiderAI = new SpiderAI($this);*/
        $this->CreeperAI = new CreeperAI($this);
        //$this->SkeletonAI = new SkeletonAI($this);
    }
    
    /*
     ************ API 部分 ************************************
     */
    /**
     * @param $r
     * 设置僵尸仇恨半径
     */
    public function setZombieHatred_r($r) {
        $this->ZombieAI->hatred_r = $r;
    }
    
    /**
     * @param $r
     * 设置夜晚刷僵尸范围（以每个玩家为中心）
     */
    public function setZombieBirth_r($r) {
        $this->birth_r = $r;
    }
    
    /**
     * @param $v
     * 设置僵尸仇恨模式下的走路速度
     */
    public function setZombieHate_v($v) {
        $this->ZombieAI->zo_hate_v = $v;
    }
    
    /**
     * @param $tick
     * @return bool
     * 重新启动刷怪计时器
     * （可用于更改刷怪时间间隔）
     */
    public function RestartSpawnTimer($tick = 1200) {
        $task = $this->tasks['ZombieGenerate'];
        if($task instanceof TaskHandler) {
            //TODO 没试验过是否有效。。。
            $task->cancel();
            $task->run($tick);
            return true;
        } else {
            return false;
        }
    }
    
    public function TimeFix() {
        $mode = $this->mode();
        foreach($this->getServer()->getLevels() as $level) {
            foreach($level->getEntities() as $entity) {
                if($entity instanceof Zombie) {
                    $this->ahurt($entity,$mode);
                }
                if($entity instanceof Spider or $entity instanceof CaveSpider) {
                    $this->bhurt($entity,$mode);
                }
            }
            if($level->getTime() > 24000) {
                $level->setTime(0);
            }
        }
    }
    
    public function mode() {
        $mode = 1;
        switch($this->getServer()->getDifficulty()) {
            case 0:
            case 1:
                $mode = 1;
                break;
            case 2:
                $mode = 2;
                break;
            case 3:
                $mode = 3;
                break;
        }
        return $mode;
    }
    
    public function ahurt($e,$mode) {
        switch($mode) {
            case 1:
                $hurt = 5;
                break;
            case 2:
                $hurt = 6;
                break;
            case 3:
                $hurt = 8;
                break;
        }
        return $e->setHurt($hurt);
    }
    
    public function bhurt($e,$mode) {
        switch($mode) {
            case 1:
                $hurt = 5;
                break;
            case 2:
                $hurt = 6;
                break;
            case 3:
                $hurt = 6;
                break;
        }
        return $e->setHurt($hurt);
    }
    
    public function spawnZombie(Position $pos, $maxHealth = 20, $health = 20) {
        $chunk = $pos->level->getChunk($pos->x >> 4, $pos->z >> 4, false);
        $nbt = $this->getNBT();
        $zo = new Zombie($chunk, $nbt);
        $zo->setPosition($pos);
        $zo->setMaxHealth($maxHealth);
        $zo->setHealth($health);
        $zo->spawnToAll();
    }
    
    public function spawnCreeper(Position $pos, $maxHealth = 20, $health = 20) {
        $chunk = $pos->level->getChunk($pos->x >> 4, $pos->z >> 4, false);
        $nbt = $this->getNBT();
        $co = new Creeper($chunk, $nbt);
        $co->setPosition($pos);
        $co->setMaxHealth($maxHealth);
        $co->setHealth($health);
        $co->spawnToAll();
    }
    
    public function spawnSkeleton(Position $pos, $maxHealth = 20, $health = 20) {
        $chunk = $pos->level->getChunk($pos->x >> 4, $pos->z >> 4, false);
        $nbt = $this->getNBT();
        $so = new Skeleton($chunk, $nbt);
        $so->setPosition($pos);
        $so->setMaxHealth($maxHealth);
        $so->setHealth($health);
        $so->spawnToAll();
    }
    
    public function spawnSpider(Position $pos, $maxHealth = 16, $health = 16) {
        $chunk = $pos->level->getChunk($pos->x >> 4, $pos->z >> 4, false);
        $nbt = $this->getNBT();
        $so = new Spider($chunk, $nbt);
        $so->setPosition($pos);
        $so->setMaxHealth($maxHealth);
        $so->setHealth($health);
        $so->spawnToAll();
    }
    
    public function spawnCow(Position $pos, $maxHealth = 8, $health = 8) {
        $chunk = $pos->level->getChunk($pos->x >> 4, $pos->z >> 4, false);
        $nbt = $this->getNBT();
        $coo = new Cow($chunk, $nbt);
        $coo->setPosition($pos);
        $coo->setMaxHealth($maxHealth);
        $coo->setHealth($health);
        $coo->spawnToAll();
    }
    
    public function spawnPig(Position $pos, $maxHealth = 10, $health = 10) {
        $chunk = $pos->level->getChunk($pos->x >> 4, $pos->z >> 4, false);
        $nbt = $this->getNBT();
        $po = new Pig($chunk, $nbt);
        $po->setPosition($pos);
        $po->setMaxHealth($maxHealth);
        $po->setHealth($health);
        $po->spawnToAll();
    }
    
    public function spawnSheep(Position $pos, $maxHealth = 8, $health = 8) {
        $chunk = $pos->level->getChunk($pos->x >> 4, $pos->z >> 4, false);
        $nbt = $this->getNBT();
        $sho = new Sheep($chunk, $nbt);
        $sho->setPosition($pos);
        $sho->setMaxHealth($maxHealth);
        $sho->setHealth($health);
        $sho->spawnToAll();
    }
    
    public function spawnChicken(Position $pos, $maxHealth = 4, $health = 4) {
        $chunk = $pos->level->getChunk($pos->x >> 4, $pos->z >> 4, false);
        $nbt = $this->getNBT();
        $cho = new Chicken($chunk, $nbt);
        $cho->setPosition($pos);
        $cho->setMaxHealth($maxHealth);
        $cho->setHealth($health);
        $cho->spawnToAll();
    }
    
    // 添加猪人生成函数
    public function spawnPigZombie(Position $pos, $maxHealth = 20, $health = 20) {
        $chunk = $pos->level->getChunk($pos->x >> 4, $pos->z >> 4, false);
        $nbt = $this->getNBT();
        $pz = new PigZombie($chunk, $nbt);
        $pz->setPosition($pos);
        $pz->setMaxHealth($maxHealth);
        $pz->setHealth($health);
        $pz->spawnToAll();
    }
    
    // 添加烈焰人生成函数
    public function spawnBlaze(Position $pos, $maxHealth = 20, $health = 20) {
        $chunk = $pos->level->getChunk($pos->x >> 4, $pos->z >> 4, false);
        $nbt = $this->getNBT();
        $blaze = new Blaze($chunk, $nbt);
        $blaze->setPosition($pos);
        $blaze->setMaxHealth($maxHealth);
        $blaze->setHealth($health);
        $blaze->spawnToAll();
    }
    
    // 添加岩浆史莱姆生成函数
    public function spawnMagmaCube(Position $pos, $maxHealth = 16, $health = 16) {
        $chunk = $pos->level->getChunk($pos->x >> 4, $pos->z >> 4, false);
        $nbt = $this->getNBT();
        $magma = new MagmaCube($chunk, $nbt);
        $magma->setPosition($pos);
        $magma->setMaxHealth($maxHealth);
        $magma->setHealth($health);
        $magma->spawnToAll();
    }
    
    // 添加恶魂生成函数
    public function spawnGhast(Position $pos, $maxHealth = 10, $health = 10) {
        $chunk = $pos->level->getChunk($pos->x >> 4, $pos->z >> 4, false);
        $nbt = $this->getNBT();
        $ghast = new Ghast($chunk, $nbt);
        $ghast->setPosition($pos);
        $ghast->setMaxHealth($maxHealth);
        $ghast->setHealth($health);
        $ghast->spawnToAll();
    }
    
    /**
     * @param Player $player
     * @param        $damage
     * @return float
     * 根据玩家的装备获取玩家应受到的伤害值
     */
    public function getPlayerDamage(Player $player, $damage) {
        $armorValues = [
                    Item::LEATHER_CAP => 1,
                    Item::LEATHER_TUNIC => 3,
                    Item::LEATHER_PANTS => 2,
                    Item::LEATHER_BOOTS => 1,
                    Item::CHAIN_HELMET => 1,
                    Item::CHAIN_CHESTPLATE => 5,
                    Item::CHAIN_LEGGINGS => 4,
                    Item::CHAIN_BOOTS => 1,
                    Item::GOLD_HELMET => 1,
                    Item::GOLD_CHESTPLATE => 5,
                    Item::GOLD_LEGGINGS => 3,
                    Item::GOLD_BOOTS => 1,
                    Item::IRON_HELMET => 2,
                    Item::IRON_CHESTPLATE => 6,
                    Item::IRON_LEGGINGS => 5,
                    Item::IRON_BOOTS => 2,
                    Item::DIAMOND_HELMET => 3,
                    Item::DIAMOND_CHESTPLATE => 8,
                    Item::DIAMOND_LEGGINGS => 6,
                    Item::DIAMOND_BOOTS => 3,
                ];
        $points = 0;
        foreach($player->getInventory()->getArmorContents() as $index => $i) {
            if(isset($armorValues[$i->getId()])) {
                $points += $armorValues[$i->getId()];
            }
        }
        $damage = floor($damage - $points * 0.04);
        if($damage < 0) {
            $damage = 0;
        }
        return $damage;
    }
    
    /**
     * @return CompoundTag
     * 返回一个空的实体通用NBT
     */
    public function getNBT() : CompoundTag {
        $nbt = new CompoundTag("", [
                    "Pos" => new ListTag("Pos", [
                        new DoubleTag("", 0),
                        new DoubleTag("", 0),
                        new DoubleTag("", 0)
                    ]),
                    "Motion" => new ListTag("Motion", [
                        new DoubleTag("", 0),
                        new DoubleTag("", 0),
                        new DoubleTag("", 0)
                    ]),
                    "Rotation" => new ListTag("Rotation", [
                        new FloatTag("", 0),
                        new FloatTag("", 0)
                    ]),
                ]);
        return $nbt;
    }
    
    /**
     * @param Position $pos
     * @return int
     * 获取某坐标(位置)的亮度
     */
    public function getLight(Position $pos) {
        $chunk = $pos->getLevel()->getChunk($pos->x >> 4, $pos->z >> 4, false);
        $l = 0;
        if($chunk instanceof FullChunk) {
            $l = $chunk->getBlockSkyLight($pos->x & 0x0f, $pos->y & 0x7f, $pos->z & 0x0f);
            if($l < 15) {
                $l = $chunk->getBlockLight($pos->x & 0x0f, $pos->y & 0x7f, $pos->z & 0x0f);
            }
        }
        return $l;
    }
    
    /******** API结束 以下为计时器 *****************************/
    /**
     * @param Entity $entity
     * @return bool
     * 判断某生物周边32格内是否有玩家存在
     * 控制僵尸是否移动（自由行走模式）
     */
    public function willMove(Entity $entity) {
        foreach($entity->getViewers() as $viewer) {
            if($entity->distance($viewer->getLocation()) <= 32) return true;
        }
        return false;
    }
    
    public function RotationTimer() {
        foreach($this->getServer()->getLevels() as $level) {
            foreach($level->getEntities() as $entity) {
                if($entity instanceof Zombie || $entity instanceof Creeper || $entity instanceof Skeleton || 
                   $entity instanceof Cow || $entity instanceof Pig || $entity instanceof Sheep || 
                   $entity instanceof Chicken || $entity instanceof Spider ||
                   $entity instanceof PigZombie || $entity instanceof Blaze || $entity instanceof MagmaCube ||
                   $entity instanceof Ghast) {
                   
                    if(count($entity->getViewers()) != 0) {
                        if($entity instanceof Zombie) {
                            $array = &$this->Zombie;
                        } elseif($entity instanceof Creeper) {
                            $array = &$this->Creeper;
                        } elseif($entity instanceof Skeleton) {
                            $array = &$this->Skeleton;
                        } elseif($entity instanceof Cow) {
                            $array = &$this->Cow;
                        } elseif($entity instanceof Pig) {
                            $array = &$this->Pig;
                        } elseif($entity instanceof Sheep) {
                            $array = &$this->Sheep;
                        } elseif($entity instanceof Chicken) {
                            $array = &$this->Chicken;
                        } elseif($entity instanceof Spider) {
                            $array = &$this->Spider;
                        } elseif($entity instanceof PigZombie) {
                            $array = &$this->PigZombie;
                        } elseif($entity instanceof Blaze) {
                            $array = &$this->Blaze;
                        } elseif($entity instanceof MagmaCube) {
                            $array = &$this->MagmaCube;
                        } elseif($entity instanceof Ghast) {
                            $array = &$this->Ghast;
                        }
                        
                        if(isset($array[$entity->getId()])) {
                            $yaw0 = $entity->yaw;
                            $yaw = $array[$entity->getId()]['yaw'];
                            
                            if(abs($yaw0 - $yaw) <= 180) {
                                if($yaw0 <= $yaw) {
                                    if($yaw - $yaw0 <= 15) {
                                        $yaw0 = $yaw;
                                    } else {
                                        $yaw0 += 15;
                                    }
                                } else {
                                    if($yaw0 - $yaw <= 15) {
                                        $yaw0 = $yaw;
                                    } else {
                                        $yaw0 -= 15;
                                    }
                                }
                            } else {
                                if($yaw0 >= $yaw) {
                                    if((180 - $yaw0) + ($yaw + 180) <= 15) {
                                        $yaw0 = $yaw;
                                    } else {
                                        $yaw0 += 15;
                                        if($yaw0 >= 180) $yaw0 = $yaw0 - 360;
                                    }
                                } else {
                                    if((180 - $yaw) - ($yaw0 + 180) <= 15) {
                                        $yaw0 = $yaw;
                                    } else {
                                        $yaw0 -= 15;
                                        if($yaw0 <= 180) $yaw0 = $yaw0 + 360;
                                    }
                                }
                            }
                            
                            $pitch0 = $entity->pitch;
                            $pitch = $array[$entity->getId()]['pitch'];
                            
                            if(abs($pitch0 - $pitch) <= 15) {
                                $pitch0 = $pitch;
                            } elseif($pitch > $pitch0) {
                                $pitch0 += 10;
                            } elseif($pitch < $pitch0) {
                                $pitch0 -= 10;
                            }
                            
                            $entity->setRotation($yaw0, $pitch0);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * @param $mx
     * @param $mz
     * @return float|int
     * 获取yaw角度
     */
    public function getyaw($mx, $mz) {
        if($mz == 0) {
            if($mx < 0) {
                $yaw = -90;
            } else {
                $yaw = 90;
            }
        } else {
            if($mx >= 0 and $mz > 0) {
                $atan = atan($mx / $mz);
                $yaw = rad2deg($atan);
            } elseif($mx >= 0 and $mz < 0) {
                $atan = atan($mx / abs($mz));
                $yaw = 180 - rad2deg($atan);
            } elseif($mx < 0 and $mz < 0) {
                $atan = atan($mx / $mz);
                $yaw = -(180 - rad2deg($atan));
            } elseif($mx < 0 and $mz > 0) {
                $atan = atan(abs($mx) / $mz);
                $yaw = -(rad2deg($atan));
            } else {
                $yaw = 0;
            }
        }
        $yaw = -$yaw;
        return $yaw;
    }
    
    /**
     * @param Vector3 $from
     * @param Vector3 $to
     * @return float|int
     * 获取pitch角度
     */
    public function getpitch(Vector3 $from, Vector3 $to) {
        $distance = $from->distance($to);
        $height = $to->y - $from->y;
        if($height > 0) {
            return -rad2deg(asin($height / $distance));
        } elseif($height < 0) {
            return rad2deg(asin(-$height / $distance));
        } else {
            return 0;
        }
    }
    
    /**
     * @param Level $level
     * @param Vector3 $v3
     * @param bool $hate
     * @param bool $reason
     * @return bool|float|string
     * 判断某坐标是否可以行走
     * 并给出原因
     */
    public function ifjump(Level $level, Vector3 $v3, $hate = false, $reason = false) {
        $x = floor($v3->getX());
        $y = floor($v3->getY());
        $z = floor($v3->getZ());
        
        if ($this->whatBlock($level,new Vector3($x,$y,$z)) == "air") {
            if ($this->whatBlock($level,new Vector3($x,$y-1,$z)) == "block" or $this->whatBlock($level,new Vector3($x,$y-1,$z)) == "climb") {
                if ($this->whatBlock($level,new Vector3($x,$y+1,$z)) == "block" or $this->whatBlock($level,new Vector3($x,$y+1,$z)) == "half" or $this->whatBlock($level,new Vector3($x,$y+1,$z)) == "high") {
                    if ($reason) return 'up!';
                    return false;
                } else {
                    if ($reason) return 'GO';
                    return $y;
                }
                    } elseif ($this->whatBlock($level,new Vector3($x,$y-1,$z)) == "water") {
            if ($reason) return 'swim';
            return $y-1;
        } elseif ($this->whatBlock($level,new Vector3($x,$y-1,$z)) == "half") {
            if ($reason) return 'half';
            return $y-0.5;
        } elseif ($this->whatBlock($level,new Vector3($x,$y-1,$z)) == "lava") {
            if ($reason) return 'lava';
            return false;
        } elseif ($this->whatBlock($level,new Vector3($x,$y-1,$z)) == "air") {
            if ($this->whatBlock($level,new Vector3($x,$y-2,$z)) == "block") {
                if ($reason) return 'down';
                return $y-1;
            } else {
                if ($reason) return 'fall';
                if ($hate === false) {
                    return false;
                } else {
                    return $y-1;
                }
            }
        }
    } elseif ($this->whatBlock($level,new Vector3($x,$y,$z)) == "water") {
        if ($this->whatBlock($level,new Vector3($x,$y+1,$z)) == "water") {
            if ($reason) return 'inwater';
            return $y+1;
        } elseif ($this->whatBlock($level,new Vector3($x,$y+1,$z)) == "block" or $this->whatBlock($level,new Vector3($x,$y+1,$z)) == "half") {
            if ($this->whatBlock($level,new Vector3($x,$y-1,$z)) == "block" or $this->whatBlock($level,new Vector3($x,$y-1,$z)) == "half") {
                if ($reason) return 'up!_down!';
                return false;
            } else {
                if ($reason) return 'up!';
                return $y-1;
            }
        } else {
            return $y;
        }
    } elseif ($this->whatBlock($level,new Vector3($x,$y,$z)) == "half") {
        if ($this->whatBlock($level,new Vector3($x,$y+1,$z)) == "block" or $this->whatBlock($level,new Vector3($x,$y+1,$z)) == "half" or $this->whatBlock($level,new Vector3($x,$y+1,$z)) == "high") {
        } else {
            if ($reason) return 'halfGO';
            return $y+0.5;
        }
    } elseif ($this->whatBlock($level,new Vector3($x,$y,$z)) == "lava") {
        if ($reason) return 'lava';
        return false;
    } elseif ($this->whatBlock($level,new Vector3($x,$y,$z)) == "high") {
        if ($reason) return 'high';
        return false;
    } elseif ($this->whatBlock($level,new Vector3($x,$y,$z)) == "climb") {
        if ($reason) return 'climb';
        if ($hate) {
            return $y + 0.7;
        } else {
            return $y + 0.5;
        }
    } else {
        if ($this->whatBlock($level,new Vector3($x,$y+1,$z)) != "air") {
            if ($reason) return 'wall';
            return false;
        } else {
            if ($this->whatBlock($level,new Vector3($x,$y+2,$z)) == "block" or $this->whatBlock($level,new Vector3($x,$y+2,$z)) == "half" or $this->whatBlock($level,new Vector3($x,$y+2,$z)) == "high") {
                if ($reason) return 'up2!';
                return false;
            } else {
                if ($reason) return 'upGO';
                return $y+1;
            }
        }
    }
    return false;
}

public function whatBlock(Level $level, $v3) {
    $block = $level->getBlock($v3);
    $id = $block->getID();
    $damage = $block->getDamage();
    switch ($id) {
        case 0:
        case 6:
        case 27:
        case 30:
        case 31:
        case 37:
        case 38:
        case 39:
        case 40:
        case 50:
        case 51:
        case 63:
        case 66:
        case 68:
        case 78:
        case 111:
        case 141:
        case 142:
        case 171:
        case 175:
        case 244:
        case 323:
            return "air";
            break;
        case 8:
        case 9:
            return "water";
            break;
        case 10:
        case 11:
            return "lava";
            break;
        case 44:
        case 158:
            if ($damage >= 8) {
                return "block";
            } else {
                return "half";
            }
            break;
        case 64:
            if (($damage & 0x08) === 0x08) {
                return "air";
            } else {
                return "block";
            }
            break;
        case 85:
        case 107:
        case 139:
            return "high";
            break;
        case 65:
        case 106:
            return "climb";
            break;
        default:
            return "block";
            break;
    }
}

public function MobDeath(EntityDeathEvent $event) {
    $entity = $event->getEntity();
    if($entity instanceof Zombie) {
        $eid = $entity->getID();
        if(isset($this->Zombie[$eid])) {
            unset($this->Zombie[$eid]);
        }
    }
    if($entity instanceof Creeper) {
        $eid = $entity->getID();
        if(isset($this->Creeper[$eid])) {
            if($this->Creeper[$eid]['boomed'] == true){
                $event->setDrops([]);
            }
            unset($this->Creeper[$eid]);
        }
    }
    if($entity instanceof Skeleton) {
        $eid = $entity->getID();
        if(isset($this->Skeleton[$eid])) {
            unset($this->Skeleton[$eid]);
        }
    }
    if($entity instanceof Spider) {
        $eid = $entity->getID();
        if(isset($this->Spider[$eid])) {
            unset($this->Spider[$eid]);
        }
    }
    if($entity instanceof Sheep) {
        $eid = $entity->getID();
        if(isset($this->Sheep[$eid])) {
            unset($this->Sheep[$eid]);
        }
    }
    if($entity instanceof Cow) {
        $eid = $entity->getID();
        if(isset($this->Cow[$eid])) {
            unset($this->Cow[$eid]);
        }
    }
    if($entity instanceof Pig) {
        $eid = $entity->getID();
        if(isset($this->Pig[$eid])) {
            unset($this->Pig[$eid]);
        }
    }
    if($entity instanceof Chicken) {
        $eid = $entity->getID();
        if(isset($this->Chicken[$eid])) {
            unset($this->Chicken[$eid]);
        }
    }
    if($entity instanceof Defult) {
        $eid = $entity->getID();
        if(isset($this->Defult[$eid])) {
            unset($this->Defult[$eid]);
        }
    }
    if($entity instanceof PigZombie) {
        $eid = $entity->getID();
        if(isset($this->PigZombie[$eid])) {
            unset($this->PigZombie[$eid]);
        }
    }
    if($entity instanceof Blaze) {
        $eid = $entity->getID();
        if(isset($this->Blaze[$eid])) {
            unset($this->Blaze[$eid]);
        }
    }
    if($entity instanceof MagmaCube) {
        $eid = $entity->getID();
        if(isset($this->MagmaCube[$eid])) {
            unset($this->MagmaCube[$eid]);
        }
    }
    if($entity instanceof Ghast) {
        $eid = $entity->getID();
        if(isset($this->Ghast[$eid])) {
            unset($this->Ghast[$eid]);
        }
    }
}

/**
 * 刷怪计时器
 */
public function MobGenerate() {
    foreach($this->getServer()->getOnlinePlayers() as $p) {
        $level = $p->getLevel();
        $max = 5;
        $v3 = new Vector3($p->getX() + mt_rand(-$this->birth_r, $this->birth_r), $p->getY(), $p->getZ() + mt_rand(-$this->birth_r, $this->birth_r));
        
        // 检查世界类型
        $worldName = $level->getName();
        $isNether = stripos($worldName, 'nether') !== false || 
                   stripos($worldName, '地狱') !== false || 
                   $level->getDimension() === Level::DIMENSION_NETHER;

        for ($y0 = $p->getY() - 15; $y0 <= $p->getY() + 15; $y0++) {
            $v3->y = $y0;
            if($this->whatBlock($level, $v3) == "block") {
                $v3_1 = $v3;
                $v3_1->y = $y0 + 1;
                $v3_2 = $v3;
                $v3_2->y = $y0 + 2;
                $random = mt_rand(0, 9);
                
                if($level->getBlock($v3_1)->getID() == 0 and $level->getBlock($v3_2)->getID() == 0) {
                    if($isNether) {
                        // 地狱世界生成逻辑
                        $pigzombiec = [];
                        $blazec = [];
                        $magmacubec = [];
                        $ghastc = [];
                        
                        foreach($level->getEntities() as $entity) {
                            if($entity instanceof PigZombie) $pigzombiec[] = $entity;
                            if($entity instanceof Blaze) $blazec[] = $entity;
                            if($entity instanceof MagmaCube) $magmacubec[] = $entity;
                            if($entity instanceof Ghast) $ghastc[] = $entity;
                        }
                        
                        // 猪人生成逻辑
                        if(count($pigzombiec) > $max) {
                            for ($i = 0; $i < (count($pigzombiec) - $max); $i++) $pigzombiec[$i]->kill();
                        } elseif($random <= 3) {
                            $pos = new Position($v3->x, $v3->y, $v3->z, $level);
                            $this->server->getPluginManager()->callEvent($ev = new EntityGenerateEvent($pos, PigZombie::NETWORK_ID, EntityGenerateEvent::CAUSE_AI_HOLDER));
                            if(!$ev->isCancelled()) {
                                $this->spawnPigZombie($ev->getPosition());
                            }
                        }
                        
                        // 烈焰人生成逻辑
                        if(count($blazec) > $max) {
                            for ($i = 0; $i < (count($blazec) - $max); $i++) $blazec[$i]->kill();
                        } elseif($random >= 4 && $random <= 5) {
                            $pos = new Position($v3->x, $v3->y, $v3->z, $level);
                            $this->server->getPluginManager()->callEvent($ev = new EntityGenerateEvent($pos, Blaze::NETWORK_ID, EntityGenerateEvent::CAUSE_AI_HOLDER));
                            if(!$ev->isCancelled()) {
                                $this->spawnBlaze($ev->getPosition());
                            }
                        }
                        
                        // 岩浆史莱姆生成逻辑
                        if(count($magmacubec) > $max) {
                            for ($i = 0; $i < (count($magmacubec) - $max); $i++) $magmacubec[$i]->kill();
                        } elseif($random >= 6 && $random <= 7) {
                            $pos = new Position($v3->x, $v3->y, $v3->z, $level);
                            $this->server->getPluginManager()->callEvent($ev = new EntityGenerateEvent($pos, MagmaCube::NETWORK_ID, EntityGenerateEvent::CAUSE_AI_HOLDER));
                            if(!$ev->isCancelled()) {
                                $this->spawnMagmaCube($ev->getPosition());
                            }
                        }
                        
                        // 恶魂生成逻辑 - 在地狱的高处生成
                        if(count($ghastc) > 2) { // 恶魂数量限制更严格
                            for ($i = 0; $i < (count($ghastc) - 2); $i++) $ghastc[$i]->kill();
                        } elseif($random >= 8 && $y0 > $p->getY() + 10) { // 在高处生成恶魂
                            $pos = new Position($v3->x, $v3->y + 5, $v3->z, $level); // 恶魂在空中生成
                            $this->server->getPluginManager()->callEvent($ev = new EntityGenerateEvent($pos, Ghast::NETWORK_ID, EntityGenerateEvent::CAUSE_AI_HOLDER));
                            if(!$ev->isCancelled()) {
                                $this->spawnGhast($ev->getPosition());
                            }
                        }
                    } else {
                        // 主世界生成逻辑
                        $zoc = [];
                        $skeletonc = [];
                        $creeperc = [];
                        $cowc = [];
                        $sheepc = [];
                        $spiderc = [];
                        $pigc = [];
                        $chickenc = [];
                        
                        foreach($level->getEntities() as $zo) {
                            if($zo instanceof Zombie) $zoc[] = $zo;
                            if($zo instanceof Skeleton) $skeletonc[] = $zo;
                            if($zo instanceof Creeper) $creeperc[] = $zo;
                            if($zo instanceof Spider) $spiderc[] = $zo;
                            if($zo instanceof Cow) $cowc[] = $zo;
                            if($zo instanceof Sheep) $sheepc[] = $zo;
                            if($zo instanceof Pig) $pigc[] = $zo;
                            if($zo instanceof Chicken) $chickenc[] = $zo;
                        }
                        
                        if(count($zoc) > $max) {
                            for ($i = 0; $i < (count($zoc) - $max); $i++) $zoc[$i]->kill();
                        } elseif($random == 0 && $level->getTime() >= 13500) {
                            $pos = new Position($v3->x, $v3->y, $v3->z, $level);
                            $this->server->getPluginManager()->callEvent($ev = new EntityGenerateEvent($pos, Zombie::NETWORK_ID, EntityGenerateEvent::CAUSE_AI_HOLDER));
                            if(!$ev->isCancelled()) {
                                $this->spawnZombie($ev->getPosition());
                            }
                        }
                        
                        if(count($skeletonc) > $max) {
                            for ($i = 0; $i < (count($skeletonc) - $max); $i++) $skeletonc[$i]->kill();
                        } elseif($random == 1 && $level->getTime() >= 13500) {
                            $pos = new Position($v3->x, $v3->y, $v3->z, $level);
                            $this->server->getPluginManager()->callEvent($ev = new EntityGenerateEvent($pos, Skeleton::NETWORK_ID, EntityGenerateEvent::CAUSE_AI_HOLDER));
                            if(!$ev->isCancelled()) {
                                $this->spawnSkeleton($ev->getPosition());
                            }
                        }
                        
                        if(count($creeperc) > $max) {
                            for ($i = 0; $i < (count($creeperc) - $max); $i++) $creeperc[$i]->kill();
                        } elseif($random == 2 && $level->getTime() >= 13500) {
                            $pos = new Position($v3->x, $v3->y, $v3->z, $level);
                            $this->server->getPluginManager()->callEvent($ev = new EntityGenerateEvent($pos, Creeper::NETWORK_ID, EntityGenerateEvent::CAUSE_AI_HOLDER));
                            if(!$ev->isCancelled()) {
                                $this->spawnCreeper($ev->getPosition());
                            }
                        }
                        
                        if(count($spiderc) > $max) {
                            for ($i = 0; $i < (count($spiderc) - $max); $i++) $spiderc[$i]->kill();
                        } elseif($random == 3 && $level->getTime() >= 13500) {
                            $pos = new Position($v3->x, $v3->y, $v3->z, $level);
                            $this->server->getPluginManager()->callEvent($ev = new EntityGenerateEvent($pos,Spider::NETWORK_ID, EntityGenerateEvent::CAUSE_AI_HOLDER));
                            if(!$ev->isCancelled()) {
                                $this->spawnSpider($ev->getPosition());
                            }
                        }
                        
                        if(count($chickenc) > $max) {
                            for ($i = 0; $i < (count($chickenc) - $max); $i++) $chickenc[$i]->kill();
                        } elseif($random == 4) {
                            $pos = new Position($v3->x, $v3->y, $v3->z, $level);
                            $this->server->getPluginManager()->callEvent($ev = new EntityGenerateEvent($pos, Chicken::NETWORK_ID, EntityGenerateEvent::CAUSE_AI_HOLDER));
                            if(!$ev->isCancelled()) {
                                $this->spawnChicken($ev->getPosition());
                            }
                        }
                        
                        if(count($sheepc) > $max) {
                            for ($i = 0; $i < (count($sheepc) - $max); $i++) $sheepc[$i]->kill();
                        } elseif($random == 5) {
                            $pos = new Position($v3->x, $v3->y, $v3->z, $level);
                            $this->server->getPluginManager()->callEvent($ev = new EntityGenerateEvent($pos, Sheep::NETWORK_ID, EntityGenerateEvent::CAUSE_AI_HOLDER));
                            if(!$ev->isCancelled()) {
                                $this->spawnSheep($ev->getPosition());
                            }
                        }
                        
                        if(count($pigc) > $max) {
                            for ($i = 0; $i < (count($pigc) - $max); $i++) $pigc[$i]->kill();
                        } elseif($random == 6) {
                            $pos = new Position($v3->x, $v3->y, $v3->z, $level);
                            $this->server->getPluginManager()->callEvent($ev = new EntityGenerateEvent($pos, Pig::NETWORK_ID, EntityGenerateEvent::CAUSE_AI_HOLDER));
                            if(!$ev->isCancelled()) {
                                $this->spawnPig($ev->getPosition());
                            }
                        }
                        
                        if(count($cowc) > $max) {
                            for ($i = 0; $i < (count($cowc) - $max); $i++) $cowc[$i]->kill();
                        } elseif($random == 7) {
                            $pos = new Position($v3->x, $v3->y, $v3->z, $level);
                            $this->server->getPluginManager()->callEvent($ev = new EntityGenerateEvent($pos, Cow::NETWORK_ID, EntityGenerateEvent::CAUSE_AI_HOLDER));
                            if(!$ev->isCancelled()) {
                                $this->spawnCow($ev->getPosition());
                            }
                        }
                    }
                    break;
                }
            }
        }
    }
}

public function EntityDamage(EntityDamageEvent $event) {
    if($event instanceof EntityDamageByEntityEvent) {
        $p = $event->getDamager();
        $entity = $event->getEntity();
        if($entity instanceof Zombie) {
            $array = &$this->Zombie;
        } elseif($entity instanceof Creeper) {
            $array = &$this->Creeper;
        } elseif($entity instanceof Cow) {
            $array = &$this->Cow;
        } elseif($entity instanceof Pig) {
            $array = &$this->Pig;
        } elseif($entity instanceof Sheep) {
            $array = &$this->Sheep;
        } elseif($entity instanceof Chicken) {
            $array = &$this->Chicken;
        } elseif($entity instanceof Skeleton) {
            $array = &$this->Skeleton;
        } elseif($entity instanceof Spider) {
            $array = &$this->Spider;
        } elseif($entity instanceof Defult) {
            $array = &$this->Defult;
        } elseif($entity instanceof PigZombie) {
            $array = &$this->PigZombie;
        } elseif($entity instanceof Blaze) {
            $array = &$this->Blaze;
        } elseif($entity instanceof MagmaCube) {
            $array = &$this->MagmaCube;
        } elseif($entity instanceof Ghast) {
            $array = &$this->Ghast;
        } else {
            $array = [];
        }
        
        if(isset($array[$entity->getId()])) {
            if($p instanceof Player and ($array[$entity->getId()]['canAttack'] == 0)) {
                $weapon = $p->getInventory()->getItemInHand()->getID();
                $high = 0;
                if($weapon == 258 or $weapon == 271 or $weapon == 275) {
                    $back = 1.5;
                } elseif($weapon == 267 or $weapon == 272 or $weapon == 279 or $weapon == 283 or $weapon == 286) {
                    $back = 3;
                } elseif($weapon == 276) {
                    $back = 4;
                } elseif($weapon == 292) {
                    $back = 8;
                    $high = 3;
                } else {
                    $back = 1;
                }
                
                $array[$entity->getId()]['x'] = $array[$entity->getId()]['x'] - $array[$entity->getId()]['xxx'] * $back;
                $array[$entity->getId()]['y'] = $entity->getY() + $high;
                $array[$entity->getId()]['z'] = $array[$entity->getId()]['z'] - $array[$entity->getId()]['zzz'] * $back;
                $entity->knockBack($entity, 0, $array[$entity->getId()]['xxx'] * $back, $array[$entity->getId()]['zzz'] * $back);
                
                if(isset($array[$entity->getId()])) {
                    $zom = &$array[$entity->getId()];
                    $zom['IsChasing'] = $p->getName();
                }
            }
        }
    }
}

public function knockBackover(Entity $entity, Vector3 $v3) {
    if($entity instanceof Entity) {
        if(isset($this->Zombie[$entity->getId()])) {
            $entity->setPosition($v3);
            $this->Zombie[$entity->getId()]['knockBack'] = false;
        }
        if(isset($this->Cow[$entity->getId()])) {
            $entity->setPosition($v3);
            $this->Cow[$entity->getId()]['knockBack'] = false;
        }
        if(isset($this->Pig[$entity->getId()])) {
            $entity->setPosition($v3);
            $this->Pig[$entity->getId()]['knockBack'] = false;
        }
        if(isset($this->Sheep[$entity->getId()])) {
            $entity->setPosition($v3);
            $this->Sheep[$entity->getId()]['knockBack'] = false;
        }
        if(isset($this->Chicken[$entity->getId()])) {
            $entity->setPosition($v3);
            $this->Chicken[$entity->getId()]['knockBack'] = false;
        }
        if(isset($this->Skeleton[$entity->getId()])) {
            $entity->setPosition($v3);
            $this->Skeleton[$entity->getId()]['knockBack'] = false;
        }
        if(isset($this->Creeper[$entity->getId()])) {
            $entity->setPosition($v3);
            $this->Creeper[$entity->getId()]['knockBack'] = false;
        }
        if(isset($this->Spider[$entity->getId()])) {
            $entity->setPosition($v3);
            $this->Spider[$entity->getId()]['knockBack'] = false;
        }
        if(isset($this->Defult[$entity->getId()])) {
            $entity->setPosition($v3);
            $this->Defult[$entity->getId()]['knockBack'] = false;
        }
        if(isset($this->PigZombie[$entity->getId()])) {
            $entity->setPosition($v3);
            $this->PigZombie[$entity->getId()]['knockBack'] = false;
        }
        if(isset($this->Blaze[$entity->getId()])) {
            $entity->setPosition($v3);
            $this->Blaze[$entity->getId()]['knockBack'] = false;
        }
        if(isset($this->MagmaCube[$entity->getId()])) {
            $entity->setPosition($v3);
            $this->MagmaCube[$entity->getId()]['knockBack'] = false;
        }
        if(isset($this->Ghast[$entity->getId()])) {
            $entity->setPosition($v3);
            $this->Ghast[$entity->getId()]['knockBack'] = false;
        }
    }
}
}