<script setup lang="ts">
import { computed } from 'vue';
import {
    VisArea,
    VisAxis,
    VisDonut,
    VisGroupedBar,
    VisLine,
    VisSingleContainer,
    VisXYContainer,
} from '@unovis/vue';
import {
    ChartContainer,
    ChartCrosshair,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
    componentToString,
    type ChartConfig,
} from '@/components/ui/chart';

type ChartType = 'area' | 'bar' | 'doughnut' | 'line' | 'pie';

type ChartData = {
    labels: string[];
    datasets: Array<{
        label?: string;
        data: Array<number | null>;
    }>;
};

type ChartPoint = {
    index: number;
    label: string;
    [key: string]: number | string;
};

type PiePoint = {
    label: string;
    value: number;
    color: string;
};

const props = withDefaults(
    defineProps<{
        type: ChartType;
        data: ChartData;
        title?: string;
    }>(),
    {
        title: '',
    },
);

const colors = [
    '--chart-1',
    '--chart-2',
    '--chart-3',
    '--chart-4',
    '--chart-5',
];
const isPieChart = computed(
    () => props.type === 'pie' || props.type === 'doughnut',
);

function colorAt(index: number): string {
    return `var(${colors[index % colors.length]})`;
}

const chartConfig = computed<ChartConfig>(() => {
    if (isPieChart.value) {
        return Object.fromEntries(
            props.data.labels.map((label, index) => [
                `slice${index}`,
                { label, color: colorAt(index) },
            ]),
        );
    }

    return Object.fromEntries(
        props.data.datasets.map((dataset, index) => [
            `series${index}`,
            {
                label: dataset.label || `Series ${index + 1}`,
                color: colorAt(index),
            },
        ]),
    );
});

const chartData = computed<ChartPoint[]>(() =>
    props.data.labels.map((label, index) => {
        const point: ChartPoint = { index, label };

        props.data.datasets.forEach((dataset, seriesIndex) => {
            point[`series${seriesIndex}`] = dataset.data[index] ?? 0;
        });

        return point;
    }),
);

const series = computed(() =>
    props.data.datasets.map((dataset, index) => {
        const key = `series${index}`;

        return {
            key,
            color: colorAt(index),
            accessor: (point: ChartPoint) => Number(point[key]),
        };
    }),
);

const pieData = computed<PiePoint[]>(() =>
    props.data.labels.map((label, index) => ({
        label,
        value: props.data.datasets[0]?.data[index] ?? 0,
        color: colorAt(index),
    })),
);

const xAccessor = (point: ChartPoint): number => point.index;
const pieValue = (point: PiePoint): number => point.value;
const pieColor = (point: PiePoint): string => point.color;
const xTicks = computed(() => props.data.labels.map((_, index) => index));

function formatXTick(value: number | Date): string {
    return props.data.labels[Number(value)] ?? '';
}

function formatTooltipLabel(value: number | Date): string {
    return props.data.labels[Number(value)] ?? String(value);
}
</script>

<template>
    <div class="w-full max-w-3xl">
        <h3 v-if="title" class="mb-3 font-semibold">{{ title }}</h3>
        <ChartContainer :config="chartConfig" class="min-h-[260px] w-full">
            <VisSingleContainer v-if="isPieChart" :data="pieData">
                <VisDonut
                    :value="pieValue"
                    :color="pieColor"
                    :arc-width="type === 'pie' ? 0 : 24"
                />
            </VisSingleContainer>

            <VisXYContainer v-else :data="chartData">
                <VisAxis
                    type="x"
                    :tick-values="xTicks"
                    :tick-format="formatXTick"
                    :domain-line="false"
                    :tick-line="false"
                />
                <VisAxis type="y" :domain-line="false" :tick-line="false" />

                <VisGroupedBar
                    v-if="type === 'bar'"
                    :x="xAccessor"
                    :y="series.map((item) => item.accessor)"
                    :color="series.map((item) => item.color)"
                    :rounded-corners="4"
                    :bar-padding="0.1"
                />
                <template v-else-if="type === 'line'">
                    <VisLine
                        v-for="item in series"
                        :key="item.key"
                        :x="xAccessor"
                        :y="item.accessor"
                        :color="item.color"
                    />
                </template>
                <template v-else>
                    <VisArea
                        v-for="item in series"
                        :key="item.key"
                        :x="xAccessor"
                        :y="item.accessor"
                        :color="item.color"
                        :line="true"
                        :opacity="0.35"
                    />
                </template>

                <ChartTooltip
                    :template="
                        componentToString(chartConfig, ChartTooltipContent, {
                            labelFormatter: formatTooltipLabel,
                        })
                    "
                />
                <ChartCrosshair
                    :color="series.map((item) => item.color)"
                    :template="
                        componentToString(chartConfig, ChartTooltipContent, {
                            labelFormatter: formatTooltipLabel,
                        })
                    "
                />
            </VisXYContainer>

            <ChartLegendContent />
        </ChartContainer>
    </div>
</template>
