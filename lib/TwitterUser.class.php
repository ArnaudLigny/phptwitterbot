<?php
require_once dirname(__FILE__).'/Tweet.class.php';
require_once dirname(__FILE__).'/TwitterEntity.class.php';

/**
 * This class represents a Twitter user, for unified interface access regarding format 
 * used by the twitter API
 *
 * @author	 Nicolas Perriault <nperriault at gmail dot com>
 * @license	 MIT License
 */
class TwitterUser extends TwitterEntity
{
  public
    $id,
    $name,
    $screen_name,
    $location,
    $description,
    $profile_image_url,
    $url,
    $protected,
    $followers_count,
    $profile_background_color,
    $profile_text_color,
    $profile_link_color,
    $profile_sidebar_fill_color,
    $profile_sidebar_border_color,
    $friends_count,
    $created_at,
    $favourites_count,
    $utc_offset,
    $time_zone,
    $profile_background_image_url,
    $profile_background_tile,
    $statuses_count,
    $notifications,
    $following,
    $status;
}