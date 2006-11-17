<!DOCTYPE html PUBLIC
    "-//W3C//DTD XHTML 1.1 plus MathML 2.0 plus SVG 1.1//EN"
    "http://www.w3.org/2002/04/xhtml-math-svg/xhtml-math-svg.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
  <head>
     <title>Doko via email</title>
     <link rel="stylesheet" type="text/css" href="standard.css"/>	
  </head>
<body>
<h1> Welcome to E-Doko </h1>

<?php
/* helper function */

function display_card($card)
{
  if( $card/2 - (int)($card/2) == 0.5)
    echo "<img src=\"cards/".$card.".png\" height=\"100\">";
  else
    echo "<img src=\"cards/".($card-1).".png\" height=\"100\">";
  return;
}
   function display_link_card($card,$me)
{
  if( $card/2 - (int)($card/2) == 0.5)
    echo "<a href=\"index.php?me=$me&card=$card\"><img src=\"cards/".$card.".png\" height=\"100\"></a>";
  else
    echo "<a href=\"index.php?me=$me&card=$card\"><img src=\"cards/".($card-1).".png\" height=\"100\"></a>";
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
	  $tmp="";
	  if( ereg("i",$player[$key]["option"]) )
	    $tmp.="i";
	  if( ereg("s",$player[$key]["option"]) )
	    $tmp.="s";
	  if( ereg("t",$player[$key]["option"]) )
	    $tmp.="t";
	  if( ereg("c",$player[$key]["option"]) )
	    $tmp.="c";
	  $player[$key]["option"]=$tmp;

	  fwrite($output,"".$player[$key]["hash"].":" );
	  fwrite($output,"".$player[$key]["name"].":" );
	  fwrite($output,"".$player[$key]["email"].":" );
	  fwrite($output,"".$player[$key]["option"].":" );
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
    isset($_REQUEST["EmailD"]) )
  {
    $PlayerA = $_REQUEST["PlayerA"];
    $PlayerB = $_REQUEST["PlayerB"];
    $PlayerC = $_REQUEST["PlayerC"];
    $PlayerD = $_REQUEST["PlayerD"];
    $EmailA  = $_REQUEST["EmailA"] ;
    $EmailB  = $_REQUEST["EmailB"] ;
    $EmailC  = $_REQUEST["EmailC"] ;
    $EmailD  = $_REQUEST["EmailD"] ;
    
    /* send out email, check for error with email */
    echo "send out emails to everyone, asking if they want to join";
    echo "use link <br>";
    echo "<a href=\"index.php?a=hash1\"> player 1</a> <br />";
    echo "<a href=\"index.php?a=hash2\"> player 2</a> <br />";
    echo "<a href=\"index.php?a=hash3\"> player 3</a> <br />";
    echo "<a href=\"index.php?a=hash4\"> player 4</a> <br />";
    
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
	fwrite($output, "hash1:".$PlayerA.":".$EmailA."::" );
	for($i=0;$i<11;$i++)
	  fwrite($output,"$randomNR[$i];" );
	fwrite($output,"$randomNR[11]:" ); $i++;
	fwrite($output,"\n");
	
	fwrite($output, "hash2:$PlayerB:$EmailB::" );
	for(;$i<23;$i++)
	  fwrite($output,"$randomNR[$i];" );
	fwrite($output,"$randomNR[23]:" ); $i++;
	fwrite($output,"\n");
	
	fwrite($output, "hash3:$PlayerC:$EmailC::" );
	for(;$i<35;$i++)
	  fwrite($output,"$randomNR[$i];" );
	fwrite($output,"$randomNR[35]:" ); $i++;
	fwrite($output,"\n");
	
	fwrite($output, "hash4:$PlayerD:$EmailD::");
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
 <p> no game in progress, please input 4 names and email addresses </p>
 <form action="index.php" method="post">
   Name:  <input name="PlayerA" type="text" size="10" maxlength="20" /> 
   Email: <input name="EmailA"  type="text" size="10" maxlength="20" /> <br />

   Name:  <input name="PlayerB" type="text" size="10" maxlength="20" /> 
   Email: <input name="EmailB"  type="text" size="10" maxlength="20" /> <br />

   Name:  <input name="PlayerC" type="text" size="10" maxlength="20" /> 
   Email: <input name="EmailC"  type="text" size="10" maxlength="20" /> <br />

   Name:  <input name="PlayerD" type="text" size="10" maxlength="20" /> 
   Email: <input name="EmailD"  type="text" size="10" maxlength="20" /> <br />

   <input type="submit" value="start game" />
 </form>
<?php
   }
 else
   { /* load game status */
     $game["init"]=0;
     
     $tmp = explode( ":",$lines[0]);
     $hash[]   = $tmp[0];
     $player[$tmp[0]]["number"] = 0;
     $player[$tmp[0]]["hash"]   = $tmp[0];
     $player[$tmp[0]]["name"]   = $tmp[1];
     $player[$tmp[0]]["email"]  = $tmp[2];
     $player[$tmp[0]]["option"] = $tmp[3];
     $player[$tmp[0]]["cards"]  = $tmp[4];
     if(ereg("s",$tmp[3])) $game["init"]++;

     $tmp = explode( ":",$lines[1]);
     $hash[]   = $tmp[0];
     $player[$tmp[0]]["number"] = 1;
     $player[$tmp[0]]["hash"]   = $tmp[0];
     $player[$tmp[0]]["name"]   = $tmp[1];
     $player[$tmp[0]]["email"]  = $tmp[2];
     $player[$tmp[0]]["option"] = $tmp[3]; 
     $player[$tmp[0]]["cards"]  = $tmp[4];
     if(ereg("s",$tmp[3])) $game["init"]++;
     
     $tmp = explode( ":",$lines[2]);
     $hash[]   = $tmp[0];
     $player[$tmp[0]]["number"] = 2;
     $player[$tmp[0]]["hash"]   = $tmp[0];
     $player[$tmp[0]]["name"]   = $tmp[1];
     $player[$tmp[0]]["email"]  = $tmp[2];
     $player[$tmp[0]]["option"] = $tmp[3];
     $player[$tmp[0]]["cards"]  = $tmp[4];
     if(ereg("s",$tmp[3])) $game["init"]++;
     
     $tmp = explode( ":",$lines[3]);
     $hash[]   = $tmp[0];
     $player[$tmp[0]]["number"] = 3;
     $player[$tmp[0]]["hash"]   = $tmp[0];
     $player[$tmp[0]]["name"]   = $tmp[1];
     $player[$tmp[0]]["email"]  = $tmp[2];
     $player[$tmp[0]]["option"] = $tmp[3];
     $player[$tmp[0]]["cards"]  = $tmp[4];
     if(ereg("s",$tmp[3])) $game["init"]++;

     /* save the game history */
     for($i=4;$i<sizeof($lines);$i++)
       {
	 if($lines[$i]!="\n")
	   $history[]=$lines[$i];
       }

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
	 else 
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
	 
	 if( ereg("[is]",$player[$b]["option"])  && $game["init"]<4)
	   {
	     echo "just wait for the game to start";
	   }
	 else if(!isset($_REQUEST["in"])|| !isset($_REQUEST["update"]) )
	   {
	     echo "go back to ";
	     echo "<a href=\"index.php?a=$b\"> here and fill out the form </a> <br />";
	   }
	 else
	   { /* show the hand */
	     if($_REQUEST["update"]=="card") $player[$b]["option"].="c";
	     if($_REQUEST["update"]=="turn") $player[$b]["option"].="t";
	     
	     $player[$b]["option"].="i";
	     
	     save_status();
	     
	     $allcards = $player[$b]["cards"];
	     $mycards = explode(";",$allcards);
	     
	     sort($mycards);
	     echo "your cards are <br>";
	     foreach($mycards as $card) 
	       {
		 display_card($card);
	       }
	     echo "<br />\n";   
 ?>
 <form action="index.php" method="post">
   
   do you want to play solo?
   yes<input type="radio" name="solo" value="yes" />
   no<input type="radio" name="solo" value="no" /> <br />

   do you have a wedding?
   yes<input type="radio" name="wedding" value="yes" />
   no<input type="radio" name="wedding" value="no" /> <br />

   do you have poverty?
   yes<input type="radio" name="poverty" value="yes" />
   no<input type="radio" name="poverty" value="no" /> <br />
   
<?php   
             echo "<input type=\"hidden\" name=\"c\" value=\"$b\" />\n";
	     echo "<input type=\"submit\" value=\"count me in\" />\n";

	     echo "</form>\n";
	   }
       }
     if(isset($_REQUEST["c"]))
       {
	 $c=$_REQUEST["c"];
	 
	 if(!isset($_REQUEST["solo"])|| !isset($_REQUEST["wedding"])|| !isset($_REQUEST["poverty"]) )
	   {
	     echo "go back to ";
	     echo "<a href=\"index.php?b=$c\"> here and fill out the form </a> <br />";
	   }
	 else if(  ereg("s",$player[$c]["option"]) && $game["init"]<4 )
	   {
	     echo "just wait for the game to start";
	   }
	 else if($game["init"]<4)
	   { 
	     echo "handle krankheit <br />";

	     echo "email this out: you're in. once everyone has filled out the form,";
	     echo "the game will start and you'll get an eamil about it";
	     
	     $player[$c]["option"].="s";

	     save_status();
	   }
       }
     if($game["init"]==4)
       {
	 if(!isset($_REQUEST["me"]))
	   echo "a game is in progress, but you are not playing";
	 else
	   {
	     $me = $_REQUEST["me"];
	     if(isset($_REQUEST["card"]))
	       {
		 $card=$_REQUEST["card"];
		 echo "EMAIL: you played $card <br>";
		 echo "check,if card correct  remove card from deck <br>";
		 

		 $tmp = explode(":",$history[sizeof($history)-1]);
		 $tmp[sizeof($tmp)-1] = "".$player[$me]["number"]."->".$card.":";
		 $history[sizeof($history)-1]=join(":",$tmp);

		 save_status();
	       }
	     echo "game in progress and you are in it<br>";
	     foreach($history as $play)
	       {
		 $trick = explode(":",$play);

		 $last=-1;
		 /* found old trick, display it */
		 for($i=0;$i<sizeof($trick)-1;$i++)
		   {
		     $card=$trick[$i];
		     $tmp = explode("->",$card);
		     echo $player[$hash[$tmp[0]]]["name"]." played ";
		     display_card($tmp[1]);
		     $last=$tmp[0];
		   }
		 echo "<br />";
		 
		 
		 $next = $last + 1;
		 if ($next>=4) 
		   $next -= 4 ;
		     
		 switch(sizeof($trick))
		   {
		   case 1:
		     echo "new trick , next player ".$trick[0]." <br>";
		     break;
		   case 4:
		     echo "figure out who will win<br>";
		   case 2:
		   case 3:
		     echo "some played $next";
		     if($hash[$next]==$me)
		       {
			 
		       echo "ITS YOUR TURN<br>";
		       $allcards = $player[$me]["cards"];
		       $mycards = explode(";",$allcards);
		       
		       sort($mycards);
		       echo "your cards are <br>";
		       foreach($mycards as $card) 
			 {
			   display_link_card($card,$me);
			 }
		       echo "<br />\n";   
		       }
		     echo "<br />";
		     break;
		   default:
		     echo "default<br>";
		   }
	       }
	   }
       }
     
}      /* is this the last player that needs to accept? */
         /* yes, figure out who starts, send out email to first player */
   /* no, email the rest to cancel game */

/* player wants to make a move? */
  /* check if it is this players turn it is (if it's the players turn, convert cards into links) */
  /* if it is the last card played*/
     /* add checkbox for who one the trick */
     /* email next player */
     /* last card played? */
        /* count score for each player */
?>

</body>
</html>
