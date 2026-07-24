<?php

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\TranslationContainer;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class PingCommand extends VanillaCommand{

    public function __construct($name){
		parent::__construct(
			$name,
			"PING玩家",
			"/ping (player)"
		);
	}
	
	public function execute(CommandSender $sender, $commandLabel, array $args)
    {
        if (!(isset($args[0]))) {
			if (!($sender instanceof Player)) {
				$sender->sendMessage("只能在游戏中使用!");
				return true;
			}
            $sender->sendMessage("your ping: " . $sender->getPing() . "ms");
            return true;
        } else {
            $target = Server::getInstance()->getPlayer($args[0]);

            if ($target == null) {
                return $sender->sendMessage(TextFormat::RED . "找不到该玩家");
            }

            $sender->sendMessage($target->getName() . "'s ping: " . $target->getPing() . "ms");
        }
        return false;
    }
}
