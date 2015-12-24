<?php
namespace MyPlot\subcommand;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class LockSubCommand extends SubCommand
{
    public function canUse(CommandSender $sender) {
        return true;
    }

    public function getUsage() {
        return "";
    }

    public function getName() {
        return "lock";
    }

    public function getDescription() {
        return "Lock you plot for building. Must be owner";
    }

    public function getAliases() {
        return ["lk"];
    }

    public function execute(CommandSender $sender, array $args) {
        $player = $sender->getServer()->getPlayer($sender->getName());
        $plot = $this->getPlugin()->getPlotByPosition($player->getPosition());
        if ($plot === null) {
            $sender->sendMessage(TextFormat::RED . "You are not standing inside a plot");
            return true;
        }
        if ($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.build.plot")) {
            $sender->sendMessage(TextFormat::RED . "You are not the owner of this plot");
            return true;
        }
        if ($plot->locked) {
            $sender->sendMessage(TextFormat::RED . "This plot has already been locked");
            return true;
        }
        $plot->locked = true;
        if ($this->getPlugin()->getProvider()->savePlot($plot)) {
            $sender->sendMessage(TextFormat::GREEN . "The plot has now been locked.");
        } else {
            $sender->sendMessage(TextFormat::RED . "Failed to lock the plot");
        }
        return true;
    }
}