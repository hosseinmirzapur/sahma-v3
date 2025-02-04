<?php

use App\Models\VoiceRecordSubmission;

return [
    'text' => 'متن پرداز',
    'image' => 'عکس پرداز',
    'voice' => 'صدا پرداز',
    'text-to-voice' => 'صدا ساز',
    'license' => 'پلاک پرداز',
    'book-page' => 'کتاب پرداز',

    VoiceRecordSubmission::STATUS_WAITING_FOR_RECORD => 'ضبط صدا',
    VoiceRecordSubmission::STATUS_GENDER_VALIDATION => 'تایید جنسیت صدا',
    VoiceRecordSubmission::STATUS_TONE_VALIDATION => 'تایید لحن صدا',
    VoiceRecordSubmission::STATUS_ACCENT_VALIDATION => 'تایید لهجه صدا',
    VoiceRecordSubmission::STATUS_TEXT_VALIDATION => 'تایید متن صدا',
    VoiceRecordSubmission::STATUS_TEXT_VALIDATION_V2 => 'تایید متن صدا',
    VoiceRecordSubmission::STATUS_WORD_VALIDATION => 'تشخیص کلمه کلیدی در صدا',

    'no-accent' => 'بدون لهجه',
    'have-accent' => 'لهجه‌دار',

    'native' => 'محاوره',
    'formal' => 'رسمی',
];
