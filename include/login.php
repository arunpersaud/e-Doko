<?php
/* Copyright 2006, 2007, 2008, 2009, 2010, 2011, 2012, 2013, 2014 Arun Persaud <arun@nubati.net>
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

    $ok  = 1;
    $myid = DB_get_userid('email',$email);

    $result = verify_password($email, $password);
    switch($result)
      {
      case 0:
	/* user information is ok, set session variable */
	$myname         = DB_get_name('email',$email);
	$hashedpassword = DB_get_passwd_by_userid($myid);
	$_SESSION['name'] = $myname;
	$_SESSION['id']   = $myid;
	$_SESSION['pass'] = $hashedpassword;
	break;
      case 1:
	echo "Can't find you in the database\n";
	break;
      case 2:
	echo "Problem creating password hash, please contact $ADMIN at $ADMIN_EMAIL\n";
	break;
      }
  }
else
  {
    echo "can't log you in... missing login information.";
  }
?>