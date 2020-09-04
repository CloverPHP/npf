<?php

//namespace %%Setup%%;

use Npf\Core\App;
use Npf\Exception\InternalError;

/**
 * Class Model
 * @package Model
 */
final class Loader
{
    /**
     * @var App
     */
    private $app;

    /**
     * @var array
     */
    private $components = [];

    /**
     * Model constructor.
     * @param App $app
     */
    final public function __construct(App &$app)
    {
        $this->app = &$app;
    }

    /**
     * @param $name
     * @return mixed
     * @throws InternalError
     */
    final public function __get($name)
    {
        if (!isset($this->components[$name]))
            $this->components[$name] = $this->app->model($name);
        return $this->components[$name];
    }
}