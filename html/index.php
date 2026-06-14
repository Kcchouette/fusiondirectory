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

/* Load required includes */
require_once("../include/php_setup.php");
require_once("functions.php");
require_once("variables.php");
require_once("class_logging.inc");
require_once("../include/Utility/InputFilter.php");

/* Set headers */
header('Content-type: text/html; charset=UTF-8');
SecurityHeaders::send();

/**
 * @var Smarty $smarty    Defined in php_setup.inc
 * @var string $BASE_DIR  Defined in php_setup.inc
 * @var string $ssl       Defined in php_setup.inc
 */

/*****************************************************************************
 *                               M   A   I   N                               *
 *****************************************************************************/

/* Set error handler to own one, initialize time calculation
   and start session. */
Session::start();

if (InputFilter::has('signout') && InputFilter::request('signout')) {
  $reason = '';
  if (Session::isSet('connected')) {
    $config = Session::get('Config');
    if (
      ($config->getCfgValue('casActivated') == 'TRUE') ||
      ($config->getCfgValue('LoginMethod') === 'LoginCAS')
    ) {
      LoginCAS::initCAS();
      phpCAS::logout();
    }
    $reason = 'Sign out';
    if (InputFilter::has('message')) {
      switch (InputFilter::request('message')) {
        case 'expired':
          $reason = 'Session expired';
          break;
        case 'invalidparameter':
          $reason = sprintf('Invalid plugin parameter "%s"!', InputFilter::request('plug'));
          break;
        case 'nosession':
          $reason = 'No session found';
          break;
        default:
      }
    }
  }
  Session::destroy($reason);
  Session::start();
}

/* Reset errors */
resetErrors();

/* Check if we need to run setup */
if (!file_exists(CONFIG_DIR.'/'.CONFIG_FILE)) {
  header('location:setup.php');
  exit();
}

/* Check if fusiondirectory.conf (.CONFIG_FILE) is accessible */
if (!is_readable(CONFIG_DIR.'/'.CONFIG_FILE)) {
  throw new FatalError(
    htmlescape(sprintf(
      _('FusionDirectory configuration %s/%s is not readable. Please run fusiondirectory-configuration-manager --check-config to fix this.'),
      CONFIG_DIR,
      CONFIG_FILE
    ))
  );
}

/* Parse configuration file */
$config = new Config(CONFIG_DIR.'/'.CONFIG_FILE, $BASE_DIR);
Session::set('Config', $config);
Session::set('DEBUGLEVEL', $config->getCfgValue('DEBUGLEVEL'));
Logging::debug(DEBUG_CONFIG, __LINE__, '', __FILE__, $config->data, 'Config');
/* Configuration was reloaded, so plist needs to be as well */
Session::unsetKey('plist');
unset($plist);

/* Set template compile directory */
$smarty->setCompileDir($config->getCfgValue('templateCompileDirectory', SPOOL_DIR));

/* Check for compile directory */
if (!(is_dir($smarty->getCompileDir()) && is_writable($smarty->getCompileDir()))) {
  throw new FatalError(
    htmlescape(sprintf(
      _('Directory "%s" specified as compile directory is not accessible!'),
      $smarty->getCompileDir()
    ))
  );
}

/* Check for old files in compile directory */
cleanSmartyCompileDir($smarty->getCompileDir());

Language::init();

// Check for location header before proceeding with authentication
if (isset($_SERVER['HTTP_X_FUSIONDIRECTORY_LOCATION'])) {
  $server = trim($_SERVER['HTTP_X_FUSIONDIRECTORY_LOCATION']);
  if (isset($config->data['LOCATIONS'][$server])) {
    // Valid location found - switch to it
    $config->setCurrent($server);
    Logging::debug(DEBUG_TRACE, __LINE__, '', __FILE__,
      $server, 'Switched to location via HTTP header');
  } else {
    // Invalid location in header - log but continue with default
    Logging::log(
      'security',
      'login warning',
      'N/A',
      [],
      sprintf(
        'Invalid location "%s" specified in HTTP header. Using location: %s',
        $server,
        $config->current['NAME']
      )
    );
  }
} else if (InputFilter::has('server')) {
  $server = InputFilter::post('server');
} else {
  $server = $config->data['MAIN']['DEFAULT'];
}

$config->setCurrent($server);
if (
  ($config->getCfgValue('casActivated') == 'TRUE') ||
  ($config->getCfgValue('httpAuthActivated') == 'TRUE') ||
  ($config->getCfgValue('httpHeaderAuthActivated') == 'TRUE') ||
  in_array($config->getCfgValue('LoginMethod'), ['LoginCas', 'LoginHTTPAuth', 'LoginHTTPHeader'])) {
  Session::set('DEBUGLEVEL', 0);
}

/* If SSL is forced, just forward to the SSL enabled site */
if (($config->getCfgValue('forcessl') == 'TRUE') && ($ssl != '')) {
  header("Location: $ssl");
  exit;
}

if (InputFilter::has('message')) {
  switch (InputFilter::request('message')) {
    case 'expired':
      $message = _('Your FusionDirectory session has expired!');
      break;
    case 'invalidparameter':
      $message = sprintf(_('Invalid plugin parameter "%s"!'), InputFilter::request('plug'));
      break;
    case 'nosession':
      $message = _('No session found!');
      break;
    default:
      $message = InputFilter::request('message');
  }
}

LoginMethod::loginProcess();
