<?php

//namespace %%Setup%%;

use Npf\Core\App;
use \Npf\Core\Model;

/**
 * Class Model
 * @package Model
 */
final class Loader extends Model
{
    /**
     * @var App
     */
    private $app;

    /**
     * @var array
     */
    private $components = [];

    /** @noinspection PhpMissingParentConstructorInspection */

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
     * @throws \Npf\Exception\InternalError
     */
    final public function __get($name)
    {
        if (!isset($this->components[$name]))
            $this->components[$name] = $this->app->model($name);
        return $this->components[$name];
    }
}