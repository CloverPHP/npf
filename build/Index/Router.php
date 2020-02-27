<?php

//namespace %%Setup%%;

use Npf\Core\App;
use Npf\Core\Route;
use Module\Module;

/**
 * Class Router
 * @package App\Index
 */
final class Router
{
    /**
     * @var App
     */
    private $app;

    /**
     * Router constructor.
     * @param App $app
     * @param Route $route
     */
    final public function __construct(App &$app, Route &$route)
    {
        $this->app = &$app;
    }

    /**
     * GetClass
     * @param App $app
     * @return array
     */
    final public function __invoke(App &$app)
    {
        $module = new Module($app);
        return [&$module];
    }
}