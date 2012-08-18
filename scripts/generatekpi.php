<?php
define('CRON_PATH', dirname(__DIR__));
include( CRON_PATH . '/public/db.inc.php' );
include( CRON_PATH . '/public/lib.inc.php' );
main();

//echo main();

function main(){
    $startdate = date( 'Y-m-d', time() - (2 * 24 * 3600) );
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
    $categorylist = array(
         1 => 'General Knowledge',
         2 => 'Entertainment',
         3 => 'Geography',
         4 => 'Arts',
         5 => 'Sports & Health',
         6 => 'Math & Science',
         7 => 'Lifestyle',
         8 => 'History & Religion',
         9 => 'Languages'
    );
    
	$db = new database( getDbcredentials() );
    $tasklist = array();
    $tasklist[ 'total games started' ] = array( 'getTotalGamesStarted', array( $db, $timeclause ) );
    $tasklist[ 'friends invited per game' ] = array( 'getFriendsPerGame', array($db, $timeclause) );
    $tasklist[ 'average acceptance per game' ] = array( 'getAvgAcceptance', array($db, false, $timeclause) );
    $tasklist[ 'average acceptance from friends per game' ] = array('getAvgAcceptance', array($db, 'friends', $timeclause ) );
    $tasklist[ 'average acceptance from strangers per game' ] = array('getAvgAcceptance', array($db, 'strangers', $timeclause ) );
    $tasklist[ 'facebook connect number' ] = array( 'countFacebookIds', array( $db, $timeclause ) );

    foreach( $categorylist as $catid=>$name ){
        $tasklist[ "correction rate per user level 1 $name" ] = array('getCorrectionrateByLevelByCat', array( $db, $timeclause, 1, $catid ) );
    }
    foreach( $categorylist as $catid=>$name ){
        $tasklist[ "correction rate per user level 2 $name" ] = array("getCorrectionrateByLevelByCat", array( $db, $timeclause, 2, $catid ) );
    }
    foreach( $categorylist as $catid=>$name ){
        $tasklist[ "correction rate per user level 3 $name" ] = array("getCorrectionrateByLevelByCat", array( $db, $timeclause, 3, $catid ) );
    }

    $tasklist[ 'average coins earned per user per game level 1' ] = array('getCoinsEarnedPerPlayerPerGameByLevel', array($db, $timeclause, 1));
    $tasklist[ 'average coins earned per user per game level 2' ] = array('getCoinsEarnedPerPlayerPerGameByLevel', array($db, $timeclause, 2));
    $tasklist[ 'average coins earned per user per game level 3' ] = array('getCoinsEarnedPerPlayerPerGameByLevel', array($db, $timeclause, 3));

    $tasklist[ 'cumulative coins earned per user' ] = array('getCumulativeCoinsPerUser', array($db, $timeclause ) );
    $tasklist[ 'cumulative jewels purchased per user' ] = array( 'getCumulativeJewelsPerUser', array( $db, $timeclause ) );
        //    $tasklist[ 'number of purchases per premium function' ] = array();
    $tasklist[ 'average hours taken per game' ] = array('getAverageHoursPerGame', array( $db, $timeclause ) );

    foreach( $categorylist as $catid=>$name ){
        $tasklist[ "number of games initiated level 1 $name" ] = array( "getGamesStartedByLevel", array( $db, $timeclause, 1, $catid ) );
    }
    foreach( $categorylist as $catid=>$name ){
        $tasklist[ "number of games initiated level 2 $name" ] = array( "getGamesStartedByLevel", array( $db, $timeclause, 2, $catid ) );
    }
    foreach( $categorylist as $catid=>$name ){
        $tasklist[ "number of games initiated level 3 $name" ] = array( "getGamesStartedByLevel", array( $db, $timeclause, 3, $catid ) );
    }

    foreach( $categorylist as $catid=>$name ){
        $tasklist[ "number of games which achieved the target level 1 $name" ] = array("getGamesWhichAchievedTarget", array($db, $timeclause, 1, $catid ) );
    }
    foreach( $categorylist as $catid=>$name ){
        $tasklist[ "number of games which achieved the target level 2 $name" ] = array("getGamesWhichAchievedTarget", array($db, $timeclause, 2, $catid ) );
    }
    foreach( $categorylist as $catid=>$name ){
        $tasklist[ "number of games which achieved the target level 3 $name" ] = array("getGamesWhichAchievedTarget", array($db, $timeclause, 3, $catid ) );
    }
    
    //$output = outformat( "Quipper Game Stats", "date,$startdate" );
    $headerlist = array( 'date' );
    $valuelist = array( $startdate );
    foreach( $tasklist as $title=>$function ){
        if( $function ){
            $data = call_user_func_array( $function[0], $function[1] );
        }
        else{
            $data = 'no function';
        }
        //$output .= outformat( $title, $data );
        $headerlist[] = $title;
        $valuelist[] = $data;
    }

    $headerrow = implode( ',' , $headerlist );
    $valuerow = implode( ',', $valuelist );
    $output .= $headerrow . "\n";
    $output .= $valuerow ;
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
    if( $n = $db->fetchSingleValueSql($jewelsql) ){
        return $n;
    }
    return 0;
}
function getCumulativeCoinsPerUser($db, $timeclause){
    $activeuserlist = getActiveUserIdListForTimeclause( $db, $timeclause );
    $sql = "SELECT AVG( coinsearned ) FROM user WHERE id IN ($activeuserlist)";
    if( $n = $db->fetchSingleValueSql($sql) ){
        return $n;
    }
    return 0;
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
function countFacebookIds($db , $timeclause){
    $activeuserlist = getActiveUserIdListForTimeclause( $db, $timeclause );
    return $db->fetchSingleValueSql( "SELECT COUNT(id) FROM user WHERE username REGEXP('^[0-9]*$') AND id IN ($activeuserlist)" );
}
function getCorrectionrateByLevelByCat( $db , $timeclause=false, $level=false, $categoryid=false ){
    $sql = "SELECT SUM(gh.corrections)/COUNT(DISTINCT user_id) 'corrections per user' FROM gamehistory gh JOIN game g ON g.id = gh.game_id JOIN category c ON c.id = g.category_id "; 
    $whereandlist = array();
    if( $timeclause ){
        $whereandlist[] = $timeclause;
        //$sql .= "WHERE $timeclause";
    }
    if( $level ){
        $whereandlist[] = "g.level = $level";
    }
    if( $categoryid ){
        $whereandlist[] = "g.category_id = $categoryid";
    }
    if( $whereandlist ){
        $sql .= " WHERE " . implode( ' AND ', $whereandlist );
    }
    $sql .= " GROUP BY g.level, g.category_id";
    return $db->fetchSingleValueSql( $sql );
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
function oldrstFormat( $rst ){
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
function getCoinsEarnedPerPlayerPerGameByLevel($db, $timeclause=false, $level=false, $categoryid=false ){
    $whereandlist = array();
    if( $timeclause ){
        $whereandlist[] = $timeclause;
    }
    if( $level ){
        $whereandlist[] = "g.level = $level";
    }
    if( $categoryid ){
        $whereandlist[] = "g.category_id = $categoryid";
    }
    $sql = "SELECT SUM(coinsearned)/SUM(strangersjoined+friendsjoined) 'coins per user' FROM gamestat gst JOIN game g ON g.id = gst.game_id ";
    if( $whereandlist ){
        $sql .= " WHERE " . implode( ' AND ', $whereandlist );
    }
    $sql .= " GROUP BY g.level";
    if( $n = $db->fetchSingleValueSql( $sql ) ){
        return $n;
    }
    return 0;
}
function getAverageHoursPerGame($db, $timeclause){
    $field = "AVG(UNIX_TIMESTAMP(finished) - UNIX_TIMESTAMP(start))/3600";
    $sql = "SELECT $field FROM game g";
    if( $timeclause ){
        $sql .= " WHERE $timeclause";
    }
    if( $n = $db->fetchSingleValueSql( $sql ) ){
        return $n;
    }
    return 0;
}
function getGamesStartedByLevel($db, $timeclause=false, $level=false, $categoryid=false ){
    $sql = "SELECT COUNT(g.id) 'no. of games' FROM game g JOIN category c ON c.id = g.category_id";
    $whereandlist = array();
    if( $timeclause ){
        $whereandlist[] = $timeclause;
    }
    if( $level ){
        $whereandlist[] = "g.level = $level";
    }
    if( $categoryid ){
        $whereandlist[] = "g.category_id = $categoryid";
    }
    if( $whereandlist ){
        $sql .= " WHERE " . implode( ' AND ' , $whereandlist );
    }
    $sql .= " GROUP BY g.level, g.category_id";
    //$info = $db->fetchAll( $sql );
    //return rstFormat( $info );
    if( $n = $db->fetchSingleValueSql( $sql ) ){
        return $n;
    }
    return 0;
}
function getGamesWhichAchievedTarget( $db, $timeclause=false, $level=false, $categoryid=false ){
    $sql = "SELECT g.level, c.name category, COUNT(g.id) 'no. of games', SUM(gst.coinsearned) 'total coins' FROM gamestat gst JOIN game g ON g.id = gst.game_id JOIN category c ON c.id = g.category_id";
    $sql = "SELECT COUNT(g.id) 'no. of games' FROM gamestat gst JOIN game g ON g.id = gst.game_id JOIN category c ON c.id = g.category_id";
    $whereandlist = array( 'gst.coinsearned >= g.target' );
    if( $timeclause ){
        $whereandlist[] = $timeclause;
    }
    if( $level ){
        $whereandlist[] = "g.level = $level";
    }
    if( $categoryid ){
        $whereandlist[] = "g.category_id = $categoryid";
    }
    if( $whereandlist ){
        $sql .= " WHERE " . implode( ' AND ' , $whereandlist );
    }
    $sql .= " GROUP BY g.level, g.category_id";
    if( $n = $db->fetchSingleValueSql( $sql ) ){
        return $n;
    }
    return 0;
}
