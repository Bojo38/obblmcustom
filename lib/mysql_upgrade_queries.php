<?php

/*
    Load only this file on demand.
*/

global $db_prefix;

$upgradeSQLs = array(
    '075-080' => array(
        # Delete, now modulized, type from texts.
        'DELETE FROM '.$db_prefix.'texts WHERE type = 8',
        SQLUpgrade::runIfColumnExists('matches', 'hash_botocs', 'ALTER TABLE '.$db_prefix.'matches DROP hash_botocs'),
        # Add mg (miss game) indicator in player's match data.
        SQLUpgrade::runIfColumnNotExists('match_data', 'mg', 'ALTER TABLE '.$db_prefix.'match_data ADD COLUMN mg BOOLEAN NOT NULL DEFAULT FALSE'),
        'UPDATE '.$db_prefix.'match_data SET mg = IF(f_player_id < 0, FALSE, IF(getPlayerStatus(f_player_id, f_match_id) = 1, FALSE, TRUE))',
        # Before migrating to using skill IDs we must correct a few skill names.
        SQLUpgrade::runIfColumnExists('players', 'ach_nor_skills', 'UPDATE '.$db_prefix.'players 
            SET ach_nor_skills = REPLACE(ach_nor_skills, "Bone Head", "Bone-Head"),
                ach_dob_skills = REPLACE(ach_dob_skills, "Bone Head", "Bone-Head")'),
            # We do a double reverse replacement to prevent replacing "Claw/Claws" as "Claw/Claw/Claws"
        SQLUpgrade::runIfColumnExists('players', 'ach_nor_skills', 'UPDATE '.$db_prefix.'players 
            SET ach_nor_skills = REPLACE(REPLACE(ach_nor_skills, "Claw/Claws", "Claws"), "Claws", "Claw/Claws"),
                ach_dob_skills = REPLACE(REPLACE(ach_dob_skills, "Claw/Claws", "Claws"), "Claws", "Claw/Claws")'),
                
        # Column alterations
        SQLUpgrade::runIfColumnExists('teams', 'elo_0', 'ALTER TABLE '.$db_prefix.'teams DROP elo_0'),
        SQLUpgrade::runIfColumnNOTExists('teams', 'played_0', 'ALTER TABLE '.$db_prefix.'teams ADD COLUMN played_0 SMALLINT UNSIGNED NOT NULL DEFAULT 0'),
        'UPDATE '.$db_prefix.'teams SET played_0 = won_0 + draw_0 + lost_0',
        'ALTER TABLE '.$db_prefix.'players MODIFY player_id MEDIUMINT SIGNED NOT NULL AUTO_INCREMENT',
            # New FF col system.
        SQLUpgrade::runIfColumnNOTExists('teams', 'ff_bought', 'ALTER TABLE '.$db_prefix.'teams ADD COLUMN ff_bought TINYINT UNSIGNED AFTER rerolls'),
        SQLUpgrade::runIfColumnNOTExists('teams', 'ff', 'ALTER TABLE '.$db_prefix.'teams ADD COLUMN ff TINYINT UNSIGNED'),
        SQLUpgrade::runIfColumnExists('teams', 'fan_factor', 'UPDATE '.$db_prefix.'teams SET ff_bought = fan_factor'),
        SQLUpgrade::runIfColumnExists('teams', 'fan_factor', 'ALTER TABLE '.$db_prefix.'teams DROP fan_factor'),
        
        // Add DPROPS fields
        SQLUpgrade::runIfColumnNotExists('teams', 'swon', 'ALTER TABLE '.$db_prefix.'teams ADD COLUMN swon SMALLINT UNSIGNED DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('teams', 'sdraw', 'ALTER TABLE '.$db_prefix.'teams ADD COLUMN sdraw SMALLINT UNSIGNED DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('teams', 'slost', 'ALTER TABLE '.$db_prefix.'teams ADD COLUMN slost SMALLINT UNSIGNED DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('teams', 'win_pct', 'ALTER TABLE '.$db_prefix.'teams ADD COLUMN win_pct FLOAT UNSIGNED DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('teams', 'wt_cnt', 'ALTER TABLE '.$db_prefix.'teams ADD COLUMN wt_cnt SMALLINT UNSIGNED DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('teams', 'elo', 'ALTER TABLE '.$db_prefix.'teams ADD COLUMN elo FLOAT DEFAULT NULL'),
        SQLUpgrade::runIfColumnNotExists('teams', 'tv', 'ALTER TABLE '.$db_prefix.'teams ADD COLUMN tv MEDIUMINT UNSIGNED'),
        SQLUpgrade::runIfColumnNotExists('coaches', 'swon', 'ALTER TABLE '.$db_prefix.'coaches ADD COLUMN swon SMALLINT UNSIGNED DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('coaches', 'sdraw', 'ALTER TABLE '.$db_prefix.'coaches ADD COLUMN sdraw SMALLINT UNSIGNED DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('coaches', 'slost', 'ALTER TABLE '.$db_prefix.'coaches ADD COLUMN slost SMALLINT UNSIGNED DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('coaches', 'win_pct', 'ALTER TABLE '.$db_prefix.'coaches ADD COLUMN win_pct FLOAT UNSIGNED DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('coaches', 'wt_cnt', 'ALTER TABLE '.$db_prefix.'coaches ADD COLUMN wt_cnt SMALLINT UNSIGNED DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('coaches', 'elo', 'ALTER TABLE '.$db_prefix.'coaches ADD COLUMN elo FLOAT DEFAULT NULL'),
        SQLUpgrade::runIfColumnNotExists('coaches', 'team_cnt', 'ALTER TABLE '.$db_prefix.'coaches ADD COLUMN team_cnt TINYINT UNSIGNED DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('players', 'value', 'ALTER TABLE '.$db_prefix.'players ADD COLUMN value MEDIUMINT SIGNED'),
        SQLUpgrade::runIfColumnNotExists('players', 'status', 'ALTER TABLE '.$db_prefix.'players ADD COLUMN status TINYINT UNSIGNED'),
        SQLUpgrade::runIfColumnNotExists('players', 'date_died', 'ALTER TABLE '.$db_prefix.'players ADD COLUMN date_died DATETIME'),
        SQLUpgrade::runIfColumnNotExists('players', 'ma', 'ALTER TABLE '.$db_prefix.'players ADD COLUMN ('.implode(', ', array_map(create_function('$f','return "$f TINYINT UNSIGNED DEFAULT 0";'), array('ma','st','ag','av','inj_ma','inj_st','inj_ag','inj_av','inj_ni'))).')'),
        SQLUpgrade::runIfColumnNotExists('players', 'win_pct', 'ALTER TABLE '.$db_prefix.'players ADD COLUMN win_pct FLOAT UNSIGNED DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('tours', 'empty', 'ALTER TABLE '.$db_prefix.'tours ADD COLUMN empty BOOLEAN DEFAULT TRUE'),
        SQLUpgrade::runIfColumnNotExists('tours', 'begun', 'ALTER TABLE '.$db_prefix.'tours ADD COLUMN begun BOOLEAN DEFAULT FALSE'),
        SQLUpgrade::runIfColumnNotExists('tours', 'finished', 'ALTER TABLE '.$db_prefix.'tours ADD COLUMN finished BOOLEAN DEFAULT FALSE'),
        SQLUpgrade::runIfColumnNotExists('tours', 'winner', 'ALTER TABLE '.$db_prefix.'tours ADD COLUMN winner MEDIUMINT UNSIGNED'),

        // Add relation fields
        SQLUpgrade::runIfColumnNotExists('players', 'f_cid', 'ALTER TABLE '.$db_prefix.'players ADD COLUMN f_cid MEDIUMINT UNSIGNED'),
        SQLUpgrade::runIfColumnNotExists('players', 'f_rid', 'ALTER TABLE '.$db_prefix.'players ADD COLUMN f_rid TINYINT UNSIGNED'),
        SQLUpgrade::runIfColumnNotExists('players', 'f_pos_id', 'ALTER TABLE '.$db_prefix.'players ADD COLUMN f_pos_id SMALLINT UNSIGNED'),
        SQLUpgrade::runIfColumnNotExists('players', 'f_tname', 'ALTER TABLE '.$db_prefix.'players ADD COLUMN f_tname VARCHAR(60)'),
        SQLUpgrade::runIfColumnNotExists('players', 'f_rname', 'ALTER TABLE '.$db_prefix.'players ADD COLUMN f_rname VARCHAR(60)'),
        SQLUpgrade::runIfColumnNotExists('players', 'f_cname', 'ALTER TABLE '.$db_prefix.'players ADD COLUMN f_cname VARCHAR(60)'),
        SQLUpgrade::runIfColumnNotExists('teams', 'f_rname', 'ALTER TABLE '.$db_prefix.'teams ADD COLUMN f_rname VARCHAR(60)'),
        SQLUpgrade::runIfColumnNotExists('teams', 'f_cname', 'ALTER TABLE '.$db_prefix.'teams ADD COLUMN f_cname VARCHAR(60)'),

        # Migrate to using player position IDs 
        SQLUpgrade::runIfColumnExists('players', 'position', 'UPDATE '.$db_prefix.'players,'.$db_prefix.'teams,'.$db_prefix.'game_data_players SET f_pos_id = pos_id WHERE owned_by_team_id = team_id AND '.$db_prefix.'teams.f_race_id = '.$db_prefix.'game_data_players.f_race_id AND position = pos'),
        SQLUpgrade::runIfColumnExists('players', 'position', 'ALTER TABLE '.$db_prefix.'players CHANGE COLUMN position f_pos_name VARCHAR(60)'),
        
        // Add improvement rolls.
#        SQLUpgrade::runIfColumnExists('match_data', 'ir_d1', 'ALTER TABLE '.$db_prefix.'match_data CHANGE ir_d1 ir1_d1 TINYINT UNSIGNED NOT NULL DEFAULT 0'),
#        SQLUpgrade::runIfColumnExists('match_data', 'ir_d2', 'ALTER TABLE '.$db_prefix.'match_data CHANGE ir_d2 ir1_d2 TINYINT UNSIGNED NOT NULL DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('match_data', 'ir1_d1', 'ALTER TABLE '.$db_prefix.'match_data ADD COLUMN ir1_d1 TINYINT UNSIGNED NOT NULL DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('match_data', 'ir1_d2', 'ALTER TABLE '.$db_prefix.'match_data ADD COLUMN ir1_d2 TINYINT UNSIGNED NOT NULL DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('match_data', 'ir2_d1', 'ALTER TABLE '.$db_prefix.'match_data ADD COLUMN ir2_d1 TINYINT UNSIGNED NOT NULL DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('match_data', 'ir2_d2', 'ALTER TABLE '.$db_prefix.'match_data ADD COLUMN ir2_d2 TINYINT UNSIGNED NOT NULL DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('match_data', 'ir3_d1', 'ALTER TABLE '.$db_prefix.'match_data ADD COLUMN ir3_d1 TINYINT UNSIGNED NOT NULL DEFAULT 0'),
        SQLUpgrade::runIfColumnNotExists('match_data', 'ir3_d2', 'ALTER TABLE '.$db_prefix.'match_data ADD COLUMN ir3_d2 TINYINT UNSIGNED NOT NULL DEFAULT 0'),
        
        'DELETE FROM '.$db_prefix.'texts WHERE type = 11', # Match summary comments are deprecated.
        SQLUpgrade::runIfColumnExists('teams', 'sw_0', 'ALTER TABLE '.$db_prefix.'teams DROP sw_0'),
        SQLUpgrade::runIfColumnExists('teams', 'sd_0', 'ALTER TABLE '.$db_prefix.'teams DROP sd_0'),
        SQLUpgrade::runIfColumnExists('teams', 'sl_0', 'ALTER TABLE '.$db_prefix.'teams DROP sl_0'),
        
        # New security system.
        SQLUpgrade::runIfColumnNotExists('leagues', 'tie_teams',  'ALTER TABLE '.$db_prefix.'leagues ADD COLUMN tie_teams BOOLEAN NOT NULL DEFAULT TRUE'),
        SQLUpgrade::runIfColumnNotExists('teams', 'f_did',  'ALTER TABLE '.$db_prefix.'teams ADD COLUMN f_did MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 AFTER f_race_id'),
        SQLUpgrade::runIfColumnNotExists('teams', 'f_lid',  'ALTER TABLE '.$db_prefix.'teams ADD COLUMN f_lid MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 AFTER f_did'),
        SQLUpgrade::runIfTrue('SELECT COUNT(*) = 0 FROM '.$db_prefix.'teams WHERE f_lid != 0', 'UPDATE '.$db_prefix.'teams SET f_lid = IFNULL((SELECT d.f_lid FROM '.$db_prefix.'matches AS m ,'.$db_prefix.'tours AS t,'.$db_prefix.'divisions AS d WHERE m.f_tour_id = t.tour_id AND t.f_did = d.did AND (team1_id = team_id OR team2_id = team_id) ORDER BY m.date_played ASC LIMIT 1), 0)'), # Teams are tied to the league in which they played their first match.
        SQLUpgrade::runIfTrue('SELECT (SELECT COUNT(*) = 1 FROM '.$db_prefix.'leagues) AND (SELECT COUNT(*) > 0 FROM '.$db_prefix.'teams WHERE f_lid = 0)', 'UPDATE '.$db_prefix.'teams SET f_lid = (SELECT lid FROM '.$db_prefix.'leagues LIMIT 1) WHERE f_lid = 0'), # If ONLY one leagues exists set the remaining untied team->league ties to this league.
        'CREATE TABLE IF NOT EXISTS '.$db_prefix.'memberships (
            cid   MEDIUMINT UNSIGNED NOT NULL,
            lid   MEDIUMINT UNSIGNED NOT NULL,
            ring  TINYINT UNSIGNED NOT NULL DEFAULT 0
        )',
        SQLUpgrade::runIfTrue('SELECT COUNT(*) = 0 FROM '.$db_prefix.'memberships', 'INSERT INTO '.$db_prefix.'memberships (cid,lid,ring) SELECT DISTINCT owned_by_coach_id, f_lid, 2 FROM '.$db_prefix.'teams WHERE f_lid != 0'), # Coaches should be regular coach members of the leagues in which their teams are tied.
        SQLUpgrade::runIfTrue('SELECT COUNT(*) = 0 FROM '.$db_prefix.'coaches WHERE ring = 5', 'UPDATE '.$db_prefix.'coaches SET ring = IF(ring = 0, 5, 0)'), # New rings system.
        
        SQLUpgrade::runIfColumnNotExists('texts', 'f_id2',  'ALTER TABLE '.$db_prefix.'texts ADD COLUMN f_id2 MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 AFTER f_id'),
        SQLUpgrade::runIfColumnExists('teams', 'tcas_0',  'ALTER TABLE '.$db_prefix.'teams DROP tcas_0'),
        SQLUpgrade::runIfColumnNotExists('coaches', 'activation_code',  'ALTER TABLE '.$db_prefix.'coaches ADD COLUMN activation_code VARCHAR(32) DEFAULT NULL AFTER retired'),
    ),
);

/*
    Upgrade functions
*/

$upgradeFuncs = array(
    '075-080' => array('upgrade_075_080_pskills_migrate'),
);

function upgrade_075_080_pskills_migrate()
{
    # Note: Requires the players_skills table AND all skills name corrections made in DB so they fit $skillsididx.
    global $skillididx,$db_prefix;
    global $skillididx_rvs; # Make global for use below (dirty trick).
    $skillididx_rvs = array_flip($skillididx);

    # Already run this version upgrade (note: this routine specifically does not remove the skills column(s))?
    if (!SQLUpgrade::doesColExist('players', 'ach_nor_skills')) {
        return true;
    }
    $status = true;
    $status &= (mysql_query("
        CREATE TABLE IF NOT EXISTS ".$db_prefix."players_skills (
            f_pid      MEDIUMINT SIGNED NOT NULL,
            f_skill_id SMALLINT UNSIGNED NOT NULL,
            type       VARCHAR(1)
        )
    ") or die(mysql_error()));
    $players = get_rows(
        'players', 
        array('player_id', 'ach_nor_skills', 'ach_dob_skills', 'extra_skills'), 
        array('ach_nor_skills != "" OR ach_dob_skills != "" OR extra_skills != ""')
    );
        foreach ($players as $p) {
        foreach (array('N' => 'ach_nor_skills', 'D' => 'ach_dob_skills', 'E' => 'extra_skills') as $t => $grp) {
            $values = empty($p->$grp) ? array() : array_map(create_function('$s', 'global $skillididx_rvs; return "('.$p->player_id.',\''.$t.'\',".$skillididx_rvs[$s].")";'), explode(',', $p->$grp));

            if (!empty($values)) {
                $status &= (mysql_query("INSERT INTO ".$db_prefix."players_skills(f_pid, type, f_skill_id) VALUES ".implode(',', $values)) or die(mysql_error()));
            }
        }
    }
    $sqls_drop = array(
        SQLUpgrade::runIfColumnExists('players', 'ach_nor_skills', 'ALTER TABLE '.$db_prefix.'players DROP ach_nor_skills'),
        SQLUpgrade::runIfColumnExists('players', 'ach_dob_skills', 'ALTER TABLE '.$db_prefix.'players DROP ach_dob_skills'),
        SQLUpgrade::runIfColumnExists('players', 'extra_skills', 'ALTER TABLE '.$db_prefix.'players DROP extra_skills'),
    );
    foreach ($sqls_drop as $query) {
        $status &= (mysql_query($query) or die(mysql_error()));
    }

    return $status;
}

/*
    Upgrade messages
*/
global $db_prefix;
$upgradeMsgs = array(
'075-080' => array(

'Teams are now required to be tied to leagues. Upgrading automatically ties teams to the league in which they played their first match.
Teams which have not yet played any games are therefore not tied to any leagues and you must manually run some SQL code to tie them to a given league, 
for example running "UPDATE '.$db_prefix.'teams SET f_lid = 5 WHERE f_lid = 0", will tie the remaining teams to the league with ID = 5 (you would generally want to do something like that). 
If you don\'t do this the non-tied teams may not be scheduled to play in any matches!',

),

);

?>
