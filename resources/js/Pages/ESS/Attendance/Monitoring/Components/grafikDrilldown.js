// Pembentuk data untuk 3 mode visualisasi drill-down interaktif:
// 1. Batang Komparasi + Drilldown Bar
// 2. Treemap (Kotak Bersarang)
// 3. Sunburst (Cincin Bertingkat)
// Semua fungsi murni, diuji dengan Vitest.

import { warnaToken } from './grafikMonitoring.js';

export const METRIK_DRILLDOWN = {
    persen_hadir: {
        key: 'persen_hadir',
        label: '% Kehadiran',
        satuan: '%',
        ambil: (n) => (n ? Number(n.persen_hadir ?? 0) : 0),
        format: (v) => `${Number(v).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`,
        sentiment: 'success',
        tokenWarna: '--hcis-success-500',
    },
    terlambat: {
        key: 'terlambat',
        label: 'Terlambat',
        satuan: 'kali',
        ambil: (n) => (n ? Number(n.terlambat_kali ?? 0) : 0),
        format: (v) => `${Number(v).toLocaleString('id-ID')}×`,
        sentiment: 'warning',
        tokenWarna: '--hcis-warning-500',
    },
    alpha: {
        key: 'alpha',
        label: 'Alpha',
        satuan: 'hari',
        ambil: (n) => (n ? Number(n.alpha ?? 0) : 0),
        format: (v) => `${Number(v).toLocaleString('id-ID')} hari`,
        sentiment: 'danger',
        tokenWarna: '--hcis-danger-500',
    },
    lembur: {
        key: 'lembur',
        label: 'Lembur',
        satuan: 'jam',
        ambil: (n) => (n ? Number(n.lembur_jam ?? 0) : 0),
        format: (v) => `${Number(v).toLocaleString('id-ID')} jam`,
        sentiment: 'purple',
        tokenWarna: '--hcis-purple-500',
    },
};

/**
 * Mencari node di dalam pohon hierarki berdasarkan array path nama.
 */
export function temukanNode(root, pathNames = []) {
    if (!root) return null;
    if (!pathNames.length) return root;

    let current = root;
    for (const name of pathNames) {
        if (!current.children || !current.children.length) return current;
        const match = current.children.find((c) => c.name === name);
        if (!match) return current;
        current = match;
    }
    return current;
}

/**
 * Membentuk opsi Bar Chart untuk anak-anak dari node aktif saat ini.
 */
export function opsiBatangDrilldown(nodeAktif, metrikKey = 'persen_hadir', warnaFn = warnaToken) {
    const metrik = METRIK_DRILLDOWN[metrikKey] || METRIK_DRILLDOWN.persen_hadir;
    const children = (nodeAktif && nodeAktif.children) ? [...nodeAktif.children] : [];

    // Urutkan nilai terbesar di atas
    children.sort((a, b) => metrik.ambil(b) - metrik.ambil(a) || a.name.localeCompare(b.name));

    // ECharts horizontal bar menggambar dari bawah ke atas pada sumbu Y:
    const displayChildren = [...children].reverse();
    const categories = displayChildren.map((c) => c.name);
    const dataValues = displayChildren.map((c) => metrik.ambil(c));
    const rawNodes = displayChildren;

    const baseColor = warnaFn(metrik.tokenWarna) || '#059669';

    return {
        grid: { top: 24, right: 64, bottom: 24, left: 16, containLabel: true },
        tooltip: {
            trigger: 'item',
            confine: true,
            backgroundColor: 'rgba(255, 255, 255, 0.97)',
            borderColor: 'rgba(226, 232, 240, 0.95)',
            borderWidth: 1,
            padding: [10, 14],
            textStyle: { color: '#0f172a', fontSize: 12, fontFamily: 'inherit' },
            extraCssText: 'box-shadow: 0 12px 28px -4px rgba(15, 23, 42, 0.18); border-radius: 12px; backdrop-filter: blur(14px); z-index: 999999;',
            formatter: (params) => {
                const node = rawNodes[params.dataIndex];
                if (!node) return '';
                const mVal = metrik.ambil(node);
                return `
                    <div style="font-weight: 750; font-size: 13px; margin-bottom: 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                        <span style="color: #0f172a;">${node.name}</span>
                        <span style="font-size: 11px; font-weight: 700; color: ${baseColor}; background: rgba(241, 245, 249, 0.8); padding: 1px 7px; border-radius: 4px;">${metrik.label}: ${metrik.format(mVal)}</span>
                    </div>
                    <div style="font-size: 11.5px; display: flex; flex-direction: column; gap: 4px; color: #475569;">
                        <div style="display: flex; justify-content: space-between; gap: 14px;">
                            <span>Total Karyawan:</span>
                            <strong style="color: #0f172a; font-family: monospace;">${node.karyawan_count ?? 0} orang</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 14px;">
                            <span>Hadir / Terjadwal:</span>
                            <strong style="color: #0f172a; font-family: monospace;">${node.hadir ?? 0} / ${node.terjadwal ?? 0}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 14px;">
                            <span>Terlambat:</span>
                            <strong style="color: ${(node.terlambat_kali ?? 0) > 0 ? '#d97706' : '#64748b'}; font-family: monospace;">${node.terlambat_kali ?? 0}× (${node.terlambat_menit ?? 0} mnt)</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 14px;">
                            <span>Alpha:</span>
                            <strong style="color: ${(node.alpha ?? 0) > 0 ? '#dc2626' : '#64748b'}; font-family: monospace;">${node.alpha ?? 0} hari</strong>
                        </div>
                    </div>
                    <div style="margin-top: 8px; font-size: 10.5px; color: #6366f1; font-weight: 600; text-align: right;">Klik batang untuk eksplorasi sub-unit →</div>
                `;
            },
        },
        xAxis: {
            type: 'value',
            axisLabel: {
                formatter: metrikKey === 'persen_hadir' ? '{value}%' : '{value}',
                color: '#64748b',
                fontSize: 11,
            },
            splitLine: { lineStyle: { type: 'dashed', color: 'rgba(226, 232, 240, 0.7)' } },
        },
        yAxis: {
            type: 'category',
            data: categories,
            axisTick: { show: false },
            axisLine: { lineStyle: { color: 'rgba(226, 232, 240, 0.8)' } },
            axisLabel: {
                width: 160,
                overflow: 'truncate',
                fontWeight: 650,
                color: '#334155',
                fontSize: 11.5,
            },
        },
        series: [
            {
                name: metrik.label,
                type: 'bar',
                data: dataValues,
                barMaxWidth: 24,
                cursor: 'pointer',
                showBackground: true,
                backgroundStyle: {
                    color: 'rgba(241, 245, 249, 0.7)',
                    borderRadius: [0, 999, 999, 0],
                },
                itemStyle: {
                    borderRadius: [0, 999, 999, 0],
                    color: baseColor,
                },
                emphasis: {
                    itemStyle: {
                        shadowBlur: 10,
                        shadowColor: 'rgba(0, 0, 0, 0.15)',
                    },
                },
                label: {
                    show: true,
                    position: 'right',
                    formatter: (p) => metrik.format(p.value),
                    fontSize: 11.5,
                    fontWeight: 700,
                    color: '#0f172a',
                },
            },
        ],
        _rawNodes: rawNodes,
    };
}

/**
 * Format satu node untuk ECharts Treemap.
 */
function bentukNodeTreemap(node, metrikKey, palette, depth = 0) {
    const metrik = METRIK_DRILLDOWN[metrikKey] || METRIK_DRILLDOWN.persen_hadir;
    const size = Math.max(1, node.karyawan_count || node.terjadwal || 1);
    const val = metrik.ambil(node);

    const formatted = {
        name: node.name,
        value: size,
        metricValue: val,
        nodeData: node,
    };

    if (node.children && node.children.length > 0) {
        formatted.children = node.children.map((c) => bentukNodeTreemap(c, metrikKey, palette, depth + 1));
    }

    return formatted;
}

/**
 * Membentuk opsi ECharts Treemap dengan visual bersih dan drill-down zoom.
 */
export function opsiTreemapDrilldown(hierarki, metrikKey = 'persen_hadir', warnaFn = warnaToken) {
    const metrik = METRIK_DRILLDOWN[metrikKey] || METRIK_DRILLDOWN.persen_hadir;
    if (!hierarki || !hierarki.children) {
        return { series: [] };
    }

    const palette = ['#4f46e5', '#0d9488', '#7c3aed', '#0284c7', '#10b981', '#f59e0b', '#dc2626'];
    const data = hierarki.children.map((c) => bentukNodeTreemap(c, metrikKey, palette, 0));

    return {
        tooltip: {
            trigger: 'item',
            confine: true,
            backgroundColor: 'rgba(255, 255, 255, 0.97)',
            borderColor: 'rgba(226, 232, 240, 0.95)',
            borderWidth: 1,
            padding: [10, 14],
            textStyle: { color: '#0f172a', fontSize: 12, fontFamily: 'inherit' },
            extraCssText: 'box-shadow: 0 12px 28px -4px rgba(15, 23, 42, 0.18); border-radius: 12px; backdrop-filter: blur(14px); z-index: 999999;',
            formatter: (params) => {
                const node = params.data?.nodeData;
                if (!node) return params.name;
                const mVal = metrik.ambil(node);
                return `
                    <div style="font-weight: 750; font-size: 13px; margin-bottom: 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                        <span style="color: #0f172a;">${node.name} <span style="font-size: 11px; font-weight: 500; color: #64748b;">(${node.tingkat})</span></span>
                        <span style="font-size: 11px; font-weight: 700; color: #4f46e5; background: #eef2ff; padding: 1px 7px; border-radius: 4px;">${metrik.label}: ${metrik.format(mVal)}</span>
                    </div>
                    <div style="font-size: 11.5px; display: flex; flex-direction: column; gap: 4px; color: #475569;">
                        <div style="display: flex; justify-content: space-between; gap: 14px;">
                            <span>Total Karyawan:</span>
                            <strong style="color: #0f172a; font-family: monospace;">${node.karyawan_count ?? 0} orang</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 14px;">
                            <span>Hadir / Terjadwal:</span>
                            <strong style="color: #0f172a; font-family: monospace;">${node.hadir ?? 0} / ${node.terjadwal ?? 0}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 14px;">
                            <span>Terlambat:</span>
                            <strong style="color: ${(node.terlambat_kali ?? 0) > 0 ? '#d97706' : '#64748b'}; font-family: monospace;">${node.terlambat_kali ?? 0}× (${node.terlambat_menit ?? 0} mnt)</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 14px;">
                            <span>Alpha:</span>
                            <strong style="color: ${(node.alpha ?? 0) > 0 ? '#dc2626' : '#64748b'}; font-family: monospace;">${node.alpha ?? 0} hari</strong>
                        </div>
                    </div>
                    <div style="margin-top: 8px; font-size: 10.5px; color: #6366f1; font-weight: 600; text-align: right;">Klik kotak untuk zoom masuk →</div>
                `;
            },
        },
        series: [
            {
                name: 'Hierarki Kehadiran',
                type: 'treemap',
                data,
                leafDepth: 1, // Drill-down 1 level per klik
                roam: false,
                nodeClick: 'zoomToNode',
                breadcrumb: {
                    show: true,
                    top: 'bottom',
                    left: 'center',
                    itemStyle: {
                        color: 'rgba(15, 23, 42, 0.8)',
                        borderRadius: 4,
                    },
                },
                levels: [
                    {
                        itemStyle: {
                            borderColor: '#ffffff',
                            borderWidth: 3,
                            gapWidth: 4,
                            borderRadius: 8,
                        },
                    },
                    {
                        itemStyle: {
                            borderColor: '#ffffff',
                            borderWidth: 2,
                            gapWidth: 3,
                            borderRadius: 6,
                        },
                        emphasis: {
                            itemStyle: {
                                borderColor: '#cbd5e1',
                            },
                        },
                    },
                    {
                        itemStyle: {
                            borderColor: '#ffffff',
                            borderWidth: 1,
                            gapWidth: 2,
                            borderRadius: 4,
                        },
                    },
                ],
                label: {
                    show: true,
                    position: 'inside',
                    formatter: (p) => {
                        const mVal = p.data?.metricValue;
                        const formatted = mVal !== undefined ? metrik.format(mVal) : '';
                        return `${p.name}\n${formatted}`;
                    },
                    fontSize: 11,
                    fontWeight: 700,
                },
            },
        ],
    };
}

/**
 * Format satu node untuk ECharts Sunburst.
 */
function bentukNodeSunburst(node, metrikKey) {
    const metrik = METRIK_DRILLDOWN[metrikKey] || METRIK_DRILLDOWN.persen_hadir;
    const val = metrik.ambil(node);
    const size = Math.max(1, node.karyawan_count || 1);

    const item = {
        name: node.name,
        value: size,
        metricValue: val,
        nodeData: node,
    };

    if (node.children && node.children.length > 0) {
        item.children = node.children.map((c) => bentukNodeSunburst(c, metrikKey));
    }

    return item;
}

/**
 * Membentuk opsi ECharts Sunburst konsentris dengan rootToNode zoom dan label anti-tabrak.
 */
export function opsiSunburstDrilldown(hierarki, metrikKey = 'persen_hadir', warnaFn = warnaToken) {
    const metrik = METRIK_DRILLDOWN[metrikKey] || METRIK_DRILLDOWN.persen_hadir;
    if (!hierarki || !hierarki.children) {
        return { series: [] };
    }

    const data = hierarki.children.map((c) => bentukNodeSunburst(c, metrikKey));

    return {
        color: ['#4f46e5', '#0d9488', '#7c3aed', '#0284c7', '#10b981', '#f59e0b', '#dc2626'],
        tooltip: {
            trigger: 'item',
            confine: true,
            backgroundColor: 'rgba(255, 255, 255, 0.97)',
            borderColor: 'rgba(226, 232, 240, 0.95)',
            borderWidth: 1,
            padding: [10, 14],
            textStyle: { color: '#0f172a', fontSize: 12, fontFamily: 'inherit' },
            extraCssText: 'box-shadow: 0 12px 28px -4px rgba(15, 23, 42, 0.18); border-radius: 12px; backdrop-filter: blur(14px); z-index: 999999;',
            formatter: (params) => {
                const node = params.data?.nodeData;
                if (!node) return params.name;
                const mVal = metrik.ambil(node);
                return `
                    <div style="font-weight: 750; font-size: 13px; margin-bottom: 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                        <span style="color: #0f172a;">${node.name} <span style="font-size: 11px; font-weight: 500; color: #64748b;">(${node.tingkat})</span></span>
                        <span style="font-size: 11px; font-weight: 700; color: #4f46e5; background: #eef2ff; padding: 1px 7px; border-radius: 4px;">${metrik.label}: ${metrik.format(mVal)}</span>
                    </div>
                    <div style="font-size: 11.5px; display: flex; flex-direction: column; gap: 4px; color: #475569;">
                        <div style="display: flex; justify-content: space-between; gap: 14px;">
                            <span>Total Karyawan:</span>
                            <strong style="color: #0f172a; font-family: monospace;">${node.karyawan_count ?? 0} orang</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 14px;">
                            <span>Hadir / Terjadwal:</span>
                            <strong style="color: #0f172a; font-family: monospace;">${node.hadir ?? 0} / ${node.terjadwal ?? 0}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 14px;">
                            <span>Terlambat:</span>
                            <strong style="color: ${(node.terlambat_kali ?? 0) > 0 ? '#d97706' : '#64748b'}; font-family: monospace;">${node.terlambat_kali ?? 0}× (${node.terlambat_menit ?? 0} mnt)</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 14px;">
                            <span>Alpha:</span>
                            <strong style="color: ${(node.alpha ?? 0) > 0 ? '#dc2626' : '#64748b'}; font-family: monospace;">${node.alpha ?? 0} hari</strong>
                        </div>
                    </div>
                    <div style="margin-top: 8px; font-size: 10.5px; color: #6366f1; font-weight: 600; text-align: right;">Klik cincin untuk fokus ke tingkat ini →</div>
                `;
            },
        },
        series: [
            {
                type: 'sunburst',
                data,
                radius: ['14%', '84%'],
                center: ['50%', '50%'],
                nodeClick: 'rootToNode',
                sort: 'desc',
                itemStyle: {
                    borderRadius: 6,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                },
                levels: [
                    {}, // Level 0 (center)
                    {
                        r0: '14%',
                        r: '36%',
                        label: { rotate: 'tangential', fontSize: 11, fontWeight: 700, minAngle: 18 },
                    },
                    {
                        r0: '36%',
                        r: '64%',
                        label: { align: 'right', fontSize: 10, fontWeight: 600, minAngle: 20 },
                    },
                    {
                        r0: '64%',
                        r: '86%',
                        label: { position: 'outside', padding: 4, silent: false, fontSize: 9.5, minAngle: 22 },
                    },
                ],
            },
        ],
    };
}
