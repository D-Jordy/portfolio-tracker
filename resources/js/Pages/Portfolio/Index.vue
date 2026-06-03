<template>
    <Head title="Portfolio" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Portfolio</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Top-line numbers -->
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Market value</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ eur(summary.total_value_eur) }}</p>
                        <p class="mt-1 text-xs text-gray-400">current value of open positions</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Deposited</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ eur(summary.deposited_eur) }}</p>
                        <p class="mt-1 text-xs text-gray-400">cash transferred in from bank</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm col-span-2 sm:col-span-1">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Net gain</p>
                        <p class="mt-1 text-2xl font-semibold" :class="gainClass(summary.net_gain_eur)">
                            {{ eur(summary.net_gain_eur) }}
                            <span v-if="summary.net_gain_pct != null" class="text-sm font-normal">
                                ({{ pct(summary.net_gain_pct) }})
                            </span>
                        </p>
                        <p class="mt-1 text-xs text-gray-400">market value − deposited</p>
                    </div>
                </div>

                <!-- Breakdown -->
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Unrealised</p>
                        <p class="mt-1 text-lg font-semibold" :class="gainClass(summary.total_unrealized_gain_eur)">
                            {{ eur(summary.total_unrealized_gain_eur) }}
                            <span v-if="summary.total_unrealized_gain_pct != null" class="text-xs font-normal">
                                ({{ pct(summary.total_unrealized_gain_pct) }})
                            </span>
                        </p>
                        <p class="mt-1 text-xs text-gray-400">price change on open positions</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Realised</p>
                        <p class="mt-1 text-lg font-semibold" :class="gainClass(summary.total_realized_gain_eur)">
                            {{ eur(summary.total_realized_gain_eur) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">profit/loss from closed trades</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Dividends</p>
                        <p class="mt-1 text-lg font-semibold" :class="gainClass(summary.total_dividend_eur)">
                            {{ eur(summary.total_dividend_eur) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">net dividend income received</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Fees (net of promo)</p>
                        <p class="mt-1 text-lg font-semibold" :class="gainClass(summary.total_fees_eur)">
                            {{ eur(summary.total_fees_eur) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">broker costs after DEGIRO promo</p>
                    </div>
                </div>

                <!-- Positions table -->
                <div v-if="positions.length === 0" class="rounded-lg bg-white p-10 text-center shadow-sm">
                    <p class="text-gray-700 font-medium">No positions yet.</p>
                    <p class="mt-1 text-sm text-gray-500">Import your DEGIRO transaction and account CSVs to get started.</p>
                    <Link :href="route('accounts.index')"
                          class="mt-4 inline-block rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Go to Accounts
                    </Link>
                </div>

                <div v-else class="overflow-x-auto rounded-lg bg-white shadow-sm">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-gray-50 text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Instrument</th>
                                <th class="px-4 py-3 text-right">Qty</th>
                                <th class="px-4 py-3 text-right">Avg cost</th>
                                <th class="px-4 py-3 text-right">Latest price</th>
                                <th class="px-4 py-3 text-right">Value (EUR)</th>
                                <th class="px-4 py-3 text-right">Unrealised</th>
                                <th class="px-4 py-3 text-right">Dividends</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="p in positions" :key="p.instrument_id" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ p.name }}</div>
                                    <div class="text-xs text-gray-400">{{ p.yahoo_symbol ?? p.isin }}</div>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ p.quantity }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600">
                                    {{ p.avg_cost_per_share != null ? fmt(p.avg_cost_per_share, p.price_currency) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600">
                                    <template v-if="p.latest_price != null">
                                        {{ fmt(p.latest_price, p.latest_price_currency) }}
                                        <div class="text-xs text-gray-400">{{ p.latest_price_date }}</div>
                                    </template>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-900">
                                    {{ p.current_value_eur != null ? eur(p.current_value_eur) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <template v-if="p.unrealized_gain_eur != null">
                                        <span :class="gainClass(p.unrealized_gain_eur)">{{ eur(p.unrealized_gain_eur) }}</span>
                                        <div class="text-xs" :class="gainClass(p.unrealized_gain_pct)">
                                            {{ pct(p.unrealized_gain_pct) }}
                                        </div>
                                    </template>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <span v-if="p.dividend_eur" :class="gainClass(p.dividend_eur)">
                                        {{ eur(p.dividend_eur) }}
                                    </span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineProps({
    positions: { type: Array, required: true },
    summary:   { type: Object, required: true },
})

const eurFmt = new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR', maximumFractionDigits: 2 })
const pctFmt = new Intl.NumberFormat('nl-NL', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 2 })

function eur(v)  { return v != null ? eurFmt.format(v) : '—' }
function pct(v)  { return v != null ? pctFmt.format(v) : '—' }
function fmt(price, currency) {
    if (price == null) return '—'
    try {
        return new Intl.NumberFormat('nl-NL', {
            style: 'currency',
            currency: currency ?? 'EUR',
            maximumFractionDigits: 4,
        }).format(price)
    } catch {
        return price
    }
}
function gainClass(v) {
    if (v == null) return 'text-gray-500'
    return v >= 0 ? 'text-green-600' : 'text-red-600'
}
</script>
