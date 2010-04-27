<?php
/* Copyright 2010 Arun Persaud <arun@nubati.net>
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

echo '
<div class="about">

<div class="code">
<h3>Coding</h3>
 <ul>
    <li> Arun Persaud </li>
    <li> Sean Brennan </li>
 </ul>
</div>

<div class="database">
<h3>Database support</h3>
 <ul>
    <li> Arun Persaud </li>
    <li> Jeff Zerger</li>
 </ul>
</div>

<div class="graphics">
<h3> Graphics </h3>
 <ul>
    <li> Lance Thornton </li>
    <li> Frances Allen </li>
    <li> Arun Persaud  </li>
 </ul>
</div>

<div class="translation">
<h3>Translation</h3>
 <ul>
  <li> German
    <ul>
      <li> Arun Persaud </li>
    </ul>
  </li>
 </ul>
</div>
</div>
'
?>
