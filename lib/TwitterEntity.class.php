<?php
/**
 * Twitter entity base class
 *
 */
class TwitterEntity
{
  protected static 
    $classMap = array(
      'status'          => 'Tweet',
      'statuses'        => 'TweetCollection',
      'user'            => 'TwitterUser',
      'users'           => 'TwitterUserCollection',
      'direct-message'  => 'TwitterDirectMessage',
      'direct-messages' => 'TwitterDirectMessageCollection',
    );
  
  /**
   * Creates a Tweet from an XML element 
   *
   * @param  DOMDocument  $document  A DOM Document instance
   *
   * @return TwitterEntity
   *
   * @throws InvalidArgumentException if no entity can be generated from source
   */
  public static function createFromXML(DOMDocument $dom)
  {
    // We need the node name to detect type of objects to convert 
    $type = trim(strtolower($dom->firstChild->nodeName));
    
    if (!isset(self::$classMap[$type]) || !class_exists(self::$classMap[$type], true))
    {
      throw new InvalidArgumentException(sprintf('Type "%s" is not supported', $type));
    }
    
    $entity = new self::$classMap[$type]();
    
    foreach (simplexml_import_dom($dom) as $nodeName => $nodeValue)
    {
      if (!property_exists($entity, $nodeName))
      {
        throw new InvalidArgumentException(sprintf('Propery "%s" does not exist here', $nodeName));
      }
      
      switch ($nodeName)
      {
        case 'sender':
        case 'recipient':
        case 'user':
          $entity->$nodeName = self::createFromXml(DOMDocument::loadXML($nodeValue->asXML()));
          break;
        
        default:
          $entity->$nodeName = self::convertValue($nodeValue);
          break;
      }
    }
    
    return $entity;
  }
  
  protected function convertValue($value)
  {
    if ('false' === strtolower($value))
    {
      $value = false;
    }
    elseif ('true' === strtolower($value))
    {
      $value = true;
    }
    else
    {
      return (string) $value;
    }
  }
}