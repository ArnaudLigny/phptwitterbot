PHPTwitterBot v2 Documentation
==============================

Introduction
------------

A simple [Twitter](http://twitter.com/) Bot written in [PHP5](http://php.net/), allowing to search and retweet things.

Features
--------

 * Clean OO architecture
 * Twitter API client, which can request several implementations of the Twitter API (eg. the identi.ca one)
 * Mockable Twitter API server class, to be able to unit-test the whole API without depending on the network connectivity
 * A `TwitterBotsFarm` class, configurable with a simple [YAML](http://yaml.org/) file
 * Configureable bot methods allowing to callback your own functions/callables
 * Command line interface you can use to run configured farms and bots
 * Unit-tested using the [http://trac.symfony-project.org/browser/tools/lime lime] testing framework

Installation
------------

You can download the [latest archive](http://github.com/n1k0/phptwitterbot/archives/master), or better checkout the [git](http://git-scm.com/) repository:

    $ mkdir ~/mybots && cd ~/mybots && mkdir vendor
    $ git clone git://github.com/n1k0/phptwitterbot.git vendor/phptwitterbot
    $ ln -s vendor/phptwitterbot/bin/phptwitterbot phptwitterbot
    $ php phptwitterbot --help

Then you have to create a bots farm configuration file:

    $ mkdir config && touch config/bots.yml

See the next section to learn how to configure this file.

Farm and Bots configuration
---------------------------

A farm is a group of configured bots execution directives, which can be described using the YAML syntax. 

Here's a sample farm configuration file:

    global:
      password:           mYGenericPasswOrd     # this password will be used by default for all bots
      stoponfail:         false                 # won't stop the whole process on error/exception
      allow_magic_method: false                 # will allow php magic methods calls on bot classes
    bots:
      myfirstbotaccount:
        password:         mYAccountPasswOrd     # this particular bot will use its own password
        operations:
          searchAndRetweet:
            arguments:
              terms:      "twitter php class"   # will search "twitter php class" on twitter timeline and retweet first matched tweets
            periodicity:  1200                  # will be run every 20 minutes
      mysecondbotaccount:
        operations:
          searchAndRetweet:
            arguments:
              terms:      "#fail"               # will search for the "#fail" hashtag
              options:
                template: "FAIL! @%s: %s"       # will render as "FAIL! @foobar: windows sucks #fail" where @foobar is the author of the original tweet
                follow:   true                  # will follow the tweet author automatically
            periodicity:  600                   # will be run every 10 minutes
          followFollowers:
            periodicity:  86400                 # will be run every day

Each sub-element of the `bots` section describes a single bot and its available operations, where the key is the bot username. Of course you still have to create a dedicated Twitter account for each bot.

In the provided example, the `mysecondbotaccount` bot will run the `searchAndRetweet` and `followFollowers` operations whereas the `myfirstbotaccount` bot will only run the `searchAndRetweet` one, each time with the provided parameters, options and the specified periodicity (in seconds).

For instance, the `searchAndRetweet` operation will search for terms into the public twitter timeline and retweet the first matched tweet containing them using a given formatter pattern. Note that the `follow` option will make the bot to follow the author of a matched tweet automatically.

The `followFollowers` operation will check periodically the list of followers for the bot account, and follow every of them back in return.

Check the `TwitterBot.class.php` API to see what are the available other operations.

To run the bots farm once configured, just use the command line interface:

    $ php phptwitterbot config/bots.yml

The command line interface
--------------------------

PHPTwitterBot ships with a shiny `phptwitterbot` executable for the command line interface you can find in the `bin` folder of the project codebase. This program allows to run all configured bots farm operations in one call.

### Usage and Options

Note that this program can be executed several ways:

    $ php bin/phptwitterbot
    $ bin/phptwitterbot
    $ cd bin
    $ php phptwitterbot
    $ ./phptwitterbot
    $ sudo ln -s phptwitterbot /usr/bin/phptwitterbot
    $ phptwitterbot

The only required argument is the relative or absolute path to where the YAML bots configuration file resides:

    $ ./phptwitterbot config/bots_configuration.yml
    $ ./phptwitterbot /home/user/my_other_bots_configuration.yml

To run a particular bot, use the --bot option:

    $ ./phptwitterbot myBots.yml --bot=myBotName

To set the path of a custom cronlogs file (this file will store the logs of 
bot executions):

    $ ./phptwitterbot configFile.yml --cronlogs=/tmp/my_cronlogs.log

To enable verbose debugging output, use the --debug option:

    $ ./phptwitterbot configFile.yml --debug

To run the whole phptwitterbot unit tests suite, use the --test option:

    $ ./phptwitterbot --test
