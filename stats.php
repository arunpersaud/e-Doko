<?php
error_reporting(E_ALL);

include_once("config.php");
include_once("./include/output.php");      /* html output only */
include_once("./include/db.php");          /* database only */
include_once("./include/functions.php");   /* the rest */

config_check();

if(DB_open()<0)
  {
    output_header();
    echo "Database error, can't connect... Please wait a while and try again. ".
      "If the problem doesn't go away feel free to contact $ADMIN_NAME at $ADMIN_EMAIL.";
    output_footer();
    exit();
  }

/* start a session, if it is not already running */
session_start();
/* done major error checking, output header of HTML page */
output_header();

/* check if we want to logout */
if(myisset("logout"))
  {
    session_unset();
    session_destroy();
    $_SESSION = array();
    echo "<div class=\"message\"><span class=\"bigger\">You are now logged out!</span><br />\n".
      "(<a href=\"$INDEX\">This will take you back to the home-page</a>)</div>";
  }
/* user status page */
else if( isset($_SESSION["name"]) )
   {
     $name = $_SESSION["name"];
     $email     = DB_get_email('name',$name);
     $password  = DB_get_passwd_by_name($name);

     /* verify password and email */
     if(strlen($password)!=32)
       $password = md5($password);

     $ok  = 1;
     $myid = DB_get_userid('email-password',$email,$password);
     if(!$myid)
       $ok = 0;

     if($ok)
       {
	 output_status();

	 DB_get_PREF($myid);

	 DB_update_user_timestamp($myid);

	 display_user_menu();

	 /* start statistics*/
	 echo "<div class=\"user\">\n";

/* always: if player logged in: add link to cards  */

	 /* total number of games */
	 echo "<p>The number of finished games on this server is: ";
	 $result = mysql_query("SELECT COUNT(*) from Game".
			       " WHERE status='gameover'");
	 $r = mysql_fetch_array($result,MYSQL_NUM);
	 $GameN =  $r[0];
	 echo " $GameN </p>\n";

	 echo "<p>The contra party wins in ";
	 $result = mysql_query("SELECT COUNT(*) from Score".
			       " LEFT JOIN Game ON Game.id=game_id".
			       " WHERE score='againstqueens'".
			       " AND Game.status='gameover'");
	 while( $r = mysql_fetch_array($result,MYSQL_NUM))
	   echo $r[0];
	 echo " games.</p>\n";


	 /* number of solos */
	 echo "<p>These kind of games have been played this often: <br />";
	 $result = mysql_query("SELECT COUNT(*) as c,type from Game".
			       " WHERE status='gameover'".
			       " GROUP BY type".
			       " ORDER BY c DESC");
	 while( $r = mysql_fetch_array($result,MYSQL_NUM))
	   echo "".$r[1]." (".$r[0].") <br />";
	 echo " </p>\n";

	 /* break up solos in types */
	 echo "<p>These kind of solos have been played this often: <br />";
	 $result = mysql_query("SELECT COUNT(*) as c,solo from Game".
			       " WHERE status='gameover'".
			       " AND type='solo'".
			       " GROUP BY solo".
			       " ORDER BY c DESC");
	 while( $r = mysql_fetch_array($result,MYSQL_NUM))
	   echo "".$r[1]." (".$r[0].")<br />";
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
	 $result = mysql_query("SELECT COUNT(*) as c,fullname FROM Score".
			       " LEFT JOIN User ON User.id=winner_id" .
			       " WHERE score IN ('fox','doko','karlchen')".
			       " GROUP BY game_id,fullname".
			       " ORDER BY c DESC LIMIT 3" );
	 while( $r = mysql_fetch_array($result,MYSQL_NUM))
	   echo $r[1]." (".$r[0].") <br />\n";
	 echo "</p>\n";

	 /* longest and shortest game */
	 $r=mysql_query("SELECT timediff(mod_date,create_date) ,session,id".
			" FROM Game WHERE status='gameover'".
			" ORDER BY time_to_sec(timediff(mod_date,create_date)) ASC LIMIT 1");

	 if($r)
	   {
	     $short= mysql_fetch_array($r,MYSQL_NUM);
	     $names = DB_get_all_names_by_gameid($short[2]);
	     echo "<p> The shortest game took only ".$short[0]." hours and was played by  ".join(", ",$names).".<br />\n";
	   }

	 $r=mysql_query("SELECT datediff(mod_date,create_date) ,session,id".
			" FROM Game WHERE status='gameover'".
			" ORDER BY time_to_sec(timediff(mod_date,create_date)) DESC LIMIT 1");
	 if($r)
	   {
	     $long= mysql_fetch_array($r,MYSQL_NUM);
	     echo "The longest game took ".$long[0]." days.</p>\n";
	   }

	 $r=mysql_query("SELECT COUNT(*) as c, session, id FROM Game ".
			" GROUP BY session ORDER BY c DESC LIMIT 1");
	 if($r)
	   {
	     $long  = mysql_fetch_array($r,MYSQL_NUM);
	     $names = DB_get_all_names_by_gameid($long[2]);
	     echo "The longest session is session ".$long[1]." with ".$long[0].
	       " games played by ".join(", ",$names).".</p>\n";
	   }

	 /* most reminders */
	 echo "<p>These players got the most reminders per game:<br />\n";
	 $result = mysql_query("SELECT COUNT(*)  /" .
			       "      (SELECT COUNT(*) FROM Hand".
			       "       WHERE user_id=User.id) as c,".
			       " fullname FROM Reminder".
			       " LEFT JOIN User ON User.id=user_id".
			       " GROUP BY user_id".
			       " ORDER BY c DESC LIMIT 5" );
	 while( $r = mysql_fetch_array($result,MYSQL_NUM))
	   echo $r[1]." (".$r[0].") <br />\n";
	 echo "</p>\n";

	 /* fox */
	 echo "<p>These players caught the most foxes per game:<br />\n";
	 $result = mysql_query("SELECT COUNT(*) /" .
			       "      (SELECT COUNT(*) FROM Hand".
			       "       WHERE user_id=User.id) as c,".
			       " fullname".
			       " FROM Score".
			       " LEFT JOIN User ON User.id=winner_id".
			       " WHERE score='fox'".
			       " GROUP BY winner_id".
			       " ORDER BY c DESC LIMIT 5" );
	 while( $r = mysql_fetch_array($result,MYSQL_NUM))
	   echo $r[1]." (".$r[0].") <br />\n";
	 echo "</p>\n";

	 echo "<p>These players lost their fox most often per game:<br />\n";
	 $result = mysql_query("SELECT COUNT(*) /" .
			       "      (SELECT COUNT(*) FROM Hand".
			       "       WHERE user_id=User.id) as c,".
			       " fullname".
			       " FROM Score".
			       " LEFT JOIN User ON User.id=looser_id".
			       " WHERE score='fox'".
			       " GROUP BY looser_id".
			       " ORDER BY c DESC LIMIT 5" );
	 while( $r = mysql_fetch_array($result,MYSQL_NUM))
	   echo $r[1]." (".$r[0].") <br />\n";
	 echo "</p>\n";

	 echo "<p>These players lost their fox least often per game:<br />\n";
	 $result = mysql_query("SELECT COUNT(*) /" .
			       "      (SELECT COUNT(*) FROM Hand".
			       "       WHERE user_id=User.id) as c,".
			       " fullname".
			       " FROM Score".
			       " LEFT JOIN User ON User.id=looser_id".
			       " WHERE score='fox'".
			       " GROUP BY looser_id".
			       " ORDER BY c ASC LIMIT 5" );
	 while( $r = mysql_fetch_array($result,MYSQL_NUM))
	   echo $r[1]." (".$r[0].") <br />\n";
	 echo "</p>\n";

	 /* which position wins the most tricks  */
	 echo "<p>Which positions at the table make the most tricks:<br />\n";
	 $result = mysql_query("SELECT COUNT(*) AS c,winner FROM Trick".
			       " GROUP BY winner".
			       " ORDER BY winner ASC " );
	 $r = mysql_fetch_array($result,MYSQL_NUM);
	 if($r[1]==NULL) /* ongoing games, no winner yet */
	   $r = mysql_fetch_array($result,MYSQL_NUM);
	 echo " left ".$r[0]." <br />\n";
	 $r = mysql_fetch_array($result,MYSQL_NUM);
	 echo " top ".$r[0]." <br />\n";
	 $r = mysql_fetch_array($result,MYSQL_NUM);
	 echo " right ".$r[0]." <br />\n";
	 $r = mysql_fetch_array($result,MYSQL_NUM);
	 echo " bottom ".$r[0]." <br />\n";
	 echo "</p>\n";

	 /* most games */
	 echo "<p>Most games played on the server:<br />\n";
	 $result = mysql_query("SELECT COUNT(*) as c,  " .
			       " fullname FROM Hand".
			       " LEFT JOIN User ON User.id=user_id".
			       " GROUP BY user_id".
			       " ORDER BY c DESC LIMIT 7" );
	 while( $r = mysql_fetch_array($result,MYSQL_NUM))
	   echo $r[1]." (".$r[0].") <br />\n";
	 echo "</p>\n";

	 /* most active games */
	 echo "<p>These players are involved in this many active games:<br />\n";
	 $result = mysql_query("SELECT COUNT(*) as c,  " .
			       " fullname FROM Hand".
			       " LEFT JOIN User ON User.id=user_id".
			       " LEFT JOIN Game ON Game.id=game_id".
			       " WHERE Game.status<>'gameover'".
			       " GROUP BY user_id".
			       " ORDER BY c DESC LIMIT 7" );
	 while( $r = mysql_fetch_array($result,MYSQL_NUM))
	   echo $r[1]." (".$r[0].") <br />\n";
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


	 /*
	  how often is the last trick a non-trump trick
	 */

	 echo "</div>\n"; /* end output */
       }
   }
 else
   {
     /* send them back to the login page */
    echo "<div class=\"message\"><span class=\"bigger\">You need to log in!</span><br />\n".
      "(<a href=\"$INDEX\">This will take you back to the login-page</a>)</div>";
   }

output_footer();
DB_close();

/*
 *Local Variables:
 *mode: php
 *mode: hs-minor
 *End:
 */
?>


