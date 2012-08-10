<?php
include('db.inc.php');
include('lib.inc.php');
$req = $_GET;
$params = array(
	'user' => getArrayValue( $req, 'user' ),
	'level' => getArrayValue( $req, 'difficulty' ),
	'category' => getArrayValue( $req, 'category' ),
	'target' => getArrayValue( $req, 'target' ),
    'to' => getArrayValue( $req, 'to' ),
    'quids' => getArrayValue( $req, 'quids' )
);

if( $errors = invalidateParams( $params ) ){
    exit(json_encode(array('error'=>$errors)));
}
else{
    echo json_encode( createNewGame( $params ) );
}

function createNewGame( $params ){
    //var_dump($params);
	$db = new database( getDbcredentials() );
    $gameid = $db->startGame( $params );
    $tolist = array();
    if( $gameid ){
        $gameinfo = array( 'gameid' => $gameid );
        if( $to = $params[ 'to' ] ){
            //send invitations to the to list
            $tolist = explode( ',', $to );
        }
        else{
            //no invitees ... choose 10 random invitees
            $tolist = $db->chooseTeammates( $gameid, $params[ 'category' ], $params[ 'level' ] );
        }
        if( count($tolist) ){
            $errorlist = array();
            $from = $params[ 'user' ];
            foreach( $tolist as $toid ){
                if( $error = inviteSingle( $gameid, $from, $toid, $db ) ){
                    $errorlist[] = $error;
                }
            }
        }
        else{
            //no invitees
        }
        if( $errorlist ){
            $gameinfo[ 'error' ] = $errorlist;
        }
        return $gameinfo;
    }
    else{ 
        return array( 'error' => 'no game created' );
    }
}

function invalidateParams( $params ){
    $required = array( 'user', 'level', 'category', 'quids', 'target' );
    $numeric = array( 'level', 'category', 'target' );
    $commaseparatednumbers = array( 'quids' );
    $expectedsize = 5;
    return findError( $params, $required, $numeric, $commaseparatednumbers, $expectedsize );
}
