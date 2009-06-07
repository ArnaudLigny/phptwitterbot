<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/../../lib/TwitterBot.class.php';

$t = new lime_test(12, new lime_output_color());

// Sample data
$xmlTweet = DOMDocument::load(dirname(__FILE__).'/xml/server/statuses/show/2043091669.xml');

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
  $t->diag(sprintf('    %s: %s', get_class($e), $e->getMessage()));
}
$t->isa_ok($tweet, 'Tweet', 'createFromXML() creates a Tweet instance');
$t->is($tweet->created_at, 'Fri Jun 05 14:21:23 +0000 2009', 'createFromXML() can retrieve created_at property');
$t->is($tweet->id, 2043091669, 'createFromXML() can retrieve id property');
$t->is($tweet->text, 'foo', 'createFromXML() can retrieve text property');
$t->is($tweet->source, '<a href="http://www.nambu.com">Nambu</a>', 'createFromXML() can retrieve source property');
$t->is($tweet->truncated, false, 'createFromXML() can retrieve truncated property');
$t->is($tweet->in_reply_to_status_id, 2043033723, 'createFromXML() can retrieve in_reply_to_status_id property');
$t->is($tweet->in_reply_to_user_id, 14587759, 'createFromXML() can retrieve in_reply_to_user_id property');
$t->is($tweet->favorited, false, 'createFromXML() can retrieve favorited property');
$t->is($tweet->in_reply_to_screen_name, 'fvsch', 'createFromXML() can retrieve in_reply_to_screen_name property');
$t->isa_ok($tweet->user, 'TwitterUser', 'createFromXML() imports TwitterUser');
