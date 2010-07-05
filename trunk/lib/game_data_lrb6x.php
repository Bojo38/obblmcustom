<?php

/*
 *  Copyright (c) Daniel Straalman <email is protected> 2008-2009. All Rights Reserved.
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

require('lib/game_data.php'); # LRB5

/*
 * Game data replacement for LRB6 experimental rules (LRB5b).
 */

//
// Changes to present teams/positionals in LRB5b.
//

$DEA['Nains']['players']['Roule Mort']['def'] = array (99, 50, 3, 53, 54, 100, 105, 57);
$DEA['Nains']['other']['rr_cost'] = 50000;
$DEA['Gobelins']['players']['Bombardier']['def'] = array (93, 23, 105, 108);
$DEA['Gobelins']['players']['Tron�onneur']['def'] = array (95, 105, 108);
$DEA['Gobelins']['players']['B�ton � ressort']['def'] = array (23, 25, 108, 79);
$DEA['Gobelins']['players']['B�ton � ressort']['cost'] = 70000;
$DEA['Halflings']['players']['Homme Arbre']['def'] = array (54, 57, 58, 109, 59, 110);
$DEA['Khemri']['players']['Squelette']['cost'] = 40000;
$DEA['Khemri']['players']['Squelette']['def'] = array (103, 59);
$DEA['Khemri']['players']['Gardien des tombes'] = $DEA['Khemri']['players']['Momie'];
unset($DEA['Khemri']['players']['Momie']);
$DEA['Khemri']['players']['Gardien des tombes']['cost'] = 100000;
$DEA['Khemri']['players']['Gardien des tombes']['ma'] = 4;
$DEA['Khemri']['players']['Gardien des tombes']['def'] = array (96, 103);
$DEA['Necromantiques']['players']['Golem de chair']['cost'] = 100000;
$DEA['Skavens']['players']['Rat Ogre']['cost'] = 150000;
$DEA['Morts-vivants']['players']['Squelette']['cost'] = 40000;
$DEA['Morts-vivants']['players']['Squelette']['def'] = array (103, 59);
$DEA['Morts-vivants']['players']['Momie']['cost'] = 120000;
$DEA['Elfes sylvains']['players']['Receveur']['ma'] = 8;
$DEA['Elfes sylvains']['players']['Receveur']['def'] = array (20, 23, 28);

//
// One new skill in LRB5b - Animosity
// Only used in the three new teams
//

$skillarray['E'][113] = $skillididx[113] = 'Animosit�';

//
// Three new teams in LRB5b.
//

$DEA['Pacte chaotique'] = array (
    'other'	=> array (
        'rr_cost' => 70000,
        'icon' => 'chaos.png',
        'race_id' => 21, # (Chaos Pact)
    ),
    'players'	=> array (
  			'Maraudeur'	=> array (
    				'ma'        	=> 6,
    				'st'        	=> 3,
    				'ag'        	=> 3,
    				'av'        	=> 8,
    				'def'	    => array (),
    				'norm'		=> array ('G', 'F', 'P', 'M'),
    				'doub'		=> array ('A'),
    				'qty'			  => 12,
    				'cost'			  => 50000,
    				'icon'			  => 'nlineman1an',
    			    'pos_id'          => 210,
  			),
  			'Renegat Gobelin'	=> array (
    				'ma'        	=> 6,
    				'st'        	=> 2,
    				'ag'        	=> 3,
    				'av'        	=> 7,
    				'def'	    => array (113, 23, 104, 108),
    				'norm'		=> array ('A', 'M'),
    				'doub'		=> array ('G', 'F', 'P'),
    				'qty'			  => 1,
    				'cost'			  => 40000,
    				'icon'			  => 'goblin1an',
    			    'pos_id'          => 211,
  			),
  			'Renegat Skaven'	=> array (
    				'ma'        	=> 7,
    				'st'        	=> 3,
    				'ag'        	=> 3,
    				'av'        	=> 7,
    				'def'	    => array (113),
    				'norm'		=> array ('G', 'M'),
    				'doub'		=> array ('A', 'F', 'P'),
    				'qty'			  => 1,
    				'cost'			  => 50000,
    				'icon'			  => 'sklineman1an',
    			    'pos_id'          => 212,
  			),
  			'Renegat Elfe noir'	=> array (
    				'ma'        	=> 6,
    				'st'        	=> 3,
    				'ag'        	=> 4,
    				'av'        	=> 8,
    				'def'	    => array (113),
    				'norm'		=> array ('G', 'A', 'M'),
    				'doub'		=> array ('F', 'P'),
    				'qty'			  => 1,
    				'cost'			  => 70000,
    				'icon'			  => 'delineman1an',
    			    'pos_id'          => 213,
  			),
  			'Troll du Chaos'	=> array (
    				'ma'        	=> 4,
    				'st'        	=> 5,
    				'ag'        	=> 1,
    				'av'        	=> 9,
    				'def'	    => array (99, 90, 54, 102, 103, 110),
    				'norm'		=> array ('F'),
    				'doub'		=> array ('G', 'A', 'M', 'P'),
    				'qty'			  => 1,
    				'cost'			  => 110000,
    				'icon'			  => 'troll1an',
    			    'pos_id'          => 214,
  			),
  			'Ogre du Chaos'	=> array (
    				'ma'        	=> 5,
    				'st'        	=> 5,
    				'ag'        	=> 2,
    				'av'        	=> 9,
    				'def'	    => array (99, 94, 54, 59, 110),
    				'norm'		=> array ('F'),
    				'doub'		=> array ('G', 'A', 'M', 'P'),
    				'qty'			  => 1,
    				'cost'			  => 140000,
    				'icon'			  => 'ogre4an',
    			    'pos_id'          => 215,
  			),
  			'Minotaure'	=> array (
    				'ma'        	=> 5,
    				'st'        	=> 5,
    				'ag'        	=> 2,
    				'av'        	=> 8,
    				'def'	    => array (99, 5, 75, 54, 59, 112),
    				'norm'		=> array ('F'),
    				'doub'		=> array ('G', 'A', 'M', 'P'),
    				'qty'			  => 1,
    				'cost'			  => 150000,
    				'icon'			  => 'minotaur2an',
    			    'pos_id'          => 216,
  			)
    )
);

$DEA['Slanns'] = array (
    'other'	=> array (
        'rr_cost' => 50000,
        'icon' => 'slann.png',
        'race_id' => 22, # (Slann)
    ),
    'players'	=> array (
  			'Trois Quart'	=> array (
    				'ma'        	=> 6,
    				'st'        	=> 3,
    				'ag'        	=> 3,
    				'av'        	=> 8,
    				'def'	    => array (25, 79),
    				'norm'		=> array ('G'),
    				'doub'		=> array ('A', 'F', 'P'),
    				'qty'			  => 16,
    				'cost'			  => 60000,
    				'icon'			  => 'lmskink2an',
    			    'pos_id'          => 220,
  			),
  			'Receveur'	=> array (
    				'ma'        	=> 7,
    				'st'        	=> 2,
    				'ag'        	=> 4,
    				'av'        	=> 7,
    				'def'	    => array (21, 25, 79),
    				'norm'		=> array ('G', 'A'),
    				'doub'		=> array ('F', 'P'),
    				'qty'			  => 4,
    				'cost'			  => 80000,
    				'icon'			  => 'lmskink1an',
    			    'pos_id'          => 221,
  			),
  			'Blitzeur'	=> array (
    				'ma'        	=> 7,
    				'st'        	=> 3,
    				'ag'        	=> 3,
    				'av'        	=> 8,
    				'def'	    => array (22, 24, 25, 79),
    				'norm'		=> array ('G', 'A', 'F'),
    				'doub'		=> array ('P'),
    				'qty'			  => 4,
    				'cost'			  => 110000,
    				'icon'			  => 'lmskink1ban',
    			    'pos_id'          => 222,
  			),
  			'Kroxigor'	=> array (
    				'ma'        	=> 6,
    				'st'        	=> 5,
    				'ag'        	=> 1,
    				'av'        	=> 9,
    				'def'	    => array (99, 94, 54, 76, 59),
    				'norm'		=> array ('F'),
    				'doub'		=> array ('G', 'A', 'P'),
    				'qty'			  => 1,
    				'cost'			  => 140000,
    				'icon'			  => 'kroxigor1an',
    			    'pos_id'          => 223,
  			)
    )
);

$DEA['Bas fonds'] = array (
    'other'	=> array (
        'rr_cost' => 70000,
        'icon' => 'underworld.png',
        'race_id' => 23, # (Underworld)
    ),
    'players'	=> array (
  			'Gobelin des Bas fonds'	=> array (
    				'ma'        	=> 6,
    				'st'        	=> 2,
    				'ag'        	=> 3,
    				'av'        	=> 7,
    				'def'	    => array (104, 23, 108),
    				'norm'		=> array ('A', 'M'),
    				'doub'		=> array ('G', 'F', 'P'),
    				'qty'			  => 12,
    				'cost'			  => 40000,
    				'icon'			  => 'goblin1an',
    			    'pos_id'          => 230,
  			),
  			'Trois Quart Skaven'	=> array (
    				'ma'        	=> 7,
    				'st'        	=> 3,
    				'ag'        	=> 3,
    				'av'        	=> 7,
    				'def'	    => array (113),
    				'norm'		=> array ('G', 'M'),
    				'doub'		=> array ('A', 'F', 'P'),
    				'qty'			  => 2,
    				'cost'			  => 50000,
    				'icon'			  => 'sklineman1an',
    			    'pos_id'          => 231,
  			),
  			'Lanceur Skaven'	=> array (
    				'ma'        	=> 7,
    				'st'        	=> 3,
    				'ag'        	=> 3,
    				'av'        	=> 7,
    				'def'	    => array (113, 45, 12),
    				'norm'		=> array ('G', 'P', 'M'),
    				'doub'		=> array ('A', 'F'),
    				'qty'			  => 2,
    				'cost'			  => 70000,
    				'icon'			  => 'skthrower1an',
    			    'pos_id'          => 232,
  			),
  			'Blitzeur Skaven'	=> array (
    				'ma'        	=> 7,
    				'st'        	=> 3,
    				'ag'        	=> 3,
    				'av'        	=> 8,
    				'def'	    => array (113, 1),
    				'norm'		=> array ('G', 'F', 'M'),
    				'doub'		=> array ('A', 'P'),
    				'qty'			  => 2,
    				'cost'			  => 90000,
    				'icon'			  => 'skstorm1an',
    			    'pos_id'          => 233,
  			),
  			'Troll de Malepierre'	=> array (
    				'ma'        	=> 4,
    				'st'        	=> 5,
    				'ag'        	=> 1,
    				'av'        	=> 9,
    				'def'	    => array (99, 90, 54, 102, 103, 110),
    				'norm'		=> array ('F', 'M'),
    				'doub'		=> array ('G', 'A', 'P'),
    				'qty'			  => 1,
    				'cost'			  => 110000,
    				'icon'			  => 'troll1an',
    			    'pos_id'          => 234,
  			)
    )
);

// Create race ID index (key:val = id:race_name).
$raceididx = array();
foreach (array_keys($DEA) as $race) {
    $raceididx[$DEA[$race]['other']['race_id']] = $race;
}

//
// New star players in LRB5b.
//

$stars['Bertha Bigfist'] = array (
    'id'            => -47, 
    'ma'            => 6,
    'st'            => 5,
    'ag'            => 3,
    'av'            => 9,
    'def'           => array (99, 94, 50, 23, 54, 59, 110),
    'cost'          => 260000,
    'icon'          => 'star',
    'races'         => array(0, 7, 16)
);
$stars['Crazy Igor'] = array (
    'id'            => -48, 
    'ma'            => 6,
    'st'            => 3,
    'ag'            => 3,
    'av'            => 8,
    'def'           => array (99, 2, 103, 59),
    'cost'          => 130000,
    'icon'          => 'star',
    'races'         => array(18, 21)
);
$stars['Dolfar Longstride'] = array (
    'id'            => -49, 
    'ma'            => 7,
    'st'            => 3,
    'ag'            => 4,
    'av'            => 7,
    'def'           => array (99, 21, 42, 6, 7, 8),
    'cost'          => 170000,
    'icon'          => 'star',
    'races'         => array(5, 8, 20)
);

$stars['Fezglitch'] = array (
    'id'            => -50, 
    'ma'            => 4,
    'st'            => 7,
    'ag'            => 3,
    'av'            => 7,
    'def'           => array (99, 91, 72, 74, 100, 105),
    'cost'          => 80000,
    'icon'          => 'star',
    'races'         => array(19, 23)
);
$stars['Glart Smashrip Jr.'] = array (
    'id'            => -51, 
    'ma'            => 7,
    'st'            => 4,
    'ag'            => 3,
    'av'            => 8,
    'def'           => array (99, 1, 71, 53),
    'cost'          => 200000,
    'icon'          => 'star',
    'races'         => array(19, 23)
);
$stars['Humerus Carpal'] = array (
    'id'            => -52, 
    'ma'            => 7,
    'st'            => 2,
    'ag'            => 3,
    'av'            => 7,
    'def'           => array (99, 20, 23, 103, 44),
    'cost'          => 130000,
    'icon'          => 'star',
    'races'         => array(10)
);
$stars['Ithaca Benoin'] = array (
    'id'            => -53, 
    'ma'            => 7,
    'st'            => 3,
    'ag'            => 3,
    'av'            => 7,
    'def'           => array (99, 40, 41, 44, 45, 103, 12),
    'cost'          => 220000,
    'icon'          => 'star',
    'races'         => array(3, 10)
);
$stars['J Earlice'] = array (
    'id'            => -54, 
    'ma'            => 8,
    'st'            => 3,
    'ag'            => 3,
    'av'            => 7,
    'def'           => array (99, 20, 21, 23, 28),
    'cost'          => 180000,
    'icon'          => 'star',
    'races'         => array(13, 17, 18)
);
$stars['Lewdgrip Whiparm'] = array (
    'id'            => -55, 
    'ma'            => 6,
    'st'            => 3,
    'ag'            => 3,
    'av'            => 9,
    'def'           => array (99, 45, 58, 12, 77),
    'cost'          => 150000,
    'icon'          => 'star',
    'races'         => array(1, 15, 21)
);
$stars['Lottabottol'] = array (
    'id'            => -56, 
    'ma'            => 8,
    'st'            => 3,
    'ag'            => 3,
    'av'            => 8,
    'def'           => array (99, 20, 22, 24, 25, 8, 10, 79),
    'cost'          => 220000,
    'icon'          => 'star',
    'races'         => array(11, 22)
);
$stars['Quetzal Leap'] = array (
    'id'            => -57, 
    'ma'            => 8,
    'st'            => 2,
    'ag'            => 4,
    'av'            => 7,
    'def'           => array (99, 20, 21, 4, 7, 25, 44, 79),
    'cost'          => 250000,
    'icon'          => 'star',
    'races'         => array(11, 22)
);
$stars['Roxanna Darknail'] = array (
    'id'            => -58, 
    'ma'            => 8,
    'st'            => 3,
    'ag'            => 5,
    'av'            => 7,
    'def'           => array (99, 23, 5, 24, 53, 25),
    'cost'          => 250000,
    'icon'          => 'star',
    'races'         => array(0, 3)
);
$stars['Sinnedbad'] = array (
    'id'            => -59, 
    'ma'            => 6,
    'st'            => 3,
    'ag'            => 2,
    'av'            => 7,
    'def'           => array (99, 1, 24, 8, 103, 105, 26, 106),
    'cost'          => 80000,
    'icon'          => 'star',
    'races'         => array(10, 17)
);
$stars['Soaren Hightower'] = array (
    'id'            => -60, 
    'ma'            => 6,
    'st'            => 3,
    'ag'            => 4,
    'av'            => 8,
    'def'           => array (99, 4, 7, 45, 46, 12, 58),
    'cost'          => 180000,
    'icon'          => 'star',
    'races'         => array(8)
);
$stars['Willow Rosebark'] = array (
    'id'            => -61, 
    'ma'            => 5,
    'st'            => 4,
    'ag'            => 3,
    'av'            => 8,
    'def'           => array (99, 2, 26, 59),
    'cost'          => 150000,
    'icon'          => 'star',
    'races'         => array(0, 7, 20)
);



//
// Changes to LRB5 star players in LRB5b
//

$stars['Bomber Dribblesnot']['def']          = array (99, 40, 93, 23, 104, 105, 108);
$stars['Bomber Dribblesnot']['races']        = array (6, 16, 12, 21, 23);
$stars['Boomer Eziasson']['def']             = array (99, 40, 1, 93, 105, 59);
$stars['Count Luthor Von Drakenborg']['def'] = array (99, 1, 98, 103, 26);
$stars['Flint Churnblade']['def']            = array (99, 1, 95, 105, 59);
$stars['Flint Churnblade']['cost']           = 130000;
$stars['Grim Ironjaw']['def']                = array (99, 1, 2, 5, 55, 59);
$stars['Hack Enslash']['def']                = array (99, 95, 103, 105, 26);
$stars['Hack Enslash']['cost']               = 120000;
$stars['Helmut Wulf']['def']                 = array (99, 95, 105, 57);
$stars['Helmut Wulf']['cost']                = 110000;
$stars['Helmut Wulf']['races']               = array (0, 9, 11, 14, 22, 18);
$stars['Hemlock']['races']                   = array (11, 22);
$stars['Hthark the Unstoppable']['cost']     = 330000;
$stars['Icepelt Hammerblow']['def']          = array (99, 71, 72, 5, 103, 59);
$stars['Jordell Freshbreeze']['cost']        = 260000;
$stars['Lord Borak the Despoiler']['cost']   = 300000;
$stars['Max Spleenripper']['def']            = array (99, 95, 105);
$stars['Max Spleenripper']['cost']           = 130000;
$stars['Mighty Zug']['cost']                 = 260000;
$stars['Morg \'n\' Thorg']['cost']           = 450000;
$stars['Morg \'n\' Thorg']['races']          = array (0, 1, 2, 21, 3, 4, 5, 6, 7, 8, 9, 11, 12, 14, 15, 16, 18, 19, 22, 23, 20);
$stars['Nobbla Blackwart']['def']            = array (99, 1, 23, 95, 105, 108);
$stars['Nobbla Blackwart']['cost']           = 130000;
$stars['Nobbla Blackwart']['races']          = array (2, 6, 16, 23);
$stars['Ripper']['races']                    = array (6, 12);
$stars['Scrappa Sorehead']['def']            = array (99, 3, 23, 25, 104, 28, 108, 29, 79);
$stars['Scrappa Sorehead']['cost']           = 150000;
$stars['Skitter Stab-Stab']['races']         = array (19, 23);
$stars['Slibli']['races']                    = array (11, 22);
$stars['Ugroth Bolgrot']['def']              = array (99, 95, 105);
$stars['Ugroth Bolgrot']['cost']             = 100000;
$stars['Ugroth Bolgrot']['races']            = array (12, 21);
$stars['Varag Ghoul-Chewer']['cost']         = 290000;
$stars['Zara the Slayer']['races']           = array (0, 4, 7, 8, 9, 14, 20);
$stars['Zzharg Madeye']['races']             = array (2, 21);

ksort($stars, SORT_STRING);

?>
