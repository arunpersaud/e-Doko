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
    $ok=1;
    if(DB_get_userid('name',$_REQUEST["Rfullname"]))
      {
	echo "please chose another name<br />";
	$ok=0;
      }
    if(DB_get_userid('email',$_REQUEST["Remail"]))
      {
	echo "this email address is already used ?!<br />";
	$ok=0;
      }
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
	    
	    echo "myname $myname --";
	    
	    echo " Welcome to e-DoKo, you are now registered, please visit the".
	      " <a href=\"".$HOST.$INDEX."\">homepage</a> to continue.";
	  }
	else
	  echo " something went wrong, couldn't add you to the database, please contact $ADMIN_NAME at $ADMIN_EMAIL.";
      }
   }
/* page for registration */
 else
   {
     echo "IMPORTANT: passwords are going over the net as clear text, so pick an easy password. No need to pick anything complicated here ;)<br /><br />";
     ?>
        <form action="index.php?action=register" method="post">
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
                     <option value="Europe/London">Berlin</option>
                     <option value="Europe/Berlin">Berlin</option>
                     <option value="America/Vancouver">Berkeley</option>
                     <option value="Pacific/Auckland">Wellington</option>
		  </select>
	         (If your timezone is not listed, just select whatever you want and email the admin your correct time zone.)
	       </td>
              </tr><tr>
               <td colspan="2"> <input type="submit" value="register" /></td>
             </table>
          </fieldset>
        </form>
<?php
   }


?>