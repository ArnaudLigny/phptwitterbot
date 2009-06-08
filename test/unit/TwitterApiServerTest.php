<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/../../lib/TwitterApiServer.class.php';

$t = new lime_test(1, new lime_output_color());

$server = new TwitterApiServer('http://localhost');
$server->setOption('httpPort', 80);
$t->is($server->getOption('httpPort'), 80, 'getOption() retrieves set option');