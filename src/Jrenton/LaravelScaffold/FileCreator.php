<?php namespace Jrenton\LaravelScaffold;

use Illuminate\Console\Command;

class FileCreator
{
    /**
     * @var \Illuminate\Console\Command
     */
    private $command;

    /**
     * @var bool
     */
    public $fromFile;

    /**
     * @var string
     */
    public $namespace;

    /**
     * @param Command $command
     */
    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    /**
     * @param string $name
     * @param string $content
     * @param string $args
     * @param string $type
     * @return string
     */
    public function createFunction($name, $content, $args = "", $type = "public")
    {
        $fileContents = "\t$type function $name($args)\n";
        $fileContents .= "\t{\n";
        $fileContents .= $content;
        $fileContents .= "\t}\n\n";

        return $fileContents;
    }

    /**
     * @param $path
     * @param $content
     */
    public function createInterface($path, $content)
    {
        $this->createClass($path, $content, array(), array(), array(), "interface");
    }

    /**
     * @param $path
     * @param $content
     * @param $name
     */
    public function createMigrationClass($path, $content, $name)
    {
        $this->createClass($path, $content, array('name' => 'Migration'), array(), array('Illuminate\Database\Migrations\Migration', 'Illuminate\Database\Schema\Blueprint'), "class", $name, false, true);
    }

    /**
     * @param $path
     * @param $content
     * @param array $extends
     * @param array $vars
     * @param array $uses
     * @param string $type
     * @param string $customName
     * @param bool $useNamespace
     * @param bool $overwrite
     */
    public function createClass($path, $content, array $extends = array(), $vars = array(), array $uses = array(), $type = "class", $customName = "", $useNamespace = true, $overwrite = false)
    {
        $usesOutput = "";
        $extendsOutput = "";
        $namespace = "";

        $fileName = substr(strrchr($path, "/"), 1);

        if(empty($customName))
            $className = substr($fileName, 0, strrpos($fileName, "."));
        else
            $className = $customName;

        if($this->namespace && $useNamespace)
            $namespace = "namespace " . $this->namespace . ";";

        if($uses) {
            foreach($uses as $use) {
                $usesOutput .= "use $use;\n";
            }
            $usesOutput .= "\n";
        }

        if($extends) {
            $extendName = "extends";
            if(array_key_exists('type', $extends))
                $extendName = $extends['type'];

            $extendsOutput .= "$extendName";
            foreach($extends as $key => $extend) {
                if($key != "type") {
                    $extendsOutput .= " ".$extend.",";
                }
            }
            $extendsOutput = rtrim($extendsOutput, ",") . " ";
        }

        $fileContents = "<?php ".$namespace."\n\n";
        $fileContents .= "$usesOutput";
        $fileContents .= "$type ". $className . " " . $extendsOutput . "\n{\n";
        foreach($vars as $type => $name) {
            $fileContents .= "\t$type \$$name;\n";
        }
        $fileContents .= "\n";
        $fileContents .= $content;
        $fileContents .= "}\n";

        $this->createFile($path, $fileContents, $overwrite);
    }

    /**
     * @param string $fileName
     * @param string $fileContents
     * @param bool $overwrite
     */
    public function createFile($fileName, $fileContents, $overwrite = false)
    {
        if(\File::exists($fileName) && !$overwrite) {
            $overwrite = $this->fromFile ? true : $this->command->confirm("$fileName already exists! Overwrite it? ", true);

            if($overwrite)
                \File::put($fileName, $fileContents);
        }
        else
            \File::put($fileName, $fileContents);
    }

    /**
     * @param string $dir
     */
    public function createDirectory($dir)
    {
        if (!\File::isDirectory($dir))
            \File::makeDirectory($dir);
    }

    /**
     * @param array $functions
     * @return string
     */
    public function createFunctions($functions)
    {
        $fileContents = "";
        foreach ($functions as $function) {
            $args = "";
            if(array_key_exists('args', $function))
                $args = $function['args'];
            $fileContents .= $this->createFunction($function['name'], $function['content'], $args);
        }
        return $fileContents;
    }

    /**
     * @param $source
     * @param $destination
     */
    public function copyFile($source, $destination)
    {
        copy($source, $destination);
    }

    /**
     * @param $source
     * @param $destination
     */
    public function copyDirectory($source, $destination)
    {
        if (file_exists ( $destination ))
            $this->removeDirectory ( $destination );
        if (is_dir ( $source )) {
            mkdir ( $destination );
            $files = scandir ( $source );
            foreach ( $files as $file )
                if ($file != "." && $file != "..")
                    $this->copyDirectory ( "$source/$file", "$destination/$file" );
        } else if (file_exists ( $source ))
            copy ( $source, $destination );
    }

    /**
     * @param $dir
     */
    public function removeDirectory($dir)
    {
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file)
                if ($file != "." && $file != "..") $this->removeDirectory("$dir/$file");
            rmdir($dir);
        }
        else if (file_exists($dir)) unlink($dir);
    }
}
