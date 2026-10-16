import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import DatabaseChangeDashboardPage from './DatabaseChangeDashboardPage.vue'
import { normalizeApiError } from '../../../utils/apiError'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        patch: vi.fn(),
        post: vi.fn(),
    },
}))

const summary = {
    total: 1,
    development_done: 1,
    tracker_pending: 1,
    production_pending: 1,
    completed: 0,
}

const item = {
    id: 1,
    change_id: 'DDL-TEST',
    type: 'DDL',
    title: 'Test change',
    description: 'Description',
    target_development: 'Web_HRIS',
    artifact_path: 'database/ddl/test/forward.sql',
    development: 'DONE',
    sql_tracker: 'PENDING',
    production: 'PENDING',
    verification: 'PENDING',
}

function mountPage() {
    return mount(DatabaseChangeDashboardPage)
}

describe('DatabaseChangeDashboardPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        axios.get.mockResolvedValue({ data: { items: [item], summary } })
        axios.patch.mockResolvedValue({ data: { data: item } })
        axios.post.mockResolvedValue({ data: { data: [item] } })
        Object.assign(navigator, { clipboard: { writeText: vi.fn().mockResolvedValue() } })
    })

    it('loads data through the feature API module using Options API lifecycle', async () => {
        const wrapper = mountPage()
        await flushPromises()

        expect(axios.get).toHaveBeenCalledWith('/system/database-changes/data', {
            params: { stage: 'action_required', type: undefined },
        })
        expect(wrapper.vm.items).toHaveLength(1)
        expect(wrapper.text()).toContain('DDL-TEST')
        expect(wrapper.vm.isEmpty).toBe(false)
    })

    it('shows a friendly retryable error without exposing technical details', async () => {
        axios.get.mockRejectedValueOnce({
            response: { status: 503, data: { message: 'SQLSTATE secret detail', request_id: 'REQ-1' } },
        })

        const wrapper = mountPage()
        await flushPromises()

        expect(wrapper.text()).toContain('Layanan sedang sibuk. Silakan coba lagi.')
        expect(wrapper.text()).not.toContain('SQLSTATE secret detail')
        expect(wrapper.text()).toContain('REQ-1')
        expect(wrapper.text()).toContain('Coba lagi')
    })

    it('normalizes validation and forbidden errors into stable UI contracts', () => {
        expect(normalizeApiError({ response: { status: 422, data: { errors: { type: ['Wajib diisi'] } } } })).toMatchObject({
            code: 'VALIDATION_FAILED',
            action: '',
            validationErrors: { type: ['Wajib diisi'] },
        })
        expect(normalizeApiError({ response: { status: 403, data: {} } })).toMatchObject({
            code: 'FORBIDDEN',
            retryable: false,
        })
    })

    it('prevents duplicate status mutations while saving', async () => {
        const wrapper = mountPage()
        await flushPromises()
        await wrapper.vm.toggle(item.id)
        const promise = wrapper.vm.markProduction()
        expect(wrapper.vm.saving).toBe(true)
        await wrapper.vm.markProduction()
        await promise
        await flushPromises()

        expect(axios.patch).toHaveBeenCalledTimes(1)
    })
})
