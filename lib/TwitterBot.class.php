<?php
require_once dirname(__FILE__).'/TwitterApiClient.class.php';
require_once dirname(__FILE__).'/Tweet.class.php';

/**
 * Simple Twitter Bot class. API documentation should be self-explanatory.
 *
 * This bot is designed to be run on a regular basis, eg. using CRON.
 *
 * This bot is *NOT* intended to be used for SPAM purpose.
 *
 * This class requires the PHP Twitter library: http://classes.verkoyen.eu/twitter/
 *
 *
 * @author	Nicolas Perriault <nperriault at gmail dot com>
 * @license	MIT License
 */
class TwitterBot
{
  const MAX_FOLLOWING = 2000;
  
  protected 
    $accountInfos = null,
    $client       = null,
    $debug        = false;
  
  /**
   * Instanciates a new Bot
   *
   * @param  string   $username  Twitter username
   * @param  string   $password  Twitter password
   * @param  Boolean  $debug     Debugging mode enabled?
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
    
    $this->client = new TwitterApiClient();
    $this->client->setUsername($username);
    $this->client->setPassword($password);
  }
  
  /**
   * Static method to use fluid interface. Example:
   *
   *    $bot = TwitterBot::create('mylogin', 'mypassword')->followFollowers();
   *
   * @param  string   $username  Twitter username
   * @param  string   $password  Twitter password
   * @param  Boolean  $debug     Debugging mode enabled?
   *
   * @throws RuntimeException if there's environment configuration problems
   */
  static public function create($username, $password, $debug = false)
  {
    return new TwitterBot($username, $password, $debug);
  }
  
  /**
   * Outputs a message, if $debug property is set to true
   * 
   * @param  string  $message
   */
  public function debug($message)
  {
    if (!$this->debug)
    {
      return;
    }
    
    printf('[bot]  %s%s', $message, PHP_EOL);
  }
  
  /**
   * Iterates over followers and follow them if needed
   *
   * @return int  The number of new followers added
   *
   * @throws RuntimeException         if any error occurs
   * @throws InvalidArgumentException if internal configuration problem occurs
   */
  public function followFollowers()
  {
    $this->debug('Checking for followers');
    
    $nbFollowers = 0;
    
    $limit = self::MAX_FOLLOWING;
    
    foreach ($this->client->getFollowers() as $follower)
    {
      if ($this->getBotAccountInfo('friends_count') >= $limit - $nbFollowers)
      {
        $this->debug('Max followers number reached, skipped mass following process');
      
        return 0;
      }
      
      if ($this->client->existsFriendship($this->getUsername(), $follower['screen_name']))
      {
        continue;
      }

      try
      {
        $this->client->createFriendship($follower['screen_name'], true);
        
        $this->debug(sprintf('Following new follower: "%s"', $follower['screen_name']));
        
        $nbFollowers++;
      }
      catch (Exception $e)
      {
        $this->debug(sprintf('Skipping following "%s": "%s"', $follower['screen_name'], $e->getMessage()));
      }
    }
    
    $this->debug(sprintf('%s follower%s added', 0 === $nbFollowers ? 'No' : (string) $nbFollowers, $nbFollowers > 1 ? 's' : ''));
    
    return $nbFollowers;
  }
  
  /**
   * Retrieves bot account informations
   *
   * @param  string  $name  Property name to retrieve
   *
   * @return mixed
   *
   * @throws InvalidArgumentException if property doesn't exist
   */
  public function getBotAccountInfo($name)
  {
    if (is_null($this->accountInfos))
    {
      $this->accountInfos = $this->client->getUser($this->getUsername());
    }
    
    if (!array_key_exists($name, $this->accountInfos))
    {
      throw new InvalidArgumentException(sprintf('Account property "%s" does not exist', $name));
    }
    
    return $this->accountInfos[$name];
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
    return $this->client->getUsername();
  }
  
  /**
   * Processes each message of the bot public timeline.
   *
   * Example:
   *
   *    function uppercase_me(Tweet $tweet)
   *    {
   *       echo strtoupper($tweet->text).PHP_EOL;
   *    }
   *    TwitterBot::create('user', 'pass')->processBotTimeline('uppercase_me');
   *
   * @param  string|array  $callback  A PHP callable
   *
   * @throws InvalidArgumentException  if provided callback is invalid
   * @throws RuntimeException          if the callback throwed an Exception
   */
  public function processBotTimeline($callback, $options = array())
  {
    $this->debug('Start processing bot timeline...');
    
    if (!is_callable($callback))
    {
      throw new InvalidArgumentException(sprintf("Invalid callback: %s", var_export($callback, true)));
    }
    
    $options = array_merge(array('max' => 20, 'source' => 'public'), $options);
    
    switch (strtolower(trim($options['source'])))
    {
      case 'public':
        $statuses = $this->client->getUserTimeline(null, null, null, (int) $options['max']);
        break;

      case 'friends':
        $statuses = $this->client->getFriendsTimeline(null, null, null, (int) $options['max']);
        break;

      default:
        throw new InvalidArgumentException(sprintf('Unsupported source "%s"', $options['source']));
        break;
    }
    
    foreach ($statuses as $tweet)
    {
      try
      {
        $this->debug(sprintf('Retrieved %s bot status: "%s"', $options['source'], $tweet->text));
        
        call_user_func($callback, $tweet);
        
        $this->debug('Tweet processed ok.');
      }
      catch (TwitterBotSkipException $e)
      {
        $this->debug(sprintf('Bot processing skipped programmatically: %s', $e->getMessage()));
        
        continue;
      }
      catch (TwitterBotStopException $e)
      {
        $this->debug(sprintf('Bot processing interrupted programmatically: %s', $e->getMessage()));
        
        break;
      }
      catch (Exception $e)
      {
        throw new RuntimeException(sprintf('Callback "%s" throwed a "%s": "%s"', 
                                           var_export($callback, true), 
                                           get_class($e), 
                                           $e->getMessage()));
      }
    }
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
    else if (20 === $nbMessages)
    {
      // There may be more than 20 pages of DMs
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
      catch (TwitterBotSkipException $e)
      {
        $this->debug('Bot processing skipped programmatically.');
        
        continue;
      }
      catch (TwitterBotStopException $e)
      {
        $this->debug('Bot processing interrupted programmatically.');
        
        break;
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
   *  - source:   The source to search matching tweets in. Possible values are:
   *    * public:   Search into the public timeline (default)
   *    * friends:  Search into the user friends timeline
   *  - template: The template to use to format bot's twits, sprintf standard
   *  - follow:   Shall the bot follow the twit original author?
   *
   * @param  string   $terms    The search terms to filter the timeline with
   * @param  array    $options  An array of options (see available values above)
   *
   * @throws RuntimeException if any error occurs
   */
  public function searchAndRetweet($terms, array $options = array())
  {
    $this->debug('Start searchAndRetweet for terms: '.$terms);
    
    $options = array_merge(array('template' => 'RT @%s: %s', 'follow' => false, 'source' => 'public'), $options);
    
    if (!is_string($terms) or !mb_strlen($terms) || mb_strlen($terms) > 140)
    {
      throw new RuntimeException(sprintf('Search terms must be a 140 chars max string (you provided "%s")', var_export($terms, true))); 
    }
    
    $message = null;
    
    $entries = $this->client->search($terms, array('source' => $options['source']));
    
    $this->debug(sprintf('Found %d results', count($entries)));

    foreach ($entries as $entry)
    {
      if (strtolower($this->getUsername()) != strtolower($entry->from_user))
      {
        $message = trim(sprintf($options['template'], $entry->from_user, $entry->text));
        
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
    
    if ($options['follow'] && !$this->client->existsFriendship($this->getUsername(), $entry->from_user))
    {
      $this->debug(sprintf('Following user "%s"', $entry->from_user));
      
      try
      {
        $this->client->createFriendship($entry->from_user, true);
      }
      catch (Exception $e) 
      {
        $this->debug(sprintf('Cannot follow user "%s" because: "%s"', $entry->from_user, $e->getMessage()));
      }
    }
    
    $this->debug('Done.');
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
 * Bot stop exception
 *
 */
class TwitterBotStopException extends Exception
{
}

/**
 * Bot skip exception
 *
 */
class TwitterBotSkipException extends Exception
{
}