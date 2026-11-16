<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Setting;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * ZATCA QR code for a receipt.
 *
 * Phase 1: Base64( TLV(1 seller) TLV(2 vat no) TLV(3 timestamp) TLV(4 total) TLV(5 vat) ).
 * Phase 2: once the order has a signed e-invoice, its QR (tags 1–9, with hash, signature and key) is used.
 */
class ZatcaQr
{
    public static function payload(Order $order): string
    {
        $signed = $order->einvoice?->qr_code;
        if ($signed) {
            return $signed;
        }

        $fields = [
            1 => (string) Setting::get('store_name_ar') ?: (string) Setting::get('store_name_en'),
            2 => (string) Setting::get('vat_number'),
            3 => $order->created_at->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            4 => number_format((float) $order->total, 2, '.', ''),
            5 => number_format((float) $order->vat_amount, 2, '.', ''),
        ];

        $tlv = '';
        foreach ($fields as $tag => $value) {
            $tlv .= chr($tag).chr(strlen($value)).$value; // length in bytes (UTF-8)
        }

        return base64_encode($tlv);
    }

    /** Returns an SVG data URI ready for an <img src>. */
    public static function dataUri(Order $order): string
    {
        return self::svgDataUri(self::payload($order));
    }

    /** SVG data URI for any QR payload (e.g. a signed e-invoice QR). */
    public static function svgDataUri(string $payload): string
    {
        $options = new QROptions([
            'outputBase64' => true,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 1,
        ]);

        return (new QRCode($options))->render($payload);
    }
}
