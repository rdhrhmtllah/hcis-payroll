import { reactive, ref } from 'vue';
import paApi from '../../api/paApi.js';

const RESOURCE_KEYS = ['ringkasan', 'monitoring', 'distribusi', 'riwayat'];

const buatResource = () => reactive({
    data: null,
    loading: false,
    error: '',
    status: 0,
    errorCode: '',
    requestId: '',
});

const pesanFallback = 'Permintaan laporan tidak bisa diselesaikan.';

function bacaError(exception) {
    return {
        message: exception?.message || pesanFallback,
        status: Number(exception?.status || exception?.response?.status || 0),
        errorCode: exception?.error_code || exception?.response?.data?.error_code || '',
        requestId: exception?.request_id || exception?.response?.data?.request_id || '',
    };
}

/**
 * Orkestrasi empat laporan admin PA.
 *
 * Composable ini sengaja menjadi satu-satunya pemilik lifecycle request Control Tower.
 * Component hanya mengirimkan cycle/filter dan membaca state aman untuk ditampilkan.
 * Setiap resource memiliki sequence sendiri agar response lama tidak menimpa saringan
 * terbaru ketika pengguna mengganti siklus atau berpindah tab dengan cepat.
 */
export function usePaAdminControlTower(api = paApi) {
    const cycleId = ref(null);
    const ringkasan = buatResource();
    const monitoring = buatResource();
    const distribusi = buatResource();
    const riwayat = buatResource();
    const sequence = Object.fromEntries(RESOURCE_KEYS.map((key) => [key, 0]));

    function resetResource(resource) {
        resource.data = null;
        resource.loading = false;
        resource.error = '';
        resource.status = 0;
        resource.errorCode = '';
        resource.requestId = '';
    }

    function reset() {
        cycleId.value = null;
        RESOURCE_KEYS.forEach((key) => { sequence[key] += 1; });
        resetResource(ringkasan);
        resetResource(monitoring);
        resetResource(distribusi);
        resetResource(riwayat);
    }

    async function jalankan(key, resource, request) {
        const requestNumber = ++sequence[key];
        resource.loading = true;
        resource.error = '';
        resource.status = 0;
        resource.errorCode = '';
        resource.requestId = '';
        resource.data = null;

        try {
            const data = await request();
            if (requestNumber !== sequence[key]) return { stale: true };

            resource.data = data || null;
            return { stale: false, data };
        } catch (exception) {
            if (requestNumber !== sequence[key]) return { stale: true };

            const error = bacaError(exception);
            resource.error = error.message;
            resource.status = error.status;
            resource.errorCode = error.errorCode;
            resource.requestId = error.requestId;
            return { stale: false, error: exception };
        } finally {
            if (requestNumber === sequence[key]) resource.loading = false;
        }
    }

    function muatRingkasan(id) {
        return id ? jalankan('ringkasan', ringkasan, () => api.ringkasanSiklus(id)) : Promise.resolve({ skipped: true });
    }

    function muatMonitoring(id, filters = {}) {
        return id ? jalankan('monitoring', monitoring, () => api.monitoringSiklus(id, filters)) : Promise.resolve({ skipped: true });
    }

    function muatDistribusi(id, filters = {}) {
        return id ? jalankan('distribusi', distribusi, () => api.distribusiSiklus(id, filters)) : Promise.resolve({ skipped: true });
    }

    function muatRiwayat(filters = {}) {
        return jalankan('riwayat', riwayat, () => api.riwayatAdmin(filters));
    }

    async function muatLaporanSiklus(id, filters = {}) {
        cycleId.value = id || null;
        if (!id) {
            resetResource(ringkasan);
            resetResource(monitoring);
            resetResource(distribusi);
            return { skipped: true };
        }

        return Promise.all([
            muatRingkasan(id),
            muatMonitoring(id, filters.monitoring || {}),
            muatDistribusi(id, filters.distribusi || {}),
        ]);
    }

    return {
        cycleId,
        ringkasan,
        monitoring,
        distribusi,
        riwayat,
        muatRingkasan,
        muatMonitoring,
        muatDistribusi,
        muatRiwayat,
        muatLaporanSiklus,
        reset,
    };
}

export default usePaAdminControlTower;
