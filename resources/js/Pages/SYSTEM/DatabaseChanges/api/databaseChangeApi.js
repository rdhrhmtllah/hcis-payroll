import axios from 'axios'

export function fetchDatabaseChanges(params = {}) {
    return axios.get('/system/database-changes/data', { params })
}

export function fetchDatabaseChangeSql(id) {
    return axios.get(`/system/database-changes/${id}/sql`)
}

/**
 * Beberapa changeset sekaligus. Urutannya DIPAKSA server menurut urutan
 * pendaftaran, jadi urutan id yang dikirim di sini tidak menentukan apa pun.
 */
export function fetchDatabaseChangesBulkSql(ids) {
    return axios.post('/system/database-changes/bulk-sql', { ids })
}

export function updateDatabaseChangeStatus(id, payload) {
    return axios.patch(`/system/database-changes/${id}/status`, payload)
}

export function updateDatabaseChangesBatch(payload) {
    return axios.post('/system/database-changes/batch', payload)
}
