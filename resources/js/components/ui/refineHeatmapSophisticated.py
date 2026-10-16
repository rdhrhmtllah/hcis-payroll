import os

chart_file = r"C:\laragon\www\HCIS-Worktree-2\resources\js\components\ui\HcisChart.vue"
with open(chart_file, 'r', encoding='utf-8') as f:
    code = f.read()

old_heatmap_block = """            visualMap: {
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
                    label: {
                        show: true,
                        formatter: (p) => {
                            const val = Array.isArray(p.data) ? p.data[2] : (p.data.value ?? p.data);
                            return val !== undefined && val !== null ? String(val) : '';
                        },
                        color: isDark ? '#ffffff' : '#0f172a',
                        fontSize: 14,
                        fontWeight: 850,
                        fontFamily: 'Inter, system-ui, -apple-system, sans-serif',
                        textShadowColor: isDark ? 'rgba(0, 0, 0, 0.45)' : 'rgba(255, 255, 255, 0.8)',
                        textShadowBlur: 2,
                    },
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
                            borderWidth: 2.5,
                            shadowBlur: 16,
                            shadowColor: `${primaryColor}80`,
                        },
                        label: {
                            color: '#ffffff',
                            fontWeight: 900,
                            fontSize: 16,
                            fontFamily: 'Inter, system-ui, -apple-system, sans-serif',
                            textShadowColor: 'rgba(0, 0, 0, 0.5)',
                            textShadowBlur: 6,
                        },
                    },
                },
            ],"""

new_heatmap_block = """            visualMap: {
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
            ],"""

if old_heatmap_block in code:
    code = code.replace(old_heatmap_block, new_heatmap_block)
    with open(chart_file, 'w', encoding='utf-8') as f:
        f.write(code)
    print("SUCCESS: Heatmap refined with sophisticated low-contrast executive styling in HcisChart.vue!")
else:
    print("WARNING: old_heatmap_block not found in HcisChart.vue")
