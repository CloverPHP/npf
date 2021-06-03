<?php
declare(strict_types=1);

namespace Npf;

use Npf\Boot\StartUp;
use Throwable;

/**
 * Class Cronjob
 * @package Npf
 */
final class Cronjob
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
        $this->startUp = new StartUp('cronjob', $env, $name);
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