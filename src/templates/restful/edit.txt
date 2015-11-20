@section('content')
<div class="row">
    <h2>Edit [Model]</h2>
</div>
<div class="row">
    {{ Form::model($[model], array('url' => '[model]/update/'.$[model]->id, 'method' => 'PUT')) }}

    [repeat]
    <div class="form-group">
        {{ Form::label('[property]', '[Property]') }}
        {{ Form::text('[property]', null, array('class' => 'form-control')) }}
    </div>
    [/repeat]

    {{ Form::submit('Edit [Model]', array('class' => 'btn btn-success')) }}

    {{Form::close()}}
</div>
@stop