<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/../../lib/TwitterApiClient.class.php';

$t = new lime_test(5, new lime_output_color());

$c = new TwitterApiClient('n1k0', 'tw21gaelle!');

// getStatus()
$t->diag('getStatus()');
$tweet = $c->getStatus(2043091669);
$t->isa_ok($tweet, 'Tweet', 'getStatus() retrieves a tweet');
$t->is($tweet->author, 'n1k0', 'getStatus() a valid tweet title');

// getUserTimeline()
$t->diag('getUserTimeline()');
$tweets = $c->getUserTimeline();
$t->isa_ok($tweets, 'TweetCollection', 'getUserTimeline() retrieves a TwitterCollection');
$t->isa_ok($tweets[0], 'Tweet', 'getUserTimeline() retrieves tweets');
$t->is($tweets[0]->author, 'n1k0', 'getUserTimeline() a valid collection of tweets');

