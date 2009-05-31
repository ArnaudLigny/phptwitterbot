<?php
require_once dirname(__FILE__).'/../vendor/lime/lib/lime.php';
require_once dirname(__FILE__).'/../TwitterBotsFarm.class.php';

$t = new lime_test(7, new lime_output_color());

class TwitterBotsFarmMock extends TwitterBotsFarm
{
  public function purgeCronLogsFile()
  {
    unlink($this->cronLogsFile);
  }
}

class myTwitterBot extends TwitterBot
{
  public function test1($foo)
  {
    return $foo;
  }
  public function test2($foo)
  {
    return $foo;
  }
}

TwitterBotsFarm::$botClass = 'myTwitterBot';

$farm = new TwitterBotsFarmMock(dirname(__FILE__).'/yaml/samplefarm.yml', null, true);

$t->isa_ok($farm, 'TwitterBotsFarmMock', 'create() ok');
$t->isa_ok($config = $farm->getConfig(), 'array', 'getConfig() ok');
$t->ok(array_key_exists('bots', $config), 'getConfig() ok');
$farm->run();
$farm->purgeCronLogsFile();
