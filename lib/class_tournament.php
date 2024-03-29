<?php

/*
 *  Copyright (c) Nicholas Mossor Rathmann <nicholas.rathmann@gmail.com> 2007-2009. All Rights Reserved.
 *
 *
 *  This file is part of OBBLM.
 *
 *  OBBLM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  OBBLM is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

// Tournament Types for MySQL tournament "type" column:
define('TT_FFA', 1);    # Free For All/manual tournament scheduling.
define('TT_RROBIN', 2); # Round-Robin

class Tour
{

    /* 
        Please note: OBBLM also uses a match's "rounds" field to distinguish ordinary matches from semi-finals and finals. 
        This means, that some round numbers are reserved for the above purpose.
        See the constant definitions from class_match.php for reserved round numbers.
    */

    /***************
     * Properties 
     ***************/

    // MySQL stored information
    public $tour_id         = 0;
    public $f_did           = 0; // From division ID.
    public $name            = '';
    public $type            = 0;
    public $date_created    = '';
    public $rs              = 0; // Ranking system.
    public $locked          = false;

    // Other
    public $winner          = null; # Team ID.
    public $is_finished     = false; # Final match has been played OR, if Round Robin, all matches have been played.
    public $is_empty        = false; # Tournament has no matches assigned with it.
    public $is_begun        = false; # Tournament contains played matches?

    /***************
     * Methods 
     ***************/

    function __construct($tour_id) {

        global $settings,$db_prefix;

        // MySQL stored information.
        $result = mysql_query("SELECT * FROM ".$db_prefix."tours WHERE tour_id = $tour_id");
        $row    = mysql_fetch_assoc($result);
        foreach ($row as $col => $val) {
            $this->$col = ($val) ? $val : 0;
        }
        $this->locked = (bool) $this->locked;
        
        $this->is_empty = $this->empty;
        $this->is_begun = $this->begun;
        $this->is_finished = $this->finished;
    }

    public function getMatches() {

        /**
         * Returns an array of match objects for those matches which are assigned to this tournament.
         **/
global $db_prefix;
        $matches = array();
        $result = mysql_query("SELECT match_id FROM ".$db_prefix."matches WHERE f_tour_id = $this->tour_id ORDER BY match_id ASC");
        if (mysql_num_rows($result) > 0) {
            while ($row = mysql_fetch_assoc($result)) {
                array_push($matches, new Match($row['match_id']));
            }
        }
        
        return $matches;
    }

    public function getTeams($only_return_ids = false) {

        /**
         * Returns an array of team objects for those teams which participate in this tournament.
         **/
		global $db_prefix;
        $teams = array();
        $team_ids = array();
        $result = mysql_query("SELECT DISTINCT(tids) AS 'tid' FROM (
            SELECT team1_id AS 'tids' FROM ".$db_prefix."matches WHERE f_tour_id = $this->tour_id 
                UNION 
            SELECT team2_id AS 'tids' FROM ".$db_prefix."matches WHERE f_tour_id = $this->tour_id
            ) AS tbl ORDER BY tids");
        if (mysql_num_rows($result) > 0) {
            while ($row = mysql_fetch_assoc($result)) {
                $team_ids[] = $row['tid'];
            }
            if ($only_return_ids) {
                return $team_ids;
            }
            foreach ($team_ids as $tid) {
                $teams[] = new Team($tid);
            }
        }
                
        return $teams;
    }

    public function getRSSortRule() {
        global $hrs;
        return isset($hrs[$this->rs]) ? $hrs[$this->rs]['rule'] : array();
    }
    
    public function isRSWithPoints() {
        // Returns bool for wheter or not this tournament's ranking system uses points.
        global $hrs;
        return !empty($hrs[$this->rs]['points']);
    }

    public function delete($force = false) {
    
        /**
         * Deletes this tournament, if no matches are assigned to it, unless forced.
         **/
        global $db_prefix;
        if ($force) {
            $q = array();
            // Don't use the match delete() routines. We do it ourselves.
            $q[] = "DELETE FROM ".$db_prefix."match_data WHERE f_tour_id = $this->tour_id";
            $q[] = "DELETE FROM ".$db_prefix."matches    WHERE f_tour_id = $this->tour_id";
            $q[] = "DELETE FROM ".$db_prefix."tours      WHERE tour_id = $this->tour_id";
            $status = true;
            foreach ($q as $query) {
                $status &= mysql_query($query);
            }
            return $status;
        }
        elseif ($this->is_empty) {			
            $query = "DELETE FROM ".$db_prefix."tours WHERE tour_id = $this->tour_id";
            if (mysql_query($query))
                return true;
        }
        else {
            return false;
        }
    }
    
    public function rename($name) {
		global $db_prefix;
        return (mysql_query("UPDATE ".$db_prefix."tours SET name = '" . mysql_real_escape_string($name) . "' WHERE tour_id = $this->tour_id"));
    }

    public function chType($type) {
	global $db_prefix;
        return (mysql_query("UPDATE ".$db_prefix."tours SET type = $type WHERE tour_id = $this->tour_id"));
    }

    public function chRS($rs) {
	global $db_prefix;
        $query = "UPDATE ".$db_prefix."tours SET rs = $rs WHERE tour_id = $this->tour_id";
        return mysql_query($query);
    }

    public function setLocked($lock) {
	global $db_prefix;
        $this->locked = (bool) $lock;
        return mysql_query("UPDATE ".$db_prefix."tours SET locked = ".(($lock) ? 1 : 0)." WHERE tour_id = $this->tour_id");
    }

    /***************
     * Statics
     ***************/

    public static function getRSstr($idx) {
        global $hrs;
        return preg_replace('/pts/', '{'.$hrs[$idx]['points'].'}', implode(', ',$hrs[$idx]['rule']));
    }

    public static function getTours() {

        /**
         * Returns an array of all tournament objects.
         **/
		global $db_prefix;
		 
        $tours = array();
        $result = mysql_query("SELECT tour_id FROM ".$db_prefix."tours ORDER BY date_created DESC");
        if (mysql_num_rows($result) > 0) {    
            while ($row = mysql_fetch_assoc($result)) {
                array_push($tours, new Tour($row['tour_id']));
            }
        }
        
        return $tours;
    }

    public static function getLatestTour() {
    
	global $db_prefix;
        /**
         * Returns the tournament object for the latest tournament.
         **/

        $result = mysql_query("SELECT tour_id FROM ".$db_prefix."tours ORDER BY date_created DESC LIMIT 1");

        if (mysql_num_rows($result) > 0) {    
            $row = mysql_fetch_assoc($result);
            return (new Tour($row['tour_id']));
        }
        else {
            return null;
        }

    }
    
    public static function create(array $input) {
    global $db_prefix;
        /**
         * Creates a new tournament.
         *
         * Arguments:
         * ----------
         *  name, type, rs, teams => array(team_ids, ...), 'rounds'
         **/

        /* Check input */

        // Done in in scheduler section code.

        /* Create tournament */
       
        // Quit if can't make tournament entry.
        $query = "INSERT INTO ".$db_prefix."tours (name, f_did, type, rs, date_created) VALUES ('" . mysql_real_escape_string($input['name']) . "', $input[did], $input[type], $input[rs], NOW())";
        if (!mysql_query($query)) {
            return false;
        }
        $tour_id = mysql_insert_id();
                
        /* Generate matches depending on type */
        
        // FFA match(es)?
        if ($input['type'] == TT_FFA) {
            $status = true;
            for ($i = 0; $i < count($input['teams'])/2; $i++) {
                 list($exitStatus, $mid) = Match::create(array(
                    'team1_id'  => $input['teams'][$i*2], 
                    'team2_id'  => $input['teams'][$i*2+1], 
                    'round'     => (($input['rounds']) ? $input['rounds'] : 1), 
                    'f_tour_id' => $tour_id
                ));
                $status &= !$exitStatus;
            }
            return $status;
        }
        // Round-Robin?
        elseif ($input['type'] == TT_RROBIN) {
            
            // Quit if can't make tournament schedule.
            $robin = new RRobin();
            if (!$robin->create($input['teams'])) # If can't create Round-Robin tour -> quit.
                return false;

            // Okey, so $input['rounds'] is incorrect in the sense that this is the multiplier of times to schedule the same round-set comprising the RR tour.
            // Instead we denote $real_rounds to be the actual number of rounds in the scheduled RR tour.
            $real_rounds = count($robin->tour);

            // Create inverse depiction round.
            foreach ($robin->tour as $ridx => $r) {
                foreach ($r as $idx => $m) {
                    $robin->tour_inv[$ridx][$idx] = array($m[1], $m[0]);
                }
            }
            
            $status = true;
            for ($i = 1; $i <= $input['rounds']; $i++) {
                foreach ($robin->{(($i % 2) ? 'tour' : 'tour_inv')} as $ridx => $r) {
                    foreach ($r as $match) { // Depict round's match compets inversely for every other round.
                        list($exitStatus, $mid) = Match::create(array('team1_id' => $match[0], 'team2_id' => $match[1], 'round' => $ridx + ($i-1)*($real_rounds), 'f_tour_id' => $tour_id));
                        $status &= !$exitStatus;
                    }
                }
            }
                
            return $status;
        }

        return false; # Return false if tournament type was not recognized.
    }
}

?>
