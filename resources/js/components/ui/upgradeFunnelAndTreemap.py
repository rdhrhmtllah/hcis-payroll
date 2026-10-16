import os

# 1. Update HcisChart.vue
chart_file = r"C:\laragon\www\HCIS-Worktree-2\resources\js\components\ui\HcisChart.vue"
with open(chart_file, 'r', encoding='utf-8') as f:
    chart_code = f.read()

old_funnel_and_treemap = """    // 7. FUNNEL RECRUITMENT & PIPELINE CHART
    if (props.type === 'funnel') {
        const funnelData = Array.isArray(props.data) ? props.data : [];

        return {
            color: colors,
            tooltip: {
                trigger: 'item',
                ...baseTooltip,
                formatter: '{b}: <strong>{c} Kandidat</strong> ({d}%)',
            },
            series: [
                {
                    name: props.title || 'Funnel',
                    type: 'funnel',
                    left: '10%',
                    top: 20,
                    bottom: 20,
                    width: '80%',
                    min: 0,
                    max: funnelData[0]?.value || 100,
                    minSize: '15%',
                    maxSize: '100%',
                    sort: 'descending',
                    gap: 4,
                    label: {
                        show: true,
                        position: 'inside',
                        formatter: '{b} ({c})',
                        color: '#ffffff',
                        fontWeight: 700,
                        fontSize: 11,
                    },
                    itemStyle: {
                        borderColor: isDark ? '#0f172a' : '#ffffff',
                        borderWidth: 2,
                        shadowColor: 'rgba(15, 23, 42, 0.1)',
                        shadowBlur: 6,
                    },
                    data: funnelData,
                    ...props.options.series?.[0],
                },
            ],
            ...props.options,
        };
    }

    // 8. 9-BOX TALENT ASSESSMENT SCATTER MATRIX
    if (props.type === 'scatter') {
        return {
            color: colors,
            grid: {
                top: 30,
                right: 30,
                bottom: 35,
                left: 35,
                containLabel: true,
            },
            tooltip: {
                trigger: 'item',
                ...baseTooltip,
                formatter: (p) => {
                    const d = p.data;
                    return `<strong>${d[2] || 'Talenta'}</strong><br/>Kinerja (Performance): <strong>${d[0]}</strong><br/>Potensi (Potential): <strong>${d[1]}</strong>`;
                },
            },
            xAxis: {
                type: 'value',
                name: 'Kinerja (Performance)',
                nameLocation: 'middle',
                nameGap: 24,
                nameTextStyle: { ...baseAxisLabel, fontWeight: 700 },
                min: 0,
                max: 100,
                axisLabel: baseAxisLabel,
                splitLine: baseSplitLine,
            },
            yAxis: {
                type: 'value',
                name: 'Potensi (Potential)',
                nameLocation: 'middle',
                nameGap: 24,
                nameTextStyle: { ...baseAxisLabel, fontWeight: 700 },
                min: 0,
                max: 100,
                axisLabel: baseAxisLabel,
                splitLine: baseSplitLine,
            },
            series: [
                {
                    type: 'scatter',
                    symbolSize: (data) => Math.max(12, (data[3] || 16)),
                    data: Array.isArray(props.data) ? props.data : [],
                    itemStyle: {
                        color: new echarts.graphic.RadialGradient(0.4, 0.3, 1, [
                            { offset: 0, color: `${primaryColor}ee` },
                            { offset: 1, color: `${primaryColor}88` },
                        ]),
                        shadowColor: `${primaryColor}40`,
                        shadowBlur: 10,
                        shadowOffsetY: 4,
                    },
                },
            ],
            ...props.options,
        };
    }

    // 9. TREEMAP ORGANIZATIONAL / BUDGET CHART
    if (props.type === 'treemap') {
        return {
            color: colors,
            tooltip: baseTooltip,
            series: [
                {
                    type: 'treemap',
                    roam: false,
                    nodeClick: false,
                    breadcrumb: { show: false },
                    data: Array.isArray(props.data) ? props.data : [],
                    levels: [
                        {
                            itemStyle: {
                                borderColor: isDark ? '#0f172a' : '#ffffff',
                                borderWidth: 3,
                                gapWidth: 3,
                            },
                        },
                        {
                            colorMappingBy: 'value',
                            itemStyle: {
                                gapWidth: 2,
                            },
                        },
                    ],
                },
            ],
            ...props.options,
        };
    }"""

new_funnel_and_treemap = """    // 7. ULTRA-PREMIUM ROUNDED FUNNEL RECRUITMENT PIPELINE
    if (props.type === 'funnel') {
        const rawFunnel = Array.isArray(props.data) ? props.data : [];
        const maxVal = rawFunnel[0]?.value || 100;

        const formattedFunnelData = rawFunnel.map((item, idx) => {
            const sColor = colors[idx % colors.length];
            return {
                ...item,
                itemStyle: {
                    borderRadius: 10,
                    borderColor: isDark ? '#0f172a' : '#ffffff',
                    borderWidth: 3.5,
                    color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                        { offset: 0, color: sColor },
                        { offset: 1, color: `${sColor}cc` },
                    ]),
                    shadowColor: `${sColor}35`,
                    shadowBlur: 8,
                    shadowOffsetY: 3,
                },
            };
        });

        return {
            color: colors,
            tooltip: {
                trigger: 'item',
                ...baseTooltip,
                formatter: (p) => {
                    const d = p.data;
                    const pct = ((d.value / maxVal) * 100).toFixed(1);
                    return `<strong>${d.name}</strong><br/>Kandidat: <strong>${d.value.toLocaleString('id-ID')} Orang</strong><br/>Konversi Total: <strong>${pct}%</strong>`;
                },
            },
            series: [
                {
                    name: props.title || 'Funnel Pipeline',
                    type: 'funnel',
                    left: '8%',
                    top: 16,
                    bottom: 16,
                    width: '84%',
                    min: 0,
                    max: maxVal,
                    minSize: '22%',
                    maxSize: '100%',
                    sort: 'descending',
                    gap: 6,
                    label: {
                        show: true,
                        position: 'inside',
                        formatter: '{title|{b}}\\n{val|{c} Kandidat ({d}%)}',
                        rich: {
                            title: {
                                color: '#ffffff',
                                fontSize: 12,
                                fontWeight: 800,
                                textShadowColor: 'rgba(0,0,0,0.3)',
                                textShadowBlur: 3,
                                lineHeight: 18,
                            },
                            val: {
                                color: 'rgba(255, 255, 255, 0.95)',
                                fontSize: 11,
                                fontWeight: 600,
                                textShadowColor: 'rgba(0,0,0,0.2)',
                                textShadowBlur: 2,
                                lineHeight: 14,
                            },
                        },
                    },
                    emphasis: {
                        itemStyle: {
                            borderRadius: 12,
                            shadowBlur: 16,
                            shadowColor: 'rgba(15, 23, 42, 0.25)',
                        },
                    },
                    data: formattedFunnelData,
                    ...props.options.series?.[0],
                },
            ],
            ...props.options,
        };
    }

    // 8. 9-BOX TALENT ASSESSMENT SCATTER MATRIX
    if (props.type === 'scatter') {
        return {
            color: colors,
            grid: {
                top: 30,
                right: 30,
                bottom: 35,
                left: 35,
                containLabel: true,
            },
            tooltip: {
                trigger: 'item',
                ...baseTooltip,
                formatter: (p) => {
                    const d = p.data;
                    return `<strong>${d[2] || 'Talenta'}</strong><br/>Kinerja (Performance): <strong>${d[0]}</strong><br/>Potensi (Potential): <strong>${d[1]}</strong>`;
                },
            },
            xAxis: {
                type: 'value',
                name: 'Kinerja (Performance)',
                nameLocation: 'middle',
                nameGap: 24,
                nameTextStyle: { ...baseAxisLabel, fontWeight: 700 },
                min: 0,
                max: 100,
                axisLabel: baseAxisLabel,
                splitLine: baseSplitLine,
            },
            yAxis: {
                type: 'value',
                name: 'Potensi (Potential)',
                nameLocation: 'middle',
                nameGap: 24,
                nameTextStyle: { ...baseAxisLabel, fontWeight: 700 },
                min: 0,
                max: 100,
                axisLabel: baseAxisLabel,
                splitLine: baseSplitLine,
            },
            series: [
                {
                    type: 'scatter',
                    symbolSize: (data) => Math.max(12, (data[3] || 16)),
                    data: Array.isArray(props.data) ? props.data : [],
                    itemStyle: {
                        color: new echarts.graphic.RadialGradient(0.4, 0.3, 1, [
                            { offset: 0, color: `${primaryColor}ee` },
                            { offset: 1, color: `${primaryColor}88` },
                        ]),
                        shadowColor: `${primaryColor}40`,
                        shadowBlur: 10,
                        shadowOffsetY: 4,
                    },
                },
            ],
            ...props.options,
        };
    }

    // 9. ULTRA-PREMIUM ROUNDED JEWEL TREEMAP
    if (props.type === 'treemap') {
        const rawTreemap = Array.isArray(props.data) ? props.data : [];
        const formattedTreemapData = rawTreemap.map((item, idx) => {
            const sColor = colors[idx % colors.length];
            return {
                ...item,
                itemStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 1, 1, [
                        { offset: 0, color: sColor },
                        { offset: 1, color: `${sColor}cc` },
                    ]),
                },
            };
        });

        return {
            color: colors,
            tooltip: {
                ...baseTooltip,
                formatter: '{b}: <strong>Rp {c} Juta</strong>',
            },
            series: [
                {
                    name: props.title || 'Alokasi',
                    type: 'treemap',
                    roam: false,
                    nodeClick: false,
                    breadcrumb: { show: false },
                    left: 10,
                    top: 10,
                    right: 10,
                    bottom: 10,
                    data: formattedTreemapData,
                    levels: [
                        {
                            itemStyle: {
                                borderRadius: 12,
                                borderColor: isDark ? '#0f172a' : '#ffffff',
                                borderWidth: 4,
                                gapWidth: 6,
                                shadowColor: 'rgba(15, 23, 42, 0.08)',
                                shadowBlur: 8,
                            },
                            upperLabel: { show: false },
                        },
                    ],
                    label: {
                        show: true,
                        position: 'insideTopLeft',
                        padding: [10, 12],
                        formatter: '{title|{b}}\\n{val|Rp {c} Jt}',
                        rich: {
                            title: {
                                fontSize: 12,
                                fontWeight: 800,
                                color: '#ffffff',
                                textShadowColor: 'rgba(0,0,0,0.35)',
                                textShadowBlur: 4,
                                lineHeight: 18,
                            },
                            val: {
                                fontSize: 11,
                                fontWeight: 600,
                                color: 'rgba(255, 255, 255, 0.92)',
                                textShadowColor: 'rgba(0,0,0,0.25)',
                                textShadowBlur: 2,
                                lineHeight: 15,
                            },
                        },
                    },
                    emphasis: {
                        itemStyle: {
                            borderRadius: 14,
                            shadowBlur: 16,
                            shadowColor: 'rgba(15, 23, 42, 0.25)',
                        },
                    },
                },
            ],
            ...props.options,
        };
    }"""

if old_funnel_and_treemap in chart_code:
    chart_code = chart_code.replace(old_funnel_and_treemap, new_funnel_and_treemap)
    with open(chart_file, 'w', encoding='utf-8') as f:
        f.write(chart_code)
    print("SUCCESS: Funnel and Treemap upgraded in HcisChart.vue!")
else:
    print("WARNING: old_funnel_and_treemap not found in HcisChart.vue")
