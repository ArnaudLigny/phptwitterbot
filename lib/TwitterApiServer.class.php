<?php
/**
 * Twitter API server proxy class, simulates a Twitter API based HTTP server
 *
 * @author  Nicolas Perriault <nperriault@gmail.com>
 * @license MIT License
 */
class TwitterApiServer
{
  protected
    $baseUrl  = 'http://twitter.com',
    $options  = array(),
    $password = null,
    $username = null;
  
  public function __construct($baseUrl = null, array $options = array())
  {
    if (!function_exists('curl_init'))
    {
      throw new RuntimeException('Curl PHP support must be enabled in order to use the TwitterApiServer class. Check the curl php manual there: http://us.php.net/curl');
    }
    
    if (!is_null($baseUrl))
    {
      $this->baseUrl = $baseUrl;
    }
    
    $this->setOptions($options);
  }
  
  /**
   * Sends a request to the server, receive a response
   *
   * @param  string   $apiPath       Request API path
   * @param  array    $parameters    Parameters
   * @param  string   $httpMethod    HTTP method to use
   * @param  Boolean  $authenticate  Authenticate user?
   *
   * @return string  HTTP response
   */
  public function request($apiPath, $parameters = array(), $httpMethod = 'GET', $authenticate = true)
  {
    // FIXME: hack to detect search requests, unsupported with XML currently and having a different subdomain
    if ('search.xml' === $apiPath)
    {
      $url = 'http://search.twitter.com/search.json';
    }
    else
    {
      $url = sprintf('%s/%s', $this->baseUrl, $apiPath);
    }
    
    $queryString = utf8_encode(http_build_query($parameters));

    $options[CURLOPT_URL] = $url . ('GET' === $httpMethod ? '?' . $queryString : '');
    $options[CURLOPT_PORT] = $this->getOption('httpPort', 80);
    $options[CURLOPT_USERAGENT] = $this->getOption('userAgent');
    $options[CURLOPT_FOLLOWLOCATION] = true;
    $options[CURLOPT_RETURNTRANSFER] = true;
    $options[CURLOPT_TIMEOUT] = $this->getOption('timeOut');

    if ($authenticate)
    {
      $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
      $options[CURLOPT_USERPWD] = sprintf('%s:%s', $this->getUsername(), $this->getPassword());
    }

    if (!empty($parameters) && 'POST' === $httpMethod)
    {
      $options[CURLOPT_POST] = true;
      $options[CURLOPT_POSTFIELDS] = $queryString;

      // Probaly Twitter's webserver doesn't support the Expect: 100-continue header. So we reset it.
      $options[CURLOPT_HTTPHEADER] = array('Expect:');
    }

    $curl = curl_init();

    curl_setopt_array($curl, $options);

    $response = curl_exec($curl);
    $headers = curl_getinfo($curl);

    $errorNumber = curl_errno($curl);
    $errorMessage = curl_error($curl);

    curl_close($curl);
    
    if (!in_array($headers['http_code'], array(0, 200)))
    {
      throw new TwitterApiServerException(null, (int) $headers['http_code']);
    }
    
    if ($errorNumber != '')
    {
      throw new TwitterApiServerException($errorMessage, $errorNumber);
    }
    
    return $response;
  }
  
  /**
   * Get single option
   *
   * @param  string  $name
   * @param  mixed   $default
   *
   */
  public function getOption($name, $default = null)
  {
    return isset($this->options[$name]) ? $this->options[$name] : $default;
  }
  
  /**
   * Sets single option
   *
   * @param  string  $name
   * @param  mixed   $value
   */
  public function setOption($name, $value)
  {
    $this->options[$name] = $value;
  }
  
  /**
   * Sets options
   *
   * @param  array  $options
   */
  public function setOptions(array $options)
  {
    $this->options = $options;
  }
  
  /**
   * Get the password
   *
   * @return string
   */
  protected function getPassword()
  {
    return $this->password;
  }
  
  /**
   * Set password
   *
   * @param  string $password
   */
  public function setPassword($password)
  {
    $this->password = $password;
  }
  
  /**
   * Get the username
   *
   * @return string
   */
  public function getUsername()
  {
    return $this->username;
  }
  
  /**
   * Set username
   *
   * @param  string $username
   */
  public function setUsername($username)
  {
    $this->username = $username;
  }
}

/**
 * Twitter server commonication error
 *
 */
class TwitterApiServerException extends Exception
{
  /**
   * Http header-codes
   * @var  array
   */
  static protected $statusCodes = array(
      0 => 'OK',
    100 => 'Continue',
    101 => 'Switching Protocols',
    200 => 'OK',
    201 => 'Created',
    202 => 'Accepted',
    203 => 'Non-Authoritative Information',
    204 => 'No Content',
    205 => 'Reset Content',
    206 => 'Partial Content',
    300 => 'Multiple Choices',
    301 => 'Moved Permanently',
    302 => 'Found',
    303 => 'See Other',
    304 => 'Not Modified',
    305 => 'Use Proxy',
    306 => '(Unused)',
    307 => 'Temporary Redirect',
    400 => 'Bad Request',
    401 => 'Unauthorized',
    402 => 'Payment Required',
    403 => 'Forbidden',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    406 => 'Not Acceptable',
    407 => 'Proxy Authentication Required',
    408 => 'Request Timeout',
    409 => 'Conflict',
    411 => 'Length Required',
    412 => 'Precondition Failed',
    413 => 'Request Entity Too Large',
    414 => 'Request-URI Too Long',
    415 => 'Unsupported Media Type',
    416 => 'Requested Range Not Satisfiable',
    417 => 'Expectation Failed',
    500 => 'Internal Server Error',
    501 => 'Not Implemented',
    502 => 'Bad Gateway',
    503 => 'Service Unavailable',
    504 => 'Gateway Timeout',
    505 => 'HTTP Version Not Supported'
  );

  /**
   * Default constructor
   *
   * @param  string $message
   * @param  int $code
   */
  public function __construct($message = null, $code = null)
  {
    if (is_null($message) && !is_null($code) && array_key_exists((int) $code, self::$statusCodes)) 
    {
      $message = sprintf('HTTP %d: %s', $code, self::$statusCodes[(int) $code]);
    }

    parent::__construct($message, $code);
  }
}