<?php
require( 'dbcredentials.inc.php' );

class database{
	protected $pdo = false;
	public function __CONSTRUCT( $credentials ){
		extract( $credentials );
		$this->pdo = new PDO( "mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass );
		return $this->pdo;
	}

    public function truncatetable( $table ){
        $sql = "truncate $table";
        $res = $this->pdo->exec( $sql );
        return 1;
    }
    public function testGetGameinfo(){
        $sql = "SELECT * FROM game";
        return $this->pdo->query( $sql )->fetchAll();
    }
    public function getGameInfo( $gameid ){
        $sql = "SELECT * FROM game WHERE id = $gameid";
        return $this->pdo->query( $sql )->fetch();
    }
	public function fetchAll( $sql, $mode=PDO::FETCH_ASSOC ){
		$res = $this->pdo->query( $sql )->fetchAll( $mode );
		return $res;
	}
    
	public function fetch( $sql ){
		$res = $this->pdo->query( $sql )->fetch( PDO::FETCH_ASSOC );
		return $res;
	}
    
	public function fetchColumn( $sql, $colno=0 ){
        $outlist = array();
		$res = $this->pdo->query( $sql )->fetchAll( PDO::FETCH_COLUMN );
		return array_values( $res );
	}

    public function fetchSingleValueSql( $sql ){
        return array_shift( $this->fetchColumn( $sql ) );
    }

    public function fetchSingleValue( $table, $field, $keyvalue=null, $keyfield='id' ){
        $sql = "SELECT $field FROM $table"; 
        if( !null === $keyvalue ){
            $sql .= " WHERE $keyfield = $keyvalue";
        }
        return array_shift( $this->fetchColumn( $sql ) );
    }

    public function log( $event ){
        $sqlevent = $this->pdo->quote($event);
        $sql = "INSERT INTO log VALUES ( null, $sqlevent )";
        $this->pdo->exec( $sql );
    }
    
    public function insertPush( $device, $msg ){
        $sqldevice = $this->pdo->quote( $device );
        $sqlmsg = $this->pdo->quote( $msg );
        $sql = "INSERT INTO pushqueue VALUES ($sqldevice, $sqlmsg, 0)" ;
        return $this->pdo->exec( $sql );
    }

    public function getPushList(){
        $sql = "UPDATE pushqueue SET sent = 1";
        $this->pdo->exec( $sql );
        $sql = "SELECT device_id, message FROM pushqueue WHERE sent";
        $outlist = $this->fetchAll( $sql );
        $sql = "DELETE FROM pushqueue WHERE sent";
        $this->pdo->exec( $sql );
        return $outlist;
    }

    protected function payGameBonus( $gameid, $amount, $n=1, $lastuser=0 ){
        //pay coins to everyone in a particular game
        $coinsdelta = $amount;
        $sql = "SELECT user_id FROM gamesession WHERE game_id = $gameid";
        $userids = implode( ',',$this->fetchColumn( $sql ) );
        $bonusqlist = array();
        $bonusqlist[] = "UPDATE user SET coins = coins + $coinsdelta WHERE id IN ($userids)";
        if( 2 == $n && $lastuser ){
            //lastuser needs the bonus again
            $bonusqlist[] = "UPDATE user SET coins = coins + $coinsdelta WHERE id = $lastuser";
        }
        foreach( $bonusqlist as $bonusql ){
            $info = $this->pdo->exec($bonusql);
        }

        //stats
        $this->gamestatIncrement( $gameid, 'coinsearned', $coinsdelta );

        return $info;
    }

    public function getCategoryNameForGame( $gameid ){
        $sql = "SELECT c.name
                FROM category c
                JOIN game g ON g.category_id = c.id
                WHERE g.id = $gameid";
        $res = $this->fetchColumn( $sql );
        return array_shift( $res );
    }

    public function addUserIdToGame( $gameid, $userid ){
        //is game still alive?
        //has user been invited?
        $error = false;
        $gamesql = "SELECT g.*, i.friend
                    FROM game g
                    JOIN invitation i ON to_id = $userid AND i.game_id = $gameid
                    WHERE g.id = $gameid AND (g.start + interval 1 day) > NOW()
        ";
        $gameinfo = $this->fetch( $gamesql );
        if( $gameinfo ){
            //is game full?
            $countsql = "SELECT COUNT(*) FROM gamesession WHERE game_id = $gameid";
            $n = array_shift( $this->fetchColumn( $countsql ) );
            $maxno = MAX_PLAYERS_PER_GAME;
            if( $n >= $maxno ){
                $error = "Game $gameid is full";
            }
            else{
                //add user to game
                $sql = "INSERT INTO gamesession (game_id, user_id) VALUES ($gameid, $userid)";
                $this->pdo->exec( $sql );

                //create gamehistory entry
                $sql = "INSERT INTO gamehistory (game_id, user_id, score) VALUES ($gameid, $userid, 0)
                        ON DUPLICATE KEY UPDATE time = NOW()
                ";
                $this->pdo->exec( $sql );

                $n += 1;
                //delete invitation
                $sql = "DELETE FROM invitation WHERE to_id = $userid AND game_id = $gameid";
                $this->pdo->exec( $sql );

                //is a nofplayers bonus payable?
                $diff = MAX_PLAYERS_PER_GAME - $n;
                if( $diff < 2 ){
                    $this->payGameBonus( $gameid, COINS_BONUS_NOFPLAYERS, 2-$diff, $userid );
                }

                //stats
                if( $gameinfo[ 'friend' ] ){
                    $statsfield = 'friendsjoined';
                }
                else{
                    $statsfield = 'strangersjoined';
                }
                $this->gamestatIncrement( $gameid, $statsfield );
                
                //all is well ... return no error
                return false;
            }
        }
        else{
            $error = "user has already joined game or game $gameid is expired or user is not invited or game never existed";
        }
        return $error;
    }

    public function getUserStatusScreen( $userid ){
        $invitation_sql = "SELECT g.id gameid, invitor.displayname, c.id category
                            FROM invitation i
                            JOIN user invitor ON invitor.id = i.from_id
                            JOIN game g ON g.id = i.game_id
                            JOIN category c ON c.id = g.category_id
                            WHERE to_id = $userid
        ";
        $gamesession_sql = "
            SELECT gs.game_id, c.id category,
                    SUM( gh.score ) currentscore, g.target,
                    UNIX_TIMESTAMP(g.start + INTERVAL 1 DAY) - UNIX_TIMESTAMP() secondsremaining
            FROM gamesession gs
            JOIN game g ON gs.game_id = g.id
            JOIN category c ON c.id = g.category_id
            LEFT JOIN gamehistory gh ON gh.game_id = gs.game_id
            WHERE gs.user_id = $userid AND ISNULL( g.finished )
            GROUP BY gs.game_id
        ";
        $gamesessioninfo = $this->fetchAll( $gamesession_sql );
        foreach( $gamesessioninfo as &$gamesession ){
            $totalseconds = $gamesession[ 'secondsremaining' ];
            $exacthours = $totalseconds / 3600;
            $gamesession[ 'hoursremaining' ] = floor( $exacthours );
            $remainder = $totalseconds - (3600 * $gamesession[ 'hoursremaining' ]);
            $exactminutes = $remainder / 60;
            $gamesession[ 'minutesremaining' ] = floor( $exactminutes );
            $remainder = $remainder - (60 * $gamesession[ 'minutesremaining' ]);
            $gamesession[ 'secondsremaining' ] = floor( $remainder );
        }
        $finished_sql = "
            SELECT g.id game_id, c.id category, UNIX_TIMESTAMP(g.finished) - UNIX_TIMESTAMP(g.start) gamelengthSeconds,
                    SUM( ghteam.score ) score, g.target, ghpersonal.score personalscore
            FROM game g
            JOIN gamehistory ghpersonal ON ghpersonal.game_id = g.id AND ghpersonal.user_id = $userid
            JOIN gamehistory ghteam ON ghteam.game_id = g.id 
            JOIN category c ON c.id = g.category_id
            WHERE g.finished 
            GROUP BY g.id
            ORDER BY g.finished DESC
            LIMIT " . HISTORICAL_GAME_LIMIT;
        return array(
            $this->fetchAll( $invitation_sql ),
            $gamesessioninfo,
            $this->fetchAll( $finished_sql )
        );
    }

    public function countGamesCreatedSince( $userid, $timelimitHours ){
        $sql = "SELECT COUNT(id) FROM game WHERE creator_id = $userid AND (start + INTERVAL $timelimitHours HOUR) > NOW()";
        return $this->fetchSingleValueSql( $sql );
    }

    public function usernameExists( $username ){
        return $this->valueExists( 'user', 'username', $username );
    }

	public function valueExists( $table, $field, $value ){
		$value = enquote( $value );
		$sql = "SELECT * FROM $table WHERE $field = $value";
		return count( $this->pdo->query( $sql )->fetchAll() );
	}
    public function getDeviceIdForUser( $userid ){
        $sql = "SELECT device_id FROM user_device WHERE user_id = $userid LIMIT 1";
        return $this->pdo->query( $sql )->fetchColumn();
    }
    public function getDeviceIdForUsername( $username, $deviceid=false ){
        $username = $this->pdo->quote( $username );
        $whereandlist = array(
            "u.username = $username"
        );
        if( $deviceid ){
            $deviceid = $this->pdo->quote( $deviceid );
            //use this to confirm existing device
            $whereandlist[] = "ud.device_id = $deviceid";
        }
        $sql = "SELECT ud.device_id 
                FROM user_device ud 
                JOIN user u ON u.id = ud.user_id
                WHERE " . implode( ' AND ', $whereandlist ) . "
                LIMIT 1";
        return $this->pdo->query( $sql )->fetchColumn();
    }
    
    public function getDeviceIdForUserId( $userid ){
        $info = $this->fetchColumn( "SELECT device_id FROM user_device WHERE user_id = $userid LIMIT 1" );
        if( $info ){
            return array_shift( $info );
        }
        return false;
    }

    public function deleteInvitation( $gameid, $username ){
        $userid = $this->getUserIdFromUserName( $username );
        $sql = "DELETE FROM invitation WHERE to_id = $userid AND game_id = $gameid";
        return $this->pdo->exec( $sql );
    }

    function gameHasReachedTarget( $gameid ){
        $sql = "SELECT g.target, SUM( gh.score ) totalscore
                FROM game g
                JOIN gamehistory gh ON gh.game_id = g.id
                WHERE g.id = $gameid
                GROUP BY g.id
        ";
        $gameinfo = $this->fetch( $sql );
        if( $gameinfo ){
	        if( $gameinfo[ 'totalscore' ] < $gameinfo[ 'target' ] ){
	            return false;
	        }
	        return true;
        }
        return false;
    }

    public function getGameStatus( $gameid ){
        $sql = "SELECT u.username, u.displayname, gh.score
                FROM gamehistory gh
                JOIN user u ON u.id = gh.user_id
                WHERE game_id = $gameid ORDER BY gh.score DESC
        ";
        $gameinfo = $this->fetchAll( $sql );
        $outlist = array();
        foreach( $gameinfo as &$row ){
            $user = ( trim($row['displayname']) ) ? $row[ 'displayname' ] : $row[ 'username' ];
            $outlist[] = array( 'user' => $user, 'score' => $row[ 'score' ] );
        }
        return $outlist;
    }

    public function finishGame( $gameid ){
        //find winners
        $sql = "SELECT user_id FROM gamehistory WHERE game_id = $gameid ORDER BY score DESC LIMIT 3";
        $winnerlist = $this->fetchColumn( $sql );
        //award coins
        $prizelist = array( COINS_BONUS_FIRST, COINS_BONUS_SECOND, COINS_BONUS_THIRD );
        $errorlist = array();
        foreach( $winnerlist as $userid ){
            $coins = array_shift( $prizelist );
            $sql = "UPDATE user SET coins";
            if( !$this->awardCoinsToUser( $userid, $coins ) ){
                $errorlist[] = "failed to award coins to userid $userid";
            }
        }
        $this->pdo->exec( "DELETE FROM invitation WHERE game_id = $gameid" );
        $this->pdo->exec( "DELETE FROM gamesession WHERE game_id = $gameid" );
        $this->pdo->exec( "UPDATE game SET finished = NOW() WHERE id = $gameid" );
        return $errorlist;
    }
    
    public function awardCoinsToUser( $userid, $coins ){
        $userinfo = $this->fetch( "SELECT * FROM user WHERE id = $userid" );
        $currentCoins = getArrayValue( $userinfo, 'coins' );
        $newCoins = $currentCoins + $coins;
        $sql = "UPDATE user SET coins = $newCoins WHERE id = $userid";
        return( $this->pdo->exec( $sql ) );
    }

    protected function prepareTeammateSql( $gameid, $whereandlist, $hourslimit, $limit, $excludelist=array() ){
        $fieldlist = array( 
                        'gh.user_id',
                        "MAX( gh.`time` ) latestActive"
        );
        if( $excludelist ){
            $whereandlist[] = "gh.user_id NOT IN (" . implode( ',', $excludelist ) . ")";
        }
        $whereclause = '';
        if( $whereandlist ){
            $whereclause = "WHERE " . implode( ' AND ', $whereandlist );
        }
        return "SELECT " . implode( ', ', $fieldlist ) . "
                FROM game g
                JOIN gamehistory gh ON gh.game_id = g.id
                JOIN gamesession gs ON gs.game_id = g.id AND gs.user_id = gh.user_id AND gs.game_id != $gameid
                $whereclause
                GROUP BY gh.user_id
                HAVING latestActive + INTERVAL $hourslimit HOUR > NOW()
                ORDER BY latestActive DESC
                LIMIT $limit
        ";
    }
    public function chooseTeammates( $gameid, $categoryid, $level, $fromid=false, $debug=false ){
        //find recently active games of same category and level
        $max = MAX_PLAYERS_PER_GAME;
        $limit = $max;
        $idlist = array();
        $foundusers = 0;

        $catandlevel = array( "g.level = $level", "g.category_id = $categoryid");
        $catonly = array( "g.category_id = $categoryid");

        $criterialist = array(
            //array( whereandlist, hourslimit )
            array( $catandlevel, 1 ),
            array( $catonly, 1 ),
            array( $catandlevel, 6 ),
            array( $catonly, 6 ),
            array( array(), 6 ),
            array( $catandlevel, 12 ),
            array( $catonly, 12 )
        );
        foreach( $criterialist as $criteria ){
            $limit = $max - count( $idlist );
            $whereandlist = $criteria[ 0 ];
            $hourslimit = $criteria[ 1 ];
            if( $fromid ){
                $excludelist = array_merge( array( $fromid ), $idlist );
            }
            else{
                $excludelist = $idlist;
            }
            $sql = $this->prepareTeammateSql( $gameid, $whereandlist, $hourslimit, $limit, $excludelist );
            if( $debug ){
                echo $sql; echo '<hr />';
            }
            if( $newids = $this->fetchColumn( $sql ) ){
                $idlist = array_merge( $idlist, $newids );
            }
            if( $debug ){
                var_dump($idlist); echo '<hr />';
            }
            if( count( $idlist ) >= $max ){
                return $idlist;
            }
        } 
        if( $diff = $max - count( $idlist ) ){
            if( 0 < $diff ){
                //just get $diff more players from gamesession
                if( $fromid ){
                    $excludelist = array_merge( array( $fromid ), $idlist );
                }
                else{
                    $excludelist = $idlist;
                }
                $sql = "SELECT distinct user_id FROM gamesession ";
                if( count( $excludelist ) ){
                    $sql .= "WHERE user_id NOT IN (" . implode( ',', $excludelist ) . ") ";
                }
                $sql .= "ORDER BY game_id DESC LIMIT $diff";
                if( $debug ){
                    echo $sql; echo '<hr />';
                    var_dump($idlist); echo '<hr />';
                }
                if( $newids = $this->fetchColumn( $sql ) ){
                    $idlist = array_merge( $idlist, $newids );
                }
            }
        }
        return $idlist;
    }

    public function getDisplayNameFromUsername( $username ){
        $sql = "SELECT id,username,displayname FROM user WHERE username = " . $this->pdo->quote( $username );
        $userinfo = $this->fetch( $sql );
        if( $displayname = trim( $userinfo[ 'displayname' ] ) ){
            return $displayname;
        }
        return $userinfo[ 'username' ];
    }

    public function insertInvitation( $gameid, $from, $to, $friend ){
        $fromid = $this->getUserIdFromUserName( $from );
        if( $friend ){
            $toid = $this->getUserIdFromUserName( $to );
        }
        else{
            //if friend not chosen by name, then $to is user.id not user.username
            $toid = $to;
        }
        $sql = "INSERT INTO invitation (game_id, from_id, to_id, friend ) VALUES ($gameid, $fromid, $toid, $friend)";
        if( $status = $this->pdo->exec( $sql ) ){
            //stats
            if( $friend ){
                $statsfield = 'friendsinvited';
            }
            else{
                $statsfield = 'strangersinvited';
            }
            $this->gamestatIncrement( $gameid, $statsfield );

            return $status;
        }
        else{
            return false;
        }
    }
    public function insertGameHistory( $params ){
        $valueparams = array(
            'game_id' => $params[ 'gameid' ],
            'user_id' => $this->getUserIdFromUserName( $params[ 'user' ] ),
            'answers' => $params[ 'answers' ],
            'score' => $params[ 'score' ]
        );
		list( $fields, $values ) = $this->assembleInsertFieldsAndValues( $valueparams );

        $coinsdelta = $valueparams[ 'score' ];
        //has this user already submitted to this game?
        $alreadyinfo = $this->fetch( "SELECT * FROM gamehistory WHERE user_id = " . $valueparams[ 'user_id' ] . " AND game_id = " . $valueparams[ 'game_id' ] );
        $userinfo = $this->fetch( "SELECT * FROM user WHERE id = " . $valueparams[ 'user_id' ] );
        if( $alreadyinfo ){
            $coinsdelta -= $alreadyinfo[ 'score' ];
        }
        $newcoins = $userinfo[ 'coins' ] + $coinsdelta;

        $sql = "INSERT INTO gamehistory ($fields) VALUES ($values)
                ON DUPLICATE KEY UPDATE corrections = corrections+1, time=NOW(), answers='" . $valueparams[ 'answers' ] . "', score=" . $valueparams[ 'score' ] ;
        $info = $this->pdo->exec( $sql );

        $usersql = "UPDATE user SET coins = $newcoins WHERE id = " . $valueparams[ 'user_id' ];
        $this->pdo->exec( $usersql );
        $this->updateLastActivity( $valueparams[ 'user_id' ] );
        return 1;
    }

    public function getUserNameFromUserId( $id ){
        $sql = "SELECT username FROM user WHERE id = $id";
        return $this->pdo->query( $sql )->fetchColumn();
    }

    public function getUserIdFromUserName( $username ){
        $sql = "SELECT id FROM user WHERE username = " . $this->pdo->quote( $username );
        return $this->pdo->query( $sql )->fetchColumn();
    }

    public function startGame( $params ){
        $valueparams = array(
            'start' => 'NOW()',
            'level' => $params['level'],
            'category_id' => $params['category'],
            'questionids' => $params[ 'quids' ],
            'target' => $params[ 'target' ],
            'open' => null,
            'creator_id' => $params[ 'creatorid' ]
        );
		list( $fields, $values ) = $this->assembleInsertFieldsAndValues( $valueparams );
        $sql = "INSERT INTO game ( $fields ) VALUES ( $values )";
        $this->pdo->exec( $sql );
        $gameid = $this->pdo->lastInsertId();
        if( $gameid ){
            $userid = $params[ 'creator_id' ];
            $sesssql = "INSERT INTO gamesession VALUES ( $gameid, $userid )";
            $this->pdo->exec( $sesssql ); 
            $this->updateLastActivity( $userid );
            return $gameid;
        }
        else{
            $this->handleError();
        }
    }

    public function updateLastActivity( $userid ){
        $sql = "UPDATE user SET lastActivity = NOW() WHERE id = $userid";
        return $this->pdo->exec( $sql );
    }

	public function insertUser( $params ){
		$allowedFieldList = array( 'username', 'displayname' );
		$valueparams = array(
			'username' => $params[ 'username' ]
		);
		if( $displayname = $params[ 'displayname' ] ){
			$valueparams[ 'displayname' ] = $displayname;
		}
		list( $fields, $values ) = $this->assembleInsertFieldsAndValues( $valueparams );
		$sql = "INSERT INTO user ( $fields ) VALUES ( $values )";
		$this->pdo->exec( $sql );
		$newuserid = $this->pdo->lastInsertId();
        if( $newuserid ){
			//add the device id
			$newdevice = $this->insertNewDeviceidForUser( $newuserid, $params[ 'token' ] );
			if( $newdevice ){
				return $newuserid;
			}
			else{
				$this->handleError();
			}
		}
        elseif( $userid = $this->getUserIdFromUserName( $valueparams[ 'username' ] ) ){
            //register new device for this user           
			$newdevice = $this->insertNewDeviceidForUser( $userid, $params[ 'token' ] );
            if( $newdevice ){
                return $userid;
            }
        }
		else{
			$this->handleError();
		}
	}
	protected function handleError(){
		var_dump( $this->pdo->errorInfo() );exit;
	}
    public function getError(){
        $info = $this->pdo->errorInfo();
        return $info[2];
    }
	protected function insertNewDeviceidForUser( $userid, $token ){
		$sql = "INSERT INTO user_device VALUES ( $userid, " . enquote( $token ) . " )";
		return $this->pdo->exec( $sql );
	}
	protected function assembleInsertFieldsAndValues( $params ){
		$fieldlist = array();
		$valuelist = array();
        $passthroughlist = array( "NOW()" );
		foreach( $params as $field=>$value ){
            if( null === $value ){}
            else{
				$fieldlist[] = $field; 
	            if( in_array( $value, $passthroughlist ) ){
	                $valuelist[] = $value;
	            }
	            else{
				    $valuelist[] = enquote( $value );
	            }
            }
		}
		return array(
			implode( ',', $fieldlist ),
			implode( ',', $valuelist )
		);
	}

    public function gamestatIncrement( $gameid, $field, $n=1 ){
        $sql = "
            INSERT INTO gamestat (game_id, $field )
            VALUES ($gameid, $n)
            ON DUPLICATE KEY UPDATE $field = $field + $n
        ";
        return $this->pdo->exec( $sql );
    }

    public function buyJewels( $username, $jewels ){
        $jewelpricelist = array(
            1 => 1000,
            2 => 1800,
            3 => 4000
        );
        $n = 0;
        if( $jewels > 2 ){
            $n = 3;
        }
        elseif( in_array( $jewels, array( 1, 2 ) ) ){
            $n = $jewels;
        }
        if( $n ){
            $cost = $jewelpricelist[ $n ];
            $userinfo = $this->fetch( "SELECT * FROM user WHERE username = " . $this->pdo->quote( $username ) );
            $currentjewels = $userinfo[ 'jewels' ];
            $currentcoins = $userinfo[ 'coins' ];
            $currentjewelsbought = $userinfo[ 'jewelsbought' ];
            if( $cost > $currentcoins ){
                $s = ( 1 == 's' ) ? '' : 's';
                return "user can't afford $n jewel$s";
            }
            else{
                $newcoins = $currentcoins - $cost;
                $newjewels = $currentjewels + $n;
                $newjewelsbought = $currentjewelsbought + $n;
                $sql = "UPDATE user SET jewels = $newjewels, jewelsbought = $newjewelsbought, lastactivity=NOW(), coins = $newcoins WHERE id = " . $userinfo[ 'id' ];
                if( $this->pdo->exec( $sql ) ){
                    //all well
                    return false;
                }
                else{
                    return "no jewels bought";
                }
            }
        }
        else{
            return 'no jewels requested';
        }
    }

    public function updateUserByUsername( $params ){
        $username = $params[ 'username' ];
        $setlist = array();
        if( $coins = $params[ 'coins' ] ){
            $setlist[] = "coins = $coins";
        }
        if( $jewels = $params[ 'jewels' ] ){
            $setlist[] = "jewels = $jewels";
        }
        if( count($setlist) ){
            $sql = "UPDATE user SET " . implode( ',', $setlist ) . " WHERE username = " . $this->pdo->quote( $params[ 'username' ] );
            if( $this->pdo->exec( $sql ) ){
                return false;
            }
            else{
                //this could be legitimate if the submitted values are the same as the existing values
                return false;
            }
        }
        else{
            //nothing to be done
            return false;
        }
    }

}
