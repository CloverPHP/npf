<?php

//namespace %%Setup%%;

/**
 * Class General
 * @package %%Setup%%
 */
class General
{
    /**
     * @var string Default Output Format json/xml
     */
    public $defaultOutput = 'json';
    /**
     * @var string Lock Key Prefix
     */
    public $lockPrefix = 'lock';
    /**
     * @var string Timezone
     */
    public $timezone = 'Asia/Kuala_Lumpur';
    /**
     * @var boolean Output Compress
     */
    public $compressOutput = true;
    /**
     * @var string Cross Origin Resource Sharing Support, fill * / url to enable or false to disable.
     */
    public $corsSupport = '*';
    /**
     * @var string Cross Origin Resource Sharing Allow Credentials (Allow Cross Domain Cookie)
     */
    public $corsAllowCredentials = 'true';
    /**
     * @var string Cross Origin Resource Sharing Allow Method
     */
    public $corsAllowMethod = 'POST,GET,OPTIONS';
    /**
     * @var string Cross Origin Resource Age
     */
    public $corsAge = 3600;
    /**
     * @var bool Cron Lock Enable
     */
    public $cronLock = false;
    /**
     * @var bool Daemon Lock Enable
     */
    public $daemonLock = false;
    /**
     * @var int Daemon Time to Life
     */
    public $daemonTtl = 300;
    /**
     * @var int Daemon Execution Interval
     */
    public $daemonInterval = 1000;
    /**
     * @var int Cronjob Time to Life
     */
    public $cronjobTtl = 60;
}