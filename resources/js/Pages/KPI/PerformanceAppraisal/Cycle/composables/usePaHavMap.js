import { ref } from 'vue';
import paApi from '../../api/paApi.js';

function pesanGalat(exception) {
    return exception?.message || 'Permintaan tidak bisa diselesaikan.';
}

function metadataGalat(exception) {
    return {
        status: Number(exception?.status || exception?.response?.status || 0),
        errorCode: exception?.error_code || exception?.response?.data?.error_code || '',
        requestId: exception?.request_id || exception?.response?.data?.request_id || '',
    };
}

/**
 * Lifecycle pembacaan HAV Map per siklus.
 * Data sembilan kotak dan distribusi grade dibiarkan dalam urutan yang dikirim server.
 */
export function usePaHavMap(api = paApi) {
    const kuadran = ref([]);
    const grade = ref([]);
    const pita = ref({ kpi: [], kompetensi: [] });
    const ringkasan = ref(null);
    const loading = ref(false);
    const error = ref('');
    const errorStatus = ref(0);
    const errorCode = ref('');
    const requestId = ref('');
    let urutanPermintaan = 0;

    function reset() {
        urutanPermintaan += 1;
        kuadran.value = [];
        grade.value = [];
        pita.value = { kpi: [], kompetensi: [] };
        ringkasan.value = null;
        loading.value = false;
        error.value = '';
        errorStatus.value = 0;
        errorCode.value = '';
        requestId.value = '';
    }

    async function muat(cycleId) {
        if (!cycleId) return { stale: false, skipped: true };

        const nomorPermintaan = ++urutanPermintaan;
        loading.value = true;
        error.value = '';
        errorStatus.value = 0;
        errorCode.value = '';
        requestId.value = '';

        try {
            const data = await api.havMap(cycleId);
            if (nomorPermintaan !== urutanPermintaan) return { stale: true };

            kuadran.value = Array.isArray(data?.kuadran) ? data.kuadran : [];
            grade.value = Array.isArray(data?.grade) ? data.grade : [];
            pita.value = {
                kpi: Array.isArray(data?.pita?.kpi) ? data.pita.kpi : [],
                kompetensi: Array.isArray(data?.pita?.kompetensi) ? data.pita.kompetensi : [],
            };
            ringkasan.value = data?.ringkasan || null;
            return { stale: false, data };
        } catch (exception) {
            if (nomorPermintaan !== urutanPermintaan) return { stale: true };

            const metadata = metadataGalat(exception);
            error.value = pesanGalat(exception);
            errorStatus.value = metadata.status;
            errorCode.value = metadata.errorCode;
            requestId.value = metadata.requestId;
            return { stale: false, error: exception };
        } finally {
            if (nomorPermintaan === urutanPermintaan) loading.value = false;
        }
    }

    return {
        kuadran,
        grade,
        pita,
        ringkasan,
        loading,
        error,
        errorStatus,
        errorCode,
        requestId,
        muat,
        reset,
    };
}

export default usePaHavMap;
