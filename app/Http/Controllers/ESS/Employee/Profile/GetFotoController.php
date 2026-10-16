<?php

namespace App\Http\Controllers\ESS\Employee\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;

class GetFotoController extends Controller
{
    public function show($filename)
    {
        $path = storage_path('app/public/ticket/'.$filename);

        if (! File::exists($path)) {
            abort(404);
        }

        $mime = File::mimeType($path);

        return response()->file($path, ['Content-Type' => $mime]);
    }
}
