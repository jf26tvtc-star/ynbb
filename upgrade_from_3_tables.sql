-- استخدم هذا الملف فقط إذا كانت قاعدة yanbu_tourism القديمة (3 جداول) موجودة عندك وتريد الاحتفاظ ببياناتها.
USE yanbu_tourism;
CREATE TABLE IF NOT EXISTS categories (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL UNIQUE,description VARCHAR(255) DEFAULT '',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS images (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,place_id INT UNSIGNED NOT NULL,image_path VARCHAR(255) NOT NULL,is_main TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,username VARCHAR(80) NOT NULL UNIQUE,password VARCHAR(255) NOT NULL,role VARCHAR(20) NOT NULL DEFAULT 'admin',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO categories(name,description) VALUES ('شواطئ','الشواطئ والواجهات البحرية'),('جبال','الجبال والطبيعة'),('معالم','المعالم التاريخية والثقافية'),('مطاعم','المطاعم والفنادق');
INSERT IGNORE INTO users(username,password,role) SELECT username,password,'admin' FROM admin;
INSERT INTO images(place_id,image_path,is_main) SELECT id,image,1 FROM places WHERE image<>'' AND NOT EXISTS (SELECT 1 FROM images i WHERE i.place_id=places.id AND i.image_path=places.image);
ALTER TABLE places ADD COLUMN category_id INT UNSIGNED NULL AFTER description;
UPDATE places p JOIN categories c ON c.name=p.category SET p.category_id=c.id WHERE p.category_id IS NULL;
ALTER TABLE places MODIFY category_id INT UNSIGNED NOT NULL;
ALTER TABLE places ADD CONSTRAINT fk_places_category FOREIGN KEY(category_id) REFERENCES categories(id) ON UPDATE CASCADE;
ALTER TABLE images ADD CONSTRAINT fk_images_place FOREIGN KEY(place_id) REFERENCES places(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE places DROP COLUMN category, DROP COLUMN image;
DROP TABLE admin;

-- ترقية إضافية: إضافة رابط خرائط قوقل اختياري لكل مكان (إن لم يكن موجودًا)
ALTER TABLE places ADD COLUMN IF NOT EXISTS google_maps_url VARCHAR(500) DEFAULT '';
ALTER TABLE places ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT '';
