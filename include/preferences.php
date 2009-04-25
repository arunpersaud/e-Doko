<?php
/* make sure that we are not called from outside the scripts,
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

include_once('openid.php');

$name  = $_SESSION["name"];
$email = DB_get_email('name',$name);
$myid  = DB_get_userid('email',$email);
if(!$myid)
  return;

/* track what got changed */
$changed_notify	      = 0;
$changed_password     = 0;
$changed_cards	      = 0;
$changed_timezone     = 0;
$changed_autosetup    = 0;
$changed_sorting      = 0;
$changed_openforgames = 0;
$changed_vacation     = 0;
$changed_openid       = 0;

display_user_menu($myid);

/* get old infos */
$PREF = DB_get_PREF($myid);
$timezone =  DB_get_user_timezone($myid);

DB_update_user_timestamp($myid);

/* does the user want to change some preferences?
 * update the database and track changes with a variable, so that
 * we can later highlight the changed value
 */

/* check for deleted openids */
foreach($_REQUEST as $key=>$value)
{
  if(strstr($key,"delete-openid-"))
    {
      /* found and openid to delete */
      $DelOpenID = substr(str_replace("_",".",$key),14);
      DB_DetachOpenID($DelOpenID, $myid);
      $changed_openid = 1;
    }
}


if(myisset('vacation_start','vacation_stop','vacation_comment') &&
   ($_REQUEST['vacation_start']!='' || $_REQUEST['vacation_stop']!='')
   )
  {
    $vacation_start   = $_REQUEST['vacation_start'].' 00:00:00';
    $vacation_stop    = $_REQUEST['vacation_stop'].' 23:59:59';
    $vacation_comment = $_REQUEST['vacation_comment'];

    /* check if everything is valid */
    if(!strtotime($vacation_start))
      $changed_vacation = -1;
    if(!strtotime($vacation_stop))
      $changed_vacation = -1;

    /* test if we should delete the entry */
    if($vacation_start == '- 00:00:00')
      {
	$result = DB_query("DELETE FROM User_Prefs".
			   " WHERE user_id='$myid' AND pref_key='vacation start'" );
	$result = DB_query("DELETE FROM User_Prefs".
			   " WHERE user_id='$myid' AND pref_key='vacation stop'" );
	$result = DB_query("DELETE FROM User_Prefs".
			   " WHERE user_id='$myid' AND pref_key='vacation comment'" );
	$changed_vacation = 1;
      }
    /* change in database if format is ok */
    else if($changed_vacation>=0)
      {
	/* only change if different from current value */
	if($vacation_start!=$PREF['vacation_start'])
	  {
	    $result = DB_query("SELECT * from User_Prefs".
			       " WHERE user_id='$myid' AND pref_key='vacation start'" );
	    if( DB_fetch_array($result))
	      $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($vacation_start).
				 " WHERE user_id='$myid' AND pref_key='vacation start'" );
	    else
	      $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,'$myid','vacation start',".
				 DB_quote_smart($vacation_start).")");

	    $changed_vacation = 1;
	  }

	/* same for the stop date */
	if($vacation_stop!=$PREF['vacation_stop'])
	  {
	    $result = DB_query("SELECT * from User_Prefs".
			       " WHERE user_id='$myid' AND pref_key='vacation stop'" );
	    if( DB_fetch_array($result))
	      $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($vacation_stop).
				 " WHERE user_id='$myid' AND pref_key='vacation stop'" );
	    else
	      $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,'$myid','vacation stop',".
				 DB_quote_smart($vacation_stop).")");

	    $changed_vacation = 1;
	  }

	/* does the user want to add a comment? */
	if($vacation_comment!=$PREF['vacation_comment'])
	  {
	    $result = DB_query("SELECT * from User_Prefs".
			       " WHERE user_id='$myid' AND pref_key='vacation comment'" );
	    if( DB_fetch_array($result))
	      $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($vacation_comment).
				 " WHERE user_id='$myid' AND pref_key='vacation comment'" );
	    else
	      $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,'$myid','vacation comment',".
				 DB_quote_smart($vacation_comment).")");

	    $changed_vacation = 1;
	  }
      }
  }

if(myisset("timezone"))
  {
    $newtimezone = $_REQUEST['timezone'];
    if($newtimezone != $timezone)
      {
	DB_query("UPDATE User SET timezone=".DB_quote_smart($newtimezone).
		 " WHERE id=".DB_quote_smart($myid));
	$changed_timezone = 1;
      }
  }

if(myisset("cards"))
  {
    $cards=$_REQUEST['cards'];
    if($cards != $PREF['cardset'])
      {
	/* check if we already have an entry for the user, if so change it, if not create new one */
	$result = DB_query("SELECT * from User_Prefs".
			   " WHERE user_id='$myid' AND pref_key='cardset'" );
	if( DB_fetch_array($result))
	  $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($cards).
			     " WHERE user_id='$myid' AND pref_key='cardset'" );
	else
	  $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,'$myid','cardset',".
			     DB_quote_smart($cards).")");
	$changed_cards = 1;
      }
  }

if(myisset("notify"))
  {
    $notify=$_REQUEST['notify'];
    if($notify != $PREF['email'])
      {
	/* check if we already have an entry for the user, if so change it, if not create new one */
	$result = DB_query("SELECT * from User_Prefs".
			   " WHERE user_id='$myid' AND pref_key='email'" );
	if( DB_fetch_array($result))
	  $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($notify).
			     " WHERE user_id='$myid' AND pref_key='email'" );
	else
	  $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,'$myid','email',".
			     DB_quote_smart($notify).")");
	$changed_notify=1;
      }
  }

if(myisset("autosetup"))
  {
    $autosetup = $_REQUEST['autosetup'];
    if($autosetup != $PREF['autosetup'])
      {
	/* check if we already have an entry for the user, if so change it, if not create new one */
	$result = DB_query("SELECT * from User_Prefs".
			   " WHERE user_id='$myid' AND pref_key='autosetup'" );
	if( DB_fetch_array($result))
	  $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($autosetup).
			     " WHERE user_id='$myid' AND pref_key='autosetup'" );
	else
	  $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,'$myid','autosetup',".
			     DB_quote_smart($autosetup).")");
	$changed_autosetup=1;
      }
  }

if(myisset("sorting"))
  {
    $sorting = $_REQUEST['sorting'];
    if($sorting != $PREF['sorting'])
      {
	/* check if we already have an entry for the user, if so change it, if not create new one */
	$result = DB_query("SELECT * from User_Prefs".
			   " WHERE user_id='$myid' AND pref_key='sorting'" );
	if( DB_fetch_array($result))
	  $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($sorting).
			     " WHERE user_id='$myid' AND pref_key='sorting'" );
	else
	  $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,'$myid','sorting',".
			     DB_quote_smart($sorting).")");
	$changed_sorting=1;
      }
  }

if(myisset("open_for_games"))
  {
    $openforgames = $_REQUEST['open_for_games'];
    if($openforgames != $PREF['open_for_games'])
      {
	/* check if we already have an entry for the user, if so change it, if not create new one */
	$result = DB_query("SELECT * from User_Prefs".
			   " WHERE user_id='$myid' AND pref_key='open for games'" );
	if( DB_fetch_array($result))
	  $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($openforgames).
			     " WHERE user_id='$myid' AND pref_key='open for games'" );
	else
	  $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,'$myid','open for games',".
			     DB_quote_smart($openforgames).")");
	$changed_openforgames=1;
      }
  }


if(myisset("password0") &&  $_REQUEST["password0"]!="" )
  {
    $changed_password = 1;

    /* check if old password matches */
    $oldpasswd = md5($_REQUEST["password0"]);
    $password  = DB_get_passwd_by_userid($myid);
    if(!( ($password == $oldpasswd) || DB_check_recovery_passwords($oldpasswd,$email) ))
      $changed_password = -1;

    /* check if new password has been typed in correctly */
    if($_REQUEST["password1"] != $_REQUEST["password2"] )
      $changed_password = -2;

    /* check if new password is long enough */
    if(strlen($_REQUEST["password1"])<4)
      $changed_password = -3;

    if($changed_password==1)
      {
	DB_query("UPDATE User SET password='".md5($_REQUEST["password1"]).
		 "' WHERE id=".DB_quote_smart($myid));
      }
    /* error output below */
  }

if(myisset("openid_url") && $_REQUEST['openid_url']!='')
  {
    $openid_url = OpenIDUrlEncode($_REQUEST['openid_url']);
    DB_AttachOpenID($openid_url, $myid);
  }

/* get infos again in case they have changed */
$PREF     = DB_get_PREF($myid);
$timezone = DB_get_user_timezone($myid);

/*
 * output settings
 */

echo "<div class=\"user\">\n";
echo "  <form action=\"index.php?action=prefs\" method=\"post\">\n";
echo "  <h2>Your settings are</h2>\n";
echo "    <fieldset>\n";
echo "    <legend>Game-related</legend>\n";
echo "      <table>\n";

echo "        <tr><td>Vacation:             </td>\n";
if($PREF['vacation_start'])
  $value = substr($PREF['vacation_start'],0,10);
 else
   $value = '';
echo "            <td>start:<input type=\"text\" id=\"vacation_start\" name=\"vacation_start\" size=\"10\" maxlength=\"10\" value=\"$value\" /></td>\n";
if($PREF['vacation_stop'])
  $value = substr($PREF['vacation_stop'],0,10);
 else
   $value = '';
echo "            <td>stop:<input type=\"text\" id=\"vacation_stop\" name=\"vacation_stop\" size=\"10\" maxlength=\"10\" value=\"$value\" /></td>\n";
if($PREF['vacation_comment'])
  $value = $PREF['vacation_comment'];
else
  $value = '';
echo "            <td>comment:<input type=\"text\" id=\"vacation_comment\" name=\"vacation_comment\" size=\"10\" maxlength=\"50\" value=\"$value\" />";
if($changed_vacation == 1) echo "changed";
if($changed_vacation == -1) echo "wrong date format";
echo "</td></tr>\n";
echo "<tr><td></td><td>use YYYY-MM-DD</td><td>use '-'  in start field to unset vacation</td></tr>\n";
echo "        <tr><td>Notification:          </td><td>\n";
echo "          <select id=\"notify\" name=\"notify\" size=\"1\">\n";
if($PREF['email']=="emailaddict")
  {
    echo "            <option value=\"emailaddict\" selected=\"selected\">less emails</option>\n";
    echo "            <option value=\"emailnonaddict\">lots of emails</option>\n";
  }
 else
   {
     echo "            <option value=\"emailaddict\">less email</option>\n";
     echo "            <option value=\"emailnonaddict\" selected=\"selected\">lots of email</option>\n";
   }
echo "          </select>";
if($changed_notify) echo "changed";
echo " </td></tr>\n";

echo "        <tr><td>Autosetup:          </td><td>\n";
echo "          <select id=\"autosetup\" name=\"autosetup\" size=\"1\">\n";
if($PREF['autosetup']=="yes")
  {
    echo "           <option value=\"yes\" selected=\"selected\">accept every game</option>\n";
    echo "           <option value=\"no\">ask for games</option>\n";
  }
 else
   {
     echo "           <option value=\"yes\">accept every game</option>\n";
     echo "           <option value=\"no\" selected=\"selected\">ask for games</option>\n";
   }
echo "         </select>";
if($changed_autosetup) echo "changed";
echo " </td></tr>\n";
echo "    <tr><td>Sorting:          </td><td>\n";

echo "         <select id=\"sorting\" name=\"sorting\" size=\"1\">\n";
if($PREF['sorting']=="high-low")
  {
    echo "           <option value=\"high-low\" selected=\"selected\">high to low</option>\n";
    echo "           <option value=\"low-high\">low to high</option>\n";
  }
 else
   {
     echo "           <option value=\"high-low\">high to low</option>\n";
     echo "           <option value=\"low-high\" selected=\"selected\">low to high</option>\n";
   }
echo "         </select>";
if($changed_sorting) echo "changed";
echo " </td></tr>\n";
echo "        <tr><td>Open for new games:          </td><td>\n";
echo "         <select id=\"open_for_games\" name=\"open_for_games\" size=\"1\">\n";
if($PREF['open_for_games']=="no")
  {
    echo "           <option value=\"yes\">yes</option>\n";
    echo "           <option value=\"no\" selected=\"selected\">no</option>\n";
  }
 else /* default */
   {
     echo "           <option value=\"yes\" selected=\"selected\">yes</option>\n";
     echo "           <option value=\"no\">no</option>\n";
   }
echo "         </select>";
if($changed_openforgames) echo "changed";
echo " </td></tr>\n";

echo "    <tr><td>Card set:              </td><td>\n";
echo "         <select id=\"cards\" name=\"cards\" size=\"1\">\n";
if($PREF['cardset']=="altenburg")
  {
    echo "           <option value=\"altenburg\" selected=\"selected\">German cards</option>\n";
    echo "           <option value=\"english\">English cards</option>\n";
  }
 else
   {
     echo "           <option value=\"altenburg\">German cards</option>\n";
     echo "           <option value=\"english\" selected=\"selected\">English cards</option>\n";
   }
echo "         </select>";
if($changed_cards) echo "changed";
echo " </td></tr>\n";
echo "      </table>\n";
echo "    </fieldset>\n";
echo "    <fieldset>\n";
echo "      <legend>Personal</legend>\n";
echo "      <table>\n";
echo "        <tr><td>Email:                 </td><td> $email    </td></tr>\n";
echo "        <tr><td>Timezone:              </td><td>\n";
output_select_timezone("timezone",$timezone);
if($changed_timezone) echo "changed";
echo "</td></tr>\n";

echo "        <tr><td>Password(old):         </td><td>",
  "<input type=\"password\" id=\"password0\" name=\"password0\" size=\"20\" maxlength=\"30\" />";
switch($changed_password)
  {
  case '-3':
    echo "The new passwords is not long enough (you need at least 4 characters).";
    break;
  case '-2':
    echo "The new passwords don't match.";
    break;
  case '-1':
    echo "The old password is not correct.";
    break;
  case '1':
    echo "changed";
    break;
  }
echo " </td></tr>\n";
echo "        <tr><td>Password(new):         </td><td>",
  "<input type=\"password\" id=\"password1\" name=\"password1\" size=\"20\" maxlength=\"30\" />",
  " </td></tr>\n";
echo "        <tr><td>Password(new, retype): </td><td>",
  "<input type=\"password\" id=\"password2\" name=\"password2\" size=\"20\" maxlength=\"30\" />",
  " </td></tr>\n";
echo "      </table>\n";
echo "    </fieldset>\n";
echo "    <fieldset>\n";
echo "      <legend>OpenID</legend>\n";

$openids = array();
$openids = DB_GetOpenIDsByUser($myid);

if(sizeof($openids))
  {
    echo "     <table class=\"openid\">\n";
    echo "     <thead><tr><th>Delete?</th><th>OpenId</th></tr></thead>\n";
    echo "     <tbody>\n";
    foreach ($openids as $ids)
      {
	$id=($ids[0]);
	echo "        <tr><td><input type=\"checkbox\" name=\"delete-openid-$id\" /></td><td>",$id, "</td></tr>\n";
      }
    echo "     </tbody>\n";
    echo "     </table>\n";
  }

echo "        add OpenID: ",
  "<input type=\"text\" id=\"openid_url\" name=\"openid_url\" size=\"20\" maxlength=\"50\" />";
if($changed_openid)
  echo "   Deleted some OpenIDs! <br />\n";
echo "    </fieldset>\n";
echo "    <fieldset><legend>Submit</legend><input type=\"submit\"  name=\"passwd\" value=\"set\" /></fieldset>\n";
echo "  </form>\n";
echo "</div>\n";

return;
?>