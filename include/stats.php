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

/* turn warnings off (problem with two warnings in function.php */
error_reporting(!E_ALL & ~E_NOTICE);

$name  = $_SESSION["name"];
$email = DB_get_email('name',$name);

$myid = DB_get_userid('email',$email);
if(!$myid)
  return;

$PREF = DB_get_PREF($myid);
/* set language chosen in preferences, will become active on the next reload (see index.php)*/
$_SESSION['language'] = $PREF['language'];
set_language($PREF['language']);

DB_update_user_timestamp($myid);

display_user_menu($myid);

/* check if cached version exist */
if( !$content = getCache("cache/stats.html",60*60*24) )
{
  /* start caching */
  ob_start();

  /* start statistics*/
  echo "<div class=\"user wide\">\n";

  echo "<p>Generated ".date("Y-m-d H:i:s")." (server time) </p>";

  /* total number of games */
  echo "<p>The number of finished games on this server is: ";
  $r = DB_query_array("SELECT COUNT(*) from Game".
		      " WHERE status='gameover'");
  $GameN =  $r[0];
  echo " $GameN </p>\n";

  echo "<p>The contra party wins in ";
  $result = DB_query("SELECT COUNT(*) from Score".
		     " WHERE score='againstqueens'");
  while( $r = DB_fetch_array($result))
    echo $r[0];
  echo " games.</p>\n";

  /* longest and shortest game */
  $r=DB_query("SELECT timediff(mod_date,create_date) ,session,id".
	    " FROM Game WHERE status='gameover'".
	      " ORDER BY time_to_sec(timediff(mod_date,create_date)) ASC LIMIT 1");

  if($r)
    {
      $short= DB_fetch_array($r);
      $names = DB_get_all_names_by_gameid($short[2]);
      echo "<p> The shortest game took only ".$short[0]." hours and was played by  ".join(", ",$names).".<br />\n";
    }

  $r=DB_query("SELECT datediff(mod_date,create_date) ,session,id".
	      " FROM Game WHERE status='gameover'".
	      " ORDER BY time_to_sec(timediff(mod_date,create_date)) DESC LIMIT 1");
  if($r)
    {
      $long= DB_fetch_array($r);
      echo "The longest game took ".$long[0]." days.</p>\n";
    }

  $r=DB_query("SELECT COUNT(*) as c, session, id FROM Game ".
	      " GROUP BY session ORDER BY c DESC LIMIT 1");
  if($r)
    {
      $long  = DB_fetch_array($r);
      $names = DB_get_all_names_by_gameid($long[2]);
      echo "<p>The longest session is session ".$long[1]." with ".$long[0].
	" games played by ".join(", ",$names).".</p>\n";
    }


  /* number of solos */
  $result = DB_query_array_all("SELECT type,COUNT(*) as c from Game".
			       " WHERE status='gameover'".
			       " GROUP BY type".
			       " ORDER BY c DESC");
  array_unshift($result,array("Type","Frequency"));
  echo output_table($result,"Game types","stats");

  /* break up solos in types */
  $result = DB_query_array_all("SELECT solo,COUNT(*) as c from Game".
			       " WHERE status='gameover'".
			       " AND type='solo'".
			       " GROUP BY solo".
			       " ORDER BY c DESC");
  array_unshift($result,array("Type","Frequency"));
  echo output_table($result,"Kind of solos","stats");

  /*
 2 top user mit maximaler quote an solo (min 10 games)

 top scoring game: winning players

 game with the same cards: show 3 at random:
 player who won, points, what kind of game
 select g1.id, g2.id from game g1 left join game g2 on g1.randomnumbers=g2.randomnumbers where g1.id<g2.id order by g1.id
 select id from game where randomnumbers like "blablabl%"; the % is like .* in regexp
 select id,type,solo,status from game where id in (select id from game where randomnumbers in (select randomnumbers from game where id=27));
  */

  /* number of calls*/
  $result = DB_query_array_all("SELECT CONCAT_WS(' ',party,IFNULL(point_call,'no call')),COUNT(*) from Hand".
			       " LEFT JOIN Game on Game.id=Hand.game_id ".
			       " WHERE Game.status='gameover'".
			       " GROUP BY party,point_call");
  array_unshift($result,array("call","Frequency"));
  echo output_table($result,"Kind of call","stats");


  /* most reminders */
  $result = DB_query_array_all("SELECT fullname, COUNT(*)  /" .
			       "      (SELECT COUNT(*) FROM Hand".
			       "       WHERE user_id=User.id) as c".
			       " FROM Reminder".
			       " LEFT JOIN User ON User.id=user_id".
			       " GROUP BY user_id".
			       " ORDER BY c DESC LIMIT 5" );
  array_unshift($result,array("Name","Reminders"));
  echo output_table($result,"Most reminders per game","stats");

  /* fox */
  $result = DB_query_array_all("SELECT fullname, COUNT(*) /" .
			       "      (SELECT COUNT(*) FROM Hand".
			       "       WHERE user_id=User.id) as c".
			       " FROM Score".
			       " LEFT JOIN User ON User.id=winner_id".
			       " WHERE score='fox'".
			       " GROUP BY winner_id".
			       " ORDER BY c DESC LIMIT 5" );
  array_unshift($result,array("Name","Number of foxes caught"));
  echo output_table($result,"Most caught foxes","stats");

  $result = DB_query_array_all("SELECT fullname, COUNT(*) /" .
			       "      (SELECT COUNT(*) FROM Hand".
			       "       WHERE user_id=User.id) as c".
			       " FROM Score".
			       " LEFT JOIN User ON User.id=looser_id".
			       " WHERE score='fox'".
			       " GROUP BY looser_id".
			       " ORDER BY c DESC LIMIT 5" );
  array_unshift($result,array("Name","Number of foxes lost"));
  echo output_table($result,"Lost foxes (most)","stats");

  $result = DB_query_array_all("SELECT fullname, COUNT(*) /" .
			       "      (SELECT COUNT(*) FROM Hand".
			       "       WHERE user_id=User.id) as c".
			       " FROM Score".
			       " LEFT JOIN User ON User.id=looser_id".
			       " WHERE score='fox'".
			       " GROUP BY looser_id".
			       " ORDER BY c ASC LIMIT 5" );
  array_unshift($result,array("Name","Number of foxes lost"));
  echo output_table($result,"Lost foxes (least)","stats");

  /* which position wins the most tricks  */
  $result = DB_query_array_all("SELECT CASE winner ".
			       "   WHEN 1 THEN 'left' ".
			       "   WHEN 2 THEN 'top' ".
			       "   WHEN 3 THEN 'right' ".
			       "   WHEN 4 THEN 'bottom' END,".
			       " COUNT(*) AS c FROM Trick".
			       " GROUP BY winner ".
			       " HAVING LENGTH(winner)>0  ".
			       " ORDER BY winner ASC " );
  array_unshift($result,array("Position","Number of tricks"));
  echo output_table($result,"Tricks at the table","stats");

  /*
 does the party win more often if they start

 echo "<p>The party playing first wins in";
 $result = mysql_query("SELECT COUNT(*) from Score".
 " LEFT JOIN Game ON Game.id=game_id".
 " WHERE score='againstqueens'".
 " AND Game.status='gameover'".
 " AND Game.type<>'solo'");
 while( $r = mysql_fetch_array($result,MYSQL_NUM))
 echo $r[1]." (".$r[0].") <br />\n";
 echo " games</p>\n";
  */
  $result = generate_global_score_table();
  array_unshift($result,array('Name','Average score per game','Total Points','Number of games', 'Active games',
			      'Response Time [min]','Number of solos','Solos/game'));
  echo output_table($result,'Players (need more than 10 games)','stats','ScoreTable');

  /*
   * how often is the last trick a non-trump trick
   */

  /* needs this so that all tables are within the div and don't float around */
  echo "<p style=\"clear:both;\">&nbsp;</p>\n";

  echo "</div>\n"; /* end output */

  /* write file to cache */
  $content = ob_get_contents();
  ob_end_clean();
  createCache($content,"cache/stats.html");
}

echo $content;

?>

