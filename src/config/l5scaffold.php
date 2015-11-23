<?php

return array(

    /*
	|--------------------------------------------------------------------------
	| Model definitions file location
	|--------------------------------------------------------------------------
	|
	| This is the location where all of your models definitions are located.
    |   Running "php artisan scaffold:update" will update any models you have
    |   changed.
	|
	*/

    'modelDefinitionsFile' => 'app/models.txt',

    /*
	|--------------------------------------------------------------------------
	| Repository Pattern
	|--------------------------------------------------------------------------
	|
	| This is where you define if you want to use the repository pattern.
    |
    | Set baseRepository to true if you want all repositories to derive
    |   from a base repository interface
	|
	*/

    'useRepository' => true,
    'useBaseRepository' => true,

    /*
	|--------------------------------------------------------------------------
	| Application Name
	|--------------------------------------------------------------------------
	|
	| Define the name of your application
	|
	*/

    'appName' => 'Your project',

    /*
	|--------------------------------------------------------------------------
	| Downloads
	|--------------------------------------------------------------------------
	|
	| Set to "true" for those which you would like downloaded with your application.
    | They will also be automatically included in your layout file
	|
	*/

    'downloads' => array(

        'jquery1' => true,
        'jquery2' => false,
        'bootstrap' => true,
        'foundation' => false,
        'underscore' => false,
        'handlebars' => false,
        'angular' => true,
        'ember' => false,
        'backbone' => false

    ),

    /*
	|--------------------------------------------------------------------------
	| Paths
	|--------------------------------------------------------------------------
	|
	| Specify the path to the following folders
    |
	*/

    'paths' => array(

        'templates' => 'resources/templates',
        'controllers' => 'app/Http/controllers',
        'migrations' => 'database/migrations',
        'seeds' => 'database/seeds',
        'models' => 'app/Models',
        'repositories' => 'app/Repositories',
        'repositoryInterfaces' => 'app/Contracts/Repositories',
        'tests' => 'tests',
        'views' => 'resources/views',
        'routes' => 'app/Http',
        'layout' => 'resources/views/layouts/default.blade.php',
        'angular' => 'public/angular'

    ),

    /*
	|--------------------------------------------------------------------------
	| Dynamic Names
	|--------------------------------------------------------------------------
	|
	| Create your own named variable and include in it the templates!
    |
	| Eg: 'myName' => '[Model] is super fantastic!'
    |
	| Then place [myName] in your template file and it will output "Book is super fantastic!"
    |
    | [model], [models], [Model], or [Models] are valid in the dynamic name
	|
	*/

    'names' => array(

        'controller' => '[Model]Controller',
        'modelName' => '[Model]',
        'test' => '[Models]ControllerTest',
        'repository' => 'Eloquent[Model]Repository',
        'baseRepositoryInterface' => 'RepositoryInterface',
        'repositoryInterface' => '[Model]RepositoryInterface',
        'viewFolder' => '[model]',

    ),

    /*
	|--------------------------------------------------------------------------
	| Views
	|--------------------------------------------------------------------------
	|
	| Specify the names of your views.
    |
	| ***IMPORTANT** Whatever you change the name to, you need to make sure you
    |   have a file with the same name.txt in your templates folder, under
    |   resource and/or restful, depending on the type of controller.
	|
	*/

    'views' => array(

        'show',
        'edit',
        'create',
        'index'

    )
);
