<?php

/* functions which only ouput html  */

function display_links($email,$password)
{
  global $wiki;
  echo "<div class=\"bug\">\n".
    "Report bugs in the <a href=\"". $wiki."\">wiki</a>.<hr />\n";
  output_link_to_user_page($email,$password);
  echo  "</div>\n";
  return;
}

function output_link_to_user_page($email,$password)
{
  echo "<div class=\"over\">\n";
  echo "<form action=\"index.php\" method=\"post\">\n";
  echo "  <input type=\"hidden\" name=\"email\" value=\"".$email."\" />\n";
  echo "  <input type=\"hidden\" name=\"password\" value=\"".$password."\" />\n";
  echo "  <input type=\"submit\" class=\"submitbutton\" value=\"go to my user page\" />\n";
  echo "</form>\n";
  echo "</div>\n";
  
  return;
}

function output_user_settings($email,$password)
{
  global $PREF;

  echo "<div class=\"useroptions\">\n";
  echo "<h4> Settings </h4>\n";
  echo "<form action=\"index.php\" method=\"post\">\n";
  echo "  <input type=\"hidden\" name=\"email\" value=\"".$email."\" />\n";
  echo "  <input type=\"hidden\" name=\"password\" value=\"".$password."\" />\n";
  echo "  <input type=\"hidden\" name=\"passwd\"  value=\"ask\" />\n";
  echo "  <input type=\"submit\" class=\"submitbutton\" name=\"pass\" value=\"change password\" /> <br />\n";
  echo "</form>\n";
  echo "<form action=\"index.php\" method=\"post\">\n";
  echo "  <input type=\"hidden\" name=\"email\" value=\"".$email."\" />\n";
  echo "  <input type=\"hidden\" name=\"password\" value=\"".$password."\" />\n";
  echo "  <input type=\"hidden\" name=\"setpref\"  value=\"englishcards\" />\n";
  echo "  <input type=\"submit\" class=\"submitbutton\" value=\"use english cards\" /> <br />\n";
  echo "</form>\n";
  echo "<form action=\"index.php\" method=\"post\">\n";
  echo "  <input type=\"hidden\" name=\"email\" value=\"".$email."\" />\n";
  echo "  <input type=\"hidden\" name=\"password\" value=\"".$password."\" />\n";
  echo "  <input type=\"hidden\" name=\"setpref\"  value=\"germancards\" />\n";
  echo "  <input type=\"submit\" class=\"submitbutton\" value=\"use german cards\" /> <br />\n";
  echo "</form>\n";
  echo "</div>\n";
  return;
}

function output_register()
{
  echo "IMPORTANT: passwords are going over the net as clear text, so pick an easy password. No need to pick anything complicated here ;)<br /><br />";
  echo "TODO: figure out a better way to handle passwords <br />\n";
  ?>
        <form action="index.php" method="post">
          <fieldset>
            <legend>Register</legend>
             <table>
              <tr>
               <td><label for="Rfullname">Full name:</label></td>
	       <td><input type="text" id="Rfullname" name="Rfullname" size="20" maxsize="30" /> </td>
              </tr><tr>
               <td><label for="Remail">Email:</label></td>
	       <td><input type="text" id="Remail" name="Remail" size="20" maxsize="30" /></td>
              </tr><tr>
	       <td><label for="Rpassword">Password(will be displayed in cleartext on the next page):</label></td>
               <td><input type="password" id="Rpassword" name="Rpassword" size="20" maxsize="30" /></td>
              </tr><tr>
	       <td><label for="Rtimezone">Timezone:</label></td>
               <td>
		  <select id="Rtimezone" name="Rtimezone" size="1">
                     <option value="1">Berlin</option>
                     <option value="-8">Berkeley</option>
                     <option value="13">Wellington</option>
		  </select>
	         (If your timezone is not listed, just select whatever you want and email the admin your correct time zone.)
	       </td>
              </tr><tr>
               <td colspan="2"> <input type="submit" value="register" /></td>
             </table>
          </fieldset>
        </form>
<?php
  return;
}					   

function output_ask_for_new_game($playerA,$playerB,$playerC,$playerD,$oldgameid)
{
  global $RULES;

  echo "Do you want to continue playing?(This will start a new game, with the next person as dealer.)\n";
  echo "<form action=\"index.php\" method=\"post\">\n";
  echo "  <input type=\"hidden\" name=\"PlayerA\" value=\"$playerA\" />\n";
  echo "  <input type=\"hidden\" name=\"PlayerB\" value=\"$playerB\" />\n";
  echo "  <input type=\"hidden\" name=\"PlayerC\" value=\"$playerC\" />\n";
  echo "  <input type=\"hidden\" name=\"PlayerD\" value=\"$playerD\" />\n";
  echo "  <input type=\"hidden\" name=\"dullen\"  value=\"".$RULES["dullen"]."\" />\n";
  echo "  <input type=\"hidden\" name=\"schweinchen\" value=\"".$RULES["schweinchen"]."\" />\n";
  echo "  <input type=\"hidden\" name=\"call\" value=\"".$RULES["call"]."\" />\n";
  echo "  <input type=\"hidden\" name=\"followup\" value=\"$oldgameid\" />\n";
  echo "  <input type=\"submit\" value=\"keep playing\" />\n";
  echo "</form>\n";

  return;
}

function output_form_for_new_game($names)
{
?>
    <h2> Players </h2>
    <p>Please select four players (or use the randomly pre-selected names)</p>
       <form action="index.php" method="post">
<?php
    /* ask for player names */
  foreach( array("PlayerA","PlayerB","PlayerC","PlayerD") as $player)
  {
    srand((float) microtime() * 10000000);
    $randkey = array_rand($names);
    $rand = $names[$randkey];
    echo "    Name:  <select name=\"$player\" size=\"1\" />  \n";
    foreach($names as $name)
    {
      if($name==$rand)
	{
	  echo "     <option selected=\"selected\">$name</option>\n";
	}
      else
	echo "     <option>$name</option>\n";
    }
    echo "  </select>\n";
   }
?>   
   <h2> Rules </h2> 
      <p> Some areas are grayed out which means that the rule is not implemented yet and therefore cannot be selected </p>
      <p> ten of hearts: 
         <ul>
         <li> <input type="radio" name="dullen" value="none" /> just normal non-trump  </li>
         <li> <input type="radio" name="dullen" value="firstwins" /> first ten of hearts wins the trick </li>
         <li> <input type="radio" name="dullen" value="secondwins" checked="checked" /> second ten of hearts wins the trick </li>
         </ul>
      </p>
      <p> schweinchen (both foxes), only in normal games or silent solos: 
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
      </p>
      <p> Call Re/Contra, etc.: 
        <ul>
           <li><input type="radio" name="call" value="1st-own-card" checked="checked" />
                Can call re/contra on the first <strong>own</strong> card played, 90 on the second, etc.</li>
           <li><input type="radio" name="call" value="5th-card" /> 
                Can call re/contra until 5th card is played, 90 until 9th card is played, etc.</li>
           <li><input type="radio" name="call" value="9-cards"  /> 
                Can call re/contra until 5th card is played, 90 if player still has 9 cards, etc.</li>
        </ul>
      </p>      
   <input type="submit" value="start game" />
 </form>
<?php
}

function display_card($card,$dir="english")
{
  /* cards are only availabl for the odd values, e.g. 1.png, 3.png, ... 
   * convert even cards to the matching odd value */

  if( $card/2 - (int)($card/2) == 0.5)
    echo "<img src=\"cards/".$dir."/".$card.".png\"  alt=\"".card_to_name($card)."\" />\n";
  else
    echo "<img src=\"cards/".$dir."/".($card-1).".png\"  alt=\"".card_to_name($card-1)."\" />\n";

  return;
}

function display_link_card($card,$dir="english",$type="card")
{
  if( $card/2 - (int)($card/2) == 0.5)
    echo "<div class=\"cardinput\"><input type=\"radio\" name=\"".$type."\" value=\"".$card."\" /><img src=\"cards/".$dir."/".$card.".png\" alt=\"\" /></div>\n";
  else
    echo "<div class=\"cardinput\" ><input type=\"radio\" name=\"".$type."\" value=\"".$card."\" /><img src=\"cards/".$dir."/".($card-1).".png\" alt=\"\" /></div>\n";
  return;
}

function output_check_for_sickness($me,$mycards)
{
 ?>
  <form action="index.php" method="post">

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

   echo "</form>\n";

  return;
}

function check_want_to_play($me)
{
   ?>
 <form action="index.php" method="post">
   Do you want to play a game of DoKo?
   yes<input type="radio" name="in" value="yes" />
   no<input type="radio" name="in" value="no" /> <br />

<?php   
/*
   Do you want to get an email for every card played or only if it your move?
   every card<input type="radio" name="update" value="card" />
   only on my turn<input type="radio" name="update" value="turn" /> <br />
*/
  echo "<input type=\"hidden\" name=\"me\" value=\"$me\" />\n";
  echo "\n";
  echo "<input type=\"submit\" value=\"count me in\" />\n";
  echo " </form>\n";

  return;
}

function output_home_page($pre,$game,$done,$avgtime)
{
?>
    <p> If you want to play a game of Doppelkopf, you found the right place ;) </p>
<?php
    if($pre == 0)
      echo "<p> At the moment there are no games that are being started ";
    else if($pre==1)
      echo "<p> At the moment there is one games that is being started ";
    else
      echo "<p> At the moment there are $pre games that are being started ";

    echo "and ";

    if($game==0)
      echo "zero games that are ongoing. ";
    else if($game==1)	
      echo "one game that is ongoing. ";
    else 
      echo "$game games that are ongoing. ";

    echo "<br />\n";

    if($done==0)
      echo "No game has been completed on this server. </p>";
    else if($done==1)
      echo "One game has been completed on this server. </p>";
    else
      echo "$done games have been completed on this server. Average time of a game: $avgtime days</p>";
?>

    <p> Please <a href="index.php?register">register</a>, in case you haven't done that yet  <br />
        or login with you email-address or name and password here:
    </p>
        <form action="index.php" method="post">
          <fieldset>
            <legend>Login</legend>
             <table>
              <tr>
                 <td><label for="email">Email:</label></td>
                 <td><input type="text" id="email" name="email" size="20" maxlength="30" /> </td>
              </tr><tr>
                 <td><label for="password">Password:</label></td>
                 <td><input type="password" id="password" name="password" size="20" maxlength="30" /></td>
              </tr><tr>
                <td> <input type="submit" class="submitbutton" name="login" value="login" /></td>
                <td> <input type="submit" class="submitbutton" name="forgot" value="Forgot your password?" /></td>
              </tr>
             </table>
          </fieldset>
        </form>

<?php
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
     <link rel="stylesheet" type="text/css" href="css/standard.css" />	
     <script type="text/javascript">
       function hl(num) {
         if(document.getElementById){
	   var i;
	   for(i=1;i<13;i++){
	     if(document.getElementById("trick"+i))
	       document.getElementById("trick"+i).style.display = 'none';
	   }
	   document.getElementById("trick"+num).style.display = 'block';
	 }
       }
       function high_last(){
	 if(document.getElementById){
	   var i;
	   for(i=12;i>0;i--) {
	     if(document.getElementById("trick"+i))
	       {
		 hl(i);
		 break;
	       }
	   }
	 }
       }
     </script>
  </head>
<body onload="high_last();">
<div class="header">
<h1> Welcome to E-Doko <sup style="color:#888;">(beta)</sup> </h1>
</div>

<?php
  return;
}



function output_footer()
{
  global $REV,$PREF;

  echo "<div class=\"footer\">\n";
  echo "<p class=\"left\"> copyright 2006-2007 Arun Persaud <br />\n".
    "Verwendung der [deutschen] Kartenbilder mit Genehmigung <br />der Spielkartenfabrik Altenburg GmbH,(c) ASS Altenburger <br />".
    "- ASS Altenburger Spielkarten - Spielkartenfabrik Altenburg GmbH <br />".
    "a Carta Mundi Company Email: info@spielkarten.com Internet: www.spielkarten.com</p>\n";
  echo "<p class=\"right\"> Revision: $REV; <br /><a href=\"http://svn.nubati.net/emaildoko/trunk/\">".
    "http://svn.nubati.net/emaildoko/trunk/</a> <br />".
    "<a href=\"http://www.dreamhost.com/green.cgi\">".
    "<img  border=\"0\" alt=\"Green Web Hosting! This site hosted by DreamHost.\"".
    "src=\"https://secure.newdream.net/green1.gif\" height=\"32\" width=\"100\" /></a>".
    "</p> \n";
  echo "\n";
  echo "</div>\n";

  echo "</body>\n";
  echo "</html>\n";

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
?>