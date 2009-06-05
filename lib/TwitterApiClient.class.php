<?php
require_once dirname(__FILE__).'/Tweet.class.php';
require_once dirname(__FILE__).'/TweetCollection.class.php';

/**
 * Twitter api client
 * 
 * Credits:
 *  - Some parts of the code are based on the work of Tijs Verkoyen on the PHPTwitter project (BSD licensed)
 *
 * @author  Nicolas Perriault <nperriault@gmail.com>
 * @license MIT License
 */
class TwitterApiClient
{
  protected 
    $apiBaseUrl = 'http://twitter.com',
    $debug      = false,
    $httpPort   = 80,
    $password   = null,
    $timeOut    = 20,
    $userAgent  = 'PHPTwitterBot (http://code.google.com/p/phptwitterbot/)',
    $username   = null;

  /**
   * Default constructor
   *
   * @param  string   $username
   * @param  string   $password
   * @param  Boolean  $debug
   */
  public function __construct($username = null, $password = null, $debug = false)
  {
    $this->debug = $debug;
    
    if (!is_null($username))
    {
      $this->setUsername($username);
    }
    
    if (!is_null($password))
    {
      $this->setPassword($password);
    }
  }

  /**
   * Make the call
   *
   * @param  string $url
   * @param  array  $aParameters
   * @param  bool   $authenticate
   * @param  bool   $usePost
   *
   * @return Tweet|TweetsCollection
   */
  protected function doCall($url, $aParameters = array(), $authenticate = false, $usePost = true)
  {    
    $url = sprintf('%s/%s.xml', $this->getApiBaseUrl(), $url);

    if ($authenticate && (!$this->getUsername() || !$this->getPassword()))
    {
      throw new TwitterApiClientException('No username or password was set.');
    }

    $queryString = utf8_encode(http_build_query($aParameters));

    $options[CURLOPT_URL] = $url . (!$usePost ? '?'.$queryString : '');
    $options[CURLOPT_PORT] = $this->getHttpPort();
    $options[CURLOPT_USERAGENT] = $this->getUserAgent();
    $options[CURLOPT_FOLLOWLOCATION] = true;
    $options[CURLOPT_RETURNTRANSFER] = true;
    $options[CURLOPT_TIMEOUT] = $this->getTimeOut();

    if ($authenticate)
    {
      $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
      $options[CURLOPT_USERPWD] = sprintf('%s:%s', $this->getUsername(), $this->getPassword());
    }

    if (!empty($aParameters) && $usePost)
    {
      $options[CURLOPT_POST] = true;
      $options[CURLOPT_POSTFIELDS] = $queryString;

      // Probaly Twitter's webserver doesn't support the Expect: 100-continue header. So we reset it.
      $options[CURLOPT_HTTPHEADER] = array('Expect:');
    }

    $curl = curl_init();

    curl_setopt_array($curl, $options);

    $response = curl_exec($curl);
    $headers = curl_getinfo($curl);

    $errorNumber = curl_errno($curl);
    $errorMessage = curl_error($curl);

    curl_close($curl);
    
    if (!in_array($headers['http_code'], array(0, 200)))
    {
      throw new TwitterApiClientException(null, (int) $headers['http_code']);
    }
    
    if ($errorNumber != '')
    {
      throw new TwitterApiClientException($errorMessage, $errorNumber);
    }
    
    return $response;
  }
  
  /**
   * Converts an HTTP response string containing XML to Tweets
   *
   * @param  string  $response  HTTP response string
   *
   * @return Tweet|TweetsCollection
   */
  protected function convertAsTweets($response)
  {
    $xml = @simplexml_load_string($response);
    
    if (!$xml || isset($xml->error))
    {
      throw new TwitterApiClientException((string) $xml->error);
    }
    
    if ('array' === (string) $xml->attributes()->type)
    {
      return TweetCollection::createFromXml($xml);
    }
    else
    {
      return Tweet::createFromXml($xml);
    }
  }
  
  /**
   * Converts a piece of XML into a message-array
   *
   * @param  SimpleXMLElement $xml
   *
   * @return array
   */
  protected function messageXMLToArray($xml)
  {
    // validate xml
    if (!isset($xml->id, $xml->text, $xml->created_at, $xml->sender, $xml->recipient))
    {
      throw new TwitterApiClientException('Invalid xml for message.');
    }

    // convert into array
    $aMessage['id'] = (string) $xml->id;
    $aMessage['created_at'] = (int) strtotime($xml->created_at);
    $aMessage['text'] = (string) utf8_decode($xml->text);
    $aMessage['sender'] = $this->userXMLToArray($xml->sender);
    $aMessage['recipient'] = $this->userXMLToArray($xml->recipient);

    // return
    return $aMessage;
  }

  /**
   * Converts a piece of XML into a status-array
   *
   * @param  SimpleXMLElement $xml
   *
   * @return array
   */
  protected function statusXMLToArray($xml)
  {
    // validate xml
    if (!isset($xml->id, $xml->text, $xml->created_at, $xml->source, $xml->truncated, $xml->in_reply_to_status_id, $xml->in_reply_to_user_id, $xml->favorited, $xml->user))
    {
      throw new TwitterApiClientException('Invalid xml for message.');
    }

    // convert into array
    $aStatus['id'] = (string) $xml->id;
    $aStatus['created_at'] = (int) strtotime($xml->created_at);
    $aStatus['text'] = utf8_decode((string) $xml->text);
    $aStatus['source'] = (isset($xml->source)) ? (string) $xml->source : '';
    $aStatus['user'] = $this->userXMLToArray($xml->user);
    $aStatus['truncated'] = (isset($xml->truncated) && $xml->truncated == 'true');
    $aStatus['favorited'] = (isset($xml->favorited) && $xml->favorited == 'true');
    $aStatus['in_reply_to_status_id'] = (string) $xml->in_reply_to_status_id;
    $aStatus['in_reply_to_user_id'] = (string) $xml->in_reply_to_user_id;

    // return
    return $aStatus;
  }

  /**
   * Converts a piece of XML into an user-array
   *
   * @param  SimpleXMLElement $xml
   *
   * @return array
   */
  protected function userXMLToArray($xml, $extended = false)
  {
    // validate xml
    if (!isset($xml->id, $xml->name, $xml->screen_name, $xml->description, $xml->location, $xml->profile_image_url, $xml->url, $xml->protected, $xml->followers_count)) throw new TwitterApiClientException('Invalid xml for message.');

    // convert into array
    $aUser['id'] = (string) $xml->id;
    $aUser['name'] = utf8_decode((string) $xml->name);
    $aUser['screen_name'] = utf8_decode((string) $xml->screen_name);
    $aUser['description'] = utf8_decode((string) $xml->description);
    $aUser['location'] = utf8_decode((string) $xml->location);
    $aUser['url'] = (string) $xml->url;
    $aUser['protected'] = (isset($xml->protected) && $xml->protected == 'true');
    $aUser['followers_count'] = (int) $xml->followers_count;
    $aUser['profile_image_url'] = (string) $xml->profile_image_url;

    // extended info?
    if ($extended)
    {
      if (isset($xml->profile_background_color)) $aUser['profile_background_color'] = utf8_decode((string) $xml->profile_background_color);
      if (isset($xml->profile_text_color)) $aUser['profile_text_color'] = utf8_decode((string) $xml->profile_text_color);
      if (isset($xml->profile_link_color)) $aUser['profile_link_color'] = utf8_decode((string) $xml->profile_link_color);
      if (isset($xml->profile_sidebar_fill_color)) $aUser['profile_sidebar_fill_color'] = utf8_decode((string) $xml->profile_sidebar_fill_color);
      if (isset($xml->profile_sidebar_border_color)) $aUser['profile_sidebar_border_color'] = utf8_decode((string) $xml->profile_sidebar_border_color);
      if (isset($xml->profile_background_image_url)) $aUser['profile_background_image_url'] = utf8_decode((string) $xml->profile_background_image_url);
      if (isset($xml->profile_background_tile)) $aUser['profile_background_tile'] = (isset($xml->profile_background_tile) && $xml->profile_background_tile == 'true');
      if (isset($xml->created_at)) $aUser['created_at'] = (int) strtotime((string) $xml->created_at);
      if (isset($xml->following)) $aUser['following'] = (isset($xml->following) && $xml->following == 'true');
      if (isset($xml->notifications)) $aUser['notifications'] = (isset($xml->notifications) && $xml->notifications == 'true');
      if (isset($xml->statuses_count)) $aUser['statuses_count'] = (int) $xml->statuses_count;
      if (isset($xml->friends_count)) $aUser['friends_count'] =  (int) $xml->friends_count;
      if (isset($xml->favourites_count)) $aUser['favourites_count'] = (int) $xml->favourites_count;
      if (isset($xml->time_zone)) $aUser['time_zone'] = utf8_decode((string) $xml->time_zone);
      if (isset($xml->utc_offset)) $aUser['utc_offset'] = (int) $xml->utc_offset;
    }

    // return
    return (array) $aUser;
  }

  /**
   * Returns the 20 most recent statuses from non-protected users who have set a custom user icon.
   *
   * Note that the public timeline is cached for 60 seconds so requesting it more often than that 
   * is a waste of resources.
   *
   * @return array
   */
  public function getPublicTimeline()
  {
    // do the call
    $response = $this->doCall('statuses/public_timeline');

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false)
    {
      throw new TwitterApiClientException('invalid body');
    }

    // init var
    $aStatuses = array();

    // loop statuses
    foreach ($xml->status as $status)
    {
      $aStatuses[] = $this->statusXMLToArray($status);
    }

    // return
    return (array) $aStatuses;
  }

  /**
   * Returns the 20 most recent statuses posted by the authenticating user and that user's friends.
   * This is the equivalent of /home on the Web.
   *
   * @param  int  $since    Narrows the returned results to just those statuses created after the specified UNIX-timestamp, up to 24 hours old.
   * @param  int  $sinceId  Returns only statuses with an id greater than (that is, more recent than) the specified $sinceId.
   * @param  int  $count    Specifies the number of statuses to retrieve. May not be greater than 200.
   * @param  int  $page
   *
   * @return array
   */
  public function getFriendsTimeline($since = null, $sinceId = null, $count = null, $page = null)
  {
    // validate parameters
    if ($since !== null && (int) $since <= 0) throw new TwitterApiClientException('Invalid timestamp for since.');
    if ($sinceId !== null && (int) $sinceId <= 0) throw new TwitterApiClientException('Invalid value for sinceId.');
    if ($count !== null && (int) $count > 200) throw new TwitterApiClientException('Count can\'t be larger then 200.');

    // build url
    $aParameters = array();
    if ($since !== null) $aParameters['since'] = date('r', (int) $since);
    if ($sinceId !== null) $aParameters['since_id'] = (int) $sinceId;
    if ($count !== null) $aParameters['count'] = (int) $count;
    if ($page !== null) $aParameters['page'] = (int) $page;

    // do the call
    $response = $this->doCall('statuses/friends_timeline', $aParameters, true, false);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // init var
    $aStatuses = array();

    // loop statuses
    foreach ($xml->status as $status) $aStatuses[] = $this->statusXMLToArray($status);

    // return
    return (array) $aStatuses;
  }

  /**
   * Returns the 20 most recent statuses posted from the authenticating user. 
   * It's also possible to request another user's timeline via the id parameter below.
   * This is the equivalent of the Web /archive page for your own user, or the profile page for a third party.
   *
   * @param  string $id  Specifies the id or screen name of the user for whom to return the friends_timeline.
   * @param  int $since  Narrows the returned results to just those statuses created after the specified UNIX-timestamp, up to 24 hours old.
   * @param  int $sinceId  Returns only statuses with an id greater than (that is, more recent than) the specified $sinceId.
   * @param  int $count  Specifies the number of statuses to retrieve. May not be greater than 200.
   * @param  int $page
   *
   * @return array
   */
  public function getUserTimeline($id = null, $since = null, $sinceId = null, $count = null, $page = null)
  {
    // validate parameters
    if ($since !== null && (int) $since <= 0) throw new TwitterApiClientException('Invalid timestamp for since.');
    if ($sinceId !== null && (int) $sinceId <= 0) throw new TwitterApiClientException('Invalid value for sinceId.');
    if ($count !== null && (int) $count > 200) throw new TwitterApiClientException('Count can\'t be larger then 200.');

    // build parameters
    $aParameters = array();
    if ($since !== null) $aParameters['since'] = date('r', (int) $since);
    if ($sinceId !== null) $aParameters['since_id'] = (int) $sinceId;
    if ($count !== null) $aParameters['count'] = (int) $count;
    if ($page !== null) $aParameters['page'] = (int) $page;

    // build url
    $url = 'statuses/user_timeline';

    if (!is_null($id))
    {
      $url = 'statuses/user_timeline/'.urlencode($id);
    }

    return $this->convertAsTweets($this->doCall($url, $aParameters, true, false));
  }

  /**
   * Returns a single status, specified by the id parameter below.
   *
   * @param  int $id  The numerical id of the status you're trying to retrieve.
   *
   * @return Tweet
   */
  public function getStatus($id)
  {
    return $this->convertAsTweets($this->doCall('statuses/show/'.urlencode($id)));
  }
  
  /**
   * Checks if a given status has already been published
   *
   * @param  string  $status  Status text
   * @param  int     $max     Number of existing statuses to check
   *
   * @return Boolean
   */
  public function isDuplicateStatus($status, $max = 1)
  {
    foreach ($this->getUserTimeline() as $tweetSource)
    {
      $tweet = Tweet::createFromSource($tweetSource);
      
      if (trim(strtolower($tweet->title)) == trim(strtolower($status)))
      {
        return true;
      }
    }
    
    return false;
  }

  /**
   * Updates the authenticating user's status.
   * A status update with text identical to the authenticating user's current status will be ignored.
   *
   * @param  string  $status       The text of your status update. Should not be more than 140 characters.
   * @param  int     $inReplyToId  The id of an existing status that the status to be posted is in reply to.
   *
   * @return array
   */
  public function updateStatus($status, $inReplyToId = null)
  {
    // redefine
    $status = (string) $status;

    // validate parameters
    if (strlen($status) > 140) throw new TwitterApiClientException('Maximum 140 characters allowed for status.');

    // build parameters
    $aParameters = array();
    $aParameters['status'] = $status;
    if ($inReplyToId !== null) $aParameters['in_reply_to_status_id'] = (int) $inReplyToId;

    // do the call
    $response = $this->doCall('statuses/update', $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (array) $this->statusXMLToArray($xml);
  }

  /**
   * Returns the 20 most recent @replies (status updates prefixed with @username) for the authenticating user.
   *
   * @return array
   * @param  int $since  Narrows the returned results to just those replies created after the specified UNIX-timestamp, up to 24 hours old.
   * @param  int $sinceId  Returns only statuses with an id greater than (that is, more recent than) the specified $sinceId.
   * @param  int $page
   */
  public function getReplies($since = null, $sinceId = null, $page = null)
  {
    // validate parameters
    if ($since !== null && (int) $since <= 0) throw new TwitterApiClientException('Invalid timestamp for since.');
    if ($sinceId !== null && (int) $sinceId <= 0) throw new TwitterApiClientException('Invalid value for sinceId.');

    // build parameters
    $aParameters = array();
    if ($since !== null) $aParameters['since'] = date('r', (int) $since);
    if ($sinceId !== null) $aParameters['since_id'] = (int) $sinceId;
    if ($page !== null) $aParameters['page'] = (int) $page;

    // do the call
    $response = $this->doCall('statuses/replies', $aParameters, true, false);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // init var
    $aStatuses = array();

    // loop statuses
    foreach ($xml->status as $status) $aStatuses[] = $this->statusXMLToArray($status);

    // return
    return (array) $aStatuses;
  }

  /**
   * Destroys the status specified by the required $id parameter.
   * The authenticating user must be the author of the specified status.
   *
   * @return array
   * @param  int $id
   */
  public function deleteStatus($id)
  {
    // redefine
    $id = (string) $id;

    // build url
    $url = 'statuses/destroy/'. urlencode($id);

    // build parameters
    $aParameters = array();
    $aParameters['id'] = $id;

    // do the call
    $response = $this->doCall($url, $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (array) $this->statusXMLToArray($xml);
  }

  /**
   * Returns up to 100 of the authenticating user's friends who have most recently updated.
   * It's also possible to request another user's recent friends list via the $id parameter.
   *
   * @return array
   * @param  string $id  The id or screen name of the user for whom to request a list of friends.
   * @param  int $page
   */
  public function getFriends($id = null, $page = null)
  {
    // build parameters
    $aParameters = array();
    if ($page !== null) $aParameters['page'] = (int) $page;

    // build url
    $url = 'statuses/friends';
    if ($id !== null) $url = 'statuses/friends/'. urlencode($id);

    // do the call
    $response = $this->doCall($url, $aParameters, true, false);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // init var
    $aUsers = array();

    // loop statuses
    foreach ($xml->user as $user) $aUsers[] = $this->userXMLToArray($user);

    // return
    return (array) $aUsers;
  }

  /**
   * Returns the authenticating user's followers.
   *
   * @return array
   * @param  string $id   The id or screen name of the user for whom to request a list of followers.
   * @param  int $page
   */
  public function getFollowers($id = null, $page = null)
  {
    // build parameters
    $aParameters = array();
    if ($page !== null) $aParameters['page'] = (int) $page;

    // build url
    $url = 'statuses/followers';
    if ($id !== null) $url = 'statuses/followers/'. urlencode($id);

    // do the call
    $response = $this->doCall($url, $aParameters, true, false);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // init var
    $aUsers = array();

    // loop statuses
    foreach ($xml->user as $user) $aUsers[] = $this->userXMLToArray($user);

    // return
    return (array) $aUsers;
  }

  /**
   * Returns extended information of a given user, specified by id or screen name.
   * This information includes design settings, so third party developers can theme their widgets according to a given user's preferences.
   * You must be properly authenticated to request the page of a protected user.
   *
   * @return array
   * @param  string $id  The id or screen name of a user.
   * @param  string $email  May be used in place of $id.
   */
  public function getFriend($id = null, $email = null)
  {
    // validate parameters
    if ($id === null && $email === null) throw new TwitterApiClientException('id or email should be specified.');

    // build parameters
    $aParameters = array();
    if ($email !== null) $aParameters['email'] = (string) $email;

    // build url
    $url = 'users/show/'. urlencode($id);
    if ($email !== null) $url = 'users/show';

    // do the call
    $response = $this->doCall($url, $aParameters, true, false);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (array) $this->userXMLToArray($xml, true);
  }

  /**
   * Returns a list of the 20 most recent direct messages sent to the authenticating user.
   *
   * @return array
   * @param  int $since  Narrows the resulting list of direct messages to just those sent after the specified UNIX-timestamp, up to 24 hours old.
   * @param  int $sinceId  Returns only direct messages with an id greater than (that is, more recent than) the specified $sinceId.
   * @param  int $page
   */
  public function getDirectMessages($since = null, $sinceId = null, $page = null)
  {
    // validate parameters
    if ($since !== null && (int) $since <= 0) throw new TwitterApiClientException('Invalid timestamp for since.');
    if ($sinceId !== null && (int) $sinceId <= 0) throw new TwitterApiClientException('Invalid value for sinceId.');

    // build parameters
    $aParameters = array();
    if ($since !== null) $aParameters['since'] = date('r', (int) $since);
    if ($sinceId !== null) $aParameters['since_id'] = (int) $sinceId;
    if ($page !== null) $aParameters['page'] = (int) $page;

    // do the call
    $response = $this->doCall('direct_messages', $aParameters, true, false);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // init var
    $aDirectMessages = array();

    // loop statuses
    foreach ($xml->direct_message as $message) $aDirectMessages[] = $this->messageXMLToArray($message);

    // return
    return (array) $aDirectMessages;
  }

  /**
   * Returns a list of the 20 most recent direct messages sent by the authenticating user.
   *
   * @return array
   * @param  int $since  Narrows the resulting list of direct messages to just those sent after the specified UNIX-timestamp, up to 24 hours old.
   * @param  int $sinceId  Returns only sent direct messages with an id greater than (that is, more recent than) the specified $sinceId.
   * @param  int $page
   */
  public function getSentDirectMessages($since = null, $sinceId = null, $page = null)
  {
    // validate parameters
    if ($since !== null && (int) $since <= 0) throw new TwitterApiClientException('Invalid timestamp for since.');
    if ($sinceId !== null && (int) $sinceId <= 0) throw new TwitterApiClientException('Invalid value for sinceId.');

    // build parameters
    $aParameters = array();
    if ($since !== null) $aParameters['since'] = date('r', (int) $since);
    if ($sinceId !== null) $aParameters['since_id'] = (int) $sinceId;
    if ($page !== null) $aParameters['page'] = (int) $page;

    // do the call
    $response = $this->doCall('direct_messages/sent', $aParameters, true, false);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // init var
    $aDirectMessages = array();

    // loop statuses
    foreach ($xml->direct_message as $message) $aDirectMessages[] = $this->messageXMLToArray($message);

    // return
    return (array) $aDirectMessages;
  }

  /**
   * Sends a new direct message to the specified user from the authenticating user.
   *
   * @return array
   * @param  string $id  The id or screen name of the recipient user.
   * @param  string $text  The text of your direct message. Keep it under 140 characters.
   */
  public function sendDirectMessage($id, $text)
  {
    // redefine
    $id = (string) $id;
    $text = (string) $text;

    // validate parameters
    if (strlen($text) > 140) throw new TwitterApiClientException('Maximum 140 characters allowed for status.');

    // build parameters
    $aParameters = array();
    $aParameters['user'] = $id;
    $aParameters['text'] = $text;

    // do the call
    $response = $this->doCall('direct_messages/new', $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (array) $this->messageXMLToArray($xml);
  }

  /**
   * Destroys the direct message.
   * The authenticating user must be the recipient of the specified direct message.
   *
   * @return array
   * @param  string $id
   */
  public function deleteDirectMessage($id)
  {
    // redefine
    $id = (string) $id;

    // build url
    $url = 'direct_messages/destroy/'. urlencode($id);

    // build parameters
    $aParameters = array();
    $aParameters['id'] = $id;

    // do the call
    $response = $this->doCall($url, $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (array) $this->messageXMLToArray($xml);
  }

  /**
   * Befriends the user specified in the id parameter as the authenticating user.
   *
   * @return array
   * @param  string $id  The id or screen name of the user to befriend.
   * @param  bool $follow  Enable notifications for the target user in addition to becoming friends.
   */
  public function createFriendship($id, $follow = true)
  {
    // redefine
    $id = (string) $id;
    $follow = (bool) $follow;

    // build url
    $url = 'friendships/create/'. urlencode($id);

    // build parameters
    $aParameters = array();
    $aParameters['id'] = $id;
    if ($follow) $aParameters['follow'] = $follow;

    // do the call
    $response = $this->doCall($url, $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (array) $this->userXMLToArray($xml);
  }

  /**
   * Discontinues friendship with the user.
   *
   * @return array
   * @param  string $id
   */
  public function deleteFriendship($id)
  {
    // redefine
    $id = (string) $id;

    // build url
    $url = 'friendships/destroy/'. urlencode($id);

    // build parameters
    $aParameters = array();
    $aParameters['id'] = $id;

    // do the call
    $response = $this->doCall($url, $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (array) $this->userXMLToArray($xml);
  }

  /**
   * Tests if a friendship exists between two users.
   *
   * @return bool
   * @param  string $id  The id or screen_name of the first user to test friendship for.
   * @param  string $friendId  The id or screen_name of the second user to test friendship for.
   */
  public function existsFriendship($id, $friendId)
  {
    // redefine
    $id = (string) $id;
    $friendId = (string) $friendId;

    // build parameters
    $aParameters = array();
    $aParameters['user_a'] = (string) $id;
    $aParameters['user_b'] = (string) $friendId;

    // do the call
    $response = $this->doCall('friendships/exists', $aParameters, true, false);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (bool) ($xml == 'true');
  }

  /**
   * Verifies your credentials
   * Use this method to test if supplied user credentials are valid.
   *
   * @return bool
   */
  public function verifyCredentials()
  {
    try
    {
      return '' !== $this->doCall('account/verify_credentials', array(), true);
    }
    catch (Exception $e)
    {
      if ($e->getCode() == 401 || $e->getMessage() == 'Could not authenticate you.')
      {
        return false;
      }
      
      else throw $e;
    }
  }

  /**
   * Ends the session of the authenticating user, returning a null cookie.
   * Use this method to sign users out of client-facing applications like widgets.
   *
   */
  public function endSession()
  {
    $this->doCall('account/end_session');
  }

  /**
   * Sets which device Twitter delivers updates to for the authenticating user.
   * Sending none as the device parameter will disable IM or SMS updates.
   *
   * @return array
   * @param  string $device  Must be one of: sms, im, none.
   */
  public function updateDeliveryDevice($device)
  {
    // redefine
    $device = (string) $device;

    // init vars
    $aPossibleDevices = array('sms', 'im', 'none');

    // validate parameters
    if (!in_array($device, $aPossibleDevices)) 
    {
      throw new TwitterApiClientException('Invalid value for device. Possible values are: '. implode(', ', $aPossibleDevices) .'.');
    }

    // build url
    $url = 'account/update_delivery_device';

    // build parameters
    $aParameters = array();
    $aParameters['device'] = $device;

    // do the call
    $response = $this->doCall($url, $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if (false === $xml)
    {
      throw new TwitterApiClientException('invalid body');
    }

    // return
    return (array) $this->userXMLToArray($xml);
  }

  /**
   * Sets values that users are able to set under the "Account" tab of their settings page.
   * Only the parameters specified will be updated.
   *
   * @return array
   * @param  string $name
   * @param  string $email
   * @param  string $url
   * @param  string $location
   * @param  string $description
   */
  public function updateProfile($name = null, $email = null, $url = null, $location = null, $description = null)
  {
    // validate parameters
    if ($name === null && $email === null && $url === null && $location === null && $description === null) throw new TwitterApiClientException('Specify at least one parameter.');
    if ($name !== null && strlen($name) > 40) throw new TwitterApiClientException('Maximum 40 characters allowed for name.');
    if ($email !== null && strlen($email) > 40) throw new TwitterApiClientException('Maximum 40 characters allowed for email.');
    if ($url !== null && strlen($url) > 100) throw new TwitterApiClientException('Maximum 100 characters allowed for url.');
    if ($location !== null && strlen($location) > 30) throw new TwitterApiClientException('Maximum 30 characters allowed for location.');
    if ($description !== null && strlen($description) > 160) throw new TwitterApiClientException('Maximum 160 characters allowed for description.');

    // build parameters
    if ($name !== null) $aParameters['name'] = (string) $name;
    if ($email !== null) $aParameters['email'] = (string) $email;
    if ($url !== null) $aParameters['url'] = (string) $url;
    if ($location !== null) $aParameters['location'] = (string) $location;
    if ($description !== null) $aParameters['description'] = (string) $description;

    // make the call
    $response = $this->doCall('account/update_profile', $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (array) $this->userXMLToArray($xml, true);
  }

  /**
   * Sets one or more hex values that control the color scheme of the authenticating user's profile page on twitter.com.
   * Only the parameters specified will be updated.
   *
   * @return array
   * @param  string $backgroundColor
   * @param  string $textColor
   * @param  string $linkColor
   * @param  string $sidebarBackgroundColor
   * @param  string $sidebarBorderColor
   */
  public function updateProfileColors($backgroundColor = null, $textColor = null, $linkColor = null, $sidebarBackgroundColor = null, $sidebarBorderColor = null)
  {
    // validate parameters
    if ($backgroundColor === null && $textColor === null && $linkColor === null && $sidebarBackgroundColor === null && $sidebarBorderColor === null) throw new TwitterApiClientException('Specify at least one parameter.');
    if ($backgroundColor !== null && (strlen($backgroundColor) < 3 || strlen($backgroundColor) > 6)) throw new TwitterApiClientException('Invalid color for background color.');
    if ($textColor !== null && (strlen($textColor) < 3 || strlen($textColor) > 6)) throw new TwitterApiClientException('Invalid color for text color.');
    if ($linkColor !== null && (strlen($linkColor) < 3 || strlen($linkColor) > 6)) throw new TwitterApiClientException('Invalid color for link color.');
    if ($sidebarBackgroundColor !== null && (strlen($sidebarBackgroundColor) < 3 || strlen($sidebarBackgroundColor) > 6)) throw new TwitterApiClientException('Invalid color for sidebar background color.');
    if ($sidebarBorderColor !== null && (strlen($sidebarBorderColor) < 3 || strlen($sidebarBorderColor) > 6)) throw new TwitterApiClientException('Invalid color for sidebar border color.');

    // build parameters
    if ($backgroundColor !== null) $aParameters['profile_background_color'] = (string) $backgroundColor;
    if ($textColor !== null) $aParameters['profile_text_color'] = (string) $textColor;
    if ($linkColor !== null) $aParameters['profile_link_color'] = (string) $linkColor;
    if ($sidebarBackgroundColor !== null) $aParameters['profile_sidebar_fill_color'] = (string) $sidebarBackgroundColor;
    if ($sidebarBorderColor !== null) $aParameters['profile_sidebar_border_color'] = (string) $sidebarBorderColor;

    // make the call
    $response = $this->doCall('account/update_profile_colors', $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false)
    {
      throw new TwitterApiClientException('invalid body');
    }

    // return
    return (array) $this->userXMLToArray($xml, true);
  }

  /**
   * Updates the authenticating user's profile image.
   * Expects raw multipart data, not a URL to an image.
   *
   * @remark  not implemented yet, feel free to code
   * @param  string $image
   */
  public function updateProfileImage($image)
  {
    throw new TwitterApiClientException(null, 501);

    // build parameters
    $aParameters = array();
    $aParameters['image'] = (string) $image;

    // make the call
    $response = $this->doCall('account/update_profile_image', $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (array) $this->userXMLToArray($xml, true);
  }

  /**
   * Updates the authenticating user's profile background image.
   * Expects raw multipart data, not a URL to an image.
   *
   * @remark  not implemented yet, feel free to code
   * @param  string $image
   */
  public function updateProfileBackgroundImage($image)
  {
    throw new TwitterApiClientException(null, 501);

    // build parameters
    $aParameters = array();
    $aParameters['image'] = (string) $image;

    // make the call
    $response = $this->doCall('account/update_profile_background_image', $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (array) $this->userXMLToArray($xml, true);
  }

  /**
   * Returns the remaining number of API requests available to the requesting user before the API limit is reached for the current hour.
   *
   * @return array
   */
  public function getRateLimitStatus()
  {
    // do the call
    $response = $this->doCall('account/rate_limit_status', array(), true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // create response
    if (isset($xml->{'remaining-hits'})) $aResponse['remaining_hits'] = (int) $xml->{'remaining-hits'};
    if (isset($xml->{'reset-time-in-seconds'})) $aResponse['reset_time'] = (int) $xml->{'reset-time-in-seconds'};
    if (isset($xml->{'hourly-limit'})) $aResponse['hourly_limit'] = (int) $xml->{'hourly-limit'};

    // return
    return $aResponse;
  }

  /**
   * Returns the 20 most recent favorite statuses for the authenticating user or user specified by the $id parameter
   *
   * @return array
   * @param  string $id  The id or screen name of the user for whom to request a list of favorite statuses.
   * @param  int $page
   */
  public function getFavorites($id = null, $page = null)
  {
    // build parameters
    $aParameters = array();
    if ($page !== null) $aParameters['page'] = (int) $page;

    $url = 'favorites';
    if ($id !== null) $url = 'favorites/'. urlencode($id);

    // do the call
    $response = $this->doCall($url, $aParameters, true, false);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // init var
    $aStatuses = array();

    // loop statuses
    foreach ($xml->status as $status) $aStatuses[] = $this->statusXMLToArray($status);

    // return
    return (array) $aStatuses;
  }

  /**
   * Favorites the status specified in the id parameter as the authenticating user.
   *
   * @return array
   * @param  string $id
   */
  public function createFavorite($id)
  {
    // redefine
    $id = (string) $id;

    // build url
    $url = 'favorites/create/'. urlencode($id);

    // build parameters
    $aParameters = array();
    $aParameters['id'] = $id;

    // do the call
    $response = $this->doCall($url, $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (array) $this->statusXMLToArray($xml);
  }

  /**
   * Un-favorites the status specified in the id parameter as the authenticating user.
   *
   * @return array
   * @param  string $id
   */
  public function deleteFavorite($id)
  {
    // redefine
    $id = (string) $id;

    // build url
    $url = 'favorites/destroy/'. urlencode($id);

    // build parameters
    $aParameters = array();
    $aParameters['id'] = $id;

    // do the call
    $response = $this->doCall($url, $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (array) $this->statusXMLToArray($xml);
  }

  /**
   * Enables notifications for updates from the specified user to the authenticating user.
   * This method requires the authenticated user to already be friends with the specified user otherwise the error "there was a problem following the specified user" will be returned.
   *
   * @param  string $id
   */
  public function follow($id)
  {
    // redefine
    $id = (string) $id;

    // build url
    $url = 'notifications/follow/'. urlencode($id);

    // build parameters
    $aParameters = array();
    $aParameters['id'] = $id;

    // do the call
    $response = $this->doCall($url, $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (array) $this->userXMLToArray($xml);
  }

  /**
   * Disables notifications for updates from the specified user to the authenticating user.
   * This method requires the authenticated user to already be friends with the specified user otherwise the error "there was a problem following the specified user" will be returned.
   *
   * @param  string $id
   */
  public function unfollow($id)
  {
    // redefine
    $id = (string) $id;

    // build url
    $url = 'notifications/leave/'. urlencode($id);

    // build parameters
    $aParameters = array();
    $aParameters['id'] = $id;

    // do the call
    $response = $this->doCall($url, $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (array) $this->userXMLToArray($xml);
  }

  /**
   * Blocks the user specified in the id parameter as the authenticating user.
   *
   * @param  string $id
   */
  public function createBlock($id)
  {
    // redefine
    $id = (string) $id;

    // build url
    $url = 'blocks/create/'. urlencode($id);

    // build parameters
    $aParameters = array();
    $aParameters['id'] = $id;

    // do the call
    $response = $this->doCall($url, $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (array) $this->userXMLToArray($xml);
  }

  /**
   * Un-blocks the user specified in the id parameter as the authenticating user.
   *
   * @param  string $id
   */
  public function deleteBlock($id)
  {
    // redefine
    $id = (string) $id;

    // build url
    $url = 'blocks/destroy/'. urlencode($id);

    // build parameters
    $aParameters = array();
    $aParameters['id'] = $id;

    // do the call
    $response = $this->doCall($url, $aParameters, true);

    // convert into xml-object
    $xml = @simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');

    // return
    return (array) $this->userXMLToArray($xml);
  }

  /**
   * Test the connection to Twitter
   *
   * @return bool
   */
  public function test()
  {
    return '<ok>true</ok>' === $this->doCall('help/test');
  }

  /**
   * Returns the same text displayed on http://twitter.com/home when a maintenance window is scheduled.
   *
   * @return string
   */
  public function getDowntimeSchedule()
  {
    // make the call
    $response = $this->doCall('help/downtime_schedule');

    // convert into xml-object
    $xml = simplexml_load_string($response);

    // validate
    if ($xml == false) throw new TwitterApiClientException('invalid body');
    if (!isset($xml->error)) throw new TwitterApiClientException('invalid body');

    // return
    return (string) utf8_decode($xml->error);
  }
  
  /**
   * Get the API base url
   *
   * @return string
   */
  public function getApiBaseUrl()
  {
    return $this->apiBaseUrl;
  }

  /**
   * Set the API base url
   *
   * @param string
   */
  public function setApiBaseUrl($apiBaseUrl)
  {
    $this->apiBaseUrl = $apiBaseUrl;
  }
  
  /**
   * Get the http port
   *
   * @return int
   */
  public function getHttpPort()
  {
    return $this->httpPort;
  }
  
  /**
   * Set the http port
   *
   * @param int
   */
  public function setHttpPort($httpPort)
  {
    $this->httpPort = $httpPort;
  }

  /**
   * Get the password
   *
   * @return string
   */
  protected function getPassword()
  {
    return (string) $this->password;
  }
  
/**
   * Set password
   *
   * @param  string $password
   */
  public function setPassword($password)
  {
    $this->password = (string) $password;
  }

  /**
   * Get the timeout
   *
   * @return int
   */
  public function getTimeOut()
  {
    return (int) $this->timeOut;
  }
  
  /**
   * Set the timeout
   *
   * @param  int $seconds
   */
  public function setTimeOut($seconds)
  {
    $this->timeOut = (int) $seconds;
  }

  /**
   * Get the useragent
   *
   * @return string
   */
  public function getUserAgent()
  {
    return $this->userAgent;
  }
  
  /**
   * Set the user-agent
   * It will be appended to ours
   *
   * @param  string $userAgent
   */
  public function setUserAgent($userAgent)
  {
    $this->userAgent = (string) $userAgent;
  }

  /**
   * Get the username
   *
   * @return string
   */
  public function getUsername()
  {
    return (string) $this->username;
  }
  
  /**
   * Set username
   *
   * @param  string $username
   */
  public function setUsername($username)
  {
    $this->username = (string) $username;
  }
}

/**
 * Twitter client error
 *
 */
class TwitterApiClientException extends Exception
{
  /**
   * Http header-codes
   * @var  array
   */
  static protected $statusCodes = array(
      0 => 'OK',
    100 => 'Continue',
    101 => 'Switching Protocols',
    200 => 'OK',
    201 => 'Created',
    202 => 'Accepted',
    203 => 'Non-Authoritative Information',
    204 => 'No Content',
    205 => 'Reset Content',
    206 => 'Partial Content',
    300 => 'Multiple Choices',
    301 => 'Moved Permanently',
    302 => 'Found',
    303 => 'See Other',
    304 => 'Not Modified',
    305 => 'Use Proxy',
    306 => '(Unused)',
    307 => 'Temporary Redirect',
    400 => 'Bad Request',
    401 => 'Unauthorized',
    402 => 'Payment Required',
    403 => 'Forbidden',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    406 => 'Not Acceptable',
    407 => 'Proxy Authentication Required',
    408 => 'Request Timeout',
    409 => 'Conflict',
    411 => 'Length Required',
    412 => 'Precondition Failed',
    413 => 'Request Entity Too Large',
    414 => 'Request-URI Too Long',
    415 => 'Unsupported Media Type',
    416 => 'Requested Range Not Satisfiable',
    417 => 'Expectation Failed',
    500 => 'Internal Server Error',
    501 => 'Not Implemented',
    502 => 'Bad Gateway',
    503 => 'Service Unavailable',
    504 => 'Gateway Timeout',
    505 => 'HTTP Version Not Supported'
  );

  /**
   * Default constructor
   *
   * @param  string $message
   * @param  int $code
   */
  public function __construct($message = null, $code = null)
  {
    if (is_null($message) && !is_null($code) && array_key_exists((int) $code, self::$statusCodes)) 
    {
      $message = sprintf('HTTP %d: %s', $code, self::$statusCodes[(int) $code]);
    }

    parent::__construct($message, $code);
  }
}