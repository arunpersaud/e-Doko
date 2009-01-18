<?php
/* make sure that we are not called from outside the scripts,
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

/* new user wants to register */
if(myisset("Rfullname","Remail","Rpassword","Rtimezone") )
  {
    global $HOST,$INDEX;

    /* is this name already in use/ */
    $ok=1;
    if(DB_get_userid('name',$_REQUEST['Rfullname']))
      {
	echo "please chose another name<br />";
	$ok=0;
      }
    /* check if email address is already used */
    if(DB_get_userid('email',$_REQUEST['Remail']))
      {
	echo "this email address is already used ?!<br />";
	$ok=0;
      }
    /* check against robots */
    $robots=0; /* at least one anti-robot question needs to be answered */
    if(myisset('Robotproof0'))
      {
	if($_REQUEST['Robotproof0']!=42)
	  $ok=0;
	else
	  $robot=1;
      }
    else if(myisset('Robotproof1'))
      {
	if($_REQUEST['Robotproof1']!=35)
	  $ok=0;
	else
	  $robot=1;
      }
    else if(myisset('Robotproof2'))
      {
	if($_REQUEST['Robotproof2']!=28)
	  $ok=0;
	else
	  $robot=1;
      }
    else if(myisset('Robotproof3'))
      {
	if($_REQUEST['Robotproof3']!=21)
	  $ok=0;
	else
	  $robot=1;
      }
    else if(myisset('Robotproof4'))
      {
	if($_REQUEST['Robotproof4']!=14)
	  $ok=0;
	else
	  $robot=1;
      }
    if($robot==0)
      {
	echo "You answered the math question wrong. <br />\n";
	$ok=0;
      }

    /* everything ok, go ahead and create user */
    if($ok)
      {
	$r=DB_query("INSERT INTO User VALUES(NULL,".DB_quote_smart($_REQUEST["Rfullname"]).
		    ",".DB_quote_smart($_REQUEST["Remail"]).
		    ",".DB_quote_smart(md5($_REQUEST["Rpassword"])).
		    ",".DB_quote_smart($_REQUEST["Rtimezone"]).",NULL,NULL)");

	if($r)
	  {
	    /* Set session, so that new user doesn't need to log in */
	    $myname = DB_get_name('email',$_REQUEST['Remail']);
	    $_SESSION["name"] = $myname;

	    echo " Welcome to e-DoKo, you are now registered, please visit the".
	      " <a href=\"".$HOST.$INDEX."\">homepage</a> to continue.";
	  }
	else
	  echo " something went wrong, couldn't add you to the database, please contact $ADMIN_NAME at $ADMIN_EMAIL.";
      }
    else
      {
	echo "Couldn't register you. Please <a href=\"index.php?action=register\">try again</a>! </br />\n";
      }
  }
 else
   {
     /* No information for new user given, ouput a page for registration */
     echo "<p><br /><strong> IMPORTANT: passwords are going over the net as clear text, so pick an easy password. ".
       "No need to pick anything complicated here ;)<br />";
     echo "N.B. Your email address will be exposed to other players whom you play games with. ";
     echo "<br /><br /></strong></p>";
     ?>
        <form action="index.php?action=register" method="post">
          <fieldset>
            <legend>Register</legend>
             <table>
              <tr>
               <td><label for="Rfullname">Full name:</label></td>
	       <td><input type="text" id="Rfullname" name="Rfullname" size="20" maxlength="30" /> </td>
              </tr><tr>
               <td><label for="Remail">Email:</label></td>
	       <td><input type="text" id="Remail" name="Remail" size="20" maxlength="30" /></td>
              </tr><tr>
	       <td><label for="Rpassword">Password(will be displayed in cleartext on the next page):</label></td>
               <td><input type="password" id="Rpassword" name="Rpassword" size="20" maxlength="30" /></td>
              </tr><tr>
	       <td><label for="Rtimezone">Timezone:</label></td>
               <td>
<?php
               output_select_timezone("Rtimezone");
?>
	       </td>
              </tr><tr>
              </tr><tr>
<?php
              /* random number to select robotproof question */
	      $rand_number = mt_rand(0,3); /* to get numbers between 0 and 4  */
              $Robotproof = "Robotproof".$rand_number;
?>
		<td><label for="Robotproof">Please answer this question: <?php echo output_robotproof($rand_number); ?></label></td>
<?php
	 echo "<td><input type=\"text\" id=\"$Robotproof\" name=\"$Robotproof\" size=\"20\" maxlength=\"30\" /></td>\n";
?>
              </tr><tr>
               <td colspan="2"> <input type="submit" value="register" /></td>
              </tr>
             </table>
          </fieldset>
        </form>
<?php
   }
?>