import { readFileSync } from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * Status tampil KELUAR_TAK_TERCATAT (celah #5): setiap layar yang memberi label
 * status kunjungan wajib mengenalnya — kalau tidak, kodenya bocor mentah.
 */
const LAYAR = [
    'resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.vue',
    'resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/VerifikatorTamuPanel.vue',
    'resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/TamuReviewModal.vue',
    'resources/js/Pages/VISITOR/Workflow/Reporting/Monitoring/components/TamuAllMonitoring.vue',
    'resources/js/Pages/VISITOR/Permit/Inspection/AgendaInspeksiPage.vue',
    'resources/js/components/ui/HcisBadge.vue',
];

describe('Status kunjungan KELUAR_TAK_TERCATAT', () => {
    it.each(LAYAR)('%s memberinya label manusia', (berkas) => {
        const isi = readFileSync(path.resolve(process.cwd(), berkas), 'utf8');
        expect(isi).toContain('KELUAR_TAK_TERCATAT');
        expect(isi).toContain('Keluar Tak Tercatat');
    });
});
