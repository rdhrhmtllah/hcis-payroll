import { computed, reactive, ref } from 'vue';
import paApi from '../../api/paApi.js';

function pesanGalat(exception) {
    return exception?.message || 'Permintaan tidak bisa diselesaikan.';
}

/**
 * Orkestrasi penugasan penilai untuk satu peserta.
 *
 * Page memilih peserta aktif dan meneruskan event dari dialog. Composable ini
 * menjadi batas request, menyusun payload sesuai kontrak, serta menjaga state
 * loading/error agar dialog tetap presentasional.
 */
export function usePaAssignment(api = paApi) {
    const usulan = ref({});
    const kesiapan = ref(null);
    const memuat = ref(false);
    const error = ref('');
    const errorStatus = ref(0);
    const errorCode = ref('');
    const requestId = ref('');
    const menyimpan = ref(false);
    const menghitung = ref(false);
    const submitError = ref('');
    const submitErrorStatus = ref(0);
    const submitErrorCode = ref('');
    const submitRequestId = ref('');
    const submitSuccess = ref('');
    const hasilPeserta = ref(null);
    const pilihan = reactive({});
    const manual = reactive({});

    /* Penilai yang DICENTANG OTOMATIS tetapi BELUM tersimpan, per kategori.

       Dilacak terpisah dari `pilihan` karena keduanya berbeda arti: `pilihan` adalah "yang akan
       tersimpan kalau Simpan ditekan", sedangkan ini "yang dipilihkan sistem, belum disetujui
       siapa pun". Tanpa pembedaan itu, layar akan menampilkan centang yang terlihat sama dengan
       penugasan yang sudah berlaku — dan admin akan menutup dialog tanpa menekan Simpan,
       menyangka pekerjaannya sudah beres. */
    const usulanBelumSimpan = reactive({});
    let urutanPermintaan = 0;

    const kategori = computed(() => Object.values(usulan.value || {}));
    const jumlahTerpilih = computed(() =>
        Object.values(pilihan).reduce((total, values) => total + values.length, 0),
    );

    function resetForm() {
        Object.keys(pilihan).forEach((key) => delete pilihan[key]);
        Object.keys(manual).forEach((key) => delete manual[key]);
        Object.keys(usulanBelumSimpan).forEach((key) => delete usulanBelumSimpan[key]);
        hasilPeserta.value = null;
        submitError.value = '';
        submitErrorStatus.value = 0;
        submitErrorCode.value = '';
        submitRequestId.value = '';
        submitSuccess.value = '';
    }

    function reset() {
        urutanPermintaan += 1;
        usulan.value = {};
        kesiapan.value = null;
        memuat.value = false;
        error.value = '';
        errorStatus.value = 0;
        errorCode.value = '';
        requestId.value = '';
        resetForm();
    }

    function siapkanForm(data) {
        resetForm();

        Object.values(data || {}).forEach((item) => {
            const calon = item.calon || [];

            /* Yang SUDAH tersimpan selalu tercentang. Ini bukan kenyamanan: kalau penugasan yang
               berlaku tidak tercentang, menekan Simpan akan mengirim daftar tanpa mereka. */
            const terpasang = calon
                .filter((candidate) => candidate.sudah_ditugaskan)
                .map((candidate) => candidate.kode_karyawan);

            /* Sisanya DIUSULKAN sampai memenuhi minimum aturan — hanya sebanyak kurangnya, tidak
               lebih.

               Kenapa mengusulkan sama sekali: calon di sini diturunkan dari relasi organisasi yang
               sudah ditegakkan aturannya, dan jalur massal (`pa:buat-penugasan`) memang memilih
               dengan cara yang sama. Membiarkan dialognya kosong berarti dua jalur mengambil
               keputusan berbeda atas data yang sama, dan admin harus mengulang pekerjaan yang
               sudah bisa dihitung.

               Kenapa hanya sampai MINIMUM, bukan maksimum: melebihi minimum adalah keputusan
               yang punya biaya — tiap penilai tambahan menggeser bobot penilai lain dalam
               kategori yang sama. Itu keputusan admin, bukan bawaan. */
            const min = Number(item.min_rater ?? 0);
            const usul = [];

            for (const candidate of calon) {
                if (terpasang.length + usul.length >= min) {
                    break;
                }

                if (candidate.sudah_ditugaskan || terpasang.includes(candidate.kode_karyawan)) {
                    continue;
                }

                /* Yang baru dicabut admin tidak diusulkan lagi — kalau ya, centangnya muncul
                   kembali tepat sesudah pencabutan dan terlihat seolah pencabutannya gagal. */
                if (candidate.pernah_dicabut) {
                    continue;
                }

                usul.push(candidate.kode_karyawan);
            }

            pilihan[item.kode_kategori] = [...terpasang, ...usul];
            usulanBelumSimpan[item.kode_kategori] = usul;
            manual[item.kode_kategori] = { kode_perusahaan: '', kode_karyawan: '', alasan_manual: '' };
        });
    }

    async function muat(participantId) {
        if (!participantId) return { stale: false, skipped: true };

        const nomorPermintaan = ++urutanPermintaan;
        memuat.value = true;
        error.value = '';
        errorStatus.value = 0;
        errorCode.value = '';
        requestId.value = '';

        try {
            const data = await api.usulanPenilai(participantId);
            if (nomorPermintaan !== urutanPermintaan) return { stale: true };

            usulan.value = data?.usulan || {};
            kesiapan.value = data?.kesiapan || null;
            siapkanForm(usulan.value);
            return { stale: false, data };
        } catch (exception) {
            if (nomorPermintaan !== urutanPermintaan) return { stale: true };

            error.value = pesanGalat(exception);
            errorStatus.value = Number(exception?.status || exception?.response?.status || 0);
            errorCode.value = exception?.error_code || '';
            requestId.value = exception?.request_id || '';
            return { stale: false, error: exception };
        } finally {
            if (nomorPermintaan === urutanPermintaan) memuat.value = false;
        }
    }

    function ubahPilihan(kodeKategori, candidate) {
        bersihkanSubmitError();
        const values = pilihan[kodeKategori] || (pilihan[kodeKategori] = []);
        const index = values.indexOf(candidate.kode_karyawan);
        if (index >= 0) values.splice(index, 1);
        else values.push(candidate.kode_karyawan);

        /* Begitu admin menyentuhnya, ia bukan usulan lagi — ia keputusannya. Membiarkannya
           tetap terhitung sebagai "usulan belum disimpan" membuat peringatannya berbohong. */
        const usul = usulanBelumSimpan[kodeKategori];

        if (Array.isArray(usul)) {
            const i = usul.indexOf(candidate.kode_karyawan);

            if (i >= 0) {
                usul.splice(i, 1);
            }
        }
    }

    function ubahManual(kodeKategori, field, value) {
        bersihkanSubmitError();
        if (!manual[kodeKategori]) manual[kodeKategori] = { kode_perusahaan: '', kode_karyawan: '', alasan_manual: '' };
        manual[kodeKategori][field] = value;
    }

    function susunPenilai() {
        return kategori.value.flatMap((item) => {
            const candidates = (item.calon || [])
                .filter((candidate) => (pilihan[item.kode_kategori] || []).includes(candidate.kode_karyawan))

                /*
                 | Yang SUDAH terpasang tidak ikut dikirim.
                 |
                 | `pilihan` sengaja berisi `[...terpasang, ...usul]` supaya kotak centangnya
                 | tampil tercentang. Tetapi endpoint ini MEMBUAT penugasan, dan `tugaskan()`
                 | menolak penilai yang sudah ada dengan "sudah ditugaskan sebagai ..." — jadi
                 | mengirim seluruh isi centang membuat Simpan GAGAL bagi setiap peserta yang
                 | sudah punya penilai, yaitu hampir semuanya.
                 |
                 | Akibat yang tak terlihat: penyimpanan bobot berjalan SESUDAH ini, sehingga
                 | mengubah bobot lalu menekan Simpan tidak pernah tersimpan — galatnya datang
                 | dari langkah pertama, jauh dari tempat gejalanya terasa.
                 |
                 | Penjaga di server sengaja TIDAK dilemahkan: ia benar untuk pemanggil yang
                 | betul-betul menugaskan orang yang sama dua kali. Yang salah adalah muatan
                 | yang dikirim layar ini.
                 */
                .filter((candidate) => ! candidate.sudah_ditugaskan)
                .map((candidate) => ({
                    kode_kategori: item.kode_kategori,
                    kode_perusahaan: candidate.kode_perusahaan,
                    kode_karyawan: candidate.kode_karyawan,
                    sumber: 'USULAN',
                    organization_relationship_id: candidate.organization_relationship_id,
                    alasan_manual: null,
                }));
            const form = manual[item.kode_kategori];
            if (item.butuh_manual && item.izinkan_manual && form?.kode_karyawan) {
                if (!form.alasan_manual.trim()) throw new Error('Alasan manual wajib diisi untuk penilai manual.');
                candidates.push({
                    kode_kategori: item.kode_kategori,
                    kode_perusahaan: form.kode_perusahaan,
                    kode_karyawan: form.kode_karyawan,
                    sumber: 'MANUAL',
                    organization_relationship_id: null,
                    alasan_manual: form.alasan_manual.trim(),
                });
            }
            return candidates;
        });
    }

    function bersihkanSubmitError() {
        submitError.value = '';
        submitErrorStatus.value = 0;
        submitErrorCode.value = '';
        submitRequestId.value = '';
        submitSuccess.value = '';
    }

    async function simpan(participantId, tambahan = {}) {
        if (!participantId || menyimpan.value) return { skipped: true };

        menyimpan.value = true;
        bersihkanSubmitError();
        try {
            const penilai = susunPenilai();

            /*
             | TIDAK ADA PENILAI BARU = TIDAK ADA PERMINTAAN.
             |
             | Endpoint ini memvalidasi `penilai` sebagai `required|array|min:1`, jadi mengirim
             | daftar kosong dijawab "The penilai field is required." — padahal tidak ada yang
             | salah: admin hanya mengubah bobot tanpa menambah siapa pun.
             |
             | Akibatnya bukan cuma pesan yang membingungkan. Penyimpanan bobot berjalan SESUDAH
             | langkah ini dan hanya bila langkah ini tidak melempar galat, sehingga penolakan di
             | sini membuat perubahan bobot tidak pernah tersimpan.
             |
             | Kembaliannya dibentuk seperti kembalian server supaya pemanggil tidak perlu tahu
             | cabang mana yang dilaluinya.
             */
            const adaBobot = (tambahan.bobot?.length || 0) > 0 || (tambahan.bobot_rata?.length || 0) > 0;

            if (penilai.length === 0 && ! adaBobot) {
                return {
                    data: {
                        dibuat: 0,
                        kategori_dihitung_ulang: [],
                        bobot_kustom_digugurkan: [],
                        bobot_diterapkan: 0,
                        kategori_dikembalikan_rata: [],
                    },
                };
            }

            /* Penilai DAN bobotnya dalam SATU permintaan: server mengerjakan keduanya dalam satu
               transaksi, jadi tidak ada lagi jeda di mana penugasannya sudah tersimpan sementara
               bobotnya belum, dan layar cukup menyegarkan diri sekali. */
            const data = await api.tugaskanPenilai(participantId, penilai, tambahan);
            const dibuat = data?.dibuat;

            /* Nol penugasan baru BUKAN "0 penugasan tersimpan" — itu terbaca seperti kegagalan.
               Bila yang berubah hanya bobotnya, saluran bobot yang berbicara, bukan yang ini. */
            /* Sesudah dikunci server langsung menjadwalkannya ke HC Learn — disebut di sini. */
            const jadwal = data?.jadwal_hclearn?.catatan;
            submitSuccess.value = dibuat ? `${dibuat} penugasan tersimpan.${jadwal ? ` ${jadwal}` : ''}` : '';
            return { data };
        } catch (exception) {
            submitError.value = pesanGalat(exception);
            submitErrorStatus.value = Number(exception?.status || exception?.response?.status || 0);
            submitErrorCode.value = exception?.error_code || '';
            submitRequestId.value = exception?.request_id || '';
            return { error: exception };
        } finally {
            menyimpan.value = false;
        }
    }

    async function hitung(participantId) {
        if (!participantId || menghitung.value) return { skipped: true };

        menghitung.value = true;
        bersihkanSubmitError();
        try {
            hasilPeserta.value = await api.hitungPeserta(participantId);
            return { data: hasilPeserta.value };
        } catch (exception) {
            submitError.value = pesanGalat(exception);
            submitErrorStatus.value = Number(exception?.status || exception?.response?.status || 0);
            submitErrorCode.value = exception?.error_code || '';
            submitRequestId.value = exception?.request_id || '';
            return { error: exception };
        } finally {
            menghitung.value = false;
        }
    }

    return {
        usulan,
        kesiapan,
        kategori,
        jumlahTerpilih,

        /* Berapa centang yang berasal dari usulan sistem dan belum tersimpan. Layar WAJIB
           menyebutkannya: centang yang terlihat sama dengan penugasan berlaku, padahal belum,
           adalah bentuk kesalahan yang paling mudah tidak disadari. */
        jumlahUsulanBelumSimpan: computed(() =>
            Object.values(usulanBelumSimpan).reduce((total, values) => total + (values?.length || 0), 0),
        ),

        pilihan,
        usulanBelumSimpan,
        manual,
        memuat,
        error,
        errorStatus,
        errorCode,
        requestId,
        menyimpan,
        menghitung,
        submitError,
        submitErrorStatus,
        submitErrorCode,
        submitRequestId,
        submitSuccess,
        hasilPeserta,
        muat,
        reset,
        ubahPilihan,
        ubahManual,
        simpan,
        hitung,
    };
}

export default usePaAssignment;
