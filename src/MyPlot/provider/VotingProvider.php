<?php

namespace MyPlot\provider;

use MyPlot\MyPlot;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class VotingProvider {
    /**
     * @var MyPlot
     */
    protected $plugin;
    /**
     * @var string $apiKey the MPServers voting api key for this server
     */
    protected $apiKey;
    
    /**
     * @var string
     */
    protected $votingURL;
    
    /**
     * @var int
     */
    protected $freePlotsBeforeVoting;
    
    /**
     * @var boolean
     */
    protected $validatedKey;
    
    /**
     * 
     * @param MyPlot $plugin
     * @param type $apiKey
     * @param type $freePlotsBeforeVoting
     * @param type $votingURL
     */
    public function __construct(
            MyPlot $plugin, 
            $apiKey, 
            $freePlotsBeforeVoting, 
            $votingURL) {
        $this->apiKey = $apiKey;
        $this->plugin = $plugin;
        $this->freePlotsBeforeVoting = $freePlotsBeforeVoting;
        $this->votingURL = $votingURL;
        $this->validateAPIKey();
    }
    
    public function getConnectionName() {
        return "minecraftpocket-servers.com";
    }
    
    public function keyValidated() {
        return $this->validatedKey;
    }
    
    public function getVotingURL() {
        return $this->votingURL;
    }
    
    public function getFreePlotsBeforeVoting() {
        return $this->freePlotsBeforeVoting;
    }
    
    public function validateAPIKey() {
        // validate api key
        $votingAPIKey = $this->apiKey;
        $url = "http://minecraftpocket-servers.com/api/?object=servers&element=detail";
        $url .= "&key=" . urlencode($votingAPIKey);
        $response_raw = file_get_contents($url);
        $response = json_decode($response_raw);
        if(json_last_error() != JSON_ERROR_NONE) {
            $err = TextFormat::RED. "Could not validate your minecraftpocket-servers.com API key!";
            $err .= " Server response was '" . TextFormat::YELLOW . $response_raw ."'";
            $err .= TextFormat::YELLOW . " key was " . $votingAPIKey;
            $this->plugin->getLogger()->warning($err);
            $this->validatedKey = false;
            return false;
        }
        if(!isset($response->name)) {
            $err = TextFormat::RED. "Could not validate your minecraftpocket-servers.com API key!";
            $err .= " Server response was '" . TextFormat::YELLOW . $response_raw ."'";
            $this->plugin->getLogger()->warning($err);
            $this->validatedKey = false;
            return false;
        }
        
        $infoMessage = "Voting enabled for " . $response->name;
        $this->plugin->getLogger()->info(TextFormat::GREEN.$infoMessage);
        $this->validatedKey = true;
        return true;        
    }
    
    public function hasPlayerVoted($playerName) {
        $apikey = $this->apiKey;
        $url = "http://minecraftpocket-servers.com/api/?object=votes&element=claim";
        $url .= "&key=" . urlencode($apikey);
        $url .= "&username=" . urlencode($playerName);
        $response = file_get_contents($url);
        $valid_responses = ["0", "1", "2"];
        if( !in_array($response, $valid_responses) ) {
            $msg = "Got an invalid response from minecraftpocket-servers api ";
            $msg .= "when " . $player->getName() . " tried to claim a plot. ";
            $msg .= "Reponse was " . $api_response;
            $this->getPlugin()->getLogger()->error($msg);
        }
        return $response;
    }
    
    public function claimVoteReward($playerName) {
        $apikey = $this->apiKey;
        $url = "http://minecraftpocket-servers.com/api/?action=post&object=votes&element=claim";
        $url .= "&key=" . urlencode($apikey);
        $url .= "&username=" . urlencode($playerName);
        $reponse = file_get_contents($url);
        if($reponse != "1") {
            $msg = "MyPlot - got an invalid response from minecraftpocket-servers ";
            $msg .= "api when " . $playerName . " tried to claim a vote reward. ";
            $msg .= "Reponse was " . $reponse;
            $this->getPlugin()->getLogger()->warning($msg);
        }
        return ($reponse == "1");
    }
    
    public function getPlugin() {
        return $this->plugin;
    }
}