<?php
/**
 * @author Uhon Liu http://phalconcmf.com <futustar@qq.com>
 */

namespace Core;

use Phalcon\Loader;
use Phalcon\Mvc\View;
use Phalcon\Di;
use Phalcon\DiInterface;
use Phalcon\Mvc\Dispatcher;
use Core\Cache\Cache;
use Phalcon\Mvc\View\Engine\Volt;
use Core\Models\CoreTemplates;
use Phalcon\Mvc\ModuleDefinitionInterface;

class FrontModule implements ModuleDefinitionInterface
{
    /**
     * @var string
     */
    public $module;

    /**
     * @var string
     */
    public $baseControllers;

    /**
     * @var string
     */
    public $baseModels;

    /**
     * @var string
     */
    public $baseForms;

    /**
     * @var string
     */
    public $baseHelpers;

    /**
     * Instance construct
     *
     * @param string $module
     */
    public function __construct($module)
    {
        global $APP_LOCATION;
        $APP_LOCATION = 'frontend';
        $this->module = $module;
        $this->baseControllers = 'Frontend\\' . ucfirst($module) . '\Controllers';
        $this->baseModels = 'Frontend\\' . ucfirst($module) . '\Models';
        $this->baseForms = 'Frontend\\' . ucfirst($module) . '\Forms';
        $this->baseHelpers = 'Frontend\\' . ucfirst($module) . '\Helpers';

        // Translate::getInstance()->addModuleLang($this->module, 'frontend');
        Translate::getInstance();
        $this->_setTemplateDefault();
    }

    /**
     * Set default template
     */
    protected final function _setTemplateDefault()
    {
        $config = Di::getDefault()->get('config');

        $cache = Cache::getInstance('APPLICATION');
        $templateDefault = $cache->get('DEFAULT_TEMPLATE');
        if($templateDefault === null) {
            /**
             * @var CoreTemplates $templateDefault
             */
            $templateDefault = CoreTemplates::findFirst('published = 1 AND location = \'frontend\'');
            $templateDefault = $templateDefault->base_name;
            $cache->save('DEFAULT_TEMPLATE', $templateDefault);
        }

        if($templateDefault) {
            $config->frontendTemplate->defaultTemplate = $templateDefault;
            Di::getDefault()->set('config', $config);
        }
    }

    /**
     * Register Auto Loaders
     *
     * @param \Phalcon\DiInterface $dependencyInjector
     */
    public function registerAutoLoaders(DiInterface $dependencyInjector = null)
    {
        $loader = new Loader();
        $loader->registerNamespaces([
            $this->baseControllers => APP_PATH . '/modules/frontend/' . $this->module . '/controllers/',
            $this->baseModels => APP_PATH . '/modules/frontend/' . $this->module . '/models/',
            $this->baseForms => APP_PATH . '/modules/frontend/' . $this->module . '/forms/',
            $this->baseHelpers => APP_PATH . '/modules/frontend/' . $this->module . '/helpers/',
        ]);

        $loader->register();
    }

    /**
     * Register services
     *
     * @param DiInterface $di
     */
    public function registerServices(DiInterface $di)
    {
        $module = $this->module;
        $baseControllers = $this->baseControllers;

        // Registering a dispatcher
        $di->set('dispatcher', function() use ($di, &$module, &$baseControllers) {

            /**
             * @var \Phalcon\Events\ManagerInterface $eventsManager
             */
            $eventsManager = $di->getShared('eventsManager');

            $eventsManager->attach("dispatch:beforeException",

                function($event, $dispatcher, $exception) {
                    /**
                     * @var Dispatcher $dispatcher
                     * @var \Phalcon\Mvc\Dispatcher\Exception $exception
                     */
                    switch ($exception->getCode()) {
                        case Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
                        case Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
                            $dispatcher->forward([
                                'module' => 'index',
                                'controller' => 'error',
                                'action' => 'notFound404',
                            ]);
                            return false;
                    }
                    return true;
                }
            );

            $dispatcher = new Dispatcher();

            // Set default namespace to this module
            $dispatcher->setDefaultNamespace($baseControllers);

            $dispatcher->setEventsManager($eventsManager);

            $dispatcher->setModuleName($module);

            return $dispatcher;
        });

        // Registering the view component
        $di->set('view', function() use ($di, &$module) {
            $view = new View();
            $view->setViewsDir(APP_PATH . '/modules/frontend/' . $module . '/views/');

            /**
             * @var \Phalcon\Events\Manager $eventsManager
             */
            $eventsManager = $di->getShared('eventsManager');
            $eventsManager->attach('view:beforeRender', new FrontTemplate($this->module));
            $eventsManager->attach('view:afterRender', new FrontTemplate($this->module));

            // Set view Event
            $view->setEventsManager($eventsManager);

            $view->registerEngines([
                '.volt' => function($view, $di) {
                    $volt = new Volt($view, $di);
                    $volt->setOptions([
                        'compiledPath' => function($templatePath) {
                            $templatePath = strstr($templatePath, '/app');
                            $dirName = dirname($templatePath);
                            if(!is_dir(ROOT_PATH . '/var/cache/volt' . $dirName)) {
                                mkdir(ROOT_PATH . '/var/cache/volt' . $dirName, 0755, true);
                            }
                            return ROOT_PATH . '/var/cache/volt' . $dirName . '/' . basename($templatePath, '.volt') . '.php';
                        },
                        'compileAlways' => method_exists($di, 'get') ? (bool)($di->get('config')->frontendTemplate->compileTemplate) : false,
                        'stat' => false
                    ]);
                    $compiler = $volt->getCompiler();
                    $compiler->addFunction('get_sidebar', 'get_sidebar');
                    $compiler->addFunction('__', '__');
                    $compiler->addFilter('t', function($resolvedArgs) {
                        return '__(' . $resolvedArgs . ')';
                    });
                    $compiler->addFunction('strtotime', 'strtotime');
                    $compiler->addFunction('human_timing', 'human_timing');
                    $compiler->addFunction('moneyFormat', 'moneyFormat');
                    $compiler->addFunction('number_format', 'number_format');
                    $compiler->addFunction('seo_header', 'seo_header');
                    $compiler->addFunction('seo_header_prefix', 'seo_header_prefix');
                    $compiler->addFunction('change_date_format', 'change_date_format');
                    $compiler->addFunction('in_array', 'in_array');
                    return $volt;
                }
            ]);
            return $view;
        });
    }
}