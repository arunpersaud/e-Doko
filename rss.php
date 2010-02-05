<?php
  /* get some information from the database */
error_reporting(E_ALL);

/* start a session, if it is not already running.
 * This way people don't have to log in all the times.
 * The session variables can also be read out from different
 * php scripts, so that the code can be easily split up across several files
 */

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

$ok = 0;
$id = 0;

if(!myisset("uid","token"))
  {
    echo "error";
  }
else
  {
    $id = $_REQUEST['uid'];
    $token = get_user_token($id);

    if($token == $_REQUEST['token'])
      $ok = 1;
  }

if(! $ok)
  {
    echo "No valid userid";
    exit();
  }

header("Content-Type: text/xml");
/* start the feed */
?>
<feed xmlns="http://www.w3.org/2005/Atom">
<title>E-DoKo Feed</title>
<subtitle>Know when it is your turn</subtitle>
<?php

  global $WIKI,$INDEX, $HOST;

  /* output last creation date */
echo "<link href=\"$HOST/$INDEX\" />\n";
echo "<link rel=\"self\" href=\"".$HOST.htmlentities($_SERVER['REQUEST_URI'])."\" />\n";
$date = DB_query_array("Select create_date from User order by create_date ASC limit 1");
$date = $date[0];
$timestamp = strtotime($date);
echo "<id>tag:".$_SERVER['SERVER_NAME'].",".date("Y-m-d",$timestamp).":$INDEX</id>\n";
echo "<updated>".date(DATE_ATOM)."</updated>\n";
echo "<author>\n";
echo "<name>$ADMIN_NAME  $date $timestamp</name>\n";
echo "<email>$ADMIN_EMAIL</email>\n";
echo "</author>\n\n";


 /* output the entries */


  $result = DB_query("SELECT Hand.hash,Hand.game_id,Game.player from Hand".
		     " LEFT JOIN Game On Hand.game_id=Game.id".
		     " WHERE Hand.user_id='$id'".
		     " AND ( Game.player='$id' OR ISNULL(Game.player) )".
		     " AND ( Game.status='pre' OR Game.status='play' )".
		     " ORDER BY Game.session" );

  while( $r = DB_fetch_array($result))
    {
      echo "<entry>\n";
      echo "<title>game ".DB_format_gameid($r[1])."</title>\n";
      $url=$INDEX."?action=game&amp;me=".$r[0];
      echo "<link href=\"".$HOST.$url."\" />\n";
      $date = DB_get_game_timestamp($r[1]);
      $timestamp = strtotime($date);
      $date = date("Y-m-d",$timestamp);
      echo "<id>tag:doko.nubati.net,$date:$url</id>\n";
      echo "<updated>".date(DATE_ATOM,$timestamp)."</updated>\n";
      echo "<summary>Please use the link to access the game.</summary>\n";
      echo "</entry>\n\n";
    }

?>
</feed>