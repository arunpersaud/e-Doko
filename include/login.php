<?php
/* make sure that we are not called from outside the scripts, 
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

if(!myisset("email","password"))
  {
    "can't log you in";
  }
else
  {
    $email     = $_REQUEST["email"];
    $password  = $_REQUEST["password"];

    /* verify password and email */
    if(strlen($password)!=32)
      $password = md5($password);
    
    $ok  = 1;
    $myid = DB_get_userid('email-password',$email,$password);
    if(!$myid)
      $ok = 0;
    
    if($ok)
      {
	/* user information is ok */
	$myname = DB_get_name('email',$email);
	$_SESSION["name"] = $myname;
      }
  }
?>