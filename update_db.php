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

include_once("config.php");                /* needs to be first in list, since other includes use this */
include_once("./include/db.php");          /* database only */
include_once("./include/output.php");   /* the rest */
include_once("./include/functions.php");   /* the rest */

/* make sure that user has set all variables in config.php */
config_check();

/* open the database */
$return = DB_open();
if($return<0 && $return != -2) /* -2 = wrong DB version is ok */
  exit();

/* only callable via cron or CLI */
if(isset($_SERVER['REMOTE_ADDR']))
  exit();

$old_version = DB_get_version();
$current_version = 3;

if($old_version < $current_version)
  echo "Will upgrade your database now:\n";
else
  echo "You are up to date (version ${current_version}), nothing to do.\n";

switch($old_version)
  {
  case 0:
    /* add database for digesting */
    DB_query("CREATE TABLE digest_email (".
	     " `id` int(11) NOT NULL auto_increment,".
	     " `email` varchar(255) default null,".
	     " `create_date` timestamp NOT NULL default '0000-00-00 00:00:00',".
	     " `content` text,".
	     " UNIQUE KEY `id` (`id`),".
	     " index (email))");
    DB_query("UPDATE Version set version=1");
    echo "Upgraded to version 1.\n";
  case 1:
    /* add new rules */
    DB_query("ALTER TABLE Rulesets".
	     " ADD COLUMN `lowtrump` enum('poverty','cancel','none') default 'poverty' AFTER schweinchen");
    DB_query("ALTER TABLE Hand".
	     " MODIFY COLUMN `sickness` enum('wedding','nines','poverty','solo','lowtrump') default NULL");
    DB_query("ALTER TABLE Game".
	     " MODIFY COLUMN `status` enum('pre','play','gameover','cancel-timedout','cancel-nines','cancel-trump','cancel-noplay','cancel-lowtrump') default NULL");

    DB_query("UPDATE Version set version=2");
    echo "Upgraded to version 2.\n";
  case 2:
    DB_query("ALTER TABLE Comment".
	     " ADD COLUMN `game_id` int(11) default NULL AFTER play_id");
    DB_query("UPDATE Version set version=3");
    echo "Upgraded to version 3.\n";

  }

?>