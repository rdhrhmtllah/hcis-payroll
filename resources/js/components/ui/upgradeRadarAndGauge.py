import os

chart_file = r"C:\laragon\www\HCIS-Worktree-2\resources\js\components\ui\HcisChart.vue"
with open(chart_file, 'r', encoding='utf-8') as f:
    code = f.read()

old_radar_and_gauge = """    // 5. RADAR COMPETENCY CHART
    if (props.type === 'radar') {
        const indicators = props.categories.map((c) => ({
            name: typeof c === 'string' ? c : c.name,
            max: typeof c === 'object' && c.max ? c.max : 100,
        }));

        const rawSeries = props.series.length > 0
            ? props.series
            : [{ name: props.title || 'Skor', value: props.data }];

        return {
            color: colors,
            tooltip: {
                trigger: 'item',
                ...baseTooltip,
            },
            radar: {
                indicator: indicators,
                shape: 'polygon',
                splitNumber: 4,
                axisName: {
                    color: isDark ? '#cbd5e1' : '#475569',
                    fontSize: 11,
                    fontWeight: 600,
                    fontFamily: 'inherit',
                },
                splitLine: {
                    lineStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(203, 213, 225, 0.8)',
                    },
                },
                splitArea: {
                    show: true,
                    areaStyle: {
                        color: isDark
                            ? ['rgba(30, 41, 59, 0.3)', 'rgba(15, 23, 42, 0.4)']
                            : ['rgba(248, 250, 252, 0.6)', 'rgba(241, 245, 249, 0.85)'],
                    },
                },
                axisLine: {
                    lineStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(203, 213, 225, 0.9)',
                    },
                },
            },
            series: [
                {
                    type: 'radar',
                    data: rawSeries.map((s, idx) => {
                        const sColor = colors[idx % colors.length];
                        return {
                            name: s.name,
                            value: s.value || s.data,
                            symbolSize: 6,
                            itemStyle: { color: sColor },
                            lineStyle: { width: 2.5, color: sColor },
                            areaStyle: {
                                color: new echarts.graphic.RadialGradient(0.5, 0.5, 1, [
                                    { offset: 0, color: `${sColor}50` },
                                    { offset: 1, color: `${sColor}15` },
                                ]),
                            },
                            ...s,
                        };
                    }),
                },
            ],
            ...props.options,
        };
    }

    // 6. GAUGE KPI PERFORMANCE METER
    if (props.type === 'gauge') {
        const gaugeValue = typeof props.data === 'number' ? props.data : (props.data?.[0]?.value || 0);

        return {
            color: colors,
            series: [
                {
                    type: 'gauge',
                    startAngle: 190,
                    endAngle: -10,
                    min: 0,
                    max: 100,
                    splitNumber: 5,
                    radius: '90%',
                    center: ['50%', '62%'],
                    itemStyle: {
                        color: primaryColor,
                        shadowColor: `${primaryColor}40`,
                        shadowBlur: 10,
                        shadowOffsetY: 4,
                    },
                    progress: {
                        show: true,
                        roundCap: true,
                        width: 14,
                    },
                    pointer: {
                        show: true,
                        length: '58%',
                        width: 4,
                        itemStyle: { color: isDark ? '#f8fafc' : '#0f172a' },
                    },
                    axisLine: {
                        roundCap: true,
                        lineStyle: {
                            width: 14,
                            color: [[1, isDark ? 'rgba(255, 255, 255, 0.1)' : '#f1f5f9']],
                        },
                    },
                    axisTick: { show: false },
                    splitLine: {
                        length: 8,
                        lineStyle: {
                            width: 2,
                            color: isDark ? 'rgba(255, 255, 255, 0.2)' : '#cbd5e1',
                        },
                    },
                    axisLabel: {
                        distance: 20,
                        color: isDark ? '#94a3b8' : '#64748b',
                        fontSize: 10,
                        fontFamily: 'inherit',
                    },
                    title: {
                        show: true,
                        offsetCenter: [0, '35%'],
                        fontSize: 12,
                        fontWeight: 600,
                        color: isDark ? '#94a3b8' : '#64748b',
                        fontFamily: 'inherit',
                    },
                    detail: {
                        valueAnimation: true,
                        offsetCenter: [0, '70%'],
                        fontSize: 26,
                        fontWeight: 800,
                        fontFamily: 'inherit',
                        color: isDark ? '#ffffff' : '#0f172a',
                        formatter: '{value}%',
                    },
                    data: [
                        {
                            value: gaugeValue,
                            name: props.metricLabel || props.title || 'Capaian KPI',
                        },
                    ],
                },
            ],
            ...props.options,
        };
    }"""

new_radar_and_gauge = """    // 5. ULTRA-PREMIUM RADAR 360 COMPETENCY MATRIX
    if (props.type === 'radar') {
        const indicators = props.categories.map((c) => ({
            name: typeof c === 'string' ? c : c.name,
            max: typeof c === 'object' && c.max ? c.max : 100,
        }));

        const rawSeries = props.series.length > 0
            ? props.series
            : [{ name: props.title || 'Skor', value: props.data }];

        return {
            color: colors,
            tooltip: {
                trigger: 'item',
                ...baseTooltip,
            },
            radar: {
                indicator: indicators,
                shape: 'polygon',
                splitNumber: 4,
                axisName: {
                    color: isDark ? '#f8fafc' : '#0f172a',
                    fontSize: 11.5,
                    fontWeight: 700,
                    fontFamily: 'inherit',
                    padding: [4, 6],
                },
                splitLine: {
                    lineStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(203, 213, 225, 0.85)',
                        width: 1.5,
                    },
                },
                splitArea: {
                    show: true,
                    areaStyle: {
                        color: isDark
                            ? ['rgba(30, 41, 59, 0.35)', 'rgba(15, 23, 42, 0.5)']
                            : ['rgba(248, 250, 252, 0.65)', 'rgba(238, 242, 255, 0.85)'],
                    },
                },
                axisLine: {
                    lineStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.12)' : 'rgba(203, 213, 225, 0.95)',
                    },
                },
            },
            series: [
                {
                    type: 'radar',
                    data: rawSeries.map((s, idx) => {
                        const sColor = colors[idx % colors.length];
                        return {
                            name: s.name,
                            value: s.value || s.data,
                            symbol: 'circle',
                            symbolSize: 8,
                            itemStyle: {
                                color: sColor,
                                borderColor: isDark ? '#0f172a' : '#ffffff',
                                borderWidth: 2.5,
                                shadowColor: `${sColor}60`,
                                shadowBlur: 6,
                            },
                            lineStyle: {
                                width: 3,
                                color: sColor,
                                shadowColor: `${sColor}50`,
                                shadowBlur: 10,
                                shadowOffsetY: 3,
                            },
                            areaStyle: {
                                color: new echarts.graphic.RadialGradient(0.5, 0.5, 0.8, [
                                    { offset: 0, color: `${sColor}60` },
                                    { offset: 1, color: `${sColor}15` },
                                ]),
                            },
                            emphasis: {
                                lineStyle: { width: 4 },
                                itemStyle: { symbolSize: 10, borderWidth: 3 },
                            },
                            ...s,
                        };
                    }),
                },
            ],
            ...props.options,
        };
    }

    // 6. ULTRA-PREMIUM GAUGE KPI PERFORMANCE SPEEDOMETER
    if (props.type === 'gauge') {
        const gaugeValue = typeof props.data === 'number' ? props.data : (props.data?.[0]?.value || 0);

        return {
            color: colors,
            series: [
                {
                    type: 'gauge',
                    startAngle: 195,
                    endAngle: -15,
                    min: 0,
                    max: 100,
                    splitNumber: 5,
                    radius: '92%',
                    center: ['50%', '64%'],
                    progress: {
                        show: true,
                        roundCap: true,
                        width: 16,
                        itemStyle: {
                            color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                                { offset: 0, color: `${primaryColor}99` },
                                { offset: 1, color: primaryColor },
                            ]),
                            shadowColor: `${primaryColor}40`,
                            shadowBlur: 10,
                            shadowOffsetY: 3,
                        },
                    },
                    axisLine: {
                        roundCap: true,
                        lineStyle: {
                            width: 16,
                            color: [[1, isDark ? 'rgba(255, 255, 255, 0.08)' : '#f1f5f9']],
                        },
                    },
                    pointer: {
                        show: true,
                        length: '60%',
                        width: 4.5,
                        itemStyle: { color: isDark ? '#f8fafc' : '#1e293b' },
                    },
                    anchor: {
                        show: true,
                        showAbove: true,
                        size: 16,
                        itemStyle: {
                            color: primaryColor,
                            borderWidth: 3.5,
                            borderColor: isDark ? '#0f172a' : '#ffffff',
                            shadowColor: 'rgba(15, 23, 42, 0.2)',
                            shadowBlur: 8,
                        },
                    },
                    axisTick: {
                        show: true,
                        distance: -22,
                        length: 5,
                        splitNumber: 4,
                        lineStyle: {
                            color: isDark ? 'rgba(255, 255, 255, 0.15)' : '#cbd5e1',
                            width: 1.5,
                            cap: 'round',
                        },
                    },
                    splitLine: {
                        show: true,
                        distance: -25,
                        length: 9,
                        lineStyle: {
                            color: isDark ? 'rgba(255, 255, 255, 0.25)' : '#94a3b8',
                            width: 2,
                            cap: 'round',
                        },
                    },
                    axisLabel: {
                        distance: 22,
                        color: isDark ? '#94a3b8' : '#64748b',
                        fontSize: 10.5,
                        fontWeight: 600,
                        fontFamily: 'inherit',
                    },
                    title: {
                        show: true,
                        offsetCenter: [0, '36%'],
                        fontSize: 12,
                        fontWeight: 700,
                        color: isDark ? '#cbd5e1' : '#475569',
                        fontFamily: 'inherit',
                    },
                    detail: {
                        valueAnimation: true,
                        offsetCenter: [0, '72%'],
                        fontSize: 30,
                        fontWeight: 850,
                        fontFamily: 'inherit',
                        color: isDark ? '#ffffff' : '#0f172a',
                        formatter: '{value}%',
                    },
                    data: [
                        {
                            value: gaugeValue,
                            name: props.metricLabel || props.title || 'Capaian KPI',
                        },
                    ],
                },
            ],
            ...props.options,
        };
    }"""

if old_radar_and_gauge in code:
    code = code.replace(old_radar_and_gauge, new_radar_and_gauge)
    with open(chart_file, 'w', encoding='utf-8') as f:
        f.write(code)
    print("SUCCESS: Radar and Gauge upgraded in HcisChart.vue!")
else:
    print("WARNING: old_radar_and_gauge not found")
