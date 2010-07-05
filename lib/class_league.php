<?php

/*
 *  Copyright (c) Nicholas Mossor Rathmann <nicholas.rathmann@gmail.com> 2009. All Rights Reserved.
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

class League
{
/***************
 * Properties 
 ***************/
 
public $lid = 0; // League ID.
public $tie_teams = true;
public $name = '';
public $date = ''; 
public $location = ''; // Physical location of league.
 
/***************
 * Methods 
 ***************/

function __construct($lid) {
	global $db_prefix;
    $result = mysql_query("SELECT * FROM ".$db_prefix."leagues WHERE lid = $lid");
    $row = mysql_fetch_assoc($result);
    foreach ($row as $col => $val) {
        $this->$col = ($val) ? $val : 0;
    }
    
    if (!$this->name) {$this->name = '';} # Make $name empty string and not zero when empty in mysql.
    if (!$this->location) {$this->location = '';}
    if (!$this->date) {$this->date = '';}
}

public function delete()
{
	global $db_prefix;
    $status = true;
    foreach ($this->getDivisions() as $d) {
        $status &= $d->delete();
    }
    return ($status && mysql_query("DELETE FROM ".$db_prefix."leagues WHERE lid = $this->lid"));
}

public function setName($name)
{
	global $db_prefix;
    $query = "UPDATE ".$db_prefix."leagues SET name = '".mysql_real_escape_string($name)."' WHERE lid = $this->lid";
    return (get_alt_col('leagues', 'name', $name, 'lid')) ? false : mysql_query($query);
}

public function setLocation($location)
{
	global $db_prefix;
    $query = "UPDATE ".$db_prefix."leagues SET location = '".mysql_real_escape_string($location)."' WHERE lid = $this->lid";
    return mysql_query($query);
}

public function setTeamDivisionTies($bool)
{
	global $db_prefix;
    $query = "UPDATE ".$db_prefix."leagues SET tie_teams = ".($bool ? 'TRUE' : 'FALSE')." WHERE lid = $this->lid";
    return mysql_query($query);
}

public function getDivisions($onlyIds = false)
{
	global $db_prefix;
    $divisions = array();
    $result = mysql_query("SELECT did FROM ".$db_prefix."divisions WHERE f_lid = $this->lid");
    if ($result && mysql_num_rows($result) > 0) {
        while ($row = mysql_fetch_assoc($result)) {
            array_push($divisions, ($onlyIds) ? $row['did'] : new Division($row['did']));
        }
    }
    return $divisions;    
}

public static function getLeagues($onlyIds = false)
{
	global $db_prefix;
    $leagues = array();
    $result = mysql_query("SELECT lid FROM ".$db_prefix."leagues");
    if ($result && mysql_num_rows($result) > 0) {
        while ($row = mysql_fetch_assoc($result)) {
            array_push($leagues, ($onlyIds) ? $row['lid'] : new League($row['lid']));
        }
    }
    return $leagues;
}

public static function create($name, $location, $tie_teams)
{
	global $db_prefix;
    $query = "INSERT INTO ".$db_prefix."leagues (date, location, name, tie_teams) VALUES (NOW(), '".mysql_real_escape_string($location)."', '".mysql_real_escape_string($name)."', ".((int) $tie_teams).")";
    return (get_alt_col('leagues', 'name', $name, 'lid')) ? false : mysql_query($query);
}
}
