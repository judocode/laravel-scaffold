# Laravel 5 Scaffold Generator


Hi, this is a scaffold generator for Laravel 5.



## Usage

### Step 1: Install Through Composer

```
composer require 'binondord/laravel-scaffold' --dev
```

### Step 2: Add the Service Provider

Open `config/app.php` and, to your **providers** array at the bottom, add:

```
"Binondord\LaravelScaffold\GeneratorsServiceProvider"
```

### Step 3: Publish vendor assets, config and templates

Publish config and templates

```
php artisan vendor:publish --tag=config --force
php artisan vendor:publish --tag=templates --force
```

### Step 3: Create modeldefinition file under app, default file name is "model.txt". Sample models with relationship

```
resource = true
Dairy hasMany Equipment, hasMany WorkOrders, hasMany Orders name:string ,description:text
WorkOrder belongsTo Dairy, hasMany WOItems ordered_by:string created_by:integer details:text scheduled_on:date finished_on:datetime status:string
Equipment belongsTo Dairy name:string description:text
Orders hasMany Product quantity:integer price:decimal extended:decimal status:string
Products belongsToMany Order name:string description:text sku:string mfg_sku:string
```

### Step 3: Run Artisan!

You're all set. Run `php artisan` from the console, and you'll see the new commands `scaffold:update`.

## Examples


```
php artisan make:scaffold Tweet --schema="title:string:default('Tweet #1'), body:text"
```
This command will generate:

```
app/Tweet.php
app/Http/Controllers/TweetController.php
database/migrations/2015_04_23_234422_create_tweets_table.php
database/seeds/TweetTableSeeder.php
resources/views/layout.blade.php
resources/views/tweets/index.blade.php
resources/views/tweets/show.blade.php
resources/views/tweets/edit.blade.php
resources/views/tweets/create.blade.php
```
And don't forget to run:

```
php artisan migrate
```


## Scaffold
![image](http://i62.tinypic.com/11maveb.png)
![image](http://i58.tinypic.com/eqchat.png)
![image](http://i62.tinypic.com/20h7k8n.png)
