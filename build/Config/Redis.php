<?php

//namespace %%Setup%%;

/**
 * Class Redis
 * @package %%Setup%%
 */
Class Redis
{
    /**
     * @var bool Redis Enable or not.
     */
    public $enable = false;
    /**
     * @var int Redis DB Index
     */
    public $db = 0;
    /**
     * @var string Redis Post Hash
     */
    public $postHash = '';
    /**
     * @var string Redis Server Auth Pass
     */
    public $authPass = '';
    /**
     * @var int Connection Timeout
     */
    public $timeout = 3;
    /**
     * @var int Read/Write Wait Timeout
     */
    public $rwTimeout = 3;
    /**
     * @var array Redis Instance
     */
    public $instance = [
        [
            ['127.0.0.1', 6379],
        ],
    ];
}