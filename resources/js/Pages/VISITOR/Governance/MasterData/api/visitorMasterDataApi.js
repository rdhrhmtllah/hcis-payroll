import axios from 'axios';

function unwrap(response) {
    const body = response?.data || {};
    const result = body.result ?? null;

    return {
        data: body.pagination
            ? {
                items: Array.isArray(result) ? result : [],
                relations: body.relations || {},
                summary: body.summary || {},
                stats: body.stats || [],
                pagination: body.pagination,
            }
            : result,
        message: body.message || '',
    };
}

/**
 * Galat pada permintaan blob datang sebagai Blob, bukan JSON.
 *
 * Tanpa pembacaan ini `error.response.data.message` selalu undefined dan
 * setiap kegagalan export — termasuk 403 gerbang halaman — muncul sebagai
 * pesan cadangan yang sama, jadi penyebab aslinya tak pernah sampai ke layar.
 */
async function bacaGalatBlob(error) {
    const data = error?.response?.data;
    if (typeof Blob === 'undefined' || !(data instanceof Blob)) return error;

    try {
        const teks = await data.text();
        error.response.data = JSON.parse(teks);
    } catch {
        error.response.data = {};
    }

    return error;
}

export function normalizeVisitorMasterError(error) {
    const body = error?.response?.data || {};
    const normalized = new Error(body.message || 'Master data belum dapat diproses.');
    normalized.status = error?.response?.status || 0;
    normalized.errorCode = body.error_code || 'VISITOR_MASTER_REQUEST_FAILED';
    normalized.requestId = body.request_id || '';
    normalized.errors = body.errors || {};
    return normalized;
}

async function request(config) {
    try {
        return unwrap(await axios(config));
    } catch (error) {
        throw normalizeVisitorMasterError(error);
    }
}

export const visitorMasterDataApi = {
    list(baseUrl, master, params = {}) {
        return request({ method: 'get', url: `${baseUrl}/${master}`, params });
    },
    create(baseUrl, master, payload) {
        return request({ method: 'post', url: `${baseUrl}/${master}`, data: payload });
    },
    update(baseUrl, master, id, payload) {
        return request({ method: 'put', url: `${baseUrl}/${master}/${id}`, data: payload });
    },
    changeStatus(baseUrl, master, id, status) {
        return request({ method: 'patch', url: `${baseUrl}/${master}/${id}/status`, data: { status } });
    },
    /** Dry run: ringkasan lembar + jumlah baris, tanpa berkas apa pun. */
    exportPreview(baseUrl, domain, params = {}) {
        return request({ method: 'get', url: `${baseUrl}/export/${domain}/pratinjau`, params });
    },
    /** Dry run import: berkas dinilai di server, tidak ada baris yang ditulis. */
    importPreview(baseUrl, domain, berkas) {
        return request({
            method: 'post',
            url: `${baseUrl}/import/${domain}/pratinjau`,
            data: berkasForm(berkas),
        });
    },
    /**
     * Berkas dikirim ULANG bersama sidik dari pratinjau.
     *
     * Server menolak kalau sidiknya tidak cocok, jadi yang ditulis selalu
     * berkas yang laporannya benar-benar dilihat orang.
     */
    importRun(baseUrl, domain, berkas, sidik) {
        return request({
            method: 'post',
            url: `${baseUrl}/import/${domain}`,
            data: berkasForm(berkas, sidik),
        });
    },
    async exportDownload(baseUrl, domain, params = {}) {
        try {
            const response = await axios({
                method: 'get',
                url: `${baseUrl}/export/${domain}`,
                params,
                responseType: 'blob',
            });

            return {
                blob: response.data,
                filename: namaBerkasDariHeader(response.headers?.['content-disposition']),
            };
        } catch (error) {
            throw normalizeVisitorMasterError(await bacaGalatBlob(error));
        }
    },
};

function berkasForm(berkas, sidik = '') {
    const form = new FormData();
    form.append('berkas', berkas);
    if (sidik) form.append('sidik', sidik);

    return form;
}

function namaBerkasDariHeader(contentDisposition) {
    const cocok = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(contentDisposition || '');

    return cocok ? decodeURIComponent(cocok[1]) : '';
}

/**
 * Nama berkas selalu datang dari server.
 *
 * Nama yang dirakit di layar akan menyimpang dari nama yang dipakai server
 * begitu salah satunya berubah, dan dua berkas dengan aturan penamaan berbeda
 * beredar di antara penerimanya.
 */
export function simpanBerkasUnduhan({ blob, filename }, namaCadangan = 'export.xlsx') {
    const url = URL.createObjectURL(blob);
    const tautan = document.createElement('a');
    tautan.href = url;
    tautan.download = filename || namaCadangan;
    document.body.appendChild(tautan);
    tautan.click();
    tautan.remove();
    URL.revokeObjectURL(url);
}
