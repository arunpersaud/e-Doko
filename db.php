<?php

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
  $result = mysql_query("SELECT * FROM User");
  while($r = mysql_fetch_array($result,MYSQL_NUM))
    {
      foreach($r as $thing)
	echo "  $thing ";
      echo "<br />\n";
    }
  return;
}

function DB_get_email_by_name($name)
{
  $result = mysql_query("SELECT email FROM User WHERE fullname=".DB_quote_smart($name)."");
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return "";
}
function DB_get_passwd_by_name($name)
{
  $result = mysql_query("SELECT password FROM User WHERE fullname=".DB_quote_smart($name)."");
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return "";
}

function DB_get_email_by_userid($id)
{
  $result = mysql_query("SELECT email FROM User WHERE id=".DB_quote_smart($id)."");
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return "";
}

function DB_get_email_by_pos_and_gameid($pos,$gameid)
{
  $result = mysql_query("SELECT email FROM User ".
			"LEFT JOIN Hand ON User.id=Hand.user_id ".
			"LEFT JOIN Game ON Game.id=Hand.game_id ".
			"WHERE Game.id=".DB_quote_smart($gameid)." ".
			"AND Hand.position=".DB_quote_smart($pos)."");
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return "";
}

function DB_get_email_by_hash($hash)
{
  $result = mysql_query("SELECT User.email FROM User LEFT JOIN Hand ON Hand.user_id=User.id WHERE Hand.hash=".DB_quote_smart($hash)."");
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return "";
}

function DB_get_userid_by_name($name)
{
  $result = mysql_query("SELECT id FROM User WHERE fullname=".DB_quote_smart($name));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return 0;
}
function DB_get_userid_by_passwd($passwd)
{
  $result = mysql_query("SELECT id FROM User WHERE password=".DB_quote_smart($passwd));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return 0;
}
function DB_get_userid_by_email($email)
{
  $result = mysql_query("SELECT id FROM User WHERE email=".DB_quote_smart($email));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return 0;
}
function DB_get_userid_by_email_and_password($email,$password)
{
  $result = mysql_query("SELECT id FROM User WHERE email=".DB_quote_smart($email)." AND password=".DB_quote_smart($password));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  /* test if a recovery password has been set */
  if(!$r)
    {
      $result = mysql_query("SELECT User.id FROM User".
			    " LEFT JOIN Recovery ON User.id=Recovery.user_id".
			    " WHERE email=".DB_quote_smart($email).
			    " AND Recovery.password=".DB_quote_smart($password).
			    " AND DATE_SUB(CURDATE(),INTERVAL 1 DAY) <= Recovery.create_date");
      $r      = mysql_fetch_array($result,MYSQL_NUM);
    }

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_check_recovery_passwords($password,$email)
{
  $result = mysql_query("SELECT User.id FROM User".
			" LEFT JOIN Recovery ON User.id=Recovery.user_id".
			" WHERE email=".DB_quote_smart($email).
			" AND Recovery.password=".DB_quote_smart($password).
			" AND DATE_SUB(CURDATE(),INTERVAL 1 DAY) <= Recovery.create_date");
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return 1;
  else
    return 0;

}

function DB_get_handid_by_hash($hash)
{
  $result = mysql_query("SELECT id FROM Hand WHERE hash=".DB_quote_smart($hash));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_get_handid_by_gameid_and_position($gameid,$pos)
{
  $result = mysql_query("SELECT id FROM Hand WHERE game_id=".
			DB_quote_smart($gameid)." AND position=".
			DB_quote_smart($pos));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return -1;
}
function DB_get_userid_by_gameid_and_position($gameid,$pos)
{
  $result = mysql_query("SELECT user_id FROM Hand WHERE game_id=".
			DB_quote_smart($gameid)." AND position=".
			DB_quote_smart($pos));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return -1;
}


function DB_get_handid_by_gameid_and_userid($gameid,$userid)
{
  $result = mysql_query("SELECT id FROM Hand WHERE game_id=".
			DB_quote_smart($gameid)." AND user_id=".
			DB_quote_smart($userid));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return -1;
}

function DB_get_userid_by_hash($hash)
{
  $result = mysql_query("SELECT user_id FROM Hand WHERE hash=".DB_quote_smart($hash));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_get_pos_by_hash($hash)
{
  $result = mysql_query("SELECT position FROM Hand WHERE hash=".DB_quote_smart($hash));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_get_name_by_hash($hash)
{
  $result = mysql_query("SELECT fullname FROM Hand LEFT JOIN User ON Hand.user_id=User.id WHERE hash=".DB_quote_smart($hash));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return "";
}

function DB_get_name_by_email($email)
{
  $result = mysql_query("SELECT fullname FROM User WHERE email=".DB_quote_smart($email));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return "";
}

function DB_get_name_by_userid($id)
{
  $result = mysql_query("SELECT fullname FROM User  WHERE id=".DB_quote_smart($id));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return "";
}

function DB_get_status_by_hash($hash)
{
  $result = mysql_query("SELECT status FROM Hand WHERE hash=".DB_quote_smart($hash));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_set_game_status_by_gameid($id,$status)
{
  mysql_query("UPDATE Game SET status='".$status."' WHERE id=".DB_quote_smart($id));
  return;
}

function DB_set_sickness_by_gameid($id,$status)
{
  mysql_query("UPDATE Game SET sickness='".$status."' WHERE id=".DB_quote_smart($id));
  return;
}
function DB_get_sickness_by_gameid($id)
{
  $result = mysql_query("SELECT sickness FROM Game WHERE id=".DB_quote_smart($id));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return NULL;
}

function DB_get_game_status_by_gameid($id)
{
  $result = mysql_query("SELECT status FROM Game WHERE id=".DB_quote_smart($id));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return NULL;
}

function DB_set_hand_status_by_hash($hash,$status)
{
  mysql_query("UPDATE Hand SET status='".$status."' WHERE hash=".DB_quote_smart($hash));
  return;
}

function DB_get_hand_status_by_userid_and_gameid($uid,$gid)
{
  $result = mysql_query("SELECT status FROM Hand WHERE user_id=".DB_quote_smart($uid).
			" AND game_id=".DB_quote_smart($gid));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_get_sickness_by_userid_and_gameid($uid,$gid)
{
  $result = mysql_query("SELECT sickness FROM Hand WHERE user_id=".DB_quote_smart($uid).
			" AND game_id=".DB_quote_smart($gid));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_get_sickness_by_pos_and_gameid($pos,$gid)
{
  $result = mysql_query("SELECT sickness FROM Hand WHERE position=".DB_quote_smart($pos).
			" AND game_id=".DB_quote_smart($gid));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_get_gameid_by_hash($hash)
{
  $result = mysql_query("SELECT game_id FROM Hand WHERE hash=".DB_quote_smart($hash));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

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
  $result = mysql_query("SELECT id FROM Hand WHERE game_id=".DB_quote_smart($gameid));
  while($r = mysql_fetch_array($result,MYSQL_NUM))
    {
      $id = $r[0];

      $tmp = mysql_query("SELECT id  FROM Hand_Card WHERE hand_id=".DB_quote_smart($id));
      $tmp = mysql_fetch_array($tmp,MYSQL_NUM);
      mysql_query("DELETE FROM Play WHERE hand_card_id=".DB_quote_smart($tmp[0]));


      mysql_query("DELETE FROM Hand_Card WHERE hand_id=".DB_quote_smart($id));
      mysql_query("DELETE FROM Hand WHERE id=".DB_quote_smart($id));
    }

  /* delete game */
  mysql_query("DELETE FROM User_Game_Prefs WHERE game_id=".DB_quote_smart($gameid));
  mysql_query("DELETE FROM Trick WHERE game_id=".DB_quote_smart($gameid));
  mysql_query("DELETE FROM Game WHERE id=".DB_quote_smart($gameid));

  return;
}

function DB_get_hand($me)
{
  $cards = array();

  $handid = DB_get_handid_by_hash($me);

  $result = mysql_query("SELECT card_id FROM Hand_Card WHERE hand_id=".DB_quote_smart($handid)." and played='false' ");
  while($r = mysql_fetch_array($result,MYSQL_NUM))
    $cards[]=$r[0];

  return $cards;
}

function DB_get_all_hand($me)
{
  $cards = array();

  $handid = DB_get_handid_by_hash($me);

  $result = mysql_query("SELECT card_id FROM Hand_Card WHERE hand_id=".DB_quote_smart($handid));
  while($r = mysql_fetch_array($result,MYSQL_NUM))
    $cards[]=$r[0];

  return $cards;
}

function DB_get_cards_by_trick($id)
{
  $cards = array();
  $i     = 1;

  $result = mysql_query("SELECT card_id,position FROM Play LEFT JOIN Hand_Card ON Hand_Card.id=Play.hand_card_id ".
			"LEFT JOIN Hand ON Hand.id=Hand_Card.hand_id ".
			"WHERE trick_id=".
			DB_quote_smart($id)." ORDER BY sequence ASC");
  while($r = mysql_fetch_array($result,MYSQL_NUM))
    {
      $cards[$i]=array("card"=>$r[0],"pos"=>$r[1]);
      $i++;
    }

  return $cards;
}


function DB_set_solo_by_hash($hash,$solo)
{
  mysql_query("UPDATE Hand SET solo=".DB_quote_smart($solo)." WHERE hash=".DB_quote_smart($hash));
  return;
}

function DB_set_solo_by_gameid($id,$solo)
{
  mysql_query("UPDATE Game SET solo=".DB_quote_smart($solo)." WHERE id=".DB_quote_smart($id));
  return;
}

function DB_set_sickness_by_hash($hash,$sickness)
{
  mysql_query("UPDATE Hand SET sickness=".DB_quote_smart($sickness)." WHERE hash=".DB_quote_smart($hash));
  return;
}

function DB_get_current_trickid($gameid)
{
  $trickid  = NULL;
  $sequence = NULL;
  $number   = 0;

  $result = mysql_query("SELECT Trick.id,MAX(Play.sequence) FROM Play ".
			"LEFT JOIN Trick ON Play.trick_id=Trick.id ".
			"WHERE Trick.game_id=".DB_quote_smart($gameid)." ".
			"GROUP BY Trick.id");
  while( $r = mysql_fetch_array($result,MYSQL_NUM) )
    {
      $trickid  = $r[0];
      $sequence = $r[1];
      $number++;
    };

  if(!$sequence || $sequence==4)
    {
      mysql_query("INSERT INTO Trick VALUES (NULL,NULL,NULL, ".DB_quote_smart($gameid).",NULL)");
      $trickid  = mysql_insert_id();
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
  $result = mysql_query("SELECT MAX(id) FROM Trick WHERE game_id=".DB_quote_smart($gameid));
  $r = mysql_fetch_array($result,MYSQL_NUM) ;

  return ($r?$r[0]:NULL);
}

function DB_play_card($trickid,$handcardid,$sequence)
{
  mysql_query("INSERT INTO Play VALUES(NULL,NULL,NULL,".DB_quote_smart($trickid).
	      ",".DB_quote_smart($handcardid).",".DB_quote_smart($sequence).")");

  $playid = mysql_insert_id();
  return $playid;
}

function DB_get_all_names_by_gameid($id)
{
  $names = array();

  $result = mysql_query("SELECT fullname FROM Hand LEFT JOIN User ON Hand.user_id=User.id WHERE game_id=".
			DB_quote_smart($id)." ORDER BY position ASC");
  while($r = mysql_fetch_array($result,MYSQL_NUM))
    $names[] = $r[0];

  return $names;
}

function DB_get_all_userid_by_gameid($id)
{
  $names = array();

  $result = mysql_query("SELECT user_id FROM Hand WHERE game_id=".
			DB_quote_smart($id)." ORDER BY position ");
  while($r = mysql_fetch_array($result,MYSQL_NUM))
    $names[] = $r[0];

  return $names;
}

function DB_get_hash_from_game_and_pos($id,$pos)
{
  $result = mysql_query("SELECT hash FROM Hand WHERE game_id=".DB_quote_smart($id)." and position=".DB_quote_smart($pos));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return "";
}

function DB_get_hash_from_gameid_and_userid($id,$user)
{
  $result = mysql_query("SELECT hash FROM Hand WHERE game_id=".DB_quote_smart($id)." and user_id=".DB_quote_smart($user));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return "";
}

function DB_get_all_names()
{
  $names  = array();

  $result = mysql_query("SELECT fullname FROM User");
  while($r = mysql_fetch_array($result,MYSQL_NUM))
    $names[] = $r[0];

  return $names;
}

function DB_update_game_timestamp($gameid)
{
  mysql_query("UPDATE Game SET mod_date = CURRENT_TIMESTAMP WHERE id=".DB_quote_smart($gameid));
  return;
}


function DB_update_user_timestamp($userid)
{
  mysql_query("UPDATE User SET last_login = CURRENT_TIMESTAMP WHERE id=".DB_quote_smart($userid));
  return;
}

function DB_get_user_timestamp($userid)
{
  $result = mysql_query("SELECT last_login FROM User WHERE id=".DB_quote_smart($userid));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return NULL;
}
function DB_get_user_timezone($userid)
{
  $result = mysql_query("SELECT timezone FROM User WHERE id=".DB_quote_smart($userid));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return "Europe/London";
}

function DB_insert_comment($comment,$playid,$userid)
{
  mysql_query("INSERT INTO Comment VALUES (NULL,NULL,NULL,$userid,$playid, ".DB_quote_smart($comment).")");

  return;
}

function DB_get_gametype_by_gameid($id)
{
  $result = mysql_query("SELECT type FROM Game WHERE id=".DB_quote_smart($id));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0]."";
  else
    return "";
}

function DB_set_gametype_by_gameid($id,$p)
{
  mysql_query("UPDATE Game SET type='".$p."' WHERE id=".DB_quote_smart($id));
  return;
}

function DB_get_solo_by_gameid($id)
{
  $result = mysql_query("SELECT solo FROM Game WHERE id=".DB_quote_smart($id));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0]."";
  else
    return "";
}


function DB_get_startplayer_by_gameid($id)
{
  $result = mysql_query("SELECT startplayer FROM Game WHERE id=".DB_quote_smart($id));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_set_startplayer_by_gameid($id,$p)
{
  mysql_query("UPDATE Game SET startplayer='".$p."' WHERE id=".DB_quote_smart($id));
  return;
}

function DB_get_player_by_gameid($id)
{
  $result = mysql_query("SELECT player FROM Game WHERE id=".DB_quote_smart($id));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return 0;
}
function DB_set_player_by_gameid($id,$p)
{
  mysql_query("UPDATE Game SET player='".DB_quote_smart($p)."' WHERE id=".DB_quote_smart($id));
  return;
}



function DB_get_ruleset_by_gameid($id)
{
  $result = mysql_query("SELECT ruleset FROM Game WHERE id=".DB_quote_smart($id));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return NULL;
}

function DB_get_session_by_gameid($id)
{
  $result = mysql_query("SELECT session FROM Game WHERE id=".DB_quote_smart($id));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return NULL;
}

function DB_get_max_session()
{
  $result = mysql_query("SELECT MAX(session) FROM Game");
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return 0;
}

function DB_get_hashes_by_session($session,$user)
{
  $r = array();

  $result = mysql_query("SELECT Hand.hash FROM Hand".
			" LEFT JOIN Game ON Game.id=Hand.game_id ".
			" WHERE Game.session=".DB_quote_smart($session).
			" AND Hand.user_id=".DB_quote_smart($user).
			" ORDER BY Game.create_date ASC");
  while($t = mysql_fetch_array($result,MYSQL_NUM))
    $r[] = $t[0];

  return $r;
}

function DB_get_ruleset($dullen,$schweinchen,$call)
{
  $r = array();

  $result = mysql_query("SELECT id FROM Rulesets WHERE".
			" dullen=".DB_quote_smart($dullen)." AND ".
			" call=".DB_quote_smart($call)." AND ".
			" schweinchen=".DB_quote_smart($schweinchen));
  if($result)
    $r    = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0]; /* found ruleset */
  else
    {
      /* create new one */
      $result = mysql_query("INSERT INTO Rulesets VALUES (NULL, NULL, ".
			    DB_quote_smart($dullen).",".
			    DB_quote_smart($schweinchen).",".
			    DB_quote_smart($call).
			    ", NULL)");
      if($result)
	return mysql_insert_id();
    };

  return -1; /* something went wrong */
}

function DB_get_party_by_hash($hash)
{
  $result = mysql_query("SELECT party FROM Hand WHERE hash=".DB_quote_smart($hash));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return NULL;
}

function DB_get_party_by_gameid_and_userid($gameid,$userid)
{
  $result = mysql_query("SELECT party FROM Hand".
			" WHERE game_id=".DB_quote_smart($gameid).
			"  AND user_id=".DB_quote_smart($userid));
  $r      = mysql_fetch_array($result,MYSQL_NUM);

  if($r)
    return $r[0];
  else
    return NULL;
}

function DB_set_party_by_hash($hash,$party)
{
  mysql_query("UPDATE Hand SET party=".DB_quote_smart($party)." WHERE hash=".DB_quote_smart($hash));
  return;
}

function DB_get_PREF($myid)
{
  global $PREF;

  /* Cardset */
  $result = mysql_query("SELECT value from User_Prefs".
			" WHERE user_id='$myid' AND pref_key='cardset'" );
  $r = mysql_fetch_array($result,MYSQL_NUM);
  if($r)
    {
      if($r[0]=="germancards" && (time()-strtotime( "2009-12-31 23:59:59")<0) ) /* licence only valid until then */
	$PREF["cardset"]="altenburg";
      else
	$PREF["cardset"]="english";
    }
  else
    $PREF["cardset"]="english";

  /* Email */
  $result = mysql_query("SELECT value FROM User_Prefs".
			" WHERE user_id='$myid' AND pref_key='email'" );
  $r = mysql_fetch_array($result,MYSQL_NUM);
  if($r)
    {
      if($r[0]=="emailaddict")
	$PREF["email"]="emailaddict";
      else
	$PREF["email"]="emailnonaddict";
    }
  else
    $PREF["email"]="emailnonaddict";

  return;
}

function DB_get_email_pref_by_hash($hash)
{
  $result = mysql_query("SELECT value FROM Hand".
			" LEFT JOIN User_Prefs ON Hand.user_id=User_Prefs.user_id".
			" WHERE hash='$hash' AND pref_key='email'" );
  $r = mysql_fetch_array($result,MYSQL_NUM);
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
  $result = mysql_query("SELECT value FROM User_Prefs ".
			" WHERE user_id='$uid' AND pref_key='email'" );
  $r = mysql_fetch_array($result,MYSQL_NUM);
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
  $queryresult = mysql_query(" SELECT randomnumbers FROM Game".
			     "   WHERE randomnumbers NOT IN".
			     "           (SELECT randomnumbers FROM Game".
			     "                LEFT JOIN Hand ON Game.id=Hand.game_id".
			     "                WHERE user_id IN  (". $userstr .")".
			     "                GROUP BY randomnumbers".
			     "           )");


  $r = mysql_fetch_array($queryresult,MYSQL_NUM);
  if($r)
    return $r[0];
  else
    return "";
}

function DB_get_number_of_passwords_recovery($user)
{
  $queryresult = mysql_query("SELECT COUNT(*) FROM Recovery ".
			     "  WHERE user_id=$user ".
			     "  AND DATE_SUB(CURDATE(),INTERVAL 1 DAY) <= create_date".
			     "  GROUP BY user_id " );

  $r = mysql_fetch_array($queryresult,MYSQL_NUM);
  if($r)
    return $r[0];
  else
    return 0;
}

function DB_set_recovery_password($user,$newpw)
{
  mysql_query("INSERT INTO Recovery VALUES(NULL,".DB_quote_smart($user).
	      ",".DB_quote_smart($newpw).",NULL)");

  return;
}

function DB_get_card_name($card)
{
  $queryresult = mysql_query("SELECT strength,suite FROM Card WHERE id='$card'");

  $r = mysql_fetch_array($queryresult,MYSQL_NUM);
  if($r)
    return $r[0]." of ".$r[1];
  else
    return "Error during get_card_name ".$card;
}

function DB_get_current_playid($gameid)
{
  $trick = DB_get_max_trickid($gameid);

  if(!$trick) return NULL;

  $queryresult = mysql_query("SELECT id FROM Play WHERE trick_id='$trick' ORDER BY create_date DESC LIMIT 1");

  $r = mysql_fetch_array($queryresult,MYSQL_NUM);
  if($r)
    return $r[0];

  return "";
}

function DB_get_call_by_hash($hash)
{
  $queryresult = mysql_query("SELECT point_call FROM Hand WHERE hash='$hash'");

  $r = mysql_fetch_array($queryresult,MYSQL_NUM);
  if($r)
    return $r[0];

  return NULL;
}

function DB_get_partner_call_by_hash($hash)
{
  $partner = DB_get_partner_hash_by_hash($hash);

  if($partner)
    {
      $queryresult = mysql_query("SELECT point_call FROM Hand WHERE hash='$partner'");

      $r = mysql_fetch_array($queryresult,MYSQL_NUM);
      if($r)
	return $r[0];
    }

  return NULL;
}

function DB_get_partner_hash_by_hash($hash)
{
  $gameid = DB_get_gameid_by_hash($hash);
  $party  = DB_get_party_by_hash($hash);

  $queryresult = mysql_query("SELECT hash FROM Hand WHERE game_id='$gameid' AND party='$party' AND hash<>'$hash'");

  $r = mysql_fetch_array($queryresult,MYSQL_NUM);
  if($r)
    return $r[0];

  return NULL;
}

function DB_format_gameid($gameid)
{
  $session = DB_get_session_by_gameid($gameid);

  /* get number of game */
  $result = mysql_query("SELECT COUNT(*),create_date FROM Game".
			" WHERE session='$session' ".
			" AND TIMEDIFF(create_date, (SELECT create_date FROM Game WHERE id='$gameid'))<=0 ".
			" GROUP by session");
  $r = mysql_fetch_array($result,MYSQL_NUM);

  return $session.".".$r[0];
}

function DB_get_reminder($user,$gameid)
{
  $queryresult = mysql_query("SELECT COUNT(*) FROM Reminder ".
			     "  WHERE user_id=$user ".
			     "  AND game_id=$gameid ".
			     "  AND DATE_SUB(CURDATE(),INTERVAL 1 DAY) <= create_date".
			     "  GROUP BY user_id " );

  $r = mysql_fetch_array($queryresult,MYSQL_NUM);
  if($r)
    return $r[0];
  else
    return 0;
}

function DB_set_reminder($user,$gameid)
{
  mysql_query("INSERT INTO Reminder ".
	      "  VALUES(NULL, ".DB_quote_smart($user).", ".DB_quote_smart($gameid).
              ", NULL) ");
  return 0;
}

function DB_is_session_active($session)
{
  $queryresult = mysql_query("SELECT COUNT(*) FROM Game ".
			     "  WHERE session=$session ".
			     "  AND status<>'gameover' ");

  $r = mysql_fetch_array($queryresult,MYSQL_NUM);
  if($r)
    return $r[0];
  else
    return -1;
}

function DB_get_score_by_gameid($gameid)
{
  /* returns the points of a game from the point of the re parth (<0 if they lost) */
  $queryresult = mysql_query("SELECT COUNT(*),party FROM Score ".
			     "  WHERE game_id=$gameid ".
			     "  GROUP BY party ");

  $re     = 0;
  $contra = 0;

  while($r = mysql_fetch_array($queryresult,MYSQL_NUM) )
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

  $queryresult = mysql_query("SELECT id FROM Game ".
			     "  WHERE session=$session ".
			     "   AND status='gameover' ".
			     " ORDER BY create_date ASC");

  $i=0;
  while($r = mysql_fetch_array($queryresult,MYSQL_NUM) )
    {
      $ids[$i] = $r[0];
      $i++;
    }

  return $ids;
}

function DB_get_card_value_by_cardid($id)
{
  $queryresult = mysql_query("SELECT points FROM Card ".
			     "  WHERE id=$id ");

  $r = mysql_fetch_array($queryresult,MYSQL_NUM);
  if($r)
    return $r[0];
  else
    return NULL;
}

?>