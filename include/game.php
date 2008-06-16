<?php
/* make sure that we are not called from outside the scripts,
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

/* calling game.php only makes sense when we give it a hash for a game */
if(!myisset("me"))
  {
    echo "Hmm, you really shouldn't mess with the urls.<br />\n";
    return;
  }
$me = $_REQUEST["me"];

/* Ok, got a hash, but is it valid? */
$myid = DB_get_userid('hash',$me);
if(!$myid)
  {
    echo "Can't find you in the database, please check the url.<br />\n";
    echo "perhaps the game has been canceled, check by login in <a href=\"$INDEX\">here</a>.";
    return;
  }

global $GAME,$RULES,$CARDS;

/* the user has done something, update the timestamp */
DB_update_user_timestamp($myid);

/* get some information from the DB */
$gameid   = DB_get_gameid_by_hash($me);
$myname   = DB_get_name('hash',$me);
$mystatus = DB_get_status_by_hash($me);
$mypos    = DB_get_pos_by_hash($me);
$myhand   = DB_get_handid('hash',$me);
$session  = DB_get_session_by_gameid($gameid);

/* get prefs and save them in a variable*/
$PREF = DB_get_PREF($myid);

/* get rule set for this game */
$RULES = DB_get_RULES($gameid);

/* get some infos about the game */
$gametype   = DB_get_gametype_by_gameid($gameid);
$gamestatus = DB_get_game_status_by_gameid($gameid);
$GT         = $gametype;
if($gametype=="solo")
  {
    $gametype = DB_get_solo_by_gameid($gameid);
    $GT  = $gametype." ".$GT;
  }

/* do we need to worry about Schweinchen?
 * check gametype and rules
 * if yes, figure out if someone actually has Schweinchen
 * save information in $GAME
 */
$ok=0;
if( $gamestatus == 'pre' )
  {
    /* always need to use Schweinchen to figure out for example who has poverty */
    $ok=1;
  }
else
  {
    /* in a game Schweinchen is not valid in all types of games */
    if( $gametype == 'normal' || $gametype == 'silent' || $gametype=='trump' )
      if( in_array($RULES['schweinchen'],array('both','second','secondaftercall')) )
	$ok=1;
  }

/* these are the defaults */
$GAME['schweinchen-who']    = NULL;
$GAME['schweinchen-first']  = NULL;
$GAME['schweinchen-second'] = NULL;

if($ok)
{
  /* need to check for Schweinchen */
  for($i=1;$i<5;$i++)
    {
      $hash  = DB_get_hash_from_game_and_pos($gameid,$i);
      $cards = DB_get_all_hand($hash);
      if( in_array("19",$cards) && in_array("20",$cards) )
	$GAME['schweinchen-who']=$hash;
    };
  $GAME['schweinchen-first']  = 0; /* to keep track if they have been played already */
  $GAME['schweinchen-second'] = 0;
}
/* end check for Schweinchen */

/* set the $CARDS variable, needed for sorting the cards
 * we set it to normal so that the pre-game phase is handled ok
 * and later set it to the correct game type that is played
 */
set_gametype('normal');

/* put everyting in a form */
echo "<form action=\"index.php?action=game&amp;me=$me\" method=\"post\">\n";

/* handle user notes (only possible while game is running)*/
if( $mystatus!='gameover'  )
  if(myisset("note"))
{
  $note = $_REQUEST['note'];
  
  if($note != "")
    DB_insert_note($note,$gameid,$myid);
};
output_user_notes($myid,$gameid,$mystatus);

/* handle calls */
if(myisset("call")  && $_REQUEST["call"]  == "120" && can_call(120,$me))
  $result = DB_query("UPDATE Hand SET point_call='120' WHERE hash='$me' ");
if(myisset("call")  && $_REQUEST["call"]  == "90" && can_call(90,$me))
  $result = DB_query("UPDATE Hand SET point_call='90'  WHERE hash='$me' ");
if(myisset("call")  && $_REQUEST["call"]  == "60" && can_call(60,$me))
  $result = DB_query("UPDATE Hand SET point_call='60'  WHERE hash='$me' ");
if(myisset("call")  && $_REQUEST["call"]  == "30" && can_call(30,$me))
  $result = DB_query("UPDATE Hand SET point_call='30'  WHERE hash='$me' ");
if(myisset("call")  && $_REQUEST["call"]  == "0" && can_call(0,$me))
  $result = DB_query("UPDATE Hand SET point_call='0'   WHERE hash='$me' ");

/* output extra division in case this game is part of a session */
if($session)
  {
    echo "<div class=\"session\">\n";
    echo "  <div class=\"sessionrules\">Rules (+icons fur rules) \n";
    echo "    <div>\n";
    echo "       10ofhearts : ".$RULES["dullen"]      ."<br />\n";
    echo "       schweinchen: ".$RULES["schweinchen"] ."<br />\n";
    echo "       call:        ".$RULES["call"]        ."<br />\n";
    echo "    </div>\n  </div>\n";
    echo "  <div class=\"sessionscore\">Score \n";
    $score   = generate_score_table($session);
    echo format_score_table_html($score,$myid);
    echo "  </div>\n";
    $hashes = DB_get_hashes_by_session($session,$myid);
    $i = 1;
    foreach($hashes as $hash)
      {
        if($hash == $me)
	  $j=$i;
        $i++;
	$lasthash=$hash;
      }
    $i--;
    echo "This is game number $j of <a href=\"".$INDEX."?action=game&amp;me=$lasthash\">$i</a> in session $session.";
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
    /* don't ask if user has autosetup set to yest */
    $skip = 0;
    if($PREF['autosetup']=='yes') $skip = 1;

    if( !myisset("in") && !$skip)
      {
	/* asks the player, if he wants to join the game */
	output_check_want_to_play($me);
	break;
      }
    else
      {
	/* check the result, if player wants to join, got next stage, else cancel game */
	if(!$skip && $_REQUEST["in"] == "no" )
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
		    break;
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
    /* here we ask the player if he is sick */
    $mycards = DB_get_hand($me);
    $mycards = mysort($mycards,$gametype);

    /* output sickness of other playes, in case the already selected and are sitting in front of the current player */
    echo "\n<ul class=\"tricks\">\n";
    echo "  <li class=\"nohighlight\"> Game ".DB_format_gameid($gameid).": </li>\n";
    echo "  <li onclick=\"hl('0');\" class=\"current\"><a href=\"#\">Pre</a>\n".
      "    <div class=\"trick\" id=\"trick0\">\n";

    for($pos=1;$pos<5;$pos++)
      {
	$usersick   = DB_get_sickness_by_pos_and_gameid($pos,$gameid);
	$userid     = DB_get_userid('gameid-position',$gameid,$pos);
	$userstatus = DB_get_hand_status_by_userid_and_gameid($userid,$gameid);

	if($userstatus=='start' || $userstatus=='init')
	  echo " <div class=\"vorbehalt".($pos-1)."\"> still needs to decide </div>\n"; /* show this to everyone */
	else
	  if($usersick!=NULL && $pos<=$mypos ) /* only show this for people sitting before the player */
	    echo " <div class=\"vorbehalt".($pos-1)."\"> sick </div>\n";
	  else if($usersick==NULL && $pos<=$mypos)
	    echo " <div class=\"vorbehalt".($pos-1)."\"> healthy </div>\n";
      }
    echo "    </div>\n  </li>\n</ul>\n";  /* end div trick, end li trick , end tricks*/
    /* end displaying sickness */

    if(!myisset("solo","wedding","poverty","nines") )
      {
	output_check_for_sickness($me,$mycards);

	echo "<div class=\"mycards\">Your cards are: <br />\n";
	foreach($mycards as $card)
	  display_card($card,$PREF["cardset"]);
	echo "</div>\n";

	break;
      }
    else
      {
	/* check if someone selected more than one sickness */
	$Nsickness = 0;
	if($_REQUEST["solo"]!="No")       $Nsickness++;
	if($_REQUEST["wedding"] == "yes") $Nsickness++;
	if($_REQUEST["poverty"] == "yes") $Nsickness++;
	if($_REQUEST["nines"] == "yes")   $Nsickness++;

	if($Nsickness>1)
	  {
	    echo "<p class=\"message\"> You selected more than one sickness, please go back ".
	      "and answer the <a href=\"$INDEX?action=game&amp;me=$me&amp;in=yes\">question</a> again.</p>";

	    echo "<div class=\"mycards\">Your cards are: <br />\n";
	    foreach($mycards as $card)
	      display_card($card,$PREF["cardset"]);
	    echo "</div>\n";

	    break;
	  }
	else
	  {
	    /* everything is ok, save what user said and proceed */
	    echo "<p class=\"message\">Processing what you selected in the last step...";

	    /* check if this sickness needs to be handled first */
	    $gametype    = DB_get_gametype_by_gameid($gameid);
	    $startplayer = DB_get_startplayer_by_gameid($gameid); /* need this to check which solo goes first */

	    if( $_REQUEST["solo"]!="No" )
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
		/* silent solo is set further down */
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

	    echo "</p>\n";

	    /* move on to the next stage*/
	    DB_set_hand_status_by_hash($me,'check');
	  };
      };

  case 'check':
    /* here we check what all players said and figure out what game we are playing
     * this can therefore only be handled once all players finished the last stage
     */

    /* only need to redisplay the cards when the user reloads the page and lands directly here */
    if($mystatus=='check')
      {
	$mycards = DB_get_hand($me);
	$mycards = mysort($mycards,$gametype);

	/* output sickness of other playes, in case the already selected and are sitting in front of the current player */
	echo "\n<ul class=\"tricks\">\n";
	echo "  <li class=\"nohighlight\"> Game ".DB_format_gameid($gameid).": </li>\n";
	echo "  <li onclick=\"hl('0');\" class=\"current\"><a href=\"#\">Pre</a>\n".
	  "    <div class=\"trick\" id=\"trick0\">\n";

	for($pos=1;$pos<5;$pos++)
	  {
	    $usersick   = DB_get_sickness_by_pos_and_gameid($pos,$gameid);
	    $userid     = DB_get_userid('gameid-position',$gameid,$pos);
	    $userstatus = DB_get_hand_status_by_userid_and_gameid($userid,$gameid);

	    if($userstatus=='start' || $userstatus=='init')
	      echo " <div class=\"vorbehalt".($pos-1)."\"> still needs to decide </div>\n"; /* show this to everyone */
	    else
	      if($usersick!=NULL) /* in the init-phase we only showed players with $pos<$mypos, now we can show all */
		echo " <div class=\"vorbehalt".($pos-1)."\"> sick </div>\n";
	      else
		echo " <div class=\"vorbehalt".($pos-1)."\"> healthy </div>\n";
	  }
	echo "    </div>\n  </li>\n</ul>\n";  /* end div trick, end li trick , end tricks*/
	/* end displaying sickness */
      }

    echo "<div class=\"message\">\n";
    echo "<p> Checking if someone else selected solo, nines, wedding or poverty.</p>";

    /* check if everyone has reached this stage */
    $userids = DB_get_all_userid_by_gameid($gameid);
    $ok = 1;
    foreach($userids as $user)
      {
	$userstat = DB_get_hand_status_by_userid_and_gameid($user,$gameid);
	if($userstat!='check')
	  {
	    $ok = 0;
	    DB_set_player_by_gameid($gameid,$user);
	    break;
	  }
      };

    if(!$ok)
      {
	echo "<p>This step can only be handled after everyone finished the last step. ".
	  "Seems like this is not the case, so you need to wait a bit... ".
	  "you will get an email once that is the case, please use the link in ".
	  "that email to continue the game.</p></div>";

	/* display cards, if player was just at the init-phase he will still see the cards from there
	 * we can put this one here, since the last player to finish the init state won't get here and
	 * will still see his card anyway from the init-phase
	 */
	if($mystatus=='check')
	  {
	    /* show cards */
	    echo "<div class=\"mycards\">Your cards are: <br />\n";
	    foreach($mycards as $card)
	      display_card($card,$PREF["cardset"]);
	    echo "</div>\n";
	  }
	break;
      }
    else
      {
	/* Ok, everyone finished the init-phase, time to figure out what game we
	 * are playing, in case there are any solos this already
	 * will have the correct information in it */

	echo "<p> Ok, everyone is done... figuring out what kind of game we are playing.</p>";

	$gametype    = DB_get_gametype_by_gameid($gameid);
	$startplayer = DB_get_startplayer_by_gameid($gameid);

	/* check for sickness */
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
		break; /* no need to check for other poverties, since only solo can win and that is already set */
	      }
	    else if($usersick == 'poverty')
	      $poverty++;
	    else if($usersick == 'wedding')
	      $wedding=$user;
	    else if($usersick == 'solo')
	      $solo++;
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
	    return;
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
	/* now the gametype is set correctly in the database */
	echo "<p> Got it :)</p>";

	/* loop over all players, set re/contra if possible and start the game if possible */
	$userids = DB_get_all_userid_by_gameid($gameid);
	foreach($userids as $userid)
	  {
	    $userhash = DB_get_hash_from_gameid_and_userid($gameid,$userid);

	    switch($gametype)
	      {
	      case "solo":
		/* are we the solo player? set us to re, else set us to contra */
		$pos = DB_get_pos_by_hash($userhash);
		if($pos == $startplayer)
		  DB_set_party_by_hash($userhash,"re");
		else
		  DB_set_party_by_hash($userhash,"contra");
		DB_set_hand_status_by_hash($userhash,'play');
		break;

	      case "wedding":
		/* set person with the wedding to re, do the rest during the game */
		$usersick = DB_get_sickness_by_userid_and_gameid($userid,$gameid);
		if($usersick == "wedding")
		  DB_set_party_by_hash($userhash,"re");
		else
		  DB_set_party_by_hash($userhash,"contra");

		DB_set_hand_status_by_hash($userhash,'play');
		break;

	      case "normal":
		$hand = DB_get_all_hand($userhash);

		if(in_array('3',$hand)||in_array('4',$hand))
		  DB_set_party_by_hash($userhash,"re");
		else
		  DB_set_party_by_hash($userhash,"contra");
		DB_set_hand_status_by_hash($userhash,'play');
		break;
	      case "poverty":
	      case "dpoverty":
		/* set person with poverty to play status */
		$usersick = DB_get_sickness_by_userid_and_gameid($userid,$gameid);
		if($usersick == "poverty")
		  DB_set_hand_status_by_hash($userhash,'play');

		/* set status of first player to be asked to poverty */
		$who = DB_get_sickness_by_gameid($gameid);
		if($who > 6) $who= $who/10; /* in case we have dpoverty */
		$whoid = DB_get_userid('gameid-position',$gameid,$who);
		if($whoid==$userid)
		  DB_set_hand_status_by_hash($userhash,'poverty');
	      }
	  }
	/* check for silent solo, set game type to solo in this case */
	$gametype = DB_get_gametype_by_gameid($gameid);
	$userids  = DB_get_all_userid_by_gameid($gameid);
	foreach($userids as $userid)
	  {
	    $userhash = DB_get_hash_from_gameid_and_userid($gameid,$userid);

	    if($gametype=='normal')
	      {
		$userhand = DB_get_all_hand($userhash);
		if(check_wedding($userhand))
		  {
		    /* normal game type and player has both queens -> silent solo */
		    /* keep startplayer, just set gametype to silent solo */
		    DB_set_gametype_by_gameid($gameid,"solo");
		    DB_set_solo_by_gameid($gameid,'silent');
		  }
	      }
	  }

	/* send out email to first player or poverty person*/
	if($gametype!="poverty" && $gametype!="dpoverty")
	  {
	    $startplayer = DB_get_startplayer_by_gameid($gameid);
	    $email       = DB_get_email('position-gameid',$startplayer,$gameid);
	    $hash        = DB_get_hash_from_game_and_pos($gameid,$startplayer);
	    $who         = DB_get_userid('email',$email);
	    DB_set_player_by_gameid($gameid,$who);

	    if($hash!=$me)
	      {
		if(DB_get_email_pref_by_hash($hash)!="emailaddict")
		  {
		    /* email startplayer */
		    $message = "It's your turn now in game ".DB_format_gameid($gameid).".\n".
		      "Use this link to play a card: ".$HOST.$INDEX."?action=game&me=".$hash."\n\n" ;
		    mymail($email,$EmailName."ready, set, go... (game ".DB_format_gameid($gameid).") ",$message);
		  }
	      }
	    else
	      echo " Please, <a href=\"$INDEX?action=game&amp;me=$me\">start</a> the game.<br />\n";
	  }
	else
	  {
	    /* set status of first player to be asked to poverty */
	    $who   = DB_get_sickness_by_gameid($gameid);
	    if($who > 6) $who= $who/10; /* in case we have dpoverty */

	    $whoid = DB_get_userid('gameid-position',$gameid,$who);
	    if($whoid==$myid)
	      echo " Please, <a href=\"$INDEX?action=game&amp;me=$me\">start</a> the game.<br />\n";
	    else
	      {
		$email   = DB_get_email('position-gameid',$who,$gameid);
		$whohash = DB_get_hash_from_game_and_pos($gameid,$who);
		DB_set_player_by_gameid($gameid,$whoid);

		if(DB_get_email_pref_by_hash($hash)!="emailaddict")
		  {
		    /* email player for poverty */
		    $message = "Poverty: It's your turn now in game ".DB_format_gameid($gameid).".\n".
		      "Use this link to play a card: ".$HOST.$INDEX."?action=game&me=".$whohash."\n\n" ;
		    mymail($email,$EmailName."Poverty (game ".DB_format_gameid($gameid).") ",$message);
		  }
	      }
	  }
	echo "</div>\n";
	break;
      }
  case 'poverty':
    /* user only gets here in a poverty game, several things have to be handled here:
     * A) ask, if user wants to take trump
     *      yes-> take trump,
     *            poverty: set re/contra
     *            dpoverty: first time: set re, send email to second player
     *                      second time: set contra
     *            poverty: set status of other players to 'play'
     *            set status to play in case 0 trump
     *      no -> set status to play,
     *            ask next player or cancle the game if no more players
     * B) user took trump and has too many cards (e.g. count(cards)>12 and re/contra set)
     *         ask to give cards back, set status to play, once player has 12 cards
     *
     * it is easier to check B) first
     */

    /* output pre game in case user reloads */


    set_gametype($gametype); /* this sets the $CARDS variable */
    $myparty = DB_get_party_by_hash($me);

    /* the following is part B) of whats needs to be done)
    /*    check if user wants to give cards back */
    if(myisset("exchange"))
      {
	$exchange    = $_REQUEST['exchange'];
	$partnerhash = DB_get_partner_hash_by_hash($me);
	$partnerid   = DB_get_userid('hash',$partnerhash);
	$partnerhand = DB_get_handid('gameid-userid',$gameid,$partnerid);

	/* if exchange is set to a value>0, exchange that card back to the partner */
	if($exchange >0)
	  {
	    $result = DB_query("UPDATE Hand_Card SET hand_id='$partnerhand'".
			       " WHERE hand_id='$myhand' AND card_id=".DB_quote_smart($exchange));
	  };
      }

    /* update hand */
    $mycards = DB_get_hand($me);
    $mycards = mysort($mycards,$gametype);

    /* check if user need to give more cards back */
    if( ($myparty=='re' || $myparty=='contra') && count($mycards)>12)
      {
	echo "<div class=\"poverty\"> you need to get rid of a few cards</div>\n";

	$type="exchange";
	echo "<div class=\"mycards\">Your cards are: <br />\n";
	foreach($mycards as $card)
	  display_link_card($card,$PREF["cardset"],$type);
	echo "  <input type=\"submit\" class=\"submitbutton\" value=\"select card to give back\" />\n";
	echo "</div>\n";
      }
    else if( ($myparty=='re' || $myparty=='contra') && count($mycards)==12)
      {
	/* user is done, ready to play */
	DB_set_hand_status_by_hash($me,'play');

	/* email start player */
	$startplayer = DB_get_startplayer_by_gameid($gameid);
	$email       = DB_get_email('position-gameid',$startplayer,$gameid);
	$hash        = DB_get_hash_from_game_and_pos($gameid,$startplayer);
	$who         = DB_get_userid('email',$email);
	DB_set_player_by_gameid($gameid,$who);

	if($hash!=$me)
	  {
	    if(DB_get_email_pref_by_hash($hash)!="emailaddict")
	      {
		/* email startplayer */
		$message = "It's your turn now in game ".DB_format_gameid($gameid).".\n".
		  "Use this link to play a card: ".$HOST.$INDEX."?action=game&me=".$hash."\n\n" ;
		mymail($email,$EmailName."ready, set, go... (game ".DB_format_gameid($gameid).") ",$message);
	      }
	  }
	else
	  echo " Please, <a href=\"$INDEX?action=game&amp;me=$me\">start</a> the game.<br />\n";
      }

    /* the following is part A) of what needs to be done */
    if(!myisset("trump"))
      {
	if(!$myparty)
	  {
	    echo "<div class=\"poverty\">\n";
	    $userids = DB_get_all_userid_by_gameid($gameid);
	    foreach($userids as $user)
	      {
		$name      = DB_get_name('userid',$user);
		$usersick  = DB_get_sickness_by_userid_and_gameid($user,$gameid);
		$userhash  = DB_get_hash_from_gameid_and_userid($gameid,$user);
		$userparty = DB_get_party_by_hash($userhash);

		if($usersick=="poverty" && !$userparty)
		  {
		    $hash    = DB_get_hash_from_gameid_and_userid($gameid,$user);
		    $cards   = DB_get_hand($hash);
		    /* count trump */
		    $nrtrump = 0;
		    foreach($cards as $card)
		      if($card<27) $nrtrump++;
		    echo "Player $name has $nrtrump trump. Do you want to take them?".
		      "<a href=\"index.php?action=game&amp;me=$me&amp;trump=$user\">Yes</a> <br />\n";
		  }
	      }
	    echo "<a href=\"index.php?action=game&amp;me=$me&amp;trump=no\">No way</a> <br />\n";
	    echo "</div><div>\n";

	    echo "<div class=\"mycards\">Your cards are: <br />\n";
	    foreach($mycards as $card)
	      display_card($card,$PREF["cardset"]);
	    echo "</div></div>\n";
	  }
	break;
      }
    else
      {
	$trump = $_REQUEST['trump'];

	if($trump=="no")
	  {
	    /* user doesn't want to take trump */
	    DB_set_hand_status_by_hash($me,'play');

	    /* set next player who needs to be asked and email him*/
	    $firstsick  = (string) DB_get_sickness_by_pos_and_gameid($mypos+1,$gameid);
	    $secondsick = (string) DB_get_sickness_by_pos_and_gameid($mypos+2,$gameid);

	    /* don't ask people who have poverty */
	    $next=1;
	    if($firstsick=="poverty")
	      {
		if($secondsick=="poverty")
		  $next=3;
		else
		  $next=2;
	      }
	    if($gametype=="dpoverty")
	      {
		$next=999; /* need to cancel for sure, since both would need to take the trump */
	      }

	    /* no more people to ask, need to cancel the game */
	    if($mypos+$next>4)
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
		return;
	      }
	    else
	      {
		/* email next player, set his status to poverty */
		$To       = DB_get_email('position-gameid',$mypos+$next,$gameid);
		$userhash = DB_get_hash_from_game_and_pos($gameid,$mypos+$next);
		$userid   = DB_get_userid('email',$To);

		DB_set_player_by_gameid($gameid,$userid);
		DB_set_hand_status_by_hash($userhash,'poverty');

		$message = "Someone has poverty, it's your turn to decide, if you want to take the trump. Please visit:".
		  " ".$HOST.$INDEX."?action=game&amp;me=".$userhash."\n\n" ;
		mymail($To,$EmailName." poverty (game ".DB_format_gameid($gameid).")",$message);
	      }
	  }
	else
	  {
	    /* player wants to take trump, change cards */

	    /* user wants to take trump */
	    $trump = $_REQUEST["trump"];
	    $userhand = DB_get_handid('gameid-userid',$gameid,$trump);
	    $userhash = DB_get_hash_from_gameid_and_userid($gameid,$trump);

	    /* copy trump from player A to B */
	    $result = DB_query("UPDATE Hand_Card SET hand_id='$myhand' WHERE hand_id='$userhand' AND card_id<'27'" );

	    /* reload cards */
	    $mycards = DB_get_hand($me);

	    /* set re/contra */
	    if($gametype=='poverty')
	      {
		$userids = DB_get_all_userid_by_gameid($gameid);
		foreach($userids as $user)
		  {
		    $hash = DB_get_hash_from_gameid_and_userid($gameid,$user);
		    if($hash==$userhash||$hash==$me)
		      {
			DB_set_party_by_hash($hash,"re");
		      }
		    else
		      {
			DB_set_party_by_hash($hash,"contra");
			DB_set_hand_status_by_hash($hash,'play'); /* the contra party is ready to play */
		      }
		  }
		/* check if we are done (in case of no trump handed over), if so, go to 'play' phase right away*/
		if(count($mycards)==12)
		  {
		    DB_set_hand_status_by_hash($me,'play');
		  }
	      }
	    else /*dpoverty*/
	      {
		/* has the re party already been set?*/
		$re_set=0;
		$userids = DB_get_all_userid_by_gameid($gameid);
		foreach($userids as $user)
		  {
		    $hash = DB_get_hash_from_gameid_and_userid($gameid,$user);
		    $party = DB_get_party_by_hash($hash);
		    if($party=='re')
		      $re_set=1;
		  }
		if($re_set)
		  {
		    DB_set_party_by_hash($me,'contra');
		    DB_set_party_by_hash($userhash,'contra');
		  }
		else
		  {
		    DB_set_party_by_hash($me,'re');
		    DB_set_party_by_hash($userhash,'re');

		    /* send out email to second non-poverty player */
		    $firstsick  = (string) DB_get_sickness_by_pos_and_gameid($mypos+1,$gameid);
		    $secondsick = (string) DB_get_sickness_by_pos_and_gameid($mypos+2,$gameid);

		    $next=1;
		    if($firstsick=="poverty")
		      if($secondsick=="poverty")
			$next=3;
		      else
			$next=2;

		    if($mypos+$next>4)
		      echo "<div class=\"message\">Error in poverty, please contact the Admin</div>\n";

		    $To       = DB_get_email('position-gameid',$mypos+$next,$gameid);
		    $userhash = DB_get_hash_from_game_and_pos($gameid,$mypos+$next);
		    $userid   = DB_get_userid('email',$To);

		    DB_set_player_by_gameid($gameid,$userid);
		    DB_set_hand_status_by_hash($userhash,'poverty');

		    $message = "Two people have poverty, it's your turn to decide, if you want to take the trump. Please visit:".
		      " ".$HOST.$INDEX."?action=game&me=".$userhash."\n\n" ;
		    mymail($To,$EmailName." double poverty (game ".DB_format_gameid($gameid).")",$message);


		  }
	      }
	    echo "<div class=\"message\"> Please, <a href=\"$INDEX?action=game&amp;me=$me\">continue</a> here.</div>\n";
	  }
      }
    echo "</div>";
    break;

  case 'play':
  case 'gameover':
    /* both entries here,  so that the tricks are visible for both.
     * in case of 'play' there is a break later that skips the last part
     */

    /* check if all players are ready to play,
     * if so, send out email to the startplayer
     * only need to do this if the game hasn't started yet
     */
    $gamestatus = DB_get_game_status_by_gameid($gameid);
    if($gamestatus == 'pre')
      {
	$ok = 1;
	$userids = DB_get_all_userid_by_gameid($gameid);
	foreach($userids as $user)
	  {
	    $userstatus = DB_get_hand_status_by_userid_and_gameid($user,$gameid);
	    if($userstatus !='play' && $userstatus!='gameover')
	      {
		$ok = 0;
		DB_set_player_by_gameid($gameid,$user);
		break;
	      }
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
	  }
      }
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
	if($gametype=='silent')
	  $GT = 'normal';
	else
	  $GT = $gametype." ".$GT;
      }
    else
      $gametype = "normal";

    set_gametype($gametype); /* this sets the $CARDS variable */

    /* get some infos about the game, need to reset this, since it might have changed */
    $gamestatus = DB_get_game_status_by_gameid($gameid);

    /* has the game started? No, then just wait here...*/
    if($gamestatus == 'pre')
      {
	echo "<p class=\"message\"> You finished the setup, but not everyone else finished it... ".
	  "You need to wait for the others. Just wait for an email. </p>";

	$mycards = DB_get_hand($me);
	$mycards = mysort($mycards,$gametype);

	echo "<div class=\"mycards\">Your cards are: <br />\n";
	foreach($mycards as $card)
	  display_card($card,$PREF["cardset"]);
	echo "</div>\n";

	break; /* not sure this works... the idea is that you can
		* only  play a card after everyone is ready to play */
      }

    /* get time from the last action of the game */
    $r = DB_query_array("SELECT mod_date from Game WHERE id='$gameid' " );
    $gameend = time() - strtotime($r[0]);

    /* handle comments in case player didn't play a card, allow comments a week after the end of the game */
    if( (!myisset("card") && $mystatus=='play') || ($mystatus=='gameover' && ($gameend < 60*60*24*7)) )
      if(myisset("comment"))
	{
	  $comment = $_REQUEST["comment"];
	  $playid = DB_get_current_playid($gameid);

	  if($comment != "")
	    DB_insert_comment($comment,$playid,$myid);
	};

    /* get everything relevant to display the tricks */
    $result = DB_query("SELECT Hand_Card.card_id as card,".
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
    $trickNR   = 0;
    $lasttrick = DB_get_max_trickid($gameid);

    $play = array(); /* needed to calculate winner later  */
    $seq  = 1;
    $pos  = DB_get_startplayer_by_gameid($gameid)-1;
    $firstcard = ""; /* first card in a trick */

    echo "\n<ul class=\"tricks\">\n";
    echo "  <li class=\"nohighlight\"> Game ".DB_format_gameid($gameid).": </li>\n";

    /* output vorbehalte */
    $mygametype =  DB_get_gametype_by_gameid($gameid);
    if($mygametype != 'normal' && $mygametype != 'silent') /* only show when needed */
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
    while($r = DB_fetch_array($result))
      {
	$pos     = $r[1];
	$seq     = $r[2];
	$trick   = $r[3];
	$comment = $r[4];
	$user    = $r[6];

	/* count number of tricks */
	if($seq==1)
	  $trickNR++;

	/* check if first schweinchen has been played */
	if( $GAME['schweinchen-who'] && ($r[0] == 19 || $r[0] == 20) )
	  if(!$GAME['schweinchen-first'])
	    $GAME['schweinchen-first'] = 1; /* playing the first fox */
	  else
	    $GAME['schweinchen-second'] = 1; /* this must be the second fox */

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
	$commentSchweinchen =""; /* used to add a comment when Schweinchen is being played */

	/* check if we have card and that we haven't played it yet*/
	/* set played in hand_card to true where hand_id and card_id*/
	$r = DB_query_array("SELECT id FROM Hand_Card WHERE played='false' and ".
			      "hand_id='$handid' AND card_id=".DB_quote_smart($card));
	$handcardid = $r[0];

	if($handcardid) /* everything ok, play card  */
	  {
	    /* update Game timestamp */
	    DB_update_game_timestamp($gameid);

	    /* mark card as played */
	    DB_query("UPDATE Hand_Card SET played='true' WHERE hand_id='$handid' AND card_id=".
		     DB_quote_smart($card));

	    /* get trick id or start new trick */
	    $a = DB_get_current_trickid($gameid);
	    $trickid  = $a[0];
	    $sequence = $a[1];
	    $tricknr  = $a[2];

	    $playid = DB_play_card($trickid,$handcardid,$sequence);

	    /* check special output for schweinchen in case in case a fox is being played
	     * check for correct rules, etc. has already been done
	     */
	    if( $GAME["schweinchen-who"] && ($card == 19 || $card == 20) )
	      {
		if(!$GAME['schweinchen-first'])
		  $GAME['schweinchen-first'] = 1; /* playing the first fox */
		else
		  $GAME['schweinchen-second'] = 1; /* this must be the second fox */

		if( ($GAME['schweinchen-second']==1 && $RULES['schweinchen']=='second') || $RULES['schweinchen']=='both')
		  {
		    DB_insert_comment("Schweinchen! ",$playid,$myid);
		    $commentSchweinchen = "Schweinchen! ";
		  }
		if ($debug)
		  echo "schweinchen = ".$GAME["schweinchen-who"]." ---<br />";
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
		    if($solo == 'trump' || $solo == 'silent')
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
			      DB_query("INSERT INTO Score".
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

			  DB_query("INSERT INTO Score".
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

		    DB_query("INSERT INTO Score".
			     " VALUES( NULL,NULL,$gameid,'$party1',$uid1,NULL,'doko')");
		  }

		if($winner>0)
		  DB_query("UPDATE Trick SET winner='$winner' WHERE id='$trickid'");
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
		if($commentSchweinchen)
		  $comment = $commentSchweinchen . $comment;
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
		$result = DB_query("SELECT User.fullname, IFNULL(SUM(Card.points),0), Hand.party FROM Hand".
				   " LEFT JOIN Trick ON Trick.winner=Hand.position AND Trick.game_id=Hand.game_id".
				   " LEFT JOIN User ON User.id=Hand.user_id".
				   " LEFT JOIN Play ON Trick.id=Play.trick_id".
				   " LEFT JOIN Hand_Card ON Hand_Card.id=Play.hand_card_id".
				   " LEFT JOIN Card ON Card.id=Hand_Card.card_id".
				   " WHERE Hand.game_id='$gameid'".
				   " GROUP BY User.fullname" );
		$message  = "The game is over. Thanks for playing :)\n";
		$message .= "Final score:\n";
		while( $r = DB_fetch_array($result) )
		  $message .= "   ".$r[0]."(".$r[2].") ".$r[1]."\n";

		$result = DB_query("SELECT  Hand.party, IFNULL(SUM(Card.points),0) FROM Hand".
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
		while( $r = DB_fetch_array($result) )
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
		  {
		    /* nobody made a call, so it's easy to figure out who won */
		    if($re>120)
		      $winning_party="re";
		    else
		      $winning_party="contra";
		  }
		else
		  {
		    /* if one party makes a call, they only win, iff they make enough points
		     * if only one party made a call, the other one wins,
		     * if the first one didn't make it
		     */
		    if($call_re)
		      {
			$offset = 120 - $call_re;
			if($call_re == 0)
			  $offset--; /* since we use a > in the next equation */

			if($re > 120+$offset)
			  $winning_party="re";
			else if ($call_contra == NULL )
			  $winning_party="contra";
		      }

		    if($call_contra)
		      {
			$offset = 120 - $call_contra;
			if($call_contra == 0)
			  $offset--; /* since we use a > in the next equation */

			if($contra > 120+$offset)
			  $winning_party="contra";
			else if ($call_re == NULL )
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
			  DB_query("INSERT INTO Score".
				   " VALUES( NULL,NULL,$gameid,'re',NULL,NULL,'against$p')");
			}

		      for( $p=$call_contra; $p<120; $p+=30)
			{
			  if( $re >= $p )
			    DB_query("INSERT INTO Score".
				     " VALUES( NULL,NULL,$gameid,'re',NULL,NULL,'made$p')");
			}
		    }
		  if($winning_party!="re" and $call_re!=NULL)
		    {
		      for( $p=$call_re;$p<=120; $p+=30 )
			{
			  DB_query("INSERT INTO Score".
				   " VALUES( NULL,NULL,$gameid,'contra',NULL,NULL,'against$p')");
			}

		      for( $p=$call_re; $p<120; $p+=30)
			{
			  if( $contra>=$p )
			    DB_query("INSERT INTO Score".
				     " VALUES( NULL,NULL,$gameid,'contra',NULL,NULL,'made$p')");
			}
		    }

		  /* point in case contra won */
		  if($winning_party=="contra")
		    {
		      DB_query("INSERT INTO Score".
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
			    DB_query("INSERT INTO Score".
				     " VALUES( NULL,NULL,$gameid,'re',NULL,NULL,'".(240-$p)."')");
			}
		      /* re called something and won */
		      foreach(array(0,30,60,90,120) as $p)
			{
			  if($call_re!=NULL && $call_re<$p+1)
			    DB_query("INSERT INTO Score".
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
			    DB_query("INSERT INTO Score".
				     " VALUES( NULL,NULL,$gameid,'contra',NULL,NULL,'".(240-$p)."')");
			}
		      /* re called something and won */
		      foreach(array(0,30,60,90,120) as $p)
			{
			  if($call_contra!=NULL && $call_contra<$p+1)
			    DB_query("INSERT INTO Score".
				     " VALUES( NULL,NULL,$gameid,'contra',NULL,NULL,'call$p')");
			}
		    }


		  /* add score points to email */
		  $message .= "\n";
		  $Tpoint = 0;
		  $message .= " Points Re: \n";
		  $queryresult = DB_query("SELECT score FROM Score ".
					  "  WHERE game_id=$gameid AND party='re'".
					  " ");
		  while($r = DB_fetch_array($queryresult) )
		    {
		      $message .= "   ".$r[0]."\n";
		      $Tpoint ++;
		    }
		  $message .= " Points Contra: \n";
		  $queryresult = DB_query("SELECT score FROM Score ".
					  "  WHERE game_id=$gameid AND party='contra'".
					  " ");
		  while($r = DB_fetch_array($queryresult) )
		    {
		      $message .= "   ".$r[0]."\n";
		      $Tpoint --;
		    }
		  $message .= " Total Points (from the Re point of view): $Tpoint\n";
		  $message .= "\n";

		  $session = DB_get_session_by_gameid($gameid);
		  $score = generate_score_table($session);

		  $message .= "Score Table:\n";
		  $message .= format_score_table_ascii($score);

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

      if($seq!=4 && $trickNR>=1)
	echo "    </div>\n  </li>\n";  /* end div trick, end li trick */

      /* display points in case game is over */
      if($mystatus=='gameover' && DB_get_game_status_by_gameid($gameid)=='gameover' )
	{
	  echo "  <li onclick=\"hl('13');\" class=\"current\"><a href=\"#\">Score</a>\n".
	    "    <div class=\"trick\" id=\"trick13\">\n";
	  /* add pic for re/contra
	   "      <img class=\"arrow\" src=\"pics/arrow".($pos-1).".png\" alt=\"table\" />\n";*/

	  $result = DB_query("SELECT User.fullname, IFNULL(SUM(Card.points),0), Hand.party,Hand.position FROM Hand".
			     " LEFT JOIN Trick ON Trick.winner=Hand.position AND Trick.game_id=Hand.game_id".
			     " LEFT JOIN User ON User.id=Hand.user_id".
			     " LEFT JOIN Play ON Trick.id=Play.trick_id".
			     " LEFT JOIN Hand_Card ON Hand_Card.id=Play.hand_card_id".
			     " LEFT JOIN Card ON Card.id=Hand_Card.card_id".
			     " WHERE Hand.game_id='$gameid'".
			     " GROUP BY User.fullname" );
	  while( $r = DB_fetch_array($result))
	    echo "      <div class=\"card".($r[3]-1)."\">\n".
	         "        <div class=\"score\">".$r[2]."<br /> ".$r[1]."</div>\n".
	         "      </div>\n";

	  echo "    </div>\n  </li>\n";  /* end div trick, end li trick */
	}

      echo "  <li onclick=\"hl_prev();\" class=\"old\"><a href=\"#\">prev</a></li>\n";
      echo "  <li onclick=\"hl_next();\" class=\"old\"><a href=\"#\">next</a></li>\n";
      echo "</ul>\n"; /* end ul tricks*/

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
	      if( ($followsuit && !same_type($card,$firstcard)) ||
		  ( (int)($card)==19 && ($RULES['schweinchen']=='second'||$RULES['schweinchen']=='secondaftercall')
		    && $GAME['schweinchen-who']==$me && !$GAME['schweinchen-first'] )
		  )
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
	  $result = DB_query("SELECT Hand.party, IFNULL(SUM(Card.points),0) FROM Hand".
			     " LEFT JOIN Trick ON Trick.winner=Hand.position AND Trick.game_id=Hand.game_id".
			     " LEFT JOIN User ON User.id=Hand.user_id".
			     " LEFT JOIN Play ON Trick.id=Play.trick_id".
			     " LEFT JOIN Hand_Card ON Hand_Card.id=Play.hand_card_id".
			     " LEFT JOIN Card ON Card.id=Hand_Card.card_id".
			     " WHERE Hand.game_id='$gameid'".
			     " GROUP BY Hand.party" );
	  echo "<div class=\"total\"> Totals:<br />\n";
	  while( $r = DB_fetch_array($result))
	    echo "  ".$r[0]." ".$r[1]."<br />\n";

	  $queryresult = DB_query("SELECT timediff(mod_date,create_date) ".
				  " FROM Game WHERE id='$gameid'");
	  $r = DB_fetch_array($queryresult);
	  echo "<p>This game took ".$r[0]." hours.</p>";

	  echo "<div class=\"re\">\n Points Re: <br />\n";
	  $queryresult = DB_query("SELECT score FROM Score ".
				  "  WHERE game_id=$gameid AND party='re'".
				  " ");
	  while($r = DB_fetch_array($queryresult) )
	    echo "   ".$r[0]."<br />\n";
	  echo "</div>\n";

	  echo "<div class=\"contra\">\n Points Contra: <br />\n";
	  $queryresult = DB_query("SELECT score FROM Score ".
				  "  WHERE game_id=$gameid AND party='contra'".
				  " ");
	  while($r = DB_fetch_array($queryresult) )
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

    if($gamestatus == 'play' )
      output_form_calls($me);

    /* get time from the last action of the game */
    $r = DB_query_array("SELECT mod_date from Game WHERE id='$gameid' " );
    $gameend = time() - strtotime($r[0]);

    if($gamestatus == 'play' || $gameend < 60*60*24*7)
      {
	echo "<br />\nA short comment:<input name=\"comment\" type=\"text\" size=\"15\" maxlength=\"100\" />\n";
      }

    echo "<input type=\"submit\" value=\"submit\" />\n";

    echo "</div>\n";

    echo "</form>\n";

    if($mystatus=='gameover' && DB_get_game_status_by_gameid($gameid)=='gameover' )
      {

	$session = DB_get_session_by_gameid($gameid);
	$result  = DB_query("SELECT id,create_date FROM Game".
			    " WHERE session=$session".
			    " ORDER BY create_date DESC".
			    " LIMIT 1");
	$r = -1;
	if($result)
	  $r = DB_fetch_array($result);

	if(!$session || $gameid==$r[0])
	  {
	    /* suggest a new game with the same people in it, just rotated once (unless last game was solo) */
	    $names = DB_get_all_names_by_gameid($gameid);
	    $type  = DB_get_gametype_by_gameid($gameid);

	    if($type=="solo")
	      {
		$solo = DB_get_solo_by_gameid($gameid);

		if($solo!='silent') /* repeat game with same first player */
		  output_ask_for_new_game($names[0],$names[1],$names[2],$names[3],$gameid);
		else /* rotate normally */
		  output_ask_for_new_game($names[1],$names[2],$names[3],$names[0],$gameid);
	      }
	    else /* rotate normally */
	      output_ask_for_new_game($names[1],$names[2],$names[3],$names[0],$gameid);
	  }
      }
?>