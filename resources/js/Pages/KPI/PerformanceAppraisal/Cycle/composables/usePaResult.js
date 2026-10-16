import { computed, ref } from 'vue';
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
 * Orkestrasi detail, perhitungan, dan aksi hasil peserta.
 *
 * Dialog hasil tetap menguasai state presentasi seperti modal dan form lokal.
 * Semua request dinamis dan sinkronisasi ulang detail berada di page boundary ini.
 */
export function usePaResult(api = paApi) {
    const detail = ref(null);
    const loading = ref(false);
    const error = ref('');
    const errorStatus = ref(0);
    const errorCode = ref('');
    const requestId = ref('');
    const hasilHitung = ref(null);
    const recalculating = ref(false);
    const acting = ref(false);
    const actionError = ref('');
    const actionErrorStatus = ref(0);
    const actionErrorCode = ref('');
    const actionRequestId = ref('');
    const actionSuccess = ref('');
    let urutanPermintaan = 0;

    const peserta = computed(() => detail.value?.peserta || null);

    function bersihkanGalatMuat() {
        error.value = '';
        errorStatus.value = 0;
        errorCode.value = '';
        requestId.value = '';
    }

    function bersihkanAksi() {
        actionError.value = '';
        actionErrorStatus.value = 0;
        actionErrorCode.value = '';
        actionRequestId.value = '';
        actionSuccess.value = '';
    }

    function setGalatAksi(exception) {
        const metadata = metadataGalat(exception);
        actionError.value = pesanGalat(exception);
        actionErrorStatus.value = metadata.status;
        actionErrorCode.value = metadata.errorCode;
        actionRequestId.value = metadata.requestId;
    }

    function reset() {
        urutanPermintaan += 1;
        detail.value = null;
        loading.value = false;
        bersihkanGalatMuat();
        hasilHitung.value = null;
        recalculating.value = false;
        acting.value = false;
        bersihkanAksi();
    }

    async function muat(participantId) {
        if (!participantId) return { stale: false, skipped: true };

        const nomorPermintaan = ++urutanPermintaan;
        loading.value = true;
        bersihkanGalatMuat();

        try {
            const data = await api.detailHasil(participantId);
            if (nomorPermintaan !== urutanPermintaan) return { stale: true };

            detail.value = data || null;
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

    async function batalkan(participantId, assignmentId, alasan) {
        if (!participantId || !assignmentId || !alasan?.trim() || acting.value) return { skipped: true };

        acting.value = true;
        bersihkanAksi();
        try {
            const data = await api.batalkanPenugasan(assignmentId, alasan.trim());
            actionSuccess.value = data?.dibatalkan ? `Penugasan ${data.dibatalkan} dibatalkan.` : 'Penugasan dibatalkan.';
            await muat(participantId);
            return { data };
        } catch (exception) {
            setGalatAksi(exception);
            return { error: exception };
        } finally {
            acting.value = false;
        }
    }

    async function hitung(participantId) {
        if (!participantId || recalculating.value) return { skipped: true };

        recalculating.value = true;
        bersihkanAksi();
        try {
            hasilHitung.value = await api.hitungPeserta(participantId);
            await muat(participantId);
            return { data: hasilHitung.value };
        } catch (exception) {
            setGalatAksi(exception);
            return { error: exception };
        } finally {
            recalculating.value = false;
        }
    }

    async function kalibrasi(participantId, resultId, payload) {
        if (!participantId || !resultId || !payload?.alasan?.trim() || acting.value) return { skipped: true };

        acting.value = true;
        bersihkanAksi();
        try {
            const data = await api.kalibrasi(resultId, {
                kodeGrade: payload.kodeGrade || null,
                kuadranId: payload.kuadranId || null,
                alasan: payload.alasan.trim(),
            });
            actionSuccess.value = 'Kalibrasi forum tersimpan.';
            await muat(participantId);
            return { data };
        } catch (exception) {
            setGalatAksi(exception);
            return { error: exception };
        } finally {
            acting.value = false;
        }
    }

    return {
        detail,
        peserta,
        loading,
        error,
        errorStatus,
        errorCode,
        requestId,
        hasilHitung,
        recalculating,
        acting,
        actionError,
        actionErrorStatus,
        actionErrorCode,
        actionRequestId,
        actionSuccess,
        muat,
        reset,
        batalkan,
        hitung,
        kalibrasi,
    };
}

export default usePaResult;
