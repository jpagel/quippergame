<?php
include( '../lib.inc.php' );
include( '../db.inc.php' );
session_start();

$req = $_GET;

echo mainn( $req );
//echo test( $req );

function test( $req ){
    include( '../../scripts/generatekpi.php' );
    $startdate = date( 'Y-m-d', time() - (2 * 24 * 3600) );
    echo "<pre>";
    echo generateStats( $startdate );
    echo "</pre>";
}

function mainn( $req ){
var_dump($_SESSION);
$timenow = date( 'H:i:s' );
if( $sessiontime = getArrayValue( $_SESSION, 'time' ) ){
    echo "Session time is $sessiontime";
}
else{
    echo $timenow;
    $_SESSION[ 'time' ] = $timenow;
}
return;
	$db = new database( getDbcredentials() );
    $availabledates = $db->getKpiList();
    if( $target = getArrayValue( $req, 'target' ) ){
        streamout( $db, $target );
    }
    echo getAdminList( $availabledates );
    return '';
}

function getAdminList( $itemlist ){
    $outlist = array();
    foreach( $itemlist as $item ){
        $href = "?target=$item";
        $outlist[] = "<li><a href=\"$href\">$item</a></li>";
    }
    return "<ul>" . implode( "\n", $outlist ) . "</ul>";
}

function streamout( $db, $target ){
    $content = $db->fetchSingleValue( 'kpi', 'reportcsv', "'$target'", 'targetdate' );
    header('Content-type: application/excel');
    header('Content-Disposition: attachment; filename="quippergame_stats_' . $target . '.csv"');
    exit( $content );
}
