<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/../../TwitterBot.class.php';

$t = new lime_test(7, new lime_output_color());

class TwitterBotMock extends TwitterBot
{
  public function searchFor($terms, $source = 'public')
  {
    return parent::searchFor($terms, $source);
  }
  protected function getSearchUrl($terms)
  {
    return sprintf(dirname(__FILE__).'/xml/search_%s.xml', $terms);
  }
}

$t->isa_ok(TwitterBotMock::create('test', 'pass'), 'TwitterBot', 'create() ok');
$t->is(TwitterBotMock::create('test', 'pass')->getUsername(), 'test', 'getUsername() ok');
$t->isa_ok(TwitterBotMock::create('test', 'pass')->getClient(), 'Twitter', 'getClient() ok');

$bot = new TwitterBotMock('test', 'pass');
$fooSearch = $bot->searchFor('foo');
$t->isa_ok($fooSearch, 'array', 'searchFor() returns an array');
$t->is(count($fooSearch), 15, 'searchFor() returns the correct number of tweets');
$t->isa_ok($fooSearch[0], 'Tweet', 'searchFor() returns collection of Tweet instances');
$t->is($fooSearch[0]->title, "Listening to Jean Carne and Foo Fighters. I'm so weird lol. Whos coming to picnic with us in Hyde Park?", 'searchFor() returns valid Tweet instance');
