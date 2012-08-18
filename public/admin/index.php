<?php
include( '../lib.inc.php' );
include( '../db.inc.php' );
session_start();

$req = $_GET;

if( authenticated( $_POST ) ){
    echo mainn( $req, $_POST );
}
else{
    echo loginform( $_POST );
}

function loginform( $post ){
    $username = getArrayValue( $post, 'username' );
    $password = getArrayValue( $post, 'password' );
    return <<<EOF
    <div>
        <form action="" method="POST">
        <div>
            <span>username</span>
            <span><input type="text" name="username" value="$username" /></span>
        </div>
        <div>
            <span>password</span>
            <span><input type="password" name="password" value="$password" /></span>
        </div>
        <div>
            <span style="width:150px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
            <span><input type="submit" value="login" /></span>
        </div>
        </form>
    </div>
EOF;
}

function authenticated( $req ){
    $userlist = array(
        'mariko' => 'kleenexswordfish'
    );
    foreach( $userlist as $username=>$password ){
        if( getArrayValue( $req, 'username' ) == $username && getArrayValue( $req, 'password' ) == $password ){
            $_SESSION[ 'authenticated' ] = true;
            return true;
        }
        elseif( getArrayValue( $_SESSION, 'authenticated' ) ){
            return true;
        }
    }
    return false;
}

function test( $req ){
    include( '../../scripts/generatekpi.php' );
    $startdate = date( 'Y-m-d', time() - (2 * 24 * 3600) );
    echo "<pre>";
    echo generateStats( $startdate );
    echo "</pre>";
}

function mainn( $req, $post ){
    if( getArrayValue( $post, 'logout' ) ){
        session_destroy();
        return loginform( $req );
    }
    echo '
        <form action="" method="POST">
            <input type="hidden" name="logout" value="1" />
            <input type="submit" value="logout" />
        </form>
    ';
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
