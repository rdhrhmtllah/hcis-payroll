<?php

namespace App\Services\SYSTEM\Integration;

use Illuminate\Support\Facades\URL;

/**
 * Service Registry untuk Outbound Integration Contracts / Requests.
 *
 * Mengelola spesifikasi kontrak integrasi di mana HCIS mengirimkan request ke
 * sistem pihak ketiga (Web Careers, HC Learn, Vendor Assessment, Banking Gateway).
 *
 * Kontrak ini disajikan ke:
 * 1. Internal HCIS Management Portal (daftar, filter, status, salin tautan)
 * 2. Public Contract Viewer (tautan mandiri tanpa gerbang Api_Public untuk vendor/developer eksternal)
 */
class OutboundContractService
{
    /**
     * Hasil definisi(), dibangun sekali per instance.
     *
     * Tanpa ini satu permintaan ke halaman daftar membangun literal besar di
     * bawah TIGA kali (all + targetSystems + categories), dan findBySlug
     * membangunnya lagi setiap pemanggilan. Untuk tiga kontrak keras biayanya
     * sepele; yang tidak sepele adalah polanya, karena ia berlipat begitu
     * daftar ini pindah ke database.
     *
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $memo = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->memo ??= $this->definisi();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definisi(): array
    {
        return [
            [
                'slug' => 'web-careers-candidate-status',
                'title' => 'Web Careers — Integrasi Sinkronisasi Rekrutmen Pelamar',
                'target_system' => 'Web Careers Portal',
                'category' => 'Webhook / Event Push',
                'version' => 'v1.3',
                'status' => 'ACTIVE',
                'last_updated' => '2026-09-04',
                'summary' => 'Daftar endpoint yang menjadi harapan wajib untuk disediakan oleh tim Web Careers guna menerima pembaruan status pelamar dan jadwal wawancara dari sistem rekrutmen HCIS.',
                'description' => 'HCIS membutuhkan Web Careers untuk menyediakan 2 endpoint webhook di bawah ini. Setiap kali tim HCIS memproses lamaran kandidat (screening lolos, jadwal interview, offering letter, atau status diterima/ditolak), HCIS akan secara otomatis mengirimkan request data ke endpoint Web Careers agar dashboard pelamar terupdate real-time.',
                'security' => [
                    'auth_type' => 'HMAC-SHA256 Signature',
                    'signature_header' => 'X-HC-Signature',
                    'timestamp_header' => 'X-HC-Timestamp',
                    'secret_env_name' => 'WEBHOOK_SHARED_SECRET',
                    'algorithm' => 'HMAC-SHA256',
                    'tolerance_seconds' => 300,
                    'raw_string_format' => 'Payload mentah JSON persis apa adanya (raw request body string).',
                ],
                'endpoints' => [
                    [
                        'id' => 'ep-candidate-status',
                        'name' => 'Webhook Pembaruan Status Pelamar',
                        'method' => 'POST',
                        'path' => '/api/webhooks/candidate-status',
                        'purpose' => 'Dipanggil setiap kali status tahapan rekrutmen pelamar berubah (Screening Lolos, Asesmen, Offering, Diterima, atau Ditolak).',
                        'trigger_event' => 'candidate.status_updated',
                        'sla_timeout' => 'Maksimal 3.0 detik',
                        'headers' => [
                            ['name' => 'Content-Type', 'type' => 'string', 'required' => true, 'example' => 'application/json', 'description' => 'Harapan wajib application/json'],
                            ['name' => 'X-HC-Timestamp', 'type' => 'string (unix timestamp)', 'required' => true, 'example' => '1725433200', 'description' => 'UTC timestamp dalam detik saat request dikirim oleh HCIS (toleransi deviasi 300 detik)'],
                            ['name' => 'X-HC-Signature', 'type' => 'string', 'required' => true, 'example' => 'sha256=d58c1a978f6580e6c64b5003c270389d38096c43499fa69b6e82845c48b26f58', 'description' => 'Tanda tangan digital HMAC-SHA256 dari raw payload menggunakan shared secret'],
                            ['name' => 'User-Agent', 'type' => 'string', 'required' => false, 'example' => 'HCIS-Outbound-Dispatcher/1.0', 'description' => 'Identitas klien HTTP dari HCIS'],
                        ],
                        'payload_fields' => [
                            ['field' => 'event', 'type' => 'string', 'required' => true, 'description' => 'Nama event tetap: "candidate.status_updated"'],
                            ['field' => 'event_id', 'type' => 'string (UUID)', 'required' => true, 'description' => 'ID unik event untuk idempotensi di sisi Web Careers'],
                            ['field' => 'timestamp', 'type' => 'string (ISO 8601)', 'required' => true, 'description' => 'Waktu event terjadi (format UTC)'],
                            ['field' => 'data.candidate_id', 'type' => 'string', 'required' => true, 'description' => 'ID unik kandidat di HCIS'],
                            ['field' => 'data.application_id', 'type' => 'string', 'required' => true, 'description' => 'Nomor berkas lamaran di Web Careers'],
                            ['field' => 'data.vacancy_id', 'type' => 'string', 'required' => true, 'description' => 'Kode lowongan pekerjaan'],
                            ['field' => 'data.status', 'type' => 'string (enum)', 'required' => true, 'description' => 'Status baru: SCREENING_PASSED | INTERVIEW_SCHEDULED | PSYCHOTEST_INVITED | OFFERING | ACCEPTED | REJECTED'],
                            ['field' => 'data.notes', 'type' => 'string', 'required' => false, 'description' => 'Catatan / pesan konfirmasi untuk kandidat'],
                        ],
                        'sample_payload' => [
                            'event' => 'candidate.status_updated',
                            'event_id' => 'evt_9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
                            'timestamp' => '2026-09-04T08:30:00Z',
                            'data' => [
                                'candidate_id' => 'CAN-2026-08912',
                                'application_id' => 'APP-2026-00412',
                                'vacancy_id' => 'VAC-ENG-2026-04',
                                'vacancy_title' => 'Senior Backend Engineer',
                                'status' => 'SCREENING_PASSED',
                                'previous_status' => 'APPLIED',
                                'notes' => 'Selamat, berkas lamaran Anda telah lolos tahap administrasi awal.',
                            ],
                        ],
                        'expected_response' => [
                            'success' => [
                                'http_status' => 200,
                                'description' => 'Harapan wajib mengembalikan HTTP 200 jika data berhasil diterima dan diproses oleh Web Careers.',
                                'fields' => [
                                    ['field' => 'status', 'type' => 'string', 'required' => true, 'description' => 'Harus berisi "success"'],
                                    ['field' => 'code', 'type' => 'integer', 'required' => true, 'description' => 'Harus angka 200'],
                                    ['field' => 'message', 'type' => 'string', 'required' => true, 'description' => 'Pesan konfirmasi pemrosesan'],
                                    ['field' => 'processed_at', 'type' => 'string (ISO 8601)', 'required' => false, 'description' => 'Waktu selesai pemrosesan di Web Careers'],
                                ],
                                'sample' => [
                                    'status' => 'success',
                                    'code' => 200,
                                    'message' => 'Candidate status successfully updated',
                                    'processed_at' => '2026-09-04T08:30:01Z',
                                ],
                            ],
                            'error' => [
                                'http_status' => 422,
                                'description' => 'Kembalikan HTTP 422 jika data application_id tidak ditemukan atau validasi gagal.',
                                'sample' => [
                                    'status' => 'error',
                                    'code' => 422,
                                    'message' => 'Unprocessable entity: application_id not found',
                                    'errors' => [
                                        'application_id' => ['Nomor lamaran APP-2026-00412 tidak terdaftar'],
                                    ],
                                ],
                            ],
                        ],
                        'retry_policy' => [
                            'max_attempts' => 5,
                            'backoff' => 'Exponential backoff (30s, 2m, 10m, 30m, 1h)',
                            'retryable_codes' => 'HTTP 500, 502, 503, 504, Connection Timeout, Connection Refused',
                            'non_retryable_codes' => 'HTTP 400 (Bad Request), 401 (Unauthorized), 404 (Not Found), 422 (Unprocessable Entity)',
                        ],
                    ],
                    [
                        'id' => 'ep-interview-schedule',
                        'name' => 'Webhook Notifikasi Jadwal Wawancara Pelamar',
                        'method' => 'POST',
                        'path' => '/api/webhooks/interview-schedule',
                        'purpose' => 'Dipanggil saat HCIS membuat atau mengubah jadwal wawancara pelamar (Online Google Meet atau Onsite Kantor).',
                        'trigger_event' => 'interview.scheduled',
                        'sla_timeout' => 'Maksimal 3.0 detik',
                        'headers' => [
                            ['name' => 'Content-Type', 'type' => 'string', 'required' => true, 'example' => 'application/json', 'description' => 'Harapan wajib application/json'],
                            ['name' => 'X-HC-Timestamp', 'type' => 'string', 'required' => true, 'example' => '1725433200', 'description' => 'UTC timestamp detik'],
                            ['name' => 'X-HC-Signature', 'type' => 'string', 'required' => true, 'example' => 'sha256=fa716b901a...', 'description' => 'HMAC-SHA256 signature verifier'],
                        ],
                        'payload_fields' => [
                            ['field' => 'event', 'type' => 'string', 'required' => true, 'description' => 'Nama event: "interview.scheduled"'],
                            ['field' => 'event_id', 'type' => 'string (UUID)', 'required' => true, 'description' => 'ID unik event'],
                            ['field' => 'data.application_id', 'type' => 'string', 'required' => true, 'description' => 'Nomor berkas lamaran di Web Careers'],
                            ['field' => 'data.interview_type', 'type' => 'string', 'required' => true, 'description' => 'ONLINE | ONSITE'],
                            ['field' => 'data.scheduled_at', 'type' => 'string (ISO 8601)', 'required' => true, 'description' => 'Waktu interview (format UTC/ISO)'],
                            ['field' => 'data.meeting_url', 'type' => 'string|null', 'required' => false, 'description' => 'URL Google Meet / Zoom jika ONLINE'],
                            ['field' => 'data.location_address', 'type' => 'string|null', 'required' => false, 'description' => 'Alamat kantor jika ONSITE'],
                        ],
                        'sample_payload' => [
                            'event' => 'interview.scheduled',
                            'event_id' => 'evt_3c9217b1-2184-4899-b1d2-a72019b88231',
                            'timestamp' => '2026-09-04T08:45:00Z',
                            'data' => [
                                'candidate_id' => 'CAN-2026-08912',
                                'application_id' => 'APP-2026-00412',
                                'interview_type' => 'ONLINE',
                                'scheduled_at' => '2026-09-10T03:00:00Z',
                                'meeting_url' => 'https://meet.google.com/xyz-hcis-interview',
                                'interviewer_name' => 'Lead Software Architect',
                                'instructions' => 'Harap bergabung ke link meeting 10 menit sebelum jadwal dimulai.',
                            ],
                        ],
                        'expected_response' => [
                            'success' => [
                                'http_status' => 200,
                                'description' => 'Harapan wajib mengembalikan HTTP 200 jika jadwal berhasil dicatat di akun pelamar.',
                                'fields' => [
                                    ['field' => 'status', 'type' => 'string', 'required' => true, 'description' => 'Harus "success"'],
                                    ['field' => 'code', 'type' => 'integer', 'required' => true, 'description' => '200'],
                                    ['field' => 'message', 'type' => 'string', 'required' => true, 'description' => 'Pesan konfirmasi'],
                                ],
                                'sample' => [
                                    'status' => 'success',
                                    'code' => 200,
                                    'message' => 'Interview schedule received and email notification dispatched to candidate.',
                                ],
                            ],
                            'error' => [
                                'http_status' => 400,
                                'description' => 'Kembalikan HTTP 400 jika format data atau waktu jadwal tidak valid.',
                                'sample' => [
                                    'status' => 'error',
                                    'code' => 400,
                                    'message' => 'Invalid scheduled_at format',
                                ],
                            ],
                        ],
                        'retry_policy' => [
                            'max_attempts' => 5,
                            'backoff' => 'Exponential backoff (30s, 2m, 10m, 30m, 1h)',
                            'retryable_codes' => 'HTTP 500, 502, 503, 504, Timeout',
                            'non_retryable_codes' => 'HTTP 400, 401, 422',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'assessment-vendor-invite',
                'title' => 'Online Assessment Vendor — Pembuatan Token & Undangan Tes',
                'target_system' => 'Vendor Psikotes & Assessment Online',
                'category' => 'API Integration Request',
                'version' => 'v1.1',
                'status' => 'ACTIVE',
                'last_updated' => '2026-09-01',
                'summary' => 'Daftar endpoint yang menjadi harapan wajib untuk disediakan oleh Vendor Assessment Online guna pembuatan token tes psikotes dan pengiriman tautan asesmen otomatis bagi kandidat HCIS.',
                'description' => 'HCIS akan mengirimkan data kandidat yang telah lolos tahap interview awal ke server Vendor Assessment. Server vendor diharapkan menyediakan endpoint untuk membuatkan token tes, menentukan expired link, dan mengembalikan URL tes pengerjaan agar tercatat di log HCIS.',
                'security' => [
                    'auth_type' => 'HMAC-SHA256 Signature',
                    'signature_header' => 'X-HC-Signature',
                    'timestamp_header' => 'X-HC-Timestamp',
                    'secret_env_name' => 'ASSESSMENT_VENDOR_SECRET',
                    'algorithm' => 'HMAC-SHA256',
                    'tolerance_seconds' => 300,
                    'raw_string_format' => 'Raw JSON string.',
                ],
                'endpoints' => [
                    [
                        'id' => 'ep-assessment-invite',
                        'name' => 'API Pembuatan Token & Sesi Asesmen Kandidat',
                        'method' => 'POST',
                        'path' => '/api/v1/assessments/invitations',
                        'purpose' => 'Dipanggil saat kandidat dijadwalkan untuk mengikuti tes psikotes/kompetensi online.',
                        'trigger_event' => 'assessment.invite_requested',
                        'sla_timeout' => 'Maksimal 4.0 detik',
                        'headers' => [
                            ['name' => 'Content-Type', 'type' => 'string', 'required' => true, 'example' => 'application/json', 'description' => 'Harapan wajib application/json'],
                            ['name' => 'X-HC-Timestamp', 'type' => 'string', 'required' => true, 'example' => '1725433200', 'description' => 'Timestamp waktu request dalam detik UTC'],
                            ['name' => 'X-HC-Signature', 'type' => 'string', 'required' => true, 'example' => 'sha256=a89f81bc26c04f911e3b6a2...', 'description' => 'HMAC-SHA256 signature verifier'],
                        ],
                        'payload_fields' => [
                            ['field' => 'event', 'type' => 'string', 'required' => true, 'description' => '"assessment.invite_requested"'],
                            ['field' => 'event_id', 'type' => 'string (UUID)', 'required' => true, 'description' => 'ID unik event'],
                            ['field' => 'data.batch_id', 'type' => 'string', 'required' => true, 'description' => 'Nomor batch asesmen rekrutmen'],
                            ['field' => 'data.test_package_code', 'type' => 'string', 'required' => true, 'description' => 'Kode modul tes (e.g. DISC_CFIT_PAULI | LEADERSHIP_BATTERY)'],
                            ['field' => 'data.candidate.candidate_id', 'type' => 'string', 'required' => true, 'description' => 'ID unik kandidat di HCIS'],
                            ['field' => 'data.candidate.full_name', 'type' => 'string', 'required' => true, 'description' => 'Nama lengkap kandidat'],
                            ['field' => 'data.candidate.email', 'type' => 'string', 'required' => true, 'description' => 'Email aktif kandidat'],
                            ['field' => 'data.valid_until', 'type' => 'string (ISO 8601)', 'required' => true, 'description' => 'Batas waktu pengerjaan tes'],
                            ['field' => 'data.callback_url', 'type' => 'string', 'required' => true, 'description' => 'URL callback HCIS saat tes selesai dikerjakan'],
                        ],
                        'sample_payload' => [
                            'event' => 'assessment.invite_requested',
                            'event_id' => 'evt_7e20b355-6671-4712-9c12-ef5a9bb31980',
                            'timestamp' => '2026-09-04T09:00:00Z',
                            'data' => [
                                'batch_id' => 'BATCH-202609-001',
                                'test_package_code' => 'DISC_CFIT_PAULI',
                                'candidate' => [
                                    'candidate_id' => 'CAN-2026-08912',
                                    'full_name' => 'Budi Pratama S.Kom',
                                    'email' => 'budi.pratama@example.com',
                                    'phone_number' => '081234567890',
                                    'position_applied' => 'Senior Backend Engineer',
                                ],
                                'valid_until' => '2026-09-11T16:59:59Z',
                                'callback_url' => 'https://hcis.company.com/api/v1/hris/third-party/assessment/complete',
                            ],
                        ],
                        'expected_response' => [
                            'success' => [
                                'http_status' => 201,
                                'description' => 'Harapan wajib mengembalikan HTTP 201 dengan link akses tes kandidat yang telah digenerate.',
                                'fields' => [
                                    ['field' => 'status', 'type' => 'string', 'required' => true, 'description' => 'Harus "success"'],
                                    ['field' => 'invitation_id', 'type' => 'string', 'required' => true, 'description' => 'ID sesi invitation dari vendor'],
                                    ['field' => 'test_access_url', 'type' => 'string', 'required' => true, 'description' => 'URL unik pengerjaan tes bagi kandidat'],
                                    ['field' => 'expires_at', 'type' => 'string (ISO 8601)', 'required' => true, 'description' => 'Waktu kadaluarsa link tes'],
                                ],
                                'sample' => [
                                    'status' => 'success',
                                    'invitation_id' => 'INV-VND-894102',
                                    'test_access_url' => 'https://assessment-platform.vendor.com/test/session?token=tk_89a7fbc6102e',
                                    'expires_at' => '2026-09-11T16:59:59Z',
                                ],
                            ],
                            'error' => [
                                'http_status' => 422,
                                'description' => 'Kembalikan HTTP 422 jika test_package_code tidak dikenali oleh vendor.',
                                'sample' => [
                                    'status' => 'error',
                                    'code' => 422,
                                    'message' => 'Invalid test_package_code',
                                ],
                            ],
                        ],
                        'retry_policy' => [
                            'max_attempts' => 3,
                            'backoff' => 'Exponential backoff (1m, 5m, 15m)',
                            'retryable_codes' => 'HTTP 500, 502, 503, 504, Timeout',
                            'non_retryable_codes' => 'HTTP 400, 401, 403, 422',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'hc-learn-enrollment-sync',
                'title' => 'HC Learn — Sinkronisasi Pendaftaran Pelatihan Karyawan',
                'target_system' => 'HC Learn LMS',
                'category' => 'Outbound Course Sync',
                'version' => 'v2.0',
                'status' => 'ACTIVE',
                'last_updated' => '2026-08-28',
                'summary' => 'Daftar endpoint yang menjadi harapan wajib untuk disediakan oleh tim HC Learn LMS guna menerima pendaftaran modul pelatihan dan sertifikasi karyawan secara otomatis dari HCIS.',
                'description' => 'Setiap kali karyawan baru menyelesaikan masa orientasi atau seorang karyawan dipromosikan ke jabatan baru, HCIS menentukan matriks kompetensi dan mem-push penugasan kursus ke sistem HC Learn LMS.',
                'security' => [
                    'auth_type' => 'HMAC-SHA256 Signature',
                    'signature_header' => 'X-HC-Signature',
                    'timestamp_header' => 'X-HC-Timestamp',
                    'secret_env_name' => 'HC_LEARN_SHARED_SECRET',
                    'algorithm' => 'HMAC-SHA256',
                    'tolerance_seconds' => 300,
                    'raw_string_format' => 'Raw JSON body.',
                ],
                'endpoints' => [
                    [
                        'id' => 'ep-lms-enrollments',
                        'name' => 'API Push Penugasan Kursus Karyawan',
                        'method' => 'POST',
                        'path' => '/api/v1/lms/sync/enrollments',
                        'purpose' => 'Dipanggil saat ada penugasan kursus pelatihan atau sertifikasi baru untuk NIK karyawan tertentu.',
                        'trigger_event' => 'training.employee_enrolled',
                        'sla_timeout' => 'Maksimal 3.0 detik',
                        'headers' => [
                            ['name' => 'Content-Type', 'type' => 'string', 'required' => true, 'example' => 'application/json', 'description' => 'Harapan wajib application/json'],
                            ['name' => 'X-HC-Timestamp', 'type' => 'string', 'required' => true, 'example' => '1725433200', 'description' => 'Timestamp waktu request'],
                            ['name' => 'X-HC-Signature', 'type' => 'string', 'required' => true, 'example' => 'sha256=fa716b901a...', 'description' => 'HMAC-SHA256 signature verifier'],
                        ],
                        'payload_fields' => [
                            ['field' => 'event', 'type' => 'string', 'required' => true, 'description' => '"training.employee_enrolled"'],
                            ['field' => 'event_id', 'type' => 'string (UUID)', 'required' => true, 'description' => 'ID unik event'],
                            ['field' => 'data.nik', 'type' => 'string', 'required' => true, 'description' => 'Nomor Induk Karyawan di HCIS'],
                            ['field' => 'data.employee_name', 'type' => 'string', 'required' => true, 'description' => 'Nama lengkap karyawan'],
                            ['field' => 'data.department', 'type' => 'string', 'required' => true, 'description' => 'Nama divisi / departemen'],
                            ['field' => 'data.courses', 'type' => 'array', 'required' => true, 'description' => 'Daftar kode kursus yang didaftarkan'],
                        ],
                        'sample_payload' => [
                            'event' => 'training.employee_enrolled',
                            'event_id' => 'evt_18fc9a10-2b99-4a00-9831-7bfa00123991',
                            'timestamp' => '2026-09-04T07:15:00Z',
                            'data' => [
                                'nik' => '2024010889',
                                'employee_name' => 'Ahmad Fauzi',
                                'email' => 'ahmad.fauzi@example.com',
                                'department' => 'Information Technology',
                                'job_level' => 'Supervisor',
                                'courses' => [
                                    [
                                        'course_code' => 'SEC-AWARE-101',
                                        'course_title' => 'Information Security & Data Protection Standard',
                                        'due_date' => '2026-09-30',
                                        'mandatory' => true,
                                    ],
                                    [
                                        'course_code' => 'LEAD-102',
                                        'course_title' => 'Effective Supervisory Leadership Fundamentals',
                                        'due_date' => '2026-10-15',
                                        'mandatory' => true,
                                    ],
                                ],
                            ],
                        ],
                        'expected_response' => [
                            'success' => [
                                'http_status' => 200,
                                'description' => 'Harapan wajib mengembalikan HTTP 200 jika kursus berhasil di-assign ke user LMS.',
                                'fields' => [
                                    ['field' => 'status', 'type' => 'string', 'required' => true, 'description' => '"success"'],
                                    ['field' => 'enrolled_count', 'type' => 'integer', 'required' => true, 'description' => 'Jumlah kursus yang berhasil didaftarkan'],
                                    ['field' => 'message', 'type' => 'string', 'required' => true, 'description' => 'Pesan konfirmasi'],
                                ],
                                'sample' => [
                                    'status' => 'success',
                                    'enrolled_count' => 2,
                                    'message' => 'Successfully enrolled employee into 2 courses.',
                                ],
                            ],
                            'error' => [
                                'http_status' => 404,
                                'description' => 'Kembalikan HTTP 404 jika NIK user belum terdaftar di database LMS.',
                                'sample' => [
                                    'status' => 'error',
                                    'code' => 404,
                                    'message' => 'User NIK 2024010889 not found in LMS directory',
                                ],
                            ],
                        ],
                        'retry_policy' => [
                            'max_attempts' => 5,
                            'backoff' => 'Exponential backoff (30s, 2m, 10m, 30m, 1h)',
                            'retryable_codes' => 'HTTP 500, 502, 503, 504, Timeout',
                            'non_retryable_codes' => 'HTTP 400, 401, 403, 404, 422',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'hc-learn-performance-appraisal',
                'title' => 'HC Learn — Performance Appraisal 360°',
                'target_system' => 'HC Learn LMS',
                'category' => 'Outbound Assessment Sync',
                'version' => 'pa-v1',
                'status' => 'DRAFT',
                'last_updated' => '2026-09-05',
                'summary' => 'Endpoint yang HCIS butuhkan dari HC Learn agar penilaian 360° bisa dikerjakan di sana: satu karyawan memegang beberapa token pada paket yang sama, token bisa dicabut sebelum diisi, dan susunan kompetensi satu paket bisa dibaca sebelum siklus berjalan.',
                'description' => 'Penilaian 360 berbeda dari Training dan SOP Kuis pada satu hal yang mengubah bentuk kontraknya: yang mengisi form bukan yang dinilai. Seorang manajer dengan lima bawahan menerima lima form terpisah dari paket soal yang sama, sehingga satu karyawan harus bisa memegang beberapa token aktif sekaligus. Alur di bawah menunjukkan bagian mana yang dikerjakan HC Learn dan bagian mana yang dikerjakan HCIS. Halaman ini memuat DUA arah: enam endpoint yang kami minta Anda sediakan, lalu satu callback milik HCIS yang Anda panggil balik — lengkap dengan bentuk kiriman yang kami harapkan, supaya tidak ada bagian yang harus ditebak dari dokumen lain.',

                /*
                 | ALUR, sebagai DATA — bukan sebagai paragraf.
                 |
                 | Penjelasan ini semula satu paragraf panjang di `description`. Pembacanya
                 | adalah developer HC Learn yang perlu tahu SATU hal lebih dulu: bagian mana
                 | yang harus ia kerjakan. Paragraf memaksa ia membaca seluruhnya untuk
                 | menemukannya, dan urutan kejadiannya tetap harus ia rangkai sendiri di
                 | kepala.
                 |
                 | Ditulis sebagai data, bukan HTML, karena kontrak ini juga terbit sebagai
                 | JSON untuk otomasi klien — diagram yang hanya hidup di template akan hilang
                 | di sana, dan yang membaca JSON justru mesin milik vendor.
                 |
                 | `aktor` menentukan warnanya di layar. `endpoint` menunjuk id endpoint di
                 | daftar bawah bila langkah itu memang salah satunya; null berarti langkah itu
                 | tidak menuntut pekerjaan HC Learn.
                 */
                'flow' => [
                    [
                        'aktor' => 'HCIS',
                        'judul' => 'Admin memilih paket soal — dari daftar yang HC Learn sediakan',
                        'detail' => 'HCIS membaca daftar paket lewat endpoint options yang SUDAH ADA dan sudah dipakai sekarang. Tidak ada yang perlu diubah pada langkah ini.',
                        'endpoint' => 'ep-pa-daftar-paket',
                    ],
                    [
                        'aktor' => 'HC Learn',
                        'judul' => 'HC Learn memberi tahu isi paket yang dipilih',
                        'detail' => 'Soal mana mengukur kompetensi apa, berapa banyak, dan skalanya. Inilah satu-satunya bagian dari pemilihan paket yang belum ada — dan cara termudah mewujudkannya adalah menyisipkan bagian Detail ke balasan daftar di langkah sebelumnya, sehingga tidak perlu endpoint baru sama sekali.',
                        'endpoint' => 'ep-pa-susunan-paket',
                    ],
                    [
                        'aktor' => 'HCIS',
                        'judul' => 'Admin mengunci siklus penilaian',
                        'detail' => 'HCIS menetapkan siapa menilai siapa: atasan, rekan, bawahan, dan diri sendiri. Satu baris penugasan lahir untuk tiap pasangan penilai-peserta.',
                        'endpoint' => null,
                    ],
                    [
                        'aktor' => 'HCIS',
                        'judul' => 'HCIS mengirim daftar penugasan',
                        'detail' => 'Satu baris = satu form. Kode_Karyawan yang sama boleh muncul berkali-kali dalam satu kiriman — itulah manajer dengan lima bawahan.',
                        'endpoint' => 'ep-pa-penjadwalan',
                    ],
                    [
                        'aktor' => 'HC Learn',
                        'judul' => 'HC Learn membuat satu token per penugasan',
                        'detail' => 'Token dikembalikan bersama Id_Integrasi asalnya. HC Learn tidak perlu memahami siapa menilai siapa — token itu sudah mewakili satu baris integrasi, dan HCIS yang menyimpan artinya.',
                        'endpoint' => 'ep-pa-penjadwalan',
                    ],
                    [
                        'aktor' => 'HCIS',
                        'judul' => 'Penilai menekan "Mulai Menilai" — dan HCIS mencatatnya sendiri',
                        'detail' => 'Penilai membuka lembar penilaiannya dari halaman Penilaian Saya di HCIS, sehingga saat itu terjadi di sisi kami dan tidak perlu dilaporkan balik. TIDAK ADA yang kami minta dari HC Learn pada langkah ini. (Sempat kami rancang sebagai callback tersendiri; kami cabut setelah sadar tombolnya memang milik kami. Meminta pihak lain mengerjakan sesuatu yang bisa kami kerjakan sendiri hanya memperbesar daftar permintaan, dan daftar yang lebih panjang membuat bagian yang benar-benar kami butuhkan ikut tertunda.)',
                        'endpoint' => null,
                    ],
                    [
                        'aktor' => 'HC Learn',
                        'judul' => 'Penilai mengisi dan mengirim',
                        'detail' => 'Hasil dikirim PER BUTIR SOAL, bukan satu angka akhir. HCIS menghitung nilai per kompetensi, sehingga rincian tiap butir harus sampai. Endpoint ini MILIK HCIS dan ALAMATNYA KHUSUS PA — bukan alamat callback Training/SOP, supaya hasil penilaian tidak perlu ditebak asalnya dari token. Mekanismenya sama persis, jadi bagi Anda ini URL berbeda dengan kode yang sama. Bentuk kirimannya ada di Callback A di bawah.',
                        'endpoint' => 'cb-pa-hasil',
                    ],
                    [
                        'aktor' => 'HC Learn',
                        'judul' => 'Bila nilainya masih menunggu verifikasi admin Anda',
                        'detail' => 'Kirim callback yang sama dengan Status_Skor MENUNGGU_VERIFIKASI, tanpa Detail bila angkanya memang belum sah. HCIS berhenti mengingatkan penilai itu — ia sudah mengerjakan bagiannya — tetapi tidak menandai penugasannya selesai dan tidak menghitung apa pun. Tanpa kiriman ini, penilai yang sudah selesai tetap menerima pengingat sampai verifikasi Anda kelar, dan peserta yang sebenarnya sudah lengkap terlihat menggantung.',
                        'endpoint' => 'cb-pa-hasil',
                    ],
                    [
                        'aktor' => 'HCIS',
                        'judul' => 'HCIS memetakan, menghitung, menerbitkan',
                        'detail' => 'Skor tiap butir dipetakan ke kompetensinya lewat Kode_Kompetensi, dinormalkan menurut Skor_Maksimal butir itu, lalu digabung menjadi nilai akhir yang diverifikasi admin sebelum terbit.',
                        'endpoint' => null,
                    ],
                    [
                        'aktor' => 'HCIS',
                        'judul' => 'Bila jendela pengisian diperpanjang',
                        'detail' => 'Tenggat siklus memang bergeser — peserta menyusul, atasan cuti. Bila hanya HCIS yang tahu, penilai membuka tautannya dan menemukan ujian yang sudah tertutup padahal di layar HCIS tugasnya masih terbuka. Tokennya dipertahankan; yang berubah cuma waktunya.',
                        'endpoint' => 'ep-pa-perpanjang',
                    ],
                    [
                        'aktor' => 'HCIS',
                        'judul' => 'Bila formulirnya sendiri yang keliru',
                        'detail' => 'Paket soal siklus diganti di tengah jalan, atau tautannya rusak. Penugasannya TETAP hidup dan tetap harus diisi — yang diganti pintunya, bukan kewajibannya. Token lama mati, token baru terbit, penugasan yang sama tetap utuh riwayatnya.',
                        'endpoint' => 'ep-pa-terbitkan-ulang',
                    ],
                    [
                        'aktor' => 'HCIS',
                        'judul' => 'Bila penugasan dibatalkan di tengah jalan',
                        'detail' => 'Peserta dikecualikan atau penilainya diganti. HCIS meminta tokennya dicabut supaya penilai tidak terlanjur mengisi sampai selesai lalu kirimannya ditolak.',
                        'endpoint' => 'ep-pa-cabut-token',
                    ],
                ],
                'security' => [
                    'auth_type' => 'HMAC-SHA512 Signature (persis mekanisme Training & SOP Kuis yang berjalan)',
                    'signature_header' => 'X-HC-Signature',
                    'timestamp_header' => 'X-HC-Timestamp',
                    'secret_env_name' => 'HC_LEARN_SHARED_SECRET',
                    'algorithm' => 'HMAC-SHA512',
                    'tolerance_seconds' => 60,
                    'raw_string_format' => 'BUKAN tanda tangan atas raw body. Yang ditandatangani adalah string kanonik tujuh bagian yang disambung dengan baris baru, berurutan: METHOD, path, query string, X-HC-Timestamp, X-HC-Nonce, X-HC-Body-Hash, X-HC-Public. X-HC-Body-Hash sendiri adalah SHA-256 heksadesimal atas body JSON (string kosong bila tanpa body). Timestamp berformat "Y-m-d H:i:s", bukan epoch, dan selisihnya maksimal 60 detik. Nonce sekali pakai — terbitkan yang baru pada setiap percobaan ulang. Tidak ada satu pun yang kami minta diubah di sini: seluruhnya sama dengan yang Anda layani untuk Training dan SOP Kuis hari ini.',
                ],
                'endpoints' => [
                    [
                        'id' => 'ep-pa-daftar-paket',
                        'name' => 'Langkah 1 — Daftar paket soal yang tersedia',
                        'method' => 'GET',
                        'path' => '/api/v1/penjadwalan/training/options',
                        'purpose' => 'SUDAH ADA DAN SUDAH KAMI PAKAI — kami tidak meminta endpoint baru untuk langkah ini. Admin HCIS membukanya saat memilih paket soal untuk satu siklus, dan HCIS menampilkan daftarnya apa adanya. Langkah ini disebutkan supaya urutan pemakaiannya terbaca utuh: memilih paket adalah DUA langkah, dan yang kedua (susunan kompetensinya) itulah yang belum ada.',
                        'trigger_event' => 'Admin HCIS membuka layar pemilihan paket soal siklus',
                        'headers' => [
                            ['name' => 'X-HC-Public', 'type' => 'string', 'required' => true, 'example' => 'hcis-prod-01', 'description' => 'Kunci publik HCIS. Sama dengan yang dipakai Training dan SOP hari ini'],
                            ['name' => 'X-HC-Timestamp', 'type' => 'string', 'required' => true, 'example' => '2026-09-05 09:15:00', 'description' => 'Format "Y-m-d H:i:s", bukan epoch. Selisih maksimal 60 detik'],
                            ['name' => 'X-HC-Nonce', 'type' => 'string', 'required' => true, 'example' => 'K7pQ2mZx9RvL4nB1cT6sW8yE3aD5gH0j', 'description' => 'Acak 32 karakter, sekali pakai'],
                            ['name' => 'X-HC-Body-Hash', 'type' => 'string', 'required' => true, 'example' => 'e3b0c44298fc1c149afbf4c8996fb924...', 'description' => 'SHA-256 heksadesimal atas body. Untuk GET tanpa body: hash dari string kosong'],
                            ['name' => 'X-HC-Signature', 'type' => 'string', 'required' => true, 'example' => 'a71b3f9c...  (hmac-sha512 hex)', 'description' => 'HMAC-SHA512 atas string kanonik tujuh bagian — lihat bagian keamanan di bawah'],
                            ['name' => 'X-App-ID', 'type' => 'string', 'required' => true, 'example' => 'HCIS-PA', 'description' => 'Identitas aplikasi pemanggil, seperti pada Training dan SOP'],
                            ['name' => 'X-Kode-Karyawan', 'type' => 'string', 'required' => true, 'example' => 'DEMO-001', 'description' => 'Identitas admin yang sedang membuka layar ini. HC Learn menolak 401/403 bila akunnya tak dikenal di sisi Anda'],
                        ],
                        'payload_fields' => [
                            /* Penampung "tidak ada parameter" DITULIS UTUH, bukan tanda hubung.
                               Tanda hubung tunggal pernah membuat penjaga isi menyala: sebagian
                               karyawan menyimpan Email sebagai "-", dan pencocokan utuh
                               menganggapnya kebocoran data. Penampung yang bermakna lebih jelas
                               dibaca vendor sekaligus tidak menabrak apa pun. */
                            ['field' => 'tanpa parameter', 'type' => 'tanpa parameter', 'required' => false, 'description' => 'HCIS memanggilnya tanpa query string. Seluruh paket yang boleh dipakai dikembalikan sekaligus'],
                        ],
                        'sample_payload' => [
                            'catatan' => 'GET tanpa body dan tanpa query parameter.',
                        ],
                        'expected_response' => [
                            'success' => [
                                'http_status' => 200,
                                'description' => 'Bentuk yang sudah berjalan sekarang. HCIS membaca result[] atau data[], dan sudah menanganinya apa adanya — tidak ada perubahan yang kami minta di sini.',
                                'fields' => [
                                    ['field' => 'result[].Id_Master_Ujian', 'type' => 'string', 'required' => true, 'description' => 'Pengenal paket. Dipakai HCIS di Langkah 2 dan saat penjadwalan'],
                                    ['field' => 'result[].Nama_Ujian', 'type' => 'string', 'required' => true, 'description' => 'Nama paket untuk ditampilkan kepada admin'],
                                    ['field' => 'result[].Kategori', 'type' => 'string', 'required' => false, 'description' => 'Pengelompokan, bila ada'],
                                ],
                                'sample' => [
                                    'result' => [
                                        ['Id_Master_Ujian' => 'PA-STAFF-2027', 'Nama_Ujian' => 'Penilaian 360 — Staff', 'Kategori' => 'Performance Appraisal'],
                                        ['Id_Master_Ujian' => 'PA-OFFICER-2027', 'Nama_Ujian' => 'Penilaian 360 — Officer', 'Kategori' => 'Performance Appraisal'],
                                    ],
                                ],
                            ],
                            'error' => [
                                'http_status' => 401,
                                'description' => 'Kembalikan 401 bila tanda tangan tidak sah.',
                                'sample' => [
                                    'status' => 'error',
                                    'code' => 401,
                                    'message' => 'Signature tidak valid',
                                ],
                            ],
                        ],
                        'retry_policy' => [
                            'max_attempts' => 2,
                            'backoff' => 'Linear (10s, 30s)',
                            'retryable_codes' => 'HTTP 500, 502, 503, 504, Timeout',
                            'non_retryable_codes' => 'HTTP 400, 401, 403, 404',
                        ],
                    ],
                    [
                        'id' => 'ep-pa-susunan-paket',
                        'name' => 'Langkah 2 — Susunan kompetensi paket yang dipilih',
                        'method' => 'GET',
                        'path' => '/api/v1/penjadwalan/training/options?Id_Master_Ujian={kode}',
                        'purpose' => 'INILAH YANG BELUM ADA. Sesudah admin memilih satu paket dari Langkah 1, HCIS perlu tahu isinya: soal mana mengukur kompetensi apa, berapa banyak, dan skalanya — supaya hasil bisa dipetakan SEBELUM siklus berjalan, bukan setelah hasil pertama masuk. PALING MUDAH BAGI SEMUA PIHAK: sisipkan saja bagian Detail ke dalam balasan Langkah 1, sehingga tidak ada endpoint baru sama sekali dan HCIS cukup satu panggilan. HCIS sudah menyiapkan keduanya — bila Detail ada di balasan daftar, ia dipakai langsung; bila tidak, paketnya tetap ditampilkan tetapi ditandai belum bisa dipakai, karena mengimpor paket tanpa butir berarti menandai satu level bersoal padahal formulirnya kosong.',
                        'trigger_event' => 'Segera sesudah admin memilih satu paket pada Langkah 1',
                        'headers' => [
                            ['name' => 'X-HC-Public', 'type' => 'string', 'required' => true, 'example' => 'hcis-prod-01', 'description' => 'Kunci publik HCIS. Sama dengan yang dipakai Training dan SOP hari ini'],
                            ['name' => 'X-HC-Timestamp', 'type' => 'string', 'required' => true, 'example' => '2026-09-05 09:15:00', 'description' => 'Format "Y-m-d H:i:s", bukan epoch. Selisih maksimal 60 detik'],
                            ['name' => 'X-HC-Nonce', 'type' => 'string', 'required' => true, 'example' => 'K7pQ2mZx9RvL4nB1cT6sW8yE3aD5gH0j', 'description' => 'Acak 32 karakter, sekali pakai'],
                            ['name' => 'X-HC-Body-Hash', 'type' => 'string', 'required' => true, 'example' => 'e3b0c44298fc1c149afbf4c8996fb924...', 'description' => 'SHA-256 heksadesimal atas body. Untuk GET tanpa body: hash dari string kosong'],
                            ['name' => 'X-HC-Signature', 'type' => 'string', 'required' => true, 'example' => 'a71b3f9c...  (hmac-sha512 hex)', 'description' => 'HMAC-SHA512 atas string kanonik tujuh bagian — lihat bagian keamanan di bawah'],
                            ['name' => 'X-App-ID', 'type' => 'string', 'required' => true, 'example' => 'HCIS-PA', 'description' => 'Identitas aplikasi pemanggil, seperti pada Training dan SOP'],
                            ['name' => 'X-Kode-Karyawan', 'type' => 'string', 'required' => true, 'example' => 'DEMO-001', 'description' => 'Identitas admin yang sedang membuka layar ini. HC Learn menolak 401/403 bila akunnya tak dikenal di sisi Anda'],
                        ],
                        'payload_fields' => [
                            ['field' => 'Id_Master_Ujian', 'type' => 'string (query)', 'required' => true, 'description' => 'Diambil dari hasil Langkah 1. Bila Detail disisipkan langsung di Langkah 1, endpoint ini tidak diperlukan sama sekali'],
                        ],
                        'sample_payload' => [
                            'Id_Master_Ujian' => 'PA-STAFF-2027',
                        ],
                        'expected_response' => [
                            'success' => [
                                'http_status' => 200,
                                'description' => 'MOHON JANGAN MENGIRIM BUNYI PERTANYAANNYA, sekalipun mudah. Teks soal yang tersimpan di HCIS berarti seluruh bank soal HC Learn ikut tersalin ke sistem kami dan terbaca siapa pun yang membuka halaman konfigurasi paket — kebocoran yang tidak bisa ditarik kembali. Kami hanya perlu tahu soal mana mengukur kompetensi apa, berapa banyak, dan skalanya.',
                                'fields' => [
                                    ['field' => 'Detail[].Kode_Soal', 'type' => 'string', 'required' => true, 'description' => 'Pengenal soal milik HC Learn. Buram bagi kami — dipakai hanya mencocokkan skor yang masuk dengan butir yang benar. Bentuknya bebas'],
                                    ['field' => 'Detail[].Kode_Kompetensi', 'type' => 'string', 'required' => true, 'description' => 'YANG PALING KAMI BUTUHKAN. Tanpa field ini skor yang masuk tidak bisa dipetakan ke kompetensi mana pun dan seluruh perhitungan pilar kompetensi berhenti. Satu kompetensi BOLEH punya beberapa soal — karena itu ia field terpisah dari Kode_Soal. Mengacu daftar yang sudah disepakati bersama HC: K01-K11, CV01-CV06, BH01-BH03, QL01-QL04'],
                                    ['field' => 'Detail[].Jenis_Soal', 'type' => 'string', 'required' => true, 'description' => '"skala" atau "uraian". Yang uraian tidak diberi skor, dibaca sebagai catatan'],
                                    ['field' => 'Detail[].Skor_Maksimal', 'type' => 'number|null', 'required' => false, 'description' => 'Skala butir itu. BOLEH berbeda antar butir dalam satu paket — HCIS menormalkan sendiri. Kosongkan untuk butir uraian'],
                                    ['field' => 'Detail[].Bobot_Penilaian', 'type' => 'number|null', 'required' => false, 'description' => 'Bobot antar butir, dipakai memverifikasi perhitungan kami. Boleh kosong'],
                                ],
                                'sample' => [
                                    'Id_Master_Ujian' => 'PA-STAFF-2027',
                                    'Detail' => [
                                        ['Kode_Soal' => 'Q-8871', 'Kode_Kompetensi' => 'K01', 'Jenis_Soal' => 'skala', 'Skor_Maksimal' => 5, 'Bobot_Penilaian' => 20],
                                        ['Kode_Soal' => 'Q-8872', 'Kode_Kompetensi' => 'K01', 'Jenis_Soal' => 'skala', 'Skor_Maksimal' => 5, 'Bobot_Penilaian' => 20],
                                        ['Kode_Soal' => 'Q-8905', 'Kode_Kompetensi' => 'CV04', 'Jenis_Soal' => 'skala', 'Skor_Maksimal' => 5, 'Bobot_Penilaian' => 15],
                                        ['Kode_Soal' => 'Q-9110', 'Kode_Kompetensi' => 'QL01', 'Jenis_Soal' => 'uraian', 'Skor_Maksimal' => null, 'Bobot_Penilaian' => null],
                                    ],
                                ],
                            ],
                            'error' => [
                                'http_status' => 404,
                                'description' => 'Kembalikan 404 bila paket soal tidak dikenal.',
                                'sample' => [
                                    'status' => 'error',
                                    'code' => 404,
                                    'message' => 'Id_Master_Ujian PA-TIDAK-ADA tidak dikenal',
                                ],
                            ],
                        ],
                        'retry_policy' => [
                            'max_attempts' => 2,
                            'backoff' => 'Linear (10s, 30s)',
                            'retryable_codes' => 'HTTP 500, 502, 503, 504, Timeout',
                            'non_retryable_codes' => 'HTTP 400, 401, 403, 404',
                        ],
                    ],
                    [
                        'id' => 'ep-pa-penjadwalan',
                        'name' => 'Langkah 3 — Penjadwalan: beberapa token untuk satu karyawan',
                        'method' => 'POST',
                        'path' => '/api/v1/penjadwalan/pa/store',
                        'purpose' => 'SALINAN training/store YANG SUDAH ADA, DENGAN SATU PERUBAHAN. Seluruh field di bawah sama persis dengan yang HCIS kirim untuk Training dan SOP Kuis hari ini — termasuk Id_Integrasi[] yang memang sudah berbentuk pasangan Kode_Karyawan + Id_Integrasi. Yang berubah hanya ini: satu Kode_Karyawan boleh muncul BERKALI-KALI dalam satu kiriman, dan balasannya harus mengembalikan Id_Integrasi pada tiap barisnya. Sekarang HCIS mencocokkan token balik lewat Kode_Karyawan, sehingga satu karyawan hanya bisa memegang satu token — dan seorang manajer dengan lima bawahan butuh lima form terpisah. Alamatnya kami minta terpisah dari training/store supaya penjadwalan penilaian tidak lahir sebagai data Training di sisi Anda; isinya sengaja kami buat sama supaya Anda bisa membangunnya dengan menyalin, bukan merancang ulang.',
                        'trigger_event' => 'performance_appraisal.cycle_locked',
                        'headers' => [
                            ['name' => 'Content-Type', 'type' => 'string', 'required' => true, 'example' => 'application/json', 'description' => 'Wajib application/json'],
                            ['name' => 'X-HC-Public', 'type' => 'string', 'required' => true, 'example' => 'hcis-prod-01', 'description' => 'Kunci publik HCIS. Sama dengan yang dipakai Training dan SOP hari ini'],
                            ['name' => 'X-HC-Timestamp', 'type' => 'string', 'required' => true, 'example' => '2026-09-05 09:15:00', 'description' => 'Format "Y-m-d H:i:s", bukan epoch. Selisih maksimal 60 detik'],
                            ['name' => 'X-HC-Nonce', 'type' => 'string', 'required' => true, 'example' => 'K7pQ2mZx9RvL4nB1cT6sW8yE3aD5gH0j', 'description' => 'Acak 32 karakter, sekali pakai. Percobaan ulang wajib memakai nonce BARU'],
                            ['name' => 'X-HC-Body-Hash', 'type' => 'string', 'required' => true, 'example' => '9f2c1d5e8a...  (sha256 hex)', 'description' => 'SHA-256 heksadesimal atas body JSON, persis byte yang dikirim'],
                            ['name' => 'X-HC-Signature', 'type' => 'string', 'required' => true, 'example' => 'a71b3f9c...  (hmac-sha512 hex)', 'description' => 'HMAC-SHA512 atas string kanonik tujuh bagian — lihat bagian keamanan di bawah'],
                            ['name' => 'X-App-ID', 'type' => 'string', 'required' => true, 'example' => 'HCIS-PA', 'description' => 'Identitas aplikasi pemanggil, seperti pada Training dan SOP'],
                            ['name' => 'X-Kode-Karyawan', 'type' => 'string', 'required' => true, 'example' => 'DEMO-001', 'description' => 'Identitas admin yang menjalankan penjadwalan'],
                        ],
                        'payload_fields' => [
                            ['field' => 'Sumber_Aplikasi', 'type' => 'string', 'required' => true, 'description' => 'Bernilai "PA". Field yang sama sudah Anda layani bernilai "SOP", dan dipakai menentukan ke mana pengisi dikembalikan seusai form. Tanpa nilai baru ini, penilai yang berangkat dari halaman PA akan mendarat di modul yang sama sekali lain'],
                            ['field' => 'Id_Master_Ujian', 'type' => 'string', 'required' => true, 'description' => 'Paket soal yang dipakai siklus ini. Sama seperti training/store'],
                            ['field' => 'Waktu_Mulai', 'type' => 'datetime', 'required' => true, 'description' => 'Awal jendela pengisian, format "Y-m-d H:i:s". Nama dan bentuknya sama persis dengan training/store'],
                            ['field' => 'Waktu_Akhir', 'type' => 'datetime', 'required' => true, 'description' => 'Tenggat pengisian. Mohon DITAMPILKAN kepada pengisi — HCIS menghitung keterlambatan dari sini, dan pengisi yang tak melihat tenggatnya akan tercatat terlambat tanpa pernah diberi tahu'],
                            ['field' => 'Jenis_User', 'type' => 'string', 'required' => true, 'description' => '"internal", sama seperti training/store'],
                            ['field' => 'Flag_Camera', 'type' => 'boolean|null', 'required' => true, 'description' => 'Selalu null untuk penilaian 360 — tidak ada proktoring pada formulir penilaian. Tetap dikirim karena training/store menjawab 500 bila field ini tak ada sama sekali'],
                            ['field' => 'User_Agent', 'type' => 'string', 'required' => true, 'description' => 'Bernilai "PA". Penanda pemanggil, bukan jejak perangkat — sama seperti yang SOP kirim hari ini'],
                            ['field' => 'Id_Karyawan[]', 'type' => 'array of string', 'required' => true, 'description' => 'Daftar kode penilai TANPA duplikat, sama seperti training/store. Yang boleh berulang adalah Id_Integrasi[] di bawah, bukan daftar ini'],
                            ['field' => 'Data_Karyawan[]', 'type' => 'array', 'required' => true, 'description' => 'Rincian tiap penilai, bentuknya sama persis dengan training/store'],
                            ['field' => 'Id_Integrasi[]', 'type' => 'array', 'required' => true, 'description' => 'NAMA DAN BENTUKNYA SUDAH SEPERTI INI HARI INI — pasangan Kode_Karyawan + Id_Integrasi. Satu baris = satu form. INILAH SATU-SATUNYA YANG BERUBAH: Kode_Karyawan yang sama boleh muncul berkali-kali'],
                            ['field' => 'Id_Integrasi[].Kode_Karyawan', 'type' => 'string', 'required' => true, 'description' => 'Orang yang MENGISI form dan login ke HC Learn — bukan orang yang dinilai'],
                            ['field' => 'Id_Integrasi[].Id_Integrasi', 'type' => 'integer', 'required' => true, 'description' => 'Nomor baris integrasi milik HCIS — BUKAN nomor penugasan. Satu penugasan bisa punya beberapa baris integrasi (paket soalnya diganti, pintunya dicetak ulang), jadi nomor penugasan tidak pernah bisa menunjuk token yang mana. Mohon dikembalikan apa adanya di balasan dan di callback hasil; HCIS menolak kiriman yang angkanya tidak cocok'],
                            ['field' => 'Id_Integrasi[].Label_Subjek', 'type' => 'string', 'required' => false, 'description' => 'TAMBAHAN BARU. Teks untuk ditampilkan di layar supaya pengisi tahu sedang menilai siapa. Tidak perlu disimpan sebagai relasi maupun divalidasi — cukup ditampilkan'],
                            ['field' => 'Deleted_Tokens[]', 'type' => 'array of string', 'required' => false, 'description' => 'Sudah ada dan sudah kami pakai lewat SOP: token yang dimatikan bersamaan dengan kiriman ini'],
                        ],
                        'sample_payload' => [
                            'Sumber_Aplikasi' => 'PA',
                            'Id_Master_Ujian' => 'PA-STAFF-2027',
                            'Waktu_Mulai' => '2026-09-01 08:00:00',
                            'Waktu_Akhir' => '2026-09-30 17:00:00',
                            'Jenis_User' => 'internal',
                            'Flag_Camera' => null,
                            'User_Agent' => 'PA',
                            'Id_Karyawan' => ['DEMO-001'],
                            'Data_Karyawan' => [
                                ['Kode_Karyawan' => 'DEMO-001', 'Nama' => 'Penilai Contoh', 'Email' => 'penilai.contoh@example.com'],
                            ],
                            'Id_Integrasi' => [
                                ['Kode_Karyawan' => 'DEMO-001', 'Id_Integrasi' => 9001, 'Label_Subjek' => 'Peserta Contoh Satu — Staff, IT'],
                                ['Kode_Karyawan' => 'DEMO-001', 'Id_Integrasi' => 9002, 'Label_Subjek' => 'Peserta Contoh Dua — Staff, IT'],
                                ['Kode_Karyawan' => 'DEMO-001', 'Id_Integrasi' => 9003, 'Label_Subjek' => 'Diri Sendiri'],
                            ],
                            'Deleted_Tokens' => [],
                        ],
                        'expected_response' => [
                            'success' => [
                                'http_status' => 200,
                                'description' => 'Bentuk balasannya sama dengan training/store — result[] berisi Kode_Karyawan, Id_Ujian_Token, dan Payload_Json — dengan SATU tambahan: Id_Integrasi ikut dikembalikan di tiap baris. Tanpa itu HCIS tidak punya cara memasangkan token ke baris integrasi yang benar, karena tiga baris di bawah ini berbagi Kode_Karyawan yang sama. Satu baris hasil untuk SATU Id_Integrasi: token yang sama pada dua Id_Integrasi berbeda membuat dua penilaian tertulis ke satu baris integrasi.',
                                'fields' => [
                                    ['field' => 'result[].Id_Integrasi', 'type' => 'integer', 'required' => true, 'description' => 'TAMBAHAN YANG KAMI MINTA. Dikembalikan apa adanya dari request — satu-satunya cara memasangkan token ke baris integrasi yang benar saat satu karyawan memegang beberapa'],
                                    ['field' => 'result[].Kode_Karyawan', 'type' => 'string', 'required' => true, 'description' => 'Sudah dikembalikan hari ini. Boleh tetap ada, tetapi tidak lagi cukup untuk mencocokkan'],
                                    ['field' => 'result[].Id_Ujian_Token', 'type' => 'string', 'required' => true, 'description' => 'Sudah dikembalikan hari ini. Token unik per baris integrasi; inilah kunci sambung yang muncul lagi di callback hasil sebagai Api_Reference'],
                                    ['field' => 'result[].Payload_Json', 'type' => 'object|string', 'required' => true, 'description' => 'Sudah dikembalikan hari ini, dan di dalamnya ada Link_Ujian yang dibuka penilai. HCIS sudah membacanya dengan cara yang sama seperti pada Training dan SOP'],
                                ],
                                'sample' => [
                                    'result' => [
                                        ['Id_Integrasi' => 9001, 'Kode_Karyawan' => 'DEMO-001', 'Id_Ujian_Token' => 'tok-aaa', 'Payload_Json' => ['Link_Ujian' => 'https://hclearn.example.com/ujian/tok-aaa']],
                                        ['Id_Integrasi' => 9002, 'Kode_Karyawan' => 'DEMO-001', 'Id_Ujian_Token' => 'tok-bbb', 'Payload_Json' => ['Link_Ujian' => 'https://hclearn.example.com/ujian/tok-bbb']],
                                        ['Id_Integrasi' => 9003, 'Kode_Karyawan' => 'DEMO-001', 'Id_Ujian_Token' => 'tok-ccc', 'Payload_Json' => ['Link_Ujian' => 'https://hclearn.example.com/ujian/tok-ccc']],
                                    ],
                                ],
                            ],
                            'error' => [
                                'http_status' => 422,
                                'description' => 'Kembalikan 422 bila paket soal tidak dikenal atau ada Kode_Karyawan yang belum terdaftar. Mohon sebutkan Id_Integrasi mana yang bermasalah — HCIS menjadwalkan ulang hanya yang gagal.',
                                'sample' => [
                                    'status' => 'error',
                                    'code' => 422,
                                    'message' => 'Kode_Karyawan DEMO-404 tidak terdaftar',
                                    'failed' => [9002],
                                ],
                            ],
                        ],
                        'retry_policy' => [
                            'max_attempts' => 3,
                            'backoff' => 'Exponential backoff (30s, 2m, 10m)',
                            'retryable_codes' => 'HTTP 500, 502, 503, 504, Timeout',
                            'non_retryable_codes' => 'HTTP 400, 401, 403, 404, 422',
                        ],
                    ],
                    [
                        'id' => 'ep-pa-perpanjang',
                        'name' => 'Langkah 4 — Perpanjang jendela, TOKEN DIPERTAHANKAN',
                        'method' => 'POST',
                        'path' => '/api/v1/penjadwalan/pa/update/{Id_Ujian_Token}',
                        'purpose' => 'SALINAN training/update/{token} YANG SUDAH ADA — SOP memakainya hari ini untuk memperpanjang sesi yang jendelanya lewat. Dibutuhkan karena tenggat siklus penilaian memang bergeser: peserta menyusul, atasan cuti, siklus diperpanjang seminggu. Bila hanya HCIS yang tahu tenggatnya mundur, penilai membuka tautannya dan menemukan ujian yang sudah tertutup — padahal di layar HCIS tugasnya masih terbuka, dan tidak ada satu pun pihak yang terlihat salah. TOKENNYA JANGAN DIGANTI. Token adalah kunci sambung callback; menggantinya membuat hasil yang sedang Anda kirim ulang tidak dikenali lagi, dan pengiriman ulang justru paling sering terjadi tepat di sekitar batas waktu.',
                        'trigger_event' => 'Admin HCIS memperpanjang jendela pengisian siklus',
                        'headers' => [
                            ['name' => 'Content-Type', 'type' => 'string', 'required' => true, 'example' => 'application/json', 'description' => 'Wajib application/json'],
                            ['name' => 'X-HC-Public', 'type' => 'string', 'required' => true, 'example' => 'hcis-prod-01', 'description' => 'Kunci publik HCIS. Sama dengan yang dipakai Training dan SOP hari ini'],
                            ['name' => 'X-HC-Timestamp', 'type' => 'string', 'required' => true, 'example' => '2026-09-05 09:15:00', 'description' => 'Format "Y-m-d H:i:s", bukan epoch. Selisih maksimal 60 detik'],
                            ['name' => 'X-HC-Nonce', 'type' => 'string', 'required' => true, 'example' => 'K7pQ2mZx9RvL4nB1cT6sW8yE3aD5gH0j', 'description' => 'Acak 32 karakter, sekali pakai. Percobaan ulang wajib memakai nonce BARU'],
                            ['name' => 'X-HC-Body-Hash', 'type' => 'string', 'required' => true, 'example' => '9f2c1d5e8a...  (sha256 hex)', 'description' => 'SHA-256 heksadesimal atas body JSON, persis byte yang dikirim'],
                            ['name' => 'X-HC-Signature', 'type' => 'string', 'required' => true, 'example' => 'a71b3f9c...  (hmac-sha512 hex)', 'description' => 'HMAC-SHA512 atas string kanonik tujuh bagian — lihat bagian keamanan di bawah'],
                            ['name' => 'X-App-ID', 'type' => 'string', 'required' => true, 'example' => 'HCIS-PA', 'description' => 'Identitas aplikasi pemanggil, seperti pada Training dan SOP'],
                            ['name' => 'X-Kode-Karyawan', 'type' => 'string', 'required' => true, 'example' => 'DEMO-001', 'description' => 'Identitas admin yang menjalankan penjadwalan'],
                        ],
                        'payload_fields' => [
                            ['field' => '{Id_Ujian_Token}', 'type' => 'string (path)', 'required' => true, 'description' => 'Token yang jendelanya diperpanjang, di alamatnya — sama seperti training/update/{token}'],
                            ['field' => 'Sumber_Aplikasi', 'type' => 'string', 'required' => true, 'description' => 'Bernilai "PA"'],
                            ['field' => 'Id_Master_Ujian', 'type' => 'string', 'required' => true, 'description' => 'Paket soal yang dipakai, tidak berubah'],
                            ['field' => 'Waktu_Mulai', 'type' => 'datetime', 'required' => true, 'description' => 'Awal jendela yang baru'],
                            ['field' => 'Waktu_Akhir', 'type' => 'datetime', 'required' => true, 'description' => 'Tenggat yang baru. Mohon DITAMPILKAN kepada pengisi'],
                            ['field' => 'Flag_Camera', 'type' => 'boolean|null', 'required' => true, 'description' => 'Selalu null untuk penilaian 360'],
                            ['field' => 'User_Agent', 'type' => 'string', 'required' => true, 'description' => 'Bernilai "PA"'],
                            ['field' => 'Deleted_Tokens[]', 'type' => 'array of string', 'required' => false, 'description' => 'Kosong pada perpanjangan biasa. Ada di sini karena training/update sudah menerimanya'],
                        ],
                        'sample_payload' => [
                            'Sumber_Aplikasi' => 'PA',
                            'Id_Master_Ujian' => 'PA-STAFF-2027',
                            'Waktu_Mulai' => '2026-09-01 08:00:00',
                            'Waktu_Akhir' => '2026-10-07 17:00:00',
                            'Flag_Camera' => null,
                            'User_Agent' => 'PA',
                            'Deleted_Tokens' => [],
                        ],
                        'expected_response' => [
                            'success' => [
                                'http_status' => 200,
                                'description' => 'Bentuknya sama dengan training/update: result[] berisi Id_Ujian_Token dan Payload_Json. Tokennya diharapkan SAMA dengan yang dikirim; bila berubah, HCIS menghormatinya dan menyimpan yang baru — tetapi setiap pergantian token membuka jendela waktu ketika hasil yang sedang dikirim ulang tidak lagi dikenali.',
                                'fields' => [
                                    ['field' => 'result[].Id_Ujian_Token', 'type' => 'string', 'required' => true, 'description' => 'Diharapkan sama dengan token di alamatnya'],
                                    ['field' => 'result[].Payload_Json', 'type' => 'object|string', 'required' => true, 'description' => 'Berisi Link_Ujian yang diperbarui'],
                                ],
                                'sample' => [
                                    'result' => [
                                        ['Id_Ujian_Token' => 'tok-aaa', 'Payload_Json' => ['Link_Ujian' => 'https://hclearn.example.com/ujian/tok-aaa']],
                                    ],
                                ],
                            ],
                            'error' => [
                                'http_status' => 404,
                                'description' => 'Kembalikan 404 bila tokennya tidak dikenali. HCIS tidak mencetak token baru diam-diam sebagai gantinya — penilai yang tautannya berganti tanpa diberi tahu akan membuka tautan lama dari pesan lama.',
                                'sample' => [
                                    'status' => 'error',
                                    'code' => 404,
                                    'message' => 'Id_Ujian_Token tidak dikenali',
                                ],
                            ],
                        ],
                        'retry_policy' => [
                            'max_attempts' => 3,
                            'backoff' => 'Exponential backoff (30s, 2m, 10m)',
                            'retryable_codes' => 'HTTP 500, 502, 503, 504, Timeout',
                            'non_retryable_codes' => 'HTTP 400, 401, 403, 404, 422',
                        ],
                    ],
                    [
                        'id' => 'ep-pa-terbitkan-ulang',
                        'name' => 'Langkah 5 — Terbitkan ulang token untuk SATU penugasan',
                        'method' => 'POST',
                        'path' => '/api/v1/penjadwalan/pa/reassign',
                        'purpose' => 'SALINAN training/reassign YANG SUDAH ADA — LMS memakainya hari ini untuk mengganti ujian satu orang tanpa menyentuh yang lain. Bedanya dengan Langkah 4: di sini token lama memang HARUS mati dan diganti yang baru. Dipakai ketika formulirnya sendiri yang keliru, bukan waktunya: paket soal siklus diganti di tengah jalan sehingga token lama menunjuk susunan yang salah, atau tautannya rusak/terlanjur tersebar. Bedanya dengan Langkah 6: penugasannya TETAP HIDUP dan tetap harus diisi — yang diganti pintunya, bukan kewajibannya. Tanpa endpoint ini, satu formulir cacat memaksa kami mencabut lalu menjadwalkan ulang, dan penugasan itu kehilangan riwayatnya di sisi Anda.',
                        'trigger_event' => 'Admin HCIS menerbitkan ulang pintu pengisian satu penugasan',
                        'headers' => [
                            ['name' => 'Content-Type', 'type' => 'string', 'required' => true, 'example' => 'application/json', 'description' => 'Wajib application/json'],
                            ['name' => 'X-HC-Public', 'type' => 'string', 'required' => true, 'example' => 'hcis-prod-01', 'description' => 'Kunci publik HCIS. Sama dengan yang dipakai Training dan SOP hari ini'],
                            ['name' => 'X-HC-Timestamp', 'type' => 'string', 'required' => true, 'example' => '2026-09-05 09:15:00', 'description' => 'Format "Y-m-d H:i:s", bukan epoch. Selisih maksimal 60 detik'],
                            ['name' => 'X-HC-Nonce', 'type' => 'string', 'required' => true, 'example' => 'K7pQ2mZx9RvL4nB1cT6sW8yE3aD5gH0j', 'description' => 'Acak 32 karakter, sekali pakai. Percobaan ulang wajib memakai nonce BARU'],
                            ['name' => 'X-HC-Body-Hash', 'type' => 'string', 'required' => true, 'example' => '9f2c1d5e8a...  (sha256 hex)', 'description' => 'SHA-256 heksadesimal atas body JSON, persis byte yang dikirim'],
                            ['name' => 'X-HC-Signature', 'type' => 'string', 'required' => true, 'example' => 'a71b3f9c...  (hmac-sha512 hex)', 'description' => 'HMAC-SHA512 atas string kanonik tujuh bagian — lihat bagian keamanan di bawah'],
                            ['name' => 'X-App-ID', 'type' => 'string', 'required' => true, 'example' => 'HCIS-PA', 'description' => 'Identitas aplikasi pemanggil, seperti pada Training dan SOP'],
                            ['name' => 'X-Kode-Karyawan', 'type' => 'string', 'required' => true, 'example' => 'DEMO-001', 'description' => 'Identitas admin yang menjalankan penjadwalan'],
                        ],
                        'payload_fields' => [
                            ['field' => 'Id_Ujian_Token_Lama', 'type' => 'string', 'required' => true, 'description' => 'Token yang digantikan. Namanya sama persis dengan training/reassign; ia yang menandai ini penggantian, bukan penjadwalan baru'],
                            ['field' => 'Sumber_Aplikasi', 'type' => 'string', 'required' => true, 'description' => 'Bernilai "PA"'],
                            ['field' => 'Id_Master_Ujian', 'type' => 'string', 'required' => true, 'description' => 'Paket soal yang berlaku SEKARANG — boleh berbeda dari yang dipakai token lama, dan itu justru sebab paling lazim endpoint ini dipanggil'],
                            ['field' => 'Waktu_Mulai', 'type' => 'datetime', 'required' => true, 'description' => 'Awal jendela'],
                            ['field' => 'Waktu_Akhir', 'type' => 'datetime', 'required' => true, 'description' => 'Tenggat'],
                            ['field' => 'Jenis_User', 'type' => 'string', 'required' => true, 'description' => '"internal"'],
                            ['field' => 'Flag_Camera', 'type' => 'boolean|null', 'required' => true, 'description' => 'Selalu null untuk penilaian 360'],
                            ['field' => 'User_Agent', 'type' => 'string', 'required' => true, 'description' => 'Bernilai "PA"'],
                            ['field' => 'Id_Karyawan[]', 'type' => 'array of string', 'required' => true, 'description' => 'Berisi satu kode penilai. Bentuknya array mengikuti training/reassign'],
                            ['field' => 'Data_Karyawan[]', 'type' => 'array', 'required' => true, 'description' => 'Rincian penilai itu, bentuknya sama dengan training/reassign'],
                            ['field' => 'Id_Integrasi[]', 'type' => 'array', 'required' => true, 'description' => 'Berisi SATU pasangan Kode_Karyawan + Id_Integrasi — dan Id_Integrasi-nya adalah baris integrasi BARU, bukan yang lama dan bukan nomor penugasan. Justru karena itulah penerbitan ulang aman: baris lama tetap ada dengan tokennya yang sudah mati, riwayatnya utuh. Berbeda dari Langkah 3, di sini tidak pernah lebih dari satu'],
                            ['field' => 'Id_Integrasi[].Label_Subjek', 'type' => 'string', 'required' => false, 'description' => 'Teks penanda siapa yang dinilai, sama seperti Langkah 3'],
                        ],
                        'sample_payload' => [
                            'Id_Ujian_Token_Lama' => 'tok-aaa',
                            'Sumber_Aplikasi' => 'PA',
                            'Id_Master_Ujian' => 'PA-STAFF-2027-REV2',
                            'Waktu_Mulai' => '2026-09-10 08:00:00',
                            'Waktu_Akhir' => '2026-09-30 17:00:00',
                            'Jenis_User' => 'internal',
                            'Flag_Camera' => null,
                            'User_Agent' => 'PA',
                            'Id_Karyawan' => ['DEMO-001'],
                            'Data_Karyawan' => [
                                ['Kode_Karyawan' => 'DEMO-001', 'Nama' => 'Penilai Contoh', 'Email' => 'penilai.contoh@example.com'],
                            ],
                            'Id_Integrasi' => [
                                ['Kode_Karyawan' => 'DEMO-001', 'Id_Integrasi' => 9001, 'Label_Subjek' => 'Peserta Contoh Satu — Staff, IT'],
                            ],
                        ],
                        'expected_response' => [
                            'success' => [
                                'http_status' => 200,
                                'description' => 'Bentuknya sama dengan training/reassign: result[] berisi token BARU dan Payload_Json. Token lama harus berhenti bisa dibuka pada saat yang sama — bila ia masih hidup, penilai yang memegang tautan lama mengisi formulir yang salah sampai selesai. Sertakan Id_Integrasi seperti pada Langkah 3, supaya tokennya bisa dipasangkan tanpa menebak.',
                                'fields' => [
                                    ['field' => 'result[].Id_Integrasi', 'type' => 'integer', 'required' => true, 'description' => 'Dikembalikan apa adanya dari request'],
                                    ['field' => 'result[].Id_Ujian_Token', 'type' => 'string', 'required' => true, 'description' => 'Token BARU. Yang lama tidak boleh bisa dibuka lagi'],
                                    ['field' => 'result[].Payload_Json', 'type' => 'object|string', 'required' => true, 'description' => 'Berisi Link_Ujian yang baru'],
                                ],
                                'sample' => [
                                    'result' => [
                                        ['Id_Integrasi' => 9001, 'Id_Ujian_Token' => 'tok-zzz', 'Payload_Json' => ['Link_Ujian' => 'https://hclearn.example.com/ujian/tok-zzz']],
                                    ],
                                ],
                            ],
                            'error' => [
                                'http_status' => 409,
                                'description' => 'Kembalikan 409 bila token lama TERLANJUR DIISI. HCIS tidak boleh membuang penilaian yang sudah selesai hanya karena admin menekan terbitkan ulang — sebutkan keadaannya, dan biar orangnya yang memutuskan.',
                                'sample' => [
                                    'status' => 'error',
                                    'code' => 409,
                                    'message' => 'Token tok-aaa sudah diisi dan tidak bisa diterbitkan ulang',
                                ],
                            ],
                        ],
                        'retry_policy' => [
                            'max_attempts' => 3,
                            'backoff' => 'Exponential backoff (30s, 2m, 10m)',
                            'retryable_codes' => 'HTTP 500, 502, 503, 504, Timeout',
                            'non_retryable_codes' => 'HTTP 400, 401, 403, 404, 422',
                        ],
                    ],
                    [
                        'id' => 'ep-pa-cabut-token',
                        'name' => 'Langkah 6 — Cabut token (Deleted_Tokens yang sudah ada, dipanggil sendirian)',
                        'method' => 'POST',
                        'path' => '/api/v1/penjadwalan/pa/revoke',
                        'purpose' => 'MEKANISMENYA SUDAH ADA DI SISI ANDA — ini hanya meminta agar bisa dipanggil sendirian. Field Deleted_Tokens pada training/store dan training/update sudah kami pakai lewat SOP untuk mematikan token, jadi yang kami butuhkan bukan kemampuan baru melainkan pintu masuk tanpa harus menjadwalkan sesuatu terlebih dahulu. Sebabnya: penugasan penilaian sering dibatalkan ketika tidak ada penjadwalan lain yang sedang berjalan — pesertanya dikecualikan dari siklus, atau penilainya diganti. Selama tokennya masih hidup, penilai tetap melihat formulirnya, MENGISINYA SAMPAI SELESAI, lalu kirimannya ditolak HCIS; tenaganya terbuang seluruhnya dan ia baru tahu di akhir. Ini benar-benar terjadi pada uji rantai penuh kami. Bila memanggil training/store dengan Deleted_Tokens terisi dan daftar penugasan kosong sudah cukup di sisi Anda, pakai saja itu — beri tahu kami, dan endpoint ini tidak perlu dibuat sama sekali.',
                        'trigger_event' => 'performance_appraisal.assignment_cancelled',
                        'headers' => [
                            ['name' => 'Content-Type', 'type' => 'string', 'required' => true, 'example' => 'application/json', 'description' => 'Wajib application/json'],
                            ['name' => 'X-HC-Public', 'type' => 'string', 'required' => true, 'example' => 'hcis-prod-01', 'description' => 'Kunci publik HCIS. Sama dengan yang dipakai Training dan SOP hari ini'],
                            ['name' => 'X-HC-Timestamp', 'type' => 'string', 'required' => true, 'example' => '2026-09-05 09:15:00', 'description' => 'Format "Y-m-d H:i:s", bukan epoch. Selisih maksimal 60 detik'],
                            ['name' => 'X-HC-Nonce', 'type' => 'string', 'required' => true, 'example' => 'K7pQ2mZx9RvL4nB1cT6sW8yE3aD5gH0j', 'description' => 'Acak 32 karakter, sekali pakai. Percobaan ulang wajib memakai nonce BARU'],
                            ['name' => 'X-HC-Body-Hash', 'type' => 'string', 'required' => true, 'example' => '9f2c1d5e8a...  (sha256 hex)', 'description' => 'SHA-256 heksadesimal atas body JSON, persis byte yang dikirim'],
                            ['name' => 'X-HC-Signature', 'type' => 'string', 'required' => true, 'example' => 'a71b3f9c...  (hmac-sha512 hex)', 'description' => 'HMAC-SHA512 atas string kanonik tujuh bagian — lihat bagian keamanan di bawah'],
                            ['name' => 'X-App-ID', 'type' => 'string', 'required' => true, 'example' => 'HCIS-PA', 'description' => 'Identitas aplikasi pemanggil, seperti pada Training dan SOP'],
                            ['name' => 'X-Kode-Karyawan', 'type' => 'string', 'required' => true, 'example' => 'DEMO-001', 'description' => 'Identitas admin yang menjalankan penjadwalan'],
                        ],
                        'payload_fields' => [
                            ['field' => 'Deleted_Tokens[]', 'type' => 'array of string', 'required' => true, 'description' => 'Token yang dicabut. Namanya sengaja sama dengan field yang sudah Anda layani pada training/store, supaya kode di baliknya bisa dipakai ulang apa adanya'],
                            ['field' => 'Sumber_Aplikasi', 'type' => 'string', 'required' => true, 'description' => 'Bernilai "PA", sama seperti langkah-langkah sebelumnya'],
                            ['field' => 'Alasan', 'type' => 'string', 'required' => true, 'description' => 'TAMBAHAN BARU. Boleh ditampilkan apa adanya kepada pengisi. HCIS selalu mengisinya — pembatalan di sisi kami wajib beralasan'],
                        ],
                        'sample_payload' => [
                            'Sumber_Aplikasi' => 'PA',
                            'Deleted_Tokens' => ['tok-aaa', 'tok-bbb'],
                            'Alasan' => 'Peserta dikecualikan dari siklus penilaian',
                        ],
                        'expected_response' => [
                            'success' => [
                                'http_status' => 200,
                                'description' => 'Token berhenti bisa dibuka, dan pengisi melihat keterangan singkat alih-alih formulir kosong atau layar galat. Bila HC Learn sudah punya mekanisme menonaktifkan ujian, kami cukup memakai yang ada — bentuk ini hanya usulan.',
                                'fields' => [
                                    ['field' => 'revoked_count', 'type' => 'integer', 'required' => true, 'description' => 'Berapa token yang berhasil dicabut. Bentuk balasan apa pun yang sudah Anda pakai untuk Deleted_Tokens hari ini juga kami terima — ini usulan, bukan tuntutan'],
                                    ['field' => 'already_submitted', 'type' => 'array of string', 'required' => false, 'description' => 'Token yang terlanjur diisi sebelum pencabutan sampai. HCIS perlu tahu ini supaya penolakan hasilnya tidak terbaca sebagai galat'],
                                ],
                                'sample' => [
                                    'revoked_count' => 1,
                                    'already_submitted' => ['tok-bbb'],
                                ],
                            ],
                            'error' => [
                                'http_status' => 404,
                                'description' => 'Kembalikan 404 bila tidak satu pun token dikenali.',
                                'sample' => [
                                    'status' => 'error',
                                    'code' => 404,
                                    'message' => 'Tidak ada token yang dikenali',
                                ],
                            ],
                        ],
                        'retry_policy' => [
                            'max_attempts' => 3,
                            'backoff' => 'Exponential backoff (30s, 2m, 10m)',
                            'retryable_codes' => 'HTTP 500, 502, 503, 504, Timeout',
                            'non_retryable_codes' => 'HTTP 400, 401, 403, 404, 422',
                        ],
                    ],
                ],

                /*
                 | ARAH SEBALIKNYA — apa yang HC Learn kirim BALIK ke HCIS.
                 |
                 | `endpoints` di atas adalah yang kami minta HC Learn sediakan. Yang di
                 | bawah ini kebalikannya: endpoint MILIK HCIS yang sudah berdiri, dan HC
                 | Learn yang memanggilnya.
                 |
                 | Sebelumnya bagian ini hanya ditunjuk — "lihat spesifikasi OpenAPI HCIS".
                 | Itu memindahkan pekerjaan membaca ke dokumen lain untuk sesuatu yang
                 | justru paling menentukan: bentuk kiriman balik inilah yang membuat nilai
                 | bisa dihitung atau tidak. Satu butir yang salah nama membuat penilaian
                 | tersimpan utuh tetapi tidak pernah menjadi angka, dan tidak ada yang
                 | terlihat rusak.
                 |
                 | DIPISAH dari `endpoints`, bukan digabung, supaya jawaban atas pertanyaan
                 | "berapa endpoint yang harus saya bangun" tetap benar: tetap yang di atas
                 | saja. Yang di bawah tidak dibangun HC Learn — hanya dipanggil.
                 |
                 | Isinya SATU, dan itu disengaja. Sempat ada callback kedua, "penilai membuka
                 | formulir", supaya HCIS bisa membedakan yang belum menyentuh dari yang sedang
                 | mengerjakan. Dicabut: penilai membuka lembarnya lewat tombol di halaman
                 | Penilaian Saya milik HCIS, jadi saat itu sudah terjadi di tangan kami. Yang
                 | tidak kami butuhkan dari pihak lain, tidak kami mintakan — daftar permintaan
                 | yang panjang membuat bagian yang benar-benar mengganjal ikut menunggu giliran.
                 */
                'callbacks' => [
                    [
                        'id' => 'cb-pa-hasil',
                        'name' => 'Callback A — Hasil penilaian per butir. SATU alamat, dipanggil sekali atau dua kali',
                        'method' => 'POST',
                        'path' => '/api/v1/pa/third-party/penilaian/complete',
                        'purpose' => 'CARA MEMAKAINYA, RINGKAS. Nilai langsung sah di sisi Anda? Kirim SEKALI dengan Status_Skor=FINAL beserta Detail[]. Nilai harus diverifikasi admin Anda dulu? Kirim DUA KALI ke alamat yang sama: (1) Status_Skor=MENUNGGU_VERIFIKASI segera setelah penilai menekan kirim — Detail[] boleh kosong, gunanya menghentikan pengingat untuk orang yang sudah mengerjakan bagiannya; (2) Status_Skor=FINAL beserta Detail[] sesudah admin Anda mengesahkan — barulah HCIS memetakan dan menghitung. Bila admin MENGUBAH angkanya, kiriman kedua wajib memakai Id_Ujian_Nilai_Akhir baru. TIDAK ADA metode HTTP kedua dan tidak ada wadah yang perlu dibuat lebih dulu: penugasannya sudah berdiri di sisi kami sejak token diterbitkan, jadi kiriman Anda selalu mengisi sesuatu yang sudah ada. KENAPA ALAMATNYA TERPISAH DARI CALLBACK TRAINING/SOP: satu alamat untuk tiga modul memaksa HCIS menebak asal kiriman dari tokennya — hari ini ia menembak tiga tabel setiap kali, dan sudah punya cabang khusus untuk token yang cocok di lebih dari satu modul. Selain itu penyaring bersamanya menuntut "Score", yang penilaian 360 tidak pernah punya: nilainya dihitung per kompetensi dari beberapa butir soal, dan satu angka akhir tidak bisa diurai kembali. ALAMATNYA BARU, MEKANISMENYA TIDAK — tanda tangan, header, dan cara menghitungnya sama persis dengan callback yang Anda pakai hari ini, sehingga di sisi Anda ini URL berbeda dengan kode yang sama.',
                        'trigger_event' => 'Penilai menyelesaikan dan mengirim formulirnya',
                        'headers' => [
                            ['name' => 'Content-Type', 'type' => 'string', 'required' => true, 'example' => 'application/json', 'description' => 'Wajib application/json'],
                            ['name' => 'X-HC-Public', 'type' => 'string', 'required' => true, 'example' => 'hclearn-prod-01', 'description' => 'Kunci publik aplikasi Anda — sama dengan callback yang sudah berjalan'],
                            ['name' => 'X-HC-Timestamp', 'type' => 'string', 'required' => true, 'example' => '2026-09-05T16:40:00+07:00', 'description' => 'Selisih maksimal 60 detik dari waktu server HCIS'],
                            ['name' => 'X-HC-Nonce', 'type' => 'string', 'required' => true, 'example' => '7b41d0e2-99aa-4c15-8f3d-1e6b0a24c7f9', 'description' => 'Sekali pakai. Nonce berulang ditolak sebagai replay — termasuk saat Anda mencoba ulang, jadi terbitkan nonce BARU tiap percobaan'],
                            ['name' => 'X-HC-Body-Hash', 'type' => 'string', 'required' => true, 'example' => '9f2c...  (sha256 hex)', 'description' => 'SHA-256 heksadesimal atas RAW body'],
                            ['name' => 'X-HC-Signature', 'type' => 'string', 'required' => true, 'example' => 'a71b...  (hmac-sha512 hex)', 'description' => 'HMAC-SHA512 atas METHOD, path, query, timestamp, nonce, body-hash, dan public key — dipisah baris baru, berurutan. Persis mekanisme yang sudah Anda pakai'],
                        ],
                        'payload_fields' => [
                            ['field' => 'Kode_Karyawan', 'type' => 'string', 'required' => true, 'description' => 'PENILAI yang mengisi, bukan yang dinilai. HCIS tidak memerlukan identitas orang yang dinilai — token sudah mewakilinya'],
                            ['field' => 'Id_Integrasi', 'type' => 'integer', 'required' => true, 'description' => 'Kembalikan Id_Integrasi dari Langkah 3 apa adanya — angka baris integrasi, BUKAN nomor penugasan. Namanya sengaja sama dengan yang KAMI kirim, supaya tidak ada penggantian nama di tengah jalan. Angka yang tidak cocok dengan Api_Reference-nya ditolak 403, karena pasangan yang salah berarti nilai seseorang nyaris tertulis ke penilaian orang lain. Bila klien callback Anda sudah terlanjur mengisi Id_Penjadwalan_Integrasi, kami menerimanya juga — tetapi jangan kirim keduanya dengan nilai berbeda'],
                            ['field' => 'Api_Reference', 'type' => 'string', 'required' => true, 'description' => 'Token pengisian dari Langkah 3 (32 heksadesimal). Satu-satunya kunci sambung; tanpa ini kiriman tidak bisa diletakkan pada baris integrasi mana pun. Kami SENGAJA tidak meminta Id_Ujian_Token numerik: bilangan berurutan bisa ditebak'],
                            ['field' => 'Id_Ujian_Token', 'type' => 'string', 'required' => false, 'description' => 'Warisan. Masih kami terima sebagai pengganti Api_Reference bila klien lama Anda mengirimnya, tetapi Api_Reference yang dicari lebih dulu'],
                            ['field' => 'Id_Ujian_Nilai_Akhir', 'type' => 'string', 'required' => false, 'description' => 'Penanda hasil milik HC Learn. Tidak wajib, tetapi SANGAT kami harapkan: dengan ini kiriman ulang bisa dikenali sebagai hasil yang sama. Satu penanda tidak boleh dipakai untuk dua isi yang berbeda — bila itu terjadi, kiriman kedua ditolak 422. Karena itu bila admin Anda MENGUBAH angka saat verifikasi, kiriman FINAL-nya wajib memakai penanda BARU — penanda yang sama untuk dua isi berbeda adalah keadaan yang sengaja kami tolak, sebab kalau diterima tidak akan pernah bisa dijelaskan versi mana yang dipakai'],
                            ['field' => 'Api_Version', 'type' => 'string', 'required' => false, 'description' => 'Versi bentuk kiriman Anda. Disimpan apa adanya, supaya hasil lama tetap bisa dijelaskan bila bentuknya berubah kelak'],
                            ['field' => 'Status_Skor', 'type' => 'string', 'required' => true, 'description' => 'FINAL | MENUNGGU_VERIFIKASI | DITOLAK. WAJIB, dan sengaja tidak punya nilai bawaan: field yang boleh dikosongkan akan suatu hari terlupa, dan yang terjadi kemudian adalah nilai yang masih menunggu verifikasi diperlakukan sebagai final lalu ikut terhitung — diam-diam, karena semuanya berhasil. Lupa mengisinya sekarang dijawab 422 pada kiriman pertama, saat masih murah. FINAL: angkanya sah, HCIS memetakan dan menghitung — inilah perilaku yang berlaku hari ini. MENUNGGU_VERIFIKASI: penilai sudah mengirim tetapi angkanya belum disahkan admin Anda; HCIS mencatat pengirimannya dan BERHENTI mengingatkan orang itu, tetapi tidak menandai selesai dan tidak menghitung apa pun — Detail boleh kosong. DITOLAK: verifikasi Anda menolak isinya dan penilai harus mengerjakan ulang; HCIS mengembalikan penugasannya ke keadaan sedang dikerjakan dan pengingat berjalan lagi. Kirim FINAL menyusul sesudah sah'],
                            ['field' => 'Total_Nilai', 'type' => 'numeric', 'required' => false, 'description' => 'PEMBANDING SAJA, tidak pernah menjadi sumber nilai. Berguna untuk mendeteksi selisih antara hitungan Anda dan hitungan kami — bukan untuk menggantikan Detail. Pada MENUNGGU_VERIFIKASI memang tidak Anda kirim, dan itu benar: angka sementara yang terlanjur dipakai lebih berbahaya daripada angka yang belum ada'],
                            ['field' => 'kode_akses', 'type' => 'string', 'required' => false, 'description' => 'Kode alat tes yang dipakai, misalnya PA360. Tersimpan apa adanya dan ikut membentuk kunci kiriman ulang bersama Api_Reference'],
                            ['field' => 'is_multi', 'type' => 'boolean', 'required' => false, 'description' => 'Penanda paket bertahap milik Anda. Disimpan untuk penelusuran; HCIS tidak mengubah perilakunya karena field ini — yang menentukan tetap Status_Skor'],
                            ['field' => 'penilaian.Nama_Tes', 'type' => 'string', 'required' => false, 'description' => 'Nama paket sebagaimana dilihat penilai. Disimpan supaya hasil lama tetap bisa dijelaskan meski paketnya kelak diganti namanya'],
                            ['field' => 'penilaian.Waktu_Mulai_Akses', 'type' => 'datetime', 'required' => false, 'description' => 'Kapan penilai membuka pintunya. Format Y-m-d H:i:s, waktu Jakarta — bukan jendela penjadwalan, melainkan akses yang sungguh terjadi'],
                            ['field' => 'penilaian.Waktu_Selesai_Akses', 'type' => 'datetime', 'required' => false, 'description' => 'Kapan ia menekan kirim. Bersama Waktu_Mulai_Akses inilah bukti pengisiannya wajar, bukan diklik sekilas'],
                            ['field' => 'penilaian.Ambang_Batas_Nilai', 'type' => 'numeric', 'required' => false, 'description' => 'Ambang lulus menurut Anda. Disimpan sebagai pembanding; ambang yang dipakai HCIS tetap milik pita nilai siklusnya'],
                            ['field' => 'penilaian.Status_Kelulusan', 'type' => 'string', 'required' => false, 'description' => 'Misalnya Lulus atau Tidak Lulus. Ditampilkan sebagai keterangan asal, tidak pernah menggantikan keputusan HCIS'],
                            ['field' => 'penilaian.Warna', 'type' => 'string', 'required' => false, 'description' => 'Warna heks untuk tampilan, misalnya #d01616. Dipakai apa adanya di layar hasil per kompetensi'],
                            ['field' => 'Detail[]', 'type' => 'array', 'required' => false, 'description' => 'WAJIB dan tidak boleh kosong bila Status_Skor bernilai FINAL — di situ ia satu-satunya sumber nilai. Boleh KOSONG bila Status_Skor MENUNGGU_VERIFIKASI: kami tidak menuntut angka yang belum sah. Kirim SELURUH butir paket dalam satu kiriman — bukan sebagian, dan bukan dicicil beberapa kali'],
                            ['field' => 'Detail[].Kode_Soal', 'type' => 'string', 'required' => true, 'description' => 'HARUS PERSIS SAMA dengan Kode_Soal yang Anda berikan di Langkah 2 (susunan paket). Inilah satu-satunya jembatan butir ke kompetensi. Butir yang kodenya tidak kami kenali TIDAK dibuang — ia tersimpan tanpa kompetensi dan dilaporkan balik, karena membuangnya diam-diam membuat nilai terhitung dari sebagian butir saja dan angkanya tetap tampak wajar'],
                            ['field' => 'Detail[].Jenis_Soal', 'type' => 'string', 'required' => false, 'description' => 'Misalnya SKALA atau ESAI. Disimpan untuk penelusuran'],
                            ['field' => 'Detail[].Skor', 'type' => 'numeric', 'required' => false, 'description' => 'Skor mentah butir itu, apa adanya menurut skala Anda — JANGAN dinormalkan ke 100; HCIS yang menormalkannya. Boleh kosong untuk butir yang memang tidak berskor, misalnya esai'],
                            ['field' => 'Detail[].Skor_Maksimal', 'type' => 'numeric', 'required' => false, 'description' => 'WAJIB ADA DAN HARUS > 0 setiap kali Skor terisi, dan Skor tidak boleh melebihinya. Tanpa pasangannya, sebuah skor tidak punya skala: angka 4 bisa berarti sangat baik pada skala 5 atau nyaris gagal pada skala 10, dan tidak ada cara menebaknya'],
                            ['field' => 'Detail[].Bobot_Penilaian', 'type' => 'numeric', 'required' => false, 'description' => 'Bobot butir menurut Anda, bila ada. Disimpan sebagai pembanding'],
                            ['field' => 'Detail[].Jawaban_Teks', 'type' => 'string', 'required' => false, 'description' => 'Jawaban esai atau komentar penilai. Ditampilkan kepada atasan dan HC saat verifikasi — inilah yang membuat angka bisa dijelaskan'],
                            ['field' => 'Detail[].Kode_Alat_Tes', 'type' => 'string', 'required' => false, 'description' => 'Kode alat tes butir itu. Tersimpan per butir karena satu paket bertahap bisa memuat lebih dari satu alat tes'],
                            ['field' => 'Detail[].Nama_Alat_Tes', 'type' => 'string', 'required' => false, 'description' => 'Nama alat tesnya, untuk ditampilkan apa adanya di layar hasil per kompetensi'],
                            ['field' => 'Detail[].Status_Interpretation', 'type' => 'string', 'required' => false, 'description' => 'Tafsir Anda atas skor butir itu, misalnya Low atau High. HCIS menampilkannya berdampingan dengan tafsir pita nilai kami — bila keduanya berbeda, perbedaannya justru yang perlu terlihat, bukan disembunyikan'],
                            ['field' => 'Detail[].Warna', 'type' => 'string', 'required' => false, 'description' => 'Warna heks butir itu. Disimpan terpisah dari penilaian.Warna: satu milik butir, satu milik keseluruhan, dan keduanya bisa berbeda'],
                            ['field' => 'Detail[].Keterangan', 'type' => 'string', 'required' => false, 'description' => 'Penjelasan pendek kompetensi atau jenis soalnya. Ditampilkan sebagai keterangan asal dari Anda'],
                            ['field' => 'Detail[].Jumlah_Soal', 'type' => 'integer', 'required' => false, 'description' => 'Berapa soal yang ada pada butir itu'],
                            ['field' => 'Detail[].Jumlah_Terjawab', 'type' => 'integer', 'required' => false, 'description' => 'Berapa yang benar-benar dijawab. TIDAK BOLEH melebihi Jumlah_Soal — bila melebihi, kiriman dijawab 422, sebab terjawab lebih banyak daripada yang ditanyakan berarti salah satu dari kedua angka itu bukan angka yang Anda maksud'],
                        ],
                        'sample_payload' => [
                            'Kode_Karyawan' => 'DEMO-001',
                            'Id_Integrasi' => 9001,
                            'Api_Reference' => '0586e3606786d74a862e4b2e307428e1',
                            'Id_Ujian_Nilai_Akhir' => '0586e3606786d74a862e4b2e307428e1-PA360-FINAL',
                            'Api_Version' => 'pa-v1',
                            'Status_Skor' => 'FINAL',
                            'Total_Nilai' => 82.5,
                            'kode_akses' => 'PA360',
                            'is_multi' => false,
                            'penilaian' => [
                                'Nama_Tes' => 'Employee Assessment 360 - STAFF',
                                'Waktu_Mulai_Akses' => '2026-09-10 09:12:44',
                                'Waktu_Selesai_Akses' => '2026-09-10 09:41:03',
                                'Ambang_Batas_Nilai' => 75,
                                'Status_Kelulusan' => 'Lulus',
                                'Warna' => '#21b080',
                            ],
                            'Detail' => [
                                ['Kode_Soal' => 'PA-STAFF-2027-Q01', 'Kode_Alat_Tes' => 'PA360', 'Nama_Alat_Tes' => 'Employee Assessment 360 - STAFF', 'Jenis_Soal' => 'SKALA', 'Skor' => 4, 'Skor_Maksimal' => 5, 'Bobot_Penilaian' => 1, 'Jawaban_Teks' => null, 'Status_Interpretation' => 'High', 'Warna' => '#21b080', 'Keterangan' => 'Kualitas kerja', 'Jumlah_Soal' => 5, 'Jumlah_Terjawab' => 5],
                                ['Kode_Soal' => 'PA-STAFF-2027-Q02', 'Kode_Alat_Tes' => 'PA360', 'Nama_Alat_Tes' => 'Employee Assessment 360 - STAFF', 'Jenis_Soal' => 'SKALA', 'Skor' => 5, 'Skor_Maksimal' => 5, 'Bobot_Penilaian' => 1, 'Jawaban_Teks' => null, 'Status_Interpretation' => 'High', 'Warna' => '#21b080', 'Keterangan' => 'Kerja sama', 'Jumlah_Soal' => 5, 'Jumlah_Terjawab' => 5],
                                ['Kode_Soal' => 'PA-STAFF-2027-Q03', 'Kode_Alat_Tes' => 'PA360', 'Nama_Alat_Tes' => 'Employee Assessment 360 - STAFF', 'Jenis_Soal' => 'ESAI', 'Skor' => null, 'Skor_Maksimal' => null, 'Bobot_Penilaian' => null, 'Jawaban_Teks' => 'Konsisten menyelesaikan pekerjaan tepat waktu.', 'Status_Interpretation' => null, 'Warna' => null, 'Keterangan' => 'Catatan atasan', 'Jumlah_Soal' => 1, 'Jumlah_Terjawab' => 1],
                            ],
                        ],
                        'expected_response' => [
                            'success' => [
                                'http_status' => 200,
                                'description' => 'BILA KIRIMAN INI MENUNGGU_VERIFIKASI, HCIS hanya mencatat bahwa penilai sudah mengirim: penugasannya TIDAK ditandai selesai, tidak ikut menggenapi kelengkapan peserta, dan tidak memicu perhitungan apa pun — tetapi pengingat untuknya BERHENTI, karena ia memang sudah mengerjakan bagiannya. Bidang pemetaan di bawah tidak ada pada balasan itu. Selebihnya berlaku untuk kiriman FINAL. HCIS menyimpan kiriman apa adanya lalu langsung memetakannya menjadi baris skor. Yang dilaporkan balik bukan sekadar "berhasil": tak_terpetakan memuat Kode_Soal yang tidak kami kenali, dan butir_hilang memuat butir paket yang tidak Anda kirim. Selama salah satunya berisi, kontrak_lengkap bernilai false dan penugasan itu TIDAK ditandai selesai — nilai peserta tidak akan pernah terhitung. Mohon periksa dua daftar ini, jangan berhenti pada status 200. Kiriman ulang yang isinya persis sama juga dijawab 200 dengan baru bernilai false; itu bukan galat dan tidak membuat baris kedua — dan pada balasan itu kelima bidang pemetaan di bawah memang TIDAK ADA, karena pemetaannya tidak dijalankan ulang. Itu disengaja: Anda sudah menerima jawaban lengkapnya pada kiriman pertama, dan mengarang angka supaya bentuknya terlihat utuh jauh lebih buruk daripada bidang yang jujur absen.',
                                'fields' => [
                                    ['field' => 'result.assignment_id', 'type' => 'integer', 'required' => false, 'description' => 'Nomor penugasan internal HCIS. SENGAJA berbeda dari Id_Integrasi yang Anda kirim — satu penugasan bisa memiliki beberapa baris integrasi. Jangan dipakai sebagai Id_Integrasi pada kiriman berikutnya'],
                                    ['field' => 'result.baru', 'type' => 'boolean', 'required' => true, 'description' => 'false bila kiriman dengan isi yang persis sama sudah pernah diterima'],
                                    ['field' => 'result.status_skor', 'type' => 'string', 'required' => true, 'description' => 'Status yang BENAR-BENAR dipakai HCIS, bukan sekadar gema dari yang Anda kirim. Berguna saat menelusuri: bila yang terbaca di sini bukan yang Anda maksud, ketahuannya pada kiriman itu juga — bukan berminggu-minggu kemudian saat nilai peserta tidak kunjung keluar'],
                                    ['field' => 'result.butir', 'type' => 'integer', 'required' => false, 'description' => 'Berapa butir yang tersimpan dari kiriman ini'],
                                    ['field' => 'result.tak_terpetakan', 'type' => 'array of string', 'required' => false, 'description' => 'Kode_Soal yang tidak ada di paket kami. Isi yang tidak kosong berarti kode butir Anda dan kami berbeda'],
                                    ['field' => 'result.butir_hilang', 'type' => 'array of string', 'required' => false, 'description' => 'Butir paket yang tidak Anda kirim. Isi yang tidak kosong berarti kiriman belum utuh'],
                                    ['field' => 'result.kontrak_lengkap', 'type' => 'boolean', 'required' => false, 'description' => 'true hanya bila seluruh butir paket hadir. Hanya nilai true yang membuat penugasan ditandai selesai dan nilai peserta mulai dihitung'],
                                ],
                                'sample' => [
                                    'success' => true,
                                    'status' => 200,
                                    'message' => 'Hasil penilaian diterima dan dipetakan.',
                                    'result' => [
                                        'payload_id' => 5521,
                                        'assignment_id' => 835,
                                        'baru' => true,
                                        'butir' => 3,
                                        'berskor' => 2,
                                        'tak_terpetakan' => [],
                                        'butir_hilang' => [],
                                        'kontrak_lengkap' => true,
                                    ],
                                ],
                            ],
                            'error' => [
                                'http_status' => 422,
                                'description' => 'TIMEOUT BUKAN KEGAGALAN, DAN INI PALING PENTING. Bila permintaan Anda kehabisan waktu tanpa jawaban, kiriman itu MUNGKIN SUDAH TERSIMPAN di sisi kami — yang hilang jawabannya, bukan datanya. Kirim ulang dengan isi yang sama persis; kami mengenalinya dari sidik isi dan menjawab 200 tanpa membuat baris kedua. Yang berbahaya justru sebaliknya: menganggap timeout sebagai gagal permanen lalu membuang hasilnya berarti penilaian yang sudah dikerjakan orang hilang tanpa jejak. Ingat menerbitkan X-HC-Nonce BARU pada tiap percobaan — nonce yang berulang ditolak 401, dan itu akan terbaca seperti masalah kredensial padahal bukan. Selebihnya, kiriman ditolak seluruhnya dan pesannya selalu menyebut butir yang bermasalah. Yang menyebabkan 422: Detail kosong atau tidak ada; ada butir tanpa Kode_Soal; satu Kode_Soal muncul dua kali dalam satu kiriman (butir kembar akan terhitung dua kali); Skor terisi tetapi Skor_Maksimal kosong atau nol; Skor melebihi Skor_Maksimal; atau Id_Ujian_Nilai_Akhir yang sama pernah dipakai untuk isi yang berbeda. Semuanya soal bentuk kiriman, bukan soal jaringan — mencoba ulang tanpa memperbaiki isi akan ditolak dengan pesan yang sama. Selain itu: 404 bila token tidak dikenali, dan 422 bila penugasannya sudah dibatalkan.',
                                'sample' => [
                                    'success' => false,
                                    'status' => 422,
                                    'message' => 'Butir PA-STAFF-2027-Q02 pada payload 5521 berskor 6 melebihi Skor_Maksimal 5.',
                                ],
                            ],
                        ],
                        'retry_policy' => [
                            'max_attempts' => 3,
                            'backoff' => 'Exponential backoff (30s, 2m, 10m) — dengan X-HC-Nonce baru tiap percobaan',
                            'retryable_codes' => 'HTTP 500, 502, 503, 504, Timeout',
                            'non_retryable_codes' => 'HTTP 400, 401, 403, 404, 422',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        foreach ($this->all() as $contract) {
            if ($contract['slug'] === $slug) {
                return $contract;
            }
        }

        return null;
    }

    /**
     * @return array<string>
     */
    public function targetSystems(): array
    {
        $systems = array_column($this->all(), 'target_system');

        return array_values(array_unique($systems));
    }

    /**
     * @return array<string>
     */
    public function categories(): array
    {
        $cats = array_column($this->all(), 'category');

        return array_values(array_unique($cats));
    }

    /**
     * Kontrak beserta tautan bertanda tangannya, untuk portal internal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function semuaDenganTautan(): array
    {
        return array_map(
            fn (array $kontrak) => $kontrak + [
                'viewer_url' => $this->tautanViewer($kontrak['slug']),
                'json_url' => $this->tautanJson($kontrak['slug']),
            ],
            $this->all(),
        );
    }

    public function tautanViewer(string $slug): string
    {
        return $this->tandaTangani('system.integration.contracts.show', $slug);
    }

    public function tautanJson(string $slug): string
    {
        return $this->tandaTangani('system.integration.contracts.json', $slug);
    }

    /**
     * Satu-satunya tempat yang tahu cara membuat tautan viewer.
     *
     * Dikumpulkan di sini karena tanda tangan yang dirakit tangan di beberapa
     * tempat adalah cara paling mudah melahirkan tautan yang 403 tanpa ada yang
     * menyadarinya sampai vendor mengeluh.
     */
    private function tandaTangani(string $namaRute, string $slug): string
    {
        $hari = config('kontrak_outbound.tautan_berlaku_hari');

        // null = tanpa kedaluwarsa. Lihat config/kontrak_outbound.php untuk
        // alasan kenapa itu bakunya.
        if ($hari === null || $hari === '') {
            return URL::signedRoute($namaRute, ['slug' => $slug]);
        }

        return URL::temporarySignedRoute($namaRute, now()->addDays((int) $hari), ['slug' => $slug]);
    }
}
