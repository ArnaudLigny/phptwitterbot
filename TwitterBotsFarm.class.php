<?php
require_once dirname(__FILE__).'/vendor/yaml/sfYaml.php';
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
 * @version	2.0.0
 * @license	MIT License
 */
class TwitterBotsFarm
{
  const MIN_PERIODICITY = 60;
  
  protected 
    $config       = array(),
    $debug        = false,
    $cronLogs     = array(),
    $cronLogsFile = null;
  
  /**
   * Constructor
   *
   * @param  string  $configFile    Absolute path to the yaml configuration file
   * @param  string  $cronLogsFile  Absolute path to the cronLogs file (optional)
   * @param  Boolean $debug         Enables debug mode
   *
   * @throws InvalidArgumentException if path to file doesn't exist or is invalid
   */
  public function __construct($configFile, $cronLogsFile = null, $debug = false)
  {
    $this->debug = $debug;
    
    $this->debug(sprintf('Created farm from config file "%s"', $configFile));
    
    if (!file_exists($configFile) || !is_file($configFile))
    {
      throw new InvalidArgumentException(sprintf('File "%s" does not exist', $configFile));
    }
    
    $config = sfYaml::load($configFile);

    if (!is_array($config) || !array_key_exists('bots', $config) || !is_array($config['bots']))
    {
      exit('No valid bots configr found, please check the documentation.');
    }
    
    $this->config = $config;
    
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
   * @param  string  $configFile    Absolute path to the yaml configuration file
   * @param  string  $cronLogsFile  Absolute path to the cronLogs file (optional)
   * @param  Boolean $debug         Enables debug mode
   *
   * @throws InvalidArgumentException if path to file doesn't exist or is invalid
   */
  static public function create($configFile, $cronLogsFile = null, $debug = false)
  {
    return new TwitterBotsFarm($configFile, $cronLogsFile, $debug);
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
   * Retrieves a bot configuration value
   *
   * @param  string  $name        The bot name
   * @param  string  $configName  The configuration name
   * @param  mixed   $default     The default value
   *
   * @return mixed
   */
  protected function getBotConfigValue($botName, $configName, $default = null)
  {
    if (isset($this->config['bots'][$botName][$configName]))
    {
      return $this->config['bots'][$botName][$configName];
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
  protected function getGlobalConfigValue($configName, $default = null)
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
  protected function isBotOperationExpired($botName, $methodName, $periodicity)
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
    
    if (is_null($this->cronLogsFile))
    {
      $this->cronLogsFile = sys_get_temp_dir().'.phptwitterbot.cronlogs.log';
      
      if (!touch($this->cronLogsFile))
      {
        throw new RuntimeException(sprintf('cronLogs file "%s" cannot be created', $this->cronLogsFile));
      }
      
      $this->debug(sprintf('Default cronLogs file set to "%s"', $this->cronLogsFile));
    }
    
    if (!is_writable($this->cronLogsFile))
    {
      throw new RuntimeException(sprintf('cronLogs file "%s" is not writeable', $this->cronLogsFile));
    }
    
    $data = sfYaml::load($this->cronLogsFile);
    
    $this->cronLogs = is_array($data) ? $data : array();
  }
  
  /**
   * Runs a bot
   *
   * @param  string  $name    The bot name
   * @param  array   $config  The bot configuration
   *
   * @throws RuntimeException if something goes wrong during the process
   */
  protected function runBot($name, $config)
  {
    $bot = new TwitterBot($name, $this->getBotConfigValue($name, 'password'), $this->getBotConfigValue($name, 'debug'));
    
    if (!isset($config['operations']) || !is_array($config['operations']))
    {
      throw new RuntimeException(sprintf('Not operations configured for bot "%s"', $name));
    }
    
    foreach ($config['operations'] as $method => $methodConfig)
    {
      if (!$this->getBotConfigValue($name, 'allow_magic_method', false) && !method_exists($bot, $method))
      {
        throw new RuntimeException(sprintf('No "%s" method exists for bot "%s"', $method, $name));
      }
      
      // Periodicity Check
      if (isset($methodConfig['periodicity']) && !$this->isBotOperationExpired($name, $method, (int) $methodConfig['periodicity']))
      {
        $this->debug(sprintf('Operation "%s" of bot "%s" is not expired, skipping', $method, $name));
        
        continue;
      }
      
      $this->debug(sprintf('Operation "%s" from bot "%s" is not expired, processing...', $method, $name));
      
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
          exit(sprintf('Bot "%s" stopped with an error: "%s"', $name, $e->getMessage()));
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
    
    return file_put_contents($this->cronLogsFile, sfYaml::dump($this->cronLogs)) > 0;
  }
}