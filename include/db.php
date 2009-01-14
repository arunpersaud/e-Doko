<?php
/* make sure that we are not called from outside the scripts,
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

/*
 * open database
 */

function DB_open()
{
  global $DB,$DB_user,$DB_host,$DB_database,$DB_password;
  $DB = @mysql_connect($DB_host,$DB_user, $DB_password);
  if ( $DB )
    {
      mysql_select_db($DB_database) or die('Could not select database');
    }
  else
    return -1;

  return 0;
}

function DB_close()
{
  global $DB;
  mysql_close($DB);
  return;
}

function DB_quote_smart($value)
{
    /* Stripslashes */
    if (get_magic_quotes_gpc()) {
        $value = stripslashes($value);
    }
    /* Quote if not a number or a numeric string */
    if (!is_numeric($value)) {
        $value = "'" . mysql_real_escape_string($value) . "'";
    }
    return $value;
}

function DB_test()
{
  $result = DB_query("SELECT * FROM User");
  while($r = DB_fetch_array($result))
    {
      foreach($r as $thing)
	echo "  $thing ";
      echo "<br />\n";
    }
  return;
}

/* use Mysql in the background */
function DB_query($query)
{
  return mysql_query($query);
}

function DB_fetch_array($result)
{
  return mysql_fetch_array($result,MYSQL_NUM);
}

function DB_insert_id()
{
  return mysql_insert_id();
}

function DB_num_rows($result)
{
  return mysql_num_rows($result);
}
/* end Mysql functions */

function DB_query_array($query)
{
  $result = DB_query($query);
  $return = DB_fetch_array($result);

  return $return;
}

function DB_query_array_all($query)
{
  $result = array();

  $queryresult  = DB_query($query);
  while($row = DB_fetch_array($queryresult))
    $result[] = $row;

  return $result;
}

function DB_get_passwd_by_name($name)
{
  $r = DB_query_array("SELECT password FROM User WHERE fullname=".DB_quote_smart($name)."");

  if($r)
    return $r[0];
  else
    return "";
}

function DB_get_passwd_by_userid($id)
{
  $r = DB_query_array("SELECT password FROM User WHERE id=".DB_quote_smart($id)."");

  if($r)
    return $r[0];
  else
    return "";
}

function DB_check_recovery_passwords($password,$email)
{
  $r = DB_query_array("SELECT User.id FROM User".
		      " LEFT JOIN Recovery ON User.id=Recovery.user_id".
		      " WHERE email=".DB_quote_smart($email).
		      " AND Recovery.password=".DB_quote_smart($password).
		      " AND DATE_SUB(CURDATE(),INTERVAL 1 DAY) <= Recovery.create_date");
  if($r)
    return 1;
  else
    return 0;
}

function DB_get_handid($type,$var1='',$var2='')
{
  switch($type)
    {
    case 'hash':
      $r = DB_query_array("SELECT id FROM Hand WHERE hash=".DB_quote_smart($var1));
      break;
    case 'gameid-position':
      $r = DB_query_array("SELECT id FROM Hand WHERE game_id=".
			  DB_quote_smart($var1)." AND position=".
			  DB_quote_smart($var2));
      break;
    case 'gameid-userid':
      $r = DB_query_array("SELECT id FROM Hand WHERE game_id=".
			  DB_quote_smart($var1)." AND user_id=".
			  DB_quote_smart($var2));
      break;
    }

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_get_pos_by_hash($hash)
{
  $r= DB_query_array("SELECT position FROM Hand WHERE hash=".DB_quote_smart($hash));

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_get_status_by_hash($hash)
{
  $r= DB_query_array("SELECT status FROM Hand WHERE hash=".DB_quote_smart($hash));

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_set_game_status_by_gameid($id,$status)
{
  DB_query("UPDATE Game SET status='".$status."' WHERE id=".DB_quote_smart($id));
  return;
}

function DB_set_sickness_by_gameid($id,$status)
{
  DB_query("UPDATE Game SET sickness='".$status."' WHERE id=".DB_quote_smart($id));
  return;
}
function DB_get_sickness_by_gameid($id)
{
  $r = DB_query_array("SELECT sickness FROM Game WHERE id=".DB_quote_smart($id));

  if($r)
    return $r[0];
  else
    return NULL;
}

function DB_get_game_status_by_gameid($id)
{
  $r = DB_query_array("SELECT status FROM Game WHERE id=".DB_quote_smart($id));

  if($r)
    return $r[0];
  else
    return NULL;
}

function DB_set_hand_status_by_hash($hash,$status)
{
  DB_query("UPDATE Hand SET status='".$status."' WHERE hash=".DB_quote_smart($hash));
  return;
}

function DB_get_hand_status_by_userid_and_gameid($uid,$gid)
{
  $r = DB_query_array("SELECT status FROM Hand WHERE user_id=".DB_quote_smart($uid).
		      " AND game_id=".DB_quote_smart($gid));
  if($r)
    return $r[0];
  else
    return 0;
}

function DB_get_sickness_by_userid_and_gameid($uid,$gid)
{
  $r = DB_query_array("SELECT sickness FROM Hand WHERE user_id=".DB_quote_smart($uid).
		      " AND game_id=".DB_quote_smart($gid));
  if($r)
    return $r[0];
  else
    return 0;
}

function DB_get_sickness_by_pos_and_gameid($pos,$gid)
{
  $r = DB_query_array("SELECT sickness FROM Hand WHERE position=".DB_quote_smart($pos).
		      " AND game_id=".DB_quote_smart($gid));
  if($r)
    return $r[0];
  else
    return 0;
}

function DB_get_gameid_by_hash($hash)
{
  $r = DB_query_array("SELECT game_id FROM Hand WHERE hash=".DB_quote_smart($hash));

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_cancel_game($hash)
{
  $gameid = DB_get_gameid_by_hash($hash);

  if(!$gameid)
    return;

  /* get the IDs of all players */
  $result = DB_query("SELECT id FROM Hand WHERE game_id=".DB_quote_smart($gameid));
  while($r = DB_fetch_array($result))
    {
      $id = $r[0];

      $tmp = DB_query_array("SELECT id  FROM Hand_Card WHERE hand_id=".DB_quote_smart($id));
      DB_query("DELETE FROM Play WHERE hand_card_id=".DB_quote_smart($tmp[0]));

      DB_query("DELETE FROM Hand_Card WHERE hand_id=".DB_quote_smart($id));
      DB_query("DELETE FROM Hand WHERE id=".DB_quote_smart($id));
    }

  /* delete game */
  DB_query("DELETE FROM User_Game_Prefs WHERE game_id=".DB_quote_smart($gameid));
  DB_query("DELETE FROM Trick WHERE game_id=".DB_quote_smart($gameid));
  DB_query("DELETE FROM Game WHERE id=".DB_quote_smart($gameid));

  return;
}

function DB_get_hand($me)
{
  $cards = array();

  $handid = DB_get_handid('hash',$me);

  $result = DB_query("SELECT card_id FROM Hand_Card WHERE hand_id=".DB_quote_smart($handid)." and played='false' ");
  while($r = DB_fetch_array($result))
    $cards[]=$r[0];

  return $cards;
}

function DB_get_all_hand($me)
{
  $cards = array();

  $handid = DB_get_handid('hash',$me);

  $result = DB_query("SELECT card_id FROM Hand_Card WHERE hand_id=".DB_quote_smart($handid));
  while($r = DB_fetch_array($result))
    $cards[]=$r[0];

  return $cards;
}

function DB_get_cards_by_trick($id)
{
  $cards = array();
  $i     = 1;

  $result = DB_query("SELECT card_id,position FROM Play LEFT JOIN Hand_Card ON Hand_Card.id=Play.hand_card_id ".
		     "LEFT JOIN Hand ON Hand.id=Hand_Card.hand_id ".
		     "WHERE trick_id=".
		     DB_quote_smart($id)." ORDER BY sequence ASC");
  while($r = DB_fetch_array($result))
    {
      $cards[$i]=array("card"=>$r[0],"pos"=>$r[1]);
      $i++;
    }

  return $cards;
}


function DB_set_solo_by_hash($hash,$solo)
{
  DB_query("UPDATE Hand SET solo=".DB_quote_smart($solo)." WHERE hash=".DB_quote_smart($hash));
  return;
}

function DB_set_solo_by_gameid($id,$solo)
{
  DB_query("UPDATE Game SET solo=".DB_quote_smart($solo)." WHERE id=".DB_quote_smart($id));
  return;
}

function DB_set_sickness_by_hash($hash,$sickness)
{
  DB_query("UPDATE Hand SET sickness=".DB_quote_smart($sickness)." WHERE hash=".DB_quote_smart($hash));
  return;
}

function DB_get_current_trickid($gameid)
{
  $trickid  = NULL;
  $sequence = NULL;
  $number   = 0;

  $result = DB_query("SELECT Trick.id,MAX(Play.sequence) FROM Play ".
		     "LEFT JOIN Trick ON Play.trick_id=Trick.id ".
		     "WHERE Trick.game_id=".DB_quote_smart($gameid)." ".
		     "GROUP BY Trick.id");
  while( $r = DB_fetch_array($result) )
    {
      $trickid  = $r[0];
      $sequence = $r[1];
      $number++;
    };

  if(!$sequence || $sequence==4)
    {
      DB_query("INSERT INTO Trick VALUES (NULL,NULL,NULL, ".DB_quote_smart($gameid).",NULL)");
      $trickid  = DB_insert_id();
      $sequence = 1;
      $number++;
    }
  else
    {
      $sequence++;
    }

  return array($trickid,$sequence,$number);
}

function DB_get_max_trickid($gameid)
{
  $r = DB_query_array("SELECT MAX(id) FROM Trick WHERE game_id=".DB_quote_smart($gameid));

  return ($r?$r[0]:NULL);
}

function DB_play_card($trickid,$handcardid,$sequence)
{
  DB_query("INSERT INTO Play VALUES(NULL,NULL,NULL,".DB_quote_smart($trickid).
	   ",".DB_quote_smart($handcardid).",".DB_quote_smart($sequence).")");

  $playid = DB_insert_id();
  return $playid;
}

function DB_get_all_names_by_gameid($id)
{
  $names = array();

  $result = DB_query("SELECT fullname FROM Hand LEFT JOIN User ON Hand.user_id=User.id WHERE game_id=".
		     DB_quote_smart($id)." ORDER BY position ASC");
  while($r = DB_fetch_array($result))
    $names[] = $r[0];

  return $names;
}

function DB_get_all_userid_by_gameid($id)
{
  $names = array();

  $result = DB_query("SELECT user_id FROM Hand WHERE game_id=".
		     DB_quote_smart($id)." ORDER BY position ");
  while($r = DB_fetch_array($result))
    $names[] = $r[0];

  return $names;
}

function DB_get_hash_from_game_and_pos($id,$pos)
{
  $r = DB_query_array("SELECT hash FROM Hand WHERE game_id=".DB_quote_smart($id)." and position=".DB_quote_smart($pos));

  if($r)
    return $r[0];
  else
    return "";
}

function DB_get_hash_from_gameid_and_userid($id,$user)
{
  $r = DB_query_array("SELECT hash FROM Hand WHERE game_id=".DB_quote_smart($id)." and user_id=".DB_quote_smart($user));

  if($r)
    return $r[0];
  else
    return "";
}

function DB_get_all_names()
{
  $names  = array();

  $result = DB_query("SELECT fullname FROM User");
  while($r = DB_fetch_array($result))
    $names[] = $r[0];

  return $names;
}

function DB_get_names_of_last_logins($N)
{
  $names  = array();

  $result = DB_query("SELECT fullname FROM User ORDER BY last_login DESC LIMIT $N");
  while($r = DB_fetch_array($result))
    $names[] = $r[0];

  return $names;
}

function DB_get_names_of_new_logins($N)
{
  $names  = array();

  $result = DB_query("SELECT fullname FROM User ORDER BY create_date DESC, id DESC LIMIT $N");
  while($r = DB_fetch_array($result))
    $names[] = $r[0];

  return $names;
}

function DB_update_game_timestamp($gameid)
{
  DB_query("UPDATE Game SET mod_date = CURRENT_TIMESTAMP WHERE id=".DB_quote_smart($gameid));
  return;
}


function DB_update_user_timestamp($userid)
{
  DB_query("UPDATE User SET last_login = CURRENT_TIMESTAMP WHERE id=".DB_quote_smart($userid));
  return;
}

function DB_get_user_timestamp($userid)
{
  $r = DB_query_array("SELECT last_login FROM User WHERE id=".DB_quote_smart($userid));

  if($r)
    return $r[0];
  else
    return NULL;
}
function DB_get_user_timezone($userid)
{
  $r = DB_query_array("SELECT timezone FROM User WHERE id=".DB_quote_smart($userid));

  if($r)
    return $r[0];
  else
    return "Europe/London";
}

function DB_insert_comment($comment,$playid,$userid)
{
  DB_query("INSERT INTO Comment VALUES (NULL,NULL,NULL,$userid,$playid, ".DB_quote_smart($comment).")");

  return;
}

function DB_insert_note($comment,$gameid,$userid)
{
  DB_query("INSERT INTO Notes VALUES (NULL,NULL,NULL,$userid,$gameid, ".DB_quote_smart($comment).")");

  return;
}

function DB_get_notes_by_userid_and_gameid($userid,$gameid)
{
  $notes = array();

  $result = DB_query("SELECT comment FROM Notes WHERE user_id=".DB_quote_smart($userid) .
		     " AND game_id=".DB_quote_smart($gameid));

  while($r = DB_fetch_array($result))
    $notes[] = $r[0];

  return $notes;
}


function DB_get_gametype_by_gameid($id)
{
  $r = DB_query_array("SELECT type FROM Game WHERE id=".DB_quote_smart($id));

  if($r)
    return $r[0]."";
  else
    return "";
}

function DB_set_gametype_by_gameid($id,$p)
{
  DB_query("UPDATE Game SET type='".$p."' WHERE id=".DB_quote_smart($id));
  return;
}

function DB_get_solo_by_gameid($id)
{
  $r = DB_query_array("SELECT solo FROM Game WHERE id=".DB_quote_smart($id));

  if($r)
    return $r[0]."";
  else
    return "";
}


function DB_get_startplayer_by_gameid($id)
{
  $r = DB_query_array("SELECT startplayer FROM Game WHERE id=".DB_quote_smart($id));

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_set_startplayer_by_gameid($id,$p)
{
  DB_query("UPDATE Game SET startplayer='".$p."' WHERE id=".DB_quote_smart($id));
  return;
}

function DB_get_player_by_gameid($id)
{
  $r = DB_query_array("SELECT player FROM Game WHERE id=".DB_quote_smart($id));

  if($r)
    return $r[0];
  else
    return 0;
}
function DB_set_player_by_gameid($id,$p)
{
  DB_query("UPDATE Game SET player='".DB_quote_smart($p)."' WHERE id=".DB_quote_smart($id));
  return;
}



function DB_get_ruleset_by_gameid($id)
{
  $r = DB_query_array("SELECT ruleset FROM Game WHERE id=".DB_quote_smart($id));

  if($r)
    return $r[0];
  else
    return NULL;
}

function DB_get_session_by_gameid($id)
{
  $r = DB_query_array("SELECT session FROM Game WHERE id=".DB_quote_smart($id));

  if($r)
    return $r[0];
  else
    return NULL;
}

function DB_get_max_session()
{
  $r = DB_query_array("SELECT MAX(session) FROM Game");

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_get_hashes_by_session($session,$user)
{
  $r = array();

  $result = DB_query("SELECT Hand.hash FROM Hand".
		     " LEFT JOIN Game ON Game.id=Hand.game_id ".
		     " WHERE Game.session=".DB_quote_smart($session).
		     " AND Hand.user_id=".DB_quote_smart($user).
		     " ORDER BY Game.create_date ASC");
  while($t = DB_fetch_array($result))
    $r[] = $t[0];

  return $r;
}

function DB_get_ruleset($dullen,$schweinchen,$call)
{
  $r = array();

  $result = DB_query("SELECT id FROM Rulesets WHERE".
		     " dullen=".DB_quote_smart($dullen)." AND ".
		     " Rulesets.call=".DB_quote_smart($call)." AND ".
		     " schweinchen=".DB_quote_smart($schweinchen));
  if($result)
    $r    = DB_fetch_array($result);

  if($r)
    return $r[0]; /* found ruleset */
  else
    {
      /* create new one */
      $result = DB_query("INSERT INTO Rulesets VALUES (NULL, NULL, ".
			 DB_quote_smart($dullen).",".
			 DB_quote_smart($schweinchen).",".
			 DB_quote_smart($call).
			 ", NULL)");
      if($result)
	return DB_insert_id();
    };

  return -1; /* something went wrong */
}

function DB_get_party_by_hash($hash)
{
  $r = DB_query_array("SELECT party FROM Hand WHERE hash=".DB_quote_smart($hash));

  if($r)
    return $r[0];
  else
    return NULL;
}

function DB_get_party_by_gameid_and_userid($gameid,$userid)
{
  $r = DB_query_array("SELECT party FROM Hand".
		      " WHERE game_id=".DB_quote_smart($gameid).
		      "  AND user_id=".DB_quote_smart($userid));
  if($r)
    return $r[0];
  else
    return NULL;
}

function DB_set_party_by_hash($hash,$party)
{
  DB_query("UPDATE Hand SET party=".DB_quote_smart($party)." WHERE hash=".DB_quote_smart($hash));
  return;
}

function DB_get_PREF($myid)
{
  /* Cardset */
  $r = DB_query_array("SELECT value from User_Prefs".
		      " WHERE user_id='$myid' AND pref_key='cardset'" );
  if($r)
    {
      /* licence only valid until then */
      if($r[0]=="altenburg" && (time()-strtotime( "2009-12-31 23:59:59")<0) )
	$PREF["cardset"]="altenburg";
      else
	$PREF["cardset"]="english";
    }
  else
    $PREF["cardset"]="english";

  /* Email */
  $r = DB_query_array("SELECT value FROM User_Prefs".
		      " WHERE user_id='$myid' AND pref_key='email'" );
  if($r)
    {
      if($r[0]=="emailaddict")
	$PREF["email"]="emailaddict";
      else
	$PREF["email"]="emailnonaddict";
    }
  else
    $PREF["email"]="emailnonaddict";

  /* Autosetup */
  $r = DB_query_array("SELECT value FROM User_Prefs".
		      " WHERE user_id='$myid' AND pref_key='autosetup'" );
  if($r)
    {
      if($r[0]=='yes')
	$PREF['autosetup']='yes';
      else
	$PREF['autosetup']='no';
    }
  else
    $PREF['autosetup']='no';

  /* Sorting */
  $r = DB_query_array("SELECT value FROM User_Prefs".
		      " WHERE user_id='$myid' AND pref_key='sorting'" );
  if($r)
    $PREF['sorting'] = $r[0];
  else
    $PREF['sorting']='high-low';

  return $PREF;
}

function DB_get_RULES($gameid)
{
  $r = DB_query_array("SELECT * FROM Rulesets".
		      " LEFT JOIN Game ON Game.ruleset=Rulesets.id ".
		      " WHERE Game.id='$gameid'" );

  $RULES["dullen"]      = $r[2];
  $RULES["schweinchen"] = $r[3];
  $RULES["call"]        = $r[4];

  return $RULES;
}

function DB_get_email_pref_by_hash($hash)
{
  $r = DB_query_array("SELECT value FROM Hand".
		      " LEFT JOIN User_Prefs ON Hand.user_id=User_Prefs.user_id".
		      " WHERE hash='$hash' AND pref_key='email'" );
  if($r)
    {
      if($r[0]=="emailaddict")
	return "emailaddict";
      else
	return "emailnonaddict";
    }
  else
    return "emailnonaddict";
}

function DB_get_email_pref_by_uid($uid)
{
  $r = DB_query_array("SELECT value FROM User_Prefs ".
		      " WHERE user_id='$uid' AND pref_key='email'" );
  if($r)
    {
      if($r[0]=="emailaddict")
	return "emailaddict";
      else
	return "emailnonaddict";
    }
  else
    return "emailnonaddict";
}

function DB_get_unused_randomnumbers($userstr)
{
  /* optimized version of this query using temporary tables (perhaps we should use a procedure here?).
     First we create a copy of the Game table using just the gameid and the cards.
     Then in a second round we delete all the gameids of games where our players are in.
     At the end we return only the first entry in the temporary table.
  */
  DB_query("DROP   TEMPORARY TABLE IF EXISTS gametmp;");
  DB_query("CREATE TEMPORARY TABLE gametmp SELECT id,randomnumbers FROM Game;");
  DB_query("DELETE FROM gametmp WHERE id IN (SELECT game_id FROM Hand WHERE user_id IN (".$userstr."));");

  $r = DB_query_array("SELECT randomnumbers FROM gametmp LIMIT 1;");
  DB_query("DROP   TEMPORARY TABLE IF EXISTS gametmp;");

  if($r)
    return $r[0];
  else
    return "";
}

function DB_get_number_of_passwords_recovery($user)
{
  $r = DB_query_array("SELECT COUNT(*) FROM Recovery ".
		      "  WHERE user_id=$user ".
		      "  AND DATE_SUB(CURDATE(),INTERVAL 1 DAY) <= create_date".
		      "  GROUP BY user_id " );
  if($r)
    return $r[0];
  else
    return 0;
}

function DB_set_recovery_password($user,$newpw)
{
  DB_query("INSERT INTO Recovery VALUES(NULL,".DB_quote_smart($user).
	   ",".DB_quote_smart($newpw).",NULL)");
  return;
}

function DB_get_card_name($card)
{
  $r = DB_query_array("SELECT strength,suite FROM Card WHERE id='$card'");

  if($r)
    return $r[0]." of ".$r[1];
  else
    return "Error during get_card_name ".$card;
}

function DB_get_current_playid($gameid)
{
  $trick = DB_get_max_trickid($gameid);

  if(!$trick) return NULL;

  $r = DB_query_array("SELECT id FROM Play WHERE trick_id='$trick' ORDER BY create_date DESC LIMIT 1");

  if($r)
    return $r[0];

  return "";
}

function DB_get_call_by_hash($hash)
{
  $r = DB_query_array("SELECT point_call FROM Hand WHERE hash='$hash'");

  if($r)
    return $r[0];

  return NULL;
}

function DB_get_partner_call_by_hash($hash)
{
  $partner = DB_get_partner_hash_by_hash($hash);

  if($partner)
    {
      $r = DB_query_array("SELECT point_call FROM Hand WHERE hash='$partner'");

      if($r)
	return $r[0];
    }

  return NULL;
}

function DB_get_partner_hash_by_hash($hash)
{
  $gameid = DB_get_gameid_by_hash($hash);
  $party  = DB_get_party_by_hash($hash);

  $r = DB_query_array("SELECT hash FROM Hand WHERE game_id='$gameid' AND party='$party' AND hash<>'$hash'");

  if($r)
    return $r[0];

  return NULL;
}

function DB_format_gameid($gameid)
{
  $session = DB_get_session_by_gameid($gameid);

  /* get number of game */
  $r = DB_query_array("SELECT SUM(TIME_TO_SEC(TIMEDIFF(create_date, (SELECT create_date FROM Game WHERE id='$gameid')))<=0) ".
		      " FROM Game".
		      " WHERE session='$session' ");
  return $session.".".$r[0];
}

function DB_get_reminder($user,$gameid)
{
  $r = DB_query_array("SELECT COUNT(*) FROM Reminder ".
		      "  WHERE user_id=$user ".
		      "  AND game_id=$gameid ".
		      "  AND DATE_SUB(CURDATE(),INTERVAL 1 DAY) <= create_date".
		      "  GROUP BY user_id " );
  if($r)
    return $r[0];
  else
    return 0;
}

function DB_set_reminder($user,$gameid)
{
  DB_query("INSERT INTO Reminder ".
	   "  VALUES(NULL, ".DB_quote_smart($user).", ".DB_quote_smart($gameid).
	   ", NULL) ");
  return 0;
}

function DB_is_session_active($session)
{
  $r = DB_query_array("SELECT COUNT(*) FROM Game ".
		      "  WHERE session=$session ".
		      "  AND status<>'gameover' ");
  if($r)
    return $r[0];
  else
    return -1;
}

function DB_get_score_by_gameid($gameid)
{
  /* returns the points of a game from the point of the re parth (<0 if they lost) */
  $queryresult = DB_query("SELECT COUNT(*),party FROM Score ".
			  "  WHERE game_id=$gameid ".
			  "  GROUP BY party ");
  $re     = 0;
  $contra = 0;

  while($r = DB_fetch_array($queryresult) )
    {
      if($r[1] == "re")
	$re += $r[0];
      else if ($r[1] == "contra")
	$contra += $r[0];
    };

  return ($re - $contra);
}

function DB_get_gameids_of_finished_games_by_session($session)
{
  $ids = array ();

  if($session==0) /* return all games */
    $queryresult = DB_query("SELECT id FROM Game ".
			    " WHERE status='gameover' ".
			    " ORDER BY create_date ASC");
  else   /* return games in a session */
    $queryresult = DB_query("SELECT id FROM Game ".
			    "  WHERE session=$session ".
			    "   AND status='gameover' ".
			    " ORDER BY create_date ASC");

  $i=0;
  while($r = DB_fetch_array($queryresult) )
    {
      $ids[$i] = $r[0];
      $i++;
    }

  return $ids;
}

function DB_get_card_value_by_cardid($id)
{
  $r = DB_query_array("SELECT points FROM Card ".
		      "  WHERE id=$id ");

  if($r)
    return $r[0];
  else
    return NULL;
}

function DB_get_userid($type,$var1="",$var2="")
{
  /* get the userid of a user
   * this can be done several ways, which are all handled below
   * if a email/password combination is given and it doesn't work, we also
   * need to check the recovery table for additional passwords
   */

  $r = NULL;

  switch($type)
    {
    case 'name':
      $result = DB_query("SELECT id FROM User WHERE fullname=".DB_quote_smart($var1));
      break;
    case 'hash':
      $result = DB_query("SELECT user_id FROM Hand WHERE hash=".DB_quote_smart($var1));
      break;
    case 'password':
      $result = DB_query("SELECT id FROM User WHERE password=".DB_quote_smart($var1));
      break;
    case 'email':
      $result = DB_query("SELECT id FROM User WHERE email=".DB_quote_smart($var1));
      break;
    case 'email-password':
      $result = DB_query("SELECT id FROM User WHERE email=".DB_quote_smart($var1)." AND password=".DB_quote_smart($var2));
      $r = DB_fetch_array($result);
      /* test if a recovery password has been set */
      if(!$r)
	{
	  echo "testing alternative password";
	  $result = DB_query("SELECT User.id FROM User".
			     " LEFT JOIN Recovery ON User.id=Recovery.user_id".
			     " WHERE email=".DB_quote_smart($var1).
			     " AND Recovery.password=".DB_quote_smart($var2).
			     " AND DATE_SUB(CURDATE(),INTERVAL 1 DAY) <= Recovery.create_date");
	}
      break;
    case 'gameid-position':
      $result = DB_query("SELECT user_id FROM Hand WHERE game_id=".
			 DB_quote_smart($var1)." AND position=".
			 DB_quote_smart($var2));
      break;
    }

  if(!$r)
    $r = DB_fetch_array($result);

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_get_email($type,$var1='',$var2='')
{
  /* return the email of a user
   * this is used for sending out emails, but also for
   * testing the login for example
   */
  switch($type)
    {
    case 'name':
      $result = DB_query("SELECT email FROM User WHERE fullname=".DB_quote_smart($var1)."");
      break;
    case 'userid':
      $result = DB_query("SELECT email FROM User WHERE id=".DB_quote_smart($var1)."");
      break;
    case 'hash':
      $result = DB_query("SELECT User.email FROM User ".
			 "LEFT JOIN Hand ON Hand.user_id=User.id ".
			 "WHERE Hand.hash=".DB_quote_smart($var1)."");
      break;
    case 'position-gameid':
      $result = DB_query("SELECT email FROM User ".
			 "LEFT JOIN Hand ON User.id=Hand.user_id ".
			 "LEFT JOIN Game ON Game.id=Hand.game_id ".
			 "WHERE Game.id=".DB_quote_smart($var2)." ".
			 "AND Hand.position=".DB_quote_smart($var1)."");
      break;
    }

  $r = DB_fetch_array($result);

  if($r)
    return $r[0];
  else
    return "";
}

function DB_get_name($type,$var1='')
{
  /* get the full name of a user
   * a user can be uniquely identified several ways
   */
  switch($type)
    {
    case 'hash':
      $r = DB_query_array("SELECT fullname FROM Hand LEFT JOIN User ON Hand.user_id=User.id WHERE hash=".DB_quote_smart($var1));
      break;
    case 'email':
      $r = DB_query_array("SELECT fullname FROM User WHERE email=".DB_quote_smart($var1));
      break;
    case 'userid':
      $r = DB_query_array("SELECT fullname FROM User  WHERE id=".DB_quote_smart($var1));
    }

  if($r)
    return $r[0];
  else
    return "";
}

function DB_add_exchanged_card($card,$old_hand_id,$new_hand_id)
{
  DB_query("INSERT INTO Card_Exchange VALUES (NULL,$new_hand_id,$old_hand_id,$card)");
  return;
}

function DB_get_exchanged_cards($hash)
{
  $cards = array();

  $handid = DB_get_handid('hash',$hash);

  $result = DB_query("SELECT card_id FROM Card_Exchange WHERE orig_hand_id=".DB_quote_smart($handid));
  while($r = DB_fetch_array($result))
    $cards[]=$r[0];

  return $cards;
}

function DB_played_by_others($gameid)
{
  $gameids = array();
  $result = DB_query("SELECT id FROM Game WHERE randomnumbers=(SELECT randomnumbers from Game where id=$gameid) and status='gameover'");
  while($r = DB_fetch_array($result))
    if($r[0]!=$gameid)
      $gameids[]=$r[0];
  return $gameids;
}
?>