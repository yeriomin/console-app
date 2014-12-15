<?php
namespace Yeriomin\ConsoleApp;

/**
 * Generated by PHPUnit_SkeletonGenerator on 2014-12-09 at 05:04:11.
 */
class ConsoleAppTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Config file name
     *
     * @var string
     */
    protected static $configFile;

    /**
     * Config content
     *
     * @var array
     */
    protected static $configValues;

    /**
     * Original console argments
     *
     * @var array
     */
    protected static $argvOriginal;

    /**
     * Console arguments with config option set
     *
     * @var array
     */
    protected static $argvWithConfig;

    /**
     * Sets up configuration defaults
     */
    public static function setUpBeforeClass()
    {
        include_once dirname(__FILE__) . '/ConsoleAppMock.php';
        self::$configFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'conf.json'
        ;
        self::$configValues['oneInstanceOnly'] = false;
        self::$argvOriginal = $_SERVER['argv'];
        self::$argvWithConfig = self::$argvOriginal;
        self::$argvWithConfig[] = '--config';
        self::$argvWithConfig[] = self::$configFile;
    }

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $_SERVER['argv'] = self::$argvOriginal;
        @unlink(self::$configFile);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        $_SERVER['argv'] = self::$argvOriginal;
        @unlink(self::$configFile);
        Lock::getInstance()->unlock();
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    protected function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Write the config replacing default values with given values
     * 
     * @param array $nonDefaultValues
     */
    protected function writeConfig(array $nonDefaultValues) {
        $configValues = self::$configValues;
        foreach ($nonDefaultValues as $name => $value) {
            $configValues[$name] = $value;
        }
        file_put_contents(self::$configFile, json_encode($configValues));
    }

    /**
     * @covers Yeriomin\ConsoleApp\ConsoleApp::getAppName
     */
    public function testGetAppName()
    {
        $object = new ConsoleAppMock();
        $this->assertEquals(
            'consoleAppMock',
            $this->invokeMethod($object, 'getAppName')
        );
    }

    /**
     * @covers Yeriomin\ConsoleApp\ConsoleApp::getGetopt
     */
    public function testGetGetopt()
    {
        $object = new ConsoleAppMock();
        $this->assertEquals(
            'consoleAppMock',
            $this->invokeMethod($object, 'getAppName')
        );
        $getopt = $this->invokeMethod($object, 'getGetopt');
        $this->assertInstanceOf('\\Yeriomin\\Getopt\\Getopt', $getopt);
        $expected = 'Usage: ' . $_SERVER['PHP_SELF'] . ' [OPTIONS] [ARGUMENTS]'
            . "\n\n" . 'Options:'
            . "\n" . ' -h, --help   Show this message'
            . "\n" . ' -c, --config Path to configuration ini file'
            . "\n"
        ;
        $actual = $getopt->getUsageMessage();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Yeriomin\ConsoleApp\ConsoleApp::readConfig
     */
    public function testReadConfigDefault()
    {
        $object = new ConsoleAppMock();
        $configResult = $this->invokeMethod($object, 'readConfig');
        $this->assertCount(1, $configResult);
        $this->assertArrayHasKey('oneInstanceOnly', $configResult);
        $this->assertEquals(true, $configResult['oneInstanceOnly']);
    }

    /**
     * @covers Yeriomin\ConsoleApp\ConsoleApp::readConfig
     * @covers Yeriomin\ConsoleApp\ConsoleApp::__construct
     */
    public function testReadConfigNonDefault()
    {
        $config = array(
            'oneInstanceOnly' => false,
            'someOtherKey' => 'someOtherValue',
        );
        $this->writeConfig($config);
        $object = new ConsoleAppMock();
        $configResult = $this->invokeMethod(
            $object,
            'readConfig',
            array(self::$configFile)
        );
        $this->assertCount(2, $configResult);
        $this->assertArrayHasKey('oneInstanceOnly', $configResult);
        $this->assertEquals(false, $configResult['oneInstanceOnly']);
        $this->assertArrayHasKey('someOtherKey', $configResult);
        $this->assertEquals('someOtherValue', $configResult['someOtherKey']);
    }

    /**
     * @covers Yeriomin\ConsoleApp\ConsoleApp::getTempFileName
     * @covers Yeriomin\ConsoleApp\ConsoleApp::__construct
     */
    public function testGetTempFileName()
    {
        $object = new ConsoleAppMock();
        $pathDefault = $this->invokeMethod($object, 'getTempFileName');
        $this->assertEquals(
            realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'consoleAppMock',
            $pathDefault
        );
        $testDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dir';
        mkdir($testDir);
        $pathExistingDir = $this->invokeMethod(
            $object,
            'getTempFileName',
            array($testDir)
        );
        $this->assertEquals(
            realpath($testDir) . DIRECTORY_SEPARATOR . 'consoleAppMock',
            $pathExistingDir
        );
        rmdir($testDir);
        $this->setExpectedException(
            '\\Yeriomin\\ConsoleApp\\ConsoleAppException',
            ''
        );
        $this->invokeMethod(
            $object,
            'getTempFileName',
            array('/inexistent123456')
        );
    }

    /**
     * @covers Yeriomin\ConsoleApp\ConsoleApp::getLogFileName
     */
    public function testGetLogFileName()
    {
        $object1 = new ConsoleAppMock();
        $pathDefault = $this->invokeMethod($object1, 'getLogFileName');
        $this->assertEquals(
            realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR
                . 'consoleAppMock.log',
            $pathDefault
        );
        $testDir = '/tmp/console-app-test-dir';
        mkdir($testDir);
        $config2 = array(
            'oneInstanceOnly' => false,
            'logDir' => $testDir,
        );
        $this->writeConfig($config2);
        Lock::getInstance()->unlock();
        $_SERVER['argv'] = self::$argvWithConfig;
        $object2 = new ConsoleAppMock();
        $pathWithDir = $this->invokeMethod($object2, 'getLogFileName');
        $this->assertEquals(
            realpath($testDir) . DIRECTORY_SEPARATOR . 'consoleAppMock.log',
            $pathWithDir
        );
        $testFile = '/tmp/console-app-test-file.log';
        $config3 = array(
            'oneInstanceOnly' => false,
            'logFile' => $testFile,
        );
        $this->writeConfig($config3);
        Lock::getInstance()->unlock();
        $object3 = new ConsoleAppMock();
        $pathWithFile = $this->invokeMethod($object3, 'getLogFileName');
        $this->assertEquals($testFile, $pathWithFile);
        unlink($pathWithDir);
        rmdir($testDir);
    }

    /**
     * @covers Yeriomin\ConsoleApp\ConsoleApp::getLockFileName
     */
    public function testGetLockFileName()
    {
        $object1 = new ConsoleAppMock();
        $pathDefault = $this->invokeMethod($object1, 'getLockFileName');
        $this->assertEquals(
            realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR
                . 'consoleAppMock.lock',
            $pathDefault
        );
        $testDir = '/tmp/console-app-test-dir';
        mkdir($testDir);
        $config2 = array(
            'oneInstanceOnly' => false,
            'lockDir' => $testDir,
        );
        $this->writeConfig($config2);
        Lock::getInstance()->unlock();
        $_SERVER['argv'] = self::$argvWithConfig;
        $object2 = new ConsoleAppMock();
        $pathWithDir = $this->invokeMethod($object2, 'getLockFileName');
        $this->assertEquals(
            realpath($testDir) . DIRECTORY_SEPARATOR . 'consoleAppMock.lock',
            $pathWithDir
        );
        $testFile = '/tmp/console-app-test-file.lock';
        $config3 = array(
            'oneInstanceOnly' => false,
            'lockFile' => $testFile,
        );
        $this->writeConfig($config3);
        Lock::getInstance()->unlock();
        $object3 = new ConsoleAppMock();
        $pathWithFile = $this->invokeMethod($object3, 'getLockFileName');
        $this->assertEquals($testFile, $pathWithFile);
        rmdir($testDir);
    }
}
