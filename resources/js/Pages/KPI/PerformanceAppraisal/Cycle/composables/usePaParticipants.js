import { reactive, ref } from 'vue';
import paApi from '../../api/paApi.js';

const PAGINATION_AWAL = {
    halaman: 1,
    total: 0,
    totalHalaman: 0,
    ringkasan: null,
    dipangkas: false,
};

/**
 * Lifecycle data peserta untuk workspace siklus PA.
 *
 * Page tetap menentukan kapan workspace dibuka dan siklus yang aktif.
 * Composable ini hanya mengelola pemuatan daftar peserta, state request,
 * metadata error, dan perlindungan terhadap respons yang datang terlambat.
 */
export function usePaParticipants(api = paApi) {
    const peserta = ref([]);
    const memuat = ref(false);
    const error = ref('');
    const errorStatus = ref(0);
    const errorCode = ref('');
    const requestId = ref('');
    const pagination = reactive({ ...PAGINATION_AWAL });
    const opsi = ref({ divisi: [], level: [], sub_divisi: [], jabatan: [], lokasi: [] });
    let urutanPermintaan = 0;

    function reset() {
        urutanPermintaan += 1;
        peserta.value = [];
        memuat.value = false;
        error.value = '';
        errorStatus.value = 0;
        errorCode.value = '';
        requestId.value = '';
        opsi.value = { divisi: [], level: [], sub_divisi: [], jabatan: [], lokasi: [] };
        Object.assign(pagination, PAGINATION_AWAL);
    }

    async function muat(cycleId, saringan = {}) {
        if (!cycleId) return { stale: false, skipped: true };

        const nomorPermintaan = ++urutanPermintaan;
        memuat.value = true;
        error.value = '';
        errorStatus.value = 0;
        errorCode.value = '';
        requestId.value = '';

        try {
            const data = await api.daftarPeserta(cycleId, saringan);

            if (nomorPermintaan !== urutanPermintaan) return { stale: true };

            peserta.value = data?.peserta || [];
            pagination.halaman = Number(data?.halaman || 1);
            pagination.total = Number(data?.total || 0);
            pagination.totalHalaman = Number(data?.total_halaman || 0);
            pagination.ringkasan = data?.ringkasan || null;
            pagination.dipangkas = Boolean(data?.dipangkas);
            if (data?.opsi) opsi.value = data.opsi;

            return { stale: false, data };
        } catch (exception) {
            if (nomorPermintaan !== urutanPermintaan) return { stale: true };

            error.value = exception?.message || 'Permintaan tidak bisa diselesaikan.';
            errorStatus.value = Number(exception?.status || exception?.response?.status || 0);
            errorCode.value = exception?.error_code || '';
            requestId.value = exception?.request_id || '';

            return { stale: false, error: exception };
        } finally {
            if (nomorPermintaan === urutanPermintaan) memuat.value = false;
        }
    }

    return {
        peserta,
        memuat,
        error,
        errorStatus,
        errorCode,
        requestId,
        pagination,
        opsi,
        muat,
        reset,
    };
}

export default usePaParticipants;
