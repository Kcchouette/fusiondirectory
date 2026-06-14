<?php
use FusionDirectory\Utility\InputFilter;
/*
  This code is part of FusionDirectory (http://www.fusiondirectory.org/)
  Copyright (C) 2003-2010  Cajus Pollmeier
  Copyright (C) 2011-2016  FusionDirectory

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301, USA.
*/

/* Basic setup, remove eventually registered sessions */
@require_once("../include/php_setup.php");
@require_once("functions.php");
@require_once("variables.php");
require_once("../include/Utility/InputFilter.php");

session_cache_limiter("private");
Session::start();
reset_errors();

/* Logged in? Simple security check */
if (!Session::isSet('ui')) {
  Logging::log('security', 'unknown', '', [], 'Error: autocomplete.php called without session');
  header('Location: index.php');
  exit;
}

/* Base completion or filter completion? */
if (InputFilter::has('type') && InputFilter::get('type') == "base") {

  // Find dn based on name and description
  if (Session::isSet("pathMapping") && count($_POST) == 1 && InputFilter::has('search')) {
    $res          = "";
    $pathMapping  = Session::get("pathMapping");
    $search       = preg_replace('/&quot;/', '"', InputFilter::post('search', ''));
    $search       = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');

    $config         = Session::get('Config');
    $departmentInfo = $config->getDepartmentInfo();
    foreach ($departmentInfo as $dn => $info) {
      if (!isset($pathMapping[$dn])) {
        continue;
      }
      if (mb_stristr($info['name'], $search) !== FALSE) {
        $res .= "<li>".mark($search, $pathMapping[$dn]).($info['description'] == '' ? "" : "<span class='informal'> [".mark($search, $info['description'])."]</span>")."</li>";
        continue;
      }
      if (mb_stristr($info['description'], $search) !== FALSE) {
        $res .= "<li>".mark($search, $pathMapping[$dn]).($info['description'] == '' ? "" : "<span class='informal'> [".mark($search, $info['description'])."]</span>")."</li>";
        continue;
      }
      if (mb_stristr($pathMapping[$dn], $search) !== FALSE) {
        $res .= "<li>".mark($search, $pathMapping[$dn]).($info['description'] == '' ? "" : "<span class='informal'> [".mark($search, $info['description'])."]</span>")."</li>";
        continue;
      }
    }

    /* Return results */
    if (!empty($res)) {
      echo "<ul>$res</ul>";
    }
  }
} else {
  $ui = Session::get('ui');
  $config = Session::get('Config');

  /* Is there a filter object around? */
  if (Session::isSet('autocomplete')) {
    $filter = Session::get('autocomplete');
    $filter->processAutocomplete();
  }
}
