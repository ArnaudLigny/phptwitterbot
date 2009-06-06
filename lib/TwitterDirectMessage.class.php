<?php
require_once dirname(__FILE__).'/TwitterUser.class.php';
require_once dirname(__FILE__).'/TwitterEntity.class.php';

/**
 * This class represents a Twitter Direct Message, for unified interface access regarding format 
 * used by the twitter API
 *
 * @author	 Nicolas Perriault <nperriault at gmail dot com>
 * @license	 MIT License
 */
class TwitterDirectMessage extends TwitterEntity
{
  public
    $id,
    $sender_id,
    $text,
    $recipient_id,
    $created_at,
    $sender_screen_name,
    $recipient_screen_name,
    $sender,
    $recipient;
}