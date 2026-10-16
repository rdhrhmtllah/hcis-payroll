<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('DB_ENCRYPT', 'no'),
            'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),

            // LoginTimeout harus lewat key 'login_timeout': SqlServerConnector
            // memetakannya ke keyword DSN. Blok 'options' berisi
            // LoginTimeout/ConnectRetryCount/ConnectRetryInterval yang dulu ada
            // di sini adalah NO-OP — array options diteruskan sebagai atribut
            // PDO, bukan keyword DSN, sehingga tidak pernah dibaca driver.
            // 15s = fail-fast: saat SQL Server tak bisa menerima login, request
            // gagal cepat alih-alih menggantung dan menahan slot concurrency.
            'login_timeout' => (int) env('DB_LOGIN_TIMEOUT', 15),

            /*
             * Satu pernyataan SQL baku memakai DUA perjalanan jaringan:
             * sp_prepare lalu sp_execute. Terukur 9 September 2026 pada tautan
             * dev (RTT soket ~27 ms): 55,3 ms per kueri, dan 27,0 ms begitu
             * DIRECT_QUERY menyala — persis separuh. Angka itu bukan sifat
             * kueri: `SELECT 1` pun membayarnya, dan `execute` ulang atas
             * statement yang SUDAH disiapkan hanya 25,5 ms.
             *
             * Di produksi sedatacenter hemat per kuerinya kecil, tetapi yang
             * dipotong separuh adalah LAMA KONEKSI DITAHAN — dan itu akar
             * saturasi pool yang pernah menjatuhkan sistem, bukan sekadar
             * angka milidetik di layar.
             *
             * DEFAULT MATI. Ini mengubah cara SETIAP kueri sampai ke SQL
             * Server, jadi ia menyala hanya di lingkungan yang sudah
             * membuktikannya dengan suite penuh.
             */
            /*
             * Satu pernyataan SQL baku memakai DUA perjalanan jaringan:
             * sp_prepare lalu sp_execute. Terukur 9 September 2026 pada tautan
             * dev (RTT soket ~27 ms): 55,3 ms per kueri, dan 27,0 ms begitu
             * DIRECT_QUERY menyala — persis separuh. Angka itu bukan sifat
             * kueri: `SELECT 1` pun membayarnya.
             *
             * Di produksi sedatacenter hemat per kuerinya kecil, tetapi yang
             * dipotong separuh adalah LAMA KONEKSI DITAHAN — dan itu akar
             * saturasi pool yang pernah menjatuhkan sistem.
             *
             * DEFAULT MATI. Ini mengubah cara SETIAP kueri sampai ke SQL
             * Server, jadi ia menyala hanya di lingkungan yang sudah
             * membuktikannya dengan suite penuh.
             *
             * ── Yang SUDAH DICOBA dan DITOLAK ────────────────────────────────
             *
             * PDO::SQLSRV_ATTR_ENCODING => SQLSRV_ENCODING_SYSTEM. pdo_sqlsrv
             * mengirim setiap string sebagai `nvarchar(4000)` sementara 4.241
             * kolom di basis data ini varchar, sehingga SQL Server meng-CONVERT
             * KOLOMNYA dan indeks yang dirancang tepat pun hanya di-scan.
             * Terukur pada kueri notifikasi topbar: 432 logical reads menjadi 6
             * — tujuh puluh dua kali — dan 6.655 dari 8.995 rencana ter-cache
             * memuat CONVERT_IMPLICIT.
             *
             * Godaannya besar, tetapi sakelar itu MERUSAK: ia memperbaiki sisi
             * pengiriman dan merusak sisi pembacaan. Data kembali dalam codepage
             * sistem sementara Laravel memperlakukan semuanya UTF-8, dan
             * json_encode gagal dengan "Malformed UTF-8 characters" — 14 uji
             * merah, halaman menjawab 500. Jangan pasang ulang.
             *
             * Perbaikan yang benar untuk kelas ini bersifat per-kueri: CAST
             * parameternya ke tipe kolomnya pada kueri yang memang panas.
             */
            'options' => (env('DB_SQLSRV_DIRECT_QUERY', false) && defined('PDO::SQLSRV_ATTR_DIRECT_QUERY'))
                ? [PDO::SQLSRV_ATTR_DIRECT_QUERY => true]
                : [],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
