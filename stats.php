<?php
error_reporting(E_ALL);

include_once("config.php");
include_once("output.php");      /* html output only */
include_once("db.php");          /* database only */
include_once("functions.php");   /* the rest */

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
    echo "you are now logged out!";
  }
/* user status page */
else if( isset($_SESSION["name"]) )
   {
     $name = $_SESSION["name"];
     $email     = DB_get_email_by_name($name);
     $password  = DB_get_passwd_by_name($name);


     /* verify password and email */
     if(strlen($password)!=32)
       $password = md5($password);

     $ok  = 1;
     $myid = DB_get_userid_by_email_and_password($email,$password);
     if(!$myid)
       $ok = 0;

     if($ok)
       {
	 DB_get_PREF($myid);

	 $time     = DB_get_user_timestamp($myid);
	 $unixtime = strtotime($time);

	 $offset   = DB_get_user_timezone($myid);
	 $zone     = return_timezone($offset);
	 date_default_timezone_set($zone);

	 output_status($name);

	 echo "<div class=\"lastlogin\">last login: ".date("r",$unixtime)."</div>";

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

	 /* number of solos */
	 echo "<p>These kind of games have been played this often: <br />";
	 $result = mysql_query("SELECT COUNT(*) as c,type from Game".
			       " WHERE status='gameover'".
			       " GROUP BY type".
			       " ORDER BY c DESC");
	 while( $r = mysql_fetch_array($result,MYSQL_NUM))
	   echo "".$r[1]." (".$r[0].")<br />";
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
	 echo "<p>Most extra points in a game::<br />\n";
	 $result = mysql_query("SELECT COUNT(*) as c,fullname FROM Score".
			       " LEFT JOIN User ON User.id=winner_id" .
			       " WHERE score IN ('fox','doko','karlchen')".
			       " GROUP BY fullname".
			       " ORDER BY c DESC LIMIT 1" );
	 while( $r = mysql_fetch_array($result,MYSQL_NUM))
	   echo $r[1]." (".$r[0].") <br />\n";
	 echo "</p>\n";

	 /* longest and shortest game */
	 $r=mysql_query("SELECT MIN(datediff(mod_date,create_date)),session".
			" FROM Game WHERE status='gameover' GROUP BY status");
	 if($r)
	   {
	     $short= mysql_fetch_array($r,MYSQL_NUM);
	     echo "<p> The shortest game took only ".$short[0]." days.<br />\n";
	   }

	 $r=mysql_query("SELECT MAX(datediff(mod_date,create_date)),session".
			" FROM Game where status='gameover' GROUP BY status");
	 if($r)
	   {
	     $long= mysql_fetch_array($r,MYSQL_NUM);
	     echo "The longest game took ".$long[0]." days.</p>\n";
	   }

	 /* most reminders */
	 echo "<p>These players got the most reminders:<br />\n";
	 $result = mysql_query("SELECT COUNT(*) as c,fullname from Reminder".
			       " LEFT JOIN User ON User.id=user_id".
			       " GROUP BY user_id".
			       " ORDER BY c DESC LIMIT 3" );
	 while( $r = mysql_fetch_array($result,MYSQL_NUM))
	   echo $r[1]." (".$r[0].") <br />\n";
	 echo "</p>\n";

	 /* fox */
	 echo "<p>These players caught the most foxes:<br />\n";
	 $result = mysql_query("SELECT COUNT(*) as c,fullname from Score".
			       " LEFT JOIN User ON User.id=winner_id".
			       " WHERE score='fox'".
			       " GROUP BY winner_id".
			       " ORDER BY c DESC LIMIT 2" );
	 while( $r = mysql_fetch_array($result,MYSQL_NUM))
	   echo $r[1]." (".$r[0].") <br />\n";
	 echo "</p>\n";

	 echo "<p>These players lost their fox most often:<br />\n";
	 $result = mysql_query("SELECT COUNT(*) as c,fullname from Score".
			       " LEFT JOIN User ON User.id=looser_id".
			       " WHERE score='fox'".
			       " GROUP BY looser_id".
			       " ORDER BY c DESC LIMIT 2" );
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

	 echo "<p>The contra party wins in ";
	 $result = mysql_query("SELECT COUNT(*) from Score".
			       " LEFT JOIN Game ON Game.id=game_id".
			       " WHERE score='againstqueens'".
			       " AND Game.status='gameover'".
			       " AND Game.type<>'solo'");
	 while( $r = mysql_fetch_array($result,MYSQL_NUM))
	   echo $r[0];
	 echo " games</p>\n";

	 /*
	  how often is the last trick a non-trump trick
	 */

	 echo "</div>\n"; /* end output */
       }
   }
 else
   {
     /* send them back to the login page */
     echo "<p>Please log in</p>";
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


