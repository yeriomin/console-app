<?php

namespace Yeriomin\ConsoleApp;

/**
 * ConsoleAppMock
 * 
 * @author yeriomin
 */
class ConsoleAppMock extends ConsoleApp
{
    public function run() {
        sleep(5);
    }

    /**
     * Preventing stdout log output
     *
     * @return Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        $logger = parent::getLogger();
        $logger->popHandler();
        return $logger;
    }

    /**
     * Preventing stdout log output
     *
     * @return Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        $logger = parent::getLogger();
        $logger->popHandler();
        return $logger;
    }
}
