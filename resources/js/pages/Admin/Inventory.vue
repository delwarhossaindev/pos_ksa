<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PosShell from '@/layouts/PosShell.vue';
import { money } from '@/lib/format';

defineOptions({ layout: PosShell });
const props = defineProps({ categories: { type: Array, required: true } });

const busy = ref(null);
const soldOut = computed(() => props.categories.reduce((n, c) => n + c.products.filter((p) => !p.is_active).length, 0));

function toggle(product) {
    busy.value = product.id;
    router.patch(route('admin.products.toggle', product.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { busy.value = null; },
    });
}
</script>

<template>
    <Head :title="__('Inventory')" />
    <div class="page-head">
        <h1>{{ __('Inventory') }}</h1>
        <span class="badge" :class="soldOut ? 'badge-danger' : 'badge-success'">{{ __(':count sold out', { count: soldOut }) }}</span>
    </div>
    <p class="muted" style="margin-top:-.5rem">{{ __('Tap an item to mark it sold out. Sold-out items are hidden from the POS until you switch them back.') }}</p>

    <div v-for="c in categories" :key="c.id" class="card inv-cat">
        <h2>{{ c.name }}</h2>
        <div class="inv-grid">
            <button
                v-for="p in c.products"
                :key="p.id"
                type="button"
                class="inv-item"
                :class="{ off: !p.is_active }"
                :disabled="busy === p.id"
                @click="toggle(p)"
            >
                <span class="inv-name">{{ p.name }}</span>
                <span class="inv-price num">{{ money(p.price) }}</span>
                <span class="inv-state">{{ p.is_active ? __('Available') : __('Sold out') }}</span>
            </button>
            <div v-if="!c.products.length" class="muted">{{ __('No records yet.') }}</div>
        </div>
    </div>
</template>
