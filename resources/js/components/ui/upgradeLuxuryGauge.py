import os

chart_file = r"C:\laragon\www\HCIS-Worktree-2\resources\js\components\ui\HcisChart.vue"
with open(chart_file, 'r', encoding='utf-8') as f:
    code = f.read()

old_gauge = """    // 6. ULTRA-PREMIUM GAUGE KPI PERFORMANCE SPEEDOMETER
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

new_gauge = """    // 6. DUAL-LAYER AEROSPACE LUXURY GAUGE KPI INSTRUMENT
    if (props.type === 'gauge') {
        const gaugeValue = typeof props.data === 'number' ? props.data : (props.data?.[0]?.value || 0);

        return {
            color: colors,
            series: [
                // Layer 1: Outer Precision Calibration Ring & Scale Ticks
                {
                    type: 'gauge',
                    startAngle: 205,
                    endAngle: -25,
                    min: 0,
                    max: 100,
                    splitNumber: 5,
                    radius: '98%',
                    center: ['50%', '62%'],
                    axisLine: {
                        lineStyle: {
                            width: 2,
                            color: [[1, isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(226, 232, 240, 0.95)']],
                        },
                    },
                    splitLine: {
                        distance: 2,
                        length: 8,
                        lineStyle: {
                            width: 2,
                            color: isDark ? 'rgba(255, 255, 255, 0.3)' : '#94a3b8',
                            cap: 'round',
                        },
                    },
                    axisTick: {
                        distance: 2,
                        splitNumber: 4,
                        length: 4,
                        lineStyle: {
                            width: 1,
                            color: isDark ? 'rgba(255, 255, 255, 0.15)' : '#cbd5e1',
                            cap: 'round',
                        },
                    },
                    axisLabel: {
                        distance: 14,
                        color: isDark ? '#94a3b8' : '#64748b',
                        fontSize: 10,
                        fontWeight: 650,
                        fontFamily: 'inherit',
                    },
                    pointer: { show: false },
                    detail: { show: false },
                },

                // Layer 2: Radiant Luminous Gradient Progress Arc with Jewel Center
                {
                    type: 'gauge',
                    startAngle: 205,
                    endAngle: -25,
                    min: 0,
                    max: 100,
                    radius: '86%',
                    center: ['50%', '62%'],
                    progress: {
                        show: true,
                        roundCap: true,
                        width: 18,
                        itemStyle: {
                            color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                                { offset: 0, color: `${primaryColor}77` },
                                { offset: 0.6, color: primaryColor },
                                { offset: 1, color: colors[1] || primaryColor },
                            ]),
                            shadowColor: `${primaryColor}55`,
                            shadowBlur: 16,
                            shadowOffsetY: 4,
                        },
                    },
                    axisLine: {
                        roundCap: true,
                        lineStyle: {
                            width: 18,
                            color: [[1, isDark ? 'rgba(30, 41, 59, 0.55)' : 'rgba(241, 245, 249, 0.95)']],
                            shadowColor: 'rgba(15, 23, 42, 0.04)',
                            shadowBlur: 6,
                        },
                    },
                    axisTick: { show: false },
                    splitLine: { show: false },
                    axisLabel: { show: false },
                    pointer: {
                        show: true,
                        length: '56%',
                        width: 5,
                        itemStyle: {
                            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                { offset: 0, color: isDark ? '#ffffff' : '#1e293b' },
                                { offset: 1, color: isDark ? '#94a3b8' : '#64748b' },
                            ]),
                            shadowColor: 'rgba(0, 0, 0, 0.25)',
                            shadowBlur: 8,
                        },
                    },
                    anchor: {
                        show: true,
                        showAbove: true,
                        size: 20,
                        itemStyle: {
                            color: primaryColor,
                            borderWidth: 4,
                            borderColor: isDark ? '#0f172a' : '#ffffff',
                            shadowColor: `${primaryColor}60`,
                            shadowBlur: 12,
                        },
                    },
                    title: {
                        show: true,
                        offsetCenter: [0, '38%'],
                        fontSize: 12,
                        fontWeight: 700,
                        color: isDark ? '#cbd5e1' : '#475569',
                        fontFamily: 'inherit',
                    },
                    detail: {
                        valueAnimation: true,
                        offsetCenter: [0, '70%'],
                        fontSize: 32,
                        fontWeight: 900,
                        fontFamily: 'inherit',
                        letterSpacing: '-0.02em',
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

if old_gauge in code:
    code = code.replace(old_gauge, new_gauge)
    with open(chart_file, 'w', encoding='utf-8') as f:
        f.write(code)
    print("SUCCESS: Dual-Layer Aerospace Luxury Gauge upgraded in HcisChart.vue!")
else:
    print("WARNING: old_gauge not found")
