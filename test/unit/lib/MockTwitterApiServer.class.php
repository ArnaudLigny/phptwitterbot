<?php
require_once dirname(__FILE__).'/../../../lib/TwitterApiServer.class.php';

class MockTwitterApiServer extends TwitterApiServer
{
  public function request($apiPath, $parameters = array(), $httpMethod = 'GET', $authenticate = true)
  {
    if ('search.xml' === $apiPath)
    {
      return file_get_contents(dirname(__FILE__).'/../xml/server/search_test.json');
    }
    else
    {
      return file_get_contents(dirname(__FILE__).'/../xml/server/'.$apiPath);
    }
  }
}