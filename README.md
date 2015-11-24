[![Build Status](https://img.shields.io/travis/binondord/laravel-scaffold/master.svg?style=flat-square)](https://travis-ci.org/binondord/laravel-scaffold)

## Laravel 5 Scaffold Command

Automatically generates the files you need to get up and running. Generates a default layout, sets up bootstrap or foundation, prompts for javascript files (options are ember, angular, backbone, underscore, and jquery), creates model, controller, and views, runs migration, updates routes, and seeds your new table with mock data - all in one command.

## Installation

Begin by installing this package through Composer. Edit your project's `composer.json` file to require `jrenton/laravel-scaffold`

    "require-dev": {
		"binondord/laravel-scaffold": "dev-master"
	}

Next, update Composer from the Terminal:

    composer update

Once this operation completes, the final step is to add the service provider. Open `app/config/app.php`, and add a new item to the providers array.

    'Binondord\LaravelScaffold\GeneratorsServiceProvider'

That's it! You're all set to go. Run the `artisan` command from the Terminal to see the new `scaffold` command.

    php artisan

## Configuration

Configure all file directories, class names, view files, whether or not you want repository pattern, which css/js files to download, and you can completely customize view and layout files from within the templates folder! Be sure to run:

```
php artisan vendor:publish --tag=config --force
php artisan vendor:publish --tag=templates --force
```

To include the config file within your config folder.

## Commands

`scaffold` will prompt you for a layout file and models

`scaffold:model` will prompt you for models

`scaffold:file "filename"` is how you can add multiple models from one file

`scaffold:update` searches for changes in the model definitions file (defined in your config file), and updates your models/migrations accordingly.

`scaffold:reset` removes previously created files except those already modified.

## Templates

This command utilizes template files, so you can specify the format for your views, controller, repository,
and tests in a folder called "templates" in your app directory (location provided in your config file -
defaults to app/templates).

You can also add your own views, as long as the name in the config file corresponds with the name
of the template within the templates folder.

## New features

###Keep a running list of your model definitions

There is now a `scaffold:update` command and it is very cool! In your config file, you will have an option
to specify a "modelDefinitions" file, and in this you will place all of your model definitions. See below
for an example.

    resource = true
    namespace = Oxford
    University hasMany Department string( name city state homepage ) -nt
    Department belongsTo University, hasMany Course string( name description ) number:integer
    resource = false
    Course belongsTo Department, hasMany Lesson string( name description ) integer( number credits ) -sd

 - Resource is whether or not your controller is a resource controller. All controllers will follow what the previous `resource` was set, so you can mix and match.
 - If namespace is set, then it is applied globally, else you can namespace specific models by prefacing the model name with the namespace.

When you update this file and run `php artisan scaffold:update` it will check to see what
has changed and update your models/migrations automatically! It will keep a "cache" file in the
same directory as your models file to track the changes, so DO NOT EDIT IT! This allows the command to
know if anything has been removed.

Models, fields, and relationships can be removed from this file and a migration will be automatically
generated to drop the respective model/field/foreign key, along with updating the model.

### Model syntax

The syntax for defining models is quite simple. Take a look at some examples:

`Book title:string published:datetime`

Or you can get fancy and add a relationship:

`Book belongsTo Author title:string published:datetime`

... and this will automatically add the "author" method to your Book model, and add "author_id" to your migration table. It will also check to see if the author table has been or will be created before book and auto-assign the foreign key.

You can also include namespaces:

`BarnesAndNoble\Book belongsTo Author title:string published:datetime`

Don't feel like typing everything all proper? That's fine too!

`book belongsto author title:string published:datetime`

You can also add multiple relationships!

`Book belongsTo Author, hasMany Word title:string published:datetime`

There are also several options that you can append to a model:

 - `-nt` is an option that sets timestamps to false on the particular model (they default to true)
 - `-sd` is an option that sets softDelete to true on the particular model.

Have a lot of properties that are "strings" or "integers" etc? No problem, just group them!

`Book belongsTo Author string( title content description publisher ) published:datetime`

If you are using the above syntax, please strictly adhere to it (for now).

## Video overview of command

Reading is boring... check out this overview: https://www.youtube.com/watch?v=6ESSjdUSNMw

This video is a bit out of date now (more awesomeness has been added), but the idea is still the same.

## Additional comments

The seeder uses faker in order to randomly generate 10 rows in each table. It will try to determine the type, but you can open the seed file to verify. For more information on Faker: https://github.com/fzaninotto/Faker

## Bonus

Want to go even FURTHER with the scaffold process?!?! Setup [foreman](https://github.com/Indatus/foreman), add jrenton/laravel-scaffold to the require-dev section, setup an app file to copy from that adds the laravel scaffold service provider, setup a database file that sets up your database to copy from, and finally setup a models.txt file to copy from (example above). Save that scaffold file somewhere convenient.

Then, you can edit your bash file to include a new alias:

    alias laravel="foremancreate"
    foremancreate(){
        foreman build /path/to/htdocs/folder/$1 /path/to/scaffold.json
        cd /path/to/htdocs/folder/$1
        composer update
        php artisan scaffold:file "app/models.txt"
        php artisan migrate
        php artisan db:seed
    }

Then run `laravel project-name` and watch as your application is fully built and scaffolded for you :)

Watch a [demonstration](http://youtu.be/e7otZWQSqrY) on youtube

## Features to be added for 1.0 release

- -nv option for no views
- -m option for migration only ( pivot tables )
- add field length option ( name:string|40 )
- on delete restrict/cascade/null
- remove model/controller/repository/views/seeds/tests if model is removed
- update model/controller/repository/views/seeds/tests if property is removed

## Future ideas

- Add command `scaffold:rollback` to remove any files that were created during the last scaffold update.
- Automatically create js file based on js framework that is specified.
