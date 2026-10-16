<template>
    <div v-if="section === 'anatomy'" class="hcis-stack">
        <div class="p-3 bg-amber-50 border border-amber-200 rounded-3 text-amber-900 text-xs">
            <strong>⚠️ Komponen Usang / Dipensiunkan:</strong> <code>el-dialog</code> sudah dipensiunkan di seluruh HCIS. Harap gunakan <code>&lt;HcisDialog&gt;</code> untuk modal terstruktur atau modul imperatif <code>hcisDialog.confirm() / alert() / prompt()</code>.
        </div>
        <div class="hcis-row">
            <HcisButton label="Buka Dialog Form Isian" variant="primary" @click="formDialog = true">
                <template #leading><span aria-hidden="true">✏️</span></template>
            </HcisButton>

            <HcisButton label="Buka Offcanvas Bottom Drawer" variant="secondary" @click="bottomDrawer = true">
                <template #leading><span aria-hidden="true">📱</span></template>
            </HcisButton>

            <HcisButton label="Buka Dialog Konfirmasi Hapus" variant="danger-quiet" @click="hapusDialog = true">
                <template #leading><span aria-hidden="true">🗑️</span></template>
            </HcisButton>
        </div>

        <!-- Custom Owned HcisDialog Form Isian -->
        <HcisDialog
            v-model="formDialog"
            title="Ubah Periode & Parameter KPI"
            description="Perbarui parameter evaluasi kinerja kuartal"
            size="md"
            sentiment="primary"
            icon="pencil-square"
        >
            <div class="hcis-stack" style="gap: var(--hcis-space-3)">
                <HcisAlert
                    variant="info"
                    title="Perhatian Periode Penilaian"
                    description="Perubahan periode akan memperbarui seluruh bobot dan kalkulasi skor KPI secara langsung."
                />

                <HcisField
                    id="ds-dlg-periode"
                    v-model="periodeJudul"
                    label="Nama Periode Penilaian"
                    placeholder="Contoh: KPI Semester II - 2026"
                    required
                />

                <div class="hcis-grid hcis-grid--2" style="gap: var(--hcis-space-2)">
                    <HcisField id="ds-dlg-tgl-mulai" v-model="tglMulai" label="Tanggal Mulai" type="date" required />
                    <HcisField id="ds-dlg-tgl-selesai" v-model="tglSelesai" label="Tanggal Selesai" type="date" required />
                </div>

                <HcisField
                    id="ds-dlg-catatan"
                    v-model="catatan"
                    label="Catatan Pengesahan (Opsional)"
                    placeholder="Tuliskan catatan khusus..."
                />
            </div>

            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" @click="formDialog = false" />
                <HcisButton label="Simpan Perubahan" variant="primary" size="sm" @click="formDialog = false" />
            </template>
        </HcisDialog>

        <!-- Custom Offcanvas Bottom Drawer HcisDialog (placement="bottom") -->
        <HcisDialog
            v-model="bottomDrawer"
            title="Offcanvas Bottom Drawer Mandiri"
            description="Meluncur mulus dari bawah layar, sangat nyaman di mobile & desktop"
            placement="bottom"
            size="lg"
            sentiment="info"
            icon="layout-sidebar-reverse"
        >
            <div class="hcis-stack" style="gap: var(--hcis-space-3)">
                <HcisAlert
                    variant="success"
                    title="Pintu Offcanvas Luncuran Bawah"
                    description="Varian placement='bottom' mengubah dialog menjadi Offcanvas Bottom Drawer yang meluncur dari dasar viewport."
                />

                <p style="margin: 0; color: var(--hcis-text); font-size: var(--hcis-font-size-sm);">
                    Komponen ini dapat diisi formulir panjang, opsi navigasi filter, atau ringkasan tugas. Pengguna dapat menutupnya dengan menekan tombol Batal, mengklik area luar overlay, atau menekan tombol ESC.
                </p>

                <div class="hcis-grid hcis-grid--2" style="gap: var(--hcis-space-2)">
                    <HcisField id="ds-bot-filter1" v-model="periodeJudul" label="Nama Filter Utama" />
                    <HcisField id="ds-bot-filter2" v-model="catatan" label="Keterangan Filter" />
                </div>
            </div>

            <template #footer>
                <HcisButton label="Tutup Offcanvas" variant="secondary" size="sm" @click="bottomDrawer = false" />
                <HcisButton label="Terapkan Filter" variant="primary" size="sm" @click="bottomDrawer = false" />
            </template>
        </HcisDialog>

        <!-- Custom Owned HcisDialog Konfirmasi Hapus -->
        <HcisDialog
            v-model="hapusDialog"
            title="Konfirmasi Hapus Pengajuan KPI"
            description="Tindakan ini permanen dan tidak dapat dibatalkan"
            size="sm"
            sentiment="danger"
            icon="exclamation-triangle-fill"
        >
            <div class="hcis-stack" style="gap: var(--hcis-space-3)">
                <HcisAlert
                    variant="danger"
                    title="Tindakan Tidak Dapat Dibatalkan"
                    description="Data pengajuan penilaian akan dihapus secara permanen dari server."
                />

                <div class="ds-delete-box">
                    <p class="ds-delete-title">Pengajuan yang Akan Dihapus:</p>
                    <p class="ds-delete-meta">Kode: <code>KPI-2026-08-0041</code></p>
                    <p class="ds-delete-meta">Karyawan: <strong>Budi Santoso</strong></p>
                </div>
            </div>

            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" @click="hapusDialog = false" />
                <HcisButton label="Ya, Hapus Permanen" variant="danger" size="sm" @click="hapusDialog = false" />
            </template>
        </HcisDialog>

        <p class="ds-hint">Dialog modal & Offcanvas Bottom Drawer mandiri HCIS (HcisDialog) memfokuskan perhatian pengguna pada tugas tunggal dengan backdrop blur transparan dan animasi luncuran terpisah.</p>
    </div>

    <div v-else-if="section === 'variants'" class="hcis-row">
        <HcisButton label="Dialog Formulir (Primary)" variant="secondary" size="sm" @click="formDialog = true" />
        <HcisButton label="Offcanvas Bottom Drawer" variant="secondary" size="sm" @click="bottomDrawer = true" />
        <HcisButton label="Dialog Hapus (Danger)" variant="danger-quiet" size="sm" @click="hapusDialog = true" />

        <!-- Mount Dialog Form di section variants -->
        <HcisDialog
            v-model="formDialog"
            title="Ubah Periode & Parameter KPI"
            size="md"
            sentiment="primary"
            icon="pencil-square"
        >
            <div class="hcis-stack" style="gap: var(--hcis-space-3)">
                <HcisAlert
                    variant="info"
                    title="Perhatian Periode Penilaian"
                    description="Perubahan periode akan memperbarui seluruh bobot dan kalkulasi skor KPI secara langsung."
                />
                <HcisField id="ds-var-periode" v-model="periodeJudul" label="Nama Periode Penilaian" />
            </div>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" @click="formDialog = false" />
                <HcisButton label="Simpan Perubahan" variant="primary" size="sm" @click="formDialog = false" />
            </template>
        </HcisDialog>

        <!-- Mount Offcanvas Bottom di section variants -->
        <HcisDialog
            v-model="bottomDrawer"
            title="Offcanvas Bottom Drawer Mandiri"
            placement="bottom"
            size="lg"
            sentiment="info"
        >
            <div class="hcis-stack" style="gap: var(--hcis-space-3)">
                <HcisAlert variant="success" title="Offcanvas Bottom" description="Meluncur mulus dari bawah viewport." />
            </div>
            <template #footer>
                <HcisButton label="Tutup" variant="secondary" size="sm" @click="bottomDrawer = false" />
            </template>
        </HcisDialog>

        <!-- Mount Dialog Hapus di section variants -->
        <HcisDialog
            v-model="hapusDialog"
            title="Konfirmasi Hapus Pengajuan KPI"
            size="sm"
            sentiment="danger"
            icon="bi bi-exclamation-triangle-fill"
        >
            <div class="hcis-stack" style="gap: var(--hcis-space-3)">
                <HcisAlert
                    variant="danger"
                    title="Tindakan Tidak Dapat Dibatalkan"
                    description="Data pengajuan penilaian akan dihapus secara permanen."
                />
            </div>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" @click="hapusDialog = false" />
                <HcisButton label="Ya, Hapus Permanen" variant="danger" size="sm" @click="hapusDialog = false" />
            </template>
        </HcisDialog>
    </div>

    <div v-else-if="section === 'states'">
        <HcisAlert
            variant="warning"
            title="Prinsip Penggunaan Tombol Danger di HcisDialog"
            description="Variasi tombol merah pekat (variant='danger') hanya muncul di dalam Dialog Konfirmasi Merusak saat pengguna sadar sedang mengambil keputusan berdampak tinggi. Di halaman biasa, aksi merusak memakai variant='danger-quiet'."
        />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { HcisAlert, HcisButton, HcisField, HcisDialog } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

const formDialog = ref(false);
const bottomDrawer = ref(false);
const hapusDialog = ref(false);

const periodeJudul = ref('KPI Q3 - Tahun 2026');
const tglMulai = ref('2026-07-01');
const tglSelesai = ref('2026-09-30');
const catatan = ref('Penyesuaian target kuartal ketiga');
</script>

<style scoped>
.ds-delete-box {
    padding: var(--hcis-space-3) var(--hcis-space-4);
    background: var(--hcis-surface-soft);
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-md);
}
.ds-delete-title { margin: 0 0 var(--hcis-space-1); color: var(--hcis-text-strong); font-size: var(--hcis-font-size-xs); font-weight: 700; }
.ds-delete-meta { margin: 2px 0; color: var(--hcis-text); font-size: var(--hcis-font-size-xs); }
.ds-hint { margin: var(--hcis-space-3) 0 0; color: var(--hcis-text-muted); font-size: var(--hcis-font-size-xs); line-height: var(--hcis-line-normal); }
code { background: var(--hcis-surface); border-radius: 4px; padding: 1px 4px; font-family: var(--hcis-font-mono); }
</style>
