<?php
namespace MyPlot\task;

use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat;

class ClearPlotTask extends PluginTask
{
    private $level, $height, $bottomBlock, $plotFillBlock, $roadBlock, $wallBlock, $plotFloorBlock,
            $plotBeginPos, $xMax, $zMax, $roadWidth, $maxBlocksPerTick, $issuer, $offset;

    public function __construct(MyPlot $plugin, Plot $plot, Player $issuer = null, $maxBlocksPerTick = 256) {
        parent::__construct($plugin);
        $this->offset = 1;
        $this->plotBeginPos = $plugin->getPlotPosition($plot);
        $this->level = $this->plotBeginPos->getLevel();

        $plotLevel = $plugin->getLevelSettings($plot->levelName);

        $plotSize = $plotLevel->plotSize;
        $roadWidth = $plotLevel->roadWidth;
        $halfRoadWidth = round($roadWidth / 2); // the road width that concerns this plot
        
        // mwvent - added offset of half a road width
        $this->plotBeginPos->x -= $halfRoadWidth;
        $this->plotBeginPos->z -= $halfRoadWidth;
        $this->roadWidth = $halfRoadWidth;
        
        $this->xMax = $this->plotBeginPos->x + $plotSize + $roadWidth + $this->offset; // mwvent added roadwidth as part of regen
        $this->zMax = $this->plotBeginPos->z + $plotSize + $roadWidth + $this->offset;

        $this->height = $plotLevel->groundHeight;
        $this->bottomBlock = $plotLevel->bottomBlock;
        $this->plotFillBlock = $plotLevel->plotFillBlock;
        $this->plotFloorBlock = $plotLevel->plotFloorBlock;
        $this->roadBlock = $plotLevel->roadBlock;
        $this->wallBlock = $plotLevel->wallBlock;

        $this->maxBlocksPerTick = $maxBlocksPerTick;
        $this->issuer = $issuer;

        $this->pos = new Vector3($this->plotBeginPos->x, 0, $this->plotBeginPos->z);
    }

    public function onRun($tick) {
        $blocks = 0;
        while ($this->pos->x < $this->xMax) {
            while ($this->pos->z < $this->zMax) {
                while ($this->pos->y < 128) {
                	// $this->offset
                    // is a road?
                    $isRoad = false;
                    if( ($this->pos->x) <= ( $this->plotBeginPos->x + $this->roadWidth ) - 2 + 0 ) $isRoad = true;
                    if( ($this->pos->x) >= ( $this->xMax - $this->roadWidth ) + 2 - $this->offset ) $isRoad = true;
                    if( ($this->pos->z) <= ( $this->plotBeginPos->z + $this->roadWidth ) - 2 + 0 ) $isRoad = true;
                    if( ($this->pos->z) >= ( $this->zMax - $this->roadWidth ) + 2 - $this->offset ) $isRoad = true;
                    // wall flag
                    $isWall = false;
                    if( ($this->pos->x) == ( $this->plotBeginPos->x + $this->roadWidth - 1 + 0 ) ) $isWall = true;
                    if( ($this->pos->x) == ( $this->xMax - $this->roadWidth + 1 - $this->offset) ) $isWall = true;
                    if( ($this->pos->z) == ( $this->plotBeginPos->z + $this->roadWidth ) - 1 + 0 ) $isWall = true;
                    if( ($this->pos->z) == ( $this->zMax - $this->roadWidth ) + 1 - $this->offset) $isWall = true;
                    if($isRoad) $isWall = false;
                    
                    // set block type
                    if ($this->pos->y === 0) {
                        $block = $this->bottomBlock;
                    } elseif ($this->pos->y < $this->height) {
                        $block = $this->plotFillBlock;
                    } elseif ($this->pos->y === $this->height) {
			if( $isRoad ) {
			    $block = $this->roadBlock;
			} else {
			    $block = $this->plotFloorBlock;
			}
		    } elseif ( ($this->pos->y === ($this->height + 1)) && $isWall ) {
			$block = $this->wallBlock;
                    } else {
                        $block = Block::get(0);
                    }
                    
                    $this->level->setBlock($this->pos, $block, false, false);
                    $blocks++;
                    if ($blocks === $this->maxBlocksPerTick) {
                        $this->getOwner()->getServer()->getScheduler()->scheduleDelayedTask($this, 1);
                        return;
                    }
                    $this->pos->y++;
                }
                $this->pos->y = 0;
                $this->pos->z++;
            }
            $this->pos->z = $this->plotBeginPos->z;
            $this->pos->x++;
        }
        if ($this->issuer !== null) {
            $this->issuer->sendMessage(TextFormat::GREEN . "Successfully cleared this plot");
        }
    }
}