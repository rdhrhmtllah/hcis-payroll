<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Route admin warisan (Blade lama, tak ada di menu) dulu cukup login.
 * RBAC Fase 0 menguncinya ke System Admin.
 */
class RuteLamaSystemAdminTest extends TestCase
{
    private const RUTE = [
        'GET dashboardSuperUser',
        'GET master-periode',
        'GET master-periode/display_tambah',
        'POST master-periode/display_tambah',
        'GET crossReviewPeriode',
        'GET addCrossReviewPeriode',
        'POST storeCrossReviewPeriode',
        'POST destroyCrossPeriode',
        'GET divison',
        'GET level',
        'GET employee',
    ];

    public function test_rute_lama_admin_bergerbang_system_admin(): void
    {
        foreach (self::RUTE as $r) {
            [$metode, $uri] = explode(' ', $r, 2);
            $rute = collect(Route::getRoutes())->first(fn ($x) => $x->uri() === $uri && in_array($metode, $x->methods(), true));
            $this->assertNotNull($rute, "Route {$r} tidak ditemukan.");
            $this->assertContains('system.admin', $rute->gatherMiddleware(), "Route {$r} belum dikunci system.admin.");
        }
    }
}
