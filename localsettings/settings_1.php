<?php

/*************************
 * Local settings for league with ID = 2
 *************************/
 
/* General */

$settings['league_name'] = '<center>ORACL<br>Open Rh&ocirc;ne Alpes Carnage League</center>'; // Name of the site or the league name if only one league is being managed.
$settings['forum_url']   = 'http://www.ainpacte.org/joomla1.5/index.php?option=com_kunena&Itemid=100003&func=showcat&catid=20'; // URL of league forum, if you have such. If not then leave this empty, that is = '' (two quotes only).
$settings['stylesheet']  = 1;                  // Default is 1. OBBLM CSS stylesheet for non-logged in guests. Currently stylesheet 1 and 2 are the only existing stylesheets.
$settings['lang']        = 'fr-FR';            // Default language. Existing: en-GB, es, de, fr.
$settings['fp_links']    = true;               // Default is true. Generate coach, team and player links on the front page?
$settings['welcome']     = 'Bienvenue sur le module ORACL';
$settings['rules']       = 'Please replace this line in your local league settings file, <i>localsettings/settings_1.php</i>, with your own league rules description for the league with ID = 1';

/* Electronics */
$settings['leegmgr_cyanide']=false;
$settings['leegmgr_botocs']=false;

/* Standings pages */

$settings['standings']['length_players'] = 30;  // Number of entries on the general players standings table.
$settings['standings']['length_teams']   = 30;  // Number of entries on the general teams   standings table.
$settings['standings']['length_coaches'] = 30;  // Number of entries on the general coaches standings table.

/* Front page messageboard */

$settings['fp_messageboard']['length']               = 5;    // Number of entries on the front page message board.
$settings['fp_messageboard']['show_team_news']       = true; // Default is true. Show team news on the front page message board.
$settings['fp_messageboard']['show_match_summaries'] = true; // Default is true. Show match summaries on the front page message board.

/*
    The below settings define which boxes to show on the right side of the front page.
    
    Note, every box MUST have a unique 'box_ID' number.
    The box IDs are used to determine the order in which the boxes are shown on the front page.
    The box with 'box_ID' = 1 is shown at the top of the page, the box with 'box_ID' = 2 is displayed underneath it and so forth.
*/

/* Front page tournament standings boxes */

$settings['fp_standings'] = array(
    # This will display a standings box of the top 6 teams in tournament with ID = 13
    array(
        'id'     => 13,
        'box_ID' => 2,
        'title'  => 'ORACL - Open - Saison 1',
        'length' => 6,
        # Format: "Displayed table column name" => "OBBLM field name".
        'fields' => array('Nom' => 'name', 'PTS' => 'pts', 'SOR' => 'cas', 'V' => 'won', 'D' => 'lost', 'N' => 'draw', 'TD+' => 'gf', 'TD-' => 'ga'),
    ),
);

/* Front page leaders boxes */

$settings['fp_leaders'] = array(
    # Please note: You can NOT make expressions out of leader fields e.g.: 'field' => 'cas+td'
    # This will display a 'most CAS' player leaders box for the tournament with ID = 1
    array(
        'id'     => 13,
        'box_ID' => 3,
        'title'  => 'ORACL: Meilleur destructeur',  
        'field'  => 'cas',
        'length' => 5,
    ),
    # This will display a 'most TD' player leaders box for the tournament with ID = 2
    array(
        'id'     => 13,
        'box_ID' => 4,
        'title'  => 'ORACL: Meilleur marqueur',  
        'field'  => 'td',
        'length' => 5,
    ),
);

/* Front page latest games boxes */

$settings['fp_latestgames'] = array(
       # This will display a latest games box for the node (league, division or tournament) with ID = 1
    array(
        'id'     => 1,
        'box_ID' => 1,
        // Please note: 'type' may be either one of: 'league', 'division' or 'tournament'
        'type'   => 'league', # This sets the node to be a league. I.e. this will make a latest games box for the league with ID = 1
        'title'  => 'Matchs r&eacute;cents de la ligue ORACL',
        'length' => 5,
    ),
);

?>
