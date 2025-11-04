DROP TABLE IF EXISTS `debts`;
CREATE TABLE `debts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('عليّ','لي') NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `total_amount` decimal(30,2) NOT NULL,
  `remaining_amount` decimal(30,2) NOT NULL,
  `date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `currency` enum('IQD','USD') NOT NULL DEFAULT 'IQD',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `debts` VALUES ('5', 'لي', 'محمد', '628151651615', '35000000.00', '35000000.00', '2025-08-27', '', 'IQD');
INSERT INTO `debts` VALUES ('16', 'لي', 'شششششششش', '123212', '15000.00', '15000.00', '2025-09-30', '', 'USD');
INSERT INTO `debts` VALUES ('18', 'عليّ', 'gbg', '65432', '2345654.00', '2345110.00', '2025-08-21', '', 'IQD');

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `debt_id` int(11) NOT NULL,
  `amount` decimal(30,2) NOT NULL,
  `payment_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `currency` enum('IQD','USD') NOT NULL DEFAULT 'IQD',
  PRIMARY KEY (`id`),
  KEY `debt_id` (`debt_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`debt_id`) REFERENCES `debts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payments` VALUES ('16', '18', '544.00', '2025-09-01', '', '[\"68acb14c1a998_pexels-pixabay-52500.jpg\",\"68acb14c1b082_pexels-pixabay-414144.jpg\",\"68acb14c1b4b6_pexels-pixabay-531321.jpg\"]', 'IQD');

DROP TABLE IF EXISTS `debt_attachments`;
CREATE TABLE `debt_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `debt_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `debt_id` (`debt_id`),
  CONSTRAINT `debt_attachments_ibfk_1` FOREIGN KEY (`debt_id`) REFERENCES `debts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `debt_attachments` VALUES ('25', '5', '1756147776_pexels-fabianwiktor-994605.jpg', 'pexels-fabianwiktor-994605.jpg', 'image/jpeg', '2025-08-25 21:49:36');
INSERT INTO `debt_attachments` VALUES ('26', '5', '1756147776_pexels-gabriela-palai-129458-395196.jpg', 'pexels-gabriela-palai-129458-395196.jpg', 'image/jpeg', '2025-08-25 21:49:36');
INSERT INTO `debt_attachments` VALUES ('27', '5', '1756147776_pexels-git-stephen-gitau-302905-1670723.jpg', 'pexels-git-stephen-gitau-302905-1670723.jpg', 'image/jpeg', '2025-08-25 21:49:36');
INSERT INTO `debt_attachments` VALUES ('28', '5', '1756147776_pexels-harun-tan-2311991-3980364.jpg', 'pexels-harun-tan-2311991-3980364.jpg', 'image/jpeg', '2025-08-25 21:49:36');
INSERT INTO `debt_attachments` VALUES ('30', '18', '68acb135d8cc7.jpg', 'pexels-apasaric-2341830.jpg', 'image/jpeg', '2025-08-25 21:53:41');
INSERT INTO `debt_attachments` VALUES ('34', '18', '68acb135da511.jpg', 'pexels-eberhardgross-640781.jpg', 'image/jpeg', '2025-08-25 21:53:41');
INSERT INTO `debt_attachments` VALUES ('35', '18', '68acb135da9f6.jpg', 'pexels-eberhardgross-1302242.jpg', 'image/jpeg', '2025-08-25 21:53:41');
INSERT INTO `debt_attachments` VALUES ('36', '18', '1756148102_expenses_report-1.pdf', 'expenses_report-1.pdf', 'application/pdf', '2025-08-25 21:55:02');

