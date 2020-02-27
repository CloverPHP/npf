<?php

//namespace %%Setup%%;

/**
 * Class Twig
 * @package %%Setup%%
 */
Class Twig
{
    /**
     * Twig Syntax Style
     * @var array
     */
    public $syntaxStyle = [
        'tag_comment' => ['{#', '#}'],
        'tag_block' => ['{%', '%}'],
        'tag_variable' => ['{{', '}}'],
        'interpolation' => ['#{', '}'],
    ];

    /**
     * Auto Append Header when using twig
     * @var array
     */
    public $appendHeader = [];

    /**
     * Twig Paths to load
     * @var array
     */
    public $paths = [];

    /**
     * Twig Extension
     */
    public $extension = [];
}