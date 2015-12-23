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
    
    public function hasPlayerVoted($playerName) {
        $apikey = $this->getPlugin()->getConfig()->get("MPServers_voting_API_key");
        $url = "http://minecraftpocket-servers.com/api/?object=votes&element=claim";
        $url .= "&key=" . urlencode($apikey);
        $url .= "&username=" . urlencode($playerName);
        return file_get_contents($url);
    }
    
    public function claimVoteReward($playerName) {
        $apikey = $this->getPlugin()->getConfig()->get("MPServers_voting_API_key");
        $url = "http://minecraftpocket-servers.com/api/?action=post&object=votes&element=claim";
        $url .= "&key=" . urlencode($apikey);
        $url .= "&username=" . urlencode($playerName);
        $reponse = file_get_contents($url);
        if($reponse != "1") {
            $this->getPlugin()->getLogger()->warning("MyPlot - got an invalid response from minecraftpocket-servers api when " . $playerName . " tried to claim a vote reward. Reponse was " . $reponse);
        }
        return ($reponse == "1");
    }

    public function getAliases() {
        return [];
    }

    public function execute(CommandSender $sender, array $args) {
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
        $needtovote = 0;
        if($uses_voting_api) {
	    $freePlotsBeforeVoting_global =  $this->getPlugin()->getConfig()->get("FreePlotsBeforeVoting");
	    $votingURL = $this->getPlugin()->getConfig()->get("MPServers_voting_direct_URL");
	    $freePlotsBeforeVoting_level = $plotLevel->freePlotsBeforeVoting;
	    if ($freePlotsBeforeVoting_level >= 0 and count($plotsOfPlayer) >= $freePlotsBeforeVoting_level) {
		 $needtovote = 1;
	    }
	    if($freePlotsBeforeVoting_global >= 0 and count($plotsOfPlayer) >= $freePlotsBeforeVoting_global) {
		 $needtovote = 2;
	    }
	    if($needtovote > 0) {
		$api_response=$this->hasPlayerVoted($player->getName());
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
			$this->getPlugin()->getLogger()->info($player->getName() . " has voted and is requesting a plot");
		    break;
		    case "2" :
			$msg = TextFormat::RED . "You have already claimed your plot reward for voting today.";
			$msg .= " You can vote and claim a new plot once every 24 hours.";
			$sender->sendMessage($msg);
			$this->getPlugin()->getLogger()->info($player->getName() . " could not claim a plot due to already voting");
			return true;
		    break;
		    default :
			$msg = TextFormat::RED . "Something has gone - please try later.";
			$sender->sendMessage($msg);
			$this->getPlugin()->getLogger()->warning("Got an invalid response from minecraftpocket-servers api when " . $player->getName() . " tried to claim a plot. Reponse was " . $api_response);
			return true;
		    break;
		}
	    }
        }

        $plot->owner = $sender->getName();
        $plot->name = $name;
        if($needtovote) {
	    if(!$this->claimVoteReward($player->getName())) {
		$sender->sendMessage(TextFormat::RED . "Something went wrong claiming your vote - please try again later.");
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
