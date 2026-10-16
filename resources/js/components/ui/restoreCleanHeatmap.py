import os

chart_file = r"C:\laragon\www\HCIS-Worktree-2\resources\js\components\ui\HcisChart.vue"
with open(chart_file, 'r', encoding='utf-8') as f:
    code = f.read()

old_heatmap = """    // 10. ROUNDED HEATMAP (ACTIVITY / ATTENDANCE MATRIX WITH IN-CELL VALUES)
    if (props.type === 'heatmap') {
        const heatmapData = Array.isArray(props.data) ? props.data : [];
        const xData = props.categories.length > 0 ? props.categories : ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00'];
        const yData = props.series.length > 0 && props.series[0].categories
            ? props.series[0].categories
            : ['Sen', 'Sel', 'Rab', 'Kam', 'Jum'];

        let maxVal = 10;
        heatmapData.forEach((d) => {
            const val = Array.isArray(d) ? d[2] : (d.value || 0);
            if (val > maxVal) maxVal = val;
        });

        return {
            color: colors,
            grid: {
                top: 20,
                right: 18,
                bottom: 45,
                left: 14,
                containLabel: true,
            },
            tooltip: {
                ...baseTooltip,
                trigger: 'item',
                position: 'top',
                formatter: props.tooltipFormatter || ((p) => {
                    const d = p.data;
                    const val = Array.isArray(d) ? d[2] : (d.value ?? d);
                    const xLabel = xData[d[0]] || d[0];
                    const yLabel = yData[d[1]] || d[1];
                    const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${primaryColor};"></span>`;
                    return `<div style="font-weight: 750; margin-bottom: 4px; color: ${isDark ? '#f8fafc' : '#0f172a'};">${dot}${yLabel} · Pukul ${xLabel} WIB</div>
                    <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px;">
                        <span>Intensitas Aktivitas:</span>
                        <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${val} Log Check-in</strong>
                    </div>`;
                }),
            },
            xAxis: {
                type: 'category',
                data: xData,
                splitArea: { show: false },
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
            },
            yAxis: {
                type: 'category',
                data: yData,
                splitArea: { show: false },
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: { ...baseAxisLabel, fontWeight: 600 },
            },
            visualMap: {
                min: 0,
                max: maxVal,
                calculable: false,
                orient: 'horizontal',
                left: 'center',
                bottom: 2,
                itemWidth: 10,
                itemHeight: 70,
                inRange: {
                    color: isDark
                        ? ['rgba(30, 41, 59, 0.45)', `${primaryColor}35`, `${primaryColor}70`, primaryColor]
                        : ['rgba(241, 245, 249, 0.85)', `${primaryColor}22`, `${primaryColor}55`, `${primaryColor}a5`],
                },
                textStyle: baseAxisLabel,
            },
            series: [
                {
                    name: props.title || 'Aktivitas',
                    type: 'heatmap',
                    data: heatmapData,
                    label: {
                        show: true,
                        formatter: (p) => {
                            const val = Array.isArray(p.data) ? p.data[2] : (p.data.value ?? p.data);
                            return val !== undefined && val !== null ? String(val) : '';
                        },
                        color: isDark ? 'rgba(248, 250, 252, 0.88)' : 'rgba(51, 65, 85, 0.88)',
                        fontSize: 12,
                        fontWeight: 650,
                        fontFamily: 'Inter, system-ui, -apple-system, sans-serif',
                    },
                    itemStyle: {
                        borderRadius: 8,
                        borderColor: isDark ? '#0f172a' : '#ffffff',
                        borderWidth: 2.5,
                        shadowColor: 'rgba(15, 23, 42, 0.03)',
                        shadowBlur: 3,
                    },
                    emphasis: {
                        itemStyle: {
                            borderRadius: 8,
                            borderColor: primaryColor,
                            borderWidth: 2,
                            shadowBlur: 10,
                            shadowColor: `${primaryColor}45`,
                        },
                        label: {
                            color: isDark ? '#ffffff' : '#0f172a',
                            fontWeight: 800,
                            fontSize: 13,
                            fontFamily: 'Inter, system-ui, -apple-system, sans-serif',
                        },
                    },
                },
            ],
            ...props.options,
        };
    }"""

new_heatmap = """    // 10. ULTRA-CLEAN ROUNDED HEATMAP (ACTIVITY / ATTENDANCE MATRIX)
    if (props.type === 'heatmap') {
        const heatmapData = Array.isArray(props.data) ? props.data : [];
        const xData = props.categories.length > 0 ? props.categories : ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00'];
        const yData = props.series.length > 0 && props.series[0].categories
            ? props.series[0].categories
            : ['Sen', 'Sel', 'Rab', 'Kam', 'Jum'];

        let maxVal = 10;
        heatmapData.forEach((d) => {
            const val = Array.isArray(d) ? d[2] : (d.value || 0);
            if (val > maxVal) maxVal = val;
        });

        return {
            color: colors,
            grid: {
                top: 20,
                right: 18,
                bottom: 45,
                left: 14,
                containLabel: true,
            },
            tooltip: {
                ...baseTooltip,
                trigger: 'item',
                position: 'top',
                formatter: props.tooltipFormatter || ((p) => {
                    const d = p.data;
                    const val = Array.isArray(d) ? d[2] : (d.value ?? d);
                    const xLabel = xData[d[0]] || d[0];
                    const yLabel = yData[d[1]] || d[1];
                    const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${primaryColor};"></span>`;
                    return `<div style="font-weight: 750; margin-bottom: 4px; color: ${isDark ? '#f8fafc' : '#0f172a'};">${dot}${yLabel} · Pukul ${xLabel} WIB</div>
                    <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px;">
                        <span>Intensitas Aktivitas:</span>
                        <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${val} Log Check-in</strong>
                    </div>`;
                }),
            },
            xAxis: {
                type: 'category',
                data: xData,
                splitArea: { show: false },
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
            },
            yAxis: {
                type: 'category',
                data: yData,
                splitArea: { show: false },
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: { ...baseAxisLabel, fontWeight: 600 },
            },
            visualMap: {
                min: 0,
                max: maxVal,
                calculable: false,
                orient: 'horizontal',
                left: 'center',
                bottom: 2,
                itemWidth: 12,
                itemHeight: 80,
                inRange: {
                    color: isDark
                        ? ['rgba(30, 41, 59, 0.6)', `${primaryColor}66`, `${primaryColor}cc`, primaryColor]
                        : [`${primaryColor}15`, `${primaryColor}40`, `${primaryColor}88`, primaryColor],
                },
                textStyle: baseAxisLabel,
            },
            series: [
                {
                    name: props.title || 'Aktivitas',
                    type: 'heatmap',
                    data: heatmapData,
                    label: { show: false },
                    itemStyle: {
                        borderRadius: 8,
                        borderColor: isDark ? '#0f172a' : '#ffffff',
                        borderWidth: 3,
                        shadowColor: 'rgba(15, 23, 42, 0.04)',
                        shadowBlur: 4,
                    },
                    emphasis: {
                        itemStyle: {
                            borderRadius: 10,
                            borderColor: primaryColor,
                            borderWidth: 2,
                            shadowBlur: 10,
                            shadowColor: `${primaryColor}60`,
                        },
                    },
                },
            ],
            ...props.options,
        };
    }"""

if old_heatmap in code:
    code = code.replace(old_heatmap, new_heatmap)
    with open(chart_file, 'w', encoding='utf-8') as f:
        f.write(code)
    print("SUCCESS: Restored ultra-clean rounded Heatmap in HcisChart.vue!")
else:
    print("WARNING: old_heatmap not found")
