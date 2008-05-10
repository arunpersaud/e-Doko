<?php
/* make sure that we are not called from outside the scripts, 
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

$name  = $_SESSION["name"];
$email = DB_get_email('name',$name);
$myid = DB_get_userid('email',$email);
if(!$myid)
  return;

/* track what got changed */
$changed_notify	  = 0;
$changed_password = 0;
$changed_cards	  = 0;
$changed_timezone = 0;

output_status();
display_user_menu();

/* get old infos */
$PREF = DB_get_PREF($myid);
$timezone =  DB_get_user_timezone($myid);

DB_update_user_timestamp($myid);

/* does the user want to change some preferences? */
if(myisset("timezone"))
  {
    $newtimezone=$_REQUEST['timezone'];
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

if(myisset("password0") &&  $_REQUEST["password0"]!="" )
  {
    $changed_password = 1;

    /* check if old password matches */
    $oldpasswd = md5($_REQUEST["password0"]);
    $password  = DB_get_passwd_by_userid($myid);
    if(!( ($password == $oldpasswd) || DB_check_recovery_passwords($oldpasswd,$email) ))
      $changed_password = -1;

    /* check if new passwords are types the same twice */
    if($_REQUEST["password1"] != $_REQUEST["password2"] )
      $changed_password = -2;
    
    if($changed_password==1)
      {
	DB_query("UPDATE User SET password='".md5($_REQUEST["password1"]).
		 "' WHERE id=".DB_quote_smart($myid));
      }
    /* error output below */
  }

/* get infos again in case they have changed */
$PREF     = DB_get_PREF($myid);
$timezone = DB_get_user_timezone($myid);

/* output settings */

echo "<div class=\"user\">\n";
echo "  <form action=\"index.php?action=prefs\" method=\"post\">\n";
echo "  <h2>Your settings are</h2>\n";
echo "    <table>\n";
echo "    <tr><td>Email:                 </td><td> $email    </td></tr>\n";
echo "    <tr><td>Timezone:              </td><td>";
output_select_timezone("timezone",$timezone);
if($changed_timezone) echo "changed";
echo "</td></tr>\n";
echo "    <tr><td>Notification:          </td><td>";

echo "  <select id=\"notify\" name=\"notify\" size=\"1\">\n";
      if($PREF['email']=="emailaddict")
	{
	  echo "   <option value=\"emailaddict\" selected=\"selected\">lots of emails</option>\n";
	  echo "   <option value=\"emailnonaddict\">less emails</option>\n";
	}
      else
	{
	  echo "   <option value=\"emailaddict\">lots of email</option>\n";
	  echo "   <option value=\"emailnonaddict\" selected=\"selected\">less email</option>\n";
	}
  echo "  </select>\n";
if($changed_notify) echo "changed";
echo " </td></tr>\n";    
echo "    <tr><td>Card set:              </td><td>";

echo "  <select id=\"cards\" name=\"cards\" size=\"1\">\n";
      if($PREF['cardset']=="altenburg")
	{
	  echo "   <option value=\"altenburg\" selected=\"selected\">German cards</option>\n";
	  echo "   <option value=\"english\">English cards</option>\n";
	}
      else
	{
	  echo "   <option value=\"altenburg\">German cards</option>\n";
	  echo "   <option value=\"english\" selected=\"selected\">English cards</option>\n";
	}
  echo "  </select>\n";
if($changed_cards) echo "changed";
echo " </td></tr>\n";    
echo "    <tr><td>Password(old):         </td><td>",
  "<input type=\"password\" id=\"password0\" name=\"password0\" size=\"20\" maxlength=\"30\" />";
switch($changed_password)
  {
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
echo  " </td></tr>\n";
echo "    <tr><td>Password(new):         </td><td>",
  "<input type=\"password\" id=\"password1\" name=\"password1\" size=\"20\" maxlength=\"30\" />",
  " </td></tr>\n";
echo "    <tr><td>Password(new, retype): </td><td>",
  "<input type=\"password\" id=\"password2\" name=\"password2\" size=\"20\" maxlength=\"30\" />",
  " </td></tr>\n";
echo "    <tr><td><input type=\"submit\" class=\"submitbutton\" name=\"passwd\" value=\"set\" /></td>",
  "<td></td></tr>\n";
echo "    </table>\n";
echo "  </form>\n";
echo "</div>\n";    

output_footer();
DB_close();
exit();

?>