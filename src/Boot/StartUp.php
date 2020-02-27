<?php

namespace Npf\Boot;

use Exception;
use Npf\Core\App;
use Npf\Core\Kernel;

/**
 * Class StartUp
 * @package Npf\Boot
 */
final class StartUp
{
    /**
     * @var Kernel
     */
    private $instance;

    /**
     * @var App
     */
    private $app;

    /**
     * @var array App Info
     */
    private $appInfo = [];

    /**
     * StartUp constructor.
     * @param string $role
     * @param string $env
     * @param string $name
     */
    final public function __construct($role = 'web', $env = 'local', $name = 'default')
    {
        define('INIT_MEMORY', memory_get_usage());
        define('INIT_TIMESTAMP', microtime(true));
        $this->appInfo = [
            'role' => $role,
            'env' => $env,
            'name' => $name,
        ];
        $this->instance = new Kernel();
        $this->app = $this->instance->createApp($this->appInfo);
    }

    /**
     * Start Up
     * @throws Exception
     */
    final public function getApp()
    {
        return $this->app;
    }

    /**
     * Start Up
     * @throws Exception
     */
    final public function start()
    {
        $this->instance->__invoke($this->appInfo);
    }
}
