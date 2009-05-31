<?php
require_once dirname(__FILE__).'/vendor/twitter.php';

/**
 * Simple Twitter Bot class. API documentation should be self-explanatory.
 *
 * This bot is designed to be run on a regular basis, eg. using CRON.
 *
 * This bot is *NOT* intended to be used for SPAM purpose.
 *
 * This class requires the PHP Twitter library: http://classes.verkoyen.eu/twitter/
 *
 * TODO:
 *
 *  - handle a CURL powered HTTP client if available
 *
 * @author	Nicolas Perriault <nperriault at gmail dot com>
 * @version	2.0.0
 * @license	MIT License
 */
class TwitterBot
{
  const 
    VERSION = '2.0.0';
  
  protected 
    $client   = null,
    $debug    = false,
    $username = null;
  
  static protected
    $searchUrl = 'http://search.twitter.com/search.atom';
  
  /**
   * Instanciates a new Bot
   *
   * @param  string  $username  Twitter username
   * @param  string  $password  Twitter password
   * @param  Boolean $debug     Debugging mode enabled?
   *
   * @throws RuntimeException if there are environment configuration problems
   */
  public function __construct($username, $password, $debug = false)
  {
    $this->debug = (boolean) $debug;
    
    if (!function_exists('mb_strlen'))
    {
      throw new RuntimeException('mbstring must be installed for TwitterBot to work properly');
    }
    
    $this->debug(sprintf('Creating "%s" bot', $username));
    $this->client = new Twitter($username, $password);
    $this->username = $username;
  }
  
  /**
   * Static method to use fluid interface. Example:
   *
   *    $bot = TwitterBot::create('mylogin', 'mypassword')->followFollowers();
   *
   * @param  string  $username  Twitter username
   * @param  string  $password  Twitter password
   * @param  Boolean $debug     Debugging mode enabled?
   *
   * @throws RuntimeException if there's environment configuration problems
   */
  static public function create($username, $password, $debug = false)
  {
    return new self($username, $password, $debug);
  }
  
  /**
   * Iterates over followers and follow them if needed
   *
   * @return int  The number of new followers added
   *
   * @throws RuntimeException if any error occurs
   */
  public function followFollowers()
  {
    $this->debug('Checking for followers');
    
    $followers = 0;
    
    foreach ($this->client->getFollowers() as $follower)
    {
      if ($this->client->existsFriendship($this->getUsername(), $follower['screen_name']))
      {
        continue;
      }

      try
      {
        $this->client->createFriendship($follower['screen_name'], true);
        $this->debug('Following new follower: '.$follower['screen_name']);
        $followers++;
      }
      catch (Exception $e)
      {
        $this->debug(sprintf('Skipping following "%s": %s', $follower['screen_name'], $e->getMessage()));
      }
    }
    
    $this->debug(sprintf('%d followers added', $followers));
    
    return $followers;
  }

  /**
   * Watch for direct messages and process them using a user defined PHP callable. Example:
   *
   *    function uppercase_me($message) {
   *      return strtoupper($message['text']);
   *    }
   *    TwitterBot::create('user', 'pass')->processDirectMessages('uppercase_me');
   *
   * @param  string|array  $callable        A PHP callable which will process the DM and generate a reply
   * @param  Boolean       $replyPrivately  Reply to DM sender privately, or publish it publicly?
   * @param  Boolean       $stopOnError     Stop on error during processing?
   *
   * @return int Number of processed DM
   *
   * @throws InvalidArgumentException if the provided callable is invalid
   * @throws RuntimeException         if a problem has been encountered at runtime
   */
  public function processDirectMessages($callable, $replyPrivately = false, $stopOnError = false)
  {
    if (!is_callable($callable))
    {
      throw new InvalidArgumentException('Invalid PHP callable');
    }
    
    $messages = $this->client->getDirectMessages();
    
    if (0 === $nbMessages = count($messages))
    {
      $this->debug('No DM waiting for beeing processed');
      
      return 0;
    } 
    else if (20 == $nbMessages)
    {
      // There's maybe more than 20 pages of DMs
      // TODO: recursive array_merge with next page DMs
    }
    
    foreach ($messages as $message)
    {
      $this->debug(sprintf('Processing DM from "%s": "%s"', $message['sender']['screen_name'], $message['text']));
    
      try
      {
        $reply = @call_user_func($callable, $message);
        
        $this->debug(sprintf('Generated message: "%s"', $reply));
        
        if (is_null($reply) || !is_string($reply) || 0 === mb_strlen($reply))
        {
          // empty reply returned, skipping
          continue;
        }
        
        if ($replyPrivately)
        {
          $this->client->sendDirectMessage($message['sender']['screen_name'], $reply);
        }
        else
        {
          $this->client->updateStatus($this->truncateText($reply));
        }

        $this->client->deleteDirectMessage($message['id']);
      }
      catch (Exception $e)
      {
        $this->debug($message = sprintf('{%s} thrown: "%s"', get_class($e), $e->getMessage()));
        
        if ($stopOnError)
        {
          throw new RuntimeException('Processing stopped: '.$message);
        }
      }
    }
  }

  /**
   * Bot will search for twits containing given terms in the public timeline, and retweet 
   * them using a given template.
   *
   * Options are:
   *  - 'source':   The source to search matching tweets in. Possible values are:
   *    * 'public':   Search into the public timeline (default)
   *    * 'friends':  Search into the user friends timeline
   *  - 'template': The template to use to format bot's twits, sprintf standard
   *  - 'follow':   Shall the bot follow the twit original author?
   *
   * @param  string   $terms    The search terms to filter the timeline with
   * @param  array    $options  An array of options (see available values above)
   *
   * @throws RuntimeException if any error occurs
   */
  public function searchAndRetweet($terms, array $options = array())
  {
    $options = array_merge(array('template' => 'RT @%s: %s', 'follow' => false, 'source' => 'public'), $options);
    
    if (!is_string($terms) or !mb_strlen($terms) || mb_strlen($terms) > 140)
    {
      throw new RuntimeException(sprintf('Search terms must be a 140 chars max string (you provided "%s")', var_export($terms, true))); 
    }
    
    $message = null;

    foreach ($this->searchFor($terms, $options['source']) as $entry)
    {
      if (strtolower($this->getUsername()) != strtolower($entry->author))
      {
        $message = trim(sprintf($options['template'], $entry->author, $entry->title));
        
        $this->debug(sprintf('Matching message found: "%s"', $message));
        
        break;
      }
    }
    
    if (!$message)
    {
      throw new RuntimeException('No valid message found matching search terms');
    }
    
    $this->debug('Sending message to twitter');
    
    try 
    {
      $this->client->updateStatus($this->truncateText($message));
    }
    catch (Exception $e) 
    {
      throw new RuntimeException(sprintf('Communication with the twitter API failed: "%s"', $e->getMessage()));
    }
    
    if ($options['follow'] && !$this->client->existsFriendship($this->getUsername(), $entry->author))
    {
      $this->debug(sprintf('Following user "%s"', $entry->author));
      
      try
      {
        $this->client->createFriendship($entry->author, true);
      }
      catch (Exception $e) 
      {
        $this->debug(sprintf('Cannot follow user "%s" because: "%s"', $entry->author, $e->getMessage()));
      }
    }
    
    $this->debug('Done.');
  }
  
  /**
   * Retrieves TwitterClient instance
   *
   * @return TwitterClient
   */
  public function getClient()
  {
    return $this->client;
  }
  
  /**
   * Returns bot username
   *
   * @return string
   */
  public function getUsername()
  {
    return $this->username;
  }
  
  /**
   * Search twitter for given terms and returns results as XML nodes collection
   *
   * @param  string  $terms   Search terms
   * @param  string  $source  The source to search in ('public', or 'friends')
   *
   * @return array
   *
   * @throws RuntimeException if no entry is found
   */
  public function searchFor($terms, $source = 'public')
  {
    $entries = array();
    
    switch (strtolower(trim($source)))
    {
      case 'public':
        $url = $this->getSearchUrl($terms);
      
        $this->debug(sprintf('Searching for terms "%s" using url "%s"', $terms, $url));
        
        if (!$xml = @simplexml_load_file($url))
        {
          throw new RuntimeException(sprintf('Unable to load or parse search results feed from url "%s"', $url));
        }
    
        if (!$count = count($xmlEntries = $xml->entry))
        {
          throw new RuntimeException(sprintf('No entry found matching the provided terms, "%s"', $terms));
        }
    
        $this->debug(sprintf('Search for "%s" returned %d results', $terms, $count));
      
        foreach ($xmlEntries as $xmlEntry)
        {
          $entries[] = Tweet::createfromXML($xmlEntry);
        }
        break;
      
      case 'friends':   
        // Search into the user followers timeline
        foreach ($this->client->getFriendsTimeline(null, null, 200) as $entry)
        {
          if (preg_match(sprintf('/%s/i', $terms), $entry['text']))
          {
            $entries[] = Tweet::createfromArray($entry);
          }
        }
        break;
      
      default:
        throw new InvalidArgumentException(sprintf('Unknown tweets source "%s"', $source));
        break;
    }
    
    return $entries;
  }
  
  /**
   * Generates the search url for given terms
   *
   * @param  string  $terms
   *
   * @return string
   */
  protected function getSearchUrl($terms)
  {
    return sprintf('%s?q=%s', self::$searchUrl, urlencode($terms));
  }
  
  /**
   * Outputs a message, if $debug property is set to true
   * 
   * @param  string  $message
   */
  protected function debug($message)
  {
    if (!$this->debug)
    {
      return;
    }
    
    printf('%s%s', $message, PHP_EOL);
  }
  
  /**
   * Truncates given text to a given number of chars
   *
   * @param  string  $text    Input text
   * @param  int     $nChars  Number of max chars
   * @param  string  $suffix  A suffix to append to the truncated text
   *
   * @return string 
   */
  protected function truncateText($text, $nChars = 140, $suffix = '…')
  {
    if (mb_strlen($text) <= $nChars)
    {
      return $text;
    }
    
    return mb_substr($text, 0, $nChars - mb_strlen($suffix)) . $suffix;
  }
}

/**
 * This class represents a Tweet
 *
 */
class Tweet
{
  public 
    $author,
    $title,
    $date;
  
  /**
   * COnstructor
   *
   * @param  mixed  $title
   * @param  mixed  $author
   * @param  mixed  $date
   *
   * @return Tweet
   */
  public function __construct($title, $author, $date)
  {
    $this->author = (string) $author;
    $this->title = (string) $title;
    $this->date = (string) $date;
  }

  /**
   * Creates a Tweet from an array
   *
   * @param  array $entry  An array
   *
   * @return Tweet
   */
  public static function createFromArray(array $entry)
  {
    return new self($entry['text'], $entry['user']['screen_name'], $entry['created_at']);
  }
  
  /**
   * Creates a Tweet from an XML element 
   *
   * @param  SimpleXMLElement $entry  An XML element
   *
   * @return Tweet
   */
  public static function createFromXML(SimpleXMLElement $entry)
  {
    return new self($entry->title, self::extractAuthorName($entry->author->name), $entry->published);
  }
  
  /**
   * Extract the author name from a xml string
   *
   * @param  SimpleXMLElement|string $authorName  The author name
   *
   * @return string
   *
   * @throws InvalidArgumentException if author name cannot be retrieved
   */
  static protected function extractAuthorName($authorName)
  {
    if (0 === mb_strlen($name = mb_substr((string) $authorName, 0, mb_strpos((string) $authorName, ' ('))))
    {
      throw new InvalidArgumentException(sprintf('Unable to retrieve author name from value "%s"', var_export($authorName, true)));
    }
    
    return $name;
  }
}