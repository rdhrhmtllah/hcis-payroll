import os

chart_file = r"C:\laragon\www\HCIS-Worktree-2\resources\js\components\ui\HcisChart.vue"
with open(chart_file, 'r', encoding='utf-8') as f:
    code = f.read()

old_donut_block = """    // 4. ULTRA-PREMIUM DONUT / PIE CHART WITH DYNAMIC GRAND TOTAL CENTERPIECE
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
                              formatter: formattedTotal,
                              fontSize: 32,
                              fontWeight: 900,
                              color: isDark ? '#ffffff' : '#0f172a',
                              fontFamily: 'inherit',
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
                                  position: 'center',
                                  formatter: '{c}',
                                  fontSize: 32,
                                  fontWeight: 900,
                                  color: isDark ? '#ffffff' : '#0f172a',
                                  fontFamily: 'inherit',
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

new_donut_block = """    // 4. ULTRA-PREMIUM DONUT / PIE CHART WITH DEDICATED VECTOR CENTERPIECE
    if (props.type === 'donut' || props.type === 'pie') {
        const isDonut = props.type === 'donut';
        const pieData = Array.isArray(props.data) ? props.data : [];

        const grandTotal = pieData.reduce((acc, curr) => acc + (Number(curr.value) || 0), 0);
        const formattedTotal = (props.metricValue !== '' && props.metricValue !== null && typeof props.metricValue !== 'undefined')
            ? String(props.metricValue)
            : grandTotal.toLocaleString('id-ID');

        return {
            color: colors,
            title: isDonut
                ? {
                      text: String(formattedTotal),
                      left: 'center',
                      top: '40%',
                      textStyle: {
                          fontSize: 38,
                          fontWeight: 900,
                          color: isDark ? '#ffffff' : '#0f172a',
                          fontFamily: 'Inter, system-ui, -apple-system, sans-serif',
                          lineHeight: 38,
                      },
                  }
                : undefined,
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
                        position: 'outside',
                        color: isDark ? '#f8fafc' : '#0f172a',
                        fontWeight: 600,
                        fontSize: 12,
                    },
                    emphasis: {
                        scale: true,
                        scaleSize: 6,
                    },
                    data: pieData,
                    ...props.options.series?.[0],
                },
            ],
            ...props.options,
        };
    }"""

if old_donut_block in code:
    code = code.replace(old_donut_block, new_donut_block)
    with open(chart_file, 'w', encoding='utf-8') as f:
        f.write(code)
    print("SUCCESS: Upgraded Donut center to dedicated vector centerpiece in HcisChart.vue!")
else:
    print("WARNING: old_donut_block not found in HcisChart.vue")
