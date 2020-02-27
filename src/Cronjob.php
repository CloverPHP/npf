<?php

namespace Npf;

use Exception;
use Npf\Boot\StartUp;

/**
 * Class Cronjob
 * @package Npf
 */
final class Cronjob
{
    /**
     * @var StartUp
     */
    private $startUp;

    /**
     * StartUp constructor.
     * @param string $env
     * @param string $name
     */
    final public function __construct($env = 'Local', $name = 'DefaultApp')
    {
        $this->startUp = new StartUp('cronjob', $env, $name);
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