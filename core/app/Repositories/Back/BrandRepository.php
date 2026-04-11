<?php

namespace App\Repositories\Back;

use App\Models\Brand;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandRepository
{

    /**
     * Store Brand.
     */
    public function store($request)
    {
        $input = $request->all();

        /*
        |--------------------------------
        | Handle image upload (SAFE)
        |--------------------------------
        */
        if ($request->hasFile('photo')) {

            $file = $request->file('photo');

            // extra safety (prevents your previous error)
            if ($file && $file->isValid()) {

                $file = $request->file('photo');

                $photoName = 'BR_' . time() . Str::random(8) . '.' . $file->getClientOriginalExtension();

                $path = Storage::disk('public')->put(
                    'images/' . $photoName,
                    file_get_contents($file->getPathname())
                );

                $input['photo'] = $photoName;
            }
        }

        Brand::create($input);
    }

    /**
     * Update Brand.
     */
    public function update($brand, $request)
    {
        $input = $request->all();

        if ($request->hasFile('photo')) {

            $file = $request->file('photo');

            if ($file && $file->isValid()) {

                // 🔥 delete old image
                if ($brand->photo) {
                    Storage::disk('public')->delete('images/' . $brand->photo);
                }

                $file = $request->file('photo');

                $photoName = 'BR_' . time() . Str::random(8) . '.' . $file->getClientOriginalExtension();

                $path = Storage::disk('public')->put(
                    'images/' . $photoName,
                    file_get_contents($file->getPathname())
                );

                $input['photo'] = $photoName;
            }
        }

        $brand->update($input);
    }

    /**
     * Delete Brand.
     */
    public function delete($brand)
    {
        // 🔥 delete image safely
        if ($brand->photo) {
            Storage::disk('public')->delete('images/' . $brand->photo);
        }

        $brand->delete();
    }
}
