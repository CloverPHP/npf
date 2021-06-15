<?php
declare(strict_types=1);

namespace Npf;

use Npf\Boot\StartUp;
use Throwable;

/**
 * Class Daemon
 * @package Npf
 */
final class Daemon
{
    /**
     * @var StartUp
     */
    private StartUp $startUp;

    /**
     * StartUp constructor.
     * @param string $env
     * @param string $name
     */
    final public function __construct(string $env = 'Local', string $name = 'DefaultApp')
    {
        $this->startUp = new StartUp('daemon', $env, $name);
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