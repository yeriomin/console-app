console-app
===========

[![Latest Version](https://img.shields.io/packagist/v/yeriomin/console-app.svg)](https://packagist.org/packages/yeriomin/console-app)
[![Build Status](https://travis-ci.org/yeriomin/console-app.svg?branch=master)](https://travis-ci.org/yeriomin/console-app)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yeriomin/console-app/badges/quality-score.png)](https://scrutinizer-ci.com/g/yeriomin/console-app)

A skeleton PHP console app.

Usage
-----

Install console-app as a dependency

`$ composer require yeriomin/console-app`

Extend `\Yeriomin\ConsoleApp\ConsoelApp` class and implement the `run()` method.

```php
<?php
class MyConsoleApp extends \Yeriomin\ConsoleApp\ConsoelApp {
    public function run()
    {
        $this->logger->info('Hi');
    }
}
```

Features
--------

1. Handles configuration files. Tries to read `./config.ini` by default.
2. Handles console arguments. Extend `getGetopt()` method to add your options.
3. Checks if another instance of the same script is running. Can be disabled in config.
4. Checks if script is running in the console. Fails otherwise. Can be disabled in config.
5. Inits monolog logger.
6. Uses error and signal handlers to let you see that very important last message in the log.

Configuration
-------------

By default the following configuration options are supported:

* `consoleOnly` Let the script run only in console, not in browser. True by default.
* `oneInstanceOnly` Let only one instance of the script be running at any time. True by default.
* `logDir` Directory to put the log file to. Defaults to system temporary directory.
* `logFile` A specific file path for the log. If none provided `/tmp/<script-class-name>.log` is used.
* `lockDir` Directory to put the lock file to. Defaults to system temporary directory.
* `lockFile`A specific file path for the lock file. If none provided `/tmp/<script-class-name>.lock` is used.

Console arguments
-----------------

Two console options are supported by default:

* `-h|--help` Shows usage message. It will include options you add.
* `-c|--config` Path to configuration file. Defaults to `./config.ini`
