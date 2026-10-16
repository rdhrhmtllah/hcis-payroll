<?php

namespace Tests\Unit\VISITOR\Permit;

use App\Http\Controllers\VISITOR\Permit\VehiclePermit\VehiclePermitController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

class TransaksiPermitDocumentStorageTest extends TestCase
{
    public function test_permit_document_is_stored_on_gcs_and_cleanup_removes_only_new_file(): void
    {
        Storage::fake('gcs');
        $controller = (new ReflectionClass(VehiclePermitController::class))->newInstanceWithoutConstructor();
        $file = UploadedFile::fake()->image('stnk.jpg');
        $path = 'visitor/permits/PMT-TEST-0001/PMT-TEST-0001_stnk_test.jpg';

        $storedPath = $this->invoke($controller, 'storeUploadedFile', [$file, $path]);
        $this->assertSame($path, $storedPath);
        Storage::disk('gcs')->assertExists($path);

        $this->invoke($controller, 'cleanupUploadedDocuments', [[['jenis' => 'STNK', 'path' => $path]]]);
        Storage::disk('gcs')->assertMissing($path);
    }

    private function invoke(object $object, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }
}
