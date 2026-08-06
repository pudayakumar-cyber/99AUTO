<?php

namespace Tests\Unit;

use App\Support\StorefrontImage;
use PHPUnit\Framework\TestCase;

class StorefrontImageTest extends TestCase
{
    public function test_it_rejects_unsafe_gallery_extensions(): void
    {
        $this->assertFalse(StorefrontImage::isSafe('GAL_example.php'));
        $this->assertFalse(StorefrontImage::isSafe('GAL_example.php56'));
        $this->assertFalse(StorefrontImage::isSafe(''));
    }

    public function test_it_accepts_supported_local_and_remote_images(): void
    {
        $this->assertTrue(StorefrontImage::isSafe('product.webp'));
        $this->assertTrue(StorefrontImage::isSafe('https://cdn.example.test/product.jpg?width=800'));
    }
}
