<?php

namespace Npf;

use Exception;
use JetBrains\PhpStorm\Pure;
use Npf\Boot\StartUp;
use Throwable;

/**
 * Class Web
 * @package Npf
 */
final class Web
{
    /**
     * @var StartUp
     */
    private StartUp $startUp;

    /**
     * StartUp constructor.
     * @param string $env
     * @param string $name
     * @param string $role
     */
    final public function __construct(string $env = 'Local', string $name = 'DefaultApp', string $role = 'web')
    {
        $role = strtolower($role);
        if (empty($role))
            $role = 'web';
        $this->startUp = new StartUp($role, $env, $name);
    }

    /**
     * Create App
     * @throws Exception
     */
    #[Pure] final public function createApp(): Core\App
    {
        return $this->startUp->getApp();
    }

    /**
     * Start Up
     * @throws Throwable
     */
    final public function __invoke()
    {
        $this->startUp->start();
    }
}