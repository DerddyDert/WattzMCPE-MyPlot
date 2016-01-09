<?php
namespace MyPlot\subcommand;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class ClaimSubCommand extends SubCommand
{
    public function canUse(CommandSender $sender) {
        return ($sender instanceof Player) and $sender->hasPermission("myplot.command.claim");
    }

    public function getUsage() {
        return "[name]";
    }

    public function getName() {
        return "claim";
    }

    public function getDescription() {
        return "Claim the plot you're standing on";
    }

    public function getAliases() {
        return [];
    }

    public function execute(CommandSender $sender, array $args) {
        $needtovote = false;
        if (count($args) > 1) {
            return false;
        }
        $name = "";
        if (isset($args[0])) {
            $name = $args[0];
        }
        $player = $sender->getServer()->getPlayer($sender->getName());
        $plot = $this->getPlugin()->getPlotByPosition($player->getPosition());
        if ($plot === null) {
            $sender->sendMessage(TextFormat::RED . "You are not standing inside a plot");
            return true;
        }
        if ($plot->owner != "") {
            if ($plot->owner === $sender->getName()) {
                $sender->sendMessage(TextFormat::RED . "You already own this plot");
            } else {
                $sender->sendMessage(TextFormat::RED . "This plot is already claimed by " . $plot->owner);
            }
            return true;
        }
        $plotLevel = $this->getPlugin()->getLevelSettings($plot->levelName);
        $maxPlotsInLevel = $plotLevel->maxPlotsPerPlayer;
        $maxPlots = $this->getPlugin()->getConfig()->get("MaxPlotsPerPlayer");
        $plotsOfPlayer = $this->getPlugin()->getProvider()->getPlotsByOwner($player->getName());
	/*
        if ($maxPlotsInLevel >= 0 and count($plotsOfPlayer) >= $maxPlotsInLevel) {
            $sender->sendMessage(TextFormat::RED . "You reached 	the limit of $maxPlotsInLevel plots per player in this world");
            return true;
        } elseif ($maxPlots >= 0 and count($plotsOfPlayer) >= $maxPlots) {
            $sender->sendMessage(TextFormat::RED . "You reached the limit of $maxPlots plots per player");
            return true;
        }
	*/
        $economy = $this->getPlugin()->getEconomyProvider();
        if ($economy !== null and !$economy->reduceMoney($player, $plotLevel->claimPrice)) {
            $sender->sendMessage(TextFormat::RED . "You don't have enough money to claim this plot");
            return true;
        }
        
        $uses_voting_api = $this->getPlugin()->getUsesVotingAPI();
        
        if($uses_voting_api) {
            $votingProvider = $this->getPlugin()->getVotingProvider();
            if( ! $votingProvider->keyValidated() ) {
                $votingProvider->validateAPIKey();
            }
            if( ! $votingProvider->keyValidated() ) {
                $msg = TextFormat::RED . "Unfortunatley our server cannot reach ";
                $msg .= "minecraftpocket-servers.com right now. ";
                $msg .= "Please try again later.";
                $sender->sendMessage($msg);
                $msg =  TextFormat::RED . " rejected plot claim for " . $sender->getName();
                $msg .= " as minecraftpocket-servers.com";
                $msg .= " seems to be unreachable right now or invalid api key.";
                $this->getPlugin()->getLogger()->error($msg);
                return true;
            }
	    $freePlotsBeforeVoting_global =  $votingProvider->getFreePlotsBeforeVoting();
	    $votingURL = $votingProvider->getVotingURL();
	    $freePlotsBeforeVoting_level = $plotLevel->freePlotsBeforeVoting;
            $needtovote = 0;
	    if ($freePlotsBeforeVoting_level >= 0 and count($plotsOfPlayer) >= $freePlotsBeforeVoting_level) {
		 $needtovote = 1;
	    }
	    if($freePlotsBeforeVoting_global >= 0 and count($plotsOfPlayer) >= $freePlotsBeforeVoting_global) {
		 $needtovote = 2;
	    }
	    if($needtovote > 0) {
		$api_response=$votingProvider->hasPlayerVoted($player->getName());
		switch($api_response) {
		    case "0" :
			$msg = TextFormat::RED . "You must vote for us to claim another plot";
			if($needtovote == 1) {
			    $msg .= " in " . $plot->levelName . ".";
			} else {
			    $msg .= ".";
			}
			$msg .= " Please visit " . TextFormat::GREEN . $votingURL;
			$msg .= TextFormat::RED . " to vote.";
			$sender->sendMessage($msg);
			$this->getPlugin()->getLogger()->info($player->getName() . " could not claim a plot due to not voted");
			return true;
		    break;
		    case "1" :
			// 1 = has unclaimed vote - carry on
                        $msg = $player->getName() . " has voted and is requesting a plot";
			$this->getPlugin()->getLogger()->info($msg);
                        break;
		    case "2" :
			$msg = TextFormat::RED . "You have already claimed your plot reward for voting today.";
			$msg .= " You can vote and claim a new plot once every 24 hours.";
			$sender->sendMessage($msg);
                        $msg = $player->getName() . " could not claim a plot due to already voting";
			$this->getPlugin()->getLogger()->info($msg);
			return true;
                        break;
		    default :
			$msg = TextFormat::RED . "Unfortunatley our server cannot reach ";
                        $msg .= "minecraftpocket-servers.com right now. ";
                        $msg .= "Please try again later.";
                        $sender->sendMessage($msg);
			return true;
                        break;
		}
	    }
        }

        $plot->owner = $sender->getName();
        $plot->name = $name;
        if($needtovote) {
	    if( ! $votingProvider->claimVoteReward($player->getName())) {
                $msg = TextFormat::RED . "Unfortunatley our server cannot reach ";
                $msg .= "minecraftpocket-servers.com right now. ";
                $msg .= "Please try again later.";
                $sender->sendMessage($msg);
		return true;
	    }
        }
        if ($this->getPlugin()->getProvider()->savePlot($plot)) {
            $sender->sendMessage(TextFormat::GREEN . "You are now the owner of " . TextFormat::WHITE . $plot);
            if($needtovote) {
		$sender->sendMessage(TextFormat::GREEN . "Thank you for voting!");
		$this->getPlugin()->getLogger()->info($player->getName() . " claimed a plot as a vote reward.");
            }
        } else {
            $sender->sendMessage(TextFormat::RED . "Something went wrong");
        }
        return true;
    }
}
