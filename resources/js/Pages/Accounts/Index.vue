<template>
    <Head title="Accounts" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Accounts</h2>
                <Link :href="route('accounts.create')"
                      class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Add account
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">

                <div v-if="flash.success"
                     class="mb-4 rounded-lg border border-green-300 bg-green-50 p-4 text-sm text-green-800">
                    {{ flash.success }}
                </div>

                <div v-if="accounts.length === 0"
                     class="rounded-lg bg-white p-10 text-center shadow-sm">
                    <p class="text-gray-500">No accounts yet.</p>
                    <Link :href="route('accounts.create')"
                          class="mt-4 inline-block rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Add your first account
                    </Link>
                </div>

                <div v-else class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-gray-50 text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-3">Name</th>
                                <th class="px-6 py-3">Broker</th>
                                <th class="px-6 py-3">Last import</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="account in accounts" :key="account.id"
                                class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium text-gray-900">{{ account.name }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ account.broker }}</td>
                                <td class="px-6 py-4 text-gray-500">
                                    {{ account.import_watermark ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link :href="route('accounts.import.show', account.id)"
                                          class="mr-4 text-indigo-600 hover:text-indigo-800">
                                        Import
                                    </Link>
                                    <button @click="confirmDelete(account)"
                                            class="text-red-500 hover:text-red-700">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Delete confirmation modal -->
        <Modal :show="!!deleting" @close="deleting = null">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Delete "{{ deleting?.name }}"?</h3>
                <p class="mt-2 text-sm text-gray-600">
                    This will permanently delete the account and all associated transactions and cash movements.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="deleting = null">Cancel</SecondaryButton>
                    <DangerButton @click="doDelete">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/Components/Modal.vue'
import DangerButton from '@/Components/DangerButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'

defineProps({
    accounts: { type: Array, required: true },
})

const flash = usePage().props.flash ?? {}

const deleting = ref(null)

function confirmDelete(account) {
    deleting.value = account
}

function doDelete() {
    router.delete(route('accounts.destroy', deleting.value.id), {
        onFinish: () => { deleting.value = null },
    })
}
</script>
