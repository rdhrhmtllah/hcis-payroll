import axios from 'axios';

/**
 * Normalisasi error API menjadi objek aman dan ramah pengguna.
 * Bentuknya sengaja sama dengan sopCategoryApi — layar yang menangani galat
 * dengan dua bentuk berbeda akan menampilkan pesan berbeda untuk kegagalan
 * yang sama.
 */
function normalizeError(error, defaultMessage = 'Terjadi kesalahan sistem') {
    const response = error?.response;
    const data = response?.data;

    return {
        success: false,
        status: response?.status || 500,
        message: data?.message || defaultMessage,
        errors: data?.errors || {},
        error_code: data?.error_code || 'UNEXPECTED_ERROR',
        request_id: data?.request_id || null,
    };
}

/** API Master Jenis Dokumen SOP (hirarki mutu). */
export const sopJenisDokumenApi = {
    /** GET /api/v1/sop/admin/jenis-dokumen/current */
    async list({ page = 1, limit = 25, q = '' } = {}, signal = null) {
        try {
            const params = {
                page: Math.max(1, page),
                limit: Math.min(100, Math.max(1, limit)),
            };
            if (q && q.trim()) params.q = q.trim();

            const response = await axios.get('/api/v1/sop/admin/jenis-dokumen/current', { params, signal });

            if (response.data?.success) {
                return {
                    success: true,
                    data: response.data.result || [],
                    page: response.data.page || 1,
                    total_page: response.data.total_page || 1,
                    total_data: response.data.total_data || 0,
                    message: response.data.message || 'Data ditemukan',
                };
            }

            return {
                success: false,
                data: [],
                page: 1,
                total_page: 1,
                total_data: 0,
                message: response.data?.message || 'Data tidak ditemukan',
            };
        } catch (error) {
            if (error.response?.status === 404) {
                return {
                    success: true,
                    data: [],
                    page: 1,
                    total_page: 1,
                    total_data: 0,
                    message: 'Belum ada jenis dokumen',
                };
            }
            if (axios.isCancel(error)) return { cancelled: true };

            throw normalizeError(error, 'Gagal memuat jenis dokumen');
        }
    },

    /** POST /api/v1/sop/admin/jenis-dokumen/store */
    async store(payload) {
        try {
            const response = await axios.post('/api/v1/sop/admin/jenis-dokumen/store', payload);
            if (response.data?.success) {
                return {
                    success: true,
                    data: response.data.result || null,
                    message: response.data.message || 'Tersimpan!',
                };
            }
            throw new Error(response.data?.message || 'Gagal menyimpan jenis dokumen');
        } catch (error) {
            throw normalizeError(error, 'Gagal menyimpan jenis dokumen');
        }
    },

    /** POST /api/v1/sop/admin/jenis-dokumen/reorder */
    async reorder(ids) {
        if (!Array.isArray(ids) || !ids.length) throw new Error('Daftar ID tidak valid');
        try {
            const response = await axios.post('/api/v1/sop/admin/jenis-dokumen/reorder', { ids });
            if (response.data?.success) {
                return { success: true, message: response.data.message || 'Urutan diperbarui!' };
            }
            throw new Error(response.data?.message || 'Gagal memperbarui urutan');
        } catch (error) {
            throw normalizeError(error, 'Gagal memperbarui urutan jenis dokumen');
        }
    },

    /** PUT /api/v1/sop/admin/jenis-dokumen/update/{id} */
    async update(id, payload) {
        if (!id) throw new Error('ID jenis dokumen tidak valid');
        try {
            const response = await axios.put(`/api/v1/sop/admin/jenis-dokumen/update/${id}`, payload);
            if (response.data?.success) {
                return { success: true, message: response.data.message || 'Diperbarui!' };
            }
            throw new Error(response.data?.message || 'Gagal memperbarui jenis dokumen');
        } catch (error) {
            throw normalizeError(error, 'Gagal memperbarui jenis dokumen');
        }
    },

    /** DELETE /api/v1/sop/admin/jenis-dokumen/delete/{id} */
    async destroy(id) {
        if (!id) throw new Error('ID jenis dokumen tidak valid');
        try {
            const response = await axios.delete(`/api/v1/sop/admin/jenis-dokumen/delete/${id}`);
            if (response.data?.success) {
                return { success: true, message: response.data.message || 'Dinonaktifkan!' };
            }
            throw new Error(response.data?.message || 'Gagal menonaktifkan jenis dokumen');
        } catch (error) {
            throw normalizeError(error, 'Gagal menonaktifkan jenis dokumen');
        }
    },
};

/**
 * Opsi master data bersama — dipakai Master Dokumen DAN Builder.
 *
 * Diletakkan di modul yang sama dengan masternya, bukan disalin ke tiap
 * halaman: dua salinan pemanggil endpoint yang sama adalah cara paling cepat
 * mendapatkan dua daftar jenis yang tidak lagi sama.
 */
export const sopMasterDataApi = {
    /** GET /api/v1/sop/admin/master-data/jenis-dokumen?tipe=DOKUMEN */
    async jenisDokumen(tipe = null) {
        try {
            const response = await axios.get('/api/v1/sop/admin/master-data/jenis-dokumen', {
                params: tipe ? { tipe } : {},
            });

            return response.data?.success ? response.data.result || [] : [];
        } catch (error) {
            throw normalizeError(error, 'Gagal memuat daftar jenis dokumen');
        }
    },

    /**
     * GET /api/v1/sop/admin/master-data/unit-kerja
     *
     * Server mengirim dua bentuk: `departemen` (datar, dipakai layar sejak
     * 22 Agu 2026) dan `divisi` (bertingkat, jalur mundur). Keduanya diteruskan
     * apa adanya supaya pemanggil memilih sendiri, bukan diputuskan di sini.
     */
    async unitKerja() {
        try {
            const response = await axios.get('/api/v1/sop/admin/master-data/unit-kerja');
            const d = response.data?.success ? response.data.result : null;

            return { departemen: d?.departemen || [], divisi: d?.divisi || [] };
        } catch (error) {
            throw normalizeError(error, 'Gagal memuat daftar unit kerja');
        }
    },

    /**
     * GET /api/v1/sop/admin/master-data/departemen
     *
     * MASTER KODE DEPARTEMEN — acuan kepemilikan dokumen sejak DDL-2026-027,
     * dan sumber segmen departemen pada nomor dokumen. Berbeda dari
     * `unitKerja()`, yang membaca struktur HRIS: daftar kode perusahaan lebih
     * rinci daripada struktur yang tersimpan (52 versus 32).
     */
    async departemen() {
        try {
            const response = await axios.get('/api/v1/sop/admin/master-data/departemen');

            return response.data?.success ? response.data.result || [] : [];
        } catch (error) {
            throw normalizeError(error, 'Gagal memuat daftar departemen');
        }
    },

    /** GET /api/v1/sop/admin/master-data/apqc?tipe=DOKUMEN */
    async apqc(tipe = null) {
        try {
            const response = await axios.get('/api/v1/sop/admin/master-data/apqc', {
                params: tipe ? { tipe } : {},
            });

            return response.data?.success ? response.data.result || [] : [];
        } catch (error) {
            throw normalizeError(error, 'Gagal memuat daftar proses APQC');
        }
    },

    /** GET /api/v1/sop/admin/master-data/entitas */
    async entitas() {
        try {
            const response = await axios.get('/api/v1/sop/admin/master-data/entitas');

            return response.data?.success ? response.data.result || [] : [];
        } catch (error) {
            throw normalizeError(error, 'Gagal memuat daftar entitas');
        }
    },
};

/**
 * Label entitas siap tampil di dropdown.
 *
 * Arti payung ditulis DI TITIK MEMILIH, bukan di dokumentasi: admin yang
 * memilih EHG harus tahu saat itu juga bahwa ia mencakup seluruh entitas.
 */
/**
 * Label jenis dokumen siap tampil: KODE dulu, karena itu yang tercetak.
 *
 * Sebelumnya dropdown menyebut tingkat hirarki ("L3 · Prosedur"). Angka itu
 * cuma urutan tampilan dan tak pernah muncul di dokumen mana pun, sementara
 * kodenya justru segmen pertama nomor dokumen. Yang dilihat admin saat memilih
 * sebaiknya sama dengan yang nanti tercetak.
 *
 * Jenis yang kodenya belum diisi jatuh ke tingkat hirarki — bukan disembunyikan,
 * sebab ia tetap boleh dipilih; nomor lengkapnya saja yang belum bisa terbit.
 */
export function labelJenisDokumen(j) {
    if (!j) return '';

    const kode = (j.Kode_Jenis_Dokumen || '').trim();
    const nama = j.Nama_Jenis || '';

    return kode ? `${kode} — ${nama}` : `L${j.Level_Hirarki ?? '?'} · ${nama}`;
}

/** Label departemen siap tampil: kode dulu, karena itu yang tercetak di nomor. */
export function labelDepartemen(dp) {
    if (!dp) return '';

    return `${dp.Kode_Departemen} — ${dp.Nama_Departemen}`;
}

export function labelEntitas(e) {
    if (!e) return '';
    const nama = `${e.Kode_Entitas} — ${e.Nama_Entitas}`;

    return e.Cakup_Semua ? `${nama} (seluruh entitas)` : nama;
}

export default sopJenisDokumenApi;
