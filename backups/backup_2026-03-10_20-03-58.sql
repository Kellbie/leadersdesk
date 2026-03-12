-- LeaderDesk Database Backup
-- Generated: 2026-03-10 20:03:58



CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `points_earned` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activity_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('1', '2', '4', 'add_prospect', 'Added prospect: Vicsum Private School', '5', '2026-03-07 18:48:32');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('2', '2', '4', 'add_prospect', 'Added prospect: joy', '5', '2026-03-07 20:34:13');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('3', '2', '4', 'logout', 'User logged out', '0', '2026-03-07 20:48:23');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('4', '3', '7', 'logout', 'User logged out', '0', '2026-03-08 21:29:43');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('5', '1', '1', 'announcement', 'Sent announcement: Create a user to 1 users', '0', '2026-03-08 21:31:19');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('6', '3', '7', 'login', 'User logged in', '0', '2026-03-08 21:32:15');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('7', '3', '7', 'logout', 'User logged out', '0', '2026-03-08 23:13:09');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('8', '3', '7', 'login', 'User logged in', '0', '2026-03-08 23:22:15');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('9', '4', '8', 'logout', 'User logged out', '0', '2026-03-09 00:15:57');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('10', '4', '9', 'registered_via_invite', 'Joined team via invite link', '10', '2026-03-09 00:17:19');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('11', '4', '9', 'add_prospect', 'Added prospect: jooy', '5', '2026-03-09 00:21:29');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('12', '4', '9', 'add_prospect', 'Added prospect: joy', '5', '2026-03-09 00:23:48');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('13', '4', '9', 'create_task', 'Created task: Knowledge Training', '5', '2026-03-09 00:30:48');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('14', '4', '9', 'complete_task', 'Completed task: Knowledge Training', '10', '2026-03-09 00:33:39');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('15', '4', '9', 'logout', 'User logged out', '0', '2026-03-09 07:17:42');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('16', '4', '9', 'login', 'User logged in', '0', '2026-03-09 07:17:54');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('17', '4', '9', 'logout', 'User logged out', '0', '2026-03-09 07:19:19');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('18', '5', '10', 'logout', 'User logged out', '0', '2026-03-09 07:25:12');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('19', '5', '11', 'registered_via_invite', 'Joined team via invite link', '10', '2026-03-09 07:26:18');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('20', '5', '11', 'add_prospect', 'Added prospect: Ejele', '5', '2026-03-09 07:29:26');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('21', '5', '11', 'add_prospect', 'Added prospect: Isa', '5', '2026-03-09 07:32:10');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('22', '5', '11', 'logout', 'User logged out', '0', '2026-03-09 07:36:49');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('23', '5', '10', 'login', 'User logged in', '0', '2026-03-09 07:37:32');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('24', '5', '10', 'logout', 'User logged out', '0', '2026-03-09 07:38:10');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('25', '5', '12', 'registered_via_invite', 'Joined team via invite link', '10', '2026-03-09 07:40:19');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('26', '5', '12', 'logout', 'User logged out', '0', '2026-03-09 07:48:45');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('27', '5', '10', 'login', 'User logged in', '0', '2026-03-09 07:49:00');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('28', '5', '10', 'add_member', 'Added team member: Abass', '10', '2026-03-09 07:51:47');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('29', '5', '10', 'add_member', 'Added team member: Gladys', '10', '2026-03-09 07:54:29');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('30', '5', '10', 'create_task', 'Created task: Meeting Venue', '5', '2026-03-09 08:01:04');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('31', '5', '10', 'create_task', 'Created task: Process', '5', '2026-03-09 08:02:50');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('32', '5', '10', 'create_task', 'Created task: Testing', '5', '2026-03-09 08:05:04');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('33', '5', '10', 'upload_training', 'Uploaded training: Knowledge ', '15', '2026-03-09 08:15:52');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('34', '5', '10', 'create_event', 'Created event: Training', '15', '2026-03-09 08:24:04');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('35', '5', '10', 'logout', 'User logged out', '0', '2026-03-09 08:32:01');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('36', '5', '12', 'login', 'User logged in', '0', '2026-03-09 08:33:40');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('37', '5', '12', 'logout', 'User logged out', '0', '2026-03-09 08:50:36');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('38', '5', '10', 'login', 'User logged in', '0', '2026-03-09 09:58:39');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('39', '5', '10', 'logout', 'User logged out', '0', '2026-03-09 10:15:44');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('40', '4', '8', 'login', 'User logged in', '0', '2026-03-09 13:05:50');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('41', '4', '8', 'logout', 'User logged out', '0', '2026-03-09 19:33:31');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('42', '5', '10', 'login', 'User logged in', '0', '2026-03-09 19:34:33');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('43', '5', '10', 'upload_training', 'Uploaded training: Commission structure', '15', '2026-03-09 19:46:29');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('44', '5', '10', 'upload_training', 'Uploaded training: Commission structure', '15', '2026-03-09 19:46:30');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('45', '5', '10', 'logout', 'User logged out', '0', '2026-03-09 20:00:47');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('46', '5', '12', 'login', 'User logged in', '0', '2026-03-09 20:01:20');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('47', '5', '12', 'logout', 'User logged out', '0', '2026-03-09 20:06:04');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('48', '5', '10', 'login', 'User logged in', '0', '2026-03-09 20:06:18');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('49', '5', '10', 'shared_training', 'Shared training material \'Commission structure\' with prospect Isa', '5', '2026-03-09 20:57:58');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('50', '5', '10', 'convert_prospect', 'Converted prospect to member: Ejele', '20', '2026-03-09 20:58:48');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('51', '5', '10', 'shared_training', 'Shared training material \'Commission structure\' with prospect Isa', '5', '2026-03-09 20:59:41');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('52', '5', '10', 'logout', 'User logged out', '0', '2026-03-09 20:59:55');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('53', '5', '12', 'login', 'User logged in', '0', '2026-03-09 21:00:22');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('54', '5', '12', 'complete_task', 'Completed task: Testing', '5', '2026-03-09 21:00:47');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('55', '5', '12', 'complete_task', 'Completed task: Testing', '5', '2026-03-09 21:17:41');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('56', '5', '12', 'logout', 'User logged out', '0', '2026-03-09 21:18:46');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('57', '5', '10', 'login', 'User logged in', '0', '2026-03-09 21:18:59');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('58', '5', '10', 'upload_training', 'Uploaded training: Leadership training', '15', '2026-03-09 21:23:11');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('59', '5', '10', 'logout', 'User logged out', '0', '2026-03-09 21:23:41');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('60', '5', '12', 'login', 'User logged in', '0', '2026-03-09 21:24:07');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('61', '5', '12', 'logout', 'User logged out', '0', '2026-03-09 21:53:46');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('62', '4', '8', 'login', 'User logged in', '0', '2026-03-09 21:54:09');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('63', '4', '8', 'logout', 'User logged out', '0', '2026-03-09 21:54:47');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('64', '5', '10', 'login', 'User logged in', '0', '2026-03-09 21:54:57');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('65', '5', '10', 'upload_training', 'Uploaded training: Knowledge documents', '15', '2026-03-09 21:56:08');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('66', '5', '10', 'upload_training', 'Uploaded training: Products Training', '15', '2026-03-09 22:35:05');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('67', '5', '10', 'upload_training', 'Uploaded training: TRAINING', '15', '2026-03-09 22:36:16');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('68', '5', '10', 'create_task', 'Created task: HDJKDN', '5', '2026-03-09 22:38:56');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('69', '5', '10', 'upload_training', 'Uploaded training: K', '15', '2026-03-09 22:46:34');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('70', '1', '1', 'announcement', 'Sent announcement: Team List to 6 users', '0', '2026-03-10 06:56:40');
INSERT INTO activity_logs (id, team_id, user_id, action, description, points_earned, created_at) VALUES ('72', '1', '1', 'announcement', 'Sent announcement: 567890 to 5 users', '0', '2026-03-10 20:02:13');


CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target` varchar(100) NOT NULL,
  `priority` varchar(50) DEFAULT 'normal',
  `sent_to_count` int(11) DEFAULT 0,
  `sent_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sent_by` (`sent_by`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO announcements (id, title, message, target, priority, sent_to_count, sent_by, created_at) VALUES ('1', 'Create a user', 'You shoudl start creating users kelto', 'team_3', 'high', '1', '1', '2026-03-08 21:31:19');
INSERT INTO announcements (id, title, message, target, priority, sent_to_count, sent_by, created_at) VALUES ('2', '567890', 'erfghjkl', 'leaders', 'normal', '5', '1', '2026-03-10 19:48:30');
INSERT INTO announcements (id, title, message, target, priority, sent_to_count, sent_by, created_at) VALUES ('3', '567890', 'erfghjkl', 'leaders', 'normal', '5', '1', '2026-03-10 20:02:13');


CREATE TABLE `badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO badges (id, name, description, icon) VALUES ('1', 'Top Recruiter', 'Best recruiter of the month', '🏆');
INSERT INTO badges (id, name, description, icon) VALUES ('2', 'Sales Champion', 'Highest sales volume', '💰');
INSERT INTO badges (id, name, description, icon) VALUES ('3', 'Top Trainer', 'Most active in training', '📚');
INSERT INTO badges (id, name, description, icon) VALUES ('4', 'Fastest Growing Leader', 'Most improved activity score', '🚀');


CREATE TABLE `event_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `response` enum('attending','not_attending','maybe') DEFAULT 'maybe',
  `points_awarded` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `event_attendance_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_attendance_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO event_attendance (id, event_id, user_id, response, points_awarded) VALUES ('1', '1', '12', 'attending', '0');


CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `event_type` enum('training','opportunity','team_meeting','product_presentation') NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `meeting_link` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO events (id, team_id, created_by, title, event_type, event_date, event_time, location, meeting_link, description, created_at) VALUES ('1', '5', '10', 'Training', 'training', '2026-03-10', '13:30:00', '', 'https://meet.google.com/qes-ddeo-wkq', 'Come with the shared training documents on getting started', '2026-03-09 08:24:04');


CREATE TABLE `leaderboard` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_score` int(11) DEFAULT 0,
  `total_recruits` int(11) DEFAULT 0,
  `total_sales` decimal(10,2) DEFAULT 0.00,
  `rank_position` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_team_user` (`team_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `leaderboard_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leaderboard_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `member_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `upline_user_id` int(11) DEFAULT NULL,
  `rank` varchar(50) DEFAULT 'Member',
  `member_type` enum('prospect','member') DEFAULT 'member',
  `activity_score` int(11) DEFAULT 0,
  `total_recruits` int(11) DEFAULT 0,
  `total_sales` decimal(10,2) DEFAULT 0.00,
  `join_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `team_id` (`team_id`),
  KEY `upline_user_id` (`upline_user_id`),
  CONSTRAINT `member_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `member_profiles_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `member_profiles_ibfk_3` FOREIGN KEY (`upline_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO member_profiles (id, user_id, team_id, upline_user_id, rank, member_type, activity_score, total_recruits, total_sales, join_date) VALUES ('1', '2', '1', NULL, 'Leader', 'member', '150', '5', '5000.00', '2026-03-07');
INSERT INTO member_profiles (id, user_id, team_id, upline_user_id, rank, member_type, activity_score, total_recruits, total_sales, join_date) VALUES ('2', '3', '1', '2', 'Member', 'member', '75', '1', '1000.00', '2026-03-07');
INSERT INTO member_profiles (id, user_id, team_id, upline_user_id, rank, member_type, activity_score, total_recruits, total_sales, join_date) VALUES ('3', '4', '2', NULL, 'Leader', 'member', '0', '0', '0.00', '2026-03-07');
INSERT INTO member_profiles (id, user_id, team_id, upline_user_id, rank, member_type, activity_score, total_recruits, total_sales, join_date) VALUES ('5', '7', '3', NULL, 'Leader', 'member', '0', '0', '0.00', '2026-03-08');
INSERT INTO member_profiles (id, user_id, team_id, upline_user_id, rank, member_type, activity_score, total_recruits, total_sales, join_date) VALUES ('6', '8', '4', NULL, 'Leader', 'member', '0', '0', '0.00', '2026-03-09');
INSERT INTO member_profiles (id, user_id, team_id, upline_user_id, rank, member_type, activity_score, total_recruits, total_sales, join_date) VALUES ('7', '9', '4', NULL, 'Member', 'member', '10', '0', '0.00', '2026-03-09');
INSERT INTO member_profiles (id, user_id, team_id, upline_user_id, rank, member_type, activity_score, total_recruits, total_sales, join_date) VALUES ('8', '10', '5', NULL, 'Leader', 'member', '0', '0', '0.00', '2026-03-09');
INSERT INTO member_profiles (id, user_id, team_id, upline_user_id, rank, member_type, activity_score, total_recruits, total_sales, join_date) VALUES ('9', '11', '5', NULL, 'Member', 'member', '0', '0', '0.00', '2026-03-09');
INSERT INTO member_profiles (id, user_id, team_id, upline_user_id, rank, member_type, activity_score, total_recruits, total_sales, join_date) VALUES ('10', '12', '5', NULL, 'Member', 'member', '0', '0', '0.00', '2026-03-09');
INSERT INTO member_profiles (id, user_id, team_id, upline_user_id, rank, member_type, activity_score, total_recruits, total_sales, join_date) VALUES ('11', '13', '5', NULL, 'Member', 'member', '0', '0', '0.00', '2026-03-09');
INSERT INTO member_profiles (id, user_id, team_id, upline_user_id, rank, member_type, activity_score, total_recruits, total_sales, join_date) VALUES ('12', '14', '5', NULL, 'Member', 'prospect', '0', '0', '0.00', '2026-03-09');
INSERT INTO member_profiles (id, user_id, team_id, upline_user_id, rank, member_type, activity_score, total_recruits, total_sales, join_date) VALUES ('13', '15', '5', NULL, 'Member', 'member', '0', '0', '0.00', '2026-03-09');


CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text DEFAULT NULL,
  `link` varchar(500) DEFAULT NULL,
  `type` enum('task','member','event','training','target','system') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('1', '3', '7', 'Create a user', 'You shoudl start creating users kelto', NULL, '', '1', '2026-03-08 21:31:19');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('2', '4', '9', 'New Task Assigned', 'You have been assigned a new task: Knowledge Training', NULL, 'task', '0', '2026-03-09 00:30:48');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('3', '5', '13', 'Welcome to the team!', 'You\'ve been added to the team by Felicia', NULL, 'member', '0', '2026-03-09 07:51:47');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('4', '5', '14', 'Welcome to the team!', 'You\'ve been added to the team by Felicia', NULL, 'member', '0', '2026-03-09 07:54:29');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('5', '5', '11', 'New Task Assigned', 'You have been assigned a new task: Meeting Venue', NULL, 'task', '0', '2026-03-09 08:01:04');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('6', '5', '11', 'New Task Assigned', 'You have been assigned a new task: Process', NULL, 'task', '0', '2026-03-09 08:02:50');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('7', '5', '12', 'New Task Assigned', 'You have been assigned a new task: Testing', NULL, 'task', '1', '2026-03-09 08:05:04');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('8', '5', '11', 'Training Shared', 'Felicia shared training material \'Commission structure\' with your prospect Isa', 'view_training_share.php?id=1', 'training', '0', '2026-03-09 20:57:58');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('9', '5', '11', 'Training Shared', 'Felicia shared training material \'Commission structure\' with your prospect Isa', 'view_training_share.php?id=2', 'training', '0', '2026-03-09 20:59:41');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('10', '5', '10', 'Upgrade Request', 'Godwin has requested to become a team leader.', NULL, 'system', '0', '2026-03-09 21:37:16');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('11', '5', '11', 'New Task Assigned', 'You have been assigned a new task: HDJKDN', 'tasks.php?view_task=7', 'task', '0', '2026-03-09 22:38:56');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('12', '5', '10', 'Team List', 'Complete your members list', NULL, '', '0', '2026-03-10 06:56:40');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('13', '5', '11', 'Team List', 'Complete your members list', NULL, '', '0', '2026-03-10 06:56:40');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('14', '5', '12', 'Team List', 'Complete your members list', NULL, '', '0', '2026-03-10 06:56:40');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('15', '5', '13', 'Team List', 'Complete your members list', NULL, '', '0', '2026-03-10 06:56:40');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('16', '5', '14', 'Team List', 'Complete your members list', NULL, '', '0', '2026-03-10 06:56:40');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('17', '5', '15', 'Team List', 'Complete your members list', NULL, '', '0', '2026-03-10 06:56:40');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('18', '1', '2', '567890', 'erfghjkl', NULL, '', '0', '2026-03-10 19:48:30');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('19', '2', '4', '567890', 'erfghjkl', NULL, '', '0', '2026-03-10 19:48:30');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('20', '3', '7', '567890', 'erfghjkl', NULL, '', '0', '2026-03-10 19:48:30');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('21', '4', '8', '567890', 'erfghjkl', NULL, '', '0', '2026-03-10 19:48:30');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('22', '5', '10', '567890', 'erfghjkl', NULL, '', '0', '2026-03-10 19:48:30');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('23', '1', '2', '567890', 'erfghjkl', NULL, '', '0', '2026-03-10 20:02:13');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('24', '2', '4', '567890', 'erfghjkl', NULL, '', '0', '2026-03-10 20:02:13');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('25', '3', '7', '567890', 'erfghjkl', NULL, '', '0', '2026-03-10 20:02:13');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('26', '4', '8', '567890', 'erfghjkl', NULL, '', '0', '2026-03-10 20:02:13');
INSERT INTO notifications (id, team_id, user_id, title, message, link, type, is_read, created_at) VALUES ('27', '5', '10', '567890', 'erfghjkl', NULL, '', '0', '2026-03-10 20:02:13');


CREATE TABLE `platform_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `is_disabled` tinyint(4) DEFAULT 0,
  `disabled_message` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO platform_status (id, is_disabled, disabled_message, updated_by, updated_at) VALUES ('1', '0', '', NULL, '2026-03-08 21:27:13');


CREATE TABLE `prospect_shares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prospect_id` int(11) NOT NULL,
  `shared_by` int(11) NOT NULL,
  `training_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `shared_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `prospect_id` (`prospect_id`),
  KEY `shared_by` (`shared_by`),
  KEY `training_id` (`training_id`),
  CONSTRAINT `prospect_shares_ibfk_1` FOREIGN KEY (`prospect_id`) REFERENCES `prospects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prospect_shares_ibfk_2` FOREIGN KEY (`shared_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prospect_shares_ibfk_3` FOREIGN KEY (`training_id`) REFERENCES `trainings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `prospects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  `stage` enum('new','contacted','invited','presentation','follow_up','joined','not_interested') DEFAULT 'new',
  `follow_up_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `prospects_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prospects_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO prospects (id, team_id, user_id, name, phone, email, source, stage, follow_up_date, notes, created_at) VALUES ('2', '2', '4', 'joy', '08097654322', 'efr@gmail.com', 'facebook', 'new', '2026-03-14', 'sounds interested', '2026-03-07 20:34:13');
INSERT INTO prospects (id, team_id, user_id, name, phone, email, source, stage, follow_up_date, notes, created_at) VALUES ('4', '4', '9', 'joy', '09077665544', 'info.travelcliques@gmail.com', 'Facebook', 'joined', '2026-03-10', 'hjyhn', '2026-03-09 00:23:48');
INSERT INTO prospects (id, team_id, user_id, name, phone, email, source, stage, follow_up_date, notes, created_at) VALUES ('5', '5', '11', 'Ejele', '08066554433', 'ejele@gmail.com', 'Instagram', 'joined', '2026-03-11', 'To do a reminder', '2026-03-09 07:29:26');
INSERT INTO prospects (id, team_id, user_id, name, phone, email, source, stage, follow_up_date, notes, created_at) VALUES ('6', '5', '11', 'Isa', '09088665543', 'isah@gamil.com', 'instagram', 'joined', '2026-03-10', 'Thank you', '2026-03-09 07:32:10');


CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO settings (id, setting_key, setting_value, created_at, updated_at) VALUES ('1', 'site_name', 'LeaderDesk', '2026-03-08 21:27:32', '2026-03-08 21:27:47');
INSERT INTO settings (id, setting_key, setting_value, created_at, updated_at) VALUES ('2', 'support_email', 'support@leaderdesk.com', '2026-03-08 21:27:32', '2026-03-08 21:27:32');
INSERT INTO settings (id, setting_key, setting_value, created_at, updated_at) VALUES ('3', 'trial_days', '60', '2026-03-08 21:27:32', '2026-03-08 21:27:32');
INSERT INTO settings (id, setting_key, setting_value, created_at, updated_at) VALUES ('4', 'monthly_price', '29', '2026-03-08 21:27:32', '2026-03-08 21:27:32');
INSERT INTO settings (id, setting_key, setting_value, created_at, updated_at) VALUES ('5', 'allow_registration', '1', '2026-03-08 21:27:32', '2026-03-08 21:27:32');
INSERT INTO settings (id, setting_key, setting_value, created_at, updated_at) VALUES ('6', 'require_verification', '0', '2026-03-08 21:27:32', '2026-03-08 21:27:32');


CREATE TABLE `targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `target_type` enum('daily','weekly','monthly') NOT NULL,
  `target_value` int(11) NOT NULL,
  `achieved_value` int(11) DEFAULT 0,
  `last_updated_by` int(11) DEFAULT NULL,
  `last_updated_at` timestamp NULL DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','completed','expired') DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  KEY `created_by` (`created_by`),
  KEY `assigned_to` (`assigned_to`),
  KEY `last_updated_by` (`last_updated_by`),
  CONSTRAINT `targets_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `targets_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `targets_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `targets_ibfk_4` FOREIGN KEY (`last_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO targets (id, team_id, created_by, assigned_to, title, description, target_type, target_value, achieved_value, last_updated_by, last_updated_at, start_date, end_date, status) VALUES ('2', '5', '10', NULL, 'Recruit', 'Recruit new members ', 'weekly', '2', '0', NULL, NULL, '2026-03-09', '2026-03-16', 'active');
INSERT INTO targets (id, team_id, created_by, assigned_to, title, description, target_type, target_value, achieved_value, last_updated_by, last_updated_at, start_date, end_date, status) VALUES ('3', '5', '10', NULL, 'Sales', 'Archieve 2m', 'weekly', '500000', '0', '12', '2026-03-09 21:27:07', '2026-03-09', '2026-03-16', 'active');
INSERT INTO targets (id, team_id, created_by, assigned_to, title, description, target_type, target_value, achieved_value, last_updated_by, last_updated_at, start_date, end_date, status) VALUES ('4', '5', '10', NULL, 'Team', 'Ensure to have 50 members in your team', 'monthly', '50', '25', '12', '2026-03-09 21:27:34', '2026-03-09', '2026-04-06', 'active');


CREATE TABLE `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `due_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed') DEFAULT 'pending',
  `points` int(11) DEFAULT 10,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  KEY `created_by` (`created_by`),
  KEY `assigned_to` (`assigned_to`),
  CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO tasks (id, team_id, created_by, assigned_to, title, description, due_date, due_time, location, attachment, status, points, created_at) VALUES ('1', '4', '9', '9', 'Knowledge Training', 'Products, usage, ansd pricing session', '2026-03-10', '08:30:00', 'Airport Hotel', 'uploads/tasks/1773016248_1.png', 'completed', '10', '2026-03-09 00:30:48');
INSERT INTO tasks (id, team_id, created_by, assigned_to, title, description, due_date, due_time, location, attachment, status, points, created_at) VALUES ('3', '5', '10', '11', 'Meeting Venue', 'Sort this out', '2026-03-20', '13:00:00', '', NULL, 'pending', '5', '2026-03-09 08:01:04');
INSERT INTO tasks (id, team_id, created_by, assigned_to, title, description, due_date, due_time, location, attachment, status, points, created_at) VALUES ('4', '5', '10', '11', 'Process', 'Create a welcome message to our team', '2026-03-11', '13:02:00', '', NULL, 'pending', '5', '2026-03-09 08:02:50');
INSERT INTO tasks (id, team_id, created_by, assigned_to, title, description, due_date, due_time, location, attachment, status, points, created_at) VALUES ('5', '5', '10', '12', 'Testing', 'Test as well', '2026-03-10', '01:04:00', '', NULL, 'pending', '5', '2026-03-09 08:05:04');
INSERT INTO tasks (id, team_id, created_by, assigned_to, title, description, due_date, due_time, location, attachment, status, points, created_at) VALUES ('7', '5', '10', '11', 'HDJKDN', 'HDDNDN', '2026-03-10', '16:40:00', 'https://meet.google.com/cup-oaxy-psc', 'uploads/tasks/1773095936_schengen-application-form-en.pdf', 'pending', '10', '2026-03-09 22:38:56');


CREATE TABLE `team_branding` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `tagline` varchar(200) DEFAULT NULL,
  `primary_color` varchar(7) DEFAULT '#4F46E5',
  `welcome_message` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_id` (`team_id`),
  CONSTRAINT `team_branding_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO team_branding (id, team_id, logo_url, tagline, primary_color, welcome_message, updated_at) VALUES ('1', '1', NULL, 'Build Your Empire', '#4F46E5', 'Welcome to Demo Team! Let\'s grow together.', '2026-03-07 17:57:27');
INSERT INTO team_branding (id, team_id, logo_url, tagline, primary_color, welcome_message, updated_at) VALUES ('2', '2', NULL, 'Build Your Empire', '#1a1a1a', 'Welcome to our team!', '2026-03-07 18:36:22');
INSERT INTO team_branding (id, team_id, logo_url, tagline, primary_color, welcome_message, updated_at) VALUES ('3', '3', NULL, 'Build Your Empire', '#1a1a1a', 'Welcome to our team!', '2026-03-08 21:29:17');
INSERT INTO team_branding (id, team_id, logo_url, tagline, primary_color, welcome_message, updated_at) VALUES ('4', '4', NULL, 'Build Your Empire', '#1a1a1a', 'Welcome to our team!', '2026-03-09 00:15:04');
INSERT INTO team_branding (id, team_id, logo_url, tagline, primary_color, welcome_message, updated_at) VALUES ('5', '5', NULL, 'Build Your Empire', '#1a1a1a', 'Welcome to our team!', '2026-03-09 07:24:52');


CREATE TABLE `teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_name` varchar(100) NOT NULL,
  `country` varchar(50) DEFAULT NULL,
  `state_province` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subscription_status` enum('trial','active','expired','suspended') DEFAULT 'trial',
  `trial_start_date` date DEFAULT NULL,
  `trial_end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_name` (`team_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO teams (id, team_name, country, state_province, email, phone, subscription_status, trial_start_date, trial_end_date, created_at) VALUES ('1', 'Demo Team', 'USA', 'California', 'demo@leaderdesk.com', '+1234567890', 'trial', '2026-03-07', '2026-05-06', '2026-03-07 17:57:27');
INSERT INTO teams (id, team_name, country, state_province, email, phone, subscription_status, trial_start_date, trial_end_date, created_at) VALUES ('2', 'Redmi', 'Nigeria', 'TEXAS', 'cindy.hinckley@hilton.com', '+23413463714473', 'trial', '2026-03-07', '2026-05-06', '2026-03-07 18:36:22');
INSERT INTO teams (id, team_name, country, state_province, email, phone, subscription_status, trial_start_date, trial_end_date, created_at) VALUES ('3', 'Kelto', 'Nigeria', 'Lagos', 'derulokell@gmail.com', '+23413463714473', 'trial', '2026-03-08', '2026-05-07', '2026-03-08 21:29:17');
INSERT INTO teams (id, team_name, country, state_province, email, phone, subscription_status, trial_start_date, trial_end_date, created_at) VALUES ('4', 'Divine Group', 'Nigeria', 'Lagos', 'mercy.ochiagha@gmail.com', '08022225582', 'trial', '2026-03-09', '2026-05-08', '2026-03-09 00:15:04');
INSERT INTO teams (id, team_name, country, state_province, email, phone, subscription_status, trial_start_date, trial_end_date, created_at) VALUES ('5', 'Fovoured', 'Nigeria', 'Benue', 'praimlabourxpert@gmail.com', '08022225582', 'trial', '2026-03-09', '2026-05-08', '2026-03-09 07:24:52');


CREATE TABLE `test_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) DEFAULT NULL,
  `option_d` varchar(255) DEFAULT NULL,
  `correct_answer` char(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `test_id` (`test_id`),
  CONSTRAINT `test_questions_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `tests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `training_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  KEY `created_by` (`created_by`),
  KEY `training_id` (`training_id`),
  CONSTRAINT `tests_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tests_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tests_ibfk_3` FOREIGN KEY (`training_id`) REFERENCES `trainings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `training_shares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prospect_id` int(11) NOT NULL,
  `shared_by` int(11) NOT NULL,
  `training_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `viewed_at` timestamp NULL DEFAULT NULL,
  `downloaded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `prospect_id` (`prospect_id`),
  KEY `shared_by` (`shared_by`),
  KEY `training_id` (`training_id`),
  CONSTRAINT `training_shares_ibfk_1` FOREIGN KEY (`prospect_id`) REFERENCES `prospects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_shares_ibfk_2` FOREIGN KEY (`shared_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_shares_ibfk_3` FOREIGN KEY (`training_id`) REFERENCES `trainings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `trainings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('getting_started','product','recruitment','leadership') NOT NULL,
  `content_type` enum('video_link','pdf','document','link') NOT NULL,
  `content_url` varchar(500) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `trainings_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trainings_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO trainings (id, team_id, uploaded_by, updated_by, title, description, category, content_type, content_url, file_path, created_at, updated_at) VALUES ('7', '5', '10', '10', 'TRAINING', 'TGH', 'recruitment', '', NULL, 'uploads/training/1773095776_69af4b6056481.pdf', '2026-03-09 22:36:16', '2026-03-09 22:45:48');
INSERT INTO trainings (id, team_id, uploaded_by, updated_by, title, description, category, content_type, content_url, file_path, created_at, updated_at) VALUES ('8', '5', '10', NULL, 'K', 'K', 'product', '', NULL, 'uploads/training/1773096394_69af4dca84289.png', '2026-03-09 22:46:34', NULL);


CREATE TABLE `user_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `awarded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `badge_id` (`badge_id`),
  KEY `team_id` (`team_id`),
  CONSTRAINT `user_badges_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_badges_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_badges_ibfk_3` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','team_leader','member') DEFAULT 'member',
  `upgrade_requested` tinyint(4) DEFAULT 0,
  `team_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO users (id, name, email, phone, password, role, upgrade_requested, team_id, status, created_at, updated_at, last_login) VALUES ('1', 'Super Admin', 'admin@leaderdesk.com', '+1234567890', '$2y$10$.lncIJAb3yIgD/Mk2VO/cOkoLHBxA30A4qlU4f.w5QgQi/4MixDAG', 'super_admin', '0', NULL, 'active', '2026-03-07 17:57:27', '2026-03-08 20:16:20', NULL);
INSERT INTO users (id, name, email, phone, password, role, upgrade_requested, team_id, status, created_at, updated_at, last_login) VALUES ('2', 'John Leader', 'john@example.com', '+1234567891', '$2y$10$YourHashedPasswordHere', 'team_leader', '0', '1', 'active', '2026-03-07 17:57:27', '2026-03-07 17:57:27', NULL);
INSERT INTO users (id, name, email, phone, password, role, upgrade_requested, team_id, status, created_at, updated_at, last_login) VALUES ('3', 'Jane Member', 'jane@example.com', '+1234567892', '$2y$10$U8kdmXVoQZf6VVLpe9hmt.O.bYpdiEhJqFzGy/ezDpRI5pqVTmjBO', 'member', '0', '1', 'active', '2026-03-07 17:57:27', '2026-03-10 19:44:09', NULL);
INSERT INTO users (id, name, email, phone, password, role, upgrade_requested, team_id, status, created_at, updated_at, last_login) VALUES ('4', 'Redmi', 'cindy.hinckley@hilton.com', '+23413463714473', '$2y$10$sEp3i6ANXsJHJEPQIJG8curL60TUYienAcw/ozTSlNRD9W9uFr8Ba', 'team_leader', '0', '2', 'active', '2026-03-07 18:36:22', '2026-03-07 18:36:22', NULL);
INSERT INTO users (id, name, email, phone, password, role, upgrade_requested, team_id, status, created_at, updated_at, last_login) VALUES ('7', 'Kelto', 'derulokell@gmail.com', '+23413463714473', '$2y$10$w5jdueuxXbWY2PeKTM2pzuoRDZCkVkyhnPvACcpfP3rfRV/nJsLey', 'team_leader', '0', '3', 'active', '2026-03-08 21:29:17', '2026-03-08 21:29:17', NULL);
INSERT INTO users (id, name, email, phone, password, role, upgrade_requested, team_id, status, created_at, updated_at, last_login) VALUES ('8', 'Ajuma M.OM', 'mercy.ochiagha@gmail.com', '08022225582', '$2y$10$70HqLr5mb2Aoz4W6FHBbeeLTzKXIMZe5SmbwLOOaaWqmTVh17nqVS', 'team_leader', '0', '4', 'active', '2026-03-09 00:15:04', '2026-03-09 00:15:04', NULL);
INSERT INTO users (id, name, email, phone, password, role, upgrade_requested, team_id, status, created_at, updated_at, last_login) VALUES ('9', 'Adaugo Kennedy', 'info.clicksint@gmail.com', '09044992296', '$2y$10$f1QavSZCF2GH2TCaIQkZ.uWXx/kIuWXHwygituNA5QMdGErPW/gCy', 'member', '0', '4', 'active', '2026-03-09 00:17:19', '2026-03-09 00:17:19', NULL);
INSERT INTO users (id, name, email, phone, password, role, upgrade_requested, team_id, status, created_at, updated_at, last_login) VALUES ('10', 'Felicia', 'praimlabourxpert@gmail.com', '08022225582', '$2y$10$u12okZK5SzbPmMwUvBNfbezMB.D5vgyHO5DFkVCfU7qXbkpE2KXsS', 'team_leader', '0', '5', 'active', '2026-03-09 07:24:52', '2026-03-09 07:24:52', NULL);
INSERT INTO users (id, name, email, phone, password, role, upgrade_requested, team_id, status, created_at, updated_at, last_login) VALUES ('11', 'Eche', 'eche@gmail.com', '08022225582', '$2y$10$UYawC4L0x8IApIlqhveTSurfJExrASKk91qiyVMjFMBU17Z/R6tty', 'member', '0', '5', 'active', '2026-03-09 07:26:18', '2026-03-09 07:26:18', NULL);
INSERT INTO users (id, name, email, phone, password, role, upgrade_requested, team_id, status, created_at, updated_at, last_login) VALUES ('12', 'Godwin', 'godwin@gmail.com', '09077665544', '$2y$10$y5Q8tgJp6z7yzLze5l/kvOHV8V0ceTDD9MdLiBIprhd/rMmTjU15a', 'member', '1', '5', 'active', '2026-03-09 07:40:19', '2026-03-09 21:37:16', NULL);
INSERT INTO users (id, name, email, phone, password, role, upgrade_requested, team_id, status, created_at, updated_at, last_login) VALUES ('13', 'Abass', 'abass@gmail.com', '98966554433', '$2y$10$icLoQSvB0VN7ycj.3BObX.CurbYYbenkLKMewjOAgz.khQ9o17fHu', 'member', '0', '5', 'active', '2026-03-09 07:51:47', '2026-03-09 07:51:47', NULL);
INSERT INTO users (id, name, email, phone, password, role, upgrade_requested, team_id, status, created_at, updated_at, last_login) VALUES ('14', 'Gladys', 'gladys@gmail.com', '98066554433', '$2y$10$WLBqJPhPxPwA22ZwUNOmjeYXyRJW3LpiFTlE0z8JrhwyoJDDrBo5a', 'member', '0', '5', 'active', '2026-03-09 07:54:29', '2026-03-09 07:54:29', NULL);
INSERT INTO users (id, name, email, phone, password, role, upgrade_requested, team_id, status, created_at, updated_at, last_login) VALUES ('15', 'Ejele', 'ejele@gmail.com', '08066554433', '$2y$10$.fN/zHFaBebLzx3Ud64NzODNdmDCwOnZubrk5IOmj8ywtuuBBlHMu', 'member', '0', '5', 'active', '2026-03-09 20:58:48', '2026-03-09 20:58:48', NULL);
