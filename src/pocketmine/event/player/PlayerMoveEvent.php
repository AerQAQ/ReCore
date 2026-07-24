<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link   http://www.pocketmine.net/
 *
 *
 */

namespace pocketmine\event\player;

use pocketmine\event\Cancellable;
use pocketmine\level\Location;
use pocketmine\Player;

class PlayerMoveEvent extends PlayerEvent implements Cancellable{
	public static $handlerList = null;

	private $from;
	private $to;

	public function __construct(Player $player, Location $from, Location $to){
		$this->player = $player;
		$this->from = $from;
		$this->to = $to;
		
            foreach ($player->getViewers() as $viewer) {
                //If player too far, continue
                if ($player->distance($viewer) > 0.8) continue;
                //Get info of that close player
                $from = $this->getFrom();
                $to = $this->getTo();
                //distance de depart par rapport au viewer
                $DistanceFromViewer = $viewer->distance($from);
                //distance final par rapport au viewer
                $DistanceToViewer = $viewer->distance($to);
                //If i'm going to move in direction of the viewer
                if ($DistanceFromViewer > $DistanceToViewer) {
                    $speed = round($from->distance($to), 3);
                    // echo "My speed: $speed - ";
                    //Knock $Player harder, if running fast
                    if ($speed > 0.15) {
                        $knockvalue = 0.1;
                        $viewer->knockBack($player, 0, $viewer->x - $player->x, $viewer->z - $player->z, $knockvalue);
                        $player->knockBack($viewer, 0, $player->x - $viewer->x, $player->z - $viewer->z, $knockvalue);
                    } else $player->knockBack($viewer, 0, $player->x - $viewer->x, $player->z - $viewer->z, 0.1);
                }
            }
	}

	public function getFrom(){
		return $this->from;
	}

	public function setFrom(Location $from){
		$this->from = $from;
	}

	public function getTo(){
		return $this->to;
	}

	public function setTo(Location $to){
		$this->to = $to;
	}
}