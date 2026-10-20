-- ترقية قاعدة البيانات الحالية على MariaDB 10.4 فأعلى دون حذف الجداول أو البيانات.
-- خذ نسخة احتياطية أولاً ثم استورد هذا الملف في قاعدة yanbu_tourism.
-- يمكن تشغيل الملف مجدداً؛ لا يغير حسابات الإدارة أو كلمات المرور.
USE yanbu_tourism;

ALTER TABLE places
    ADD COLUMN IF NOT EXISTS google_maps_url VARCHAR(500) DEFAULT '',
    ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT '',
    MODIFY COLUMN phone VARCHAR(30) DEFAULT '';

ALTER TABLE messages
    CHANGE COLUMN IF EXISTS sender_name name VARCHAR(120) NOT NULL,
    ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT '',
    MODIFY COLUMN email VARCHAR(190) NOT NULL;

-- توحيد العلاقات مع database.sql: منع حذف فئة مستخدمة، وحذف صور المكان عند حذفه.
ALTER TABLE places
    DROP FOREIGN KEY IF EXISTS places_ibfk_1,
    DROP FOREIGN KEY IF EXISTS fk_places_category;

ALTER TABLE places
    ADD CONSTRAINT fk_places_category FOREIGN KEY (category_id)
        REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE images
    DROP FOREIGN KEY IF EXISTS images_ibfk_1,
    DROP FOREIGN KEY IF EXISTS fk_images_place;

ALTER TABLE images
    ADD CONSTRAINT fk_images_place FOREIGN KEY (place_id)
        REFERENCES places(id) ON DELETE CASCADE ON UPDATE CASCADE;
