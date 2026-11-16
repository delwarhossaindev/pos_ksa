/** Tabs under the Settings bottom-bar button (none of these has a bottom-bar button of its own). */
export const SETTINGS_TABS = [
    { label: 'Store', route: 'admin.settings.edit', active: ['admin.settings.*'] },
    { label: 'Users', route: 'admin.users.index', active: ['admin.users.*'] },
    { label: 'ZATCA', route: 'admin.zatca.edit', active: ['admin.zatca.*'] },
    { label: 'E-Invoices', route: 'admin.invoices.index', active: ['admin.invoices.*'] },
];
