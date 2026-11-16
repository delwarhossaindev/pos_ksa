<script setup>
// Line icons used on the POS screen (stroke = currentColor).
defineProps({ name: { type: String, required: true } });

const PATHS = {
    headset: 'M4 14v-2a8 8 0 0 1 16 0v2M4 14a2 2 0 0 1 2-2h1v6H6a2 2 0 0 1-2-2v-2Zm16 0a2 2 0 0 0-2-2h-1v6h1a2 2 0 0 0 2-2v-2Zm-3 4c0 1.7-1.8 3-5 3',
    drawer: 'M6 3h12v8H6zM3 11h18v9H3zM9 15h6M10 6h4M12 6v2',
    calendar: 'M4 6h16v14H4zM4 10h16M8 3v4m8-4v4M8 14h2m4 0h2m-8 3h2',
    tools: 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.5-.5-.5-2.5 2.5-2.5ZM3 6l3 3m-1-6 4 4',
    bell: 'M6 16V11a6 6 0 1 1 12 0v5l2 2H4l2-2Zm4 4a2 2 0 0 0 4 0',
    user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0',
    'user-plus': 'M10 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 12.5-4.3M19 14v6m-3-3h6',
    minus: 'M5 12h14',
    expand: 'M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5',
    printer: 'M7 9V3h10v6M7 17H4v-7h16v7h-3M7 14h10v7H7z',
    close: 'M6 6l12 12M18 6 6 18',
    'chevron-up': 'm6 15 6-6 6 6',
    'chevron-down': 'm6 9 6 6 6-6',
    'chevron-left': 'm15 6-6 6 6 6',
    'chevron-right': 'm9 6 6 6-6 6',
    pencil: 'M4 20h4L19 9l-4-4L4 16v4Zm9-13 4 4',
    trash: 'M9 3h6m-9 4h12m-1 0-.7 11.2A2 2 0 0 1 14.3 20H9.7a2 2 0 0 1-2-1.8L7 7m3 4v5m4-5v5',
    search: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Zm9 2-4.3-4.3',
    globe: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm-9-9h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3Z',
    download: 'M12 4v11m0 0-4-4m4 4 4-4M5 20h14',
    cart: 'M3 4h2l2.4 11.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.2L21 8H6.2M9.5 20.5h.01M17 20.5h.01',
};
</script>

<template>
    <svg class="picon" viewBox="0 0 24 24" aria-hidden="true">
        <path :d="PATHS[name]" />
    </svg>
</template>
