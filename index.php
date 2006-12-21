<?php
error_reporting(E_ALL);

include_once("config.php");      
include_once("output.php");      /* html output only */
include_once("db.php");          /* database only */
include_once("functions.php");   /* the rest */

DB_open();

output_header();

/* check if we want to start a new game */
if(myisset("new"))
  output_form_for_new_game();

/*check if everything is ready to set up a new game */
else if( myisset("PlayerA", "PlayerB","PlayerC","PlayerD" ))
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
    $followup = NULL;
    if(myisset("followup") )
      {
	$followup= $_REQUEST["followup"];
	mysql_query("INSERT INTO Game VALUES (NULL, NULL, '$randomNRstring', NULL, NULL,'pre','$followup' ,NULL)");
      }
    else
      mysql_query("INSERT INTO Game VALUES (NULL, NULL, '$randomNRstring', NULL, NULL,'pre', NULL ,NULL)");
    $game_id = mysql_insert_id();
    
    /* create hash */
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

    /* send out email, TODO: check for error with email */
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
        
  }    
/* end set up a new game */

else if(myisset("me"))
  {
    /* handle request from one specific player,
     * the hash is set on a per game base
     */
    
    $me = $_REQUEST["me"];

    /* test for valid ID */
    $myid = DB_get_userid_by_hash($me);
    if(!$myid)
      {
	echo "Can't find you in the database, please check the url.<br />\n";
	echo "perhaps the game has been cancled, check by login in <a href=\"$host\">here</a>.";
	exit();
      }
    
    DB_update_user_timestamp($myid);

    /* get some information from the DB */
    $gameid   = DB_get_gameid_by_hash($me);
    $myname   = DB_get_name_by_hash($me);
    $mystatus = DB_get_status_by_hash($me);
    
    switch($mystatus)
      {
      case 'start':
	check_want_to_play($me);
	DB_set_hand_status_by_hash($me,'init');
	break;
      case 'init':
	if( !myisset("in","update") )
	  {
	    DB_set_hand_status_by_hash($me,'start');
	    echo "you need to answer both question";
	  }
	else
	  {
	    if($_REQUEST["in"] == "no")
	      {
		echo "TODO: email everyone that the game has been canceled.<br />";
		 /*something like need to modify for DB backend
		 for($i=0;$i<4;$i++)
 		   {
 		     $message = "Hello ".$player[$hash[$i]]["name"].",\n\n".
 		       "the game has been canceled due to the request of one of the players.\n";
 		     mymail($player[$hash[$i]]["email"],"[DoKo-Debug] the game has been canceled",$message); 
 		   }
		 */
		/* delete everything from the dB */
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
	echo "no checking at the moment... you need to play a normal game.".
	  " At the moment you need to reload this page to finish the setup.";
	if(!myisset("solo","wedding","poverty","nines") )
	  {
	    /* all these variables have a pre-selected default,
	     * so we should never get here,
	     * unless a user tries to cheat ;) */
	    echo "something went wrong...please contact the admin.";
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
	/* here we need to check if there is a solo or some other form o sickness.
	 * If so, which one counts
	 * set that one in the Game table, delete other ones form Hand table 
	 * tell people about it.
	 */
	echo "<br />poverty not handeled at the moment... you need to play a normal game<br />";
	
	/* only set this after all poverty, etc. are handeled*/
	DB_set_hand_status_by_hash($me,'play');

	/* check if the game can start  */
	$userids = DB_get_all_userid_by_gameid($gameid);
	$ok=1;
	foreach($userids as $user)
	  if(DB_get_hand_status_by_userid_and_gameid($user,$gameid)!='play')
	    $ok=0;

	if($ok)
	  DB_set_game_status_by_gameid($gameid,'play');

	break;
      case 'play':
      case 'gameover': 
	/* both entries here,  so that the tricks are visible for both.
	 * in case of 'play' there is a break later that skips the last part
	 */

	/* display useful things in divs */
	
	/* display local time */
	echo "<div class=\"time\">\n Local times:<table>";
	$users = array();
	$users = DB_get_all_userid_by_gameid($gameid);
	foreach($users as $user)
	  {
	    $offset = DB_get_user_timezone($user);
	    $zone   = return_timezone($offset);
	    date_default_timezone_set($zone);
	    $name   = DB_get_name_by_userid($user);
	    
	    echo "<tr> <td>$name</td> <td>".date("Y-m-d H:i:s")."</td></tr>\n";
	  };
	echo "</table>\n</div>\n";

	display_status();

	/* display links to the users status page */
	$result = mysql_query("SELECT email,password from User WHERE id='$myid'" );
	$r      = mysql_fetch_array($result,MYSQL_NUM);
	output_link_to_user_page($r[0],$r[1]);
	  
	display_news();

	/* end display useful things*/

	/* has the game started? No, then just wait here...*/
	$gamestatus = DB_get_game_status_by_gameid($gameid);
	if($gamestatus == 'pre')
	  {
	    echo "you need to wait for the others... <br />";
	    break; /* not sure this works... the idea is that you can 
		    * only  play a card after everyone is ready to play */
	  }
	
	/* get everything relevant to display the tricks */
	$result = mysql_query("SELECT Hand_Card.card_id as card,".
			      "       User.fullname as name,".
			      "       Hand.position as position,".
			      "       Play.sequence as sequence, ".
			      "       Hand.hash     as hash,     ".
			      "       Trick.id, ".
			      "       Comment.comment ".
			      "FROM Trick ".
			      "LEFT JOIN Play ON Trick.id=Play.trick_id ".
			      "LEFT JOIN Hand_Card ON Play.hand_card_id=Hand_Card.id ".
			      "LEFT JOIN Hand ON Hand_Card.hand_id=Hand.id ".
			      "LEFT JOIN User ON User.id=Hand.user_id ".
			      "LEFT JOIN Comment ON Play.id=Comment.play_id ".
			      "WHERE Trick.game_id='".$gameid."' ".
			      "ORDER BY Trick.id,sequence ASC");
	
	
	$trickNR = 1;
	
	$lasttrick = DB_get_max_trickid($gameid);
	
	$play = array(); /* needed to calculate winner later  */
	$seq  = 1;          
	$pos  = 0;
	
	echo "\n<ul class=\"oldtrick\">\n";
	echo "  <li> Hello $myname!   History: </li>\n";
	
	while($r = mysql_fetch_array($result,MYSQL_NUM))
	  {
	    $seq     = $r[3];
	    $pos     = $r[2];
	    $trick   = $r[5];
	    $comment = $r[6];
	    
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
		  "      <img class=\"table\" src=\"pics/table".($pos-1).".png\" alt=\"table\" />\n";
	      }
	    
	    /* display card */
	    echo "      <div class=\"card".($pos-1)."\">\n";
	    
	    $play[$pos]=$r[0];
	    
	    if($comment!="")
	      echo "        <span class=\"comment\">";
	    else
	      echo "        <span>";
	    
	    /* print name */
	    echo $r[1];
	    
	    /* check for comment */
	    if($comment!="")
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

	if($seq!=4 && $trickNR>1) 
	  echo "    </div>\n  </li>\n";  /* end div table, end li table */
	
	echo "</ul>\n";
	
	/* whos turn is it? */
	if($seq==4)
	  {
	     $winner = get_winner($play,"normal"); /* returns the position */
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
	if(myisset("card") && $myturn)
	  {
	    $card   = $_REQUEST["card"];
	    $handid = DB_get_handid_by_hash($me); 
	    
	    /* check if we have card and that we haven't played it yet*/
	    /* set played in hand_card to true where hand_id and card_id*/
	    $result = mysql_query("SELECT id FROM Hand_Card WHERE played='false' and ".
				  "hand_id='$handid' AND card_id=".DB_quote_smart($card));
	    $r = mysql_fetch_array($result,MYSQL_NUM);
	    $handcardid = $r[0];
	    
	    if($handcardid)
	      {
		mysql_query("UPDATE Hand_Card SET played='true' WHERE hand_id='$handid' AND card_id=".DB_quote_smart($card));
		
		/* get trick id or start new trick */
		$a = DB_get_current_trickid($gameid);
		$trickid  = $a[0];
		$sequence = $a[1];
		
		$playid = DB_play_card($trickid,$handcardid,$sequence);

		/*check for coment */
		if(myisset("comment"))
		  {
		    DB_insert_comment($_REQUEST["comment"],$playid,$myid);
		  };  

		echo "<div class=\"card\">";
		echo " you played  <br />";
		display_card($card);
		echo "</div>\n";
		

		/*check if we still have cards left, else set status to gameover */
		if(sizeof(DB_get_hand($me))==0)
		  {
		    DB_set_hand_status_by_hash($me,'gameover');
		    $mystatus='gameover';
		  }
		
		/* if all players are done, set game status to game over */
		$userids = DB_get_all_userid_by_gameid($gameid);
		$done=1;
		foreach($userids as $user)
		  if(DB_get_hand_status_by_userid_and_gameid($user,$gameid)!='gameover')
		    $done=0;

		if($done)
		  DB_set_game_status_by_gameid($gameid,"gameover");
		
		/* email next player */
		if(DB_get_game_status_by_gameid($gameid)=='play')
		  {
		    if($sequence==4)
		      {
			$play   = DB_get_cards_by_trick($trickid);
			$winner = get_winner($play,"normal"); /* returns the position */
			$next = $winner;
		      }
		    else
		      {
			$next = DB_get_pos_by_hash($me)+1;
		      }
		    if($next==5) $next=1;

		    /* email next player */
		    $next_hash = DB_get_hash_from_game_and_pos($gameid,$next);
		    $email     = DB_get_email_by_hash($next_hash);

		    $message = "It's your turn  now.\n".
		      "Use this link to play a card: ".$host."?me=".$next_hash."\n\n" ;
		    mymail($email,"[DoKo-debug] a card has been played",$message);
		    
		    if($debug)
		      echo "DEBUG:<a href=\"index.php?me=".DB_get_hash_from_game_and_pos($gameid,$next).
			"\"> next player </a> <br />\n";

		  }
	      }
	    else
	      {
		echo "couldn't find card <br />\n";
	      }
	  }
	else if(myisset("card") && !$myturn )
	  {
	    echo "please wait until it is your turn! <br />\n";
	  }
	
	$mycards = DB_get_hand($me);
	sort($mycards);
	echo "<div class=\"mycards\">\n";
	
	if($myturn && !myisset("card"))
	  {
	    echo "Hello ".$myname.", it's your turn!  <br />\n";
	    echo "Your cards are: <br />\n";
	    echo "<form action=\"index.php?me=$me\" method=\"post\">\n";
	    foreach($mycards as $card) 
	      display_link_card($card);

	    echo "<br />\nA short comments:<input name=\"comment\" type=\"text\" size=\"30\" maxlength=\"50\" />\n";
	    echo "<input type=\"hidden\" name=\"me\" value=\"$me\" />\n";
	    echo "<input type=\"submit\" value=\"move\" />\n";
	    echo "</form>\n";
         }
	else if($mystatus=='play')
	  {
	    echo "Your cards are: <br />\n";
	    foreach($mycards as $card) 
	      display_card($card);
	  }
	echo "</div>\n";

	/* check if we need to set status to 'gameover' is done during playing of the card */
	if($mystatus=='play')
	  break;
	/* the following happens only when the gamestatus is 'gameover' */
	/* check if game is over, display results */
	if(DB_get_game_status_by_gameid($gameid)=='play')
	  {
	    echo "the game is over for you.. other people still need to play though";
	  }
	else
	  {
	    echo "the game is over now... guess the final score should be displayed here...<br />\n";
	    
	    /* suggest a new game with the same people in it, just rotated once */
	    $names = DB_get_all_names_by_gameid($gameid);
	    output_ask_for_new_game($names[1],$names[2],$names[3],$names[0],$gameid);
	  }
	break;
      default:
	echo "error in testing the status";
      }
    exit();
  } 
/* user status page */ 
    else if(myisset("email","password"))
  {
    /* test id and password, should really be done in one step */
    $email     = $_REQUEST["email"];
    $password  = $_REQUEST["password"];

    if(strlen($password)!=32)
      $password = md5($password);

    $ok=1;
    $uid = DB_get_userid_by_email_and_password($email,$password);
    if(!$uid)
      $ok=0;

    if($ok)
      {
	$time = DB_get_user_timestamp($uid);
	$unixtime =strtotime($time);
	
	$offset = DB_get_user_timezone($uid);
	$zone = return_timezone($offset);
	date_default_timezone_set($zone);

	echo "last login: ".date("r",$unixtime)."<br />";

	DB_update_user_timestamp($uid);

	echo "<p>these are the games you are playing in:<br />\n";
	$result = mysql_query("SELECT hash,game_id from Hand WHERE user_id='$uid' AND status<>'gameover'" );
	while( $r = mysql_fetch_array($result,MYSQL_NUM))
	  echo "<a href=\"".$host."?me=".$r[0]."\">game #".$r[1]." </a><br />";
	echo "</p>\n";

	$names = DB_get_all_names();
	echo "<p>registered players:<br />\n";
	foreach ($names as $name)
	  echo "$name <br />\n";
	echo "</p>\n";

	echo "<p>Want to start a new game? remember 4 names from the list above and visit ".
	  "<a href=\"".$host."?new\">this page.</a></p>";
      }
    else
      {
	echo "sorry email and password don't match <br />";
      }
    exit();
  }
/* page for registration */
else if(myisset("register") )
  {
    output_register();
  }
/* new user wants to register */
 else if(myisset("Rfullname","Remail","Rpassword","Rtimezone") )
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
	      echo " added you to the database";
	    else
	      echo " something went wrong";
	  }
  }
/* default login page */
else
  { 
    output_home_page();
  }

output_footer();

DB_close();

/*
 *Local Variables: 
 *mode: php
 *mode: hs-minor
 *End:
 */
?>


