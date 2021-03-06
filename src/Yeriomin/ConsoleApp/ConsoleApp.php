<?php
namespace Yeriomin\ConsoleApp;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Yeriomin\Getopt;

/**
 * A template for a console application
 * manages logging, logs start and finish, manages console arguments,
 * prevents running of several apps simultaneously
 *
 * @author yeriomin
 */
abstract class ConsoleApp implements ConsoleAppInterface, \Psr\Log\LoggerAwareInterface
{

    const DEFAULT_CONFIG = 'config.ini';

    /**
     * Signals to process
     *
     * @var array
     */
    protected static $signalsToCatch = array(
        SIGTERM,
        SIGINT,
        SIGHUP,
        SIGQUIT,
    );

    /**
     * An app name to be used in logs and while creating lock/log files
     *
     * @var string
     */
    protected $appName;

    /**
     * Console arguments parser and storage
     *
     * @var \Yeriomin\Getopt\Getopt
     */
    protected $getopt;

    /**
     * Configuration storage
     *
     * @var array
     */
    protected $config;

    /**
     * Logger instance
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Locks process if needed, inits logger, parses console args
     *
     */
    public function __construct()
    {
        $this->appName = $this->getAppName();
        // Getting console arguments.
        $this->getopt = $this->getGetopt();
        try {
            $this->getopt->parse();
        } catch (\Yeriomin\Getopt\GetoptException $e) {
            echo $e->getMessage() . "\n";
            echo $this->getopt->getUsageMessage() . "\n";
            exit(1);
        }
        if ($this->getopt->h) {
            echo $this->getopt->getUsageMessage() . "\n";
            exit(0);
        }
        // If no configuration file is provided but default exists we use it
        $configPath =
            empty($this->getopt->config) && file_exists(self::DEFAULT_CONFIG)
            ? self::DEFAULT_CONFIG
            : $this->getopt->config
        ;
        // Reading configuration file if provided
        if (!empty($configPath) && !file_exists($configPath)) {
            throw new ConsoleAppException(
                'Failed to read configuration from "' . $configPath . '"'
            );
        }
        $this->config = $this->readConfig($configPath);
        // Checking if the script runs in the console
        if (PHP_SAPI !== 'cli' && $this->config['consoleOnly']) {
            throw new ConsoleAppException(
                'This app is supposed to be run in console only'
            );
        }
        // Locking script to let only one instance run at a time.
        if ($this->config['oneInstanceOnly']) {
            $lockFile = $this->getTempFileName('lock');
            Lock::getInstance()->lock($lockFile);
        }
        // Attaching signal and error handlers
        $this->attachHandlers();
        // Preparations complete
        $this->log('Starting ' . $this->appName);
    }

    /**
     * Destructor
     * Need to delete lock file if it was created
     *
     */
    public function __destruct()
    {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        $this->unlockAndLog();
    }

    /**
     * We need this to handle termination signals
     *
     * @param integer $signo
     */
    public function signalHandler($signo)
    {
        $this->log('Caught signal ' . $signo);
        $this->unlockAndLog();
        exit(1);
    }

    /**
     * We need this to delete lock file when fatal errors happen
     * while showing the error itself
     *
     * @param integer $errno
     * @param string $errstr
     * @return boolean
     */
    public function errorHandler($errno, $errstr)
    {
        $this->log('Error occured: [' . $errno . '] ' . $errstr);
        $this->unlockAndLog();

        return false;
    }

    /**
     * Sets a logger instance on the object
     *
     * @param LoggerInterface $logger
     *
     * @return null
     */
    public function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
        return null;
    }

    /**
     * Get dash-delimited app name
     *
     * @return string
     */
    protected function getAppName()
    {
        $classParts = explode('\\', get_called_class());
        $className = array_pop($classParts);
        $nameParts = array();
        foreach (explode('_', $className) as $part) {
            $nameParts[] = lcfirst($part);
        }
        return implode('-', $nameParts);
    }

    /**
     * Getting console arguments
     *
     * @return Getopt
     */
    protected function getGetopt()
    {
        $optionHelp = new \Yeriomin\Getopt\OptionDefinition(
            'h',
            'help',
            'Show this message'
        );
        $optionConfig = new \Yeriomin\Getopt\OptionDefinition(
            'c',
            'config',
            'Path to configuration ini file'
        );
        $getopt = new \Yeriomin\Getopt\Getopt();
        $getopt
            ->addOptionDefinition($optionHelp)
            ->addOptionDefinition($optionConfig)
        ;
        return $getopt;
    }

    /**
     * Read configuration file and return its contents
     * By default attempts to read an ini file with the same name as this class
     *
     * @param string $path Path to configuration ini file
     *
     * @return array
     */
    protected function readConfig($path = '')
    {
        if (empty($path)) {
            $path = $this->appName . '.ini';
        }
        $result = array(
            'oneInstanceOnly' => true,
            'consoleOnly' => true,
        );
        if (file_exists($path)) {
            $configula = new \Configula\Config();
            $result = array_merge($result, $configula->parseConfigFile($path));
        }
        return $result;
    }

    /**
     * Log a message
     *
     * @param string  $message  Message to log
     * @param integer $priority Message level: err, warn, info..
     *
     * @return void
     */
    protected function log($message, $priority = Logger::INFO)
    {
        if (null == $this->logger) {
            $this->setLogger($this->getLogger());
        }
        $this->logger->log($priority, $message);
    }

    /**
     * Init logger
     *
     * @return Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        $logger = new Logger($this->appName);
        $logFile = $this->getTempFileName('log');
        $logger->pushHandler(new StreamHandler($logFile));
        $logger->pushHandler(new StreamHandler('php://stdout'));
        return $logger;
    }

    /**
     * Build a path to a file in a temp dir for locking or logging
     * Can be used for any other purpose
     * Searches configuration for <type>File and <type>Dir values
     * and builds a path to a file in the system temporary dir if none found
     *
     * @param string $type
     * @return string
     * @throws ConsoleAppException
     */
    protected function getTempFileName($type)
    {
        $config = $this->config;
        if (!empty($config[$type . 'File'])) {
            return $config[$type . 'File'];
        }
        $dir = !empty($config[$type . 'Dir']) ? $config[$type . 'Dir'] : '';
        if (!empty($dir)) {
            if (!is_dir($dir)) {
                throw new ConsoleAppException(
                    '"' . $dir . '" is not a directory'
                );
            }
        } else {
            $dir = sys_get_temp_dir();
        }
        return realpath($dir) . DIRECTORY_SEPARATOR . $this->appName . '.'
            . $type
        ;
    }

    /**
     * Remove the lock file and put the last message to the log
     *
     */
    private function unlockAndLog()
    {
        if ($this->config['oneInstanceOnly']) {
            Lock::getInstance()->unlock();
        }
        $this->log('Stopping ' . $this->appName);
    }

    /**
     * Attaching signal and error handlers if platform allows it
     * This lets us print the final message to the log and remove the lock file
     *
     * @return void
     */
    private function attachHandlers()
    {
        if (function_exists('pcntl_signal')) {
            foreach (self::$signalsToCatch as $signal) {
                pcntl_signal($signal, array($this, 'signalHandler'));
            }
        }
        set_error_handler(array($this, 'errorHandler'), E_ERROR | E_USER_ERROR);
    }
}
