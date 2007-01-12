<?php

function mymail($To,$Subject,$message)
{  
  global $debug;

  if($debug)
    {
      $message = str_replace("\n","<br />",$message);
      echo "<br />To: $To<br />Subject: $Subject <br />$message<br />\n";
    }
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
      /* echo "$arg: ok = $ok <br />";
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
  global $TRUMP;

  if(in_array($c,$TRUMP))
    return 1;
  else 
    return 0;
}

function is_same_suite($c1,$c2) 
{
  global $TRUMP,$DIAMONDS,$HEARTS,$CLUBS,$SPADES;
  
  if(in_array($c1,$TRUMP   ) && in_array($c2,$TRUMP   ) ) return 1;
  if(in_array($c1,$CLUBS   ) && in_array($c2,$CLUBS   ) ) return 1;
  if(in_array($c1,$HEARTS  ) && in_array($c2,$HEARTS  ) ) return 1;
  if(in_array($c1,$SPADES  ) && in_array($c2,$SPADES  ) ) return 1;
  if(in_array($c1,$DIAMONDS) && in_array($c2,$DIAMONDS) ) return 1;
  
  return 0;
}

function compare_cards($a,$b,$game)
{
  /* if "a" is higher than "b" return 1, else 0, "a" being the card first played */

  global $TRUMP,$DIAMONDS,$HEARTS,$CLUBS,$SPADES;

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
      if($a==1 && $b==1) /* both 10 of hearts */
	return 0;        /* second one wins. TODO should be able to set this at the start of a new game */
    }
  
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
	  $posA = pos_array($a,$CLUBS);
	  $posB = pos_array($b,$CLUBS);
	  if($posA && $posB)
	    if($posA <= $posB)
	      return 1;
	    else
	      return 0;

	  /* both spades? */
	  $posA = pos_array($a,$SPADES);
	  $posB = pos_array($b,$SPADES);
	  if($posA && $posB)
	    if($posA <= $posB)
	      return 1;
	    else
	      return 0;

	  /* both hearts? */
	  $posA = pos_array($a,$HEARTS);
	  $posB = pos_array($b,$HEARTS);
	  if($posA && $posB)
	    if($posA <= $posB)
	      return 1;
	    else
	      return 0;

	  /* both diamonds? */
	  $posA = pos_array($a,$DIAMONDS);
	  $posB = pos_array($b,$DIAMONDS);
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
  /* get all 4 cards played in a trick */
  $c1 = $p[1];
  $c2 = $p[2];
  $c3 = $p[3];
  $c4 = $p[4];


  /* first card is better than all the rest */
  if( compare_cards($c1,$c2,$mode) && compare_cards($c1,$c3,$mode) && compare_cards($c1,$c4,$mode) )
    return 1; 

  /* second card is better than first and better than the rest */
  if( !compare_cards($c1,$c2,$mode) &&  compare_cards($c2,$c3,$mode) && compare_cards($c2,$c4,$mode) )
    return 2;

  /* third card is better than first card and better than last */
  if( !compare_cards($c1,$c3,$mode) &&  compare_cards($c3,$c4,$mode) )
    /* if second card is better than first, third card needs to be even better */
    if( !compare_cards($c1,$c2,$mode) && !compare_cards($c2,$c3,$mode) )
      return 3;
    /* second is worse than first, e.g. not following suite */
    else if (compare_cards($c1,$c2,$mode) )
      return 3;

  /* non of the above */
  return 4;
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
  $trump = 0;

  /* count each trump */
  foreach($cards as $c)
    if( (int)($c) <27) 
      $trump++;

  /* subtract foxes */
  if( in_array("19",$cards))
    $trump--;
  if( in_array("20",$cards) )
    $trump--;
  /* add one, in case the player has both foxes (schweinchen) */
  if( in_array("19",$cards) && in_array("20",$cards) )
    $trump++;

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
      return "something went wrong, please contact the admin. Error: code1.";
    }
}

function card_value($card)
{
  switch($card)
    {
    case 1:      /* heart */
    case 2:
      return 10;
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
  $r = array();
  $a = array();
  
  for($i=1;$i<49;$i++)
    $a[$i]=$i;
  
  $r = array_rand($a,48);
   
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
  global $TRUMP,$DIAMONDS,$HEARTS,$CLUBS,$SPADES;
  $suit = array();

  if(in_array($c,$TRUMP))
    $suit = $TRUMP;
  else if(in_array($c,$CLUBS))
    $suit = $CLUBS;
  else if(in_array($c,$SPADES))
    $suit = $SPADES;
  else if(in_array($c,$HEARTS))
    $suit = $HEARTS;
  else if(in_array($c,$DIAMONDS))
    $suit = $DIAMONDS;

  foreach($cards as $card)
    {
      if(in_array($card,$suit))
	return 1;
    }

  return 0;
}

function same_type($card,$c)
{
  global $TRUMP,$DIAMONDS,$HEARTS,$CLUBS,$SPADES;
  $suit = "";

  /* figure out what kind of card c is */
  if(in_array($c,$TRUMP))
    $suit = $TRUMP;
  else if(in_array($c,$CLUBS))
    $suit = $CLUBS;
  else if(in_array($c,$SPADES))
    $suit = $SPADES;
  else if(in_array($c,$HEARTS))
    $suit = $HEARTS;
  else if(in_array($c,$DIAMONDS))
    $suit = $DIAMONDS;

  /* card is the same suid return 1 */ 
  if(in_array($card,$suit))
    return 1;
  
  return 0;
}

function set_gametype($gametype)
{
  global $TRUMP,$DIAMONDS,$HEARTS,$CLUBS,$SPADES;

  switch($gametype)
    {
    case "normal":
    case "trump":
    case "silent":
      $TRUMP    = array('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16', 
                        '17','18','19','20','21','22','23','24','25','26');
      $DIAMONDS = array();
      $CLUBS    = array('27','28','29','30','31','32','33','34');
      $SPADES   = array('35','36','37','38','39','40','41','42');
      $HEARTS   = array('43','44','45','46','47','48');
      break;
    case "queen":
      $TRUMP    = array('3','4','5','6','7','8','9','10');
      $CLUBS    = array('27','28','29','30','31','32','11','12','33','34');
      $SPADES   = array('35','36','37','38','39','40','13','14','41','42');
      $HEARTS   = array('43','44', '1', '2','45','46','15','16','47','48');
      $DIAMONDS = array('19','20','21','22','23','24','17','18','25','26');
      break;
    case "jack":
      $TRUMP    = array('11','12','13','14','15','16','17','18');
      $CLUBS    = array('27','28','29','30','31','32','3', '4','33','34');
      $SPADES   = array('35','36','37','38','39','40','5', '6','41','42');
      $HEARTS   = array('43','44', '1', '2','45','46','7', '8','47','48');
      $DIAMONDS = array('19','20','21','22','23','24','9','10','25','26');
      break;
    case "trumpless":
      $TRUMP    = array();
      $CLUBS    = array('27','28','29','30','31','32','3', '4','11','12','33','34');
      $SPADES   = array('35','36','37','38','39','40','5', '6','13','14','41','42');
      $HEARTS   = array('43','44', '1', '2','45','46','7', '8','15','16','47','48');
      $DIAMONDS = array('19','20','21','22','23','24','9','10','17','18','25','26');
      break;
    case "club":
      $TRUMP    = array('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16', 
                        '17','18','27','28','29','30','31','32','33','34');
      $CLUBS    = array();
      $SPADES   = array('35','36','37','38','39','40','41','42');
      $HEARTS   = array('43','44','45','46','47','48');
      $DIAMONDS = array('19','20','21','22','23','24','25','26');
      break;
    case "spade":
      $TRUMP    = array('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16', 
                        '17','18','35','36','37','38','39','40','41','42');
      $CLUBS    = array('27','28','29','30','31','32','33','34');
      $SPADES   = array();
      $HEARTS   = array('43','44','45','46','47','48');
      $DIAMONDS = array('19','20','21','22','23','24','25','26');
      break;
    case "heart":
      $TRUMP    = array('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16', 
                        '17','18','43','44','45','46','47','48');
      $CLUBS    = array('27','28','29','30','31','32','33','34');
      $SPADES   = array('35','36','37','38','39','40','41','42');
      $HEARTS   = array();
      $DIAMONDS = array('19','20','21','22','23','24','25','26');
      break;
    }
}

?>