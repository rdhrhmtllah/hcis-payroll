import os

# 1. Update HcisChart.vue
chart_file = r"C:\laragon\www\HCIS-Worktree-2\resources\js\components\ui\HcisChart.vue"
with open(chart_file, 'r', encoding='utf-8') as f:
    code = f.read()

old_donut = """    // 4. DONUT / PIE CHART
    if (props.type === 'donut' || props.type === 'pie') {
        const isDonut = props.type === 'donut';
        const pieData = Array.isArray(props.data) ? props.data : [];

        return {
            color: colors,
            tooltip: {
                ...baseTooltip,
                trigger: 'item',
                formatter: props.tooltipFormatter || ((p) => {
                    const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${p.color};"></span>`;
                    const val = typeof p.value === 'number' ? p.value.toLocaleString('id-ID') : p.value;
                    return `<div style="font-weight: 750; margin-bottom: 4px; color: ${isDark ? '#f8fafc' : '#0f172a'};">${dot}${p.name}</div>
                    <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px;">
                        <span>Jumlah:</span>
                        <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${val} Orang (${p.percent}%)</strong>
                    </div>`;
                }),
            },
            legend: {
                bottom: 4,
                left: 'center',
                icon: 'circle',
                itemWidth: 8,
                itemHeight: 8,
                itemGap: 16,
                textStyle: baseAxisLabel,
            },
            series: [
                {
                    name: props.title || 'Distribusi',
                    type: 'pie',
                    radius: isDonut ? ['52%', '76%'] : '74%',
                    center: ['50%', '46%'],
                    avoidLabelOverlap: true,
                    itemStyle: {
                        borderRadius: 8,
                        borderColor: isDark ? '#0f172a' : '#ffffff',
                        borderWidth: 3,
                        shadowColor: 'rgba(15, 23, 42, 0.08)',
                        shadowBlur: 8,
                    },
                    label: {
                        show: !isDonut,
                        position: isDonut ? 'center' : 'outside',
                        color: isDark ? '#f8fafc' : '#0f172a',
                        fontWeight: 600,
                        fontSize: 12,
                    },
                    data: pieData,
                    ...props.options.series?.[0],
                },
            ],
            ...props.options,
        };
    }"""

new_donut = """    // 4. ULTRA-PREMIUM DONUT / PIE CHART WITH DYNAMIC GRAND TOTAL CENTERPIECE
    if (props.type === 'donut' || props.type === 'pie') {
        const isDonut = props.type === 'donut';
        const pieData = Array.isArray(props.data) ? props.data : [];

        const grandTotal = pieData.reduce((acc, curr) => acc + (Number(curr.value) || 0), 0);
        const formattedTotal = (props.metricValue !== '' && props.metricValue !== null && typeof props.metricValue !== 'undefined')
            ? String(props.metricValue)
            : grandTotal.toLocaleString('id-ID');
        const centerKickerText = props.metricLabel || props.metricUnit || 'TOTAL';

        return {
            color: colors,
            tooltip: {
                ...baseTooltip,
                trigger: 'item',
                formatter: props.tooltipFormatter || ((p) => {
                    const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${p.color};"></span>`;
                    const val = typeof p.value === 'number' ? p.value.toLocaleString('id-ID') : p.value;
                    return `<div style="font-weight: 750; margin-bottom: 4px; color: ${isDark ? '#f8fafc' : '#0f172a'};">${dot}${p.name}</div>
                    <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px;">
                        <span>Jumlah:</span>
                        <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${val} Orang (${p.percent}%)</strong>
                    </div>`;
                }),
            },
            legend: {
                bottom: 4,
                left: 'center',
                icon: 'circle',
                itemWidth: 8,
                itemHeight: 8,
                itemGap: 16,
                textStyle: baseAxisLabel,
            },
            series: [
                {
                    name: props.title || 'Distribusi',
                    type: 'pie',
                    radius: isDonut ? ['54%', '78%'] : '74%',
                    center: ['50%', '46%'],
                    avoidLabelOverlap: false,
                    itemStyle: {
                        borderRadius: 8,
                        borderColor: isDark ? '#0f172a' : '#ffffff',
                        borderWidth: 3,
                        shadowColor: 'rgba(15, 23, 42, 0.08)',
                        shadowBlur: 8,
                    },
                    label: isDonut
                        ? {
                              show: true,
                              position: 'center',
                              formatter: `{val|${formattedTotal}}\\n{lbl|${centerKickerText.toUpperCase()}}`,
                              rich: {
                                  val: {
                                      fontSize: 24,
                                      fontWeight: 850,
                                      color: isDark ? '#ffffff' : '#0f172a',
                                      lineHeight: 30,
                                      fontFamily: 'inherit',
                                  },
                                  lbl: {
                                      fontSize: 10,
                                      fontWeight: 700,
                                      color: isDark ? '#94a3b8' : '#64748b',
                                      lineHeight: 16,
                                      letterSpacing: 1,
                                      fontFamily: 'inherit',
                                  },
                              },
                          }
                        : {
                              show: true,
                              position: 'outside',
                              color: isDark ? '#f8fafc' : '#0f172a',
                              fontWeight: 600,
                              fontSize: 12,
                          },
                    emphasis: {
                        scale: true,
                        scaleSize: 6,
                        label: isDonut
                            ? {
                                  show: true,
                                  fontSize: 22,
                                  formatter: '{val|{c}}\\n{lbl|{b}}',
                                  rich: {
                                      val: {
                                          fontSize: 22,
                                          fontWeight: 850,
                                          color: isDark ? '#ffffff' : '#0f172a',
                                          lineHeight: 28,
                                          fontFamily: 'inherit',
                                      },
                                      lbl: {
                                          fontSize: 10,
                                          fontWeight: 700,
                                          color: isDark ? '#a5b4fc' : '#4f46e5',
                                          lineHeight: 16,
                                          fontFamily: 'inherit',
                                      },
                                  },
                              }
                            : undefined,
                    },
                    data: pieData,
                    ...props.options.series?.[0],
                },
            ],
            ...props.options,
        };
    }"""

if old_donut in code:
    code = code.replace(old_donut, new_donut)
    with open(chart_file, 'w', encoding='utf-8') as f:
        f.write(code)
    print("SUCCESS: Dynamic Grand Total centerpiece added to Donut in HcisChart.vue!")
else:
    print("WARNING: old_donut not found")

# 2. Update demos/chart.vue
demo_file = r"C:\laragon\www\HCIS-Worktree-2\resources\js\Pages\SYSTEM\DesignSystem\demos\chart.vue"
with open(demo_file, 'r', encoding='utf-8') as f:
    demo_code = f.read()

demo_code = demo_code.replace(
    '                    :data="[\n                        { name: \'Sarjana (S1)\', value: 210 },\n                        { name: \'Magister (S2)\', value: 74 },\n                        { name: \'Diploma (D3)\', value: 65 },\n                        { name: \'Doktor (S3)\', value: 25 }\n                    ]"',
    '                    :data="[\n                        { name: \'Sarjana (S1)\', value: 210 },\n                        { name: \'Magister (S2)\', value: 74 },\n                        { name: \'Diploma (D3)\', value: 65 },\n                        { name: \'Doktor (S3)\', value: 25 }\n                    ]"\n                    metric-label="Karyawan"'
)

with open(demo_file, 'w', encoding='utf-8') as f:
    f.write(demo_code)
print("SUCCESS: demos/chart.vue updated with metric-label for Donut!")
