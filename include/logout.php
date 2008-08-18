<?php
/* make sure that we are not called from outside the scripts,
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

/* to log a player out, all we need to do is to distroy the session */
session_unset();
session_destroy();
$_SESSION = array();

?>