<?php

namespace Npf;

use Exception;
use Npf\Boot\StartUp;

/**
 * Class Web
 * @package Npf
 */
final class Web
{
    /**
     * @var StartUp
     */
    private $startUp;

    /**
     * StartUp constructor.
     * @param string $env
     * @param string $name
     * @param string $role
     */
    final public function __construct($env = 'Local', $name = 'DefaultApp', $role = 'web')
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
    final public function createApp()
    {
        return $this->startUp->getApp();
    }

    /**
     * Start Up
     * @throws Exception
     */
    final public function __invoke()
    {
        $this->startUp->start();
    }
}