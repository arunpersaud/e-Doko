<?php
/* make sure that we are not called from outside the scripts, 
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

output_status();
/* user needs to be logged in to do this */
if( isset($_SESSION["name"]) )
  {
    $names = DB_get_all_names();
    echo "<div class=\"user\">\n";
    output_form_for_new_game($names);
    echo "</div>\n";
    display_user_menu();
  }
 else
   {
     echo "<div class=\"message\">Please <a href=\"$INDEX\">log in</a>.</div>";
   }
?>