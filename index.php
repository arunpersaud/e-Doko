<!DOCTYPE html PUBLIC
    "-//W3C//DTD XHTML 1.1 plus MathML 2.0 plus SVG 1.1//EN"
    "http://www.w3.org/2002/04/xhtml-math-svg/xhtml-math-svg.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
  <head>
     <title>e-Doko</title>
     <meta content="text/html; charset=ISO-8859-1" http-equiv="content-type" />
     <link rel="stylesheet" type="text/css" href="standard.css" />	
     <script type="text/javascript">
       function hl(num) {
         if(document.getElementById){
	   var i;
	   for(i=1;i<13;i++){
	     if(document.getElementById("trick"+i))
	       document.getElementById("trick"+i).style.display = 'none';
	   }
	   document.getElementById("trick"+num).style.display = 'block';
	 }
       }
       function high_last(){
	 if(document.getElementById){
	   var i;
	   for(i=12;i>0;i--) {
	     if(document.getElementById("trick"+i))
	       {
		 hl(i);
		 break;
	       }
	   }
	 }
       }
     </script>
  </head>
<body onload="high_last();">
<div class="header">
<h1> Welcome to E-Doko </h1>
<?php

/*
 * config 
 */

$host  = "http://doko.nubati.net/index.php";
$wiki  = "http://wiki.nubati.net/index.php?title=EmailDoko";
$debug = 1;

$last=-2;

/*
 * end config
 */	

echo "</div>\n";

/* end header */

					
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
  $c1 = $p[0];
  $c2 = $p[1];
  $c3 = $p[2];
  $c4 = $p[3];

  /* find out who won */
  if( compare_cards($c1,$c2) && compare_cards($c1,$c3) && compare_cards($c1,$c4) )
    return 0;
  if( compare_cards($c2,$c3) && compare_cards($c2,$c4) )
    return 1;
  if( compare_cards($c3,$c4) )
    return 2;
  return 3;
}

function parse_status()
{
  global $game,$history,$player,$hash,$lines;
  
  $game["init"]        = 0;
  $game["solo-who"]    = -1;
  $game["solo-what"]   = "todo";
  $game["wedding"]     = -1;
  $game["poverty"]     = "";
  $game["nines"]       = -1;
  $game["startplayer"] = 0;

  for($i=0;$i<4;$i++)
    {
      $tmp = explode( ":",$lines[$i]);
      $hash[$i]   = $tmp[0];
      $player[$tmp[0]]["number"] = $i;
      $player[$tmp[0]]["hash"]   = $tmp[0];
      $player[$tmp[0]]["name"]   = $tmp[1];
      $player[$tmp[0]]["email"]  = $tmp[2];
      $player[$tmp[0]]["option"] = $tmp[3];
      $player[$tmp[0]]["points"] = $tmp[4]; 
      $player[$tmp[0]]["cards"]  = $tmp[5];
      if(ereg("s",$tmp[3])) $game["init"]++;       /* how many players are ready? */
      if(ereg("P",$tmp[3])) $game["poverty"].= $i; /* players with poverty, could be two, so use a string */
      if(ereg("N",$tmp[3])) $game["nines"]   = $i; /* the player with too many nines, only one possible */
      if(ereg("W",$tmp[3])) $game["wedding"] = $i; /* the player with the wedding, also only one possible */
      if(ereg("([OSQJCAH])",$tmp[3],$match) && ($game["solo-who"]<0) )
	{
	  $game["solo-who"]    = $i;     
	  $game["startplayer"] = $i;
	  switch($match[1])
	    {
	    case "O":
	      $game["solo-what"] = "No Trump";
	    case "S":
	      $game["solo-what"] = "Trump";
	    case "Q":
	      $game["solo-what"] = "Queen";
	    case "J":
	      $game["solo-what"] = "Jack";
	    case "C":
	      $game["solo-what"] = "Club";
	    case "A":
	      $game["solo-what"] = "Spade";
	    case "H":
	      $game["solo-what"] = "Heart";
	    }
	}
  
    }  
  /* save the game history */
  for($i=4;$i<sizeof($lines);$i++)
    if(!ereg("^[[:space:]]*$",trim($lines[$i])))
      $history[] = $lines[$i];
  
  if(sizeof($history)==0 || (sizeof($history)==1 && strlen($history[0])==3 ))
    $history[0] = $game["startplayer"].":";
  
  return;
}

function count_nines($cards)
{
  $card  = explode(";",$cards);
  $nines = 0;

  foreach($card as $c)
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
  $card  = explode(";",$cards);

  if( in_array("3",$card) && in_array("2",$card) )
    return 1;

  return 0;
}

function count_trump($cards)
{
  $card  = explode(";",$cards);
  $trump = 0;

  /* count each trump */
  foreach($card as $c)
    if( (int)($c) <27) 
      $trump++;

  /* subtract foxes */
  if( in_array("19",$card))
    $trump--;
  if( in_array("20",$card) )
    $trump--;
  /* add one, in case the player has both foxes (schweinchen) */
  if( in_array("19",$card) && in_array("20",$card) )
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

function display_link_card($card,$me)
{
  if( $card/2 - (int)($card/2) == 0.5)
    echo "<input type=\"radio\" name=\"card\" value=\"".$card."\" /><img src=\"cards/".$card.".png\" alt=\"\" />\n";
  else
    echo "<input type=\"radio\" name=\"card\" value=\"".$card."\" /><img src=\"cards/".($card-1).".png\" alt=\"\" />\n";
  return;
}

function save_status()
{
  global $player,$game,$hash,$history;

  $output = fopen("status.txt","w");
  if ($output)
    {
      foreach($hash as $key)
	{
	  /* sorting the options, not sure why I do that actually */
	  $tmp="";
	  if( ereg("i",$player[$key]["option"]) )
	    $tmp.="i";
	  if( ereg("s",$player[$key]["option"]) )
	    $tmp.="s";
	  if( ereg("t",$player[$key]["option"]) )
	    $tmp.="t";
	  if( ereg("c",$player[$key]["option"]) )
	    $tmp.="c";
	  if( ereg("N",$player[$key]["option"]) )
	    $tmp.="N";
	  if( ereg("W",$player[$key]["option"]) )
	    $tmp.="W";
	  if( ereg("P",$player[$key]["option"]) )
	    $tmp.="P";
	  if( ereg("O",$player[$key]["option"]) ) 
	    $tmp.="O";
	  if( ereg("S",$player[$key]["option"]) )
	    $tmp.="S";
	  if( ereg("Q",$player[$key]["option"]) )
	    $tmp.="Q";
	  if( ereg("J",$player[$key]["option"]) )
	    $tmp.="J";
	  if( ereg("C",$player[$key]["option"]) )
	    $tmp.="C";
	  if( ereg("A",$player[$key]["option"]) )
	    $tmp.="A";
	  if( ereg("H",$player[$key]["option"]) )
	    $tmp.="H";
	  $player[$key]["option"]=$tmp;

	  /* saving the player stats */
	  fwrite($output,"".$player[$key]["hash"].":" );
	  fwrite($output,"".$player[$key]["name"].":" );
	  fwrite($output,"".$player[$key]["email"].":" );
	  fwrite($output,"".$player[$key]["option"].":" );
	  fwrite($output,"".$player[$key]["points"].":" );
	  fwrite($output,"".$player[$key]["cards"] .":");
	  fwrite($output,"\n");
	}
      fwrite($output,"\n");
      foreach($history as $line)
	fwrite($output,$line);

      fwrite($output,"\n");
      fclose($output);
    }
  else
    echo "can't open file for writing, please inform the admin.errorcode3";
  
  return;
}

/*****************  M A I N **************************/


$history=array();

/* check for status file and read it, if possible */

if(file_exists("status.txt"))
  $lines = file("status.txt");
else
  die("no file");

/* check if we want to start a new game */
if( isset($_REQUEST["PlayerA"]) && 
    isset($_REQUEST["PlayerB"]) && 
    isset($_REQUEST["PlayerC"]) && 
    isset($_REQUEST["PlayerD"]) && 
    isset($_REQUEST["EmailA"]) && 
    isset($_REQUEST["EmailB"]) && 
    isset($_REQUEST["EmailC"]) && 
    isset($_REQUEST["EmailD"]) && sizeof($lines<2))
  {
    $PlayerA = $_REQUEST["PlayerA"];
    $PlayerB = $_REQUEST["PlayerB"];
    $PlayerC = $_REQUEST["PlayerC"];
    $PlayerD = $_REQUEST["PlayerD"];
    $EmailA  = $_REQUEST["EmailA"] ;
    $EmailB  = $_REQUEST["EmailB"] ;
    $EmailC  = $_REQUEST["EmailC"] ;
    $EmailD  = $_REQUEST["EmailD"] ;
    
    $hashA = md5("AGameOfDoko".$PlayerA.$EmailA);
    $hashB = md5("AGameOfDoko".$PlayerB.$EmailB);
    $hashC = md5("AGameOfDoko".$PlayerC.$EmailC);
    $hashD = md5("AGameOfDoko".$PlayerD.$EmailD);

    /* send out email, check for error with email */

    $message = "\n".
      "you are invited to play a game of DoKo (that is to debug the program ;).\n".
      "Place comments and bug reports here:\n".
      "http://wiki.nubati.net/index.php?title=EmailDoko\n\n".
      "The whole round would consist of the following players:\n".
      "$PlayerA\n".
      "$PlayerB\n".
      "$PlayerC\n".
      "$PlayerD\n\n".
      "If you want to join this game, please follow this link:\n\n".
      " ".$host."?a=";
    
    mymail($EmailA,"You are invited to a game of DoKo","Hello $PlayerA,\n".$message.$hashA);
    mymail($EmailB,"You are invited to a game of DoKo","Hello $PlayerB,\n".$message.$hashB);
    mymail($EmailC,"You are invited to a game of DoKo","Hello $PlayerC,\n".$message.$hashC);
    mymail($EmailD,"You are invited to a game of DoKo","Hello $PlayerD,\n".$message.$hashD);
    
    /* read in random.txt */
    if(file_exists("random.txt"))
      $random = file("random.txt");
    else
      die("no random file");
 
    $randomNR = explode( ":", $random[2] );
    
    /* write initial status into file */
    $output = fopen("status.txt","w");
    if ($output)
      {
	fwrite($output, "$hashA:$PlayerA:$EmailA:::" );
	for($i=0;$i<11;$i++)
	  fwrite($output,"$randomNR[$i];" );
	fwrite($output,"$randomNR[11]:" ); $i++;
	fwrite($output,"\n");
	
	fwrite($output, "$hashB:$PlayerB:$EmailB:::" );
	for(;$i<23;$i++)
	  fwrite($output,"$randomNR[$i];" );
	fwrite($output,"$randomNR[23]:" ); $i++;
	fwrite($output,"\n");
	
	fwrite($output, "$hashC:$PlayerC:$EmailC:::" );
	for(;$i<35;$i++)
	  fwrite($output,"$randomNR[$i];" );
	fwrite($output,"$randomNR[35]:" ); $i++;
	fwrite($output,"\n");
	
	fwrite($output, "$hashD:$PlayerD:$EmailD:::");
	for(;$i<47;$i++)
	  fwrite($output,"$randomNR[$i];" );
	fwrite($output,"$randomNR[47]:" );
	fwrite($output,"\n");
	
	fclose($output);
      }
    else
      echo "can't open file for writing";
  };
/* reread file */
if(file_exists("status.txt"))
  $lines = file("status.txt");
 else
   die("no file");

/* test if a game is running, else output everything for a new game */
if(sizeof($lines)<2)
  {
?>
    <p> no game in progress, please input 4 names and email addresses, please make sure that the addresses are correct! </p>
 <form action="index.php" method="post">
   Name:  <input name="PlayerA" type="text" size="10" maxlength="20" /> 
   Email: <input name="EmailA"  type="text" size="20" maxlength="30" /> <br />

   Name:  <input name="PlayerB" type="text" size="10" maxlength="20" /> 
   Email: <input name="EmailB"  type="text" size="20" maxlength="30" /> <br />

   Name:  <input name="PlayerC" type="text" size="10" maxlength="20" /> 
   Email: <input name="EmailC"  type="text" size="20" maxlength="30" /> <br />

   Name:  <input name="PlayerD" type="text" size="10" maxlength="20" /> 
   Email: <input name="EmailD"  type="text" size="20" maxlength="30" /> <br />

   <input type="submit" value="start game" />
 </form>
<?php
   }
else
  { /* load game status */
    parse_status();
/*     **
 *    *  *
 *    ****
 *    *  *
 *
 * check if a player wants to accept a game 
 */
    if(isset($_REQUEST["a"]))
      {
	$a = $_REQUEST["a"];
	
	if( ereg("[is]",$player[$a]["option"]) &&  $game["init"]<4)
	  {
	    echo "just wait for the game to start";
	  }
	else if( !ereg("[is]",$player[$a]["option"]) )
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
	     echo "<input type=\"hidden\" name=\"b\" value=\"$a\" />\n";
	     echo "\n";
	     echo "<input type=\"submit\" value=\"count me in\" />\n";
	     echo " </form>\n";
	   }
       }
/*   ***
 *   *  *
 *   ***
 *   *  *
 *   ***
 * yes? email him his hand, ask for solo, poverty, email every move or every card? 
 */
     if(isset($_REQUEST["b"]))
       {
	 $b = $_REQUEST["b"];
	 
	 if( ereg("s",$player[$b]["option"])  && $game["init"]<4)
	   { /* the player already filled out everything */
	     echo "just wait for the game to start";
	   }
	 else if( (!isset($_REQUEST["in"])|| !isset($_REQUEST["update"])) && !ereg("i",$player[$b]["option"]))
	   { /* the player didn't fill out the form at "a" correctly */
	     echo "go back to ";
	     echo "<a href=\"index.php?a=$b\"> here and fill out the form </a> <br />\n";
	   }
	 else
	   { /* show the hand and check if the player is sick*/
	     if($_REQUEST["in"]=="no")
	       { /* player doesn't want to play, cancel the game */
		 for($i=0;$i<4;$i++)
		   {
		     $message = "Hello ".$player[$hash[$i]]["name"].",\n\n".
		       "the game has been canceled due to the request of one of the players.\n";
		     mymail($player[$hash[$i]]["email"],"[DoKo-Debug] the game has been canceled",$message); 
		   }
		 /* canceling the game is as easy as removing the contents of the status file*/
		 $output = fopen("status.txt","w");
		 if($output)
		   fclose($output);
		 else
		   echo "problem opening file";
	       }
	     else
	       {
		 /* player wants to play, save information from "a"*/
		 if($_REQUEST["update"]=="card") 
		   $player[$b]["option"] .= "c";
		 else
		   $player[$b]["option"] .= "t";
		 
		 $player[$b]["option"] .= "i"; /* player finished stage "a" */
		 
		 save_status();
		 
		 $allcards = $player[$b]["cards"];
		 $mycards  = explode(";",$allcards);
		 
		 sort($mycards);
		 echo "<p class=\"mycards\">your cards are: <br />\n";
		 foreach($mycards as $card) 
		   display_card($card);
		 echo "</p>\n";   
 ?>
 <p> aehm... at the moment poverty is not implented. so I guess you need to play a normal game, even if you have less than 3 trump :(...sorry </p>	 	  

 <form action="index.php" method="post">

   do you want to play solo? 
   <select name="solo" size="1">
     <option>No</option>
     <option>No trump</option>
     <option>Normal solo</option>
     <option>Queen solo</option>
     <option>Jack solo</option>
     <option>Club solo</option>
     <option>Spade solo</option>
     <option>Heart solo</option>
   </select>     
   <br />

<?php   
     
                 echo "wedding?";
		 if(check_wedding($player[$b]["cards"]))
		   {
		     echo " yes<input type=\"radio\" name=\"wedding\" value=\"yes\" />";
		     echo " no <input type=\"radio\" name=\"wedding\" value=\"no\" /> <br />\n";
		   }
		 else
		   {
		     echo " no <input type=\"hidden\" name=\"wedding\" value=\"no\" /> <br />\n";
		   };

                 echo "do you have poverty?";
		 if(count_trump($player[$b]["cards"])<4)
		   {
		     echo " yes<input type=\"radio\" name=\"poverty\" value=\"yes\" />";
		     echo " no <input type=\"radio\" name=\"poverty\" value=\"no\" /> <br />\n";
		   }
		 else
		   {
		     echo " no <input type=\"hidden\" name=\"poverty\" value=\"no\" /> <br />\n";
		   };

                 echo "do you have too many nines?";
		 if(count_nines($player[$b]["cards"])>4)
		   {
		     echo " yes<input type=\"radio\" name=\"nines\" value=\"yes\" />";
		     echo " no <input type=\"radio\" name=\"nines\" value=\"no\" /> <br />\n";
		   }
		 else
		   {
		     echo " no <input type=\"hidden\" name=\"nines\" value=\"no\" /> <br />\n";
		   };

                 echo "<input type=\"hidden\" name=\"c\" value=\"$b\" />\n";
		 echo "<input type=\"submit\" value=\"count me in\" />\n";
		 
		 echo "</form>\n";
	       }
	   }
       }

     if(isset($_REQUEST["c"]))
       {
	 $c = $_REQUEST["c"];
	 

	 if( ereg("s",$player[$c]["option"]) && $game["init"]<4 )
	   { /* the player already filled out everything */
	     echo "<p>just wait for the game to start</p>\n";
	   }
	 else if(!isset($_REQUEST["solo"])    || 
		 !isset($_REQUEST["wedding"]) ||
		 !isset($_REQUEST["poverty"]) ||
		 !isset($_REQUEST["nines"]) )
	   {/* player still needs to fill out the form */
	     echo "go back to ";
	     echo "<a href=\"index.php?b=$c\"> here and fill out the form </a> <br />\n";
	   }
	 else if($game["init"]<4)
	   { /* save information */
	     if( $_REQUEST["solo"]!="No")
	       {
		 switch($_REQUEST["solo"])
		   {
		   case "No trump":
		     $player[$c]["option"].="O";
		     break;
		   case "Normal solo":
		     $player[$c]["option"].="S";
		     break;
		   case "Queen solo":
		     $player[$c]["option"].="Q";
		     break;
		   case "Jack solo":
		     $player[$c]["option"].="J";
		     break;
		   case "Club solo":
		     $player[$c]["option"].="C";
		     break;
		   case "Spade solo":
		     $player[$c]["option"].="A";
		     break;
		   case "Hear solo":
		     $player[$c]["option"].="H";
		     break;
		   }
	       }
	     else if($_REQUEST["wedding"] == "yes")
	       {
		 echo "wedding was chosen<br />\n";
		 $player[$c]["option"].="W";
	       }
	     else if($_REQUEST["poverty"] == "yes")
	       {
		 echo "poverty was chosen<br />\n";
		 $player[$c]["option"].="P"; 
	       }
	     else if($_REQUEST["nines"] == "yes")
	       {
		 echo "nines was chosen<br />\n";
		 $player[$c]["option"].="N";
	       }
	     
	     /* player finished setup */
	     $player[$c]["option"].="s";

	     save_status();
	     /* reread status file, to get the correct startplayer, etc */
	     if(file_exists("status.txt"))
	       $lines = file("status.txt");
	     else
	       die("no file");
	     parse_status();

	     if($game["init"]==4 && $player[$c]["number"]==$game["startplayer"])
	       {
		 echo "<p> The game can start now, it's your turn, please use this <a href=\"".
		   $host."?me=".$hash[$c]."\">link</a> to play a card.</p>\n";
	       }
	     else if($game["init"]==4)
	       {
		 $message = "The game can start now, it's your turn, please use this link to play a card:\n".
		   $host."?me=".$hash[$game["startplayer"]]."\n";
		 mymail($player[$hash[$game["startplayer"]]]["email"],"[DoKo-debug] let's go",$message);
		 echo "<p> The game has started. An email has been sent out to the first player.</p>\n";
	       }
	     else
	       {
		 echo "<p>You're in. Once everyone has filled out the form, ".
		   "the game will start and you'll get an eamil on your turn.</p>\n";
	       }
	   }
       }
     /* END SETUP */

     /* the game */
     if($game["init"]==4)
       {
	 /* check for sickness, only would need to do this on the first trick really...*/
	 /***** someone has 5 nines and no one is playing solo => cancel game */
	 if($game["nines"]>=0 && $game["solo-who"]<0)
	   {
	     $message = $player[$hash[$game["poverty"]]]["nines"]." has more than 4 nines. Game aborted!\n";
	     for($i=0;$i<4;$i++)
	       mymail($player[$hash[$i]]["email"],"[DoKo-debug] the game has been canceled",$message); 
	     
	     $output = fopen("status.txt","w");
	     if($output)
	       fclose($output);
	     else
	       echo "problem opening file";
	   };
	 
	 /* who is requesting this*/
	 if(!isset($_REQUEST["me"]))
	   {	
	     if(!isset($_REQUEST["recovery"]))
	       {
		 echo "A game is in progress and kibitzing is not allowed. Sorry!.<br />\n";
		 echo "In case you are playing, but lost your email or can't access the game anymore, please input your email here:<br />\n";
		 ?>
 <form action="index.php" method="post">
   recorvery: <input name="recovery"  type="text" size="20" maxlength="30" /> <br />
   <input type="submit" value="get me back into the game" />
 </form>
<?php
               }
	     else
	       {
		 $recovery = $_REQUEST["recovery"];
		 $ok = -1;
		 for($i=0;$i<4;$i++)
		   if(trim($recovery)==trim($player[$hash[$i]]["email"]))
		     $ok = $i;
		 if($ok>=0)
		   {
		     $message = "Please try this link: ".$host."?me=".$hash[$ok]."\n".
		       "\n if this doesn't work, contact the admin.error4\n";
		     mymail($recovery,"[DoKo-Debug] recovery ",$message);
		     echo "<p> An email with the game information has been sent.</p>\n";
		   }
		 else
		   {
		     echo "<p> can't find this email address, sorry.</p>\n";
		   }; 
	       } /* end recovery */
	   }
	 else
	   { /* $me is set */ 
	     $me = $_REQUEST["me"];
	     
	     /* show history */
	     /* old tricks as list */
	     echo "<ul class=\"oldtrick\">\n";
	     echo "  <li> History: </li>\n";
	     $j=0;
	     foreach($history as $play) 
	       {
		 $j++;
		 $trick = explode(":",$play);
		 
		 /* found old trick, display it */
		 if(sizeof($trick)==5)
		   echo "  <li onclick=\"hl('$j');\"><a href=\"#\">Trick $j</a>\n    <div class=\"table\" id=\"trick".$j."\">\n      <img class=\"table\" src=\"pics/table".$play[0].".png\" alt=\"table\" />\n";
		 else
		   {
		     /* display current trick */
		     echo "<li onclick=\"hl('$j');\"><a href=\"#\">Current Trick</a>\n  <div class=\"table\" id=\"trick".$j."\">\n      <img class=\"table\" src=\"pics/table".$play[0].".png\" alt=\"table\" />";
		   }
		 for($i=0;$i<sizeof($trick)-1;$i++)
		   {
		     $card = $trick[$i];

	             $last=-2;
	             /* has a card been played? */
		     if(ereg("->",$card))
		       {
			 $tmp = explode("->",$card);

		         echo "      <div class=\"card".$tmp[0]."\">\n";

			 if(strlen($tmp[2])>0)
			   echo "        <span class=\"comment\">";
			 else
			   echo "        <span>";
		         echo $player[$hash[$tmp[0]]]["name"];
			 /* check for comment */
			 if(strlen($tmp[2])>0)
			   echo "<span>".$tmp[2]."</span>";
			 echo "</span>\n        ";
	
			 display_card($tmp[1]);

			 $last = $tmp[0];
			 echo "      </div>\n";
		       }
		   }
		 
		 echo "    </div>\n  </li>\n";
	       }
	     echo "</ul>\n";

	     echo "<div class=\"line\"></div>";

	     /* output if we are playing a solo or a wedding */
	     echo "<div class=\"info\">";
	     if($game["solo-who"]>=0)
	       echo $player[$hash[$game["solo-who"]]]["name"]." is playing a ".$game["solo-what"]." solo!<br />\n";
	     else if($game["wedding"]>=0)
	       echo $player[$hash[$game["wedding"]]]["name"]." is playing a wedding!<br />\n";
	     echo "</div>";
	     echo "<div class=\"bug\"> Poverty not working yet! <br />".
	       " Schweinchen not working yet <br />".
	       "Bug: at the end of the game the winner of the last trick can add more points to his score by reloading the page <br />".
	       "If you find more bugs, please list them in the <a href=\"".$wiki.
	       "\">wiki</a>.Hit shift reload every now and then too.</div>\n";


	     /* figure out who needs to play next */
	     $next = $last + 1;
	     if ($next>=4) 
	       $next -= 4 ;

	     /* if no one has played yet or we are at the start of a new trick */
	     if($last<0)
	       $next = $history[sizeof($history)-1][0];
	     
	     /* are we trying to play a card? */
	     if(isset($_REQUEST["card"]))
	       {
		 if($hash[$next]==$me)
		   {
		     $card    = $_REQUEST["card"];
		     $mycards = explode(";",$player[$me]["cards"]);
		     $comment = $_REQUEST["comment"];
		     $comment = str_replace(":","",$comment);           /*can't have ":" in comments */
		     
		     /* do we have that card */
		     if(in_array($card,$mycards))
		       {
			 /* delete card from array */
			 $tmp = array();
			 foreach($mycards as $m)
			   if($m != $card)
			     $tmp[]=$m;
			 
			 $tmp2="";
			 for($i=0;$i<sizeof($tmp)-1;$i++)
			   {
			     $tmp2.=$tmp[$i].";";
			   }
			 $tmp2.=$tmp[$i];
			 $player[$me]["cards"]=$tmp2;
			 
			 /* add card to history, special case if this is the first card */
			 if($last<0)
			   {
			     $history[sizeof($history)-1]="".$player[$me]["number"]."->".$card."->$comment:\n";
			   }
			 else
			   {
			     $tmp = explode(":",$history[sizeof($history)-1]);
			     $tmp[sizeof($tmp)-1] = "".$player[$me]["number"]."->".$card."->$comment:";
			     $history[sizeof($history)-1]=join(":",$tmp);
			   }
			 save_status();
			 
			 echo "<div class=\"card\">";
			 echo " you played  <br />";
			 display_card($card);
			 echo "</div>\n";

			 /* send out email to players who want/need to get informed */
                         /* check if we are in a trick, if trick is done, this needs to be handelt in the
			  * who-won-the-trick section further down */
                         $tmp = explode(":",$history[sizeof($history)-1]);
			 if(sizeof($tmp)<5)
			   for($i=0;$i<4;$i++)
			     {
			       $mynext = $next+1; if($mynext>3)$mynext-=4;
			       
			       if((ereg("c",$player[$hash[$i]]["option"]) || $i==$mynext) && $hash[$i]!=$me)
				 {
				   $message = " Hello ".$player[$hash[$i]]["name"].",\n\n";
				   
				   if($i==$mynext)
				     {
				       $message .= "it's your turn  now.\n".
					 "Use this link to play a card: ".$host."?me=".$hash[$i]."\n\n" ;
				     }
				   $message .= $player[$me]["name"]." has played the following card ".
				     card_to_name($card)."\n";
				   
				   if($game["solo-who"]>=0)
				     $message .= $player[$hash[$game["solo-who"]]]["name"]." is playing a ".
				       $game["solo-what"]." solo!\n";
				   
				   mymail($player[$hash[$i]]["email"],"[DoKo-debug] a card has been played",$message);
				   
				   if($debug)
				     echo "<a href=\"index.php?me=".$hash[$mynext]."\"> next player </a> <br />\n";
				 }
			     }
		       }
		     else
		       echo "seems like you don't have that card<br />\n";
		   }
	       } /* end if card is set */
	    if(substr_count($history[sizeof($history)-1],":")==4)
	       { /* count points, email winner */
		 $p = array();
		 $c = array();

		 $tmp  = explode(":",$history[sizeof($history)-1]); /*last played trick */

		 /* get player and cards of last trick */
		 for($i=0;$i<4;$i++)
		   {
		     $tmp2 = explode("->",$tmp[$i]);
		     $p[] = $tmp2[0];
		     $c[] = $tmp2[1];
		   };
		 
		 $win = $p[get_winner($c)];
		   		 
		 if(strlen($player[$hash[0]]["cards"]))
		   $history[] = "\n".$win.":\n"; /* not sure why I need the first \n here */

		 /* email the player who needs to move next*/
		 for($i=0;$i<4;$i++)
		   {
		     if((ereg("c",$player[$hash[$i]]["option"]) || $i==$win) )
		       {
			 $message = " Hello ".$player[$hash[$i]]["name"].",\n\n";
			 
			 if($i == $win)
			   {
			     $message .= "You won the last trick,it's your turn  now.\n".
			       "Use this link to play a card: ".$host."?me=".$hash[$i]."\n\n" ;
			   }
			 else
			   $message .= $player[$hash[$win]]["name"]." has won the last trick\n".
			     "Use this link to look at the game: ".$host."?me=".$hash[$i]."\n\n" ;
			 
			 if($game["solo-who"]>=0)
			   $message.= $player[$hash[$game["solo-who"]]]["name"]." is playing a ".
			     $game["solo-what"]." solo!\n";
			 
			 mymail($player[$hash[$i]]["email"],"[DoKo-debug] a card has been played",$message);
			 
			 if($debug)
			   echo "<a href=\"index.php?me=".$hash[$win]."\"> next player </a> <br />\n";
		       }
		   }
		 
		 /* count points of the last trick */
		 $points = 0;

		 $tmp = explode(":",$history[sizeof($history)-2]);
		 for($i=0;$i<4;$i++)
		   {
		     $tmp2 = explode("->",$tmp[$i]);
		     $c = $tmp2[1];
		     $points += card_value($c);
		   }
		 $player[$hash[$win]]["points"] += $points;
		 echo "<br />\n ".$player[$hash[$win]]["name"]." won: $points Points <br />\n";
		 
		 save_status();
	       }; /* end if win is set */
	     echo "<br />\n";

	     /* check if game is done */
	     $end = 1;
	     for($i=0;$i<4;$i++)
	       if(strlen(trim($player[$hash[$i]]["cards"]))!=0)
		 $end = 0;
	     
	     if($end)
	       { 
		 echo "<br /> game over, count points <br />\n";
		 for($i=0;$i<4;$i++)
		   {
		     echo $player[$hash[$i]]["name"]." got ".$player[$hash[$i]]["points"]."<br />\n";
		   }
	       }
	     /* check end of game */
  
	     /* do we still have cards? display them */
	     if(strlen(trim($player[$me]["cards"]))>0 )
	       {
		 $allcards = trim($player[$me]["cards"]);
		 $mycards  = explode(";",$allcards);
		 
		 sort($mycards);
		 
		 echo "<div class=\"mycards\">\n";
		 /* is it our turn? */
		 if($hash[$next]==$me && !isset($_REQUEST["card"])) 
		   {
		     echo "Hello ".$player[$me]["name"].", it's your turn!  <br />\n";
		     echo "Your cards are: <br />\n";
		     echo "<form action=\"index.php\" method=\"post\">\n";
		     foreach($mycards as $card) 
		       display_link_card($card,$me);
?>
   <br />A short comment:<input name="comment" type="text" size="30" maxlength="50" /> 
   <input type="hidden" name="me" value="<?php echo $me; ?>" />
   <input type="submit" value="move" />
</form>
<?php
		   }
		 else 
		   { /* not our turn, just show the hand */
		     echo "Your cards are: <br />\n";
		     foreach($mycards as $card) 
		       display_card($card);
		   }
		 echo "</div>\n";   
	       }
	   }
       }

 } 

?>
</body>
</html>

<?php
/*
 *Local Variables: 
 *mode: php
 *mode: hs-minor
 *End:
 */
?>
