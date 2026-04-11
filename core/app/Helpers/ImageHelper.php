<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageHelper
{
    public static function optimizedThumbnailContents($file, int $size = 230, int $quality = 78): string
    {
        $image = \Image::make($file)
            ->orientate()
            ->fit($size, $size, function ($constraint) {
                $constraint->upsize();
            });

        return (string) $image->encode('jpg', $quality);
    }

    public static function handleUploadedImage($file, $path, $delete = null)
    {
        if ($file) {

            if ($delete) {
                Storage::delete($path . '/' . $delete);
            }

            $name = Str::random(4) . $file->getClientOriginalName();
            Storage::putFileAs($path, $file, $name);

            return $name;
        }
    }


    public static function uploadSummernoteImage($file, $path)
    {

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        if ($file) {

            $name = 'OM_' . time() .  Str::random(8) . '.' . $file->getClientOriginalExtension();
            Storage::putFileAs($path, $file, $name);

            return $name;
        }
    }



    public static function ItemhandleUploadedImage($file, $path, $delete = null)
    {
        if ($file) {

            if ($delete) {
                Storage::disk('public')->delete($path.'/'.$delete);
            }

            $photoName = 'OM_' . time() . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $thumbnailName = 'OM_' . time() . Str::random(8) . '.jpg';

            // Save original image
            Storage::disk('public')->putFileAs($path, $file, $photoName);

            $thumbnailPath = $path.'/'.$thumbnailName;
            Storage::disk('public')->put($thumbnailPath, self::optimizedThumbnailContents($file));

            return [$photoName, $thumbnailName];
        }
    }

    public static function handleUpdatedUploadedImage($file, $path, $data, $delete_path, $field)
    {

        $name = 'OM_' . time() .  Str::random(8) . '.' . $file->getClientOriginalExtension();

        Storage::putFileAs($path, $file, $name);


        if ($data[$field] != null) {
            Storage::delete($delete_path . '/' . $data[$field]);
        }

        return $name;
    }


    public static function ItemhandleUpdatedUploadedImage($file, $path, $data, $delete_path, $field)
    {

        $photoName = 'OM_' . time() .  Str::random(8) . '.' . $file->getClientOriginalExtension();
        $thumbnailName = 'OM_' . time() . Str::random(8) . '.jpg';


        $thumbnailPath = $path . '/' . $thumbnailName;
        Storage::put($thumbnailPath, self::optimizedThumbnailContents($file));


        $photoPath = $path . '/' . $photoName;
        Storage::putFileAs($path, $file, $photoName);

        if (!empty($data['thumbnail'])) {
            Storage::delete($delete_path . '/' . $data['thumbnail']);
        }

        if (!empty($data[$field])) {
            Storage::delete($delete_path . '/' . $data[$field]);
        }

        return [$photoName, $thumbnailName];
    }


    public static function handleDeletedImage($data, $field, $delete_path)
    {
        if (!empty($data[$field])) {
            Storage::delete($delete_path . '/' . $data[$field]);
        }
    }
}
