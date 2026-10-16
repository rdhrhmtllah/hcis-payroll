import axios from 'axios';

/**
 * Klien Export Studio.
 *
 * baseUrl datang dari halaman, bukan dari konstanta di sini. Itu yang membuat
 * kerangka ini bisa dipakai modul lain tanpa disentuh: SOP mengirim
 * '/api/v1/sop/export-studio', modul berikutnya mengirim miliknya sendiri.
 */
export function reportStudioApi(baseUrl) {
    const url = (path) => `${baseUrl}${path}`;

    return {
        katalog: (dataset) => axios.get(url(`/katalog/${dataset}`)).then((r) => r.data.result),

        galeri: () => axios.get(url('/template')).then((r) => r.data.result),

        simpan: (payload) => axios.post(url('/template'), payload).then((r) => r.data.result),

        perbarui: (kode, payload) => axios.put(url(`/template/${kode}`), payload).then((r) => r.data.result),

        duplikat: (kode) => axios.post(url(`/template/${kode}/duplikat`)).then((r) => r.data.result),

        hapus: (kode) => axios.delete(url(`/template/${kode}`)),

        /**
         * signal wajib diteruskan: definisi berubah lebih cepat daripada
         * pratinjau selesai, dan hasil lama yang datang belakangan akan
         * menimpa hasil baru bila request-nya tidak dibatalkan.
         */
        pratinjau: (payload, signal) => {
            const body = (typeof payload === 'object' && payload !== null && ('definisi' in payload || 'template_kode' in payload))
                ? payload
                : { definisi: payload };
            return axios.post(url('/pratinjau'), body, { signal }).then((r) => r.data.result);
        },

        jalankan: (payload) => axios.post(url('/jalankan'), payload).then((r) => r.data.result),
    };
}
