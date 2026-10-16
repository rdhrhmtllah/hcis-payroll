import os

chart_file = r"C:\laragon\www\HCIS-Worktree-2\resources\js\components\ui\HcisChart.vue"
with open(chart_file, 'r', encoding='utf-8') as f:
    code = f.read()

old_donut_label = """                    label: isDonut
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
                    },"""

new_donut_label = """                    label: isDonut
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
                    },"""

if old_donut_label in code:
    code = code.replace(old_donut_label, new_donut_label)
    with open(chart_file, 'w', encoding='utf-8') as f:
        f.write(code)
    print("SUCCESS: Clean minimalist Grand Total number applied to Donut in HcisChart.vue!")
else:
    print("WARNING: old_donut_label not found")
