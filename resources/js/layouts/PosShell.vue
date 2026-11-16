<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import PosIcon from '@/components/PosIcon.vue';
import { useI18n } from '@/lib/i18n';
import { money } from '@/lib/format';
import { confirmDialog } from '@/lib/dialog';
import { printUrl } from '@/lib/printer';
import { shell } from '@/lib/shell';
import { showToast, toastState } from '@/lib/toast';

/**
 * The till frame from the reference screen — header + bottom bar stay put while the
 * POS and the bottom-bar screens (Reports, Settings, Item List, Customers, Orders,
 * Inventory) swap in the middle without a page reload.
 */
const page = usePage();
const { t } = useI18n();
const user = computed(() => page.props.auth.user);
const store = computed(() => page.props.store);
const onPos = computed(() => page.component === 'Pos/Index');
const TYPE_LABELS = { takeaway: 'Takeaway', dine_in: 'Dine In', delivery: 'Delivery' };

// Big header title: the POS sets it (order type); other screens show their name.
const SCREEN_TITLES = {
    'Orders/Index': 'Orders',
    'Orders/Show': 'Order',
    'Admin/Reports': 'Reports',
    'Admin/Settings': 'Settings',
    'Admin/Users/Index': 'Users',
    'Admin/Users/Form': 'Users',
    'Admin/Products/Index': 'Item List',
    'Admin/Products/Form': 'Item List',
    'Admin/Categories/Index': 'Categories',
    'Admin/Categories/Form': 'Categories',
    'Admin/Customers': 'Customers',
    'Admin/Inventory': 'Inventory',
    'Admin/Zatca': 'ZATCA',
    'Admin/Invoices/Index': 'E-Invoices',
    'Admin/Invoices/Create': 'E-Invoices',
    'Admin/Invoices/Show': 'E-Invoices',
};
const headerTitle = computed(() => (onPos.value ? shell.title : t(SCREEN_TITLES[page.component] || 'POS')));

// ---------- clock (store timezone, like the receipts) ----------
const clock = reactive({ date: '', time: '', hijri: '' });
const tzOption = (() => {
    try { new Intl.DateTimeFormat('en', { timeZone: store.value.timezone }); return { timeZone: store.value.timezone }; } catch (e) { return {}; }
})();
const dateFmt = new Intl.DateTimeFormat('en-US', { ...tzOption, day: 'numeric', month: 'numeric', year: 'numeric' });
const timeFmt = new Intl.DateTimeFormat('en-US', { ...tzOption, hour: 'numeric', minute: '2-digit', hour12: true });
function tick() {
    const now = new Date();
    const ar = page.props.locale === 'ar';
    clock.date = dateFmt.format(now);
    const time = timeFmt.format(now);
    clock.time = ar ? time.replace('AM', 'ص').replace('PM', 'م') : time;
    try {
        clock.hijri = new Intl.DateTimeFormat(ar ? 'ar-SA-u-ca-islamic-umalqura-nu-latn' : 'en-u-ca-islamic-umalqura', {
            ...tzOption, day: 'numeric', month: 'long', year: 'numeric',
        }).format(now);
    } catch (e) { clock.hijri = ''; }
}
let clockTimer;

// ---------- header pop-overs ----------
const menuOpen = ref(null); // 'notifications' | 'user'
const toggleMenu = (name) => { menuOpen.value = menuOpen.value === name ? null : name; };
const closeMenus = (e) => { if (!e.target.closest('.tp-pop-wrap')) menuOpen.value = null; };

onMounted(() => {
    tick();
    clockTimer = setInterval(tick, 15000);
    document.addEventListener('click', closeMenus);
});
onBeforeUnmount(() => {
    clearInterval(clockTimer);
    document.removeEventListener('click', closeMenus);
});
watch(() => page.props.locale, tick);
watch(() => page.url, () => { menuOpen.value = null; });

// ---------- header actions ----------
function goPos() {
    if (!onPos.value) router.visit(route('pos'));
}
function customerService() {
    if (shell.customerHandler) return shell.customerHandler();
    // From another screen: go to the POS and open the customer dialog there.
    shell.openCustomerOnArrival = true;
    router.visit(route('pos'));
}
function openDrawer() {
    printUrl(route('pos.drawer'));
    showToast(t('Cash drawer opened'));
}
function reprintLast() {
    const last = page.props.lastOrder;
    if (!last) return showToast(t('No receipt to print yet.'), true);
    printUrl(last.receipt_url);
}
function toggleFullscreen() {
    if (document.fullscreenElement) document.exitFullscreen?.();
    else document.documentElement.requestFullscreen?.().catch(() => {});
}
function switchLanguage() {
    menuOpen.value = null;
    router.get(route('locale', page.props.locale === 'ar' ? 'en' : 'ar'), {}, { preserveState: true, preserveScroll: true, replace: true });
}
async function closeSession() {
    menuOpen.value = null;
    const ok = await confirmDialog({
        title: t('Logout'),
        message: t('Close the POS and sign out?'),
        detail: user.value.name,
        confirmText: t('Logout'),
        icon: 'warning',
    });
    if (ok) router.post(route('logout'));
}

// ---------- bottom bar: exactly the six buttons of the reference ----------
const FOOTER = [
    { label: 'Reports', route: 'admin.reports', active: ['admin.reports'], admin: true },
    { label: 'Settings', route: 'admin.settings.edit', active: ['admin.settings.*', 'admin.users.*', 'admin.zatca.*', 'admin.invoices.*'], admin: true },
    { label: 'Item List', route: 'admin.products.index', active: ['admin.products.*', 'admin.categories.*'], admin: true },
    { label: 'Customers', route: 'admin.customers.index', active: ['admin.customers.*'], admin: true },
    { label: 'Orders', route: 'orders.index', active: ['orders.*'], admin: false },
    { label: 'Inventory', route: 'admin.inventory.index', active: ['admin.inventory.*'], admin: true },
];
// route().current() reads window.location → depend on page.url so it re-evaluates after each visit.
const isActive = (patterns) => page.url && patterns.some((p) => route().current(p));
const footerScroll = ref(null);
const scrollFooter = (dir) => {
    const rtl = page.props.dir === 'rtl' ? -1 : 1;
    footerScroll.value?.scrollBy({ left: dir * rtl * 320, behavior: 'smooth' });
};

// Flash + general errors for the back-office screens.
const flash = ref(null);
const flashError = ref(null); // e.g. a ZATCA API failure — stays until the next visit
let flashTimer;
watch(() => page.props, (props) => {
    flash.value = props.flash?.status || null;
    flashError.value = props.flash?.error || null;
    clearTimeout(flashTimer);
    if (flash.value) flashTimer = setTimeout(() => { flash.value = null; }, 4000);
}, { immediate: true });
const generalError = computed(() => page.props.errors?.user || page.props.errors?.items || null);
</script>

<template>
    <div class="tp">
        <!-- ===== Header ===== -->
        <header class="tp-header">
            <button type="button" class="tp-title" :class="{ link: !onPos }" :title="onPos ? '' : __('POS')" @click="goPos">
                {{ headerTitle }}
            </button>

            <button type="button" class="tp-hbtn" @click="customerService">
                <PosIcon name="headset" />
                <span class="tp-hbtn-text two">{{ __('Customer Service') }}</span>
            </button>
            <button type="button" class="tp-hbtn" @click="openDrawer">
                <PosIcon name="drawer" />
                <span class="tp-hbtn-text two">{{ __('Open Cash Drawer') }}</span>
            </button>

            <div class="tp-datetime">
                <span class="num">{{ clock.date }}</span>
                <span class="num">{{ clock.time }}</span>
            </div>
            <div class="tp-hbtn static" :title="__('Hijri date')">
                <PosIcon name="calendar" />
                <span class="tp-hbtn-text">{{ clock.hijri }}</span>
            </div>

            <div class="tp-spacer" />

            <button v-if="user.is_admin" type="button" class="tp-hicon" :title="__('Settings')" @click="router.visit(route('admin.settings.edit'))">
                <PosIcon name="tools" />
            </button>

            <div class="tp-pop-wrap">
                <button type="button" class="tp-hbtn" @click="toggleMenu('notifications')">
                    <span class="tp-bell"><PosIcon name="bell" /><span v-if="$page.props.todayCount" class="tp-count num">{{ $page.props.todayCount }}</span></span>
                    <span class="tp-hbtn-text one">{{ __('Notifications') }}</span>
                </button>
                <div v-if="menuOpen === 'notifications'" class="tp-pop">
                    <div class="tp-pop-head">{{ __('Today') }} · {{ __('Orders') }}: <span class="num">{{ $page.props.todayCount }}</span></div>
                    <button
                        v-for="o in $page.props.recentOrders"
                        :key="o.id"
                        type="button"
                        class="tp-pop-item"
                        @click="router.visit(route('orders.show', o.id))"
                    >
                        <strong class="num">#{{ o.order_no }}</strong>
                        <span>{{ __(TYPE_LABELS[o.order_type] || o.order_type) }}</span>
                        <span class="num">{{ o.time }}</span>
                        <span class="num" :class="{ 'tp-void': o.status === 'void' }">{{ money(o.total) }}</span>
                    </button>
                    <div v-if="!$page.props.recentOrders?.length" class="tp-pop-empty">{{ __('No orders for this day.') }}</div>
                </div>
            </div>

            <div class="tp-pop-wrap">
                <button type="button" class="tp-hbtn" @click="toggleMenu('user')">
                    <PosIcon name="user" />
                    <span class="tp-hbtn-text one">{{ user.name }}</span>
                </button>
                <div v-if="menuOpen === 'user'" class="tp-pop tp-pop-user">
                    <div class="tp-pop-head">{{ user.name }} · {{ user.is_admin ? __('Administrator') : __('Cashier') }}</div>
                    <button type="button" class="tp-pop-item" @click="switchLanguage">
                        <PosIcon name="globe" /> {{ $page.props.locale === 'ar' ? 'English' : 'العربية' }}
                    </button>
                    <button type="button" class="tp-pop-item" @click="closeSession">
                        <PosIcon name="close" /> {{ __('Logout') }}
                    </button>
                </div>
            </div>

            <button type="button" class="tp-hicon" :title="__('Full screen')" @click="toggleFullscreen"><PosIcon name="minus" /></button>
            <button type="button" class="tp-hprint" :title="__('Reprint last receipt')" @click="reprintLast"><PosIcon name="printer" /></button>
            <button type="button" class="tp-hicon" :title="__('Logout')" @click="closeSession"><PosIcon name="close" /></button>
        </header>

        <!-- ===== Middle: the POS itself, or a bottom-bar screen in a panel ===== -->
        <slot v-if="onPos" />
        <main v-else class="tp-page">
            <div class="tp-page-inner">
                <div v-if="flash" class="alert alert-success">{{ flash }}</div>
                <div v-if="flashError" class="alert alert-danger" style="white-space:pre-line;overflow-wrap:anywhere">{{ flashError }}</div>
                <div v-if="generalError" class="alert alert-danger">{{ generalError }}</div>
                <slot />
            </div>
        </main>

        <!-- ===== Bottom bar ===== -->
        <nav class="tp-footer">
            <button type="button" class="tp-footer-arrow" :aria-label="__('Previous')" @click="scrollFooter(-1)"><PosIcon name="chevron-left" /></button>
            <div ref="footerScroll" class="tp-footer-items">
                <!-- Empty cell on the POS (as in the reference); a way back to selling everywhere else -->
                <button v-if="!onPos" type="button" class="tp-footer-item tp-footer-home" @click="goPos">
                    <PosIcon name="cart" /> {{ __('POS') }}
                </button>
                <div v-else class="tp-footer-item tp-footer-blank" aria-hidden="true" />

                <template v-for="item in FOOTER" :key="item.route">
                    <button
                        v-if="item.admin && !user.is_admin"
                        type="button"
                        class="tp-footer-item"
                        disabled
                        :title="__('Administrator only')"
                    >
                        {{ __(item.label) }}
                    </button>
                    <button
                        v-else
                        type="button"
                        class="tp-footer-item"
                        :class="{ active: isActive(item.active) }"
                        @click="router.visit(route(item.route))"
                    >
                        {{ __(item.label) }}
                    </button>
                </template>
            </div>
            <button type="button" class="tp-footer-arrow" :aria-label="__('Next')" @click="scrollFooter(1)"><PosIcon name="chevron-right" /></button>
        </nav>

        <div v-if="toastState.visible" class="toast tp-toast" :class="{ error: toastState.error }">{{ toastState.message }}</div>
    </div>
</template>
