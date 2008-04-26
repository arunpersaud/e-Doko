<?php
/* make sure that we are not called from outside the scripts, 
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

output_status();

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

DB_update_user_timestamp($myid);

/* get some information from the DB */
$gameid   = DB_get_gameid_by_hash($me);
$myname   = DB_get_name('hash',$me);

/* check if game really is old enough to be canceled */
$result = mysql_query("SELECT mod_date from Game WHERE id='$gameid' " );
$r = mysql_fetch_array($result,MYSQL_NUM);
if(time()-strtotime($r[0]) > 60*60*24*30) /* = 1 month */
  {
    $message = "Hello, \n\n".
      "Game ".DB_format_gameid($gameid).
      " has been canceled since nothing happend for a while and $myname requested it.\n";

    $userids = DB_get_all_userid_by_gameid($gameid);
    foreach($userids as $user)
      {
	$To = DB_get_email('userid',$user);
	mymail($To,$EmailName."game ".DB_format_gameid($gameid)." canceled (timed out)",$message);
      }

    /* delete everything from the dB */
    DB_cancel_game($me);

    echo "<p style=\"background-color:red\";>Game ".DB_format_gameid($gameid).
      " has been canceled.<br /><br /></p>";
  }
 else
   echo "<p>You need to wait longer before you can cancel a game...</p>\n";
?>