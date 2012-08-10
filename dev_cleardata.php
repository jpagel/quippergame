<?php
include('db.inc.php');
include('lib.inc.php');

$tablesForChop = array(
    'user', 
    'user_device', 
    'invitation', 
    'game', 
    'gamesession', 
    'gamehistory'
);

$db = new database( getDbcredentials() );
foreach( $tablesForChop as $table ){
    truncatetable( $db, $table );   
}

function truncatetable( $db, $table ){
    echo "truncating $table:";
    if( $db->truncatetable( $table ) ){
        echo " done";
    }
    else{
        echo " nothing happened";
    }
    echo "<hr />";
}
