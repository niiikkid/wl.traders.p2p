<script setup>
import '@/Components/Charts/echarts.js';
import VChart from 'vue-echarts';
import { computed } from 'vue';
import { useThemeColors, withAlpha } from '@/composables/useThemeColors.js';

/**
 * Reusable dashboard chart built on vue-echarts. Handles theme-aware colors,
 * value formatting (money / percent / count), a dashed comparison series and
 * multi-series (e.g. per-merchant split), so pages only pass data.
 */
const props = defineProps({
    type: {
        type: String,
        default: 'line',
        validator: (value) => ['line', 'bar'].includes(value),
    },
    labels: {
        type: Array,
        default: () => [],
    },
    /**
     * Array of { name, data:number[], colorToken?, color?, dashed?, area? }.
     */
    series: {
        type: Array,
        default: () => [],
    },
    valueType: {
        type: String,
        default: 'raw',
        validator: (value) => ['money', 'percent', 'count', 'raw'].includes(value),
    },
    yMin: {
        type: [Number, null],
        default: null,
    },
    yMax: {
        type: [Number, null],
        default: null,
    },
    showLegend: {
        type: Boolean,
        default: false,
    },
    smooth: {
        type: Boolean,
        default: true,
    },
    height: {
        type: String,
        default: '100%',
    },
    /** Overrides value rendering on the axis (and tooltip unless overridden). */
    valueFormatter: {
        type: Function,
        default: null,
    },
    /** Overrides value rendering in the tooltip only. */
    tooltipValueFormatter: {
        type: Function,
        default: null,
    },
});

const PALETTE_TOKENS = ['primary', 'secondary', 'success', 'accent', 'info', 'warning', 'error'];
const FALLBACK_COLOR = '#6366f1';
const SHADOW_COLOR = 'rgba(148, 163, 175, 0.55)';

const { colors } = useThemeColors();

const axisLabelColor = computed(() => (
    colors.value['base-content'] ? withAlpha(colors.value['base-content'], 0.5) : '#9ca3af'
));
// Subtle, neutral-grey gridlines that stay secondary to the data line.
const splitLineColor = 'rgba(148, 163, 184, 0.12)';

const formatValue = (value) => {
    if (props.valueFormatter) {
        return props.valueFormatter(value);
    }
    const number = Number(value ?? 0);
    if (props.valueType === 'percent') {
        return `${value}%`;
    }
    if (props.valueType === 'count') {
        return Math.round(number);
    }
    if (props.valueType === 'money') {
        return `$${value}`;
    }
    return value;
};

const formatTooltipValue = (value) => {
    if (props.tooltipValueFormatter) {
        return props.tooltipValueFormatter(value);
    }
    return formatValue(value);
};

const resolveSeriesColor = (item, index) => {
    if (item.color) {
        return item.color;
    }
    if (item.dashed) {
        return SHADOW_COLOR;
    }
    if (item.colorToken) {
        return colors.value[item.colorToken] || FALLBACK_COLOR;
    }
    const token = PALETTE_TOKENS[index % PALETTE_TOKENS.length];
    return colors.value[token] || FALLBACK_COLOR;
};

const showSymbol = computed(() => props.labels.length <= 40);

const buildSeriesItem = (item, index) => {
    const color = resolveSeriesColor(item, index);
    const showMarkers = showSymbol.value && !item.dashed;
    const lineType = item.dashed ? 'dashed' : 'solid';
    const lineStyle = {
        width: 2,
        color,
        type: lineType,
    };

    if (props.type === 'bar') {
        return {
            name: item.name,
            type: 'bar',
            data: item.data,
            barMaxWidth: 28,
            itemStyle: { color, borderRadius: [3, 3, 0, 0] },
            emphasis: { focus: 'none' },
        };
    }

    return {
        name: item.name,
        type: 'line',
        data: item.data,
        smooth: props.smooth,
        symbol: 'circle',
        symbolSize: 6,
        showSymbol: showMarkers,
        lineStyle,
        itemStyle: { color },
        // focus: 'series' conflicts with axis tooltip — it blurs the line and flickers
        // when multiple series share the same x-axis category.
        emphasis: item.dashed
            ? { disabled: true }
            : {
                focus: 'none',
                lineStyle,
                itemStyle: { color },
                showSymbol: showMarkers,
                scale: showMarkers,
            },
        ...(item.area
            ? { areaStyle: { color: withAlpha(color, 0.12) } }
            : {}),
        z: item.dashed ? 1 : 2,
    };
};

const option = computed(() => ({
    animationDuration: 300,
    grid: {
        left: 8,
        right: 16,
        top: props.showLegend ? 40 : 16,
        bottom: 8,
        containLabel: true,
    },
    legend: props.showLegend
        ? {
            top: 0,
            left: 0,
            icon: 'roundRect',
            itemWidth: 12,
            itemHeight: 12,
            textStyle: { color: axisLabelColor.value },
        }
        : undefined,
    tooltip: {
        trigger: 'axis',
        confine: true,
        backgroundColor: 'rgba(17, 24, 39, 0.92)',
        borderWidth: 0,
        textStyle: { color: '#e5e7eb' },
        valueFormatter: (value) => formatTooltipValue(value),
    },
    xAxis: {
        type: 'category',
        boundaryGap: props.type === 'bar',
        data: props.labels,
        axisLine: { show: false },
        axisTick: { show: false },
        axisLabel: { color: axisLabelColor.value, hideOverlap: true },
    },
    yAxis: {
        type: 'value',
        min: props.yMin ?? undefined,
        max: props.yMax ?? undefined,
        splitLine: { lineStyle: { color: splitLineColor, width: 1 } },
        axisLabel: {
            color: axisLabelColor.value,
            formatter: (value) => formatValue(value),
        },
    },
    series: props.series
        .filter((item) => Array.isArray(item?.data))
        .map((item, index) => buildSeriesItem(item, index)),
}));
</script>

<template>
    <VChart
        :option="option"
        :update-options="{ notMerge: true }"
        :style="{ height, width: '100%' }"
        autoresize
    />
</template>
