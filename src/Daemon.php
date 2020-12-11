<?php
declare(strict_types=1);

namespace Npf;

use Exception;
use Npf\Boot\StartUp;

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
    final public function __construct($env = 'Local', $name = 'DefaultApp')
    {
        $this->startUp = new StartUp('daemon', $env, $name);
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