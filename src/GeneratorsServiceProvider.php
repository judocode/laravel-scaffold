<?php

namespace Binondord\LaravelScaffold;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;

class GeneratorsServiceProvider extends ServiceProvider {

    protected $additionalAliases = [];

	/**
	 * Bootstrap the application services.
	 *
	 * @return void
	 */
	public function boot()
	{
        $this->publishes([
            __DIR__.'/config/l5scaffold.php' => config_path('l5scaffold.php'),
        ],'config');

        $this->publishes([
            __DIR__.'/templates/' => base_path('resources/templates'),
        ],'templates');

	}

	/**
	 * Register the application services.
	 *
	 * @return void
	 */
	public function register()
	{
        $this->customBindings();
		$this->registerScaffoldGenerator();
        $this->addAliases();

	}

    private function customBindings()
    {
        $usePrefix = [
            'Make'
        ];

        $customBindings = [
            'Service' => [
                'Scaffold'
            ],
            'Make' => [
                'Controller',
                'Layout',
                'Migration',
                'Seed',
                'View',
            ],
            'Migration' => [
                'AssetDownloader',
                'BaseModel',
                'FileCreator',
                'Migration',
                'Model',
                'NameParser',
                'Relation',
                'SchemaParser',
                'SyntaxBuilder'
            ]
        ];

        foreach($customBindings as $group => $items)
        {
            foreach($items as $customBinding)
            {
                $unit = in_array($group, $usePrefix) ? $group.$customBinding : $customBinding;

                $this->additionalAliases[] = 'Rdb'.$unit;

                $class = __NAMESPACE__ ."\\".$group."s"."\\".$unit;
                $contract = __NAMESPACE__ ."\\Contracts\\".$group."s\\".$unit."Interface";

                $this->app->bind($contract, $class);

                $this->app->bindShared(strtolower($unit), function($app) use($group, $unit)
                {
                    return app(__NAMESPACE__.'\\Contracts\\'.$group.'\\'.$unit.'Interface');
                });
            }
        }
    }


	/**
	 * Register the commands.
	 */
    private function registerScaffoldGenerator()
    {
        $nameBase = 'command.larascaf.';
        $commandNamespace = __NAMESPACE__.'\\Commands\\';

        $commands = [
            'make',
            /*'model',*/
            'update',
            'file',
        ];

        foreach($commands as $command)
        {
            $class = $commandNamespace.'Scaffold'.ucfirst($command).'Command';
            $bindname = $nameBase.'scaffold'.$command;
            $this->app->singleton($bindname, function ($app) use($class){
                return $app[$class];
            });

            $this->commands($bindname);
        }
    }

    private function addAliases()
    {
        $app = $this->app;
        $facadePath = __NAMESPACE__.'\\Facades\\';
        $additionalAliases =  $this->additionalAliases;

        $app->booting(function() use($additionalAliases, $facadePath){
            $loader = AliasLoader::getInstance();
            $aliases = $loader->getAliases();

            foreach($additionalAliases as $additionalAlias){
                $i=0;
                $key = $additionalAlias;
                do{
                    $isAliasExist = array_key_exists($key, $aliases);
                    if($isAliasExist){
                        throw new \Exception("Alias {$key} already existed.");
                    }

                    if($isAliasExist){
                        $i++;
                        $key = $additionalAlias.$i;
                        continue;
                    }

                    $aliases[$key] =  $facadePath.$additionalAlias;
                }while($isAliasExist);
            }
            $loader->setAliases($aliases);
        });
    }

}

