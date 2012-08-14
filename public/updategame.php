<?php
include('db.inc.php');
include('lib.inc.php');
$req = $_GET;
$params = array(
	'user' => getArrayValue( $req, 'user' ),
	'gameid' => getArrayValue( $req, 'game' ),
	'score' => getArrayValue( $req, 'score' ),
    'answers' => getArrayValue( $req, 'answers' )
);

if( $errors = invalidateParams( $params ) ){
    exit(json_encode(array('error'=>$errors)));
}
else{
    echo json_encode( updateGame( $params ) );
}


function updateGame( $params ){
	$db = new database( getDbcredentials() );
    $gameover = false;
    if( $db->gameHasReachedTarget( $params[ 'gameid' ] ) ){
        $gameover = true;
    }
    elseif( $db->insertGameHistory( $params ) ){
        if( $db->gameHasReachedTarget( $params[ 'gameid' ] ) ){
            $gameover = true;
            $info = finishGame( $db, $params[ 'gameid' ] );
        }
        $info = array(
            'status' => 'success'
        );
    }
    else{
        $info = array(
            'error' => $db->getError()
        );
    }
    $info[ 'gamestatus' ] = $db->getGameStatus( $params[ 'gameid' ] );
    if( $gameover ){
        $info[ 'gameover' ] = 1;
    }
    else{
        $info[ 'gameover' ] = 0;
    }
    
    return $info;
}

function finishGame( $db, $gameid ){
    $errorlist = $db->finishGame( $gameid );
    if( count( $errorlist ) ){
        return array( 'error' => $errorlist );
    }
    return array( 'status' => 'success', 'message' => "game $gameid has reached its target" );
}

function invalidateParams( $params ){
    $required = array( 'user', 'gameid', 'score', 'answers' );
    $numeric = array( 'gameid', 'score' );
    $csn = array( 'answers' );
    return findError( $params, $required, $numeric, $csn, 5 );
}
