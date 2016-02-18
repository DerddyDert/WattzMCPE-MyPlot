<?php
namespace MyPlot\provider;

use MyPlot\MyPlot;
use MyPlot\Plot;

abstract class DataProvider
{
    /** @var Plot[] */
    protected $cache = [];
    /** @var int */
    protected $cacheSize;
    /** @var MyPlot */
    protected $plugin;
    /** @var boolean */
    protected $cacheAll;

    public function __construct(MyPlot $plugin, $cacheSize = 0, $cacheAll = false) {
        $this->plugin = $plugin;
        $this->cacheSize = ($cacheAll == false) ? $cacheSize : -1;
        $this->cacheAll = $cacheAll;
    }

    protected final function cachePlot(Plot $plot) {
        $usecache = ($this->cacheSize > 0) || $this->cacheAll;
        if(!$usecache) {
            return;
        }
        $cacheFull = ($this->cacheSize <= count($this->cache)) && ( !$this->cacheAll );
        $key = $plot->levelName . ';' . $plot->X . ';' . $plot->Z;
        $alreadyInCache = isset($this->cache[$key]);

        if ($alreadyInCache) {
            unset($this->cache[$key]);
        } elseif($cacheFull) {
            array_pop($this->cache);
        }
        $this->cache = array_merge(array($key => clone $plot), $this->cache);
    }
    
    protected final function removePlotFromCache($levelName, $X, $Z) {
        if ($this->cacheSize > 0) {
            $key = $levelName . ';' . $X . ';' . $Z;
            if (isset($this->cache[$key])) {
                unset($this->cache[$key]);
            }
        }
        return new Plot ( $levelName, $X, $Z );
    }

    protected final function getPlotFromCache($levelName, $X, $Z) {
        $key = $levelName . ';' . $X . ';' . $Z;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        return null;
    }
    
    protected final function getPlotFromCacheById($id) {
        foreach($this->cache as $currentPlot) {
            if($currentPlot->id == $id) {
                return $currentPlot;
            }
        }
        return null;
    }

    /**
     * @param Plot $plot
     * @return bool
     */
    public abstract function savePlot(Plot $plot);

    /**
     * @param Plot $plot
     * @return bool
     */
    public abstract function deletePlot(Plot $plot);

    /**
     * @param string $levelName
     * @param int $X
     * @param int $Z
     * @return Plot
     */
    public abstract function getPlot($levelName, $X, $Z);
    
    /**
     * @param int $id
     * @return Plot|null
     */
    public abstract function getPlotById($id);

    /**
     * @param string $owner
     * @param string $levelName
     * @return Plot[]
     */
    public abstract function getPlotsByOwner($owner, $levelName = "");

    /**
     * @param string $levelName
     * @param int $limitXZ
     * @return Plot|null
     */
    public abstract function getNextFreePlot($levelName, $limitXZ = 20);

    public abstract function close();
    
    
    
    /**
     * Functions to be called by child class if cacheAll is set
     */
    /**
     * @param string $owner
     * @param string $levelName
     * @return Plot[]
     */
    public function cache_getPlotsByOwner($owner, $levelName = "") {
        $returnPlots = [];
        
        foreach($this->cache as $currentPlot) {
            $isOwner = strtolower($currentPlot->owner) == strtolower($owner);
            $isLevel = strtolower($currentPlot->levelName) == strtolower($levelName);
            $isLevel = ($levelName == "") ? true : $isLevel;
            if( $isOwner && $isLevel ) {
                $returnPlots[] = $currentPlot;
            }
        }

        usort ( $returnPlots, function ($plot1, $plot2) {
                /** @var Plot $plot1 */
                /** @var Plot $plot2 */
                return strcmp ( $plot1->levelName, $plot2->levelName );
        } );
        return $returnPlots;
    }
    
    /**
     * @param string $levelName
     * @param int $limitXZ
     * @return Plot|null
     */
    public function cache_getNextFreePlot($levelName, $limitXZ = 20, $currentX = null, $currentZ = null) {
        $foundPlots = [];
        foreach( $this->cache as $currentOccupiedPlot ) {
            $diffx = ( $currentOccupiedPlot->X - $currentX );
            $diffz = ( $currentOccupiedPlot->Z - $currentZ );
            $tooCloseToCurrent = ($diffx > -2) && ($diffx < 2) && ($diffz > -2) && ($diffz < 2);
            if($tooCloseToCurrent) {
                continue;
            }
            
            $potentialPlotCoords = [
                "X+1" => [ "X" => $currentOccupiedPlot->X+1, "Z" => $currentOccupiedPlot->Z ],
                "X-1" => [ "X" => $currentOccupiedPlot->X-1, "Z" => $currentOccupiedPlot->Z ],
                "Z+1" => [ "X" => $currentOccupiedPlot->X, "Z" => $currentOccupiedPlot->Z+1 ],
                "Z-1" => [ "X" => $currentOccupiedPlot->X, "Z" => $currentOccupiedPlot->Z-1 ]
            ];

            foreach($potentialPlotCoords as $potLocation) {
                $currentPotentialKey = $levelName . ';' . $potLocation["X"] . ';' . $potLocation["Z"];
                $potentialPlotNotInCache = ! array_key_exists($currentPotentialKey, $this->cache);
                if($potentialPlotNotInCache) {
                   $foundPlots[] = new Plot ( $levelName, $potLocation["X"], $potLocation["Z"] );
                } else {
                    // found a plots that is cached (but is it empty ?)
                    $potentialPlot = $this->cache[$currentPotentialKey];
                    if( $potentialPlot->owner == "" ) {
                         $foundPlots[] = $this->cache[$currentPotentialKey];
                    }
                }
            }
            
        }
        
        return $foundPlots[array_rand ( $foundPlots, 1 )];
    }
    
    
}