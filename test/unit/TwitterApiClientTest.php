<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/../../lib/TwitterApiClient.class.php';

$t = new lime_test(19, new lime_output_color());

class MockTwitterApiServer extends TwitterApiServer
{
  public function request($apiPath, $parameters = array(), $httpMethod = 'GET', $authenticate = true)
  {
    return file_get_contents(dirname(__FILE__).'/xml/server/'.$apiPath);
  }
}

$client = new TwitterApiClient(new MockTwitterApiServer());
$client->setUsername('foo');
$client->setPassword('bar');

// getDirectMessages()
$t->diag('getDirectMessages()');
$dms = $client->getDirectMessages();
$t->isa_ok($dms, 'TwitterDirectMessageCollection', 'getDirectMessages() retrieves a TwitterDirectMessageCollection');
$t->is($dms[0]->sender->screen_name, 'duboisnicolas', 'getDirectMessages() retrieves correctly dm sender');
$t->is($dms[0]->recipient->screen_name, 'n1k0', 'getDirectMessages() retrieves correctly dm recipient');

// getFriendsTimeline()
$t->diag('getFriendsTimeline()');
$tweets = $client->getFriendsTimeline();
$t->isa_ok($tweets, 'TweetCollection', 'getFriendsTimeline() retrieves a tweet collection');
$t->is($tweets[0]->user->screen_name, 'plouga', 'getFriendsTimeline() retrieves correctly the first tweet author');

// getPublicTimeline()
$t->diag('getPublicTimeline()');
$tweets = $client->getPublicTimeline();
$t->isa_ok($tweets, 'TweetCollection', 'getPublicTimeline() retrieves a tweet collection');
$t->is($tweets[0]->user->screen_name, 'Susy67', 'getPublicTimeline() retrieves correctly the first tweet author');

// getStatus()
$t->diag('getStatus()');
$tweet = $client->getStatus(2043091669);
$t->isa_ok($tweet, 'Tweet', 'getStatus() retrieves a tweet');
$t->is($tweet->user->screen_name, 'n1k0', 'getStatus() a valid tweet title');

// getReplies()
$t->diag('getReplies()');
$tweets = $client->getReplies();
$t->isa_ok($tweets, 'TweetCollection', 'getReplies() retrieves a TweetCollection');
$t->isa_ok($tweets[0], 'Tweet', 'getReplies() retrieves tweets');
$t->is($tweets[0]->user->screen_name, 'Mitternacht', 'getReplies() a valid collection of tweets');

// getUserTimeline()
$t->diag('getUserTimeline()');
$tweets = $client->getUserTimeline();
$t->isa_ok($tweets, 'TweetCollection', 'getUserTimeline() retrieves a TweetCollection');
$t->isa_ok($tweets[0], 'Tweet', 'getUserTimeline() retrieves tweets');
$t->is($tweets[0]->user->screen_name, 'n1k0', 'getUserTimeline() a valid collection of tweets');

// isDuplicateStatus()
$t->diag('isDuplicateStatus()');
$tweets = $client->getUserTimeline();
$tweet = $tweets[0];
$t->ok($client->isDuplicateStatus("don't understand all this #home bashing", 1), 'isDuplicateStatus() detects duplicate status');
$t->ok(!$client->isDuplicateStatus("gnagnaghn", 1), 'isDuplicateStatus() does not detect fake duplicate status');

// updateStatus()
$t->diag('updateStatus()');
$client->updateStatus('hello world!');
$t->isa_ok($tweet, 'Tweet', 'getStatus() retrieves a tweet');
$t->is($tweet->user->screen_name, 'n1k0', 'getStatus() a valid tweet title');