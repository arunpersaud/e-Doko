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

error_reporting(E_ALL);

include_once("config.php");                /* needs to be first in list, since other includes use this */
include_once("./include/output.php");      /* html output only */
include_once("./include/db.php");          /* database only */
include_once("./include/functions.php");   /* the rest */

/* make sure that user has set all variables in config.php */
config_check();

/* open the database */
if(DB_open()<0)
  exit();

/* only callable via cron or CLI */
if(isset($_SERVER['REMOTE_ADDR']))
  exit();

/* get userid for users that have digest set != digest-off */
$users = DB_get_digest_users();

global $WIKI;

global $defaulttimezone;
foreach($users as $uid)
  {
    // get local time
    $zone  = DB_get_user_timezone($uid);
    date_default_timezone_set($zone);
    $time = (int)(date("H"));

    // load users preferences
    $PREF = DB_get_PREF($uid);

    // set users language preference
    $language = $PREF['language'];

    switch($language)
      {
      case 'de':
	putenv("LC_ALL=de_DE");
	setlocale(LC_ALL, "de_DE");
	// Specify location of translation tables
	bindtextdomain("edoko", "./locale");
	// Choose domain
	textdomain("edoko");
	break;
      default:
	/* do nothing */
      }

    // calculate mod by digest-time
    switch($PREF['digest'])
      {
      case 'digest-off':
	break;
      case 'digest-1h':
	$every = 1;
	break;
      case 'digest-2h':
	$every = 2;
	break;
      case 'digest-3h':
	$every = 3;
	break;
      case 'digest-4h':
	$every = 4;
	break;
      case 'digest-6h':
	$every = 6;
	break;
      case 'digest-12h':
	$every = 12;
	break;
      case 'digest-24h':
	$every = 24;
	break;
      }

    // make default time to send email 18:00
    if( ( ($time-18) % $every) == 0 )
      {
	$email = DB_get_email('userid',$uid);

	// get messages
	$messages = DB_get_digest_message_by_email($email);

	// check messages for outdated ones and delete those
	foreach ($messages as $key=>$mess)
	  {
	    if($mess[2] == 'your_turn' && $uid != DB_get_player_by_gameid($mess[3]) )
	      {
		DB_digest_delete_message($mess[0]);
		unset($messages[$key]);
	      }
	  }

	// add them together
	if(sizeof($messages))
	  {
	    $text = array();
	    $i=0;
	    foreach($messages as $mess )
	      {
		$text[$i] = $mess[1]."\n++++++++++++++++++++++++++++++++++++++++++++++++++++++\n";
		$i++;
	      }

	    $text = implode("\n",$text);

	    // add header, footer, sent out
	    $name    = DB_get_name('userid',$uid);
	    $header  = "Hello $name\n\nHere is a digest of the latest emails:\n\n";

	    $footer  = "\nHave a nice day\n".
	      "   your E-Doko digester\n\n".
	      "-- \n".
	      "You can change your mail delivery mode in the preference menu.\n".
	      'web: http://doko.nubati.net   '.
	      "help, bugs, etc.: $WIKI";

	    $subject = "$EmailName Digest";

	    sendmail($email,$subject,$header.$text.$footer);
	  }

	// delete all messages
	foreach($messages as $mess )
	  DB_digest_delete_message($mess[0]);
      }
  } /* end foreach users */
?>