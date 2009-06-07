<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/lib/MockTwitterApiServer.class.php';
require_once dirname(__FILE__).'/../../lib/TwitterApiClient.class.php';

$t = new lime_test(46, new lime_output_color());

$client = new TwitterApiClient(new MockTwitterApiServer());

// blockUser()

// createFavorite()

// createFriendship()

// deleteDirectMessage()

// deleteFavorite()

// deleteFriendship()

// deleteStatus()

// existsFriendship()
$t->diag('existsFriendship()');
$t->is($client->existsFriendship('a', 'b'), true, 'existsFriendship() retrieves friendship status');

// followUser()

// getDirectMessage()
$t->diag('getDirectMessage()');
$dms = $client->getDirectMessage(155216447);
$t->isa_ok($dms, 'TwitterDirectMessage', 'getDirectMessage() retrieves a TwitterDirectMessage');
$t->is($dms->sender->screen_name, 'duboisnicolas', 'getDirectMessage() retrieves correctly dm sender');
$t->is($dms->recipient->screen_name, 'n1k0', 'getDirectMessage() retrieves correctly dm recipient');

// getDirectMessages()
$t->diag('getDirectMessages()');
$dms = $client->getDirectMessages();
$t->isa_ok($dms, 'TwitterDirectMessageCollection', 'getDirectMessages() retrieves a TwitterDirectMessageCollection');
$t->is($dms[0]->sender->screen_name, 'duboisnicolas', 'getDirectMessages() retrieves correctly dm sender');
$t->is($dms[0]->recipient->screen_name, 'n1k0', 'getDirectMessages() retrieves correctly dm recipient');

// getDowntimeSchedule()

// getFavorites()
$t->diag('getFavorites()');
$faves = $client->getFavorites();
$t->isa_ok($faves, 'TweetCollection', 'getFavorites() retrieves a tweet collection');
$t->is($faves[0]->user->screen_name, 'tschellenbach', 'getFavorites() retrieves correctly the first tweet author');

// getFollowers()
$t->diag('getFollowers()');
$followers = $client->getFollowers();
$t->isa_ok($followers, 'TwitterUserCollection', 'getFollowers() retrieves a TwitterUserCollection');
$t->is($followers[0]->screen_name, 'css4design', 'getFollowers() retrieves correctly the first user screen name');

// getFriends()
$t->diag('getFriends()');
$friends = $client->getFriends();
$t->isa_ok($friends, 'TwitterUserCollection', 'getFriends() retrieves a TwitterUserCollection');
$t->is($friends[0]->screen_name, 'ancailliau', 'getFriends() retrieves correctly the first user screen name');

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

// getRateLimitStatus()
$t->diag('getRateLimitStatus()');
$status = $client->getRateLimitStatus();
$t->isa_ok($status, 'array', 'getRateLimitStatus() retrieves an hash of status infos');
$t->is($status['remaining-hits'], 59, 'getRateLimitStatus() retrieves remaining-hits');
$t->is($status['reset-time'], '2009-06-07T15:37:57+00:00', 'getRateLimitStatus() retrieves reset-time');
$t->is($status['hourly-limit'], 100, 'getRateLimitStatus() retrieves hourly-limit');
$t->is($status['reset-time-in-seconds'], 1244389077, 'getRateLimitStatus() retrieves reset-time-in-seconds');

// getReplies()
$t->diag('getReplies()');
$tweets = $client->getReplies();
$t->isa_ok($tweets, 'TweetCollection', 'getReplies() retrieves a TweetCollection');
$t->isa_ok($tweets[0], 'Tweet', 'getReplies() retrieves tweets');
$t->is($tweets[0]->user->screen_name, 'Mitternacht', 'getReplies() a valid collection of tweets');

// getSentDirectMessages()
$t->diag('getSentDirectMessages()');
$dms = $client->getSentDirectMessages();
$t->isa_ok($dms, 'TwitterDirectMessageCollection', 'getSentDirectMessages() retrieves a TwitterDirectMessageCollection');
$t->is($dms[0]->sender->screen_name, 'n1k0', 'getSentDirectMessages() retrieves correctly dm sender');
$t->is($dms[0]->recipient->screen_name, 'duboisnicolas', 'getSentDirectMessages() retrieves correctly dm recipient');

// getStatus()
$t->diag('getStatus()');
$tweet = $client->getStatus(2043091669);
$t->isa_ok($tweet, 'Tweet', 'getStatus() retrieves a tweet');
$t->is($tweet->user->screen_name, 'n1k0', 'getStatus() a valid tweet title');

// getUser()
$t->diag('getUser()');
$user = $client->getUser(6896142);
$t->isa_ok($user, 'TwitterUser', 'getUser() retrieves a TwitterUser');
$t->is($user->screen_name, 'ancailliau', 'getUser() a valid user screen name');

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

// search()
$t->diag('search()');
$results = $client->search('foo');
$t->isa_ok($results, 'TweetCollection', 'search() retrieves a TweetCollection');
$t->is($results[0]->text, 'Obama Saturday said North Korea`s nuclear weapon test had been &quot;extraordinarily provocative&quot;. &quot;Profoundly dangerous&quot; for Iran to get nukes.', 'search() retrieves first result text okay');
$t->is($results[0]->id, 2065832081, 'search() retrieves first result id okay');
$t->is($results[0]->created_at, "Sun, 07 Jun 2009 16:22:43 +0000", 'search() retrieves first result date okay');
$results = $client->search('anniversaires', array('source' => 'friends'));
$t->isa_ok($results, 'TweetCollection', 'search() with friends as source retrieves a TweetCollection');
$t->is(count($results), 1, 'search() retrieves correct number of results');
$t->is($results[0]->user->screen_name, 'franckpaul', 'search() retrieves first result author name okay');

// sendDirectMessage()

// unblockUser()

// unfollowUser()

// updateProfile()

// updateProfileColors()

// updateStatus()
$t->diag('updateStatus()');
$client->updateStatus('hello world!');
$t->isa_ok($tweet, 'Tweet', 'getStatus() retrieves a tweet');
$t->is($tweet->user->screen_name, 'n1k0', 'getStatus() a valid tweet title');

// verifyCredentials()