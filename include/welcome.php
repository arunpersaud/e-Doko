<?php
/* make sure that we are not called from outside the scripts, 
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

/* this outputs the default home page with some extra statistics on it */

$pre[0]=0;$game[0]=0;$done[0]=0;
$r=DB_query("SELECT COUNT(id) FROM Game GROUP BY status");
if($r) {
  $pre  = DB_fetch_array($r);
  $game = DB_fetch_array($r);
  $done = DB_fetch_array($r);
 }

$r=DB_query("SELECT AVG(datediff(mod_date,create_date)) FROM Game where status='gameover' ");
if($r)
  $avgage= DB_fetch_array($r);
 else
   $avgage[0]=0;

output_home_page($pre[0],$game[0],$done[0],$avgage[0]);

?>