<?php
/* make sure that we are not called from outside the scripts, 
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

output_status();
/* user needs to be logged in to do this */
if(! isset($_SESSION["name"]) )
  {
    echo "<div class=\"message\">Please <a href=\"$INDEX\">log in</a>.</div>";
  }
else
  {
    if( myisset("PlayerA", "PlayerB","PlayerC","PlayerD","dullen","schweinchen","callrule" ))
      {
	/* get my name */
	$name = $_SESSION["name"];
	
	/* the names of the four players */
	$PlayerA = $_REQUEST["PlayerA"];
	$PlayerB = $_REQUEST["PlayerB"];
	$PlayerC = $_REQUEST["PlayerC"];
	$PlayerD = $_REQUEST["PlayerD"];
	
	/* the person who sets up the game has to be one of the players */
	if(!in_array($name,array($PlayerA,$PlayerB,$PlayerC,$PlayerD)))
	  {
	    echo "<div class=\"message\">You need to be one of the players to start a <a href=\"$INDEX?action=new\">new game</a>.</div>";
	    output_footer();
	    DB_close();
	    exit();
	  }
	
	/* what rules were selected */
	$dullen      = $_REQUEST["dullen"];
	$schweinchen = $_REQUEST["schweinchen"];
	$call        = $_REQUEST["callrule"];
	
	/* get the emails addresses of the players */
	$EmailA  = DB_get_email('name',$PlayerA);
	$EmailB  = DB_get_email('name',$PlayerB);
	$EmailC  = DB_get_email('name',$PlayerC);
	$EmailD  = DB_get_email('name',$PlayerD);
	
	/* this is used to check if the player names are all ok */
	if($EmailA=="" || $EmailB=="" || $EmailC=="" || $EmailD=="")
	  {
	    echo "couldn't find one of the names, please start a new game";
	    output_footer();
	    DB_close();
	    exit();
	  }
	
	/* get user ids */
	$useridA  = DB_get_userid('name',$PlayerA);
	$useridB  = DB_get_userid('name',$PlayerB);
	$useridC  = DB_get_userid('name',$PlayerC);
	$useridD  = DB_get_userid('name',$PlayerD);
	
	/* create random numbers */
	$randomNR       = create_array_of_random_numbers($useridA,$useridB,$useridC,$useridD);
	$randomNRstring = join(":",$randomNR);
	
	/* create game */
	$followup = NULL;
	/* is this game a follow up in an already started session? */
	if(myisset("followup") )
	  {
	    $followup= $_REQUEST["followup"];
	    $session = DB_get_session_by_gameid($followup);
	    $ruleset = DB_get_ruleset_by_gameid($followup); /* just copy ruleset from old game,
							     this way no manipulation is possible */
	    
	    /* check if there is a game in pre or play mode, in that case do nothing */
	    if( DB_is_session_active($session) > 0 )
	      {
		echo "<p class=\"message\"> There is already a game going on in session $session, you can't start a new one</p>";
		output_footer();
		DB_close();
		exit();
	      }
	    else if ( DB_is_session_active($session) < 0 )
	      {
		echo "<p class=\"message\"> ERROR: status of session $session couldn't be determined.</p>";
		output_footer();
		DB_close();
		exit();
	      }
	    
	    if($session)
	      DB_query("INSERT INTO Game VALUES (NULL, NULL, '$randomNRstring', 'normal', NULL,NULL,'1',NULL,'pre',".
		       "'$ruleset','$session' ,NULL)");
	    else
	      {
		/* get max session and start a new one */
		$max = DB_get_max_session();
		$max++;
		DB_query("UPDATE Game SET session='".$max."' WHERE id=".DB_quote_smart($followup));
		DB_query("INSERT INTO Game VALUES (NULL, NULL, '$randomNRstring', 'normal', NULL,NULL,'1',NULL,'pre',".
			    "'$ruleset','$max' ,NULL)");
	      }
	  }
	else /* no follow up, start a new session */
	  {
	    /* get ruleset information or create new one */
	    $ruleset = DB_get_ruleset($dullen,$schweinchen,$call);
	    if($ruleset <0)
	      {
		myerror("Error defining ruleset: $ruleset");
		output_footer();
		DB_close();
		exit();
	      };
	    /* get max session */
	    $max = DB_get_max_session();
	    $max++;
	    
	    DB_query("INSERT INTO Game VALUES (NULL, NULL, '$randomNRstring', 'normal', NULL,NULL,'1',NULL,'pre', ".
		     "'$ruleset','$max' ,NULL)");
	  }
	$game_id = DB_insert_id();
	
	/* create hash */
	$TIME  = (string) time(); /* to avoid collisions */
	$hashA = md5("AGameOfDoko".$game_id.$PlayerA.$EmailA.$TIME);
	$hashB = md5("AGameOfDoko".$game_id.$PlayerB.$EmailB.$TIME);
	$hashC = md5("AGameOfDoko".$game_id.$PlayerC.$EmailC.$TIME);
	$hashD = md5("AGameOfDoko".$game_id.$PlayerD.$EmailD.$TIME);
	
	/* create hands */
	DB_query("INSERT INTO Hand VALUES (NULL,".DB_quote_smart($game_id).",".DB_quote_smart($useridA).
		 ", ".DB_quote_smart($hashA).", 'start','1',NULL,NULL,NULL,NULL)");
	$hand_idA = DB_insert_id();
	DB_query("INSERT INTO Hand VALUES (NULL,".DB_quote_smart($game_id).",".DB_quote_smart($useridB).
		 ", ".DB_quote_smart($hashB).", 'start','2',NULL,NULL,NULL,NULL)");
	$hand_idB = DB_insert_id();
	DB_query("INSERT INTO Hand VALUES (NULL,".DB_quote_smart($game_id).",".DB_quote_smart($useridC).
		 ", ".DB_quote_smart($hashC).", 'start','3',NULL,NULL,NULL,NULL)");
	$hand_idC = DB_insert_id();
	DB_query("INSERT INTO Hand VALUES (NULL,".DB_quote_smart($game_id).",".DB_quote_smart($useridD).
		 ", ".DB_quote_smart($hashD).", 'start','4',NULL,NULL,NULL,NULL)");
	$hand_idD = DB_insert_id();
	
	/* save cards */
	for($i=0;$i<12;$i++)
	  DB_query("INSERT INTO Hand_Card VALUES (NULL, '$hand_idA', '".$randomNR[$i]."', 'false')");
	for($i=12;$i<24;$i++)
	  DB_query("INSERT INTO Hand_Card VALUES (NULL, '$hand_idB', '".$randomNR[$i]."', 'false')");
	for($i=24;$i<36;$i++)
	  DB_query("INSERT INTO Hand_Card VALUES (NULL, '$hand_idC', '".$randomNR[$i]."', 'false')");
	for($i=36;$i<48;$i++)
	  DB_query("INSERT INTO Hand_Card VALUES (NULL, '$hand_idD', '".$randomNR[$i]."', 'false')");
	
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
	  "".$HOST.$INDEX."?action=game&me=";
	
	mymail($EmailA,"You are invited to a game of DoKo","Hello $PlayerA,\n".$message.$hashA);
	mymail($EmailB,"You are invited to a game of DoKo","Hello $PlayerB,\n".$message.$hashB);
	mymail($EmailC,"You are invited to a game of DoKo","Hello $PlayerC,\n".$message.$hashC);
	mymail($EmailD,"You are invited to a game of DoKo","Hello $PlayerD,\n".$message.$hashD);
	
	echo "<div class=\"message\">You started a new game. The emails have been sent out!</div>\n";
      }
    else
      {
	$names = DB_get_all_names();
	echo "<div class=\"user\">\n";
	output_form_for_new_game($names);
	echo "</div>\n";
	display_user_menu();
      }
  }

?>
