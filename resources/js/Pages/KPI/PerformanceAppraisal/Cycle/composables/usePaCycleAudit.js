import { reactive, ref } from 'vue';
import paApi from '../../api/paApi.js';

const FILTER_DEFAULTS = {
    jenis: '',
    participant_id: '',
    q: '',
    hanya_keputusan_manusia: false,
    halaman: 1,
    per_halaman: 10,
};

function pesanGalat(exception) {
    return exception?.message || 'Riwayat audit tidak dapat dimuat.';
}

function metadataGalat(exception) {
    return {
        status: Number(exception?.status || exception?.response?.status || 0),
        errorCode: exception?.error_code || exception?.response?.data?.error_code || '',
        requestId: exception?.request_id || exception?.response?.data?.request_id || '',
    };
}

function queryDariFilter(filters) {
    return {
        jenis: filters.jenis || undefined,
        participant_id: filters.participant_id ? Number(filters.participant_id) : undefined,
        q: filters.q?.trim() || undefined,
        /* DIKIRIM `1`, ATAU TIDAK DIKIRIM SAMA SEKALI — bukan `true`/`false`.

           Ini permintaan GET: `URLSearchParams` mengubah boolean menjadi teks, sehingga
           `false` berangkat sebagai string `"false"`. Aturan `boolean` Laravel menerima
           true, false, 1, 0, "1", dan "0" — tetapi TIDAK menerima "false", jadi setiap
           permintaan audit dijawab 422 "must be true or false". Cacatnya duduk persis di
           batas HTTP: layanan yang dipanggil langsung bekerja, dan uji yang memalsukan
           api-nya juga hijau, sehingga tak satu pun dari keduanya pernah menyentuhnya.

           Pola `1`/`undefined` inilah yang sudah dipakai `hanya_bermasalah` di halaman
           penugasan; yang di sini menyimpang sendirian. */
        hanya_keputusan_manusia: filters.hanya_keputusan_manusia ? 1 : undefined,
        halaman: Number(filters.halaman) || 1,
        per_halaman: Math.min(100, Math.max(1, Number(filters.per_halaman) || 10)),
    };
}

/**
 * Orkestrasi lini masa audit A12.
 *
 * Filter dan pagination tetap dikirim ke server karena audit adalah data
 * berhalaman. UI tidak menyaring kejadian sendiri dan tidak menghapus cacah
 * sistem saat mode keputusan manusia dipilih.
 */
export function usePaCycleAudit(api = paApi) {
    const terbuka = ref(false);
    const cycle = ref(null);
    const kejadian = ref([]);
    const jenisTersedia = ref([]);
    const cacah = ref({ keputusan_manusia: 0, oleh_sistem: 0 });
    const batas = ref(null);
    const filters = reactive({ ...FILTER_DEFAULTS });
    const pagination = reactive({ halaman: 1, per_halaman: 10, total: 0, total_halaman: 1, dipangkas: false });

    const loading = ref(false);
    const error = ref('');
    const errorStatus = ref(0);
    const errorCode = ref('');
    const requestId = ref('');
    let urutanPermintaan = 0;

    function bersihkanError() {
        error.value = '';
        errorStatus.value = 0;
        errorCode.value = '';
        requestId.value = '';
    }

    function resetData() {
        cycle.value = null;
        kejadian.value = [];
        jenisTersedia.value = [];
        cacah.value = { keputusan_manusia: 0, oleh_sistem: 0 };
        batas.value = null;
        Object.assign(pagination, { halaman: 1, per_halaman: 10, total: 0, total_halaman: 1, dipangkas: false });
    }

    function resetFilter() {
        Object.assign(filters, FILTER_DEFAULTS);
    }

    function reset() {
        urutanPermintaan += 1;
        terbuka.value = false;
        resetData();
        resetFilter();
        loading.value = false;
        bersihkanError();
    }

    async function muat(cycleId, patch = {}) {
        if (!cycleId) return { skipped: true };

        Object.assign(filters, patch);
        const nomorPermintaan = ++urutanPermintaan;
        loading.value = true;
        bersihkanError();

        try {
            const data = await api.auditSiklus(cycleId, queryDariFilter(filters));
            if (nomorPermintaan !== urutanPermintaan) return { stale: true };

            cycle.value = data?.siklus || null;
            kejadian.value = data?.kejadian || [];
            jenisTersedia.value = data?.jenis_tersedia || [];
            cacah.value = data?.cacah || { keputusan_manusia: 0, oleh_sistem: 0 };
            batas.value = data?.batas || null;
            Object.assign(pagination, {
                halaman: Number(data?.halaman || filters.halaman || 1),
                per_halaman: Number(data?.per_halaman || filters.per_halaman || 10),
                total: Number(data?.total || 0),
                total_halaman: Number(data?.total_halaman || 1),
                dipangkas: Boolean(data?.dipangkas),
            });
            filters.halaman = pagination.halaman;
            filters.per_halaman = pagination.per_halaman;
            return { data };
        } catch (exception) {
            if (nomorPermintaan !== urutanPermintaan) return { stale: true };

            const metadata = metadataGalat(exception);
            error.value = pesanGalat(exception);
            errorStatus.value = metadata.status;
            errorCode.value = metadata.errorCode;
            requestId.value = metadata.requestId;
            return { error: exception };
        } finally {
            if (nomorPermintaan === urutanPermintaan) loading.value = false;
        }
    }

    function buka(row) {
        const cycleId = row?.Id_PA_Cycle;
        if (!cycleId) return Promise.resolve({ skipped: true });

        terbuka.value = true;
        resetData();
        resetFilter();
        return muat(cycleId);
    }

    function saring(cycleId, patch) {
        filters.halaman = 1;
        return muat(cycleId, patch);
    }

    function gantiHalaman(cycleId, halaman) {
        return muat(cycleId, { halaman: Number(halaman) || 1 });
    }

    function gantiUkuranHalaman(cycleId, per_halaman) {
        return muat(cycleId, { halaman: 1, per_halaman: Math.min(100, Math.max(1, Number(per_halaman) || 10)) });
    }

    return {
        terbuka,
        cycle,
        kejadian,
        jenisTersedia,
        cacah,
        batas,
        filters,
        pagination,
        loading,
        error,
        errorStatus,
        errorCode,
        requestId,
        buka,
        muat,
        saring,
        gantiHalaman,
        gantiUkuranHalaman,
        reset,
        resetFilter,
    };
}

export default usePaCycleAudit;
