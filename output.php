<?php

/* functions which only ouput html  */

function display_status()
{
  echo "<div class=\"info\">";
  echo " is someone playing solo, etc?";
  echo "</div>\n";
  
  return;
}

function display_news()
{
  global $wiki;
  echo "<div class=\"bug\">\n".
    "  Please hit <strong>shift+reload</strong>.<br /><hr />\n".
    "  added local time display, let me know what you think<br /><hr />\n".
    "  If you find more bugs, please list them in the <a href=\"".$wiki.
    "\">wiki</a>.\n</div>\n";
  return;
}


function output_form_for_new_game()
{
?>
    <p>Please add 4 names, please make sure that the names are correct! </p>
       <form action="index.php" method="post">
   Name:  <input name="PlayerA" type="text" size="10" maxlength="20" /> 
   Name:  <input name="PlayerB" type="text" size="10" maxlength="20" /> 
   Name:  <input name="PlayerC" type="text" size="10" maxlength="20" /> 
   Name:  <input name="PlayerD" type="text" size="10" maxlength="20" /> 

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
  <p> nothing implemented so far, but give it a try anyway ;) </p>	 	  

  <form action="index.php" method="post">

    do you want to play solo? 
    <select name="solo" size="1">
      <option>No</option>
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
       echo " yes<input type=\"radio\" name=\"wedding\" value=\"yes\" />";
       echo " no <input type=\"radio\" name=\"wedding\" value=\"no\" /> <br />\n";
     }
   else
     {
       echo " no <input type=\"hidden\" name=\"wedding\" value=\"no\" /> <br />\n";
     };

  echo "do you have poverty?";
  if(count_trump($mycards)<4)
    {
      echo " yes<input type=\"radio\" name=\"poverty\" value=\"yes\" />";
      echo " no <input type=\"radio\" name=\"poverty\" value=\"no\" /> <br />\n";
    }
  else
    {
      echo " no <input type=\"hidden\" name=\"poverty\" value=\"no\" /> <br />\n";
    };

   echo "do you have too many nines?";
  if(count_nines($mycards)>4)
     {
       echo " yes<input type=\"radio\" name=\"nines\" value=\"yes\" />";
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

function home_page()
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

?>