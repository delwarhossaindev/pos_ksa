<?php

namespace App\Services\Zatca;

use RuntimeException;

/**
 * Wraps the X.509 certificate (CSID) returned by ZATCA.
 */
class Certificate
{
    private array $parsed;

    /**
     * @param  string  $base64  Base64 encoded DER certificate (decoded binarySecurityToken)
     */
    public function __construct(private string $base64)
    {
        $this->base64 = preg_replace('/\s+/', '', $base64);
        $parsed = openssl_x509_parse($this->pem());

        if ($parsed === false) {
            throw new RuntimeException('Unable to parse the ZATCA certificate.');
        }

        $this->parsed = $parsed;
    }

    public function base64(): string
    {
        return $this->base64;
    }

    public function pem(): string
    {
        return "-----BEGIN CERTIFICATE-----\n".chunk_split($this->base64, 64, "\n").'-----END CERTIFICATE-----';
    }

    /**
     * Certificate digest as ZATCA expects it: base64 of the hex SHA-256 of the base64 certificate text.
     */
    public function hash(): string
    {
        return base64_encode(hash('sha256', $this->base64));
    }

    /**
     * Issuer name in "CN=..., DC=..." order.
     */
    public function issuerName(): string
    {
        $parts = [];

        foreach ($this->parsed['issuer'] as $key => $value) {
            foreach ((array) $value as $item) {
                $parts[] = "$key=$item";
            }
        }

        return implode(', ', array_reverse($parts));
    }

    /**
     * Serial number in decimal (PHP returns large serials as hex).
     *
     * Plain PHP on purpose: bcmath / gmp are often missing on shared hosting, and a missing extension
     * here silently stopped every sale from getting its e-invoice there.
     */
    public function serialNumber(): string
    {
        return self::hexToDecimal($this->parsed['serialNumberHex']);
    }

    /** Arbitrary-length hex → decimal string, digit by digit (decimal digits kept least significant first). */
    public static function hexToDecimal(string $hex): string
    {
        $digits = [0];

        foreach (str_split(strtolower(ltrim($hex, '0')) ?: '0') as $hexDigit) {
            $carry = hexdec($hexDigit);
            foreach ($digits as $i => $d) {
                $value = $d * 16 + $carry;
                $digits[$i] = $value % 10;
                $carry = intdiv($value, 10);
            }
            while ($carry > 0) {
                $digits[] = $carry % 10;
                $carry = intdiv($carry, 10);
            }
        }

        return implode('', array_reverse($digits));
    }

    public function validTo(): string
    {
        return date('Y-m-d H:i', $this->parsed['validTo_time_t']);
    }

    public function subject(): string
    {
        return $this->parsed['name'] ?? '';
    }

    /**
     * DER encoded SubjectPublicKeyInfo (QR tag 8).
     */
    public function publicKey(): string
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public($this->pem()));
        $pem = preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s+/', '', $details['key']);

        return base64_decode($pem);
    }

    /**
     * Raw signature value of the certificate (QR tag 9).
     */
    public function signature(): string
    {
        $der = base64_decode($this->base64);

        // Certificate ::= SEQUENCE { tbsCertificate, signatureAlgorithm, signatureValue BIT STRING }
        [, $contentOffset] = $this->readTlv($der, 0);
        $offset = $contentOffset;

        [$tbsLength, $tbsOffset] = $this->readTlv($der, $offset);
        $offset = $tbsOffset + $tbsLength;

        [$algLength, $algOffset] = $this->readTlv($der, $offset);
        $offset = $algOffset + $algLength;

        [$sigLength, $sigOffset] = $this->readTlv($der, $offset);

        // Skip the "unused bits" byte of the BIT STRING
        return substr($der, $sigOffset + 1, $sigLength - 1);
    }

    /**
     * @return array{0: int, 1: int} [content length, content offset]
     */
    private function readTlv(string $der, int $offset): array
    {
        $length = ord($der[$offset + 1]);
        $offset += 2;

        if ($length & 0x80) {
            $bytes = $length & 0x7F;
            $length = 0;

            for ($i = 0; $i < $bytes; $i++) {
                $length = ($length << 8) | ord($der[$offset + $i]);
            }

            $offset += $bytes;
        }

        return [$length, $offset];
    }
}
