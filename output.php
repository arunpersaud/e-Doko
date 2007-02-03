<?php

/* functions which only ouput html  */

function display_status($gametype)
{
  echo "<div class=\"info\">";
  echo " Gametype: $gametype";
  echo "</div>\n";
  
  return;
}

function display_news()
{
  global $wiki;
  echo "<div class=\"bug\">\n".
    "  Please hit <strong>shift+reload</strong>.<br /><hr />\n".
    "  The server now keeps score... (only from now on) <br /><hr />".
    "  If you find more bugs, please list them in the <a href=\"".
    $wiki."\">wiki</a>.\n</div>\n";
  return;
}

function output_link_to_user_page($email,$password)
{
  echo "<div class=\"over\">\n";
  echo "<form action=\"index.php\" method=\"post\">\n";
  echo "  <input type=\"hidden\" name=\"email\" value=\"".$email."\" />\n";
  echo "  <input type=\"hidden\" name=\"password\" value=\"".$password."\" />\n";
  echo "  <input type=\"submit\" value=\"go to my user page\" />\n";
  echo "</form>\n";
  echo "</div>\n";
  
  return;
}

function output_register()
{
  echo "IMPORTANT: passwords are going over the net as clear text, so pick an easy password. No need to pick anything complicated here ;)<br /><br />";
  echo "TODO: convert timezone into a menu<br />\n";
  echo "TODO: figure out a way to handle passwrods <br />\n";
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
                  <input type="text" id="Rtimezone" name="Rtimezone" size="4" maxsize="4" value="+1" />
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
  echo "Do you want to continue playing?(This will start a new game, with the next person as dealer.)\n";
  echo "<form action=\"index.php\" method=\"post\">\n";
  echo "  <input type=\"hidden\" name=\"PlayerA\" value=\"$playerA\" />\n";
  echo "  <input type=\"hidden\" name=\"PlayerB\" value=\"$playerB\" />\n";
  echo "  <input type=\"hidden\" name=\"PlayerC\" value=\"$playerC\" />\n";
  echo "  <input type=\"hidden\" name=\"PlayerD\" value=\"$playerD\" />\n";
  echo "  <input type=\"hidden\" name=\"followup\" value=\"$oldgameid\" />\n";
  echo "  <input type=\"submit\" value=\"keep playing\" />\n";
  echo "</form>\n";

  return;
}

function output_form_for_new_game($names)
{
?>
    <p>Please select four players </p>
       <form action="index.php" method="post">
<?php
  foreach( array("PlayerA","PlayerB","PlayerC","PlayerD") as $player)
  {
    echo "    Name:  <select name=\"$player\" size=\"1\" />  \n";
     foreach($names as $name)
      echo "     <option>$name</option>\n";
    echo "  </select>\n";
   }
?>   

   <input type="submit" value="start game" />
 </form>
<?php
}

function display_card($card)
{
  /* cards are only availabl for the odd values, e.g. 1.png, 3.png, ... 
   * convert even cards to the matching odd value */

  if( $card/2 - (int)($card/2) == 0.5)
    echo "<img src=\"cards/".$card.".png\"  alt=\"".card_to_name($card)."\" />\n";
  else
    echo "<img src=\"cards/".($card-1).".png\"  alt=\"".card_to_name($card-1)."\" />\n";

  return;
}

function display_link_card($card)
{
  if( $card/2 - (int)($card/2) == 0.5)
    echo "<input type=\"radio\" name=\"card\" value=\"".$card."\" /><img src=\"cards/".$card.".png\" alt=\"\" />\n";
  else
    echo "<input type=\"radio\" name=\"card\" value=\"".$card."\" /><img src=\"cards/".($card-1).".png\" alt=\"\" />\n";
  return;
}

function check_for_sickness($me,$mycards)
{
 ?>
  <p> Solo will work, but the first player will not change. Nothing else implemented. </p>	 	  

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
      
  echo "wedding?";
  if(check_wedding($mycards))
     {
       echo " yes<input type=\"radio\" name=\"wedding\" value=\"yes\" checked=\"checked\" />";
       echo " no <input type=\"radio\" name=\"wedding\" value=\"no\" /> <br />\n";
     }
   else
     {
       echo " no <input type=\"hidden\" name=\"wedding\" value=\"no\" /> <br />\n";
     };

  echo "do you have poverty?";
  if(count_trump($mycards)<4)
    {
      echo " yes<input type=\"radio\" name=\"poverty\" value=\"yes\" checked=\"checked\" />";
      echo " no <input type=\"radio\" name=\"poverty\" value=\"no\" /> <br />\n";
    }
  else
    {
      echo " no <input type=\"hidden\" name=\"poverty\" value=\"no\" /> <br />\n";
    };

   echo "do you have too many nines?";
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

   Do you want to get an email for every card played or only if it your move?
   every card<input type="radio" name="update" value="card" />
   only on my turn<input type="radio" name="update" value="turn" /> <br />
<?php   
  echo "<input type=\"hidden\" name=\"me\" value=\"$me\" />\n";
  echo "\n";
  echo "<input type=\"submit\" value=\"count me in\" />\n";
  echo " </form>\n";

  return;
}

function output_home_page()
{
?>
    <p> If you want to play a game of Doppelkopf, you found the right place ;) </p>
    <p> Please <a href="index.php?register">register</a>, in case you haven't done yet  <br />
        or login with you email-address or name and password here:
    </p>
        <form action="index.php" method="post">
          <fieldset>
            <legend>Login</legend>
             <table>
              <tr>
               <td><label for="email">Email:</label></td><td><input type="text" id="email" name="email" size="20" maxlength="30" /> </td>
              </tr><tr>
               <td><label for="password">Password:</label></td><td><input type="password" id="password" name="password" size="20" maxlength="30" /></td>
              </tr><tr>
               <td> <input type="submit" value="login" /></td>
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
<h1> Welcome to E-Doko </h1>
<p> Revision: <?php echo "$REV"; ?></p>
</div>

<?php
  return;
}



function output_footer()
{
  echo "</body>\n";
  echo "</html>\n";

  return;
}
?>