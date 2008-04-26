<?php
/* make sure that we are not called from outside the scripts, 
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

if(!myisset("me"))
  {
    echo "Hmm, you really shouldn't mess with the urls.<br />\n";
    output_footer();
    DB_close();
    exit();
  }

$me = $_REQUEST["me"];

/* test for valid ID */
$myid = DB_get_userid('hash',$me);
if(!$myid)
  {
    echo "Can't find you in the database, please check the url.<br />\n";
    echo "perhaps the game has been canceled, check by login in <a href=\"$INDEX\">here</a>.";
    output_footer();
    DB_close();
    exit();
  }
    
/* user might get here by clicking on the link in an email, so session might not be set */
if(isset($_SESSION["name"]))
  output_status($_SESSION["name"]);

/* the user had done something, update the timestamp */
DB_update_user_timestamp($myid);

/* get some information from the DB */
$gameid   = DB_get_gameid_by_hash($me);
$myname   = DB_get_name('hash',$me);
$mystatus = DB_get_status_by_hash($me);
$mypos    = DB_get_pos_by_hash($me);
$myhand   = DB_get_handid('hash',$me);
$session  = DB_get_session_by_gameid($gameid);

/* get prefs and save them */
DB_get_PREF($myid);

/* get rule set for this game */
$result = mysql_query("SELECT * FROM Rulesets".
		      " LEFT JOIN Game ON Game.ruleset=Rulesets.id ".
		      " WHERE Game.id='$gameid'" );
$r      = mysql_fetch_array($result,MYSQL_NUM);

$RULES["dullen"]      = $r[2];
$RULES["schweinchen"] = $r[3];
$RULES["call"]        = $r[4];

/* get some infos about the game */
$gametype   = DB_get_gametype_by_gameid($gameid);
$gamestatus = DB_get_game_status_by_gameid($gameid);
$GT         = $gametype;
if($gametype=="solo")
  {
    $gametype = DB_get_solo_by_gameid($gameid);
    $GT  = $gametype." ".$GT;
  }

/* does anyone have both foxes */
$GAME["schweinchen"]=0;
for($i=1;$i<5;$i++)
  {
    $hash  = DB_get_hash_from_game_and_pos($gameid,$i);
    $cards = DB_get_all_hand($hash);
    if( in_array("19",$cards) && in_array("20",$cards) )
      {
	$GAME["schweinchen"]=1;
	$GAME["schweinchen-who"]=$hash;
      }
  };

/* put everyting in a form */
echo "<form action=\"index.php?action=game&me=$me\" method=\"post\">\n";

/* output game */

/* output extra division in case this game is part of a session */
if($session)
  {
    echo "<div class=\"session\">\n".
      "This game is part of session $session: \n";
    $hashes = DB_get_hashes_by_session($session,$myid);
    $i = 1;
    foreach($hashes as $hash)
      {
	if($hash == $me)
	  echo "$i \n";
	else
	  echo "<a href=\"".$INDEX."?action=game&me=".$hash."\">$i</a> \n";
	$i++;
      }
    echo "</div>\n";
  }

/* display the table and the names */
display_table();

/* mystatus gets the player through the different stages of a game.
 * start:    does the player want to play?
 * init:     check for sickness
 * check:    check for return values from init
 * poverty:  handle poverty, wait here until all player have reached this state
 *           display sickness and move on to game
 * play:     game in progress
 * gameover: are we revisiting a game
 */
switch($mystatus)
  {
  case 'start':
    if( !myisset("in") )
      {
	/* asks the player, if he wants to join the game */
	output_check_want_to_play($me);
	break;
      }
    else
      {
	/* check the result, if player wants to join, got next stage, else cancel game */
	if($_REQUEST["in"] == "no")
	  {
	    /* cancel the game */
	    $message = "Hello, \n\n".
	      "the game has been canceled due to the request of one of the players.\n";

	    $userids = DB_get_all_userid_by_gameid($gameid);
	    foreach($userids as $user)
	      {
		$To = DB_get_email('userid',$user);
		mymail($To,$EmailName."game ".DB_format_gameid($gameid)." canceled",$message);
	      }

	    /* delete everything from the dB */
	    DB_cancel_game($me);
	    break;
	  }
	else
	  {
	    /* user wants to join the game */

	    /* move on to the next stage,
	     * no break statement to immediately go to the next stage
	     */

	    DB_set_hand_status_by_hash($me,'init');

	    /* check if everyone has reached this stage, send out email */
	    $userids = DB_get_all_userid_by_gameid($gameid);
	    $ok = 1;
	    foreach($userids as $user)
	      {
		$userstat = DB_get_hand_status_by_userid_and_gameid($user,$gameid);
		if($userstat!='init')
		  {
		    /* whos turn is it? */
		    DB_set_player_by_gameid($gameid,$user);
		    $ok = 0;
		  }
	      };
	    if($ok)
	      {
		/* all done, send out email unless this player is the startplayer */
		$startplayer = DB_get_startplayer_by_gameid($gameid);
		if($mypos == $startplayer)
		  {
		    /* do nothing, go to next stage */
		  }
		else
		  {
		    /* email startplayer */
		    /*
		     $email       = DB_get_email('position-gameid',$startplayer,$gameid);
		     $hash        = DB_get_hash_from_game_and_pos($gameid,$startplayer);
		     $who         = DB_get_userid('email',$email);
		     DB_set_player_by_gameid($gameid,$who);

		     $message = "It's your turn now in game ".DB_format_gameid($gameid).".\n".
		     "Use this link to go the game: ".$HOST.$INDEX."?action=game&me=".$hash."\n\n" ;
		     mymail($email,$EmailName."ready, set, go... (game ".DB_format_gameid($gameid).") ",$message);
		    */
		  }
	      }
	  }
      }
  case 'init':

    $mycards = DB_get_hand($me);
    sort($mycards);

    output_check_for_sickness($me,$mycards);

    echo "<p class=\"mycards\">Your cards are: <br />\n";
    foreach($mycards as $card)
      display_card($card,$PREF["cardset"]);
    echo "</p>\n";

    /* move on to the next stage*/
    DB_set_hand_status_by_hash($me,'check');
    break;

  case 'check':
    /* ok, user is in the game, saw his cards and selected his vorbehalt
     * so first we check what he selected
     */
    if(!myisset("solo","wedding","poverty","nines") )
      {
	/* all these variables have a pre-selected default,
	 * so we should never get here,
	 * unless a user tries to cheat ;)
	 * can also happen if user reloads the page!
	 */
	echo "<p class=\"message\"> You need to answer the <a href=\"$INDEX?action=game&me=$me&in=yes\">questions</a>.</p>";
	DB_set_hand_status_by_hash($me,'init');
      }
    else
      {
	/* check if someone selected more than one vorbehalt */
	$Nvorbehalt = 0;
	if($_REQUEST["solo"]!="No")       $Nvorbehalt++;
	if($_REQUEST["wedding"] == "yes") $Nvorbehalt++;
	if($_REQUEST["poverty"] == "yes") $Nvorbehalt++;
	if($_REQUEST["nines"] == "yes")   $Nvorbehalt++;

	if($Nvorbehalt>1)
	  {
	    echo "<p class=\"message\"> You selected more than one vorbehalt, please go back ".
	      "and answer the <a href=\"$INDEX?action=game&me=$me&in=yes\">question</a> again.</p>";
	    DB_set_hand_status_by_hash($me,'init');
	  }
	else
	  {
	    echo "<p class=\"message\">Processing what you selected in the last step...";

	    /* check if this sickness needs to be handled first */
	    $gametype    = DB_get_gametype_by_gameid($gameid);
	    $startplayer = DB_get_startplayer_by_gameid($gameid);

	    if( $_REQUEST["solo"]!="No")
	      {
		/* user wants to play a solo */

		/* store the info in the user's hand info */
		DB_set_solo_by_hash($me,$_REQUEST["solo"]);
		DB_set_sickness_by_hash($me,"solo");

		echo "<br />Seems like you want to play a ".$_REQUEST["solo"]." solo. Got it.<br />\n";

		if($gametype == "solo" && $startplayer<$mypos)
		  {}/* do nothing, since someone else already is playing solo */
		else
		  {
		    /* this solo comes first
		     * store info in game table
		     */
		    DB_set_gametype_by_gameid($gameid,"solo");
		    DB_set_startplayer_by_gameid($gameid,$mypos);
		    DB_set_solo_by_gameid($gameid,$_REQUEST["solo"]);
		  };
	      }
	    else if($_REQUEST["wedding"] == "yes")
	      {
		/* TODO: add silent solo somewhere*/
		echo "Ok, you don't want to play a silent solo...wedding was chosen.<br />\n";
		DB_set_sickness_by_hash($me,"wedding");
	      }
	    else if($_REQUEST["poverty"] == "yes")
	      {
		echo "Don't think you can win with just a few trump...? ok, poverty chosen <br />\n";
		DB_set_sickness_by_hash($me,"poverty");
	      }
	    else if($_REQUEST["nines"] == "yes")
	      {
		echo "What? You just don't want to play a game because you have a few nines? Well, if no one".
		  " is playing solo, this game will be canceled.<br />\n";
		DB_set_sickness_by_hash($me,"nines");
	      }

	    echo " Ok, done with checking, please go to the <a href=\"$INDEX?action=game&me=$me\">next step of the setup</a>.</p>";

	    /* move on to the next stage*/
	    DB_set_hand_status_by_hash($me,'poverty');

	    /* check if everyone has reached this stage, send out email */
	    $userids = DB_get_all_userid_by_gameid($gameid);
	    $ok = 1;
	    foreach($userids as $user)
	      {
		$userstat = DB_get_hand_status_by_userid_and_gameid($user,$gameid);
		if($userstat!='poverty' && $userstat!='play')
		  {
		    $ok = 0;
		    DB_set_player_by_gameid($gameid,$user);
		  }
	      };
	    if($ok)
	      {
		/* reset player = everyone has to do something now */
		DB_set_player_by_gameid($gameid,NULL);

		foreach($userids as $user)
		  {
		    $To       = DB_get_email('userid',$user);
		    $userhash = DB_get_hash_from_gameid_and_userid($gameid,$user);
		    if($userhash != $me)
		      {
			$message = "Everyone finish the questionary in game ".DB_format_gameid($gameid).", ".
			  "please visit this link now to continue: \n".
			  " ".$HOST.$INDEX."?action=game&me=".$userhash."\n\n" ;
			mymail($To,$EmailName." finished setup in game ".DB_format_gameid($gameid),$message);
		      }
		  };
	      };
	  };
      };
    break;

  case 'poverty':
    /* here we need to check if there is a solo or some other form of sickness.
     * If so, which one is the most important one
     * set that one in the Game table
     * tell people about it.
     */
    echo "<div class=\"message\">\n";
    echo "<p> Checking if someone else selected solo, nines, wedding or poverty.</p>";

    /* check if everyone has reached this stage */
    $userids = DB_get_all_userid_by_gameid($gameid);
    $ok = 1;
    foreach($userids as $user)
      {
	$userstat = DB_get_hand_status_by_userid_and_gameid($user,$gameid);
	if($userstat!='poverty' && $userstat!='play')
	  $ok = 0;
      };

    if(!$ok)
      {
	echo "This step can only be handled after everyone finished the last step. ".
	  "Seems like this is not the case, so you need to wait a bit... ".
	  "you will get an email once that is the case, please use the link in ".
	  "that email to continue the game.<br />";
      }
    else
      {
	echo "Everyone has finished checking their cards, let's see what they said...<br />";

	/* check what kind of game we are playing,  in case there are any solos this already
	 *will have the correct information in it */
	$gametype    = DB_get_gametype_by_gameid($gameid);
	$startplayer = DB_get_startplayer_by_gameid($gameid);

	/* check for different sickness and just output a general info */
	$nines   = 0;
	$poverty = 0;
	$wedding = 0;
	$solo    = 0;
	foreach($userids as $user)
	  {
	    $name     = DB_get_name('userid',$user);
	    $usersick = DB_get_sickness_by_userid_and_gameid($user,$gameid);
	    if($usersick == 'nines')
	      {
		$nines = $user;
		echo "$name has a Vorbehalt. <br />";
		break;
	      }
	    else if($usersick == 'poverty')
	      {
		$poverty++;
		echo "$name has a Vorbehalt. <br />";
	      }
	    else if($usersick == 'wedding')
	      {
		$wedding=$user;
		echo "$name has a Vorbehalt. <br />"  ;
	      }
	    else if($usersick == 'solo')
	      {
		$solo++;
		echo "$name has a Vorbehalt. <br />"  ;
	      }
	  }

	/* now check which sickness comes first and set the gametype to it */

	if($gametype == "solo")
	  {
	    /* do nothing */
	  }
	else if($nines)
	  {
	    /* cancel game */
	    /* TODO: should we keep statistics of this? */
	    $message = "Hello, \n\n".
	      " the game has been canceled because ".DB_get_name('userid',$nines).
	      " has five or more nines and nobody is playing solo.\n\n".
	      " To redeal either start a new game or, in case the game was part of a tournament, \n".
	      " go to the last game and use the link at the bottom of the page to redeal.";

	    $userids = DB_get_all_userid_by_gameid($gameid);
	    foreach($userids as $user)
	      {
		$To = DB_get_email('userid',$user);
		mymail($To,$EmailName."game ".DB_format_gameid($gameid)." canceled",$message);
	      }

	    /* delete everything from the dB */
	    DB_cancel_game($me);

	    echo "The game has been canceled because ".DB_get_name('userid',$nines).
	      " has five or more nines and nobody is playing solo.\n";
	    output_footer();
	    DB_close();
	    exit();
	  }
	else if($poverty==1) /* one person has poverty */
	  {
	    DB_set_gametype_by_gameid($gameid,"poverty");
	    $gametype = "poverty";
	    $who      = DB_get_sickness_by_gameid($gameid);
	    if(!$who)
	      {
		$firstsick = DB_get_sickness_by_pos_and_gameid(1,$gameid);
		if($firstsick == "poverty")
		  DB_set_sickness_by_gameid($gameid,2); /* who needs to be asked first */
		else
		  DB_set_sickness_by_gameid($gameid,1); /* who needs to be asked first */
	      }
	  }
	else if($poverty==2) /* two people have poverty */
	  {
	    DB_set_gametype_by_gameid($gameid,"dpoverty");
	    $gametype = "dpoverty";
	    $who      = DB_get_sickness_by_gameid($gameid);
	    if(!$who)
	      {
		$firstsick = DB_get_sickness_by_pos_and_gameid(1,$gameid);
		if($firstsick == "poverty")
		  {
		    $seconsick = DB_get_sickness_by_pos_and_gameid(1,$gameid);
		    if($secondsick == "poverty")
		      DB_set_sickness_by_gameid($gameid,30); /* who needs to be asked first */
		    else
		      DB_set_sickness_by_gameid($gameid,20); /* who needs to be asked first */
		  }
		else
		  DB_set_sickness_by_gameid($gameid,10); /* who needs to be asked first */
	      }
	  }
	else if($wedding> 0)
	  {
	    DB_set_gametype_by_gameid($gameid,"wedding");
	    DB_set_sickness_by_gameid($gameid,'-1'); /* wedding not resolved yet */
	    $gametype = "wedding";
	  };

	echo "<br />\n";

	/* now the gametype is set correctly (shouldn't matter that this is calculated for every user)
	 * output what kind of game we have */

	$poverty = 0;
	foreach($userids as $user)
	  {
	    /* userids are sorted by position...
	     * so output whatever the first one has, then whatever the next one has
	     * stop when the sickness is the same as the gametype
	     */

	    $name     = DB_get_name('userid',$user);
	    $usersick = DB_get_sickness_by_userid_and_gameid($user,$gameid);

	    if($usersick)
	      echo "$name has $usersick. <br />"; /*TODO: perhaps save this in a string and store in Game? */

	    if($usersick=="poverty")
	      $poverty++;
	    if($usersick == "wedding" && $gametype =="wedding")
	      break;
	    if($usersick == "poverty" && $gametype =="poverty")
	      break;
	    if($usersick == "poverty" && $gametype =="dpoverty" && $poverty==2)
	      break;
	    if($usersick == "solo" && $gametype =="solo")
	      break;
	  };

	/* output Schweinchen in case the rules need it */
	if( $gametype != "solo")
	  if($GAME["schweinchen"] && $RULES["schweinchen"]=="both" )
	    echo DB_get_name('hash',$GAME["schweinchen-who"])." has Schweinchen. <br />";

	echo "<br />\n";

	/* finished the setup, set re/contra parties if possible, go to next stage unless there is a case of poverty*/
	switch($gametype)
	  {
	  case "solo":
	    /* are we the solo player? set us to re, else set us to contra */
	    $pos = DB_get_pos_by_hash($me);
	    if($pos == $startplayer)
	      DB_set_party_by_hash($me,"re");
	    else
	      DB_set_party_by_hash($me,"contra");
	    DB_set_hand_status_by_hash($me,'play');
	    break;

	  case "wedding":
	    /* set person with the wedding to re, do the rest during the game */
	    $usersick = DB_get_sickness_by_userid_and_gameid($myid,$gameid);
	    if($usersick == "wedding")
	      DB_set_party_by_hash($me,"re");
	    else
	      DB_set_party_by_hash($me,"contra");

	    echo "Whoever will make the first trick will be on the re team. <br />\n";
	    echo " Ok, the game can start now, please finish <a href=\"$INDEX?action=game&me=$me\">the setup</a>.<br />";
	    DB_set_hand_status_by_hash($me,'play');
	    break;

	  case "normal":
	    $hand = DB_get_all_hand($me);

	    if(in_array('3',$hand)||in_array('4',$hand))
	      DB_set_party_by_hash($me,"re");
	    else
	      DB_set_party_by_hash($me,"contra");
	    DB_set_hand_status_by_hash($me,'play');
	    break;
	  case "poverty":
	  case "dpoverty":
	    /* check if poverty resolved (e.g. DB.Game who set to NULL)
	     *   yes? =>trump was taken, start game; break;
	     */
	    $who = DB_get_sickness_by_gameid($gameid);
	    if($who<0)
	      { /* trump has been taken */
		DB_set_hand_status_by_hash($me,'play');
		break;
	      };

	    if($who>9) /*= two people still have trump on the table*/
	      $add = 10;
	    else
	      $add = 1;

	    /* check if we are being asked now
	     *    no? display wait message, e.g. player X is asked at the moment
	     */
	    $usersick = DB_get_sickness_by_userid_and_gameid($myid,$gameid);
	    if(myisset("trump") && $_REQUEST["trump"]=="no" && ($who==$mypos || $who==$mypos*10))
	      {
		/* user doesn't want to take trump */
		/* set next player who needs to be asked */
		$firstsick  = (string) DB_get_sickness_by_pos_and_gameid($mypos+1,$gameid);
		$secondsick = (string) DB_get_sickness_by_pos_and_gameid($mypos+2,$gameid);

		if($firstsick=="poverty")
		  {
		    if($secondsick=="poverty")
		      DB_set_sickness_by_gameid($gameid,$who+$add*3);
		    else
		      DB_set_sickness_by_gameid($gameid,$who+$add*2);
		  }
		else
		  DB_set_sickness_by_gameid($gameid,$who+$add);

		/* email next player */
		$who = DB_get_sickness_by_gameid($gameid);
		if($who>9) $who = $who/10;

		if($who<=4)
		  {
		    $To       = DB_get_email('position-gameid',$who,$gameid);
		    $userhash = DB_get_hash_from_game_and_pos($gameid,$who);
		    $userid   = DB_get_userid('email',$To);
		    DB_set_player_by_gameid($gameid,$userid);

		    $message = "Someone has poverty, it's your turn to decide, if you want to take the trump. Please visit:".
		      " ".$HOST.$INDEX."?action=game&me=".$userhash."\n\n" ;
		    mymail($To,$EmailName." poverty (game ".DB_format_gameid($gameid).")",$message);
		  }

		/* this user is done */
		DB_set_hand_status_by_hash($me,'play');
		break;
	      }
	    else if(myisset("trump") && !myisset("exchange") && $_REQUEST["trump"]>0 && ($who==$mypos || $who==$mypos*10))
	      {
		/* user wants to take trump */
		$trump = $_REQUEST["trump"];

		/* get hand id for user $trump */
		$userhand = DB_get_handid('gameid-userid',$gameid,$trump);
		/* copy trump from player A to B */
		$result = mysql_query("UPDATE Hand_Card SET hand_id='$myhand' WHERE hand_id='$userhand' AND card_id<'27'" );

		/* add hidden button with trump in it to get to the next point */
		echo "</div><div class=\"poverty\">\n";
		echo "  <input type=\"hidden\" name=\"exchange\" value=\"-1\" />\n";
		echo "  <input type=\"hidden\" name=\"trump\" value=\"".$trump."\" />\n";
		echo "  <input type=\"submit\" class=\"submitbutton\" value=\"select cards to give back\" />\n";
		echo "</div><div>\n";
	      }
	    else if(myisset("trump","exchange") && $_REQUEST["trump"]>0 && ($who==$mypos || $who==$mypos*10))
	      {
		$trump    = $_REQUEST["trump"];
		$exchange = $_REQUEST["exchange"];
		$userhand = DB_get_handid('gameid-userid',$gameid,$trump);

		/* if exchange is set to a value>0, exchange that card back to user $trump */
		if($exchange >0)
		  {
		    $result = mysql_query("UPDATE Hand_Card SET hand_id='$userhand'".
					  " WHERE hand_id='$myhand' AND card_id='$exchange'" );
		  };

		/* if number of cards == 12, set status to play for both users */
		$result = mysql_query("SELECT COUNT(*) FROM Hand_Card  WHERE hand_id='$myhand'" );
		$r      = mysql_fetch_array($result,MYSQL_NUM);
		if(!$r)
		  {
		    myerror("error in poverty");
		    die();
		  };
		if($r[0]==12)
		  {
		    if($gametype=="poverty" || $who<9)
		      {
			DB_set_sickness_by_gameid($gameid,-1); /* done with poverty */
		      }
		    else /* reduce poverty count by one, that is go to single digits $who */
		      {
			$add = 1;
			$who = $who/10;

			/* whom to ask next */
			$firstsick  = DB_get_sickness_by_pos_and_gameid($mypos+1,$gameid);
			$secondsick = DB_get_sickness_by_pos_and_gameid($mypos+2,$gameid);

			if($firstsick!="poverty")
			  DB_set_sickness_by_gameid($gameid,$who+$add);
			else
			  {
			    if($secondsick!="poverty")
			      DB_set_sickness_by_gameid($gameid,$who+$add*2);
			    else
			      DB_set_sickness_by_gameid($gameid,$who+$add*3);
			  };

			/* email next player */
			$who = DB_get_sickness_by_gameid($gameid);
			if($who<=4)
			  {
			    $To       = DB_get_email('position-gameid',$who,$gameid);
			    $userhash = DB_get_hash_from_game_and_pos($gameid,$who);
			    $userid   = DB_get_userid('email',$To);
			    DB_set_player_by_gameid($gameid,$userid);

			    $message = "Someone has poverty, it's your turn to decide, ".
			      "if you want to take the trump. Please visit:".
			      " ".$HOST.$INDEX."?action=game&me=".$userhash."\n\n" ;
			    mymail($To,$EmailName." poverty (game ".DB_format_gameid($gameid).")",$message);
			  }
		      }

		    /* this user is done */
		    DB_set_hand_status_by_hash($me,'play');
		    /* and so is his partner */
		    $hash = DB_get_hash_from_gameid_and_userid($gameid,$trump);
		    DB_set_hand_status_by_hash($hash,'play');

		    /* set party to re, unless we had dpoverty, in that case check if we need to set re/contra*/
		    $re_set = 0;
		    foreach($userids as $user)
		      {
			$userhash = DB_get_hash_from_gameid_and_userid($gameid,$user);
			$party    = DB_get_party_by_hash($userhash);
			if($party=="re")
			  $re_set = 1;
		      }
		    if($re_set)
		      {
			DB_set_party_by_hash($me,"contra");
			DB_set_party_by_hash($hash,"contra");
		      }
		    else
		      {
			foreach($userids as $user)
			  {
			    $userhash = DB_get_hash_from_gameid_and_userid($gameid,$user);
			    if($userhash==$hash||$userhash==$me)
			      DB_set_party_by_hash($userhash,"re");
			    else
			      DB_set_party_by_hash($userhash,"contra");
			  }
		      }


		    break;
		  }
		else
		  {
		    /* else show all trump, have lowest card pre-selected, have hidden setting for */
		    echo "</div><div class=\"poverty\"> you need to get rid of a few cards</div>\n";

		    set_gametype($gametype); /* this sets the $CARDS variable */
		    $mycards = DB_get_hand($me);
		    $mycards = mysort($mycards,$gametype);

		    $type="exchange";
		    echo "<div class=\"mycards\">Your cards are: <br />\n";
		    foreach($mycards as $card)
		      display_link_card($card,$PREF["cardset"],$type);
		    echo "  <input type=\"hidden\" name=\"trump\" value=\"".$trump."\" />\n";
		    echo "  <input type=\"submit\" class=\"submitbutton\" value=\"select one card to give back\" />\n";
		    echo "</div><div>\n";
		  }
	      }
	    else if($who == $mypos || $who == $mypos*10)
	      {
		echo "</div><div class=\"poverty\">\n";
		foreach($userids as $user)
		  {
		    $name     = DB_get_name('userid',$user);
		    $usersick = DB_get_sickness_by_userid_and_gameid($user,$gameid);

		    if($usersick=="poverty")
		      {
			$hash    = DB_get_hash_from_gameid_and_userid($gameid,$user);
			$cards   = DB_get_hand($hash);
			$nrtrump = count_trump($cards);
			/* count trump */
			if($nrtrump<4)
			  echo "Player $name has $nrtrump trump. Do you want to take them?".
			    "<a href=\"index.php?action=game&me=$me&amp;trump=$user\">yes</a> <br />\n";
		      }
		  }
		echo "<a href=\"index.php?action=game&me=$me&amp;trump=no\">No,way I take those trump...</a> <br />\n";
		echo "</div><div>\n";

		echo "Your cards are: <br />\n";
		$mycards = DB_get_hand($me);
		sort($mycards);
		echo "<p class=\"mycards\">Your cards are: <br />\n";
		foreach($mycards as $card)
		  display_card($card,$PREF["cardset"]);
		echo "</p>\n";
	      }
	    else
	      {
		$mysick = DB_get_sickness_by_userid_and_gameid($myid,$gameid);
		if($mysick=="poverty")
		  echo "The others are asked if they want to take your trump, you have to wait (you'll get an email).";
		else
		  echo "it's not your turn yet to decide if you want to take the trump or not.";
	      }
	  };
	/* check if no one wanted to take trump, in that case the gamesickness would be set to 5 or 50 */
	$who = DB_get_sickness_by_gameid($gameid);
	if($who==5 || $who==50)
	  {
	    $message = "Hello, \n\n".
	      "Game ".DB_format_gameid($gameid)." has been canceled since nobody wanted to take the trump.\n";

	    $userids = DB_get_all_userid_by_gameid($gameid);
	    foreach($userids as $user)
	      {
		$To = DB_get_email('userid',$user);
		mymail($To,$EmailName."game ".DB_format_gameid($gameid)." canceled (poverty not resolved)",$message);
	      }

	    /* delete everything from the dB */
	    DB_cancel_game($me);

	    echo "<p style=\"background-color:red\";>Game ".DB_format_gameid($gameid)." has been canceled.<br /><br /></p>";
	    output_footer();
	    DB_close();
	    exit();
	  }

	/* check if all players are ready to play */
	$ok = 1;
	foreach($userids as $user)
	  if(DB_get_hand_status_by_userid_and_gameid($user,$gameid)!='play')
	    {
	      $ok = 0;
	      DB_set_player_by_gameid($gameid,$user);
	    }

	if($ok)
	  {
	    /* only set this after all poverty, etc. are handled*/
	    DB_set_game_status_by_gameid($gameid,'play');

	    /* email startplayer */
	    $startplayer = DB_get_startplayer_by_gameid($gameid);
	    $email       = DB_get_email('position-gameid',$startplayer,$gameid);
	    $hash        = DB_get_hash_from_game_and_pos($gameid,$startplayer);
	    $who         = DB_get_userid('email',$email);
	    DB_set_player_by_gameid($gameid,$who);

	    if($hash!=$me && DB_get_email_pref_by_hash($hash)!="emailaddict")
	      {
		/* email startplayer) */
		$message = "It's your turn now in game ".DB_format_gameid($gameid).".\n".
		  "Use this link to play a card: ".$HOST.$INDEX."?action=game&me=".$hash."\n\n" ;
		mymail($email,$EmailName."ready, set, go... (game ".DB_format_gameid($gameid).") ",$message);
	      }
	    else
	      echo " Please, <a href=\"$INDEX?action=game&me=$me\">start</a> the game.<br />";
	  }
	else
	  echo "\n <br />";
      }
    echo "</div>\n";
    break;
  case 'play':
  case 'gameover':
    /* both entries here,  so that the tricks are visible for both.
     * in case of 'play' there is a break later that skips the last part
     */

    /* figure out what kind of game we are playing,
     * set the global variables $CARDS["trump"],$CARDS["diamonds"],$CARDS["hearts"],
     * $CARDS["clubs"],$CARDS["spades"],$CARDS["foxes"]
     * accordingly
     */

    $gametype = DB_get_gametype_by_gameid($gameid);
    $GT       = $gametype;
    if($gametype=="solo")
      {
	$gametype = DB_get_solo_by_gameid($gameid);
	$GT       = $gametype." ".$GT;
      }
    else
      $gametype = "normal";

    set_gametype($gametype); /* this sets the $CARDS variable */

    /* get some infos about the game */
    $gamestatus = DB_get_game_status_by_gameid($gameid);

    /* has the game started? No, then just wait here...*/
    if($gamestatus == 'pre')
      {
	echo "<p class=\"message\"> You finished the setup, but not everyone else finished it... ".
	  "You need to wait for the others. Just wait for an email. </p>";
	break; /* not sure this works... the idea is that you can
		* only  play a card after everyone is ready to play */
      }

    /* get time from the last action of the game */
    $result  = mysql_query("SELECT mod_date from Game WHERE id='$gameid' " );
    $r       = mysql_fetch_array($result,MYSQL_NUM);
    $gameend = time() - strtotime($r[0]);

    /* handel comments in case player didn't play a card, allow comments a week after the end of the game */
    if( (!myisset("card") && $mystatus=='play') || ($mystatus=='gameover' && ($gameend < 60*60*24*7)) )
      if(myisset("comment"))
	{
	  $comment = $_REQUEST["comment"];
	  $playid = DB_get_current_playid($gameid);

	  if($comment != "")
	    DB_insert_comment($comment,$playid,$myid);
	};

    /* handle notes in case player didn't play a card, allow notes only during a game */
    if( (!myisset("card") && $mystatus=='play')  )
      if(myisset("note"))
	{
	  $note = $_REQUEST["note"];

	  if($note != "")
	    DB_insert_note($note,$gameid,$myid);
	};

    /* get everything relevant to display the tricks */
    $result = mysql_query("SELECT Hand_Card.card_id as card,".
			  "       Hand.position as position,".
			  "       Play.sequence as sequence, ".
			  "       Trick.id, ".
			  "       GROUP_CONCAT(CONCAT('<span>',User.fullname,': ',Comment.comment,'</span>')".
			  "                    SEPARATOR '\n' ), ".
			  "       Play.create_date, ".
			  "       Hand.user_id ".
			  "FROM Trick ".
			  "LEFT JOIN Play ON Trick.id=Play.trick_id ".
			  "LEFT JOIN Hand_Card ON Play.hand_card_id=Hand_Card.id ".
			  "LEFT JOIN Hand ON Hand_Card.hand_id=Hand.id ".
			  "LEFT JOIN Comment ON Play.id=Comment.play_id ".
			  "LEFT JOIN User On User.id=Comment.user_id ".
			  "WHERE Trick.game_id='".$gameid."' ".
			  "GROUP BY Trick.id, sequence ".
			  "ORDER BY Trick.id, sequence  ASC");
    $trickNR   = 1;
    $lasttrick = DB_get_max_trickid($gameid);

    $play = array(); /* needed to calculate winner later  */
    $seq  = 1;
    $pos  = DB_get_startplayer_by_gameid($gameid)-1;
    $firstcard = ""; /* first card in a trick */

    echo "\n<ul class=\"tricks\">\n";
    echo "  <li class=\"nohighlight\"> Game ".DB_format_gameid($gameid).": </li>\n";

    /* output vorbehalte */
    $mygametype =  DB_get_gametype_by_gameid($gameid);
    if($mygametype != "normal") /* only show when needed */
      {
	echo "  <li onclick=\"hl('0');\" class=\"current\"><a href=\"#\">Pre</a>\n".
	  "    <div class=\"trick\" id=\"trick0\">\n";
	$show = 1;
	for($mypos=1;$mypos<5;$mypos++)
	  {
	    $usersick = DB_get_sickness_by_pos_and_gameid($mypos,$gameid);
	    if($usersick!=NULL)
	      {
		echo "      <div class=\"vorbehalt".($mypos-1)."\"> Vorbehalt <br />";
		if($show)
		  echo " $usersick <br />";
		echo  " </div>\n";

		if($mygametype == $usersick)
		  $show = 0;
	      }
	  }
	echo "    </div>\n  </li>\n";  /* end div trick, end li trick */
      }

    /* output tricks */
    while($r = mysql_fetch_array($result,MYSQL_NUM))
      {
	$pos     = $r[1];
	$seq     = $r[2];
	$trick   = $r[3];
	$comment = $r[4];
	$user    = $r[6];

	/* check if first schweinchen has been played */
	if( $GAME["schweinchen"] && ($r[0] == 19 || $r[0] == 20) )
	  $GAME["schweinchen"]++;

	/* save card to be able to find the winner of the trick later */
	$play[$seq] = array("card"=>$r[0],"pos"=>$pos);

	if($seq==1)
	  {
	    /* first card in a trick, output some html */
	    if($trick!=$lasttrick)
	      {
		/* start of an old trick? */
		echo "  <li onclick=\"hl('$trickNR');\" class=\"old\"><a href=\"#\">Trick $trickNR</a>\n".
		  "    <div class=\"trick\" id=\"trick".$trickNR."\">\n".
		  "      <img class=\"arrow\" src=\"pics/arrow".($pos-1).".png\" alt=\"table\" />\n";
	      }
	    else if($trick==$lasttrick)
	      {
		/* start of a last trick? */
		echo "  <li onclick=\"hl('$trickNR');\" class=\"current\"><a href=\"#\">Trick $trickNR</a>\n".
		  "    <div class=\"trick\" id=\"trick".$trickNR."\">\n".
		  "      <img class=\"arrow\" src=\"pics/arrow".($pos-1).".png\" alt=\"table\" />\n";
	      };

	    /* remember first card, so that we are able to check, what cards can be played */
	    $firstcard = $r[0];
	  };

	/* display card */
	echo "      <div class=\"card".($pos-1)."\">\n";

	/* display comments */
	if($comment!="")
	  echo "        <span class=\"comment\">".$comment."</span>\n";

	echo "        ";
	display_card($r[0],$PREF["cardset"]);

	echo "      </div>\n"; /* end div card */

	/* end of trick? */
	if($seq==4)
	  {
	    $trickNR++;
	    echo "    </div>\n  </li>\n";  /* end div trick, end li trick */
	  }
      }

    /* whos turn is it? */
    if($seq==4)
      {
	$winner    = get_winner($play,$gametype); /* returns the position */
	$next      = $winner;
	$firstcard = ""; /* new trick, no first card */
      }
    else
      {
	$next = $pos+1;
	if($next==5) $next = 1;
      }

    /* my turn?, display cards as links, ask for comments*/
    if(DB_get_pos_by_hash($me) == $next)
      $myturn = 1;
    else
      $myturn = 0;

    /* do we want to play a card? */
    if(myisset("card") && $myturn)
      {
	$card   = $_REQUEST["card"];
	$handid = DB_get_handid('hash',$me);

	/* check if we have card and that we haven't played it yet*/
	/* set played in hand_card to true where hand_id and card_id*/
	$result = mysql_query("SELECT id FROM Hand_Card WHERE played='false' and ".
			      "hand_id='$handid' AND card_id=".DB_quote_smart($card));
	$r = mysql_fetch_array($result,MYSQL_NUM);
	$handcardid = $r[0];

	if($handcardid) /* everything ok, play card  */
	  {
	    /* update Game timestamp */
	    DB_update_game_timestamp($gameid);

	    /* check if a call was made, must do this before we set the card status to played */
	    if(myisset("call")  && $_REQUEST["call"]  == "120" && can_call(120,$me))
	      $result = mysql_query("UPDATE Hand SET point_call='120' WHERE hash='$me' ");
	    if(myisset("call")  && $_REQUEST["call"]  == "90" && can_call(90,$me))
	      $result = mysql_query("UPDATE Hand SET point_call='90'  WHERE hash='$me' ");
	    if(myisset("call")  && $_REQUEST["call"]  == "60" && can_call(60,$me))
	      $result = mysql_query("UPDATE Hand SET point_call='60'  WHERE hash='$me' ");
	    if(myisset("call")  && $_REQUEST["call"]  == "30" && can_call(30,$me))
	      $result = mysql_query("UPDATE Hand SET point_call='30'  WHERE hash='$me' ");
	    if(myisset("call")  && $_REQUEST["call"]  == "0" && can_call(0,$me))
	      $result = mysql_query("UPDATE Hand SET point_call='0'   WHERE hash='$me' ");

	    /* mark card as played */
	    mysql_query("UPDATE Hand_Card SET played='true' WHERE hand_id='$handid' AND card_id=".
			DB_quote_smart($card));

	    /* get trick id or start new trick */
	    $a = DB_get_current_trickid($gameid);
	    $trickid  = $a[0];
	    $sequence = $a[1];
	    $tricknr  = $a[2];

	    $playid = DB_play_card($trickid,$handcardid,$sequence);

	    /* check special output for schweinchen in case: 
	     * schweinchen is in the rules, a fox has been played and the gametype is correct
	     */
	    if( $GAME["schweinchen"] && 
		($card == 19 || $card == 20) && 
		($gametype == "normal" || $gametype == "silent"|| $gametype=="trump"))
	      {
		$GAME["schweinchen"]++; // count how many have been played including this one
		if($GAME["schweinchen"]==3 && $RULES["schweinchen"]=="second" )
		  DB_insert_comment("Schweinchen! ",$playid,$myid);
		if($RULES["schweinchen"]=="both" )
		  DB_insert_comment("Schweinchen! ",$playid,$myid);
		if ($debug)
		  echo "schweinchen = ".$GAME["schweinchen"]." ---<br />";
	      }

	    /* if sequence == 4 check who one in case of wedding */
	    if($sequence == 4 && $GT == "wedding")
	      {
		/* is wedding resolve */
		$resolved = DB_get_sickness_by_gameid($gameid);
		if($resolved<0)
		  {
		    /* who has wedding */
		    $userids = DB_get_all_userid_by_gameid($gameid);
		    foreach($userids as $user)
		      {
			$usersick = DB_get_sickness_by_userid_and_gameid($user,$gameid);
			if($usersick == "wedding")
			  $whosick = $user;
		      }
		    /* who won the trick */
		    $play     = DB_get_cards_by_trick($trickid);
		    $winner   = get_winner($play,$gametype); /* returns the position */
		    $winnerid = DB_get_userid('gameid-position',$gameid,$winner);
		    /* is tricknr <=3 */
		    if($tricknr <=3 && $winnerid!=$whosick)
		      {
			/* set resolved at tricknr*/
			$resolved = DB_set_sickness_by_gameid($gameid,$tricknr);
			/* set partner */
			$whash = DB_get_hash_from_gameid_and_userid($gameid,$winnerid);
			DB_set_party_by_hash($whash,"re");
		      }
		    if($tricknr == 3 && $winnerid==$whosick)
		      {
			/* set resolved at tricknr*/
			$resolved = DB_set_sickness_by_gameid($gameid,'3');
		      }
		  }
	      }

	    /* if sequence == 4, set winner of the trick, count points and set the next player */
	    if($sequence==4)
	      {
		$play   = DB_get_cards_by_trick($trickid);
		$winner = get_winner($play,$gametype); /* returns the position */

		/* check if someone caught a fox */
		/* first check if we should account for solos at all, 
		 * since it doesn't make sense in some games
		 */
		$ok = 0; /* fox shouldn't be counted */
		if(DB_get_gametype_by_gameid($gameid)=="solo")
		  {
		    $solo = DB_get_solo_by_gameid($gameid);
		    if($solo == "trump" || $solo == "silent")
		      $ok = 1; /* for trump solos and silent solos, foxes are ok */
		  }
		else
		  $ok = 1; /* for all other games (not solos) foxes are ok too */
		  
		if($ok==1)
		  foreach($play as $played)
		    {
		      if ( $played['card']==19 || $played['card']==20 )
			if ($played['pos']!= $winner )
			  {
			    /* possible caught a fox, check party */
			    $uid1 = DB_get_userid('gameid-position',$gameid,$winner);
			    $uid2 = DB_get_userid('gameid-position',$gameid,$played['pos']);

			    $party1 = DB_get_party_by_gameid_and_userid($gameid,$uid1);
			    $party2 = DB_get_party_by_gameid_and_userid($gameid,$uid2);

			    if($party1 != $party2)
			      mysql_query("INSERT INTO Score".
					  " VALUES( NULL,NULL,$gameid,'$party1',$uid1,$uid2,'fox')");
			  }
		    }
		  
		/* check for karlchen (jack of clubs in the last trick)*/
		/* same as for foxes, karlchen doesn't always make sense
		 * check what kind of game it is and set karlchen accordingly */
		$ok = 1; /* default: karlchen should be accounted for */
		if($tricknr != 12 )
		  $ok = 0; /* Karlchen works only in the last trick */
		if($ok && DB_get_gametype_by_gameid($gameid)=="solo" )
		  {
		    $solo = DB_get_solo_by_gameid($gameid);
		    if($solo == "trumpless" || $solo == "jack" || $solo == "queen" )
		      $ok = 0; /* no Karlchen in these solos */
		  }
		  
		if($ok)
		  foreach($play as $played)
		    if ( $played['card']==11 || $played['card']==12 )
		      if ($played['pos'] == $winner )
			{
			  /* possible caught a fox, check party */
			  $uid1   = DB_get_userid('gameid-position',$gameid,$winner);
			  $party1 = DB_get_party_by_gameid_and_userid($gameid,$uid1);

			  mysql_query("INSERT INTO Score".
				      " VALUES( NULL,NULL,$gameid,'$party1',$uid1,NULL,'karlchen')");
			}
		/* check for doppelopf (>40 points)*/
		$points = 0;
		foreach($play as $played)
		  {
		    $points += DB_get_card_value_by_cardid($played['card']);
		  }
		if($points > 39)
		  {
		    $uid1   = DB_get_userid('gameid-position',$gameid,$winner);
		    $party1 = DB_get_party_by_gameid_and_userid($gameid,$uid1);

		    mysql_query("INSERT INTO Score".
				" VALUES( NULL,NULL,$gameid,'$party1',$uid1,NULL,'doko')");
		  }

		if($winner>0)
		  mysql_query("UPDATE Trick SET winner='$winner' WHERE id='$trickid'");
		else
		  echo "ERROR during scoring";

		if($debug)
		  echo "DEBUG: position $winner won the trick <br />";

		/* who is the next player? */
		$next = $winner;
	      }
	    else
	      {
		$next = DB_get_pos_by_hash($me)+1;
	      }
	    if($next==5) $next=1;

	    /* check for coment */
	    if(myisset("comment"))
	      {
		$comment = $_REQUEST["comment"];
		if($comment != "")
		  DB_insert_comment($comment,$playid,$myid);
	      };

	    /* check for note */
	    if(myisset("note"))
	      {
		$note = $_REQUEST["note"];
		if($note != "")
		  DB_insert_note($note,$gameid,$myid);
	      };

	    /* display played card */
	    $pos = DB_get_pos_by_hash($me);
	    if($sequence==1)
	      {
		echo "  <li onclick=\"hl('".($tricknr)."');\" class=\"current\"><a href=\"#\">Trick ".($tricknr)."</a>\n".
		  "    <div class=\"trick\" id=\"trick".($tricknr)."\">\n".
		  "      <img class=\"arrow\" src=\"pics/arrow".($pos-1).".png\" alt=\"table\" />\n";
	      }

	    echo "      <div class=\"card".($pos-1)."\">\n        ";

	    /* display comments */
	    display_card($card,$PREF["cardset"]);
	    if($comment!="")
	      echo "\n        <span class=\"comment\"> ".$comment."</span>\n";
	    echo "      </div>\n";

	    /*check if we still have cards left, else set status to gameover */
	    if(sizeof(DB_get_hand($me))==0)
	      {
		DB_set_hand_status_by_hash($me,'gameover');
		$mystatus = 'gameover';
	      }

	    /* if all players are done, set game status to game over,
	     * get the points of the last trick and send out an email
	     * to all players
	     */
	    $userids = DB_get_all_userid_by_gameid($gameid);

	    $done=1;
	    foreach($userids as $user)
	      if(DB_get_hand_status_by_userid_and_gameid($user,$gameid)!='gameover')
		$done=0;

	    if($done)
	      DB_set_game_status_by_gameid($gameid,"gameover");

	    /* email next player, if game is still running */
	    if(DB_get_game_status_by_gameid($gameid)=='play')
	      {
		$next_hash = DB_get_hash_from_game_and_pos($gameid,$next);
		$email     = DB_get_email('hash',$next_hash);
		$who       = DB_get_userid('email',$email);
		DB_set_player_by_gameid($gameid,$who);

		$message = "A card has been played in game ".DB_format_gameid($gameid).".\n\n".
		  "It's your turn  now.\n".
		  "Use this link to play a card: ".$HOST.$INDEX."?action=game&me=".$next_hash."\n\n" ;
		if( DB_get_email_pref_by_uid($who)!="emailaddict" )
		  mymail($email,$EmailName."a card has been played in game ".DB_format_gameid($gameid),$message);
	      }
	    else /* send out final email */
	      {
		/* individual score */
		$result = mysql_query("SELECT User.fullname, IFNULL(SUM(Card.points),0), Hand.party FROM Hand".
				      " LEFT JOIN Trick ON Trick.winner=Hand.position AND Trick.game_id=Hand.game_id".
				      " LEFT JOIN User ON User.id=Hand.user_id".
				      " LEFT JOIN Play ON Trick.id=Play.trick_id".
				      " LEFT JOIN Hand_Card ON Hand_Card.id=Play.hand_card_id".
				      " LEFT JOIN Card ON Card.id=Hand_Card.card_id".
				      " WHERE Hand.game_id='$gameid'".
				      " GROUP BY User.fullname" );
		$message  = "The game is over. Thanks for playing :)\n";
		$message .= "Final score:\n";
		while( $r = mysql_fetch_array($result,MYSQL_NUM))
		  $message .= "   ".$r[0]."(".$r[2].") ".$r[1]."\n";

		$result = mysql_query("SELECT  Hand.party, IFNULL(SUM(Card.points),0) FROM Hand".
				      " LEFT JOIN Trick ON Trick.winner=Hand.position AND Trick.game_id=Hand.game_id".
				      " LEFT JOIN User ON User.id=Hand.user_id".
				      " LEFT JOIN Play ON Trick.id=Play.trick_id".
				      " LEFT JOIN Hand_Card ON Hand_Card.id=Play.hand_card_id".
				      " LEFT JOIN Card ON Card.id=Hand_Card.card_id".
				      " WHERE Hand.game_id='$gameid'".
				      " GROUP BY Hand.party" );
		$message .= "\nTotals:\n";
		$re     = 0;
		$contra = 0;
		while( $r = mysql_fetch_array($result,MYSQL_NUM))
		  {
		    $message .= "    ".$r[0]." ".$r[1]."\n";
		    if($r[0] == "re")
		      $re = $r[1];
		    else if($r[0] == "contra")
		      $contra = $r[1];
		  }

		/*
		 * save score in database
		 *
		 */

		/* get calls from re/contra */
		$call_re     = NULL;
		$call_contra = NULL;
		foreach($userids as $user)
		  {
		    $hash  = DB_get_hash_from_gameid_and_userid($gameid,$user);
		    $call  = DB_get_call_by_hash($hash);
		    $party = DB_get_party_by_hash($hash);

		    if($call!=NULL)
		      {
			$call = (int) $call;

			if($party=="re")
			  {
			    if($call_re==NULL)
			      $call_re = $call;
			    else if( $call < $call_re)
			      $call_re = $call;
			  }
			else if($party=="contra")
			  {
			    if($call_contra==NULL)
			      $call_contra = $call;
			    else if( $call < $call_re)
			      $call_contra = $call;
			  }
		      }
		  }

		/* figure out who one */
		$winning_party = NULL;

		if($call_re == NULL && $call_contra==NULL)
		  if($re>120)
		    $winning_party="re";
		  else
		    $winning_party="contra";
		else
		  {
		    if($call_re)
		      {
			$offset = 120 - $call_re;
			if($call_re == 0)
			  $offset--; /* since we use a > in the next equation */

			if($re > 120+$offset)
			  $winning_party="re";
			else if ( $call_contra == NULL )
			  $winning_party="contra";
		      }

		    if($call_contra)
		      {
			$offset = 120 - $call_contra;
			if($call_contra == 0)
			  $offset--; /* since we use a > in the next equation */

			if($contra > 120+$offset)
			  $winning_party="contra";
			else if ( $call_contra == NULL )
			  $winning_party="re";
		      }
		  }

		/* one point for each call of the other party in case the other party didn't win
		 * and one point each in case the party made more than points than one of the calls
		 */
		if($winning_party!="contra" && $call_contra!=NULL)
		  {
		    for( $p=$call_contra;$p<=120; $p+=30 )
		      {
			  mysql_query("INSERT INTO Score".
				      " VALUES( NULL,NULL,$gameid,'re',NULL,NULL,'against$p')");
			}

		      for( $p=$call_contra; $p<120; $p+=30)
			{
			  if( $re >= $p )
			    mysql_query("INSERT INTO Score".
					" VALUES( NULL,NULL,$gameid,'re',NULL,NULL,'made$p')");
			}
		    }
		  if($winning_party!="re" and $call_re!=NULL)
		    {
		      for( $p=$call_re;$p<=120; $p+=30 )
			{
			  mysql_query("INSERT INTO Score".
				      " VALUES( NULL,NULL,$gameid,'contra',NULL,NULL,'against$p')");
			}

		      for( $p=$call_re; $p<120; $p+=30)
			{
			  if( $contra>=$p )
			    mysql_query("INSERT INTO Score".
					" VALUES( NULL,NULL,$gameid,'contra',NULL,NULL,'made$p')");
			}
		    }

		  /* point in case contra won */
		  if($winning_party=="contra")
		    {
		      mysql_query("INSERT INTO Score".
				  " VALUES( NULL,NULL,$gameid,'contra',NULL,NULL,'againstqueens')");
		    }

		  /* one point each for winning and each 30 points + calls */
		  if($winning_party=="re")
		    {
		      foreach(array(120,150,180,210,240) as $p)
			{
			  $offset = 0;
			  if($p==240 || $call_contra!=NULL)
			    $offset = 1;

			  if($re>$p-$offset)
			    mysql_query("INSERT INTO Score".
					" VALUES( NULL,NULL,$gameid,'re',NULL,NULL,'".(240-$p)."')");
			}
		      /* re called something and won */
		      foreach(array(0,30,60,90,120) as $p)
			{
			  if($call_re!=NULL && $call_re<$p+1)
			    mysql_query("INSERT INTO Score".
					" VALUES( NULL,NULL,$gameid,'re',NULL,NULL,'call$p')");
			}
		    }
		  else if( $winning_party=="contra")
		    {
		      foreach(array(120,150,180,210,240) as $p)
			{
			  $offset = 0;
			  if($p==240 || $call_re!=NULL)
			    $offset = 1;

			  if($contra>$p-$offset)
			    mysql_query("INSERT INTO Score".
					" VALUES( NULL,NULL,$gameid,'contra',NULL,NULL,'".(240-$p)."')");
			}
		      /* re called something and won */
		      foreach(array(0,30,60,90,120) as $p)
			{
			  if($call_contra!=NULL && $call_contra<$p+1)
			    mysql_query("INSERT INTO Score".
					" VALUES( NULL,NULL,$gameid,'contra',NULL,NULL,'call$p')");
			}
		    }


		  /* add score points to email */
		  $message .= "\n";
		  $Tpoint = 0;
		  $message .= " Points Re: \n";
		  $queryresult = mysql_query("SELECT score FROM Score ".
					     "  WHERE game_id=$gameid AND party='re'".
					     " ");
		  while($r = mysql_fetch_array($queryresult,MYSQL_NUM) )
		    {
		      $message .= "   ".$r[0]."\n";
		      $Tpoint ++;
		    }
		  $message .= " Points Contra: \n";
		  $queryresult = mysql_query("SELECT score FROM Score ".
					     "  WHERE game_id=$gameid AND party='contra'".
					     " ");
		  while($r = mysql_fetch_array($queryresult,MYSQL_NUM) )
		    {
		      $message .= "   ".$r[0]."\n";
		      $Tpoint --;
		    }
		  $message .= " Total Points (from the Re point of view): $Tpoint\n";
		  $message .= "\n";

		  $session = DB_get_session_by_gameid($gameid);
		  $score = generate_score_table($session);
		  /* convert html to ascii */
		  $score = str_replace("<div class=\"scoretable\">\n<table class=\"score\">\n <tr>\n","",$score);
		  $score = str_replace("</table></div>\n","",$score);
		  $score = str_replace("\n","",$score);
		  $score = str_replace(array("<tr>","</tr>","<td>","</td>"),array("","\n","","|"),$score);
		  $score = explode("\n",$score);

		  $header = array_slice($score,0,1);
		  $header = explode("|",$header[0]);
		  for($i=0;$i<sizeof($header);$i++)
		    $header[$i]=str_pad($header[$i],6," ",STR_PAD_BOTH);
		  $header = implode("|",$header);
		  $header.= "\n------+------+------+------+------+\n";
		  if(sizeof($score)>5) $header.=   "                ...   \n";

		  if(sizeof($score)>5) $score = array_slice($score,-5,5);
		  for($i=0;$i<sizeof($score);$i++)
		    {
		      $line = explode("|",$score[$i]);
		      for($j=0;$j<sizeof($line);$j++)
			$line[$j]=str_pad($line[$j],6," ",STR_PAD_LEFT);
		      $score[$i] = implode("|",$line);
		    }

		  $score = implode("\n",$score);
		  $score = $header.$score;
		  
		  $message .= "Score Table:\n";
		  $message .= $score;

		  /* send out final email */
		  $all = array();

		  foreach($userids as $user)
		    $all[] = DB_get_email('userid',$user);
		  $To = implode(",",$all);

		  $help = "\n\n (you can use reply all on this email to reach all the players.)\n";
		  mymail($To,$EmailName."game over (game ".DB_format_gameid($gameid).") part 1(2)",$message.$help);

		  foreach($userids as $user)
		    {
		      $To   = DB_get_email('userid',$user);
		      $hash = DB_get_hash_from_gameid_and_userid($gameid,$user);

		      $link = "Use this link to have a look at game ".DB_format_gameid($gameid).": ".
			$HOST.$INDEX."?action=game&me=".$hash."\n\n" ;
		      if( DB_get_email_pref_by_uid($user) != "emailaddict" )
			mymail($To,$EmailName."game over (game ".DB_format_gameid($gameid).") part 2(2)",$link);
		    }
		}
	    }
	  else
	    {
	      echo "can't find that card?! <br />\n";
	    }
	}
      else if(myisset("card") && !$myturn )
	{
	  echo "please wait until it's your turn! <br />\n";
	}

      if($seq!=4 && $trickNR>1)
	echo "    </div>\n  </li>\n";  /* end div trick, end li trick */

      /* display points in case game is over */
      if($mystatus=='gameover' && DB_get_game_status_by_gameid($gameid)=='gameover' )
	{
	  echo "  <li onclick=\"hl('13');\" class=\"current\"><a href=\"#\">Score</a>\n".
	    "    <div class=\"trick\" id=\"trick13\">\n";
	  /* add pic for re/contra
	   "      <img class=\"arrow\" src=\"pics/arrow".($pos-1).".png\" alt=\"table\" />\n";*/

	  $result = mysql_query("SELECT User.fullname, IFNULL(SUM(Card.points),0), Hand.party,Hand.position FROM Hand".
				" LEFT JOIN Trick ON Trick.winner=Hand.position AND Trick.game_id=Hand.game_id".
				" LEFT JOIN User ON User.id=Hand.user_id".
				" LEFT JOIN Play ON Trick.id=Play.trick_id".
				" LEFT JOIN Hand_Card ON Hand_Card.id=Play.hand_card_id".
				" LEFT JOIN Card ON Card.id=Hand_Card.card_id".
				" WHERE Hand.game_id='$gameid'".
				" GROUP BY User.fullname" );
	  while( $r = mysql_fetch_array($result,MYSQL_NUM))
	    echo "      <div class=\"card".($r[3]-1)."\">\n".
	         "        <div class=\"score\">".$r[2]."<br /> ".$r[1]."</div>\n".
	         "      </div>\n";

	  echo "    </div>\n  </li>\n";  /* end div trick, end li trick */
	}


      echo "</ul>\n"; /* end ul tricks*/

      echo "<div class=\"notes\"> Personal notes: <br />\n";
      $notes = DB_get_notes_by_userid_and_gameid($myid,$gameid);
      foreach($notes as $note)
	echo "$note <hr \>\n";
      echo "Insert note:<input name=\"note\" type=\"text\" size=\"15\" maxlength=\"100\" />\n";
      echo "</div> \n";
      
      $mycards = DB_get_hand($me);
      $mycards = mysort($mycards,$gametype);
      echo "<div class=\"mycards\">\n";

      if($myturn && !myisset("card") && $mystatus=='play' )
	{
	  echo "Hello ".$myname.", it's your turn!  <br />\n";
	  echo "Your cards are: <br />\n";

	  /* do we have to follow suite? */
	  $followsuit = 0;
	  if(have_suit($mycards,$firstcard))
	    $followsuit = 1;

	  foreach($mycards as $card)
	    {
	      if($followsuit && !same_type($card,$firstcard))
		display_card($card,$PREF["cardset"]);
	      else
		display_link_card($card,$PREF["cardset"]);
	    }
	}
      else if($mystatus=='play' )
	{
	  echo "Your cards are: <br />\n";
	  foreach($mycards as $card)
	    display_card($card,$PREF["cardset"]);
	}
      else if($mystatus=='gameover')
	{
	  $oldcards = DB_get_all_hand($me);
	  $oldcards = mysort($oldcards,$gametype);
	  echo "Your cards were: <br />\n";
	  foreach($oldcards as $card)
	    display_card($card,$PREF["cardset"]);

	  $userids = DB_get_all_userid_by_gameid($gameid);
          foreach($userids as $user)
            {
              $userhash = DB_get_hash_from_gameid_and_userid($gameid,$user);

              if($userhash!=$me)
                {
                  echo "<br />";

                  $name = DB_get_name('userid',$user);
                  $oldcards = DB_get_all_hand($userhash);
                  $oldcards = mysort($oldcards,$gametype);
                  echo "$name's cards were: <br />\n";
                  foreach($oldcards as $card)
                    display_card($card,$PREF["cardset"]);
                }
            };
	}
      echo "</div>\n";

      /* if the game is over do some extra stuff, therefore exit the swtich statement if we are still playing*/
      if($mystatus=='play')
	break;

      /* the following happens only when the gamestatus is 'gameover' */
      /* check if game is over, display results */
      if(DB_get_game_status_by_gameid($gameid)=='play')
	{
	  echo "The game is over for you.. other people still need to play though";
	}
      else
	{
	  $result = mysql_query("SELECT Hand.party, IFNULL(SUM(Card.points),0) FROM Hand".
				" LEFT JOIN Trick ON Trick.winner=Hand.position AND Trick.game_id=Hand.game_id".
				" LEFT JOIN User ON User.id=Hand.user_id".
				" LEFT JOIN Play ON Trick.id=Play.trick_id".
				" LEFT JOIN Hand_Card ON Hand_Card.id=Play.hand_card_id".
				" LEFT JOIN Card ON Card.id=Hand_Card.card_id".
				" WHERE Hand.game_id='$gameid'".
				" GROUP BY Hand.party" );
	  echo "<div class=\"total\"> Totals:<br />\n";
	  while( $r = mysql_fetch_array($result,MYSQL_NUM))
	    echo "  ".$r[0]." ".$r[1]."<br />\n";

	  $queryresult = mysql_query("SELECT timediff(mod_date,create_date) ".
				     " FROM Game WHERE id='$gameid'");
	  $r = mysql_fetch_array($queryresult,MYSQL_NUM);
	  echo "<p>This game took ".$r[0]." hours.</p>";

	  echo "<div class=\"re\">\n Points Re: <br />\n";
	  $queryresult = mysql_query("SELECT score FROM Score ".
				     "  WHERE game_id=$gameid AND party='re'".
				     " ");
	  while($r = mysql_fetch_array($queryresult,MYSQL_NUM) )
	    echo "   ".$r[0]."<br />\n";
	  echo "</div>\n";

	  echo "<div class=\"contra\">\n Points Contra: <br />\n";
	  $queryresult = mysql_query("SELECT score FROM Score ".
				     "  WHERE game_id=$gameid AND party='contra'".
				     " ");
	  while($r = mysql_fetch_array($queryresult,MYSQL_NUM) )
	    echo "   ".$r[0]."<br />\n";
	  echo "</div>\n";

	  echo "</div>\n";


	}
      break;
    default:
      myerror("error in testing the status");
    }
    /* output left menu */
    display_user_menu();

    /* output right menu */

      /* display rule set for this game */
    echo "<div class=\"gameinfo\">\n";

    if($gamestatus != 'pre')
      echo " Gametype: $GT <br />\n";

    echo "Rules: <br />\n";
    echo "10ofhearts : ".$RULES["dullen"]      ."<br />\n";
    echo "schweinchen: ".$RULES["schweinchen"] ."<br />\n";
    echo "call:        ".$RULES["call"]        ."<br />\n";

    echo "<hr />\n";
    if($gamestatus == 'play' )
      output_form_calls($me);

    /* get time from the last action of the game */
    $result  = mysql_query("SELECT mod_date from Game WHERE id='$gameid' " );
    $r       = mysql_fetch_array($result,MYSQL_NUM);
    $gameend = time() - strtotime($r[0]);

    if($gamestatus == 'play' || $gameend < 60*60*24*7)
      {
	echo "<br />\nA short comment:<input name=\"comment\" type=\"text\" size=\"15\" maxlength=\"100\" />\n";
	echo "<hr />";
      }

    echo "<input type=\"submit\" value=\"submit\" />\n";


    if($mystatus=='gameover' && DB_get_game_status_by_gameid($gameid)=='gameover' )
      {
	echo "<hr />\n";

	$session = DB_get_session_by_gameid($gameid);
	$result  = mysql_query("SELECT id,create_date FROM Game".
			       " WHERE session=$session".
			       " ORDER BY create_date DESC".
			       " LIMIT 1");
	$r = -1;
	if($result)
	  $r = mysql_fetch_array($result,MYSQL_NUM);

	if(!$session || $gameid==$r[0])
	  {
	    /* suggest a new game with the same people in it, just rotated once (unless last game was solo) */
	    $names = DB_get_all_names_by_gameid($gameid);
	    $type  = DB_get_gametype_by_gameid($gameid);

	    if($type=="solo")
	      output_ask_for_new_game($names[0],$names[1],$names[2],$names[3],$gameid);
	    else
	      output_ask_for_new_game($names[1],$names[2],$names[3],$names[0],$gameid);
	  }
      }

    $session = DB_get_session_by_gameid($gameid);
    $score = generate_score_table($session);

    //  if(size_of($score)>30)
      echo $score;

    echo "</div>\n";

    echo "</form>\n";
    output_footer();
    DB_close();
    exit();
?>