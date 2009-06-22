<?php
error_reporting(E_ALL);

include_once("config.php");                /* needs to be first in list, since other includes use this */
include_once("./include/db.php");          /* database only */
include_once("./include/functions.php");   /* the rest */

/* make sure that user has set all variables in config.php */
config_check();

/* open the database */
if(DB_open()<0)
  exit();

/* only callable via cron or CLI */
if(isset($_SERVER['REMOTE_ADDR']))
  exit();

$old_version = DB_get_version();
$current_version = 1;

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
  }

?>