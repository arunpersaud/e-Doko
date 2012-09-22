<?php
/* Copyright 2006, 2007, 2008, 2009, 2010, 2011, 2012 Arun Persaud <arun@nubati.net>
 *
 *   This file is part of e-DoKo.
 *
 *   e-DoKo is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   e-DoKo is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with e-DoKo.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

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
  $version_needed = 5;

  global $DB,$DB_user,$DB_host,$DB_database,$DB_password;
  $DB = @mysql_connect($DB_host,$DB_user, $DB_password);
  if ( $DB )
    {
      mysql_select_db($DB_database) or die('Error: Could not select database');
    }
  else
    {
      echo mysql_errno() . ": " . mysql_error(). "\n";
      return -1;
    };

  $version = DB_get_version();
  if ($version != $version_needed)
    return -2;

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
  /* debug/optimize the database
  $time = microtime();
  $return = mysql_query($query);
  $time = $time - microtime();

  if($time > 0.05) // this way we can find only the long ones
  {
    $logfile=fopen('/tmp/DBlog.log','a+');
    fwrite($logfile,"EXPLAIN $query ;\n");
    fwrite($logfile,"time of above query: $time\n");
    fclose($logfile);
  };

  return $return;
  */

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

function DB_get_version()
{
  $version = DB_query_array('SELECT version FROM Version');
  return $version[0];
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

function DB_get_all_user_names_open_for_games()
{
  $names  = array();

  DB_query("DROP   TEMPORARY TABLE IF EXISTS Usertmp;");
  DB_query("CREATE TEMPORARY TABLE Usertmp SELECT id,fullname FROM User;");
  DB_query("DELETE FROM Usertmp WHERE id IN (SELECT user_id FROM User_Prefs WHERE pref_key='open for games' and value='no')");

  $result = DB_query("SELECT fullname FROM Usertmp");
  DB_query("DROP   TEMPORARY TABLE IF EXISTS Usertmp;");

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

function DB_get_emails_of_last_logins($N)
{
  $emails  = array();

  $result = DB_query("SELECT email FROM User ORDER BY last_login DESC LIMIT $N");
  while($r = DB_fetch_array($result))
    $emails[] = $r[0];

  return $emails;
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

function DB_get_game_timestamp($gameid)
{
  $r = DB_query_array("SELECT mod_date FROM Game WHERE id=".DB_quote_smart($gameid));

  if($r)
    return $r[0];
  else
    return NULL;
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

function DB_get_user_creation_date($userid)
{
  $r = DB_query_array("SELECT create_date FROM User WHERE id=".DB_quote_smart($userid));

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

function DB_insert_comment($comment,$playid,$gameid,$userid)
{
  DB_query("INSERT INTO Comment VALUES (NULL,NULL,NULL,$userid,$playid,$gameid, ".DB_quote_smart($comment).")");

  return;
}

function DB_get_pre_comment($gameid)
{
  $r = DB_query_array_all("SELECT comment, User.fullname FROM Comment".
			  " LEFT JOIN User ON User.id=user_id".
			  " WHERE play_id=-1".
			  " AND game_id=$gameid ");
  return $r;
}

function DB_get_pre_comment_call($gameid)
{
  $r = DB_query_array_all("SELECT comment, User.fullname FROM Comment".
			  " LEFT JOIN User ON User.id=user_id".
			  " WHERE play_id=-2".
			  " AND game_id=$gameid ");
  return $r;
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

function DB_get_ruleset($dullen,$schweinchen,$call,$lowtrump)
{
  $r = array();

  $result = DB_query("SELECT id FROM Rulesets WHERE".
		     " dullen=".DB_quote_smart($dullen)." AND ".
		     " Rulesets.call=".DB_quote_smart($call)." AND ".
		     " schweinchen=".DB_quote_smart($schweinchen)." AND ".
		     " lowtrump=".DB_quote_smart($lowtrump));
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
			 DB_quote_smart($lowtrump).",".
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

function DB_get_party_by_session_and_userid($session,$userid)
{
  /* used in score table by index. gameids are sorted by date, so we need to sort here too! */
  $r = DB_query_array_all("SELECT party FROM Hand".
			  " LEFT JOIN Game ON Game.id = Hand.game_id".
			  " WHERE Game.session=".DB_quote_smart($session).
			  "  AND user_id=".DB_quote_smart($userid)."".
			  "  AND Game.status='gameover' ".
			  " ORDER BY Game.create_date ASC");
  return $r;
}

function DB_set_party_by_hash($hash,$party)
{
  DB_query("UPDATE Hand SET party=".DB_quote_smart($party)." WHERE hash=".DB_quote_smart($hash));
  return;
}

function DB_get_PREF($myid)
{
  /* set defaults */
  $PREF['cardset']		= 'english';
  $PREF['email']		= 'emailnonaddict';
  $PREF['digest']               = 'digest-off';
  $PREF['autosetup']		= 'no';
  $PREF['sorting']		= 'high-low';
  $PREF['open_for_games']	= 'yes';
  $PREF['vacation_start']	=  NULL;
  $PREF['vacation_stop']	=  NULL;
  $PREF['vacation_comment']	=  '';
  $PREF['language']	        =  'en';

  /* get all preferences */
  $r = DB_query('SELECT pref_key, value FROM User_Prefs'.
		      " WHERE user_id='$myid' " );
  while($pref = DB_fetch_array($r) )
    {
      switch($pref[0])
	{
	case 'cardset':
	  /* licence only valid until then */
	  if($pref[1]=='altenburg' && (time()-strtotime( '2012-12-31 23:59:59')<0) )
	    $PREF['cardset']='altenburg';
	  break;

	case 'email':
	  if($pref[1]=='emailaddict')
	    $PREF['email']='emailaddict';
	  break;

	case 'digest':
	  if($pref[1])
	    $PREF['digest'] = $pref[1];
	  break;

	case 'autosetup':
	  if($pref[1]=='yes')
	    $PREF['autosetup']='yes';
	  break;

	case 'sorting':
	  if($pref[1])
	    $PREF['sorting'] = $pref[1];
	  break;

	case 'open for games':
	  if($pref[1])
	    $PREF['open_for_games'] = $pref[1];
	  break;

	case 'vacation start':
	  if($pref[1])
	    $PREF['vacation_start'] = $pref[1];
	  break;

	case 'vacation stop':
	  if($pref[1])
	    $PREF['vacation_stop'] = $pref[1];
	  break;

	case 'vacation comment':
	  if($pref[1])
	    $PREF['vacation_comment'] = $pref[1];
	  break;

	case 'language':
	  if($pref[1])
	    $PREF['language'] = $pref[1];
	  break;
	}
    }

  return $PREF;
}

function DB_get_RULES($gameid)
{
  $r = DB_query_array("SELECT * FROM Rulesets".
		      " LEFT JOIN Game ON Game.ruleset=Rulesets.id ".
		      " WHERE Game.id='$gameid'" );

  $RULES['dullen']      = $r[2];
  $RULES['schweinchen'] = $r[3];
  $RULES['lowtrump']    = $r[4];
  $RULES['call']        = $r[5];

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
  DB_query("DELETE FROM gametmp WHERE randomnumbers IN (SELECT randomnumbers FROM Hand LEFT JOIN Game ON Game.id=game_id WHERE user_id IN (".$userstr."));");

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

function DB_delete_recovery_passwords($userid)
{
  DB_query("DELETE FROM Recovery WHERE user_id=".DB_quote_smart($userid));
  return;
}

function DB_get_card_name($card)
{
  if($card==0)
    return 'backside';

  $r = DB_query_array("SELECT strength,suite FROM Card WHERE id='$card'");

  if($r)
    return $r[0]." of ".$r[1];
  else
    return "Error during get_card_name ".$card;
}

function DB_get_current_playid($gameid)
{
  /* return playid or -1 for pre-game phase */
  $trick = DB_get_max_trickid($gameid);

  if(!$trick) return -1;

  $r = DB_query_array("SELECT id FROM Play WHERE trick_id='$trick' ORDER BY create_date DESC LIMIT 1");

  if($r)
    return $r[0];

  return -1;
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
  /* get session and create date */
  $r = DB_query_array("SELECT session, create_date FROM Game WHERE id='$gameid' ");
  $session = $r[0];
  $date    = $r[1];

  /* get number of game */
  $r = DB_query_array("SELECT SUM(TIME_TO_SEC(TIMEDIFF(create_date, '$date'))<=0) ".
		      " FROM Game".
		      " WHERE session='$session' ");
  return $session.'.'.$r[0];
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
		      "  AND status IN ('pre','play') ");
  if($r)
    return $r[0];
  else
    return -1;
}

function DB_get_gameids_of_finished_games_by_session($session)
{
  $ids = array ();

  if($session==0) /* return all games */
    $queryresult = DB_query_array_all("SELECT Game.id,SUM(IF(STRCMP(Score.party,'re'),-1,1)),Game.type FROM Game ".
				  " LEFT JOIN Score on game_id=Game.id".
				  " WHERE status='gameover' ".
				  " GROUP BY Game.id");
  else   /* return games in a session */
    $queryresult = DB_query_array_all("SELECT Game.id,SUM(IF(STRCMP(Score.party,'re'),-1,1)),Game.type FROM Game ".
				  " LEFT JOIN Score on game_id=Game.id".
				  "  WHERE session=$session ".
				  "   AND status='gameover' ".
				  " GROUP BY Game.id".
				  " ORDER BY Game.create_date ASC");

  return $queryresult;
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
	  /* testing alternative password */
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
  $result = DB_query("SELECT id FROM Game WHERE randomnumbers=(SELECT randomnumbers FROM Game WHERE id=$gameid) AND status='gameover'");
  while($r = DB_fetch_array($result))
    if($r[0]!=$gameid)
      $gameids[]=$r[0];
  return $gameids;
}

function DB_get_number_of_tricks($gameid,$position)
{
  $r = DB_query_array("SELECT COUNT(winner) FROM Trick Where game_id='$gameid' and winner='$position'");
  return $r[0];
}

function DB_digest_insert_email($To,$message,$type,$gameid)
{
  if($type == GAME_YOUR_TURN)
    DB_query("INSERT INTO digest_email VALUES (NULL,".DB_quote_smart($To).",NULL,'your_turn',$gameid,".DB_quote_smart($message).")");
  else
    DB_query("INSERT INTO digest_email VALUES (NULL,".DB_quote_smart($To).",NULL,'misc',NULL,".DB_quote_smart($message).")");
  return;
}

function DB_get_digest_users()
{
  $users = array();

  $result = DB_query("SELECT user_id FROM User_Prefs WHERE pref_key='digest' and value <> 'digest-off'");
  while($r = DB_fetch_array($result))
    $users[]=$r[0];

  return $users;
}

function DB_get_digest_message_by_email($email)
{
  $messages = array();

  $result = DB_query("SELECT id,content,type,game_id FROM digest_email Where email='$email'");
  while($r = DB_fetch_array($result))
    $messages[]=$r;

  return $messages;
}

function DB_digest_delete_message($id)
{
  DB_query("Delete from digest_email where id='$id'");
}

?>