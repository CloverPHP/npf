<?php

//namespace %%Setup%%;

/**
 * Class Profiler
 * @package %%Setup%%
 */
class Profiler
{
    /**
     * @var bool Profiler Enable or not
     */
    public $enable = true;
    /**
     * @var string Output Error as config format: json, xml, html, none(status=500)
     */
    public $errorOutput = 'json';
    /**
     * @var int Maximum Log
     */
    public $maxLog = 100;
    /**
     * @var bool Log Critical
     */
    public $logCritical = true;
    /**
     * @var bool Log Error
     */
    public $logError = true;
    /**
     * @var bool Log Info
     */
    public $logInfo = true;
    /**
     * @var bool Log Debug or not
     */
    public $logDebug = true;
    /**
     * @var bool Log db query
     */
    public $queryLogDb = true;
    /**
     * @var bool Log redis query
     */
    public $queryLogRedis = true;
}