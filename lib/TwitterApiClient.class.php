<?php
require_once dirname(__FILE__).'/TwitterApiServer.class.php';
require_once dirname(__FILE__).'/Tweet.class.php';
require_once dirname(__FILE__).'/TweetCollection.class.php';
require_once dirname(__FILE__).'/TwitterDirectMessage.class.php';
require_once dirname(__FILE__).'/TwitterDirectMessageCollection.class.php';
require_once dirname(__FILE__).'/TwitterUser.class.php';
require_once dirname(__FILE__).'/TwitterUserCollection.class.php';

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
    $debug     = false,
    $server    = null,
    $userAgent = 'PHPTwitterBot (http://code.google.com/p/phptwitterbot/)';

  /**
   * Default constructor
   *
   * @param  TwitterApiServer\null  $server
   * @param  Boolean                $debug
   */
  public function __construct(TwitterApiServer $server = null, $debug = false)
  {
    if (is_null($server))
    {
      // Default server configuration
      $server = new TwitterApiServer('http://twitter.com', array(
        'userAgent' => $this->getUserAgent(),
        'httpPort'  => 80,
        'timeOut'   => 30,
      ));
    }
    
    $this->debug = $debug;
    $this->server = $server;
  }

  /**
   * Make the call
   *
   * @param  string $url
   * @param  array  $parameters
   * @param  bool   $authenticate
   * @param  bool   $usePost
   *
   * @return Tweet|TweetsCollection
   *
   * @throws TwitterApiServerException  if the request fails for any reason
   */
  protected function doCall($url, $parameters = array(), $authenticate = false, $usePost = true)
  {
    return $this->convertResponse($this->server->request(sprintf('%s.xml', $url), $parameters, $usePost ? 'POST' : 'GET'));
  }
  
  /**
   * Converts an HTTP response string containing XML to Tweets
   *
   * @param  string  $response  HTTP response string
   *
   * @return TwitterEntity
   *
   * @throws RuntimeException if unable to parse the XML response
   * @throws RuntimeException if XML response contains an error description node
   */
  protected function convertResponse($response)
  {
    if (!$xml = @simplexml_load_string($response))
    {
      throw new RuntimeException(sprintf('Unable to parse XML response received'));
    }
    
    if (!$xml || isset($xml->error))
    {
      throw new RuntimeException((string) $xml->error);
    }

    $dom = new DOMDocument();
    $dom->loadXML($xml->asXML());
    
    return TwitterEntity::createFromXml($xml);
  }

  /**
   * Returns the 20 most recent statuses from non-protected users who have set a custom user icon.
   *
   * Note that the public timeline is cached for 60 seconds so requesting it more often than that 
   * is a waste of resources.
   *
   * @return TweetCollection
   */
  public function getPublicTimeline()
  {
    return $this->doCall('statuses/public_timeline');
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
   * @return TweetCollection
   */
  public function getFriendsTimeline($since = null, $sinceId = null, $count = null, $page = null)
  {
    // validate parameters
    if (!is_null($since) && (int) $since <= 0) throw new TwitterApiClientException('Invalid timestamp for since.');
    if (!is_null($sinceId) && (int) $sinceId <= 0) throw new TwitterApiClientException('Invalid value for sinceId.');
    if (!is_null($count) && (int) $count > 200) throw new TwitterApiClientException('Count can\'t be larger then 200.');

    // build url
    $parameters = array();
    if (!is_null($since)) $parameters['since'] = date('r', (int) $since);
    if (!is_null($sinceId)) $parameters['since_id'] = (int) $sinceId;
    if (!is_null($count)) $parameters['count'] = (int) $count;
    if (!is_null($page)) $parameters['page'] = (int) $page;

    return $this->doCall('statuses/friends_timeline', $parameters, true, false);
  }

  /**
   * Returns the 20 most recent statuses posted from the authenticating user. 
   *
   * It's also possible to request another user's timeline via the id parameter below.
   * This is the equivalent of the Web /archive page for your own user, or the profile page for a third party.
   *
   * @param  string  $id       Specifies the id or screen name of the user for whom to return the friends_timeline.
   * @param  int     $since    Narrows the returned results to just those statuses created after the specified UNIX-timestamp, up to 24 hours old
   * @param  int     $sinceId  Returns only statuses with an id greater than (that is, more recent than) the specified $sinceId.
   * @param  int     $count    Specifies the number of statuses to retrieve. May not be greater than 200.
   * @param  int     $page     Page number
   *
   * @return TweetCollection
   */
  public function getUserTimeline($id = null, $since = null, $sinceId = null, $count = null, $page = null)
  {
    // validate parameters
    if (!is_null($since) && (int) $since <= 0) throw new TwitterApiClientException('Invalid timestamp for since.');
    if (!is_null($sinceId) && (int) $sinceId <= 0) throw new TwitterApiClientException('Invalid value for sinceId.');
    if (!is_null($count) && (int) $count > 200) throw new TwitterApiClientException('Count can\'t be larger then 200.');

    // build parameters
    $parameters = array();
    if (!is_null($since)) $parameters['since'] = date('r', (int) $since);
    if (!is_null($sinceId)) $parameters['since_id'] = (int) $sinceId;
    if (!is_null($count)) $parameters['count'] = (int) $count;
    if (!is_null($page)) $parameters['page'] = (int) $page;

    // build url
    $url = 'statuses/user_timeline';

    if (!is_null($id))
    {
      $url = 'statuses/user_timeline/'.urlencode($id);
    }

    return $this->doCall($url, $parameters, true, false);
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
    return $this->doCall('statuses/show/'.urlencode($id));
  }
  
  /**
   * Checks if a given status has already been published recently
   *
   * @param  string  $status  Status text
   * @param  int     $max     Number of existing statuses to check
   *
   * @return Boolean
   */
  public function isDuplicateStatus($status, $max = 1)
  {
    foreach ($this->getUserTimeline() as $tweet)
    {
      if (trim(strtolower($tweet->text)) == trim(strtolower($status)))
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
   * @return Tweet
   */
  public function updateStatus($status, $inReplyToId = null)
  {
    if (mb_strlen($status) > 140)
    {
      throw new TwitterApiClientException('Maximum 140 characters allowed for status.');
    }

    $parameters = array('status' => $status);
    
    if (!is_null($inReplyToId))
    {
      $parameters['in_reply_to_status_id'] = (int) $inReplyToId;
    }

    return $this->doCall('statuses/update', $parameters, true);
  }

  /**
   * Returns the 20 most recent @replies (status updates prefixed with @username) for the authenticating user.
   *
   * @param  int  $since    Narrows the returned results to just those replies created after the specified UNIX-timestamp, up to 24 hours old.
   * @param  int  $sinceId  Returns only statuses with an id greater than (that is, more recent than) the specified $sinceId.
   * @param  int  $page
   *
   * @return TweetCollection
   */
  public function getReplies($since = null, $sinceId = null, $page = null)
  {
    if (!is_null($since) && (int) $since <= 0) throw new TwitterApiClientException('Invalid timestamp for since.');
    if (!is_null($sinceId) && (int) $sinceId <= 0) throw new TwitterApiClientException('Invalid value for sinceId.');

    $parameters = array();
    if (!is_null($since)) $parameters['since'] = date('r', (int) $since);
    if (!is_null($sinceId)) $parameters['since_id'] = (int) $sinceId;
    if (!is_null($page)) $parameters['page'] = (int) $page;

    return $this->doCall('statuses/replies', $parameters, true, false);
  }

  /**
   * Destroys the status specified by the required $id parameter.
   * The authenticating user must be the author of the specified status.
   *
   * @param  int $id
   *
   * @return Tweet
   */
  public function deleteStatus($id)
  {
    $url = 'statuses/destroy/' . urlencode($id);

    $parameters = array();
    $parameters['id'] = $id;

    return $this->doCall($url, $parameters, true);
  }

  /**
   * Returns up to 100 of the authenticating user's friends who have most recently updated.
   * It's also possible to request another user's recent friends list via the $id parameter.
   *
   * @param  string $id  The id or screen name of the user for whom to request a list of friends.
   * @param  int $page
   *
   * @return TwitterUserCollection
   */
  public function getFriends($id = null, $page = null)
  {
    $parameters = array();
    
    if (!is_null($page))
    {
      $parameters['page'] = (int) $page;
    }

    $url = 'statuses/friends';
    
    if (!is_null($id))
    {
      $url = 'statuses/friends/'. urlencode($id);
    }

    return $this->doCall($url, $parameters, true, false);
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
    $parameters = array();
    
    if (!is_null($page))
    {
      $parameters['page'] = (int) $page;
    }

    $url = 'statuses/followers';
    
    if (!is_null($id))
    {
      $url = 'statuses/followers/'. urlencode($id);
    }

    return $this->doCall($url, $parameters, true, false);
  }

  /**
   * Returns extended information of a given user, specified by id or screen name.
   * This information includes design settings, so third party developers can theme their widgets according to a given user's preferences.
   * You must be properly authenticated to request the page of a protected user.
   *
   * @param  string $id  The id or screen name of a user.
   * @param  string $email  May be used in place of $id.
   *
   * @return TwitterUserCollection
   */
  public function getFriend($id = null, $email = null)
  {
    if ($id === null && $email === null)
    {
      throw new TwitterApiClientException('id or email should be specified.');
    }

    $parameters = array();
    
    if (!is_null($email))
    {
      $parameters['email'] = (string) $email;
    }

    $url = 'users/show/'. urlencode($id);
    
    if (!is_null($email)) 
    {
      $url = 'users/show';
    }

    $response = $this->doCall($url, $parameters, true, false);
  }

  /**
   * Returns a direct message. This method of the twitter API is not documented but exists though.
   *
   * @param  int  $id  Direct message id
   *
   * @return TwitterDirectMessage
   */
  public function getDirectMessage($id)
  {
    if (!is_null($id))
    {
      $parameters = array('id' => $id);
    }

    return $this->doCall('direct_messages/show/'.urlencode($id), $parameters, true, false);
  }


  /**
   * Returns a list of the 20 most recent direct messages sent to the authenticating user.
   *
   * @param  int $since  Narrows the resulting list of direct messages to just those sent after the specified UNIX-timestamp, up to 24 hours old.
   * @param  int $sinceId  Returns only direct messages with an id greater than (that is, more recent than) the specified $sinceId.
   * @param  int $page
   *
   * @return TwitterDirectMessageCollection
   */
  public function getDirectMessages($since = null, $sinceId = null, $page = null)
  {
    if (!is_null($since) && (int) $since <= 0) throw new TwitterApiClientException('Invalid timestamp for since.');
    if (!is_null($sinceId) && (int) $sinceId <= 0) throw new TwitterApiClientException('Invalid value for sinceId.');

    $parameters = array();
    if (!is_null($since)) $parameters['since'] = date('r', (int) $since);
    if (!is_null($sinceId)) $parameters['since_id'] = (int) $sinceId;
    if (!is_null($page)) $parameters['page'] = (int) $page;

    return $this->doCall('direct_messages', $parameters, true, false);
  }

  /**
   * Returns a list of the 20 most recent direct messages sent by the authenticating user.
   *
   * @param  int $since  Narrows the resulting list of direct messages to just those sent after the specified UNIX-timestamp, up to 24 hours old.
   * @param  int $sinceId  Returns only sent direct messages with an id greater than (that is, more recent than) the specified $sinceId.
   * @param  int $page
   *
   * @return TwitterDirectMessageCollection
   */
  public function getSentDirectMessages($since = null, $sinceId = null, $page = null)
  {
    if (!is_null($since) && (int) $since <= 0) throw new TwitterApiClientException('Invalid timestamp for since.');
    if (!is_null($sinceId) && (int) $sinceId <= 0) throw new TwitterApiClientException('Invalid value for sinceId.');

    $parameters = array();
    if (!is_null($since)) $parameters['since'] = date('r', (int) $since);
    if (!is_null($sinceId)) $parameters['since_id'] = (int) $sinceId;
    if (!is_null($page)) $parameters['page'] = (int) $page;

    return $this->doCall('direct_messages/sent', $parameters, true, false);
  }

  /**
   * Sends a new direct message to the specified user from the authenticating user.
   *
   * @param  string  $id    The id or screen name of the recipient user.
   * @param  string  $text  The text of your direct message. Keep it under 140 characters.
   *
   * @return TwitterDirectMessage
   */
  public function sendDirectMessage($id, $text)
  {
    if (mb_strlen($text) > 140)
    {
      throw new TwitterApiClientException('Maximum 140 characters allowed for status.');
    }

    $parameters = array();
    $parameters['user'] = $id;
    $parameters['text'] = $text;

    return $this->doCall('direct_messages/new', $parameters, true);
  }

  /**
   * Destroys the direct message.
   * The authenticating user must be the recipient of the specified direct message.
   *
   * @param  string $id
   *
   * @return TwitterDirectMessage
   */
  public function deleteDirectMessage($id)
  {
    $parameters = array();
    $parameters['id'] = $id;

    return $this->doCall('direct_messages/destroy/'.urlencode($id), $parameters, true);
  }

  /**
   * Befriends the user specified in the id parameter as the authenticating user.
   *
   * @param  string $id      The id or screen name of the user to befriend.
   * @param  bool   $follow  Enable notifications for the target user in addition to becoming friends.
   *
   * @return TwitterUser
   */
  public function createFriendship($id, $follow = true)
  {
    $url = 'friendships/create/'. urlencode($id);

    $parameters = array();
    $parameters['id'] = $id;
    
    if ($follow)
    {
      $parameters['follow'] = $follow;
    }

    return $this->doCall($url, $parameters, true);
  }

  /**
   * Discontinues friendship with the user.
   *
   * @return array
   * @param  string $id
   */
  public function deleteFriendship($id)
  {
    // build url
    $url = 'friendships/destroy/'. urlencode($id);

    // build parameters
    $parameters = array();
    $parameters['id'] = $id;

    // do the call
    $response = $this->doCall($url, $parameters, true);

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
    // build parameters
    $parameters = array();
    $parameters['user_a'] = (string) $id;
    $parameters['user_b'] = (string) $friendId;

    // do the call
    $response = $this->doCall('friendships/exists', $parameters, true, false);

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
    $parameters = array();
    $parameters['device'] = $device;

    // do the call
    $response = $this->doCall($url, $parameters, true);

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
    if (!is_null($name) && mb_strlen($name) > 40) throw new TwitterApiClientException('Maximum 40 characters allowed for name.');
    if (!is_null($email) && mb_strlen($email) > 40) throw new TwitterApiClientException('Maximum 40 characters allowed for email.');
    if (!is_null($url) && mb_strlen($url) > 100) throw new TwitterApiClientException('Maximum 100 characters allowed for url.');
    if (!is_null($location) && mb_strlen($location) > 30) throw new TwitterApiClientException('Maximum 30 characters allowed for location.');
    if (!is_null($description) && mb_strlen($description) > 160) throw new TwitterApiClientException('Maximum 160 characters allowed for description.');

    // build parameters
    if (!is_null($name)) $parameters['name'] = (string) $name;
    if (!is_null($email)) $parameters['email'] = (string) $email;
    if (!is_null($url)) $parameters['url'] = (string) $url;
    if (!is_null($location)) $parameters['location'] = (string) $location;
    if (!is_null($description)) $parameters['description'] = (string) $description;

    // make the call
    $response = $this->doCall('account/update_profile', $parameters, true);

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
    if (!is_null($backgroundColor) && (mb_strlen($backgroundColor) < 3 || mb_strlen($backgroundColor) > 6)) throw new TwitterApiClientException('Invalid color for background color.');
    if (!is_null($textColor) && (mb_strlen($textColor) < 3 || mb_strlen($textColor) > 6)) throw new TwitterApiClientException('Invalid color for text color.');
    if (!is_null($linkColor) && (mb_strlen($linkColor) < 3 || mb_strlen($linkColor) > 6)) throw new TwitterApiClientException('Invalid color for link color.');
    if (!is_null($sidebarBackgroundColor) && (mb_strlen($sidebarBackgroundColor) < 3 || mb_strlen($sidebarBackgroundColor) > 6)) throw new TwitterApiClientException('Invalid color for sidebar background color.');
    if (!is_null($sidebarBorderColor) && (mb_strlen($sidebarBorderColor) < 3 || mb_strlen($sidebarBorderColor) > 6)) throw new TwitterApiClientException('Invalid color for sidebar border color.');

    // build parameters
    if (!is_null($backgroundColor)) $parameters['profile_background_color'] = (string) $backgroundColor;
    if (!is_null($textColor)) $parameters['profile_text_color'] = (string) $textColor;
    if (!is_null($linkColor)) $parameters['profile_link_color'] = (string) $linkColor;
    if (!is_null($sidebarBackgroundColor)) $parameters['profile_sidebar_fill_color'] = (string) $sidebarBackgroundColor;
    if (!is_null($sidebarBorderColor)) $parameters['profile_sidebar_border_color'] = (string) $sidebarBorderColor;

    // make the call
    $response = $this->doCall('account/update_profile_colors', $parameters, true);

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
    $parameters = array();
    $parameters['image'] = (string) $image;

    // make the call
    $response = $this->doCall('account/update_profile_image', $parameters, true);

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
    $parameters = array();
    $parameters['image'] = (string) $image;

    // make the call
    $response = $this->doCall('account/update_profile_background_image', $parameters, true);

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
    $parameters = array();
    if (!is_null($page)) $parameters['page'] = (int) $page;

    $url = 'favorites';
    if (!is_null($id)) $url = 'favorites/'. urlencode($id);

    // do the call
    $response = $this->doCall($url, $parameters, true, false);

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
    // build url
    $url = 'favorites/create/'. urlencode($id);

    // build parameters
    $parameters = array();
    $parameters['id'] = $id;

    // do the call
    $response = $this->doCall($url, $parameters, true);

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
    // build url
    $url = 'favorites/destroy/'. urlencode($id);

    // build parameters
    $parameters = array();
    $parameters['id'] = $id;

    // do the call
    $response = $this->doCall($url, $parameters, true);

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
    // build url
    $url = 'notifications/follow/'. urlencode($id);

    // build parameters
    $parameters = array();
    $parameters['id'] = $id;

    // do the call
    $response = $this->doCall($url, $parameters, true);

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
    // build url
    $url = 'notifications/leave/'. urlencode($id);

    // build parameters
    $parameters = array();
    $parameters['id'] = $id;

    // do the call
    $response = $this->doCall($url, $parameters, true);

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
    // build url
    $url = 'blocks/create/'. urlencode($id);

    // build parameters
    $parameters = array();
    $parameters['id'] = $id;

    // do the call
    $response = $this->doCall($url, $parameters, true);

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
    // build url
    $url = 'blocks/destroy/'. urlencode($id);

    // build parameters
    $parameters = array();
    $parameters['id'] = $id;

    // do the call
    $response = $this->doCall($url, $parameters, true);

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
    $this->userAgent = $userAgent;
  }

  /**
   * Get the username
   *
   * @return string
   */
  public function getUsername()
  {
    return $this->server->getUsername();
  }
  
  /**
   * Set username
   *
   * @param  string $username
   */
  public function setUsername($username)
  {
    $this->server->setUsername($username);
  }
  
  /**
   * Get the password
   *
   * @return string
   */
  public function getPassword()
  {
    return $this->server->getPassword();
  }
  
  /**
   * Set the password
   *
   * @param  string $password
   */
  public function setPassword($password)
  {
    $this->server->setPassword($password);
  }
}

class TwitterApiClientException extends Exception
{
}