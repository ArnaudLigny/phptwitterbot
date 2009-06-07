<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/../../lib/TwitterDirectMessageCollection.class.php';

$t = new lime_test(11, new lime_output_color());

// Sample data
$xmlDMs = DOMDocument::load(dirname(__FILE__).'/xml/server/direct_messages.xml');

// createFromXml()
$t->diag('createFromXML()');
try
{
  $messages = TwitterDirectMessageCollection::createFromXML($xmlDMs);
  $t->pass('createFromXml() creates a TwitterDirectMessageCollection instance from an XML element without throwing an exception');
}
catch (Exception $e)
{
  $t->fail('createFromXml() creates a TwitterDirectMessageCollection instance from an XML element without throwing an exception');
  $t->diag(sprintf('    %s: %s', get_class($e), $e->getMessage()));
}
$t->isa_ok($messages, 'TwitterDirectMessageCollection', 'createFromXML() creates a TwitterDirectMessageCollection instance');
$t->is($messages[0]->id, 155216447, 'createFromXML() populated XML nodes values to object properties');
$t->is($messages[0]->sender_id, 20970455, 'createFromXML() retrieves sender_id property correctly');
$t->is($messages[0]->text, 'foo', 'createFromXML() retrieves text property correctly');
$t->is($messages[0]->recipient_id, '6619162', 'createFromXML() retrieves recipient_id property correctly');
$t->is($messages[0]->created_at, 'Thu Jun 04 09:06:23 +0000 2009', 'createFromXML() retrieves created_at property correctly');
$t->is($messages[0]->sender_screen_name, 'duboisnicolas', 'createFromXML() retrieves sender_screen_name property correctly');
$t->is($messages[0]->recipient_screen_name, 'n1k0', 'createFromXML() retrieves recipient_screen_name property correctly');
$t->isa_ok($messages[0]->sender, 'TwitterUser', 'createFromXML() converted sender as TwitterUser');
$t->isa_ok($messages[0]->recipient, 'TwitterUser', 'createFromXML() converted recipient as TwitterUser');
