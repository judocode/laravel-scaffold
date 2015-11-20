<?php

namespace Binondord\LaravelScaffold\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Composer;
use Binondord\LaravelScaffold\Migrations\Scaffold;
use Binondord\LaravelScaffold\Contracts\ScaffoldCommandInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\ArgvInput;
use Illuminate\Console\GeneratorCommand;
use Mockery;

class ScaffoldCommand extends GeneratorCommand
{

	/**
     * @var Composer
     */
    private $composer;

    /**
     * Meta information for the requested migration.
     *
     * @var array
     */
    protected $meta;

    /**
     * Views to generate
     *
     * @var array
     */
    private $views = ['index', 'create', 'show', 'edit'];

    /**
     * Store name from Model
     * @var string
     */
    private $nameModel = "";
	
	/**
     * Store name from AppBasePath
     * @var string
     */
    private $appBasePath = "";

	/**
     * Create a new command instance.
     *
     * @param Filesystem $files
     * @param Composer $composer
     */
    public function __construct(Filesystem $files, Composer $composer)
    {
        parent::__construct($files);

        $this->composer = $composer;
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
    	//return content
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }

    public function getAppBasePath()
	{
		$this->appBasePath = $this->laravel['path'];
		return $this->appBasePath;
	}

	/**
     * Parse the name and format according to the root namespace.
     *
     * @param  string  $name
     * @return string
     */
    protected function parseName($name)
    {
    	$name = parent::parseName($name);
        return $name;
    	#return $this->getObjName();
    }

	/**
     * Generate names
     *
     * @param string $config
     * @return mixed
     * @throws \Exception
     */
    public function getObjName($config = 'Name')
    {

        $names = [];
        $this->setIOIfNull();

        $args_name = $this->getNameInput();


        // Name[0] = Tweet
        $names['Name'] = str_singular(ucfirst($args_name));
        // Name[1] = Tweets
        $names['Names'] = str_plural(ucfirst($args_name));
        // Name[2] = tweets
        $names['names'] = str_plural(strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $args_name)));
        // Name[3] = tweet
        $names['name'] = str_singular(strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $args_name)));


        if (!isset($names[$config])) {
            throw new \Exception("Position name is not found");
        };


        return $names[$config];
    }

    public function getModelPath()
    {
        $name = $this->parseName($this->getObjName());
        $path = $this->getPath($name);
        $this->makeDirectory($path);

        return [$name, $path];
    }

    public function processClass($name)
    {
        return $this->buildClass($name);
    }

    protected function dumpAutoload()
    {
        $this->info('Dump-autoload...');
        $this->composer->dumpAutoloads();
    }

    public function setIOIfNull()
    {
    	if(is_null($this->input))
    	{
    		$inputMock = Mockery::mock(ArgvInput::class);

    		$inputMock->shouldReceive('getArgument')
    			->once()
    			->with('name')
    			->andReturn('sample');

    		$this->input = $inputMock;
    	}
    }



}