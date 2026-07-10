-- ================================================================
--  PawsHome v4 — Complete MySQL Setup
--  Import in phpMyAdmin: Import tab → Choose File → Go
-- ================================================================

DROP DATABASE IF EXISTS `pawshome`;
CREATE DATABASE `pawshome` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pawshome`;

-- ── USERS ──────────────────────────────────────────────────────
CREATE TABLE `users` (
  `id`            INT          NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(256) NOT NULL,
  `role`          ENUM('user','admin') NOT NULL DEFAULT 'user',
  `phone`         VARCHAR(30)  DEFAULT '',
  `favorites`     TEXT         DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── SESSIONS ───────────────────────────────────────────────────
CREATE TABLE `sessions` (
  `id`         INT         NOT NULL AUTO_INCREMENT,
  `token`      VARCHAR(64) NOT NULL,
  `user_id`    INT         NOT NULL,
  `expires_at` DATETIME    NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_sess_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── PETS ───────────────────────────────────────────────────────
CREATE TABLE `pets` (
  `id`              INT           NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(100)  NOT NULL,
  `species`         VARCHAR(50)   NOT NULL,
  `breed`           VARCHAR(100)  NOT NULL,
  `age`             DECIMAL(4,1)  NOT NULL DEFAULT 0,
  `gender`          ENUM('Male','Female') NOT NULL DEFAULT 'Male',
  `description`     TEXT          NOT NULL,
  `vaccinated`      TINYINT(1)    NOT NULL DEFAULT 0,
  `status`          ENUM('available','adopted','pending') NOT NULL DEFAULT 'available',
  `image_url`       VARCHAR(300)  DEFAULT NULL,
  `color`           VARCHAR(80)   DEFAULT '',
  `weight`          VARCHAR(50)   DEFAULT '',
  `health_notes`    TEXT          DEFAULT NULL,
  `adopter_name`    VARCHAR(100)  DEFAULT '',
  `adopter_message` TEXT          DEFAULT NULL,
  `adopted_date`    DATE          DEFAULT NULL,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status`  (`status`),
  KEY `idx_species` (`species`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── ENQUIRIES ──────────────────────────────────────────────────
CREATE TABLE `enquiries` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `user_name`  VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `phone`      VARCHAR(30)  DEFAULT '',
  `pet_id`     INT          DEFAULT NULL,
  `message`    TEXT         NOT NULL,
  `status`     ENUM('pending','responded') NOT NULL DEFAULT 'pending',
  `user_id`    INT          DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pet`  (`pet_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_enq_pet`  FOREIGN KEY (`pet_id`)  REFERENCES `pets`(`id`)  ON DELETE SET NULL,
  CONSTRAINT `fk_enq_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── APPOINTMENTS ───────────────────────────────────────────────
CREATE TABLE `appointments` (
  `id`           INT          NOT NULL AUTO_INCREMENT,
  `visitor_name` VARCHAR(100) NOT NULL,
  `email`        VARCHAR(150) NOT NULL,
  `phone`        VARCHAR(30)  DEFAULT '',
  `pet_id`       INT          DEFAULT NULL,
  `visit_date`   DATE         NOT NULL,
  `visit_time`   VARCHAR(20)  DEFAULT '10:00 AM',
  `status`       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `notes`        TEXT         DEFAULT NULL,
  `user_id`      INT          DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pet`  (`pet_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_appt_pet`  FOREIGN KEY (`pet_id`)  REFERENCES `pets`(`id`)  ON DELETE SET NULL,
  CONSTRAINT `fk_appt_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── BLOG POSTS ─────────────────────────────────────────────────
CREATE TABLE `blog_posts` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(200) NOT NULL,
  `category`   VARCHAR(80)  DEFAULT 'Care Tips',
  `summary`    TEXT         DEFAULT NULL,
  `content`    LONGTEXT     DEFAULT NULL,
  `image_url`  VARCHAR(300) DEFAULT NULL,
  `emoji`      VARCHAR(10)  DEFAULT '🐾',
  `author`     VARCHAR(100) DEFAULT 'Admin',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── SURRENDERS ─────────────────────────────────────────────────
CREATE TABLE `surrenders` (
  `id`          INT          NOT NULL AUTO_INCREMENT,
  `owner_name`  VARCHAR(100) NOT NULL,
  `owner_email` VARCHAR(150) NOT NULL,
  `owner_phone` VARCHAR(30)  DEFAULT '',
  `pet_name`    VARCHAR(100) NOT NULL,
  `species`     VARCHAR(50)  DEFAULT '',
  `breed`       VARCHAR(100) DEFAULT '',
  `age`         DECIMAL(4,1) DEFAULT 0,
  `gender`      VARCHAR(20)  DEFAULT 'Unknown',
  `vaccinated`  TINYINT(1)   DEFAULT 0,
  `reason`      TEXT         DEFAULT NULL,
  `health_info` TEXT         DEFAULT NULL,
  `image_url`   VARCHAR(300) DEFAULT NULL,
  `status`      ENUM('under_review','accepted','declined') NOT NULL DEFAULT 'under_review',
  `admin_notes` TEXT         DEFAULT NULL,
  `user_id`     INT          DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_surr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── APPLICATIONS (Adoption Workflow) ───────────────────────────
CREATE TABLE `applications` (
  `id`               INT          NOT NULL AUTO_INCREMENT,
  `user_id`          INT          NOT NULL,
  `pet_id`           INT          DEFAULT NULL,
  `full_name`        VARCHAR(100) NOT NULL,
  `email`            VARCHAR(150) NOT NULL,
  `phone`            VARCHAR(30)  DEFAULT '',
  `address`          TEXT         DEFAULT NULL,
  `reason`           TEXT         NOT NULL  COMMENT 'Why they want to adopt',
  `living_situation` VARCHAR(200) DEFAULT '' COMMENT 'House/apartment, garden etc.',
  `has_children`     TINYINT(1)   DEFAULT 0,
  `has_other_pets`   TINYINT(1)   DEFAULT 0,
  `experience`       TEXT         DEFAULT NULL COMMENT 'Previous pet ownership',
  `status`           ENUM('submitted','under_review','interview_scheduled','approved','rejected','withdrawn')
                                  NOT NULL DEFAULT 'submitted',
  `admin_notes`      TEXT         DEFAULT NULL,
  `interview_date`   DATETIME     DEFAULT NULL,
  `approved_at`      DATETIME     DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user`   (`user_id`),
  KEY `idx_pet`    (`pet_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_app_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_app_pet`  FOREIGN KEY (`pet_id`)  REFERENCES `pets`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── LOST & FOUND ───────────────────────────────────────────────
CREATE TABLE `lost_found` (
  `id`              INT          NOT NULL AUTO_INCREMENT,
  `user_id`         INT          DEFAULT NULL,
  `type`            ENUM('lost','found') NOT NULL DEFAULT 'lost',
  `pet_name`        VARCHAR(100) DEFAULT '',
  `species`         VARCHAR(50)  NOT NULL,
  `breed`           VARCHAR(100) DEFAULT '',
  `color`           VARCHAR(80)  DEFAULT '',
  `area`            VARCHAR(200) NOT NULL COMMENT 'Neighbourhood / landmark',
  `description`     TEXT         NOT NULL,
  `contact_name`    VARCHAR(100) DEFAULT '',
  `contact_phone`   VARCHAR(30)  DEFAULT '',
  `contact_email`   VARCHAR(150) DEFAULT '',
  `lost_found_date` DATE         NOT NULL,
  `image_url`       VARCHAR(300) DEFAULT NULL,
  `status`          ENUM('open','resolved') NOT NULL DEFAULT 'open',
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type`   (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_user`   (`user_id`),
  CONSTRAINT `fk_lf_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── SUCCESS STORIES ────────────────────────────────────────────
CREATE TABLE `success_stories` (
  `id`           INT          NOT NULL AUTO_INCREMENT,
  `user_id`      INT          DEFAULT NULL,
  `pet_name`     VARCHAR(100) NOT NULL,
  `pet_species`  VARCHAR(50)  DEFAULT '',
  `adopter_name` VARCHAR(100) DEFAULT '',
  `title`        VARCHAR(200) NOT NULL,
  `story`        TEXT         NOT NULL,
  `image_url`    VARCHAR(300) DEFAULT NULL,
  `status`       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `featured`     TINYINT(1)   DEFAULT 0,
  `adopted_year` YEAR         DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status`   (`status`),
  KEY `idx_featured` (`featured`),
  KEY `idx_user`     (`user_id`),
  CONSTRAINT `fk_story_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
--  SEED DATA
-- ================================================================

-- Admin user  (password: admin123)
INSERT INTO `users` (`name`,`email`,`password_hash`,`role`,`phone`,`favorites`) VALUES
('Admin','admin@pawshome.com',
 '$2y$10$qc369qfB1ES0W5PPLqKpv.Gw37Y9ADKEhs/xw/4Z3gEi4obJxZGUu',
 'admin','','[]');

-- Demo user  (password: user123)
INSERT INTO `users` (`name`,`email`,`password_hash`,`role`,`phone`,`favorites`) VALUES
('Rahul Sharma','user@example.com',
 '$2y$10$Uz2eXsVBGYiCpLurdO6C.uKtzb7ryZ04vQsQLorVBpCRUiXtU3xw.',
 'user','+91 98765 43210','[1,3]');

-- Pets
INSERT INTO `pets`
  (`name`,`species`,`breed`,`age`,`gender`,`description`,`vaccinated`,`status`,
   `color`,`weight`,`health_notes`,`adopter_name`,`adopter_message`,`adopted_date`)
VALUES
('Buddy','Dog','Golden Retriever',2,'Male',
 'Buddy is a sunshine-in-fur-form Golden Retriever who greets every new day with boundless enthusiasm. He is fully house-trained, knows six commands, and his greatest joy is a tennis ball and a long walk. Perfect for active families.',
 1,'available','Golden','30 kg','All vaccinations up to date. Excellent hip score.',NULL,NULL,NULL),
('Luna','Cat','Persian',3,'Female',
 'Luna is elegance itself — a silver Persian who glides through rooms like a cloud and settles on laps like a warm blanket. She asks for little: a sunny perch, gentle brushing twice a week, and quiet companionship.',
 1,'available','Silver-White','4 kg','Dental check done. Regular eye-cleaning required for breed.',NULL,NULL,NULL),
('Max','Dog','Labrador Mix',1,'Male',
 'Max arrived at our shelter as a tiny, wide-eyed pup and has blossomed into a spirited young dog. He has mastered basic commands and thrives with an active family.',
 1,'adopted','Chocolate Brown','22 kg','Fully vaccinated. Neutered.',
 'The Sharma Family','Max has filled our home with laughter and muddy paw prints. Best decision ever!','2024-03-15'),
('Coco','Rabbit','Holland Lop',1,'Female',
 'Coco is a velvet-eared Holland Lop who communicates in binkies and nose twitches. She loves fresh herbs, cardboard tunnels, and being stroked behind her ears.',
 0,'available','Caramel & White','1.8 kg',NULL,NULL,NULL,NULL),
('Tweety','Bird','Budgerigar',0.5,'Male',
 'Tweety is a lime-green chatterbox who already mimics whistles and is learning his first words. He enjoys millet sprays, mirror toys, and shoulder rides.',
 0,'available','Lime Green','35 g','Clean bill of health. Beak and claws trimmed.',NULL,NULL,NULL),
('Bella','Dog','Beagle',4,'Female',
 'Bella is every neighbourhood''s favourite — a sturdy Beagle with a chocolate-brown gaze that melts hearts. Calm indoors, excellent with children, and in her element on a sniff expedition.',
 1,'available','Tricolor','12 kg','Vaccinations current. Light diet recommended.',NULL,NULL,NULL),
('Oliver','Cat','Domestic Tabby',5,'Male',
 'Oliver was found on a rainy doorstep and has since decided indoors is the only sensible option. A philosopher — content at the window, occasionally deigning to sit on your lap.',
 1,'adopted','Classic Tabby','5.2 kg','Fully vaccinated. Microchipped.',
 'Priya Mehta','Oliver is the most thoughtful companion. My flat feels complete.','2024-04-02'),
('Hazel','Hamster','Syrian Hamster',0.5,'Female',
 'Hazel is the definition of concentrated cuteness — a cinnamon hamster who runs marathons on her wheel every evening. An excellent first pet for families.',
 0,'available','Cinnamon & Cream','150 g',NULL,NULL,NULL,NULL);

-- Blog posts
INSERT INTO `blog_posts` (`title`,`category`,`summary`,`emoji`,`author`) VALUES
('Your New Kitten''s First 30 Days: A Complete Care Guide','Care Tips',
 'The earliest weeks shape a kitten''s personality for life. Here''s exactly what to do from the first night to the first vet visit.','🐱','Dr. Anita Sharma'),
('The Dog Owner''s Complete Vaccination Calendar','Health',
 'Confused by core vs non-core vaccines? This no-jargon guide walks you through every jab your dog needs and when.','💉','Dr. Vikram Gupta'),
('What Should Your Pet Actually Be Eating?','Nutrition',
 'From raw diets to premium kibble, we break down species-specific nutrition for dogs, cats, rabbits, and birds.','🍖','Dr. Meena Nair'),
('5 Training Mistakes First-Time Dog Owners Make','Training',
 'Inconsistent commands, skipping socialisation, punishment-based corrections — easy to avoid once you know what to watch for.','🎾','Rajan Pillai'),
('Why Senior Pets Make the Most Underrated Companions','Adoption',
 'Older shelter animals are often overlooked. Here''s the compelling case for adopting a senior pet.','❤️','PawsHome Team');

-- Sample enquiries
INSERT INTO `enquiries` (`user_name`,`email`,`phone`,`pet_id`,`message`,`status`) VALUES
('Arjun Kumar','arjun@email.com','+91 98765 43210',1,
 'I have a large home with a garden and would love to give Buddy a forever home. We are an active family.','pending'),
('Priya Singh','priya@email.com','+91 87654 32109',2,
 'Luna sounds perfect for my quiet apartment lifestyle. I work from home so she would never be alone.','responded');

-- Sample appointments
INSERT INTO `appointments` (`visitor_name`,`email`,`phone`,`pet_id`,`visit_date`,`visit_time`,`status`) VALUES
('Rahul Verma','rahul@email.com','+91 91234 56789',1,'2025-06-10','11:00 AM','pending'),
('Sneha Patel','sneha@email.com','',4,'2025-06-12','3:00 PM','approved');

-- Sample adoption applications
INSERT INTO `applications`
  (`user_id`,`pet_id`,`full_name`,`email`,`phone`,`address`,
   `reason`,`living_situation`,`has_children`,`has_other_pets`,`experience`,`status`)
VALUES
(2, 1, 'Rahul Sharma', 'user@example.com', '+91 98765 43210',
 '45 MG Road, Indiranagar, Bangalore 560038',
 'I have always loved Golden Retrievers and have a large house with a garden. Buddy would have plenty of space and daily outdoor activities.',
 'Independent house with garden, gated community','0','0',
 'Grew up with dogs, had a Labrador for 8 years','under_review');

-- Sample lost & found reports
INSERT INTO `lost_found`
  (`user_id`,`type`,`pet_name`,`species`,`breed`,`color`,`area`,
   `description`,`contact_name`,`contact_phone`,`contact_email`,`lost_found_date`,`status`)
VALUES
(2,'lost','Bruno','Dog','German Shepherd','Black & Tan',
 'Koramangala 5th Block, near Forum Mall',
 'Bruno went missing on Sunday evening. He is 3 years old, wearing a red collar with ID tag. Very friendly with people.',
 'Rahul Sharma','+91 98765 43210','user@example.com','2025-01-10','open'),
(NULL,'found','Unknown','Cat','Unknown','Orange Tabby',
 'HSR Layout Sector 2, near the park',
 'Found an orange tabby cat near the park on Sunday morning. The cat appears healthy, well-fed and friendly — likely someone''s pet.',
 'Good Samaritan','+91 80123 45678','finder@email.com','2025-01-12','open');

-- Sample success story (approved)
INSERT INTO `success_stories`
  (`user_id`,`pet_name`,`pet_species`,`adopter_name`,`title`,`story`,`status`,`featured`,`adopted_year`)
VALUES
(NULL,'Max','Dog','The Sharma Family',
 'Max Turned Our House Into a Home',
 'We were hesitant about adopting — worried about the responsibility, the cost, the commitment. Then we met Max at PawsHome and everything changed. From day one, this energetic Labrador mix brought laughter, muddy paw prints, and unconditional love into our lives. My children learned empathy and responsibility. My wife''s morning walks became something she actually looks forward to. And me? I just cannot imagine coming home without that tail wagging at the door. If you are on the fence about adoption, please take the leap. You are not just giving a pet a home — you are giving your family a gift.',
 'approved',1,2024);

-- Verify counts
SELECT 'users'            AS `table`, COUNT(*) AS `rows` FROM users
UNION ALL SELECT 'pets',           COUNT(*) FROM pets
UNION ALL SELECT 'blog_posts',     COUNT(*) FROM blog_posts
UNION ALL SELECT 'enquiries',      COUNT(*) FROM enquiries
UNION ALL SELECT 'appointments',   COUNT(*) FROM appointments
UNION ALL SELECT 'applications',   COUNT(*) FROM applications
UNION ALL SELECT 'lost_found',     COUNT(*) FROM lost_found
UNION ALL SELECT 'success_stories',COUNT(*) FROM success_stories;

SELECT 'PawsHome v4 database ready ✅' AS message;
