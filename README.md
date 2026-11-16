# Restaurant POS (Laravel) — English / العربية

Touch-friendly restaurant point of sale built from the reference photos in [`pos/`](pos/).

It is a **single-page application**: Laravel + **Inertia.js v3 + Vue 3**, with Laravel routes in JS via Ziggy. After login, moving between screens, switching language and taking payments never reload the page. Only the printable receipt is a separate Blade page, loaded into a hidden frame for printing.

## Features
- **Two languages**: English (LTR) and Arabic (RTL), switchable from any screen. Products and categories have Arabic and English names.
- **POS screen**: category list, product grid, search, cart with qty, item notes, discount (amount or %), customer/table/note, and Takeaway / Dine-in / Delivery.
- **Payment**: Cash (amount received, quick-cash buttons, change) or Card (mada / Visa / MasterCard).
- **Receipt**: 80 mm *Simplified Tax Invoice* with Arabic and English labels. It shows VAT extracted from VAT-inclusive prices, a daily order number, and a **ZATCA QR code** (TLV: seller, VAT no., timestamp, total, VAT). It prints automatically after payment.
- **Orders**: daily list, search, view, reprint, and void (admin only).
- **Admin**: sales report (totals, VAT, by payment / order type / cashier, top products), categories, products, users (admin / cashier), and store settings.
- **Seed data**: the full menu from the photos (8 categories, 155 items) and the store details from the sample receipt.

### POS screen (laid out like the reference till)
- **Header:** order type title, Customer Service (customer details), Open Cash Drawer (prints a "no sale" slip; set the printer driver to open the drawer when it prints), date, time, Hijri date, settings, Notifications (today's orders with a count badge), user menu (language and logout), full screen, reprint last receipt, logout.
- **Body:** categories with ▲▼, a 5×4 item grid with ‹ › pages and Arabic-aware search, and the order panel. The panel has Dine In / Takeaway / Delivery tabs, the order lines, Items Total / Discount Total / Tax Total / Total Before Tax / Tax / Grand Total, and a **Qty / Unit Price / Discount (%)** editor for the selected line (per-item discount is stored on the order). Buttons: **Save** (pay and print), **Cancel** (clear), **Print** (kitchen ticket), **Download** (last receipt as a file).
- **Bottom bar:** exactly as in the reference: Reports · Settings · Item List · Customers · Orders · Inventory.
  - These screens open **inside the same frame**: the header and bottom bar stay put and the page doesn't reload.
  - Off the POS, the empty first cell becomes a **POS** button to go back to selling.
  - Categories is a tab inside Item List, and Users is a tab inside Settings.
  - A cashier sees the admin buttons greyed out.
- **Categories:** besides the printed menu, **Ramadan Items (رمضانيات)** and **Add-ons (إضافات)**. Their prices are placeholders, so edit them in Item List. Use Inventory to hide Ramadan Items outside Ramadan.
- **Customers** (Admin): built automatically from the phone numbers entered on orders.
- **Inventory** (Admin): tap an item to mark it sold out (hidden from the POS) or available again.
- Code: `resources/js/pages/Pos/Index.vue`, `resources/css/pos-screen.css`.

### Arabic-first features
- **Arabic by default (RTL)**, with an instant switch to English.
- **Cairo font**, bundled locally so it works offline.
- **Green and gold theme with an Islamic 8-point star (khatam) pattern** on the header, the login and error screens, and faintly behind the content.
- **Hijri date (Umm al-Qura)** next to the Gregorian date: in the POS header, on the receipt and on order details.
- **Amount in Arabic words (تفقيط)** on the receipt and order details, e.g. "فقط اثنان وعشرون ريالاً لا غير".
- **Arabic-Indic digits accepted everywhere**: ٢٠ or ١٥٫٥ in amounts and prices, ٠٥٥… in phone numbers. Converted by `ConvertArabicDigits` middleware and `parseNum()` in JS.
- **Arabic-aware search**: أ/إ/آ = ا, ة = ه, ى = ي, and diacritics are ignored. Applies to the POS search and the admin product search (`products.search_text`).
- **Translated error pages** (403 / 404 / 429 / 500 / 503) and a friendly message when a session expires (419).
- Code: `app/Support/Arabic.php`, `app/Support/Tafqeet.php`, `resources/js/lib/format.js`, `resources/css/pattern-*.svg`.

## Setup
```bash
composer install
npm install
npm run build            # builds the Vue SPA into public/build (re-run after changing resources/js or resources/css)
cp .env.example .env && php artisan key:generate   # skip if .env exists
php artisan migrate --seed
php artisan serve        # http://127.0.0.1:8000
```
For development with hot reload, run `npm run dev` alongside `php artisan serve`.
On WAMP you can also open `http://localhost/pos_software/public`.

### Using MySQL instead of SQLite
Edit `.env`:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos_software
DB_USERNAME=root
DB_PASSWORD=
```
Create the `pos_software` database (utf8mb4), then run `php artisan migrate:fresh --seed`.

## Default logins
| Role    | Email              | Password  |
|---------|--------------------|-----------|
| Admin   | admin@pos.test     | password  |
| Cashier | cashier@pos.test   | password  |

**Change these passwords** in Admin → Users before going live.

With `DEMO_LOGINS=true` in `.env`, these accounts appear as one-click buttons under the login form. It is on in the local `.env`. Set **`DEMO_LOGINS=false`** on a real till: when it's off, no credentials are sent to the browser. Accounts that are deactivated are never shown.

## Notes
- Prices are entered **including VAT**. The VAT rate (default 15%) is set in Admin → Settings.
- Default language is Arabic (`APP_LOCALE=ar`). Timezone is `Asia/Riyadh` (`APP_TIMEZONE`).
- Translations: `lang/ar.json` (UI, shared with Vue as the `translations` prop and used via `__()` / `useI18n()`) and `lang/ar/validation.php`.
- Front end: `resources/js/pages/**` (one Vue page per screen), `resources/js/layouts/AppLayout.vue` (persistent back-office layout), `resources/js/components/`, `resources/css/app.css`.
- Back end: `app/Http/Middleware/HandleInertiaRequests.php` (shared props), `app/Support/OrderService.php` (totals and numbering), `app/Support/ZatcaQr.php`, `resources/views/orders/receipt.blade.php`.
- Tests: `php artisan test`
