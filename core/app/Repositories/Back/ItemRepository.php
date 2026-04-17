<?php

namespace App\Repositories\Back;

use App\Helpers\ImageHelper;
use App\Models\Item;
use App\Models\Gallery;
use App\Models\Currency;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ItemRepository
{

    public function highlight($item, $request)
    {
        $allowedTypes = ['undefine', 'new', 'feature', 'top', 'best', 'flash_deal'];
        $isType = in_array($request->is_type, $allowedTypes, true) ? $request->is_type : 'undefine';

        $item->update([
            'is_type' => $isType,
            'date' => $isType === 'flash_deal' ? $request->date : null,
        ]);

        $this->clearHomepageCache();
    }

public function galleryDelete($gallery)
    {
        if ($gallery->photo) {
            Storage::disk('public')->delete('images/'.$gallery->photo);
        }

        $gallery->delete();
    }

    public function store($request)
    {
        $input = $request->all();

        /*
        |--------------------------------
        | Handle main image upload
        |--------------------------------
        */
        if ($request->hasFile('photo')) {

            $file = $request->file('photo');

            $photoName = 'OM_' . time() . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $thumbnailName = 'OM_' . time() . Str::random(8) . '.jpg';

            Storage::disk('public')->putFileAs('images', $file, $photoName);

            Storage::disk('public')->put('images/'.$thumbnailName, ImageHelper::optimizedThumbnailContents($file));

            $input['photo'] = $photoName;
            $input['thumbnail'] = $thumbnailName;
        }

        $curr = Currency::where('is_default',1)->first();
        $input['discount_price'] = $request->discount_price / $curr->value;
        $input['previous_price'] = $request->previous_price / $curr->value;

        if($request->has('meta_keywords')){
            $input['meta_keywords'] = str_replace(["value","{","}","[","]",":","\""],'', $request->meta_keywords);
        }

        if($request->has('tags')){
            $input['tags'] = str_replace(["value","{","}","[","]",":","\""],'', $request->tags);
        }

        if($request->has('is_social')){
            $input['social_icons'] = json_encode($input['social_icons']);
            $input['social_links'] = json_encode($input['social_links']);
        } else {
            $input['is_social'] = 0;
            $input['social_icons'] = null;
            $input['social_links'] = null;
        }

        if($request->has('is_specification')){
            $input['specification_name'] = json_encode($input['specification_name']);
            $input['specification_description'] = json_encode($input['specification_description']);
        } else {
            $input['is_specification'] = 0;
            $input['specification_name'] = null;
            $input['specification_description'] = null;
        }

        if($request->has('license_name') && $request->has('license_key')){
            $input['license_name'] = json_encode($input['license_name']);
            $input['license_key'] = json_encode($input['license_key']);
        } else {
            $input['license_name'] = null;
            $input['license_key'] = null;
        }

        /*
        |--------------------------------
        | Digital file upload
        |--------------------------------
        */
        if($request->item_type == 'digital' || $request->item_type == 'license'){

            if($request->hasFile('file')){
                $file = $request->file('file');

                $name = time().str_replace(' ','',$file->getClientOriginalName());

                $file->move('assets/files',$name);

                $input['file'] = $name;
            }
        }

        $input['is_type'] = 'undefine';

        $item_id = Item::create($input)->id;

        if($request->hasFile('galleries')){
            $this->galleriesUpdate($request,$item_id);
        }

        $this->clearHomepageCache();

        return $item_id;
    }


    /*
    |--------------------------------
    | Update Item
    |--------------------------------
    */

    public function update($item,$request)
    {
        $input = $request->all();

        if ($request->hasFile('photo')) {

            if($item->photo){
                Storage::disk('public')->delete('images/'.$item->photo);
            }

            if($item->thumbnail){
                Storage::disk('public')->delete('images/'.$item->thumbnail);
            }

            $file = $request->file('photo');

            $photoName = 'OM_' . time() . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $thumbnailName = 'OM_' . time() . Str::random(8) . '.jpg';

            Storage::disk('public')->putFileAs('images',$file,$photoName);

            Storage::disk('public')->put('images/'.$thumbnailName, ImageHelper::optimizedThumbnailContents($file));

            $input['photo'] = $photoName;
            $input['thumbnail'] = $thumbnailName;
        }

        $curr = Currency::where('is_default',1)->first();
        $input['discount_price'] = $request->discount_price / $curr->value;
        $input['previous_price'] = $request->previous_price / $curr->value;

        if($request->has('meta_keywords')){
            $input['meta_keywords'] = str_replace(["value","{","}","[","]",":","\""],'', $request->meta_keywords);
        }

        if($request->has('tags')){
            $input['tags'] = str_replace(["value","{","}","[","]",":","\""],'', $request->tags);
        }

        if($request->has('is_social')){
            $input['social_icons'] = json_encode($input['social_icons']);
            $input['social_links'] = json_encode($input['social_links']);
        } else {
            $input['is_social'] = 0;
            $input['social_icons'] = null;
            $input['social_links'] = null;
        }

        $item->update($input);

        if($request->hasFile('galleries')){
            $this->galleriesUpdate($request,$item->id);
        }

        $this->clearHomepageCache();
    }


    /*
    |--------------------------------
    | Delete Item
    |--------------------------------
    */

    public function delete($item)
    {

        if($item->photo){
            Storage::disk('public')->delete('images/'.$item->photo);
        }

        if($item->thumbnail){
            Storage::disk('public')->delete('images/'.$item->thumbnail);
        }

        if($item->galleries()->count() > 0){
            foreach($item->galleries as $gallery){

                Storage::disk('public')->delete('images/'.$gallery->photo);

                $gallery->delete();
            }
        }

        $item->delete();

        $this->clearHomepageCache();
    }


    /*
    |--------------------------------
    | Galleries
    |--------------------------------
    */

    public function galleriesUpdate($request,$item_id=null)
    {
        Gallery::insert($this->storeImageData($request,$item_id));
    }


    public function storeImageData($request,$item_id=null)
    {
        $storeData = [];

        if ($galleries = $request->file('galleries')) {

            foreach($galleries as $key => $gallery){

                $name = 'GAL_'.time().Str::random(6).'.'.$gallery->getClientOriginalExtension();

                Storage::disk('public')->putFileAs('images',$gallery,$name);

                $storeData[$key] = [
                    'photo' => $name,
                    'item_id' => $item_id ? $item_id : $request['item_id']
                ];
            }
        }

        return $storeData;
    }

    private function clearHomepageCache()
    {
        Cache::forget('homepage_data');

        foreach (['theme1', 'theme2', 'theme3', 'theme4', 'default'] as $theme) {
            Cache::forget('homepage_full_payload_' . $theme);
        }
    }

}
