export function normalizeApiError(error) {
    const response = error?.response
    const data = response?.data || {}
    const status = response?.status || null
    const requestId = data.request_id || null

    if (!response) {
        return { code: 'NETWORK_ERROR', message: 'Koneksi bermasalah. Periksa jaringan Anda lalu coba lagi.', action: 'retry', retryable: true, status: null, requestId, validationErrors: {} }
    }
    if (status === 401 || data.error_code === 'UNAUTHENTICATED') {
        return { code: 'UNAUTHENTICATED', message: 'Sesi Anda telah berakhir. Silakan masuk kembali.', action: '', retryable: false, status, requestId, validationErrors: {} }
    }
    if (status === 403 || data.error_code === 'FORBIDDEN') {
        return { code: 'FORBIDDEN', message: 'Anda tidak memiliki akses ke fitur ini.', action: '', retryable: false, status, requestId, validationErrors: {} }
    }
    if (status === 404 || data.error_code === 'NOT_FOUND') {
        return { code: 'NOT_FOUND', message: 'Data yang diminta tidak ditemukan.', action: '', retryable: false, status, requestId, validationErrors: {} }
    }
    if (status === 409 || data.error_code === 'STATE_CONFLICT') {
        return { code: 'STATE_CONFLICT', message: 'Data sudah berubah. Silakan muat ulang halaman.', action: 'refresh', retryable: false, status, requestId, validationErrors: {} }
    }
    if (status === 422 || data.error_code === 'VALIDATION_FAILED') {
        return { code: 'VALIDATION_FAILED', message: data.message || 'Periksa kembali data yang Anda masukkan.', action: '', retryable: false, status, requestId, validationErrors: data.errors || {} }
    }
    if (status === 503 || data.error_code === 'DEPENDENCY_UNAVAILABLE' || data.error_code === 'TEMPORARY_FAILURE') {
        return { code: data.error_code || 'TEMPORARY_FAILURE', message: 'Layanan sedang sibuk. Silakan coba lagi.', action: 'retry', retryable: true, status, requestId, validationErrors: {} }
    }
    return { code: data.error_code || 'INTERNAL_ERROR', message: 'Data belum dapat diproses. Silakan coba lagi.', action: 'retry', retryable: true, status, requestId, validationErrors: {} }
}
