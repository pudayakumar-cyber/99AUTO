<?php

namespace App\Http\Controllers\Back;

use App\{
    Models\Page,
    Http\Requests\PageRequest,
    Http\Controllers\Controller
};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;



class PageController extends Controller
{
    /**
     * Constructor Method.
     *
     * Setting Authentication
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('adminlocalize');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('back.page.index',[
            'datas' => Page::orderBy('id','desc')->get()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('back.page.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(PageRequest $request)
    {
        $input = $request->all();

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $photoName = 'PAGE_' . time() . Str::random(8) . '.' . $file->getClientOriginalExtension();

            Storage::disk('public')->put(
                'images/' . $photoName,
                file_get_contents($file->getPathname())
            );

            $input['photo'] = $photoName;
        }

        Page::create($input);
        return redirect()->route('back.page.index')->withSuccess(__('New Page Added Successfully.'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Page $page)
    {
        return view('back.page.edit',compact('page'));
    }


    /**
     * Change the status for editing the specified resource.
     *
     * @param  int  $id
     * @param  int  $pos
     * @return \Illuminate\Http\Response
     */
    public function pos($id,$pos)
    {
        $page = Page::find($id)->update(['pos' => $pos]);
        return redirect()->route('back.page.index')->withSuccess(__('Status Updated Successfully.'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(PageRequest $request, Page $page)
    {
        $input = $request->all();

        if ($request->hasFile('photo')) {
            if ($page->photo) {
                Storage::disk('public')->delete('images/' . $page->photo);
            }

            $file = $request->file('photo');
            $photoName = 'PAGE_' . time() . Str::random(8) . '.' . $file->getClientOriginalExtension();

            Storage::disk('public')->put(
                'images/' . $photoName,
                file_get_contents($file->getPathname())
            );

            $input['photo'] = $photoName;
        }

        $page->update($input);
        return redirect()->route('back.page.index')->withSuccess(__('Page Updated Successfully.'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Page $page)
    {
        if ($page->photo) {
            Storage::disk('public')->delete('images/' . $page->photo);
        }

        $page->delete();
        return redirect()->route('back.page.index')->withSuccess(__('Page Deleted Successfully.'));
    }
}
