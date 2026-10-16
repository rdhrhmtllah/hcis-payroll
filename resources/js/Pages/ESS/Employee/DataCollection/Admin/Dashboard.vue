<template>
    <div class="adm">
        <HcisHeader
            title="Pembaruan Data Karyawan"
            description="Pantau siapa yang sudah mengisi, baca jawabannya, dan urus pengecualian data."
            subtitle="Modul Admin HR"
            icon="bi bi-shield-lock-fill"
        >
            <template #actions>
                <!--
                    Keadaan dibacakan SATU kali, oleh lencana. Sakelar dengan
                    dua teks menampilkan kata "dimatikan" juga saat kampanyenya
                    menyala — sekilas terbaca seperti kampanye sedang mati.
                -->
                <div class="adm__sakelar">
                    <HcisStatusBadge
                        :status="sakelarAktif ? 'success' : 'warning'"
                        :label="sakelarAktif ? 'Kampanye menyala' : 'Kampanye dimatikan'"
                        size="sm"
                        dot
                    />
                    <HcisSwitch
                        :model-value="sakelarAktif"
                        :disabled="mengubahSakelar"
                        :loading="mengubahSakelar"
                        aria-label="Sakelar kampanye pengumpulan data"
                        data-uji="sakelar"
                        @update:model-value="ubahSakelar"
                    />
                </div>
            </template>
        </HcisHeader>

        <HcisAlert
            v-if="!sakelarAktif"
            variant="warning"
            appearance="soft"
            icon="bi bi-exclamation-triangle-fill"
            title="Kampanye dimatikan"
            class="adm__catatan"
        >
            Tidak ada karyawan yang melihat ajakan, dan formulirnya tertutup. Halaman ini tetap bisa dipakai
            membaca dan mengekspor data yang sudah masuk.
        </HcisAlert>

        <!--
            Cakupan diletakkan bersebelahan dengan sakelar, BUKAN di dalam tabel.
            Ia menentukan siapa yang wajib mengisi — kalau ditaruh di antara
            saringan tabel, ia akan terbaca sebagai pengatur tampilan dan
            digeser orang yang mengira tidak sedang mengubah apa pun.
        -->
        <HcisCard
            title="Cakupan pembukaan"
            icon="bi bi-geo-alt-fill"
            size="sm"
            class="adm__cakupan"
        >
            <template #actions>
                <HcisStatusBadge
                    :status="cakupanTersimpan.length ? 'info' : 'neutral'"
                    :label="cakupanTersimpan.length ? `${cakupanTersimpan.length} lokasi dibuka` : 'Seluruh lokasi'"
                    size="sm"
                />
            </template>

            <p class="adm__cakupan-petunjuk">
                Centang lokasi kerja yang pembaruan datanya dibuka. Yang tidak dicentang tidak dihitung,
                tidak melihat ajakan, dan tidak dikirimi pengingat.
                <strong>Tanpa satu pun centang, kampanye terbuka untuk seluruh lokasi.</strong>
            </p>

            <div class="adm__cakupan-pilihan">
                <HcisCheckbox
                    v-for="lokasi in pilihanLokasi"
                    :key="lokasi"
                    v-model="cakupanPilihan"
                    :value="lokasi"
                    :label="lokasi"
                    variant="button"
                    size="sm"
                    :disabled="menyimpanCakupan"
                />

                <span v-if="!pilihanLokasi.length" class="adm__cakupan-kosong">
                    Tidak ada lokasi kerja yang terbaca dari data karyawan aktif.
                </span>
            </div>

            <div class="adm__cakupan-aksi">
                <HcisButton
                    variant="primary"
                    size="sm"
                    :label="menyimpanCakupan ? 'Menyimpan…' : 'Simpan cakupan'"
                    leading-icon="bi bi-check2-circle"
                    :disabled="!cakupanBerubah || menyimpanCakupan"
                    :loading="menyimpanCakupan"
                    @click="simpanCakupan"
                />
                <HcisButton
                    v-if="cakupanBerubah"
                    variant="ghost"
                    size="sm"
                    label="Batalkan perubahan"
                    :disabled="menyimpanCakupan"
                    @click="cakupanPilihan = [...cakupanTersimpan]"
                />
                <span v-if="cakupanPilihan.length === 0 && cakupanTersimpan.length > 0" class="adm__cakupan-catatan">
                    Menyimpan tanpa centang membuka kampanye untuk semua orang.
                </span>
            </div>

            <!--
                Karyawan tanpa lokasi kerja tidak pernah masuk cakupan mana pun,
                dan karena ia juga hilang dari daftar, kehilangannya tak terlihat
                oleh siapa pun. Angka ini satu-satunya tempat ia bersuara.
            -->
            <HcisAlert
                v-if="cakupanTersimpan.length && ringkasanKini.di_luar_cakupan?.tanpa_lokasi"
                variant="warning"
                appearance="soft"
                icon="bi bi-question-circle-fill"
                title="Ada karyawan tanpa lokasi kerja"
                class="adm__cakupan-peringatan"
            >
                {{ ringkasanKini.di_luar_cakupan.tanpa_lokasi }} karyawan aktif tidak punya lokasi kerja di
                master data, jadi mereka berada di luar cakupan mana pun dan tidak muncul di daftar.
                Lengkapi Cabang Untuk Gaji mereka supaya bisa ikut dibuka.
            </HcisAlert>
        </HcisCard>

        <!--
            'auto' bukan 5: kontrak HcisStatGroup hanya mengenal 1-4 atau 'auto',
            dan angka di luar itu jatuh ke satu kolom — lima kartu bertumpuk ke
            bawah memenuhi layar. 'auto' memuat kelimanya berjajar di layar lebar
            lalu melipat sendiri di layar sempit.
        -->
        <HcisStatGroup :stats="statistik" cols="auto" class="adm__stat" />

        <HcisCard title="Progres pengumpulan data" icon="bi bi-pie-chart-fill" size="sm" class="adm__progres">
            <template #actions>
                <span class="adm__progres-persen">{{ ringkasanKini.persen }}% selesai</span>
            </template>

            <HcisProgress
                :value="ringkasanKini.persen"
                sentiment="brand"
                size="lg"
                :label="`${ringkasanKini.sudah} dari ${ringkasanKini.total} karyawan`"
            />
        </HcisCard>

        <HcisCard size="sm" class="adm__alat">
            <div class="adm__alat-baris">
                <HcisButton
                    variant="secondary"
                    :label="mengekspor ? 'Menyiapkan…' : 'Ekspor Excel'"
                    leading-icon="bi bi-file-earmark-excel-fill"
                    :disabled="mengekspor"
                    :loading="mengekspor"
                    @click="mintaEkspor('xlsx')"
                />
                <HcisButton
                    variant="secondary"
                    label="Ekspor CSV"
                    leading-icon="bi bi-file-earmark-text-fill"
                    :disabled="mengekspor"
                    @click="mintaEkspor('csv')"
                />
                <HcisButton
                    variant="primary"
                    :label="mengirimPengingat ? 'Mengirim…' : `Kirim pengingat ke ${ringkasanKini.belum} orang`"
                    leading-icon="bi bi-send-fill"
                    :disabled="mengirimPengingat"
                    :loading="mengirimPengingat"
                    @click="kirimPengingat"
                />
                <HcisButton
                    v-if="tautanUnduh"
                    variant="success"
                    label="Unduh berkas ekspor"
                    leading-icon="bi bi-download"
                    :href="tautanUnduh"
                />
                <span v-if="pesanAlat" class="adm__alat-pesan">{{ pesanAlat }}</span>
            </div>
        </HcisCard>

        <HcisTabs :model-value="tab" :tabs="tabsUntukKit" class="adm__tab" @update:model-value="pilihTab" />

        <div class="adm__isi">
            <TabelKaryawan
                v-show="tab === 'daftar'"
                ref="tabel"
                :awal="daftar"
                :pilihan-departemen="pilihanDepartemen"
                :pilihan-lokasi="pilihanLokasi"
                @detail="bukaDetail"
                @buka-kunci="mintaBukaKunci"
                @pengecualian="ubahPengecualian"
            />

            <HcisCard v-if="tab === 'departemen'" title="Progres per departemen" icon="bi bi-building" size="sm">
                <div v-for="d in ringkasanKini.per_departemen" :key="d.departemen" class="adm__dept-baris">
                    <span class="adm__dept-nama">{{ d.departemen }}</span>
                    <HcisProgress
                        :value="d.total ? Math.round((d.sudah / d.total) * 100) : 0"
                        sentiment="brand"
                        size="sm"
                        class="adm__dept-bar"
                    />
                    <span class="adm__dept-angka">{{ d.sudah }} / {{ d.total }}</span>
                </div>
            </HcisCard>

            <HcisCard v-if="tab === 'kualitas'" title="Kualitas data" icon="bi bi-clipboard2-check" size="sm">
                <HcisSkeleton v-if="memuatKualitas" :lines="3" />

                <HcisEmptyState
                    v-else-if="!kualitas.length"
                    icon="bi bi-shield-check"
                    sentiment="success"
                    title="Tidak ada kejanggalan"
                    description="Semua NIK, email, dan nomor HP unik dan berbentuk benar."
                />

                <HcisAlert
                    v-for="(t, i) in kualitas"
                    v-else
                    :key="i"
                    variant="warning"
                    appearance="soft"
                    :title="`${labelTemuan(t.jenis)} — ${t.nilai}`"
                    class="adm__temuan"
                >
                    {{ t.karyawan.map((k) => `${k.nama} (${k.kode})`).join(' · ') }}
                </HcisAlert>
            </HcisCard>

            <HcisCard v-if="tab === 'audit'" title="Jejak tindakan admin" icon="bi bi-clock-history" size="sm">
                <HcisSkeleton v-if="memuatAudit" :lines="3" />

                <HcisEmptyState
                    v-else-if="!audit.length"
                    icon="bi bi-clock-history"
                    title="Belum ada tindakan admin yang tercatat"
                />

                <HcisTimeline v-else :items="jejakAudit" />
            </HcisCard>
        </div>

        <PanelDetail v-if="detail" :detail="detail" :skema="skema" @tutup="detail = null" />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import {
    HcisAlert,
    HcisButton,
    HcisCard,
    HcisCheckbox,
    HcisEmptyState,
    HcisHeader,
    HcisProgress,
    HcisSkeleton,
    HcisStatGroup,
    HcisStatusBadge,
    HcisSwitch,
    HcisTabs,
    HcisTimeline,
} from '@/components/ui';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import TabelKaryawan from './components/TabelKaryawan.vue';
import PanelDetail from './components/PanelDetail.vue';

const props = defineProps({
    ringkasan: { type: Object, required: true },
    daftar: { type: Object, required: true },
    pilihanDepartemen: { type: Array, default: () => [] },
    pilihanLokasi: { type: Array, default: () => [] },
    skema: { type: Object, required: true },
    pengumuman: { type: Object, required: true },
});

const tabs = [
    { kunci: 'daftar', label: 'Daftar Karyawan' },
    { kunci: 'departemen', label: 'Per Departemen' },
    { kunci: 'kualitas', label: 'Kualitas Data' },
    { kunci: 'audit', label: 'Jejak Audit' },
];

/**
 * Bentuk data disesuaikan dengan kontrak komponen bersama — bukan komponennya
 * yang ditambal agar menerima bentuk halaman ini.
 */
const statistik = computed(() => [
    { key: 'total', label: 'Karyawan Aktif', value: ringkasanKini.value.total, icon: 'bi bi-people-fill' },
    { key: 'sudah', label: 'Sudah Mengisi', value: ringkasanKini.value.sudah, icon: 'bi bi-check-circle-fill', variant: 'success' },
    { key: 'belum', label: 'Belum Mengisi', value: ringkasanKini.value.belum, icon: 'bi bi-hourglass-split', variant: 'warning' },
    { key: 'terkunci', label: 'Terkunci', value: ringkasanKini.value.terkunci, icon: 'bi bi-lock-fill', variant: 'danger' },
    { key: 'dikecualikan', label: 'Dikecualikan', value: ringkasanKini.value.dikecualikan, icon: 'bi bi-slash-circle-fill' },
]);

// HcisTabs mengenali tabnya lewat 'key', bukan 'value'. Memberi 'value' saja
// membuat setiap tab ber-key undefined: yang tersorot tab terakhir, dan isi
// tabnya kosong karena tak ada kunci yang cocok.
const tabsUntukKit = computed(() =>
    tabs.map((t) => ({ key: t.kunci, label: t.label, icon: ikonTab(t.kunci) })),
);

const jejakAudit = computed(() =>
    audit.value.map((a) => ({
        id: a.id,
        title: `${a.oleh} ${labelAksi(a.aksi)}${a.nama_target ? ` pada ${a.nama_target}` : ''}`,
        description: a.keterangan ? `"${a.keterangan}"` : '',
        time: a.pada,
        icon: 'bi bi-person-gear',
    })),
);

const tab = ref('daftar');
const tabel = ref(null);
const detail = ref(null);
const ringkasanKini = ref({ ...props.ringkasan });
const sakelarAktif = ref(props.ringkasan.kampanye_aktif);
const mengubahSakelar = ref(false);

const cakupanTersimpan = ref([...(props.ringkasan.cakupan_lokasi ?? [])]);
const cakupanPilihan = ref([...cakupanTersimpan.value]);
const menyimpanCakupan = ref(false);

/** Urutan centang tidak boleh terbaca sebagai perubahan. */
const sidikCakupan = (daftar) => [...daftar].sort().join('|');

const cakupanBerubah = computed(
    () => sidikCakupan(cakupanPilihan.value) !== sidikCakupan(cakupanTersimpan.value),
);

const kualitas = ref([]);
const memuatKualitas = ref(false);
const audit = ref([]);
const memuatAudit = ref(false);

const segarkanRingkasan = async () => {
    const { data } = await axios.get('/admin/pengumpulan-data/ringkasan');
    ringkasanKini.value = data;
    sakelarAktif.value = data.kampanye_aktif;

    // Centang yang sedang disunting tidak ditimpa: penyegaran ini berjalan
    // sendiri, dan menghapus pekerjaan orang tanpa diminta adalah cara tercepat
    // membuat panel ini tidak dipercaya.
    if (!cakupanBerubah.value) {
        cakupanTersimpan.value = [...(data.cakupan_lokasi ?? [])];
        cakupanPilihan.value = [...cakupanTersimpan.value];
    }
};

const simpanCakupan = async () => {
    menyimpanCakupan.value = true;

    try {
        const { data } = await axios.post('/admin/pengumpulan-data/cakupan', {
            lokasi: cakupanPilihan.value,
        });

        cakupanTersimpan.value = [...(data.cakupan_lokasi ?? [])];
        cakupanPilihan.value = [...cakupanTersimpan.value];
        ringkasanKini.value = data.ringkasan;

        // Pilihan departemen ikut menyempit bersama cakupan, dan itu prop
        // Inertia — tanpa muat ulang, dropdown-nya menawarkan departemen yang
        // seluruh orangnya baru saja keluar dari kampanye. Barisnya sendiri
        // TIDAK ikut diminta di sini: tabel menyalin prop 'awal' sekali saat
        // lahir, jadi muat ulang prop itu hanya menambah satu kueri yang
        // hasilnya tidak pernah dipakai.
        router.reload({ only: ['pilihanDepartemen', 'pilihanLokasi'] });
        tabel.value?.muat(1);
    } finally {
        menyimpanCakupan.value = false;
    }
};

const pilihTab = async (kunci) => {
    tab.value = kunci;

    if (kunci === 'kualitas' && !kualitas.value.length && !memuatKualitas.value) {
        memuatKualitas.value = true;
        try {
            const { data } = await axios.get('/admin/pengumpulan-data/kualitas');
            kualitas.value = data.temuan;
        } finally {
            memuatKualitas.value = false;
        }
    }

    if (kunci === 'audit') {
        memuatAudit.value = true;
        try {
            const { data } = await axios.get('/admin/pengumpulan-data/audit');
            audit.value = data.items;
        } finally {
            memuatAudit.value = false;
        }
    }
};

const bukaDetail = async (baris) => {
    const { data } = await axios.get(`/admin/pengumpulan-data/${encodeURIComponent(baris.Kode_Karyawan)}`);
    detail.value = data;
};

const ubahSakelar = async () => {
    mengubahSakelar.value = true;

    try {
        const { data } = await axios.post('/admin/pengumpulan-data/sakelar', { aktif: !sakelarAktif.value });
        sakelarAktif.value = data.aktif;
    } finally {
        mengubahSakelar.value = false;
    }
};

const ubahPengecualian = async (baris) => {
    const jadiDikecualikan = baris.status !== 'DIKECUALIKAN';

    // Arah sebaliknya dulu tidak menanyakan apa pun: satu klik meleset
    // mengembalikan orang ke kampanye tanpa sepatah kata. Yang mengecualikan
    // sudah punya penahan berupa pertanyaan alasan; yang membatalkan belum.
    if (!jadiDikecualikan) {
        if (!window.confirm(`Ikutsertakan kembali ${baris.nama} dalam pembaruan data?`)) return;
    }

    const alasan = jadiDikecualikan
        ? window.prompt(`Alasan mengecualikan ${baris.nama}?`, '')
        : null;

    if (jadiDikecualikan && alasan === null) return;

    const { data } = await axios.post('/admin/pengumpulan-data/pengecualian', {
        kode_karyawan: baris.Kode_Karyawan,
        dikecualikan: jadiDikecualikan,
        alasan,
    });

    ringkasanKini.value = data.ringkasan;
    tabel.value?.muat(1);
};

const mintaBukaKunci = async (baris) => {
    const alasan = window.prompt(`Alasan membuka kunci ${baris.nama}? (wajib diisi)`, '');
    if (!alasan) return;

    const { data } = await axios.post('/admin/pengumpulan-data/buka-kunci', {
        kode_karyawan: baris.Kode_Karyawan,
        alasan,
    });

    ringkasanKini.value = data.ringkasan;
    tabel.value?.muat(1);
};

const mengekspor = ref(false);
const mengirimPengingat = ref(false);
const tautanUnduh = ref(null);
const pesanAlat = ref('');

const mintaEkspor = async (format) => {
    mengekspor.value = true;
    tautanUnduh.value = null;
    pesanAlat.value = '';

    try {
        const { data } = await axios.post('/admin/pengumpulan-data/ekspor', { format });
        const id = data.export_id;

        for (let percobaan = 0; percobaan < 60; percobaan += 1) {
            await new Promise((r) => setTimeout(r, 1500));

            const { data: progres } = await axios.get(`/admin/pengumpulan-data/ekspor/${id}/status`);

            if (progres.status === 'ready') {
                tautanUnduh.value = `/admin/pengumpulan-data/ekspor/${id}/unduh`;
                pesanAlat.value = 'Berkas siap diunduh.';

                return;
            }

            if (progres.status === 'failed') {
                pesanAlat.value = 'Ekspor gagal. Coba lagi atau periksa log.';

                return;
            }
        }

        pesanAlat.value = 'Ekspor masih diproses. Muat ulang halaman ini beberapa saat lagi.';
    } catch (e) {
        pesanAlat.value = 'Permintaan ekspor ditolak.';
    } finally {
        mengekspor.value = false;
    }
};

const kirimPengingat = async () => {
    if (!window.confirm(`Kirim pengingat ke ${ringkasanKini.value.belum} karyawan yang belum mengisi?`)) return;

    mengirimPengingat.value = true;
    pesanAlat.value = '';

    try {
        const { data } = await axios.post('/admin/pengumpulan-data/pengingat');
        pesanAlat.value = `Pengingat terkirim ke ${data.terkirim} karyawan.`;
    } catch (e) {
        pesanAlat.value = 'Pengingat gagal dikirim.';
    } finally {
        mengirimPengingat.value = false;
    }
};

const labelTemuan = (jenis) =>
    ({
        NIK_TIDAK_16_DIGIT: 'NIK bukan 16 digit',
        NIK_KEMBAR: 'NIK dipakai lebih dari satu orang',
        EMAIL_KEMBAR: 'Email dipakai lebih dari satu orang',
        HP_KEMBAR: 'Nomor HP dipakai lebih dari satu orang',
    })[jenis] ?? jenis;

const labelAksi = (aksi) =>
    ({
        BUKA_KUNCI: 'membuka kunci',
        TAMBAH_PENGECUALIAN: 'mengecualikan',
        HAPUS_PENGECUALIAN: 'membatalkan pengecualian',
        UBAH_SAKELAR: 'mengubah sakelar kampanye',
        UBAH_CAKUPAN: 'mengubah cakupan lokasi',
        UBAH_PENGUMUMAN: 'mengubah teks pengumuman',
        KIRIM_PENGINGAT: 'mengirim pengingat',
    })[aksi] ?? aksi;

const ikonTab = (k) =>
    ({
        daftar: 'bi bi-table',
        departemen: 'bi bi-bar-chart-line',
        kualitas: 'bi bi-shield-exclamation',
        audit: 'bi bi-clock-history',
    })[k] ?? 'bi bi-folder';

segarkanRingkasan().catch(() => {});
</script>

<style scoped>
.adm {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-5);
    padding: var(--hcis-space-6) var(--hcis-space-4);
}

.adm__sakelar {
    display: inline-flex;
    align-items: center;
    gap: var(--hcis-space-3);
}

.adm__cakupan-petunjuk {
    margin: 0 0 var(--hcis-space-3);
    font-size: var(--hcis-text-sm);
    color: var(--hcis-text-muted);
    line-height: 1.6;
}

.adm__cakupan-pilihan {
    display: flex;
    flex-wrap: wrap;
    gap: var(--hcis-space-2);
}

.adm__cakupan-kosong {
    font-size: var(--hcis-text-sm);
    color: var(--hcis-text-muted);
}

.adm__cakupan-aksi {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--hcis-space-3);
    margin-top: var(--hcis-space-4);
}

.adm__cakupan-catatan {
    font-size: var(--hcis-text-xs);
    color: var(--hcis-text-muted);
}

.adm__cakupan-peringatan {
    margin-top: var(--hcis-space-4);
}

.adm__progres-persen {
    font-size: var(--hcis-text-sm);
    font-weight: 700;
    color: var(--hcis-text-brand);
}

.adm__alat-baris {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--hcis-space-3);
}

.adm__alat-pesan {
    font-size: var(--hcis-text-xs);
    color: var(--hcis-text-muted);
}

.adm__dept-baris {
    display: grid;
    grid-template-columns: minmax(8rem, 1fr) minmax(0, 2fr) auto;
    align-items: center;
    gap: var(--hcis-space-3);
    padding: var(--hcis-space-2) 0;
}

.adm__dept-nama {
    font-size: var(--hcis-text-sm);
    font-weight: 600;
    color: var(--hcis-text-strong);
}

.adm__dept-angka {
    font-size: var(--hcis-text-xs);
    font-weight: 700;
    color: var(--hcis-text-muted);
    font-variant-numeric: tabular-nums;
}

.adm__temuan {
    margin-bottom: var(--hcis-space-3);
}

@media (max-width: 40rem) {
    .adm__dept-baris {
        grid-template-columns: minmax(0, 1fr) auto;
    }

    .adm__dept-bar {
        grid-column: 1 / -1;
    }
}
</style>
