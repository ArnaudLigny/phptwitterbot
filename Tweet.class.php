<?php
/**
 * This class represents a Tweet, for unified interface access regarding format 
 * used by the twitter API
 *
 * This class can only be instanciated using two static methods, createFromArray() and createFromXML().
 *
 * @author	 Nicolas Perriault <nperriault at gmail dot com>
 * @version	 2.0.0
 * @license	 MIT License
 */
class Tweet
{
  public 
    $author = null,
    $title  = null,
    $date   = null;
  
  /**
   * Constructor
   *
   * @param  mixed  $title   The twitt message
   * @param  mixed  $author  The author screen name
   * @param  mixed  $date    The publication date
   *
   * @return Tweet
   */
  protected function __construct($title, $author, $date)
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
   * @param  SimpleXMLElement|string  $authorName  The author name
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