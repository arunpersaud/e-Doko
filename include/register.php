<?php
/* Copyright 2006, 2007, 2008, 2009, 2010, 2011, 2012 Arun Persaud <arun@nubati.net>
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

/* new user wants to register */
if(myisset('Rfullname','Remail','Rtimezone') )
  {
    global $HOST,$INDEX;

    /* is this name already in use/ */
    $ok=1;
    if(DB_get_userid('name',$_REQUEST['Rfullname']))
      {
	echo 'please chose another name<br />';
	$ok=0;
      }
    /* check if email address is already used */
    if(DB_get_userid('email',$_REQUEST['Remail']))
      {
	echo 'this email address is already used ?!<br />';
	$ok=0;
      }
    /* need either openid or password */
    if(!myisset('Rpassword')  &&  !myisset('Ropenid'))
      {
	echo 'I need either a Password or an Openid url.<br />';
	$ok=0;
      }
    /* check for password length */
    if(myisset('Rpassword') && strlen(trim($_REQUEST['Rpassword']))==0 )
      {
	echo 'Password cannot be empty!<br />';
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
	echo 'You answered the math question wrong. <br />\n';
	$ok=0;
      }
    /* everything ok, go ahead and create user */
    if($ok)
      {
	if(myisset('Rpassword'))
	  {
	    $r=DB_query('INSERT INTO User VALUES(NULL,'.DB_quote_smart($_REQUEST['Rfullname']).
			','.DB_quote_smart($_REQUEST['Remail']).
			','.DB_quote_smart(md5($_REQUEST['Rpassword'])).
			','.DB_quote_smart($_REQUEST['Rtimezone']).',NULL,NULL)');
	  }
	else if(myisset('Ropenid'))
	  {
	    $password = $_REQUEST['Rfullname'].preg_replace('/([ ])/e', 'chr(rand(33,122))', '               ');
	    $r=DB_query('INSERT INTO User VALUES(NULL,'.DB_quote_smart($_REQUEST['Rfullname']).
			','.DB_quote_smart($_REQUEST['Remail']).
			','.DB_quote_smart(md5($password)).
			','.DB_quote_smart($_REQUEST['Rtimezone').',NULL,NULL)');
	    if($r)
	      {
		include_once('openid.php');
		$myid = DB_get_userid('email',$_REQUEST['Remail']);
		DB_AttachOpenID($_REQUEST['Ropenid'], $myid);
	      }
	  }
	else
	  {
	    echo 'Error during registration, please contact '.$ADMIN_NAME.' at '.$ADMIN_EMAIL;
	  }
	if($r)
	  {
	    /* Set session, so that new user doesn't need to log in */
	    $myname = DB_get_name('email',$_REQUEST['Remail']);
	    $_SESSION['name'] = $myname;

	    echo ' Welcome to e-DoKo, you are now registered, please visit the'.
	      ' <a href="'.$HOST.$INDEX.'">homepage</a> to continue.';
	  }
	else
	  echo " something went wrong, couldn't add you to the database, please contact $ADMIN_NAME at $ADMIN_EMAIL.";
      }
    else
      {
	echo 'Could not register you. Please <a href="index.php">try again</a>! </br />\n';
      }
  }
else
  {
    echo "Test test test... hmm, this page shouldn't really be here, should it? <a href=\"index.php\">Go back here :)</a> </br />\n";
  }
?>