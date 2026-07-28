<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Support\AdminImageUpload;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminImageUploadTest extends TestCase
{
    public function test_detects_heic_by_extension_and_mime(): void
    {
        $this->assertTrue(AdminImageUpload::isHeic(
            \Illuminate\Http\UploadedFile::fake()->create('photo.HEIC', 10, 'image/heic')
        ));
        $this->assertTrue(AdminImageUpload::isHeic(
            \Illuminate\Http\UploadedFile::fake()->create('photo.heif', 10, 'image/heif')
        ));
        $this->assertFalse(AdminImageUpload::isHeic(
            \Illuminate\Http\UploadedFile::fake()->image('photo.JPG')
        ));
    }

    public function test_multipart_payload_failed_when_post_body_stripped(): void
    {
        $handler = new class
        {
            use HandlesAdminUploads;

            public function check(Request $request): bool
            {
                return $this->multipartPayloadFailed($request);
            }

            public function message(): string
            {
                return $this->multipartPayloadErrorMessage();
            }
        };

        $request = Request::create('/admin/exhibitions/1', 'PUT', [], [], [], [
            'CONTENT_LENGTH' => '25000000',
            'HTTP_CONTENT_LENGTH' => '25000000',
        ]);

        $this->assertTrue($handler->check($request));
        $this->assertStringContainsString('iPhone', $handler->message());
    }
}
