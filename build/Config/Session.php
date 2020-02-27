<?php

//namespace %%Setup%%;

/**
 * Class Session
 * @package %%Setup%%
 */
Class Session
{
    /**
     * Session Driver Redis/Php
     */
    public $enable = true;

    /**
     * Session string Driver Redis/Php
     */
    public $driver = 'Redis';

    /**
     * Session string Name from cookie
     */
    public $name = 'PHPSESSID';

    /**
     * Session string key prefix
     */
    public $prefix = '';
    /**
     * @var int Redis Lock Time
     */
    public $lockTime = 600;

    /**
     * Cookie Setting
     */
    public $cookieLifetime = 0;
    public $cookiePath = '/';
    public $cookieDomain = null;
    public $cookieSecurity = false;
    public $cookieHttpOnly = true;
}