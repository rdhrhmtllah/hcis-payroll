<?php

namespace App\Http\Controllers\SYSTEM\Application\About;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AboutController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('SYSTEM/Application/About/About', [
            'appVersion' => config('services.project_config.app_version', '2.13.0'),
            'heroVideoUrl' => asset('vid/videoHero.mp4'),
            'userName' => $user?->name ?? ($user?->username ?? 'User'),
            'dashboardUrl' => '/homepage',
        ]);
    }
}
