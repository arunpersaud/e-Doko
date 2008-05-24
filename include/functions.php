<?php
/* make sure that we are not called from outside the scripts,
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

function config_check()
{
  global $EmailName,$EMAIL_REPLY,$ADMIN_NAME,$ADMIN_EMAIL,$DB_work;

  /* check if some variables are set in the config file, else set defaults */
  if(!isset($EmailName))
    $EmailName="[DoKo] ";
  if(isset($EMAIL_REPLY))
    {
      ini_set("sendmail_from",$EMAIL_REPLY);
    }
  if(!isset($ADMIN_NAME))
    {
      output_header();
      echo "<h1>Setup not completed</h1>";
      echo "You need to set \$ADMIN_NAME in config.php.";
      output_footer();
      exit();
    }
  if(!isset($ADMIN_EMAIL))
    {
      output_header();
      echo "<h1>Setup not completed</h1>";
      echo "You need to set \$ADMIN_EMAIL in config.php. ".
	"If something goes wrong an email will be send to this address.";
      output_footer();
      exit();
    }
  if(!isset($DB_work))
    {
      output_header();
      echo "<h1>Setup not completed</h1>";
      echo "You need to set \$DB_work in config.php. ".
	"If this is set to 1, the game will be suspended and one can work safely on the database.".
	"The default should be 0 for the game to work.";
      output_footer();
      exit();
    }
  if($DB_work)
    {
      output_header();
      echo "Working on the database...please check back later.";
      output_footer();
      exit();
    }

  return;
}

function mymail($To,$Subject,$message,$header="")
{
  global $debug,$EMAIL_REPLY;

  if(isset($EMAIL_REPLY))
    $header .= "From: e-DoKo daemon <$EMAIL_REPLY>\r\n";

  if($debug)
    {
      /* display email on screen,
       * change txt -> html
       */
      $message = str_replace("\n","<br />\n",$message);
      $message = ereg_replace("[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]",
                     "<a href=\"\\0\">\\0</a>", $message);

      echo "<br />To: $To<br />";
      if($header != "")
	echo $header."<br />";
      echo "Subject: $Subject <br />$message<br />\n";
    }
  else
    if($header != "")
      mail($To,$Subject,$message,$header);
    else
      mail($To,$Subject,$message);
  return;
}

function myisset()
{
  /* returns 1 if all names passed as args are defined by a GET or POST statement,
   * else return 0
   */

  $ok   = 1;
  $args = func_get_args();

  foreach($args as $arg)
    {
      $ok = $ok * isset($_REQUEST[$arg]);
      /*echo "$arg: ok = $ok <br />";
       */
    }
  return $ok;
}

function myerror($message)
{
  echo "<span class=\"error\">".htmlspecialchars($message)."</span>\n";
  mymail($ADMIN_EMAIL,$EmailName." Error in Code",$message);
  return;
}

function pos_array($c,$arr)
{
  $ret = 0;

  $i   = 0;
  foreach($arr as $a)
    {
      $i++;
      if($a == $c)
	{
	  $ret = $i;
	  break;
	}
    }
  return $ret;
}

function is_trump($c)
{
  global $CARDS;

  if(in_array($c,$CARDS["trump"]))
    return 1;
  else
    return 0;
}

function is_same_suite($c1,$c2)
{
  global $CARDS;

  if(in_array($c1,$CARDS["trump"]   ) && in_array($c2,$CARDS["trump"]   ) ) return 1;
  if(in_array($c1,$CARDS["clubs"]   ) && in_array($c2,$CARDS["clubs"]   ) ) return 1;
  if(in_array($c1,$CARDS["hearts"]  ) && in_array($c2,$CARDS["hearts"]  ) ) return 1;
  if(in_array($c1,$CARDS["spades"]  ) && in_array($c2,$CARDS["spades"]  ) ) return 1;
  if(in_array($c1,$CARDS["diamonds"]) && in_array($c2,$CARDS["diamonds"]) ) return 1;

  return 0;
}

function compare_cards($a,$b,$game)
{
  /* if "a" is higher than "b" return 1, else 0, "a" being the card first played */

  global $CARDS;
  global $RULES;
  global $GAME;

  /* first map all cards to the odd number,
   * this insure that the first card wins the trick
   * if they are the same card
   */
  if( $a/2 - (int)($a/2) != 0.5)
    $a--;
  if( $b/2 - (int)($b/2) != 0.5)
    $b--;

  /* check for schweinchen and ten of hearts*/
  switch($game)
    {
    case "normal":
    case "silent":
    case "trump":
      if($RULES['schweinchen']=='both' && $GAME['schweinchen-who'])
	{
	  if($a == 19 || $a == 20 )
	    return 1;
	  if($b == 19 || $b == 20 )
	    return 0;
	};
      if($RULES['schweinchen']=='second' && $GAME['schweinchen-second'])
	{
	  if($a == 19 || $a == 20 )
	    return 1;
	  if($b == 19 || $b == 20 )
	    return 0;
	};
    case "heart":
    case "spade":
    case "club":
      /* check for ten of hearts rule */
      if($RULES["dullen"]=="secondwins")
	if($a==1 && $b==1) /* both 10 of hearts */
	  return 0;        /* second one wins.*/
    case "trumpless":
    case "jack":
    case "queen":
      /* no special cases here */
    }

  /* normal case */
  if(is_trump($a) && is_trump($b) && $a<=$b)
    return 1;
  else if(is_trump($a) && is_trump($b) )
    return 0;
  else
    { /*$a is not a trump */
      if(is_trump($b))
	return 0;
      else
	{ /* both no trump */

	  /* both clubs? */
	  $posA = pos_array($a,$CARDS["clubs"]);
	  $posB = pos_array($b,$CARDS["clubs"]);
	  if($posA && $posB)
	    if($posA <= $posB)
	      return 1;
	    else
	      return 0;

	  /* both spades? */
	  $posA = pos_array($a,$CARDS["spades"]);
	  $posB = pos_array($b,$CARDS["spades"]);
	  if($posA && $posB)
	    if($posA <= $posB)
	      return 1;
	    else
	      return 0;

	  /* both hearts? */
	  $posA = pos_array($a,$CARDS["hearts"]);
	  $posB = pos_array($b,$CARDS["hearts"]);
	  if($posA && $posB)
	    if($posA <= $posB)
	      return 1;
	    else
	      return 0;

	  /* both diamonds? */
	  $posA = pos_array($a,$CARDS["diamonds"]);
	  $posB = pos_array($b,$CARDS["diamonds"]);
	  if($posA && $posB)
	    if($posA <= $posB)
	      return 1;
	    else
	      return 0;

	  /* not the same suit and no trump: a wins */
	  return 1;
	}
    }
}

function get_winner($p,$mode)
{
  /* get all 4 cards played in a trick, in the order they are played */
  $tmp = $p[1];
  $c1    = $tmp["card"];
  $c1pos = $tmp["pos"];

  $tmp = $p[2];
  $c2    = $tmp["card"];
  $c2pos = $tmp["pos"];

  $tmp = $p[3];
  $c3    = $tmp["card"];
  $c3pos = $tmp["pos"];

  $tmp = $p[4];
  $c4    = $tmp["card"];
  $c4pos = $tmp["pos"];

  /* first card is better than all the rest */
  if( compare_cards($c1,$c2,$mode) && compare_cards($c1,$c3,$mode) && compare_cards($c1,$c4,$mode) )
    return $c1pos;

  /* second card is better than first and better than the rest */
  if( !compare_cards($c1,$c2,$mode) &&  compare_cards($c2,$c3,$mode) && compare_cards($c2,$c4,$mode) )
    return $c2pos;

  /* third card is better than first card and better than last */
  if( !compare_cards($c1,$c3,$mode) &&  compare_cards($c3,$c4,$mode) )
    /* if second card is better than first, third card needs to be even better */
    if( !compare_cards($c1,$c2,$mode) && !compare_cards($c2,$c3,$mode) )
      return $c3pos;
    /* second is worse than first, e.g. not following suite */
    else if (compare_cards($c1,$c2,$mode) )
      return $c3pos;

  /* non of the above */
  return $c4pos;
}

function count_nines($cards)
{
  $nines = 0;

  foreach($cards as $c)
    {
      if($c == "25" || $c == "26") $nines++;
      else if($c == "33" || $c == "34") $nines++;
      else if($c == "41" || $c == "42") $nines++;
      else if($c == "47" || $c == "48") $nines++;
    }

  return $nines;
}

function check_wedding($cards)
{

  if( in_array("3",$cards) && in_array("4",$cards) )
    return 1;

  return 0;
}

function count_trump($cards)
{
  global $RULES;

  $trump = 0;

  /* count each trump, including the foxes */
  foreach($cards as $c)
    if( (int)($c) <27)
      $trump++;

  /* normally foxes don't count as trump, so we substract them here
   * in case someone has schweinchen, one or two of them should count as trump
   * though, so we need to add one trump for those cases */

  /* subtract foxes */
  if( in_array("19",$cards))
    $trump--;
  if( in_array("20",$cards) )
    $trump--;

  /* handle case where player has schweinchen */
  if( in_array("19",$cards) && in_array("20",$cards) )
    switch($RULES["schweinchen"])
      {
      case "both":
	/* add two, in case the player has both foxes (schweinchen) */
	$trump++;
	$trump++;
	break;
      case "second":
      case "secondaftercall":
	/* add one, in case the player has both foxes (schweinchen) */
	$trump++;
	break;
      case "none":
	break;
      }

  return $trump;
}

function  create_array_of_random_numbers($useridA,$useridB,$useridC,$useridD)
{
  global $debug;

  $r = array();

  if($debug)
    {
      $r[ 0]=1;     $r[12]=47;   $r[24]=13;       $r[36]=37;
      $r[ 1]=2;     $r[13]=23;   $r[25]=14;	  $r[37]=38;
      $r[ 2]=3;     $r[14]=27;   $r[26]=15;	  $r[38]=39;
      $r[ 3]=4;     $r[15]=16;   $r[27]=28;	  $r[39]=40;
      $r[ 4]=5;     $r[16]=17;   $r[28]=29;	  $r[40]=41;
      $r[ 5]=18;    $r[17]=6;    $r[29]=30;	  $r[41]=42;
      $r[ 6]=21;    $r[18]=7;    $r[30]=31;	  $r[42]=43;
      $r[ 7]=22;    $r[19]=8;    $r[31]=32;	  $r[43]=44;
      $r[ 8]=45;    $r[20]=9;    $r[32]=19;	  $r[44]=33;
      $r[ 9]=46;    $r[21]=10;   $r[33]=20;	  $r[45]=24;
      $r[10]=35;    $r[22]=11;   $r[34]=48;	  $r[46]=25;
      $r[11]=36;    $r[23]=12;   $r[35]=34;	  $r[47]=26;
    }
  else
    {
      /* check if we can find a game were non of the player was involved and return
       * cards insted
       */
      $userstr = "'".implode("','",array($useridA,$useridB,$useridC,$useridD))."'";
      $randomnumbers = DB_get_unused_randomnumbers($userstr);
      $randomnumbers = explode(":",$randomnumbers);

      if(sizeof($randomnumbers)==48)
	return $randomnumbers;

      /* need to create new numbers */
      for($i=0;$i<48;$i++)
	$r[$i]=$i+1;

      /* shuffle using a better random generator than the standard one */
      for ($i = 0; $i <48; $i++)
	{
	  $j = @mt_rand(0, $i);
	  $tmp = $r[$i];
	  $r[$i] = $r[$j];
	  $r[$j] = $tmp;
	}
    };

  return $r;
}

function display_cards($me,$myturn)
{
  return;
}

function have_suit($cards,$c)
{
  global $CARDS;
  $suite = array();

  if(in_array($c,$CARDS["trump"]))
    $suite = $CARDS["trump"];
  else if(in_array($c,$CARDS["clubs"]))
    $suite = $CARDS["clubs"];
  else if(in_array($c,$CARDS["spades"]))
    $suite = $CARDS["spades"];
  else if(in_array($c,$CARDS["hearts"]))
    $suite = $CARDS["hearts"];
  else if(in_array($c,$CARDS["diamonds"]))
    $suite = $CARDS["diamonds"];

  foreach($cards as $card)
    {
      if(in_array($card,$suite))
	return 1;
    }

  return 0;
}

function same_type($card,$c)
{
  global $CARDS;
  $suite = "";

  /* figure out what kind of card c is */
  if(in_array($c,$CARDS["trump"]))
    $suite = $CARDS["trump"];
  else if(in_array($c,$CARDS["clubs"]))
    $suite = $CARDS["clubs"];
  else if(in_array($c,$CARDS["spades"]))
    $suite = $CARDS["spades"];
  else if(in_array($c,$CARDS["hearts"]))
    $suite = $CARDS["hearts"];
  else if(in_array($c,$CARDS["diamonds"]))
    $suite = $CARDS["diamonds"];

  /* card is the same suid return 1 */
  if(in_array($card,$suite))
    return 1;

  return 0;
}

function set_gametype($gametype)
{
  global $CARDS;
  global $RULES;
  global $GAME;

  switch($gametype)
    {
    case "normal":
    case "wedding":
    case "poverty":
    case "dpoverty":
    case "trump":
    case "silent":
      $CARDS["trump"]    = array('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16',
				 '17','18','19','20','21','22','23','24','25','26');
      $CARDS["diamonds"] = array();
      $CARDS["clubs"]    = array('27','28','29','30','31','32','33','34');
      $CARDS["spades"]   = array('35','36','37','38','39','40','41','42');
      $CARDS["hearts"]   = array('43','44','45','46','47','48');
      $CARDS["foxes"]    = array('19','20');
      if($RULES["dullen"]=='none')
	{
	  $CARDS["trump"]    = array('3','4','5','6','7','8','9','10','11','12','13','14','15','16',
				     '17','18','19','20','21','22','23','24','25','26');
	  $CARDS["hearts"]   = array('43','44','1','2','45','46','47','48');
	}
      /* do we need to reorder for Schweinchen? need to search for it because of special case for dullen above*/
      if($RULES['schweinchen']=='both'&& $GAME['schweinchen-who'])
	{
	  /* find the fox and put them at the top of the stack */
	  foreach(array('19','20') as $fox)
	    {
	      /* search for fox */
	      $trump = $CARDS['trump'];
	      $key = array_keys($trump, $fox);

	      /* reorder */
	      $foxa = array();
	      $foxa[]=$trump[$key[0]];
	      unset($trump[$key[0]]);
	      $trump = array_merge($foxa,$trump);
	      $CARDS['trump'] = $trump;
	    }
	}
      else if( ($RULES['schweinchen']=='second' || $RULES['schweinchen']=='secondaftercall')
	       && $GAME['schweinchen-who'])
	{
	  /* find the fox and put them at the top of the stack */
	  $trump = $CARDS['trump'];
	  $key = array_keys($trump, '19');

	  /* reorder */
	  $foxa = array();
	  $foxa[]=$trump[$key[0]];
	  unset($trump[$key[0]]);
	  $trump = array_merge($foxa,$trump);
	  $CARDS['trump'] = $trump;
	}
      break;
    case "queen":
      $CARDS["trump"]    = array('3','4','5','6','7','8','9','10');
      $CARDS["clubs"]    = array('27','28','29','30','31','32','11','12','33','34');
      $CARDS["spades"]   = array('35','36','37','38','39','40','13','14','41','42');
      $CARDS["hearts"]   = array('43','44', '1', '2','45','46','15','16','47','48');
      $CARDS["diamonds"] = array('19','20','21','22','23','24','17','18','25','26');
      $CARDS["foxes"]    = array();
      break;
    case "jack":
      $CARDS["trump"]    = array('11','12','13','14','15','16','17','18');
      $CARDS["clubs"]    = array('27','28','29','30','31','32','3', '4','33','34');
      $CARDS["spades"]   = array('35','36','37','38','39','40','5', '6','41','42');
      $CARDS["hearts"]   = array('43','44', '1', '2','45','46','7', '8','47','48');
      $CARDS["diamonds"] = array('19','20','21','22','23','24','9','10','25','26');
      $CARDS["foxes"]    = array();
      break;
    case "trumpless":
      $CARDS["trump"]    = array();
      $CARDS["clubs"]    = array('27','28','29','30','31','32','3', '4','11','12','33','34');
      $CARDS["spades"]   = array('35','36','37','38','39','40','5', '6','13','14','41','42');
      $CARDS["hearts"]   = array('43','44', '1', '2','45','46','7', '8','15','16','47','48');
      $CARDS["diamonds"] = array('19','20','21','22','23','24','9','10','17','18','25','26');
      $CARDS["foxes"]    = array();
      break;
    case "club":
      $CARDS["trump"]    = array('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16',
				 '17','18','27','28','29','30','31','32','33','34');
      $CARDS["clubs"]    = array();
      $CARDS["spades"]   = array('35','36','37','38','39','40','41','42');
      $CARDS["hearts"]   = array('43','44','45','46','47','48');
      $CARDS["diamonds"] = array('19','20','21','22','23','24','25','26');
      $CARDS["foxes"]    = array();
      if($RULES["dullen"]=='none')
	{
	  $CARDS["trump"]    = array('3','4','5','6','7','8','9','10','11','12','13','14','15','16',
				     '17','18','27','28','29','30','31','32','33','34');
	  $CARDS["hearts"]   = array('43','44','1','2','45','46','47','48');
	}
      break;
    case "spade":
      $CARDS["trump"]    = array('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16',
				 '17','18','35','36','37','38','39','40','41','42');
      $CARDS["clubs"]    = array('27','28','29','30','31','32','33','34');
      $CARDS["spades"]   = array();
      $CARDS["hearts"]   = array('43','44','45','46','47','48');
      $CARDS["diamonds"] = array('19','20','21','22','23','24','25','26');
      $CARDS["foxes"]    = array();
      if($RULES["dullen"]=='none')
	{
	  $CARDS["trump"]    = array('3','4','5','6','7','8','9','10','11','12','13','14','15','16',
				     '17','18','35','36','37','38','39','40','41','42');
	  $CARDS["hearts"]   = array('43','44','1','2','45','46','47','48');
	}
      break;
    case "heart":
      $CARDS["trump"]    = array('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16',
				 '17','18','43','44','45','46','47','48');
      $CARDS["clubs"]    = array('27','28','29','30','31','32','33','34');
      $CARDS["spades"]   = array('35','36','37','38','39','40','41','42');
      $CARDS["hearts"]   = array();
      $CARDS["diamonds"] = array('19','20','21','22','23','24','25','26');
      $CARDS["foxes"]    = array();
      if($RULES["dullen"]=='none')
	{
	  $CARDS["trump"]    = array('3','4','5','6','7','8','9','10','11','12','13','14','15','16',
			    '17','18','43','44','1','2','45','46','47','48');
	}
      break;
    }
}

function mysort($cards,$gametype)
{
  usort ( $cards, "sort_comp" );
  return $cards;
}

function sort_comp($a,$b)
{
  global $CARDS;

  $ALL = array();
  $ALL = array_merge($CARDS["trump"],$CARDS["diamonds"],$CARDS["clubs"],
		     $CARDS["hearts"],$CARDS["spades"]);

  return pos_array($a,$ALL)-pos_array($b,$ALL);
}

function can_call($what,$hash)
{
  global $RULES;

  $gameid   = DB_get_gameid_by_hash($hash);
  $gametype = DB_get_gametype_by_gameid($gameid);
  $oldcall  = DB_get_call_by_hash($hash);
  $pcall    = DB_get_partner_call_by_hash($hash);

  if( ($pcall!=NULL && $what >= $pcall) ||
      ($oldcall!=NULL && $what >=$oldcall) )
    {
      return 0;
    }

  $NRcards  = count(DB_get_hand($hash));

  $NRallcards = 0;
  for ($i=1;$i<5;$i++)
    {
      $user         = DB_get_hash_from_game_and_pos($gameid,$i);
      $NRallcards  += count(DB_get_hand($user));
    };

  /* in case of a wedding, everything will be delayed by an offset */
  $offset = 0;
  if($gametype=="wedding")
    {
      $offset = DB_get_sickness_by_gameid($gameid);
      if ($offset <0) /* not resolved */
	return 0;
    };

  switch ($RULES["call"])
    {
    case "1st-own-card":
      if( 4-($what/30) >= 12 - ($NRcards + $offset))
	return 1;
      break;
    case "5th-card":
      if( 27+4*($what/30) <= $NRallcards + $offset*4)
	return 1;
      break;
    case "9-cards":

      if($oldcall!=NULL && $pcall!=NULL)
	$mincall = ($oldcall>$pcall) ? $pcall : $oldcall;
      else if($oldcall!=NULL)
	$mincall = $oldcall;
      else if ($pcall!=NULL)
	$mincall = $pcall;
      else
	$mincall = -1;

      if( 12 <= ($NRcards + $offset))
	{
	  return 1;
	}
      else if ( 9 <= ($NRcards + $offset))
	{
	  if( ($mincall>=0 && $mincall==120) )
	    return 1;
	}
      else if ( 6 <= ($NRcards + $offset))
	{
	  if( ($mincall>=0 && $mincall<=90 && $what<=60 ) )
	    return 1;
	}
      else if ( 3 <= ($NRcards + $offset))
	{
	  if( ($mincall>=0 && $mincall<=60 && $what<=30 ) )
	    return 1;
	}
      else if ( 0 <= ($NRcards + $offset))
	{
	  if( ($mincall>=0 && $mincall<=30 && $what==0 ) )
	    return 1;
	};
      break;
    }

  return 0;
}

function display_table ()
{
  global $gameid, $GT, $debug,$INDEX,$defaulttimezone;
  global $RULES,$GAME,$gametype;

  $result = DB_query("SELECT  User.fullname as name,".
		     "        Hand.position as position, ".
		     "        User.id, ".
		     "        Hand.party as party, ".
		     "        Hand.sickness as sickness, ".
		     "        Hand.point_call, ".
		     "        User.last_login, ".
		     "        Hand.hash,       ".
		     "        User.timezone    ".
		     "FROM Hand ".
		     "LEFT JOIN User ON User.id=Hand.user_id ".
		     "WHERE Hand.game_id='".$gameid."' ".
		     "ORDER BY position ASC");

  echo "<div class=\"table\">\n".
    "  <img class=\"table\" src=\"pics/table.png\" alt=\"table\" />\n";
  while($r = DB_fetch_array($result))
    {
      $name  = $r[0];
      $pos   = $r[1];
      $user  = $r[2];
      $party = $r[3];
      $sickness  = $r[4];
      $call      = $r[5];
      $hash      = $r[7];
      $timezone  = $r[8];
      date_default_timezone_set($defaulttimezone);
      $lastlogin = strtotime($r[6]);
      date_default_timezone_set($timezone);
      $timenow   = strtotime(date("Y-m-d H:i:s"));

      echo "  <div class=\"table".($pos-1)."\">\n";
      if(!$debug)
	echo "   $name \n";
      else
	echo "   <a href=\"".$INDEX."?action=game&me=".$hash."\">$name</a>\n";

      /* add hints for poverty, wedding, solo, etc */
      if( $gametype != "solo")
	if( $RULES["schweinchen"]=="both" && $GAME["schweinchen-who"]==$hash )
	  echo " Schweinchen. <br />";

      if($GT=="poverty" && $party=="re")
	if($sickness=="poverty")
	  {
	    $userhash = DB_get_hash_from_gameid_and_userid($gameid,$user);
	    $cards    = DB_get_all_hand($userhash);
	    $trumpNR  = count_trump($cards);
	    if($trumpNR)
	      echo "   <img src=\"pics/button/poverty_trump_button.png\" class=\"button\" alt=\"poverty < trump back\" />";
	    else
	      echo "   <img src=\"pics/button/poverty_notrump_button.png\" class=\"button\" alt=\"poverty <\" />";
	  }
	else
	  echo "   <img src=\"pics/button/poverty_partner_button.png\" class=\"button\" alt=\"poverty >\" />";

      if($GT=="dpoverty")
	if($party=="re")
	  if($sickness=="poverty")
	    {
	      $userhash = DB_get_hash_from_gameid_and_userid($gameid,$user);
	      $cards    = DB_get_all_hand($userhash);
	      $trumpNR  = count_trump($cards);
	      if($trumpNR)
		echo "   <img src=\"pics/button/poverty_trump_button.png\" class=\"button\" alt=\"poverty < trump back\" />";
	      else
		echo "   <img src=\"pics/button/poverty_notrump_button.png\" class=\"button\" alt=\"poverty <\" />";
	    }
	  else
	    echo "   <img src=\"pics/button/poverty_partner_button.png\" class=\"button\" alt=\"poverty >\" />";
	else
	  if($sickness=="poverty")
	    {
	      $userhash = DB_get_hash_from_gameid_and_userid($gameid,$user);
	      $cards    = DB_get_all_hand($userhash);
	      $trumpNR  = count_trump($cards);
	      if($trumpNR)
		echo "   <img src=\"pics/button/poverty2_trump_button.png\" class=\"button\" alt=\"poverty2 < trump back\" />";
	      else
		echo "   <img src=\"pics/button/poverty2_notrump_button.png\" class=\"button\" alt=\"poverty2 <\" />";
	    }
	  else
	    echo "   <img src=\"pics/button/poverty2_partner_button.png\" class=\"button\" alt=\"poverty2 >\" />";

      if($GT=="wedding" && $party=="re")
	if($sickness=="wedding")
	  echo "   <img src=\"pics/button/wedding_button.png\" class=\"button\" alt=\"wedding\" />";
	else
	  echo "   <img src=\"pics/button/wedding_partner_button.png\" class=\"button\" alt=\"wedding partner\" />";

      if(ereg("solo",$GT) && $party=="re")
	{
	  if(ereg("queen",$GT))
	    echo "   <img src=\"pics/button/queensolo_button.png\" class=\"button\" alt=\"$GT\" />";
	  else if(ereg("jack",$GT))
	    echo "   <img src=\"pics/button/jacksolo_button.png\" class=\"button\" alt=\"$GT\" />";
	  else if(ereg("club",$GT))
	    echo "   <img src=\"pics/button/clubsolo_button.png\" class=\"button\" alt=\"$GT\" />";
	  else if(ereg("spade",$GT))
	    echo "   <img src=\"pics/button/spadesolo_button.png\" class=\"button\" alt=\"$GT\" />";
	  else if(ereg("heart",$GT))
	    echo "   <img src=\"pics/button/heartsolo_button.png\" class=\"button\" alt=\"$GT\" />";
	  else if(ereg("trumpless",$GT))
	    echo "   <img src=\"pics/button/notrumpsolo_button.png\" class=\"button\" alt=\"$GT\" />";
	  else if(ereg("trump",$GT))
	    echo "   <img src=\"pics/button/trumpsolo_button.png\" class=\"button\" alt=\"$GT\" />";
	}

      /* add point calls */
      if($call!=NULL)
	{
	  if($party=="re")
	    echo "  <img src=\"pics/button/re_button.png\" class=\"button\" alt=\"re\" />";
	  else
	    echo "  <img src=\"pics/button/contra_button.png\" class=\"button\" alt=\"contra\" />";
	  switch($call)
	    {
	    case "0":
	      echo "   <img src=\"pics/button/0_button.png\" class=\"button\" alt=\"0\" />";
	      break;
	    case "30":
	      echo "   <img src=\"pics/button/30_button.png\" class=\"button\" alt=\"30\" />";
	      break;
	    case "60":
	      echo "   <img src=\"pics/button/60_button.png\" class=\"button\" alt=\"60\" />";
	      break;
	    case "90":
	      echo "   <img src=\"pics/button/90_button.png\" class=\"button\" alt=\"90\" />";
	      break;
	    }
	}

      echo "    <br />\n";
      echo "    <span title=\"".date("Y-m-d H:i:s",$timenow).  "\">local time</span>\n";
      echo "    <span title=\"".date("Y-m-d H:i:s",$lastlogin)."\">last login</span>\n";
      echo "   </div>\n";

    }
  echo  "</div>\n"; /* end output table */


  return;
}


function display_user_menu()
{
  global $WIKI,$myid,$INDEX;
  echo "<div class=\"usermenu\">\n";

  $result = DB_query("SELECT Hand.hash,Hand.game_id,Game.player from Hand".
		     " LEFT JOIN Game On Hand.game_id=Game.id".
		     " WHERE Hand.user_id='$myid'".
		     " AND Game.player='$myid'".
		     " AND Game.status<>'gameover'".
		     " ORDER BY Game.session" );
  if(DB_num_rows($result))
      echo "It's your turn in these games:<br />\n";

  $i=0;
  while( $r = DB_fetch_array($result))
    {
      $i++;
      echo "<a href=\"".$INDEX."?action=game&me=".$r[0]."\">game ".DB_format_gameid($r[1])." </a><br />\n";
      if($i>4)
	{
	  echo "...<br />\n";
	  break;
	}
    }

  echo  "</div>\n";
  return;
}

function generate_score_table($session)
{
  /* get all ids */
  $gameids = DB_get_gameids_of_finished_games_by_session($session);

  if($gameids == NULL)
    return "";

  $output = "<div class=\"scoretable\">\n<table class=\"score\">\n <tr>\n";


  /* get player id, names... from the first game */
  $player = array();
  $result = DB_query("SELECT User.id, User.fullname from Hand".
		     " LEFT JOIN User On Hand.user_id=User.id".
		     " WHERE Hand.game_id=".$gameids[0]);
  while( $r = DB_fetch_array($result))
    {
      $player[] = array( 'id' => $r[0], 'points' => 0 );
      $output.= "  <td> ".substr($r[1],0,2)." </td>\n";
    }
  $output.="  <td>P</td>\n </tr>\n";

  /* get points and generate table */
  foreach($gameids as $gameid)
    {
      $output.=" <tr>\n";

      $re_score = DB_get_score_by_gameid($gameid);
      foreach($player as $key=>$pl)
	{
	  $party = DB_get_party_by_gameid_and_userid($gameid,$pl['id']);
	  if($party == "re")
	    if(DB_get_gametype_by_gameid($gameid)=="solo")
	      $player[$key]['points'] += 3*$re_score;
	    else
	      $player[$key]['points'] += $re_score;
	  else if ($party == "contra")
	    $player[$key]['points'] -= $re_score;

	  $output.="  <td>".$player[$key]['points']."</td>\n";
	}
      $output.="  <td>".abs($re_score);

      /* check for solo */
      if(DB_get_gametype_by_gameid($gameid)=="solo")
	$output.= " S";
      $output.="</td>\n </tr>\n";
    }

  $output.="</table></div>\n";

  return $output;
}

function generate_global_score_table()
{
  /* get all ids */
  $gameids = DB_get_gameids_of_finished_games_by_session(0);

  if($gameids == NULL)
    return "";

  /* get player id, names... from the User table */
  $player = array();
  $result = DB_query("SELECT User.id, User.fullname FROM User");

  while( $r = DB_fetch_array($result))
    $player[] = array( 'id' => $r[0], 'name'=> $r[1], 'points' => 0 ,'nr' => 0);

  /* get points and generate table */
  foreach($gameids as $gameid)
    {
      $re_score = DB_get_score_by_gameid($gameid);
      /* TODO: this shouldn't loop over all players, just the 4 players that are in the game */
      foreach($player as $key=>$pl)
	{
	  $party = DB_get_party_by_gameid_and_userid($gameid,$pl['id']);
	  if($party == "re")
	    if(DB_get_gametype_by_gameid($gameid)=="solo")
	      $player[$key]['points'] += 3*$re_score;
	    else
	      $player[$key]['points'] += $re_score;
	  else if ($party == "contra")
	    $player[$key]['points'] -= $re_score;
	  if($party)
	    $player[$key]['nr']+=1;
	}
    }

  echo "<table>\n <tr>\n";
  function cmp($a,$b)
  {
    if($a['nr']==0 ) return 1;
    if($b['nr']==0) return 1;

    $a=$a['points']/$a['nr'];
    $b=$b['points']/$b['nr'];

    if ($a == $b)
      return 0;
    return ($a > $b) ? -1 : 1;
  }
  usort($player,"cmp");
  foreach($player as $pl)
    {
      if($pl['nr']>10)
	echo "  <tr><td>",$pl['name'],"</td><td>",round($pl['points']/$pl['nr'],3),"</td></tr>\n";
    }
  echo "</table>\n";

  return;
}



?>
