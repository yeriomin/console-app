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

    /**
     * Signals to process
     *
     * @var array
     */
    protected static $signalsToCatch = array(
        SIGTERM,
        SIGINT,
        SIGHUP,
        SIGQUIT
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
     * @var Psr\Log\LoggerInterface
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
        // Reading configuration file if provided
        $configPath = $this->getopt->config;
        if (!empty($configPath) && !file_exists($configPath)) {
            throw ConsoleAppException(
                'Failed to read configuration from "' . $configPath . '"'
            );
        }
        $this->config = $this->readConfig($configPath);
        // Locking script to let only one instance run at a time.
        if ($this->config['oneInstanceOnly']) {
            $lockFile = $this->getLockFileName($this->config);
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
        $this->log('Stopping ' . $this->appName);
        if ($this->config['oneInstanceOnly']) {
            Lock::getInstance()->unlock();
        }
    }

    /**
     * Attaching signal and error handlers if platform allows it
     * They do nothing by default in non-single instance mode
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
        set_error_handler(array($this, 'errorHandler'), E_ERROR & E_USER_ERROR);
    }

    /**
     * We need this to handle termination signals
     *
     */
    public function signalHandler()
    {
        if ($this->config['oneInstanceOnly']) {
            Lock::getInstance()->unlock();
        }
        die();
    }

    /**
     * We need this to delete lock file when fatal errors happen
     * while showing the error itself
     *
     * @return boolean
     */
    public function errorHandler($errno, $errstr)
    {
        if ($this->config['oneInstanceOnly']) {
            Lock::getInstance()->unlock();
        }

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
            'logPath' => $this->appName . '.log',
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
            $this->setLogger($this->getLogger($this->config));
        }
        $this->logger->log($priority, $message);
    }

    /**
     * Init logger
     *
     * @param array $config Configuration storage
     *
     * @return Psr\Log\LoggerInterface
     */
    protected function getLogger(array $config)
    {
        $logger = new Logger($this->appName);
        $logger->pushHandler(new StreamHandler('php://stdout'));
        $logFileName = empty($config['logFile'])
            ? $this->getLogFileName($config)
            : $config['logFile']
        ;
        $logger->pushHandler(new StreamHandler($logFileName));
        return $logger;
    }

    /**
     * Get filename of a lock file used for maintaining one instance of self
     *
     * @param array $config Configuration storage
     *
     * @return string
     */
    private function getLockFileName(array $config)
    {
        $path = !empty($config['lockDir']) ? $config['lockDir'] : '';
        return $this->getTempFileName($path) . '.lock';
    }

    /**
     * Build and return log file name based on configuration
     *
     * @param array $config Configuration storage
     *
     * @return string
     */
    private function getLogFileName(array $config)
    {
        $path = !empty($config['logDir']) ? $config['logDir'] : '';
        return $this->getTempFileName($path) . '.log';
    }

    /**
     * Build a path to a file in a temp dir for locking or logging
     *
     * @param string $path
     * @return string
     * @throws ConsoleAppException
     */
    private function getTempFileName($path = '')
    {
        if (!empty($path)) {
            if (!is_dir($path)) {
                throw new ConsoleAppException(
                    '"' . $path . '" is not a directory'
                );
            }
        } else {
            $path = sys_get_temp_dir();
        }
        return realpath($path) . DIRECTORY_SEPARATOR . $this->appName;
    }
}
