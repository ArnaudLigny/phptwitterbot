<?php

error_reporting(E_ALL);
require_once(dirname(__FILE__).'/../../vendor/lime/lime.php');

$h = new lime_harness(new lime_output_color());
$h->base_dir = realpath(dirname(__FILE__).'/..');
$h->register(glob(dirname(__FILE__).'/../unit/*Test.php'));
exit($h->run() ? 0 : 1);
