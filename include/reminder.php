<?php
/* Copyright 2006, 2007, 2008, 2009, 2010, 2011, 2012, 2013, 2014, 2016 Arun Persaud <arun@nubati.net>
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
set_language($myid,'uid');

/* get some information from the DB */
$gameid   = DB_get_gameid_by_hash($me);
$myname   = DB_get_name('hash',$me);

/* check if player hasn't done anything in a while */
$r = DB_query_array("SELECT mod_date,player,status from Game WHERE id='$gameid' " );
if( (time()-strtotime($r[0]) > 60*60*24*7)  && ($r[2]!='gameover') ) /* = 1 week */
  {
    $uid  = $r[1];
    $name = DB_get_name('userid',$uid);
    $userhash = DB_get_hash_from_gameid_and_userid($gameid,$uid);

    /* make sure we don't send too many reminders to one person */
    if(DB_get_reminder($uid,$gameid)>0)
      {
	echo '<p>'._('An email has already been sent out.')."</p>\n";
      }
    else
      {
        set_language($uid,'uid');
        $message = _("It's your turn in game ").DB_format_gameid($gameid)." \n".
	  _("Actually everyone else is waiting for you for more than a week now ;)")."\n\n".
	  _("Please visit this link now to continue").": \n".
	  " ".$HOST.$INDEX."?action=game&me=".$userhash."\n\n" ;

	DB_set_reminder($uid,$gameid);
	mymail($r[1],$gameid, GAME_REMINDER, $message);
        set_language($myid,'uid');

	echo '<p>'._('Game')." ".DB_format_gameid($gameid).': '.
	  _('an email has been sent out.')."<br /><br /></p>\n";
      }
  }
 else
   echo '<p>'._('You need to wait longer before you can send out a reminder...')."</p>\n";
?>