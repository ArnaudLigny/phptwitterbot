<?php
require_once dirname(__FILE__).'/../vendor/yaml/sfYaml.php';
require_once dirname(__FILE__).'/TwitterBot.class.php';

/**
 * Simple twitter bots farm, configurable with a simple YAML file.
 *
 * Usage:
 *
 *    $farm = new TwitterBotsFarm(dirname(__FILE__).'/bots.yml');
 *    $farm->run();
 *
 * Example YAML configuration file content, assuming you configure a bot using a 'myfirstbot' twitter account:
 *
 *  global:
 *    debug: true
 *  bots:
 *    myfirstbot:
 *      password: mypassw0rd
 *      operations:
 *        searchAndRetweet:
 *          arguments:
 *            terms:      "my search terms"
 *            options:
 *              template: "RT @%s: %s"
 *          periodicity:  3600
 *
 * @author	Nicolas Perriault <nperriault at gmail dot com>
 * @license	MIT License
 */
class TwitterBotsFarm
{
  const
    CRONLOG_DEFAULT_FILENAME = '.phptwitterbot.cronlogs.log',
    MIN_PERIODICITY          = 60;
  
  public static
    $botClass = 'TwitterBot';
  
  protected 
    $config       = array(),
    $cronLogs     = array(),
    $cronLogsFile = null,
    $debug        = false,
    $forceUpdate  = false;
  
  /**
   * Constructor
   *
   * @param  string   $configFile    Absolute path to the yaml configuration file
   * @param  string   $cronLogsFile  Absolute path to the cronLogs file (optional)
   * @param  Boolean  $debug         Enables debug mode
   * @param  Boolean  $forceUpdate   Forces updates
   *
   * @throws InvalidArgumentException if path to file doesn't exist or is invalid
   */
  public function __construct($configFile, $cronLogsFile = null, $debug = false, $forceUpdate = false)
  {
    if (!file_exists($configFile) || !is_file($configFile))
    {
      throw new InvalidArgumentException(sprintf('Farm configuration file "%s" does not exist', $configFile));
    }
    
    $config = sfYaml::load($configFile);

    if (!is_array($config) || !array_key_exists('bots', $config) || !is_array($config['bots']))
    {
      throw new InvalidArgumentException('No valid bots configuration found, please check the documentation.');
    }
    
    $this->config = $config;
    
    $this->debug = $this->getGlobalConfigValue('debug', $debug);
    
    if (true === $this->forceUpdate = $forceUpdate)
    {
      $this->debug('Forcing updates');
    }
    
    $this->debug(sprintf('Creating farm from config file "%s"', $configFile));
    
    if (!is_null($cronLogsFile))
    {
      $this->debug(sprintf('Setting custom cronLogs file: "%s"', $cronLogsFile));
      
      $this->cronLogsFile = $cronLogsFile;
    }
  }
  
  /**
   * Static method to instantiate a new farm. Example:
   *
   *    TwitterBotsFarm::create($path_to_yaml_config_file)->run();
   *
   * @param  string   $configFile    Absolute path to the yaml configuration file
   * @param  string   $cronLogsFile  Absolute path to the cronLogs file (optional)
   * @param  Boolean  $debug         Enables debug mode
   * @param  Boolean  $forceUpdate   Forces updates
   *
   * @throws InvalidArgumentException if path to file doesn't exist or is invalid
   */
  static public function create($configFile, $cronLogsFile = null, $debug = false, $forceUpdate = false)
  {
    return new TwitterBotsFarm($configFile, $cronLogsFile, $debug, $forceUpdate);
  }
  
  /**
   * Outputs a message, if $debug property is set to true
   * 
   * @param  string  $message
   */
  public function debug($message)
  {
    if (!$this->debug)
    {
      return;
    }
    
    printf('[farm] %s%s', $message, PHP_EOL);
  }
  
  /**
   * Retrieves farm configuration
   *
   * @return array
   */
  public function getConfig()
  {
    return $this->config;
  }
  
  /**
   * Runs configured bots actions
   *
   * @throws Exception if something goes wrong during the process
   */
  public function run()
  {
    $this->debug('Running the farm...');
    
    $this->loadCronLogs();
    
    foreach ($this->config['bots'] as $name => $botConfig)
    {
      $this->debug(sprintf('Running bot "%s"', $name));
      
      try
      {
        $this->runBot($name, $botConfig);
      }
      catch (Exception $e)
      {
        $this->debug('Interrupted run, writing processed operations in cronLogs...');
        
        $this->writeCronLogsFile();
        
        throw $e;
      }
    }
  }
  
  /**
   * Retrieves a bot configuration
   *
   * @param  string  $name  The bot name
   *
   * @return array
   *
   * @throws InvalidArgumentException if bot is not configured
   */
  public function getBotConfig($name)
  {
    if (!isset($this->config['bots'][$name]) || !is_array($this->config['bots'][$name]))
    {
      throw new InvalidArgumentException(sprintf('Bot "%s" is not configured', $name));
    }
    
    return $this->config['bots'][$name];
  }
  
  /**
   * Retrieves a bot configuration value
   *
   * @param  string  $name        The bot name
   * @param  string  $configName  The configuration name
   * @param  mixed   $default     The default value
   *
   * @return mixed
   */
  public function getBotConfigValue($botName, $configName, $default = null)
  {
    $config = $this->getBotConfig($botName);
    
    if (isset($config[$configName]))
    {
      return $config[$configName];
    }

    return $this->getGlobalConfigValue($configName, $default);
  }
  
  /**
   * Retrieves global configuration value
   *
   * @param  string  $configName  The configuration name
   * @param  mixed   $default     The default value
   *
   * @return mixed
   */
  public function getGlobalConfigValue($configName, $default = null)
  {
    if (isset($this->config['global'][$configName]))
    {
      return $this->config['global'][$configName];
    }

    return $default;
  }
  
  /**
   * Checks of a bot operation has expired
   *
   * @param  string      $botName
   * @param  string      $methodName
   * @param  int|string  $periodicity
   *
   * @return Boolean   
   */
  public function isBotOperationExpired($botName, $methodName, $periodicity)
  {
    if (!isset($periodicity) || $periodicity < self::MIN_PERIODICITY)
    {
      $periodicity = self::MIN_PERIODICITY;
    }
    
    if (!isset($this->cronLogs[$botName]) || !isset($this->cronLogs[$botName][$methodName]))
    {
      return true;
    }
    
    return ($this->cronLogs[$botName][$methodName] + $periodicity < time());
  }
  
  /**
   * Loads the cron logs from file, if exists
   *
   * @throws  RuntimeException  if configured cronLogs file is not writable
   */
  protected function loadCronLogs()
  {
    $this->debug('Loading cronLogs...');
    
    $this->checkCronLogsFile();
    
    $data = sfYaml::load($this->cronLogsFile);
    
    $this->cronLogs = is_array($data) ? $data : array();
  }
  
  /**
   * Checks the cron logs file, create a default one if none or invalid provided
   *
   * @throws  RuntimeException  on failure
   */
  public function checkCronLogsFile()
  {
    if (!$this->cronLogsFile)
    {
      $this->cronLogsFile = tempnam(sys_get_temp_dir(), self::CRONLOG_DEFAULT_FILENAME);
      
      $this->debug(sprintf('Default cronLogs file set to "%s"', $this->cronLogsFile));
    }
    
    if (!file_exists($this->cronLogsFile) && !@touch($this->cronLogsFile))
    {
      $this->debug(sprintf('Unable to create cronlogs file "%s"', $this->cronLogsFile));
      
      throw new RuntimeException(sprintf('cronLogs file "%s" cannot be created', $this->cronLogsFile));
    }
    
    if (!is_writable($this->cronLogsFile))
    {
      throw new RuntimeException(sprintf('cronLogs file "%s" is not writeable', $this->cronLogsFile));
    }
  }
  
  /**
   * Runs a bot
   *
   * @param  string      $name    The bot name
   * @param  array|null  $config  A bot configuration array (optional)
   *
   * @throws RuntimeException if something goes wrong during the process
   */
  public function runBot($name, array $config = null)
  {
    if (!class_exists(self::$botClass, true))
    {
      throw new RuntimeException(sprintf('Bot class "%s" cannot be loaded', self::$botClass));
    }
    else if ('TwitterBot' != self::$botClass && !in_array('TwitterBot', class_parents(self::$botClass)))
    {
      throw new RuntimeException(sprintf('Custom bot class "%s" must extend the TwitterBot class', self::$botClass));
    }
    
    // We're running only one bot, we must be sure the environment is loaded
    if (is_null($config))
    {
      $this->loadCronLogs();
      
      $config = $this->getBotConfig($name);
    }
    
    $bot = new self::$botClass($name, $this->getBotConfigValue($name, 'password'), $this->getBotConfigValue($name, 'debug'));
    
    if (!isset($config['operations']) || !is_array($config['operations']))
    {
      throw new RuntimeException(sprintf('Not operations configured for bot "%s"', $name));
    }
    
    foreach ($config['operations'] as $method => $methodConfig)
    {
      if ($this->getGlobalConfigValue('stoponfail', true) && !$this->getBotConfigValue($name, 'allow_magic_method', false) && !method_exists($bot, $method))
      {
        throw new RuntimeException(sprintf('No "%s" method exists for bot "%s"', $method, $name));
      }
      
      // Periodicity Check
      if (!$this->forceUpdate && (isset($methodConfig['periodicity']) && !$this->isBotOperationExpired($name, $method, (int) $methodConfig['periodicity'])))
      {
        $this->debug(sprintf('Operation "%s" of bot "%s" is not expired, skipping', $method, $name));
        
        continue;
      }
      
      $this->debug(sprintf('Operation "%s" from bot "%s" is expired, processing...', $method, $name));
      
      // Bot execution
      try
      {
        if (isset($methodConfig['arguments']) && is_array($methodConfig['arguments']))
        {
          call_user_func_array(array($bot, $method), $methodConfig['arguments']);
        }
        else
        {
          call_user_func(array($bot, $method));
        }
      }
      catch (Exception $e)
      {
        if ($this->getGlobalConfigValue('stoponfail', true))
        {
          throw new RuntimeException(sprintf('Bot "%s" stopped with an error: "%s"', $name, $e->getMessage()));
        }
      }
      
      $this->updateCronLogs($name, $method);
    }
    
    $this->writeCronLogsFile();
  }
  
  /**
   * Updates cronLogs
   *
   * @param  string      $botName
   * @param  string      $methodName
   */
  protected function updateCronLogs($botName, $methodName)
  {
    $this->debug(sprintf('Updating cronlog for bot "%s" for "%s" method', $botName, $methodName));
    
    $this->checkCronLogsFile();
    
    if (!array_key_exists($botName, $this->cronLogs))
    {
      $this->cronLogs[$botName] = array();
    }
    
    $this->cronLogs[$botName][$methodName] = time();
  }
  
  /**
   * Write cronLogs into a file
   *
   * @return Boolean
   */
  protected function writeCronLogsFile()
  {
    $this->debug(sprintf('Writing cronLogs into file "%s"', $this->cronLogsFile));
    
    $this->checkCronLogsFile();
    
    if (!@file_put_contents($this->cronLogsFile, sfYaml::dump($this->cronLogs)))
    {
      throw new RuntimeException(sprintf('Unable to write data in cronLogs file "%s"', $this->cronLogs));
    }
  }
}