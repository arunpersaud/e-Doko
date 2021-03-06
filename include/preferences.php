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
$changed_digest       = 0;
$changed_language     = 0;

display_user_menu($myid);

/* get old infos */
$PREF = DB_get_PREF($myid);
/* set language chosen in preferences, will become active on the next reload (see index.php)*/
$_SESSION['language'] = $PREF['language'];
set_language($PREF['language']);
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
    if($_REQUEST['vacation_start'] == $_REQUEST['vacation_stop'])
      {
	$result = DB_query("DELETE FROM User_Prefs".
			   " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='vacation start'" );
	$result = DB_query("DELETE FROM User_Prefs".
			   " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='vacation stop'" );
	$result = DB_query("DELETE FROM User_Prefs".
			   " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='vacation comment'" );
	$changed_vacation = 1;
      }
    /* change in database if format is ok */
    else if($changed_vacation>=0)
      {
	/* only change if different from current value */
	if($vacation_start!=$PREF['vacation_start'])
	  {
	    $result = DB_query("SELECT * from User_Prefs".
			       " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='vacation start'" );
	    if( DB_fetch_array($result))
	      $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($vacation_start).
				 " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='vacation start'" );
	    else
	      $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,".DB_quote_smart($myid).",'vacation start',".
				 DB_quote_smart($vacation_start).")");

	    $changed_vacation = 1;
	  }

	/* same for the stop date */
	if($vacation_stop!=$PREF['vacation_stop'])
	  {
	    $result = DB_query("SELECT * from User_Prefs".
			       " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='vacation stop'" );
	    if( DB_fetch_array($result))
	      $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($vacation_stop).
				 " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='vacation stop'" );
	    else
	      $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,".DB_quote_smart($myid).",'vacation stop',".
				 DB_quote_smart($vacation_stop).")");

	    $changed_vacation = 1;
	  }

	/* does the user want to add a comment? */
	if($vacation_comment!=$PREF['vacation_comment'])
	  {
	    $result = DB_query("SELECT * from User_Prefs".
			       " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='vacation comment'" );
	    if( DB_fetch_array($result))
	      $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($vacation_comment).
				 " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='vacation comment'" );
	    else
	      $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,".DB_quote_smart($myid).",'vacation comment',".
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
			   " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='cardset'" );
	if( DB_fetch_array($result))
	  $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($cards).
			     " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='cardset'" );
	else
	  $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,".DB_quote_smart($myid).",'cardset',".
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
			   " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='email'" );
	if( DB_fetch_array($result))
	  $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($notify).
			     " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='email'" );
	else
	  $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,".DB_quote_smart($myid).",'email',".
			     DB_quote_smart($notify).")");
	$changed_notify=1;
      }
  }

if(myisset("digest"))
  {
    $digest=$_REQUEST['digest'];
    if($digest != $PREF['digest'])
      {
	/* check if we already have an entry for the user, if so change it, if not create new one */
	$result = DB_query("SELECT * from User_Prefs".
			   " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='digest'" );
	if( DB_fetch_array($result))
	  $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($digest).
			     " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='digest'" );
	else
	  $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,".DB_quote_smart($myid).",'digest',".
			     DB_quote_smart($digest).")");
	$changed_digest=1;
      }
  }

if(myisset("autosetup"))
  {
    $autosetup = $_REQUEST['autosetup'];
    if($autosetup != $PREF['autosetup'])
      {
	/* check if we already have an entry for the user, if so change it, if not create new one */
	$result = DB_query("SELECT * from User_Prefs".
			   " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='autosetup'" );
	if( DB_fetch_array($result))
	  $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($autosetup).
			     " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='autosetup'" );
	else
	  $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,".DB_quote_smart($myid).",'autosetup',".
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
			   " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='sorting'" );
	if( DB_fetch_array($result))
	  $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($sorting).
			     " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='sorting'" );
	else
	  $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,".DB_quote_smart($myid).",'sorting',".
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
			   " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='open for games'" );
	if( DB_fetch_array($result))
	  $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($openforgames).
			     " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='open for games'" );
	else
	  $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,".DB_quote_smart($myid).",'open for games',".
			     DB_quote_smart($openforgames).")");
	$changed_openforgames=1;
      }
  }


if(myisset("password0","password1","password2") &&  $_REQUEST["password0"]!="" &&  $_REQUEST["password0"]!= $_REQUEST["password1"])
  {
    $changed_password = 1;

    /* check if old password matches */
    $result = verify_password($email, $_REQUEST["password0"]);

    if( $result!=0 )
      $changed_password = -1;

    /* check if new password has been typed in correctly */
    if($_REQUEST["password1"] != $_REQUEST["password2"] )
      $changed_password = -2;

    /* check if new password is long enough */
    if(strlen($_REQUEST["password1"])<4)
      $changed_password = -3;

    if($changed_password==1)
      {
	// create a password hash using the crypt function, need php 5.3 for this
	// create and random salt
	$salt = substr(str_replace('+', '.', base64_encode(sha1(microtime(true), true))), 0, 22);
	// hash incoming password using 12 rounds of blowfish
	$hash = crypt($_REQUEST["password1"], '$2y$12$' . $salt);

	DB_query("UPDATE User SET password='".$hash.
		 "' WHERE id=".DB_quote_smart($myid));

	/* in case this was done using a recovery password delete that password */
	$tmppasswd = md5($_REQUEST["password0"]);
	if(DB_check_recovery_passwords($tmppasswd,$email))
	  DB_delete_recovery_passwords($myid);
      }
    /* error output below */
  }

if(myisset("openid_url") && $_REQUEST['openid_url']!='')
  {
    $openid_url = OpenIDUrlEncode($_REQUEST['openid_url']);
    DB_AttachOpenID($openid_url, $myid);
  }

if(myisset("language"))
  {
    $language = $_REQUEST['language'];
    if($language != $PREF['language'])
      {
	/* check if we already have an entry for the user, if so change it, if not create new one */
	$result = DB_query("SELECT * from User_Prefs".
			   " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='language'" );
	if( DB_fetch_array($result))
	  $result = DB_query("UPDATE User_Prefs SET value=".DB_quote_smart($language).
			     " WHERE user_id=".DB_quote_smart($myid)." AND pref_key='language'" );
	else
	  $result = DB_query("INSERT INTO User_Prefs VALUES(NULL,".DB_quote_smart($myid).",'language',".
			     DB_quote_smart($language).")");
	$changed_language = 1;
      }
  }


/* get infos again in case they have changed */
$PREF     = DB_get_PREF($myid);
$timezone = DB_get_user_timezone($myid);

/*
 * output settings
 */

echo "<div class=\"user\">\n";
echo "  <form action=\"index.php?action=prefs\" method=\"post\">\n";
echo '  <h2>'._('Your settings')."</h2>\n";
echo "    <fieldset>\n";
echo '    <legend>'._('Game-related')."</legend>\n";
echo "      <table>\n";

echo '        <tr><td>'._('Vacation').":             </td>\n";
if($PREF['vacation_start'])
  $value = substr($PREF['vacation_start'],0,10);
 else
   $value = '';
echo "            <td>"._('start').":<input type=\"date\" class=\"date\" name=\"vacation_start\" value=\"$value\" /></td>\n";
if($PREF['vacation_stop'])
  $value = substr($PREF['vacation_stop'],0,10);
 else
   $value = '';
echo "            <td>"._('stop').":<input type=\"date\" class=\"date\" name=\"vacation_stop\" value=\"$value\" /></td>\n";
if($PREF['vacation_comment'])
  $value = $PREF['vacation_comment'];
else
  $value = '';
echo '            <td>'._('comment:')."<input type=\"text\" id=\"vacation_comment\" name=\"vacation_comment\" size=\"10\" maxlength=\"50\" value=\"$value\" />";
if($changed_vacation == 1) echo _('changed');
if($changed_vacation == -1) echo _('wrong date format');
echo "</td></tr>\n";
echo '<tr><td></td><td colspan="2">'._("set both dates to the same day to end vacation")."</td></tr>\n";
echo '        <tr><td>'._('Notification').":          </td><td>\n";
echo "          <select id=\"notify\" name=\"notify\" size=\"1\">\n";
if($PREF['email']=="emailaddict")
  {
    echo "            <option value=\"emailaddict\" selected=\"selected\">"._('less emails')."</option>\n";
    echo "            <option value=\"emailnonaddict\">"._('lots of emails')."</option>\n";
  }
else
  {
    echo "            <option value=\"emailaddict\">"._('less emails')."</option>\n";
    echo "            <option value=\"emailnonaddict\" selected=\"selected\">"._('lots of emails')."</option>\n";
  }
echo "          </select>";
if($changed_notify) echo _('changed');
echo " </td></tr>\n";

echo '        <tr><td>'._('Digest').":          </td><td>\n";
echo "          <select id=\"digest\" name=\"digest\" size=\"1\">\n";

$selected = "selected=\"selected\"";
echo "            <option value=\"digest-off\"";
if($PREF['digest']=="digest-off") echo $selected;
echo '>'._('digest off')."</option>\n";

echo "            <option value=\"digest-1h\" ";
if($PREF['digest']=="digest-1h") echo $selected;
echo ">"._('every hour')."</option>\n";

echo "            <option value=\"digest-2h\" ";
if($PREF['digest']=="digest-2h") echo $selected;
echo ">"._('every 2h')."</option>\n";

echo "            <option value=\"digest-3h\" ";
if($PREF['digest']=="digest-3h") echo $selected;
echo ">"._('every 3h')."</option>\n";

echo "            <option value=\"digest-4h\" ";
if($PREF['digest']=="digest-4h") echo $selected;
echo ">"._('every 4h')."</option>\n";

echo "            <option value=\"digest-6h\" ";
if($PREF['digest']=="digest-6h") echo $selected;
echo ">"._('every 6h')."</option>\n";

echo "            <option value=\"digest-12h\"";
if($PREF['digest']=="digest-12h") echo $selected;
echo ">"._('every 12h')."</option>\n";

echo "            <option value=\"digest-24h\"";
if($PREF['digest']=="digest-24h") echo $selected;
echo ">"._('every 24h')."</option>\n";

echo "          </select>";
if($changed_digest) echo _('changed');
echo " </td></tr>\n";


echo '        <tr><td>'._('Autosetup').":          </td><td>\n";
echo "          <select id=\"autosetup\" name=\"autosetup\" size=\"1\">\n";
if($PREF['autosetup']=="yes")
  {
    echo "           <option value=\"yes\" selected=\"selected\">"._('accept every game')."</option>\n";
    echo "           <option value=\"no\">"._('ask for games')."</option>\n";
  }
 else
   {
     echo "           <option value=\"yes\">"._('accept every game')."</option>\n";
     echo "           <option value=\"no\" selected=\"selected\">"._('ask for games')."</option>\n";
   }
echo "         </select>";
if($changed_autosetup) echo _('changed');
echo " </td></tr>\n";
echo '    <tr><td>'._('Sorting').":          </td><td>\n";

echo "         <select id=\"sorting\" name=\"sorting\" size=\"1\">\n";
if($PREF['sorting']=="high-low")
  {
    echo "           <option value=\"high-low\" selected=\"selected\">"._('high to low')."</option>\n";
    echo "           <option value=\"low-high\">"._('low to high')."</option>\n";
  }
 else
   {
     echo "           <option value=\"high-low\">"._('high to low')."</option>\n";
     echo "           <option value=\"low-high\" selected=\"selected\">"._('low to high')."</option>\n";
   }
echo "         </select>";
if($changed_sorting) echo _('changed');
echo " </td></tr>\n";
echo '        <tr><td>'._('Open for new games').":          </td><td>\n";
echo "         <select id=\"open_for_games\" name=\"open_for_games\" size=\"1\">\n";
if($PREF['open_for_games']=="no")
  {
    echo '           <option value="yes">'._('yes')."</option>\n";
    echo '           <option value="no" selected="selected">'._('no')."</option>\n";
  }
 else /* default */
   {
     echo '           <option value="yes" selected="selected">'._('yes')."</option>\n";
     echo '           <option value="no">'._('no')."</option>\n";
   }
echo "         </select>";
if($changed_openforgames) echo _('changed');
echo " </td></tr>\n";

echo '    <tr><td>'.('Card set').":              </td><td>\n";
echo "         <select id=\"cards\" name=\"cards\" size=\"1\">\n";
if($PREF['cardset']=="english2")
  {
    echo "           <option value=\"english\" >"._('English cards')."</option>\n";
    echo "           <option value=\"english2\" selected=\"selected\">"._('English cards 2')."</option>\n";
  }
 else /* default */
   {
     echo "           <option value=\"english\" selected=\"selected\">"._('English cards')."</option>\n";
     echo "           <option value=\"english2\" >"._('English cards 2')."</option>\n";
   };
echo "         </select>";
if($changed_cards) echo _('changed');
echo " </td></tr>\n";
echo "      </table>\n";
echo "    </fieldset>\n";
echo "    <fieldset>\n";
echo '      <legend>'._('Personal')."</legend>\n";
echo "      <table>\n";
echo '        <tr><td>'._('Email').":                 </td><td> $email    </td></tr>\n";
echo '        <tr><td>'._('Timezone').":              </td><td>\n";
output_select_timezone("timezone",$timezone);
if($changed_timezone) echo _('changed');
echo "</td></tr>\n";
echo '        <tr><td>'._('Language').":              </td><td>\n";
output_select_language("language",$PREF['language']);
if($changed_language == 1) echo _('changed');
echo "</td></tr>\n";
echo '        <tr><td>'._('Password(old)').":         </td><td>",
  "<input type=\"password\" id=\"password0\" name=\"password0\" size=\"20\" maxlength=\"30\" />";
switch($changed_password)
  {
  case '-3':
    echo _('The new passwords is not long enough (you need at least 4 characters).');
    break;
  case '-2':
    echo _('The new passwords don\'t match.');
    break;
  case '-1':
    echo _('The old password is not correct.');
    break;
  case '1':
    echo _('changed');
    break;
  }
echo " </td></tr>\n";
echo '        <tr><td>'._('Password(new)').":         </td><td>",
  "<input type=\"password\" id=\"password1\" name=\"password1\" size=\"20\" maxlength=\"30\" />",
  " </td></tr>\n";
echo '        <tr><td>'._('Password(new, retype)').": </td><td>",
  "<input type=\"password\" id=\"password2\" name=\"password2\" size=\"20\" maxlength=\"30\" />",
  " </td></tr>\n";
echo "      </table>\n";
echo "    </fieldset>\n";
echo "    <fieldset>\n";
echo '      <legend>'._('OpenID')."</legend>\n";

$openids = array();
$openids = DB_GetOpenIDsByUser($myid);

if(sizeof($openids))
  {
    echo "     <table class=\"openid\">\n";
    echo '     <thead><tr><th>'._('Delete')."?</th><th>OpenId</th></tr></thead>\n";
    echo "     <tbody>\n";
    foreach ($openids as $ids)
      {
	$id=($ids[0]);
	echo "        <tr><td><input type=\"checkbox\" name=\"delete-openid-$id\" /></td><td>",$id, "</td></tr>\n";
      }
    echo "     </tbody>\n";
    echo "     </table>\n";
  }

echo '        '._('add OpenID').': ',
  "<input type=\"text\" id=\"openid_url\" name=\"openid_url\" size=\"20\" maxlength=\"50\" />";
if($changed_openid)
  echo '   '._('Deleted some OpenIDs!')." <br />\n";
echo "    </fieldset>\n";
echo '    <fieldset><legend>'._('Submit')."</legend><input type=\"submit\"  name=\"passwd\" value=\"set\" /></fieldset>\n";
echo "  </form>\n";
echo ' <p>'._('E-DoKo uses <a href="http://www.gravatar.org">gravatars</a> as icons.').'</p>';
echo "</div>\n";

// add jquery date picker if html5 is not available
?>
<script>
  $(".date").dateinput({  format: 'yyyy-mm-dd'  });
</script>
<?php


return;
?>