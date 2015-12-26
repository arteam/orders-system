<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class MonologProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $config = parse_ini_file("/etc/orders-system/conf.ini", false);
        $logger = new Logger('name');
        $logger->pushHandler(new StreamHandler($config['logFile']));
        $container['logger'] = $logger;
    }
}