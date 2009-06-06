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
    $text,
    $source,
    $truncated,
    $in_reply_to_status_id,
    $in_reply_to_user_id,
    $favorited,
    $in_reply_to_screen_name,
    $user;
}