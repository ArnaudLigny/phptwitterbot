<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/../../TwitterBotsFarm.class.php';

$t = new lime_test(10, new lime_output_color());

class TwitterBotsFarmMock extends TwitterBotsFarm
{
  public function __construct($configFile, $cronLogsFile = null, $debug = false)
  {
    $this->purgeCronLogsFile();
    
    parent::__construct($configFile, realpath(sys_get_temp_dir().DIRECTORY_SEPARATOR.'.cronlogs.test.log'), false);
  }
  public function getCronLogsFile()
  {
    return $this->cronLogsFile;
  }
  public function getBotConfigValueTest($botName, $configName, $default = null)
  {
    return parent::getBotConfigValue($botName, $configName, $default);
  }
  public function getGlobalConfigValueTest($configName, $default = null)
  {
    return parent::getGlobalConfigValue($configName, $default);
  }
  public function isBotOperationExpiredTest($botName, $methodName, $periodicity)
  {
    return parent::isBotOperationExpired($botName, $methodName, $periodicity);
  }
  public function purgeCronLogsFile()
  {
    if (!is_null($this->cronLogsFile) && file_exists($this->cronLogsFile) && !@unlink($this->cronLogsFile))
    {
      throw new Exception('Unable to unlink test cronLogs file');
    }
  }
  public function setCronLogs(array $data)
  {
    $this->cronLogs = $data;
  }
  public function getCronLogs()
  {
    return $this->cronLogs;
  }
}

class myTwitterBot extends TwitterBot
{
  public function testA($foo)
  {
    return $foo;
  }
  public function testB($foo)
  {
    return $foo;
  }
  public function testWithError($foo)
  {
    throw new Exception('This is a test error');
  }
}

// Use test bot class
TwitterBotsFarm::$botClass = 'myTwitterBot';

$t->diag('Testing farm instanciation and configuration');
$farm = new TwitterBotsFarmMock(dirname(__FILE__).'/yaml/sample_farm.yml');
$t->isa_ok($farm, 'TwitterBotsFarmMock', 'create() ok');
$t->isa_ok($farm->getConfig(), 'array', 'getConfig() ok');
$t->ok(array_key_exists('bots', $farm->getConfig()), 'getConfig() ok');

$t->diag('Testing configuration values retrieval');
$farm = new TwitterBotsFarmMock(dirname(__FILE__).'/yaml/sample_farm.yml');
$t->is($farm->getGlobalConfigValueTest('password', 'fail'), 'foo', 'getGlobalConfigValue() retrieves expected global configured value');
$t->is($farm->getBotConfigValueTest('myfirstbot', 'password', 'fail'), 'bar', 'getBotConfigValue() retrieves expected configured value');
$t->is($farm->getBotConfigValueTest('mysecondbot', 'password', 'fail'), 'foo', 'getBotConfigValue() retrieves global configured value when not declared for a bot');

$t->diag('Testing bots throwing exceptions');
$farm = new TwitterBotsFarmMock(dirname(__FILE__).'/yaml/sample_farm_with_error.yml');
try
{
  $farm->run();
  $t->fail('run() rethrows bot Exception during processing');
}
catch (Exception $e)
{
  $t->pass('run() rethrows bot Exception during processing');
}

$t->diag('Testing bots throwing exceptions when stoponfail is set to false');
$farm = new TwitterBotsFarmMock(dirname(__FILE__).'/yaml/sample_farm_with_error_no_stoponfail.yml');
try
{
  $farm->run();
  $t->pass('run() does not rethrow bot Exception during processing');
}
catch (Exception $e)
{
  $t->fail('run() does not rethrow bot Exception during processing');
  $t->comment('  Exception message: '.$e->getMessage());
}

$t->diag('Testing periodicity checks');
$farm = new TwitterBotsFarmMock(dirname(__FILE__).'/yaml/sample_farm.yml');
$farm->setCronLogs(array('myfirstbot' => array('testA' => time() - 500), 'mysecondbot' => array('testB' => time() - 500)));
$t->is($farm->isBotOperationExpiredTest('myfirstbot', 'testA', 400), true, 'isBotOperationExpired() detects expired operations correctly');
$t->is($farm->isBotOperationExpiredTest('mysecondbot', 'testB', 600), false, 'isBotOperationExpired() detects expired operations correctly');

// Purges created cronlogs file
$farm->purgeCronLogsFile();