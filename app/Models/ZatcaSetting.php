<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZatcaSetting extends Model
{
    /**
     * Base64 SHA-256 of "0", used as the PIH of the very first invoice.
     */
    public const INITIAL_PIH = 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';

    protected $guarded = ['id'];

    protected $casts = [
        'private_key' => 'encrypted',
        'compliance_secret' => 'encrypted',
        'production_secret' => 'encrypted',
        'compliance_checks' => 'array',
    ];

    protected $hidden = ['private_key', 'compliance_secret', 'production_secret'];

    public static function current(): ?self
    {
        return static::query()->first();
    }

    /** Active ZATCA environment: the one saved on the ZATCA page, else ZATCA_ENV from .env. */
    public static function currentEnvironment(): string
    {
        return static::query()->value('environment') ?: (string) config('zatca.env');
    }

    /** The fixed test VAT number of an environment ('seller' or 'buyer'), or null when it is typed by hand. */
    public static function fixedVat(string $environment, string $party): ?string
    {
        return config("zatca.environments.{$environment}.{$party}_vat") ?: null;
    }

    /**
     * Forget everything tied to the old environment's EGS onboarding: key, CSR, CSIDs, compliance results
     * and the invoice chain. A new environment is a new EGS unit, so its ICV/PIH chain starts again.
     */
    public function resetOnboarding(): void
    {
        $this->forceFill([
            'private_key' => null,
            'csr' => null,
            'compliance_request_id' => null,
            'compliance_certificate' => null,
            'compliance_secret' => null,
            'compliance_checks' => null,
            'production_request_id' => null,
            'production_certificate' => null,
            'production_secret' => null,
            'icv' => 0,
            'previous_invoice_hash' => null,
        ]);
    }

    public function hasCsr(): bool
    {
        return filled($this->private_key) && filled($this->csr);
    }

    public function hasComplianceCsid(): bool
    {
        return filled($this->compliance_certificate) && filled($this->compliance_secret);
    }

    public function hasProductionCsid(): bool
    {
        return filled($this->production_certificate) && filled($this->production_secret);
    }

    public function previousInvoiceHash(): string
    {
        return $this->previous_invoice_hash ?: self::INITIAL_PIH;
    }

    /**
     * Base64 encoded DER certificate (the decoded binarySecurityToken).
     */
    public function signingCertificate(): ?string
    {
        $token = $this->hasProductionCsid() ? $this->production_certificate : $this->compliance_certificate;

        return $token ? base64_decode($token) : null;
    }
}
