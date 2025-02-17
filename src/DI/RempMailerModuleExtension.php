<?php

namespace Crm\RempMailerModule\DI;

use Contributte\Translation\DI\TranslationProviderInterface;
use Crm\RempMailerModule\RempMailerModule;
use Nette\Application\IPresenterFactory;
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

final class RempMailerModuleExtension extends CompilerExtension implements TranslationProviderInterface
{
    private const PARAM_HOST = 'host';
    private const PARAM_API_TOKEN = 'api_token';

    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            self::PARAM_HOST => Expect::string()->dynamic()->required(),
            self::PARAM_API_TOKEN => Expect::string()->dynamic()->required(),
        ]);
    }

    public function loadConfiguration()
    {
        $builder = $this->getContainerBuilder();

        // load services from config and register them to Nette\DI Container
        $this->compiler->loadDefinitionsFromConfig(
            $this->loadFromFile(__DIR__.'/../config/config.neon')['services']
        );

        // configure API client
        $builder->getDefinitionByType(Config::class)
            ->addSetup('setHost', [$this->config->{self::PARAM_HOST}])
            ->addSetup('setApiToken', [$this->config->{self::PARAM_API_TOKEN}]);

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
        $builder->getDefinition($builder->getByType(IPresenterFactory::class))
            ->addSetup('setMapping', [['RempMailer' => 'Crm\RempMailerModule\Presenters\*Presenter']]);
    }

    /**
     * Return array of directories, that contain resources for translator.
     * @return string[]
     */
    public function getTranslationResources(): array
    {
        return [__DIR__ . '/../lang/'];
    }
}
