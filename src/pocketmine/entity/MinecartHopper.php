<?php


namespace pocketmine\entity;

use pocketmine\block\Block;
use pocketmine\block\Rail;
use pocketmine\block\Chest;
use pocketmine\block\Air;
use pocketmine\math\Math;
use pocketmine\math\Vector3;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\item\Item as ItemItem;
use pocketmine\Player;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\tile\Chest as TileChest;
use pocketmine\tile\Tile;
use pocketmine\level\Explosion;
use pocketmine\level\Level;

class MinecartHopper extends Vehicle {
    const NETWORK_ID = 96;

    const TYPE_NORMAL = 1;
    const TYPE_CHEST = 2;
    const TYPE_HOPPER = 3;
    const TYPE_TNT = 4;

    const STATE_INITIAL = 0;
    const STATE_ON_RAIL = 1;
    const STATE_OFF_RAIL = 2;

    public $height = 0.7;
    public $width = 0.98;

    public $drag = 0.1;
    public $gravity = 0.5;

    public $isMoving = false;
    public $moveSpeed = 0.5;

    private $state = Minecart::STATE_INITIAL;
    private $direction = -1;
    private $moveVector = [];
	public $motionX = 0;
	public $motionY = 0;
	public $motionZ = 0;
	
	public $chestx = 0;
	public $chesty = 0;
	public $chestz = 0;
	
	public $chestplaced = false;
	public $chestblock = null;
	public $chesttile = null;
	public $chestnbt = null;

    public function initEntity(){
        $this->setMaxHealth(1);
        $this->setHealth($this->getMaxHealth());
        $this->moveVector[Entity::NORTH] = new Vector3(-1, 0, 0);
        $this->moveVector[Entity::SOUTH] = new Vector3(1, 0, 0);
        $this->moveVector[Entity::EAST] = new Vector3(0, 0, -1);
        $this->moveVector[Entity::WEST] = new Vector3(0, 0, 1);
        parent::initEntity();
    }

    public function getName(): string{
        return "Minecart Hopper";
    }

    public function getType(): int{
        return self::TYPE_NORMAL;
    }

    public function onUpdate($currentTick){
        if ($this->closed !== false) {
            return false;
        }

        $tickDiff = $currentTick - $this->lastUpdate;
        if ($tickDiff <= 1) {
            return false;
        }

        $this->lastUpdate = $currentTick;

        $this->timings->startTiming();

        $hasUpdate = false;

        if ($this->isAlive()){
			
        }
        
        if ($this->state === Minecart::STATE_INITIAL){
					$this->checkIfOnRail();
			}elseif($this->state === Minecart::STATE_ON_RAIL){
					$hasUpdate = $this->forwardOnRail($this);
					$this->updateMovement();
			}
		
        $this->timings->stopTiming();

        return $hasUpdate or !$this->onGround or abs($this->motionX) > 0.00001 or abs($this->motionY) > 0.00001 or abs($this->motionZ) > 0.00001;
    }

    private function checkIfOnRail(){
        for ($y = -1; $y !== 2 and $this->state === Minecart::STATE_INITIAL; $y++) {
            $positionToCheck = $this->temporalVector->setComponents($this->x, $this->y + $y, $this->z);
            $block = $this->level->getBlock($positionToCheck);
            if ($this->isRail($block)) {
                $minecartPosition = $positionToCheck->floor()->add(0.5, 0, 0.5);
                $this->setPosition($minecartPosition);    // Move minecart to center of rail
                $this->state = Minecart::STATE_ON_RAIL;
            }
        }
        if ($this->state !== Minecart::STATE_ON_RAIL) {
            $this->state = Minecart::STATE_OFF_RAIL;
        }
    }

    private function isRail(Block $rail){
        return ($rail !== null and in_array($rail->getId(), [Block::RAIL, Block::ACTIVATOR_RAIL, Block::DETECTOR_RAIL, Block::POWERED_RAIL]));
    }

    private function getCurrentRail(){
        $block = $this->getLevel()->getBlock($this);
        if ($this->isRail($block)) {
            return $block;
        }
        // Rail could be one block below descending down
        $down = $this->temporalVector->setComponents($this->x, $this->y - 1, $this->z);
        $block = $this->getLevel()->getBlock($down);
        if ($this->isRail($block)) {
            return $block;
        }
        return null;
    }

    private function forwardOnRail(MinecartHopper $player){
        if ($this->direction === -1) {
            $candidateDirection = $player->getDirection();
        } else {
            $candidateDirection = $this->direction;
        }
        $rail = $this->getCurrentRail();
        if ($rail !== null) {
            $railType = $rail->getDamage();
            $nextDirection = $this->getDirectionToMove($railType, $candidateDirection);
            if ($nextDirection !== -1) {
                $this->direction = $nextDirection;
                $moved = $this->checkForVertical($railType, $nextDirection);
                if (!$moved) {
                    return $this->moveIfRail();
                } else {
                    return true;
                }
            } else {
                $this->direction = -1;  // Was not able to determine direction to move, so wait for player to look in valid direction
            }
        } else {
            // Not able to find rail
            $this->state = Minecart::STATE_INITIAL;
        }

        return false;
    }

    private function getDirectionToMove($railType, $candidateDirection){
        switch ($railType) {
            case Rail::STRAIGHT_NORTH_SOUTH:
            case Rail::SLOPED_ASCENDING_NORTH:
            case Rail::SLOPED_ASCENDING_SOUTH:
                switch ($candidateDirection) {
                    case Entity::NORTH:
                    case Entity::SOUTH:
                        return $candidateDirection;
                }
                break;
            case Rail::STRAIGHT_EAST_WEST:
            case Rail::SLOPED_ASCENDING_EAST:
            case Rail::SLOPED_ASCENDING_WEST:
                switch ($candidateDirection) {
                    case Entity::WEST:
                    case Entity::EAST:
                        return $candidateDirection;
                }
                break;
            case Rail::CURVED_SOUTH_EAST:
                switch ($candidateDirection) {
                    case Entity::SOUTH:
                    case Entity::EAST:
                        return $candidateDirection;
                    case Entity::NORTH:
                        return $this->checkForTurn($candidateDirection, Entity::EAST);
                    case Entity::WEST:
                        return $this->checkForTurn($candidateDirection, Entity::SOUTH);
                }
                break;
            case Rail::CURVED_SOUTH_WEST:
                switch ($candidateDirection) {
                    case Entity::SOUTH:
                    case Entity::WEST:
                        return $candidateDirection;
                    case Entity::NORTH:
                        return $this->checkForTurn($candidateDirection, Entity::WEST);
                    case Entity::EAST:
                        return $this->checkForTurn($candidateDirection, Entity::SOUTH);
                }
                break;
            case Rail::CURVED_NORTH_WEST:
                switch ($candidateDirection) {
                    case Entity::NORTH:
                    case Entity::WEST:
                        return $candidateDirection;
                    case Entity::SOUTH:
                        return $this->checkForTurn($candidateDirection, Entity::WEST);
                    case Entity::EAST:
                        return $this->checkForTurn($candidateDirection, Entity::NORTH);

                }
                break;
            case Rail::CURVED_NORTH_EAST:
                switch ($candidateDirection) {
                    case Entity::NORTH:
                    case Entity::EAST:
                        return $candidateDirection;
                    case Entity::SOUTH:
                        return $this->checkForTurn($candidateDirection, Entity::EAST);
                    case Entity::WEST:
                        return $this->checkForTurn($candidateDirection, Entity::NORTH);
                }
                break;
        }
        return -1;
    }

    private function checkForTurn($currentDirection, $newDirection){
        switch ($currentDirection) {
            case Entity::NORTH:
                $diff = $this->x - $this->getFloorX();
                if ($diff !== 0 and $diff <= .5) {
                    $dx = ($this->getFloorX() + .5) - $this->x;
                    $this->move($dx, 0, 0);
                    return $newDirection;
                }
                break;
            case Entity::SOUTH:
                $diff = $this->x - $this->getFloorX();
                if ($diff !== 0 and $diff >= .5) {
                    $dx = ($this->getFloorX() + .5) - $this->x;
                    $this->move($dx, 0, 0);
                    return $newDirection;
                }
                break;
            case Entity::EAST:
                $diff = $this->z - $this->getFloorZ();
                if ($diff !== 0 and $diff <= .5) {
                    $dz = ($this->getFloorZ() + .5) - $this->z;
                    $this->move(0, 0, $dz);
                    return $newDirection;
                }
                break;
            case Entity::WEST:
                $diff = $this->z - $this->getFloorZ();
                if ($diff !== 0 and $diff >= .5) {
                    $dz = $dz = ($this->getFloorZ() + .5) - $this->z;
                    $this->move(0, 0, $dz);
                    return $newDirection;
                }
                break;
        }
		

        return $currentDirection;
    }

    private function checkForVertical($railType, $currentDirection){
        switch ($railType) {
            case Rail::SLOPED_ASCENDING_NORTH:
                switch ($currentDirection) {
                    case Entity::NORTH:
                        $diff = $this->x - $this->getFloorX();
                        if ($diff !== 0 and $diff <= .5) {
                            $dx = ($this->getFloorX() - .1) - $this->x;
                            $this->move($dx, -1, 0);
                            return true;
                        }
                        break;
                    case Entity::SOUTH:
                        $diff = $this->x - $this->getFloorX();
                        if ($diff !== 0 and $diff >= .5) {
                            $dx = ($this->getFloorX() + 1) - $this->x;
                            $this->move($dx, 1, 0);
                            return true;
                        }
                        break;
                }
                break;
            case Rail::SLOPED_ASCENDING_SOUTH:
                switch ($currentDirection) {
                    case Entity::SOUTH:
                        $diff = $this->x - $this->getFloorX();
                        if ($diff !== 0 and $diff >= .5) {
                            $dx = ($this->getFloorX() + 1) - $this->x;
                            $this->move($dx, -1, 0);
                            return true;
                        }
                        break;
                    case Entity::NORTH:
                        $diff = $this->x - $this->getFloorX();
                        if ($diff !== 0 and $diff <= .5) {
                            $dx = ($this->getFloorX() - .1) - $this->x;
                            $this->move($dx, 1, 0);
                            return true;
                        }
                        break;
                }
                break;
            case Rail::SLOPED_ASCENDING_EAST:
                switch ($currentDirection) {
                    case Entity::EAST:
                        $diff = $this->z - $this->getFloorZ();
                        if ($diff !== 0 and $diff <= .5) {
                            $dz = ($this->getFloorZ() - .1) - $this->z;
                            $this->move(0, 1, $dz);
                            return true;
                        }
                        break;
                    case Entity::WEST:
                        $diff = $this->z - $this->getFloorZ();
                        if ($diff !== 0 and $diff >= .5) {
                            $dz = ($this->getFloorZ() + 1) - $this->z;
                            $this->move(0, -1, $dz);
                            return true;
                        }
                        break;
                }
                break;
            case Rail::SLOPED_ASCENDING_WEST:
                switch ($currentDirection) {
                    case Entity::WEST:
						
                        $diff = $this->z - $this->getFloorZ();
                        if ($diff !== 0 and $diff >= .5) {
                            $dz = ($this->getFloorZ() + 1) - $this->z;
                            $this->move(0, 1, $dz);
                            return true;
                        }
                        break;
                    case Entity::EAST:
                        $diff = $this->z - $this->getFloorZ();
                        if ($diff !== 0 and $diff <= .5) {
                            $dz = ($this->getFloorZ() - .1) - $this->z;
                            $this->move(0, -1, $dz);
                            return true;
                        }
                        break;
                }
                break;
        }

        return false;
    }

    private function moveIfRail(){
        $nextMoveVector = $this->moveVector[$this->direction];
        $nextMoveVector = $nextMoveVector->multiply($this->moveSpeed);
        $newVector = $this->add($nextMoveVector->x, $nextMoveVector->y, $nextMoveVector->z);
        $possibleRail = $this->getCurrentRail();
        if (in_array($possibleRail->getId(), [Block::RAIL, Block::ACTIVATOR_RAIL, Block::DETECTOR_RAIL, Block::POWERED_RAIL])) {
            $this->moveUsingVector($newVector);
            return true;
        }

        return false;
    }

    private function moveUsingVector(Vector3 $desiredPosition){
        $dx = $desiredPosition->x - $this->x;
        $dy = $desiredPosition->y - $this->y;
        $dz = $desiredPosition->z - $this->z;
        $this->move($dx, $dy, $dz);
    }

    public function getNearestRail(){
        $minX = Math::floorFloat($this->boundingBox->minX);
        $minY = Math::floorFloat($this->boundingBox->minY);
        $minZ = Math::floorFloat($this->boundingBox->minZ);
        $maxX = Math::ceilFloat($this->boundingBox->maxX);
        $maxY = Math::ceilFloat($this->boundingBox->maxY);
        $maxZ = Math::ceilFloat($this->boundingBox->maxZ);

        $rails = [];

        for ($z = $minZ; $z <= $maxZ; ++$z) {
            for ($x = $minX; $x <= $maxX; ++$x) {
                for ($y = $minY; $y <= $maxY; ++$y) {
                    $block = $this->level->getBlock($this->temporalVector->setComponents($x, $y, $z));
                    if (in_array($block->getId(), [Block::RAIL, Block::ACTIVATOR_RAIL, Block::DETECTOR_RAIL, Block::POWERED_RAIL])) $rails[] = $block;
                }
            }
        }

        $minDistance = PHP_INT_MAX;
        $nearestRail = null;
        foreach ($rails as $rail) {
            $dis = $this->distance($rail);
            if ($dis < $minDistance) {
                $nearestRail = $rail;
                $minDistance = $dis;
            }
        }

        return $nearestRail;
    }

    public function spawnTo(Player $player){
        $pk = new AddEntityPacket();
        $pk->eid = $this->getId();
        $pk->type = MinecartHopper::NETWORK_ID;
        $pk->x = $this->x;
        $pk->y = $this->y + $this->getEyeHeight() - 1.25;
        $pk->z = $this->z;
        $pk->speedX = 0;
        $pk->speedY = 0;
        $pk->speedZ = 0;
        $pk->yaw = 0;
        $pk->pitch = 0;
        $pk->metadata = $this->dataProperties;
        $player->dataPacket($pk);

        parent::spawnTo($player);
        
        /*$this->chestx = $player->x;
		if($player->y > 6){
		$this->chesty = $player->y - 5;
		}else{
		$this->chesty = $player->y;
		}
        $this->chestz = $player->z;
        
        $this->chestnbt = new CompoundTag("", [
			new ListTag("Items", []),
			new StringTag("id", Tile::CHEST),
			new IntTag("x", $this->chestx),
			new IntTag("y", $this->chesty),
			new IntTag("z", $this->chestz)
		]);
		$this->chestnbt->Items->setTagType(NBT::TAG_Compound);
		$this->chestnbt->CustomName = new StringTag("CustomName", "漏斗矿车");
		$this->getLevel()->setBlock(new Vector3($this->chestx,$this->chesty,$this->chestz), new Chest(), true, true);
		$this->chesttile = Tile::createTile("MinecartChest", $this->getLevel()->getChunk($this->chestx >> 4, $this->chestz >> 4), $this->chestnbt);*/
    }

    public function attack($damage, EntityDamageEvent $source){
        parent::attack($damage, $source);

        if(!$source->isCancelled()){
            $pk = new EntityEventPacket();
            $pk->eid = $this->id;
            $pk->event = EntityEventPacket::HURT_ANIMATION;
            foreach($this->getLevel()->getPlayers() as $player){
                $player->dataPacket($pk);
            }
        }
    }
	
	public function getDrops(){
		return [ItemItem::get(ItemItem::MINECART, 0, 1)];
	}
	
	public function MinecartChestOpen(){
		//$level = $this->getLevel();
		//$this->linkplayer->addWindow($this->chesttile->getInventory());
	}

    public function getSaveId(){
        $class = new \ReflectionClass(static::class);
        return $class->getShortName();
    }
	
	public function close(){
		if(!$this->closed){
			foreach($this->getDrops() as $item){
				$this->getLevel()->dropItem($this, $item);
			}
		}
		parent::close();
	}
}
