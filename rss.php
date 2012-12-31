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
<?php

/* set language */
set_language($id,'uid');

echo '<subtitle>'._('Know when it is your turn')."</subtitle>\n";

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
      echo "<title>"._('game').' '.DB_format_gameid($r[1])."</title>\n";
      $url=$INDEX."?action=game&amp;me=".$r[0];
      echo "<link href=\"".$HOST.$url."\" />\n";
      $date = DB_get_game_timestamp($r[1]);
      $timestamp = strtotime($date);
      $date = date("Y-m-d",$timestamp);
      echo "<id>tag:doko.nubati.net,$date:$url</id>\n";
      echo "<updated>".date(DATE_ATOM,$timestamp)."</updated>\n";
      echo '<summary>'._('Please use the link to access the game.')."</summary>\n";
      echo "</entry>\n\n";
    }

?>
</feed>