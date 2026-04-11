<?php

namespace App\Repositories\Back;

use App\Models\Category;
use App\Models\HomeCutomize;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryRepository
{

    /**
     * Store category.
     */
    public function store($request)
    {
        $input = $request->all();

        /*
        |--------------------------------
        | Handle image upload (SAME STYLE)
        |--------------------------------
        */
        if ($request->hasFile('photo')) {

            $file = $request->file('photo');

            $photoName = 'CAT_' . time() . Str::random(8) . '.' . $file->getClientOriginalExtension();

            $path = Storage::disk('public')->put(
                'images/' . $photoName,
                file_get_contents($file->getPathname())
            );

            $input['photo'] = $photoName;
        }

        Category::create($input);
    }

    /**
     * Update category.
     */
    public function update($category, $request)
    {
        $input = $request->all();

        if ($request->hasFile('photo')) {

            // 🔥 Delete old image (same as ItemRepository)
            if ($category->photo) {
                Storage::disk('public')->delete('images/' . $category->photo);
            }

            $file = $request->file('photo');

            $photoName = 'CAT_' . time() . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $path = Storage::disk('public')->put(
                'images/' . $photoName,
                file_get_contents($file->getPathname())
            );

            $input['photo'] = $photoName;
        }

        $category->update($input);
    }

    /**
     * Delete category.
     */
    public function delete($category)
    {
        $home = HomeCutomize::first();

        $popular_category = json_decode($home['popular_category'], true);
        $feature_category = json_decode($home['feature_category'], true);
        $two_column_category = json_decode($home['two_column_category'], true);
        $home_4_popular_category = json_decode($home['home_4_popular_category'], true);

        $check = false;

        for ($i = 1; $i < 5; $i++) {
            if (isset($popular_category['category_id' . $i]) && $popular_category['category_id' . $i] == $category->id) {
                $check = true;
            }
        }

        for ($i = 1; $i < 5; $i++) {
            if (isset($feature_category['category_id' . $i]) && $feature_category['category_id' . $i] == $category->id) {
                $check = true;
            }
        }

        for ($i = 1; $i < 3; $i++) {
            if (isset($two_column_category['category_id' . $i]) && $two_column_category['category_id' . $i] == $category->id) {
                $check = true;
            }
        }

        if (isset($home_4_popular_category) && in_array($category->id, $home_4_popular_category)) {
            $check = true;
        }

        if ($check) {
            return [
                'message' => __('This Category already used Home page section. Please change it first.'),
                'status' => 0
            ];
        }

        /*
        |--------------------------------
        | Delete image (SAME STYLE)
        |--------------------------------
        */
        if ($category->photo) {
            Storage::disk('public')->delete('images/' . $category->photo);
        }

        $category->delete();

        return [
            'message' => __('Category Deleted Successfully.'),
            'status' => 1
        ];
    }

}
