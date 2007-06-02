<?php

function mymail($To,$Subject,$message,$header="")
{  
  global $debug;

  if($debug)
    {
      $message = str_replace("\n","<br />\n",$message);
      $message = ereg_replace("[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]",
                     "<a href=\"\\0\">\\0</a>", $message);
      //$message = ereg_replace("(http.*)[ <>]","<a href=\"\\1\">\\1 </a>",$message);
      
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

  /* some special cases */
  switch($game)
    {
    case "normal":
    case "silent":
      if($RULES["schweinchen"]=="both" && $GAME["schweinchen"])
	{
	  if($a == 19 || $a == 20 )
	    return 1;
	  if($b == 19 || $b == 20 )
	    return 0;
	};
      if($RULES["schweinchen"]=="second" && $GAME["schweinchen"]==3)
	{
	  if($a == 19 || $a == 20 )
	    return 1;
	  if($b == 19 || $b == 20 )
	    return 0;
	};
    case "trump":
    case "heart":
    case "spade":
    case "club":
      if($RULES["dullen"]=="secondwins")
	if($a==1 && $b==1) /* both 10 of hearts */
	  return 0;        /* second one wins.*/
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

  /* count each trump */
  foreach($cards as $c)
    if( (int)($c) <27) 
      $trump++;

  switch($RULES["schweinchen"])
    {
    case "none":
      break;
    case "second":
    case "secondaftercall":
      /* add one, in case the player has both foxes (schweinchen) */
      if( in_array("19",$cards) && in_array("20",$cards) )
	$trump++;
    case "both":
      /* subtract foxes */
      if( in_array("19",$cards))
	$trump--;
      if( in_array("20",$cards) )
	$trump--;
      break;
    }

  return $trump;
}

function card_to_name($card)
{
  switch($card)
    {
      case 1:
      case 2:
        return "ten of hearts";
      case 3:
      case 4:
      return "queen of clubs";
      case 5:
      case 6:
      return "queen of spades";
      case 7:
      case 8:
      return "queen of hearts";
      case 9:
      case 10:
      return "queen of diamonds";
      case 11:
      case 12:
      return "jack of clubs";
      case 13:
      case 14:
      return "jack of spades";
      case 15:
      case 16:
      return "jack of hearts";
      case 17:
      case 18:
      return "jack of diamonds";
      case 19:
      case 20:
      return "ace of diamonds";
      case 21:
      case 22:
      return "ten of diamonds";
      case 23:
      case 24:
      return "king of diamonds";
      case 25:
      case 26:
      return "nine of diamonds";;
      case 27:
      case 28:
      return "ace of clubs";
      case 29:
      case 30:
      return "ten of clubs";
      case 31:
      case 32:
      return "king of clubs";
      case 33:
      case 34:
      return "nine of clubs";
      case 35:
      case 36:
      return "ace of spades";
      case 37:
      case 38:
      return "ten of spades";
      case 39:
      case 40:
      return "king of spades";
      case 41:
      case 42:
      return "nine of spades";
      case 43:
      case 44:
      return "ace of hearts";
      case 45:
      case 46:
      return "king of hearts";
      case 47:
      case 48:
      return "nine of hearts";
      default:
      return "something went wrong, please contact the admin. Error: code1. $card <br />";
    }
}

function card_value($card)
{
  switch($card)
    {
    case 3:     /* clubes */	 
    case 4:	                 
    case 5:     /* spades */	 
    case 6:	                 
    case 7:     /* hearts */	 
    case 8:	                 
    case 9:     /* diamonds */	 
    case 10:                     
      return 3;
    case 11:    /* clubes */	 
    case 12:	                 
    case 13:	/* spades */	 
    case 14:	                 
    case 15:	/* hearts */	 
    case 16:	                 
    case 17:	/* diamonds */	 
    case 18:
      return 2;	                 
    case 19:    /* diamonds */ 
    case 20:	               
    case 27:    /* clubs */    
    case 28:	               
    case 35:    /* spades */   
    case 36:	               
    case 43:    /* hearts */   
    case 44:                   
      return 11;
    case 1:      /* heart */
    case 2:
    case 21:    /* diamonds */    
    case 22:
    case 29:    /* clubs */
    case 30:
    case 37:    /* spades */
    case 38:
      return 10;
    case 23:    /* diamonds */ 
    case 24:	               
    case 31:	/* clubs */    
    case 32:	               
    case 39:	/* spades */   
    case 40:	               
    case 45:	/* hearts */   
    case 46:	               
      return 4;
    case 25:    /* diamonds */   
    case 26:	               
    case 33:	/* clubs */    
    case 34:	               
    case 41:	/* spades */   
    case 42:	               
    case 47:	/* hearts */   
    case 48:	               
      return 0;
    default:
      echo "something went wrong, please contact the admin. ErrorCode: 2 - $card<br>";
      return 0;
    }
}


function  create_array_of_random_numbers()
{
  global $debug;

  $r = array();
  
  if($debug)
    {
      $r[ 0]=1;     $r[12]=47;   $r[24]=13;       $r[36]=37;
      $r[ 1]=2;     $r[13]=48;   $r[25]=14;	  $r[37]=38;
      $r[ 2]=3;     $r[14]=27;   $r[26]=15;	  $r[38]=39;
      $r[ 3]=4;     $r[15]=16;   $r[27]=28;	  $r[39]=40;
      $r[ 4]=5;     $r[16]=17;   $r[28]=29;	  $r[40]=41;
      $r[ 5]=18;    $r[17]=6;    $r[29]=30;	  $r[41]=42;
      $r[ 6]=19;    $r[18]=7;    $r[30]=31;	  $r[42]=43;
      $r[ 7]=20;    $r[19]=8;    $r[31]=32;	  $r[43]=44;
      $r[ 8]=45;    $r[20]=9;    $r[32]=21;	  $r[44]=33;
      $r[ 9]=46;    $r[21]=10;   $r[33]=22;	  $r[45]=34;
      $r[10]=35;    $r[22]=11;   $r[34]=23;	  $r[46]=25;
      $r[11]=36;    $r[23]=12;   $r[35]=24;	  $r[47]=26;
    }
  else
    {
      for($i=0;$i<48;$i++)
	$r[$i]=$i+1;
      
      shuffle($r);
    };

  return $r;
}




function display_cards($me,$myturn)
{
  return;
}

function return_timezone($offset)
{
  switch($offset)
    {
    case '1':
      $zone = "Europe/Berlin";
      break;
    case '-8':
      $zone = "America/Vancouver";
      break;
    case '13':
      $zone = "Pacific/Auckland";
      break;
    default:
      $zone = "Europe/London";
    }
  
  return $zone;
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
		     $CARDS["hearts"],$CARDS["spades"],$CARDS["diamonds"]);

  return pos_array($a,$ALL)-pos_array($b,$ALL);
}

function can_call($what,$hash)
{
  global $RULES;

  /*TODO: check if this already has been call by teammate */
  
  $gameid   = DB_get_gameid_by_hash($hash);
  $gametype = DB_get_gametype_by_gameid($gameid);

  $NRcards  = count(DB_get_hand($me));
  
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
      if( 4-($what/30) >= 12 - $NRcards + $offset)
	return 1;
      break;
    case "5th-card":
      if( 27+4*($what/30) <= $NRallcards + $offset*4)
	return 1;
      break;
    case "9-cards":
      if( ($what/10) <= $NRcards + $offset)
	return 1;
      break;
    }

  return 0;
}

?>
