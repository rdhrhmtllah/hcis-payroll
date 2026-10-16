import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PaPolicyDetailDialog from './PaPolicyDetailDialog.vue';

const uiStubs = {
    HcisDialog: {
        props: ['modelValue', 'title', 'description'],
        template: '<div v-if="modelValue" class="dialog-stub"><h2>{{ title }}</h2><p>{{ description }}</p><slot /><slot name="footer" /></div>',
    },
    HcisBadge: {
        props: ['label'],
        template: '<span class="badge-stub">{{ label }}</span>',
    },
    HcisButton: {
        props: ['label', 'disabled', 'loading'],
        template: '<button :disabled="disabled">{{ label }}</button>',
    },
    HcisEmptyState: {
        props: ['title', 'description'],
        template: '<div class="empty-state"><strong>{{ title }}</strong></div>',
    },
    HcisIcon: { template: '<i />' },
    HcisResultState: {
        props: ['title', 'description'],
        template: '<div class="result-state"><strong>{{ title }}</strong><span>{{ description }}</span></div>',
    },
};

const references = {
    kategori_penilai: [
        // Kode & URUTAN sungguhan master (N_PA_Rater_Category): urutan id BUKAN urutan preset.
        { Id_PA_Rater_Category: 1, Kode_Kategori: 'ATASAN', Nama_Kategori: 'Atasan Langsung' },
        { Id_PA_Rater_Category: 2, Kode_Kategori: 'BAWAHAN', Nama_Kategori: 'Bawahan Langsung' },
        { Id_PA_Rater_Category: 3, Kode_Kategori: 'SELF', Nama_Kategori: 'Diri Sendiri' },
        { Id_PA_Rater_Category: 4, Kode_Kategori: 'PEER', Nama_Kategori: 'Rekan Kerja' },
    ],
    jenis_relasi: [
        { Id_Organization_Relationship_Type: 1, Nama_Relasi: 'Atasan 1' },
    ],
};

const samplePolicy = {
    Id_PA_Rater_Policy: 10,
    Kode_Policy: 'POL_STAFF_2026',
    Nama_Policy: 'Aturan Penilai Staff',
    ID_Level: 1,
    Versi: 1,
    Status_Policy: 'DRAFT',
};

const sampleDetail = [
    {
        Id_PA_Rater_Policy_Detail: 101,
        PA_Rater_Category_Id: 1,
        Kode_Kategori: 'ATASAN',
        Nama_Kategori: 'Atasan Langsung',
        Bobot_Persen: 70,
        Min_Rater: 1,
        Maks_Rater: 1,
        Min_Rater_Selesai: 1,
        Organization_Relationship_Type_Id: 1,
        Flag_Izinkan_Manual: 'T',
        Urutan_Tampil: 1,
    },
    {
        Id_PA_Rater_Policy_Detail: 102,
        PA_Rater_Category_Id: 4,
        Kode_Kategori: 'PEER',
        Nama_Kategori: 'Rekan Kerja',
        Bobot_Persen: 30,
        Min_Rater: 2,
        Maks_Rater: 4,
        Min_Rater_Selesai: 2,
        Organization_Relationship_Type_Id: '',
        Flag_Izinkan_Manual: 'Y',
        Urutan_Tampil: 2,
    },
];

describe('PaPolicyDetailDialog', () => {
    it('renders rater categories, calculates total weight 100% and marks as valid', async () => {
        const wrapper = mount(PaPolicyDetailDialog, {
            props: {
                modelValue: true,
                policy: samplePolicy,
                detail: sampleDetail,
                references,
            },
            global: { stubs: uiStubs },
        });

        expect(wrapper.find('h2').text()).toContain('Aturan Penilai Staff');
        expect(wrapper.text()).toContain('Total 100% (Sempurna & Valid)');
        expect(wrapper.text()).toContain('Atasan Langsung');
        expect(wrapper.text()).toContain('Rekan Kerja');
    });

    it('applies 360 multi-rater preset model in 1 click and updates category weights', async () => {
        const wrapper = mount(PaPolicyDetailDialog, {
            props: {
                modelValue: true,
                policy: samplePolicy,
                detail: sampleDetail,
                references,
            },
            global: { stubs: uiStubs },
        });

        const presetButtons = wrapper.findAll('.pa-preset-chip');
        const chip360 = presetButtons.find((c) => c.text().includes('360° Multi-Rater'));
        expect(chip360).toBeTruthy();
        await chip360.trigger('click');

        // Check if 3 categories (Atasan, Rekan, Bawahan) are now populated
        expect(wrapper.text()).toContain('Bawahan Langsung');
        expect(wrapper.text()).toContain('Total 100% (Sempurna & Valid)');

        /* Bobot jatuh ke kategori yang DISEBUT presetnya, bukan menurut urutan master.
           (Dulu 25% "Peer" jatuh ke Bawahan dan 25% "Bawahan" ke Diri Sendiri.) */
        const saveBtn = wrapper.findAll('button').find((b) => b.text().includes('Simpan Rincian'));
        await saveBtn.trigger('click');
        const bobot = Object.fromEntries(wrapper.emitted('save')[0][0].map((r) => [r.pa_rater_category_id, r.bobot_persen]));
        expect(bobot).toEqual({ 1: 50, 4: 25, 2: 25 });
    });

    it('preset yang kategorinya tidak ada di master tidak diterapkan setengah', async () => {
        const wrapper = mount(PaPolicyDetailDialog, {
            props: {
                modelValue: true,
                policy: samplePolicy,
                detail: sampleDetail,
                references: { ...references, kategori_penilai: references.kategori_penilai.filter((k) => k.Kode_Kategori !== 'SELF') },
            },
            global: { stubs: { ...uiStubs, HcisAlert: { props: ['title'], template: '<div class="alert-stub">{{ title }}</div>' } } },
        });

        await wrapper.findAll('.pa-preset-chip').find((c) => c.text().includes('Self')).trigger('click');
        expect(wrapper.find('[data-uji="pesan-preset"]').text()).toContain('SELF');
        expect(wrapper.text()).toContain('Total 100% (Sempurna & Valid)'); // komposisi lama utuh
    });

    it('emits save event with batch payload when save button is clicked', async () => {
        const wrapper = mount(PaPolicyDetailDialog, {
            props: {
                modelValue: true,
                policy: samplePolicy,
                detail: sampleDetail,
                references,
            },
            global: { stubs: uiStubs },
        });

        const saveBtn = wrapper.findAll('button').find((b) => b.text().includes('Simpan Rincian'));
        expect(saveBtn).toBeTruthy();
        await saveBtn.trigger('click');

        expect(wrapper.emitted('save')).toBeTruthy();
        const payload = wrapper.emitted('save')[0][0];
        expect(payload).toHaveLength(2);
        expect(payload[0].pa_rater_category_id).toBe(1);
        expect(payload[0].bobot_persen).toBe(70);
        expect(payload[1].pa_rater_category_id).toBe(4);
        expect(payload[1].bobot_persen).toBe(30);
    });
});
