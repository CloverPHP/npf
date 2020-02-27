<?php

//namespace %%Setup%%;

use Npf\Core\App;
use Module\Module;
use Npf\Exception\InternalError;

/**
 * Class Router
 * @package Application\Index
 */
final class Html
{
    /**
     * @var App
     */
    private $app;
    /**
     * @var Module
     */
    private $module;

    /**
     * Router constructor.
     * @param App $app
     * @param Module $module
     */
    final public function __construct(App &$app, Module &$module)
    {
        $this->app = &$app;
        $this->module = &$module;
    }

    /**
     * GetClass
     * @param App $app
     * @param Module $module
     * @return void
     * @throws InternalError
     */
    final public function __invoke(App &$app, Module &$module)
    {
        $app->response->add('name', 'Hello World');
        $app->view->setView('twig', 'Sample.twig');
    }
}