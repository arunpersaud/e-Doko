<?php
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
  echo "Do you want to continue playing?(This will start a new game, with the next person as dealer.)\n";
  echo "  <input type=\"hidden\" name=\"PlayerA\" value=\"$playerA\" />\n";
  echo "  <input type=\"hidden\" name=\"PlayerB\" value=\"$playerB\" />\n";
  echo "  <input type=\"hidden\" name=\"PlayerC\" value=\"$playerC\" />\n";
  echo "  <input type=\"hidden\" name=\"PlayerD\" value=\"$playerD\" />\n";
  echo "  <input type=\"hidden\" name=\"dullen\"  value=\"".$RULES["dullen"]."\" />\n";
  echo "  <input type=\"hidden\" name=\"schweinchen\" value=\"".$RULES["schweinchen"]."\" />\n";
  echo "  <input type=\"hidden\" name=\"callrule\" value=\"".$RULES["call"]."\" />\n";
  echo "  <input type=\"hidden\" name=\"followup\" value=\"$oldgameid\" />\n";
  echo "  <input type=\"submit\" value=\"keep playing\" />\n";
  echo "</form>\n</div>";
  return;
}

function output_form_for_new_game($names)
{
?>
  <form action="index.php?action=new" method="post">
    <h2> Select players (Remember: you need to be one of the players) </h2>

   <div class="table">
     <img class="table" src="pics/table.png" alt="table" />
<?php
    /* ask for player names */
    $i=0;
  foreach( array("PlayerA","PlayerB","PlayerC","PlayerD") as $player)
    {
      srand((float) microtime() * 10000000);
      $randkey = array_rand($names);
      $rand = $names[$randkey];
      echo  "     <div class=\"table".$i."\">\n";
      $i++;
      echo "       <select name=\"$player\" size=\"1\">  \n";
      foreach($names as $name)
	{
	  if($name==$rand)
	    {
	      echo "         <option selected=\"selected\">$name</option>\n";
	    }
	  else
	    echo "         <option>$name</option>\n";
	}
      echo "       </select>\n     </div>\n";
    }
?>
    </div>

   <h2 class="rules"> Rules </h2>
      <p> Some areas are grayed out which means that the rule is not implemented yet and therefore cannot be selected </p>
      <p> Ten of hearts: </p>
      <ul>
        <li> <input type="radio" name="dullen" value="none" /> just normal non-trump  </li>
        <li> <input type="radio" name="dullen" value="firstwins" /> first ten of hearts wins the trick </li>
        <li> <input type="radio" name="dullen" value="secondwins" checked="checked" /> second ten of hearts wins the trick </li>
      </ul>
      <p> Schweinchen (both foxes), only in normal games or silent solos: </p>
      <ul>
        <li> <input type="radio" name="schweinchen" value="none" checked="checked" /> none </li>
        <li> <input type="radio" name="schweinchen" value="both" />
              both become highest trump (automatic call at beginning of the game)
        </li>
        <li> <input type="radio" name="schweinchen" value="second" />
             first one normal, second one becomes highest (call during the game) </li>
        <li> <input type="radio" name="schweinchen" value="secondaftercall"  disabled="disabled" />
             second one become highest only in case re/contra was announced (not working yet)
        </li>
      </ul>
      <p> Call Re/Contra, etc.: </p>
      <ul>
         <li><input type="radio" name="callrule" value="1st-own-card" checked="checked" />
              Can call re/contra on the first <strong>own</strong> card played, 90 on the second, etc.</li>
         <li><input type="radio" name="callrule" value="5th-card" />
              Can call re/contra until 5th card is played, 90 until 9th card is played, etc.</li>
         <li><input type="radio" name="callrule" value="9-cards"  />
              Can call re/contra until 5th card is played, 90 if player still has 9 cards, etc.</li>
      </ul>
   <input type="submit" value="start game" />
 </form>
<?php
}

function output_table($data,$caption="",$class="")
{
  if($class!="")
    $HTML  = "\n<table class=\"$class\">\n";
  else
    $HTML  = "\n<table>\n";

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
	  if($i % 2)
	    $HTML .= "  <tr class=\"odd\">   ";
	  else
	    $HTML .= "  <tr class=\"even\">  ";
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

function display_link_card($card,$dir="english",$type="card")
{
  if( $card/2 - (int)($card/2) == 0.5)
    echo "<div class=\"cardinput\"><input type=\"radio\" name=\"".$type."\" value=\"".$card."\" /><img src=\"cards/".$dir."/".$card.".png\" alt=\"".DB_get_card_name($card)."\" /></div>\n";
  else
    echo "<div class=\"cardinput\" ><input type=\"radio\" name=\"".$type."\" value=\"".$card."\" /><img src=\"cards/".$dir."/".($card-1).".png\" alt=\"".DB_get_card_name($card-1)."\" /></div>\n";
  return;
}

function output_check_for_sickness($me,$mycards)
{
 ?>
  <div class="sickness"> Thanks for joining the game...<br />

    do you want to play solo?
    <select name="solo" size="1">
      <option selected="selected">No</option>
      <option>trumpless</option>
      <option>trump</option>
      <option>queen</option>
      <option>jack</option>
      <option>club</option>
      <option>spade</option>
      <option>heart</option>
    </select>
    <br />

 <?php

  echo "Wedding?";
  if(check_wedding($mycards))
     {
       echo " yes<input type=\"radio\" name=\"wedding\" value=\"yes\" checked=\"checked\" />";
       echo " no <input type=\"radio\" name=\"wedding\" value=\"no\" /> <br />\n";
     }
   else
     {
       echo " no <input type=\"hidden\" name=\"wedding\" value=\"no\" /> <br />\n";
     };

  echo "Do you have poverty?";
  if(count_trump($mycards)<4)
    {
      echo " yes<input type=\"radio\" name=\"poverty\" value=\"yes\" checked=\"checked\" />";
      echo " no <input type=\"radio\" name=\"poverty\" value=\"no\" /> <br />\n";
    }
  else
    {
      echo " no <input type=\"hidden\" name=\"poverty\" value=\"no\" /> <br />\n";
    };

   echo "Do you have too many nines?";
  if(count_nines($mycards)>4)
     {
       echo " yes<input type=\"radio\" name=\"nines\" value=\"yes\" checked=\"checked\" />";
       echo " no <input type=\"radio\" name=\"nines\" value=\"no\" /> <br />\n";
     }
   else
     {
       echo " no <input type=\"hidden\" name=\"nines\" value=\"no\" /> <br />\n";
     };

   echo "<input type=\"hidden\" name=\"me\" value=\"$me\" />\n";
   echo "<input type=\"submit\" value=\"count me in\" />\n";

   echo "</div>\n";

  return;
}

function output_form_calls($me)
{
  if( can_call(120,$me) )
    echo " re/contra (120):".
      " <input type=\"radio\" name=\"call\" value=\"120\" /> <br />";
  if( can_call(90,$me) )
    echo " 90:".
      " <input type=\"radio\" name=\"call\" value=\"90\" /> <br />";
  if( can_call(60,$me) )
    echo " 60:".
      " <input type=\"radio\" name=\"call\" value=\"60\" /> <br />";
  if( can_call(30,$me) )
    echo " 30:".
      " <input type=\"radio\" name=\"call\" value=\"30\" /> <br />";
  if( can_call(0,$me) )
    echo " 0:".
      " <input type=\"radio\" name=\"call\" value=\"0\" /> <br />".
      " no call:".
      " <input type=\"radio\" name=\"call\" value=\"no\" /> <br />";
}

function output_check_want_to_play($me)
{
   ?>
 <div class="joingame">
   Do you want to play a game of DoKo? <br />
   yes<input type="radio" name="in" value="yes" />
   no<input type="radio" name="in" value="no" /> <br />
<?php
  echo "<input type=\"hidden\" name=\"me\" value=\"$me\" />\n";
  echo "\n";
  echo "<input type=\"submit\" value=\"submit\" />\n";
  echo " </div>\n";

  return;
}

function output_header()
{
   global $REV;
?>
<!DOCTYPE html PUBLIC
    "-//W3C//DTD XHTML 1.1 plus MathML 2.0 plus SVG 1.1//EN"
    "http://www.w3.org/2002/04/xhtml-math-svg/xhtml-math-svg.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
  <head>
     <title>e-Doko</title>
     <meta content="text/html; charset=ISO-8859-1" http-equiv="content-type" />
     <link rel="shortcut icon" type="image/x-icon" href="pics/edoko-favicon.png" />
     <link rel="stylesheet" type="text/css" href="css/standard010.css" />
     <script type="text/javascript">
       var current=0;
       function hl(num) {
         if(document.getElementById){
	   var i;
	   for(i=1;i<14;i++){
	     if(document.getElementById("trick"+i))
	       document.getElementById("trick"+i).style.display = 'none';
	   }
	   document.getElementById("trick"+num).style.display = 'block';
	   current=num;
	 }
       }
       function high_last(){
	 if(document.getElementById){
	   var i;
	   for(i=13;i>=0;i--) {
	     if(document.getElementById("trick"+i))
	       {
		 hl(i);
		 current=i;
		 break;
	       }
	   }
	 }
       }
       function hl_next()
	 {
	   if(document.getElementById("trick"+(current+1)))
	     hl(current+1);
	 }
       function hl_prev()
	 {
	   if(document.getElementById("trick"+(current-1)))
	     hl(current-1);
	 }
     </script>
  </head>
<body onload="high_last();">
<div class="header">
<h1> Welcome to E-Doko <sup style="color:#888;">(beta)</sup> </h1>
</div>
<?php

  echo "<div class=\"main\">";
  return;
}

function output_footer()
{
  global $REV,$PREF;

  echo "</div>\n\n";
  echo "<div class=\"footer\">\n";
  echo "  <p class=\"left\"> copyright 2006-2008 Arun Persaud, Lance Thornton <br />\n".
    "  Verwendung der [deutschen] Kartenbilder mit Genehmigung <br />der Spielkartenfabrik Altenburg GmbH,(c) ASS Altenburger <br />\n".
    "  - ASS Altenburger Spielkarten - Spielkartenfabrik Altenburg GmbH <br />\n".
    "  a Carta Mundi Company Email: info@spielkarten.com Internet: www.spielkarten.com</p>\n";
 echo " <p class=\"right\"> See the latest changes <a href=\"http://nubati.net/cgi-bin/gitweb.cgi?p=e-DoKo.git;a=summary\">\n".
    "  via git </a> <br />or download the source via <br />\n'git clone http://nubati.net/git/e-DoKo.git' <br />\n".
    "  <a href=\"http://www.dreamhost.com/green.cgi\">\n".
    "  <img  border=\"0\" alt=\"Green Web Hosting! This site hosted by DreamHost.\"".
    "src=\"https://secure.newdream.net/green1.gif\" height=\"32\" width=\"100\" /></a>\n".
    "  </p> \n";
  echo "\n";
  echo "</div>\n";

  echo "</body>\n";
  echo "</html>\n";

  return;
}

function output_status()
{
  global $defaulttimezone,$INDEX,$WIKI;
   if(isset($_SESSION["name"]))
     {
       $name = $_SESSION["name"];

       /* logout info */
       echo "\n<div class=\"status\">\n";
       echo $name,"\n";
       echo " | <a href=\"".$INDEX."\"> mypage </a>\n";
       echo " | <a href=\"".$INDEX."?action=prefs\">settings</a>\n";
       echo " | <a href=\"".$INDEX."?action=new\">new game</a>\n";
       echo " | <a href=\"".$INDEX."?action=stats\">statistics</a>\n";
       echo " | <a href=\"".$WIKI."\">wiki</a>\n";
       echo " |&nbsp;&nbsp;&nbsp; <a href=\"".$INDEX."?action=logout\">logout</a>\n";
       echo "</div>\n";

       /* last logon time */
       $myid  = DB_get_userid("name",$name);
       $zone  = DB_get_user_timezone($myid);

       $time     = DB_get_user_timestamp($myid);
       date_default_timezone_set($defaulttimezone);
       $unixtime = strtotime($time);
       date_default_timezone_set($zone);

       echo "<div class=\"lastlogin\"><span>last login: ".date("r",$unixtime)."</span></div>\n";
     }
   else
     {
       echo "\n<div class=\"status\">\n";
       echo "<a href=\"".$INDEX."\">login</a>\n";
       echo "</div>\n";
     }
  return;
}

function output_select_timezone($name,$timezone="")
{
  $Tzone = array ( "Europe/London"     => "London",
		   "Europe/Berlin"     => "Berlin",
		   "America/Vancouver" => "Berkeley",
		   "Pacific/Auckland"  => "Wellington" );

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
            <td><label for="email">Old password:</label></td>
            <td><input type="password" id="password0" name="password0" size="20" maxlength="30" /> </td>
         </tr><tr>
            <td><label for="password">New password:</label></td>
            <td><input type="password" id="password1" name="password1" size="20" maxlength="30" /></td>
         </tr><tr>
            <td><label for="password">Retype:</label></td>
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
  echo "<div class=\"notes\"> Personal notes: <br />\n";
  $notes = DB_get_notes_by_userid_and_gameid($userid,$gameid);
  foreach($notes as $note)
    echo "$note <hr />\n";
  if($userstatus!='gameover')
    echo "<input name=\"note\" type=\"text\" size=\"15\" maxlength=\"100\" />\n";
  echo "</div> \n";

  return;
}
    
?>