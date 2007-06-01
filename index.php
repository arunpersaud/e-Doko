<?php
error_reporting(E_ALL);

global $REV;
$REV  ="\$Rev$";

include_once("config.php");      
include_once("output.php");      /* html output only */
include_once("db.php");          /* database only */
include_once("functions.php");   /* the rest */

/* check if some variables are set in the config file, else set defaults */
if(!isset($EmailName))
     $EmailName="[DoKo] ";


/* in case work has to be done on the database or other section we can
 * shut down the server and tell people to come back later 
 */
if(0) 
  {
    output_header();
    echo "Working on the database...please check back in a few mintues"; 
    output_footer(); 
    exit(); 
  }

if(DB_open()<0)
  {
    output_header();
    echo "Database error, can't connect...";
    output_footer(); 
    exit(); 
  }

/* done major error checking, output header of HTML page */
output_header();

/* check if we want to start a new game */
if(myisset("new"))
  {
    $names = DB_get_all_names();
    output_form_for_new_game($names);
  }
/*check if everything is ready to set up a new game */
 else if( myisset("PlayerA", "PlayerB","PlayerC","PlayerD","dullen","schweinchen" ))
  {
    $PlayerA = $_REQUEST["PlayerA"];
    $PlayerB = $_REQUEST["PlayerB"];
    $PlayerC = $_REQUEST["PlayerC"];
    $PlayerD = $_REQUEST["PlayerD"];

    $dullen      = $_REQUEST["dullen"];
    $schweinchen = $_REQUEST["schweinchen"];
    
    $EmailA  = DB_get_email_by_name($PlayerA);
    $EmailB  = DB_get_email_by_name($PlayerB);
    $EmailC  = DB_get_email_by_name($PlayerC);
    $EmailD  = DB_get_email_by_name($PlayerD);
    
    if($EmailA=="" || $EmailB=="" || $EmailC=="" || $EmailD=="")
      {
	echo "couldn't find one of the names, please start a new game";
	output_footer();
	exit();
      }
    
    $useridA  = DB_get_userid_by_name($PlayerA);
    $useridB  = DB_get_userid_by_name($PlayerB);
    $useridC  = DB_get_userid_by_name($PlayerC);
    $useridD  = DB_get_userid_by_name($PlayerD);
    
    /* create random numbers */
    $randomNR       = create_array_of_random_numbers();
    $randomNRstring = join(":",$randomNR);
    
    /* get ruleset information or create new one */
    $ruleset = DB_get_ruleset($dullen,$schweinchen);
    if($ruleset <0) 
      {
	echo "Error defining ruleset: $ruleset";
	output_footer();
	exit();
      };
    
    /* create game */
    $followup = NULL;
    if(myisset("followup") )
      {
	$followup= $_REQUEST["followup"];
	$session = DB_get_session_by_gameid($followup);
	$ruleset = DB_get_ruleset_by_gameid($followup); /* just copy ruleset from old game, 
							 this way no manipulation is possible */
	if($session)
	  mysql_query("INSERT INTO Game VALUES (NULL, NULL, '$randomNRstring', 'normal', NULL,NULL,'1','pre',".
		      "'$ruleset','$session' ,NULL)");
	else
	  {
	    /* get max session */
	    $max = DB_get_max_session();
	    $max++;
	    mysql_query("INSERT INTO Game VALUES (NULL, NULL, '$randomNRstring', 'normal', NULL,NULL,'1','pre',".
			"'$ruleset','$max' ,NULL)");
	    mysql_query("UPDATE Game SET session='".$max."' WHERE id=".DB_quote_smart($followup));
	  }
      }
    else
      mysql_query("INSERT INTO Game VALUES (NULL, NULL, '$randomNRstring', 'normal', NULL,NULL,'1','pre', ".
		  "'$ruleset',NULL ,NULL)");
    $game_id = mysql_insert_id();
    
    /* create hash */
    $TIME  = (string) time(); /* to avoid collisions */
    $hashA = md5("AGameOfDoko".$game_id.$PlayerA.$EmailA.$TIME);
    $hashB = md5("AGameOfDoko".$game_id.$PlayerB.$EmailB.$TIME);
    $hashC = md5("AGameOfDoko".$game_id.$PlayerC.$EmailC.$TIME);
    $hashD = md5("AGameOfDoko".$game_id.$PlayerD.$EmailD.$TIME);
    
    /* create hands */
    mysql_query("INSERT INTO Hand VALUES (NULL,".DB_quote_smart($game_id).",".DB_quote_smart($useridA).
		", ".DB_quote_smart($hashA).", 'start','1',NULL,NULL,NULL,NULL)");
    $hand_idA = mysql_insert_id();							       
    mysql_query("INSERT INTO Hand VALUES (NULL,".DB_quote_smart($game_id).",".DB_quote_smart($useridB).
		", ".DB_quote_smart($hashB).", 'start','2',NULL,NULL,NULL,NULL)");
    $hand_idB = mysql_insert_id();							       
    mysql_query("INSERT INTO Hand VALUES (NULL,".DB_quote_smart($game_id).",".DB_quote_smart($useridC).
		", ".DB_quote_smart($hashC).", 'start','3',NULL,NULL,NULL,NULL)");
    $hand_idC = mysql_insert_id();							       
    mysql_query("INSERT INTO Hand VALUES (NULL,".DB_quote_smart($game_id).",".DB_quote_smart($useridD).
		", ".DB_quote_smart($hashD).", 'start','4',NULL,NULL,NULL,NULL)");
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
      "".$host."?me=";
    
    mymail($EmailA,"You are invited to a game of DoKo","Hello $PlayerA,\n".$message.$hashA);
    mymail($EmailB,"You are invited to a game of DoKo","Hello $PlayerB,\n".$message.$hashB);
    mymail($EmailC,"You are invited to a game of DoKo","Hello $PlayerC,\n".$message.$hashC);
    mymail($EmailD,"You are invited to a game of DoKo","Hello $PlayerD,\n".$message.$hashD);
    
    echo "You started a new game. The emails have been sent out!";    
  }    /* end set up a new game */
/* cancle a game, if nothing has happend in the last N minutes */
else if(myisset("cancle","me"))
  {
    $me = $_REQUEST["me"];
    
    /* test for valid ID */
    $myid = DB_get_userid_by_hash($me);
    if(!$myid)
      {
	echo "Can't find you in the database, please check the url.<br />\n";
	echo "perhaps the game has been cancled, check by login in <a href=\"$host\">here</a>.";
	output_footer();
	exit();
      }
    
    DB_update_user_timestamp($myid);
    
    /* get some information from the DB */
    $gameid   = DB_get_gameid_by_hash($me);
    $myname   = DB_get_name_by_hash($me);
    
    /* check if game really is old enough */
    $result = mysql_query("SELECT mod_date from Game WHERE id='$gameid' " );
    $r = mysql_fetch_array($result,MYSQL_NUM);
    if(time()-strtotime($r[0]) > 60*60*24*30) /* = 1 month */
      {
	$message = "Hello, \n\n".
	  "Game $gameid has been cancled since nothing happend for a while and $myname requested it.\n";
	
	$userids = DB_get_all_userid_by_gameid($gameid);
	foreach($userids as $user)
	  {
	    $To = DB_get_email_by_userid($user);
	    mymail($To,$EmailName."game $gameid cancled (timed out)",$message);
	  }
	
	/* delete everything from the dB */
	DB_cancel_game($me);
	
	echo "<p style=\"background-color:red\";>Game $gameid has been cancled.<br /><br /></p>";
      }
    else
      echo "<p>You need to wait longer before you can cancle a game...</p>\n";
  }
/* handle request from one specific player for one game,
 * (the hash is set on a per game base) */
else if(myisset("me"))
  {
    $me = $_REQUEST["me"];
    
    /* test for valid ID */
    $myid = DB_get_userid_by_hash($me);
    if(!$myid)
      {
	echo "Can't find you in the database, please check the url.<br />\n";
	echo "perhaps the game has been cancled, check by login in <a href=\"$host\">here</a>.";
	output_footer();
	exit();
      }

    /* the user had done something, update the timestamp */
    DB_update_user_timestamp($myid);
    
    /* get some information from the DB */
    $gameid   = DB_get_gameid_by_hash($me);
    $myname   = DB_get_name_by_hash($me);
    $mystatus = DB_get_status_by_hash($me);
    $mypos    = DB_get_pos_by_hash($me);
    $myhand   = DB_get_handid_by_hash($me);

    /* get prefs and save them */
    DB_get_PREF($myid);
    /* end set pref */
      
      
    /* get rule set for this game */
    $result = mysql_query("SELECT * FROM Rulesets".
			  " LEFT JOIN Game ON Game.ruleset=Rulesets.id ".
			  " WHERE Game.id='$gameid'" );
    $r      = mysql_fetch_array($result,MYSQL_NUM);

    $RULES["dullen"]      = $r[2];
    $RULES["schweinchen"] = $r[3];
    
    /* get some infos about the game */
    $gametype   = DB_get_gametype_by_gameid($gameid);
    $gamestatus = DB_get_game_status_by_gameid($gameid);
    $GT         = $gametype;
    if($gametype=="solo")
      {
	$gametype = DB_get_solo_by_gameid($gameid);
	$GT  = $gametype." ".$GT;
      }

    /* display rule set for this game */
    echo "<div class=\"ruleset\">\n";

    if($gamestatus != 'pre')
      echo " Gametype: $GT <br />\n";
    
    echo "Rules: <br />\n";
    echo "10ofhearts : ".$r[2]."<br />\n";
    echo "schweinchen: ".$r[3]."<br />\n";
    echo "</div>\n";
    
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

    /* mystatus gets the player through the different stages of a game.
     * start:    yes/no
     * init:     check values from start,
     *           check for sickness
     * check:    check for return values from init
     * poverty:  handle poverty, wait here until all player have reached this state
     *           display sickness and move on to game
     * play:     game in progress
     * gameover: are we revisiting a game
     */
    switch($mystatus)
      {
      case 'start':
	check_want_to_play($me);
	/* move on to the next stage*/
	DB_set_hand_status_by_hash($me,'init');
	break;
      case 'init':
	/* first check if everything went ok  in the last step
	 * if not, send user back, if yes, check what he did
	 */
	if( !myisset("in") )
	  {
	    echo "<p> you need to answer the <a href=\"$host?me=$me\">question</a>.</p>";
	    DB_set_hand_status_by_hash($me,'start');
	  }
	else
	  {
	    if($_REQUEST["in"] == "no")
	      {
		/* cancle the game */
		$message = "Hello, \n\n".
		  "the game has been canceled due to the request of one of the players.\n";
		
		$userids = DB_get_all_userid_by_gameid($gameid);
		foreach($userids as $user)
		  {
		    $To = DB_get_email_by_userid($user);
		    mymail($To,$EmailName."game $gameid canceled",$message);
		  }
		
		/* delete everything from the dB */
		DB_cancel_game($me);
	      }
	    else
	      {
		echo "thanks for joining the game...";
		
		$mycards = DB_get_hand($me);
		sort($mycards);
		echo "<p class=\"mycards\" style=\"margin-top:8em;\">your cards are: <br />\n";
		foreach($mycards as $card) 
		  display_card($card,$PREF["cardset"]);
		echo "</p>\n";   
		
		check_for_sickness($me,$mycards);
		
		/* move on to the next stage*/
		DB_set_hand_status_by_hash($me,'check');
	      }
	  }
	break;

    case 'check':
      /* ok, user is in the game, saw his cards and selected his vorbehalt
       * so first we check what he selected
       */
      echo "Processing what you selected in the last step...<br />";

      if(!myisset("solo","wedding","poverty","nines") )
	{
	  /* all these variables have a pre-selected default,
	   * so we should never get here,
	   * unless a user tries to cheat ;) */
	  echo "something went wrong...please contact the admin.";
	}
      else
	{
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
	}

      echo " Ok, done with checking, please go to the <a href=\"$host?me=$me\">next step of the setup</a>.<br />";

      /* move on to the next stage*/
      DB_set_hand_status_by_hash($me,'poverty');

      /* check if everyone has reached this stage, send out email */
      $userids = DB_get_all_userid_by_gameid($gameid);
      $ok=1;
      foreach($userids as $user)
	{
	  $userstat = DB_get_hand_status_by_userid_and_gameid($user,$gameid);
	  if($userstat!='poverty' && $userstat!='play')
	    $ok=0;
	};
      if($ok)
	foreach($userids as $user)
	  {
	    $To = DB_get_email_by_userid($user);
	    $userhash =DB_get_hash_from_gameid_and_userid($gameid,$user);
	    if($userhash!=$me)
	      {
		$message = "Everyone finish the questionary in game $gameid, ".
		           "please visit this link now to continue: \n".
		           " ".$host."?me=".$userhash."\n\n" ;
		mymail($To,$EmailName." finished setup",$message);
	      }
	  };

      break;

    case 'poverty':
      /* here we need to check if there is a solo or some other form of sickness.
       * If so, which one is the most important one
       * set that one in the Game table
       * tell people about it.
       */
      echo "<br /> Checking if someone else selected solo, nines or wedding or poverty.<br />";
      
      /* check if everyone has reached this stage */
      $userids = DB_get_all_userid_by_gameid($gameid);
      $ok=1;
      foreach($userids as $user)
	{
	  $userstat = DB_get_hand_status_by_userid_and_gameid($user,$gameid);
	  if($userstat!='poverty' && $userstat!='play')
	    $ok=0;
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

	  
	  $nines = 0;
	  $poverty = 0;
	  $wedding = 0;
	  $solo = 0;
	  foreach($userids as $user)
	    {
	      $name = DB_get_name_by_userid($user);
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

	  /* gamestatus == normal, => cancel game */
	  if($gametype == "solo")
	    {
	      /* do nothing */
	    }
	  else if($nines)
	    {
	      /* cancle game */
	      /* TODO: should we keep statistics of this? */
	      $message = "Hello, \n\n".
		"the game has been canceled because ".DB_get_name_by_userid($nines).
		" has five or more nines and nobody is playing solo.\n";
	      
	      /* TODO: add info about redeal in case this is a game of a series */
	      
	      $userids = DB_get_all_userid_by_gameid($gameid);
	      foreach($userids as $user)
		{
		  $To = DB_get_email_by_userid($user);
		  mymail($To,$EmailName."game $gameid canceled",$message);
		}
	      
	      /* delete everything from the dB */
	      DB_cancel_game($me);
	      
	      echo "The game has been canceled because ".DB_get_name_by_userid($nines).
		" has five or more nines and nobody is playing solo.\n";
	      output_footer();
	      exit();
	    }
	  else if($poverty==1)
	    {
	      DB_set_gametype_by_gameid($gameid,"poverty");
	      $gametype = "poverty";
	      $who=DB_get_sickness_by_gameid($gameid);
	      if(!$who)
		{
		  $firstsick = DB_get_sickness_by_pos_and_gameid(1,$gameid);
		  if($firstsick == "poverty")
		    DB_set_sickness_by_gameid($gameid,2); /* who needs to be asked first */
		  else
		    DB_set_sickness_by_gameid($gameid,1); /* who needs to be asked first */
		}
	    }
	  else if($poverty==2)
	    {
	      DB_set_gametype_by_gameid($gameid,"dpoverty");
	      $gametype = "dpoverty";
	      $who=DB_get_sickness_by_gameid($gameid);
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
	       * so output whatever the firstone has, then whatever the next one has
	       * stop when the sickness is the same as the gametype 
	       */
	      
	      $name     = DB_get_name_by_userid($user);
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
	      echo DB_get_name_by_hash($GAME["schweinchen-who"])." has Schweinchen. <br />";
	  
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
	      echo " Ok, the game can start now, please finish <a href=\"$host?me=$me\">the setup</a>.<br />";	     
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
		      $To       = DB_get_email_by_pos_and_gameid($who,$gameid);
		      $userhash = DB_get_hash_from_game_and_pos($gameid,$who);
		      
		      $message = "Someone has poverty, it's your turn to decide, if you want to take the trump. Please visit:".
			" ".$host."?me=".$userhash."\n\n" ;
		      mymail($To,$EmailName." poverty",$message);
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
		  $userhand = DB_get_handid_by_gameid_and_userid($gameid,$trump);
		  /* copy trump from player A to B */
		  $result = mysql_query("UPDATE Hand_Card SET hand_id='$myhand' WHERE hand_id='$userhand' AND card_id<'27'" );
		  
		  /* add hidden button with trump in it to get to the next point */
		  echo "<form action=\"index.php\" method=\"post\">\n";
		  echo "  <input type=\"hidden\" name=\"exchange\" value=\"-1\" />\n";
		  echo "  <input type=\"hidden\" name=\"trump\" value=\"".$trump."\" />\n";
		  echo "  <input type=\"hidden\" name=\"me\" value=\"".$me."\" />\n";
		  echo "  <input type=\"submit\" class=\"submitbutton\" value=\"select cards to give back\" />\n";
		  echo "</form>\n";
		}
	      else if(myisset("trump","exchange") && $_REQUEST["trump"]>0 && ($who==$mypos || $who==$mypos*10))
		{
		  $trump    = $_REQUEST["trump"];
		  $exchange = $_REQUEST["exchange"];
		  $userhand = DB_get_handid_by_gameid_and_userid($gameid,$trump);

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
		      die("error in poverty");
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
			      $To       = DB_get_email_by_pos_and_gameid($who,$gameid);
			      $userhash = DB_get_hash_from_game_and_pos($gameid,$who);
			      
			      $message = "Someone has poverty, it's your turn to decide, ".
				         "if you want to take the trump. Please visit:".
				         " ".$host."?me=".$userhash."\n\n" ;
			      mymail($To,$EmailName." poverty",$message);
			    }

			}
		      
		      /* this user is done */
		      DB_set_hand_status_by_hash($me,'play');
		      /* and so is his partner */
		      $hash = DB_get_hash_from_gameid_and_userid($gameid,$trump);
		      DB_set_hand_status_by_hash($hash,'play');

		      /* set party to re, unless we had dpoverty, in that case check if we need to set re/contra*/
		      $re_set=0;
		      foreach($userids as $user)
			{
			  $userhash =DB_get_hash_from_gameid_and_userid($gameid,$user);
			  $party=DB_get_party_by_hash($userhash);
			  if($party=="re")
			    $re_set=1;
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
			      $userhash =DB_get_hash_from_gameid_and_userid($gameid,$user);
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
		      echo "you need to get rid of a few cards<br />\n";
		      
		      set_gametype($gametype); /* this sets the $CARDS variable */
		      $mycards = DB_get_hand($me);
		      $mycards = mysort($mycards,$gametype);

		      echo "<form class=\"exchange\" action=\"index.php\" method=\"post\">\n";
		      $type="exchange";
		      foreach($mycards as $card) 
			display_link_card($card,$PREF["cardset"],$type);
		      echo "  <input type=\"hidden\" name=\"trump\" value=\"".$trump."\" />\n";
		      echo "  <input type=\"hidden\" name=\"me\" value=\"".$me."\" />\n";
		      echo "  <input type=\"submit\" class=\"submitbutton\" value=\"select one card to give back\" />\n";
		      echo "</form>\n";
		    }
		}
	      else if($who == $mypos || $who == $mypos*10)
		{
		  foreach($userids as $user)
		    {
		      $name = DB_get_name_by_userid($user);
		      $usersick = DB_get_sickness_by_userid_and_gameid($user,$gameid);
		      
		      if($usersick=="poverty")
			{
			  $hash =DB_get_hash_from_gameid_and_userid($gameid,$user);
			  $cards=DB_get_hand($hash);
			  $nrtrump=count_trump($cards);
			  /* count trump */
			  if($nrtrump<4)
			    echo "Player $name has $nrtrump trump. Do you want to take them?".
			      "<a href=\"index.php?me=$me&amp;trump=$user\">yes</a> <br />";
			}
		    }
		  echo "I don't want to take any trump: ".
		    "<a href=\"index.php?me=$me&amp;trump=no\">yes</a> <br />";

		  echo "Your cards are: <br />\n";
		  $mycards = DB_get_hand($me);
		  sort($mycards);
		  echo "<p class=\"mycards\" style=\"margin-top:8em;\">your cards are: <br />\n";
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
	      /*
	       *    yes, display number of trump and user's hand, ask if he wants to take it 
	       *      no, set whom-to-ask to next player, email next player, cancle game if no next player
	       *      yes -> link to new page:display all cards, ask for N return cards
	       *          set re/contra 
	       *        
	       */
	    };
	}
      /* check if no one wanted to take trump, in that case the gamesickness would be set to 5 or 50 */
      $who = DB_get_sickness_by_gameid($gameid);
      if($who==5 || $who==50)
	{
	  $message = "Hello, \n\n".
	    "Game $gameid has been cancled since nobody wanted to take the trump.\n";
	  
	  $userids = DB_get_all_userid_by_gameid($gameid);
	  foreach($userids as $user)
	    {
	      $To = DB_get_email_by_userid($user);
	      mymail($To,$EmailName."game $gameid cancled (poverty not resolved)",$message);
	    }
	  
	  /* delete everything from the dB */
	  DB_cancel_game($me);
	  
	  echo "<p style=\"background-color:red\";>Game $gameid has been cancled.<br /><br /></p>";
	  output_footer();
	  exit();
	}

      /* check if all players are ready to play */
      $ok=1;
      foreach($userids as $user)
	if(DB_get_hand_status_by_userid_and_gameid($user,$gameid)!='play')
	  $ok=0;

      if($ok)
	{
	  /* only set this after all poverty, etc. are handled*/
	  DB_set_game_status_by_gameid($gameid,'play');

	  /* email startplayer */
	  $startplayer = DB_get_startplayer_by_gameid($gameid);
	  $email       = DB_get_email_by_pos_and_gameid($startplayer,$gameid);
	  $hash        = DB_get_hash_from_game_and_pos($gameid,$startplayer);
	  
	  if($hash!=$me)
	    {
	      /* email startplayer) */
	      $message = "It's your turn now in game $gameid.\n".
		"Use this link to play a card: ".$host."?me=".$hash."\n\n" ;
	      mymail($email,$EmailName."ready, set, go... ",$message);
	    }
	  else
	    echo " Please, <a href=\"$host?me=$me\">start</a> the game.<br />";	 
	}
      else
	echo "\n <br />";	 

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
      $GT = $gametype;
      if($gametype=="solo")
	{
	  $gametype = DB_get_solo_by_gameid($gameid);
	  $GT = $gametype." ".$GT;
	}
      else
	$gametype="normal";
      
      set_gametype($gametype); /* this sets the $CARDS variable */
      
      /* get some infos about the game */
      $gamestatus = DB_get_game_status_by_gameid($gameid);
      
      /* display useful things in divs */
      
      /* display links to the users status page */
      $result = mysql_query("SELECT email,password from User WHERE id='$myid'" );
      $r      = mysql_fetch_array($result,MYSQL_NUM);
      
      display_links($r[0],$r[1]);
      
      /* end display useful things*/
      
      /* has the game started? No, then just wait here...*/
      if($gamestatus == 'pre')
	{
	  echo "You finished the setup, but not everyone else finished it... ".
	       "so you need to wait for the others. Just wait for the an email... <br />";
	  break; /* not sure this works... the idea is that you can 
		  * only  play a card after everyone is ready to play */
	}
      
      /* display the table and the names */
      $result = mysql_query("SELECT  User.fullname as name,".
			    "        Hand.position as position, ".
			    "        User.id, ".
			    "        Hand.party as party, ".
			    "        Hand.sickness as sickness, ".
			    "        Hand.point_call, ".
			    "        User.last_login ".
			    "FROM Hand ".
			    "LEFT JOIN User ON User.id=Hand.user_id ".
			    "WHERE Hand.game_id='".$gameid."' ".
			    "ORDER BY position ASC");
      
      echo "<div class=\"table\">\n".
	"  <img src=\"pics/table.png\" alt=\"table\" />\n";
      while($r = mysql_fetch_array($result,MYSQL_NUM))
	{
	  $name  = $r[0];
	  $pos   = $r[1];
	  $user  = $r[2];
	  $party = $r[3];
	  $sickness  = $r[4];
	  $call      = $r[5];
	  $lastlogin = strtotime($r[6]);
	  
	  $offset = DB_get_user_timezone($user);
	  $zone   = return_timezone($offset);
	  date_default_timezone_set($zone);

	  echo " <span class=\"table".($pos-1)."\">\n";
	  echo " $name ";
	  /* add hints for poverty, wedding, solo, etc */
	  if($GT=="poverty" && $party=="re")
	    if($sickness=="poverty")
	      {
		$userhash = DB_get_hash_from_gameid_and_userid($gameid,$user);
		$cards = DB_get_all_hand($userhash);
		$trumpNR = count_trump($cards);
		if($trumpNR)
		  echo "(poverty < trump back)";
		else
		  echo "(poverty <)";
	      }
	    else
	      echo "(poverty >)";

	  if($GT=="dpoverty")
	    if($party=="re")
	      if($sickness=="poverty")
		{
		$userhash = DB_get_hash_from_gameid_and_userid($gameid,$user);
		$cards = DB_get_all_hand($userhash);
		$trumpNR = count_trump($cards);
		if($trumpNR)
		  echo "(poverty A < trump back)";
		else
		  echo "(poverty A <)";
		}
	      else
		echo "(poverty A >)";
	    else
	      if($sickness=="poverty")
		{
		$userhash = DB_get_hash_from_gameid_and_userid($gameid,$user);
		$cards = DB_get_all_hand($userhash);
		$trumpNR = count_trump($cards);
		if($trumpNR)
		  echo "(poverty B < trump back)";
		else
		  echo "(poverty B <)";
		}
	      else
		echo "(poverty B >)";
	      
	  if($GT=="wedding" && $party=="re")
	      if($sickness=="wedding")
		echo "(wedding  +)";
	      else
		echo "(wedding)";
	  
	  if(substr_compare($GT,"solo",0,4)==0 && $party=="re")
	     echo "($GT)";

	  /* add point calls */
	  if($call!=NULL)
	    echo " $party $call ";

	  echo "<br />\n";
	  echo " local time: ".date("Y-m-d H:i:s")."<br />\n";
	  echo " last login: ".date("Y-m-d H:i:s",$lastlogin)."<br />\n";
	  echo " </span>\n";

	}
      echo  "</div>\n";

      /* get everything relevant to display the tricks */
      $result = mysql_query("SELECT Hand_Card.card_id as card,".
			    "       Hand.position as position,".
			    "       Play.sequence as sequence, ".
			    "       Trick.id, ".
			    "       Comment.comment, ".
			    "       Play.create_date, ".
			    "       Hand.user_id ".
			    "FROM Trick ".
			    "LEFT JOIN Play ON Trick.id=Play.trick_id ".
			    "LEFT JOIN Hand_Card ON Play.hand_card_id=Hand_Card.id ".
			    "LEFT JOIN Hand ON Hand_Card.hand_id=Hand.id ".
			    "LEFT JOIN Comment ON Play.id=Comment.play_id ".
			    "WHERE Trick.game_id='".$gameid."' ".
			    "ORDER BY Trick.id,sequence ASC");
      $trickNR = 1;
      
      $lasttrick = DB_get_max_trickid($gameid);
      
      $play = array(); /* needed to calculate winner later  */
      $seq  = 1;          
      $pos  = DB_get_startplayer_by_gameid($gameid)-1; 
      $firstcard = ""; /* first card in a trick */
      
      echo "\n<ul class=\"tricks\">\n";
      echo "  <li class=\"nohighlight\"> Game $gameid: </li>\n";
      
      while($r = mysql_fetch_array($result,MYSQL_NUM))
	{
	  $pos     = $r[1];
	  $seq     = $r[2];
	  $trick   = $r[3];
	  $comment = $r[4];
	  $timeplayed = strtotime($r[5]);
	  $user    = $r[6];

	  $offset = DB_get_user_timezone($user);
	  $zone   = return_timezone($offset);
	  date_default_timezone_set($zone);

	  /* check if first schweinchen has been played */
	  if($r[0] == 19 || $r[0] == 20 )
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
	      echo "    </div>\n  </li>\n";  /* end div table, end li table */
	    }
	}
      
      if($seq!=4 && $trickNR>1) 
	echo "    </div>\n  </li>\n";  /* end div table, end li table */
      
      echo "</ul>\n";
      
      /* whos turn is it? */
      if($seq==4)
	{
	  $winner = get_winner($play,$gametype); /* returns the position */
	  $next = $winner;
	  $firstcard = ""; /* new trick, no first card */
	}
      else
	{
	  $next = $pos+1;
	  if($next==5) $next=1;
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
	  $handid = DB_get_handid_by_hash($me); 
	  
	  /* check if we have card and that we haven't played it yet*/
	  /* set played in hand_card to true where hand_id and card_id*/
	  $result = mysql_query("SELECT id FROM Hand_Card WHERE played='false' and ".
				"hand_id='$handid' AND card_id=".DB_quote_smart($card));
	  $r = mysql_fetch_array($result,MYSQL_NUM);
	  $handcardid = $r[0];
	  
	  if($handcardid) /* everything ok, play card  */
	    {
	      $comment = "";

	      /* mark card as played */
	      mysql_query("UPDATE Hand_Card SET played='true' WHERE hand_id='$handid' AND card_id=".
			  DB_quote_smart($card));

	      /* update Game timestamp */
	      DB_update_game_timestamp($gameid);

	      /* check if a call was made */
	      if(myisset("call120") && $_REQUEST["call120"] == "yes")
		$result = mysql_query("UPDATE Hand SET point_call='120' WHERE hash='$me' ");
	      if(myisset("call90")  && $_REQUEST["call90"]  == "yes")
		$result = mysql_query("UPDATE Hand SET point_call='90'  WHERE hash='$me' ");
	      if(myisset("call60")  && $_REQUEST["call60"]  == "yes")
		$result = mysql_query("UPDATE Hand SET point_call='60'  WHERE hash='$me' ");
	      if(myisset("call30")  && $_REQUEST["call30"]  == "yes")
		$result = mysql_query("UPDATE Hand SET point_call='30'  WHERE hash='$me' ");
	      if(myisset("call0")   && $_REQUEST["call0"]   == "yes")
		$result = mysql_query("UPDATE Hand SET point_call='0'   WHERE hash='$me' ");
		

	      /* check for schweinchen */
	      //echo "schweinchen = ".$GAME["schweinchen"]." --$card-<br />";
	      if($card == 19 || $card == 20 )
		{
		  $GAME["schweinchen"]++;
		  if($GAME["schweinchen"]==3 && $RULES["schweinchen"]=="second" )
		    $comment="Schweinchen! ";
		  if($RULES["schweinchen"]=="both" )
		    $comment="Schweinchen! ";
		  if ($debug) echo "schweinchen = ".$GAME["schweinchen"]." ---<br />";
		}

	      /* get trick id or start new trick */
	      $a = DB_get_current_trickid($gameid);
	      $trickid  = $a[0];
	      $sequence = $a[1];
	      $tricknr  = $a[2];
	      
	      $playid = DB_play_card($trickid,$handcardid,$sequence);
	      
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
		      $winnerid = DB_get_userid_by_gameid_and_position($gameid,$winner);
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
	      
	      /* check for coment */
	      if(myisset("comment"))
		{
		  $comment.=$_REQUEST["comment"];
		};  
	      if($comment != "")
		DB_insert_comment($comment,$playid,$myid);

	      /* display played card */
	      echo "<div class=\"card\">";
	      echo " you played  <br />";
	      display_card($card,$PREF["cardset"]);
	      echo "</div>\n";
	      
	      /*check if we still have cards left, else set status to gameover */
	      if(sizeof(DB_get_hand($me))==0)
		{
		  DB_set_hand_status_by_hash($me,'gameover');
		  $mystatus='gameover';
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
		{
		  DB_set_game_status_by_gameid($gameid,"gameover");
		  /* get score for last trick 
		   * all other tricks are handled a few lines further down*/
		  $play   = DB_get_cards_by_trick($trickid);
		  $winner = get_winner($play,$gametype); /* returns the position */
		  /* get points of last trick and save it */
		  $points = 0;
		  foreach($play as $card)
		    $points = $points + card_value($card["card"]);
		  $winnerid = DB_get_handid_by_gameid_and_position($gameid,$winner);
		  if($winnerid>0)
		    mysql_query("INSERT INTO Score VALUES (NULL, '$gameid', '$winnerid', '$points')");
		  else
		    echo "ERROR during scoring";
		  
		  /* email all players */
		  /* individual score */
		  $result = mysql_query("SELECT fullname, SUM(score), Hand.party FROM Score".
					" LEFT JOIN Hand ON Hand.id=hand_id".
					" LEFT JOIN User ON Hand.user_id=User.id".
					" WHERE Hand.game_id=$gameid".
					" GROUP BY fullname" );
		  $message = "The game is over. Thanks for playing :)\n";
		  while( $r = mysql_fetch_array($result,MYSQL_NUM))
		    $message .= " FINAL SCORE: ".$r[0]."(".$r[2].") ".$r[1]."\n";
		  $message .= "\nIf your not in the list above your score is zero...\n\n";

		  $result = mysql_query("SELECT Hand.party, SUM(score) FROM Score".
					" LEFT JOIN Hand ON Hand.id=hand_id".
					" LEFT JOIN User ON Hand.user_id=User.id".
					" WHERE Hand.game_id=$gameid".
					" GROUP BY Hand.party" );
		  $message .= "\n";
		  while( $r = mysql_fetch_array($result,MYSQL_NUM))
		    $message .= " FINAL SCORE: ".$r[0]." ".$r[1]."\n";
		  
		  /* check who wants to be CC'ed on the email */
		  $h = array();
		  $header = "";
		  foreach($userids as $user)
		    {
		      $result = mysql_query("SELECT value from User_Prefs".
					    " WHERE user_id='$user' AND pref_key='ccemail'" );
		      $r = mysql_fetch_array($result,MYSQL_NUM);
		      if($r && $r[0]=="yes")
			$h[]   = DB_get_email_by_userid($user);
		    }
		  if(sizeof($h))
		    $header = "CC: ".join(",",$h)."\r\n";
		  
		  foreach($userids as $user)
		    {
		      $To   = DB_get_email_by_userid($user);
		      $hash = DB_get_hash_from_gameid_and_userid($gameid,$user);
		      $mymessage = $message."Use this link to have a look at the game: ".$host."?me=".$hash."\n\n" ;
		      mymail($To,$EmailName."game over (game $gameid)",$mymessage,$header);
		    }
		}
	      
	      
	      /* email next player */
	      if(DB_get_game_status_by_gameid($gameid)=='play')
		{
		  if($sequence==4)
		    {
		      $play   = DB_get_cards_by_trick($trickid);
		      $winner = get_winner($play,$gametype); /* returns the position */
		      
		      /* get points of last trick and save it, last trick is handled 
		       * a few lines further up  */
		      $points = 0;
		      foreach($play as $card)
			$points = $points + card_value($card["card"]);
		      
		      $winnerid = DB_get_handid_by_gameid_and_position($gameid,$winner);
		      if($winnerid>0)
			mysql_query("INSERT INTO Score VALUES (NULL, '$gameid', '$winnerid', '$points')");
		      else
			echo "ERROR during scoring";
		      
		      if($debug)
			echo "DEBUG: $winner got $points <br />";
		      
		      /* who is the next player? */
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
		  
		  $message = "A card has been played in game $gameid.\n\n".
		    "It's your turn  now.\n".
		    "Use this link to play a card: ".$host."?me=".$next_hash."\n\n" ;
		  mymail($email,$EmailName."a card has been played",$message);		  
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
      
      $mycards = DB_get_hand($me);
      $mycards = mysort($mycards,$gametype);
      echo "<div class=\"mycards\">\n";
      
      if($myturn && !myisset("card"))
	{
	  echo "Hello ".$myname.", it's your turn!  <br />\n";
	  echo "Your cards are: <br />\n";
	  echo "<form  action=\"index.php?me=$me\" method=\"post\">\n";
	  
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
	  
	  if( can_call(120,$me) )
	      echo " re/contra (120):".
		" <input type=\"radio\" name=\"call120\" value=\"yes\" /> ";
	  if( can_call(90,$me) )
	      echo " 90:".
		" <input type=\"radio\" name=\"call90\" value=\"yes\" /> ";
	  if( can_call(60,$me) )
	      echo " 60:".
		" <input type=\"radio\" name=\"call60\" value=\"yes\" /> ";
	  if( can_call(30,$me) )
	      echo " 30:".
		" <input type=\"radio\" name=\"call30\" value=\"yes\" /> ";
	  if( can_call(0,$me) )
	      echo " 0:".
		" <input type=\"radio\" name=\"call0\" value=\"yes\" /> ";

	  echo "<br />\nA short comments:<input name=\"comment\" type=\"text\" size=\"30\" maxlength=\"50\" />\n";
	  echo "<input type=\"hidden\" name=\"me\" value=\"$me\" />\n";
	  echo "<input type=\"submit\" value=\"move\" />\n";
	  echo "</form>\n";
	}
      else if($mystatus=='play')
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
	  echo "the game is over now...<br />\n";
	  
	  $result = mysql_query("SELECT fullname, SUM(score), Hand.party FROM Score".
				" LEFT JOIN Hand ON Hand.id=hand_id".
				" LEFT JOIN User ON Hand.user_id=User.id".
				" WHERE Hand.game_id=$gameid".
				" GROUP BY fullname" );
	  while( $r = mysql_fetch_array($result,MYSQL_NUM))
	    echo " FINAL SCORE: ".$r[0]."(".$r[2].") ".$r[1]."<br />";
	  
	  $result = mysql_query("SELECT Hand.party, SUM(score) FROM Score".
				" LEFT JOIN Hand ON Hand.id=hand_id".
				" LEFT JOIN User ON Hand.user_id=User.id".
				" WHERE Hand.game_id=$gameid".
				" GROUP BY Hand.party" );
	  while( $r = mysql_fetch_array($result,MYSQL_NUM))
	    echo " FINAL SCORE: ".$r[0]." ".$r[1]."<br />\n";

	  
	  $session = DB_get_session_by_gameid($gameid);
	  $result  = mysql_query("SELECT id,create_date FROM Game".
				 " WHERE session=$session".
				 " ORDER BY create_date DESC".
				 " LIMIT 1");
	  $r=-1;
	  if($result)
	    $r = mysql_fetch_array($result,MYSQL_NUM);
	  
	  if(!$session || $gameid==$r[0])
	    {
	      /* suggest a new game with the same people in it, just rotated once */
	      $names = DB_get_all_names_by_gameid($gameid);
	      output_ask_for_new_game($names[1],$names[2],$names[3],$names[0],$gameid);
	    }
	}
      break;
    default:
      echo "error in testing the status";
    }
    output_footer();
    exit();
 } 
/* user status page */ 
 else if(myisset("email","password"))
   {
     /* test id and password, should really be done in one step */
     $email     = $_REQUEST["email"];
     $password  = $_REQUEST["password"];
     

     if(myisset("forgot"))
       {
	 $ok=1;

	 $uid = DB_get_userid_by_email($email);
	 if(!$uid)
	   $ok=0;
	 
	 if($ok)
	   {
	     echo "Hmm, you forgot your passwort...nothing I can do at the moment:(  ";
	     echo " you need to email Arun for now... in the future it will be all automated and an ";
	     echo "email with a new password will go to $email.";
	   }
	 else
	   {
	     if($email=="")
	       echo "you need to give me an email address!";
	     else
	       echo "couldn't find a player with this email, please contact Arun, if you think this is a mistake";
	   } 
       }
     else 
     {
       /* verify password and email */
       if(strlen($password)!=32)
	 $password = md5($password);
       
       $ok=1;
       $uid = DB_get_userid_by_email_and_password($email,$password);
       if(!$uid)
	 $ok=0;
       
       if($ok)
	 {
	   DB_get_PREF($uid);

	   if(myisset("setpref"))
	     {
	       $setpref=$_REQUEST["setpref"];
	       switch($setpref)
		 {
		 case "germancards":
		 case "englishcards":
		   $result = mysql_query("SELECT * from User_Prefs".
					 " WHERE user_id='$uid' AND pref_key='cardset'" );
		   if( mysql_fetch_array($result,MYSQL_NUM))
		     $result = mysql_query("UPDATE User_Prefs SET value=".DB_quote_smart($setpref).
					   " WHERE user_id='$uid' AND pref_key='cardset'" );
		   else
		     $result = mysql_query("INSERT INTO User_Prefs VALUES(NULL,'$uid','cardset',".DB_quote_smart($setpref).")");
		   echo "Ok, changed you preferences for the cards.\n";
		   break;
		 case "ccemail":
		   $result = mysql_query("SELECT * from User_Prefs".
					 " WHERE user_id='$uid' AND pref_key='ccemail'" );
		   if( mysql_fetch_array($result,MYSQL_NUM))
		     if($PREF["ccemail"]=="yes")
		       $result = mysql_query("UPDATE User_Prefs SET value=".DB_quote_smart("no").
					     " WHERE user_id='$uid' AND pref_key='ccemail'" );
		     else
		       $result = mysql_query("UPDATE User_Prefs SET value=".DB_quote_smart("yes").
					     " WHERE user_id='$uid' AND pref_key='ccemail'" );
		   else
		     $result = mysql_query("INSERT INTO User_Prefs VALUES(NULL,'$uid','ccemail',".DB_quote_smart("yes").")");
		   echo "Ok, changed you preferences for being CC'ed on emails.\n";
		   break;

		 }
	     }
	   else /* output default user page */
	     {
	       $time = DB_get_user_timestamp($uid);
	       $unixtime =strtotime($time);
	       
	       $offset = DB_get_user_timezone($uid);
	       $zone = return_timezone($offset);
	       date_default_timezone_set($zone);
	       
	       /* display links to settings */
	       output_user_settings($email,$password);
	       
	       echo "last login: ".date("r",$unixtime)."<br />";
	       
	       DB_update_user_timestamp($uid);
	       
	       echo "<p>these are your games that haven't started yet:<br />\n";
	       $result = mysql_query("SELECT Hand.hash,Hand.game_id,Game.mod_date from Hand".
				     " LEFT JOIN Game On Hand.game_id=Game.id".
				     " WHERE Hand.user_id='$uid' AND Game.status='pre'" );
	       while( $r = mysql_fetch_array($result,MYSQL_NUM))
		 {
		   echo "<a href=\"".$host."?me=".$r[0]."\">game #".$r[1]." </a>";
		   if(time()-strtotime($r[2]) > 60*60*24*30)
		     echo " The game has been running for over a month.".
		       " Do you want to cancel it? <a href=\"$host?cancle=1&amp;me=".$r[0]."\">yes</a>".
		       " (clicking here is final and can't be restored)";
		   echo "<br />";
		 }
	       echo "</p>\n";

	       echo "<p>these are the games you are playing in:<br />\n";
	       $result = mysql_query("SELECT Hand.hash,Hand.game_id,Game.mod_date from Hand".
				     " LEFT JOIN Game On Hand.game_id=Game.id".
				     " WHERE Hand.user_id='$uid' AND Game.status='play'" );
	       while( $r = mysql_fetch_array($result,MYSQL_NUM))
		 {
		   echo "<a href=\"".$host."?me=".$r[0]."\">game #".$r[1]." </a>";
		   if(time()-strtotime($r[2]) > 60*60*24*30)
		     echo " The game has been running for over a month.".
		       " Do you want to cancel it? <a href=\"$host?cancle=1&amp;me=".$r[0]."\">yes</a>".
		       " (clicking here is final and can't be restored)";
		   echo "<br />";
		 }
	       echo "</p>\n";
	       
	       
	       echo "<p>and these are your games that are already done:<br />Game: \n";
	       $result = mysql_query("SELECT hash,game_id from Hand WHERE user_id='$uid' AND status='gameover'" );
	       while( $r = mysql_fetch_array($result,MYSQL_NUM))
		 echo "<a href=\"".$host."?me=".$r[0]."\">#".$r[1]." </a>, ";
	       echo "</p>\n";
	       
	       $names = DB_get_all_names();
	       echo "<p>registered players:<br />\n";
	       foreach ($names as $name)
		 echo "$name, \n";
	       echo "</p>\n";
	       
	       echo "<p>Want to start a new game? Visit <a href=\"".$host."?new\">this page.</a></p>";
	     }
	 }
       else
	 {
	   echo "sorry email and password don't match <br />";
	 }
     };
     output_footer();
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
     $pre[0]=0;$game[0]=0;$done[0]=0;
     $r=mysql_query("SELECT COUNT(id) FROM Game GROUP BY status");
     if($r) {
       $pre  = mysql_fetch_array($r,MYSQL_NUM);     
       $game = mysql_fetch_array($r,MYSQL_NUM);     
       $done = mysql_fetch_array($r,MYSQL_NUM);     
     }
     output_home_page($pre[0],$game[0],$done[0]);
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


