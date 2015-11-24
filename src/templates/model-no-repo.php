<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class [Model] extends Model
{
    [content]

    public function store($input)
    {
        $[model] = new [Model];
        [repeat]
        $[model]->[property] = $input['[property]'];
        [/repeat]
        $[model]->save();
    }
}