<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/../../lib/TwitterDirectMessage.class.php';

$t = new lime_test(11, new lime_output_color());

// Sample data
$xmlDM = DOMDocument::load(dirname(__FILE__).'/xml/server/direct_messages/show/155216447.xml');

// createFromXml()
$t->diag('createFromXML()');
try
{
  $message = TwitterDirectMessage::createFromXML($xmlDM);
  $t->pass('createFromXml() creates a TwitterDirectMessage instance from an XML element without throwing an exception');
}
catch (Exception $e)
{
  $t->fail('createFromXml() creates a TwitterDirectMessage instance from an XML element without throwing an exception');
  $t->diag(sprintf('    %s: %s', get_class($e), $e->getMessage()));
}
$t->isa_ok($message, 'TwitterDirectMessage', 'createFromXML() creates a TwitterDirectMessage instance');
$t->is($message->id, 155216447, 'createFromXML() populated XML nodes values to object properties');
$t->is($message->sender_id, 20970455, 'createFromXML() retrieves sender_id property correctly');
$t->is($message->text, 'foo', 'createFromXML() retrieves text property correctly');
$t->is($message->recipient_id, '6619162', 'createFromXML() retrieves recipient_id property correctly');
$t->is($message->created_at, 'Thu Jun 04 09:06:23 +0000 2009', 'createFromXML() retrieves created_at property correctly');
$t->is($message->sender_screen_name, 'duboisnicolas', 'createFromXML() retrieves sender_screen_name property correctly');
$t->is($message->recipient_screen_name, 'n1k0', 'createFromXML() retrieves recipient_screen_name property correctly');
$t->isa_ok($message->sender, 'TwitterUser', 'createFromXML() converted sender as TwitterUser');
$t->isa_ok($message->recipient, 'TwitterUser', 'createFromXML() converted recipient as TwitterUser');