<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/../../lib/TwitterUserCollection.class.php';

$t = new lime_test(8, new lime_output_color());

// Sample data
$xmlUsers = DOMDocument::load(dirname(__FILE__).'/xml/server/statuses/friends.xml');

// createFromXml()
$t->diag('createFromXML()');
try
{
  $users = TwitterUserCollection::createFromXML($xmlUsers);
  $t->pass('createFromXml() creates a TwitterUserCollection instance from an XML element without throwing an exception');
}
catch (Exception $e)
{
  $t->fail('createFromXml() creates a TwitterUserCollection instance from an XML element without throwing an exception');
  $t->diag(sprintf('    %s: %s', get_class($e), $e->getMessage()));
}
$t->isa_ok($users, 'TwitterUserCollection', 'createFromXML() creates a TwitterUserCollection instance');
$t->is($users[0]->id, 6896142, 'createFromXML() populated XML nodes values to object properties');
$t->is($users[0]->name, 'Antoine Cailliau', 'createFromXML() retrieves name property');
$t->is($users[0]->screen_name, 'ancailliau', 'createFromXML() retrieves name property');
$t->is($users[0]->location, 'Louvain-la-Neuve', 'createFromXML() retrieves name property');
$t->isa_ok($users[0]->status, 'Tweet', 'createFromXML() converted status as a Tweet');
$t->is($users[0]->status->text, 'Damn, two books, one topic. One consider light is a continuum and the other a quantum... Two differents derivations to understand and map...', 'createFromXML() created the Tweet correctly');