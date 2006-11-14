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

   /* write status into file */
   $output = fopen("status.txt","w");
   if ($output)
   {
     fwrite($output, "0\n");

     fwrite($output, "hash1:".$PlayerA.":".$EmailA."::" );
     for($i=0;$i<12;$i++)
	fwrite($output,";$randomNR[$i]" );
     fwrite($output,"\n");

     fwrite($output, "hash2:$PlayerB:$EmailB::" );
     for(;$i<24;$i++)
	fwrite($output,";$randomNR[$i]" );
     fwrite($output,"\n");

     fwrite($output, "hash3:$PlayerC:$EmailC::" );
     for(;$i<36;$i++)
	fwrite($output,";$randomNR[$i]" );
     fwrite($output,"\n");

     fwrite($output, "hash4:$PlayerD:$EmailD::");
     for(;$i<48;$i++)
	fwrite($output,";$randomNR[$i]" );
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
   $tmp = explode( ":",$lines[1]);
   $player[$tmp[0]]["name"]   = $tmp[1];
   $player[$tmp[0]]["email"]  = $tmp[2];
   $player[$tmp[0]]["option"] = $tmp[3];
   $player[$tmp[0]]["cards"]  = $tmp[4];

   $tmp = explode( ":",$lines[2]);
   $player[$tmp[0]]["name"]   = $tmp[1];
   $player[$tmp[0]]["email"]  = $tmp[2];
   $player[$tmp[0]]["option"] = $tmp[3]; 
   $player[$tmp[0]]["cards"]  = $tmp[4];

   $tmp = explode( ":",$lines[3]);
   $player[$tmp[0]]["name"]   = $tmp[1];
   $player[$tmp[0]]["email"]  = $tmp[2];
   $player[$tmp[0]]["option"] = $tmp[3];
   $player[$tmp[0]]["cards"]  = $tmp[4];

   $tmp = explode( ":",$lines[4]);
   $player[$tmp[0]]["name"]   = $tmp[1];
   $player[$tmp[0]]["email"]  = $tmp[2];
   $player[$tmp[0]]["option"] = $tmp[3];
   $player[$tmp[0]]["cards"]  = $tmp[4];
}

/* check if a player wants to accept a game */
if(isset($_REQUEST["a"]))
{
   $a=$_REQUEST["a"];
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
   /* yes? email him his hand, ask for solo, poverty, email every move or every card? */
if(isset($_REQUEST["b"]))
{
   $b=$_REQUEST["b"];
   echo "hash is $b  <br>";
   if(!isset($_REQUEST["in"])|| !isset($_REQUEST["update"]) )
   {
     echo "go back to ";
     echo "<a href=\"index.php?a=$b\"> here and fill out the form </a> <br />";
   }
   else
   { /* show the hand */
        echo $player[$b]["cards"];
        $tmp   = $player[$b]["cards"];
        $cards = explode( ":",$tmp);
        echo "your cards are";
        foreach($cards as $card) echo " $card ";
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
   
   
   <input type="hidden" name="c" value="$b" />
     
   <input type="submit" value="count me in" />

 </form>

<?php
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
