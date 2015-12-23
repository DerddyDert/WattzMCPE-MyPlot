<?php
namespace MyPlot\subcommand;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class AutoSubCommand extends SubCommand
{
    public function canUse(CommandSender $sender) {
        return ($sender instanceof Player) and $sender->hasPermission("myplot.command.auto");
    }

    public function getUsage() {
        return "";
    }

    public function getName() {
        return "auto";
    }

    public function getDescription() {
        return "Teleport to the next free plot";
    }

    public function getAliases() {
        return [];
    }

    public function execute(CommandSender $sender, array $args) {
        if (!empty($args)) {
            return false;
        }
        $player = $sender->getServer()->getPlayer($sender->getName());
        $levelName = $player->getLevel()->getName();
        if (!$this->getPlugin()->isLevelLoaded($levelName)) {
            $sender->sendMessage(TextFormat::RED . "You are not inside a plot world");
            return true;
        }
        $sender->sendMessage(TextFormat::RED . "Finding a free plot for you");
        if($this->getPlugin()->getProvider() instanceof \MyPlot\provider\MYSQLDataProvider) {
	    $plot = $this->getPlugin()->getPlotByPosition($sender->getPosition());
	    $plot =  $this->getPlugin()->getProvider()->getNextFreePlot($levelName, 20, $plot->X, $plot->Z);
	} else {
	    $plot = $this->getPlugin()->getProvider()->getNextFreePlot($levelName);
	}
	
        if ($plot !== null) {
            $this->getPlugin()->teleportPlayerToPlot($player, $plot);
            $sender->sendMessage(TextFormat::GREEN . "Teleported to " . TextFormat::WHITE . $plot);
            $sender->sendMessage(TextFormat::GREEN . "If you dont like this plot use the /p auto command again to find another.");
        } else {
            $sender->sendMessage(TextFormat::RED . "No free plots found in this world");
        }
        return true;
    }
}