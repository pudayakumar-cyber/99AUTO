<?php

namespace Tests\Unit;

use App\Support\BlogImage;
use Tests\TestCase;

class BlogImageTest extends TestCase
{
    public function test_it_returns_the_first_safe_image_from_json(): void
    {
        $this->app['url']->forceRootUrl('https://99autoparts.ca');

        $this->assertSame(
            'https://99autoparts.ca/core/public/storage/images/post-one.webp',
            BlogImage::url(
                json_encode(['post-one.webp', 'post-two.jpg']),
                'https://99autoparts.ca/core/public/storage/images/placeholder.png'
            )
        );
    }

    public function test_it_uses_the_fallback_for_missing_or_unsafe_images(): void
    {
        $fallback = 'https://99autoparts.ca/core/public/storage/images/placeholder.png';

        $this->assertSame($fallback, BlogImage::url(null, $fallback));
        $this->assertSame($fallback, BlogImage::url('null', $fallback));
        $this->assertSame($fallback, BlogImage::url('["payload.php"]', $fallback));
    }

    public function test_it_supports_legacy_single_filename_values(): void
    {
        $this->app['url']->forceRootUrl('https://99autoparts.ca');

        $this->assertSame(
            ['https://99autoparts.ca/core/public/storage/images/legacy.jpg'],
            BlogImage::urls('legacy.jpg')
        );
    }
}
