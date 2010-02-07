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

error_reporting(E_ALL);

/* start a session, if it is not already running.
 * This way people don't have to log in all the times.
 * The session variables can also be read out from different
 * php scripts, so that the code can be easily split up across several files
 */
session_start();

include_once("config.php");                /* needs to be first in list, since other includes use this */
include_once("./include/output.php");      /* html output only */
include_once("./include/db.php");          /* database only */
include_once("./include/functions.php");   /* the rest */

/* make sure that user has set all variables in config.php */
config_check();

/* open the database */
if(DB_open()<0)
  {
    output_header();
    echo "Database error, can't connect... Please wait a while and try again. ".
      "If the problem doesn't go away feel free to contact $ADMIN_NAME at $ADMIN_EMAIL.";
    output_footer();
    exit();
  }

/* done major error checking, output5B header of HTML page */
output_header();

/* The rest of the file consists of handling user input.
 * The user sends information via html GET and POST variables,
 * and the action variable tells the prog what the user wants to do
 */
if(myisset("action"))
  $action=$_REQUEST['action'];
else
  $action=""; /* so that we can use a default option below */

switch($action)
  {
  case 'new':
    require './include/newgame.php';
    break;
  case 'cancel':
    require './include/cancelgame.php';
    break;
  case 'reminder':
    require './include/reminder.php';
    break;
  case 'logout':
    require './include/logout.php';
    require './include/welcome.php';
    break;
  case 'login':
    require './include/login.php';
    require './include/user.php';
    break;
  case 'register':
    require './include/register.php';
    break;
  case 'prefs':
    require './include/preferences.php';
    break;
  case 'game':
    require './include/game.php';
    break;
  case 'stats':
    if(isset($_SESSION["name"]))
      require './include/stats.php';
    else
      require './include/welcome.php';
    break;
  default:
    if(isset($_SESSION["name"]))
      require './include/user.php';
    else
      require './include/welcome.php';
  }

/* ask for login or display login info, needs to go at the end, so that we have the
 * session-variable already set.
 */
output_status();

output_footer();

DB_close();

/*
 *Local Variables:
 *mode: php
 *mode: hs-minor
 *End:
 */
?>


