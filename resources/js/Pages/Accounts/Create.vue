<template>
    <Head title="Add account" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Add account</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg bg-white p-6 shadow-sm">
                    <form @submit.prevent="submit" class="space-y-5">

                        <div>
                            <InputLabel for="name" value="Account name" />
                            <TextInput id="name" v-model="form.name" type="text"
                                       class="mt-1 block w-full" placeholder="e.g. DEGIRO main"
                                       autofocus required />
                            <InputError :message="form.errors.name" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="broker" value="Broker" />
                            <TextInput id="broker" v-model="form.broker" type="text"
                                       class="mt-1 block w-full" placeholder="e.g. DEGIRO"
                                       required />
                            <InputError :message="form.errors.broker" class="mt-1" />
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <Link :href="route('accounts.index')"
                                  class="text-sm text-gray-600 hover:text-gray-800">
                                Cancel
                            </Link>
                            <PrimaryButton type="submit" :disabled="form.processing">
                                Create &amp; go to import
                            </PrimaryButton>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const form = useForm({
    name:   '',
    broker: 'DEGIRO',
})

function submit() {
    form.post(route('accounts.store'))
}
</script>
