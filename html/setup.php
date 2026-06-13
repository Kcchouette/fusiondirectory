<?php
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

/* Get standard functions */
require_once("../include/php_setup.php");
require_once("functions.php");
require_once("variables.php");

require_once("../setup/class_setup.inc");
require_once("../setup/class_setupStep.inc");
require_once("../setup/class_setupStepWelcome.inc");
require_once("../setup/class_setupStepLanguage.inc");
require_once("../setup/class_setupStepChecks.inc");
require_once("../setup/class_setupStepLdap.inc");
require_once("../setup/class_setupStepConfig.inc");
require_once("../setup/class_setupStepMigrate.inc");
require_once("../setup/class_setupStepFinish.inc");


/* Set headers */
header('Content-type: text/html; charset=UTF-8');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: deny');

/**
 * @var Smarty $smarty                  Defined in php_setup.inc
 * @var string $BASE_DIR                Defined in php_setup.inc
 * @var string $ssl                     Defined in php_setup.inc
 * @var string $error_collector         Defined in php_setup.inc
 * @var string $error_collector_mailto  Defined in php_setup.inc
 */

/* Set cookie lifetime to one day (The parameter is in seconds ) */
session_set_cookie_params(24 * 60 * 60);
// default cache_expire is 180
session_cache_expire(60 * 24);
ini_set("session.gc_maxlifetime", 24 * 60 * 60);

/* Start session */
Session::start();
Session::set('DEBUGLEVEL', 0);

CSRFProtection::check();

/* Attribute initialization, reset errors */
reset_errors();

/* Set template compile directory */
$smarty->setCompileDir(SPOOL_DIR);

/* Check for compile directory */
if (!(is_dir($smarty->getCompileDir()) && is_writable($smarty->getCompileDir()))) {
  throw new FatalError(
    htmlescape(sprintf(
      _('Directory "%s" specified as compile directory is not accessible!'),
      $smarty->getCompileDir()
    ))
  );
}

/* Get posted language */
if (isset($_POST['lang_selected']) && $_POST['lang_selected'] != '') {
  $lang = $_POST['lang_selected'];

  /* Append .UTF-8 to language string if necessary */
  if (!preg_match('/utf(-)?8$/i', $lang)) {
    $lang .= '.UTF-8';
  }
} elseif (Session::is_set('lang')) {
  $lang = Session::get('lang');

  /* Append .UTF-8 to language string if necessary */
  if (!preg_match('/utf(-)?8$/i', $lang)) {
    $lang .= '.UTF-8';
  }
} else {
  $lang = Language::detect();
}

Language::init($lang);

$smarty->assign('lang', preg_replace('/_.*$/', '', $lang));
$smarty->assign('rtl',  Language::isRTL($lang));

/* Minimal config */
if (!Session::is_set('Config')) {
  $config = new Config('');
  Session::set('Config', $config);
}
$config = Session::get('Config');
IconTheme::loadThemes('themes');
/* Fake user bypassing acl system */
$ui = new UserInfoNoAuth('setup');
/* Call setup */
setup::mainInc();
/**
 * @var string $display filled by setup::mainInc
 */

$focus = '<script>';
$focus .= 'next_msg_dialog();';
$focus .= '</script>';

/* show web frontend */
$setup = Session::get('setup');

$smarty->assign('date',           date('l, dS F Y H:i:s O'));
$smarty->assign('headline',       $setup->get_header_text());
$header = $smarty->fetch(get_template_path('headers.tpl'));

$smarty->assign("contents",       $display.$setup->get_bottom_html());
$smarty->assign("navigation",     $setup->get_navigation_html());
$smarty->assign("headline_image", $setup->get_header_image());
$smarty->assign("focus",          $focus);
$smarty->assign('CSRFtoken',      CSRFProtection::getToken());
$smarty->assign("msg_dialogs",    MsgDialog::get_dialogs());

if ($error_collector != "") {
  $smarty->assign("php_errors", preg_replace("/%BUGBODY%/", $error_collector_mailto, $error_collector)."</div>");
} else {
  $smarty->assign("php_errors", "");
}

$smarty->assign("version", FD_VERSION);

echo $header.$smarty->fetch("$BASE_DIR/setup/setup_frame.tpl");
