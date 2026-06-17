<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\PageSeo;
use Illuminate\Http\Request;

class PageSeoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('adminlocalize');
    }

    /**
     * Display a listing of page SEO records.
     */
    public function index()
    {
        $pages = PageSeo::orderBy('id', 'asc')->get();
        return view('back.seo.index', compact('pages'));
    }

    /**
     * Show the form for editing the specified page SEO record.
     */
    public function edit($id)
    {
        $page = PageSeo::findOrFail($id);
        return view('back.seo.edit', compact('page'));
    }

    /**
     * Update the specified page SEO record.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|max:255',
            'meta_keywords' => 'nullable',
            'meta_description' => 'required|max:1000',
        ]);

        $page = PageSeo::findOrFail($id);
        
        $input = $request->only(['title', 'meta_description']);
        
        // Handle Tagify JSON format for meta_keywords
        if ($request->has('meta_keywords')) {
            $input['meta_keywords'] = str_replace(["value", "{", "}", "[", "]", ":", "\""], '', $request->meta_keywords);
            // Re-convert to Tagify JSON for consistency in admin panel editor
            $tags = array_map(function($val) {
                return ['value' => trim($val)];
            }, explode(',', $input['meta_keywords']));
            $input['meta_keywords'] = json_encode($tags);
        }

        $page->update($input);

        return redirect()->route('back.page_seo.index')->withSuccess(__('Page SEO Updated Successfully.'));
    }
}
