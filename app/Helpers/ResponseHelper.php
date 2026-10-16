<?php

namespace App\Helpers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Request;

class ResponseHelper
{
    /**
     * Response sukses
     *
     * @param  array  $extra  Field tambahan yang digabung ke payload (mis. penanda
     *                        daftar terpotong). Opsional & aditif — pemanggil lama
     *                        tidak terpengaruh.
     */
    public static function success($data = null, string $message = 'Berhasil', int $status = 200, array $extra = [])
    {
        return response()->json(array_merge([
            'success' => true,
            'status' => $status,
            'message' => $message,
            'result' => $data,
        ], $extra), $status);
    }

    /**
     * @param  array  $extra  Field tambahan yang digabung ke payload (mis. hitungan tab).
     *                        Opsional & aditif — pemanggil lama tidak terpengaruh.
     */
    public static function successWithPagination($data, int $page, int $limit, int $total, string $message = 'Berhasil', int $status = 200, array $extra = [])
    {
        return response()->json(array_merge([
            'success' => true,
            'status' => $status,
            'message' => $message,
            'result' => $data,
            'page' => $page,
            'total_page' => ceil($total / $limit),
            'total_data' => $total,
        ], $extra), $status);
    }

    public static function successWithPaginationV2($data, int $page, int $limit, int $total, string $message = 'Berhasil', int $status = 200, array $extra = [])
    {
        $paginator = new LengthAwarePaginator(
            $data,
            $total,
            $limit,
            $page,
            [
                'path' => Request::url(),
                'query' => Request::query(),
            ]
        );

        $paginatorArray = $paginator->toArray();

        return response()->json(array_merge([
            'success' => true,
            'status' => $status,
            'message' => $message,
            'result' => $paginatorArray['data'],
            'pagination' => [
                'total' => $paginatorArray['total'],
                'per_page' => $paginatorArray['per_page'],
                'current_page' => $paginatorArray['current_page'],
                'total_pages' => $paginatorArray['last_page'],
                'first_page_url' => $paginatorArray['first_page_url'],
                'last_page_url' => $paginatorArray['last_page_url'],
                'next_page_url' => $paginatorArray['next_page_url'],
                'prev_page_url' => $paginatorArray['prev_page_url'],
                'from' => $paginatorArray['from'],
                'to' => $paginatorArray['to'],
                'links' => $paginatorArray['links'],
            ],
        ], $extra), $status);
    }

    /**
     * Response error
     */
    /**
     * Tanggapan galat server yang MENCATAT exception aslinya.
     *
     * Dipakai menggantikan `error()` di dalam blok `catch` yang memulangkan 5xx.
     * Bedanya satu dan menentukan: `error()` hanya membentuk JSON, sehingga
     * exception yang barusan ditangkap hilang tanpa jejak. Diukur 16 September
     * 2026, 123 blok catch di repo ini berperilaku begitu — layar menerima 500,
     * log tidak memuat apa pun, dan sebab sebenarnya (misalnya kolom yang belum
     * ada di production) tersembunyi di balik kalimat "Gagal mengambil data".
     *
     * Middleware CatatGalatServer sudah menjamin setiap 5xx meninggalkan jejak,
     * tetapi ia hanya melihat TANGGAPAN. Yang memegang kelas exception, berkas,
     * baris, dan SQL-nya cuma blok catch itu sendiri — dan itulah yang membuat
     * selisih antara "ada 500 di halaman ini" dan "kolom X tidak ada".
     *
     * Pesan untuk pengguna sengaja tetap generik: detail teknis masuk log, bukan
     * ke layar.
     */
    public static function galatServer(
        \Throwable $e,
        string $message = 'Terjadi kesalahan pada server',
        int $status = 500,
        array $konteks = [],
    ) {
        $rincian = [
            'kelas' => $e::class,
            'pesan' => $e->getMessage(),
            'berkas' => $e->getFile().':'.$e->getLine(),
        ];

        /* SQL-nya ikut dicatat untuk galat basis data. Tanpa ini, "Invalid column
           name" tidak memberi tahu kolom mana pada kueri mana — dan itu satu-satunya
           hal yang ingin diketahui orang yang membaca log. Binding TIDAK ikut:
           isinya bisa memuat data pribadi. */
        if ($e instanceof \Illuminate\Database\QueryException) {
            $rincian['sql'] = $e->getSql();
        }

        \Illuminate\Support\Facades\Log::error(
            'Galat server: '.$message,
            $rincian + $konteks,
        );

        $request = request();

        if ($request instanceof \Illuminate\Http\Request) {
            // Middleware CatatGalatServer tidak perlu mencatat ulang: barisnya
            // sudah ada di atas, lengkap dengan exception-nya.
            $request->attributes->set(
                \App\Http\Middleware\CatatGalatServer::PENANDA_SUDAH_DICATAT,
                true,
            );
        }

        return self::error($message, $status);
    }

    public static function error(string $message = 'Terjadi kesalahan', int $status = 500)
    {
        return response()->json([
            'success' => false,
            'status' => $status,
            'message' => $message,
        ], $status);
    }

    /**
     * Response error dengan kode stabil.
     *
     * Method terpisah, bukan parameter tambahan pada error(): error() dipanggil
     * dari puluhan tempat, dan mengubah tanda tangannya menukar kebutuhan satu
     * fitur dengan risiko regresi di seluruh repo.
     *
     * `error_code` stabil dan tidak ikut berubah ketika wording `message`
     * diperbaiki — frontend bercabang pada kode, bukan pada kalimat.
     */
    public static function errorWithCode(
        string $message,
        string $errorCode,
        int $status = 400,
        array $errors = [],
        ?string $requestId = null,
    ) {
        return response()->json([
            'success' => false,
            'status' => $status,
            'message' => $message,
            'error_code' => $errorCode,
            'request_id' => $requestId ?? (string) \Illuminate\Support\Str::uuid(),
            'errors' => $errors,
        ], $status);
    }
}
