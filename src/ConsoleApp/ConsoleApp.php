<?php
namespace ConsoleApp;

use Psr\Log;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Ulrichsg\Getopt\Getopt;
use Ulrichsg\Getopt\Option;

/**
 * Abstract console service
 *
 * @author eremin
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
     * @var Getopt\Getopt
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
        $this->getopt = $this->initGetopt();
        try {
            $this->getopt->parse();
        } catch (UnexpectedValueException $e) {
            echo $e->getMessage() . "\n";
            echo $this->getopt->getTextMessage() . "\n";
            exit(1);
        }
        if ($this->getopt->getOption('help')) {
            echo $this->getopt->getTextMessage() . "\n";
            exit(0);
        }
        // Reading configuration file if provided
        $configPath = $this->getopt->getOption('config');
        if (!empty($configPath) && !file_exists($configPath)) {
            throw ConsoleAppException(
                'Failed to read configuration from "' . $configPath . '"'
            );
        }
        $this->config = $this->readConfig($configPath);
        // Filling with defaults if needed
        if (empty($this->config)) {
            $this->config['oneInstanceOnly'] = true;
            $this->config['logPath'] = $this->getServiceName() . '.log';
        }
        // Locking script to let only one instance run at a time.
        if ($this->config['oneInstanceOnly']) {
            $this->lock($this->config);
        }
        // Attaching signal and error handlers
        $this->attachHandlers();
        // Preparations complete
        $this->log('Starting ' . get_called_class());
    }

    /**
     * Destructor
     * Need to delete lock file if it was created
     *
     */
    public function __destruct()
    {
        $this->log('Stopping ' . get_called_class());
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
     *
     * @return void
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
     * @param array $config Configuration storage
     *
     * @return void
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
     * @param array $config Configuration storage
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
            $this->setLogger($this->initLogger($this->config));
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
    protected function initLogger(array $config)
    {
        $logger = new Logger($this->getServiceName());
        $logger->pushHandler(new StreamHandler('php://stdout'));
        $logger->pushHandler(new StreamHandler($this->getLogFileName($config)));
        return $logger;
    }

    /**
     * Get dash-delimited service name without "Service_" part
     *
     * @return string
     */
    protected function getServiceName()
    {
        $nameParts = array();
        foreach (explode('_', get_called_class()) as $part) {
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
        $path = sys_get_temp_dir();
        if (!empty($config['lockDir'])
            && file_exists($config['lockDir'])
        ) {
            $path = $config['lockDir'];
        }
        $name = $this->getServiceName();
        return realpath($path) . '/' . $name . '.lock';
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
        $path = sys_get_temp_dir();
        if (!empty($config['logDir'])
            && file_exists($this['logDir'])
        ) {
            $path = $config['logDir'];
        }
        $name = $this->getServiceName();
        return realpath($path) . '/' . $name . '.log';
    }

    /**
     * Getting console arguments
     *
     * @return Getopt
     */
    protected function initGetopt()
    {
        $optionHelp = new Option('h', 'help', Getopt::OPTIONAL_ARGUMENT);
        $optionHelp->setDescription('Display this help message');
        $optionConfig = new Option('c', 'config', Getopt::REQUIRED_ARGUMENT);
        $optionConfig->setDescription('Path to configuration ini file');
        return new Getopt(
            array($optionHelp, $optionConfig),
            Getopt::OPTIONAL_ARGUMENT
        );
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
            $path = $this->getServiceName() . '.ini';
        }
        $result = array();
        if (file_exists($path)) {
            $result = parse_ini_file($path);
            if ($result === false) {
                $result = array();
            }
        }
        return $result;
    }

}
