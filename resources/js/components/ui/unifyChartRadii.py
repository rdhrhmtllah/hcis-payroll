import os

chart_file = r"C:\laragon\www\HCIS-Worktree-2\resources\js\components\ui\HcisChart.vue"
with open(chart_file, 'r', encoding='utf-8') as f:
    code = f.read()

# 1. Update Bar radius to [8, 8, 0, 0]
code = code.replace(
    "borderRadius: props.stacked ? [0, 0, 0, 0] : [6, 6, 0, 0],",
    "borderRadius: props.stacked ? [0, 0, 0, 0] : [8, 8, 0, 0],"
)

# 2. Update Funnel radius to 8
code = code.replace(
    "borderRadius: 10,\n                    borderColor: isDark ? '#0f172a' : '#ffffff',\n                    borderWidth: 3.5,",
    "borderRadius: 8,\n                    borderColor: isDark ? '#0f172a' : '#ffffff',\n                    borderWidth: 3,"
)
code = code.replace(
    "borderRadius: 12,\n                            shadowBlur: 16,",
    "borderRadius: 10,\n                            shadowBlur: 14,"
)

# 3. Update Treemap radius to 8 with borderWidth 3 and gapWidth 4
code = code.replace(
    "borderRadius: 12,\n                                borderColor: isDark ? '#0f172a' : '#ffffff',\n                                borderWidth: 4,\n                                gapWidth: 6,",
    "borderRadius: 8,\n                                borderColor: isDark ? '#0f172a' : '#ffffff',\n                                borderWidth: 3,\n                                gapWidth: 4,"
)
code = code.replace(
    "emphasis: {\n                        itemStyle: {\n                            borderRadius: 14,",
    "emphasis: {\n                        itemStyle: {\n                            borderRadius: 10,"
)

with open(chart_file, 'w', encoding='utf-8') as f:
    f.write(code)

print("SUCCESS: Standardized all chart corner radii to 8px in HcisChart.vue!")
