Add the following lines to the API controller

// [Models]
public function getAll[Models]()
{
    return [Model]::all();
}

public function get[Model]($id)
{
    return [Model]::findOrFail($id);
}