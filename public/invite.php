<?php
include('db.inc.php');
include('lib.inc.php');

$req = $_GET;

$params = array(
    'from' => getArrayValue( $req, 'from' ),
    'to' => getArrayValue( $req, 'to' ),
    'gameid' => getArrayValue( $req, 'game' )
);

if( $errors = invalidateParams( $params ) ){
    exit(json_encode(array('error'=>$errors)));
}
else{
    echo json_encode( sendInvitations( $params ) );
}

function sendInvitations( $params ){
    extract( $params );
    $tolist = explode( ',', $to );
    $errorlist = array();
    $db = new database( getDbcredentials() );
    foreach( $tolist as $toid ){
        if( $error = inviteSingle( $gameid, $from, $toid, 1, $db ) ){
            $errorlist[] = $error;
        }
    }
    if( count( $errorlist ) ){
        return array(
            'error' => $errorlist
        );
    }
    else{
        return array(
            'category' => $db->getCategoryNameForGame( $gameid ),
            'status' => 'success'
        );
    }
}

function invalidateParams( $params ){
    $required = array( 'from', 'to', 'gameid' );
    $numeric = array( 'gameid' );
    $errorlist = findError( $params, $required, $numeric );
    //to should be comma-seperated list
    $tolist = explode(',',$params[ 'to' ] );
    if( $tolist ){
	    foreach( $tolist as $id ){
	        if( trim($id) && !is_numeric( $id ) ){
	            //$errorlist[] = "$id is not numeric";
	        }
	    }
    }
    return $errorlist;
}
