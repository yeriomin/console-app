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
     * Constructor
     * Locks process if needed
     * Inits configuration and db connection
     *
     */
    public function __construct()
    {
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
        // Filling with defaults if needed
        $appName = $this->getAppName();
        if (empty($this->config)) {
            $this->config['oneInstanceOnly'] = true;
            $this->config['logPath'] = $appName . '.log';
        }
        // Locking script to let only one instance run at a time.
        if ($this->config['oneInstanceOnly']) {
            $this->lock($this->config);
        }
        // Attaching signal and error handlers
        $this->attachHandlers();
        // Preparations complete
        $this->log('Starting ' . $appName);
    }

    /**
     * Destructor
     * Need to delete lock file if it was created
     *
     */
    public function __destruct()
    {
        $this->log('Stopping ' . $this->getAppName());
        if ($this->config['oneInstanceOnly']) {
            $this->unlock($this->config);
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
                pcntl_signal($signal, array(&$this, 'signalHandler'));
            }
        }
        set_error_handler(array(&$this, 'errorHandler'));
    }

    /**
     * Unlock and delete lock file
     *
     * @param array $config Configuration storage
     */
    public function unlock(array $config)
    {
        $filename = $this->getLockFileName($config);
        if (file_exists($filename)) {
            $lockSaved = (integer) file_get_contents($filename);
            if ($lockSaved == getmypid()) {
                unlink($filename);
            }
        }
    }

    /**
     * We need this to handle termination signals
     *
     */
    public function signalHandler()
    {
        if ($this->config['oneInstanceOnly']) {
            $this->unlock($this->config);
        }
        die();
    }

    /**
     * We need this to delete lock file when fatal errors happen
     * while showing the error itself
     *
     * @return boolean
     */
    public function errorHandler()
    {
        if ($this->config['oneInstanceOnly']) {
            $this->unlock($this->config);
        }
        return false;
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
     * Sets a logger instance on the object
     *
     * @param LoggerInterface $logger
     *
     * @return null
     */
    public function setLogger(\Psr\Log\LoggerInterface $logger) {
        $this->logger = $logger;
        return null;
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
        $logger = new Logger($this->getAppName());
        $logger->pushHandler(new StreamHandler('php://stdout'));
        $logFileName = empty($config['logFile'])
            ? $this->getLogFileName($config)
            : $config['logFile']
        ;
        $logger->pushHandler(new StreamHandler($logFileName));
        return $logger;
    }

    /**
     * Get dash-delimited app name
     *
     * @return string
     */
    protected function getAppName()
    {
        $className = array_pop(explode('\\', get_called_class()));
        $nameParts = array();
        foreach (explode('_', $className) as $part) {
            $nameParts[] = lcfirst($part);
        }
        return implode('-', $nameParts);
    }

    /**
     * Attempt to create and lock a lock file
     *
     * @throws ConsoleAppException
     *
     * @param array $config Configuration storage
     *
     * @return void
     */
    private function lock(array $config)
    {
        $filename = $this->getLockFileName($config);
        if (file_exists($filename)) {
            $lock = (integer) file_get_contents($filename);
            $command = 'ps h -p ' . $lock;
            $output = array();
            exec($command, $output);
            if (!empty($output)) {
                throw new ConsoleAppException('Could not lock ' . $filename);
            }
        }
        file_put_contents($filename, getmypid());
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
        return realpath($path) . DIRECTORY_SEPARATOR . $this->getAppName();
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
    private function readConfig($path = '')
    {
        if (empty($path)) {
            $path = $this->getAppName() . '.ini';
        }
        $result = array();
        if (file_exists($path)) {
            $result = new \Configula\Config($path);
        }
        return $result;
    }

}
