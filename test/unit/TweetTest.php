<?php
require_once dirname(__FILE__).'/../../vendor/lime/lib/lime.php';
require_once dirname(__FILE__).'/../../TwitterBot.class.php';

$t = new lime_test(8, new lime_output_color());

$t->diag('createFromArray()');
$tweet = Tweet::createFromArray(array('text' => 'foo', 'user' => array('screen_name' => 'bar'), 'created_at' => 'today'));
$t->isa_ok($tweet, 'Tweet', 'createFromArray() creates a Tweet instance');
$t->is($tweet->title, 'foo', 'createFromArray() populates title correctly');
$t->is($tweet->author, 'bar', 'createFromArray() populates author name correctly');
$t->is($tweet->date, 'today', 'createFromArray() populates date correctly');

$t->diag('createFromXML()');
$tweet = Tweet::createFromXML(simplexml_load_string(<<<EOF
<entry xmlns:google="http://base.google.com/ns/1.0" xml:lang="en-US" xmlns:openSearch="http://a9.com/-/spec/opensearch/1.1/" xmlns="http://www.w3.org/2005/Atom" xmlns:twitter="http://api.twitter.com/">
  <id>tag:search.twitter.com,2005:1989452075</id>
  <published>today</published>
  <link type="text/html" rel="alternate" href="http://twitter.com/pauperprincess/statuses/1989452075"/>
  <title>foo</title>
  <content type="html">foo</content>
  <updated>later</updated>
  <link type="image/png" rel="image" href="toto.png"/>
  <twitter:source>&lt;a href="http://twitter.com/"&gt;web&lt;/a&gt;</twitter:source>
  <twitter:lang>en</twitter:lang>
  <author>
    <name>bar (Bill Gates)</name>
    <uri>http://twitter.com/bar</uri>
  </author>
</entry>
EOF
));
$t->isa_ok($tweet, 'Tweet', 'createFromXML() creates a Tweet instance');
$t->is($tweet->title, 'foo', 'createFromXML() populates title correctly');
$t->is($tweet->author, 'bar', 'createFromXML() populates author name correctly');
$t->is($tweet->date, 'today', 'createFromXML() populates date correctly');