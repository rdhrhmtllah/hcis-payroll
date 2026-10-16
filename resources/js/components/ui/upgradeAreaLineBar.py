import os

chart_file = r"C:\laragon\www\HCIS-Worktree-2\resources\js\components\ui\HcisChart.vue"
with open(chart_file, 'r', encoding='utf-8') as f:
    code = f.read()

old_area_line_bar = """    // 1. AREA / LINE CHART
    if (props.type === 'area' || props.type === 'line') {
        const isArea = props.type === 'area';
        const rawSeries = props.series.length > 0
            ? props.series
            : [{ name: props.title || 'Nilai', data: props.data }];

        return {
            color: colors,
            grid: baseGrid,
            tooltip: baseTooltip,
            dataZoom: dataZoomConfig,
            xAxis: {
                type: 'category',
                data: props.categories,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
            },
            yAxis: {
                type: 'value',
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
                splitLine: baseSplitLine,
            },
            series: rawSeries.map((s, idx) => {
                const sColor = colors[idx % colors.length];
                return {
                    name: s.name,
                    type: 'line',
                    smooth: props.smooth,
                    data: s.data,
                    showSymbol: false,
                    symbolSize: 7,
                    lineStyle: {
                        width: 3,
                        color: sColor,
                        shadowColor: `${sColor}40`,
                        shadowBlur: 10,
                        shadowOffsetY: 4,
                    },
                    itemStyle: {
                        color: sColor,
                        borderColor: isDark ? '#0f172a' : '#ffffff',
                        borderWidth: 2,
                    },
                    areaStyle: isArea
                        ? {
                              color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                  { offset: 0, color: `${sColor}45` },
                                  { offset: 0.7, color: `${sColor}10` },
                                  { offset: 1, color: `${sColor}00` },
                              ]),
                          }
                        : undefined,
                    markLine: idx === 0 ? targetMarkLine : undefined,
                    ...s,
                };
            }),
            ...props.options,
        };
    }

    // 2. VERTICAL BAR CHART
    if (props.type === 'bar') {
        const rawSeries = props.series.length > 0
            ? props.series
            : [{ name: props.title || 'Total', data: props.data }];

        return {
            color: colors,
            grid: baseGrid,
            tooltip: baseTooltip,
            dataZoom: dataZoomConfig,
            xAxis: {
                type: 'category',
                data: props.categories,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
            },
            yAxis: {
                type: 'value',
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
                splitLine: baseSplitLine,
            },
            series: rawSeries.map((s, idx) => {
                const sColor = colors[idx % colors.length];
                return {
                    name: s.name,
                    type: 'bar',
                    stack: props.stacked ? 'total' : undefined,
                    data: s.data,
                    barMaxWidth: 26,
                    itemStyle: {
                        borderRadius: props.stacked ? [0, 0, 0, 0] : [8, 8, 0, 0],
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                            { offset: 0, color: sColor },
                            { offset: 1, color: `${sColor}cc` },
                        ]),
                        shadowColor: `${sColor}25`,
                        shadowBlur: 6,
                        shadowOffsetY: 3,
                    },
                    markLine: idx === 0 ? targetMarkLine : undefined,
                    ...s,
                };
            }),
            ...props.options,
        };
    }

    // 3. HORIZONTAL BAR (LEADERBOARD / RANKING)
    if (props.type === 'bar-horizontal') {
        const rawSeries = props.series.length > 0
            ? props.series
            : [{ name: props.title || 'Skor', data: props.data }];

        return {
            color: colors,
            grid: {
                top: 18,
                right: 32,
                bottom: 18,
                left: 14,
                containLabel: true,
            },
            tooltip: baseTooltip,
            xAxis: {
                type: 'value',
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
                splitLine: baseSplitLine,
            },
            yAxis: {
                type: 'category',
                data: props.categories,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: { ...baseAxisLabel, fontWeight: 600 },
                inverse: true,
            },
            series: rawSeries.map((s, idx) => {
                const sColor = colors[idx % colors.length];
                return {
                    name: s.name,
                    type: 'bar',
                    data: s.data,
                    barMaxWidth: 18,
                    itemStyle: {
                        borderRadius: [0, 999, 999, 0],
                        color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                            { offset: 0, color: `${sColor}99` },
                            { offset: 1, color: sColor },
                        ]),
                        shadowColor: `${sColor}30`,
                        shadowBlur: 6,
                    },
                    label: {
                        show: true,
                        position: 'right',
                        color: isDark ? '#f8fafc' : '#0f172a',
                        fontWeight: 700,
                        fontSize: 11,
                    },
                    ...s,
                };
            }),
            ...props.options,
        };
    }"""

new_area_line_bar = """    // 1. ULTRA-PREMIUM NEON AREA & LINE SPLINE CHARTS
    if (props.type === 'area' || props.type === 'line') {
        const isArea = props.type === 'area';
        const rawSeries = props.series.length > 0
            ? props.series
            : [{ name: props.title || 'Nilai', data: props.data }];

        return {
            color: colors,
            grid: baseGrid,
            tooltip: {
                ...baseTooltip,
                axisPointer: {
                    type: 'line',
                    lineStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.25)' : 'rgba(99, 102, 241, 0.4)',
                        width: 1.5,
                        type: 'dashed',
                    },
                },
            },
            dataZoom: dataZoomConfig,
            xAxis: {
                type: 'category',
                data: props.categories,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
            },
            yAxis: {
                type: 'value',
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
                splitLine: baseSplitLine,
            },
            series: rawSeries.map((s, idx) => {
                const sColor = colors[idx % colors.length];
                return {
                    name: s.name,
                    type: 'line',
                    smooth: props.smooth,
                    data: s.data,
                    showSymbol: true,
                    symbol: 'circle',
                    symbolSize: 6,
                    lineStyle: {
                        width: 3.5,
                        color: sColor,
                        shadowColor: `${sColor}55`,
                        shadowBlur: 14,
                        shadowOffsetY: 4,
                    },
                    itemStyle: {
                        color: isDark ? '#0f172a' : '#ffffff',
                        borderColor: sColor,
                        borderWidth: 2.5,
                        shadowColor: `${sColor}80`,
                        shadowBlur: 6,
                    },
                    emphasis: {
                        scale: 1.6,
                        itemStyle: {
                            color: sColor,
                            borderColor: '#ffffff',
                            borderWidth: 3.5,
                            shadowBlur: 14,
                            shadowColor: `${sColor}80`,
                        },
                    },
                    areaStyle: isArea
                        ? {
                              color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                  { offset: 0, color: `${sColor}45` },
                                  { offset: 0.4, color: `${sColor}18` },
                                  { offset: 0.8, color: `${sColor}06` },
                                  { offset: 1, color: `${sColor}00` },
                              ]),
                          }
                        : undefined,
                    markLine: idx === 0 ? targetMarkLine : undefined,
                    ...s,
                };
            }),
            ...props.options,
        };
    }

    // 2. ULTRA-PREMIUM PILL-CAP VERTICAL BAR CHART
    if (props.type === 'bar') {
        const rawSeries = props.series.length > 0
            ? props.series
            : [{ name: props.title || 'Total', data: props.data }];

        return {
            color: colors,
            grid: baseGrid,
            tooltip: baseTooltip,
            dataZoom: dataZoomConfig,
            xAxis: {
                type: 'category',
                data: props.categories,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
            },
            yAxis: {
                type: 'value',
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
                splitLine: baseSplitLine,
            },
            series: rawSeries.map((s, idx) => {
                const sColor = colors[idx % colors.length];
                return {
                    name: s.name,
                    type: 'bar',
                    stack: props.stacked ? 'total' : undefined,
                    data: s.data,
                    barMaxWidth: 26,
                    showBackground: true,
                    backgroundStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.03)' : 'rgba(241, 245, 249, 0.75)',
                        borderRadius: props.stacked ? [0, 0, 0, 0] : [8, 8, 0, 0],
                    },
                    itemStyle: {
                        borderRadius: props.stacked ? [0, 0, 0, 0] : [8, 8, 0, 0],
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                            { offset: 0, color: sColor },
                            { offset: 0.8, color: `${sColor}cc` },
                            { offset: 1, color: `${sColor}77` },
                        ]),
                        shadowColor: `${sColor}35`,
                        shadowBlur: 8,
                        shadowOffsetY: 3,
                    },
                    emphasis: {
                        itemStyle: {
                            color: sColor,
                            shadowBlur: 14,
                            shadowColor: `${sColor}70`,
                        },
                    },
                    markLine: idx === 0 ? targetMarkLine : undefined,
                    ...s,
                };
            }),
            ...props.options,
        };
    }

    // 3. ULTRA-PREMIUM CAPSULE HORIZONTAL LEADERBOARD
    if (props.type === 'bar-horizontal') {
        const rawSeries = props.series.length > 0
            ? props.series
            : [{ name: props.title || 'Skor', data: props.data }];

        return {
            color: colors,
            grid: {
                top: 18,
                right: 36,
                bottom: 18,
                left: 14,
                containLabel: true,
            },
            tooltip: baseTooltip,
            xAxis: {
                type: 'value',
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
                splitLine: baseSplitLine,
            },
            yAxis: {
                type: 'category',
                data: props.categories,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: { ...baseAxisLabel, fontWeight: 650 },
                inverse: true,
            },
            series: rawSeries.map((s, idx) => {
                const sColor = colors[idx % colors.length];
                return {
                    name: s.name,
                    type: 'bar',
                    data: s.data,
                    barMaxWidth: 18,
                    showBackground: true,
                    backgroundStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.04)' : 'rgba(241, 245, 249, 0.8)',
                        borderRadius: [0, 999, 999, 0],
                    },
                    itemStyle: {
                        borderRadius: [0, 999, 999, 0],
                        color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                            { offset: 0, color: `${sColor}88` },
                            { offset: 0.85, color: sColor },
                            { offset: 1, color: colors[1] || sColor },
                        ]),
                        shadowColor: `${sColor}35`,
                        shadowBlur: 8,
                    },
                    label: {
                        show: true,
                        position: 'right',
                        distance: 10,
                        color: isDark ? '#f8fafc' : '#0f172a',
                        fontWeight: 800,
                        fontSize: 11.5,
                        fontFamily: 'inherit',
                    },
                    emphasis: {
                        itemStyle: {
                            color: sColor,
                            shadowBlur: 14,
                            shadowColor: `${sColor}70`,
                        },
                    },
                    ...s,
                };
            }),
            ...props.options,
        };
    }"""

if old_area_line_bar in code:
    code = code.replace(old_area_line_bar, new_area_line_bar)
    with open(chart_file, 'w', encoding='utf-8') as f:
        f.write(code)
    print("SUCCESS: Area, Line, and Bar upgraded in HcisChart.vue!")
else:
    print("WARNING: old_area_line_bar not found")
