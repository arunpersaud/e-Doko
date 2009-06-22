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
  exit();

/* only callable via cron or CLI */
if(isset($_SERVER['REMOTE_ADDR']))
  exit();

/* get userid for users that have digest set != digest-off */
$users = DB_get_digest_users();

global $defaulttimezone;
foreach($users as $uid)
  {
    // get local time

    $zone  = DB_get_user_timezone($uid);
    date_default_timezone_set($zone);
    $time = (int)(date("H"));

    // calculate mod by digest-time
    $PREF = DB_get_PREF($uid);
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
	      'help: http://wiki.nubati.net/EmailDoko   '.
	      'bugs: http://wiki.nubati.net/EmailDokoIssues';

	    $subject = "$EmailName Digest";

	    sendmail($email,$subject,$header.$text.$footer);
	  }

	// delete all messages
	foreach($messages as $mess )
	  DB_digest_delete_message($mess[0]);
      }
  } /* end foreach users */
?>