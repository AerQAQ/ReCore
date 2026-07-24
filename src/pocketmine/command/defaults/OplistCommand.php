<?php
namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\TranslationContainer;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class OplistCommand extends VanillaCommand{

	public function __construct($name){
		parent::__construct(
			$name,
			"列出管理员列表",
			"/oplist"
		);
		$this->setPermission("pocketmine.command.oplist");
	}

	public function execute(CommandSender $sender, $currentAlias, array $args){
		if(!$this->testPermission($sender)){
			return true;
		}
		$arr = $sender->getServer()->OPlist();
		for($i = 0; $arr[$i] != ''; $i ++){
			$sender->sendMessage($arr[$i] . "\n");
		}
		//$sender->sendMessage("hello");
		return true;
	}
}