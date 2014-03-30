<?php namespace Jrenton\LaravelScaffold;

class FileCreator
{
    private $command;
    public $fromFile;
    public $namespace;

    public function __construct($command)
    {
        $this->command = $command;
    }

    public function createFunction($name, $content, $args = "", $type = "public")
    {
        $fileContents = "\t$type function $name($args)\n";
        $fileContents .= "\t{\n";
        $fileContents .= $content;
        $fileContents .= "\t}\n\n";

        return $fileContents;
    }

    public function createInterface($path, $content)
    {
        $this->createClass($path, $content, array(), array(), array(), "interface");
    }

    public function createMigrationClass($path, $content, $name)
    {
        $this->createClass($path, $content, array('name' => 'Migration'), array(), array('Illuminate\Database\Migrations\Migration', 'Illuminate\Database\Schema\Blueprint'), "class", $name, false, true);
    }

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

    /*
    *   Checks if file exists, and then prompts to overwrite
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
     * @param $dir
     */
    public function createDirectory($dir)
    {
        if (!\File::isDirectory($dir))
            \File::makeDirectory($dir);
    }

    /**
     * @param $functions
     * @param $fileContents
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

    public function copyFile($source, $destination)
    {
        copy($source, $destination);
    }

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
