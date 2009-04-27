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
 * @author	Nicolas Perriault <nperriault at gmail dot com>
 * @version	1.0.0
 * @license	MIT License
 */
class TwitterBot
{
  const VERSION = '0.8';
  
  protected 
    $client = null,
    $debug  = false,
    $follow = false,
    $terms  = null;
  
  /**
   * Instanciates a new Bot
   *
   * @param  string  $username  Twitter username
   * @param  string  $password  Twitter password
   *
   * @throws RuntimeException if there's environment configuration problems
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
  }
  
  /**
   * Bot will search for twits containing given terms in the public timeline, and retweet 
   * them using a given template.
   *
   * @param  string   $terms    The search terms to filter the timeline with
   * @param  string   $template The template to use to format bot's twits, sprintf standard
   * @param  Boolean  $follow   Shall the bot follow the twit original author?
   *
   * @throws RuntimeException if any error occurs
   */
  public function searchAndRetweet($terms, $template = 'RT @%s: %s', $follow = false)
  {
    if (!is_string($terms) or !mb_strlen($terms))
    {
      throw new RuntimeException('Search terms must be a string'); 
    }
    
    $message = $author = null;

    foreach ($this->searchFor($terms) as $entry)
    {
      $author = $this->extractAuthorName($entry->author->name);
      
      if (strtolower($this->client->getUsername()) != strtolower($author))
      {
        $message = trim(sprintf($template, $author, (string) $entry->title));
        $this->debug('Matching message found: '.$message);
        
        break;
      }
    }
    
    if (!$message || !$author)
    {
      throw new RuntimeException('No valid message found matching search terms, or invalid/empty author name');
    }
    
    $this->debug('Sending message to twitter');
    
    try 
    {
      $this->client->updateStatus($this->truncateText($message));
    }
    catch (Exception $e) 
    {
      throw new RuntimeException('Communication with the twitter API failed: '.$e->getMessage());
    }
    
    if ($follow && !$this->client->existsFriendship($this->client->getUsername(), $author))
    {
      $this->debug('Following '.$author);
      
      try
      {
        $this->client->createFriendship($author, true);
      }
      catch (Exception $e) 
      {
        $this->debug(sprintf('Cannot follow "%s" because: %s', $author, $e->getMessage()));
      }
    }
    
    $this->debug('Done.');
  }
  
  /**
   * Iterates over followers and follow them if needed
   *
   * @throws RuntimeException if any error occurs
   */
  public function followFollowers()
  {
    $this->debug('Checking for followers');
    
    foreach ($this->client->getFollowers() as $follower)
    {
      if ($this->client->existsFriendship($this->client->getUsername(), $follower['screen_name']))
      {
        continue;
      }

      try
      {
        $this->client->createFriendship($follower['screen_name'], true);
        $this->debug('Following new follower: '.$follower['screen_name']);
      }
      catch (Exception $e)
      {
        $this->debug(sprintf('Skipping following "%s": %s', $follower['screen_name'], $e->getMessage()));
      }
    }
    
    $this->debug('Done.');
  }

  /**
   * Extract the author name from a xml string
   *
   * @param  SimpleXMLElement|string $authorName  The author name
   *
   * @return string
   */
  protected function extractAuthorName($authorName)
  {
    return mb_substr((string) $authorName, 0, mb_strpos((string) $authorName, ' ('));
  }
  
  /**
   * Search twitter for given terms and returns results as XML nodes collection
   *
   * @param  string  $terms  Search terms
   *
   * @return array
   *
   * @throws RuntimeException if no entry is found
   */
  protected function searchFor($terms)
  {
    if (!$xml = @simplexml_load_file('http://search.twitter.com/search.atom?q='.urlencode($terms)))
    {
      throw new RuntimeException('Unable to load or parse search results feed');
    }
    
    if (!count($entries = $xml->entry))
    {
      throw new RuntimeException('No entry found');
    }
    
    return $entries;
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
    
    printf("$message\n");
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