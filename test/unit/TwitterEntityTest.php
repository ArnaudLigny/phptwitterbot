<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/../../lib/Tweet.class.php';
require_once dirname(__FILE__).'/../../lib/TweetCollection.class.php';
require_once dirname(__FILE__).'/../../lib/TwitterUser.class.php';
require_once dirname(__FILE__).'/../../lib/TwitterUserCollection.class.php';
require_once dirname(__FILE__).'/../../lib/TwitterDirectMessage.class.php';
require_once dirname(__FILE__).'/../../lib/TwitterDirectMessageCollection.class.php';
require_once dirname(__FILE__).'/../../lib/TwitterEntity.class.php';

$t = new lime_test(6, new lime_output_color());

// createFromXml()
$t->diag('createFromXML()');
$t->isa_ok(TwitterEntity::createFromXML(DOMDocument::load(dirname(__FILE__).'/xml/server/statuses/show/2043091669.xml')),
           'Tweet', 'createFromXml() converts an XML status to a Tweet');
$t->isa_ok(TwitterEntity::createFromXML(DOMDocument::load(dirname(__FILE__).'/xml/server/statuses/replies.xml')),
           'TweetCollection', 'createFromXml() converts an XML statuses to a TweetCollection');

$t->isa_ok(TwitterEntity::createFromXML(DOMDocument::load(dirname(__FILE__).'/xml/server/direct_messages/show/155216447.xml')),
           'TwitterDirectMessage', 'createFromXml() converts an XML dm to a TwitterDirectMessage');
$t->isa_ok(TwitterEntity::createFromXML(DOMDocument::load(dirname(__FILE__).'/xml/server/direct_messages.xml')),
           'TwitterDirectMessageCollection', 'createFromXml() converts an XML dms to a TwitterDirectMessageCollection');

$t->isa_ok(TwitterEntity::createFromXML(DOMDocument::load(dirname(__FILE__).'/xml/server/users/show/6896142.xml')),
           'TwitterUser', 'createFromXml() converts an XML user to a TwitterUser');
$t->isa_ok(TwitterEntity::createFromXML(DOMDocument::load(dirname(__FILE__).'/xml/server/statuses/friends.xml')),
           'TwitterUserCollection', 'createFromXml() converts an XML users to a TwitterUserCollection');