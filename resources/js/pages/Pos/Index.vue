<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import PosShell from '@/layouts/PosShell.vue';
import Modal from '@/components/Modal.vue';
import PosIcon from '@/components/PosIcon.vue';
import { useI18n } from '@/lib/i18n';
import { money, normalizeSearch, parseNum, round2, xsrfToken } from '@/lib/format';
import { confirmDialog, promptDialog } from '@/lib/dialog';
import { printHtml, printUrl } from '@/lib/printer';
import { shell, useCustomerHandler, useShellTitle } from '@/lib/shell';
import { showToast } from '@/lib/toast';

defineOptions({ layout: PosShell });

const props = defineProps({
    menu: { type: Array, required: true },
});

const page = usePage();
const { t } = useI18n();
const STORAGE_KEY = 'pos.cart.v2';
const ORDER_TYPES = ['dine_in', 'takeaway', 'delivery'];
const TYPE_LABELS = { takeaway: 'Takeaway', dine_in: 'Dine In', delivery: 'Delivery' };
const PAGE_SIZE = 20; // 5 columns × 4 rows, like the reference screen

const store = computed(() => page.props.store);
const user = computed(() => page.props.auth.user);
const lastOrder = computed(() => page.props.lastOrder);
const vatRate = computed(() => store.value.vat_rate);
const vatLabel = computed(() => `${+vatRate.value.toFixed(2)}%`);

// ---------- menu, categories, product pages ----------
const products = computed(() => {
    const map = new Map();
    props.menu.forEach((c) => c.products.forEach((p) => map.set(p.id, { ...p, key: normalizeSearch(p.name) })));
    return map;
});
const categoryId = ref(props.menu[0]?.id ?? null);
const search = ref('');
const pageIndex = ref(0);

const filteredProducts = computed(() => {
    // Arabic-aware: ة/ه, أ/إ/آ/ا, ى/ي and tashkeel are folded; every word must match.
    const words = normalizeSearch(search.value).split(' ').filter(Boolean);
    if (words.length) return [...products.value.values()].filter((p) => words.every((w) => p.key.includes(w)));
    return props.menu.find((c) => c.id === categoryId.value)?.products ?? [];
});
const pageCount = computed(() => Math.max(1, Math.ceil(filteredProducts.value.length / PAGE_SIZE)));
const pagedProducts = computed(() => filteredProducts.value.slice(pageIndex.value * PAGE_SIZE, (pageIndex.value + 1) * PAGE_SIZE));
watch([categoryId, search], () => { pageIndex.value = 0; });
watch(pageCount, (n) => { if (pageIndex.value >= n) pageIndex.value = n - 1; });

function pickCategory(id) {
    categoryId.value = id;
    search.value = '';
}

const catList = ref(null);
const scrollCats = (dir) => catList.value?.scrollBy({ top: dir * 210, behavior: 'smooth' });

// ---------- order state (persisted so a refresh never loses the cart) ----------
const order = reactive({
    type: 'takeaway',
    lines: [], // { key, id, qty, discount, note } — name & price always come from the current menu/locale
    customer: { name: '', phone: '', table: '', note: '' },
});
const selectedKey = ref(null);

try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
    if (saved) {
        order.lines = (saved.lines || []).filter((l) => products.value.has(l.id) && l.qty > 0);
        if (saved.customer) order.customer = { ...order.customer, ...saved.customer };
        if (ORDER_TYPES.includes(saved.type)) order.type = saved.type;
    }
} catch (e) { /* storage unavailable */ }

watch(order, () => {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(order)); } catch (e) { /* ignore */ }
}, { deep: true });

// Products can disappear (sold out) after a reload of props.
watch(products, (map) => { order.lines = order.lines.filter((l) => map.has(l.id)); });

// Header: order type as the big title, and "Customer Service" opens the customer dialog.
useShellTitle(() => t(TYPE_LABELS[order.type]));
const modal = ref(null); // 'pay' | 'customer'
useCustomerHandler(() => { modal.value = 'customer'; });
onMounted(() => {
    if (shell.openCustomerOnArrival) {
        shell.openCustomerOnArrival = false;
        modal.value = 'customer';
    }
});

const cartLines = computed(() => order.lines.map((l) => {
    const p = products.value.get(l.id);
    const gross = round2(p.price * l.qty);
    const discountAmount = round2(gross * (l.discount || 0) / 100);
    return { ...l, name: p.name, price: p.price, gross, discountAmount, total: round2(gross - discountAmount) };
}));
const itemCount = computed(() => order.lines.reduce((s, l) => s + l.qty, 0));
const selectedLine = computed(() => cartLines.value.find((l) => l.key === selectedKey.value) || null);

const totals = computed(() => {
    const items = round2(cartLines.value.reduce((s, l) => s + l.gross, 0));
    const discount = round2(cartLines.value.reduce((s, l) => s + l.discountAmount, 0));
    const total = round2(items - discount);
    const vat = round2(total * vatRate.value / (100 + vatRate.value));
    return { items, discount, total, vat, net: round2(total - vat) };
});

const linesBox = ref(null);
const scrollLines = (dir) => linesBox.value?.scrollBy({ top: dir * 160, behavior: 'smooth' });

async function addProduct(id) {
    let line = order.lines.find((l) => l.id === id && !l.note && !l.discount);
    if (line) line.qty += 1;
    else {
        line = { key: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`, id, qty: 1, discount: 0, note: '' };
        order.lines.push(line);
    }
    selectedKey.value = line.key;
    await nextTick();
    linesBox.value?.querySelector('.tp-line.selected')?.scrollIntoView({ block: 'nearest' });
}
function removeLine(key) {
    order.lines = order.lines.filter((l) => l.key !== key);
    if (selectedKey.value === key) selectedKey.value = order.lines.at(-1)?.key ?? null;
}
async function editNote() {
    const line = order.lines.find((l) => l.key === selectedKey.value);
    if (!line) return;
    const note = await promptDialog({
        title: t('Item note'),
        detail: products.value.get(line.id)?.name,
        value: line.note || '',
        placeholder: t('e.g. no garlic, extra sauce'),
        confirmText: t('Save'),
    });
    if (note !== null) line.note = note.trim().slice(0, 255);
}

// Qty / Unit Price / Discount (%) editor for the selected line. Accepts Arabic digits.
const editor = reactive({ qty: '', discount: '' });
watch(() => [selectedLine.value?.key, selectedLine.value?.qty, selectedLine.value?.discount], () => {
    editor.qty = selectedLine.value ? String(selectedLine.value.qty) : '';
    editor.discount = selectedLine.value ? String(selectedLine.value.discount || 0) : '';
}, { immediate: true });
function commitQty() {
    const line = order.lines.find((l) => l.key === selectedKey.value);
    const n = Math.floor(parseNum(editor.qty));
    if (line && n >= 1) line.qty = Math.min(n, 999);
}
function commitDiscount() {
    const line = order.lines.find((l) => l.key === selectedKey.value);
    const n = parseNum(editor.discount === '' ? '0' : editor.discount);
    if (line && !isNaN(n) && n >= 0 && n <= 100) line.discount = round2(n);
}
function stepQty(delta) {
    const line = order.lines.find((l) => l.key === selectedKey.value);
    if (!line) return;
    if (line.qty + delta <= 0) removeLine(line.key);
    else line.qty += delta;
}

function resetOrder() {
    order.lines = [];
    order.customer = { name: '', phone: '', table: '', note: '' };
    selectedKey.value = null;
}
async function cancelOrder() {
    if (!order.lines.length) return;
    const ok = await confirmDialog({
        title: t('Clear order'),
        message: t('Clear the current order?'),
        detail: t(':count items · :total', { count: itemCount.value, total: `${money(totals.value.total)} ${store.value.currency}` }),
        confirmText: t('Clear'),
        variant: 'danger',
        icon: 'warning',
    });
    if (ok) resetOrder();
}
const hasCustomer = computed(() => !!(order.customer.name || order.customer.phone || order.customer.table || order.customer.note));

// ---------- payment ----------
const pay = reactive({ method: 'cash', card: 'mada', paid: '', saving: false });
const paidInput = ref(null);
const quickCash = computed(() => {
    const total = totals.value.total;
    return [total, ...[5, 10, 20, 50, 100, 200, 500].filter((n) => n > total).slice(0, 3)];
});
const change = computed(() => {
    const paid = parseNum(pay.paid);
    return round2((isNaN(paid) ? totals.value.total : paid) - totals.value.total);
});
const canConfirm = computed(() => !pay.saving && (pay.method === 'card' || change.value >= 0));

async function openPay() {
    if (!order.lines.length) return;
    pay.method = 'cash';
    pay.paid = money(totals.value.total);
    modal.value = 'pay';
    await nextTick();
    paidInput.value?.select();
}

async function confirmPayment() {
    if (!canConfirm.value || !order.lines.length) return;
    const tt = totals.value;
    const payload = {
        order_type: order.type,
        items: order.lines.map((l) => ({ product_id: l.id, quantity: l.qty, discount_percent: l.discount || 0, note: l.note || null })),
        payment_method: pay.method,
        card_type: pay.method === 'card' ? pay.card : null,
        paid_amount: pay.method === 'cash' ? (parseNum(pay.paid) || tt.total) : tt.total,
        customer_name: order.customer.name || null,
        customer_phone: order.customer.phone || null,
        table_no: order.customer.table || null,
        note: order.customer.note || null,
    };

    pay.saving = true;
    try {
        const res = await fetch(route('orders.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify(payload),
        });
        const data = await res.json().catch(() => ({}));
        if (res.status === 401 || res.status === 419) {
            router.visit(route('login'));
            return;
        }
        if (!res.ok) {
            throw new Error((data.errors && Object.values(data.errors)[0][0]) || data.message || t('Could not save the order. Please try again.'));
        }
        modal.value = null;
        resetOrder();
        showToast(t('Order #:no saved', { no: data.order_no }));
        printUrl(data.receipt_url);
        // Refresh header notifications + "last order" (reprint / download) without touching the menu.
        router.reload({ only: ['recentOrders', 'todayCount', 'lastOrder'] });
    } catch (err) {
        showToast(err.message || t('Could not save the order. Please try again.'), true);
    } finally {
        pay.saving = false;
    }
}

function downloadLast() {
    if (!lastOrder.value) return showToast(t('No receipt to download yet.'), true);
    const a = document.createElement('a');
    a.href = `${lastOrder.value.receipt_url}?download=1`;
    document.body.append(a);
    a.click();
    a.remove();
}

/** Kitchen ticket for the current (unsaved) order — printed straight from the browser. */
function printKitchenTicket() {
    if (!order.lines.length) return;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const rows = cartLines.value.map((l) => `
        <tr><td class="q">${l.qty}×</td><td>${esc(l.name)}${l.note ? `<div class="n">* ${esc(l.note)}</div>` : ''}</td></tr>`).join('');
    const c = order.customer;
    const time = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: store.value.timezone });
    const html = `<!DOCTYPE html><html lang="${page.props.locale}" dir="${page.props.dir}"><head><meta charset="utf-8"><title>Kitchen</title>
        <style>@page{size:80mm auto;margin:0}body{margin:0;font-family:Tahoma,Arial,sans-serif}.t{width:80mm;padding:4mm;font-size:14px}
        h1{font-size:20px;text-align:center;margin:0 0 4px}.m{text-align:center;font-size:12px}hr{border:0;border-top:1px dashed #000;margin:6px 0}
        table{width:100%;border-collapse:collapse}td{padding:3px 0;vertical-align:top;font-size:15px;font-weight:700}.q{width:42px}.n{font-size:12px;font-weight:400}</style>
        </head><body><div class="t">
        <h1>${esc(t('Kitchen ticket'))}</h1>
        <div class="m"><b>${esc(t(TYPE_LABELS[order.type]))}</b>${c.table ? ` · ${esc(t('Table no.'))} ${esc(c.table)}` : ''}</div>
        <div class="m">${esc(time)} · ${esc(user.value.name)}</div><hr><table>${rows}</table>
        ${c.note ? `<hr><div>${esc(c.note)}</div>` : ''}<hr><div class="m">${itemCount.value} ${esc(t('items'))}</div></div></body></html>`;
    printHtml(html);
}
</script>

<template>
    <Head :title="__('POS')" />
    <div class="tp-body">
        <!-- Categories -->
        <aside class="tp-cats">
            <button type="button" class="tp-arrow" :aria-label="__('Previous')" @click="scrollCats(-1)"><PosIcon name="chevron-up" /></button>
            <div ref="catList" class="tp-cat-list">
                <button
                    v-for="c in menu"
                    :key="c.id"
                    type="button"
                    class="tp-cat"
                    :class="{ active: !search && c.id === categoryId }"
                    @click="pickCategory(c.id)"
                >
                    {{ c.name }}
                </button>
            </div>
            <button type="button" class="tp-arrow" :aria-label="__('Next')" @click="scrollCats(1)"><PosIcon name="chevron-down" /></button>
        </aside>

        <!-- Items -->
        <section class="tp-items">
            <div class="tp-grid">
                <button v-for="p in pagedProducts" :key="p.id" type="button" class="tp-tile" @click="addProduct(p.id)">
                    <span class="tp-tile-name">{{ p.name }}</span>
                    <span class="tp-tile-price num">{{ money(p.price) }}</span>
                </button>
                <div v-if="!pagedProducts.length" class="tp-empty">{{ __('No products found.') }}</div>
            </div>
            <div class="tp-pager">
                <button type="button" class="tp-pager-btn" :disabled="pageIndex === 0" :aria-label="__('Previous')" @click="pageIndex--">
                    <PosIcon name="chevron-left" />
                </button>
                <div class="tp-search">
                    <PosIcon name="search" />
                    <input v-model="search" type="search" :placeholder="__('Search products…')" autocomplete="off">
                    <span v-if="pageCount > 1" class="tp-page-no num">{{ pageIndex + 1 }} / {{ pageCount }}</span>
                </div>
                <button type="button" class="tp-pager-btn" :disabled="pageIndex >= pageCount - 1" :aria-label="__('Next')" @click="pageIndex++">
                    <PosIcon name="chevron-right" />
                </button>
            </div>
        </section>

        <!-- Order panel -->
        <aside class="tp-panel">
            <div class="tp-tabs" role="tablist">
                <button
                    v-for="type in ORDER_TYPES"
                    :key="type"
                    type="button"
                    role="tab"
                    :aria-selected="order.type === type"
                    :class="{ active: order.type === type }"
                    @click="order.type = type"
                >
                    {{ __(TYPE_LABELS[type]) }}
                </button>
            </div>

            <div ref="linesBox" class="tp-lines">
                <div
                    v-for="l in cartLines"
                    :key="l.key"
                    class="tp-line"
                    :class="{ selected: l.key === selectedKey }"
                    @click="selectedKey = l.key"
                >
                    <div class="tp-line-main">
                        <span class="tp-line-name">{{ l.name }}</span>
                        <span class="tp-line-total num">{{ money(l.total) }}</span>
                    </div>
                    <div class="tp-line-sub">
                        <span class="num">{{ l.qty }} × {{ money(l.price) }}</span>
                        <span v-if="l.discount" class="tp-disc num">-{{ +l.discount }}%</span>
                        <span v-if="l.note" class="tp-note">✎ {{ l.note }}</span>
                    </div>
                </div>
                <div v-if="!cartLines.length" class="tp-lines-empty">{{ __('No items yet. Tap a product to add it.') }}</div>
            </div>

            <div class="tp-totals">
                <div class="tp-totals-rows">
                    <div class="tp-row"><span>{{ __('Items Total') }}</span><span class="num">{{ money(totals.items) }}</span></div>
                    <div class="tp-row"><span>{{ __('Discount Total') }}</span><span class="num">{{ money(totals.discount) }}</span></div>
                    <div class="tp-row sep"><span>{{ __('Tax Total') }}</span><span class="num">{{ money(totals.vat) }}</span></div>
                    <div class="tp-row big"><span>{{ __('Total Before Tax') }}</span><span class="num">{{ money(totals.net) }}</span></div>
                    <div class="tp-row big"><span>{{ __('Tax') }}</span><span class="num">{{ vatLabel }}</span></div>
                    <div class="tp-row grand"><span>{{ __('Grand Total') }}</span><span class="num">{{ money(totals.total) }}</span></div>
                </div>
                <div class="tp-totals-arrows">
                    <button type="button" :aria-label="__('Previous')" @click="scrollLines(-1)"><PosIcon name="chevron-up" /></button>
                    <button type="button" :aria-label="__('Next')" @click="scrollLines(1)"><PosIcon name="chevron-down" /></button>
                </div>
            </div>

            <div class="tp-editor-wrap">
                <div class="tp-editor">
                    <div class="tp-editor-bar">
                        <span class="tp-editor-name">{{ selectedLine?.name || '' }}</span>
                        <template v-if="selectedLine">
                            <button type="button" :title="__('Note')" @click="editNote"><PosIcon name="pencil" /></button>
                            <button type="button" :title="__('Remove')" @click="removeLine(selectedLine.key)"><PosIcon name="trash" /></button>
                        </template>
                    </div>
                    <div class="tp-editor-head">
                        <span>{{ __('Qty') }}</span><span>{{ __('Unit Price') }}</span><span>{{ __('Discount (%)') }}</span>
                    </div>
                    <div class="tp-editor-cells">
                        <div class="tp-qty-cell">
                            <button type="button" :disabled="!selectedLine" @click="stepQty(-1)">−</button>
                            <input v-model="editor.qty" type="text" inputmode="numeric" class="num" :disabled="!selectedLine" @input="commitQty" @blur="editor.qty = selectedLine ? String(selectedLine.qty) : ''">
                            <button type="button" :disabled="!selectedLine" @click="stepQty(1)">+</button>
                        </div>
                        <input :value="selectedLine ? money(selectedLine.price) : ''" type="text" class="num" readonly tabindex="-1">
                        <input v-model="editor.discount" type="text" inputmode="decimal" class="num" :disabled="!selectedLine" @input="commitDiscount" @blur="editor.discount = selectedLine ? String(selectedLine.discount || 0) : ''">
                    </div>
                </div>
                <button type="button" class="tp-customer" :class="{ filled: hasCustomer }" :title="__('Customer')" @click="modal = 'customer'">
                    <PosIcon name="user-plus" />
                </button>
            </div>

            <div class="tp-actions">
                <button type="button" class="tp-btn blue" :disabled="!order.lines.length" @click="openPay">{{ __('Save') }}</button>
                <button type="button" class="tp-btn blue" :disabled="!order.lines.length" @click="cancelOrder">{{ __('Cancel') }}</button>
                <button type="button" class="tp-btn grey" :disabled="!order.lines.length" :title="__('Kitchen ticket')" @click="printKitchenTicket">{{ __('Print') }}</button>
                <button type="button" class="tp-btn grey" :disabled="!lastOrder" :title="__('Download last receipt')" @click="downloadLast">{{ __('Download') }}</button>
            </div>
        </aside>
    </div>

    <!-- Payment -->
    <Modal v-if="modal === 'pay'" :title="__('Payment')" @close="modal = null">
        <div class="pay-total"><span class="num">{{ money(totals.total) }}</span> {{ store.currency }}</div>

        <div class="seg">
            <button type="button" :class="{ active: pay.method === 'cash' }" @click="pay.method = 'cash'">{{ __('Cash') }}</button>
            <button type="button" :class="{ active: pay.method === 'card' }" @click="pay.method = 'card'">{{ __('Card') }}</button>
        </div>

        <div v-if="pay.method === 'card'">
            <label>{{ __('Card type') }}</label>
            <div class="seg">
                <button type="button" :class="{ active: pay.card === 'mada' }" @click="pay.card = 'mada'">{{ __('Mada') }}</button>
                <button type="button" :class="{ active: pay.card === 'visa' }" @click="pay.card = 'visa'">Visa</button>
                <button type="button" :class="{ active: pay.card === 'mastercard' }" @click="pay.card = 'mastercard'">MasterCard</button>
            </div>
        </div>

        <div v-else>
            <label for="paidInput">{{ __('Amount received') }}</label>
            <input id="paidInput" ref="paidInput" v-model="pay.paid" type="text" inputmode="decimal" autocomplete="off" class="input num" style="font-size:1.3rem" @keydown.enter="confirmPayment">
            <div class="quick-cash">
                <button v-for="(v, i) in quickCash" :key="i" type="button" @click="pay.paid = money(v)">{{ i === 0 ? __('Exact') : money(v) }}</button>
            </div>
            <div class="change-line" :class="{ bad: change < 0 }"><span>{{ __('Change') }}</span><span class="num">{{ money(change) }}</span></div>
        </div>

        <template #footer>
            <button type="button" class="btn" @click="modal = null">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-success btn-lg" :disabled="!canConfirm" @click="confirmPayment">{{ __('Confirm & Print') }}</button>
        </template>
    </Modal>

    <!-- Customer / note -->
    <Modal v-if="modal === 'customer'" :title="__('Customer')" @close="modal = null">
        <div class="grid-2">
            <div class="field"><label>{{ __('Customer name') }}</label><input v-model="order.customer.name" class="input" maxlength="100"></div>
            <div class="field"><label>{{ __('Phone') }}</label><input v-model="order.customer.phone" class="input num" maxlength="30" inputmode="tel"></div>
        </div>
        <div class="field"><label>{{ __('Table no.') }}</label><input v-model="order.customer.table" class="input" maxlength="20"></div>
        <div class="field"><label>{{ __('Order note') }}</label><textarea v-model="order.customer.note" class="input" rows="2" maxlength="500" /></div>
        <template #footer>
            <button type="button" class="btn btn-primary" @click="modal = null">{{ __('Done') }}</button>
        </template>
    </Modal>
</template>
