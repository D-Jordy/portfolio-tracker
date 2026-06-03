<template>
    <div class="rounded-lg bg-white p-4 shadow-sm">
        <!-- Header row: title + time range buttons -->
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-medium uppercase tracking-wider text-gray-500">Portfolio history</h3>
            <div class="flex gap-1">
                <button
                    v-for="r in ranges"
                    :key="r"
                    @click="selectedRange = r"
                    class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                    :class="selectedRange === r
                        ? 'bg-gray-800 text-white'
                        : 'text-gray-400 hover:bg-gray-100 hover:text-gray-700'"
                >
                    {{ r }}
                </button>
            </div>
        </div>

        <!-- Series toggle chips -->
        <div class="mb-2 flex flex-wrap gap-1.5">
            <button
                v-for="s in seriesConfig"
                :key="s.name"
                @click="toggleSeries(s.name)"
                class="rounded-full border px-2.5 py-0.5 text-xs font-medium transition-all"
                :class="visibility[s.name]
                    ? 'border-transparent text-white'
                    : 'border-gray-200 bg-white !text-gray-400'"
                :style="visibility[s.name] ? { backgroundColor: s.color } : { color: s.color }"
            >
                {{ s.name }}
            </button>
        </div>

        <VueApexCharts
            :key="chartKey"
            type="line"
            height="260"
            :options="chartOptions"
            :series="series"
        />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import VueApexCharts from 'vue3-apexcharts'

const props = defineProps({
    chartData: { type: Array, required: true },
})

const ranges = ['1M', '3M', '6M', '1Y', 'All']
const selectedRange = ref('All')

const seriesConfig = [
    { name: 'Total value', key: 'total_value_eur',          color: '#3b82f6', dash: 0, width: 2   },
    { name: 'Net gain',    key: 'net_gain_eur',             color: '#22c55e', dash: 0, width: 2   },
    { name: 'Dividends',  key: 'cumulative_dividends_eur',  color: '#8b5cf6', dash: 0, width: 1.5 },
    { name: 'Fees',       key: 'cumulative_fees_eur',       color: '#f97316', dash: 4, width: 1.5 },
]

const visibility = ref(Object.fromEntries(seriesConfig.map(s => [s.name, true])))

function toggleSeries(name) {
    visibility.value[name] = !visibility.value[name]
}

const filteredData = computed(() => {
    if (selectedRange.value === 'All' || props.chartData.length === 0) {
        return props.chartData
    }
    const last = props.chartData[props.chartData.length - 1].date
    const cutoff = new Date(last)
    cutoff.setMonth(cutoff.getMonth() - { '1M': 1, '3M': 3, '6M': 6, '1Y': 12 }[selectedRange.value])
    const cutoffStr = cutoff.toISOString().slice(0, 10)
    return props.chartData.filter(d => d.date >= cutoffStr)
})

const visibleConfig = computed(() => seriesConfig.filter(s => visibility.value[s.name]))

// Forces a full chart re-mount on range/visibility change so ApexCharts recalculates both axes.
const chartKey = computed(() =>
    selectedRange.value + '-' + visibleConfig.value.map(s => s.name).join(',')
)

const series = computed(() =>
    visibleConfig.value.map(s => ({
        name: s.name,
        data: filteredData.value.map(d => [d.date, d[s.key]]),
    }))
)

const eurFmt = new Intl.NumberFormat('nl-NL', {
    style: 'currency', currency: 'EUR', maximumFractionDigits: 0,
})

const chartOptions = computed(() => ({
    chart: {
        type: 'line',
        toolbar: { show: false },
        animations: { enabled: false },
        fontFamily: 'inherit',
    },
    colors: visibleConfig.value.map(s => s.color),
    stroke: {
        curve: 'straight',
        width: visibleConfig.value.map(s => s.width),
        dashArray: visibleConfig.value.map(s => s.dash),
    },
    markers: { size: 0 },
    xaxis: {
        type: 'datetime',
        labels: { datetimeUTC: false, style: { fontSize: '11px' } },
    },
    yaxis: {
        labels: {
            formatter: v => eurFmt.format(v),
            style: { fontSize: '11px' },
        },
    },
    tooltip: {
        x: { format: 'dd MMM yyyy' },
        y: { formatter: v => eurFmt.format(v) },
        shared: true,
        intersect: false,
    },
    legend: { show: false },
    grid: { strokeDashArray: 3, borderColor: '#e5e7eb' },
}))
</script>
