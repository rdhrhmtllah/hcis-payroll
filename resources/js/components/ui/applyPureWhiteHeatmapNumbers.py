import os

chart_file = r"C:\laragon\www\HCIS-Worktree-2\resources\js\components\ui\HcisChart.vue"
with open(chart_file, 'r', encoding='utf-8') as f:
    code = f.read()

old_heatmap_section = """            visualMap: {
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
            ],"""

new_heatmap_section = """            visualMap: {
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
                        ? ['rgba(30, 41, 59, 0.75)', `${primaryColor}66`, `${primaryColor}aa`, primaryColor]
                        : [`${primaryColor}45`, `${primaryColor}70`, `${primaryColor}95`, primaryColor],
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
                        color: '#ffffff',
                        fontSize: 12,
                        fontWeight: 750,
                        fontFamily: 'Inter, system-ui, -apple-system, sans-serif',
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
                            borderColor: '#ffffff',
                            borderWidth: 2.5,
                            shadowBlur: 14,
                            shadowColor: `${primaryColor}80`,
                        },
                        label: {
                            color: '#ffffff',
                            fontWeight: 900,
                            fontSize: 13.5,
                            fontFamily: 'Inter, system-ui, -apple-system, sans-serif',
                        },
                    },
                },
            ],"""

if old_heatmap_section in code:
    code = code.replace(old_heatmap_section, new_heatmap_section)
    with open(chart_file, 'w', encoding='utf-8') as f:
        f.write(code)
    print("SUCCESS: Pure white numbers on colored Heatmap cells applied to HcisChart.vue!")
else:
    print("WARNING: old_heatmap_section not found")
