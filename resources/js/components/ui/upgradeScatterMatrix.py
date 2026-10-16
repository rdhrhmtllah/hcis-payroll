import os

chart_file = r"C:\laragon\www\HCIS-Worktree-2\resources\js\components\ui\HcisChart.vue"
with open(chart_file, 'r', encoding='utf-8') as f:
    code = f.read()

old_scatter = """    // 8. 9-BOX TALENT ASSESSMENT SCATTER MATRIX
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
                formatter: props.tooltipFormatter || ((p) => {
                    const d = p.data;
                    const name = d[2] || 'Talenta';
                    const perf = d[0];
                    const pot = d[1];
                    const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${primaryColor};"></span>`;
                    let quadrant = 'Core Performer';
                    if (perf >= 85 && pot >= 85) quadrant = '🌟 Star & High Potential';
                    else if (perf >= 85 && pot < 85) quadrant = '⚡ High Performer';
                    else if (perf < 85 && pot >= 85) quadrant = '🚀 High Potential';
                    else quadrant = '📈 Solid Professional';

                    return `<div style="font-weight: 750; margin-bottom: 4px; color: ${isDark ? '#f8fafc' : '#0f172a'};">${dot}${name}</div>
                    <div style="font-size: 11px; margin-bottom: 6px; color: ${isDark ? '#818cf8' : '#4f46e5'}; font-weight: 650;">${quadrant}</div>
                    <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px;">
                        <span>Kinerja (Performance):</span>
                        <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${perf} / 100</strong>
                    </div>
                    <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px; margin-top: 2px;">
                        <span>Potensi (Potential):</span>
                        <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${pot} / 100</strong>
                    </div>`;
                }),
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
    }"""

new_scatter = """    // 8. ULTRA-PREMIUM 9-BOX TALENT ASSESSMENT SCATTER MATRIX
    if (props.type === 'scatter') {
        const scatterData = Array.isArray(props.data) ? props.data : [];

        return {
            color: colors,
            grid: {
                top: 24,
                right: 28,
                bottom: 38,
                left: 38,
                containLabel: true,
            },
            tooltip: {
                trigger: 'item',
                ...baseTooltip,
                formatter: props.tooltipFormatter || ((p) => {
                    const d = p.data;
                    const name = d[2] || 'Talenta';
                    const perf = d[0];
                    const pot = d[1];
                    const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${primaryColor};"></span>`;
                    let quadrant = 'Core Performer';
                    if (perf >= 80 && pot >= 80) quadrant = '🌟 Star & Future Leader (Tier 1)';
                    else if (perf >= 80 && pot < 80) quadrant = '⚡ High Performer / Specialist';
                    else if (perf < 80 && pot >= 80) quadrant = '🚀 High Potential / Growth';
                    else if (perf < 60 && pot < 60) quadrant = '⚠️ Underperformer / Need Coaching';
                    else quadrant = '📈 Solid Core Contributor';

                    return `<div style="font-weight: 750; margin-bottom: 4px; color: ${isDark ? '#f8fafc' : '#0f172a'};">${dot}${name}</div>
                    <div style="font-size: 11px; margin-bottom: 6px; color: ${isDark ? '#818cf8' : '#4f46e5'}; font-weight: 700;">${quadrant}</div>
                    <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px;">
                        <span>Skor Kinerja (X):</span>
                        <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${perf} / 100</strong>
                    </div>
                    <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px; margin-top: 2px;">
                        <span>Skor Potensi (Y):</span>
                        <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${pot} / 100</strong>
                    </div>`;
                }),
            },
            xAxis: {
                type: 'value',
                name: 'Kinerja (Performance) →',
                nameLocation: 'middle',
                nameGap: 26,
                nameTextStyle: { ...baseAxisLabel, fontWeight: 700, fontSize: 11.5 },
                min: 0,
                max: 100,
                axisLabel: baseAxisLabel,
                splitLine: {
                    lineStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(226, 232, 240, 0.8)',
                        type: 'dashed',
                    },
                },
            },
            yAxis: {
                type: 'value',
                name: 'Potensi (Potential) →',
                nameLocation: 'middle',
                nameGap: 26,
                nameTextStyle: { ...baseAxisLabel, fontWeight: 700, fontSize: 11.5 },
                min: 0,
                max: 100,
                axisLabel: baseAxisLabel,
                splitLine: {
                    lineStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(226, 232, 240, 0.8)',
                        type: 'dashed',
                    },
                },
            },
            series: [
                {
                    name: 'Talenta',
                    type: 'scatter',
                    symbolSize: (data) => Math.max(16, (data[3] || 20)),
                    data: scatterData,
                    itemStyle: {
                        color: new echarts.graphic.RadialGradient(0.3, 0.3, 0.85, [
                            { offset: 0, color: '#ffffff' },
                            { offset: 0.3, color: primaryColor },
                            { offset: 1, color: `${primaryColor}cc` },
                        ]),
                        borderColor: isDark ? '#0f172a' : '#ffffff',
                        borderWidth: 2.5,
                        shadowColor: `${primaryColor}60`,
                        shadowBlur: 14,
                        shadowOffsetY: 4,
                    },
                    label: {
                        show: true,
                        position: 'bottom',
                        distance: 6,
                        formatter: (p) => (p.data[2] ? p.data[2].split(' ')[0] : ''),
                        color: isDark ? '#cbd5e1' : '#475569',
                        fontSize: 10.5,
                        fontWeight: 700,
                        fontFamily: 'inherit',
                    },
                    emphasis: {
                        scale: 1.4,
                        itemStyle: {
                            borderColor: '#ffffff',
                            borderWidth: 3.5,
                            shadowBlur: 22,
                            shadowColor: `${primaryColor}99`,
                        },
                    },
                    markArea: {
                        silent: true,
                        data: [
                            // 1. Top-Right Star Talent Zone
                            [
                                {
                                    name: '🌟 STAR TALENT',
                                    xAxis: 80,
                                    yAxis: 80,
                                    itemStyle: {
                                        color: isDark ? 'rgba(99, 102, 241, 0.12)' : 'rgba(99, 102, 241, 0.06)',
                                    },
                                    label: {
                                        position: 'insideTopRight',
                                        offset: [-6, 6],
                                        color: isDark ? '#a5b4fc' : '#4f46e5',
                                        fontWeight: 800,
                                        fontSize: 10,
                                        fontFamily: 'inherit',
                                    },
                                },
                                { xAxis: 100, yAxis: 100 },
                            ],
                            // 2. Bottom-Left Development Zone
                            [
                                {
                                    name: '⚠️ DEV NEEDED',
                                    xAxis: 0,
                                    yAxis: 0,
                                    itemStyle: {
                                        color: isDark ? 'rgba(239, 68, 68, 0.07)' : 'rgba(239, 68, 68, 0.035)',
                                    },
                                    label: {
                                        position: 'insideBottomLeft',
                                        offset: [6, -6],
                                        color: isDark ? '#fca5a5' : '#dc2626',
                                        fontWeight: 750,
                                        fontSize: 9.5,
                                        fontFamily: 'inherit',
                                    },
                                },
                                { xAxis: 60, yAxis: 60 },
                            ],
                        ],
                    },
                    markLine: {
                        silent: true,
                        symbol: ['none', 'none'],
                        lineStyle: {
                            type: 'dashed',
                            color: isDark ? 'rgba(255, 255, 255, 0.18)' : 'rgba(148, 163, 184, 0.65)',
                            width: 1.5,
                        },
                        data: [
                            { xAxis: 60 },
                            { xAxis: 80 },
                            { yAxis: 60 },
                            { yAxis: 80 },
                        ],
                    },
                },
            ],
            ...props.options,
        };
    }"""

if old_scatter in code:
    code = code.replace(old_scatter, new_scatter)
    with open(chart_file, 'w', encoding='utf-8') as f:
        f.write(code)
    print("SUCCESS: 9-Box Talent Assessment Scatter Matrix upgraded in HcisChart.vue!")
else:
    print("WARNING: old_scatter not found")
