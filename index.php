<?php
// composer autoloader for required packages and dependencies
require_once('vendor/autoload.php');
date_default_timezone_set('America/Argentina/Buenos_Aires');
/** @var \Base $f3 */
$f3 = \Base::instance();
$f3->config('config/config.ini');
$f3->config('config/routes.ini');
$f3->run();

