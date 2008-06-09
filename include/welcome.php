<?php
/* make sure that we are not called from outside the scripts, 
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

/* this outputs the default home page with some extra statistics on it */

$pre[0]=0;$game[0]=0;$done[0]=0;

$r=DB_query("SELECT COUNT(id) FROM Game GROUP BY status");
if($r) {
  $pre  = DB_fetch_array($r);
  $game = DB_fetch_array($r);
  $done = DB_fetch_array($r);
 }

$r=DB_query("SELECT AVG(datediff(mod_date,create_date)) FROM Game where status='gameover' ");
if($r)
  $avgage= DB_fetch_array($r);
 else
   $avgage[0]=0;

$pre	= $pre[0];
$game	= $game[0];
$done	= $done[0];
$avgage	= $avgage[0];

echo "<div class=\"login\">\n";

echo "<p> If you want to play a game of Doppelkopf, you found the right place ;)</p>".
"<p> For more information please visit our <a href=\"$WIKI\">wiki</a>. </p>".
"<h4>Some statistics:</h4>";


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
   echo "$done games have been completed on this server. Average time of a game: $avgage days</p>";
?>

  <h4> Login/Register:</h4>
  <p> 
  Please <a href="index.php?action=register">register</a>, in case you have not done that yet  <br />
  or login with you email-address or name and password here:
  </p>

  <form action="index.php?action=login" method="post">
  <fieldset>
  <table>
  <tr>
    <td><label for="email">Email:</label></td>
    <td><input type="text" class="submitbutton" id="email" name="email" size="20" maxlength="30" /> </td>
  </tr>
  <tr>
    <td><label for="password">Password:</label></td>
    <td><input type="password" class="submitbutton" id="password" name="password" size="20" maxlength="30" /></td>
  </tr>
  <tr class="center">
    <td> <input type="submit" class="submitbutton" name="login" value="login" /></td>
    <td> <input type="submit" class="submitbutton" name="forgot" value="Forgot your password?" /></td>
  </tr>
  </table>
  </fieldset>
  </form>
  
</div>  