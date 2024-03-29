<?php 

/*
    Load only this file on demand.
*/

define('T_ELO_K',   20);
define('T_ELO_D',   400);
define('T_ELO_R_0', 200);
define('T_ELO_PTS_WON',  1);
define('T_ELO_PTS_LOST', 0);
define('T_ELO_PTS_DRAW', 0.5);

class SQLCore
{

/*
    Synchronizes PHP stored BB game date with DB game data. 
    These MUST be in sync thus this routine MUST be run whenever the PHP-stored game data is modified.
*/
public static function syncGameData() 
{
    global $core_tables, $DEA, $stars, $skillarray,$db_prefix;
    
    $players   = 'game_data_players';
    $races     = 'races';
    $starstbl  = 'game_data_stars';
    $skillstbl = 'game_data_skills';
    
    $status = true;
    // Drop and re-create game data tables.
    $status &= Table::createTable($players,  $core_tables[$players]);
    $status &= Table::createTable($races,    $core_tables[$races]);
    $status &= Table::createTable($starstbl, $core_tables[$starstbl]);
    $status &= Table::createTable($skillstbl,$core_tables[$skillstbl]);

    foreach ($DEA as $race_name => $race_details) {
        $query = "INSERT INTO ".$db_prefix."$races(race_id, name, cost_rr) VALUES (".$race_details['other']['race_id'].", '".mysql_real_escape_string($race_name)."', ".$race_details['other']['rr_cost'].")";
        $status &= mysql_query($query);
        foreach ($race_details['players'] as $player_name => $PD) { # Player Details
            $query = "INSERT INTO ".$db_prefix."$players(
                    pos_id, f_race_id, pos, cost, qty, ma,st,ag,av, skills,norm,doub
                ) VALUES (
                    $PD[pos_id], ".$race_details['other']['race_id'].", '".mysql_real_escape_string($player_name)."', $PD[cost], $PD[qty], $PD[ma],$PD[st],$PD[ag],$PD[av],
                    '".implode(',',$PD['def'])."', '".implode('',$PD['norm'])."', '".implode('',$PD['doub'])."'
                )";
            $status &= mysql_query($query);
        }
    }

    foreach ($stars as $star_name => $SD) {
        $query = "INSERT INTO ".$db_prefix."$starstbl(star_id, name, cost, races, ma,st,ag,av, skills) VALUES (
            $SD[id], '".mysql_real_escape_string($star_name)."', $SD[cost], '".implode(',', $SD['races'])."', $SD[ma],$SD[st],$SD[ag],$SD[av], '".implode(',', $SD['def'])."'
        )";
        $status = mysql_query($query);
    }


    foreach ($skillarray as $grp => $skills) {
        foreach ($skills as $id => $s) {
            $status &= mysql_query("INSERT INTO ".$db_prefix."$skillstbl(skill_id, name, cat) VALUES ($id, '".mysql_real_escape_string($s)."', '$grp')");
        }
    }
    
    return $status;
}

public static function mkHRS(array $HRSs)
{
    global $CT_cols, $core_tables,$db_prefix;
    
    $allowed_fields = array(
        'mvp', 'cp', 'td', 'intcpt', 'bh', 'si', 'ki', 'cas', 'tdcas', 'smp', 'elo', 
        'gf', 'ga', 'sdiff', 'tcasf', 'tcasa', 'tcdiff', 'won', 'lost', 'draw', 'swon', 'slost', 'sdraw', 'played', 'win_pct',
    );
    $query = 'CREATE FUNCTION getPTS(tid '.$CT_cols[T_OBJ_TEAM].', trid '.$CT_cols[T_NODE_TOURNAMENT].')
        RETURNS '.$CT_cols['pts'].'
        NOT DETERMINISTIC
        READS SQL DATA

    BEGIN
        DECLARE rs TINYINT UNSIGNED DEFAULT NULL;
        SELECT '.$db_prefix.'tours.rs INTO rs FROM '.$db_prefix.'tours WHERE tour_id = trid;
        IF rs IS NULL THEN
            RETURN NULL;
        END IF;
        
        CASE rs
    ';
    foreach ($HRSs as $nr => $rs) {
        if (empty($rs['points'])) {
            continue;
        }
        $pts = preg_replace('/\[(\w*)\]/', "IFNULL(SUM(\\1),0)", $rs['points']);
        $query .= "WHEN $nr THEN RETURN (SELECT $pts FROM ".$db_prefix."mv_teams WHERE f_tid = tid AND f_trid = trid);\n";
    }
    $query .= '
            ELSE RETURN 0;
        END CASE;
        
        RETURN NULL;
    END';
    
    return $query;
}

public static function installProcsAndFuncs($install = true)
{
    global $CT_cols, $core_tables, $ES_fields, $rules,$db_prefix;

    /* 
     *  Re-useable code-chunks for routines.
     */
    
    // MV syncs 
    $common_fields_keys = 'td,cp,intcpt,bh,si,ki,mvp,cas,tdcas,spp';
    $common_fields = 'IFNULL(SUM(td),0), IFNULL(SUM(cp),0), IFNULL(SUM(intcpt),0), IFNULL(SUM(bh),0), IFNULL(SUM(si),0), IFNULL(SUM(ki),0), IFNULL(SUM(mvp),0), IFNULL(SUM(bh+si+ki),0), IFNULL(SUM(bh+si+ki+td),0), IFNULL(SUM(cp*1+(bh+si+ki)*2+intcpt*2+td*3+mvp*5),0)';
    $mstat_fields_suffix__common = ''.$db_prefix.'matches.f_tour_id = trid AND '.$db_prefix.'matches.date_played IS NOT NULL';
    $mstat_fields_suffix_player = "FROM ".$db_prefix."matches,".$db_prefix."match_data WHERE $mstat_fields_suffix__common AND ".$db_prefix."matches.match_id = ".$db_prefix."match_data.f_match_id AND ".$db_prefix."match_data.f_player_id = pid AND ".$db_prefix."match_data.mg IS FALSE";
    $mstat_fields_suffix_team   = "FROM ".$db_prefix."matches WHERE $mstat_fields_suffix__common AND (team1_id = tid OR team2_id = tid)";
    $mstat_fields_suffix_coach  = "FROM ".$db_prefix."matches,".$db_prefix."teams WHERE $mstat_fields_suffix__common AND (team1_id = tid OR team2_id = tid) AND ".$db_prefix."teams.owned_by_coach_id = cid";
    $mstat_fields_suffix_race   = "FROM ".$db_prefix."matches,".$db_prefix."teams WHERE $mstat_fields_suffix__common AND (team1_id = tid OR team2_id = tid) AND ".$db_prefix."teams.f_race_id = rid";
    $mstat_fields = '
        SET played = IFNULL((SELECT SUM(IF(team1_id = tid OR team2_id = tid, 1, 0)) REGEX_REPLACE_HERE), 0), 
            won    = IFNULL((SELECT SUM(IF((team1_id = tid AND team1_score > team2_score) OR (team2_id = tid AND team2_score > team1_score), 1, 0)) REGEX_REPLACE_HERE), 0), 
            lost   = IFNULL((SELECT SUM(IF((team1_id = tid AND team1_score < team2_score) OR (team2_id = tid AND team2_score < team1_score), 1, 0)) REGEX_REPLACE_HERE), 0), 
            draw   = IFNULL((SELECT SUM(IF((team1_id = tid OR team2_id = tid) AND team1_score = team2_score, 1, 0)) REGEX_REPLACE_HERE), 0), 
            gf     = IFNULL((SELECT SUM(IF(team1_id = tid, team1_score, IF(team2_id = tid, team2_score, 0))) REGEX_REPLACE_HERE), 0), 
            ga     = IFNULL((SELECT SUM(IF(team1_id = tid, team2_score, IF(team2_id = tid, team1_score, 0))) REGEX_REPLACE_HERE), 0),  
            tcasf  = IFNULL((SELECT SUM(IF(team1_id = tid, tcas1, IF(team2_id = tid, tcas2, 0))) REGEX_REPLACE_HERE), 0), 
            tcasa  = IFNULL((SELECT SUM(IF(team1_id = tid, tcas2, IF(team2_id = tid, tcas1, 0))) REGEX_REPLACE_HERE), 0),  
            smp    = IFNULL((SELECT SUM(IF(team1_id = tid, smp1, IF(team2_id = tid, smp2, 0))) REGEX_REPLACE_HERE), 0),
            ff     = IFNULL((SELECT SUM(IF(team1_id = tid, ffactor1, IF(team2_id = tid, ffactor2, 0))) REGEX_REPLACE_HERE), 0)
    ';
    $mstat_fields_player = preg_replace('/REGEX_REPLACE_HERE/', $mstat_fields_suffix_player, $mstat_fields);
    $mstat_fields_team   = preg_replace('/REGEX_REPLACE_HERE/', $mstat_fields_suffix_team,   $mstat_fields);
    $mstat_fields_coach  = preg_replace('/REGEX_REPLACE_HERE/', $mstat_fields_suffix_coach,  $mstat_fields);
    $mstat_fields_race   = preg_replace('/REGEX_REPLACE_HERE/', $mstat_fields_suffix_race,   $mstat_fields);
    $mstat_fields_coach = preg_replace('/tid/', $db_prefix.'teams.team_id', $mstat_fields_coach);
    $mstat_fields_race  = preg_replace('/tid/', $db_prefix.'teams.team_id', $mstat_fields_race);
    $mstat_fields_stars = preg_replace('/tid/', $db_prefix.'match_data.f_team_id', $mstat_fields_player);
        # ES
    $common_es_fields_keys = implode(',', array_keys($ES_fields));
    $common_es_fields = implode(',', array_map(create_function('$k', 'return "IFNULL(SUM($k),0)";'), array_keys($ES_fields)));
    
    // ELO
    $elo_matchsync_R0 = '
        SELECT IF(IFNULL((SELECT SUM(played) FROM '.$db_prefix.'mv_teams   WHERE f_tid = tid1 REGEX_REPLACE_HERE),FALSE) AND IFNULL('.$db_prefix.'teams.elo,FALSE),   '.$db_prefix.'teams.elo, '.T_ELO_R_0.')   INTO Rt1_0 FROM '.$db_prefix.'teams   WHERE team_id = tid1;
        SELECT IF(IFNULL((SELECT SUM(played) FROM '.$db_prefix.'mv_teams   WHERE f_tid = tid2 REGEX_REPLACE_HERE),FALSE) AND IFNULL('.$db_prefix.'teams.elo,FALSE),   '.$db_prefix.'teams.elo, '.T_ELO_R_0.')   INTO Rt2_0 FROM '.$db_prefix.'teams   WHERE team_id = tid2;
        SELECT IF(IFNULL((SELECT SUM(played) FROM '.$db_prefix.'mv_coaches WHERE f_cid = cid1 REGEX_REPLACE_HERE),FALSE) AND IFNULL('.$db_prefix.'coaches.elo,FALSE), '.$db_prefix.'coaches.elo, '.T_ELO_R_0.') INTO Rc1_0 FROM '.$db_prefix.'coaches WHERE coach_id = cid1;
        SELECT IF(IFNULL((SELECT SUM(played) FROM '.$db_prefix.'mv_coaches WHERE f_cid = cid2 REGEX_REPLACE_HERE),FALSE) AND IFNULL('.$db_prefix.'coaches.elo,FALSE), '.$db_prefix.'coaches.elo, '.T_ELO_R_0.') INTO Rc2_0 FROM '.$db_prefix.'coaches WHERE coach_id = cid2;
    ';    
    $elo_matchsync_R0_alltime = preg_replace('/REGEX_REPLACE_HERE/', '', $elo_matchsync_R0);
    $elo_matchsync_R0_tour    = preg_replace('/REGEX_REPLACE_HERE/', 'AND f_trid = trid', $elo_matchsync_R0);
    
    // Streak pseudo-table components
    $streaks_TBL1_team = '
        SELECT 
            date_played, 
            IF((team1_id = obj_id AND team1_score > team2_score) OR (team2_id = obj_id AND team1_score < team2_score), "W", IF(team1_score = team2_score, "D", "L")) AS "result"
        FROM '.$db_prefix.'matches WHERE date_played IS NOT NULL AND (team1_id = obj_id OR team2_id = obj_id) AND IF(trid IS NULL, TRUE, f_tour_id = trid) ORDER BY date_played ASC
    ';
    $streaks_TBL1_coach = '
        SELECT 
            date_played, 
            IF((team1_id = '.$db_prefix.'teams.team_id AND team1_score > team2_score) OR (team2_id = '.$db_prefix.'teams.team_id AND team1_score < team2_score), "W", IF(team1_score = team2_score, "D", "L")) AS "result"
        FROM '.$db_prefix.'matches, '.$db_prefix.'teams WHERE date_played IS NOT NULL AND IF(trid IS NULL, TRUE, f_tour_id = trid) AND owned_by_coach_id = obj_id AND (team1_id = '.$db_prefix.'teams.team_id OR team2_id = '.$db_prefix.'teams.team_id) ORDER BY date_played ASC
    ';
    $streaks_TBL2 = '
        SELECT 
            *,
            (
                SELECT COUNT(*) 
                FROM (REGEX_REPLACE_TBL1) AS G
                WHERE G.result <> TBL1.result 
                AND G.date_played <= TBL1.date_played
            ) AS RunGroup 
        FROM (REGEX_REPLACE_TBL1) AS TBL1
    ';
    $streaks_TBL3 = '
        SELECT 
            result, 
            MIN(date_played) as StartDate, 
            MAX(date_played) as EndDate, 
            COUNT(*) as games
        FROM (REGEX_REPLACE_TBL2) AS TBL2
        GROUP BY result, RunGroup
        ORDER BY date_played
    ';
    $streaks_final = '
        SET swon  = (SELECT IFNULL(MAX(games),0) FROM (REGEX_REPLACE_TBL3) AS TBL3 WHERE result = "W");
        SET slost = (SELECT IFNULL(MAX(games),0) FROM (REGEX_REPLACE_TBL3) AS TBL3 WHERE result = "L");
        SET sdraw = (SELECT IFNULL(MAX(games),0) FROM (REGEX_REPLACE_TBL3) AS TBL3 WHERE result = "D");
    ';
    $streaks_team = preg_replace('/REGEX_REPLACE_TBL3/', $streaks_TBL3, $streaks_final);
    $streaks_team = preg_replace('/REGEX_REPLACE_TBL2/', $streaks_TBL2, $streaks_team);
    $streaks_team = preg_replace('/REGEX_REPLACE_TBL1/', $streaks_TBL1_team, $streaks_team);
    $streaks_coach = preg_replace('/REGEX_REPLACE_TBL3/', $streaks_TBL3, $streaks_final);
    $streaks_coach = preg_replace('/REGEX_REPLACE_TBL2/', $streaks_TBL2, $streaks_coach);
    $streaks_coach = preg_replace('/REGEX_REPLACE_TBL1/', $streaks_TBL1_coach, $streaks_coach);
    
    // Post match sync.
    $matches_setup_rels = '
        /* GENERAL */
        DECLARE ret BOOLEAN;
        DECLARE rid1, rid2 '.$CT_cols[T_OBJ_RACE].';
        DECLARE cid1, cid2 '.$CT_cols[T_OBJ_COACH].';

        /* Tour DPROPS */
        DECLARE empty,begun,finished BOOLEAN;
        DECLARE winner '.$CT_cols[T_OBJ_TEAM].';
        
        /* Team DPROPS */
        DECLARE TV '.$CT_cols['tv'].';
        DECLARE FF TINYINT UNSIGNED;
        
        /* Streaks */
        DECLARE swon,sdraw,slost '.$CT_cols['streak'].';
        
        /* Player DPROPS */
        DECLARE done INT DEFAULT 0;
        DECLARE inj_ma,inj_av,inj_ag,inj_st,inj_ni, ma,av,ag,st '.$CT_cols['chr'].';
        DECLARE value '.$CT_cols['pv'].';
        DECLARE status '.$core_tables['players']['status'].';
        DECLARE date_died '.$core_tables['players']['date_died'].'; 
        DECLARE pid '.$CT_cols[T_OBJ_PLAYER].';
        DECLARE cur_p CURSOR FOR SELECT player_id FROM '.$db_prefix.'players WHERE owned_by_team_id = tid1 OR owned_by_team_id = tid2;
        DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
        
        SELECT t1.f_race_id, t2.f_race_id, t1.owned_by_coach_id, t2.owned_by_coach_id, t1.team_id, t2.team_id
        INTO rid1, rid2, cid1, cid2, tid1, tid2
        FROM '.$db_prefix.'teams AS t1, '.$db_prefix.'teams AS t2 WHERE t1.team_id = tid1 AND t2.team_id = tid2;
    ';
        # Needs $matches_setup_rels.
    $matches_tourDProps = '
        CALL getTourDProps(trid, empty, begun, finished, winner);
        UPDATE '.$db_prefix.'tours SET '.$db_prefix.'tours.empty = empty, '.$db_prefix.'tours.begun = begun, '.$db_prefix.'tours.finished = finished, '.$db_prefix.'tours.winner = winner WHERE tour_id = trid;
    ';
        # Needs $matches_setup_rels.
    $matches_teamDProps = '
        CALL getTeamDProps(tid1, TV, FF);
        UPDATE '.$db_prefix.'teams SET tv = TV, ff = FF WHERE team_id = tid1;
        CALL getTeamDProps(tid2, TV, FF);
        UPDATE '.$db_prefix.'teams SET tv = TV, ff = FF WHERE team_id = tid2;
    ';
        # Needs $matches_setup_rels.
    $matches_team_cnt = '
        UPDATE '.$db_prefix.'mv_races   SET team_cnt = getTeamCnt('.T_OBJ_RACE.', rid1, trid) WHERE f_trid = trid AND f_rid = rid1;
        UPDATE '.$db_prefix.'mv_races   SET team_cnt = getTeamCnt('.T_OBJ_RACE.', rid2, trid) WHERE f_trid = trid AND f_rid = rid2;
        UPDATE '.$db_prefix.'mv_coaches SET team_cnt = getTeamCnt('.T_OBJ_COACH.', cid1, trid) WHERE f_trid = trid AND f_cid = cid1;
        UPDATE '.$db_prefix.'mv_coaches SET team_cnt = getTeamCnt('.T_OBJ_COACH.', cid2, trid) WHERE f_trid = trid AND f_cid = cid2;
    ';
        # Needs $matches_setup_rels.
    $matches_pts = '
        UPDATE '.$db_prefix.'mv_teams SET pts = getPTS(tid1, trid) WHERE f_trid = trid AND f_tid = tid1;
        UPDATE '.$db_prefix.'mv_teams SET pts = getPTS(tid2, trid) WHERE f_trid = trid AND f_tid = tid2;
    ';
    
        # Needs $matches_setup_rels.
    $matches_wt_cnt = '
        UPDATE '.$db_prefix.'races SET wt_cnt = getWTCnt('.T_OBJ_RACE.', rid1) WHERE race_id = rid1;
        UPDATE '.$db_prefix.'races SET wt_cnt = getWTCnt('.T_OBJ_RACE.', rid2) WHERE race_id = rid2;
        UPDATE '.$db_prefix.'teams SET wt_cnt = getWTCnt('.T_OBJ_TEAM.', tid1) WHERE team_id = tid1;
        UPDATE '.$db_prefix.'teams SET wt_cnt = getWTCnt('.T_OBJ_TEAM.', tid2) WHERE team_id = tid2;
        UPDATE '.$db_prefix.'coaches SET wt_cnt = getWTCnt('.T_OBJ_COACH.', cid1) WHERE coach_id = cid1;
        UPDATE '.$db_prefix.'coaches SET wt_cnt = getWTCnt('.T_OBJ_COACH.', cid2) WHERE coach_id = cid2;
    ';
        # Needs $matches_setup_rels.
    $matches_win_pct = '
        UPDATE '.$db_prefix.'races SET win_pct = getWinPct('.T_OBJ_RACE.', rid1) WHERE race_id = rid1;
        UPDATE '.$db_prefix.'races SET win_pct = getWinPct('.T_OBJ_RACE.', rid2) WHERE race_id = rid2;
        UPDATE '.$db_prefix.'teams SET win_pct = getWinPct('.T_OBJ_TEAM.', tid1) WHERE team_id = tid1;
        UPDATE '.$db_prefix.'teams SET win_pct = getWinPct('.T_OBJ_TEAM.', tid2) WHERE team_id = tid2;
        UPDATE '.$db_prefix.'coaches SET win_pct = getWinPct('.T_OBJ_COACH.', cid1) WHERE coach_id = cid1;
        UPDATE '.$db_prefix.'coaches SET win_pct = getWinPct('.T_OBJ_COACH.', cid2) WHERE coach_id = cid2;
    ';
        # Needs $matches_setup_rels.
    $matches_streaks = '
        CALL getStreaks('.T_OBJ_TEAM.', tid1, NULL, swon,sdraw,slost);
        UPDATE '.$db_prefix.'teams SET '.$db_prefix.'teams.swon = swon, '.$db_prefix.'teams.sdraw = sdraw, '.$db_prefix.'teams.slost = slost WHERE '.$db_prefix.'teams.team_id = tid1;
        CALL getStreaks('.T_OBJ_TEAM.', tid2, NULL, swon,sdraw,slost);
        UPDATE '.$db_prefix.'teams SET '.$db_prefix.'teams.swon = swon, '.$db_prefix.'teams.sdraw = sdraw, '.$db_prefix.'teams.slost = slost WHERE '.$db_prefix.'teams.team_id = tid2;

        CALL getStreaks('.T_OBJ_TEAM.', tid1, trid, swon,sdraw,slost);
        UPDATE '.$db_prefix.'mv_teams SET '.$db_prefix.'mv_teams.swon = swon, '.$db_prefix.'mv_teams.sdraw = sdraw, '.$db_prefix.'mv_teams.slost = slost WHERE '.$db_prefix.'mv_teams.f_trid = trid AND '.$db_prefix.'mv_teams.f_tid = tid1;
        CALL getStreaks('.T_OBJ_TEAM.', tid2, trid, swon,sdraw,slost);
        UPDATE '.$db_prefix.'mv_teams SET '.$db_prefix.'mv_teams.swon = swon, '.$db_prefix.'mv_teams.sdraw = sdraw, '.$db_prefix.'mv_teams.slost = slost WHERE '.$db_prefix.'mv_teams.f_trid = trid AND '.$db_prefix.'mv_teams.f_tid = tid2;
        
        CALL getStreaks('.T_OBJ_COACH.', cid1, NULL, swon,sdraw,slost);
        UPDATE '.$db_prefix.'coaches SET '.$db_prefix.'coaches.swon = swon, '.$db_prefix.'coaches.sdraw = sdraw, '.$db_prefix.'coaches.slost = slost WHERE '.$db_prefix.'coaches.coach_id = cid1;
        CALL getStreaks('.T_OBJ_COACH.', cid2, NULL, swon,sdraw,slost);
        UPDATE '.$db_prefix.'coaches SET '.$db_prefix.'coaches.swon = swon, '.$db_prefix.'coaches.sdraw = sdraw, '.$db_prefix.'coaches.slost = slost WHERE '.$db_prefix.'coaches.coach_id = cid2;

        CALL getStreaks('.T_OBJ_COACH.', cid1, trid, swon,sdraw,slost);
        UPDATE '.$db_prefix.'mv_coaches SET '.$db_prefix.'mv_coaches.swon = swon, '.$db_prefix.'mv_coaches.sdraw = sdraw, '.$db_prefix.'mv_coaches.slost = slost WHERE '.$db_prefix.'mv_coaches.f_trid = trid AND '.$db_prefix.'mv_coaches.f_cid = cid1;
        CALL getStreaks('.T_OBJ_COACH.', cid2, trid, swon,sdraw,slost);
        UPDATE '.$db_prefix.'mv_coaches SET '.$db_prefix.'mv_coaches.swon = swon, '.$db_prefix.'mv_coaches.sdraw = sdraw, '.$db_prefix.'mv_coaches.slost = slost WHERE '.$db_prefix.'mv_coaches.f_trid = trid AND '.$db_prefix.'mv_coaches.f_cid = cid2;
    ';
        # Needs $matches_setup_rels.
    $matches_MVs = '
        SET ret = syncMVteam(tid1, trid);
        SET ret = syncMVteam(tid2, trid);
        SET ret = syncMVcoach(cid1, trid);
        SET ret = syncMVcoach(cid2, trid);
        SET ret = syncMVrace(rid1, trid);
        SET ret = syncMVrace(rid2, trid);
    ';
        # Needs $matches_setup_rels.
    $matches_player_all_stats = '
        OPEN cur_p;
        REPEAT
            FETCH cur_p INTO pid;
            IF NOT done THEN

                /* Update player DPROPS */            
                CALL getPlayerDProps(pid, inj_ma,inj_av,inj_ag,inj_st,inj_ni, ma,av,ag,st, value,status,date_died);
                UPDATE '.$db_prefix.'players 
                    SET '.$db_prefix.'players.inj_ma = inj_ma, '.$db_prefix.'players.inj_av = inj_av, '.$db_prefix.'players.inj_ag = inj_ag, '.$db_prefix.'players.inj_st = inj_st, '.$db_prefix.'players.inj_ni = inj_ni,
                        '.$db_prefix.'players.ma = ma, '.$db_prefix.'players.av = av, '.$db_prefix.'players.ag = ag, '.$db_prefix.'players.st = st, 
                        '.$db_prefix.'players.value = value, '.$db_prefix.'players.status = status, '.$db_prefix.'players.date_died = date_died
                    WHERE '.$db_prefix.'players.player_id = pid;

                /* All-time win percentage */
                UPDATE '.$db_prefix.'players SET win_pct = getWinPct('.T_OBJ_PLAYER.', pid) WHERE player_id = pid;
        
                /* Update MV */
                SET ret = syncMVplayer(pid, trid);
                
            END IF;
        UNTIL done END REPEAT;
        CLOSE cur_p;
        SET done = 0;
    ';
    
    /* 
     *  All routines
     */
     
    $routines = array(
    
        /* 
         *  General 
         */
         
        // Returns status of player in match and latest/current status on mid = -1 or unplayed mid.
        'CREATE FUNCTION getPlayerStatus(pid '.$CT_cols[T_OBJ_PLAYER].', mid '.$CT_cols[T_NODE_MATCH].') 
            RETURNS '.$core_tables['players']['status'].' 
            NOT DETERMINISTIC
            READS SQL DATA
        BEGIN
            DECLARE status '.$core_tables['players']['status'].' DEFAULT NULL;

            IF !EXISTS(SELECT f_match_id FROM '.$db_prefix.'match_data WHERE f_player_id = pid LIMIT 1) THEN
                RETURN '.NONE.';
            END IF;

            IF mid = -1 OR EXISTS(SELECT match_id FROM '.$db_prefix.'matches WHERE match_id = mid AND date_played IS NULL) THEN
                SELECT inj INTO status FROM '.$db_prefix.'match_data, '.$db_prefix.'matches WHERE 
                    f_player_id = pid AND
                    match_id = f_match_id AND
                    date_played IS NOT NULL
                    ORDER BY date_played DESC LIMIT 1;
            ELSE
                SELECT inj INTO status FROM '.$db_prefix.'match_data, '.$db_prefix.'matches WHERE 
                    '.$db_prefix.'match_data.f_player_id = pid AND
                    '.$db_prefix.'matches.match_id = '.$db_prefix.'match_data.f_match_id AND
                    '.$db_prefix.'matches.date_played IS NOT NULL AND
                    '.$db_prefix.'matches.date_played < (SELECT date_played FROM '.$db_prefix.'matches WHERE '.$db_prefix.'matches.match_id = mid)
                    ORDER BY date_played DESC LIMIT 1;
            END IF;
            RETURN IF(status IS NULL, '.NONE.', status);
        END',
        
        'CREATE PROCEDURE getTourParentNodes(IN trid '.$CT_cols[T_NODE_TOURNAMENT].', OUT did '.$CT_cols[T_NODE_DIVISION].', OUT lid '.$CT_cols[T_NODE_LEAGUE].')
            NOT DETERMINISTIC
            READS SQL DATA
        BEGIN
            SELECT  '.$db_prefix.'divisions.did, '.$db_prefix.'divisions.f_lid INTO did,lid FROM '.$db_prefix.'tours,'.$db_prefix.'divisions WHERE '.$db_prefix.'tours.tour_id = trid AND  '.$db_prefix.'tours.f_did = '.$db_prefix.'divisions.did;
        END',

        'CREATE PROCEDURE getObjParents(IN obj TINYINT UNSIGNED, IN pid '.$CT_cols[T_OBJ_PLAYER].', INOUT tid '.$CT_cols[T_OBJ_TEAM].', OUT cid '.$CT_cols[T_OBJ_COACH].', OUT rid '.$CT_cols[T_OBJ_RACE].')
            NOT DETERMINISTIC
            READS SQL DATA
        BEGIN
            CASE obj
              WHEN '.T_OBJ_PLAYER.' THEN SELECT '.$db_prefix.'teams.team_id,'.$db_prefix.'teams.owned_by_coach_id,'.$db_prefix.'teams.f_race_id INTO tid,cid,rid FROM '.$db_prefix.'players,'.$db_prefix.'teams WHERE '.$db_prefix.'players.player_id = pid AND '.$db_prefix.'players.owned_by_team_id = '.$db_prefix.'teams.team_id;
              WHEN '.T_OBJ_TEAM.'   THEN SELECT '.$db_prefix.'teams.owned_by_coach_id,'.$db_prefix.'teams.f_race_id INTO cid,rid FROM '.$db_prefix.'teams WHERE '.$db_prefix.'teams.team_id = tid;
            END CASE;
        END',
        
        /* 
         *  ELO
         */        

        'CREATE PROCEDURE syncAllELOs() 
            NOT DETERMINISTIC
            CONTAINS SQL
        BEGIN
            DECLARE done INT DEFAULT 0;
            DECLARE trid '.$CT_cols[T_NODE_TOURNAMENT].';
            DECLARE cur CURSOR FOR SELECT tour_id FROM '.$db_prefix.'tours;
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
            OPEN cur;
            REPEAT
                FETCH cur INTO trid;
                IF NOT done THEN
                    CALL syncELOTour(trid);
                END IF;
            UNTIL done END REPEAT;
            CLOSE cur;
            CALL syncELOTour(NULL);
        END',
         
        'CREATE PROCEDURE syncELOTour(IN trid '.$CT_cols[T_NODE_TOURNAMENT].') 
            NOT DETERMINISTIC
            CONTAINS SQL
        BEGIN
            DECLARE ret INT;
            DECLARE done INT DEFAULT 0;
            DECLARE mid '.$CT_cols[T_NODE_MATCH].';
            DECLARE curA CURSOR FOR SELECT '.$db_prefix.'matches.match_id FROM '.$db_prefix.'matches WHERE '.$db_prefix.'matches.date_played IS NOT NULL AND '.$db_prefix.'matches.match_id != '.T_IMPORT_MID.' ORDER BY '.$db_prefix.'matches.date_played ASC;
            DECLARE curB CURSOR FOR SELECT '.$db_prefix.'matches.match_id FROM '.$db_prefix.'matches WHERE '.$db_prefix.'matches.date_played IS NOT NULL AND '.$db_prefix.'matches.f_tour_id = trid ORDER BY '.$db_prefix.'matches.date_played ASC;
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

            IF trid IS NULL THEN
                UPDATE '.$db_prefix.'teams   SET elo = NULL;
                UPDATE '.$db_prefix.'coaches SET elo = NULL;
                OPEN curA;
                REPEAT
                    FETCH curA INTO mid;
                    IF NOT done THEN
                        SET ret = syncELOMatch(NULL, mid);
                    END IF;
                UNTIL done END REPEAT;
                CLOSE curA;
            ELSE
                UPDATE '.$db_prefix.'mv_teams   SET elo = NULL WHERE f_trid = trid;
                UPDATE '.$db_prefix.'mv_coaches SET elo = NULL WHERE f_trid = trid;
                OPEN curB;
                REPEAT
                    FETCH curB INTO mid;
                    IF NOT done THEN
                        SET ret = syncELOMatch(trid, mid);
                    END IF;
                UNTIL done END REPEAT;
                CLOSE curB;
            END IF;
        END',

        // If trid is NULL the sets all-time ELO (via. all played matches).
        'CREATE FUNCTION syncELOMatch(trid '.$CT_cols[T_NODE_TOURNAMENT].', mid '.$CT_cols[T_NODE_MATCH].') 
            RETURNS BOOLEAN
            DETERMINISTIC
            CONTAINS SQL
        BEGIN
            DECLARE Rt1_0, Rt2_0, Rc1_0, Rc2_0, Rt1, Rt2, Rc1, Rc2 '.$CT_cols['elo'].';
            DECLARE Et1, Et2, Ec1, Ec2 FLOAT;
            DECLARE S1, S2 FLOAT;
            DECLARE tid1, tid2 '.$CT_cols[T_OBJ_TEAM].';
            DECLARE cid1, cid2 '.$CT_cols[T_OBJ_COACH].';
            
            SELECT 
                team1_id, team2_id, t1.owned_by_coach_id, t2.owned_by_coach_id, 
                IF(team1_score = team2_score, '.T_ELO_PTS_DRAW.', IF(team1_score > team2_score,'.T_ELO_PTS_WON.','.T_ELO_PTS_LOST.')), 
                IF(team1_score = team2_score, '.T_ELO_PTS_DRAW.', IF(team1_score < team2_score,'.T_ELO_PTS_WON.','.T_ELO_PTS_LOST.'))
            INTO tid1, tid2, cid1, cid2, S1, S2 
            FROM '.$db_prefix.'matches, '.$db_prefix.'teams AS t1, '.$db_prefix.'teams AS t2 WHERE match_id = mid AND team1_id = t1.team_id AND team2_id = t2.team_id;
            
            IF trid IS NULL THEN '.$elo_matchsync_R0_alltime.'
            ELSE '.$elo_matchsync_R0_tour.'
            END IF;

            SET Et1 = ELO_E(Rt1_0, Rt2_0);
            SET Et2 = ELO_E(Rt2_0, Rt1_0);
            SET Ec1 = ELO_E(Rc1_0, Rc2_0);
            SET Ec2 = ELO_E(Rc2_0, Rc1_0);

            SET Rt1 = ELO_R(Rt1_0, S1, Et1);
            SET Rt2 = ELO_R(Rt2_0, S2, Et2);
            SET Rc1 = ELO_R(Rc1_0, S1, Ec1);
            SET Rc2 = ELO_R(Rc2_0, S2, Ec2);

            IF trid IS NULL THEN
                UPDATE '.$db_prefix.'teams   SET elo = Rt1 WHERE team_id = tid1;
                UPDATE '.$db_prefix.'teams   SET elo = Rt2 WHERE team_id = tid2;
                UPDATE '.$db_prefix.'coaches SET elo = Rc1 WHERE coach_id = cid1;
                UPDATE '.$db_prefix.'coaches SET elo = Rc2 WHERE coach_id = cid2;                
            ELSE
                UPDATE '.$db_prefix.'mv_teams   SET elo = Rt1 WHERE f_trid = trid AND f_tid = tid1;
                UPDATE '.$db_prefix.'mv_teams   SET elo = Rt2 WHERE f_trid = trid AND f_tid = tid2;
                UPDATE '.$db_prefix.'mv_coaches SET elo = Rc1 WHERE f_trid = trid AND f_cid = cid1;
                UPDATE '.$db_prefix.'mv_coaches SET elo = Rc2 WHERE f_trid = trid AND f_cid = cid2;
            END IF;
            
            RETURN TRUE;
        END',

        'CREATE FUNCTION ELO_E(R1 '.$CT_cols['elo'].', R2 '.$CT_cols['elo'].') 
            RETURNS FLOAT
            DETERMINISTIC
            NO SQL
        BEGIN
            RETURN 1/(1+POW(10,(R2-R1)/'.T_ELO_D.'));
        END',
        
        'CREATE FUNCTION ELO_R(R_0 '.$CT_cols['elo'].', S FLOAT, E FLOAT) 
            RETURNS '.$CT_cols['elo'].'
            DETERMINISTIC
            NO SQL
        BEGIN
            RETURN (R_0 + '.T_ELO_K.'*(S-E));
        END',

        /* 
         *  Streaks
         */
         
        'CREATE PROCEDURE syncAllStreaks()
            NOT DETERMINISTIC
            CONTAINS SQL
        BEGIN
            DECLARE done INT DEFAULT 0;
            DECLARE trid '.$CT_cols[T_NODE_TOURNAMENT].';
            DECLARE tid '.$CT_cols[T_OBJ_TEAM].';
            DECLARE cid '.$CT_cols[T_OBJ_COACH].';
            DECLARE swon,sdraw,slost '.$CT_cols['streak'].';
            DECLARE cur_t CURSOR FOR SELECT team_id FROM '.$db_prefix.'teams;
            DECLARE cur_c CURSOR FOR SELECT coach_id FROM '.$db_prefix.'coaches;
            DECLARE cur_mv_t CURSOR FOR SELECT f_trid, f_tid FROM '.$db_prefix.'mv_teams;
            DECLARE cur_mv_c CURSOR FOR SELECT f_trid, f_cid FROM '.$db_prefix.'mv_coaches;
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
            
            OPEN cur_c;
            REPEAT
                FETCH cur_c INTO cid;
                IF NOT done THEN
                    CALL getStreaks('.T_OBJ_COACH.', cid, NULL, swon,sdraw,slost);
                    UPDATE '.$db_prefix.'coaches SET '.$db_prefix.'coaches.swon = swon, '.$db_prefix.'coaches.sdraw = sdraw, '.$db_prefix.'coaches.slost = slost WHERE '.$db_prefix.'coaches.coach_id = cid;
                END IF;
            UNTIL done END REPEAT;
            CLOSE cur_c;
            SET done = 0;
            
            OPEN cur_mv_c;
            REPEAT
                FETCH cur_mv_c INTO trid, cid;
                IF NOT done THEN
                    CALL getStreaks('.T_OBJ_COACH.', cid, trid, swon,sdraw,slost);
                    UPDATE '.$db_prefix.'mv_coaches SET '.$db_prefix.'mv_coaches.swon = swon, '.$db_prefix.'mv_coaches.sdraw = sdraw, '.$db_prefix.'mv_coaches.slost = slost WHERE '.$db_prefix.'mv_coaches.f_cid = cid AND '.$db_prefix.'mv_coaches.f_trid = trid;
                END IF;
            UNTIL done END REPEAT;
            CLOSE cur_mv_c;
            SET done = 0;

            OPEN cur_t;
            REPEAT
                FETCH cur_t INTO tid;
                IF NOT done THEN
                    CALL getStreaks('.T_OBJ_TEAM.', tid, NULL, swon,sdraw,slost);
                    UPDATE '.$db_prefix.'teams SET '.$db_prefix.'teams.swon = swon, '.$db_prefix.'teams.sdraw = sdraw, '.$db_prefix.'teams.slost = slost WHERE '.$db_prefix.'teams.team_id = tid;
                END IF;
            UNTIL done END REPEAT;
            CLOSE cur_t;
            SET done = 0;

            OPEN cur_mv_t;
            REPEAT
                FETCH cur_mv_t INTO trid, tid;
                IF NOT done THEN
                    CALL getStreaks('.T_OBJ_TEAM.', tid, trid, swon,sdraw,slost);
                    UPDATE '.$db_prefix.'mv_teams SET '.$db_prefix.'mv_teams.swon = swon, '.$db_prefix.'mv_teams.sdraw = sdraw, '.$db_prefix.'mv_teams.slost = slost WHERE '.$db_prefix.'mv_teams.f_tid = tid AND '.$db_prefix.'mv_teams.f_trid = trid;
                END IF;
            UNTIL done END REPEAT;
            CLOSE cur_mv_t;
            SET done = 0;
        END',         
         
        # If trid is NULL returns all-time streaks (across all leagues).
        'CREATE PROCEDURE getStreaks(IN obj TINYINT UNSIGNED, IN obj_id '.$CT_cols[T_OBJ_TEAM].', IN trid '.$CT_cols[T_NODE_TOURNAMENT].',
        OUT swon '.$CT_cols['streak'].', OUT sdraw '.$CT_cols['streak'].', OUT slost '.$CT_cols['streak'].'
        )
            DETERMINISTIC
            READS SQL DATA
        BEGIN 
            IF obj = '.T_OBJ_TEAM.' THEN 
                '.$streaks_team.'
            ELSEIF obj = '.T_OBJ_COACH.' THEN 
                '.$streaks_coach.'
            END IF;
        END',

        /* 
         *  Team count 
         */
         
        'CREATE PROCEDURE syncAllTeamCnts()
            NOT DETERMINISTIC
            CONTAINS SQL
        BEGIN
            UPDATE '.$db_prefix.'races      SET team_cnt = getTeamCnt('.T_OBJ_RACE.', race_id, NULL);
            UPDATE '.$db_prefix.'coaches    SET team_cnt = getTeamCnt('.T_OBJ_COACH.', coach_id, NULL);
            UPDATE '.$db_prefix.'mv_races   SET team_cnt = getTeamCnt('.T_OBJ_RACE.', f_rid, f_trid);
            UPDATE '.$db_prefix.'mv_coaches SET team_cnt = getTeamCnt('.T_OBJ_COACH.', f_cid, f_trid);
        END',
         
        'CREATE FUNCTION getTeamCnt(obj TINYINT UNSIGNED, obj_id '.$CT_cols[T_OBJ_TEAM].', trid '.$CT_cols[T_NODE_TOURNAMENT].')
            RETURNS '.$CT_cols['team_cnt'].'
            NOT DETERMINISTIC
            READS SQL DATA
        BEGIN
            IF obj = '.T_OBJ_RACE.' THEN 
                RETURN (SELECT COUNT(*) FROM '.$db_prefix.'teams WHERE f_race_id = obj_id AND IF(trid,0 < (SELECT COUNT(*) FROM '.$db_prefix.'matches WHERE f_tour_id = trid AND (team1_id = team_id OR team2_id = team_id) LIMIT 1),TRUE));
            ELSEIF obj = '.T_OBJ_COACH.' THEN 
                RETURN (SELECT COUNT(*) FROM '.$db_prefix.'teams WHERE owned_by_coach_id = obj_id AND IF(trid,0 < (SELECT COUNT(*) FROM '.$db_prefix.'matches WHERE f_tour_id = trid AND (team1_id = team_id OR team2_id = team_id) LIMIT 1),TRUE));
            END IF;
        END',

        /* 
         *  Won  '.$db_prefix.'tours count (wt_cnt)
         */

        'CREATE PROCEDURE syncAllWTCnts()
            NOT DETERMINISTIC
            CONTAINS SQL
        BEGIN
            UPDATE '.$db_prefix.'races SET wt_cnt = getWTCnt('.T_OBJ_RACE.', race_id);
            UPDATE '.$db_prefix.'teams SET wt_cnt = getWTCnt('.T_OBJ_TEAM.', team_id);
            UPDATE '.$db_prefix.'coaches SET wt_cnt = getWTCnt('.T_OBJ_COACH.', coach_id);
        END',
         
        'CREATE FUNCTION getWTCnt(obj TINYINT UNSIGNED, obj_id '.$CT_cols[T_OBJ_TEAM].')
            RETURNS '.$CT_cols['team_cnt'].'
            NOT DETERMINISTIC
            READS SQL DATA
        BEGIN
            IF obj = '.T_OBJ_TEAM.' THEN 
                RETURN (SELECT COUNT(*) FROM '.$db_prefix.'tours WHERE winner = obj_id);
            ELSEIF obj = '.T_OBJ_COACH.' THEN 
                RETURN (SELECT COUNT(*) FROM '.$db_prefix.'tours,'.$db_prefix.'teams WHERE '.$db_prefix.'teams.owned_by_coach_id = obj_id AND winner = '.$db_prefix.'teams.team_id);
            ELSEIF obj = '.T_OBJ_RACE.' THEN 
                RETURN (SELECT COUNT(*) FROM '.$db_prefix.'tours,'.$db_prefix.'teams WHERE '.$db_prefix.'teams.f_race_id = obj_id AND winner = '.$db_prefix.'teams.team_id);
            END IF;
        END',
        
        /* 
         *  ALL-TIME win percentages. 
         *  
         *  Note: Tour win pcts are set in MV sync routines.
         */        
        
        'CREATE PROCEDURE syncAllWinPcts()
            NOT DETERMINISTIC
            CONTAINS SQL
        BEGIN
            UPDATE '.$db_prefix.'races SET win_pct = getWinPct('.T_OBJ_RACE.', race_id);
            UPDATE '.$db_prefix.'teams SET win_pct = getWinPct('.T_OBJ_TEAM.', team_id);
            UPDATE '.$db_prefix.'coaches SET win_pct = getWinPct('.T_OBJ_COACH.', coach_id);
            UPDATE '.$db_prefix.'players SET win_pct = getWinPct('.T_OBJ_PLAYER.', player_id);
        END',
         
        'CREATE FUNCTION getWinPct(obj TINYINT UNSIGNED, obj_id '.$CT_cols[T_OBJ_PLAYER].')
            RETURNS '.$CT_cols['win_pct'].'
            NOT DETERMINISTIC
            READS SQL DATA
        BEGIN
            IF obj = '.T_OBJ_TEAM.' THEN 
                RETURN (SELECT winPct(SUM(won),SUM(lost),SUM(draw),SUM(played)) FROM '.$db_prefix.'mv_teams WHERE f_tid = obj_id);
            ELSEIF obj = '.T_OBJ_COACH.' THEN 
                RETURN (SELECT winPct(SUM(won),SUM(lost),SUM(draw),SUM(played)) FROM '.$db_prefix.'mv_coaches WHERE f_cid = obj_id);
            ELSEIF obj = '.T_OBJ_RACE.' THEN 
                RETURN (SELECT winPct(SUM(won),SUM(lost),SUM(draw),SUM(played)) FROM '.$db_prefix.'mv_races WHERE f_rid = obj_id);
            ELSEIF (obj = '.T_OBJ_PLAYER.' OR obj = '.T_OBJ_STAR.') THEN 
                RETURN (SELECT winPct(SUM(won),SUM(lost),SUM(draw),SUM(played)) FROM '.$db_prefix.'mv_players WHERE f_pid = obj_id);
            END IF;
        END', 
        
        'CREATE FUNCTION winPct(won INT UNSIGNED, lost INT UNSIGNED, draw INT UNSIGNED, played INT UNSIGNED)
            RETURNS '.$CT_cols['win_pct'].'
            DETERMINISTIC
            NO SQL
        BEGIN
            RETURN IFNULL(100*(won+draw/2)/played,0);
        END', 

        /* 
         *  Sync all points (PTS)
         */        
        
        'CREATE PROCEDURE syncAllPTS()
            NOT DETERMINISTIC
            CONTAINS SQL
        BEGIN
            UPDATE '.$db_prefix.'mv_teams SET pts = getPTS(f_tid, f_trid);
        END',

        /* 
         *  Object relations
         */
        
        'CREATE PROCEDURE syncAllRels()
            NOT DETERMINISTIC
            CONTAINS SQL
        BEGIN
            DECLARE done INT DEFAULT 0;
            DECLARE pid '.$CT_cols[T_OBJ_PLAYER].';
            DECLARE tid '.$CT_cols[T_OBJ_TEAM].';
            DECLARE f_cid '.$CT_cols[T_OBJ_COACH].';
            DECLARE f_rid '.$CT_cols[T_OBJ_RACE].';
            DECLARE f_rname, f_cname, f_tname, f_pos_name '.$CT_cols['name'].';
            DECLARE cur_p CURSOR FOR SELECT player_id FROM '.$db_prefix.'players;
            DECLARE cur_t CURSOR FOR SELECT team_id FROM '.$db_prefix.'teams;
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
            
            OPEN cur_p;
            REPEAT
                FETCH cur_p INTO pid;
                IF NOT done THEN
                    CALL getPlayerRels(pid, f_cid,f_rid, f_cname,f_rname, f_tname, f_pos_name);
                    UPDATE '.$db_prefix.'players SET 
                        '.$db_prefix.'players.f_cid = f_cid, '.$db_prefix.'players.f_rid = f_rid, 
                        '.$db_prefix.'players.f_cname = f_cname, '.$db_prefix.'players.f_rname = f_rname, 
                        '.$db_prefix.'players.f_tname = f_tname, '.$db_prefix.'players.f_pos_name = f_pos_name
                    WHERE '.$db_prefix.'players.player_id = pid;
                END IF;
            UNTIL done END REPEAT;
            CLOSE cur_p;
            SET done = 0;

            OPEN cur_t;
            REPEAT
                FETCH cur_t INTO tid;
                IF NOT done THEN
                    CALL getTeamRels(tid, f_cname,f_rname);
                    UPDATE '.$db_prefix.'teams SET 
                        '.$db_prefix.'teams.f_cname = f_cname, '.$db_prefix.'teams.f_rname = f_rname
                    WHERE '.$db_prefix.'teams.team_id = tid;
                END IF;
            UNTIL done END REPEAT;
            CLOSE cur_t;
            SET done = 0;

        END',
        
        'CREATE PROCEDURE getPlayerRels(IN pid '.$CT_cols[T_OBJ_PLAYER].', 
            OUT f_cid '.$CT_cols[T_OBJ_COACH].', OUT f_rid '.$CT_cols[T_OBJ_RACE].',
            OUT f_cname '.$CT_cols['name'].', OUT f_rname '.$CT_cols['name'].', 
            OUT f_tname '.$CT_cols['name'].', OUT f_pos_name '.$CT_cols['name'].'
        )
            NOT DETERMINISTIC
            READS SQL DATA
        BEGIN
            SELECT '.$db_prefix.'coaches.coach_id, '.$db_prefix.'races.race_id, '.$db_prefix.'coaches.name, '.$db_prefix.'races.name, '.$db_prefix.'teams.name, '.$db_prefix.'game_data_players.pos
            INTO f_cid, f_rid, f_cname, f_rname, f_tname, f_pos_name
            FROM '.$db_prefix.'players,'.$db_prefix.'teams,'.$db_prefix.'coaches,'.$db_prefix.'races,'.$db_prefix.'game_data_players
            WHERE player_id = pid AND owned_by_team_id = team_id AND owned_by_coach_id = coach_id AND '.$db_prefix.'teams.f_race_id = race_id AND f_pos_id = pos_id;
        END',
        
        'CREATE PROCEDURE getTeamRels(IN tid '.$CT_cols[T_OBJ_TEAM].',
            OUT f_cname '.$CT_cols['name'].', OUT f_rname '.$CT_cols['name'].'
        )
            NOT DETERMINISTIC
            READS SQL DATA
        BEGIN
            SELECT '.$db_prefix.'coaches.name, '.$db_prefix.'races.name
            INTO f_cname, f_rname
            FROM '.$db_prefix.'teams,'.$db_prefix.'coaches,'.$db_prefix.'races
            WHERE team_id = tid AND owned_by_coach_id = coach_id AND f_race_id = race_id;
        END',

        /* 
         *  MV syncs
         */

        'CREATE PROCEDURE syncAllMVs()
            NOT DETERMINISTIC
            CONTAINS SQL
        BEGIN
            DECLARE ret INT;
            DECLARE done INT DEFAULT 0;
            DECLARE pid '.$CT_cols[T_OBJ_PLAYER].';
            DECLARE tid '.$CT_cols[T_OBJ_TEAM].';
            DECLARE cid '.$CT_cols[T_OBJ_COACH].';
            DECLARE rid '.$CT_cols[T_OBJ_RACE].';
            DECLARE trid '.$CT_cols[T_NODE_TOURNAMENT].';
            DECLARE cur_p CURSOR FOR SELECT f_player_id,f_tour_id FROM '.$db_prefix.'match_data GROUP BY f_player_id,f_tour_id;
            DECLARE cur_t CURSOR FOR SELECT f_team_id,  f_tour_id FROM '.$db_prefix.'match_data GROUP BY f_team_id,  f_tour_id;
            DECLARE cur_c CURSOR FOR SELECT f_coach_id, f_tour_id FROM '.$db_prefix.'match_data GROUP BY f_coach_id, f_tour_id;
            DECLARE cur_r CURSOR FOR SELECT f_race_id,  f_tour_id FROM '.$db_prefix.'match_data WHERE f_race_id IS NOT NULL GROUP BY f_race_id, f_tour_id;
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

            OPEN cur_p;
            REPEAT
                FETCH cur_p INTO pid, trid;
                IF NOT done THEN
                    SET ret = syncMVplayer(pid,trid);
                END IF;
            UNTIL done END REPEAT;
            CLOSE cur_p;
            SET done = 0;
            
            OPEN cur_t;
            REPEAT
                FETCH cur_t INTO tid, trid;
                IF NOT done THEN
                    SET ret = syncMVteam(tid,trid);
                END IF;
            UNTIL done END REPEAT;
            CLOSE cur_t;
            SET done = 0;
            
            OPEN cur_c;
            REPEAT
                FETCH cur_c INTO cid, trid;
                IF NOT done THEN
                    SET ret = syncMVcoach(cid,trid);
                END IF;
            UNTIL done END REPEAT;
            CLOSE cur_c;
            SET done = 0;
            
            OPEN cur_r;
            REPEAT
                FETCH cur_r INTO rid, trid;
                IF NOT done THEN
                    SET ret = syncMVrace(rid,trid);
                END IF;
            UNTIL done END REPEAT;
            CLOSE cur_r;
            SET done = 0;
            
        END',

        'CREATE FUNCTION syncMVplayer(pid '.$CT_cols[T_OBJ_PLAYER].', trid '.$CT_cols[T_NODE_TOURNAMENT].')
            RETURNS BOOLEAN
            DETERMINISTIC
            CONTAINS SQL
        BEGIN
            DECLARE did '.$CT_cols[T_NODE_DIVISION].' DEFAULT NULL;
            DECLARE lid '.$CT_cols[T_NODE_LEAGUE].' DEFAULT NULL;
            DECLARE tid '.$CT_cols[T_OBJ_TEAM].' DEFAULT NULL;
            DECLARE cid '.$CT_cols[T_OBJ_COACH].' DEFAULT NULL;
            DECLARE rid '.$CT_cols[T_OBJ_RACE].' DEFAULT NULL;
            CALL getTourParentNodes(trid, did, lid);
            /* Non-ordinary players with no parent relations? */
            IF pid > 0 THEN
                CALL getObjParents('.T_OBJ_PLAYER.', pid,tid,cid,rid);
            END IF;
            
            DELETE FROM '.$db_prefix.'mv_players WHERE f_pid = pid AND f_trid = trid;
            
            INSERT INTO '.$db_prefix.'mv_players(f_pid,f_tid,f_cid,f_rid, f_trid,f_did,f_lid, '.$common_fields_keys.') 
                SELECT pid,tid,cid,rid, trid,did,lid, '.$common_fields.'
                FROM '.$db_prefix.'match_data 
                WHERE '.$db_prefix.'match_data.f_player_id = pid AND '.$db_prefix.'match_data.f_tour_id = trid;
            IF pid > '.ID_MERCS.' THEN
                UPDATE '.$db_prefix.'mv_players '.$mstat_fields_player.' WHERE f_pid = pid AND f_trid = trid;
            ELSE
                UPDATE '.$db_prefix.'mv_players '.$mstat_fields_stars.' WHERE f_pid = pid AND f_trid = trid;
            END IF;
            UPDATE '.$db_prefix.'mv_players SET win_pct = winPct(won,lost,draw,played), sdiff = CAST(gf-ga AS SIGNED), tcdiff = CAST(tcasf-tcasa AS SIGNED) WHERE f_pid = pid AND f_trid = trid;
            
            /* ES */
            DELETE FROM '.$db_prefix.'mv_es_players WHERE f_pid = pid AND f_trid = trid; 
            INSERT INTO '.$db_prefix.'mv_es_players(f_pid,f_tid,f_cid,f_rid, f_trid,f_did,f_lid, '.$common_es_fields_keys.') 
                SELECT pid,tid,cid,rid, trid,did,lid, '.$common_es_fields.'
                FROM '.$db_prefix.'match_data_es
                WHERE '.$db_prefix.'match_data_es.f_pid = pid AND '.$db_prefix.'match_data_es.f_trid = trid;
            
            RETURN EXISTS(SELECT COUNT(*) FROM '.$db_prefix.'mv_players WHERE f_pid = pid AND f_trid = trid);
        END',
        
        'CREATE FUNCTION syncMVteam(tid '.$CT_cols[T_OBJ_TEAM].', trid '.$CT_cols[T_NODE_TOURNAMENT].')
            RETURNS BOOLEAN
            DETERMINISTIC
            CONTAINS SQL
        BEGIN
            DECLARE did '.$CT_cols[T_NODE_DIVISION].' DEFAULT NULL;
            DECLARE lid '.$CT_cols[T_NODE_LEAGUE].' DEFAULT NULL;
            DECLARE cid '.$CT_cols[T_OBJ_COACH].' DEFAULT NULL;
            DECLARE rid '.$CT_cols[T_OBJ_RACE].' DEFAULT NULL;
            CALL getTourParentNodes(trid, did, lid);
            CALL getObjParents('.T_OBJ_TEAM.', NULL,tid,cid,rid);
            
            DELETE FROM '.$db_prefix.'mv_teams WHERE f_tid = tid AND f_trid = trid;

            INSERT INTO '.$db_prefix.'mv_teams(f_tid,f_cid,f_rid, f_trid,f_did,f_lid, '.$common_fields_keys.') 
                SELECT tid,cid,rid, trid,did,lid, '.$common_fields.'
                FROM '.$db_prefix.'match_data 
                WHERE '.$db_prefix.'match_data.f_team_id = tid AND '.$db_prefix.'match_data.f_tour_id = trid;
            UPDATE '.$db_prefix.'mv_teams '.$mstat_fields_team.' WHERE f_tid = tid AND f_trid = trid;
            UPDATE '.$db_prefix.'mv_teams SET win_pct = winPct(won,lost,draw,played), sdiff = CAST(gf-ga AS SIGNED), tcdiff = CAST(tcasf-tcasa AS SIGNED) WHERE f_tid = tid AND f_trid = trid;

            /* ES */
            DELETE FROM '.$db_prefix.'mv_es_teams WHERE f_tid = tid AND f_trid = trid; 
            INSERT INTO '.$db_prefix.'mv_es_teams(f_tid,f_cid,f_rid, f_trid,f_did,f_lid, '.$common_es_fields_keys.') 
                SELECT tid,cid,rid, trid,did,lid, '.$common_es_fields.'
                FROM '.$db_prefix.'match_data_es
                WHERE '.$db_prefix.'match_data_es.f_tid = tid AND '.$db_prefix.'match_data_es.f_trid = trid;

            RETURN EXISTS(SELECT COUNT(*) FROM '.$db_prefix.'mv_teams WHERE f_tid = tid AND f_trid = trid);
        END',
        
        'CREATE FUNCTION syncMVcoach(cid '.$CT_cols[T_OBJ_COACH].', trid '.$CT_cols[T_NODE_TOURNAMENT].')
            RETURNS BOOLEAN
            DETERMINISTIC
            CONTAINS SQL
        BEGIN
            DECLARE did '.$CT_cols[T_NODE_DIVISION].' DEFAULT NULL;
            DECLARE lid '.$CT_cols[T_NODE_LEAGUE].' DEFAULT NULL;
            CALL getTourParentNodes(trid, did, lid);
            
            DELETE FROM '.$db_prefix.'mv_coaches WHERE f_cid = cid AND f_trid = trid;

            INSERT INTO '.$db_prefix.'mv_coaches(f_cid, f_trid,f_did,f_lid, '.$common_fields_keys.') 
                SELECT cid, trid,did,lid, '.$common_fields.'
                FROM '.$db_prefix.'match_data
                WHERE '.$db_prefix.'match_data.f_coach_id = cid AND '.$db_prefix.'match_data.f_tour_id = trid;
            UPDATE '.$db_prefix.'mv_coaches '.$mstat_fields_coach.' WHERE f_cid = cid AND f_trid = trid;
            UPDATE '.$db_prefix.'mv_coaches SET win_pct = winPct(won,lost,draw,played), sdiff = CAST(gf-ga AS SIGNED), tcdiff = CAST(tcasf-tcasa AS SIGNED) WHERE f_cid = cid AND f_trid = trid;

            /* ES */
            DELETE FROM '.$db_prefix.'mv_es_coaches WHERE f_cid = cid AND f_trid = trid; 
            INSERT INTO '.$db_prefix.'mv_es_coaches(f_cid, f_trid,f_did,f_lid, '.$common_es_fields_keys.') 
                SELECT cid, trid,did,lid, '.$common_es_fields.'
                FROM '.$db_prefix.'match_data_es
                WHERE '.$db_prefix.'match_data_es.f_cid = cid AND '.$db_prefix.'match_data_es.f_trid = trid;

            RETURN EXISTS(SELECT COUNT(*) FROM '.$db_prefix.'mv_coaches WHERE f_cid = cid AND f_trid = trid);
        END',

        'CREATE FUNCTION syncMVrace(rid '.$CT_cols[T_OBJ_RACE].', trid '.$CT_cols[T_NODE_TOURNAMENT].')
            RETURNS BOOLEAN
            DETERMINISTIC
            CONTAINS SQL
        BEGIN
            DECLARE did '.$CT_cols[T_NODE_DIVISION].' DEFAULT NULL;
            DECLARE lid '.$CT_cols[T_NODE_LEAGUE].' DEFAULT NULL;
            CALL getTourParentNodes(trid, did, lid);
            
            DELETE FROM '.$db_prefix.'mv_races WHERE f_rid = rid AND f_trid = trid;

            INSERT INTO '.$db_prefix.'mv_races(f_rid, f_trid,f_did,f_lid, '.$common_fields_keys.') 
                SELECT rid, trid,did,lid, '.$common_fields.'
                FROM '.$db_prefix.'match_data
                WHERE '.$db_prefix.'match_data.f_race_id = rid AND '.$db_prefix.'match_data.f_tour_id = trid;
            UPDATE '.$db_prefix.'mv_races '.$mstat_fields_race.' WHERE f_rid = rid AND f_trid = trid;
            UPDATE '.$db_prefix.'mv_races SET win_pct = winPct(won,lost,draw,played), sdiff = CAST(gf-ga AS SIGNED), tcdiff = CAST(tcasf-tcasa AS SIGNED) WHERE f_rid = rid AND f_trid = trid;

            /* ES */
            DELETE FROM '.$db_prefix.'mv_es_races WHERE f_rid = rid AND f_trid = trid; 
            INSERT INTO '.$db_prefix.'mv_es_races(f_rid, f_trid,f_did,f_lid, '.$common_es_fields_keys.') 
                SELECT rid, trid,did,lid, '.$common_es_fields.'
                FROM '.$db_prefix.'match_data_es
                WHERE '.$db_prefix.'match_data_es.f_rid = rid AND '.$db_prefix.'match_data_es.f_trid = trid;

            RETURN EXISTS(SELECT COUNT(*) FROM '.$db_prefix.'mv_races WHERE f_rid = rid AND f_trid = trid);
        END',
        
        /* 
         *  Dynamic (object) properties calculators
         */
        
        'CREATE PROCEDURE syncAllDPROPS()
            NOT DETERMINISTIC
            CONTAINS SQL
        BEGIN
            DECLARE done INT DEFAULT 0;
            
            DECLARE trid '.$CT_cols[T_NODE_TOURNAMENT].';
            DECLARE empty,begun,finished BOOLEAN;
            DECLARE winner '.$CT_cols[T_OBJ_TEAM].';

            DECLARE pid '.$CT_cols[T_OBJ_PLAYER].';
            DECLARE inj_ma,inj_av,inj_ag,inj_st,inj_ni, ma,av,ag,st '.$CT_cols['chr'].';
            DECLARE value '.$CT_cols['pv'].';
            DECLARE status '.$core_tables['players']['status'].';
            DECLARE date_died '.$core_tables['players']['date_died'].';

            DECLARE tid '.$CT_cols[T_OBJ_TEAM].';
            DECLARE tv '.$CT_cols['tv'].';
            DECLARE ff '.$core_tables['teams']['ff'].';

            DECLARE cur_tr CURSOR FOR SELECT tour_id FROM '.$db_prefix.'tours;            
            DECLARE cur_p  CURSOR FOR SELECT player_id FROM '.$db_prefix.'players;
            DECLARE cur_t  CURSOR FOR SELECT team_id FROM '.$db_prefix.'teams;
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

            OPEN cur_tr;
            REPEAT
                FETCH cur_tr INTO trid;
                IF NOT done THEN
                    CALL getTourDProps(trid, empty, begun, finished, winner);
                    UPDATE  '.$db_prefix.'tours SET  '.$db_prefix.'tours.empty = empty,  '.$db_prefix.'tours.begun = begun,  '.$db_prefix.'tours.finished = finished,  '.$db_prefix.'tours.winner = winner WHERE  '.$db_prefix.'tours.tour_id = trid;
                END IF;
            UNTIL done END REPEAT;
            CLOSE cur_tr;
            SET done = 0;

            OPEN cur_p;
            REPEAT
                FETCH cur_p INTO pid;
                IF NOT done THEN
                    CALL getPlayerDProps(pid, inj_ma,inj_av,inj_ag,inj_st,inj_ni, ma,av,ag,st, value,status,date_died);
                    UPDATE '.$db_prefix.'players 
                        SET '.$db_prefix.'players.inj_ma = inj_ma, '.$db_prefix.'players.inj_av = inj_av, '.$db_prefix.'players.inj_ag = inj_ag, '.$db_prefix.'players.inj_st = inj_st, '.$db_prefix.'players.inj_ni = inj_ni,
                            '.$db_prefix.'players.ma = ma, '.$db_prefix.'players.av = av, '.$db_prefix.'players.ag = ag, '.$db_prefix.'players.st = st, 
                            '.$db_prefix.'players.value = value, '.$db_prefix.'players.status = status, '.$db_prefix.'players.date_died = date_died
                        WHERE '.$db_prefix.'players.player_id = pid; 
                END IF;
            UNTIL done END REPEAT;
            CLOSE cur_p;
            SET done = 0;

            OPEN cur_t;
            REPEAT
                FETCH cur_t INTO tid;
                IF NOT done THEN
                    CALL getTeamDProps(tid, tv, ff);
                    UPDATE '.$db_prefix.'teams SET '.$db_prefix.'teams.tv = tv, '.$db_prefix.'teams.ff = ff WHERE '.$db_prefix.'teams.team_id = tid;
                END IF;
            UNTIL done END REPEAT;
            CLOSE cur_t;
            SET done = 0;
        END',
        
        'CREATE PROCEDURE getPlayerDProps(
            IN pid '.$CT_cols[T_OBJ_PLAYER].',
            OUT inj_ma '.$CT_cols['chr'].', OUT inj_av '.$CT_cols['chr'].', OUT inj_ag '.$CT_cols['chr'].', OUT inj_st '.$CT_cols['chr'].', OUT inj_ni '.$CT_cols['chr'].',
            OUT ma '.$CT_cols['chr'].',     OUT av '.$CT_cols['chr'].',     OUT ag '.$CT_cols['chr'].',     OUT st '.$CT_cols['chr'].',
            OUT value '.$CT_cols['pv'].', OUT status '.$core_tables['players']['status'].', OUT date_died '.$core_tables['players']['date_died'].'
        )
            NOT DETERMINISTIC
            READS SQL DATA
        BEGIN
            DECLARE ach_ma,ach_st,ach_ag,ach_av, def_ma,def_st,def_ag,def_av '.$CT_cols['chr'].' DEFAULT 0;
            DECLARE cnt_skills_norm, cnt_skills_doub TINYINT UNSIGNED;
            DECLARE extra_val '.$CT_cols['pv'].';
            DECLARE f_pos_id '.$CT_cols['pos_id'].';

            SELECT 
                '.$db_prefix.'players.f_pos_id, '.$db_prefix.'players.extra_val, '.$db_prefix.'players.ach_ma, '.$db_prefix.'players.ach_st, '.$db_prefix.'players.ach_ag, '.$db_prefix.'players.ach_av
            INTO
                f_pos_id, extra_val, ach_ma, ach_st, ach_ag, ach_av
            FROM '.$db_prefix.'players WHERE player_id = pid;
            
            SET cnt_skills_norm = (SELECT COUNT(*) FROM '.$db_prefix.'players_skills WHERE f_pid = pid AND type = "N");
            SET cnt_skills_doub = (SELECT COUNT(*) FROM '.$db_prefix.'players_skills WHERE f_pid = pid AND type = "D");
        
            SELECT 
                IFNULL(SUM(IF(inj = '.NI.', 1, 0) + IF(agn1 = '.NI.', 1, 0) + IF(agn2 = '.NI.', 1, 0)), 0), 
                IFNULL(SUM(IF(inj = '.MA.', 1, 0) + IF(agn1 = '.MA.', 1, 0) + IF(agn2 = '.MA.', 1, 0)), 0), 
                IFNULL(SUM(IF(inj = '.AV.', 1, 0) + IF(agn1 = '.AV.', 1, 0) + IF(agn2 = '.AV.', 1, 0)), 0), 
                IFNULL(SUM(IF(inj = '.AG.', 1, 0) + IF(agn1 = '.AG.', 1, 0) + IF(agn2 = '.AG.', 1, 0)), 0), 
                IFNULL(SUM(IF(inj = '.ST.', 1, 0) + IF(agn1 = '.ST.', 1, 0) + IF(agn2 = '.ST.', 1, 0)), 0)
            INTO 
                inj_ni,inj_ma,inj_av,inj_ag,inj_st
            FROM '.$db_prefix.'match_data WHERE f_player_id = pid;

            SET value = (SELECT cost FROM '.$db_prefix.'game_data_players WHERE '.$db_prefix.'game_data_players.pos_id = f_pos_id)
                + (ach_ma + ach_av) * 30000
                + ach_ag            * 40000
                + ach_st            * 50000
                + cnt_skills_norm   * 20000
                + cnt_skills_doub   * 30000
                + extra_val
                - inj_ma * '.$rules['value_reduction_ma'].'
                - inj_av * '.$rules['value_reduction_av'].'
                - inj_ag * '.$rules['value_reduction_ag'].'
                - inj_st * '.$rules['value_reduction_st'].';


            SELECT 
                '.$db_prefix.'game_data_players.ma, '.$db_prefix.'game_data_players.st, '.$db_prefix.'game_data_players.ag, '.$db_prefix.'game_data_players.av
            INTO 
                def_ma,def_st,def_ag,def_av
            FROM '.$db_prefix.'game_data_players WHERE '.$db_prefix.'game_data_players.pos_id = f_pos_id;
            SET ma = (ach_ma + def_ma) - inj_ma;
            SET st = (ach_st + def_st) - inj_st;
            SET ag = (ach_ag + def_ag) - inj_ag;
            SET av = (ach_av + def_av) - inj_av;
                
            SET status = getPlayerStatus(pid, -1);
            
            IF status = '.DEAD.' THEN
                SET date_died = (SELECT date_played FROM '.$db_prefix.'matches, '.$db_prefix.'match_data WHERE f_match_id = match_id AND f_player_id = pid AND inj = '.DEAD.');
            ELSE
                SET date_died = NULL;
            END IF;
        END',
        
        'CREATE PROCEDURE getTeamDProps(IN tid '.$CT_cols[T_OBJ_TEAM].', OUT tv '.$CT_cols['tv'].', OUT ff '.$core_tables['teams']['ff'].')
            NOT DETERMINISTIC
            READS SQL DATA
        BEGIN
            DECLARE f_race_id '.$CT_cols[T_OBJ_RACE].';
            DECLARE rerolls '.$core_tables['teams']['rerolls'].';
            DECLARE ff_bought '.$core_tables['teams']['ff_bought'].';
            DECLARE cheerleaders '.$core_tables['teams']['cheerleaders'].';
            DECLARE apothecary '.$core_tables['teams']['apothecary'].';
            DECLARE ass_coaches '.$core_tables['teams']['ass_coaches'].';

            SELECT 
                '.$db_prefix.'teams.f_race_id, '.$db_prefix.'teams.rerolls, '.$db_prefix.'teams.ff_bought, '.$db_prefix.'teams.cheerleaders, '.$db_prefix.'teams.apothecary, '.$db_prefix.'teams.ass_coaches
            INTO 
                f_race_id, rerolls, ff_bought, cheerleaders, apothecary, ass_coaches
            FROM '.$db_prefix.'teams WHERE team_id = tid;

            SET ff = ff_bought + (SELECT IFNULL(SUM('.$db_prefix.'mv_teams.ff),0) FROM '.$db_prefix.'mv_teams WHERE '.$db_prefix.'mv_teams.f_tid = tid);

            SET tv = (SELECT IFNULL(SUM(value),0) FROM '.$db_prefix.'players WHERE owned_by_team_id = tid AND '.$db_prefix.'players.status = '.NONE.' AND '.$db_prefix.'players.date_sold IS NULL)
                + rerolls      * (SELECT cost_rr FROM '.$db_prefix.'races WHERE '.$db_prefix.'races.race_id = f_race_id)
                + ff           * '.$rules['cost_fan_factor'].'
                + cheerleaders * '.$rules['cost_cheerleaders'].'
                + apothecary   * '.$rules['cost_apothecary'].'
                + ass_coaches  * '.$rules['cost_ass_coaches'].';
        END',
        
        'CREATE PROCEDURE getTourDProps(IN trid '.$CT_cols[T_NODE_TOURNAMENT].', OUT empty BOOLEAN, OUT begun BOOLEAN, OUT finished BOOLEAN, OUT winner '.$CT_cols[T_OBJ_TEAM].')
            NOT DETERMINISTIC
            READS SQL DATA
        BEGIN
            DECLARE type '.$core_tables['tours']['type'].';
            SELECT  '.$db_prefix.'tours.type INTO type FROM '.$db_prefix.'tours WHERE tour_id = trid;
            
            SET empty = (SELECT (COUNT(*) < 1) FROM '.$db_prefix.'matches WHERE f_tour_id = trid);
            SET begun = (SELECT (COUNT(*) > 0) FROM '.$db_prefix.'matches WHERE f_tour_id = trid AND date_played IS NOT NULL);
            SET winner = (SELECT IF(team1_score > team2_score, team1_id, team2_id) FROM '.$db_prefix.'matches WHERE f_tour_id = trid AND round = '.RT_FINAL.' AND date_played IS NOT NULL AND team1_score != team2_score LIMIT 1);
            SET finished = (SELECT (type = '.TT_RROBIN.' AND COUNT(*) = 0 OR type = '.TT_FFA.' AND winner IS NOT NULL) FROM '.$db_prefix.'matches WHERE f_tour_id = trid AND date_played IS NULL);
        END',

        /*
            Match sync - ALWAYS run after changes to match data.
        */
        
        'CREATE PROCEDURE match_sync(IN mid '.$CT_cols[T_NODE_MATCH].', IN trid '.$CT_cols[T_NODE_TOURNAMENT].', IN tid1 '.$CT_cols[T_OBJ_TEAM].', IN tid2 '.$CT_cols[T_OBJ_TEAM].', IN played BOOLEAN)
            NOT DETERMINISTIC
            CONTAINS SQL
        BEGIN
            '.$matches_setup_rels.'
            '.$matches_player_all_stats.'
            '.$matches_MVs.'
            '.$matches_tourDProps.'
            '.$matches_teamDProps.'
            '.$matches_team_cnt.'
            '.$matches_wt_cnt.'
            '.$matches_streaks.'
            '.$matches_win_pct.'
            IF played THEN
                CALL syncELOTour(NULL);
                CALL syncELOTour(trid);
            ELSE
                SET ret = syncELOMatch(NULL, mid);
                SET ret = syncELOMatch(trid, mid);
            END IF;
            '.$matches_pts. /* Must be last since the PTS field definition may else depend on other not yet calcualted fields. */ '
        END',
        
        /*
            Sync ALL
        */
        'CREATE PROCEDURE syncAll()
            NOT DETERMINISTIC
            CONTAINS SQL
        BEGIN
            CALL syncAllMVs();      #SELECT "MVs done";
            CALL syncAllDPROPS();   #SELECT "DPROPS done";
            CALL syncAllRels();     #SELECT "Relations done";
            CALL syncAllWinPcts();  #SELECT "Win pcts done";
            CALL syncAllWTCnts();   #SELECT "WT cnts done";
            CALL syncAllTeamCnts(); #SELECT "Team cnts done";
            CALL syncAllStreaks();  #SELECT "Streaks done";
            CALL syncAllELOs();     #SELECT "ELO done";
            /* Must be last since the PTS field definition may else depend on other not yet calcualted fields. */
            CALL syncAllPTS();      #SELECT "PTS done";
        END',
    );
    global $hrs;

    $routines[] = self::mkHRS($hrs);

    $status = true;
    foreach ($routines as $r) {
        $matches = array();
        if (preg_match('/^CREATE FUNCTION (\w*)\(/', $r, $matches)) {
            $status &= mysql_query('DROP FUNCTION IF EXISTS '.$matches[1]);
        }
        $matches = array();
        if (preg_match('/^CREATE PROCEDURE (\w*)\(/', $r, $matches)) {
            $status &= mysql_query('DROP PROCEDURE IF EXISTS '.$matches[1]);
        }
    }

    if (!$install) {
        return $status;
    }

    foreach ($routines as $r) {
        $status &= (mysql_query($r) or die(mysql_error()."\nCODE:\n-----\n\n".$r));
    }
    
    return $status;
}

public static function installTableIndexes()
{
	global $db_prefix;
    // Add tables indicies/keys.
    $indicies = array(
        array("tbl" => "texts",      'name' => "idx_f_id",              "idx" =>  "(f_id)"),
        array("tbl" => "texts",      'name' => "idx_f_id",              "idx" =>  "(f_id2)"),
        array("tbl" => "texts",      'name' => "idx_type",              "idx" =>  "(type)"),
        array("tbl" => "texts",      'name' => "idx_type_f_id_f_id2",   "idx" =>  "(type,f_id,f_id2)"),
        
        array("tbl" => "memberships",'name' => "idx_lid",               "idx" =>  "(lid)"),
        array("tbl" => "memberships",'name' => "idx_cid_lid",           "idx" =>  "(cid,lid)"),
        array("tbl" => "players",    'name' => "idx_owned_by_team_id",  "idx" =>  "(owned_by_team_id)"),
        array("tbl" => "teams",      'name' => "idx_owned_by_coach_id", "idx" =>  "(owned_by_coach_id)"),
        array("tbl" => "matches",    'name' => "idx_f_tour_id",         "idx" =>  "(f_tour_id)"),
        array("tbl" => "matches",    'name' => "idx_team1_id_team2_id", "idx" =>  "(team1_id,team2_id)"),
        array("tbl" => "matches",    'name' => "idx_team2_id",          "idx" =>  "(team2_id)"),
        array("tbl" => "tours",      'name' => "idx_winner",            "idx" =>  "(winner)"),

        array("tbl" => "match_data", 'name' => "idx_m",      "idx" =>  "(f_match_id)"),
        array("tbl" => "match_data", 'name' => "idx_tr",     "idx" =>  "(f_tour_id)"),
        array("tbl" => "match_data", 'name' => "idx_p_m",    "idx" =>  "(f_player_id,f_match_id)"),
        array("tbl" => "match_data", 'name' => "idx_t_m",    "idx" =>  "(f_team_id,  f_match_id)"),
        array("tbl" => "match_data", 'name' => "idx_r_m",    "idx" =>  "(f_race_id,  f_match_id)"),
        array("tbl" => "match_data", 'name' => "idx_c_m",    "idx" =>  "(f_coach_id, f_match_id)"),
        array("tbl" => "match_data", 'name' => "idx_p_tr",   "idx" =>  "(f_player_id,f_tour_id)"),
        array("tbl" => "match_data", 'name' => "idx_t_tr",   "idx" =>  "(f_team_id,  f_tour_id)"),
        array("tbl" => "match_data", 'name' => "idx_r_tr",   "idx" =>  "(f_race_id,  f_tour_id)"),
        array("tbl" => "match_data", 'name' => "idx_c_tr",   "idx" =>  "(f_coach_id, f_tour_id)"),

        array("tbl" => "match_data_es", 'name' => "idx_m",      "idx" =>  "(f_mid)"),
        array("tbl" => "match_data_es", 'name' => "idx_tr",     "idx" =>  "(f_trid)"),
        array("tbl" => "match_data_es", 'name' => "idx_p_m",    "idx" =>  "(f_pid,f_mid)"),
        array("tbl" => "match_data_es", 'name' => "idx_t_m",    "idx" =>  "(f_tid,f_mid)"),
        array("tbl" => "match_data_es", 'name' => "idx_r_m",    "idx" =>  "(f_rid,f_mid)"),
        array("tbl" => "match_data_es", 'name' => "idx_c_m",    "idx" =>  "(f_cid,f_mid)"),
        array("tbl" => "match_data_es", 'name' => "idx_p_tr",   "idx" =>  "(f_pid,f_trid)"),
        array("tbl" => "match_data_es", 'name' => "idx_t_tr",   "idx" =>  "(f_tid,f_trid)"),
        array("tbl" => "match_data_es", 'name' => "idx_r_tr",   "idx" =>  "(f_rid,f_trid)"),
        array("tbl" => "match_data_es", 'name' => "idx_c_tr",   "idx" =>  "(f_cid,f_trid)"),
        
        array('tbl' => 'mv_players',  'name' => 'idx_p_tr', 'idx' => '(f_pid,f_trid)'),
        array('tbl' => 'mv_teams',    'name' => 'idx_t_tr', 'idx' => '(f_tid,f_trid)'),
        array('tbl' => 'mv_coaches',  'name' => 'idx_p_tr', 'idx' => '(f_cid,f_trid)'),
        array('tbl' => 'mv_races',    'name' => 'idx_r_tr', 'idx' => '(f_rid,f_trid)'),
        
        array('tbl' => 'mv_es_players',  'name' => 'idx_p_tr', 'idx' => '(f_pid,f_trid)'),
        array('tbl' => 'mv_es_teams',    'name' => 'idx_t_tr', 'idx' => '(f_tid,f_trid)'),
        array('tbl' => 'mv_es_coaches',  'name' => 'idx_p_tr', 'idx' => '(f_cid,f_trid)'),
        array('tbl' => 'mv_es_races',    'name' => 'idx_r_tr', 'idx' => '(f_rid,f_trid)'),
    );

    $status = true;
    foreach ($indicies as $def) {
        @mysql_query("DROP INDEX $def[name] ON ".$db_prefix."$def[tbl]");
        $status &= mysql_query("ALTER TABLE ".$db_prefix."$def[tbl] ADD INDEX $def[name] $def[idx]");
    }
    return $status;
}

public static function installMVs($delIfExists) {
    
    global $core_tables,$db_prefix;
    $status = true;
    foreach ($core_tables as $name => $tbl) {
        if (!preg_match('/^mv\_/', $name))
            continue;
            
        if ($delIfExists) {
            $status &= mysql_query("DROP TABLE IF EXISTS ".$db_prefix."$name");
        }
        $status &= Table::createTable($name,$core_tables[$name]);
    }
    
    return $status;
}

public static function reviseEStables()
{
    global $ES_fields, $core_tables,$db_prefix;
    $MDES = $core_tables['match_data_es'];
    $status = true;
    $dropped = $added = array();
    
    // Create tables if not existing:
    # This will create all the ES MV (and regular, though not needed) tables with the correct up-to-date fields.
    self::installMVs(true);
    # Create, if not exists, the match_data_es table.
    Table::createTableIfNotExists('match_data_es', $MDES);
    
    // Remove non-existing fields.
    $result = mysql_query("DESCRIBE ".$db_prefix."match_data_es");
    $existingFields = array();
    while ($r = mysql_fetch_assoc($result)) {
        // Ignore relational fields.
        if (preg_match('/^f\_/', $r['Field'])) {
            continue;
        }
        $existingFields[] = $r['Field'];
        if (!in_array($r['Field'], array_keys($ES_fields))) {
            $dropped[] = $r['Field'];
            $status &= mysql_query("ALTER TABLE match_data_es DROP $r[Field]");
        }
    }
    // Add new fields.
    foreach (array_diff(array_keys($ES_fields), $existingFields) as $newField) {
        $added[] = $newField;
        $status &= mysql_query("ALTER TABLE match_data_es ADD COLUMN $newField ".$ES_fields[$newField]['type']);
    }
    
    return array($status,$added,$dropped);
}

}

?>
