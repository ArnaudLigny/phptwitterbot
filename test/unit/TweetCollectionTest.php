<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/../../lib/TweetCollection.class.php';

$t = new lime_test(3, new lime_output_color());

// Sample data
$xmlTweets = DOMDocument::load(dirname(__FILE__).'/xml/server/statuses/replies.xml');

// createFromXml()
$t->diag('createFromXML()');
try
{
  $tweets = TweetCollection::createFromXML($xmlTweets);
  $t->pass('createFromXml() creates a TweetCollection instance from an XML element without throwing an exception');
}
catch (Exception $e)
{
  $t->fail('createFromXml() creates a TweetCollection instance from an XML element without throwing an exception');
  $t->diag(sprintf('    %s: %s', get_class($e), $e->getMessage()));
}
$t->isa_ok($tweets, 'TweetCollection', 'createFromXML() creates a TweetCollection instance');
$t->is($tweets[0]->text, "@n1k0 Les gens ne supportent pas les bonnes intentions parce qu'ils se sentent coupables de ne rien faire de leur côté. #home, c'est bien.", 'createFromXml() retrieves first tweet ok');