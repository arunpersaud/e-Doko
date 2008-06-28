<?php
/* make sure that we are not called from outside the scripts, 
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

/* test id and password, should really be done in one step */
if(!isset($_SESSION["name"])) 
  {
    $email     = $_REQUEST["email"];
    $password  = $_REQUEST["password"];
  }
else
  {
    $name = $_SESSION["name"];
    $email     = DB_get_email('name',$name);
    $password  = DB_get_passwd_by_name($name);
  };

/* user has forgotten his password */
if(myisset("forgot"))
  {
    /* check if player is in the database */
    $ok = 1;

    $myid = DB_get_userid('email',$email);
    if(!$myid)
      $ok = 0;

    if($ok)
      {
	/* check how many entries in recovery table */
	$number = DB_get_number_of_passwords_recovery($myid);

	/* if less than N recent ones, add a new one and send out email */
	if( $number < 5 )
	  {
	    echo "Ok, I send you a new password. <br />";
	    if($number >1)
	      echo "N.B. You tried this already $number times during the last day and it will only work ".
		" 5 times during a day.<br />";
	    echo "The new password will be valid for one day, make sure you reset it to something else.<br />";
	    echo "Back to the  <a href=\"$INDEX\">main page</a>.";

	    /* create temporary password, use the fist 8 letters of a md5 hash */
	    $TIME  = (string) time(); /* to avoid collisions */
	    $hash  = md5("Anewpassword".$email.$TIME);
	    $newpw = substr($hash,1,8);

	    $message = "Someone (hopefully you) requested a new password. \n".
	      "You can use this email and the following password: \n".
	      "   $newpw    \n".
	      "to log into the server. The new password is valid for 24h, so make\n".
	      "sure you reset your password to something new. Your old password will\n".
	      " also still be valid until you set a new one\n";
	    mymail($email,$EmailName."recovery ",$message);

	    /* we save these in the database */
	    DB_set_recovery_password($myid,md5($newpw));
	  }
	else
	  {
	    /* make it so that people (or a robot) can request thousands of passwords within a short time
	     * and spam a user this way */
	    echo "Sorry you already tried 5 times during the last 24h.<br />".
	      "You need to use one of those passwords or wait to get a new one.<br />";
	    echo "Back to the <a href=\"$INDEX\">main page</a>.";
	  }
      }
    else
      {/* can't find user id in the database */
	     
	/* no email given? */
	if($email=="")
	  echo "You need to give me an email address! <br />".
	    "Please try <a href=\"$INDEX\">again</a>.";
	else /* default error message */
	  echo "Couldn't find a player with this email! <br />".
	    "Please contact Arun, if you think this is a mistake <br />".
	    "or else try <a href=\"$INDEX\">again</a>.";
      }
  }
else 
  { /* normal user page */
    
    /* verify password and email */
    if(strlen($password)!=32)
      $password = md5($password);
    
    $ok  = 1;
    $myid = DB_get_userid('email-password',$email,$password);
    if(!$myid)
      $ok = 0;
    
    if($ok)
      {
	/* user information is ok */
	$myname = DB_get_name('email',$email);
	$_SESSION["name"] = $myname;
	
	$PREF = DB_get_PREF($myid);
	
	DB_update_user_timestamp($myid);
	
	display_user_menu();
	
	/* display all games the user has played */
	echo "<div class=\"user\">";
	echo "<h4>These are all your games:</h4>\n";
	echo "<p>Session: <br />\n";
	echo "<span class=\"gamestatuspre\"> p </span> =  pre-game phase ";
	echo "<span class=\"gamestatusplay\">P </span> =  game in progess ";
	echo "<span class=\"gamestatusover\">F </span> =  game finished <br />";
	echo "</p>\n";
	
	$output = array();
	$result = DB_query("SELECT Hand.hash,Hand.game_id,Game.mod_date,Game.player,Game.status from Hand".
			   " LEFT JOIN Game ON Game.id=Hand.game_id".
			   " WHERE user_id='$myid'".
			   " ORDER BY Game.session,Game.create_date" );
	$gamenrold = -1;
	echo "<table>\n <tr><td>\n";
	while( $r = DB_fetch_array($result))
	  {
	    $game = DB_format_gameid($r[1]);
	    $gamenr = (int) $game;
	    if($gamenrold < $gamenr)
	      {
		if($gamenrold!=-1)
		  echo "</td></tr>\n <tr> <td>$gamenr:</td>\n";
		else
		  echo "$gamenr:</td>\n";
		$gamenrold = $gamenr;
		echo "<td class=\"usergames\">\n";
	      }
	    if($r[4]=='pre')
	      echo "   <span class=\"gamestatuspre\"><a href=\"".$INDEX."?action=game&amp;me=".$r[0]."\">p </a></span>\n";
	    else if ($r[4]=='gameover')
	      echo "   <span class=\"gamestatusover\"><a href=\"".$INDEX."?action=game&amp;me=".$r[0]."\">F </a></span>\n";
	    else
	      echo "   <span class=\"gamestatusplay\"><a href=\"".$INDEX."?action=game&amp;me=".$r[0]."\">P </a></span>\n";
	    if($r[4] != 'gameover')
	      {
		echo "</td>\n<td>\n    ";
		if($r[3]==$myid || !$r[3])
		  echo "(it's <strong>your</strong> turn)\n";
		else
		  {
		    $name = DB_get_name('userid',$r[3]);
		    $gameid = $r[1];
		    if(DB_get_reminder($r[3],$gameid)==0)
		      if(time()-strtotime($r[2]) > 60*60*24*7)
			echo "<a href=\"$INDEX?action=reminder&amp;me=".$r[0]."\">Send a reminder.</a>";
		    echo "(it's $name's turn)\n";
		  };
		if(time()-strtotime($r[2]) > 60*60*24*30)
		  echo "<a href=\"$INDEX?action=cancel&amp;me=".$r[0]."\">Cancel?</a>".
		    " (clicking here is final and can't be restored)";
	      }
	  }
	echo "</td></tr>\n</table>\n";
	
	/* display last 5 users that have signed up to e-DoKo */
	$names = DB_get_names_of_new_logins(5);
	echo "<h4>New Players:</h4>\n<p>\n";
	echo implode(", ",$names).",...\n";
	echo "</p>\n";
	
	/* display last 5 users that logged on */
	$names = DB_get_names_of_last_logins(5);
	echo "<h4>Players last logged in:</h4>\n<p>\n";
	echo implode(", ",$names).",...\n";
	echo "</p>\n";
	
	echo "</div>\n";
      }
    else
      {
	echo "<div class=\"message\">Sorry email and password don't match. Please <a href=\"$INDEX\">try again</a>. </div>";
      }
  };
?>