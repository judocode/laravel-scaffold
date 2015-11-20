<?php namespace Binondord\LaravelScaffold\Migrations;

use Illuminate\Console\Command;
use Binondord\LaravelScaffold\Contracts\AssetDownloaderInterface;
use Binondord\LaravelScaffold\Contracts\ScaffoldCommandInterface;
use Binondord\LaravelScaffold\Contracts\FileCreatorInterface;

/**
 * From Jrenton\LaravelScaffold\AssetDownloader
 * Class AssetDownloader
 * @package Binondord\LaravelScaffold\Migrations
 */

class AssetDownloader implements AssetDownloaderInterface
{
    /**
     * @var \Illuminate\Console\Command
     */
    private $command;

    /**
     * @var array
     */
    private $configSettings;

    /**
     * @var FileCreator
     */
    private $fileCreator;

    /**
     * @var bool
     */
    public $fromFile;

    /**
     * @var string
     */
    public $fileContents;

    /**
     * @param Command $command
     * @param array $configSettings
     * @param FileCreator $fileCreator
     */
    public function __construct(ScaffoldCommandInterface $command, FileCreatorInterface $fileCreator)
    {
        $this->command = $command;
        $this->fileCreator = $fileCreator;
    }

    public function setConfigSettings(array $configSettings)
    {
        $this->configSettings = $configSettings;
    }

    /**
     * @param $assetName
     * @param $downloadLocation
     */
    public function downloadAsset($assetName, $downloadLocation)
    {
        $type = substr(strrchr($downloadLocation, "."), 1);

        if($assetName == "jquery")
        {
            $assetName .= "1";
            if($this->configSettings['downloads'][$assetName] !== true) {
                $assetName = substr($assetName, 0, strlen($assetName)-1) ."2";
                if($this->configSettings['downloads'][$assetName] === true)
                    $downloadLocation = "http://code.jquery.com/jquery-2.1.0.min.js";
            }
        }

        if( $this->configSettings['downloads'][$assetName] === true )
        {
            $localLocation = "public/" . $type . "/" . $assetName . "." . $type;
            $ch = curl_init($downloadLocation);
            $fp = fopen($localLocation, "w");

            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_exec($ch);
            curl_close($ch);
            fclose($fp);

            $this->fileContents = str_replace("<!--[javascript]-->", "<script src=\"{{ url('$type/$assetName.$type') }}\"></script>\n<!--[javascript]-->", $this->fileContents);
        }
    }

    public function generateLayoutFiles()
    {
        $makeLayout = $this->fromFile ? true : $this->command->confirm('Create default layout file [y/n]? (specify css/js files in config) ', true);

        if( $makeLayout )
        {
            $layoutPath = $this->configSettings['pathTo']['layout'];

            $layoutDir = substr($layoutPath, 0, strrpos($layoutPath, "/"));

            $next_to_last = strrpos($layoutPath, "/", strrpos($layoutPath, "/") - strlen($layoutPath) - 1) + 1;

            $layoutName = str_replace("/", ".", substr($layoutPath, $next_to_last, strpos($layoutPath, ".") - $next_to_last));

            $directoriesToCreate = array($layoutDir, 'public/js', 'public/css', 'public/img');

            foreach ($directoriesToCreate as $dir) {
                $this->fileCreator->createDirectory($dir);
            }

            $content = \File::get($this->configSettings['pathTo']['controllers'].'BaseController.php');
            if(strpos($content, "\$layout") === false)
            {
                $content = preg_replace("/Controller {/", "Controller {\n\tprotected \$layout = '$layoutName';", $content);
                \File::put($this->configSettings['pathTo']['controllers'].'BaseController.php', $content);
            }

            $overwrite = false;

            if(\File::exists($layoutPath))
                $overwrite = $this->command->confirm('Layout file exists. Overwrite? [y/n]? ', true);

            if(!\File::exists($layoutPath) || $overwrite)
            {
                $this->fileContents = \File::get($this->configSettings['pathTo']['templates'].'layout.txt');

                $this->fileContents = str_replace("<!--[appName]-->", $this->configSettings['appName'], $this->fileContents);

                $this->downloadAsset("jquery", "http://code.jquery.com/jquery-1.11.0.min.js");

                $this->downloadCSSFramework();

                $this->downloadAsset("underscore", "http://underscorejs.org/underscore-min.js");
                $this->downloadAsset("handlebars", "http://builds.handlebarsjs.com.s3.amazonaws.com/handlebars-v1.3.0.js");
                $this->downloadAsset("angular", "https://ajax.googleapis.com/ajax/libs/angularjs/1.2.16/angular.min.js");
                $this->downloadAsset("ember", "http://builds.emberjs.com/tags/v1.5.0/ember.min.js");
                $this->downloadAsset("backbone", "http://backbonejs.org/backbone-min.js");

                \File::put($layoutPath, $this->fileContents);
            }
            else
            {
                $this->command->error('Layout file already exists!');
            }
        }
    }

    private function downloadCSSFramework()
    {
        if( $this->configSettings['downloads']['bootstrap'] )
        {
            $ch = curl_init("https://github.com/twbs/bootstrap/releases/download/v3.1.1/bootstrap-3.1.1-dist.zip");
            $fp = fopen("public/bootstrap.zip", "w");

            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_exec($ch);
            curl_close($ch);
            fclose($fp);

            $zip = zip_open("public/bootstrap.zip");
            if ($zip)
            {
                while ($zip_entry = zip_read($zip))
                {
                    $foundationFile = "public/".zip_entry_name($zip_entry);
                    $foundationDir = dirname($foundationFile);

                    $this->fileCreator->createDirectory($foundationDir);

                    if($foundationFile[strlen($foundationFile)-1] == "/")
                    {
                        if(!is_dir($foundationDir))
                            \File::makeDirectory($foundationDir);
                    }
                    else
                    {
                        $fp = fopen($foundationFile, "w");
                        if (zip_entry_open($zip, $zip_entry, "r"))
                        {
                            $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                            fwrite($fp,"$buf");
                            zip_entry_close($zip_entry);
                            fclose($fp);
                        }
                    }
                }
                zip_close($zip);
                \File::delete('public/bootstrap.zip');

                $dirPath = 'public/bootstrap-3.1.1-dist';
                $this->fileCreator->copyDirectory($dirPath, 'public/bootstrap');
                foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dirPath, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {
                    $path->isFile() ? unlink($path->getPathname()) : rmdir($path->getPathname());
                }
                rmdir($dirPath);
            }

            $fileReplace = "\t<link href=\"{{ url('bootstrap/css/bootstrap.min.css') }}\" rel=\"stylesheet\">\n";
            $fileReplace .= "\t<style>\n";
            $fileReplace .= "\t\tbody {\n";
            $fileReplace .= "\t\tpadding-top: 60px;\n";
            $fileReplace .= "\t\t}\n";
            $fileReplace .= "\t</style>\n";
            $fileReplace .= "\t<link href=\"{{ url('bootstrap/css/bootstrap-theme.min.css') }}\" rel=\"stylesheet\">\n";
            $fileReplace .= "<!--[css]-->\n";
            $this->fileContents = str_replace("<!--[css]-->",  $fileReplace, $this->fileContents);
            $this->fileContents = str_replace("<!--[javascript]-->", "<script src=\"{{ url('bootstrap/js/bootstrap.min.js') }}\"></script>\n<!--[javascript]-->", $this->fileContents);
        }
        else if($this->configSettings['downloads']['foundation'])
        {
            $ch = curl_init("http://foundation.zurb.com/cdn/releases/foundation-5.2.2.zip");
            $fp = fopen("public/foundation.zip", "w");

            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_exec($ch);
            curl_close($ch);
            fclose($fp);
            $zip = zip_open("public/foundation.zip");
            if ($zip)
            {
                while ($zip_entry = zip_read($zip))
                {
                    $foundationFile = "public/".zip_entry_name($zip_entry);
                    $foundationDir = dirname($foundationFile);

                    $this->fileCreator->createDirectory($foundationDir);

                    $fp = fopen("public/".zip_entry_name($zip_entry), "w");
                    if (zip_entry_open($zip, $zip_entry, "r"))
                    {
                        $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                        fwrite($fp,"$buf");
                        zip_entry_close($zip_entry);
                        fclose($fp);
                    }
                }
                zip_close($zip);
                \File::delete('public/index.html');
                \File::delete('public/robots.txt');
                \File::delete('humans.txt');
                \File::delete('foundation.zip');
                \File::deleteDirectory('public/js/foundation');
                \File::deleteDirectory('public/js/vendor');
                \File::move('public/js/foundation.min.js', 'public/js/foundation.js');
            }
            $fileReplace = "\t<link href=\"{{ url ('css/foundation.min.css') }}\" rel=\"stylesheet\">\n<!--[css]-->";
            $this->fileContents = str_replace("<!--[css]-->",  $fileReplace, $this->fileContents);
            $this->fileContents = str_replace("<!--[javascript]-->", "<script src=\"{{ url ('/js/foundation.js') }}\"></script>\n<!--[javascript]-->", $this->fileContents);
        }
    }
}
