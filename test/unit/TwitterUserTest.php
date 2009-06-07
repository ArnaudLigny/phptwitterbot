<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/../../lib/TwitterUser.class.php';

$t = new lime_test(8, new lime_output_color());

// Sample data
$xmlUser = DOMDocument::load(dirname(__FILE__).'/xml/server/users/show/6896142.xml');

// createFromXml()
$t->diag('createFromXML()');
try
{
  $message = TwitterUser::createFromXML($xmlUser);
  $t->pass('createFromXml() creates a TwitterUser instance from an XML element without throwing an exception');
}
catch (Exception $e)
{
  $t->fail('createFromXml() creates a TwitterUser instance from an XML element without throwing an exception');
  $t->diag(sprintf('    %s: %s', get_class($e), $e->getMessage()));
}
$t->isa_ok($message, 'TwitterUser', 'createFromXML() creates a TwitterUser instance');
$t->is($message->id, 6896142, 'createFromXML() populated XML nodes values to object properties');
$t->is($message->name, 'Antoine Cailliau', 'createFromXML() retrieves name property');
$t->is($message->screen_name, 'ancailliau', 'createFromXML() retrieves name property');
$t->is($message->location, 'Louvain-la-Neuve', 'createFromXML() retrieves name property');
$t->isa_ok($message->status, 'Tweet', 'createFromXML() converted status as a Tweet');
$t->is($message->status->text, 'Damn, two books, one topic. One consider light is a continuum and the other a quantum... Two differents derivations to understand and map...', 'createFromXML() created the Tweet correctly');