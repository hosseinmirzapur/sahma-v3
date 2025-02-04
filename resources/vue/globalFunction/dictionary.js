export const dictionary = (item) => {
  const translations = {
    // inboxes
    NORMAL: 'عادی',
    SECRET: 'سری',
    CONFIDENTIAL: 'محرمانه',
    IMMEDIATELY: 'فوری',
    INSTANT: 'آنی',
    Attachment: 'الصاق',
    reminder: 'یادآوری',
    saveMessage: 'ذخیره پیام',
    referenceType: 'عطف / پیرو',
    ReferenceType: 'عطف / پیرو',
    signMe: 'امضا من',
    Archive: 'آرشیو',
    FollowUp: 'پیگیری',
    RECEIVED: 'دریافت شده',
    DRAFT: 'پیش نویس',
    SENT: 'ارسال شده',
    ACHIEVED: 'آرشیو شده',
    DELETED: 'حذف شده',
    Delete: 'حذف',
    FOLLOW: 'پیرو',
    REFERENCE: 'عطف',
    REPLIED: 'پاسخ داده شده',

    //  userManagement
    super_admin: 'مدیر سیستم',
    full: 'ادمین',
    read_only: ' کاربر سطح یک ',
    modify: 'کاربر سطح دو',

    //  searchAdvance
    all: 'همه',
    transcribed: 'پردازش شده',
    not_transcribed: ' پردازش نشده ',
    image: 'عکس',
    voice: ' صوتی ',
    video: ' تصویری ',
    book: ' کتاب ',
    office: ' سند ',
    owner: 'بارگذاری شده توسط من',
    other: ' بارگذاری شده توسط ادمین‌های دیگر ',
    identifier: ' جستجوی نام ادمین '
  }
  return translations[item] || item
}
