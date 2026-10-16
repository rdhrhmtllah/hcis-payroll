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

/** API Master APQC (klasifikasi proses bisnis). */
export const sopApqcApi = {
    /** GET /api/v1/sop/admin/apqc/current */
    async list({ page = 1, limit = 25, q = '' } = {}, signal = null) {
        try {
            const params = { page: Math.max(1, page), limit: Math.min(100, Math.max(1, limit)) };
            if (q && q.trim()) params.q = q.trim();

            const response = await axios.get('/api/v1/sop/admin/apqc/current', { params, signal });

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
            if (axios.isCancel(error)) return { cancelled: true };

            throw normalizeError(error, 'Gagal memuat daftar proses APQC');
        }
    },

    /** POST /api/v1/sop/admin/apqc/store */
    async store(payload) {
        try {
            const response = await axios.post('/api/v1/sop/admin/apqc/store', payload);
            if (response.data?.success) {
                return { success: true, message: response.data.message || 'Tersimpan!' };
            }
            throw new Error(response.data?.message || 'Gagal menyimpan proses APQC');
        } catch (error) {
            throw normalizeError(error, 'Gagal menyimpan proses APQC');
        }
    },

    /** PUT /api/v1/sop/admin/apqc/update/{id} */
    async update(id, payload) {
        if (!id) throw new Error('ID proses APQC tidak valid');
        try {
            const response = await axios.put(`/api/v1/sop/admin/apqc/update/${id}`, payload);
            if (response.data?.success) {
                return { success: true, message: response.data.message || 'Diperbarui!' };
            }
            throw new Error(response.data?.message || 'Gagal memperbarui proses APQC');
        } catch (error) {
            throw normalizeError(error, 'Gagal memperbarui proses APQC');
        }
    },

    /** DELETE /api/v1/sop/admin/apqc/delete/{id} */
    async destroy(id) {
        if (!id) throw new Error('ID proses APQC tidak valid');
        try {
            const response = await axios.delete(`/api/v1/sop/admin/apqc/delete/${id}`);
            if (response.data?.success) {
                return { success: true, message: response.data.message || 'Dinonaktifkan!' };
            }
            throw new Error(response.data?.message || 'Gagal menonaktifkan proses APQC');
        } catch (error) {
            throw normalizeError(error, 'Gagal menonaktifkan proses APQC');
        }
    },
};

export default sopApqcApi;
