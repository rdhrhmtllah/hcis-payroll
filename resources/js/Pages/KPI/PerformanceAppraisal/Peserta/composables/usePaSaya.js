import { computed, ref } from 'vue';

import paApi from '../../api/paApi.js';

function createResource(initialValue) {
    return {
        data: ref(initialValue),
        loading: ref(false),
        loaded: ref(false),
        error: ref(''),
        status: ref(0),
        errorCode: ref(''),
        requestId: ref(''),
    };
}

function errorMeta(error) {
    return {
        message: error?.message || 'Permintaan tidak dapat diselesaikan.',
        status: Number(error?.status || error?.response?.status || 0),
        errorCode: error?.error_code || error?.response?.data?.error_code || '',
        requestId: error?.request_id || error?.response?.data?.request_id || '',
    };
}

function angka(value, fallback = 0) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
}

/**
 * Orkestrasi empat endpoint sisi peserta.
 *
 * Page hanya menghubungkan state dan event. Semua perjalanan data tetap melewati
 * paApi, sedangkan komponen di bawahnya hanya menerima props dan memancarkan event.
 * Satu sequence per resource mencegah respons lama menimpa refresh atau saringan
 * terbaru ketika user berpindah tab dengan cepat.
 */
export function usePaSaya(api = paApi) {
    const sumber = {
        ringkasan: createResource(null),
        assignment: createResource({
            assignment: [],
            halaman: 1,
            per_halaman: 25,
            total: 0,
            total_halaman: 1,
            dipangkas: false,
        }),
        hasil: createResource(null),
        riwayat: createResource(null),

        /* Agregat per kategori. Sumber TERPISAH dari `assignment`, bukan diturunkan darinya:
           `assignment` berpaginasi 25, dan agregat yang diturunkan dari satu halaman akan
           melaporkan sebagian sebagai seluruhnya. */
        progres: createResource(null),
    };

    const sequences = {
        ringkasan: 0,
        assignment: 0,
        hasil: 0,
        riwayat: 0,
        progres: 0,
    };

    const assignmentQuery = ref({ halaman: 1, per_halaman: 25 });

    async function muatResource(nama, pemanggil, simpan) {
        const state = sumber[nama];
        const sequence = ++sequences[nama];

        state.loading.value = true;
        state.error.value = '';
        state.status.value = 0;
        state.errorCode.value = '';
        state.requestId.value = '';

        try {
            const data = await pemanggil();

            if (sequence !== sequences[nama]) {
                return { stale: true, data };
            }

            state.data.value = simpan(data);
            state.loaded.value = true;

            return { stale: false, data };
        } catch (error) {
            if (sequence !== sequences[nama]) {
                return { stale: true, error };
            }

            const meta = errorMeta(error);
            state.error.value = meta.message;
            state.status.value = meta.status;
            state.errorCode.value = meta.errorCode;
            state.requestId.value = meta.requestId;

            return { stale: false, error };
        } finally {
            if (sequence === sequences[nama]) {
                state.loading.value = false;
            }
        }
    }

    function queryAssignment(filters = {}) {
        const next = {
            halaman: angka(filters.halaman, 1),
            per_halaman: angka(filters.per_halaman, 25),
        };

        ['status', 'q', 'cycle_id'].forEach((key) => {
            if (filters[key] !== undefined && filters[key] !== null && filters[key] !== '') {
                next[key] = filters[key];
            }
        });

        assignmentQuery.value = next;
        return next;
    }

    function muatRingkasan(cycleId = null) {
        return muatResource('ringkasan', () => api.ringkasanSaya(cycleId), (data) => data || null);
    }

    function muatProgres(cycleId = null) {
        return muatResource('progres', () => api.progresKategoriSaya(cycleId), (data) => data || null);
    }

    function muatAssignment(filters = assignmentQuery.value) {
        const query = queryAssignment(filters);

        return muatResource(
            'assignment',
            () => api.assignmentSaya(query),
            (data) => ({
                assignment: Array.isArray(data?.assignment) ? data.assignment : [],
                halaman: angka(data?.halaman, query.halaman),
                per_halaman: angka(data?.per_halaman, query.per_halaman),
                total: angka(data?.total, 0),
                total_halaman: angka(data?.total_halaman, 1),
                dipangkas: data?.dipangkas === true,
            }),
        );
    }

    function muatHasil(cycleId = null) {
        return muatResource('hasil', () => api.hasilSaya(cycleId), (data) => data || null);
    }

    function muatRiwayat() {
        return muatResource(
            'riwayat',
            () => api.riwayatSaya(),
            (data) => ({
                periode: Array.isArray(data?.periode) ? data.periode : [],
                jumlah_periode: angka(data?.jumlah_periode, 0),
                rata_rata: data?.rata_rata ?? null,
                terbaik: data?.terbaik ?? null,
                perubahan_terakhir: data?.perubahan_terakhir ?? null,
            }),
        );
    }

    function muatSemua({ cycleId = null, assignment: filters = assignmentQuery.value } = {}) {
        return Promise.all([
            muatRingkasan(cycleId),
            muatProgres(cycleId),
            muatAssignment(filters),
            muatHasil(cycleId),
            muatRiwayat(),
        ]);
    }

    return {
        ringkasan: sumber.ringkasan.data,
        memuatRingkasan: sumber.ringkasan.loading,
        ringkasanTermuat: sumber.ringkasan.loaded,
        errorRingkasan: sumber.ringkasan.error,
        statusErrorRingkasan: sumber.ringkasan.status,
        kodeErrorRingkasan: sumber.ringkasan.errorCode,
        requestIdRingkasan: sumber.ringkasan.requestId,

        assignment: computed(() => sumber.assignment.data.value.assignment),
        assignmentMeta: sumber.assignment.data,
        memuatAssignment: sumber.assignment.loading,
        assignmentTermuat: sumber.assignment.loaded,
        errorAssignment: sumber.assignment.error,
        statusErrorAssignment: sumber.assignment.status,
        kodeErrorAssignment: sumber.assignment.errorCode,
        requestIdAssignment: sumber.assignment.requestId,
        assignmentQuery,

        hasil: sumber.hasil.data,
        memuatHasil: sumber.hasil.loading,
        hasilTermuat: sumber.hasil.loaded,
        errorHasil: sumber.hasil.error,
        statusErrorHasil: sumber.hasil.status,
        kodeErrorHasil: sumber.hasil.errorCode,
        requestIdHasil: sumber.hasil.requestId,

        riwayat: sumber.riwayat.data,
        memuatRiwayat: sumber.riwayat.loading,
        riwayatTermuat: sumber.riwayat.loaded,
        errorRiwayat: sumber.riwayat.error,
        statusErrorRiwayat: sumber.riwayat.status,
        kodeErrorRiwayat: sumber.riwayat.errorCode,
        requestIdRiwayat: sumber.riwayat.requestId,

        queryAssignment,
        muatRingkasan,
        muatAssignment,
        muatHasil,
        muatRiwayat,
        progres: sumber.progres.data,
        memuatProgres: sumber.progres.loading,
        errorProgres: sumber.progres.error,

        muatProgres,
        muatSemua,
        memuat: computed(() => Object.values(sumber).some((item) => item.loading.value)),
    };
}
