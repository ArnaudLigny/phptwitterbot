<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/../../lib/TweetCollection.class.php';

$t = new lime_test(2, new lime_output_color());

// Sample data
$xmlTweets = DOMDocument::load(dirname(__FILE__).'/xml/server/statuses/replies.xml');

// createFromXml()
$t->diag('createFromXML()');
try
{
  $tweet = TweetCollection::createFromXML($xmlTweets);
  $t->pass('createFromXml() creates a TweetCollection instance from an XML element without throwing an exception');
}
catch (Exception $e)
{
  $t->fail('createFromXml() creates a TweetCollection instance from an XML element without throwing an exception');
}
$t->isa_ok($tweet, 'TweetCollection', 'createFromXML() creates a TweetCollection instance');
