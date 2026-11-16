<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ZATCA e-invoicing (Phase 2): EGS onboarding data and the signed / reported e-invoices.
 * Ported from the zatca_app project; invoices may be linked to a POS order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_settings', function (Blueprint $table) {
            $table->id();
            $table->string('environment')->default('sandbox');

            // Seller (taxpayer) information
            $table->string('seller_name');
            $table->string('vat_number', 15);
            $table->string('crn', 20)->nullable();
            $table->string('street');
            $table->string('building_number', 4);
            $table->string('plot_identification', 4)->nullable();
            $table->string('district');
            $table->string('city');
            $table->string('postal_code', 5);

            // EGS unit / CSR information
            $table->string('egs_name');
            $table->string('egs_model')->default('1');
            $table->string('egs_serial');
            $table->string('branch_name');
            $table->string('industry');
            $table->string('invoice_types', 4)->default('1100');

            // Keys and certificates
            $table->text('private_key')->nullable();
            $table->text('csr')->nullable();
            $table->string('compliance_request_id')->nullable();
            $table->text('compliance_certificate')->nullable();
            $table->text('compliance_secret')->nullable();
            $table->json('compliance_checks')->nullable();
            $table->string('production_request_id')->nullable();
            $table->text('production_certificate')->nullable();
            $table->text('production_secret')->nullable();

            // Invoice chain (ICV counter and previous invoice hash)
            $table->unsignedBigInteger('icv')->default(0);
            $table->string('previous_invoice_hash')->nullable();

            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('invoice_number')->unique();
            $table->string('invoice_type');      // standard (B2B) | simplified (B2C)
            $table->string('document_type');     // invoice | credit | debit
            $table->date('issue_date');
            $table->string('issue_time', 8);
            $table->string('original_invoice_number')->nullable();
            $table->string('note_reason')->nullable();
            $table->string('payment_means', 2)->default('10');
            $table->string('currency', 3)->default('SAR');

            // Buyer information (required for standard invoices)
            $table->string('buyer_name')->nullable();
            $table->string('buyer_vat_number', 15)->nullable();
            $table->string('buyer_street')->nullable();
            $table->string('buyer_building_number', 4)->nullable();
            $table->string('buyer_district')->nullable();
            $table->string('buyer_city')->nullable();
            $table->string('buyer_postal_code', 5)->nullable();

            $table->decimal('line_extension_amount', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);

            // ZATCA data
            $table->unsignedBigInteger('icv')->nullable();
            $table->string('previous_invoice_hash')->nullable();
            $table->string('invoice_hash')->nullable();
            $table->longText('signed_xml')->nullable();
            $table->text('qr_code')->nullable();
            $table->string('zatca_status')->default('pending');
            $table->json('zatca_response')->nullable();
            $table->longText('cleared_xml')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('quantity', 14, 6);
            $table->decimal('unit_price', 14, 2);
            $table->string('vat_category', 1)->default('S');
            $table->decimal('vat_percent', 5, 2)->default(15);
            $table->decimal('line_extension_amount', 14, 2);
            $table->decimal('vat_amount', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('zatca_settings');
    }
};
