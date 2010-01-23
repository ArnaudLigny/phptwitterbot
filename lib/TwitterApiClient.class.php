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
   * @param  string $url           API url to call
   * @param  array  $parameters    Parameters for the request
   * @param  bool   $authenticate  Shall we use authentication?
   * @param  bool   $usePost       Uses POST method instead of GET
   *
   * @return mixed
   *
   * @throws InvalidArgumentException   if the type provided is not supported
   * @throws TwitterApiServerException  if the request fails for any reason
   * @throws RuntimeException           if the xml response is invalid
   */
  protected function doCall($url, $parameters = array(), $authenticate = false, $usePost = true, $type = 'entity')
  {
    $response = $this->server->request(sprintf('%s.xml', $url), $parameters, $usePost ? 'POST' : 'GET');
 
    switch ($type)
    {
      case 'entity':
        $dom = new DOMDocument();
        $dom->loadXML($response);
        return TwitterEntity::createFromXml($dom);
      break;
      
      case 'boolean':
        if (!$xml = @simplexml_load_string($response))
        {
          throw new RuntimeException('XML error');
        }
        return (bool) ((string) $xml === 'true');  
      break;
        
      case 'hash':
        if (!$xml = @simplexml_load_string($response))
        {
          throw new RuntimeException('XML error');
        }
        return (array) $xml;  
      break;
      
      case 'search_results':
        return TweetCollection::createFromJSON($response);
      break;
      
      default:
        throw new InvalidArgumentException(sprintf('Type "%s" is not supported', $type));  
      break;
    }
  }
  
  /**
   * Search in public or friends timeline for tweets. We cannot use the doCall method
   * because twitter doesn't provide XML format for searches, and will therefore force us to use
   * JSON.
   *
   * Available options:
   *
   *  - source: can be 'public' or 'friends'
   *  - max:    Number of items to retrieve
   *  - page:   Page number to query
   *
   * @param  string  $terms    Search terms string
   * @param  array   $options  Options
   *
   * @return TweetCollection
   *
   * @throws InvalidArgumentException if unsupported source name
   */
  public function search($terms, array $options = array())
  {
    $options = array_merge(array('source' => 'public', 'max' => 15, 'page' => 1), $options);
    
    if (!in_array($options['source'], array('public', 'friends')))
    {
      throw new InvalidArgumentException(sprintf('Source "%s" is not supported', $source));
    }
    
    if ('public' === $options['source'])
    {
      $parameters = array('q' => $terms, 'page' => $options['page'], 'rpp' => $options['max']);
      
      return $this->doCall('search', $parameters, false, false, 'search_results');
    }
    else
    {
      $results = array();
      
      foreach ($this->getFriendsTimeline(null, null, 200) as $tweet)
      {
        if (preg_match(sprintf('/%s/i', $terms), $tweet->text))
        {
          $results[] = $tweet;
        }
      }
      
      return new TweetCollection($results);
    }
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
   * @return Booleanean
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
      $parameters['in_reply_to_status_id'] = $inReplyToId;
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
    return $this->doCall('statuses/destroy/'.urlencode($id), array('id' => $id), true);
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
      $url = 'statuses/friends/'.urlencode($id);
    }

    return $this->doCall($url, $parameters, true, false);
  }

  /**
   * Returns the authenticating user's followers.
   *
   * @param  string  $id    The id or screen name of the user for whom to request a list of followers.
   * @param  int     $page
   *
   * @return TwitterUserCollection
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
      $url = 'statuses/followers/'.urlencode($id);
    }

    return $this->doCall($url, $parameters, true, false);
  }

  /**
   * Returns extended information of a given user, specified by id or screen name.
   * This information includes design settings, so third party developers can theme their widgets according to a given user's preferences.
   * You must be properly authenticated to request the page of a protected user.
   *
   * @param  string  $id     The id or screen name of a user.
   * @param  string  $email  May be used in place of $id.
   *
   * @return TwitterUser
   */
  public function getUser($id)
  {
    return $this->doCall('users/show/'.urlencode($id), array('id' => $id), true, false);
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
    return $this->doCall('direct_messages/show/'.urlencode($id), array('id' => $id), true, false);
  }


  /**
   * Returns a list of the 20 most recent direct messages sent to the authenticating user.
   *
   * @param  int  $since    Narrows the resulting list of direct messages to just those sent after the specified UNIX-timestamp, up to 24 hours old.
   * @param  int  $sinceId  Returns only direct messages with an id greater than (that is, more recent than) the specified $sinceId.
   * @param  int  $page
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
   * @param  int  $since    Narrows the resulting list of direct messages to just those sent after the specified UNIX-timestamp, up to 24 hours old.
   * @param  int  $sinceId  Returns only sent direct messages with an id greater than (that is, more recent than) the specified $sinceId.
   * @param  int  $page
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

    return $this->doCall('direct_messages/new', array('user' => $id, 'text' => $text), true);
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
    return $this->doCall('direct_messages/destroy/'.urlencode($id), array('id' => $id), true);
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
    $parameters = array('id' => $id);
    
    if ($follow)
    {
      $parameters['follow'] = $follow;
    }

    return $this->doCall('friendships/create/'.urlencode($id), $parameters, true);
  }

  /**
   * Discontinues friendship with the user.
   *
   * @param  string $id
   */
  public function deleteFriendship($id)
  {
    return $this->doCall('friendships/destroy/'.urlencode($id), array('id' => $id), true);
  }

  /**
   * Tests if a friendship exists between two users.
   *
   * @param  string  $id        The id or screen_name of the first user to test friendship for.
   * @param  string  $friendId  The id or screen_name of the second user to test friendship for.
   */
  public function existsFriendship($id, $friendId)
  {
    return $this->doCall('friendships/exists', array('user_a' => $id, 'user_b' => $friendId), true, false, 'boolean');
  }

  /**
   * Verifies your credentials
   * Use this method to test if supplied user credentials are valid.
   *
   * @return Boolean
   */
  public function verifyCredentials()
  {
    try
    {
      return $this->doCall('account/verify_credentials', array(), true) instanceof TwitterUser;
    }
    catch (Exception $e)
    {
      if ($e->getCode() == 401 || $e->getMessage() == 'Could not authenticate you.')
      {
        return false;
      }
      else
      {
        throw $e;
      }
    }
  }

  /**
   * Sets values that users are able to set under the "Account" tab of their settings page.
   * Only the parameters specified will be updated.
   *
   * @param  string  $name
   * @param  string  $email
   * @param  string  $url
   * @param  string  $location
   * @param  string  $description
   *
   * @return TwitterUser
   */
  public function updateProfile($name = null, $email = null, $url = null, $location = null, $description = null)
  {
    if ($name === null && $email === null && $url === null && $location === null && $description === null) throw new TwitterApiClientException('Specify at least one parameter.');
    if (!is_null($name) && mb_strlen($name) > 40) throw new TwitterApiClientException('Maximum 40 characters allowed for name.');
    if (!is_null($email) && mb_strlen($email) > 40) throw new TwitterApiClientException('Maximum 40 characters allowed for email.');
    if (!is_null($url) && mb_strlen($url) > 100) throw new TwitterApiClientException('Maximum 100 characters allowed for url.');
    if (!is_null($location) && mb_strlen($location) > 30) throw new TwitterApiClientException('Maximum 30 characters allowed for location.');
    if (!is_null($description) && mb_strlen($description) > 160) throw new TwitterApiClientException('Maximum 160 characters allowed for description.');

    $parameters = array();
    if (!is_null($name)) $parameters['name'] = (string) $name;
    if (!is_null($email)) $parameters['email'] = (string) $email;
    if (!is_null($url)) $parameters['url'] = (string) $url;
    if (!is_null($location)) $parameters['location'] = (string) $location;
    if (!is_null($description)) $parameters['description'] = (string) $description;

    return $this->doCall('account/update_profile', $parameters, true);
  }

  /**
   * Sets one or more hex values that control the color scheme of the authenticating user's profile page on twitter.com.
   * Only the parameters specified will be updated.
   *
   * @param  string  $backgroundColor
   * @param  string  $textColor
   * @param  string  $linkColor
   * @param  string  $sidebarBackgroundColor
   * @param  string  $sidebarBorderColor
   *
   * @return TwitterUser
   */
  public function updateProfileColors($backgroundColor = null, $textColor = null, $linkColor = null, $sidebarBackgroundColor = null, $sidebarBorderColor = null)
  {
    if ($backgroundColor === null && $textColor === null && $linkColor === null && $sidebarBackgroundColor === null && $sidebarBorderColor === null) throw new TwitterApiClientException('Specify at least one parameter.');
    if (!is_null($backgroundColor) && (mb_strlen($backgroundColor) < 3 || mb_strlen($backgroundColor) > 6)) throw new TwitterApiClientException('Invalid color for background color.');
    if (!is_null($textColor) && (mb_strlen($textColor) < 3 || mb_strlen($textColor) > 6)) throw new TwitterApiClientException('Invalid color for text color.');
    if (!is_null($linkColor) && (mb_strlen($linkColor) < 3 || mb_strlen($linkColor) > 6)) throw new TwitterApiClientException('Invalid color for link color.');
    if (!is_null($sidebarBackgroundColor) && (mb_strlen($sidebarBackgroundColor) < 3 || mb_strlen($sidebarBackgroundColor) > 6)) throw new TwitterApiClientException('Invalid color for sidebar background color.');
    if (!is_null($sidebarBorderColor) && (mb_strlen($sidebarBorderColor) < 3 || mb_strlen($sidebarBorderColor) > 6)) throw new TwitterApiClientException('Invalid color for sidebar border color.');

    $parameters = array();
    if (!is_null($backgroundColor)) $parameters['profile_background_color'] = (string) $backgroundColor;
    if (!is_null($textColor)) $parameters['profile_text_color'] = (string) $textColor;
    if (!is_null($linkColor)) $parameters['profile_link_color'] = (string) $linkColor;
    if (!is_null($sidebarBackgroundColor)) $parameters['profile_sidebar_fill_color'] = (string) $sidebarBackgroundColor;
    if (!is_null($sidebarBorderColor)) $parameters['profile_sidebar_border_color'] = (string) $sidebarBorderColor;

    return $this->doCall('account/update_profile_colors', $parameters, true);
  }

  /**
   * Returns the remaining number of API requests available to the requesting user before 
   * the API limit is reached for the current hour.
   *
   * @return array
   */
  public function getRateLimitStatus()
  {
    return $this->doCall('account/rate_limit_status', array(), true, false, 'hash');
  }

  /**
   * Returns the 20 most recent favorite statuses for the authenticating user or user specified 
   * by the $id parameter
   *
   * @param  string  $id    The id or screen name of the user for whom to request a list of favorite statuses.
   * @param  int     $page
   *
   * @return TweetCollection
   */
  public function getFavorites($id = null, $page = null)
  {
    $parameters = array();
    
    if (!is_null($page))
    {
      $parameters['page'] = (int) $page;
    }

    $url = 'favorites';
    
    if (!is_null($id))
    {
      $url = 'favorites/'.urlencode($id);
    }

    return $this->doCall($url, $parameters, true, false);
  }

  /**
   * Favorites the status specified in the id parameter as the authenticating user.
   *
   * @param  string $id
   *
   * @return Tweet
   */
  public function createFavorite($id)
  {
    return $this->doCall('favorites/create/'.urlencode($id), array('id' => $id), true);
  }

  /**
   * Un-favorites the status specified in the id parameter as the authenticating user.
   *
   * @param  string $id
   *
   * @return Tweet
   */
  public function deleteFavorite($id)
  {
    return $this->doCall('favorites/destroy/'.urlencode($id), array('id' => $id), true);
  }

  /**
   * Enables notifications for updates from the specified user to the authenticating user.
   * This method requires the authenticated user to already be friends with the specified 
   * user otherwise the error "there was a problem following the specified user" will be 
   * returned.
   *
   * @param  string $id
   *
   * @return TwitterUser
   */
  public function followUser($id)
  {
    return $this->doCall('notifications/follow/'.urlencode($id), array('id' => $id), true);
  }

  /**
   * Disables notifications for updates from the specified user to the authenticating user.
   * This method requires the authenticated user to already be friends with the specified 
   * user otherwise the error "there was a problem following the specified user" will be 
   * returned.
   *
   * @param  string $id
   */
  public function unfollowUser($id)
  {
    return $this->doCall('notifications/leave/'.urlencode($id), array('id' => $id), true);
  }

  /**
   * Blocks the user specified in the id parameter as the authenticating user.
   *
   * @param  string $id
   */
  public function blockUser($id)
  {
    return $this->doCall('blocks/create/'.urlencode($id), array('id' => $id), true);
  }

  /**
   * Un-blocks the user specified in the id parameter as the authenticating user.
   *
   * @param  string $id
   */
  public function unblockUser($id)
  {
    return $this->doCall('blocks/destroy/'.urlencode($id), array('id' => $id), true);
  }

  /**
   * Test the connection to Twitter
   *
   * @return Boolean
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