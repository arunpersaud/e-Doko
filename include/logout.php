<?php
/* make sure that we are not called from outside the scripts, 
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

/* distroy the session */
session_unset();
session_destroy();
$_SESSION = array();
    
echo "<div class=\"message\"><span class=\"bigger\">You are now logged out!</span><br />\n".
"(<a href=\"$INDEX\">This will take you back to the home-page</a>)</div>";
?>