CREATE DATABASE IF NOT EXISTS mymedia_site_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `mymedia_site_comentario-digital` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON mymedia_platform.* TO 'mymedia'@'%';
GRANT ALL PRIVILEGES ON mymedia_site_demo.* TO 'mymedia'@'%';
GRANT ALL PRIVILEGES ON `mymedia_site_comentario-digital`.* TO 'mymedia'@'%';
FLUSH PRIVILEGES;
