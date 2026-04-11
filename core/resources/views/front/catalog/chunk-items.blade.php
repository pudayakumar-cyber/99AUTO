@php
    $resolveProductImageUrl = function (?string $rawPath): string {
        $rawPath = trim((string) $rawPath);
        if ($rawPath === '') {
            return url('/core/public/storage/images/placeholder.png');
        }

        $pathOnly = parse_url($rawPath, PHP_URL_PATH) ?? $rawPath;

        if (preg_match('~/core/public/storage/images/([^/?#]+)~i', (string) $pathOnly, $m)) {
            return url('/core/public/storage/images/' . $m[1]);
        }
        if (preg_match('~/storage/images/([^/?#]+)~i', (string) $pathOnly, $m)) {
            return url('/core/public/storage/images/' . $m[1]);
        }

        $filename = basename((string) $pathOnly);
        if (trim($filename) === '') {
            return url('/core/public/storage/images/placeholder.png');
        }

        return url('/core/public/storage/images/' . $filename);
    };

    $extractItemFitmentRows = function ($item): array {
        if (empty($item->details)) return [];
        $rowsOut = [];
        preg_match_all('/<tr>(.*?)<\/tr>/si', $item->details, $rows);
        foreach ($rows[1] ?? [] as $rowHtml) {
            preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $rowHtml, $cols);
            if (count($cols[1] ?? []) !== 3) {
                continue;
            }

            [$yearsCell, $makeCell, $modelCell] = array_map(
                fn ($v) => trim(strip_tags((string) $v)),
                $cols[1]
            );
            if ($yearsCell === '' || $makeCell === '' || $modelCell === '') continue;
            $rowsOut[] = [
                'years' => array_values(array_filter(array_map('trim', explode(',', $yearsCell)))),
                'make' => $makeCell,
                'model' => $modelCell,
            ];
        }
        return $rowsOut;
    };

    $itemPartial = $checkType != 'list'
        ? 'front.catalog.partials.grid-item'
        : 'front.catalog.partials.list-item';
@endphp
<div class="catalog-chunk-payload" data-rendered-count="{{ $itemsChunk->count() }}">
    @foreach ($itemsChunk as $item)
        @include($itemPartial, ['item' => $item, 'resolveProductImageUrl' => $resolveProductImageUrl, 'extractItemFitmentRows' => $extractItemFitmentRows])
    @endforeach
</div>
