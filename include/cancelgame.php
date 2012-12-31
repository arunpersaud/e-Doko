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

/* you should only get here from a game page, so $me should be set */
if(!myisset("me"))
  {
    echo "Hmm, you really shouldn't mess with the urls.<br />\n";
    return;
  }

$me = $_REQUEST["me"];

/* test for valid ID */
$myid = DB_get_userid('hash',$me);
if(!$myid)
  {
    echo "Can't find you in the database, please check the url.<br />\n";
    echo "perhaps the game has been canceled, check by login in <a href=\"$INDEX\">here</a>.";
    return;
  }

DB_update_user_timestamp($myid);

/* get some information from the DB */
$gameid   = DB_get_gameid_by_hash($me);
$myname   = DB_get_name('hash',$me);

/* check if game really is old enough to be canceled */
$r = DB_query_array("SELECT mod_date from Game WHERE id='$gameid' " );
if(time()-strtotime($r[0]) > 60*60*24*30) /* = 1 month */
  {
    /* email to all players */
    $userids = DB_get_all_userid_by_gameid($gameid);
    foreach($userids as $user)
      {
        set_language($user, 'uid');
	$message = sprintf(_('Game %s has been canceled since nothing happend for a while and %s requested it.'),DB_format_gameid($gameid),$myname)."\n\n";

	mymail($user,$gameid, GAME_CANCELED_TIMEOUT, $message);
      }
    set_language($myid, 'uid');

    /* set gamestatus to canceled */
    cancel_game('timedout',$gameid);

    echo '<p style="background-color:red";>'.
      sprintf(_('Game %s has been canceled.'),DB_format_gameid($gameid)).
      "<br /><br /></p>\n";
  }
 else /* game can't be canceled yet */
   echo "<p>"._('You need to wait longer before you can cancel a game...')."</p>\n";
?>