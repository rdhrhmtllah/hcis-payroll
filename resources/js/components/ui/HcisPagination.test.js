import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HcisPagination from './HcisPagination.vue';

describe('HcisPagination owned primitive component', () => {
    it('renders total item text correctly', () => {
        const wrapper = mount(HcisPagination, {
            props: {
                currentPage: 2,
                pageSize: 25,
                total: 100,
            },
        });
        expect(wrapper.text()).toContain('Menampilkan 26-50 dari 100 data');
    });

    it('emits change and update:currentPage when page item clicked', async () => {
        const wrapper = mount(HcisPagination, {
            props: {
                currentPage: 1,
                pageSize: 10,
                total: 50,
            },
        });

        const items = wrapper.findAll('.hcis-pagination__pager .hcis-pagination__item');
        // Click page 3 button (index 2)
        await items[2].trigger('click');

        expect(wrapper.emitted('update:currentPage')?.[0]).toEqual([3]);
        expect(wrapper.emitted('change')?.[0]).toEqual([{ page: 3, pageSize: 10 }]);
    });

    it('emits size-change and update:pageSize when select option changed', async () => {
        const wrapper = mount(HcisPagination, {
            props: {
                currentPage: 1,
                pageSize: 10,
                total: 100,
                pageSizes: [10, 25, 50],
            },
        });

        const trigger = wrapper.find('.hcis-pagination__size-trigger');
        await trigger.trigger('click');

        const options = wrapper.findAll('.hcis-pagination__size-option');
        await options[1].trigger('click');

        expect(wrapper.emitted('update:pageSize')?.[0]).toEqual([25]);
        expect(wrapper.emitted('size-change')?.[0]).toEqual([25]);
    });

    it('disables prev button on first page and next button on last page', () => {
        const wrapperFirst = mount(HcisPagination, {
            props: { currentPage: 1, pageSize: 10, total: 30 },
        });
        const prevBtn = wrapperFirst.find('.hcis-pagination__desktop .hcis-pagination__btn--prev');
        expect(prevBtn.element.disabled).toBe(true);

        const wrapperLast = mount(HcisPagination, {
            props: { currentPage: 3, pageSize: 10, total: 30 },
        });
        const nextBtn = wrapperLast.find('.hcis-pagination__desktop .hcis-pagination__btn--next');
        expect(nextBtn.element.disabled).toBe(true);
    });
});
