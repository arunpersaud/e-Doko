<?php

/*
 * open database 
 */

function DB_open()
{
  global $DB;
  if ( $DB = mysql_connect('mysql.nubati.net','doko', '$DoKo#.') )
    mysql_select_db('dokodb') or die('Could not select database'); 
  else
    die (mysql_error());
  
  return;
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
  echo "*looking for $name*";
  $result = mysql_query("SELECT email FROM User WHERE fullname=".DB_quote_smart($name)."");
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

function DB_get_handid_by_hash($hash)
{
  $result = mysql_query("SELECT id FROM Hand WHERE hash=".DB_quote_smart($hash));
  $r      = mysql_fetch_array($result,MYSQL_NUM);
  
  if($r)
    return $r[0];
  else
    return 0;
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
  $result = mysql_query("SELECT fullname FROM Hand LEFT JOIN User ON hand.user_id=user.id WHERE hash=".DB_quote_smart($hash));
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

function DB_set_hand_status_by_hash($hash,$status)
{
  mysql_query("UPDATE Hand SET status='".$status."' WHERE hash=".DB_quote_smart($hash));
  return;
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
  $gameid = DB_get_gameid_by_hash($me);

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
      mysql_query("DELETE FROM Score WHERE hand_id=".DB_quote_smart($id));
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

function DB_set_solo_by_hash($me,$solo)
{
  mysql_query("UPDATE Hand SET solo=".DB_quote_smart($solo)." WHERE hash=".DB_quote_smart($hash));
  return;
}

function DB_set_sickness_by_hash($me,$sickness)
{
  mysql_query("UPDATE Hand SET sickness=".DB_quote_smart($sickness)." WHERE hash=".DB_quote_smart($hash));
  return;
}

function DB_get_current_trickid($gameid)
{
  $result = mysql_query("SELECT trick.id,MAX(play.sequence) FROM Play ".
			"LEFT JOIN Trick ON play.trick_id=trick.id ".
			"WHERE trick.game_id=".DB_quote_smart($gameid)." ".
			"GROUP BY trick.id");
  while(  $r = mysql_fetch_array($result,MYSQL_NUM) )
    {
      $trickid  = $r[0];
      $sequence = $r[1];
    };
  
  if(!$sequence || $sequence==4)
    {
      mysql_query("INSERT INTO Trick VALUES (NULL,NULL,NULL, ".DB_quote_smart($gameid).")");
      $trickid  = mysql_insert_id();
      $sequence = 1;
    }
  else
    {
      $sequence++;
    }

  return array($trickid,$sequence);
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
  return;
}

?>