<?php
namespace MyPlot\subcommand;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class WarpSubCommand extends SubCommand
{
    public function canUse(CommandSender $sender) {
        return true;
    }

    public function getUsage() {
        return "[plot number]";
    }

    public function getName() {
        return "warp";
    }

    public function getDescription() {
        return "Teleport to a plot by its id";
    }

    public function getAliases() {
        return ["w"];
    }

    public function execute(CommandSender $sender, array $args) {
        if (empty($args)) {
            $sender->sendMessage(TextFormat::RED . "You must give a plot number.");
            return false;
        }
        if (!is_numeric($args[0])) {
            $sender->sendMessage(TextFormat::RED . "Plot number must be a number - duh! :-)");
            return false;
        }
        $plot = $this->getPlugin()->getPlotById((int) $args[0]);
        if (is_null($plot)) {
            $sender->sendMessage(TextFormat::RED . "I cannot find a plot with number $args[0] :-(");
            return false;
        }
        $player = $this->getPlugin()->getServer()->getPlayer($sender->getName());
        if ($this->getPlugin()->teleportPlayerToPlot($player, $plot)) {
            $sender->sendMessage(TextFormat::GREEN . "Teleported to " . TextFormat::WHITE . $plot);
        } else {
            $sender->sendMessage(TextFormat::RED . "Error teleporting - sorry :-(");
        }
        return true;
    }
}
