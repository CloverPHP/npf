<?php

namespace Npf\Boot;

use Exception;
use Npf\Core\App;
use Npf\Core\Kernel;
use Throwable;

/**
 * Class StartUp
 * @package Npf\Boot
 */
final class StartUp
{
    /**
     * @var Kernel
     */
    private Kernel $instance;

    /**
     * @var App
     */
    private App $app;

    /**
     * @var array App Info
     */
    private array $appInfo;

    /**
     * StartUp constructor.
     * @param string $role
     * @param string $env
     * @param string $name
     */
    final public function __construct(string $role = 'web',
                                      string $env = 'local',
                                      string $name = 'default'
    )
    {
        define('INIT_MEMORY', memory_get_usage());
        define('INIT_TIMESTAMP', microtime());
        define('INIT_HRTIME', hrtime(true));
        $this->appInfo = [
            'role' => $role,
            'env' => $env,
            'name' => $name,
        ];
        $this->instance = new Kernel();
        $this->app = $this->instance->buildApp($this->appInfo);
    }

    /**
     * Start Up
     * @throws Exception
     */
    final public function getApp(): App
    {
        return $this->app;
    }

    /**
     * Start Up
     * @throws Throwable
     */
    final public function start(): void
    {
        $this->instance->__invoke($this->appInfo);
    }
}
