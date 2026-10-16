<?php

namespace Tests\Support\VISITOR;

use App\Services\VISITOR\Permit\VehiclePermit\PermitCoklatTamuService;

/**
 * Menerbitkan permit COKLAT sebuah kunjungan sampai hidup di gerbang.
 *
 * Sejak permit COKLAT melewati approval, izinnya lahir dalam DUA langkah:
 * diajukan oleh PermitCoklatTamuService, lalu disetujui verifikator. Uji yang
 * hanya menjalankan langkah pertama tidak akan menemukan satu baris pun di
 * proyeksi gerbang — dan mudah salah dibaca sebagai "permitnya tidak terbit".
 *
 * Persetujuannya lewat PermitCoklatTamuService::setujuiOtomatis(), bukan
 * ApprovalService: validasi approver menuntut approver NYATA, dan alur milik
 * PIC uji berujung pada ROLE pool yang keanggotaannya berbeda-beda antar
 * environment. Yang ditiru di sini adalah BEKAS yang ditinggalkan persetujuan
 * final pada basis data — bukan wewenang siapa yang boleh menyetujui, yang
 * memang bukan urusan uji-uji ini.
 */
trait MenerbitkanPermitCoklat
{
    /** Ajukan permitnya saja; ia akan berhenti menunggu verifikator. */
    protected function ajukanPermitCoklat(string $noTransaksiTamu): ?string
    {
        return app(PermitCoklatTamuService::class)->sinkron($noTransaksiTamu);
    }

    /** Ajukan lalu setujui — permitnya hidup di gerbang setelah ini. */
    protected function terbitkanPermitCoklat(string $noTransaksiTamu, string $approver = 'A1'): string
    {
        $noPermit = $this->ajukanPermitCoklat($noTransaksiTamu);

        $this->assertNotNull(
            $noPermit,
            "Permit COKLAT untuk {$noTransaksiTamu} tidak diajukan sama sekali.",
        );

        $this->setujuiPermitCoklat($noPermit, $approver);

        return $noPermit;
    }

    /** Tutup alur persetujuan permitnya, lalu terbitkan ke proyeksi gerbang. */
    protected function setujuiPermitCoklat(string $noPermit, string $approver = 'A1'): void
    {
        app(PermitCoklatTamuService::class)->setujuiOtomatis($noPermit, $approver);
    }

    /** Nomor permit COKLAT milik satu kunjungan. */
    protected function nomorPermitCoklat(string $noTransaksiTamu): ?string
    {
        return app(PermitCoklatTamuService::class)->nomorPermitUntukTamu($noTransaksiTamu);
    }
}
