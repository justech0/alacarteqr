<?php
declare(strict_types=1);

return [
    'categories' => [
        ['id' => 'et', 'name' => ['tr' => 'Et Menü', 'en' => 'Meat Menu', 'ar' => 'قائمة اللحوم']],
        ['id' => 'makarna', 'name' => ['tr' => 'Makarna Menü', 'en' => 'Pasta Menu', 'ar' => 'قائمة المعكرونة']],
        ['id' => 'salata', 'name' => ['tr' => 'Salata', 'en' => 'Salads', 'ar' => 'السلطات']],
        ['id' => 'cocuk', 'name' => ['tr' => 'Çocuk Menü', 'en' => "Kids' Menu", 'ar' => 'قائمة الأطفال']],
    ],
    'items' => [
        [
            'id' => 'izgara-kofte', 'category' => 'et', 'price' => '415.00',
            'name' => ['tr' => 'Izgara Köfte', 'en' => 'Grilled Meatballs', 'ar' => 'كفتة مشوية'],
            'description' => ['tr' => '160gr Köfte + Patates Kızartması + Salata', 'en' => '160g Meatballs + French Fries + Salad', 'ar' => '١٦٠غ كفتة + بطاطس مقلية + سلطة'],
        ],
        [
            'id' => 'sade-bonfile', 'category' => 'et', 'price' => '525.00',
            'name' => ['tr' => 'Sade Bonfile', 'en' => 'Plain Tenderloin', 'ar' => 'فيليه سادة'],
            'description' => ['tr' => '160gr Et + Erişte + Salata', 'en' => '160g Beef + Noodles + Salad', 'ar' => '١٦٠غ لحم + نودلز + سلطة'],
        ],
        [
            'id' => 'begendili-dana', 'category' => 'et', 'price' => '475.00',
            'name' => ['tr' => 'Beğendili Dana Eti', 'en' => 'Veal with Eggplant Purée', 'ar' => 'لحم عجل مع بيوريه الباذنجان'],
            'description' => ['tr' => '160gr Et + Patates Kızartması + Salata', 'en' => '160g Beef + French Fries + Salad', 'ar' => '١٦٠غ لحم + بطاطس مقلية + سلطة'],
        ],
        [
            'id' => 'pideli-kofte', 'category' => 'et', 'price' => '470.00',
            'name' => ['tr' => 'Pideli Köfte', 'en' => 'Meatballs on Pita', 'ar' => 'كفتة على الخبز'],
            'description' => ['tr' => '160gr Köfte + Patates Kızartması + Yoğurt', 'en' => '160g Meatballs + French Fries + Yogurt', 'ar' => '١٦٠غ كفتة + بطاطس مقلية + لبن'],
        ],
        [
            'id' => 'mantarli-bonfile', 'category' => 'et', 'price' => '555.00',
            'name' => ['tr' => 'Mantarlı Bonfile', 'en' => 'Tenderloin with Mushrooms', 'ar' => 'فيليه بالفطر'],
            'description' => ['tr' => '160gr Et + Erişte + Salata', 'en' => '160g Beef + Noodles + Salad', 'ar' => '١٦٠غ لحم + نودلز + سلطة'],
        ],
        [
            'id' => 'dana-mexican', 'category' => 'et', 'price' => '445.00',
            'name' => ['tr' => 'Dana Mexican', 'en' => 'Mexican Style Veal', 'ar' => 'لحم عجل مكسيكي'],
            'description' => ['tr' => '160gr Et + Erişte + Salata', 'en' => '160g Beef + Noodles + Salad', 'ar' => '١٦٠غ لحم + نودلز + سلطة'],
        ],
        [
            'id' => 'karabiberli-bonfile', 'category' => 'et', 'price' => '555.00',
            'name' => ['tr' => 'Tane Karabiberli Bonfile', 'en' => 'Peppercorn Tenderloin', 'ar' => 'فيليه بالفلفل الأسود'],
            'description' => ['tr' => '160gr Et + Erişte + Salata', 'en' => '160g Beef + Noodles + Salad', 'ar' => '١٦٠غ لحم + نودلز + سلطة'],
        ],
        [
            'id' => 'ekmek-kofte', 'category' => 'et', 'price' => '320.00',
            'name' => ['tr' => 'Ekmek Köfte', 'en' => 'Meatball Sandwich', 'ar' => 'ساندويتش كفتة'],
            'description' => ['tr' => '120gr Köfte + Ayran', 'en' => '120g Meatballs + Ayran', 'ar' => '١٢٠غ كفتة + عيران'],
        ],
        [
            'id' => 'et-sote', 'category' => 'et', 'price' => '470.00',
            'name' => ['tr' => 'Et Sote', 'en' => 'Beef Sauté', 'ar' => 'لحم سوتيه'],
            'description' => ['tr' => '160gr Et + Patates Kızartması', 'en' => '160g Beef + French Fries', 'ar' => '١٦٠غ لحم + بطاطس مقلية'],
        ],
        [
            'id' => 'spagetti-napoliten', 'category' => 'makarna', 'price' => '270.00',
            'name' => ['tr' => 'Spagetti Napoliten', 'en' => 'Spaghetti Napolitana', 'ar' => 'سباغيتي نابوليتان'],
            'description' => ['tr' => '270gr Makarna', 'en' => '270g Pasta', 'ar' => '٢٧٠غ معكرونة'],
        ],
        [
            'id' => 'kori-soslu-makarna', 'category' => 'makarna', 'price' => '275.00',
            'name' => ['tr' => 'Köri Soslu Makarna', 'en' => 'Curry Pasta', 'ar' => 'معكرونة بالكاري'],
            'description' => ['tr' => '250gr Makarna', 'en' => '250g Pasta', 'ar' => '٢٥٠غ معكرونة'],
        ],
        [
            'id' => 'penne-arabiata', 'category' => 'makarna', 'price' => '270.00',
            'name' => ['tr' => 'Penne Arabiata', 'en' => 'Penne Arrabbiata', 'ar' => 'بيني أرابياتا'],
            'description' => ['tr' => '250gr Makarna', 'en' => '250g Pasta', 'ar' => '٢٥٠غ معكرونة'],
        ],
        [
            'id' => 'tavuklu-mantarli', 'category' => 'makarna', 'price' => '295.00',
            'name' => ['tr' => 'Tavuklu Mantarlı Makarna', 'en' => 'Chicken & Mushroom Pasta', 'ar' => 'معكرونة بالدجاج والفطر'],
            'description' => ['tr' => '250gr Makarna', 'en' => '250g Pasta', 'ar' => '٢٥٠غ معكرونة'],
        ],
        [
            'id' => 'sebzeli-makarna', 'category' => 'makarna', 'price' => '285.00',
            'name' => ['tr' => 'Sebzeli Makarna', 'en' => 'Vegetable Pasta', 'ar' => 'معكرونة بالخضار'],
            'description' => ['tr' => '250gr Makarna', 'en' => '250g Pasta', 'ar' => '٢٥٠غ معكرونة'],
        ],
        [
            'id' => 'spagetti-bolonez', 'category' => 'makarna', 'price' => '290.00',
            'name' => ['tr' => 'Spagetti Bolonez', 'en' => 'Spaghetti Bolognese', 'ar' => 'سباغيتي بولونيز'],
            'description' => ['tr' => '270gr Makarna', 'en' => '270g Pasta', 'ar' => '٢٧٠غ معكرونة'],
        ],
        [
            'id' => 'pesto-soslu', 'category' => 'makarna', 'price' => '295.00',
            'name' => ['tr' => 'Pesto Soslu Makarna', 'en' => 'Pesto Pasta', 'ar' => 'معكرونة بالبيستو'],
            'description' => ['tr' => '270gr Makarna', 'en' => '270g Pasta', 'ar' => '٢٧٠غ معكرونة'],
        ],
        [
            'id' => 'bonfile-salata', 'category' => 'salata', 'price' => '500.00',
            'name' => ['tr' => 'Bonfile Salata', 'en' => 'Tenderloin Salad', 'ar' => 'سلطة فيليه'],
            'description' => ['tr' => '160gr Et + Salata', 'en' => '160g Beef + Salad', 'ar' => '١٦٠غ لحم + سلطة'],
        ],
        [
            'id' => 'ton-balikli-salata', 'category' => 'salata', 'price' => '285.00',
            'name' => ['tr' => 'Ton Balıklı Salata', 'en' => 'Tuna Salad', 'ar' => 'سلطة التونة'],
            'description' => ['tr' => '160gr Ton Balığı + Salata', 'en' => '160g Tuna + Salad', 'ar' => '١٦٠غ تونة + سلطة'],
        ],
        [
            'id' => 'sezar-salata', 'category' => 'salata', 'price' => '340.00',
            'name' => ['tr' => 'Sezar Salata', 'en' => 'Caesar Salad', 'ar' => 'سلطة سيزر'],
            'description' => ['tr' => '170gr Tavuk + Salata', 'en' => '170g Chicken + Salad', 'ar' => '١٧٠غ دجاج + سلطة'],
        ],
        [
            'id' => 'citir-salata', 'category' => 'salata', 'price' => '320.00',
            'name' => ['tr' => 'Çıtır Salata', 'en' => 'Crispy Salad', 'ar' => 'سلطة مقرمشة'],
            'description' => ['tr' => '150gr Tavuk + Salata', 'en' => '150g Chicken + Salad', 'ar' => '١٥٠غ دجاج + سلطة'],
        ],
        [
            'id' => 'akdeniz-salata', 'category' => 'salata', 'price' => '270.00',
            'name' => ['tr' => 'Akdeniz Salata', 'en' => 'Mediterranean Salad', 'ar' => 'سلطة البحر المتوسط'],
            'description' => ['tr' => '100gr Peynir + 30gr Zeytin + Salata', 'en' => '100g Cheese + 30g Olives + Salad', 'ar' => '١٠٠غ جبنة + ٣٠غ زيتون + سلطة'],
        ],
        [
            'id' => 'hellim-salata', 'category' => 'salata', 'price' => '285.00',
            'name' => ['tr' => 'Hellim Salata', 'en' => 'Halloumi Salad', 'ar' => 'سلطة الحلوم'],
            'description' => ['tr' => '110gr Hellim Peyniri + Salata', 'en' => '110g Halloumi Cheese + Salad', 'ar' => '١١٠غ جبنة حلوم + سلطة'],
        ],
        [
            'id' => 'cocuk-kofte', 'category' => 'cocuk', 'price' => '300.00',
            'name' => ['tr' => 'Çocuk Menü Köfte', 'en' => "Kids' Meatballs", 'ar' => 'كفتة الأطفال'],
            'description' => ['tr' => '95gr Köfte + Patates Kızartması', 'en' => '95g Meatballs + French Fries', 'ar' => '٩٥غ كفتة + بطاطس مقلية'],
        ],
        [
            'id' => 'cocuk-tavuk', 'category' => 'cocuk', 'price' => '285.00',
            'name' => ['tr' => 'Çocuk Menü Tavuk', 'en' => "Kids' Chicken", 'ar' => 'دجاج الأطفال'],
            'description' => ['tr' => '150gr Tavuk + Patates Kızartması', 'en' => '150g Chicken + French Fries', 'ar' => '١٥٠غ دجاج + بطاطس مقلية'],
        ],
    ],
];
