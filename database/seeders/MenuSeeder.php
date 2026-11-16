<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Menu transcribed from the printed menu in /pos (prices in SAR, VAT inclusive).
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->menu() as $sort => [$nameEn, $nameAr, $items]) {
            $category = Category::updateOrCreate(
                ['name_en' => $nameEn],
                ['name_ar' => $nameAr, 'sort_order' => $sort + 1, 'is_active' => true],
            );

            foreach ($items as $i => [$en, $ar, $price]) {
                $category->products()->updateOrCreate(
                    ['name_en' => $en],
                    ['name_ar' => $ar, 'price' => $price, 'sort_order' => $i + 1, 'is_active' => true],
                );
            }
        }
    }

    /** Expand one dish into Small / Medium / Large (/ XL) products. */
    private function sized(string $en, string $ar, array $prices): array
    {
        $sizes = [['Small', 'صغير'], ['Medium', 'وسط'], ['Large', 'كبير'], ['XL', 'XL']];

        return array_map(
            fn ($price, $i) => ["{$en} {$sizes[$i][0]}", "{$ar} {$sizes[$i][1]}", $price],
            $prices,
            array_keys($prices),
        );
    }

    private function menu(): array
    {
        return [
            ['Shawarma', 'الشاورما', [
                ['Shawarma Sandwich', 'ساندوتش شاورما', 7],
                ['Shawarma Sandwich with Cheese', 'ساندوتش شاورما بالجبن', 8],
                ['Shawarma Sandwich (Saj Bread)', 'ساندوتش شاورما خبز صاج', 8],
                ['Shawarma Sandwich (Saj Bread) with Cheese', 'ساندوتش شاورما خبز صاج بالجبن', 9],
                ['Large Shawarma', 'شاورما كبير', 14],
                ['Large Shawarma with Cheese', 'شاورما كبير بالجبن', 15],
                ['Jumbo Shawarma', 'شاورما جامبو', 15],
                ['Jumbo Shawarma with Cheese', 'شاورما جامبو بالجبن', 16],
                ['Alloush Special Shawarma', 'شاورما سبيشل علوش', 17],
                ['Arabic Shawarma', 'شاورما عربي', 23],
                ['Oven Arabic Shawarma', 'شاورما عربي بالفرن', 23],
                ['Double Arabic Shawarma', 'شاورما عربي دبل', 46],
                ['Shawarma Plate Small', 'صحن شاورما صغير', 19],
                ['Shawarma Plate Medium', 'صحن شاورما وسط', 24],
                ['Shawarma Plate Large', 'صحن شاورما كبير', 34],
                ['Shawarma Plate XL', 'صحن شاورما الحجم الأكبر XL', 49],
                ['Alloush Special Shawarma Plate', 'صحن شاورما سبيشل علوش', 57],
            ]],
            ['Grills', 'المشاوي', [
                ['Meat Kebab Portion', 'نفر كباب لحم', 33],
                ['Chicken Kebab Portion', 'نفر كباب دجاج', 33],
                ['Awsal Portion', 'نفر أوصال', 33],
                ['Shish Tawook Portion', 'نفر شيش طاووق', 33],
                ['Mixed Grill Portion', 'نفر مشكل', 42],
                ['Grilled Fish Fillet', 'سمك فيليه مشوي', 36],
                ['Mixed Grills Half Kilo', 'مشكل مشاوي نصف كيلو', 66],
                ['Mixed Grills 1 Kilo', 'مشكل مشاوي كيلو', 121],
                ['Alloush Special Grills', 'مشاوي علوش سبيشل', 156],
                ['Meat Kebab Sandwich', 'ساندوتش كباب لحم', 17],
                ['Chicken Kebab Sandwich', 'ساندوتش كباب دجاج', 16],
                ['Awsal Sandwich', 'ساندوتش أوصال', 16],
                ['Shish Tawook Sandwich', 'ساندوتش شيش طاووق', 16],
            ]],
            ['Pastries', 'المعجنات', [
                ['Labneh & Honey Pie', 'فطيرة لبنة بالعسل', 8],
                ['Akkawi Cheese Pie', 'فطيرة جبن عكاوي', 8],
                ['Liquid Cheese Pie', 'فطيرة جبن سائل', 8],
                ['Labneh Pie', 'فطيرة لبنة', 8],
                ['Labneh & Zaatar Pie', 'فطيرة لبنة بالزعتر', 8],
                ['Zaatar Pie', 'فطيرة زعتر', 8],
                ['Meat Pie', 'فطيرة لحم', 8],
                ['Chicken Pie', 'فطيرة دجاج', 8],
                ['Shawarma Pie', 'فطيرة شاورما', 9],
                ['Muhammara Pie', 'فطيرة محمرة', 9],
                ['Spinach Pie', 'فطيرة سبانخ', 8],
                ['Meat Arayes', 'عرايس لحم', 11],
                ['Chicken Arayes', 'عرايس دجاج', 11],
                ['Shawarma Arayes', 'عرايس شاورما', 11],
                ['Sliced Cheese Arayes', 'عرايس جبن شرايح', 12],
                ['Cheddar Cheese Arayes', 'عرايس جبن شادر', 12],
                ['Mixed Pies Plate Small', 'صحن فطاير مشكل صغير', 38],
                ['Mixed Pies Plate Medium', 'صحن فطاير مشكل وسط', 63],
                ['Mixed Pies Plate Large', 'صحن فطاير مشكل كبير', 81],
            ]],
            ['Broasted', 'البروستد', [
                ['Regular Broasted', 'بروستد عادي', 20],
                ['Spicy Broasted', 'بروستد حراق', 20],
                ['Broasted Fingers', 'بروستد أصابع', 24],
                ['Boneless Broasted', 'بروستد مسحب', 24],
                ['Fish Broasted', 'بروستد سمك', 31],
                ['Chicken Wings', 'جوانح دجاج', 21],
                ['Fries Plate Small', 'صحن بطاطس صغير', 11],
                ['Fries Plate Medium', 'صحن بطاطس وسط', 17],
                ['Fries Plate Large', 'صحن بطاطس كبير', 22],
            ]],
            ['Kudu & Burger', 'الكودو والبرجر', [
                ['Beef Burger', 'برجر لحم', 11],
                ['Beef Burger with Cheese', 'برجر لحم بالجبن', 12],
                ['Chicken Burger', 'برجر دجاج', 11],
                ['Chicken Burger with Cheese', 'برجر دجاج بالجبن', 12],
                ['Kudu', 'كودو', 15],
                ['Kudu with Cheese', 'كودو بالجبن', 16],
                ['Charcoal Grilled Chicken Burger', 'برجر دجاج مشوي على الفحم', 17],
                ['Charcoal Grilled Beef Burger', 'برجر لحم مشوي على الفحم', 17],
                ['Jumbo Zinger Sandwich', 'سندوتش جامبو زنجر', 17],
            ]],
            ['Pizza', 'البيتزا', [
                ...$this->sized('Vegetable Pizza', 'بيتزا خضار', [15, 20, 25]),
                ...$this->sized('Meat Pizza', 'بيتزا باللحم', [18, 23, 31]),
                ...$this->sized('Chicken Pizza', 'بيتزا دجاج', [18, 22, 31]),
                ...$this->sized('Shawarma Pizza', 'بيتزا شاورما', [20, 27, 37]),
                ...$this->sized('Bulbul Nest', 'عش البلبل', [15, 20, 25]),
                ...$this->sized('Pepperoni Pizza', 'بيتزا بيبروني', [17, 22, 31]),
                ...$this->sized('Sausage Pizza', 'بيتزا نقانق', [17, 22, 31]),
                ...$this->sized('Ranch Pizza', 'بيتزا الرانش', [17, 22, 31]),
                ...$this->sized('Cheese Pizza', 'بيتزا أجبان', [20, 27, 37]),
            ]],
            ['Appetizers', 'المقبلات', [
                ...$this->sized('Kibbeh Plate', 'صحن كبة', [13, 24, 36]),
                ...$this->sized('Grape Leaves Plate', 'صحن ورق عنب', [9, 16, 31, 46]),
                ...$this->sized('Hummus', 'حمص', [8, 13, 24]),
                ...$this->sized('Mutabbal', 'متبل', [8, 13, 24]),
                ...$this->sized('Baba Ghanoush', 'بابا غنوج', [8, 13, 24]),
                ...$this->sized('Tabbouleh', 'تبولة', [8, 13, 24]),
                ...$this->sized('Olive Salad', 'سلطة زيتون', [8, 13, 24]),
                ...$this->sized('Mixed Appetizers Plate', 'صحن مقبلات مشكل', [9, 16, 26, 36]),
                ['Garlic Plate Small', 'صحن ثوم صغير', 6],
                ['Spicy Garlic Plate', 'صحن ثوم حراق', 6],
            ]],
            ['Juices & Drinks', 'العصائر والمشروبات', [
                ['Cocktail', 'كوكتيل', 10],
                ['Alloush Cocktail', 'كوكتيل علوش', 15],
                ['Blended Orange Juice', 'عصير برتقال خلاط', 10],
                ['Fresh Squeezed Orange Juice', 'عصير برتقال كبس', 13],
                ['Mango Juice', 'عصير مانجو', 10],
                ['Strawberry Juice', 'عصير فراولة', 10],
                ['Banana Milk Juice', 'عصير موز بالحليب', 10],
                ['Guava Juice', 'عصير جوافة', 10],
                ['Layers', 'طبقات', 13],
                ['Fruit Chunks (Shaqaf)', 'شقف', 15],
                ['Lemon Juice', 'عصير ليمون', 10],
                ['Apple Juice', 'عصير تفاح', 10],
                ['Kiwi Juice', 'عصير كيوي', 10],
                ['Pomegranate Juice', 'عصير رمان', 10],
                ['Cantaloupe Milk Juice', 'عصير شمام بالحليب', 10],
                ['Pineapple Juice', 'عصير أناناس', 10],
                ['Orange Jug', 'جاك برتقال', 27],
                ['Fresh Squeezed Orange Jug', 'جاك برتقال كبس', 37],
                ['Mango Jug', 'جاك مانجو', 27],
                ['Strawberry Jug', 'جاك فراولة', 27],
                ['Banana Milk Jug', 'جاك موز بالحليب', 27],
                ['Guava Jug', 'جاك جوافة', 27],
                ['Layers Jug', 'جاك طبقات', 27],
                ['Lemon Jug', 'جاك ليمون', 27],
                ['Apple Jug', 'جاك تفاح', 27],
                ['Kiwi Jug', 'جاك كيوي', 27],
                ['Pomegranate Jug', 'جاك رمان', 27],
                ['Cantaloupe Milk Jug', 'جاك شمام بالحليب', 27],
                ['Alloush Cocktail Jug', 'جاك كوكتيل علوش', 32],
                ['Pepsi', 'بيبسي', 3],
                ['7Up', 'سفن أب', 3],
                ['Mirinda Citrus', 'ميرندا حمضيات', 3],
                ['Water', 'ماء', 1],
            ]],
            // The two categories below appear on the till screen but not in the printed menu:
            // prices are typical placeholders — adjust them in Item List.
            ['Ramadan Items', 'رمضانيات', [
                ['Meat Samosa (5 pcs)', 'سمبوسة لحم (5 حبات)', 10],
                ['Cheese Samosa (5 pcs)', 'سمبوسة جبن (5 حبات)', 10],
                ['Vegetable Samosa (5 pcs)', 'سمبوسة خضار (5 حبات)', 8],
                ['Luqaimat', 'لقيمات', 10],
                ['Sobia', 'سوبيا', 5],
                ['Vimto', 'فيمتو', 5],
                ['Tamarind Juice', 'تمر هندي', 6],
                ['Qamar Al-Din', 'قمر الدين', 6],
                ['Dates Box', 'علبة تمر', 15],
                ['Iftar Meal', 'وجبة إفطار', 35],
            ]],
            ['Add-ons', 'إضافات', [
                ['Extra Cheese', 'جبن إضافي', 2],
                ['Extra Garlic Sauce', 'ثوم إضافي', 1],
                ['Extra Spicy Sauce', 'صوص حار إضافي', 1],
                ['Extra Tahini', 'طحينة إضافية', 2],
                ['Extra Pickles', 'مخلل إضافي', 1],
                ['Extra Bread', 'خبز إضافي', 1],
                ['Extra Fries', 'بطاطس إضافية', 5],
                ['Extra Meat', 'لحم إضافي', 6],
                ['Extra Chicken', 'دجاج إضافي', 5],
                ['Hummus Side', 'حمص جانبي', 4],
            ]],
        ];
    }
}
