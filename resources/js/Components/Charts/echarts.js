import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { BarChart, LineChart } from 'echarts/charts';
import {
    GridComponent,
    LegendComponent,
    MarkLineComponent,
    TooltipComponent,
} from 'echarts/components';

/**
 * Single tree-shaken registration of the ECharts building blocks used across
 * dashboards. Importing this module (for its side effects) is enough to make
 * `vue-echarts` render line/bar charts with grid, tooltip and legend support.
 */
use([
    CanvasRenderer,
    LineChart,
    BarChart,
    GridComponent,
    TooltipComponent,
    LegendComponent,
    MarkLineComponent,
]);
