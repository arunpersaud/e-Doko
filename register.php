<?php
error_reporting(E_ALL);

include_once("config.php");      
include_once("output.php");      /* html output only */
include_once("db.php");          /* database only */
include_once("functions.php");   /* the rest */

config_check();

if(DB_open()<0)
  {
    output_header();
    echo "Database error, can't connect... Please wait a while and try again. ".
      "If the problem doesn't go away feel free to contact $ADMIN_NAME at $ADMIN_EMAIL.";
    output_footer(); 
    exit(); 
  }


/* done major error checking, output header of HTML page */
output_header();

/* new user wants to register */
if(myisset("Rfullname","Remail","Rpassword","Rtimezone") )
  {
    $ok=1;
    if(DB_get_userid_by_name($_REQUEST["Rfullname"]))
      {
	echo "please chose another name<br />";
	$ok=0;
      }
    if(DB_get_userid_by_email($_REQUEST["Remail"]))
      {
	echo "this email address is already used ?!<br />";
	$ok=0;
      }
    if($ok)
      {
	$r=mysql_query("INSERT INTO User VALUES(NULL,".DB_quote_smart($_REQUEST["Rfullname"]).
		       ",".DB_quote_smart($_REQUEST["Remail"]).
		       ",".DB_quote_smart(md5($_REQUEST["Rpassword"])).
		       ",".DB_quote_smart($_REQUEST["Rtimezone"]).",NULL)"); 
	
	if($r)
	  echo " Welcome to e-DoKo, you are now registered, please visit the".
	    " <a href=\"$host\">homepage</a> to continue.";
	else
	  echo " something went wrong, couldn't add you to the database, please contact $ADMIN_NAME at $ADMIN_EMAIL.";
      }
   }
/* page for registration */
 else
   {
     echo "IMPORTANT: passwords are going over the net as clear text, so pick an easy password. No need to pick anything complicated here ;)<br /><br />";
     ?>
        <form action="register.php" method="post">
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
   }

output_footer();

DB_close();

/*
 *Local Variables: 
 *mode: php
 *mode: hs-minor
 *End:
 */
?>
