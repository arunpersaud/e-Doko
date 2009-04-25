<?php
/* make sure that we are not called from outside the scripts,
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

include_once('openid.php');

function escape($thing) {
    return htmlentities($thing);
}

/* check for openid stuff */
if($OPENIDPATH && myisset('openid_identity') && $_REQUEST['openid_identity']!='')
  {
    /* what openid is being used? */
    $openid_url = OpenIDUrlEncode($_REQUEST['openid_identity']);
    /* get the userid from the database, openids need to be registered within E-DoKo */
    $data = OpenIDVerify();
    $ok  = 0;

    /* verify ok? */
    if($data)
      {
	/* do we know this openid?*/
	$myid = DB_GetUserId($openid_url);

	if(!$myid)
	  {
	    /* openid unknown, perhaps not registered? */
	    echo "<p>Openid ok, but not registered with any account. If you have an account ".
	      "on E-DoKo, please log in and add your openid in your preferences first. </p>\n";


	    /* or perhaps a new user...*/
	    $email = $data['email'];
	    $name  = $data['fullname'];
	    echo "<p>If you wan to register a new account with this OpenID, please follow this ".
	      "<a href=\"index.php?action=register&amp;openid_url=".$openid_url.
	      "&amp;openidname=$name&amp;openidemail=$email\">link</a>.</p>";
	  }
	else
	  $ok=1;
      }

    if($ok)
      {
	/* user information is ok, set session variabel */
	$email  = DB_get_email('userid',$myid);
	$myname = DB_get_name('email',$email);
	$password =  DB_get_passwd_by_userid($myid);
	$_SESSION['name'] = $myname;
	$_SESSION['id']   = $myid;
	$_SESSION['pass'] = $password;
      }
  }
else if($OPENIDPATH && myisset('openid_url') && $_REQUEST['openid_url']!='')
  {
    OpenIDAskForVerification(OpenIDUrlEncode($_REQUEST['openid_url']));
  }
/* check if normal login information is present */
else if(myisset('email','password'))
  {
    $email     = $_REQUEST['email'];
    $password  = $_REQUEST['password'];

    /* verify password and email */
    if(strlen($password)!=32)
      $password = md5($password);

    $ok  = 1;
    $myid = DB_get_userid('email-password',$email,$password);
    if(!$myid)
      $ok = 0;

    if($ok)
      {
	/* user information is ok, set session variabel */
	$myname = DB_get_name('email',$email);
	$_SESSION['name'] = $myname;
	$_SESSION['id']   = $myid;
	$_SESSION['pass'] = $password;
      }
  }
else
  {
    echo "can't log you in... missing login information.";
  }
?>