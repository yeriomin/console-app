<?php
namespace Yeriomin\ConsoleApp;

/**
 * Console service interface
 * The only method tha needs to be implemented is run()
 * It must do all the work...
 *
 * @author yeriomin
 */
interface ConsoleAppInterface
{

    /**
     * Do the service's useful work
     *
     * @return void
     */
    public function run();
}
