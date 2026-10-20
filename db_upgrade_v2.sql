-- ======================================================================
-- ترقية إضافية لقاعدة بياناتك الحالية (لا تحذف أي بيانات) — شغّلها مرة واحدة
-- فقط إذا عندك بيانات حقيقية مدخلة مسبقًا ولا تريد إعادة استيراد database.sql
-- من الصفر. افتح phpMyAdmin → قاعدة yanbu_tourism → تبويب SQL → الصق هذا
-- الملف كاملاً → Go.
-- ======================================================================

USE yanbu_tourism;

-- عمود رابط خرائط قوقل (إن لم يكن موجودًا بعد)
ALTER TABLE places ADD COLUMN IF NOT EXISTS google_maps_url VARCHAR(500) DEFAULT '';

-- عمود رقم الهاتف (مفيد للمطاعم/الفنادق)
ALTER TABLE places ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT '';

-- تأكيد وجود مستخدم admin سليم (admin / admin123)
-- إذا كان موجودًا، هذا السطر لن يغيّر شيئًا (INSERT IGNORE)
INSERT IGNORE INTO users (username, password, role) VALUES
('admin', '$2y$12$6UqbZTvCrIFhjYXIMoiVveMfHCydQAKIaH5Af4HVROPdH3pAXa5na', 'admin');

-- ملاحظة: إذا بعد تشغيل هذا ما زلت لا تستطيع تسجيل الدخول، فهذا يعني أن صف
-- admin موجود بالفعل لكن كلمة مروره تالفة. في هذه الحالة افتح من المتصفح:
--   http://localhost/yanbu-tourism/admin/diagnose.php
-- وهي أداة تُصلح كلمة المرور تلقائيًا دون الحاجة لنسخ/لصق أي SQL يدويًا.
