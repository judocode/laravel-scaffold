<?php namespace Binondord\LaravelScaffold\Traits;

use Illuminate\Filesystem\Filesystem;
use Binondord\LaravelScaffold\Commands\ScaffoldMakeCommand;

trait MakerTrait {

    /**
     * Get the path to where we should store the controller.
     *
     * @param $file_name
     * @param string $path
     * @return string
     */
    /*
    protected function getPath($file_name, $path='controller'){

        $appBasePath = $this->scaffoldCommandObj->getAppBasePath();
        $rootBasePath = dirname($appBasePath);

        if($path == "controller"){
            return $appBasePath.'/Http/Controllers/' . $file_name . '.php';

        } elseif($path == "model"){
            return $appBasePath.'/Models/'.$file_name.'.php';

        } elseif($path == "seed"){
            return $rootBasePath.'/database/seeds/'.$file_name.'.php';

        } elseif($path == "view-index"){
            return $rootBasePath.'/resources/views/'.$file_name.'/index.blade.php';

        } elseif($path == "view-edit"){
            return $rootBasePath.'/resources/views/'.$file_name.'/edit.blade.php';

        } elseif($path == "view-show"){
            return $rootBasePath.'/resources/views/'.$file_name.'/show.blade.php';

        } elseif($path == "view-create"){
            return $rootBasePath.'/resources/views/'.$file_name.'/create.blade.php';

        }
    }*/

}