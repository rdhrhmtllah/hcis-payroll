<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

class UpdatePerformanceControllerTest extends TestCase
{
    public function test_temporary_performance_upload_falls_back_to_uploaded_file_pathname(): void
    {
        Storage::fake('gcs');

        $temporaryPath = tempnam(sys_get_temp_dir(), 'kpi-upload-');
        file_put_contents($temporaryPath, 'performance evidence');

        try {
            $file = new class($temporaryPath, 'evidence.pdf', 'application/pdf', UPLOAD_ERR_OK, true) extends UploadedFile
            {
                public function getRealPath(): string|false
                {
                    return false;
                }
            };

            $request = Request::create(
                '/api/v1/update-performance/store/temporary',
                'POST',
                [],
                [],
                ['kpi_data' => [['File_Berkas' => $file]]],
            );

            $method = new ReflectionMethod(UpdatePerformanceController::class, 'handleFileUploadStream');
            $storedPath = $method->invoke(new UpdatePerformanceController, $request, 0);

            $this->assertStringStartsWith('KPI/data-source/evidence_', $storedPath);
            Storage::disk('gcs')->assertExists($storedPath);
            $this->assertSame('performance evidence', Storage::disk('gcs')->get($storedPath));
        } finally {
            @unlink($temporaryPath);
        }
    }
}
