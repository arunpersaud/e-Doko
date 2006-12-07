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
</div>

<?php
     
include_once("functions.php");
include_once("db.php");

DB_open();

/* end header */

/*****************  M A I N **************************/

/* check if we want to start a new game */
if(isset($_REQUEST["new"]))
  {
?>
    <p> no game in progress, please input 4 names and email addresses, please make sure that the addresses are correct! </p>
 <form action="index.php" method="post">
   Name:  <input name="PlayerA" type="text" size="10" maxlength="20" /> 
   Name:  <input name="PlayerB" type="text" size="10" maxlength="20" /> 
   Name:  <input name="PlayerC" type="text" size="10" maxlength="20" /> 
   Name:  <input name="PlayerD" type="text" size="10" maxlength="20" /> 

   <input type="submit" value="start game" />
 </form>
<?php
  } 
/* end start a new game */

/*check if everything is ready to set up a new game */
else if( isset($_REQUEST["PlayerA"]) && 
    isset($_REQUEST["PlayerB"]) && 
    isset($_REQUEST["PlayerC"]) && 
    isset($_REQUEST["PlayerD"]) )
  {
    $PlayerA = $_REQUEST["PlayerA"];
    $PlayerB = $_REQUEST["PlayerB"];
    $PlayerC = $_REQUEST["PlayerC"];
    $PlayerD = $_REQUEST["PlayerD"];
    
    $EmailA  = DB_get_email_by_name($PlayerA);
    $EmailB  = DB_get_email_by_name($PlayerB);
    $EmailC  = DB_get_email_by_name($PlayerC);
    $EmailD  = DB_get_email_by_name($PlayerD);
    
    if($EmailA=="" || $EmailB=="" || $EmailC=="" || $EmailD=="")
      {
	echo "couldn't find one of the names, please start a new game";
	exit();
      }
    
    $useridA  = DB_get_userid_by_name($PlayerA);
    $useridB  = DB_get_userid_by_name($PlayerB);
    $useridC  = DB_get_userid_by_name($PlayerC);
    $useridD  = DB_get_userid_by_name($PlayerD);
    
    /* create random numbers */
    $randomNR       = create_array_of_random_numbers();
    $randomNRstring = join(":",$randomNR);
    
    /* create game */
    mysql_query("INSERT INTO Game VALUES (NULL, NULL, '$randomNRstring', NULL, NULL,'pre', NULL)");
    $game_id = mysql_insert_id();
    
    
    $hashA = md5("AGameOfDoko".$game_id.$PlayerA.$EmailA);
    $hashB = md5("AGameOfDoko".$game_id.$PlayerB.$EmailB);
    $hashC = md5("AGameOfDoko".$game_id.$PlayerC.$EmailC);
    $hashD = md5("AGameOfDoko".$game_id.$PlayerD.$EmailD);
    
    /* create hands */
    mysql_query("INSERT INTO Hand VALUES (NULL,".DB_quote_smart($game_id).",".DB_quote_smart($useridA).
		", ".DB_quote_smart($hashA).", 'start','1',NULL,NULL,NULL,'false','false',NULL)");
    $hand_idA = mysql_insert_id();							       
    mysql_query("INSERT INTO Hand VALUES (NULL,".DB_quote_smart($game_id).",".DB_quote_smart($useridB).
		", ".DB_quote_smart($hashB).", 'start','2',NULL,NULL,NULL,'false','false',NULL)");
    $hand_idB = mysql_insert_id();							       
    mysql_query("INSERT INTO Hand VALUES (NULL,".DB_quote_smart($game_id).",".DB_quote_smart($useridC).
		", ".DB_quote_smart($hashC).", 'start','3',NULL,NULL,NULL,'false','false',NULL)");
    $hand_idC = mysql_insert_id();							       
    mysql_query("INSERT INTO Hand VALUES (NULL,".DB_quote_smart($game_id).",".DB_quote_smart($useridD).
		", ".DB_quote_smart($hashD).", 'start','4',NULL,NULL,NULL,'false','false',NULL)");
    $hand_idD = mysql_insert_id();
    
    /* save cards */
    for($i=0;$i<12;$i++)
      mysql_query("INSERT INTO Hand_Card VALUES (NULL, '$hand_idA', '".$randomNR[$i]."', 'false')");
    for($i=12;$i<24;$i++)
      mysql_query("INSERT INTO Hand_Card VALUES (NULL, '$hand_idB', '".$randomNR[$i]."', 'false')");
    for($i=24;$i<36;$i++)
      mysql_query("INSERT INTO Hand_Card VALUES (NULL, '$hand_idC', '".$randomNR[$i]."', 'false')");
    for($i=36;$i<48;$i++)
      mysql_query("INSERT INTO Hand_Card VALUES (NULL, '$hand_idD', '".$randomNR[$i]."', 'false')");

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
      " ".$host."?me=";
    
    mymail($EmailA,"You are invited to a game of DoKo","Hello $PlayerA,\n".$message.$hashA);
    mymail($EmailB,"You are invited to a game of DoKo","Hello $PlayerB,\n".$message.$hashB);
    mymail($EmailC,"You are invited to a game of DoKo","Hello $PlayerC,\n".$message.$hashC);
    mymail($EmailD,"You are invited to a game of DoKo","Hello $PlayerD,\n".$message.$hashD);
    
    /*

   do things like:
     select fullname,strength,suite,game_id from hand_card left join hand on hand_id=hand.id left join user on user.id=user_id left join card on card_id=card.id where game_id='4'

    */
    
  }    
/* end set up a new game */

else if(isset($_REQUEST["me"]))
  {
     /* handle request from one specifig player,
      * the hash is set on a  per game base, so first just handle this game
      * perhaps also show links to other games in a sidebar
      */
    
    $me = $_REQUEST["me"];
    
    $myid = DB_get_userid_by_hash($me);
    
    if(!$myid)
      {
	echo "Can't find you in the database, please check the url.<br />\n";
	echo "perhaps the game has been cancled.";
	 exit();
      }
    
    $myname   = DB_get_name_by_hash($me);
    $mystatus = DB_get_status_by_hash($me);
    
    switch($mystatus)
      {
      case 'start':
	check_want_to_play($me);
	DB_set_hand_status_by_hash($me,'init');
	break;
	
      case 'init':
	if( !isset($_REQUEST["in"]) || !isset($_REQUEST["update"]))
	  {
	    DB_set_hand_status_by_hash($me,'start');
	    echo "you need to answer both question";
	  }
	else
	  {
	    if($_REQUEST["in"] == "no")
	      {
		echo "TODO: email everyone that the game has been canceld<br />";
		 /*something like
		 for($i=0;$i<4;$i++)
 		   {
 		     $message = "Hello ".$player[$hash[$i]]["name"].",\n\n".
 		       "the game has been canceled due to the request of one of the players.\n";
 		     mymail($player[$hash[$i]]["email"],"[DoKo-Debug] the game has been canceled",$message); 
 		   }
		 */
		DB_cancel_game($me);
	      }
	    else
	      {
		echo "thanks for joining the game... please scroll down";
		echo "TODO: make this page nicer<br />";
		echo "TODO: set card pref<br />";
		
		$mycards = DB_get_hand($me);
		sort($mycards);
		echo "<p class=\"mycards\">your cards are: <br />\n";
		foreach($mycards as $card) 
		  display_card($card);
		echo "</p>\n";   
		
		check_for_sickness($me,$mycards);
		
		DB_set_hand_status_by_hash($me,'check');
	      }
	   }
	break;
	
      case 'check':
	echo "no checking at the moment... you need to play a normal game";
	if(!isset($_REQUEST["solo"])    || 
	   !isset($_REQUEST["wedding"]) ||
	   !isset($_REQUEST["poverty"]) ||
	   !isset($_REQUEST["nines"]) )
	  {
	    DB_set_hand_status_by_hash($me,'init');
	    /* problem: by setting it back to init, variables "in" and "update" are 
	     * not set, so the player will be send back to the start, after seeing his hand
	     */
	    echo "you need to fill out the form";
	  }
	else
	  {
	    if( $_REQUEST["solo"]!="No")
	      {
		DB_set_solo_by_hash($me,$_REQUEST["solo"]);
		DB_set_sickness_by_hash($me,"solo");
	      }
	    else if($_REQUEST["wedding"] == "yes")
	      {
		echo "wedding was chosen<br />\n";
		DB_set_sickness_by_hash($me,"wedding");
	      }
	    else if($_REQUEST["poverty"] == "yes")
	      {
		echo "poverty was chosen<br />\n";
		DB_set_sickness_by_hash($me,"poverty");
	      }
	    else if($_REQUEST["nines"] == "yes")
	      {
		echo "nines was chosen<br />\n";
 		 DB_set_sickness_by_hash($me,"nines");
	      }
	  }
	DB_set_hand_status_by_hash($me,'poverty');
	
	/* check all players and set game to final result, e.g. solo, wedding, povert, redeal */
	
	/* reset solo, etc from players who did say something, but it didn't matter? */
	break;
      case 'poverty':
	echo "<br />poverty not handeled at the moment... you need to play a normal game<br />";
	
	/* only set this after all poverty, etc. are handeled*/
	DB_set_hand_status_by_hash($me,'play');
	break;
      case 'play':
	display_news();
	display_status();
	
	 /* get game id */
	$gameid = DB_get_gameid_by_hash($me);
	
	/* get trick ids */
	$result = mysql_query("SELECT hand_card.card_id as card,".
			      "       user.fullname as name,".
			      "       hand.position as position,".
			      "       play.sequence as sequence, ".
			      "       hand.hash     as hash,     ".
			      "       trick.id ".
			      "FROM Trick ".
			      "LEFT JOIN Play ON trick.id=play.trick_id ".
			      "LEFT JOIN Hand_Card ON play.hand_card_id=hand_card.id ".
			      "LEFT JOIN Hand ON hand_card.hand_id=hand.id ".
			      "LEFT JOIN User ON user.id=hand.user_id ".
			      "WHERE trick.game_id='".$gameid."' ".
			      "ORDER BY trick.id,sequence ASC");
	
	
	$trickNR = 1;
	
	$lasttrick = DB_get_max_trickid($gameid);
	
	$play = array(); /* needed to calculate winner later  */
	$seq=1;          
	$pos=0;
	
	echo "\n<ul class=\"oldtrick\">\n";
	echo "  <li> Hello $myname!   History: </li>\n";
	
	while($r = mysql_fetch_array($result,MYSQL_NUM))
	  {
	    $seq   = $r[3];
	    $pos   = $r[2];
	    $trick = $r[5];
	    
	    if($trick!=$lasttrick && $seq==1)
	      {
		/* start of an old trick? */
		echo "  <li onclick=\"hl('$trickNR');\"><a href=\"#\">Trick $trickNR</a>\n".
		  "    <div class=\"table\" id=\"trick".$trickNR."\">\n".
		  "      <img class=\"table\" src=\"pics/table".($pos-1).".png\" alt=\"table\" />\n";
	      }
	    else if($trick==$lasttrick && $seq==1)
	      {
		/* start of a last trick? */
		echo "  <li onclick=\"hl('$trickNR');\"><a href=\"#\">Current Trick</a>\n".
		  "    <div class=\"table\" id=\"trick".$trickNR."\">\n".
		  "      <img class=\"table\" src=\"pics/table".($pos-1).".png\" alt=\"table\" />";
	      }
	    
	    /* display card */
	    echo "      <div class=\"card".($pos-1)."\">\n";
	    
	    $play[$pos]=$r[0];
	    
	    $comment=0;
	    if($comment)
	      echo "        <span class=\"comment\">";
	    else
	      echo "        <span>";
	    
	    /* print name */
	    echo $r[1];
	    
	    /* check for comment */
	    if($comment)
	      echo "<span>".$comment."</span>";
	    echo "</span>\n        ";
	    
	    display_card($r[0]);
	    
	    echo "      </div>\n"; /* end div card */
	    
	    /* end of trick? */
	    if($seq==4)
	      {
		$trickNR++;
		echo "    </div>\n  </li>\n";  /* end div table, end li table */
	      }
	  }
	if($seq!=4) 
	  echo "    </div>\n  </li>\n";  /* end div table, end li table */
	
	echo "</ul>\n";
	
	/* whos turn is it? */
	if($seq==4)
	  {
	     $winner = get_winner($play); /* returns the position */
	     $next = $winner;
	  }
	else
	  {
	    $next = $pos+1;
	  }
	if($next==5) $next=1;
	
	/* my turn?, display cards as links, ask for comments*/
	if(DB_get_pos_by_hash($me) == $next)
	  $myturn = 1;
	else
	  $myturn = 0;
	
	/* do we want to play a card? */
	if(isset($_REQUEST["card"]) && $myturn)
	  {
	    $card   = $_REQUEST["card"];
	    $handid = DB_get_handid_by_hash($me); 
	    
	    /* check if we have card */
	    /* set played in hand_card to true where hand_id and card_id*/
	    $result = mysql_query("SELECT id FROM Hand_Card WHERE hand_id='$handid' AND card_id=".DB_quote_smart($card));
	    $r = mysql_fetch_array($result,MYSQL_NUM);
	    $handcardid = $r[0];
	    
	    if($handcardid)
	      {
		mysql_query("UPDATE Hand_Card SET played='true' WHERE hand_id='$handid' AND card_id=".DB_quote_smart($card));
		
		/* get trick id or start new trick */
		$a = DB_get_current_trickid($gameid);
		$trickid  = $a[0];
		$sequence = $a[1];
		
		DB_play_card($trickid,$handcardid,$sequence);
		echo "<div class=\"card\">";
		echo " you played  <br />";
		display_card($card);
		echo "</div>\n";
		
		if(sizeof(DB_get_hand($me))==0)
		  DB_set_hand_status_by_hash($me,'gameover');
		
		echo "TODO: email next player<br />";
	      }
	    else
	      {
		echo "couldn't find card <br />\n";
	      }
	  }
	else if(isset($_REQUEST["card"]) && !$myturn )
	  {
	    echo "please wait until it is your turn! <br />\n";
	  }
	
	$mycards = DB_get_hand($me);
	sort($mycards);
	echo "<div class=\"mycards\">\n";
	
	if($myturn && !isset($_REQUEST["card"]))
	  {
	    echo "Hello ".DB_get_name_by_hash($me).", it's your turn!  <br />\n";
	    echo "Your cards are: <br />\n";
	    echo "<form action=\"index.php?me=$me\" method=\"post\">\n";
	    foreach($mycards as $card) 
	      display_link_card($card);
?>
    <br />A short comment:<input name="comment" type="text" size="30" maxlength="50" /> 
    <input type="hidden" name="me" value="<?php echo $me; ?>" />
    <input type="submit" value="move" />
 </form>
 <?php
         }
	else
	  {
	    echo "Your cards are: <br />\n";
	    foreach($mycards as $card) 
	      display_card($card);
	  }
	echo "</div>\n";
	/*check if we still have cards left, else set status to gameover */
	
	break;
      case 'gameover':
	echo "the game is over... guess the final score should be displayed here...<br />\n";
	echo "TODO: suggest a new game with the next person as dealer <br />\n";
	break;
      default:
	echo "error in testing the status";
      }
    exit();
  } 
 else if(isset($_REQUEST["email"]) && isset($_REQUEST["password"]))
  {
    $ok=1;
    $uid = DB_get_userid_by_email($_REQUEST["email"]);
    if(!$uid)
      $ok=0;
    if(!DB_get_userid_by_passwd(md5($_REQUEST["password"])))
      $ok=0;

    if($ok)
      {
	echo "ok. your logged in, now what? :)<br />";
	
      }
    else
      {
	echo "sorry email and password don't match <br />";
      }
    exit();
  }
else if(isset($_REQUEST["register"]) )
  {
    echo "TODO: convert timezone into a menu<br />\n";
    echo "TODO: figure out a way to handle passwrods <br />\n";
?>
        <form action="index.php" method="post">
          <fieldset>
            <legend>Register</legend>
             <table>
              <tr>
               <td><label for="Rfullname">Full name:</label></td>
	       <td><input type="text" id="Rfullname" name="Rfullname" size="20" maxsize="30" /> </td>
              </tr><tr>
               <td><label for="Remail">Email:</label></td>
	       <td><input type="text" id="Remail" name="Remail" size="20" maxsize="30" /></td>
              </tr><tr>
	       <td><label for="Rpassword">Password(will be displayed in cleartext on the next page):</label></td>
               <td><input type="password" id="Rpassword" name="Rpassword" size="20" maxsize="30" /></td>
              </tr><tr>
	       <td><label for="Rtimezone">Timezone:</label></td>
               <td><input type="text" id="Rtimezone" name="Rtimezone" size="4" maxsize="4" value="+1"/></td>
              </tr><tr>
               <td colspan="2"> <input type="submit" value="register" /></td>
             </table>
          </fieldset>
        </form>
<?php
  }
else if(isset($_REQUEST["Rfullname"]) && 
	isset($_REQUEST["Remail"]   ) && 
	isset($_REQUEST["Rpassword"]) && 
	isset($_REQUEST["Rtimezone"]) )
  {
	$ok=1;
	if(DB_get_userid_by_name($_REQUEST["Rfullname"]))
	  {
	    echo "please chose another name<br />";
	    $ok=0;
	  }
	if(DB_get_userid_by_email($_REQUEST["Remail"]))
	  {
	    echo "this email address is already used ?!<br />";
	    $ok=0;
	  }
	if($ok)
	  {
	    $r=mysql_query("INSERT INTO User VALUES(NULL,".DB_quote_smart($_REQUEST["Rfullname"]).
		      ",".DB_quote_smart($_REQUEST["Remail"]).
     		      ",".DB_quote_smart(md5($_REQUEST["Rpassword"])).
		      ",".DB_quote_smart($_REQUEST["Rtimezone"]).",NULL)"); 
	    
	    if($r)
	      echo "  added you to the database";
	    else
	      echo " something went wrong";
	  }
  }
else
  { /* no new game, not in a game */
?>
    <p> If you want to play a game of Doppelkopf, you found the right place ;) </p>
    <p> Please <a href="index.php?register">register</a>, in case you haven't done yet  <br />
        or login with you email-address or name and password here:
        <form action="index.php" method="post">
          <fieldset>
            <legend>Login</legend>
             <table>
              <tr>
               <td><label for="email">Email:</label></td><td><input type="text" id="email" name="email" size="20" maxsize="30" /> </td>
              </tr><tr>
               <td><label for="password">Password:</label></td><td><input type="password" id="password" name="password" size="20" maxsize="30" /></td>
              </tr><tr>
               <td> <input type="submit" value="login" /></td>
             </table>
          </fieldset>
        </form>
 
    </p>


<?php
  }
?>
</body>
</html>

<?php

DB_close();

/*
 *Local Variables: 
 *mode: php
 *mode: hs-minor
 *End:
 */
?>


