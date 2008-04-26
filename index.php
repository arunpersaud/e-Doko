<?php
error_reporting(E_ALL);

include_once("config.php");                /* needs to be first in list, since other includes use this */
include_once("./include/output.php");      /* html output only */
include_once("./include/db.php");          /* database only */
include_once("./include/functions.php");   /* the rest */

/* make sure that user has set all variables in config.php */
config_check();

/* open the database */
if(DB_open()<0)
  {
    output_header();
    echo "Database error, can't connect... Please wait a while and try again. ".
      "If the problem doesn't go away feel free to contact $ADMIN_NAME at $ADMIN_EMAIL.";
    output_footer();
    exit();
  }

/* start a session, if it is not already running.
 * This way people don't have to log in all the times. 
 * The session variables can also be read out from different
 * php scripts, so that the code can be easily split up across several files
 */
session_start();

/* done major error checking, output header of HTML page */
output_header();

/* The rest of the file consists of handling user input.
 * The user sends information via html GET and POST variables,
 * the script checks if these are set via "myisset"
 * which can check a list of variables.
 */

/* does the user want to log out? */
if(myisset("logout"))
  {
    require './include/logout.php';
  }
/* check if we want to start a new game */
else if(myisset("new"))
  {
    require './include/newgame.php';
  }
/*check if everything is ready to set up a new game */
else if( myisset("PlayerA", "PlayerB","PlayerC","PlayerD","dullen","schweinchen","callrule" ))
  {
    require './include/newgameready.php';
  }    
/* cancel a game, if nothing has happend in the last N minutes */
else if(myisset("cancel","me"))
  {
    require './include/cancelgame.php';
  }
/* send out a reminder */
else if(myisset("remind","me"))
  {
    require './include/reminder.php';
  }
/* handle request from one specific player for one game,
 * (the hash is set on a per game base) */
else if(myisset("me"))
  {
    require './include/game.php';
 }
/* user status page */
else if( myisset("email","password") || isset($_SESSION["name"]) )
   {
     require './include/user.php';
   }
/* default login page */
 else
   {
     require './include/welcome.php';
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


