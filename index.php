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

DB_open();
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
	  mysql_query("INSERT INTO Game VALUES (NULL, NULL, '$randomNRstring', 'normal', NULL,'1','pre',".
		      "'$ruleset','$session' ,NULL)");
	else
	  {
	    /* get max session */
	    $max = DB_get_max_session();
	    $max++;
	    mysql_query("INSERT INTO Game VALUES (NULL, NULL, '$randomNRstring', 'normal', NULL,'1','pre',".
			"'$ruleset','$max' ,NULL)");
	    mysql_query("UPDATE Game SET session='".$max."' WHERE id=".DB_quote_smart($followup));
	  }
      }
    else
      mysql_query("INSERT INTO Game VALUES (NULL, NULL, '$randomNRstring', 'normal', NULL,'1','pre', ".
		  "'$ruleset',NULL ,NULL)");
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
	    mymail($To,$EmailName."game cancled (timed out)",$message);
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
	    echo "you need to answer the <a href=\"$host?me=$me\">question</a>.";
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
		    mymail($To,$EmailName."game canceled",$message);
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
		  display_card($card);
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
	      echo "So you got poverty. You might as well have said nothing, since this is not implemented yet,".
		" you need to play a normal game...to make it a bit harder, I'll tell the other people that".
		" you only have a few trump... should make the game more interesting (although perhaps not for you:))<br />\n";
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
      break;

    case 'poverty':
      /* here we need to check if there is a solo or some other form of sickness.
       * If so, which one is the most important one
       * set that one in the Game table
       * tell people about it.
       */
      echo "<br /> Checking if someone else selected solo, nines or wedding... Poverty not handled at the moment<br />";
      
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
	    "Seems like this is not the case, so you need to wait a bit... please check back later....<br />";
	}
      else
	{
	  echo "Everyone has finished checking their cards, let's see what they said...<br />";

	  /* check what kind of game we are playing,  in case there are any solos this already 
	   *will have the correct information in it */
	  $gametype    = DB_get_gametype_by_gameid($gameid);
	  $startplayer = DB_get_startplayer_by_gameid($gameid);

	  /* check for different sickness and just output a general info */

	  /* check for nines */
	  $nines = 0;
	  foreach($userids as $user)
	    if(DB_get_sickness_by_userid_and_gameid($user,$gameid) == 'nines')
	      {
		$nines = $user;
		$name = DB_get_name_by_userid($user);
		echo "$name has a Vorbehalt. <br />";
		break;
	      }
	  	  
	  /* check players for poverty */
	  $poverty = 0;
	  foreach($userids as $user)
	    {
	      if(DB_get_sickness_by_userid_and_gameid($user,$gameid) == 'poverty')
		{
		  $poverty++;
		  $name = DB_get_name_by_userid($user);
		  echo "$name has a Vorbehalt. <br />";
		}
	    }

	  /* check players for wedding */
	  $wedding = 0;
	  foreach($userids as $user)
	    {
	      if(DB_get_sickness_by_userid_and_gameid($user,$gameid) == 'wedding')
		{
		  $wedding=$user;
		  $name = DB_get_name_by_userid($user);
		  echo "$name has a Vorbehalt. <br />"  ;
		}
	    };

	  /* check for solo, output vorbehalt */
	  $solo = 0;
	  foreach($userids as $user)
	    {
	      if(DB_get_sickness_by_userid_and_gameid($user,$gameid) == 'solo')
		{
		  $solo++;
		  $name = DB_get_name_by_userid($user);
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
		  mymail($To,$EmailName."game canceled",$message);
		}
	      
	      /* delete everything from the dB */
	      DB_cancel_game($me);
	      
	      echo "The game has been canceled because ".DB_get_name_by_userid($nines).
		" has five or more nines and nobody is playing solo.\n";
	      output_footer();
	      exit();
	    }
	  else if($poverty==1 && $gametype !="poverty")
	    {
	      DB_set_gametype_by_gameid($gameid,"poverty");
	      $gametype = "poverty";
	    }
	  else if($poverty==2 && $gametype !="dpoverty")
	    {
	      DB_set_gametype_by_gameid($gameid,"dpoverty");
	      $gametype = "dpoverty";
	    }
	  else if($wedding> 0 && $gametype !="wedding")
	    {
	      DB_set_gametype_by_gameid($gameid,"wedding");
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
		echo "$name has $usersick <br />"; /*TODO: perhaps save this in a string and store in Game? */

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
	      
	      echo "Don't know who will be Re and Contra, you need to ".
		"figure that out at the end of the game yourself <br />\n";
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
	      /* use extra column in DB.Game to store whom to ask, 
	       * should be set for poverty and dpoverty(use two digits for dpoverty?) earlier*/

	      /* check if poverty resolved (e.g. DB.Game who set to NULL)
	       *   yes? =>trump was taken, start game; break; 
	       *   
	       * check if we are being asked now
	       *    no, display wait message, e.g. player X is asked at the moment
	       *    yes, display number of trump and user's hand, ask if he wants to take it 
	       *      no, set whom-to-ask to next player, email next player, cancle game if no next player
	       *      yes -> link to new page:display all cards, ask for N return cards
	       *          set re/contra 
	       *        
	       */
	    case "dpoverty":
	      echo "TODO: handle poverty here (almost done in my developing version)";
	      DB_set_hand_status_by_hash($me,'play');
	    };
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
	      $message = "It's your turn now.\n".
		"Use this link to play a card: ".$host."?me=".$hash."\n\n" ;
	      mymail($email,$EmailName."ready, set, go... ",$message);
	    }
	  else
	    echo " Please, <a href=\"$host?me=$me\">start</a> the game.<br />";	 
	}
      else
	echo "You finished the setup, once everyone else has done the same you'll get an email when it is your turn..<br />";	 

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
	  echo "You finished the setup, but not everyone else finished it...so you need to wait for the others... <br />";
	  break; /* not sure this works... the idea is that you can 
		  * only  play a card after everyone is ready to play */
	}
      
      /* display the table and the names */
      $result = mysql_query("SELECT  User.fullname as name,".
			    "        Hand.position as position, ".
			    "        User.id ".
			    "FROM Hand ".
			    "LEFT JOIN User ON User.id=Hand.user_id ".
			    "WHERE Hand.game_id='".$gameid."' ".
			    "ORDER BY position ASC");
      
      echo "<div class=\"table\">\n".
	"  <img src=\"pics/table.png\" alt=\"table\" />\n";
      while($r = mysql_fetch_array($result,MYSQL_NUM))
	{
	  $name = $r[0];
	  $pos  = $r[1];
	  $user = $r[2];

	  $offset = DB_get_user_timezone($user);
	  $zone   = return_timezone($offset);
	  date_default_timezone_set($zone);

	  echo " <span class=\"table".($pos-1)."\">\n";
	  echo " $name <br />\n";
	  echo " local time: ".date("Y-m-d H:i:s")."\n";
	  echo " </span>\n";

	}
      echo  "</div>\n";

      /* get everything relevant to display the tricks */
      $result = mysql_query("SELECT Hand_Card.card_id as card,".
			    "       Hand.position as position,".
			    "       Play.sequence as sequence, ".
			    "       Trick.id, ".
			    "       Comment.comment ".
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
	  
	  if($handcardid)
	    {
	      $comment = "";

	      /* mark card as played */
	      mysql_query("UPDATE Hand_Card SET played='true' WHERE hand_id='$handid' AND card_id=".
			  DB_quote_smart($card));
	      /* update Game timestamp */
	      DB_update_game_timestamp($gameid);

	      /* check for schweinchen */
	      //echo "schweinchen = ".$GAME["schweinchen"]." --$card-<br />";
	      if($card == 19 || $card == 20 )
		{
		  $GAME["schweinchen"]++;
		  if($GAME["schweinchen"]==3 && $RULES["schweinchen"]=="second" )
		    $comment="Schweinchen! ";
		  if($RULES["schweinchen"]=="both" )
		    $comment="Schweinchen! ";
		  echo "schweinchen = ".$GAME["schweinchen"]." ---<br />";
		}

	      /* get trick id or start new trick */
	      $a = DB_get_current_trickid($gameid);
	      $trickid  = $a[0];
	      $sequence = $a[1];
	      
	      $playid = DB_play_card($trickid,$handcardid,$sequence);
	      
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
	      display_card($card);
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

		  foreach($userids as $user)
		    {
		      $To = DB_get_email_by_userid($user);
		      mymail($To,$EmailName."game over",$message);
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
		  
		  $message = "It's your turn  now.\n".
		    "Use this link to play a card: ".$host."?me=".$next_hash."\n\n" ;
		  mymail($email,$EmailName."a card has been played",$message);
		  
		  if($debug)
		    echo "DEBUG:<a href=\"index.php?me=".DB_get_hash_from_game_and_pos($gameid,$next).
		      "\"> next player </a> <br />\n";
		  
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
	  echo "<form action=\"index.php?me=$me\" method=\"post\">\n";
	  
	  /* do we have to follow suite? */
	  $followsuit = 0;
	  if(have_suit($mycards,$firstcard))
	    $followsuit = 1;
	  
	  foreach($mycards as $card) 
	    {
	      if($followsuit && !same_type($card,$firstcard))
		display_card($card);
	      else
		display_link_card($card);
	    }
	  
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
      else if($mystatus=='gameover')
	{
	  $oldcards = DB_get_all_hand($me);
	  $oldcards = mysort($oldcards,$gametype);
	  echo "Your cards were: <br />\n";
	  foreach($oldcards as $card) 
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
	   $result = mysql_query("SELECT Hand.hash,Hand.game_id,Game.mod_date from Hand".
				 " LEFT JOIN Game On Hand.game_id=Game.id".
				 " WHERE Hand.user_id='$uid' AND Game.status<>'gameover'" );
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
     $pre=0;$game=0;$done=0;
     $r=mysql_query("SELECT COUNT(id) FROM Game GROUP BY status");
     if($r) {
       $pre = mysql_fetch_array($r,MYSQL_NUM);     
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


