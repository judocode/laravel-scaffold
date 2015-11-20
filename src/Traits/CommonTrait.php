<?php

namespace Binondord\LaravelScaffold\Traits;

use Binondord\LaravelScaffold\Makes\MakeModel;

trait CommonTrait {
    protected $commandContract = Binondord\LaravelScaffold\Contracts\ScaffoldCommandInterface::class;

    protected function useUtf8Encoding($argument)
    {
        return iconv(mb_detect_encoding($argument, mb_detect_order(), true), "UTF-8", $argument);
    }

    public function getModelDefinitionFile()
    {
        //do checking here
    }

    /**
     * Generate the desired migration.
     */
    protected function makeMigration()
    {
        new MakeMigration($this, $this->files);
    }


    /**
     * Generate an Eloquent model, if the user wishes.
     */
    protected function makeModel()
    {
        \App::make(MakeModel::class, [$this]);
    }


    /**
     * Generate a Seed
     */
    private function makeSeed()
    {
        new MakeSeed($this, $this->files);
    }

    /**
     * Make a Controller with default actions
     */
    private function makeController()
    {

        new MakeController($this, $this->files);

    }


    /**
     * Setup views and assets
     *
     */
    private function makeViews()
    {

        foreach ($this->views as $view) {
            // index, create, show, edit
            new MakeView($this, $this->files, $view);
        }


        $this->info('Views created successfully.');

        $this->info('Route::resource("'.$this->getObjName("names").'","'.$this->getObjName("Name").'Controller"); // Add this line in routes.php');

    }


    /**
     * Make a layout.blade.php with bootstrap
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function makeViewLayout()
    {
        new MakeLayout($this, $this->files);
    }


    /**
     * Get access to $meta array
     * @return array
     */
    public function getMeta()
    {
        return $this->meta;
    }

    protected function prepFire()
    {
        $this->info('Configuring ' . $this->getObjName("Name") . '...');

        // Setup migration and saves configs
        $this->meta['action'] = 'create';
        $this->meta['var_name'] = $this->getObjName("name");
        $this->meta['table'] = $this->getObjName("names"); // Store table name
    }

    protected function prepFileFire()
    {
        $this->info('Scaffold from file starting... ');
    }

    protected function prepUpdateFire()
    {
        $this->info('Scaffold Update starting... ');
    }
}