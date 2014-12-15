<?php

namespace Yeriomin\ConsoleApp;

/**
 * Generated by PHPUnit_SkeletonGenerator on 2014-12-15 at 00:25:05.
 */
class LockTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Lock
     */
    protected $object;

    /**
     * A temporary file used for testing
     *
     * @var string
     */
    protected $testFileName;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = Lock::getInstance();
        $this->testFileName = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'yeriomin-lock-test'
        ;
        @unlink($this->testFileName);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        @unlink($this->testFileName);
    }

    /**
     * @covers Yeriomin\ConsoleApp\Lock::lock
     * @covers Yeriomin\ConsoleApp\Lock::unlock
     */
    public function testLockUnlock()
    {
        $this->object->lock($this->testFileName);
        $this->assertFileExists($this->testFileName);
        $this->assertEquals(getmypid(), file_get_contents($this->testFileName));
        $this->object->unlock();
        $this->assertFileNotExists($this->testFileName);
        $this->object->lock($this->testFileName);
        $this->assertFileExists($this->testFileName);
        $this->assertEquals(getmypid(), file_get_contents($this->testFileName));
    }

    /**
     * @covers Yeriomin\ConsoleApp\Lock::lock
     */
    public function testLockExists()
    {
        file_put_contents($this->testFileName, getmypid());
        $this->setExpectedException(
            '\\Yeriomin\\ConsoleApp\\ConsoleAppException',
            'Could not lock ' . $this->testFileName
        );
        $this->object->lock($this->testFileName);
    }

    /**
     * Trying to lock when previous launch of our app crashed
     * and left a lock file intact
     *
     * @covers Yeriomin\ConsoleApp\Lock::lock
     */
    public function testLockOtherProcess()
    {
        file_put_contents($this->testFileName, '99999999');
        $this->object->lock($this->testFileName);
        $this->assertFileExists($this->testFileName);
        $this->assertEquals(getmypid(), file_get_contents($this->testFileName));
    }

    /**
     * Attempting to unlock while the other process is still running
     * must not break the lock
     *
     * @covers Yeriomin\ConsoleApp\Lock::unlock
     */
    public function testUnlockOtherProcess()
    {
        $fakePid = 99999999;
        file_put_contents($this->testFileName, $fakePid);
        $this->object->unlock();
        $this->assertFileExists($this->testFileName);
        $this->assertEquals($fakePid, file_get_contents($this->testFileName));
    }

    /**
     * @covers Yeriomin\ConsoleApp\Lock::unlock
     */
    public function testUnlockNothing()
    {
        // Nothing happens
        $this->object->unlock();
    }
}