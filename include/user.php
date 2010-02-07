<?php
/* Copyright 2006, 2007, 2008, 2009, 2010 Arun Persaud <arun@nubati.net>
 *
 *   This file is part of e-DoKo.
 *
 *   e-DoKo is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   e-DoKo is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with e-DoKo.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

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
	      "also still be valid until you set a new one.\n";
	    $subject = 'Recovery';
	    mymail($myid,$subject,$message);

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

	display_user_menu($myid);

	/* display all games the user has played */
	echo "<div class=\"user\">";

	if($myvacation = check_vacation($myid))
	  {
	    $vac_start   = $myvacation[0];
	    $vac_stop    = $myvacation[1];
	    $vac_comment = $myvacation[2];
	    echo "<p class=\"vacation\">Enjoy your vacation (don't forgot to change your settings once you're back). Between $vac_start and $vac_stop other users will see the following message: $vac_comment.</p>\n";
	  }

	echo "<h4>These are all your games:</h4>\n";
	/* output legend */
	echo "<p>Session: <br />\n";
	echo "<span class=\"gamestatuspre\"> p </span> =  pre-game phase ";
	echo "<span class=\"gamestatusplay\">P </span> =  game in progess ";
	echo "<span class=\"gamestatusover\">E </span> =  game ended ";
	echo "<span class=\"gamestatusover multi\"><a>N</a> </span> =  N games with same hand <br />";
	echo "</p>\n";

	$output = array();
	$result = DB_query("SELECT Hand.hash,Hand.game_id,G.mod_date,G.player,G.status, ".
			   " (SELECT count(H.randomnumbers) FROM Game H WHERE H.randomnumbers=G.randomnumbers) AS count ".
			   " FROM Hand".
			   " LEFT JOIN Game G ON G.id=Hand.game_id".
			   " WHERE user_id='$myid'".
			   " ORDER BY G.session,G.create_date" );

	$gamenrold = -1;
	$count = 0;
	echo "<table>\n <tr><td>\n";
	while( $r = DB_fetch_array($result))
	  {
	    $count++;
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
	    $Multi = ($r[5]>1) ? "multi" : "";
	    if($r[4]=='pre')
	      echo "   <span class=\"gamestatuspre $Multi\"><a href=\"".$INDEX."?action=game&amp;me=".$r[0]."\">p </a></span>\n";
	    else if (in_array($r[4],array('gameover','cancel-timedout','cancel-nines','cancel-noplay','cancel-trump')))
	    {
	      echo "   <span class=\"gamestatusover $Multi\"><a href=\"".$INDEX."?action=game&amp;me=".$r[0]."\">";
	      if($r[5]<2)
		echo "E ";
	      else
		echo $r[5];
	      echo "</a></span>\n";
	    }
	    else
	      echo "   <span class=\"gamestatusplay $Multi\"><a href=\"".$INDEX."?action=game&amp;me=".$r[0]."\">P </a></span>\n";
	    if($r[4] == 'pre' || $r[4] == 'play')
	      {
		echo "</td>\n<td>\n    ";
		if($r[3]==$myid || !$r[3])
		  echo "(it's <strong>your</strong> turn)\n";
		else
		  {
		    $name = DB_get_name('userid',$r[3]);
		    $gameid = $r[1];
		    /* check if we need to send out a reminder */
		    if(DB_get_reminder($r[3],$gameid)==0)
		      if(time()-strtotime($r[2]) > 60*60*24*7)
			echo "<a href=\"$INDEX?action=reminder&amp;me=".$r[0]."\">Send a reminder.</a>";

		    /* check vacaction status of this user */
		    if($vacation=check_vacation($r[3]))
		      {
			$stop = substr($vacation[1],0,10);
			$title = 'begin:'.substr($vacation[0],0,10).' end:'.$vacation[1].' '.$vacation[2];
			echo "(it's <span class=\"vacation\" title=\"$title\">$name's (on vacation until $stop)</span> turn)\n";
		      }
		    else
		      echo "(it's $name's turn)\n";
		  };
		if(time()-strtotime($r[2]) > 60*60*24*30)
		  echo "<a href=\"$INDEX?action=cancel&amp;me=".$r[0]."\">Cancel?</a> ";
	      }
	  }
	echo "</td></tr>\n</table>\n";

	/* give a hint for new players */
	if($count<10)
	  echo "<p class=\"newbiehint\">You can start new games using the link in the top right corner!</p>\n";

	/* display last 5 users that have signed up to e-DoKo */
	$names = DB_get_names_of_new_logins(5);
	echo "<h4>New Players:</h4>\n<p>\n";
	echo implode(", ",$names).",...\n";
	echo "</p>\n";

	/* display last 5 users that logged on */
	echo "<h4>Players last logged in:</h4>\n<p>\n";

	$names  = DB_get_names_of_last_logins(7);
	$emails = DB_get_emails_of_last_logins(7);
	for($i=0;$i<7;$i++)
	  {
	    echo "<img class=\"gravatar\" title=\"".$names[$i]."\" src=\"http://www.gravatar.com/avatar/".md5(strtolower(trim($emails[$i])))."?d=identicon\" />\n";
	  }
	echo "</p>\n";

	echo "</div>\n";
      }
    else
      {
	echo "<div class=\"message\">Sorry email and password don't match. Please <a href=\"$INDEX\">try again</a>. </div>";
      }
  };
?>