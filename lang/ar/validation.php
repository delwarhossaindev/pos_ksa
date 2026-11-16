<?php

// Arabic validation messages for the rules used in this app. Missing rules fall back to English.
return [
    'array' => 'يجب أن يكون حقل :attribute مصفوفة.',
    'confirmed' => 'تأكيد :attribute غير متطابق.',
    'date' => 'حقل :attribute ليس تاريخاً صحيحاً.',
    'email' => 'يجب أن يكون :attribute بريداً إلكترونياً صحيحاً.',
    'exists' => 'قيمة :attribute المختارة غير صحيحة.',
    'in' => 'قيمة :attribute المختارة غير صحيحة.',
    'integer' => 'يجب أن يكون :attribute عدداً صحيحاً.',
    'max' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عناصر.',
        'numeric' => 'يجب ألا تكون قيمة :attribute أكبر من :max.',
        'string' => 'يجب ألا يتجاوز طول :attribute :max حرفاً.',
    ],
    'min' => [
        'array' => 'يجب أن يحتوي :attribute على :min عنصر على الأقل.',
        'numeric' => 'يجب أن تكون قيمة :attribute على الأقل :min.',
        'string' => 'يجب أن يكون طول :attribute على الأقل :min أحرف.',
    ],
    'numeric' => 'يجب أن يكون :attribute رقماً.',
    'required' => 'حقل :attribute مطلوب.',
    'string' => 'يجب أن يكون :attribute نصاً.',
    'unique' => 'قيمة :attribute مستخدمة من قبل.',

    'attributes' => [
        'name' => 'الاسم',
        'name_en' => 'الاسم (إنجليزي)',
        'name_ar' => 'الاسم (عربي)',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'role' => 'الصلاحية',
        'price' => 'السعر',
        'category_id' => 'القسم',
        'sort_order' => 'ترتيب العرض',
        'items' => 'الأصناف',
        'order_type' => 'نوع الطلب',
        'payment_method' => 'طريقة الدفع',
        'paid_amount' => 'المبلغ المدفوع',
        'discount' => 'الخصم',
        'store_name_en' => 'اسم المتجر (إنجليزي)',
        'store_name_ar' => 'اسم المتجر (عربي)',
        'vat_rate' => 'نسبة الضريبة',
        'vat_number' => 'الرقم الضريبي',
        'currency_en' => 'العملة (إنجليزي)',
        'currency_ar' => 'العملة (عربي)',
    ],
];
