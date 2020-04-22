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
    public $defaultStaticFileContentType = 'application/octet-stream';
    /**
     * @var array Static File Content Type
     */
    public $staticFileContentType = [
        'txt' => 'text/plan',
        'csv' => 'text/csv',
        'htm' => 'text/html',
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'text/javascript',

        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'font/vnd.ms-fontobject',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',

        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',

        'xml' => 'application/xml',
        'json' => 'application/json',
        'pdf' => 'application/pdf',
        'ogx' => 'application/ogg',
        'rtf' => 'application/rtf',

        'zip' => 'application/zip',
        'gz' => 'application/gzip',
        'bz' => 'application/x-bzip',
        'bz2' => 'application/x-bzip2',
        'rar' => 'application/vnd.rar',
        '7z' => 'application/x-7z-compressed',

        'avi' => 'video/x-msvideo',
        'mpeg' => 'video/mpeg',
        'ogv' => 'video/ogg',
        'webm' => 'video/webm',
        'mp4' => 'video/mp4',
        'flv' => 'video/x-flv',

        'weba' => 'audio/webm',
        'wav' => 'audio/wav',
        'mp3' => 'audio/mpeg',
        'oga' => 'audio/ogg',

        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

    ];
}