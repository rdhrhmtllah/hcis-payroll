<?php

namespace App\Http\Requests\VISITOR\Workflow\Approval;

use Illuminate\Foundation\Http\FormRequest;

class VisitorApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'catatan' => ['nullable', 'string', 'max:500'],
            'id_durasi_permit' => ['nullable', 'integer'],
            'answers' => ['nullable', 'array'],
            'answers.*.question_id' => ['required_with:answers', 'integer', 'distinct'],
            'answers.*.jawaban' => ['required_with:answers', 'string', 'max:20'],
            'answers.*.catatan' => ['nullable', 'string', 'max:500'],
            // Hasil inspeksi kendaraan yang dicatat verifikator pada permit
            // COKLAT. Nullable di sini, bukan required: approval bertahap
            // mengirim formulir yang sama pada setiap langkah, dan yang menuntut
            // kelengkapannya adalah penjaga di langkah FINAL — bukan validasi
            // payload, yang akan memblokir approver pertama tanpa sebab.
            'id_master_model' => ['nullable', 'integer', 'exists:N_EMI_VISITOR_Master_Vehicle_Model,Id_Vehicle_Model'],
            'id_master_warna' => ['nullable', 'integer', 'exists:N_EMI_VISITOR_Master_Vehicle_Color,Id_Master_Color'],
            'tahun_kendaraan' => ['nullable', 'integer', 'min:1900', 'max:'.((int) date('Y') + 1)],
            // Jendela berlaku permit tamu, ditetapkan verifikator saat inspeksi.
            // Nullable dengan alasan yang sama seperti hasil inspeksi di atas —
            // dan karena aturan isinya (berakhir > mulai, tanggal mulai tidak
            // mundur) milik service, bukan payload: jalur approval lain melewati
            // FormRequest ini sepenuhnya, dan aturan yang hanya ada di sini akan
            // meloloskan mereka tanpa satu pun pemeriksaan.
            'waktu_mulai_berlaku' => ['nullable', 'date'],
            'waktu_berakhir' => ['nullable', 'date'],
        ];
    }
}
