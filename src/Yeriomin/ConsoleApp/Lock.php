<?php

namespace Yeriomin\ConsoleApp;

/**
 * Lock (pid) file manager
 * Creates a lock file to prevent running multiple instaces of given console app
 *
 * @author yeriomin
 */
class Lock
{

    /**
     * Lock instance
     *
     * @var \Yeriomin\ConsoleApp\Lock
     */
    private static $instance;

    /**
     * Path to lock file
     *
     * @var string
     */
    private $filename;

    /**
     * Get lock manager instance
     * Construct if needed
     *
     * @return \Yeriomin\ConsoleApp\Lock
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new Lock();
        }
        return self::$instance;
    }

    /**
     * Making constructor private to enforce getInstance usage
     *
     */
    private function __construct()
    {

    }

    /**
     * Create a lock file
     *
     * @param string $filename
     * @throws ConsoleAppException
     */
    public function lock($filename)
    {
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
        $this->filename = $filename;
    }

    /**
     * Remove the lock file
     *
     */
    public function unlock()
    {
        if (file_exists($this->filename)) {
            $lockSaved = (integer) file_get_contents($this->filename);
            if ($lockSaved == getmypid()) {
                unlink($this->filename);
            }
        }
    }
}
