## Laravel 4 Scaffold Command

Automatically generates the files you need to get up and running. Generates a default layout, sets up bootstrap or foundation, prompts for javascript files (options are ember, angular, backbone, underscore, and jquery), creates model, controller, and views, runs migration, updates routes, and seeds your new table with mock data - all in one command.

## Installation

Begin by installing this package through Composer. Edit your project's `composer.json` file to require `jrenton/laravel-scaffold`

    "require-dev": {
		"jrenton/laravel-scaffold": "dev-master"
	}

Next, update Composer from the Terminal:

    composer update

Once this operation completes, the final step is to add the service provider. Open `app/config/app.php`, and add a new item to the providers array.

    'Jrenton\LaravelScaffold\LaravelScaffoldServiceProvider'

That's it! You're all set to go. Run the `artisan` command from the Terminal to see the new `scaffold` command.

    php artisan

## New features

###Keep a running list of your model definitions

There is now a `scaffold:update` command and it is very cool! In your config file, you will have an option
to specify a "modelDefinitions" file, and in this you will place all of your model definitions. See below
for an example.

When you update this file and run `php artisan scaffold:update` it will check to see what
has changed and update your models/migrations automatically! Currently, there is no support for what you
remove from this file, but it will come soon.


###Load models and properties from a file

You can now load as many models as you want from one file! Just run the command `php artisan scaffold:file "path/to/file.txt"` where `file.txt` is of the format:

    resource = true
    namespace = Oxford
    University hasMany Department string( name city state homepage ) -nt
    Department belongsTo University, hasMany Course string( name description ) number:integer
    resource = false
    Course belongsTo Department, hasMany Lesson string( name description ) integer( number credits )

Where resource is whether or not your controller is a resource controller. All controllers will follow what the previous `resource` was set, so you can mix and match.

If namespace is set, then it is applied globally, else you can namespace specific models by prefacing the name with the namespace.

`-nt` is an option that sets timestamps to false on the particular model.

`-sd` is an option that sets softDelete to true on the particular model.

###Configurations

This now comes with a configuration file. Configure all file directories, class names, view files, whether or not you want repository pattern, which css/js files to download, and you can completely customize view and layout files from within the templates folder! Be sure to run:

`php artisan config:publish jrenton/laravel-scaffold`

To include the config file within your config folder!

## Commands

`scaffold` will prompt you for a layout file and models
`scaffold:model` will prompt you for models
`scaffold:file "filename"` is how you can add multiple models from one file
`scaffold:update` searches for changes in the model definitions file (defined in your config file), and updates your models/migrations accordingly.

### Accepted arguments at the add model prompt

Within the command, there is a prompt to ask if you want to add tables, the syntax is simple!

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

Have a lot of properties that are "strings" or "integers" etc? No problem, just group them!

`Book belongsTo Author string( title content description publisher ) published:datetime`

If you are using the above syntax, please strictly adhere to it (for now).

### Video overview of command

Reading is boring... check out this overview: https://www.youtube.com/watch?v=6ESSjdUSNMw

This video is a bit out of date now, but the idea is still the same.

## Additional comments

The seeder now uses faker in order to randomly generate 10 rows in each table. It will try to determine the type, but you can open the seed file to verify. For more information on Faker: https://github.com/fzaninotto/Faker

This now utilizes template files, so you can specify the format for your views, controller, repository, and tests in a folder called "templates" in your app directory.

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

## Features to be added

- Add command `scaffold:rollback` to remove any files that were created during the last scaffold update.

## Future ideas

- Automatically create js file based on js framework that is specified.
