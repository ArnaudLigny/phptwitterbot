<?php
require_once dirname(__FILE__).'/TwitterUser.class.php';

/**
 * This class represents a Twitter user collection, for unified interface access regarding format 
 * used by the twitter API
 *
 * @author	 Nicolas Perriault <nperriault at gmail dot com>
 * @license	 MIT License
 */
class TwitterUserCollection extends ArrayObject
{
  /**
   * Creates a TwitterUserCollection from an XML element 
   *
   * @param  SimpleXMLElement $entry  An XML element
   *
   * @return TwitterUserCollection
   *
   * @throws InvalidArgumentException if an invalid entry is provided
   */
  public static function createFromXML(SimpleXMLElement $entry)
  {
    $users = array();
    
    foreach ($entry->user as $user)
    {
      $users[] = TwitterUser::createFromXML($user);
    }
    
    return new self($users);
  }
}