<?php
namespace MyPlot\subcommand;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class ClearSubCommand extends SubCommand
{
    public function canUse(CommandSender $sender) {
        return ($sender instanceof Player) and $sender->hasPermission("myplot.command.clear");
    }

    public function getUsage() {
        return "";
    }

    public function getName() {
        return "clear";
    }

    public function getDescription() {
        return "Clear the plot you are standing on back to an empty plot";
    }

    public function getAliases() {
        return [];
    }

    public function execute(CommandSender $sender, array $args) {
        $argString = strtolower(implode(" ", $args));
        
        $player = $sender->getServer()->getPlayer($sender->getName());
        $plot = $this->getPlugin()->getPlotByPosition($player->getPosition());
        $senderIsAdmin = $sender->hasPermission("myplot.admin.clear");
        $senderIsPlotOwner = ($plot->owner == $sender->getName());
        $clearCommandParm = "yes i really want to clear this";
        $adminClearCommandParm = "confirm";
        
        if ($plot === null) {
            $sender->sendMessage(TextFormat::RED . "You are not standing inside a plot");
            return true;
        }
        
        if ( ! $senderIsPlotOwner and ! $senderIsAdmin ) {
            $sender->sendMessage(TextFormat::RED . "You are not the owner of this plot");
            return true;
        }

        $economy = $this->getPlugin()->getEconomyProvider();
        $price = $this->getPlugin()->getLevelSettings($plot->levelName)->clearPrice;
        if ($economy !== null and !$economy->reduceMoney($player, $price)) {
            $sender->sendMessage(TextFormat::RED . "You don't have enough money to clear this plot");
            return true;
        }

        if( $argString != $clearCommandParm and ! $senderIsAdmin ) {
            $msg  = TextFormat::GREEN . "This command will completley ";
            $msg .= TextFormat::RED . "WIPE YOUR PLOT";
            $msg .= TextFormat::GREEN . " and surrounding road. ";
            $msg .= TextFormat::WHITE . "If you really want to do this type ";
            $msg .= TextFormat::DARK_PURPLE . "/p clear " . $clearCommandParm;
            $sender->sendMessage($msg);
            return true;
        } elseif ( $argString != $adminClearCommandParm and $senderIsAdmin ) {
            $msg  = TextFormat::GREEN . "This command will completley ";
            $msg .= TextFormat::RED . "WIPE THE PLOT";
            $msg .= TextFormat::GREEN . " beloning to " . $plot->owner . ". ";
            $msg .= TextFormat::WHITE . "If you really want to do this type ";
            $msg .= TextFormat::DARK_PURPLE . "/p clear " . $adminClearCommandParm;
            $sender->sendMessage($msg);
            return true;
        }

        if ($this->getPlugin()->clearPlot($plot, $player)) {
            $sender->sendMessage("Plot is being cleared...");
        } else {
            $sender->sendMessage(TextFormat::RED . "Could not clear this plot");
        }
        return true;
    }
}
