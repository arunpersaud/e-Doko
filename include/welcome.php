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

echo "  <p> Play Doppelkopf online.</p>\n".
"  <p> For more information please visit our <a href=\"$WIKI\">wiki</a>. </p>\n";
?>
  <ul class="loginregister">
  <li> Login </li>
  <li> Register </li>
  </ul>

  <form class="dologin" action="index.php?action=login" method="post">
  <fieldset>
    <label for="email">Email:</label>
    <input type="email" id="email" name="email" size="20" maxlength="30" autofocus /> <br />
    <label for="password">Password:</label>
    <input type="password" id="password" name="password" size="20" maxlength="30" /> <br />
    <input type="submit" class="submitbutton" name="login" value="login" />
    <input type="submit" class="submitbutton" name="forgot" value="Forgot your password?" />
<?php
  if($OPENIDPATH)
    {?>
    <hr />
    <label for="openid_url">Openid:</label>
    <input type="text" id="openid_url" name="openid_url" size="20" maxlength="50" placeholder="http://username.openid.net"/> <br />
    <p>See <a href="http://openid.net">openid.net</a> for more information.</p>
    <input type="submit" class="submitbutton" name="login" value="Sign in" /><br />
<?php }?>
  </fieldset>
  </form>

<?php
     /* check for openid information */
     $openid_url = '';
     $name	 = '';
     $email	 = '';
     if(myisset('openid_url'))
       $openid_url = $_REQUEST['openid_url'];
     if(myisset('openidname'))
       $name       = $_REQUEST['openidname'];
     if(myisset('openidemail'))
       $email      = $_REQUEST['openidemail'];

     echo '  <form class="doregister" action="index.php?action=register" method="post">'; echo "\n";
     echo '  <fieldset>'; echo "\n";
     echo '     <label for="Rfullname">Full name:</label>'; echo "\n";
     echo "     <input type=\"text\" id=\"Rfullname\" name=\"Rfullname\" size=\"20\" maxlength=\"30\" value=\"$name\" /> <br />\n";
     echo '     <label for="Remail">Email:</label>'; echo "\n";
     echo "     <input type=\"text\" id=\"Remail\" name=\"Remail\" size=\"20\" maxlength=\"30\" value=\"$email\" />  <br />\n";
     if($openid_url=='')
       {
	 echo '     <label for="Rpassword">Password:</label>'; echo "\n";
	 echo '     <input type="password" id="Rpassword" name="Rpassword" size="20" maxlength="30" />'; echo "<br />\n";
       }
     else
       {
	 echo '    <label for="Ropenid">OpenId:</label>'; echo "\n";
	 echo '    <input type="text" id="Ropenid" name="Ropenid" size="20" maxlength="50" value="'.htmlentities($openid_url).'" /> <br />'; echo "\n";
       }
     echo '     <label for="Rtimezone">Timezone:</label>'; echo "\n";
     output_select_timezone("Rtimezone");

     /* random number to select robotproof question */
     $rand_number = mt_rand(0,3); /* to get numbers between 0 and 4  */
     $Robotproof = "Robotproof".$rand_number;
?>
    <p style="float: left">Please answer this anti-spam question:</p>
    <label for="Robotproof">  <?php echo output_robotproof($rand_number); ?></label>
<?php
	 echo "    <input type=\"text\" id=\"Robotproof\" name=\"$Robotproof\" size=\"20\" maxlength=\"30\" /> <br />\n";
?>
    <input type="submit" value="register" />

<?php
       if($openid_url=='')
	 echo "  <p><strong> IMPORTANT: passwords are going over the net as clear text, so pick an easy password. ".
	   "No need to pick anything complicated here ;)</strong></p>\n";

     echo "  <p><strong>N.B. Your email address will be exposed to other players whom you play games with.";
     echo "</strong></p>\n";
?>
  </fieldset>
  </form>

<?php
echo "<h4>Some statistics:</h4>\n";

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
   echo "$done games have been completed on this server. Average time of a game: $avgage days</p>\n";
?>

</div>
