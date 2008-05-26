<?php
/* make sure that we are not called from outside the scripts, 
 * use a variable defined in config.php to check this
 */
if(!isset($HOST))
  exit;

$name  = $_SESSION["name"];
$email = DB_get_email('name',$name);

$myid = DB_get_userid('email',$email);
if(!$myid)
  return;

output_status();

$PREF = DB_get_PREF($myid);

DB_update_user_timestamp($myid);

display_user_menu();

/* start statistics*/
echo "<div class=\"user\">\n";

/* always: if player logged in: add link to cards  */

/* total number of games */
echo "<p>The number of finished games on this server is: ";
$r = DB_query_array("SELECT COUNT(*) from Game".
		    " WHERE status='gameover'");
$GameN =  $r[0];
echo " $GameN </p>\n";

echo "<p>The contra party wins in ";
$result = DB_query("SELECT COUNT(*) from Score".
		   " LEFT JOIN Game ON Game.id=game_id".
		   " WHERE score='againstqueens'".
		   " AND Game.status='gameover'");
while( $r = DB_fetch_array($result))
  echo $r[0];
echo " games.</p>\n";


/* number of solos */
echo "<p>These kind of games have been played this often: <br />";
$result = DB_query_array_all("SELECT type,COUNT(*) as c from Game".
			     " WHERE status='gameover'".
			     " GROUP BY type".
			     " ORDER BY c DESC");
array_unshift($result,array("Type","Frequency"));
echo output_table($result,"stats");
echo " </p>\n";

/* break up solos in types */
echo "<p>These kind of solos have been played this often: <br />";
$result = DB_query_array_all("SELECT solo,COUNT(*) as c from Game".
			     " WHERE status='gameover'".
			     " AND type='solo'".
			     " GROUP BY solo".
			     " ORDER BY c DESC");
array_unshift($result,array("Type","Frequency"));
echo output_table($result,"stats");
echo "</p>\n";

/*
 2 top user mit maximaler quote an solo (min 10 games)
 
 top scoring game: winning players
 
 game with the same cards: show 3 at random:
 player who won, points, what kind of game
 select g1.id, g2.id from game g1 left join game g2 on g1.randomnumbers=g2.randomnumbers where g1.id<g2.id order by g1.id
 select id from game where randomnumbers like "blablabl%"; the % is like .* in regexp
 select id,type,solo,status from game where id in (select id from game where randomnumbers in (select randomnumbers from game where id=27));
 
*/
echo "<p>Most extra points (doko, fox, karlchen) in a single game:<br />\n";
$result = DB_query_array_all("SELECT fullname,COUNT(*) as c FROM Score".
			     " LEFT JOIN User ON User.id=winner_id" .
			     " WHERE score IN ('fox','doko','karlchen')".
			     " GROUP BY game_id,fullname".
			     " ORDER BY c DESC LIMIT 3" );
array_unshift($result,array("Name","Points"));
echo output_table($result,"stats");
echo "</p>\n";

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
    echo "The longest session is session ".$long[1]." with ".$long[0].
      " games played by ".join(", ",$names).".</p>\n";
  }

/* most reminders */
echo "<p>These players got the most reminders per game:<br />\n";
$result = DB_query_array_all("SELECT fullname, COUNT(*)  /" .
			     "      (SELECT COUNT(*) FROM Hand".
			     "       WHERE user_id=User.id) as c".
			     " FROM Reminder".
			     " LEFT JOIN User ON User.id=user_id".
			     " GROUP BY user_id".
			     " ORDER BY c DESC LIMIT 5" );
array_unshift($result,array("Name","Reminders"));
echo output_table($result,"stats");
echo "</p>\n";

/* fox */
echo "<p>These players caught the most foxes per game:<br />\n";
$result = DB_query_array_all("SELECT fullname, COUNT(*) /" .
			     "      (SELECT COUNT(*) FROM Hand".
			     "       WHERE user_id=User.id) as c".
			     " FROM Score".
			     " LEFT JOIN User ON User.id=winner_id".
			     " WHERE score='fox'".
			     " GROUP BY winner_id".
			     " ORDER BY c DESC LIMIT 5" );
array_unshift($result,array("Name","Number of foxes caught"));
echo output_table($result,"stats");
echo "</p>\n";

echo "<p>These players lost their fox most often per game:<br />\n";
$result = DB_query_array_all("SELECT fullname, COUNT(*) /" .
			     "      (SELECT COUNT(*) FROM Hand".
			     "       WHERE user_id=User.id) as c".
			     " FROM Score".
			     " LEFT JOIN User ON User.id=looser_id".
			     " WHERE score='fox'".
			     " GROUP BY looser_id".
			     " ORDER BY c DESC LIMIT 5" );
array_unshift($result,array("Name","Number of foxes lost"));
echo output_table($result,"stats");
echo "</p>\n";

echo "<p>These players lost their fox least often per game:<br />\n";
$result = DB_query_array_all("SELECT fullname, COUNT(*) /" .
			     "      (SELECT COUNT(*) FROM Hand".
			     "       WHERE user_id=User.id) as c".
			     " FROM Score".
			     " LEFT JOIN User ON User.id=looser_id".
			     " WHERE score='fox'".
			     " GROUP BY looser_id".
			     " ORDER BY c ASC LIMIT 5" );
array_unshift($result,array("Name","Number of foxes lost"));
echo output_table($result,"stats");
echo "</p>\n";

/* which position wins the most tricks  */
echo "<p>Which positions at the table make the most tricks:<br />\n";
$result = DB_query("SELECT COUNT(*) AS c,winner FROM Trick".
		   " GROUP BY winner".
		   " ORDER BY winner ASC " );
$r = DB_fetch_array($result);
if($r[1]==NULL) /* ongoing games, no winner yet */
  $r = DB_fetch_array($result);
echo " left ".$r[0]." <br />\n";
$r = DB_fetch_array($result);
echo " top ".$r[0]." <br />\n";
$r = DB_fetch_array($result);
echo " right ".$r[0]." <br />\n";
$r = DB_fetch_array($result);
echo " bottom ".$r[0]." <br />\n";
echo "</p>\n";

/* most games */
echo "<p>Most games played on the server:<br />\n";
$result = DB_query_array_all("SELECT fullname, COUNT(*) as c  " .
		   " FROM Hand".
		   " LEFT JOIN User ON User.id=user_id".
		   " GROUP BY user_id".
		   " ORDER BY c DESC LIMIT 7" );
array_unshift($result,array("Name","Number of games"));
echo output_table($result,"stats");
echo "</p>\n";

/* most active games */
echo "<p>These players are involved in this many active games:<br />\n";
$result = DB_query_array_all("SELECT fullname, COUNT(*) as c  " .
		   " FROM Hand".
		   " LEFT JOIN User ON User.id=user_id".
		   " LEFT JOIN Game ON Game.id=game_id".
		   " WHERE Game.status<>'gameover'".
		   " GROUP BY user_id".
		   " ORDER BY c DESC LIMIT 7" );
array_unshift($result,array("Name","Number of active games"));
echo output_table($result,"stats");
echo "</p>\n";


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
echo "<p>Points/game (you need at least 10 games to be in this statistic): <br />\n";
generate_global_score_table();
echo "</p>\n";
/*
 how often is the last trick a non-trump trick
*/

echo "</div>\n"; /* end output */

?>

