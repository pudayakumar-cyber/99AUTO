<?php

namespace Tests\Feature;

use App\Repositories\Back\PostRepository;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostTest extends TestCase
{
    public function testStoreImageDataWithEmptyPhotoArray()
    {
        Storage::fake('public');

        $repository = new PostRepository();
        
        // Simulating the case when no file is selected but photo[] is sent,
        // which results in an array containing null: [null]
        $request = new Request([], [
            'photo' => [null]
        ]);

        $storeData = $repository->storeImageData($request);

        $this->assertEquals([], $storeData);
    }

    public function testStoreImageDataWithValidPhoto()
    {
        Storage::fake('public');

        $repository = new PostRepository();

        // Create a fake uploaded file
        $file = UploadedFile::fake()->image('test_blog.jpg');

        $request = new Request();
        $request->files->set('photo', [$file]);
        // Set request input 'photo' to be truthy since form has photo input
        $request->merge(['photo' => [$file]]);

        $storeData = $repository->storeImageData($request);

        $this->assertCount(1, $storeData);
        $fileName = $storeData[0];
        $this->assertNotNull($fileName);

        // Assert file exists on disk
        Storage::assertExists('images/' . $fileName);
    }

    public function testUpdateImageDataWithEmptyPhotoArray()
    {
        Storage::fake('public');

        $repository = new PostRepository();

        // Create a fake post object
        $post = new \stdClass();
        $post->photo = json_encode(['existing_image.jpg']);

        $request = new Request([], [
            'photo' => [null]
        ]);

        $storeData = $repository->UpdateImageData($request, $post);

        $this->assertEquals(['existing_image.jpg'], $storeData);
    }

    public function testUpdateImageDataWithValidPhoto()
    {
        Storage::fake('public');

        $repository = new PostRepository();

        // Create a fake post object
        $post = new \stdClass();
        $post->photo = json_encode(['existing_image.jpg']);

        // Create a fake uploaded file
        $file = UploadedFile::fake()->image('new_blog.jpg');

        $request = new Request();
        $request->files->set('photo', [$file]);
        $request->merge(['photo' => [$file]]);

        $storeData = $repository->UpdateImageData($request, $post);

        $this->assertCount(2, $storeData);
        $this->assertEquals('existing_image.jpg', $storeData[0]);
        $newFileName = $storeData[1];
        $this->assertNotNull($newFileName);

        // Assert file exists on disk
        Storage::assertExists('images/' . $newFileName);
    }
}
