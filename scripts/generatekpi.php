<?php
define('CRON_PATH', dirname(__DIR__));
include( CRON_PATH . '/public/db.inc.php' );
include( CRON_PATH . '/public/lib.inc.php' );

//echo main();
main();

function main(){
    $startdate = date( 'Y-m-d', time() - (1 * 24 * 3600) );
    echo $startdate;
    $stats = generateStats( $startdate );
	$db = new database( getDbcredentials() );
    $db->insertKpi( $startdate, $stats );
    return $stats;
}
function generateStats( $startdate ){
    $mysqlstart = "$startdate 00:00:00";
    $mysqlend = "$startdate 23:50:59";
    $timeclause = "g.start >= '$mysqlstart' AND g.start <= '$mysqlend'";
    //echo $timeclause;
	$db = new database( getDbcredentials() );
    $tasklist = array(
        'total games started' => array( 'getTotalGamesStarted', array( $db, $timeclause ) ),
        'friends invited per game' => array( 'getFriendsPerGame', array($db, $timeclause) ),
        'average acceptance per game' => array( 'getAvgAcceptance', array($db, false, $timeclause) ),
        'average acceptance from friends per game' => array('getAvgAcceptance', array($db, 'friends', $timeclause ) ),
        'average acceptance from strangers per game' => array('getAvgAcceptance', array($db, 'strangers', $timeclause ) ),
        'facebook connect number' => array( 'countFacebookIds', array( $db ) ),
        'correction rate per user per difficulty per category' => array('getCorrectionrateByLevelByCat', array( $db, $timeclause ) ),
        'average coins earned per user per game per difficulty' => array('getCoinsEarnedPerPlayerPerGameByLevel', array($db)),
        'cumulative coins earned per user' => array('getCumulativeCoinsPerUser', array($db, $timeclause ) ),
        'cumulative jewels purchased per user' => array( 'getCumulativeJewelsPerUser', array( $db, $timeclause ) ),
        //'number of purchases per premium function' => array(),
        'average hours taken per game' => array('getAverageHoursPerGame', array( $db, $timeclause ) ),
        'number of games initiated per difficulty per category' => array( 'getGamesStartedByLevel', array( $db, $timeclause ) ),
        'number of games which achieved the target per difficulty per category' => array('getGamesWhichAchievedTarget', array($db, $timeclause ) )
/*
*/
    );
    $output = outformat( "Quipper Game Stats", "date,$startdate" );
    foreach( $tasklist as $title=>$function ){
        if( $function ){
            $data = call_user_func_array( $function[0], $function[1] );
        }
        else{
            $data = 'no function';
        }
        $output .= outformat( $title, $data );
    }
    return $output;
}

function outformat( $title, $data ){
    return "$title\n$data\n\n";
    return "<div>\n\t<h3>$title</h3>\n\t<p>$data</p>\n</div>";
}

function wrong_getCumulativeJewelsPerUser( $db ){
    $sql = "SELECT AVG(jewelsbought) FROM USER";
    return $db->fetchSingleValueSql( $sql );
}
function getActiveUserIdListForTimeclause( $db, $timeclause ){
    $sql = "
            SELECT GROUP_CONCAT( DISTINCT u.id) 
            FROM user u
            JOIN gamehistory gh ON gh.user_id = u.id
            JOIN game g ON g.id = gh.game_id
            WHERE $timeclause
    ";
    $activeuserlist = array_shift( $db->fetchColumn( $sql ) );
    return $activeuserlist;
}

function getCumulativeJewelsPerUser($db, $timeclause){
    $activeuserlist = getActiveUserIdListForTimeclause( $db, $timeclause );
    $jewelsql = "SELECT AVG(jewelsbought) FROM user WHERE id IN ($activeuserlist)";
    return $db->fetchSingleValueSql($jewelsql);
}
function getCumulativeCoinsPerUser($db, $timeclause){
    $activeuserlist = getActiveUserIdListForTimeclause( $db, $timeclause );
    //$sql = "SELECT AVG( coinsearned ) FROM user WHERE id IN ($activeuserlist)";
    //return $db->fetchSingleValueSql($sql);
}

function getTotalGamesStarted( $db, $timeclause ){
    $sql = "SELECT COUNT(*) FROM game g WHERE $timeclause";
    return $db->fetchSingleValueSql( $sql );
}

function getFriendsPerGame($db, $timeclause){
    //return array_shift( $db->fetchColumn( $sql ) );
    $sql = "SELECT AVG(gst.friendsinvited) FROM gamestat gst 
            JOIN game g ON g.id = gst.game_id
            WHERE $timeclause
            GROUP BY g.id
    ";
    return $db->fetchSingleValueSql( $sql );
}
function getAvgAcceptance($db, $subcat=false, $timeclause=false){
    if( $subcat ){
        $field = "SUM(gst.$subcat" . "joined) / SUM( gst.$subcat" . "invited )";
    }
    else{
        $field = "(SUM(gst.friendsjoined) + SUM( gst.strangersjoined))/(SUM(gst.friendsinvited)+SUM(gst.strangersinvited))";
    }
    $sql = "SELECT $field 
            FROM gamestat gst
            JOIN game g ON g.id = gst.game_id
            WHERE $timeclause;
    ";
    return $db->fetchSingleValueSql($sql);
}
function countFacebookIds($db){
    return $db->fetchSingleValueSql( "SELECT COUNT(id) FROM user WHERE username REGEXP('^[0-9]*$')" );
}
function getCorrectionrateByLevelByCat( $db , $timeclause=false ){
    $sql = "SELECT g.level, c.name category, SUM(gh.corrections)/COUNT(DISTINCT user_id) 'corrections per user' FROM gamehistory gh JOIN game g ON g.id = gh.game_id JOIN category c ON c.id = g.category_id "; 
    if( $timeclause ){
        $sql .= "WHERE $timeclause";
    }
    $sql .= " GROUP BY g.level, g.category_id";
    return rstFormat( $db->fetchAll( $sql ) );
}
function rstFormat( $rst ){
    $outlist = array();
    if( count($rst) ){
	    $headinglist = array_keys( $rst[0] );
	    $outlist[] = implode( ',', $headinglist );
	    foreach( $rst as $row ){
	        $outlist[] = implode( ',', array_values( $row ) );
	    }
	    return implode( "\n", $outlist );
    }
    return 'no data';
}
function getCoinsEarnedPerPlayerPerGameByLevel($db, $timeclause=false){
    $whereandlist = array();
    if( $timeclause ){
        $whereandlist[] = $timeclause;
    }
    $sql = "SELECT g.level, COUNT(gst.game_id) 'no. of games', SUM(coinsearned)/SUM(strangersjoined+friendsjoined) 'coins per user' FROM gamestat gst JOIN game g ON g.id = gst.game_id GROUP BY g.level";
    if( $whereandlist ){
        $sql .= " WHERE " . implode( ' AND ', $whereandlist );
    }
    return rstFormat( $db->fetchAll( $sql ) );
}
function getAverageHoursPerGame($db, $timeclause){
    $field = "AVG(UNIX_TIMESTAMP(finished) - UNIX_TIMESTAMP(start))/3600";
    $sql = "SELECT $field FROM game g";
    if( $timeclause ){
        $sql .= " WHERE $timeclause";
    }
    return $db->fetchSingleValueSql($sql);
}
function getGamesStartedByLevel($db, $timeclause=false ){
    $sql = "SELECT g.level, c.name category, COUNT(g.id) 'no. of games' FROM game g JOIN category c ON c.id = g.category_id";
    if( $timeclause ){
        $sql .= " WHERE $timeclause";
    }
    $sql .= " GROUP BY g.level, g.category_id";
    $info = $db->fetchAll( $sql );
    return rstFormat( $info );
}
function getGamesWhichAchievedTarget( $db, $timeclause=false ){
    $sql = "SELECT g.level, c.name category, COUNT(g.id) 'no. of games', SUM(gst.coinsearned) 'total coins' FROM gamestat gst JOIN game g ON g.id = gst.game_id JOIN category c ON c.id = g.category_id WHERE gst.coinsearned >= g.target";
    if( $timeclause ){
        $sql .= " AND $timeclause";
    }
    $sql .= " GROUP BY g.level, g.category_id";
    $info = $db->fetchAll( $sql );
    return rstFormat($info);
}
