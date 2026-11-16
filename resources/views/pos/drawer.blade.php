<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>فتح الدرج / Cash drawer</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        body { margin: 0; font-family: Tahoma, "Segoe UI", Arial, sans-serif; color: #000; }
        .slip { width: 80mm; padding: 4mm; text-align: center; font-size: 12px; line-height: 1.4; }
        .b { font-weight: 700; font-size: 14px; }
        .num { direction: ltr; unicode-bidi: isolate; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
    </style>
</head>
<body>
<div class="slip">
    <div class="b">{{ $settings['store_name_ar'] }}</div>
    <hr>
    <div class="b">*** فتح الدرج — بدون بيع ***</div>
    <div>NO SALE — CASH DRAWER OPENED</div>
    <hr>
    <div>{{ $user->name }}</div>
    <div class="num">{{ now()->format('d/m/Y h:i:s A') }}</div>
</div>
</body>
</html>
