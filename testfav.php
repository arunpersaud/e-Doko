<?php
/* Copyright 2013, 2014 Arun Persaud <arun@nubati.net>
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

error_reporting(E_ALL);

session_start();

include_once("config.php");                /* needs to be first in list, since other includes use this */
include_once("./include/db.php");          /* database only */

/* open the database */
$DBopen = DB_open();
if($DBopen<0)
  exit();

if(isset($_SESSION['id']))
  {
    $myid = $_SESSION['id'];

    $result = DB_query_array("SELECT count(player) from Game ".
			     " WHERE player=".DB_quote_smart($myid).
			     " AND ( status='pre' OR  status='play' ) ");
    if($result[0])
      $ret=array('turn'=>'yes');
    else
      $ret=array('turn'=>'no');

    echo json_encode($ret);
  };

DB_close();

/*
 *Local Variables:
 *mode: php
 *mode: hs-minor
 *End:
 */
?>
