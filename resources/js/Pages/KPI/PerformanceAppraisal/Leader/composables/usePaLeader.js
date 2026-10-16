import { computed, ref } from 'vue';

import paApi from '../../api/paApi.js';

function buatResource(initialValue = null) {
    return {
        data: ref(initialValue),
        loading: ref(false),
        error: ref(''),
        status: ref(0),
        errorCode: ref(''),
        requestId: ref(''),
        sequence: 0,
    };
}

function bukaRespons(response) {
    return response?.result ?? response ?? null;
}

function bacaError(error) {
    return {
        message: error?.message || 'Permintaan Performance Appraisal tidak dapat diselesaikan.',
        status: Number(error?.status || error?.response?.status || 0),
        errorCode: error?.error_code || error?.response?.data?.error_code || '',
        requestId: error?.request_id || error?.response?.data?.request_id || '',
    };
}

/**
 * Lifecycle data untuk L01 Dashboard Tim dan L02 Detail Anggota.
 *
 * Page hanya mengorkestrasi state dan komponen hanya menyajikan data. Sequence
 * dipisah untuk tim dan detail supaya response detail lama tidak menimpa anggota
 * yang baru dipilih.
 */
export function usePaLeader(api = paApi) {
    const tim = buatResource();
    const detail = buatResource();
    const anggotaTerpilih = ref(null);

    async function jalankan(resource, request) {
        const sequence = ++resource.sequence;
        resource.loading.value = true;
        resource.error.value = '';
        resource.status.value = 0;
        resource.errorCode.value = '';
        resource.requestId.value = '';

        try {
            const response = await request();

            if (sequence !== resource.sequence) {
                return { stale: true, response };
            }

            resource.data.value = bukaRespons(response);
            return { stale: false, data: resource.data.value };
        } catch (error) {
            if (sequence !== resource.sequence) {
                return { stale: true, error };
            }

            const metadata = bacaError(error);
            resource.error.value = metadata.message;
            resource.status.value = metadata.status;
            resource.errorCode.value = metadata.errorCode;
            resource.requestId.value = metadata.requestId;

            return { stale: false, error };
        } finally {
            if (sequence === resource.sequence) {
                resource.loading.value = false;
            }
        }
    }

    function muatTim(cycleId = null) {
        return jalankan(tim, () => api.timLeader(cycleId));
    }

    function muatDetail(participantId) {
        anggotaTerpilih.value = participantId;
        return jalankan(detail, () => api.detailAnggotaLeader(participantId));
    }

    function resetDetail() {
        detail.sequence += 1;
        anggotaTerpilih.value = null;
        detail.data.value = null;
        detail.loading.value = false;
        detail.error.value = '';
        detail.status.value = 0;
        detail.errorCode.value = '';
        detail.requestId.value = '';
    }

    return {
        tim: tim.data,
        memuatTim: tim.loading,
        errorTim: tim.error,
        statusErrorTim: tim.status,
        kodeErrorTim: tim.errorCode,
        requestIdTim: tim.requestId,

        detail: detail.data,
        memuatDetail: detail.loading,
        errorDetail: detail.error,
        statusErrorDetail: detail.status,
        kodeErrorDetail: detail.errorCode,
        requestIdDetail: detail.requestId,
        anggotaTerpilih,

        muatTim,
        muatDetail,
        resetDetail,
        memuat: computed(() => tim.loading.value || detail.loading.value),
    };
}

export default usePaLeader;

