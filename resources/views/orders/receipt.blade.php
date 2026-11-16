@php
    $currency = $settings['currency_en'] ?? 'SAR';
    $m = fn ($v) => number_format((float) $v, 2);
    $types = ['takeaway' => ['سفري', 'Takeaway'], 'dine_in' => ['محلي', 'Dine-in'], 'delivery' => ['توصيل', 'Delivery']];
    $cards = ['mada' => 'mada', 'visa' => 'Visa', 'mastercard' => 'MasterCard'];
    $vatRate = rtrim(rtrim(number_format((float) $order->vat_rate, 2), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $order->invoice_no }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; font-family: Tahoma, "Segoe UI", Arial, sans-serif; color: #000; }
        .receipt { width: 80mm; margin: 12px auto; background: #fff; padding: 4mm 4mm 6mm; font-size: 12px; line-height: 1.35; }
        .c { text-align: center; }
        .b { font-weight: 700; }
        .lg { font-size: 15px; }
        .xl { font-size: 22px; }
        .num { direction: ltr; unicode-bidi: isolate; font-variant-numeric: tabular-nums; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; gap: 6px; }
        .row .en { font-size: 10px; color: #333; }
        .row > span:last-child { white-space: nowrap; }
        .row.grand .en { display: block; }
        .words { font-size: 11px; font-weight: 700; margin-top: 3px; padding: 3px 4px; border: 1px dashed #000; text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        th { font-size: 10px; font-weight: 700; border-bottom: 1px solid #000; padding: 2px 0; text-align: right; vertical-align: bottom; }
        td { padding: 3px 0; vertical-align: top; }
        th.n, td.n { text-align: left; white-space: nowrap; padding-inline-start: 4px; }
        .item-en { font-size: 10px; color: #333; }
        .item-note { font-size: 10px; font-style: italic; }
        .grand { font-size: 15px; font-weight: 700; }
        .order-no { border: 2px solid #000; border-radius: 6px; padding: 4px; margin: 8px 0; }
        .qr img { width: 38mm; height: 38mm; image-rendering: pixelated; }
        .void { border: 3px solid #c00; color: #c00; font-size: 20px; font-weight: 700; padding: 4px; margin: 6px 0; }
        .toolbar { text-align: center; margin: 10px; }
        .toolbar button { font: inherit; font-size: 15px; padding: 8px 18px; cursor: pointer; }
        @media print {
            body { background: #fff; }
            .receipt { margin: 0; width: 100%; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button onclick="window.print()">🖨 طباعة / Print</button>
</div>

<div class="receipt">
    <div class="c">
        <div class="b lg">{{ $settings['store_name_ar'] }}</div>
        <div class="b">{{ $settings['store_name_en'] }}</div>
        @if ($settings['address_ar'])<div>{{ $settings['address_ar'] }}</div>@endif
        @if ($settings['address_en'])<div style="font-size:10px">{{ $settings['address_en'] }}</div>@endif
        @if ($settings['phone'])<div class="num">{{ $settings['phone'] }}</div>@endif
    </div>
    <hr>
    <div class="c b">فاتورة ضريبية مبسطة<br><span style="font-size:11px">Simplified Tax Invoice</span></div>
    @if ($settings['vat_number'])
        <div class="row" style="margin-top:4px"><span>الرقم الضريبي <span class="en">VAT No.</span></span><span class="num b">{{ $settings['vat_number'] }}</span></div>
    @endif
    @if ($settings['cr_number'])
        <div class="row"><span>السجل التجاري <span class="en">CR No.</span></span><span class="num">{{ $settings['cr_number'] }}</span></div>
    @endif
    <div class="row"><span>رقم الفاتورة <span class="en">Invoice</span></span><span class="num">{{ $order->invoice_no }}</span></div>
    <div class="row"><span>التاريخ <span class="en">Date</span></span><span class="num">{{ $order->created_at->format('d/m/Y h:i A') }}</span></div>
    <div class="row"><span>التاريخ الهجري <span class="en">Hijri</span></span><span>{{ \App\Support\Arabic::hijri($order->created_at, 'ar') }}</span></div>
    <div class="row"><span>نوع الطلب <span class="en">Type</span></span><span class="b">{{ $types[$order->order_type][0] ?? $order->order_type }} / {{ $types[$order->order_type][1] ?? '' }}</span></div>
    <div class="row"><span>الكاشير <span class="en">Cashier</span></span><span>{{ $order->user?->name }}</span></div>
    @if ($order->table_no)
        <div class="row"><span>رقم الطاولة <span class="en">Table</span></span><span class="num">{{ $order->table_no }}</span></div>
    @endif
    @if ($order->customer_name || $order->customer_phone)
        <div class="row"><span>العميل <span class="en">Customer</span></span><span>{{ $order->customer_name }} <span class="num">{{ $order->customer_phone }}</span></span></div>
    @endif

    @if ($order->isVoid())
        <div class="c void">ملغاة / VOID</div>
    @endif

    <hr>
    <table>
        <thead>
        <tr>
            <th>الصنف<br>Item</th>
            <th class="n">الكمية<br>Qty</th>
            <th class="n">السعر<br>Price</th>
            <th class="n">الإجمالي<br>Total</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td>
                    {{ $item->name_ar }}
                    <div class="item-en">{{ $item->name_en }}</div>
                    @if ($item->note)<div class="item-note">* {{ $item->note }}</div>@endif
                    @if ((float) $item->discount_amount > 0)
                        <div class="item-note">خصم / Disc. <span class="num">{{ rtrim(rtrim(number_format((float) $item->discount_percent, 2), '0'), '.') }}% (-{{ $m($item->discount_amount) }})</span></div>
                    @endif
                </td>
                <td class="n num">{{ $item->quantity }}</td>
                <td class="n num">{{ $m($item->unit_price) }}</td>
                <td class="n num">{{ $m((float) $item->unit_price * $item->quantity) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <hr>

    @if ((float) $order->discount > 0)
        <div class="row"><span>المجموع <span class="en">Subtotal</span></span><span class="num">{{ $m($order->subtotal) }} {{ $currency }}</span></div>
        <div class="row"><span>الخصم <span class="en">Discount</span></span><span class="num">-{{ $m($order->discount) }} {{ $currency }}</span></div>
    @endif
    <div class="row"><span>الإجمالي بدون الضريبة <span class="en">Total excl. VAT</span></span><span class="num">{{ $m($order->net_amount) }} {{ $currency }}</span></div>
    <div class="row"><span>ضريبة القيمة المضافة <span class="num">{{ $vatRate }}%</span> <span class="en">VAT</span></span><span class="num">{{ $m($order->vat_amount) }} {{ $currency }}</span></div>
    <div class="row grand"><span>الإجمالي شامل الضريبة <span class="en">Total incl. VAT</span></span><span class="num">{{ $m($order->total) }} {{ $currency }}</span></div>
    <div class="words">{{ \App\Support\Tafqeet::sar((float) $order->total) }}</div>
    <hr>
    <div class="row">
        <span>طريقة الدفع <span class="en">Payment</span></span>
        <span>
            @if ($order->payment_method === 'card')
                شبكة / Card @if ($order->card_type)({{ $cards[$order->card_type] ?? $order->card_type }})@endif
            @else
                نقدي / Cash
            @endif
        </span>
    </div>
    @if ($order->payment_method === 'cash')
        <div class="row"><span>المدفوع <span class="en">Paid</span></span><span class="num">{{ $m($order->paid_amount) }} {{ $currency }}</span></div>
        <div class="row"><span>المتبقي <span class="en">Change</span></span><span class="num">{{ $m($order->change_amount) }} {{ $currency }}</span></div>
    @endif
    @if ($order->note)
        <div style="margin-top:4px">ملاحظة / Note: {{ $order->note }}</div>
    @endif

    <div class="c order-no">
        <div>رقم الطلب / Order No.</div>
        <div class="xl b num"># {{ $order->order_no }}</div>
    </div>

    <div class="c qr"><img src="{{ $qr }}" alt="QR"></div>
    <div class="c" style="font-size:10px">QR الفاتورة الإلكترونية / E-Invoice QR</div>
    <hr>
    <div class="c">*** {{ $settings['receipt_footer_ar'] }} ***</div>
    <div class="c" style="font-size:11px">{{ $settings['receipt_footer_en'] }}</div>
    <div class="c num" style="font-size:10px;margin-top:4px">طبعت / Printed: {{ now()->format('d/m/Y h:i:s A') }}</div>
</div>

@if ($autoPrint)
    <script>window.addEventListener('load', () => window.print());</script>
@endif
</body>
</html>
