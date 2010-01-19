<?php
require_once dirname(__FILE__).'/../lib/TwitterUser.class.php';
require_once dirname(__FILE__).'/TwitterEntity.class.php';

/**
 * This class represents a Tweet, for unified interface access regarding format 
 * used by the twitter API
 *
 * @author	 Nicolas Perriault <nperriault at gmail dot com>
 * @license	 MIT License
 */
class Tweet extends TwitterEntity
{
  public
    $created_at,
    $id,
    $geo,
    $text,
    $source,
    $truncated,
    $from_user,
    $from_user_id,
    $to_user,
    $to_user_id,
    $in_reply_to_status_id,
    $in_reply_to_user_id,
    $iso_language_code,
    $profile_image_url,
    $favorited,
    $in_reply_to_screen_name,
    $user;
  
  static public function createFromArray(array $array = array())
  {
    $entity = new self();
    
    foreach ($array as $propertyName => $propertyValue)
    {
      if (!property_exists($entity, $propertyName))
      {
        throw new InvalidArgumentException(sprintf('Propery "%s" does not exist for Tweet entity', $propertyName));
      }
      
      $entity->$propertyName = parent::cleanValue($propertyValue);
    }
    
    return $entity;
  }
}