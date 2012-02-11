<?php
/* Copyright 2006, 2007, 2008, 2009, 2010, 2011, 2012 Arun Persaud <arun@nubati.net>
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
if(!isset($_SESSION['name']))
  {
    $email     = $_REQUEST['email'];
    $password  = $_REQUEST['password'];
  }
else
  {
    $name = $_SESSION['name'];
    $email     = DB_get_email('name',$name);
    $password  = DB_get_passwd_by_name($name);
  };

global  $ADMIN_NAME;

/* user has forgotten his password */
if(myisset('forgot'))
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
	    echo _('Ok, I will send you a new password.').' <br />';
	    if($number >1)
	      echo "N.B. You tried this already $number times during the last day and it will only work ".
		" 5 times during a day.<br />";
	    echo _('The new password will be valid for one day, make sure you reset it to something else.').'<br />';
	    echo "Back to the  <a href=\"$INDEX\">main page</a>.";

	    /* create temporary password, use the fist 8 letters of a md5 hash */
	    $TIME  = (string) time(); /* to avoid collisions */
	    $hash  = md5('Anewpassword'.$email.$TIME);
	    $newpw = substr($hash,1,8);

	    $message = "Someone (hopefully you) requested a new password. \n".
	      "You can use this email and the following password: \n".
	      "   $newpw    \n".
	      "to log into the server. The new password is valid for 24h, so make\n".
	      "sure you reset your password to something new. Your old password will\n".
	      "also still be valid until you set a new one.\n";
	    mymail($myid,0, GAME_RECOVERY, $message);

	    /* we save these in the database */
	    DB_set_recovery_password($myid,md5($newpw));
	  }
	else
	  {
	    /* make it so that people (or a robot) can request thousands of passwords within a short time
	     * and spam a user this way */
	    echo _('Sorry you already tried 5 times during the last 24h.<br />'.
		   'You need to use one of those passwords or wait to get a new one.').'<br />';
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
	    "Please contact $ADMIN_NAME, if you think this is a mistake <br />".
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
	$_SESSION['name'] = $myname;

	$PREF = DB_get_PREF($myid);

	DB_update_user_timestamp($myid);

	display_user_menu($myid);

	/* display all games the user has played */
	echo '<div class="user">';

	if($myvacation = check_vacation($myid))
	  {
	    $vac_start   = $myvacation[0];
	    $vac_stop    = $myvacation[1];
	    $vac_comment = $myvacation[2];
	    echo '<p class="vacation">'._("Enjoy your vacation (don't forgot to change your settings once you're back).").
	      " Between $vac_start and $vac_stop other users will see the following message: $vac_comment.</p>\n";
	  }

	echo '<h4>'._('These are your games').":</h4>\n";
	/* output legend */
	echo "<p>\n";
	echo ' <span class="gamestatuspre"> &nbsp; </span> &nbsp;'._('pre-game phase');
	echo ' <span class="gamestatusplay"> &nbsp; </span> &nbsp;'._('game in progess');
	echo ' <span class="gamestatusover "><a>N</a> </span> &nbsp;'._('game over (N people played this hand)').' <br />';
	echo ' '._("Reminder: canceling a game can't be reversed!");
	echo "</p>\n";

	/* get all games */
	$output = array();
	$result = DB_query("SELECT Hand.hash,Hand.game_id,G.mod_date,G.player,G.status, ".
			   " (SELECT count(H.randomnumbers) FROM Game H WHERE H.randomnumbers=G.randomnumbers) AS count, ".
			   " G.session".
			   " FROM Hand".
			   " LEFT JOIN Game G ON G.id=Hand.game_id".
			   " WHERE user_id='$myid'".
			   " ORDER BY G.session,G.create_date" );

	/* sort into active and passive sessions */
	$count   = 0; /* count number of games to check for beginner status */
	$session = -1;
	$maxgame =  0;
	$output_active   = "";
	$output_inactive = "";
	$sessionoutput   = "";
	$gameoutput      = "";
	$keep_going = 2;
	while( $keep_going )
	  {
	    /* get next element */
	    $r = DB_fetch_array($result);

	    if($r)
	      $count++;
	    else
	      {
		/* need to run the while loop one more time when we run out of elements in the database */
		$keep_going--;
		$r[0] = NULL;
		$r[1] = NULL;
		$r[2] = NULL;
		$r[3] = NULL;
		$r[4] = NULL;
		$r[5] = NULL;
		$r[6] = -2;
	      }
	    if( $r[6]==$session )
	      {
		/* same session, update information */
		$maxgame++;
		$myhash        = $r[0];
		$gameid        = $r[1];
		$gamemoddate   = $r[2];
		$userid        = $r[3];
		$gamestatus    = $r[4];
		$gamefrequence = $r[5];

		/* create output */
		$sessionoutput .= $gameoutput;
		$gameoutput     = "   <span class=\"gamestatusover \"><a href=\"".$INDEX."?action=game&amp;me=".$myhash."\">"
		  .$gamefrequence."</a></span>\n";
	      }
	    else
	      {	/* new session */

		/* output old session if available */
		if($maxgame)
		  {
		    /* is session active? */
		    if($gamestatus == 'pre' || $gamestatus== 'play' || time()-strtotime($gamemoddate) < 60*60*24*5 )
		      {
			$output_active .= "<li> ";
			if($gamestatus == 'pre')
			  $output_active .= '<span class="gamestatuspre gameid">';
			else if($gamestatus == 'play')
			  $output_active .= '<span class="gamestatusplay gameid">';
			else
			  $output_active .= '<span class="gamestatusover gameid">';
			$output_active .= "<a href=\"$INDEX?action=game&amp;me=$myhash\">".
			  DB_format_gameid($gameid).'</a></span>&nbsp;&nbsp;&nbsp;';



			/* who's turn is it? */
			if( $gamestatus == 'pre' || $gamestatus == 'play')
			  {
			    $output_active .= '<span class="turn">';
			    if($userid==$myid || !$userid)
			      $output_active .= ' <strong>'._('your turn')."</strong>\n";
			    else
			      {
				$name = DB_get_name('userid',$userid);

				/* check vacaction status of this user */
				if($vacation=check_vacation($userid))
				  {
				    $stop = substr($vacation[1],0,10);
				    $title = 'begin:'.substr($vacation[0],0,10).' end:'.$vacation[1].' '.$vacation[2];
				    $output_active .= " <span class=\"vacation\" title=\"$title\">$name's (on vacation until $stop)</span> turn\n";
				  }
				else
				  $output_active .= "$name's turn\n";

				/* check if we need to send out a reminder */
				if(DB_get_reminder($userid,$gameid)==0)
				  if(time()-strtotime($gamemoddate) > 60*60*24*7)
				    $output_active .= "<a href=\"$INDEX?action=reminder&amp;me=".$myhash."\">"._('Send a reminder?').'</a> ';

			      };
			    $output_active .= '</span>';

			    if(time()-strtotime($gamemoddate) > 60*60*24*30)
			      $output_active .= "<a href=\"$INDEX?action=cancel&amp;me=".$myhash."\">Cancel?</a> ";
			  }

			if($maxgame>1)
			  {
			    $output_active .= ' <span class="gamesshowsession"><a href="#">'._('show old').'</a></span>'.
			      '  <span class="gameshidesession"><a href="#">'._('hide old').'</a></span><br />'."\n";
			    $output_active .= ' <span class="gamessession">'.$sessionoutput.'</span>';
			  }

			$output_active .= "</li>\n";

		      }
		    else
		      {
			/* session is not active anymore */
			$output_inactive .= "<li> $session:" ;
			$output_inactive .= $sessionoutput.$gameoutput ;
			$output_inactive .= "</li>\n";
		      }

		    /* reset all session variables */
		    $maxgame =  0;
		    $sessionoutput = "";
		    $gameoutput    = "";

		  }

		/* save game information */
		$maxgame++;
		$myhash        = $r[0];
		$gameid        = $r[1];
		$gamemoddate   = $r[2];
		$userid        = $r[3];
		$gamestatus    = $r[4];
		$gamefrequence = $r[5];
		$session       = $r[6];

		/* create output */
		$sessionoutput .= $gameoutput;
		$gameoutput     = "   <span class=\"gamestatusover \"><a href=\"".$INDEX."?action=game&amp;me=".$myhash."\">"
		  .$gamefrequence."</a></span>\n";

	      }
	  }

	echo "<ul>\n ";
	echo ' <li><span class="gameshowall"><a href="#">'._('show all').'</a></span> <span class="gamehideall"><a href="#">'._('hide all')."</a></span></li>\n";
	echo $output_active;
	echo ' <li><span class="gamesshowsession"><a href="#">'._('show inactive').'</a></span><span class="gameshidesession"><a href="#">'._('hide inactive').'</a></span><ul class="gamessession">'."$output_inactive </ul></li>";
	echo "</ul>\n";

	/* give a hint for new players */
	if($count<10)
	  echo '<p class="newbiehint">'._('You can start new games using the link in the top right corner!')."</p>\n";

	/* display last 5 users that have signed up to e-DoKo */
	$names = DB_get_names_of_new_logins(5);
	echo '<h4>'._('New Players').":</h4>\n<p>\n";
	echo implode(", ",$names).",...\n";
	echo "</p>\n";

	/* display last 5 users that logged on */
	echo '<h4>'._('Players last logged in').":</h4>\n<p>\n";

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
	echo '<div class="message">'."Sorry email and password don't match. Please <a href=\"$INDEX\">try again</a>.".' </div>';
      }
  };
?>