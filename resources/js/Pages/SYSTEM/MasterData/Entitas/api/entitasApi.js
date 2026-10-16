import axios from 'axios';

const BASE = '/system/master-data/entitas';

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

/** API pengelolaan Master Entitas (khusus System Admin). */
export const entitasApi = {
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
            throw normalizeError(error, 'Gagal memuat daftar entitas');
        }
    },

    /** Kandidat induk. Dipakai form, bukan daftar. */
    async options() {
        try {
            const response = await axios.get(`${BASE}/options`);

            return response.data?.success ? response.data.result || [] : [];
        } catch (error) {
            throw normalizeError(error, 'Gagal memuat kandidat induk');
        }
    },

    /** Struktur grup untuk org chart. */
    async pohon() {
        try {
            const response = await axios.get(`${BASE}/pohon`);

            return response.data?.success ? response.data.result || null : null;
        } catch (error) {
            throw normalizeError(error, 'Gagal memuat struktur grup');
        }
    },

    async store(payload) {
        try {
            const response = await axios.post(`${BASE}/store`, payload);
            if (response.data?.success) {
                return { success: true, message: response.data.message || 'Tersimpan!' };
            }
            throw new Error(response.data?.message || 'Gagal menyimpan entitas');
        } catch (error) {
            throw normalizeError(error, 'Gagal menyimpan entitas');
        }
    },

    async update(id, payload) {
        if (!id) throw new Error('ID entitas tidak valid');
        try {
            const response = await axios.put(`${BASE}/update/${id}`, payload);
            if (response.data?.success) {
                return { success: true, message: response.data.message || 'Diperbarui!' };
            }
            throw new Error(response.data?.message || 'Gagal memperbarui entitas');
        } catch (error) {
            throw normalizeError(error, 'Gagal memperbarui entitas');
        }
    },

    async destroy(id) {
        if (!id) throw new Error('ID entitas tidak valid');
        try {
            const response = await axios.delete(`${BASE}/delete/${id}`);
            if (response.data?.success) {
                return { success: true, message: response.data.message || 'Dinonaktifkan!' };
            }
            throw new Error(response.data?.message || 'Gagal menonaktifkan entitas');
        } catch (error) {
            throw normalizeError(error, 'Gagal menonaktifkan entitas');
        }
    },
};

export default entitasApi;
