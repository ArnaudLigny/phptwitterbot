<?php
require_once dirname(__FILE__).'/TwitterDirectMessage.class.php';

/**
 * This class represents a Twitter Direct Messages collection, for unified interface access regarding format 
 * used by the twitter API
 *
 * @author	 Nicolas Perriault <nperriault at gmail dot com>
 * @license	 MIT License
 */
class TwitterDirectMessageCollection extends ArrayObject
{
  /**
   * Creates a TwitterDirectMessageCollection from an XML element 
   *
   * @param  SimpleXMLElement $entry  An XML element
   *
   * @return TwitterDirectMessageCollection
   *
   * @throws InvalidArgumentException if an invalid entry is provided
   */
  public static function createFromXML(SimpleXMLElement $entry)
  {
    $messages = array();
    
    foreach ($entry->direct_message as $message)
    {
      $messages[] = TwitterDirectMessage::createFromXML($message);
    }
    
    return new self($messages);
  }
}