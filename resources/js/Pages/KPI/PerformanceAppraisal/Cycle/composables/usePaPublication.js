import { ref } from 'vue';
import { hcisConfirm } from '@/components/ui';
import paApi from '../../api/paApi.js';

function pesanGalat(exception) {
    return exception?.message || 'Permintaan penerbitan tidak bisa diselesaikan.';
}

/**
 * Hasil yang dilewati karena KPI-nya belum lengkap WAJIB disebut: tanpa kalimat ini admin
 * membaca "penerbitan selesai" dan mengira semua yang DIHITUNG sudah sampai ke karyawan.
 */
export function pesanKpiBelumLengkap(data) {
    const jumlah = Number(data?.dilewati_kpi_belum_lengkap || 0);
    if (!jumlah) return '';

    const daftar = (data?.peserta_kpi_belum_lengkap || []).map((p) => p.nama).filter(Boolean);
    const nama = daftar.slice(0, 5).join(', ') + (daftar.length > 5 ? ` dan ${daftar.length - 5} lainnya` : '');

    return ` ${jumlah} dilewati karena sebagian pengajuan KPI-nya belum disetujui${nama ? ` (${nama})` : ''}; selesaikan persetujuannya lalu hitung ulang, atau terbitkan per orang dengan sadar.${pesanKategoriGugur(data)}`;
}

/**
 * Hasil yang dilewati karena ada kategori penilai GUGUR (pengisi di bawah minimal) — sama
 * wajibnya disebut (UAT 13 Okt 2026). Ikut ditempelkan oleh `pesanKpiBelumLengkap` supaya
 * setiap pesan penerbitan yang sudah ada otomatis menyebutnya.
 */
export function pesanKategoriGugur(data) {
    const jumlah = Number(data?.dilewati_kategori_gugur || 0);
    if (!jumlah) return '';

    const daftar = (data?.peserta_kategori_gugur || []).map((p) => p.nama).filter(Boolean);
    const nama = daftar.slice(0, 5).join(', ') + (daftar.length > 5 ? ` dan ${daftar.length - 5} lainnya` : '');

    return ` ${jumlah} dilewati karena ada kategori penilai yang tidak dihitung (pengisi di bawah minimal)${nama ? ` (${nama})` : ''}; tagih penilainya lalu hitung ulang, atau terbitkan per orang dengan sadar.`;
}

/** Ada yang dilewati penerbitan massal — nadanya peringatan, bukan ucapan sukses biasa. */
export function adaYangDilewati(data) {
    return Number(data?.dilewati_kpi_belum_lengkap || 0) + Number(data?.dilewati_kategori_gugur || 0) > 0;
}

/**
 * Terbitkan satu orang; bila server menahannya karena kategori penilai gugur, admin ditanya
 * dulu lalu — bila setuju — diterbitkan dengan sadar. Mengembalikan null bila dibatalkan.
 */
export async function terbitkanPesertaBerkonfirmasi(api, participantId) {
    return denganKonfirmasiGugur(
        (payload) => (payload ? api.terbitkanPeserta(participantId, payload) : api.terbitkanPeserta(participantId)),
        'Tetap terbitkan',
        'Hasil yang sudah terbit tidak bisa dihitung ulang.',
    );
}

/** Sama dengan terbitkanPesertaBerkonfirmasi, untuk rilis ke leader — rilis juga membekukan hasil. */
export async function rilisLeaderPesertaBerkonfirmasi(api, participantId) {
    return denganKonfirmasiGugur(
        (payload) => (payload ? api.rilisLeaderPeserta(participantId, payload) : api.rilisLeaderPeserta(participantId)),
        'Tetap rilis',
        'Hasil yang sudah dirilis ke leader tidak dihitung ulang.',
    );
}

async function denganKonfirmasiGugur(kirim, labelSetuju, akibat) {
    try {
        // Panggilan pertama persis seperti biasa — payload hanya dikirim saat admin menimpa penahanan.
        return await kirim();
    } catch (e) {
        if (e?.error_code !== 'PA_KATEGORI_GUGUR') throw e;

        const yakin = await hcisConfirm({
            title: 'Ada kategori penilai yang tidak dihitung',
            description: `${e.message} ${akibat}`,
            confirmText: labelSetuju,
            cancelText: 'Batal',
            sentiment: 'warning',
            icon: 'exclamation-triangle',
        });

        // Penanda eksplisit, bukan null: API yang sukses pun boleh mengembalikan isi kosong.
        if (!yakin) return { dibatalkan: true };

        return kirim({ abaikan_kategori_gugur: true });
    }
}

function metadataGalat(exception) {
    return {
        status: Number(exception?.status || exception?.response?.status || 0),
        errorCode: exception?.error_code || exception?.response?.data?.error_code || '',
        requestId: exception?.request_id || exception?.response?.data?.request_id || '',
    };
}

/**
 * Lifecycle ringkasan dan aksi penerbitan hasil.
 *
 * Dialog hanya mengatur konfirmasi dan presentasi. Endpoint tetap berada di paApi
 * melalui boundary composable ini, termasuk proteksi double-submit dan stale response.
 */
export function usePaPublication(api = paApi) {
    const ringkasan = ref(null);
    const loading = ref(false);
    const error = ref('');
    const errorStatus = ref(0);
    const errorCode = ref('');
    const requestId = ref('');
    const acting = ref(false);
    const actionName = ref('');
    const actionResult = ref(null);
    const actionError = ref('');
    const actionErrorStatus = ref(0);
    const actionErrorCode = ref('');
    const actionRequestId = ref('');
    const actionSuccess = ref('');
    let urutanPermintaan = 0;

    function bersihkanGalatMuat() {
        error.value = '';
        errorStatus.value = 0;
        errorCode.value = '';
        requestId.value = '';
    }

    function bersihkanAksi() {
        actionName.value = '';
        actionResult.value = null;
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
        ringkasan.value = null;
        loading.value = false;
        bersihkanGalatMuat();
        acting.value = false;
        bersihkanAksi();
    }

    async function muat(cycleId) {
        if (!cycleId) return { stale: false, skipped: true };

        const nomorPermintaan = ++urutanPermintaan;
        loading.value = true;
        bersihkanGalatMuat();

        try {
            const data = await api.ringkasanPenerbitan(cycleId);
            if (nomorPermintaan !== urutanPermintaan) return { stale: true };

            ringkasan.value = data || null;
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

    async function jalankan(nama, request, pesanSukses) {
        if (acting.value) return { skipped: true };

        acting.value = true;
        actionName.value = nama;
        bersihkanAksi();
        actionName.value = nama;

        try {
            const data = await request();
            actionResult.value = data || null;
            actionSuccess.value = pesanSukses(data);
            return { data };
        } catch (exception) {
            setGalatAksi(exception);
            return { error: exception };
        } finally {
            acting.value = false;
            actionName.value = '';
        }
    }

    function terbitkan(cycleId, payload = {}) {
        if (!cycleId) return Promise.resolve({ skipped: true });

        return jalankan(
            'terbitkan',
            () => api.terbitkan(cycleId, payload || {}),
            (data) =>
                `Penerbitan selesai: ${data?.diterbitkan ?? 0} diterbitkan, ${data?.sudah_terbit ?? 0} sudah terbit, ${data?.tertahan ?? 0} tertahan, ${data?.belum_dihitung ?? 0} belum dihitung.${pesanKpiBelumLengkap(data)}`,
        ).then(async (response) => {
            if (response?.data) await muat(cycleId);
            return response;
        });
    }

    function tutup(cycleId) {
        if (!cycleId) return Promise.resolve({ skipped: true });

        return jalankan(
            'tutup',
            () => api.tutupPenerbitan(cycleId),
            (data) => `Penerbitan siklus ditutup dengan status ${data?.status || 'PUBLISHED'}.`,
        );
    }

    async function terbitkanPeserta(participantId) {
        if (!participantId) return { skipped: true };

        const respons = await jalankan(
            'terbitkan-peserta',
            () => terbitkanPesertaBerkonfirmasi(api, participantId),
            (data) => (data?.dibatalkan ? '' : `Hasil peserta diterbitkan dengan status ${data?.status || 'TERBIT'}.`),
        );

        // Dibatalkan di konfirmasi kategori gugur: bukan sukses, bukan galat.
        return respons?.data?.dibatalkan ? { skipped: true } : respons;
    }

    return {
        ringkasan,
        loading,
        error,
        errorStatus,
        errorCode,
        requestId,
        acting,
        actionName,
        actionResult,
        actionError,
        actionErrorStatus,
        actionErrorCode,
        actionRequestId,
        actionSuccess,
        muat,
        reset,
        terbitkan,
        terbitkanPeserta,
        tutup,
    };
}

export default usePaPublication;
