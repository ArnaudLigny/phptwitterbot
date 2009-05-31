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
 * Example YAML configuration file content:
 *
 *  global:
 *    debug: true
 *  bots:
 *    myfirstbot:
 *      password: mypassw0rd
 *      operations:
 *        searchAndRetweet:
 *          terms:      "my search terms"
 *          options:
 *            template: "RT @%s: %s"
 *
 * @author	Nicolas Perriault <nperriault at gmail dot com>
 * @version	2.0.0
 * @license	MIT License
 */
class TwitterBotsFarm
{
  protected $config = array();
  
  /**
   * Constructor
   *
   * @param  string  $configFile  Absolute path to the yaml configuration file
   *
   * @throws InvalidArgumentException if path to file doesn't exist or is invalid
   */
  public function __construct($configFile)
  {
    if (!is_file($configFile))
    {
      throw new InvalidArgumentException(sprintf('File "%s" does not exist', $configFile));
    }
    
    $config = sfYaml::load($configFile);

    if (!$config['bots'])
    {
      exit('No bots config found');
    }
    
    $this->config = $config;
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
   * Runs the bots farm
   *
   */
  public function run()
  {
    foreach ($this->config['bots'] as $name => $botConfig)
    {
      $this->runBot($name, $botConfig);
    }
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
    
    if (!isset($config['operations']))
    {
      throw new RuntimeException(sprintf('Not operations configured for bot "%s"', $name));
    }
    
    foreach ($config['operations'] as $method => $args)
    {
      if (!method_exists($bot, $method))
      {
        throw new RuntimeException(sprintf('No "%s" method exists for bot "%s"', $method, $name));
      }
      
      try
      {
        call_user_func_array(array($bot, $method), $args);
      }
      catch (Exception $e)
      {
        if ($this->getGlobalConfigValue('stoponfail', true))
        {
          exit(sprintf('Bot "%s" stopped with an error: %s', $name, $e->getMessage()));
        }
      }
    }
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
}