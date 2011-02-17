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

/* this outputs the default home page with some extra statistics on it */

$pre[0]=0;$game[0]=0;$done[0]=0;

$r=DB_query("SELECT COUNT(*) FROM Game where status='pre'");
if($r)  $pre  = DB_fetch_array($r);

$r=DB_query("SELECT COUNT(*) FROM Game where status='play'");
if($r)  $game  = DB_fetch_array($r);

$r=DB_query("SELECT COUNT(*) FROM Game where status='gameover'");
if($r)  $done  = DB_fetch_array($r);


$r=DB_query("SELECT AVG(datediff(mod_date,create_date)) FROM Game where status='gameover' ");
if($r)
  $avgage= DB_fetch_array($r);
 else
   $avgage[0]=0;

$pre	= $pre[0];
$game	= $game[0];
$done	= $done[0];
$avgage	= $avgage[0];

echo "\n\n<div class=\"login\">\n";

echo "<p> If you want to play a game of Doppelkopf, you found the right place ;)</p>".
"<p> For more information please visit our <a href=\"$WIKI\">wiki</a>. </p>".
"<h4>Some statistics:</h4>";


if($pre == 0)
  echo "<p> At the moment there are no games that are being started ";
 else if($pre==1)
   echo "<p> At the moment there is one game that is being started ";
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
    <label for="email">Email:</label>
    <input type="text" id="email" name="email" size="20" maxlength="30" autofocus /> <br />
    <script>
    if (!("autofocus" in document.createElement("input"))) {
      document.getElementById("email").focus();
    }
    </script>
    <label for="password">Password:</label>
    <input type="password" id="password" name="password" size="20" maxlength="30" /> <br />
    <input type="submit" class="submitbutton" name="login" value="login" />
    <input type="submit" class="submitbutton" name="forgot" value="Forgot your password?" />
<?php
  if($OPENIDPATH)
    {?>
    <hr />
    <p> Have an OpenID account? Sign in below <br />
    <input type="text" id="openid_url" name="openid_url" size="20" maxlength="50" />
    <input type="submit" class="submitbutton" name="login" value="Sign in" /><br />
     e.g. http://username.openid.net. See <a href="http://openid.net">openid.net</a> for more information.</p>
<?php }?>
  </fieldset>
  </form>
</div>
