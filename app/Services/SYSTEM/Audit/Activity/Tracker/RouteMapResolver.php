<?php

namespace App\Services\SYSTEM\Audit\Activity\Tracker;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RouteMapResolver
{
    protected ?array $maps = null;

    protected const CACHE_KEY = 'activity:routemap:v1';

    /**
     * Resolve route/path saat ini menjadi:
     *  ['id_grup_fitur' => ?int, 'id_menu' => ?int, 'default_action' => ?string]
     */
    public function resolve(Request $request): array
    {
        $this->loadOnce();

        $routeName = optional($request->route())->getName();
        $path = ltrim($request->path(), '/');
        $pathLower = strtolower($path);

        // 1. exact route name
        if ($routeName && isset($this->maps['route'][$routeName])) {
            return $this->maps['route'][$routeName];
        }

        // 2. exact path
        if (isset($this->maps['path'][$pathLower])) {
            return $this->maps['path'][$pathLower];
        }

        // 3. prefix (longest match menang)
        if (! empty($this->maps['prefix'])) {
            foreach ($this->maps['prefix'] as $prefix => $value) {
                if ($prefix === '' || str_starts_with($pathLower, $prefix)) {
                    return $value;
                }
            }
        }

        return ['id_grup_fitur' => null, 'id_menu' => null, 'default_action' => null];
    }

    public function flush(): void
    {
        $this->maps = null;
        Cache::forget(self::CACHE_KEY);
    }

    protected function loadOnce(): void
    {
        if ($this->maps !== null) {
            return;
        }

        $ttl = (int) (config('activity-tracker.route_map_cache_ttl') ?: 3600);

        $this->maps = Cache::remember(self::CACHE_KEY, $ttl, function () {
            return $this->buildFromDb();
        });
    }

    protected function buildFromDb(): array
    {
        $maps = ['route' => [], 'path' => [], 'prefix' => []];

        try {
            $rows = DB::table('N_HRIS_Activity_Route_Map')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Priority')
                ->orderByRaw('LEN(Match_Value) DESC')
                ->get([
                    'Match_Type',
                    'Match_Value',
                    'Id_Grup_Fitur',
                    'Id_Menu',
                    'Default_Action',
                ]);
        } catch (\Throwable $e) {
            return $maps;
        }

        foreach ($rows as $r) {
            $type = strtolower((string) $r->Match_Type);
            $value = strtolower((string) $r->Match_Value);
            $entry = [
                'id_grup_fitur' => $r->Id_Grup_Fitur !== null ? (int) $r->Id_Grup_Fitur : null,
                'id_menu' => $r->Id_Menu !== null ? (int) $r->Id_Menu : null,
                'default_action' => $r->Default_Action ? (string) $r->Default_Action : null,
            ];

            if ($type === 'route') {
                $maps['route'][$value] = $entry;
            } elseif ($type === 'path') {
                $maps['path'][$value] = $entry;
            } elseif ($type === 'prefix') {
                $maps['prefix'][$value] = $entry;
            }
        }

        return $maps;
    }
}
