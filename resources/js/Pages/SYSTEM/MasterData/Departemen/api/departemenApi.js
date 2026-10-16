import axios from 'axios';

const BASE = '/system/master-data/departemen';

/** Normalisasi galat API — bentuknya sama dengan modul master lain. */
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

/**
 * API pengelolaan Master Kode Departemen (khusus System Admin).
 *
 * Payload sukses ResponseHelper ada di `result`, BUKAN `data`. Membacanya dari
 * `data` mengembalikan undefined tanpa satu pun galat di konsol — dropdown
 * tampak kosong dan tak ada yang menunjukkan sebabnya.
 */
export const departemenApi = {
    async list({ page = 1, limit = 25, q = '' } = {}) {
        try {
            const params = { page: Math.max(1, page), limit: Math.min(100, Math.max(1, limit)) };
            if (q && q.trim()) params.q = q.trim();

            const response = await axios.get(`${BASE}/data`, { params });
            if (response.data?.success) {
                return {
                    success: true,
                    data: response.data.result || [],
                    page: response.data.page || 1,
                    total_page: response.data.total_page || 1,
                    total_data: response.data.total_data || 0,
                };
            }

            return { success: false, data: [], page: 1, total_page: 1, total_data: 0 };
        } catch (error) {
            if (error.response?.status === 404) {
                return { success: true, data: [], page: 1, total_page: 1, total_data: 0 };
            }
            throw normalizeError(error, 'Gagal memuat daftar departemen');
        }
    },

    /** Kandidat sub-divisi HRIS untuk binding. */
    async subDivisi() {
        try {
            const response = await axios.get(`${BASE}/sub-divisi`);

            return response.data?.success ? response.data.result || [] : [];
        } catch (error) {
            throw normalizeError(error, 'Gagal memuat daftar sub-divisi');
        }
    },

    async store(payload) {
        try {
            const response = await axios.post(`${BASE}/store`, payload);
            if (response.data?.success) {
                return { success: true, message: response.data.message || 'Tersimpan!' };
            }
            throw new Error(response.data?.message || 'Gagal menyimpan departemen');
        } catch (error) {
            throw normalizeError(error, 'Gagal menyimpan departemen');
        }
    },

    async update(id, payload) {
        if (!id) throw new Error('ID departemen tidak valid');
        try {
            const response = await axios.put(`${BASE}/update/${id}`, payload);
            if (response.data?.success) {
                return { success: true, message: response.data.message || 'Diperbarui!' };
            }
            throw new Error(response.data?.message || 'Gagal memperbarui departemen');
        } catch (error) {
            throw normalizeError(error, 'Gagal memperbarui departemen');
        }
    },

    async destroy(id) {
        if (!id) throw new Error('ID departemen tidak valid');
        try {
            const response = await axios.delete(`${BASE}/delete/${id}`);
            if (response.data?.success) {
                return { success: true, message: response.data.message || 'Dinonaktifkan!' };
            }
            throw new Error(response.data?.message || 'Gagal menonaktifkan departemen');
        } catch (error) {
            throw normalizeError(error, 'Gagal menonaktifkan departemen');
        }
    },
};

export default departemenApi;
