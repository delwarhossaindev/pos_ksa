<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Store details taken from the sample receipt in /pos — edit them in Admin → Settings.
        Setting::put([
            'store_name_en' => 'Shawarma Alloush Fast Food Restaurant',
            'store_name_ar' => 'مطعم شاورما علوش للوجبات السريعة',
            'address_en' => 'Riyadh - Sheikh Jaber Al-Sabah St. - Al Qadisiyah',
            'address_ar' => 'الرياض - شارع الشيخ جابر الصباح - حي القادسية',
            'phone' => '0534400886 - 0534400885',
            'vat_number' => '302256240800003',
            'vat_rate' => '15',
            'currency_en' => 'SAR',
            'currency_ar' => 'ر.س',
            'receipt_footer_en' => 'Thank you for your visit',
            'receipt_footer_ar' => 'شكراً لزيارتكم',
        ]);

        $this->call([DemoUsersSeeder::class, MenuSeeder::class]);
    }
}
