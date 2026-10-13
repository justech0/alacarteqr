/**
 * Chef À La Carte — Menu Data
 * Extracted from actual menu images (menuler/*.png)
 * Prices valid from 28.09.2026, VAT included.
 */

const MENU_DATA = {
  categories: [
    { id: 'et', name: { tr: 'Et Menü', en: 'Meat Menu', ar: 'قائمة اللحوم' } },
    { id: 'makarna', name: { tr: 'Makarna Menü', en: 'Pasta Menu', ar: 'قائمة المعكرونة' } },
    { id: 'salata', name: { tr: 'Salata', en: 'Salads', ar: 'السلطات' } },
    { id: 'cocuk', name: { tr: 'Çocuk Menü', en: "Kids' Menu", ar: 'قائمة الأطفال' } }
  ],

  items: [
    // ── Et Menü ──────────────────────────────────────────
    {
      id: 'izgara-kofte',
      category: 'et',
      name: { tr: 'Izgara Köfte', en: 'Grilled Meatballs', ar: 'كفتة مشوية' },
      price: 415,
      description: {
        tr: '160gr Köfte + Patates Kızartması + Salata',
        en: '160g Meatballs + French Fries + Salad',
        ar: '١٦٠غ كفتة + بطاطس مقلية + سلطة'
      },
      ingredients: {
        tr: ['Köfte (160gr)', 'Patates Kızartması', 'Salata'],
        en: ['Meatballs (160g)', 'French Fries', 'Salad'],
        ar: ['كفتة (١٦٠غ)', 'بطاطس مقلية', 'سلطة']
      },
      image: null,
      calories: null
    },
    {
      id: 'sade-bonfile',
      category: 'et',
      name: { tr: 'Sade Bonfile', en: 'Plain Tenderloin', ar: 'فيليه سادة' },
      price: 525,
      description: {
        tr: '160gr Et + Erişte + Salata',
        en: '160g Beef + Noodles + Salad',
        ar: '١٦٠غ لحم + نودلز + سلطة'
      },
      ingredients: {
        tr: ['Bonfile (160gr)', 'Erişte', 'Salata'],
        en: ['Tenderloin (160g)', 'Noodles', 'Salad'],
        ar: ['فيليه (١٦٠غ)', 'نودلز', 'سلطة']
      },
      image: null,
      calories: null
    },
    {
      id: 'begendili-dana',
      category: 'et',
      name: { tr: 'Beğendili Dana Eti', en: 'Veal with Eggplant Purée', ar: 'لحم عجل مع بيوريه الباذنجان' },
      price: 475,
      description: {
        tr: '160gr Et + Patates Kızartması + Salata',
        en: '160g Beef + French Fries + Salad',
        ar: '١٦٠غ لحم + بطاطس مقلية + سلطة'
      },
      ingredients: {
        tr: ['Dana Eti (160gr)', 'Patlıcan Beğendi', 'Patates Kızartması', 'Salata'],
        en: ['Veal (160g)', 'Eggplant Purée', 'French Fries', 'Salad'],
        ar: ['لحم عجل (١٦٠غ)', 'بيوريه الباذنجان', 'بطاطس مقلية', 'سلطة']
      },
      image: null,
      calories: null
    },
    {
      id: 'pideli-kofte',
      category: 'et',
      name: { tr: 'Pideli Köfte', en: 'Meatballs on Pita', ar: 'كفتة على الخبز' },
      price: 470,
      description: {
        tr: '160gr Köfte + Patates Kızartması + Yoğurt',
        en: '160g Meatballs + French Fries + Yogurt',
        ar: '١٦٠غ كفتة + بطاطس مقلية + لبن'
      },
      ingredients: {
        tr: ['Köfte (160gr)', 'Pide', 'Patates Kızartması', 'Yoğurt'],
        en: ['Meatballs (160g)', 'Pita Bread', 'French Fries', 'Yogurt'],
        ar: ['كفتة (١٦٠غ)', 'خبز', 'بطاطس مقلية', 'لبن']
      },
      image: null,
      calories: null
    },
    {
      id: 'mantarli-bonfile',
      category: 'et',
      name: { tr: 'Mantarlı Bonfile', en: 'Tenderloin with Mushrooms', ar: 'فيليه بالفطر' },
      price: 555,
      description: {
        tr: '160gr Et + Erişte + Salata',
        en: '160g Beef + Noodles + Salad',
        ar: '١٦٠غ لحم + نودلز + سلطة'
      },
      ingredients: {
        tr: ['Bonfile (160gr)', 'Mantar Sos', 'Erişte', 'Salata'],
        en: ['Tenderloin (160g)', 'Mushroom Sauce', 'Noodles', 'Salad'],
        ar: ['فيليه (١٦٠غ)', 'صلصة الفطر', 'نودلز', 'سلطة']
      },
      image: null,
      calories: null
    },
    {
      id: 'dana-mexican',
      category: 'et',
      name: { tr: 'Dana Mexican', en: 'Mexican Style Veal', ar: 'لحم عجل مكسيكي' },
      price: 445,
      description: {
        tr: '160gr Et + Erişte + Salata',
        en: '160g Beef + Noodles + Salad',
        ar: '١٦٠غ لحم + نودلز + سلطة'
      },
      ingredients: {
        tr: ['Dana Eti (160gr)', 'Erişte', 'Salata'],
        en: ['Veal (160g)', 'Noodles', 'Salad'],
        ar: ['لحم عجل (١٦٠غ)', 'نودلز', 'سلطة']
      },
      image: null,
      calories: null
    },
    {
      id: 'karabiberli-bonfile',
      category: 'et',
      name: { tr: 'Tane Karabiberli Bonfile', en: 'Peppercorn Tenderloin', ar: 'فيليه بالفلفل الأسود' },
      price: 555,
      description: {
        tr: '160gr Et + Erişte + Salata',
        en: '160g Beef + Noodles + Salad',
        ar: '١٦٠غ لحم + نودلز + سلطة'
      },
      ingredients: {
        tr: ['Bonfile (160gr)', 'Tane Karabiber Sos', 'Erişte', 'Salata'],
        en: ['Tenderloin (160g)', 'Peppercorn Sauce', 'Noodles', 'Salad'],
        ar: ['فيليه (١٦٠غ)', 'صلصة الفلفل الأسود', 'نودلز', 'سلطة']
      },
      image: null,
      calories: null
    },
    {
      id: 'ekmek-kofte',
      category: 'et',
      name: { tr: 'Ekmek Köfte', en: 'Meatball Sandwich', ar: 'ساندويتش كفتة' },
      price: 320,
      description: {
        tr: '120gr Köfte + Ayran',
        en: '120g Meatballs + Ayran',
        ar: '١٢٠غ كفتة + عيران'
      },
      ingredients: {
        tr: ['Köfte (120gr)', 'Ekmek', 'Ayran'],
        en: ['Meatballs (120g)', 'Bread', 'Ayran'],
        ar: ['كفتة (١٢٠غ)', 'خبز', 'عيران']
      },
      image: null,
      calories: null
    },
    {
      id: 'et-sote',
      category: 'et',
      name: { tr: 'Et Sote', en: 'Beef Sauté', ar: 'لحم سوتيه' },
      price: 470,
      description: {
        tr: '160gr Et + Patates Kızartması',
        en: '160g Beef + French Fries',
        ar: '١٦٠غ لحم + بطاطس مقلية'
      },
      ingredients: {
        tr: ['Et (160gr)', 'Patates Kızartması'],
        en: ['Beef (160g)', 'French Fries'],
        ar: ['لحم (١٦٠غ)', 'بطاطس مقلية']
      },
      image: null,
      calories: null
    },

    // ── Makarna Menü ──────────────────────────────────────
    {
      id: 'spagetti-napoliten',
      category: 'makarna',
      name: { tr: 'Spagetti Napoliten', en: 'Spaghetti Napolitana', ar: 'سباغيتي نابوليتان' },
      price: 270,
      description: {
        tr: '270gr Makarna',
        en: '270g Pasta',
        ar: '٢٧٠غ معكرونة'
      },
      ingredients: {
        tr: ['Spagetti (270gr)', 'Napoliten Sos'],
        en: ['Spaghetti (270g)', 'Napolitana Sauce'],
        ar: ['سباغيتي (٢٧٠غ)', 'صلصة نابوليتان']
      },
      image: null,
      calories: null
    },
    {
      id: 'kori-soslu-makarna',
      category: 'makarna',
      name: { tr: 'Köri Soslu Makarna', en: 'Curry Pasta', ar: 'معكرونة بالكاري' },
      price: 275,
      description: {
        tr: '250gr Makarna',
        en: '250g Pasta',
        ar: '٢٥٠غ معكرونة'
      },
      ingredients: {
        tr: ['Makarna (250gr)', 'Köri Sos'],
        en: ['Pasta (250g)', 'Curry Sauce'],
        ar: ['معكرونة (٢٥٠غ)', 'صلصة الكاري']
      },
      image: null,
      calories: null
    },
    {
      id: 'penne-arabiata',
      category: 'makarna',
      name: { tr: 'Penne Arabiata', en: 'Penne Arrabbiata', ar: 'بيني أرابياتا' },
      price: 270,
      description: {
        tr: '250gr Makarna',
        en: '250g Pasta',
        ar: '٢٥٠غ معكرونة'
      },
      ingredients: {
        tr: ['Penne (250gr)', 'Arabiata Sos'],
        en: ['Penne (250g)', 'Arrabbiata Sauce'],
        ar: ['بيني (٢٥٠غ)', 'صلصة أرابياتا']
      },
      image: null,
      calories: null
    },
    {
      id: 'tavuklu-mantarli',
      category: 'makarna',
      name: { tr: 'Tavuklu Mantarlı Makarna', en: 'Chicken & Mushroom Pasta', ar: 'معكرونة بالدجاج والفطر' },
      price: 295,
      description: {
        tr: '250gr Makarna',
        en: '250g Pasta',
        ar: '٢٥٠غ معكرونة'
      },
      ingredients: {
        tr: ['Makarna (250gr)', 'Tavuk', 'Mantar', 'Krema Sos'],
        en: ['Pasta (250g)', 'Chicken', 'Mushrooms', 'Cream Sauce'],
        ar: ['معكرونة (٢٥٠غ)', 'دجاج', 'فطر', 'صلصة كريمة']
      },
      image: null,
      calories: null
    },
    {
      id: 'sebzeli-makarna',
      category: 'makarna',
      name: { tr: 'Sebzeli Makarna', en: 'Vegetable Pasta', ar: 'معكرونة بالخضار' },
      price: 285,
      description: {
        tr: '250gr Makarna',
        en: '250g Pasta',
        ar: '٢٥٠غ معكرونة'
      },
      ingredients: {
        tr: ['Makarna (250gr)', 'Mevsim Sebzeleri'],
        en: ['Pasta (250g)', 'Seasonal Vegetables'],
        ar: ['معكرونة (٢٥٠غ)', 'خضار موسمية']
      },
      image: null,
      calories: null
    },
    {
      id: 'spagetti-bolonez',
      category: 'makarna',
      name: { tr: 'Spagetti Bolonez', en: 'Spaghetti Bolognese', ar: 'سباغيتي بولونيز' },
      price: 290,
      description: {
        tr: '270gr Makarna',
        en: '270g Pasta',
        ar: '٢٧٠غ معكرونة'
      },
      ingredients: {
        tr: ['Spagetti (270gr)', 'Bolonez Sos'],
        en: ['Spaghetti (270g)', 'Bolognese Sauce'],
        ar: ['سباغيتي (٢٧٠غ)', 'صلصة بولونيز']
      },
      image: null,
      calories: null
    },
    {
      id: 'pesto-soslu',
      category: 'makarna',
      name: { tr: 'Pesto Soslu Makarna', en: 'Pesto Pasta', ar: 'معكرونة بالبيستو' },
      price: 295,
      description: {
        tr: '270gr Makarna',
        en: '270g Pasta',
        ar: '٢٧٠غ معكرونة'
      },
      ingredients: {
        tr: ['Makarna (270gr)', 'Pesto Sos'],
        en: ['Pasta (270g)', 'Pesto Sauce'],
        ar: ['معكرونة (٢٧٠غ)', 'صلصة بيستو']
      },
      image: null,
      calories: null
    },

    // ── Salata ──────────────────────────────────────
    {
      id: 'bonfile-salata',
      category: 'salata',
      name: { tr: 'Bonfile Salata', en: 'Tenderloin Salad', ar: 'سلطة فيليه' },
      price: 500,
      description: {
        tr: '160gr Et + Salata',
        en: '160g Beef + Salad',
        ar: '١٦٠غ لحم + سلطة'
      },
      ingredients: {
        tr: ['Bonfile (160gr)', 'Mevsim Salata'],
        en: ['Tenderloin (160g)', 'Seasonal Salad'],
        ar: ['فيليه (١٦٠غ)', 'سلطة موسمية']
      },
      image: null,
      calories: null
    },
    {
      id: 'ton-balikli-salata',
      category: 'salata',
      name: { tr: 'Ton Balıklı Salata', en: 'Tuna Salad', ar: 'سلطة التونة' },
      price: 285,
      description: {
        tr: '160gr Ton Balığı + Salata',
        en: '160g Tuna + Salad',
        ar: '١٦٠غ تونة + سلطة'
      },
      ingredients: {
        tr: ['Ton Balığı (160gr)', 'Mevsim Salata'],
        en: ['Tuna (160g)', 'Seasonal Salad'],
        ar: ['تونة (١٦٠غ)', 'سلطة موسمية']
      },
      image: null,
      calories: null
    },
    {
      id: 'sezar-salata',
      category: 'salata',
      name: { tr: 'Sezar Salata', en: 'Caesar Salad', ar: 'سلطة سيزر' },
      price: 340,
      description: {
        tr: '170gr Tavuk + Salata',
        en: '170g Chicken + Salad',
        ar: '١٧٠غ دجاج + سلطة'
      },
      ingredients: {
        tr: ['Tavuk (170gr)', 'Sezar Sos', 'Kruton', 'Parmesan'],
        en: ['Chicken (170g)', 'Caesar Dressing', 'Croutons', 'Parmesan'],
        ar: ['دجاج (١٧٠غ)', 'صلصة سيزر', 'خبز محمص', 'بارميزان']
      },
      image: null,
      calories: null
    },
    {
      id: 'citir-salata',
      category: 'salata',
      name: { tr: 'Çıtır Salata', en: 'Crispy Salad', ar: 'سلطة مقرمشة' },
      price: 320,
      description: {
        tr: '150gr Tavuk + Salata',
        en: '150g Chicken + Salad',
        ar: '١٥٠غ دجاج + سلطة'
      },
      ingredients: {
        tr: ['Çıtır Tavuk (150gr)', 'Mevsim Salata'],
        en: ['Crispy Chicken (150g)', 'Seasonal Salad'],
        ar: ['دجاج مقرمش (١٥٠غ)', 'سلطة موسمية']
      },
      image: null,
      calories: null
    },
    {
      id: 'akdeniz-salata',
      category: 'salata',
      name: { tr: 'Akdeniz Salata', en: 'Mediterranean Salad', ar: 'سلطة البحر المتوسط' },
      price: 270,
      description: {
        tr: '100gr Peynir + 30gr Zeytin + Salata',
        en: '100g Cheese + 30g Olives + Salad',
        ar: '١٠٠غ جبنة + ٣٠غ زيتون + سلطة'
      },
      ingredients: {
        tr: ['Peynir (100gr)', 'Zeytin (30gr)', 'Mevsim Salata'],
        en: ['Cheese (100g)', 'Olives (30g)', 'Seasonal Salad'],
        ar: ['جبنة (١٠٠غ)', 'زيتون (٣٠غ)', 'سلطة موسمية']
      },
      image: null,
      calories: null
    },
    {
      id: 'hellim-salata',
      category: 'salata',
      name: { tr: 'Hellim Salata', en: 'Halloumi Salad', ar: 'سلطة الحلوم' },
      price: 285,
      description: {
        tr: '110gr Hellim Peyniri + Salata',
        en: '110g Halloumi Cheese + Salad',
        ar: '١١٠غ جبنة حلوم + سلطة'
      },
      ingredients: {
        tr: ['Hellim Peyniri (110gr)', 'Mevsim Salata'],
        en: ['Halloumi Cheese (110g)', 'Seasonal Salad'],
        ar: ['جبنة حلوم (١١٠غ)', 'سلطة موسمية']
      },
      image: null,
      calories: null
    },

    // ── Çocuk Menü ──────────────────────────────────────
    {
      id: 'cocuk-kofte',
      category: 'cocuk',
      name: { tr: 'Çocuk Menü Köfte', en: "Kids' Meatballs", ar: 'كفتة الأطفال' },
      price: 300,
      description: {
        tr: '95gr Köfte + Patates Kızartması',
        en: '95g Meatballs + French Fries',
        ar: '٩٥غ كفتة + بطاطس مقلية'
      },
      ingredients: {
        tr: ['Köfte (95gr)', 'Patates Kızartması'],
        en: ['Meatballs (95g)', 'French Fries'],
        ar: ['كفتة (٩٥غ)', 'بطاطس مقلية']
      },
      image: null,
      calories: null
    },
    {
      id: 'cocuk-tavuk',
      category: 'cocuk',
      name: { tr: 'Çocuk Menü Tavuk', en: "Kids' Chicken", ar: 'دجاج الأطفال' },
      price: 285,
      description: {
        tr: '150gr Tavuk + Patates Kızartması',
        en: '150g Chicken + French Fries',
        ar: '١٥٠غ دجاج + بطاطس مقلية'
      },
      ingredients: {
        tr: ['Tavuk (150gr)', 'Patates Kızartması'],
        en: ['Chicken (150g)', 'French Fries'],
        ar: ['دجاج (١٥٠غ)', 'بطاطس مقلية']
      },
      image: null,
      calories: null
    }
  ],

  // Campaign data — placeholder, shown only when text is provided
  campaign: {
    tr: '',
    en: '',
    ar: ''
  },

  // WiFi credentials
  wifi: {
    ssid: 'Chef_Guest',
    password: 'chefalacarte2026'
  }
};
