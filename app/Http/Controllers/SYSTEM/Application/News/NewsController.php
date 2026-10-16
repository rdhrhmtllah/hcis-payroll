<?php

namespace App\Http\Controllers\SYSTEM\Application\News;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NewsController extends Controller
{
    //
    public function index()
    {
        return inertia('SYSTEM/Application/News/addInformation');
    }

    public function store(Request $request) // Ganti nama metode jika perlu
    {
        $request->validate([
            'title' => 'required|string',
            'markdown_content' => 'required|string',
        ]);
        // dd($request->all());
        // Simpan Markdown mentah ke database
        // Anda bisa menggunakan find(1) untuk update satu record,
        // atau create() jika Anda mengelola banyak konten.
        // $content = Content::updateOrCreate(
        //     ['id' => 1], // Anggap saja Anda mengelola satu konten utama
        //     ['markdown_content' => $request->markdown_content]
        // );

        DB::table('HRIS_News_Dashboard')->insert([
            'Judul' => $request->title,
            'Content' => $request->markdown_content,
        ]);

        return response()->json([
            'message' => 'Konten Markdown berhasil disimpan!',

        ], 200);
    }

    /**
     * Mengambil konten Markdown dari database.
     * Menggunakan nama yang lebih spesifik untuk endpoint ini.
     */
    public function showMarkdown() // Ganti nama metode jika perlu
    {
        // Ambil konten yang terakhir disimpan atau konten dengan ID tertentu
        $content = Content::find(1); // Ambil konten dengan ID 1

        if (! $content) {
            return response()->json([
                'message' => 'Konten Markdown tidak ditemukan.',
                'markdown_content' => '', // Berikan string kosong jika tidak ada
            ], 404);
        }

        return response()->json([
            'message' => 'Konten Markdown berhasil diambil.',
            'markdown_content' => $content->markdown_content,
        ], 200);
    }
}
