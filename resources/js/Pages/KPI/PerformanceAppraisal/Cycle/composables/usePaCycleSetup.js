import { reactive, ref } from 'vue';
import paApi from '../../api/paApi.js';

export const CYCLE_FORM_FIELDS = [
    'kode_cycle',
    'nama_cycle',
    'tahun',
    'deskripsi',
    'periode_dinilai_dari',
    'periode_dinilai_sampai',
    'pengisian_dari',
    'pengisian_sampai',
    'tanggal_acuan_organisasi',
];

const RESPONSE_FIELDS = {
    kode_cycle: 'Kode_Cycle',
    nama_cycle: 'Nama_Cycle',
    tahun: 'Tahun',
    deskripsi: 'Deskripsi',
    periode_dinilai_dari: 'Periode_Dinilai_Dari',
    periode_dinilai_sampai: 'Periode_Dinilai_Sampai',
    pengisian_dari: 'Pengisian_Dari',
    pengisian_sampai: 'Pengisian_Sampai',
    tanggal_acuan_organisasi: 'Tanggal_Acuan_Organisasi',
};

const OPTIONAL_FIELDS = [
    'deskripsi',
    'periode_dinilai_dari',
    'periode_dinilai_sampai',
    'pengisian_dari',
    'pengisian_sampai',
    'tanggal_acuan_organisasi',
];

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

export function formulirKosong() {
    return CYCLE_FORM_FIELDS.reduce((form, field) => {
        form[field] = '';
        return form;
    }, {});
}

function tanggal(value) {
    return value ? String(value).slice(0, 10) : '';
}

export function formulirDariSiklus(cycle) {
    return CYCLE_FORM_FIELDS.reduce((form, field) => {
        const value = cycle?.[RESPONSE_FIELDS[field]];
        form[field] = field === 'tahun' && value !== null && value !== undefined ? String(value) : tanggal(value);
        if (field === 'kode_cycle' || field === 'nama_cycle' || field === 'deskripsi') {
            form[field] = value === null || value === undefined ? '' : String(value);
        }
        return form;
    }, formulirKosong());
}

function nilaiNormal(value) {
    return value === null || value === undefined ? '' : String(value);
}

function nilaiKirim(field, value) {
    if (field === 'tahun') return Number(value);
    return value === '' ? null : String(value).trim();
}

export function susunPayloadBuat(form) {
    /* `kode_cycle` TIDAK ikut dikirim: server yang membuatnya
       (`PaCycleSetupService::kodeBaru()`). Mengirimkannya tetap diterima server dan
       diabaikan, tetapi mengirim nilai yang pasti dibuang membuat pembaca berikutnya
       mengira layar ini masih ikut menentukan kodenya. */
    const payload = {
        nama_cycle: String(form?.nama_cycle || '').trim(),
        tahun: Number(form?.tahun),
    };

    OPTIONAL_FIELDS.forEach((field) => {
        payload[field] = nilaiKirim(field, nilaiNormal(form?.[field]));
    });

    return payload;
}

export function susunPayloadUbah(form, originalForm) {
    const payload = {};

    CYCLE_FORM_FIELDS.filter((field) => field !== 'kode_cycle').forEach((field) => {
        const next = nilaiNormal(form?.[field]);
        const previous = nilaiNormal(originalForm?.[field]);
        if (next !== previous) payload[field] = nilaiKirim(field, next);
    });

    return payload;
}

export function validasiForm(form, mode = 'create') {
    // Kode tidak lagi diperiksa: ia dibuat server, dan menuntut orang mengisinya berarti
    // menuntut sesuatu yang tidak ada kolomnya lagi di layar.
    if (!String(form?.nama_cycle || '').trim()) return 'Nama siklus wajib diisi.';
    if (!String(form?.tahun || '').trim()) return 'Tahun wajib diisi.';
    return '';
}

/**
 * Orkestrasi detail, pembuatan, dan penyuntingan siklus.
 *
 * A09 sengaja tetap berada di boundary Cycle karena registry hanya menyediakan
 * satu page parent `/performance-appraisal`. Dialog menerima state dan
 * mengeluarkan draft; semua request tetap melalui paApi.
 */
export function usePaCycleSetup(api = paApi) {
    const terbuka = ref(false);
    const mode = ref('create');
    const activeCycleId = ref(null);
    const cycle = ref(null);
    const detail = ref(null);
    const form = reactive(formulirKosong());
    const originalForm = ref(formulirKosong());

    const detailLoading = ref(false);
    const detailError = ref('');
    const detailErrorStatus = ref(0);
    const detailErrorCode = ref('');
    const detailRequestId = ref('');

    const saving = ref(false);
    const saveError = ref('');
    const saveErrorStatus = ref(0);
    const saveErrorCode = ref('');
    const saveRequestId = ref('');
    const saveSuccess = ref('');
    const saveResult = ref(null);
    const validationError = ref('');
    let urutanPermintaan = 0;

    function isiForm(nextForm) {
        CYCLE_FORM_FIELDS.forEach((field) => {
            form[field] = nextForm?.[field] ?? '';
        });
    }

    function bersihkanDetailError() {
        detailError.value = '';
        detailErrorStatus.value = 0;
        detailErrorCode.value = '';
        detailRequestId.value = '';
    }

    function bersihkanSaveState() {
        saveError.value = '';
        saveErrorStatus.value = 0;
        saveErrorCode.value = '';
        saveRequestId.value = '';
        saveSuccess.value = '';
        saveResult.value = null;
        validationError.value = '';
    }

    function reset() {
        urutanPermintaan += 1;
        terbuka.value = false;
        mode.value = 'create';
        activeCycleId.value = null;
        cycle.value = null;
        detail.value = null;
        isiForm(formulirKosong());
        originalForm.value = formulirKosong();
        detailLoading.value = false;
        bersihkanDetailError();
        saving.value = false;
        bersihkanSaveState();
    }

    function bukaBuat() {
        urutanPermintaan += 1;
        terbuka.value = true;
        mode.value = 'create';
        activeCycleId.value = null;
        cycle.value = null;
        detail.value = null;
        isiForm(formulirKosong());
        originalForm.value = formulirKosong();
        detailLoading.value = false;
        bersihkanDetailError();
        bersihkanSaveState();
    }

    async function bukaUbah(row) {
        const cycleId = row?.Id_PA_Cycle;
        if (!cycleId) return { skipped: true };

        const nomorPermintaan = ++urutanPermintaan;
        terbuka.value = true;
        mode.value = 'edit';
        activeCycleId.value = cycleId;
        cycle.value = row || null;
        detail.value = null;
        isiForm(formulirDariSiklus(row));
        originalForm.value = formulirDariSiklus(row);
        detailLoading.value = true;
        bersihkanDetailError();
        bersihkanSaveState();

        try {
            const data = await api.detailSiklus(cycleId);
            if (nomorPermintaan !== urutanPermintaan) return { stale: true };

            detail.value = data || null;
            cycle.value = data?.siklus || row || null;
            const nextForm = formulirDariSiklus(data?.siklus || row);
            isiForm(nextForm);
            originalForm.value = { ...nextForm };
            return { data };
        } catch (exception) {
            if (nomorPermintaan !== urutanPermintaan) return { stale: true };

            const metadata = metadataGalat(exception);
            detailError.value = pesanGalat(exception);
            detailErrorStatus.value = metadata.status;
            detailErrorCode.value = metadata.errorCode;
            detailRequestId.value = metadata.requestId;
            return { error: exception };
        } finally {
            if (nomorPermintaan === urutanPermintaan) detailLoading.value = false;
        }
    }

    function tutup() {
        reset();
    }

    async function simpan(nextForm) {
        if (saving.value) return { skipped: true };

        const draft = { ...form, ...(nextForm || {}) };
        validationError.value = validasiForm(draft, mode.value);
        if (validationError.value) return { error: new Error(validationError.value), validation: true };
        if (mode.value === 'edit' && (!activeCycleId.value || !detail.value)) {
            return { skipped: true };
        }

        const payload = mode.value === 'create'
            ? susunPayloadBuat(draft)
            : susunPayloadUbah(draft, originalForm.value);

        if (mode.value === 'edit' && Object.keys(payload).length === 0) {
            saveSuccess.value = 'Tidak ada perubahan yang disimpan.';
            saveResult.value = null;
            return { skipped: true, unchanged: true };
        }

        saving.value = true;
        bersihkanSaveState();

        try {
            const data = mode.value === 'create'
                ? await api.buatSiklus(payload)
                : await api.perbaruiSiklus(activeCycleId.value, payload);
            saveResult.value = data || null;
            saveSuccess.value = mode.value === 'create'
                ? `Siklus ${data?.kode_cycle || '(kode menyusul)'} berhasil dibuat sebagai ${data?.status || 'DRAFT'}.`
                : data?.perlu_siapkan_ulang
                    ? 'Siklus diperbarui. Siklus perlu disiapkan ulang agar salinan peserta mengikuti periode terbaru.'
                    : 'Perubahan siklus berhasil disimpan.';
            return { data };
        } catch (exception) {
            const metadata = metadataGalat(exception);
            saveError.value = pesanGalat(exception);
            saveErrorStatus.value = metadata.status;
            saveErrorCode.value = metadata.errorCode;
            saveRequestId.value = metadata.requestId;
            return { error: exception };
        } finally {
            saving.value = false;
        }
    }

    return {
        terbuka,
        mode,
        activeCycleId,
        cycle,
        detail,
        form,
        detailLoading,
        detailError,
        detailErrorStatus,
        detailErrorCode,
        detailRequestId,
        saving,
        saveError,
        saveErrorStatus,
        saveErrorCode,
        saveRequestId,
        saveSuccess,
        saveResult,
        validationError,
        bukaBuat,
        bukaUbah,
        tutup,
        simpan,
        reset,
    };
}

export default usePaCycleSetup;
