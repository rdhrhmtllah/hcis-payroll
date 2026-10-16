<?php

namespace App\Console\Commands\SYSTEM\Application\Maintenance;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MakeFullController extends Command
{
    protected $signature = 'make:fullcontroller
                            {module? : Registered top-level module, or legacy controller name}
                            {submodule? : Business submodule, or legacy developer identifier}
                            {feature? : Feature class name, for example SopExport}
                            {developer? : Developer identifier for generated metadata}
                            {--api : Also generate an API controller and API route file}
                            {--dry-run : Preview generated paths without writing files}';

    protected $description = 'Generate Laravel controllers and routes under the approved module structure.';

    private const REGISTERED_MODULES = [
        'HRIS',
        'KPI',
        'LMS',
        'SOP',
        'VISITOR',
        'SYSTEM',
    ];

    public function handle(): int
    {
        $arguments = array_values(array_filter([
            $this->argument('module'),
            $this->argument('submodule'),
            $this->argument('feature'),
            $this->argument('developer'),
        ], static fn ($argument) => $argument !== null));

        if (count($arguments) === 2) {
            $this->legacyFormatMessage();

            return self::FAILURE;
        }

        if (count($arguments) !== 4) {
            $this->error('Format tidak lengkap. Gunakan empat argumen: <module> <submodule> <feature> <developer>.');
            $this->line('Contoh: php artisan make:fullcontroller SOP Export SopExport Frans');

            return self::FAILURE;
        }

        try {
            $module = $this->normalizeModule($this->argument('module'));
            $submodule = $this->normalizeSegment($this->argument('submodule'), 'submodule');
            $feature = $this->normalizeStudly($this->argument('feature'), 'feature');
            $developer = $this->normalizeDeveloper($this->argument('developer'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $controllerPath = "{$module}/{$submodule}";
        $routePath = base_path("routes/{$module}/{$submodule}");
        $webController = "{$feature}Controller";
        $apiController = "{$feature}ApiController";
        $routePrefix = strtolower($module).'/'.Str::kebab($submodule);
        $featureRoute = Str::kebab($feature);

        $this->line("Module: {$module}");
        $this->line("Submodule: {$submodule}");
        $this->line("Feature: {$feature}");
        $this->line("Developer metadata: {$developer}");

        if (! $this->option('dry-run')) {
            $this->call('make:controller', [
                'name' => "{$controllerPath}/{$webController}",
                '-r' => true,
            ]);
        }

        $webRouteFile = $routePath."/{$feature}Web.php";
        if (! $this->option('dry-run')) {
            $this->writeRouteFile(
                $webRouteFile,
                $this->webRouteContent($module, $submodule, $feature, $webController, $routePrefix, $featureRoute, $developer),
            );
        }

        $created = [
            "app/Http/Controllers/{$controllerPath}/{$webController}.php",
            "routes/{$module}/{$submodule}/{$feature}Web.php",
        ];

        if ($this->option('api')) {
            if (! $this->option('dry-run')) {
                $this->call('make:controller', [
                    'name' => "{$controllerPath}/{$apiController}",
                    '--api' => true,
                ]);
            }

            $apiRouteFile = $routePath."/{$feature}Api.php";
            if (! $this->option('dry-run')) {
                $this->writeRouteFile(
                    $apiRouteFile,
                    $this->apiRouteContent($module, $submodule, $feature, $apiController, $routePrefix, $featureRoute, $developer),
                );
            }

            $created[] = "app/Http/Controllers/{$controllerPath}/{$apiController}.php";
            $created[] = "routes/{$module}/{$submodule}/{$feature}Api.php";
        }

        $this->info($this->option('dry-run')
            ? 'Dry-run selesai. Tidak ada file yang ditulis:'
            : 'Generator selesai. File yang dibuat atau dipertahankan:'
        );
        foreach ($created as $path) {
            $this->line(" - {$path}");
        }

        $this->newLine();
        $this->comment('Route loader/require harus diregistrasikan pada loader modul yang sesuai.');
        $this->comment('Command tidak membuat modul baru dan tidak menambahkan route ke routes/KPI berdasarkan developer.');

        return self::SUCCESS;
    }

    private function legacyFormatMessage(): void
    {
        $this->warn('Format command lama terdeteksi.');
        $this->newLine();
        $this->line('Command lama:');
        $this->line('php artisan make:fullcontroller <name> <developer>');
        $this->newLine();
        $this->line('Command baru:');
        $this->line('php artisan make:fullcontroller <module> <submodule> <feature> <developer>');
        $this->newLine();
        $this->line('Contoh:');
        $this->line('php artisan make:fullcontroller SOP Export Packaging Frans');
        $this->newLine();
        $this->line('Modul yang tersedia:');
        $this->line(implode(', ', self::REGISTERED_MODULES));
        $this->newLine();
        $this->error('Tidak ada file yang dibuat.');
    }

    private function normalizeModule(string $value): string
    {
        $module = strtoupper(trim($value));

        if (! in_array($module, self::REGISTERED_MODULES, true)) {
            throw new InvalidArgumentException(
                "Modul '{$value}' belum terdaftar. Pilih salah satu: ".implode(', ', self::REGISTERED_MODULES).'. '.
                'Jika ini domain baru, minta persetujuan dan perbarui module registry terlebih dahulu.'
            );
        }

        return $module;
    }

    private function normalizeSegment(string $value, string $label): string
    {
        $value = trim($value);

        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $value)) {
            throw new InvalidArgumentException(
                ucfirst($label)." '{$value}' tidak valid. Gunakan PascalCase tanpa slash atau spasi, misalnya Export."
            );
        }

        return $value;
    }

    private function normalizeStudly(string $value, string $label): string
    {
        $value = trim($value);
        $normalized = Str::studly($value);

        if ($normalized === '' || ! preg_match('/^[A-Z][A-Za-z0-9]*$/', $normalized)) {
            throw new InvalidArgumentException(
                ucfirst($label)." '{$value}' tidak valid. Gunakan nama fitur alfanumerik, misalnya SopExport."
            );
        }

        return $normalized;
    }

    private function normalizeDeveloper(string $value): string
    {
        $value = trim($value);

        if (! preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $value)) {
            throw new InvalidArgumentException(
                "Developer '{$value}' tidak valid. Gunakan identifier sederhana tanpa spasi atau path."
            );
        }

        return $value;
    }

    private function writeRouteFile(string $path, string $content): void
    {
        if (File::exists($path)) {
            $this->warn("Dilewati karena file sudah ada: {$path}");

            return;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);
    }

    private function webRouteContent(
        string $module,
        string $submodule,
        string $feature,
        string $controller,
        string $routePrefix,
        string $featureRoute,
        string $developer,
    ): string {
        return <<<PHP
<?php

use App\Http\Controllers\{$module}\{$submodule}\{$controller};
use Illuminate\Support\Facades\Route;

// Module: {$module} | Submodule: {$submodule} | Feature: {$feature} | Developer: {$developer}
Route::prefix('{$routePrefix}')->name('{$routePrefix}.')->group(function () {
    Route::resource('{$featureRoute}', {$controller}::class);
});
PHP."\n";
    }

    private function apiRouteContent(
        string $module,
        string $submodule,
        string $feature,
        string $controller,
        string $routePrefix,
        string $featureRoute,
        string $developer,
    ): string {
        return <<<PHP
<?php

use App\Http\Controllers\{$module}\{$submodule}\{$controller};
use Illuminate\Support\Facades\Route;

// Module: {$module} | Submodule: {$submodule} | Feature: {$feature} | Developer: {$developer}
Route::prefix('{$routePrefix}')->name('api.{$routePrefix}.')->group(function () {
    Route::apiResource('{$featureRoute}', {$controller}::class);
});
PHP."\n";
    }
}
