<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\[repositoryInterface];

class [controller] extends Controller
{
	protected $[model];

	public function __construct([repositoryInterface] $[model])
	{
		$this->[model] = $[model];
	}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
	public function index()
	{
    	$[models] = $this->[model]->all();
        return view('[model].index', compact('[models]'));
	}

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
	public function create()
	{
        return view('[model].create');
	}

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
	public function store()
	{
        $this->[model]->store(\Request::only([repeat]'[property]',[/repeat]));
        return redirect()->route('[model].index');
	}

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
	public function show($id)
	{
        $[model] = $this->[model]->find($id);
        return \View::make('[model].show')->with('[model]', $[model]);
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
	public function edit($id)
	{
        $[model] = $this->[model]->find($id);
        return view('[model].edit')->with('[model]', $[model]);
	}

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
	public function update($id)
	{
        $this->[model]->find($id)->update(\Request::only([repeat]'[property]',[/repeat]));
        return redirect()->route('[model].show', $id)->with('message', 'Item updated successfully.');
	}

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
	public function destroy($id)
	{
        $this->[model]->destroy($id);
        return redirect()->route('[model].index')->with('message', 'Item deleted successfully.');
	}

}
