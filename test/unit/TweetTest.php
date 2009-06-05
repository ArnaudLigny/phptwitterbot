<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/../../lib/TwitterBot.class.php';

$t = new lime_test(15, new lime_output_color());

// Sample data
$arrayTweet = array('text' => 'foo', 'user' => array('screen_name' => 'bar'), 'created_at' => 'today');
$xmlTweet = simplexml_load_file(dirname(__FILE__).'/xml/status.xml');
$rssEntryTweet = simplexml_load_file(dirname(__FILE__).'/xml/tweet_rss_entry.xml');

// createFromArray()
$t->diag('createFromArray()');
try
{
  $tweet = Tweet::createFromArray($arrayTweet);
  $t->pass('createFromArray() creates a Tweet instance from an array without throwing an exception');
}
catch (Exception $e)
{
  $t->fail('createFromArray() creates a Tweet instance from an array without throwing an exception');  
}
$t->isa_ok($tweet, 'Tweet', 'createFromArray() creates a Tweet instance');
$t->is($tweet->title, 'foo', 'createFromArray() populates title correctly');
$t->is($tweet->author, 'bar', 'createFromArray() populates author name correctly');
$t->is($tweet->date, 'today', 'createFromArray() populates date correctly');

// createFromXml()
$t->diag('createFromXML()');
try
{
  $tweet = Tweet::createFromXML($xmlTweet);
  $t->pass('createFromXml() creates a Tweet instance from an XML element without throwing an exception');
}
catch (Exception $e)
{
  $t->fail('createFromXml() creates a Tweet instance from an XML element without throwing an exception');
}
$t->isa_ok($tweet, 'Tweet', 'createFromXML() creates a Tweet instance');
$t->is($tweet->title, "@fvsch source ? j'essaye de me convaincre que c'est impossible qu'une société en arrive là", 'createFromXML() populates title correctly');
$t->is($tweet->author, 'n1k0', 'createFromXML() populates author name correctly');
$t->is($tweet->date, 'today', 'createFromXML() populates date correctly');

// createFromRssEntry()
$t->diag('createFromRssEntry()');
try
{
  $tweet = Tweet::createFromRssEntry($rssEntryTweet);
  $t->pass('createFromRssEntry() creates a Tweet instance from an RSS entry without throwing an exception');
}
catch (Exception $e)
{
  $t->fail('createFromRssEntry() creates a Tweet instance from an RSS entry without throwing an exception');
}
$t->isa_ok($tweet, 'Tweet', 'createFromXML() creates a Tweet instance');
$t->is($tweet->title, 'foo', 'createFromXML() populates title correctly');
$t->is($tweet->author, 'bar', 'createFromXML() populates author name correctly');
$t->is($tweet->date, 'today', 'createFromXML() populates date correctly');