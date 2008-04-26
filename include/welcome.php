<?php
/* make sure that we are not called from outside the scripts, 
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

/* this outputs the default home page with some extra statistics on it */

$pre[0]=0;$game[0]=0;$done[0]=0;
$r=mysql_query("SELECT COUNT(id) FROM Game GROUP BY status");
if($r) {
  $pre  = mysql_fetch_array($r,MYSQL_NUM);
  $game = mysql_fetch_array($r,MYSQL_NUM);
  $done = mysql_fetch_array($r,MYSQL_NUM);
 }

$r=mysql_query("SELECT AVG(datediff(mod_date,create_date)) FROM Game where status='gameover' ");
if($r)
  $avgage= mysql_fetch_array($r,MYSQL_NUM);
 else
   $avgage[0]=0;

output_home_page($pre[0],$game[0],$done[0],$avgage[0]);

?>