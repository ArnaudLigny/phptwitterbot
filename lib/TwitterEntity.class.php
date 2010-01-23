<?php
/**
 * Twitter entity base class
 *
 * @author	 Nicolas Perriault <nperriault at gmail dot com>
 * @license	 MIT License
 */
class TwitterEntity extends ArrayObject
{
  protected static 
    $classMap = array(
      'status'          => 'Tweet',
      'statuses'        => 'TweetCollection',
      'user'            => 'TwitterUser',
      'users'           => 'TwitterUserCollection',
      'direct-message'  => 'TwitterDirectMessage',
      'direct_message'  => 'TwitterDirectMessage',
      'direct-messages' => 'TwitterDirectMessageCollection',
      'direct_messages' => 'TwitterDirectMessageCollection',
      'sender'          => 'TwitterUser',
      'recipient'       => 'TwitterUser',
    );
  
  /**
   * Creates a Tweet from an XML element 
   *
   * @param  DOMDocument  $document  A DOM Document instance
   *
   * @return TwitterEntity
   *
   * @throws InvalidArgumentException if no entity can be generated from the provided source
   */
  public static function createFromXML(DOMDocument $dom)
  {
    // We need the node name to detect type of objects to convert 
    $type = trim(strtolower($dom->firstChild->nodeName));
    
    if (!isset(self::$classMap[$type]) || !class_exists(self::$classMap[$type], true))
    {
      throw new InvalidArgumentException(sprintf('Type "%s" is not supported', $type));
    }
    
    $entityClassName = self::$classMap[$type];
    
    $entries = simplexml_import_dom($dom);
    
    // Collections
    if (strpos($entityClassName, 'Collection'))
    {
      $elements = array();
      
      foreach ($entries as $entry)
      {
        $dom = new DOMDocument();
        $dom->loadXML($entry->asXML());
        $elements[] = self::createFromXml($dom);
      }
      
      return $entity = new $entityClassName($elements);
    }
    
    $entity = new $entityClassName();
    
    // Simple entity
    foreach ($entries as $nodeName => $nodeValue)
    {
      if (!property_exists($entity, $nodeName))
      {
        continue;
      }
      
      if (in_array($nodeName, array_keys(self::$classMap)))
      {
        $dom = new DOMDocument();
        $dom->loadXML($nodeValue->asXML());
        $entity->$nodeName = self::createFromXml($dom);
      }
      else
      {
        $entity->$nodeName = self::cleanValue($nodeValue);
      }
    }
    
    return $entity;
  }
  
  /**
   * Cleans a value
   *
   * @param   mixed  $value
   *
   * @return  mixed
   */
  static protected function cleanValue($value)
  {
    if ('false' === strtolower($value))
    {
      return false;
    }
    elseif ('true' === strtolower($value))
    {
      return true;
    }
    else
    {
      return (string) $value;
    }
  }
}