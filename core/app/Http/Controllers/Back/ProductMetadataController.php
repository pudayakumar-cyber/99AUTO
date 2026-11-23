<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductMetadataController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function edit(Item $item)
    {
        return view('back.item.metadata', ['item' => $item]);
    }

    public function update(Request $request, Item $item)
    {
        $input = $request->only(['seo_title', 'seo_meta_description']);
        foreach (['seo_title', 'seo_meta_description'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                if (mb_check_encoding($input[$field], 'UTF-8')) {
                    $input[$field] = preg_replace('/\A[\p{Z}\s]+|[\p{Z}\s]+\z/u', '', $input[$field]);
                }
                if ($input[$field] === '') {
                    $input[$field] = null;
                }
            }
        }

        $validUtf8 = function ($attribute, $value, $fail) {
            if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                $fail('The '.$attribute.' must be valid UTF-8 text.');
            }
        };
        $metadata = Validator::make($input, [
            'seo_title' => ['present', 'nullable', 'string', 'max:255', 'not_regex:/<[^>]*>/', $validUtf8],
            'seo_meta_description' => ['present', 'nullable', 'string', 'max:1000', 'not_regex:/<[^>]*>/', $validUtf8],
        ])->validate();

        $query = DB::table('items')->where('id', $item->getKey());
        if ($query->update($metadata) === 0 && ! $query->exists()) {
            throw (new ModelNotFoundException())->setModel(Item::class, [$item->getKey()]);
        }

        return redirect()->route('back.item.metadata.edit', ['item' => $item->getKey()])
            ->with('success', __('SEO metadata updated.'));
    }
}
