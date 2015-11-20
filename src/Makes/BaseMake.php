<?php namespace Binondord\LaravelScaffold\Makes;

use Binondord\LaravelScaffold\Contracts\ScaffoldCommandInterface;
use Illuminate\Filesystem\Filesystem;

class BaseMake
{
	/**
     * The Filesystem instance.
     *
     * @var $files
     */
    protected $files;

	/**
     * The ScaffoldCommandInterface instance.
     *
     * @var $command
     */
    protected $scaffoldCommandObj;

	function __construct(ScaffoldCommandInterface $command, Filesystem $files)
    {
    	$this->files = $files;
        $this->command = $command;
    }
}