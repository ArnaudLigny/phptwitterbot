<?php
require_once dirname(__FILE__).'/Tweet.class.php';

/**
 * Collection of Tweet instances
 *
 * @author	 Nicolas Perriault <nperriault at gmail dot com>
 * @license	 MIT License
 */
class TweetCollection extends TwitterEntity
{
  static public function createFromJSON($json)
  {
    $tweets = json_decode($json, true);
    
    if (!is_array($tweets) || !isset($tweets['results']))
    {
      throw new InvalidArgumentException('Unable to decode JSON response');
    }

    $tweetCollection = array();
    
    foreach ($tweets['results'] as $tweetArray)
    {
      $tweetCollection[] = Tweet::createFromArray($tweetArray);
    }
    
    return new self($tweetCollection);
  }
}