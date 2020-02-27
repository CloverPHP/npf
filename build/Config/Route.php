<?php

//namespace %%Setup%%;

/**
 * Class Route
 * @package %%Setup%%
 */
Class Route
{
    /**
     * Root Directory of Controller
     * @var array
     */
    public $rootDirectory = 'App';

    /**
     * Default Home Directory of Controller
     * @var array
     */
    public $homeDirectory = 'Index';

    /**
     * Default Index File of Controller
     * @var array
     */
    public $indexFile = 'Index';

    /**
     * Default Index File of Controller
     * @var array
     */
    public $defaultWebRoute = 'Index/Index';

    /**
     * Force Secure Connection if not
     */
    public $forceSecure = false;

    /**
     * @var bool Route static file if true.
     */
    public $routeStaticFile = true;

    /**
     * @var int Static File Expired time, how long to be expired.
     * time in second
     */
    public $staticFileCacheTime = 3600;

    /**
     * @var string Use this config is not declare on $staticFileContentType
     * auto - if value set to auto then will try detect file content-type.
     */
    public $defaultStaticFileContentType = 'auto';

    /**
     * @var array Content type for static file if declare here.
     */
    public $staticFileContentType = [
        'html' => 'text/html; charset=UTF-8',
        'xml' => 'text/xml; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'json' => 'application/json charset=UTF-8',
    ];
}