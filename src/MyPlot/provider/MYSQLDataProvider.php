<?php

namespace MyPlot\provider;

use MyPlot\MyPlot;
use MyPlot\Plot;
use Mysqli;

class MYSQLDataProvider extends DataProvider {
	/** @var \mysqli */
	private $db;
	private $db_statements;
        
        
	public function __construct(MyPlot $plugin, $cacheSize = 0, $cacheAll = false) {
            parent::__construct ( $plugin, $cacheSize, $cacheAll );
            $mysqlhost = $plugin->getConfig()->get("mysqlhost");
            $mysqluser = $plugin->getConfig()->get("mysqluser");
            $mysqlpass = $plugin->getConfig()->get("mysqlpass");
            $mysqldb = $plugin->getConfig()->get("mysqldb");
            $this->db = new \mysqli ( $mysqlhost, $mysqluser, $mysqlpass, $mysqldb );
            $this->db->query ( "CREATE TABLE IF NOT EXISTS `plots`
                 (id INTEGER PRIMARY KEY auto_increment, 
                  level TEXT, 
                  X INTEGER, 
                  Z INTEGER, 
                  name TEXT, 
                  owner TEXT, 
                  helpers TEXT, 
                  biome TEXT);" );
            @$this->db->query ( "CREATE INDEX `XZ` ON `plots` (X,Z);" );

            // upgrade database without locked column
            @$this->db->query ( "ALTER TABLE `plots` ADD `locked` INTEGER;" );

            $queryName = "GetPlot"; // sii level, X, Z
            $sql = "SELECT id, name, owner, helpers, biome, locked FROM plots WHERE level = ? AND X = ? AND Z = ?;";
            $this->checkPreparedStatement ( $queryName, $sql );
            
            $queryName = "GetPlotById"; // i id
            $sql = "SELECT id, X, Z, level, name, owner, helpers, biome, locked FROM plots WHERE id = ?;";
            $this->checkPreparedStatement ( $queryName, $sql );
            
            $queryName = "GetAllPlots";
            $sql = "SELECT id, X, Z, level, name, owner, helpers, biome, locked FROM plots;";
            $this->checkPreparedStatement ( $queryName, $sql );

            $queryName = "SavePlot"; // siisiissss level, X, Z, level, X, Z, name, owner, helpers, biome
            $sql = "
                REPLACE INTO plots (id, level, X, Z, name, owner, helpers, biome, locked) VALUES
                ((select id from plots AS tmp where level = ? AND X = ? AND Z = ?),
                 ?, ?, ?, ?, ?, ?, ?, ?);";
            $this->checkPreparedStatement ( $queryName, $sql );
            
            // only to be used when we have a full cache ($this->cacheAll = true _
            $queryName = "SaveNewPlot"; // siisiissss level, X, Z, level, X, Z, name, owner, helpers, biome
            $sql = "
                INSERT INTO plots (level, X, Z, name, owner, helpers, biome, locked) VALUES
                ( ?, ?, ?, ?, ?, ?, ?, ?);";
            $this->checkPreparedStatement ( $queryName, $sql );

            $queryName = "SavePlotById"; // ssssi
            $sql = "UPDATE plots SET name = ?, owner = ?, helpers = ?, biome = ?, locked = ? WHERE id = ?;";
            $this->checkPreparedStatement ( $queryName, $sql );

            $queryName = "RemovePlot"; // sii
            $sql = "DELETE FROM plots WHERE level = ? AND X = ? AND Z = ?;";
            $this->checkPreparedStatement ( $queryName, $sql );

            $queryName = "RemovePlotById"; // i
            $sql = "DELETE FROM plots WHERE id = ?;";
            $this->checkPreparedStatement ( $queryName, $sql );

            $queryName = "getPlotsByOwner"; // s
            $sql = "SELECT id, name, owner, helpers, biome, X, Z, level, locked
                    FROM plots WHERE owner = ?;";
            $this->checkPreparedStatement ( $queryName, $sql );

            $queryName = "getPlotsByOwnerAndLevel"; // ss
            $sql = "SELECT id, name, owner, helpers, biome, X, Z, level, locked
                    FROM plots WHERE owner = ? AND level = ?;";
            $this->checkPreparedStatement ( $queryName, $sql );

            $queryName = "GetExistingXZ"; // siiii level, size x 4
            $sql = "SELECT X, Z FROM plots WHERE (
                    level = ?
                    AND (
                        (abs(X) = ? AND abs(Z) <= ?) OR
                        (abs(Z) = ? AND abs(X) <= ?)
                    )
                )";
            $this->checkPreparedStatement ( $queryName, $sql );

            $queryName = "GetFreeXZ";
            $sql = "
                SELECT X, Z,
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X - 1 AND sub1.Z=plots.Z LIMIT 1)) AS `xm1`,
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X + 1 AND sub1.Z=plots.Z LIMIT 1)) AS `xp1`,
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X AND sub1.Z=plots.Z - 1 LIMIT 1)) AS `zm1`,
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X AND sub1.Z=plots.Z + 1 LIMIT 1)) AS `zp1`,
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X + 1 AND sub1.Z=plots.Z + 1 LIMIT 1)) AS `xp1zp1`,
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X - 1 AND sub1.Z=plots.Z - 1 LIMIT 1)) AS `xm1zm1`,
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X + 1 AND sub1.Z=plots.Z - 1 LIMIT 1)) AS `xp1zm1`,
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X - 1 AND sub1.Z=plots.Z + 1 LIMIT 1)) AS `xm1zp1`
                FROM `plots` WHERE
                (
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X - 1 AND sub1.Z=plots.Z LIMIT 1)) OR
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X + 1 AND sub1.Z=plots.Z LIMIT 1)) OR
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X AND sub1.Z=plots.Z - 1 LIMIT 1)) OR
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X AND sub1.Z=plots.Z + 1 LIMIT 1)) OR
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X + 1 AND sub1.Z=plots.Z + 1 LIMIT 1)) OR
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X - 1 AND sub1.Z=plots.Z - 1 LIMIT 1)) OR
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X + 1 AND sub1.Z=plots.Z - 1 LIMIT 1)) OR
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X - 1 AND sub1.Z=plots.Z + 1 LIMIT 1))
                ) 
                AND
                (
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X - 1 AND sub1.Z=plots.Z LIMIT 1)) +
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X + 1 AND sub1.Z=plots.Z LIMIT 1)) +
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X AND sub1.Z=plots.Z - 1 LIMIT 1)) +
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X AND sub1.Z=plots.Z + 1 LIMIT 1)) +
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X + 1 AND sub1.Z=plots.Z + 1 LIMIT 1)) +
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X - 1 AND sub1.Z=plots.Z - 1 LIMIT 1)) +
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X + 1 AND sub1.Z=plots.Z - 1 LIMIT 1)) +
                isnull ((SELECT id FROM plots AS sub1 WHERE sub1.X=plots.X - 1 AND sub1.Z=plots.Z + 1 LIMIT 1))
                ) < 8 
                AND level = ? 
                AND (  (`plots`.`X` < (? - 1))  OR  (`plots`.`X` > (? + 1))  )
                AND (  (`plots`.`Z` < (? - 1))  OR  (`plots`.`Z` > (? + 1))  )
            ORDER BY ABS(X)+ABS(Z) ASC
                LIMIT 1;
            ";
            $this->checkPreparedStatement ( $queryName, $sql );
            
            if($this->cacheAll) {
                $this->plugin->getLogger()->info("Preloading ALL plot data");
                $this->getAllPlots();
                $this->plugin->getLogger()->info("Loaded plots - count is " . count($this->cache));
            }
	}
        
	private function criticalError($errmsg) {
		die ( $errmsg );
	}
        
	private function checkPreparedStatement($queryname, $sql) {
		if (! isset ( $this->db_statements [$queryname] )) {
			$this->db_statements [$queryname] = $this->db->prepare ( $sql );
		}
		if ($this->db_statements [$queryname] === false) {
			$this->criticalError ( "Database error preparing query for  " . $queryname . ": " . $this->db->error );
			return false;
		}
		return true;
	}
	public function close() {
		$this->db->close ();
	}
        
	public function savePlot(Plot $plot) {
                $plotAlreadyHadId = ($plot->id >= 0);
		$helpers = implode ( ",", $plot->helpers );
		if ($plotAlreadyHadId) {
			$thisQueryName = "SavePlotById";
			$bind_result = $this->db_statements [$thisQueryName]->bind_param (
                                "ssssii", 
                                $plot->name, $plot->owner, $helpers, $plot->biome, $plot->locked, $plot->id
                        );
		} elseif( ! $this->cacheAll) {
			$thisQueryName = "SavePlot";
			$bind_result = $this->db_statements [$thisQueryName]->bind_param (
                                "siisiissssi", 
                                $plot->levelName, $plot->X, $plot->Z, $plot->levelName, 
                                $plot->X, $plot->Z, $plot->name, $plot->owner, 
                                $helpers, $plot->biome, $plot->locked
                        );
		} else { // implies $this->cacheAll=true
                        $thisQueryName = "SaveNewPlot";
			$bind_result = $this->db_statements [$thisQueryName]->bind_param (
                                "siissssi", 
                                $plot->levelName, 
                                $plot->X, $plot->Z, $plot->name, $plot->owner, 
                                $helpers, $plot->biome, $plot->locked
                        );
                }
		
		if ($bind_result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Could not bind to query " . $thisQueryName . ":" . $err );
			return false;
		}
		$exec_result = $this->db_statements [$thisQueryName]->execute ();
		if ($exec_result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Could not execute query " . $thisQueryName . ":" . $err );
			return false;
		}
                if( $thisQueryName == "SaveNewPlot" ) {
                    $plot->id = $this->db->insert_id;
                }
                
                $this->db_statements [$thisQueryName]->free_result ();
                
                
                if( ! $plotAlreadyHadId ) {
                    $msg = "Saved plot to database, existing ID " . $plot->id;
                    $this->plugin->getServer()->getLogger()->debug($msg);
                    $this->cachePlot ( $plot );
                } elseif( ! $this->cacheAll ) {
                    /* if plot didnt have a save id remove it from cache
                     * and reload it ( last insert id cannot be used here
                     * due to the sql being a REPLACE INTO query )
                     **/
                    $this->removePlotFromCache($plot->levelName, $plot->X, $plot->Z);
                    $plot = $this->getPlot($plot->levelName, $plot->X, $plot->Z);
                    // getplot function will recache no need to call cachePlot
                    $msg = "Saved plot to database, got new ID " . $plot->id;
                    $this->plugin->getServer()->getLogger()->debug($msg);
                } else { // implies $thisQueryName == "SaveNewPlot"
                    $this->cachePlot($plot);
                    $msg = "Saved new plot $plot->X, $plot->Z to database , insert_id " . $plot->id;
                    $this->plugin->getServer()->getLogger()->debug($msg);
                }
		return true;
	}
        
	public function deletePlot(Plot $plot) {
		if ($plot->id >= 0) {
			$thisQueryName = "RemovePlotById";
			$bind_result = $this->db_statements [$thisQueryName]->bind_param (
                                "i", $plot->id
                        );
		} else {
			$thisQueryName = "RemovePlot";
			$bind_result = $this->db_statements [$thisQueryName]->bind_param (
                                "sii", $plot->levelName, $plot->X, $plot->Z 
                        );
		}
		
		if ($bind_result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Could not bind to query " . $thisQueryName . ": " . $err );
			return false;
		}
		
		$exec_result = $this->db_statements [$thisQueryName]->execute ();
		if ($exec_result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Could not execute query " . $thisQueryName . ": " . $err );
			return false;
		}
		
		$this->db_statements [$thisQueryName]->free_result ();
		$plot = new Plot ( $plot->levelName, $plot->X, $plot->Z );
		$this->cachePlot ( $plot );
		return true;
	}
        
        /**
         * @param int $id
         * @return boolean|Plot|null
         */
        public function getPlotById($id) {
		if ($plot = $this->getPlotFromCacheById ( $id )) {
			return $plot;
		}
                // if set to load all plots at startup we dont need to check database
                // for the plots existence just return null
                if($this->cacheAll) {
                    return null;
                }
                
		$thisQueryName = "GetPlotById";
		$bind_result = $this->db_statements [$thisQueryName]->bind_param (
                        "i", $id
                );
		if ($bind_result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Could not bind to query " . $thisQueryName . ": " . $err );
			return false;
		}
		
		$exec_result = $this->db_statements [$thisQueryName]->execute ();
		if ($exec_result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Could not execute query " . $thisQueryName . ": " . $err );
			return false;
		}
		
		$result = $this->db_statements [$thisQueryName]->bind_result ( 
                        $id, $X, $Z, $levelName, $name, $owner, $helpers_csv, $biome, $locked
                );
		if ($result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Failed to bind result " . $thisQueryName . ": " . $err );
			return false;
		}
		
		if ($this->db_statements [$thisQueryName]->fetch ()) {
			if (is_null ( $helpers_csv )) {
				$helpers = array ();
			} else {
				$helpers = explode ( ",", ( string ) $helpers_csv );
			}
			$plot = new Plot (
                                $levelName, $X, $Z, ( string ) $name, $owner, 
                                $helpers, ( string ) $biome, ( int ) $id, $locked );
		} else {
                        @$this->db_statements [$thisQueryName]->free_result ();
			return null;
		}
		
		$this->db_statements [$thisQueryName]->free_result ();
		$this->cachePlot ( $plot );
		return $plot;
	}
        
        public function getPlot($levelName, $X, $Z) {
		if ($plot = $this->getPlotFromCache ( $levelName, $X, $Z )) {
			return $plot;
		}
                // if set to load all plots at startup we dont need to check database
                // for the plots existence just return empty
                if($this->cacheAll) {
                    return new Plot ( $levelName, $X, $Z );
                }
		
		$thisQueryName = "GetPlot";
		$bind_result = $this->db_statements [$thisQueryName]->bind_param (
                        "sii", $levelName, $X, $Z 
                );
		if ($bind_result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Could not bind to query " . $thisQueryName . ": " . $err );
			return false;
		}
		
		$exec_result = $this->db_statements [$thisQueryName]->execute ();
		if ($exec_result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Could not execute query " . $thisQueryName . ": " . $err );
			return false;
		}
		
		$result = $this->db_statements [$thisQueryName]->bind_result ( 
                        $id, $name, $owner, $helpers_csv, $biome, $locked
                );
		if ($result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Failed to bind result " . $thisQueryName . ": " . $err );
			return false;
		}
		
		if ($this->db_statements [$thisQueryName]->fetch ()) {
			if (is_null ( $helpers_csv )) {
				$helpers = array ();
			} else {
				$helpers = explode ( ",", ( string ) $helpers_csv );
			}
			$plot = new Plot (
                                $levelName, $X, $Z, ( string ) $name, $owner, 
                                $helpers, ( string ) $biome, ( int ) $id, $locked );
		} else {
			$plot = new Plot ( $levelName, $X, $Z );
		}
		
		$this->db_statements [$thisQueryName]->free_result ();
		$this->cachePlot ( $plot );
		return $plot;
	}
        
	public function getAllPlots() {
		$thisQueryName = "GetAllPlots";
                
		$exec_result = $this->db_statements [$thisQueryName]->execute ();
		if ($exec_result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Could not execute query " . $thisQueryName . ": " . $err );
			return false;
		}
		
		$result = $this->db_statements [$thisQueryName]->bind_result ( 
                        $id, $X, $Z, $levelName, $name, $owner, $helpers_csv, $biome, $locked
                );
		if ($result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Failed to bind result " . $thisQueryName . ": " . $err );
			return false;
		}
		
		while ($this->db_statements [$thisQueryName]->fetch ()) {
			if (is_null ( $helpers_csv )) {
				$helpers = array ();
			} else {
				$helpers = explode ( ",", ( string ) $helpers_csv );
			}
			$plot = new Plot (
                                $levelName, $X, $Z, ( string ) $name, $owner, 
                                $helpers, ( string ) $biome, ( int ) $id, $locked );
                        $key = $levelName . ';' . $X . ';' . $Z;
                        $this->cache[$key] = $plot;
                }
		$this->db_statements [$thisQueryName]->free_result ();
	}
        
	public function getPlotsByOwner($owner, $levelName = "") {
                if($this->cacheAll) {
                    return $this->cache_getPlotsByOwner($owner, $levelName);
                }
            
		if ($levelName == "") {
			$thisQueryName = "getPlotsByOwner";
			$bind_result = $this->db_statements [$thisQueryName]->bind_param ( "s", $owner );
		} else {
			$thisQueryName = "getPlotsByOwnerAndLevel";
			$bind_result = $this->db_statements [$thisQueryName]->bind_param ( "ss", $owner, $levelName );
		}
		if ($bind_result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Could not bind to query " . $thisQueryName . ": " . $err );
			return false;
		}
		
		$exec_result = $this->db_statements [$thisQueryName]->execute ();
		if ($exec_result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Could not execute query " . $thisQueryName . ": " . $err );
			return false;
		}
		
		$result = $this->db_statements [$thisQueryName]->bind_result (
                        $id, $name, $owner, $helpers, $biome, $X, $Z, $foundlevel, $locked
                );
		if ($result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Failed to bind result " . $thisQueryName . ": " . $err );
			return false;
		}
		
		$plots = [ ];
		
		while ( $this->db_statements [$thisQueryName]->fetch () ) {
			if (is_null ( $helpers )) {
				$helpers = array ();
			} else {
				$helpers = explode ( ",", ( string ) $helpers );
			}
			$plots [] = new Plot ( 
                                $foundlevel, ( int ) $X, ( int ) $Z, ( string ) $name,
                                $owner, $helpers, ( string ) $biome, ( int ) $id, $locked );
		}
		
		$this->db_statements [$thisQueryName]->free_result ();
		
		usort ( $plots, function ($plot1, $plot2) {
			/** @var Plot $plot1 */
			/** @var Plot $plot2 */
			return strcmp ( $plot1->levelName, $plot2->levelName );
		} );
		return $plots;
	}
	public function getNextFreePlot($levelName, $limitXZ = 20, $currentX = null, $currentZ = null) {
		if (is_null ( $currentX ))
			$currentX = - 99999;
		if (is_null ( $currentZ ))
			$currentZ = - 99999;
                
                if($this->cacheAll) {
                    return $this->cache_getNextFreePlot($levelName, $limitXZ, $currentX, $currentZ);
                }
		
		$thisQueryName = "GetFreeXZ";
		
		$bind_result = $this->db_statements [$thisQueryName]->bind_param ( "siiii", $levelName, $currentX, $currentX, $currentZ, $currentZ );
		
		if ($bind_result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Could not bind to query " . $thisQueryName . ": " . $err );
			return false;
		}
		
		$exec_result = $this->db_statements [$thisQueryName]->execute ();
		if ($exec_result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Could not execute query " . $thisQueryName . ": " . $err );
			return false;
		}
		
		$result = $this->db_statements [$thisQueryName]->bind_result ( $X, $Z, $xm1, $xp1, $zm1, $zp1, $xp1zp1, $xm1zm1, $xp1zm1, $xm1zp1 );
		if ($result === false) {
			$err = $this->db_statements [$thisQueryName]->error;
			$this->criticalError ( "Failed to bind result " . $thisQueryName . ": " . $err );
			return false;
		}
		
		$potential_plots = array ();
		
		while ( $this->db_statements [$thisQueryName]->fetch () ) {
			if ($xm1) { // x-1
				$potential_plots [] = new Plot ( $levelName, $X - 1, $Z );
                        }
			if ($xp1) { // x+1
				$potential_plots [] = new Plot ( $levelName, $X + 1, $Z );
			}
                        if ($zm1) { // z-1
				$potential_plots [] = new Plot ( $levelName, $X, $Z );
                        }
			if ($zp1) { // z+1
				$potential_plots [] = new Plot ( $levelName, $X, $Z );
                        }
                        if ($xp1zp1) { // x+1 z+1
				$potential_plots [] = new Plot ( $levelName, $X + 1, $Z + 1 );
                        }
                        if ($xm1zm1) { // x-1 z-1
				$potential_plots [] = new Plot ( $levelName, $X - 1, $Z - 1 );
                        }
			if ($xp1zm1) { // x+1 z-1
				$potential_plots [] = new Plot ( $levelName, $X + 1, $Z - 1 );
                        }
			if ($xm1zp1) { // x-1 z+1
				$potential_plots [] = new Plot ( $levelName, $X - 1, $Z + 1 );
                        }
		}
		
		$this->db_statements [$thisQueryName]->free_result ();
		
		foreach ( $potential_plots as $potential_plot ) {
			$this->cachePlot ( $potential_plot );
		}
		
		if (count ( $potential_plots ) < 1) {
			return null;
		}
		
		return $potential_plots [array_rand ( $potential_plots )];
	}
}
