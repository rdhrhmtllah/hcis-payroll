import { describe, it, expect } from "vitest";
import { readFileSync } from "node:fs";
import path from "node:path";

/**
 * Kit gambar harus netral terhadap modul.
 *
 * Tooltip heatmap pernah menulis "Jumlah Kedatangan: N Tamu" dan judul
 * "Pukul HH:00 WIB" secara mati — kalimat milik SATU pemakai (peta jam sibuk
 * tamu Visitor). Kalimat itu ikut terbawa ke setiap heatmap lain: peta capaian
 * KPI membacakan skor karyawan sebagai "102 Tamu", dan peta aktivitas SOP
 * menyebut jumlah bacaan SOP sebagai tamu juga. Sumbu X-nya pun dipaksa
 * berbentuk jam, sehingga nama departemen tampil sebagai "Produksi:00".
 *
 * Penjaga ini membaca SUMBER komponen: kata khas modul tidak boleh muncul di
 * sana. Yang bergantung pada modul harus datang lewat prop (valueLabel,
 * valueSuffix, categoryUnit) atau tooltipFormatter milik pemanggil.
 */
describe("HcisChart tetap netral terhadap modul", () => {
    const sumber = readFileSync(path.resolve(process.cwd(), "resources/js/components/ui/HcisChart.vue"), "utf8");

    // Bagian kode saja: blok komentar justru MENJELASKAN sejarah di atas.
    const kode = sumber
        .replace(/\/\*[\s\S]*?\*\//g, "")
        .split("\n")
        .filter((b) => !b.trim().startsWith("//") && !b.trim().startsWith("*"))
        .join("\n");

    it.each(["Tamu", "Kedatangan", "Karyawan", "SOP"])(
        "tidak menyebut '%s' di luar komentar",
        (kata) => {
            expect(kode).not.toMatch(new RegExp(`\\b${kata}\\b`));
        },
    );

    it("menyediakan prop untuk kalimat yang bergantung modul", () => {
        expect(kode).toMatch(/valueLabel:\s*\{[^}]*default:\s*'Nilai'/);
        expect(kode).toMatch(/valueSuffix:\s*\{[^}]*default:\s*''/);
        expect(kode).toMatch(/categoryUnit:\s*\{[^}]*default:\s*''/);
    });

    /**
     * 'WIB' boleh ada, tapi HANYA pada cabang jam. Ia keterangan waktu, bukan
     * istilah modul — yang salah dulu adalah memakainya untuk sumbu X apa pun.
     */
    it("menyebut zona waktu hanya saat sumbu X memang jam", () => {
        kode.split("\n")
            .filter((b) => b.includes("WIB"))
            .forEach((b) => expect(b).toMatch(/perJam \?/));
    });

    it("tidak memaksa sumbu X heatmap berbentuk jam saat satuannya tidak disebut", () => {
        // Padanan HH:00 hanya boleh terjadi di balik penjaga perJam.
        const padanan = kode.match(/padStart\(2, '0'\)\}:00/g) || [];
        expect(padanan).toHaveLength(1);
        expect(kode).toMatch(/if \(!perJam\) return s;/);
    });
});
