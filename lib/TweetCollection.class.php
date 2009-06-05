<?php
/**
 * Collection of Tweet instances
 *
 */
class TweetCollection extends ArrayObject
{
  /**
   * Creates a TweetCollection from an XML element 
   *
   * @param  SimpleXMLElement $entry  An XML element
   *
   * @return TweetCollection
   *
   * @throws InvalidArgumentException if an invalid entry is provided
   */
  public static function createFromXML(SimpleXMLElement $entry)
  {
    $tweets = array();
    
    foreach ($entry->status as $status)
    {
      $tweets[] = Tweet::createFromXML($status);
    }
    
    return new self($tweets);
  }
}