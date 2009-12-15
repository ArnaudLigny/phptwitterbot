<?php
require_once dirname(__FILE__).'/../lib/TwitterBotsFarm.class.php';

// Check if we have at least a configuration file provided as the first argument

$configFile = null;
$botName = null;
$cronLogsFile = null;
$debug = false;
$forceUpdate = false;

foreach (array_slice($argv, 1) as $argValue)
{
  $arg = $name = substr($argValue, strpos($argValue, '--') + 2);
  
  if (false !== strpos($arg, '='))
  {
    list($name, $optionValue) = explode('=', $arg);
  }
  
  switch ($name)
  {
    case 'bot':
      $botName = $optionValue;
      break;
    
    case 'cronlogs':
      $cronLogsFile = $optionValue;
      break;
    
    case 'debug':
      $debug = true;
      break;
    
    case 'force':
      $forceUpdate = true;
      break;
    
    case 'help':
      exit(help($argv[0]));
      break;

    case 'test':
      echo "Lauching test suite...".PHP_EOL.PHP_EOL;
      include dirname(__FILE__).'/../test/bin/prove.php';
      break;
    
    default:
      $configFile = (file_exists($argValue) ? $argValue : false);
      break;
  }
}

if (!$configFile)
{
  exit(help($argv[0]));
}

try
{
  $farm = TwitterBotsFarm::create($configFile, $cronLogsFile, $debug, $forceUpdate);
  
  if (!is_null($botName))
  {
    $farm->runBot($botName);
  }
  else
  {
    $farm->run();
  }
}
catch (Exception $e)
{
  exit(sprintf('Farm execution stopped with error: "%s"%s', $e->getMessage(), PHP_EOL));
}

function help($scriptName)
{
  $year = date('Y');
  
  return <<<EOF
©{$year} Nicolas Perriault - http://code.google.com/p/phptwitterbot

This executable runs a PHPTwitterBot farm from the command line, using a YAML 
configuration file.

Usage:

    \$ {$scriptName} config/bots_configuration.yml
    \$ {$scriptName} /home/user/my_other_bots_configuration.yml

To run a particular bot, use the --bot option:

    \$ {$scriptName} myBots.yml --bot=myBotName

To set the path of a custom cronlogs file (this file will store the logs of 
bot executions):

    \$ {$scriptName} configFile.yml --cronlogs=/tmp/my_cronlogs.log

To enable verbose debugging output, use the --debug option:

    \$ {$scriptName} configFile.yml --debug

To run the whole phptwitterbot unit tests suite, use the --test option:
    \$ {$scriptName} --test


EOF;
}