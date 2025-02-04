<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute الزامی است.',
    'active_url' => 'The :attribute is not a valid URL.',
    "after" => ":attribute باید تاریخی بعد از :date باشد.",
    'after_or_equal' => ':attribute باید بعد از یا برابر تاریخ :date باشد.',
    'alpha' => 'The :attribute may only contain letters.',
    'alpha_dash' => 'The :attribute may only contain letters, numbers, dashes and underscores.',
    'alpha_num' => 'The :attribute may only contain letters and numbers.',
    'array' => 'The :attribute must be an array.',
    "before" => ":attribute باید تاریخی قبل از :date باشد.",
    'before_or_equal' => ':attribute باید قبل از یا برابر تاریخ :date باشد.',
    'between' => [
        'numeric' => 'مقدار :attribute باید بین :min و :max باشد.',
        'file' => 'The :attribute must be between :min and :max kilobytes.',
        'string' => 'The :attribute must be between :min and :max characters.',
        'array' => 'The :attribute must have between :min and :max items.',
    ],
    'boolean' => 'The :attribute field must be true or false.',
    'confirmed' => 'تکرار :attribute با :attribute مطابقت ندارد.',
    'current_password' => 'رمز عبور فعلی اشتباه است.',
    'date' => 'The :attribute is not a valid date.',
    'date_equals' => 'The :attribute must be a date equal to :date.',
    'date_format' => 'The :attribute does not match the format :format.',
    'different' => 'The :attribute and :other must be different.',
    'digits' => ' :attribute باید :digits رقم باشد.',
    'digits_between' => 'تعداد ارقام :attribute باید بین :min و :max باشد.',
    'dimensions' => 'The :attribute has invalid image dimensions.',
    'distinct' => 'مقدار :attribute تکراری است.',
    'email' => ':attribute باید معتبر باشد.',
    'exists' => ':attribute وارد شده اشتباه است.',
    'file' => 'The :attribute must be a file.',
    'filled' => 'The :attribute field must have a value.',
    'gt' => [
        'numeric' => 'The :attribute must be greater than :value.',
        'file' => 'The :attribute must be greater than :value kilobytes.',
        'string' => 'The :attribute must be greater than :value characters.',
        'array' => 'The :attribute must have more than :value items.',
    ],
    'gte' => [
        'numeric' => 'The :attribute must be greater than or equal :value.',
        'file' => 'The :attribute must be greater than or equal :value kilobytes.',
        'string' => 'The :attribute must be greater than or equal :value characters.',
        'array' => 'The :attribute must have :value items or more.',
    ],
    'image' => ' :attribute باید عکس باشد.',
    'in' => 'مقدار :attribute نامعتبر است.',
    'in_array' => 'The :attribute field does not exist in :other.',
    'integer' => 'The :attribute must be an integer.',
    'ip' => 'The :attribute must be a valid IP address.',
    'ipv4' => 'The :attribute must be a valid IPv4 address.',
    'ipv6' => 'The :attribute must be a valid IPv6 address.',
    'json' => 'The :attribute must be a valid JSON string.',
    'lt' => [
        'numeric' => 'The :attribute must be less than :value.',
        'file' => 'The :attribute must be less than :value kilobytes.',
        'string' => 'The :attribute must be less than :value characters.',
        'array' => 'The :attribute must have less than :value items.',
    ],
    'lte' => [
        'numeric' => 'The :attribute must be less than or equal :value.',
        'file' => 'The :attribute must be less than or equal :value kilobytes.',
        'string' => 'The :attribute must be less than or equal :value characters.',
        'array' => 'The :attribute must not have more than :value items.',
    ],
    'max' => [
        'numeric' => 'The :attribute may not be greater than :max.',
        'file' => 'مقدار :attribute نباید بیش از :max کیلوبایت باشد.',
        'string' => 'مقدار :attribute نمی‌تواند بیش از :max کاراکتر باشد.',
        'array' => 'مقدار :attribute نمی‌تواند بیش از :max تا باشد.',
    ],
    'mimes' => ' :attribute باید از نوع :values باشد.',
    'mimetypes' => ':attribute باید فایلی از جنس :values باشد.',
    'min' => [
        'numeric' => 'The :attribute must be at least :min.',
        'file' => 'The :attribute must be at least :min kilobytes.',
        'string' => ':attribute باید حداقل :min کاراکتر باشد.',
        'array' => ':attribute حداقل باید :min مورد باشد.',
    ],
    'not_in' => 'The selected :attribute is invalid.',
    'not_regex' => 'The :attribute format is invalid.',
    'numeric' => ':attribute باید عدد باشد.',
    'present' => 'The :attribute field must be present.',
    'regex' => 'فرمت :attribute اشتباه است.',
    'required' => 'وارد کردن :attribute الزامی است.',
    'required_if' => 'The :attribute field is required when :other is :value.',
    'required_unless' => 'مقدار  :attribute الزامی می‌باشد مگر اینکه :other :values باشد.',
    'required_with' => 'The :attribute field is required when :values is present.',
    'required_with_all' => 'The :attribute field is required when :values are present.',
    'required_without' => 'The :attribute field is required when :values is not present.',
    'required_without_all' => 'The :attribute field is required when none of :values are present.',
    'same' => 'The :attribute and :other must match.',
    'size' => [
        'numeric' => 'The :attribute must be :size.',
        'file' => 'The :attribute must be :size kilobytes.',
        'string' => ':attribute باید :size کاراکتر باشد.',
        'array' => 'تعداد :attribute باید :size باشد.',
    ],
    'starts_with' => 'The :attribute must start with one of the following: :values',
    'string' => ':attribute باید رشته‌ی حرفی باشد.',
    'timezone' => 'The :attribute must be a valid zone.',
    'unique' => ':attribute تکراری است.',
    'uploaded' => 'The :attribute failed to upload.',
    'url' => 'The :input format is invalid.',
    'uuid' => 'The :attribute must be a valid UUID.',

    'captcha' => 'کد امنیتی صحیح نیست.',
    'recaptcha' => 'کد امنیتی صحیح نیست.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'email' => 'ایمیل',
        'char' => 'حرف پلاک',
        'first_part' => 'بخش اول پلاک',
        'second_part' => 'بخش دوم پلاک',
        'third_part' => 'بخش سوم پلاک',
        'state_code' => 'کد پلاک',
        'captcha' => 'کد امنیتی',
        'status' => 'وضعیت',
        'voice_text' => 'متن صدا',
        'birth_date' => 'تاریخ تولد',
        'birth_city' => 'شهر تولد',
        'city_of_residence' => 'شهر اقامت',
        'education_level' => 'سطح تحصیلات',
        'sex' => 'جنسیت',
        'image_text' => 'متن عکس',
        'voice' => 'صدا',
        'option' => 'گزینه',
        'keywords' => 'عبارت‌ها',
        'keywords.0' => 'عبارت اول',
        'keywords.1' => 'عبارت دوم',
        'keywords.2' => 'عبارت سوم',
        'keywords.3' => 'عبارت چهارم',
        'title' => 'عنوان',
        'file' => 'فایل',
        'name' => 'نام',
        'publisher' => 'ناشر',
        'series' => 'جلد',
        'collection' => 'مجموعه',
        'writer' => 'نویسنده',
        'translator' => 'مترجم',
        'tags' => 'تگ',
        'province' => 'استان',
        'city' => 'شهر',
        'main_street' => 'خیابان اصلی',
        'side_street' => 'خیابان فرعی',
        'alley' => 'کوچه',
        'plaque' => 'پلاک',
        'unit' => 'واحد',
        'mobile' => 'موبایل',
        'password' => 'رمز عبور',
        'rejection_reason' => 'علت رد',
        "car-card-front" => 'تصویر روی کارت خودرو',
        "car-card-back" => 'تصویر پشت کارت خودرو',
        "license-card" => 'تصویر گواهینامه',
        "insurance-card" => 'تصویر بیمه نامه قبلی',
        "phone" => 'شماره تماس',
        "message" => 'پیام',
        "worker_state" => 'حالت کاربر',
        "accent" => 'لهجه',
        "accents" => 'لهجه‌ها',
        "options" => 'گزینه',
        "colors" => 'رنگ‌ها',
        "insurance_code" => 'کد یکتا',
        "national_code" => 'کد ملی',
        "question_text" => 'صورت سوال',
        "price" => 'مبلغ',
        "entity_type" => 'فرمت داده ارسالی',
        "display_name" => 'نام نمایشی',
        "mobileCode" => 'کد تایید',
        'text' => 'متن',
        'rules_acceptance' => 'پذیرش قوانین و مقررات',
        'question-type' => 'پیشه',
        'conversion_type' => 'نحوه انجام',
        'language' => 'زبان',
        "id_file" => "فایل کارت ملی",
        "old_mobile_number" => "شماره موبایل قدیمی",
        "new_mobile_number" => "شماره موبایل جدید",
        "identifier" => "شماره موبایل",
        "iban" => "شماره شبا",
        "cargo" => 'عنوان بار',
        "origin" => 'مبدا',
        "destination" => 'مقصد',
        "vehicleType" => 'نوع ماشین یا وزن بار',
        "orders" => 'سفارشات'
    ],
];
