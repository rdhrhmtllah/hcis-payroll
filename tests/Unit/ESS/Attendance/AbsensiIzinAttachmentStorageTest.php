<?php

namespace Tests\Unit\ESS\Attendance;

use App\Http\Controllers\ESS\Attendance\Legacy\AbsensiController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

class AbsensiIzinAttachmentStorageTest extends TestCase
{
    public function test_izin_attachment_is_uploaded_to_gcs_before_database_work(): void
    {
        Storage::fake('gcs');
        $controller = (new ReflectionClass(AbsensiController::class))->newInstanceWithoutConstructor();
        $request = Request::create('/', 'POST');
        $request->files->set('file', UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'));

        $path = $this->invoke($controller, 'uploadIzinAttachment', [$request]);

        $this->assertNotNull($path);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::transactionLevel());
        Storage::disk('gcs')->assertExists($path);
        Storage::disk('gcs')->delete($path);
    }

    private function invoke(object $object, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }
}
