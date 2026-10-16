import os

chart_file = r"C:\laragon\www\HCIS-Worktree-2\resources\js\components\ui\HcisChart.vue"
with open(chart_file, 'r', encoding='utf-8') as f:
    code = f.read()

old_treemap_block = """    // 9. ULTRA-PREMIUM ROUNDED JEWEL TREEMAP
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
                                borderRadius: 8,
                                borderColor: isDark ? '#0f172a' : '#ffffff',
                                borderWidth: 3,
                                gapWidth: 4,
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
                        formatter: '{title|{b}}\n{val|Rp {c} Jt}',
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
                            borderRadius: 10,
                            shadowBlur: 16,
                            shadowColor: 'rgba(15, 23, 42, 0.25)',
                        },
                    },
                },
            ],
            ...props.options,
        };
    }"""

new_treemap_block = """    // 9. ULTRA-PREMIUM ROUNDED JEWEL TREEMAP
    if (props.type === 'treemap') {
        const rawTreemap = Array.isArray(props.data) ? props.data : [];
        const formattedTreemapData = rawTreemap.map((item, idx) => {
            const sColor = colors[idx % colors.length];
            return {
                ...item,
                itemStyle: {
                    borderRadius: 8,
                    borderColor: isDark ? '#0f172a' : '#ffffff',
                    borderWidth: 3,
                    gapWidth: 4,
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
                    left: 6,
                    top: 6,
                    right: 6,
                    bottom: 6,
                    data: formattedTreemapData,
                    itemStyle: {
                        borderRadius: 8,
                        borderColor: isDark ? '#0f172a' : '#ffffff',
                        borderWidth: 3,
                        gapWidth: 4,
                        shadowColor: 'rgba(15, 23, 42, 0.08)',
                        shadowBlur: 8,
                    },
                    levels: [
                        {
                            itemStyle: {
                                borderRadius: 8,
                                borderColor: isDark ? '#0f172a' : '#ffffff',
                                borderWidth: 3,
                                gapWidth: 4,
                            },
                        },
                        {
                            itemStyle: {
                                borderRadius: 8,
                                borderColor: isDark ? '#0f172a' : '#ffffff',
                                borderWidth: 3,
                                gapWidth: 4,
                            },
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
                            borderRadius: 10,
                            shadowBlur: 16,
                            shadowColor: 'rgba(15, 23, 42, 0.25)',
                        },
                    },
                },
            ],
            ...props.options,
        };
    }"""

if old_treemap_block in code:
    code = code.replace(old_treemap_block, new_treemap_block)
    with open(chart_file, 'w', encoding='utf-8') as f:
        f.write(code)
    print("SUCCESS: Treemap borderRadius explicitly set on itemStyle, series, and all levels!")
else:
    print("WARNING: old_treemap_block not found")
