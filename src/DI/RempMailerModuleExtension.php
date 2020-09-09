<?php

namespace Crm\RempMailerModule\DI;

use Crm\RempMailerModule\RempMailerModule;
use Kdyby\Translation\DI\ITranslationProvider;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Tracy\Debugger;
use Tracy\ILogger;

final class RempMailerModuleExtension extends CompilerExtension implements ITranslationProvider
{
    private const PARAM_HOST = 'host';
    private const PARAM_API_TOKEN = 'api_token';
    private const PARAM_ENABLED = 'enabled';

    private $defaults = [
        self::PARAM_HOST => null,
        self::PARAM_API_TOKEN => null,
        self::PARAM_ENABLED => true,
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
        Compiler::loadDefinitions(
            $builder,
            $this->loadFromFile(__DIR__.'/../config/config.neon')['services']
        );

        // configure API client
        $builder->getDefinitionByType(Config::class)
            ->addSetup('setHost', [$this->config[self::PARAM_HOST]])
            ->addSetup('setApiToken', [$this->config[self::PARAM_API_TOKEN]]);

        if (!$this->config[self::PARAM_ENABLED]) {
            return;
        }

        // enable module
        $module = $builder->addDefinition($this->prefix('module'))
            ->setFactory(RempMailerModule::class);

        $builder->getDefinition('moduleManager')
            ->addSetup('addModule', [$module]);
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
