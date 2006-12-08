<?php

/*
 * config 
 */

$host  = "http://doko.nubati.net/database/index.php";
$wiki  = "http://wiki.nubati.net/index.php?title=EmailDoko";
$debug = 0;

$last=-2;

/*
 * end config
 */	


/* helper function */
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

function is_trump($c) { return (($c<27) ? 1:0);}
function is_club($c)  { return (in_array($c,array('27','28','29','30','31','32','33','34')));}
function is_spade($c) { return (in_array($c,array('35','36','37','38','39','40','41','42')));}
function is_heart($c) { return (in_array($c,array('43','44','45','46','47','48')));}

function compare_cards($a,$b)
{
  /* if a is higher than b return 1, else 0, a being the card first played */
  
  /* first map all cards to the odd number */
  if( $a/2 - (int)($a/2) != 0.5)
    $a--;
  if( $b/2 - (int)($b/2) != 0.5)
    $b--;
  
  if(is_trump($a) && $a<=$b)
    return 1;
  else if(is_trump($a) && $a>$b)
    return 0;
  else 
    { /*$a is not a trump */
      if(is_trump($b))
	return 0;
      else
	{
	  /* both clubs? */
	  if( is_club($a) && is_club($b))
	    if($a<=$b)
	      return 1;
	    else
	      return 0;
	  /* both spade? */
	  if( is_spade($a) && is_spade($b))
	    if($a<=$b)
	      return 1;
	    else
	      return 0;
	  /* both heart? */
	  if( is_heart($a) && is_heart($b))
	    if($a<=$b)
	      return 1;
	    else
	      return 0;
      return 1;
	}	  
    }
      
} 

function get_winner($p)
{
  /* get all 4 cards played in a trick */
  $c1 = $p[1];
  $c2 = $p[2];
  $c3 = $p[3];
  $c4 = $p[4];

  /* find out who won */
  if( compare_cards($c1,$c2) && compare_cards($c1,$c3) && compare_cards($c1,$c4) )
    return 1;
  if( compare_cards($c2,$c3) && compare_cards($c2,$c4) )
    return 2;
  if( compare_cards($c3,$c4) )
    return 3;
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

  if( in_array("3",$cards) && in_array("2",$cards) )
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
      echo "something went wrong, please contact the admin. ErrorCode: 2<br>";
      return 0;
    }
}

function display_card($card)
{
  /* cards are only availabl for the odd values, e.g. 1.png, 3.png, ... 
   * convert even cards to the matching odd value */

  if( $card/2 - (int)($card/2) == 0.5)
    echo "<img src=\"cards/".$card.".png\"  alt=\"".card_to_name($card)."\" />\n";
  else
    echo "<img src=\"cards/".($card-1).".png\"  alt=\"".card_to_name($card-1)."\" />\n";

  return;
}

function display_link_card($card)
{
  if( $card/2 - (int)($card/2) == 0.5)
    echo "<input type=\"radio\" name=\"card\" value=\"".$card."\" /><img src=\"cards/".$card.".png\" alt=\"\" />\n";
  else
    echo "<input type=\"radio\" name=\"card\" value=\"".$card."\" /><img src=\"cards/".($card-1).".png\" alt=\"\" />\n";
  return;
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

function check_want_to_play($me)
{
   ?>
 <form action="index.php" method="post">
   Do you want to play a game of DoKo?
   yes<input type="radio" name="in" value="yes" />
   no<input type="radio" name="in" value="no" /> <br />

   Do you want to get an email for every card played or only if it your move?
   every card<input type="radio" name="update" value="card" />
   only on my turn<input type="radio" name="update" value="turn" /> <br />
<?php   
  echo "<input type=\"hidden\" name=\"me\" value=\"$me\" />\n";
  echo "\n";
  echo "<input type=\"submit\" value=\"count me in\" />\n";
  echo " </form>\n";

  return;
}

function check_for_sickness($me,$mycards)
{
 ?>
  <p> nothing implemented so far, but give it a try anyway ;) </p>	 	  

  <form action="index.php" method="post">

    do you want to play solo? 
    <select name="solo" size="1">
      <option>No</option>
      <option>trumpless</option>
      <option>trump</option>
      <option>queen</option>
      <option>jack</option>
      <option>club</option>
      <option>spade</option>
      <option>heart</option>
    </select>     
    <br />

 <?php   
      
   echo "wedding?";
  if(check_wedding($mycards))
     {
       echo " yes<input type=\"radio\" name=\"wedding\" value=\"yes\" />";
       echo " no <input type=\"radio\" name=\"wedding\" value=\"no\" /> <br />\n";
     }
   else
     {
       echo " no <input type=\"hidden\" name=\"wedding\" value=\"no\" /> <br />\n";
     };

  echo "do you have poverty?";
  if(count_trump($mycards)<4)
    {
      echo " yes<input type=\"radio\" name=\"poverty\" value=\"yes\" />";
      echo " no <input type=\"radio\" name=\"poverty\" value=\"no\" /> <br />\n";
    }
  else
    {
      echo " no <input type=\"hidden\" name=\"poverty\" value=\"no\" /> <br />\n";
    };

   echo "do you have too many nines?";
  if(count_nines($mycards)>4)
     {
       echo " yes<input type=\"radio\" name=\"nines\" value=\"yes\" />";
       echo " no <input type=\"radio\" name=\"nines\" value=\"no\" /> <br />\n";
     }
   else
     {
       echo " no <input type=\"hidden\" name=\"nines\" value=\"no\" /> <br />\n";
     };

   echo "<input type=\"hidden\" name=\"me\" value=\"$me\" />\n";
   echo "<input type=\"submit\" value=\"count me in\" />\n";

   echo "</form>\n";

  return;
}

function display_status()
{
  echo "<div class=\"info\">";
  echo " is someone playing solo, etc?";
  echo "</div>";
  
  return;
}

function display_news()
{
  global $wiki;
  echo "<div class=\"bug\"> ".
    "Please hit shift+reload.<br /><hr />".
    "New Database backend, lost a few features on the way.<br /><hr />".
    "If you find more bugs, please list them in the <a href=\"".$wiki.
    "\">wiki</a>.</div>\n";
  return;
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
    default:
      $zone = "Europe/London";
    }
  
  return $zone;
}

?>