<?php
exit;
include( 'lib.inc.php' );
include( 'db.inc.php' );

var_dump( main() );

function main(){
	$db = new database( getDbcredentials() );
    $msglist = array();
    //select a game
    $sql = "SELECT id FROM game ORDER BY RAND() LIMIT 1";
$msglist[] = $sql;
    //$gameid = array_shift( $db->fetchColumn( $sql ) );
    $gameid = 1074;
    $msglist[] = "got gameid $gameid";
    $msglist[] = $db->gamestatIncrement( $gameid, 'friendsinvited' );
    return $msglist;
}
