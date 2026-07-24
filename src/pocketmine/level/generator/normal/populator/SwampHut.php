<?php

/*
 *  ______   _____    ______  __   __  ______
 * /  ___/  /  ___|  / ___  \ \ \ / / |  ____|
 * | |___  | |      | |___| |  \ / /  | |____
 * \___  \ | |      |  ___  |   / /   |  ____|
 *  ___| | | |____  | |   | |  / / \  | |____
 * /_____/  \_____| |_|   |_| /_/ \_\ |______|
 * 生存斧服务器Minecraft PE 0.14.x核心.
 * By Sunch233#3226 QQ2125696621 And KKK
 * @link https://github.com/ScaxeTeam/Scaxe/
 *
*/

namespace pocketmine\level\generator\normal\populator;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\generator\populator\VariableAmountPopulator;
use pocketmine\level\Level;
use pocketmine\utils\Random;

class SwampHut extends VariableAmountPopulator{

    /** @var ChunkManager */
    private $level;

    /**
     * Populates the chunk
     *
     * @param ChunkManager $level
     * @param int $chunkX
     * @param int $chunkZ
     * @param Random $random
     * @return void
     */
    public function populate(ChunkManager $level, $chunkX, $chunkZ, Random $random){
        $this->level = $level;
        if ($random->nextBoundedInt(1000) > 25)
            return; // ~1 chance / 1000 due to building limitations.
        $SwampHut = new \pocketmine\level\generator\normal\object\SwampHut();
        $x = $random->nextRange($chunkX << 4, ($chunkX << 4) + 15);
        $z = $random->nextRange($chunkZ << 4, ($chunkZ << 4) + 15);
        $y = $this->getHighestWorkableBlock($x, $z) - 1;
        if ($SwampHut->canPlaceObject($level, $x, $y, $z, $random))
            $SwampHut->placeObject($level, $x, $y, $z, $random);
    }

    protected function getHighestWorkableBlock($x, $z){
        for ($y = 127; $y > 0; --$y) {
            $b = $this->level->getBlockIdAt($x, $y, $z);
            if ($b === Block::WATER or $b === Block::STILL_WATER) {
                break;
            }
        }

        return ++$y;
    }

}