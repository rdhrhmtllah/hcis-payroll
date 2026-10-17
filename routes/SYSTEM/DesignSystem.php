<?php

use Illuminate\Support\Facades\Route;

/*
 * Katalog design system HCIS.
 *
 * Halamannya berada di bawah modul SYSTEM karena design system adalah
 * kapabilitas teknis lintas modul, bukan domain bisnis. Master grup fitur
 * hanya mengenal ESS, KPI, LMS, VISITOR, dan SYSTEM.
 */

Route::group([], function () {
    Route::get('/design-system', function () {
        return inertia('SYSTEM/DesignSystem/Index');
    })->name('design-system');

    // Wajib didaftarkan SEBELUM rute {slug} di bawahnya. Pola [a-z0-9-]+ juga
    // cocok dengan kata statis, jadi rute yang terdaftar belakangan tidak
    // akan pernah tercapai.
    Route::get('/design-system/fondasi', function () {
        return inertia('SYSTEM/DesignSystem/Foundations');
    })->name('design-system.foundations');

    Route::get('/design-system/pola', function () {
        return inertia('SYSTEM/DesignSystem/Archetypes');
    })->name('design-system.archetypes');

    Route::get('/design-system/panduan', function () {
        return inertia('SYSTEM/DesignSystem/Guidelines');
    })->name('design-system.guidelines');

    Route::get('/design-system/migrasi', function () {
        return inertia('SYSTEM/DesignSystem/Migration');
    })->name('design-system.migration');

    Route::get('/design-system/{slug}', function (string $slug) {
        return inertia('SYSTEM/DesignSystem/Component', ['slug' => $slug]);
    })->where('slug', '[a-z0-9-]+')->name('design-system.component');
});
