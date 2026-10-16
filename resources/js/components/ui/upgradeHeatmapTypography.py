import os

chart_file = r"C:\laragon\www\HCIS-Worktree-2\resources\js\components\ui\HcisChart.vue"
with open(chart_file, 'r', encoding='utf-8') as f:
    code = f.read()

old_label_block = """                    label: {
                        show: true,
                        formatter: (p) => {
                            const val = Array.isArray(p.data) ? p.data[2] : (p.data.value ?? p.data);
                            return val !== undefined && val !== null ? val : '';
                        },
                        color: isDark ? '#f8fafc' : '#1e293b',
                        fontSize: 11,
                        fontWeight: 750,
                        fontFamily: 'inherit',
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
                            shadowBlur: 12,
                            shadowColor: `${primaryColor}70`,
                        },
                        label: {
                            color: '#ffffff',
                            fontWeight: 900,
                            fontSize: 12,
                        },
                    },"""

new_label_block = """                    label: {
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
                    },"""

if old_label_block in code:
    code = code.replace(old_label_block, new_label_block)
    with open(chart_file, 'w', encoding='utf-8') as f:
        f.write(code)
    print("SUCCESS: Upgraded Heatmap typography to 14px Heavy / 16px Hover in HcisChart.vue!")
else:
    print("WARNING: old_label_block not found")
