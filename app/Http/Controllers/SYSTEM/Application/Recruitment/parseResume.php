<?php

namespace App\Http\Controllers\SYSTEM\Application\Recruitment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class parseResume extends Controller
{
    //

    public function index()
    {
        $title = 'Post Resume';

        return inertia('SYSTEM/Application/Recruitment/parseResume', ['title' => $title]);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'cv' => 'required|file|mimes:pdf',
        ]);

        // Simpan file sementara
        $path = $request->file('cv')->store('temp-cvs');

        // Parse teks dari PDF
        $text = (new \Smalot\PdfParser\Parser)->parseFile(storage_path("app/{$path}"))->getText();

        // Prompt untuk OpenAI
        $prompt = "Berikan data berikut dalam JSON dari isi CV:\n".
            "- nama\n- email\n- alamat\n- hp atau telepon sebagai telepon\n- pendidikan terakhir digabung dengan degree nya apa dalam format indonesia contoh 'S1 Universitas Sriwijaya Jurusan Teknik Komputer' sebagai pendidikan\n- menurut mu orang di CV ini bergender apa jawab antara Male atau Female as gender\n- pengalaman kerja atau organisasi buat dalam poin poin dan buat penjelasan singkat tentang poin poin as pengalaman\n- Hard Skill ambil dalam bentuk poin poin tidak perlu dijelaskan as hardSkill\n- Soft Skill buat juga dalam bentuk poin poin tidak perlu dijelaskan as softSkill\n- sertifikasi buat dalam bentuk poin pon tidak perlu dijelaskan as  sertifikasi\n- penghargaan buat dalam poin poin tidak perlu dalam array tpi buat saja output nya dalam 1 string as penghargaan \n ";

        try {
            $response = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => 'Ekstrak data dari CV'],
                    ['role' => 'user', 'content' => $prompt."\n\n".$text],
                ],
                'temperature' => 0.2,
            ]);

            // Pastikan response sukses
            if (! $response->successful()) {
                throw new \Exception('OpenAI API error: HTTP '.$response->status());
            }

            $content = $response->json();

            // Ambil isi pesan dari response OpenAI
            $messageContent = $content['choices'][0]['message']['content'] ?? null;
            if (! $messageContent) {
                throw new \Exception('Response OpenAI tidak berisi konten yang valid.');
            }

            // Parse JSON hasil ekstraksi dari OpenAI
            $parsed = json_decode($messageContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON parse error: '.json_last_error_msg());
            }

            // $parsed = [
            //         "nama" => "RIDHO RAHMATULLAH",
            //         "email" => "rdh.rhmtllah@gmail.com",
            //         "alamat" => "Jl. H. Matnur No. 560 Rt. 07 Kel. Muara Enim Kec. Lubuklinggau Barat 1 Kota Lubuklinggau, Sumatera Selatan",
            //         "telepon" => "+6285269805413",
            //         "pendidikan" => "D3 Politeknik Negeri Sriwijaya Jurusan Teknik Komputer",
            //         "gender" => "Male",
            //         "pengalaman" => [
            //             "Internship at the South Sumatra Department of Industry - Palembang: Developed a web-based schedule management system. Served as a Full Stack Web Developer using Bootstrap for the frontend, Laravel for the backend, and MySQL as the database management system.",
            //             "IoT Competition KMIPN V - Surabaya: Built a real-time monitoring system for oxygen saturation and heart rate of ambulance patients. Developed a complete ecosystem including sensor devices, application servers, and user interfaces.",
            //             "Application Development for KPAD Sumsel - Palembang: Served as a Full Stack Developer and built a child abuse reporting app for South Sumatra using Tailwind CSS, Laravel, MySQL, and CPanel for seamless access."
            //         ],
            //         "hardSkill" => [
            //             "HTML",
            //             "CSS",
            //             "PHP",
            //             "Laravel",
            //             "Javascript",
            //             "Typescript",
            //             "React.js",
            //             "Node.js",
            //             "Next.js",
            //             "Tailwind",
            //             "Java",
            //             "IoT",
            //             "MySQL",
            //             "MongoDb",
            //             "Socket.io",
            //             "Docker",
            //             "Nginx",
            //             "VM",
            //             "Git"
            //         ],
            //         "softSkill" => [
            //             "Good Communication",
            //             "Problem Solving",
            //             "Time Management",
            //             "Collaboration skill"
            //         ],
            //         "sertifikasi" => [
            //             "Database Programmer National Certification"
            //         ],
            //         "penghargaan" => "4th place Internet of Things National Polytechnic Informatics Student Competition (KMIPN) 2023 Surabaya, 1st place National PCFest Documentary Video 2023 Jakarta.",
            //         "tempPath" => "temp-cvs/jK8gYIC3TG1DdNitmSBN6uBd76gyaABbZDkdLnXT.pdf"
            //     ];

            // dd($parsed);
            // Kirim response sukses
            return response()->json([
                'parsed' => $parsed,
                'tempPath' => $path,
            ]);
        } catch (\Exception $e) {
            // Jika terjadi error, hapus file sementara agar tidak menumpuk
            Storage::delete($path);

            // Log error dan kirimkan respons error ke client
            Log::channel('openaiError')->warning('Openai', [
                'error' => $response,
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Gagal memproses CV: '.$e->getMessage(),
            ], 500);
        }
    }

    public function submit(Request $request)
    {
        $validation = $request->validate([
            'temp_path' => 'required|string',
            'nama' => 'required',
            'email' => 'required|email',
            'telepon' => 'required',
            'alamat' => 'required',
            'pendidikan' => 'required',
            'gender' => 'required',
        ]);

        $newPath = str_replace('temp-cvs/', 'cvs/', $request->temp_path);
        // dd($validation);
        // Storage::move($request->temp_path, $newPath);
        // \App\Models\CalonKaryawan::create([
        //     'nama' => $request->nama,
        //     'email' => $request->email,
        //     'telepon' => $request->telepon,
        //     'alamat' => $request->alamat,
        //     'hp' => $request->hp,
        //     'nik' => $request->nik,
        //     'pendidikan' => $request->pendidikan,
        //     'npwp' => $request->npwp,
        //     'cv_path' => $newPath,
        // ]);

        return response()->json(['success' => true]);
    }
}
