<?php

namespace Crm\RempMailerModule\DI;

use Crm\RempMailerModule\RempMailerModule;
use Kdyby\Translation\DI\ITranslationProvider;
use Nette\DI\CompilerExtension;
use Tracy\Debugger;
use Tracy\ILogger;

final class RempMailerModuleExtension extends CompilerExtension implements ITranslationProvider
{
    private const PARAM_HOST = 'host';
    private const PARAM_API_TOKEN = 'api_token';

    private $defaults = [
        self::PARAM_HOST => null,
        self::PARAM_API_TOKEN => null,
    ];

    public function loadConfiguration()
    {
        $builder = $this->getContainerBuilder();

        $this->config = $this->validateConfig($this->defaults);
        if (!$this->config[self::PARAM_HOST]) {
            Debugger::log('Unable to initialize RempMailerModuleExtension, host config option is missing', ILogger::ERROR);
            return;
        }
        if (!$this->config[self::PARAM_API_TOKEN]) {
            Debugger::log('Unable to initialize RempMailerModuleExtension, api_token config option is missing', ILogger::ERROR);
            return;
        }

        // load services from config and register them to Nette\DI Container
        $this->compiler->loadDefinitionsFromConfig(
            $this->loadFromFile(__DIR__.'/../config/config.neon')['services']
        );

        // configure API client
        $builder->getDefinitionByType(Config::class)
            ->addSetup('setHost', [$this->config[self::PARAM_HOST]])
            ->addSetup('setApiToken', [$this->config[self::PARAM_API_TOKEN]]);

        // enable module
        $module = $builder->addDefinition($this->prefix('module'))
            ->setFactory(RempMailerModule::class);

        $builder->getDefinition('moduleManager')
            ->addSetup('addModule', [$module]);
    }

    public function beforeCompile()
    {
        $builder = $this->getContainerBuilder();
        // load presenters from extension to Nette
        $builder->getDefinition($builder->getByType(\Nette\Application\IPresenterFactory::class))
            ->addSetup('setMapping', [['RempMailer' => 'Crm\RempMailerModule\Presenters\*Presenter']]);
    }

    /**
     * Return array of directories, that contain resources for translator.
     * @return string[]
     */
    public function getTranslationResources()
    {
        return [__DIR__ . '/../lang/'];
    }
}
