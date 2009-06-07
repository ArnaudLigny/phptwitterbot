<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/lib/MockTwitterApiServer.class.php';
require_once dirname(__FILE__).'/../../lib/TwitterBot.class.php';

$t = new lime_test(3, new lime_output_color());

class TwitterBotMock extends TwitterBot
{
  public function __construct($username, $password, $debug = false)
  {
    parent::__construct($username, $password, $debug);
    
    $this->client = new TwitterApiClient(new MockTwitterApiServer());
    $this->client->setUsername($username);
    $this->client->setPassword($password);
  }
}

$t->isa_ok(TwitterBotMock::create('test', 'pass'), 'TwitterBot', 'create() ok');
$t->is(TwitterBotMock::create('test', 'pass')->getUsername(), 'test', 'getUsername() ok');
$t->isa_ok(TwitterBotMock::create('test', 'pass')->getClient(), 'TwitterApiClient', 'getClient() ok');