<?php
use FusionDirectory\Utility\InputFilter;
/*
  This code is part of FusionDirectory (http://www.fusiondirectory.org/)
  Copyright (C) 2003-2010  Cajus Pollmeier
  Copyright (C) 2011-2018  FusionDirectory

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

/**
 * @var Smarty $smarty                  Defined in php_setup.inc
 * @var string $BASE_DIR                Defined in php_setup.inc
 * @var string $ssl                     Defined in php_setup.inc
 * @var string $error_collector         Defined in php_setup.inc
 * @var string $error_collector_mailto  Defined in php_setup.inc
 */

/* Basic setup, remove eventually registered sessions */
require_once("../include/php_setup.php");
require_once("functions.php");
require_once("variables.php");
require_once("../include/Utility/InputFilter.php");

/* Set headers */
header('Content-type: text/html; charset=UTF-8');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: deny');

/* Set the text domain as 'fusiondirectory' */
$domain = 'fusiondirectory';
bindtextdomain($domain, LOCALE_DIR);
textdomain($domain);

/* Remember everything we did after the last click */
Session::start();
reset_errors();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $safePost = array_map('htmlspecialchars', $_POST);
  Logging::debug(DEBUG_POST, __LINE__, '', __FILE__, $safePost, '_POST');
}
Logging::debug(DEBUG_SESSION, __LINE__, '', __FILE__, $_SESSION, '_SESSION');

/* Logged in? Simple security check */
if (!Session::isSet('connected')) {
  Session::destroy('main.php called without session');
  header('Location: index.php?message=nosession');
  exit;
}

CSRFProtection::check();

$ui     = Session::get('ui');
$config = Session::get('Config');

/* If SSL is forced, just forward to the SSL enabled site */
if (($config->getCfgValue('forcessl') == 'TRUE') && ($ssl != '')) {
  header("Location: $ssl");
  exit;
}

Timezone::setDefaultTimezoneFromConfig();

/* Check for invalid sessions */
if (Session::get('_LAST_PAGE_REQUEST') != '') {
  /* check FusionDirectory.conf for defined session lifetime */
  $max_life = $config->getCfgValue('sessionLifetime', 60 * 60 * 2);

  if ($max_life > 0) {
    /* get time difference between last page reload */
    $request_time = (time() - Session::get('_LAST_PAGE_REQUEST'));

    /* If page wasn't reloaded for more than max_life seconds
     * kill session
     */
    if ($request_time > $max_life) {
      Session::destroy('main.php called with expired session');
      header('Location: index.php?signout=1&message=expired');
      exit;
    }
  }
}
Session::set('_LAST_PAGE_REQUEST', time());


Logging::debug(DEBUG_CONFIG, __LINE__, '', __FILE__, $config->data, "Config");

/* Set template compile directory */
$smarty->setCompileDir($config->getCfgValue('templateCompileDirectory', SPOOL_DIR));

Language::init();

/* Prepare plugin list */
Pluglist::load();
/**
 * @var Pluglist $plist built by Pluglist::load
 */

/* Check previous plugin index */
if (Session::isSet('plugin_index')) {
  $old_plugin_index = Session::get('plugin_index');
} else {
  $old_plugin_index = '';
}

$plist->gen_menu();

$smarty->assign('hideMenus', FALSE);
/* check user expiration status */
$expired = $ui->expiredStatus();
if (($expired == POSIX_WARN_ABOUT_EXPIRATION) && !Session::isSet('POSIX_WARN_ABOUT_EXPIRATION__DONE')) {
  Logging::debug(DEBUG_TRACE, __LINE__, '', __FILE__, $expired, 'This user account ('.$ui->uid.') is about to expire');

  // The users password is about to expire soon, display a warning message.
  Logging::log('security', 'fusiondirectory', '', [], 'password for user "'.$ui->uid.'" is about to expire');
  MsgDialog::display(_('Password change'), htmlescape(_('Your password is about to expire, please change your password!')), INFO_DIALOG);
  Session::set('POSIX_WARN_ABOUT_EXPIRATION__DONE', TRUE);
} elseif ($expired == POSIX_FORCE_PASSWORD_CHANGE) {
  Logging::debug(DEBUG_TRACE, __LINE__, '', __FILE__, $expired, 'This user account expired');

  // The password is expired, we are now going to enforce a new one from the user.

  // Hide the FusionDirectory menus to avoid leaving the enforced password change dialog.
  $smarty->assign('hideMenus', TRUE);
  $plug = InputFilter::get('plug');

  // Search for the 'user' class and set its id as active plug.
  foreach ($plist->dirlist as $key => $value) {
    if ($value == 'user') {
      if (!InputFilter::has('plug') || (InputFilter::get('plug') != $key)) {
        $_GET['plug'] = $key;
        $warning = new FusionDirectoryWarning(htmlescape(_('Your password has expired, please set a new one.')));
        $warning->display();
      }
      break;
    }
  }
}

if (InputFilter::has('plug') && $plist->plugin_access_allowed(InputFilter::get('plug'))) {
  $plugin_index = validate(InputFilter::get('plug'));
} else {
  /* set to welcome page as default plugin */
  $plugin_index = 'welcome';
}
Session::set('plugin_index', $plugin_index);

/* Handle plugin locks.
    - Remove the plugin from session if we switched to another. (cleanup)
    - Remove all created locks if "reset" was posted.
    - Remove all created locks if we switched to another plugin.
*/
$cleanup      = FALSE;
$remove_lock  = FALSE;

/* Check if we have changed the selected plugin */
if (!empty($old_plugin_index) && ($old_plugin_index != $plugin_index)) {
  Pluglist::runMainInc($old_plugin_index, TRUE);
} elseif ((InputFilter::has('reset') && InputFilter::get('reset') == 1) || InputFilter::has('delete_lock')) {
  /* Reset was posted, remove all created locks for the current plugin */
  $remove_lock = TRUE;
}

/* Check for sizelimits */
$ui->getSizeLimitHandler()->update();

/* Check for memory */
if (memory_get_usage() > (to_byte(ini_get('memory_limit')) - 2048000)) {
  $warning = new FusionDirectoryWarning(htmlescape(_('Running out of memory!')));
  $warning->display();
}

/* show web frontend */
$smarty->assign("date", date("l, dS F Y H:i:s O"));
$lang = Session::get('lang');
$smarty->assign('lang',  preg_replace('/_.*$/', '', $lang));
$smarty->assign('rtl',   Language::isRTL($lang));
if (isset($plugin_index)) {
  $plug = "?plug=$plugin_index";
} else {
  $plug = "";
}

if ($ui->ignoreAclForCurrentUser()) {
  $smarty->assign('username', '<div style="color:#FF0000;">'._('User ACL checks disabled').'</div>&nbsp;'.$ui->uid);
} else {
  $smarty->assign('username', $ui->uid);
}
$smarty->assign("menu", $plist->menu);
$smarty->assign("plug", "$plug");

$smarty->assign("usePrototype", "false");

/* React on clicks */
if (($_SERVER['REQUEST_METHOD'] == 'POST')
  && (InputFilter::has('delete_lock') || InputFilter::has('open_readonly'))) {

  /* Set old Post data */
  if (Session::isSet('LOCK_VARS_USED_GET')) {
    foreach (Session::get('LOCK_VARS_USED_GET') as $name => $value) {
      $_GET[$name]  = $value;
    }
  }
  if (Session::isSet('LOCK_VARS_USED_POST')) {
    foreach (Session::get('LOCK_VARS_USED_POST') as $name => $value) {
      $_POST[$name] = $value;
    }
  }
  if (Session::isSet('LOCK_VARS_USED_REQUEST')) {
    foreach (Session::get('LOCK_VARS_USED_REQUEST') as $name => $value) {
      $_REQUEST[$name] = $value;
    }
  }
}

/* Load plugin */
Pluglist::runMainInc($plugin_index);
/**
 * @var string $display Filled by Pluglist::runMainInc
 */

/* Print_out last ErrorMessage repeated string. */
$smarty->assign("msg_dialogs", MsgDialog::getDialogs());
$smarty->assign("contents", $display);
$smarty->assign("sessionLifetime", $config->getCfgValue("sessionLifetime", 60 * 60 * 2));

/* If there's some post, take a look if everything is there... */
if (count($_POST) && !InputFilter::has('php_c_check')) {
  throw new FatalError(
    htmlescape(_('Fatal error: not all POST variables have been transfered by PHP - please inform your administrator!'))
  );
}

/* Assign errors to smarty */
if ($error_collector != "") {
  $smarty->assign("php_errors", preg_replace("/%BUGBODY%/", $error_collector_mailto, $error_collector)."</div>");
} else {
  $smarty->assign("php_errors", "");
}

$focus = '<script>';
$focus .= 'next_msg_dialog();';
$focus .= '</script>';
$smarty->assign('focus',      $focus);
$smarty->assign('CSRFtoken',  CSRFProtection::getToken());

if (class_available('Game')) {
  $smarty->assign('game_screen', Game::run());
} else {
  $smarty->assign('game_screen', '');
}

$display  = $smarty->fetch(get_template_path('headers.tpl')).
            $smarty->fetch(get_template_path('framework.tpl'));

/* Show page... */
echo $display;

/* Save plist and config */
Session::set('plist', $plist);
Session::set('Config', $config);
reset_errors();
