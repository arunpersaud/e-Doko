<?php
/* Copyright 2006, 2007, 2008, 2009, 2010 Arun Persaud <arun@nubati.net>
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

/* functions which only ouput html  */

function output_ask_for_new_game($playerA,$playerB,$playerC,$playerD,$oldgameid)
{
  global $RULES;

  echo "<div class=\"message\">\n<form action=\"index.php?action=new\" method=\"post\">\n";
  $output = sprintf(_('Do you want to continue playing? (This will start a new game, with %s starting the game.)'),$playerA);
  echo $output."\n";
  echo "  <input type=\"hidden\" name=\"PlayerA\" value=\"$playerA\" />\n";
  echo "  <input type=\"hidden\" name=\"PlayerB\" value=\"$playerB\" />\n";
  echo "  <input type=\"hidden\" name=\"PlayerC\" value=\"$playerC\" />\n";
  echo "  <input type=\"hidden\" name=\"PlayerD\" value=\"$playerD\" />\n";
  echo "  <input type=\"hidden\" name=\"dullen\"  value=\"".$RULES['dullen']."\" />\n";
  echo "  <input type=\"hidden\" name=\"schweinchen\" value=\"".$RULES['schweinchen']."\" />\n";
  echo "  <input type=\"hidden\" name=\"callrule\" value=\"".$RULES['call']."\" />\n";
  echo "  <input type=\"hidden\" name=\"lowtrump\" value=\"".$RULES['lowtrump']."\" />\n";
  echo "  <input type=\"hidden\" name=\"followup\" value=\"$oldgameid\" />\n";
  echo "  <input type=\"submit\" value=\""._('keep playing')."\" />\n";
  echo "</form>\n</div>";
  return;
}

function output_form_for_new_game($names)
{
  $copy_names = $names; /* local copy, so that we can delete names from it
			 * after we selected them to make sure that each name
			 * only shows up once
			 */

  /* delete players name, since he will be on position D anyway */
  unset($copy_names[array_search($_SESSION["name"],$copy_names)]);
  srand((float) microtime() * 10000000);


  echo '  <form action="index.php?action=new" method="post">';
  echo '    <h2> '._('Select players (Remember: you need to be one of the players)').' </h2>';

  echo '   <div class="table">';

  echo  "     <div class=\"table1\">\n";
  $randkey = array_rand($copy_names);
  $rand = $copy_names[$randkey];
  /* delete this name from the list of possible names */
  unset($copy_names[$randkey]);
  echo "       <select name=\"PlayerB\" size=\"1\">  \n";
  foreach($names as $name)
    {
      if($name==$rand)
	echo "         <option selected=\"selected\">$name</option>\n";
      else
	echo "         <option>$name</option>\n";
    }
  echo "       </select>\n     </div>\n";

  echo '   <div class="middle">';

  $randkey = array_rand($copy_names);
  $rand = $copy_names[$randkey];
  /* delete this name from the list of possible names */
  unset($copy_names[$randkey]);
  echo  "     <div class=\"table0\">\n";
  echo "       <select name=\"PlayerA\" size=\"1\">  \n";
  foreach($names as $name)
    {
      if($name==$rand)
	echo "         <option selected=\"selected\">$name</option>\n";
      else
	echo "         <option>$name</option>\n";
    }
  echo "       </select>\n     </div>\n";

  echo '     <img class="table" src="pics/table.png" alt="table" />';
  $randkey = array_rand($copy_names);
  $rand = $copy_names[$randkey];
  /* delete this name from the list of possible names */
  unset($copy_names[$randkey]);
  echo  "     <div class=\"table2\">\n";
  echo "       <select name=\"PlayerC\" size=\"1\">  \n";
  foreach($names as $name)
    {
      if($name==$rand)
	echo "         <option selected=\"selected\">$name</option>\n";
      else
	echo "         <option>$name</option>\n";
    }
  echo "       </select>\n     </div>\n";

  echo '   </div>';
  $rand = $_SESSION["name"];
  echo  "     <div class=\"table3\">\n";
  $i++;
  echo "       <select name=\"PlayerD\" size=\"1\">  \n";
  foreach($names as $name)
    {
      if($name==$rand)
	echo "         <option selected=\"selected\">$name</option>\n";
      else
	echo "         <option>$name</option>\n";
    }
  echo "       </select>\n     </div>\n";

  /* ask player for names */

    echo '    </div>';
    echo '';
    echo '     <h2 class="rules">'._('Rules').'</h2>';
    echo '     <h3>'._('Gameplay-related').'</h3>';
    echo '     <h4>'._('Ten of hearts').':</h4>';
    echo '     <p>';
    echo '       <select name="dullen">';
    echo '         <option value="none"> '._('just normal non-trump').'  </option>';
    echo '         <option value="firstwins"> '._('first ten of hearts wins the trick').' </option>';
    echo '         <option value="secondwins" selected="selected"> '.('second ten of hearts wins the trick').' </option>';
    echo '       </select>';
    echo '     </p>';
    echo '     <h4>'._('Schweinchen (both foxes), only in normal games or silent solos').':</h4>';
    echo '     <p>';
    echo '       <select name="schweinchen">';
    echo '         <option value="none" selected="selected"> '._('none').' </option>';
    echo '	 <option value="both"> '._('both become highest trump (automatic call at beginning of the game)').'   </option>';
    echo '	 <option value="second"> '._('first one normal, second one becomes highest (call during the game)').' </option>';
    echo '	 <option value="secondaftercall">  ',_('second one become highest only in case re/contra was announced');
    echo '	 </option>';
    echo '       </select>';
    echo '     </p>';
    echo '     <h4>'._('Call Re/Contra, etc.').':</h4>';
    echo '     <p>';
    echo '       <select name="callrule">';
    echo '	 <option value="1st-own-card" selected="selected">  '._('Can call re/contra on the first <strong>own</strong> card played, 90 on the second, etc.').'</option>';
    echo '	 <option value="5th-card"> '._('Can call re/contra until 5th card is played, 90 until 9th card is played, etc.').'</option>';
    echo '	 <option value="9-cards" > '._('Can call re/contra until 5th card is played, 90 if player still has 9 cards, etc.').'</option>';
    echo '       </select>';
    echo '     </p>';
    echo '     <h4>'._('Low trump').'</h4>';
    echo '     <p>';
    echo '       '._('Player can\'t trump a fox, that is none of his trump is higher than a fox.');
    echo '       <select name="lowtrump">';
    echo '	 <option value="poverty">'._('The trump will be treated as poverty and offered to another player.').'</option>';
    echo '	 <option value="cancel"> '._('The game will be canceled unless there is a solo.').'</option>';
    echo '	 <option value="none">   '._('Bad luck, the player needs to play a normal game.').'</option>';
    echo '       </select>';
    echo '     </p>';
    echo '     <h3>'._('Scoring-related').'</h3>';
    echo '     <h4>'._('(not yet implemented)').'</h4>';
    echo '     <p><input type="submit" value="'._('start game').'"></p>';
    echo '     </form>';

}

function output_table($data,$caption="",$class="",$id="")
{

  $HTML  = "\n<table";

  if($class!="")
    $HTML.= " class=\"$class\"";
  if($id!="")
    $HTML.= " id=\"$id\"";

  $HTML.=">\n";

  $i=0;

  if($caption!="")
    $HTML .= "  <caption> $caption </caption>\n";

  foreach($data as $record)
    {
      if(!$i)
	$HTML .= "  <thead>\n  <tr>\n";
      else
	{
	  if($i==1) $HTML .= "  <tbody>\n";
	  $HTML .= "  <tr>  ";
	}
      foreach($record as $point)
	{
	  if($i)
	    $HTML .= "    <td>$point</td> ";
	  else
	    $HTML .= "    <th>$point</th> ";
	}

      if(!$i)
	$HTML .= "  </tr>\n  </thead>\n";
      else
	{
	  $HTML .= "  </tr>\n";
	}
      $i++;
    }
  $HTML .= "  </tbody>\n</table>\n";

  return $HTML;
}

function display_card($card,$dir="english")
{
  /* cards are only availabl for the odd values, e.g. 1.png, 3.png, ...
   * convert even cards to the matching odd value */

  if( $card/2 - (int)($card/2) == 0.5 || $card == 0)
    echo "<img src=\"cards/".$dir."/".$card.".png\"  alt=\"".DB_get_card_name($card)."\" />\n";
  else
    echo "<img src=\"cards/".$dir."/".($card-1).".png\"  alt=\"".DB_get_card_name($card-1)."\" />\n";

  return;
}

function display_link_card($card,$dir="english",$type="card", $selected=0)
{
  if($selected)
    $selected = 'checked="checked"';

  if( $card/2 - (int)($card/2) == 0.5)
    echo "<label class=\"cardinput\"><input type=\"radio\" name=\"".$type."\" value=\"".$card."\" $selected /><img src=\"cards/".$dir."/".$card.".png\" alt=\"".DB_get_card_name($card)."\" /></label>\n";
  else
    echo "<label class=\"cardinput\"><input type=\"radio\" name=\"".$type."\" value=\"".$card."\" $selected /><img src=\"cards/".$dir."/".($card-1).".png\" alt=\"".DB_get_card_name($card-1)."\" /></label>\n";
  return;
}

function output_check_for_sickness($me,$mycards)
{
  global $RULES;

  echo '  <div class="sickness"> '._('Thanks for joining the game').'...<br />';
  echo '';
  echo '    '._('Do you want to play solo?').'';
  echo '    <select name="solo" size="1">';
  echo '      <option value="No" selected="selected">'.'No'.'</option>';
  echo '      <option value="trumpless">'._('trumpless').'</option>';
  echo '      <option value="trump">'._('trump').'</option>';
  echo '      <option value="queen">'._('queen').'</option>';
  echo '      <option value="jack">'._('jack').'</option>';
  echo '      <option value="club">'._('club').'</option>';
  echo '      <option valvue="spade">'._('spade').'</option>';
  echo '      <option value="hear">'._('heart').'</option>';
  echo '    </select>';
  echo '    <br />';

  echo _('Wedding?');
  if(check_wedding($mycards))
     {
       echo ' '._('yes')."<input type=\"radio\" name=\"wedding\" value=\"yes\" checked=\"checked\" />";
       echo ' '._('no')." <input type=\"radio\" name=\"wedding\" value=\"no\" /> <br />\n";
     }
   else
     {
       echo ' '._('no')." <input type=\"hidden\" name=\"wedding\" value=\"no\" /> <br />\n";
     };

  echo _('Do you have poverty?');
  if(count_trump($mycards)<4)
    {
      echo ' '._('yes')."<input type=\"radio\" name=\"poverty\" value=\"yes\" checked=\"checked\" />";
      echo ' '._('no')." <input type=\"radio\" name=\"poverty\" value=\"no\" /> <br />\n";
    }
  else
    {
      echo ' '._('no')." <input type=\"hidden\" name=\"poverty\" value=\"no\" /> <br />\n";
    };

  echo _('Do you have too many nines?');
  if(count_nines($mycards)>4)
     {
       echo ' '._('yes')."<input type=\"radio\" name=\"nines\" value=\"yes\" checked=\"checked\" />";
       echo ' '._('no')." <input type=\"radio\" name=\"nines\" value=\"no\" /> <br />\n";
     }
   else
     {
       echo ' '._('no')." <input type=\"hidden\" name=\"nines\" value=\"no\" /> <br />\n";
     };

  if($RULES['lowtrump']=='cancel' || $RULES['lowtrump']=='poverty')
    {
      if($RULES['lowtrump']=='cancel')
	echo _('Do you have low trump (cancel game)?');
      else
	echo _('Do you have low trump (poverty)?');

      if(check_low_trump($mycards))
	{
	  echo ' '._('yes')."<input type=\"radio\" name=\"lowtrump\" value=\"yes\" checked=\"checked\" />";
	  echo ' '._('no')." <input type=\"radio\" name=\"lowtrump\" value=\"no\" /> <br />\n";
	}
      else
	{
	  echo ' '._('no')." <input type=\"hidden\" name=\"lowtrump\" value=\"no\" /> <br />\n";
	};
    }
  else
    echo "<input type=\"hidden\" name=\"lowtrump\" value=\"no\" />";

   echo "<input type=\"hidden\" name=\"me\" value=\"$me\" />\n";
   echo "<input type=\"submit\" value=\""._('count me in')."\" />\n";

   echo "</div>\n";

  return;
}

function output_form_calls($me,$myparty)
{
  $highstart = '  <span class="highcall">';
  $highend   = '</span>';

  $tmp = can_call(120,$me);
  if( $tmp )
    {
      if($tmp==2) echo $highstart;
      if($myparty=='re')
	echo '  re (120):';
      else if ($myparty=='contra')
	echo '  contra (120):';
      else
	echo '  re/contra (120):';
      echo ' <input type="radio" name="call" value="120" />';
      if($tmp==2) echo $highend;
      echo "\n";
    }
  $tmp = can_call(90,$me);
  if( $tmp )
    {
      if($tmp==2) echo $highstart;
      echo '  90:'.
	' <input type="radio" name="call" value="90" />';
      if($tmp==2) echo $highend;
      echo "\n";
    }
  $tmp = can_call(60,$me);
  if( $tmp )
    {
      if($tmp==2) echo $highstart;
      echo '  60:'.
	' <input type="radio" name="call" value="60" />';
      if($tmp==2) echo $highend;
      echo "\n";
    }
  $tmp = can_call(30,$me);
  if( $tmp )
    {
      if($tmp==2) echo $highstart;
      echo '  30:'.
	' <input type="radio" name="call" value="30" />';
      if($tmp==2) echo $highend;
      echo "\n";
    }
  $tmp = can_call(0,$me);
  if( $tmp )
    {
      if($tmp==2) echo $highstart;
      echo '  0:'.
	' <input type="radio" name="call" value="0" />';
      if($tmp==2) echo $highend;
      echo "\n".
	'  no call:'.
	' <input type="radio" name="call" value="no" />'."\n";
    }
}

function output_check_want_to_play($me)
{
  echo ' <div class="joingame">';
  echo '   '._('Do you want to play a game of DoKo?').' <br />';
  echo '   '._('yes').'<input type="radio" name="in" value="yes" />';
  echo '   '._('no').'<input type="radio" name="in" value="no" /> <br />';
  echo "<input type=\"hidden\" name=\"me\" value=\"$me\" />\n";
  echo "\n";
  echo "<input type=\"submit\" value=\""._('submit')."\" />\n";
  echo " </div>\n";

  return;
}

function output_header()
{
   global $REV;
?>
<!DOCTYPE html>
<html lang="en">
  <head>
     <title>e-Doko</title>
     <meta charset="utf-8" />
     <meta name="viewport" content="width=device-width; initial-scale=1.0;" />
     <link rel="shortcut icon" href="pics/edoko-favicon.png" />
     <link rel="stylesheet" href="css/normalize.css?v=1" />
     <link rel="stylesheet" href="css/standard.css?v=40" />
     <link rel="stylesheet" href="css/dateinput.css?v=1"/>
     <script type="text/javascript" src="include/jquery.js"> </script>
     <script type="text/javascript" src="include/jquery.tablesorter.js"></script>
     <script type="text/javascript" src="include/jquery.tools.min.js"></script>
     <script type="text/javascript" src="include/game.js"> </script>
     <script type="text/javascript">
     </script>
  </head>
<body onload="high_last();">
<header>
<?php
  echo '<h1> '._('Welcome to E-Doko').' </h1>';
?>
</header>
<?php

  echo "<div class=\"main\">";
  return;
}

function output_footer()
{
  global $REV, $PREF, $INDEX;

  echo "</div>\n\n";
  echo "<footer>\n";
  echo "  <p class=\"left\"> copyright 2006-2011 <a href=\"$INDEX?action=about\">Arun Persaud, et al.</a> <br />\n".
    "  Verwendung der [deutschen] Kartenbilder mit Genehmigung <br />der Spielkartenfabrik Altenburg GmbH,(c) ASS Altenburger <br />\n".
    "  - ASS Altenburger Spielkarten - Spielkartenfabrik Altenburg GmbH <br />\n".
    "  a Carta Mundi Company Email: info@spielkarten.com Internet: www.spielkarten.com</p>\n";
  echo " <p class=\"right\"> See the latest changes <a href=\"http://nubati.net/cgi-bin/gitweb.cgi?p=e-DoKo.git;a=summary\">\n".
    "  via git </a> <br />or download the source via <br />\n'git clone http://nubati.net/git/e-DoKo.git' <br />\n".
    "  <a href=\"http://www.dreamhost.com/green.cgi\">\n".
    "  <img alt=\"Green Web Hosting! This site hosted by DreamHost.\"".
    " src=\"pics/green1.gif\" height=\"32\" width=\"100\" /></a>\n".
    "  </p> \n";
  echo "</footer>\n\n";
  echo "</body>\n";
  echo "</html>\n";

  return;
}

function output_status()
{
  global $defaulttimezone, $INDEX, $WIKI, $RSS;

  if(isset($_SESSION['name']))
    {
      $name = $_SESSION['name'];

      /* last logon time */
      $myid  = DB_get_userid('name',$name);
      $zone  = DB_get_user_timezone($myid);

      $time     = DB_get_user_timestamp($myid);
      date_default_timezone_set($defaulttimezone);
      $unixtime = strtotime($time);
      date_default_timezone_set($zone);

      /* rss token */
      $token = get_user_token($myid);

      /* logout info */
      echo "\n<div class=\"status\">\n";
      echo $name,"\n";
      echo " | <a href=\"".$INDEX."\">"._('mypage')."</a>\n";
      echo " | <a href=\"".$INDEX."?action=prefs\">"._('settings')."</a>\n";
      echo " | <a href=\"".$INDEX."?action=new\">"._('new game')."</a>\n";
      echo " | <a href=\"".$INDEX."?action=stats\">"._('statistics')."</a>\n";
      echo " | <a href=\"".$WIKI."\">"._('wiki/bugs')."</a>\n";
      echo " | <a href=\"".$RSS."?uid=".$myid."&amp;token=".$token."\">"._('atom')."</a>\n";
      echo " |&nbsp;&nbsp;&nbsp; <a href=\"".$INDEX."?action=logout\">"._('logout')."</a>\n";
      echo "</div>\n\n";

      echo "<div class=\"lastlogin\"><span>"._('last login').": ".date("r",$unixtime)."</span></div>\n\n";
    }
  return;
}

function output_select_timezone($name,$timezone="")
{
  $Tzone = array ("Pacific/Apia"         => "Apia",                /*UTC-11*/
                  "Pacific/Honolulu"     => "Honolulu",            /*UTC-10*/
                  "America/Anchorage"    => "Anchorage",           /*UTC-9*/
                  "America/Vancouver"    => "Berkeley",            /*UTC-8*/
                  "America/Phoenix"      => "Phoenix",             /*UTC-7*/
                  "America/Chicago"      => "Chicago",             /*UTC-6*/
                  "America/New_York"     => "New York",            /*UTC-5*/
                  "America/Santiago"     => "Santiago",            /*UTC-4*/
                  "America/Buenos_Aires" => "Buenos Aires",        /*UTC-3*/
                  "Atlantic/South_Georgia" => "Fernando de Noronha", /*UTC-2*/
                  "Atlantic/Azores"       => "Azores",             /*UTC-1"*/
                  "Europe/London"         => "London",             /*UTC*/
                  "Europe/Berlin"         => "Berlin",             /*UTC+1*/
                  "Africa/Cairo"          => "Cairo",              /*UTC+2*/
                  "Europe/Moscow"         => "Moscow",             /*UTC+3*/
                  "Asia/Tehran"           => "Tehran",             /*UTC+3:30*/
                  "Asia/Dubai"            => "Dubai",              /*UTC+4*/
                  "Asia/Karachi"          => "Karachi",            /*UTC+5*/
                  "Asia/Calcutta"         => "Delhi",              /*UTC+5:30*/
                  "Asia/Kathmandu"        => "Kathmandu",          /*UTC+5:45*/
                  "Asia/Dhaka"            => "Dhaka",              /*UTC+6*/
                  "Asia/Rangoon"          => "Yangon",             /*UTC+6:30*/
                  "Asia/Bangkok"          => "Bangkok",            /*UTC+7*/
                  "Asia/Hong_Kong"        => "Beijing",            /*UTC+8*/
                  "Asia/Tokyo"            => "Tokyo",              /*UTC+9*/
                  "Australia/Darwin"      => "Darwin",             /*UTC+9:30*/
                  "Australia/Sydney"      => "Sydney",             /*UTC+10*/
                  "Asia/Magadan"          => "Magadan",            /*UTC+11*/
                  "Pacific/Auckland"      => "Wellington" );       /*UTC+12*/

  echo "  <select id=\"$name\" name=\"$name\" size=\"1\">\n";

  foreach($Tzone as $zone=>$city)
    {
      if($timezone==$zone)
	echo "   <option value=\"$zone\" selected=\"selected\">$city</option>\n";
      else
	echo "   <option value=\"$zone\">$city</option>\n";
    }
  echo "  </select>\n";

  return;
}

function output_select_language($name,$language="")
{
  $LOCALE = array ("English"     => "en",
		   "Deutsch"     => "de" );

  echo "  <select id=\"$name\" name=\"$name\" size=\"1\">\n";

  foreach($LOCALE as $place=>$locale)
    {
      if($language==$locale)
	echo "   <option value=\"$locale\" selected=\"selected\">$place</option>\n";
      else
	echo "   <option value=\"$locale\">$place</option>\n";
    }
  echo "  </select>\n";

  return;
}


function output_password_recovery($email,$password)
{
?>
   <form action="index.php" method="post">
<?php
  echo "  <input type=\"hidden\" name=\"email\" value=\"".$email."\" />\n";
  echo "  <input type=\"hidden\" name=\"password\" value=\"".$password."\" />\n";
  echo "  <input type=\"hidden\" name=\"passwd\"  value=\"set\" />\n";
?>
     <fieldset>
       <legend>Password recovery</legend>
        <table>
         <tr>
            <td><label for="password0">Old password:</label></td>
            <td><input type="password" id="password0" name="password0" size="20" maxlength="30" /> </td>
         </tr><tr>
            <td><label for="password1">New password:</label></td>
            <td><input type="password" id="password1" name="password1" size="20" maxlength="30" /></td>
         </tr><tr>
            <td><label for="password2">Retype:</label></td>
            <td><input type="password" id="password2" name="password2" size="20" maxlength="30" /></td>
         </tr><tr>
           <td></td>
           <td> <input type="submit" class="submitbutton" name="passwd" value="set" /></td>
         </tr>
        </table>
     </fieldset>
   </form>

<?php
}

function output_user_notes($userid,$gameid,$userstatus)
{
  echo "<div class=\"notes\"> "._('Personal notes').": <br />\n";
  $notes = DB_get_notes_by_userid_and_gameid($userid,$gameid);
  foreach($notes as $note)
    echo "  $note <hr />\n";
  if($userstatus!='gameover')
    echo "  <input name=\"note\" type=\"text\" size=\"15\" maxlength=\"100\" />\n";
  echo "</div>\n\n";

  return;
}

function output_robotproof($i)
{
  switch($i)
    {
    case 0:
      return "6*7=";
    case 1:
      return "5*7=";
    case 2:
      return "4*7=";
    case 3:
      return "3*7=";
    case 4:
      return "2*7=";
    }
}

function output_exchanged_cards()
{
  /* in a poverty game this function will output the exchanged cards
   * players in the team will see the cards, the other team will see
   * the backside of cards
   */

  /* need some information about the game */
  global $gameid,$mygametype, $PREF,$me,$mystatus, $RULES;

  /* some variables to track where the people with poverty are sitting */
  $partnerpos1 = 0;
  $povertypos1 = 0;
  $partnerpos2 = 0;
  $povertypos2 = 0;

  /* only need to do it in a poverty game, this might not be needed, but
   * just to make sure everything is ok
   */
  if($mygametype == 'poverty' || $mygametype=='dpoverty')
    {
      /* find out who has poverty */
      for($mypos=1;$mypos<5;$mypos++)
	{
	  $usersick = DB_get_sickness_by_pos_and_gameid($mypos,$gameid);
	  if($usersick == 'poverty' || ($RULES['lowtrump']=='poverty' && $usersick=='lowtrump'))
	    if($povertypos1)
	      $povertypos2 = $mypos;
	    else
	      $povertypos1 = $mypos;
	}
      /* get hash and exchanged cards for all involved */
      $povertyhash1 = DB_get_hash_from_game_and_pos($gameid,$povertypos1);
      $partnerhash1 = DB_get_partner_hash_by_hash($povertyhash1);

      $povertycards1 = DB_get_exchanged_cards($povertyhash1);
      $partnercards1 = DB_get_exchanged_cards($partnerhash1);

      $partnerpos1 = DB_get_pos_by_hash($partnerhash1);
      if($povertypos2)
	{
	  $povertyhash2 = DB_get_hash_from_game_and_pos($gameid,$povertypos2);
	  $partnerhash2 = DB_get_partner_hash_by_hash($povertyhash2);

	  $povertycards2 = DB_get_exchanged_cards($povertyhash2);
	  $partnercards2 = DB_get_exchanged_cards($partnerhash2);

	  $partnerpos2 = DB_get_pos_by_hash($partnerhash2);
	}
    }

  /* output the cards
   * go through all positions, check that position has cards that need to be shown and
   * show those cards
   */
  $show=1;
  for($mypos=1;$mypos<5;$mypos++)
    {
      /* output comments */
      if($mypos==2)
	{
	  /* display all comments on the top right (card1)*/
	  $comments = DB_get_pre_comment($gameid);
	  /* display card */
	  echo "      <div class=\"card1\">\n";
	  /* display comments */
	  foreach( $comments as $comment )
	    echo "        <span class=\"comment\">".$comment[1].": ".$comment[0]."</span>\n";
	  echo "      </div>\n"; /* end div card */
	}

      $usersick = DB_get_sickness_by_pos_and_gameid($mypos,$gameid);
      if($usersick!=NULL ||
	 $mypos==$povertypos1 || $mypos==$partnerpos1 ||
	 $mypos==$povertypos2 || $mypos==$partnerpos2 )
	{
	  /* figure out if we gave trump back */
	  $trump_back1=0;
	  if($povertypos2)
	    foreach($povertycards1 as $card)
	      {
		if(is_trump($card))
		  {
		    $trump_back1=1;
		    break;
		  }
	      }
	  $trump_back2=0;
	  if($povertypos2)
	    foreach($povertycards2 as $card)
	      {
		if(is_trump($card))
		  {
		    $trump_back2=1;
		    break;
		  }
	      }

	  /* output vorbehalt  */
	  echo "      <div class=\"vorbehalt".($mypos-1)."\"> Vorbehalt <br />\n";
	  if($show)
	    echo "       $usersick <br />\n";

	  /* output cards */
	  if($mypos==$partnerpos1)
	    {
	      foreach($partnercards1 as $card)
		{
		  echo '        ';
		  if($povertyhash1 == $me || $partnerhash1 == $me || $mystatus=='gameover')
		    display_card($card,$PREF['cardset']);
		  else
		    display_card(0,$PREF['cardset']);
		}
	      if($trump_back1) echo '        '._('Trump back');
	    }
	  else if($mypos==$povertypos1)
	    {
	      foreach($povertycards1 as $card)
		{
		  echo '        ';
		  if($povertyhash1 == $me || $partnerhash1 == $me || $mystatus=='gameover')
		    display_card($card,$PREF['cardset']);
		  else
		    display_card(0,$PREF['cardset']);
	      }
	      if($trump_back1) echo '        '._('Trump back');
	    }
	  else if($mypos==$povertypos2)
	    {
	      foreach($povertycards2 as $card)
		{
		  echo '        ';
		  if($povertyhash2 == $me || $partnerhash2 == $me || $mystatus=='gameover')
		    display_card($card,$PREF['cardset']);
		  else
		    display_card(0,$PREF['cardset']);
		}
	      if($trump_back2) echo '        '._('Trump back');
	    }
	  else if($mypos==$partnerpos2)
	    {
	      foreach($partnercards2 as $card)
		{
		  if(is_trump($card)) $trump_back=1;
		  echo '        ';
		  if($povertyhash2 == $me || $partnerhash2 == $me || $mystatus=='gameover')
		    display_card($card,$PREF['cardset']);
		  else
		    display_card(0,$PREF['cardset']);
		}
	      if($trump_back2) echo '        '._('Trump back');
	    }
	  echo  "      </div>\n";
	}
      if($mygametype == $usersick)
	$show = 0;
    }
}


?>
