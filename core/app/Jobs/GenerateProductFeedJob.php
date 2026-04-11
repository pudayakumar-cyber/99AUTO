<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Item;
use App\Models\ProductExport;

class GenerateProductFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $exportId;

    public function __construct($exportId)
    {
        $this->exportId = $exportId;
    }

    public function handle(): void
    {
        $export = ProductExport::findOrFail($this->exportId);
        $export->update(['status' => 'processing']);

        $directoryPath = storage_path('app/public/exports');
        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        $fileName = 'product_feed_' . now()->timestamp . '.csv';
        $fullPath = $directoryPath . '/' . $fileName;

        $handle = fopen($fullPath, 'w');

        // ✅ FIXED HEADER (fitment instead of years/makes/models)
        fputcsv($handle, [
            'id',
            'prod_number',
            'moog',
            'category_id',
            'subcategory_id',
            'childcategory_id',
            'brand_id',
            'brand_name', // 👈 ADD THIS
            'name',
            'slug',
            'sku',
            'fitment', // 👈 SINGLE COLUMN
            'tags',
            'video',
            'sort_details',
            'details',
            'photo',
            'gallery_images',
            'discount_price',
            'previous_price',
            'stock',
            'meta_keywords',
            'meta_description',
            'status',
            'is_type',
            'date',
            'link',
            'file_type',
            'created_at',
            'updated_at',
            'item_type',
            'thumbnail'
        ]);

        $total = Item::count();
        $export->update(['total_records' => $total]);

        $processed = 0;

        Item::with(['galleries','brands'])
            ->chunk(500, function ($items) use ($handle, $export, $total, &$processed) {

                foreach ($items as $item) {

                    $fitmentStrings = [];

                    if (!empty($item->details)) {

                        libxml_use_internal_errors(true);
                        $dom = new \DOMDocument();

                        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $item->details);
                        libxml_clear_errors();

                        $xpath = new \DOMXPath($dom);

                        $rows = $xpath->query("//table[contains(@class,'pa-fitment-table')]//tbody/tr");

                        foreach ($rows as $row) {

                            $cells = $row->getElementsByTagName('td');

                            if ($cells->length >= 3) {

                                $yearText = trim($cells->item(0)->nodeValue);
                                $make     = trim($cells->item(1)->nodeValue);
                                $model    = trim($cells->item(2)->nodeValue);

                                $yearParts = array_map('trim', explode(',', $yearText));

                                foreach ($yearParts as $year) {
                                    $fitmentStrings[] = $year . ' ' . $make . ' ' . $model;
                                }
                            }
                        }
                    }

                    $fitmentColumn = implode(' | ', array_unique($fitmentStrings));

                    $baseImageUrl = 'https://99autoparts.ca/core/public/storage/images/';
                    $productUrl = 'https://99autoparts.ca/product/' . $item->slug;

                    $photoUrl = $item->photo
                        ? $baseImageUrl . $item->photo
                        : '';

                    $thumbnailUrl = $item->thumbnail
                        ? $baseImageUrl . $item->thumbnail
                        : '';

                    $galleryImages = $item->galleries->pluck('photo')->toArray();

                    $galleryUrls = array_map(function ($image) use ($baseImageUrl) {
                        return $baseImageUrl . $image;
                    }, $galleryImages);

                    $galleryString = implode('|', $galleryUrls);

                    fputcsv($handle, [
                        $item->id,
                        $item->prod_number,
                        $item->moog,
                        $item->category_id,
                        $item->subcategory_id,
                        $item->childcategory_id,
                        $item->brand_id,
                        $item->brand->name,
                        $item->name,
                        $item->slug,
                        $item->sku,
                        $fitmentColumn,
                        $item->tags,
                        $item->video,
                        $item->sort_details,
                        $item->details,
                        $photoUrl,
                        $galleryString,
                        $item->discount_price,
                        $item->previous_price,
                        $item->stock,
                        $item->meta_keyword,
                        $item->meta_description,
                        $item->status,
                        $item->is_type,
                        $item->date,
                        $productUrl,
                        $item->file_type,
                        $item->created_at,
                        $item->updated_at,
                        $item->item_type,
                        $thumbnailUrl,
                    ]);

                    $processed++;
                }

                $progress = $total > 0
                    ? intval(($processed / $total) * 100)
                    : 0;

                $export->update([
                    'processed_records' => $processed,
                    'progress' => $progress
                ]);
            });

        fclose($handle);

        $export->update([
            'file_name' => $fileName,
            'status' => 'completed',
            'progress' => 100
        ]);
    }
}