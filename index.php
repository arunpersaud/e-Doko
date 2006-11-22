<!DOCTYPE html PUBLIC
    "-//W3C//DTD XHTML 1.1 plus MathML 2.0 plus SVG 1.1//EN"
    "http://www.w3.org/2002/04/xhtml-math-svg/xhtml-math-svg.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
  <head>
     <title>e-Doko</title>
     <meta content="text/html; charset=ISO-8859-1" http-equiv="content-type" />
     <link rel="stylesheet" type="text/css" href="standard.css" />	
  </head>
<body>
<h1> Welcome to E-Doko </h1>
<?php
/*
 * config 
 */

$host  = "http://doko.nubati.net/index.php";
$wiki  = "http://wiki.nubati.net/index.php?title=EmailDoko";
$debug = 0;

/*
 * end config
 */	
					

/* helper function */
function mymail($To,$Subject,$message)
{  
  global $debug;

  if($debug)
    {
      str_replace("\n","<br />",$message);
      echo "<br>To: $To<br>Subject: $Subject <br>$message<br>";
    }
  else
    mail($To,$Subject,$message);
  return;
}

function parse_status()
{
  global $game,$history,$player,$hash,$lines;
  
  $game["init"]        = 0;
  $game["solo-who"]    = -1;
  $game["solo-what"]   = "todo";
  $game["wedding"]     = -1;
  $game["poverty"]     = -1;
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
      if(ereg("s",$tmp[3])) $game["init"]++;
      if(ereg("P",$tmp[3])) $game["poverty"] = $i;
      if(ereg("N",$tmp[3])) $game["nines"]   = $i;
      if(ereg("W",$tmp[3])) $game["wedding"] = $i;
      if(ereg("[OSQJCAH]",$tmp[3]) && ($game["solo-who"]<0) )
	{
	  $game["solo-who"]    = $i;     
	  $game["startplayer"] = $i;
	}
  
    }  
  /* save the game history */
  for($i=4;$i<sizeof($lines);$i++)
    {
      if(!ereg("^[[:space:]]*$",trim($lines[$i])))
	{
	  $history[] = $lines[$i];
	}
    }
  
  if(sizeof($history)==0 || (sizeof($history)==1 && strlen($history[0])==3 ))
    $history[0] = $game["startplayer"].":";
  
  return;
}

function count_nines($cards)
{
  $card = explode(";",$cards);
  
  $nines =0;

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
  $card = explode(";",$cards);
  
  $count =0;

  if( in_array("3",$card) && in_array("2",$card) )
    $count=1;

  return $count;
}

function count_trump($cards)
{
  $card = explode(";",$cards);
  
  $trump =0;

  /* count each trump */
  foreach($card as $c)
    {
      if( (int)($c) <27) $trump++;
    }

  /* subtract one, in case player has both foxes */
  if( in_array("19",$card) && in_array("20",$card) )
    $trump--;

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
      return "something went wrong, please contact the admin";
    }
}

function card_value($card)
{
  switch($card-1)
    {
      case 0:
      case 1:
        return 10;
      case 2:
      case 3:
      case 4:
      case 5:
      case 6:
      case 7:
      case 8:
      case 9:
        return 3;
      case 10:
      case 11:
      case 12:
      case 13:
      case 14:
      case 15:
      case 16:
      case 17:
        return 2;
      case 18:
      case 19:
        return 11;
      case 20:
      case 21:
        return 10;
      case 22:
      case 23:
        return 4;
      case 24:
      case 25:
      return 0;
      case 26:
      case 27:
      return 11;
      case 28:
      case 29:
      return 10;
      case 30:
      case 31:
      return 4;
      case 32:
      case 33:
      return 0;
      case 34:
      case 35:
      return 11;
      case 36:
      case 37:
      return 10;
      case 38:
      case 39:
      return 4;
      case 40:
      case 41:
      return 0;
      case 42:
      case 43:
      return 11;
      case 44:
      case 45:
      return 4;
      case 46:
      case 47:
      return 0;
      default:
      echo "something went wrong, please contact the admin <br>";
      return 0;
    }
}

function display_card($card)
{
  if( $card/2 - (int)($card/2) == 0.5)
    echo "<img src=\"cards/".$card.".png\" height=\"100\" alt=\"".card_to_name($card)."\" />\n";
  else
    echo "<img src=\"cards/".($card-1).".png\" height=\"100\" alt=\"".card_to_name($card-1)."\" />\n";
  return;
}

function display_link_card($card,$me)
{
  if( $card/2 - (int)($card/2) == 0.5)
    echo "<a href=\"index.php?me=$me&card=$card\"><img src=\"cards/".$card.".png\" height=\"100\" alt=\"".card_to_name($card)."\" /></a>\n";
  else
    echo "<a href=\"index.php?me=$me&card=$card\"><img src=\"cards/".($card-1).".png\" height=\"100\" alt=\"".card_to_name($card-1)."\" /></a>\n";
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
    echo "can't open file for writing, please inform the admin.";
  
  return;
}

echo "<p>If you find bugs, please list them in the <a href=\"".$wiki."\">wiki</a></p>\n";
	
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
    
    mymail($EmailA,"Invite for a game of DoKo","Hello $PlayerA,\n".$message.$hashA);
    mymail($EmailB,"Invite for a game of DoKo","Hello $PlayerB,\n".$message.$hashB);
    mymail($EmailC,"Invite for a game of DoKo","Hello $PlayerC,\n".$message.$hashC);
    mymail($EmailD,"Invite for a game of DoKo","Hello $PlayerD,\n".$message.$hashD);
    
    /* read in random.txt */
    if(file_exists("random.txt"))
      $random = file("random.txt");
    else
      die("no random file");
 
    $randomNR = explode( ":", $random[1] );
    
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
	 $a=$_REQUEST["a"];
	 
	 if( ereg("[is]",$player[$a]["option"]) &&  $game["init"]<4)
	   {
	     echo "just wait for the game to start";
	   }
	 else  if( !ereg("[is]",$player[$a]["option"]) )
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
	 $b=$_REQUEST["b"];
	 
	 if( ereg("s",$player[$b]["option"])  && $game["init"]<4)
	   {
	     echo "just wait for the game to start";
	   }
	 else if( (!isset($_REQUEST["in"])|| !isset($_REQUEST["update"])) && !ereg("i",$player[$b]["option"]))
	   {
	     echo "go back to ";
	     echo "<a href=\"index.php?a=$b\"> here and fill out the form </a> <br />\n";
	   }
	 else
	   { /* show the hand */
	     if($_REQUEST["in"]=="no")
	       {
		 for($i=0;$i<4;$i++)
		   {
		     $message = "Hello ".$player[$hash[$i]]["name"].",\n\n".
		       "the game has been canceled due to the request of one of the players.\n";
		     mymail($player[$hash[$i]]["email"],"[DoKo-Debug] the game has been canceled",$message); 
		   }
		   $output = fopen("status.txt","w");
		   if($output)
		     fclose($output);
		   else
		     echo "problem opening file";
	       }
	     else
	       {
		 if($_REQUEST["update"]=="card") $player[$b]["option"].="c";
		 if($_REQUEST["update"]=="turn") $player[$b]["option"].="t";
		 
		 $player[$b]["option"].="i";
		 
		 save_status();
		 
		 $allcards = $player[$b]["cards"];
		 $mycards = explode(";",$allcards);
		 
		 sort($mycards);
		 echo "your cards are <br>\n";
		 foreach($mycards as $card) 
		   {
		     display_card($card);
		   }
		 echo "<br />\n";   
 ?>
 <p>aehm... at the moment please just answer everything with no, still need to implement this</p>	 	  
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
	 $c=$_REQUEST["c"];
	 
	 if(!isset($_REQUEST["solo"])|| !isset($_REQUEST["wedding"])|| !isset($_REQUEST["poverty"]) || !isset($_REQUEST["nines"]) )
	   {
	     echo "go back to ";
	     echo "<a href=\"index.php?b=$c\"> here and fill out the form </a> <br />\n";
	   }
	 else if(  ereg("s",$player[$c]["option"]) && $game["init"]<4 )
	   {
	     echo "just wait for the game to start";
	   }
	 else if($game["init"]<4)
	   { 
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
	     
	     $message = "you're in. once everyone has filled out the form,".
	       "the game will start and you'll get an eamil on your turn\n";
	     mymail($player[$c]["email"],"[DoKo-debug] the game will start soon",$message); 

	     $player[$c]["option"].="s";
	     save_status();
	     if(file_exists("status.txt"))
	       $lines = file("status.txt");
	     else
	       die("no file");
	     parse_status();

	     if($game["init"]==4)
	       {
		 $message = "The game can start now, it's your turn, please use this link to play a card:\n".
		   $host."?me=".$hash[$game["startplayer"]]."\n";
		 mymail($player[$hash[$game["startplayer"]]]["email"],"[DoKo-debug] let's go",$message);
	       }
	     
	   }
       }
     if($game["init"]==4)
       {
	 /* check for sickness*/
	 /***** someone has 5 nines and no one is playing solo=> cance5Al game */
	 if($game["nines"]>=0 && $game["solo-who"]<0)
	   {
	     $message = $player[$hash[$game["poverty"]]]["nines"]." has more than 4 nines. Game aborted!\n";
	     for($i=0;$i<4;$i++)
	       {
		 mymail($player[$hash[$i]]["email"],"[DoKo-debug] the game has been canceled",$message); 
	       }
	     $output = fopen("status.txt","w");
	     if($output)
	       fclose($output);
	     else
	       echo "problem opening file";
	   }

	 /* who is requesting this*/
	 if(!isset($_REQUEST["me"]))
	   {	
	     if(!isset($_REQUEST["recovery"]))
	       {
		 echo "a game is in progress, but you are not playing.<br />\n";
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
		 $recovery=$_REQUEST["recovery"];
		 $ok=-1;
		 for($i=0;$i<4;$i++)
		   if(trim($recovery)==trim($player[$hash[$i]]["email"]))
		     $ok=$i;
		 if($ok>=0)
		   {
		     $message = "Please try this link: ".$host."?me=".$hash[$ok]."\n".
		       "\n if this doesn't work, contact the admin.\n";
		     mymail($recovery,"[DoKo-Debug] recovery ",$message);
		     echo "email has been sent.";
		   }
		 else
		   {
		     echo "can't find this email address, sorry.";
		   }; 
	       } /* end recovery */
	   }
	 else
	   { /* $me is set */ 
	     $me = $_REQUEST["me"];
	     
	     echo "game in progress and you are in it<br />\n";
	     if($game["solo-who"]>=0)
	       echo $player[$hash[$game["solo-who"]]]["name"]." is playing a ".$game["solo-what"]." solo!<br />\n";
	     else if($game["wedding"]>=0)
	       echo $player[$hash[$game["wedding"]]]["name"]." is playing a wedding!<br />\n";
	     
	     /* show history */
	     foreach($history as $play) 
	       {
		 echo "<br />\n";
		 
		 $trick = explode(":",$play);
		 
		 $last=-2;
		 /* found old trick, display it */
		 for($i=0;$i<sizeof($trick)-1;$i++)
		   {
		     $card=$trick[$i];
		     if(ereg("->",$card))
		       {
			 $tmp = explode("->",$card);
			 echo $player[$hash[$tmp[0]]]["name"]." played ";
			 display_card($tmp[1]);
			 $last=$tmp[0];
		       }
		   }
	       }
	     
	     /* figure out who needs to play next */
	     $next = $last + 1;
	     if ($next>=4) 
	       $next -= 4 ;
	     if($last<0)
	       {
		 $next=$history[sizeof($history)-1][0];
	       }
	     
	     /* are we trying to play a card? */
	     if(isset($_REQUEST["card"]))
	       {
		 if($hash[$next]==$me)
		   {
		     $card    = $_REQUEST["card"];
		     $mycards = explode(";",$player[$me]["cards"]);
		     
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
			     $history[sizeof($history)-1]="".$player[$me]["number"]."->".$card.":\n";
			   }
			 else
			   {
			     $tmp = explode(":",$history[sizeof($history)-1]);
			     $tmp[sizeof($tmp)-1] = "".$player[$me]["number"]."->".$card.":";
			     $history[sizeof($history)-1]=join(":",$tmp);
			   }
			 save_status();
			 
			 echo " you played ";
			 display_card($card);
			 
			 /* send out email to players who want/need to get informed */
			 for($i=0;$i<4;$i++)
			   {
			     $mynext=$next+1; if($mynext>3)$mynext-=4;
			     if((ereg("c",$player[$hash[$i]]["option"]) || $i==$mynext) && $hash[$i]!=$me)
			       {
				 $message = " Hello ".$player[$hash[$i]]["name"].",\n\n";
				 
				 if($i==$mynext)
				   {
				     $message .= "it's your turn  now.\n".
				       "Use this link to play a card: ".$host."?me=".$hash[$i]."\n\n" ;
				   }
				 $message .= $player[$me]["name"]. "has played the following card ".card_to_name($card)."\n";
				 
				 if($game["solo-who"]>=0)
				   $message.= $player[$hash[$game["solo-who"]]]." is playing a ".$game["solo-what"]." solo!\n";
				 
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
	     else if(isset($_REQUEST["win"]) && strlen($history[sizeof($history)-1])>3)
	       {
		 $win=$_REQUEST["win"];
		 $history[]=$win.":\n";
		 /* count points of the last trick */
		 $points=0;

		 $tmp = explode(":",$history[sizeof($history)-2]);
		 for($i=0;$i<4;$i++)
		   {
		     $tmp2 = explode("->",$tmp[$i]);
		     $c = $tmp2[1];
		     $points += card_value($c);
		   }
		 $player[$hash[$win]]["points"]+=$points;
		 echo "<br />\n ".$player[$hash[$win]]["name"]." won: $points Points <br />\n";
		 
		 save_status();
	       }; /* end if win is set */
	     echo "<br />\n";

	     $tmp = explode(":",$history[sizeof($history)-1]);

	     /* check last history entry: end of a trick? ask who won it */
	     if(sizeof($tmp)==5)
	       {
		 ?>
<form action="index.php" method="post">
who won?
<?php 
   for($i=0;$i<4;$i++)
     echo $player[$hash[$i]]["name"]." <input type=\"radio\" name=\"win\" value=\"$i\" />";
   echo "<input type=\"hidden\" name=\"me\" value=\"$me\" />";
?>
<input type="submit" value="submit" />

</form>
<?php
               }
	     else if(sizeof($tmp)<5 && 1<sizeof($tmp) && !isset($_REQUEST["card"]))
	       { 		 
		 if(sizeof($tmp)==2 && strlen($tmp[0])==1)
		   {
		     $next=$tmp[0];
		     
		     if($debug)
		       echo "DEBUG: the next move is for <a href=\"index.php?me=".$hash[$next]."\">the next player</a><br />\n";
		     
		     if(strlen(trim($player[$me]["cards"]))==0)
		       {
			 echo "<br /> game over, count points <br />\n";
			 for($i=0;$i<4;$i++)
			   {
			     echo $player[$hash[$i]]["name"]." got ".$player[$hash[$i]]["points"]."<br />\n";
			   }
		       }
		   }
		 echo "<br />\n";
	       } /* end check for winner */
	     
	     /* do we still have cards? display them */
	     if(strlen(trim($player[$me]["cards"]))>0 )
	       {
		 $allcards = trim($player[$me]["cards"]);
		 $mycards = explode(";",$allcards);
		 
		 sort($mycards);
		 
		 echo "<p class=\"mycards\">\n";
		 /* is it our turn? */
		 if($hash[$next]==$me) 
		   {
		     echo "ITS YOUR TURN   <br />\n";
		     echo "your cards are: <br />\n";
		     foreach($mycards as $card) 
		       {
			 display_link_card($card,$me);
		       }
		   }
		 else 
		   { /* not our turn, just show the hand */
		     echo "your cards are: <br />\n";
		     foreach($mycards as $card) 
		       {
			 display_card($card);
		       }
		   }
		 echo "</p>\n";   
	       }
	   }
       }

 } 

?>
</body>
</html>
