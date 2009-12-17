<?php
require_once dirname(__FILE__).'/../../vendor/lime/lime.php';
require_once dirname(__FILE__).'/../../lib/TwitterBotsFarm.class.php';

$t = new lime_test(14, new lime_output_color());

class TwitterBotsFarmMock extends TwitterBotsFarm
{
  public function __construct($configFile, $cronLogsFile = null, $debug = false)
  {
    $this->purgeCronLogsFile();
    
    parent::__construct($configFile, tempnam(sys_get_temp_dir(), 'test_cronlogs'), false);
  }
  public function getCronLogsFile()
  {
    return $this->cronLogsFile;
  }
  public function getDebug()
  {
    return $this->debug;
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
$t->isa_ok($farm->getBotConfig('myfirstbot'), 'array', 'getBotConfig() retrieves a bot configuration array');
$t->isa_ok($farm->getBotConfig('mysecondbot'), 'array', 'getBotConfig() retrieves a bot configuration array');
$t->is($farm->getGlobalConfigValue('password', 'fail'), 'foo', 'getGlobalConfigValue() retrieves expected global configured value');
$t->is($farm->getBotConfigValue('myfirstbot', 'password', 'fail'), 'bar', 'getBotConfigValue() retrieves expected configured value');
$t->is($farm->getBotConfigValue('mysecondbot', 'password', 'fail'), 'foo', 'getBotConfigValue() retrieves global configured value when not declared for a bot');

$t->diag('Testing debug mode activation');
ob_start(); // avoid sending output
$farm = new TwitterBotsFarmMock(dirname(__FILE__).'/yaml/debug_on.yml');
ob_clean();
$t->is($farm->getDebug(), true, 'getConfig() debugging can be activated via configuration file');
$farm = new TwitterBotsFarmMock(dirname(__FILE__).'/yaml/debug_off.yml');
$t->is($farm->getDebug(), false, 'getConfig() debugging can be disabled via configuration file');

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
  $t->diag(sprintf('    %s: %s', get_class($e), $e->getMessage()));
}

$t->diag('Testing periodicity checks');
$farm = new TwitterBotsFarmMock(dirname(__FILE__).'/yaml/sample_farm.yml');
$farm->setCronLogs(array('myfirstbot' => array('testA' => time() - 500), 'mysecondbot' => array('testB' => time() - 500)));
$t->is($farm->isBotOperationExpired('myfirstbot', 'testA', 400), true, 'isBotOperationExpired() detects expired operations correctly');
$t->is($farm->isBotOperationExpired('mysecondbot', 'testB', 600), false, 'isBotOperationExpired() detects expired operations correctly');

// Purges created cronlogs file
$farm->purgeCronLogsFile();