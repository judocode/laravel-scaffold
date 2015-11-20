<?php

namespace Binondord\LaravelScaffold;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

class GeneratorsServiceProvider extends ServiceProvider {

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

        foreach($customBindings as $group => $customBinding)
        {
            foreach($customBinding as $unit)
            {
                $unit = in_array($group, $usePrefix) ? $group.$unit : $unit;

                $class = __NAMESPACE__ ."\\".$group."s"."\\".$unit;
                $contract = __NAMESPACE__ ."\\Contracts\\".$group."s\\".$unit."Interface";

                $this->app->bind($contract, $class);
            }
        }
    }


	/**
	 * Register the commands.
	 */
    private function registerScaffoldGenerator()
    {
        $nameBase = 'command.larascaf.';
        $namespace = 'Binondord\\LaravelScaffold\\Commands\\';

        $commands = [
            'make',
            'model',
            'update',
            'file',
        ];

        foreach($commands as $command)
        {
            $class = $namespace.'Scaffold'.ucfirst($command).'Command';
            $bindname = $nameBase.'scaffold'.$command;
            $this->app->singleton($bindname, function ($app) use($class){
                return $app[$class];
            });

            $this->commands($bindname);
        }
    }

}

