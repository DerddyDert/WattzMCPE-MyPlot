<?php
namespace MyPlot\subcommand;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class InfoSubCommand extends SubCommand
{
    public function canUse(CommandSender $sender) {
        return ($sender instanceof Player) and $sender->hasPermission("myplot.command.info");
    }

    public function getUsage() {
        return "";
    }

    public function getName() {
        return "info";
    }

    public function getDescription() {
        return "Get info about the plot you are standing on";
    }

    public function getAliases() {
        return [];
    }

    public function execute(CommandSender $sender, array $args) {
        $player = $sender->getServer()->getPlayer($sender->getName());
        $plot = $this->getPlugin()->getPlotByPosition($player->getPosition());
        if ($plot === null) {
            $sender->sendMessage(TextFormat::RED . "You are not standing inside a plot");
            return true;
        }
        
        
        
        if( $plot->owner == "" ) {
	    		$msg = TextFormat::DARK_RED. "Unclaimed Plot";
	    		$sender->sendMessage($msg);
		} else {
			if( isset($args[0]) ) {
                                $msg = TextFormat::DARK_GREEN . "Plot " . TextFormat::WHITE . $plot->id;
                                $msg .= " ($plot->X , $plot->Z) ";
				$msg .= TextFormat::DARK_GREEN . "Owner: " . TextFormat::WHITE . $plot->owner;
				if($plot->name != "") {
					$msg .= " " . TextFormat::DARK_BLUE . $plot->name;
				}
				if( count($plot->helpers) > 0 ) {
					if($plot->helpers[0] != "") {
						$msg .= " " . TextFormat::DARK_GREEN . " with " .  implode(", ", $plot->helpers);
					}
				}
				$sender->sendMessage($msg);
			} else {
                            $pos = " ($plot->X , $plot->Z) ";
                            $sender->sendMessage(TextFormat::DARK_GREEN. "Position: " . TextFormat::WHITE . $pos);
                            $sender->sendMessage(TextFormat::DARK_GREEN. "Plot Number: " . TextFormat::WHITE . $plot->id);
			    $sender->sendMessage(TextFormat::DARK_GREEN. "Name: " . TextFormat::WHITE . $plot->name);
			    $sender->sendMessage(TextFormat::DARK_GREEN. "Owner: " . TextFormat::WHITE . $plot->owner);
			    $helpers = implode(", ", $plot->helpers);
			    $sender->sendMessage(TextFormat::DARK_GREEN. "Helpers: " . TextFormat::WHITE . $helpers);
			    $sender->sendMessage(TextFormat::DARK_GREEN. "Biome: " . TextFormat::WHITE . $plot->biome);
                            
			}
		}
        return true;
    }
}