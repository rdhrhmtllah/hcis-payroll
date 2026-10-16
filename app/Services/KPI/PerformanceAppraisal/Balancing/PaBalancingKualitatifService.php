<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\PaParticipantReadService;

/**
 * Bahan modal kualitatif di board Balancing: untuk setiap kualitatif yang dibahas
 * (PaBalancingSesiService::KUALITATIF_BALANCING), soal yang diajukan kepada penilai orang itu
 * dan jawaban tiap penilai per soal. Hanya-baca; rangkuman admin (Before) sudah ada di sesi.
 */
class PaBalancingKualitatifService
{
    public function __construct(private readonly PaParticipantReadService $baca) {}

    /** @return list<array<string, mixed>> */
    public function untukPeserta(int $participantId): array
    {
        ['pertanyaan' => $pertanyaan, 'jawaban' => $jawaban] = $this->baca->kualitatifPeserta($participantId);

        $hasil = [];

        foreach ($pertanyaan as $b) {
            $kode = (string) $b['Kode_Competency'];

            if (! in_array($kode, PaBalancingSesiService::KUALITATIF_BALANCING, true)) {
                continue;
            }

            $hasil[$kode] ??= ['kode' => $kode, 'nama' => (string) $b['Nama_Competency'], 'kode_soal' => [], 'diminta' => 0, 'soal' => []];
            $hasil[$kode]['kode_soal'][] = (string) $b['Kode_Soal'];
            $hasil[$kode]['diminta'] += (int) $b['diminta'];
        }

        foreach ($hasil as $kode => &$k) {
            foreach ($jawaban as $j) {
                if (! in_array((string) ($j['Kode_Soal_Sumber'] ?? ''), $k['kode_soal'], true)) {
                    continue;
                }

                $teks = trim((string) ($j['Jawaban_Teks'] ?? ''));

                if ($teks === '') {
                    continue;
                }

                // Soal tanpa bunyi (format lama) dikumpulkan di bawah nama bagiannya.
                $soal = trim((string) ($j['Pertanyaan'] ?? '')) ?: $k['nama'];
                $k['soal'][$soal] ??= ['pertanyaan' => $soal, 'jawaban' => []];
                $k['soal'][$soal]['jawaban'][] = [
                    'kategori' => $j['Nama_Kategori'] ?? null,
                    'kode_kategori' => $j['Kode_Kategori'] ?? null,
                    'penilai' => $j['Nama_Penilai_Saat_Cycle'] ?? null,
                    'teks' => $teks,
                ];
            }

            $k['soal'] = array_values($k['soal']);
            // Nama penilai bisa kembar, jadi yang dihitung soal dengan jawaban terbanyak: tiap penilai menjawab satu soal sekali.
            $k['penjawab'] = $k['soal'] === [] ? 0 : max(array_map(fn ($s) => count($s['jawaban']), $k['soal']));
            unset($k['kode_soal']);
        }
        unset($k);

        return array_values($hasil);
    }
}
