-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 23, 2026 at 04:44 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sacliconnect`
--

-- --------------------------------------------------------

--
-- Table structure for table `achievements`
--

CREATE TABLE `achievements` (
  `id` int(11) NOT NULL,
  `student_name` varchar(100) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `date_posted` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `achievements`
--

INSERT INTO `achievements` (`id`, `student_name`, `category`, `title`, `description`, `image`, `date_posted`) VALUES
(2, 'Top Student 1', 'Top Student', 'Dean\'s Lister', 'GWA: 1.25', '', '2026-02-22 09:52:21'),
(3, 'Contest Winner 1', 'Contest', 'Science Quiz Bee Champion', '1st Place Regional Level', '', '2026-02-22 09:52:21'),
(4, 'Athlete 1', 'Sports', 'MVP Volleyball', 'Led the team to championship', '', '2026-02-22 09:52:21'),
(6, 'Justin Ritardo', 'Featured', 'Dean Lister', 'GWA: 1.25', 'achievement_1772035796.png', '2026-02-26 00:09:56');

-- --------------------------------------------------------

--
-- Table structure for table `active_meetings`
--

CREATE TABLE `active_meetings` (
  `id` int(11) NOT NULL,
  `meeting_code` varchar(50) NOT NULL,
  `host_id` varchar(50) DEFAULT NULL,
  `status` enum('active','ended') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `active_meetings`
--

INSERT INTO `active_meetings` (`id`, `meeting_code`, `host_id`, `status`, `created_at`) VALUES
(1, 'ROOM-1500', 'T-15', 'active', '2026-03-22 12:57:31'),
(2, 'ROOM-1404', 'T-15', 'active', '2026-03-22 13:22:20');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`) VALUES
(1, 'Admin', '20913');

-- --------------------------------------------------------

--
-- Table structure for table `admins1`
--

CREATE TABLE `admins1` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins1`
--

INSERT INTO `admins1` (`id`, `username`, `password`) VALUES
(1, 'Admin_tim', '20913');

-- --------------------------------------------------------

--
-- Table structure for table `admins2`
--

CREATE TABLE `admins2` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins2`
--

INSERT INTO `admins2` (`id`, `username`, `password`, `profile_pic`, `email`, `otp_code`, `otp_expiry`) VALUES
(2, 'JustinAdmins2', '$2y$10$2Q2gDUNDw35fpmnwvTzeN.YAiRcl5gh0s/r2gM6tKsnd9Z7mwFnFe', NULL, NULL, NULL, NULL),
(6, 'Justin', '120302', 'admin_6_1772758601.jpg', 'timritardo1@gmail.com', NULL, NULL),
(9, 'Princess', '$2y$10$AUQIa0HoylWQ13.bBJLqle3Lu5dq.WuK8OrS2O.7Ka0r/GYzG5XoK', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admin_concerns`
--

CREATE TABLE `admin_concerns` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `sender_type` enum('student','admin') NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_concerns`
--

INSERT INTO `admin_concerns` (`id`, `student_id`, `message`, `sender_type`, `timestamp`, `is_read`) VALUES
(27, '123456789', 'hi Admin', 'student', '2026-03-06 12:13:50', 1),
(28, '123456789', 'hi what is your concern to your account?', 'admin', '2026-03-06 12:14:34', 1),
(29, '123456789', 'i forget my Password...', 'student', '2026-03-06 12:15:35', 1),
(30, '123456789', 'okay just accept the confimation in a notification', 'admin', '2026-03-06 12:17:33', 1),
(31, '123456789', 'okay just accept the confimation in a notification and ill send your new password on your Email', 'admin', '2026-03-06 12:18:34', 1),
(32, '123456789', 'Thank you', 'student', '2026-03-06 12:23:36', 1),
(33, '123456789', 'Always Welcome :)', 'admin', '2026-03-06 12:24:32', 1);

-- --------------------------------------------------------

--
-- Table structure for table `admin_security`
--

CREATE TABLE `admin_security` (
  `id` int(11) NOT NULL,
  `pin_code` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_security`
--

INSERT INTO `admin_security` (`id`, `pin_code`) VALUES
(1, '092025');

-- --------------------------------------------------------

--
-- Table structure for table `alumni`
--

CREATE TABLE `alumni` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `batch_year` varchar(20) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `status` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `alumni`
--

INSERT INTO `alumni` (`id`, `name`, `course`, `batch_year`, `profile_pic`, `birthdate`, `status`, `location`, `student_id`, `phone`) VALUES
(28, 'Justin Timothy N. Ritardo', 'BS Information Technology', '2027', 'alumni_1773631803.png', '2004-07-22', '', 'Australia, Adelaide', '121212', NULL),
(30, 'Princess Ritardo', 'BS Hospitality Management', '2026', 'alumni_1773851759.jpg', '2004-07-22', 'Supervisor', 'Australia', '131313', '0406147559'),
(32, 'Timmy Ritardo', 'BS Information Technology', '2019', 'alumni_1776777654.jpg', '2002-12-03', 'Software Engineer', 'Lucena city', '124578', NULL),
(33, 'testing', 'BS Nursing', '2019', '', '2002-12-03', '', 'Lucena city', '789', '');

-- --------------------------------------------------------

--
-- Table structure for table `calendar_events`
--

CREATE TABLE `calendar_events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `event_image` varchar(255) DEFAULT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `image_content` longblob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `calendar_events`
--

INSERT INTO `calendar_events` (`id`, `title`, `event_date`, `description`, `created_at`, `event_image`, `time_in`, `time_out`, `image_content`) VALUES
(14, 'New Year\'s Day ', '2026-01-01', 'Regular Holiday', '2026-02-16 21:34:34', '', NULL, NULL, NULL),
(15, 'Opening Of Classes', '2026-01-12', 'Welcome Back Student!!!', '2026-02-16 21:37:13', '', NULL, NULL, NULL),
(16, 'Last Day of Submission of Application for Graduation', '2026-01-30', 'Last Day of Submission of Application for Graduation', '2026-02-16 21:38:05', '', NULL, NULL, NULL),
(17, 'Submission of Test Papers for PRELIM Examinations', '2026-02-05', 'Submission of Test Papers for PRELIM Examinations', '2026-02-16 21:38:37', '', NULL, NULL, NULL),
(18, 'Preliminary Examination', '2026-02-17', 'Preliminary Examination', '2026-02-16 21:39:08', '', NULL, NULL, NULL),
(19, 'Preliminary Examination Day 2', '2026-02-18', 'Preliminary Examination Day 2', '2026-02-16 21:39:35', '', NULL, NULL, NULL),
(20, 'Preliminary Examination Day 3', '2026-02-19', 'Preliminary Examination Day 3', '2026-02-16 21:39:53', '', NULL, NULL, NULL),
(22, 'People Power Anniversary', '2026-02-25', 'People Power Anniversary', '2026-02-16 21:47:33', 'uploads/event_1771249653.jpg', NULL, NULL, NULL),
(25, '40th Foundation Anniversary Celebration', '2026-03-03', 'Day 1 Of 40th Foundation', '2026-02-16 21:51:04', 'uploads/event_1771249864.jpg', NULL, NULL, NULL),
(27, '	40th Foundation Anniversary Celebration', '2026-03-04', 'Day 2 Of 40th Foundation', '2026-02-16 21:55:19', 'uploads/event_1771250119.jpg', NULL, NULL, NULL),
(28, '40th Foundation Anniversary Celebration', '2026-03-05', 'Day 3 Of 40th Foundation', '2026-02-16 22:23:27', 'uploads/event_1771251807.jpg', NULL, NULL, NULL),
(30, '	40th Foundation Anniversary Celebration', '2026-03-06', 'Last Day Of 40th Foundation', '2026-02-16 22:25:13', 'uploads/event_1771251913.jpg', NULL, NULL, NULL),
(31, 'Submission of Prelim Grades (Portal)', '2026-03-14', '4 – Submission of Prelim Grades (Portal)', '2026-02-16 22:27:56', '', NULL, NULL, NULL),
(32, 'Submission of Test Papers for MIDTERM Examinations', '2026-03-21', 'Submission of Test Papers for MIDTERM Examinations', '2026-02-16 22:28:34', '', NULL, NULL, NULL),
(33, 'Maundy Thursday (Regular Holiday)', '2026-04-02', 'Maundy Thursday (Regular Holiday)', '2026-02-16 22:29:01', '', NULL, NULL, NULL),
(34, 'Good Friday (Regular Holiday)', '2026-04-03', 'Good Friday (Regular Holiday)', '2026-02-16 22:29:17', '', NULL, NULL, NULL),
(35, 'Black Saturday ', '2026-04-04', 'Special Non-Working Holiday', '2026-02-16 22:29:50', '', NULL, NULL, NULL),
(36, 'Midterm Examination', '2026-04-06', 'Day 1 Midterm Examination', '2026-02-16 22:30:38', '', NULL, NULL, NULL),
(37, 'Midterm Examination', '2026-04-07', 'Day 2 Midterm Examination', '2026-02-16 22:31:12', '', NULL, NULL, NULL),
(38, 'Midterm Examination', '2026-04-08', 'Day 3 Midterm Examination', '2026-02-16 22:31:32', '', NULL, NULL, NULL),
(39, 'Midterm Examination', '2026-04-09', 'Day 4 Midterm Examination', '2026-02-16 22:31:53', '', NULL, NULL, NULL),
(40, 'Midterm Examination', '2026-04-10', 'Day 5 Midterm Examination', '2026-02-16 22:32:14', '', NULL, NULL, NULL),
(41, 'Araw ng Kagitingan', '2026-04-19', 'Regular Holiday', '2026-02-16 22:33:17', '', NULL, NULL, NULL),
(42, 'Submission of Midterm Grades (Portal)', '2026-04-22', 'Submission of Midterm Grades (Portal)', '2026-02-16 22:33:43', '', NULL, NULL, NULL),
(43, 'Final Examinations for Graduating Students', '2026-04-27', 'Final Examinations for Graduating Students', '2026-02-16 22:34:19', '', NULL, NULL, NULL),
(44, 'Final Examinations for Graduating Students', '2026-04-28', 'Final Examinations for Graduating Students', '2026-02-16 22:34:50', '', NULL, NULL, NULL),
(45, 'Submission of Test Papers for FINAL Examinations', '2026-04-30', 'Submission of Test Papers for FINAL Examinations', '2026-02-16 22:35:04', '', NULL, NULL, NULL),
(46, 'Labor Day', '2026-05-01', '(Regular Holiday)', '2026-02-16 22:35:32', '', NULL, NULL, NULL),
(47, 'Submission of Grades of Graduating Students', '2026-05-05', 'Submission of Grades of Graduating Students', '2026-02-16 22:35:53', '', NULL, NULL, NULL),
(49, 'Deliberation of Graduating Students ', '2026-05-07', '(by Department)', '2026-02-16 22:37:06', '', NULL, NULL, NULL),
(50, 'Deliberation of Graduating Students', '2026-05-08', '(by Department)', '2026-02-16 22:37:24', '', NULL, NULL, NULL),
(51, 'Submission of Final List of Candidates for Graduation', '2026-05-12', 'Submission of Final List of Candidates for Graduation', '2026-02-16 22:37:44', '', NULL, NULL, NULL),
(52, 'Final Examinations', '2026-05-12', 'Day 1 Of Final Examinations', '2026-02-16 22:38:13', '', NULL, NULL, NULL),
(54, 'Final Examinations', '2026-05-13', 'Day 2 Of Final Examinations', '2026-02-16 22:39:26', '', NULL, NULL, NULL),
(55, 'Final Examinations', '2026-05-14', 'Day 3 Of Final Examinations', '2026-02-16 22:39:55', '', NULL, NULL, NULL),
(56, 'Final Examinations', '2026-05-15', 'Day 4 Of Final Examinations', '2026-02-16 22:40:26', '', NULL, NULL, NULL),
(57, 'Last Day of Classes ', '2026-05-16', '(2nd Semester)', '2026-02-16 22:41:20', '', NULL, NULL, NULL),
(58, 'Start of Summer Classes', '2026-05-25', 'Start of Summer Classes', '2026-02-16 22:41:50', '', NULL, NULL, NULL),
(59, 'Submission of FINAL Grade', '2026-05-26', 'Submission of FINAL Grade', '2026-02-16 22:42:09', '', NULL, NULL, NULL),
(60, 'Midterm Examinations ', '2026-06-11', '(Summer Classes) Exam Day 1', '2026-02-16 22:42:49', '', NULL, NULL, NULL),
(61, 'Midterm Examinations', '2026-06-12', '(Summer Classes) Exam Day 2', '2026-02-16 22:43:19', '', NULL, NULL, NULL),
(62, 'Independence Day ', '2026-06-12', '(Regular Holiday)', '2026-02-16 22:43:45', '', NULL, NULL, NULL),
(63, 'Final Examinations ', '2026-07-02', '(Summer Classes) Exam Day 1', '2026-02-16 22:44:24', '', NULL, NULL, NULL),
(65, 'Final Examinations', '2026-07-03', '(Summer Classes) Exam Day 2', '2026-02-16 22:45:37', '', NULL, NULL, NULL),
(66, 'Final Examinations', '2026-07-04', '(Summer Classes) Last Day Of Examination', '2026-02-16 22:46:30', '', NULL, NULL, NULL),
(67, '39th Commencement Exercises ', '2026-07-09', '(Tentative)', '2026-02-16 22:46:58', '', NULL, NULL, NULL),
(69, '22th Birthday Princess', '2026-07-22', 'Happy Birthday BEBE!!!', '2026-02-19 16:14:55', 'uploads/event_1771488895.jpg', NULL, NULL, NULL),
(70, '5th Monthsary', '2026-02-20', '', '2026-02-20 10:18:26', 'uploads/event_1771553906.jpg', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `chat_clear_history`
--

CREATE TABLE `chat_clear_history` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `other_id` varchar(50) DEFAULT NULL,
  `cleared_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_clear_history`
--

INSERT INTO `chat_clear_history` (`id`, `user_id`, `other_id`, `cleared_at`) VALUES
(1, '20913', '1701-00352', '2026-05-18 08:52:38'),
(4, '1701-00352', '147852369', '2026-05-20 12:01:35'),
(5, '1701-00352', 'null', '2026-05-21 06:57:45'),
(10, '1701-00352', '121212', '2026-05-20 12:02:03'),
(12, '1701-00352', 'Admin', '2026-05-20 23:10:51'),
(16, '1701-00352', '2401-00186', '2026-06-08 19:32:12');

-- --------------------------------------------------------

--
-- Table structure for table `chat_media`
--

CREATE TABLE `chat_media` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `chat_type` enum('direct','group') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_content` longblob DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_media`
--

INSERT INTO `chat_media` (`id`, `message_id`, `chat_type`, `file_path`, `file_type`, `file_content`, `uploaded_at`) VALUES
(1, 390, 'direct', 'uploads/chat_1779249303_6a0d30976d4a5.jpg', 'photo', NULL, '2026-05-20 11:55:03'),
(2, 391, 'direct', 'uploads/chat_1779249328_6a0d30b0c66f9.jpg', 'photo', NULL, '2026-05-20 11:55:28'),
(3, 391, 'direct', 'uploads/chat_1779249328_6a0d30b0c8061.jpg', 'photo', NULL, '2026-05-20 11:55:28'),
(4, 391, 'direct', 'uploads/chat_1779249328_6a0d30b0c9bd4.jpg', 'photo', NULL, '2026-05-20 11:55:28'),
(5, 416, 'direct', 'uploads/chat_1779276793_6a0d9bf9e7dcb.mp4', 'video', NULL, '2026-05-20 19:33:13'),
(6, 418, 'direct', 'uploads/chat_1779317042_6a0e39325676c.png', 'photo', NULL, '2026-05-21 06:44:02'),
(7, 418, 'direct', 'uploads/chat_1779317042_6a0e39325896a.png', 'photo', NULL, '2026-05-21 06:44:02'),
(8, 418, 'direct', 'uploads/chat_1779317042_6a0e39325bd90.jpg', 'photo', NULL, '2026-05-21 06:44:02'),
(9, 418, 'direct', 'uploads/chat_1779317042_6a0e39325e4a3.jpg', 'photo', NULL, '2026-05-21 06:44:02'),
(10, 418, 'direct', 'uploads/chat_1779317042_6a0e3932605ac.jpg', 'photo', NULL, '2026-05-21 06:44:02'),
(11, 418, 'direct', 'uploads/chat_1779317042_6a0e3932629c6.jpg', 'photo', NULL, '2026-05-21 06:44:02'),
(12, 418, 'direct', 'uploads/chat_1779317042_6a0e3932651c7.jpg', 'photo', NULL, '2026-05-21 06:44:02'),
(13, 419, 'direct', 'uploads/chat_1779317091_6a0e3963ceadd.jpg', 'photo', NULL, '2026-05-21 06:44:51'),
(14, 419, 'direct', 'uploads/chat_1779317091_6a0e3963d02ee.jpg', 'photo', NULL, '2026-05-21 06:44:51'),
(15, 419, 'direct', 'uploads/chat_1779317091_6a0e3963d1a1f.jpg', 'photo', NULL, '2026-05-21 06:44:51'),
(16, 458, 'direct', 'uploads/chat_1779443797_6a102855de9bd.pdf', 'file', NULL, '2026-05-22 17:56:37'),
(17, 459, 'direct', 'uploads/chat_1779505529_6a111979307b7.pptx', 'file', NULL, '2026-05-23 11:05:29');

-- --------------------------------------------------------

--
-- Table structure for table `direct_chat_themes`
--

CREATE TABLE `direct_chat_themes` (
  `user1_id` varchar(50) NOT NULL,
  `user2_id` varchar(50) NOT NULL,
  `theme` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `direct_chat_themes`
--

INSERT INTO `direct_chat_themes` (`user1_id`, `user2_id`, `theme`) VALUES
('1701-00352', '20913', 'space'),
('1701-00352', '2301-000111', 'midnight'),
('1701-00352', '2401-00186', 'space'),
('20913', '2301-000111', 'flashlight'),
('20913', '2301-000428', 'space');

-- --------------------------------------------------------

--
-- Table structure for table `direct_messages`
--

CREATE TABLE `direct_messages` (
  `id` int(11) NOT NULL,
  `sender_id` varchar(50) DEFAULT NULL,
  `receiver_id` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `media` varchar(255) DEFAULT NULL,
  `media_type` varchar(20) DEFAULT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `media_content` longblob DEFAULT NULL,
  `is_unsent` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `direct_messages`
--

INSERT INTO `direct_messages` (`id`, `sender_id`, `receiver_id`, `message`, `timestamp`, `is_read`, `media`, `media_type`, `is_pinned`, `media_content`, `is_unsent`) VALUES
(324, '20913', '1701-00352', 'asdad', '2026-05-18 13:18:02', 1, NULL, NULL, 0, NULL, 0),
(325, '20913', '1701-00352', 'asdad', '2026-05-18 13:18:11', 1, NULL, NULL, 0, NULL, 0),
(326, '20913', '1701-00352', 'asdad', '2026-05-18 13:18:13', 1, NULL, NULL, 0, NULL, 0),
(332, '20913', '1701-00352', 'Love', '2026-05-18 19:31:12', 1, NULL, NULL, 0, NULL, 0),
(333, '1701-00352', '20913', 'Yes love, bkt love', '2026-05-18 19:31:38', 1, NULL, NULL, 0, NULL, 0),
(334, '20913', '1701-00352', 'pauwi kana ba', '2026-05-18 19:31:51', 1, NULL, NULL, 0, NULL, 0),
(335, '20913', '1701-00352', 'Kanina pa ako nag aantay', '2026-05-18 19:32:06', 1, NULL, NULL, 0, NULL, 0),
(336, '1701-00352', '20913', 'Malapit na ako matapos love', '2026-05-18 19:32:43', 1, NULL, NULL, 0, NULL, 0),
(337, '20913', '1701-00352', 'intayin mo nalng ako sa bahay bebe', '2026-05-18 19:33:10', 1, NULL, NULL, 0, NULL, 0),
(338, '1701-00352', '20913', 'okay love', '2026-05-18 19:33:18', 1, NULL, NULL, 0, NULL, 0),
(339, '1701-00352', '20913', 'pauwi narin ako mamaya', '2026-05-18 19:33:31', 1, NULL, NULL, 0, NULL, 0),
(340, '20913', '1701-00352', 'okay love you', '2026-05-18 19:33:45', 1, NULL, NULL, 0, NULL, 0),
(341, '1701-00352', '20913', 'Iloveyoutoo', '2026-05-18 19:33:57', 1, NULL, NULL, 0, NULL, 0),
(342, '20913', '1701-00352', 'changed the theme to Celestial Space', '2026-05-18 21:37:48', 1, NULL, 'system', 0, NULL, 0),
(343, '1701-00352', '20913', 'BEBE', '2026-05-18 21:40:14', 1, NULL, NULL, 0, NULL, 0),
(344, '1701-00352', '20913', 'anong gawa mo', '2026-05-18 21:40:21', 1, NULL, NULL, 0, NULL, 0),
(345, '20913', '1701-00352', 'natutulog na', '2026-05-18 21:40:35', 1, NULL, NULL, 0, NULL, 0),
(346, '20913', '1701-00352', 'Bukas nalng tayo mag gawa sa Programing mo bebe, puyat na puyat talaga akong nung nakaraan na araw, need kong bumawi ng tulog bebe', '2026-05-18 21:41:25', 1, NULL, NULL, 0, NULL, 0),
(347, '1701-00352', '20913', 'Okay love', '2026-05-18 21:41:56', 1, NULL, NULL, 0, NULL, 0),
(348, '1701-00352', '20913', 'bukas na nga lng tayo mag gawa nung sa program, pahinga kana muna iloveyou bebe', '2026-05-18 21:42:24', 1, NULL, NULL, 0, NULL, 0),
(349, '20913', '1701-00352', 'ilove you bebe', '2026-05-18 21:51:10', 1, NULL, NULL, 0, NULL, 0),
(350, '20913', '1701-00352', 'Goodnight bebe', '2026-05-18 21:55:42', 1, NULL, NULL, 0, NULL, 0),
(351, '1701-00352', '20913', 'Goodnight bebe', '2026-05-18 21:56:24', 1, NULL, NULL, 0, NULL, 0),
(352, '1701-00352', '20913', 'changed the theme to Stormy Rain', '2026-05-18 22:03:31', 1, NULL, 'system', 0, NULL, 0),
(353, '1701-00352', '20913', 'changed the theme to Default Neural', '2026-05-19 07:13:38', 1, NULL, 'system', 0, NULL, 0),
(354, '20913', '2301-000111', 'sdfsd', '2026-05-19 09:55:55', 0, NULL, NULL, 0, NULL, 0),
(355, '20913', '2301-000111', 'sdfsdf', '2026-05-19 09:55:58', 0, NULL, NULL, 0, NULL, 0),
(356, '20913', '2301-000111', 'sdf', '2026-05-19 09:55:59', 0, NULL, NULL, 0, NULL, 0),
(357, '20913', '2301-000111', 'sdf', '2026-05-19 09:55:59', 0, NULL, NULL, 0, NULL, 0),
(361, '1701-00352', '20913', 'love', '2026-05-19 09:59:13', 1, NULL, NULL, 0, NULL, 0),
(362, '1701-00352', '20913', 'nasaan ka now', '2026-05-19 09:59:27', 1, NULL, NULL, 0, NULL, 0),
(363, '1701-00352', '20913', 'seen lng', '2026-05-19 09:59:40', 1, NULL, NULL, 0, NULL, 0),
(364, '20913', '2301-000111', 'changed the theme to Flashlight Dark', '2026-05-19 10:25:50', 0, NULL, 'system', 0, NULL, 0),
(365, '1701-00352', '20913', 'changed the theme to Celestial Space', '2026-05-19 10:35:21', 1, NULL, 'system', 0, NULL, 0),
(366, '1701-00352', '20913', 'love', '2026-05-19 10:53:13', 1, NULL, NULL, 0, NULL, 0),
(367, '20913', '1701-00352', 'bkt bebe', '2026-05-19 10:53:18', 1, NULL, NULL, 0, NULL, 0),
(368, '1701-00352', '20913', 'gawa mo', '2026-05-19 10:53:21', 1, NULL, NULL, 0, NULL, 0),
(369, '20913', '1701-00352', 'sorry nag aayus ako kanina', '2026-05-19 10:53:46', 1, NULL, NULL, 0, NULL, 0),
(370, '1701-00352', '20913', 'okay bebe', '2026-05-19 10:53:54', 1, NULL, NULL, 0, NULL, 0),
(372, '20913', '1701-00352', 'bjjbjb', '2026-05-19 14:05:11', 1, NULL, NULL, 0, NULL, 0),
(373, '1701-00352', '20913', 'hghghg', '2026-05-19 14:05:51', 1, NULL, NULL, 0, NULL, 0),
(374, '1701-00352', '20913', 'jhjh', '2026-05-19 14:05:59', 1, NULL, NULL, 0, NULL, 0),
(377, '20913', '1701-00352', 'nnh', '2026-05-19 21:19:27', 1, NULL, NULL, 0, NULL, 0),
(378, '20913', '1701-00352', 'jhb', '2026-05-19 21:19:28', 1, NULL, NULL, 0, NULL, 0),
(379, '20913', '1701-00352', '@Justin Ritardo', '2026-05-19 21:47:02', 1, NULL, NULL, 0, NULL, 0),
(380, '20913', '2301-000111', 'sfd', '2026-05-19 21:47:48', 0, NULL, NULL, 0, NULL, 0),
(381, NULL, '1701-00352', 'hfgcg', '2026-05-19 21:58:39', 0, NULL, NULL, 0, NULL, 0),
(382, NULL, 'A23-8725', 'werwer', '2026-05-19 21:59:01', 0, NULL, NULL, 0, NULL, 0),
(383, '20913', '1701-00352', 'changed the theme to Celestial Space', '2026-05-19 22:06:41', 1, NULL, 'system', 0, NULL, 0),
(384, '1701-00352', '147852369', 'jnjn', '2026-05-19 22:06:59', 0, NULL, NULL, 0, NULL, 0),
(385, '1701-00352', '20913', 'love', '2026-05-19 22:07:10', 1, NULL, NULL, 0, NULL, 0),
(386, '20913', '1701-00352', 'yes bebe', '2026-05-19 22:07:25', 1, NULL, NULL, 0, NULL, 0),
(387, '1701-00352', '20913', '', '2026-05-20 11:33:26', 1, 'uploads/chat_1779248006_6a0d2b8642202.jpg', 'photo', 0, NULL, 0),
(388, '1701-00352', '20913', '', '2026-05-20 11:33:33', 1, 'uploads/chat_1779248013_6a0d2b8dba4ce.jpg', 'photo', 0, NULL, 0),
(389, '1701-00352', '20913', 'sdf', '2026-05-20 11:33:40', 1, NULL, NULL, 0, NULL, 0),
(390, '1701-00352', '20913', '', '2026-05-20 11:55:03', 1, NULL, NULL, 0, NULL, 0),
(391, '1701-00352', '20913', '', '2026-05-20 11:55:28', 1, NULL, NULL, 0, NULL, 0),
(392, '1701-00352', '20913', 'changed the theme to Celestial Space', '2026-05-20 11:59:48', 1, NULL, 'system', 0, NULL, 0),
(393, '1701-00352', '20913', 'asdasdas', '2026-05-20 12:00:02', 1, NULL, NULL, 0, NULL, 0),
(394, '20913', '1701-00352', 'bebe ka,usta ka', '2026-05-20 12:01:04', 1, NULL, NULL, 0, NULL, 0),
(395, '20913', '1701-00352', 'anong ginagawa mo', '2026-05-20 12:01:20', 1, NULL, NULL, 0, NULL, 0),
(396, '1701-00352', '20913', 'wala bebe nag cocode', '2026-05-20 12:02:28', 1, NULL, NULL, 0, NULL, 0),
(397, '1701-00352', '2301-000111', 'changed the theme to Celestial Space', '2026-05-20 12:06:09', 0, NULL, 'system', 0, NULL, 0),
(398, '1701-00352', '2301-000111', 'changed the theme to Default Neural', '2026-05-20 12:06:36', 0, NULL, 'system', 0, NULL, 0),
(399, '20913', '1701-00352', 'changed the theme to Celestial Space', '2026-05-20 17:44:57', 1, NULL, 'system', 0, NULL, 0),
(400, '1701-00352', '20913', 'lovevvsdadas', '2026-05-20 17:54:39', 1, NULL, NULL, 0, NULL, 0),
(401, '1701-00352', '20913', 'asdasdasdasd', '2026-05-20 18:04:39', 1, NULL, NULL, 0, NULL, 0),
(402, '1701-00352', '20913', 'asdasd', '2026-05-20 18:04:45', 1, NULL, NULL, 0, NULL, 0),
(403, '1701-00352', '20913', 'asdasd', '2026-05-20 18:04:52', 1, NULL, NULL, 0, NULL, 0),
(404, '20913', '1701-00352', 'sdfsdfsdf', '2026-05-20 18:05:11', 1, NULL, NULL, 0, NULL, 0),
(405, '20913', '1701-00352', 'asdad', '2026-05-20 18:06:14', 1, NULL, NULL, 0, NULL, 0),
(406, '20913', '1701-00352', 'sdfsdf', '2026-05-20 18:20:33', 1, NULL, NULL, 0, NULL, 0),
(407, '1701-00352', '20913', 'asdas', '2026-05-20 18:20:38', 1, NULL, NULL, 0, NULL, 0),
(408, '20913', '1701-00352', 'sdfsdf', '2026-05-20 18:20:43', 1, NULL, NULL, 0, NULL, 0),
(409, '20913', '1701-00352', 'sdfsdf', '2026-05-20 18:20:49', 1, NULL, NULL, 0, NULL, 0),
(410, '20913', '1701-00352', 'sdfsdf', '2026-05-20 18:20:57', 1, NULL, NULL, 0, NULL, 0),
(411, '20913', '1701-00352', 'asdasd', '2026-05-20 18:47:51', 1, NULL, NULL, 0, NULL, 0),
(412, '1701-00352', '20913', 'asdas', '2026-05-20 18:47:58', 1, NULL, NULL, 0, NULL, 0),
(413, '1701-00352', '20913', 'sdfsdf', '2026-05-20 18:49:15', 1, NULL, NULL, 0, NULL, 0),
(414, '20913', '1701-00352', 'asdasd', '2026-05-20 18:49:19', 1, NULL, NULL, 0, NULL, 0),
(415, '1701-00352', '20913', 'AaaS', '2026-05-20 19:07:44', 1, NULL, NULL, 1, NULL, 0),
(416, '1701-00352', '20913', '', '2026-05-20 19:33:13', 1, NULL, NULL, 0, NULL, 0),
(417, '1701-00352', '20913', 'changed the theme to Celestial Space', '2026-05-20 23:05:43', 1, NULL, 'system', 0, NULL, 0),
(418, '1701-00352', '20913', '', '2026-05-21 06:44:02', 1, NULL, NULL, 0, NULL, 0),
(420, '1701-00352', '2301-000111', 'changed the theme to Stormy Rain', '2026-05-21 06:49:03', 0, NULL, 'system', 0, NULL, 0),
(421, '1701-00352', '20913', 'uut', '2026-05-21 07:08:50', 1, NULL, NULL, 0, NULL, 0),
(422, '1701-00352', '20913', 'kkkmkm', '2026-05-21 07:15:24', 1, NULL, NULL, 0, NULL, 0),
(423, '1701-00352', '20913', 'bebe', '2026-05-21 07:50:29', 1, NULL, NULL, 0, NULL, 0),
(424, '20913', '1701-00352', 'yes bebe', '2026-05-21 07:50:47', 1, NULL, NULL, 0, NULL, 0),
(425, '1701-00352', '20913', 'nasaan ka now', '2026-05-21 07:50:55', 1, NULL, NULL, 0, NULL, 0),
(426, '1701-00352', '20913', 'uyuuyuyuy', '2026-05-21 08:54:07', 1, NULL, NULL, 0, NULL, 0),
(427, '20913', '1701-00352', 'sdfsdf', '2026-05-21 11:02:07', 1, NULL, NULL, 0, NULL, 0),
(428, '20913', '1701-00352', 'sdfsd', '2026-05-21 11:02:23', 1, NULL, NULL, 0, NULL, 0),
(429, '20913', '1701-00352', 'asdsd', '2026-05-21 11:02:53', 1, NULL, NULL, 0, NULL, 0),
(430, '20913', '1701-00352', 'sfsd', '2026-05-21 11:03:15', 1, NULL, NULL, 0, NULL, 0),
(431, '20913', '1701-00352', 'sdsdfsd', '2026-05-21 11:03:41', 1, NULL, NULL, 0, NULL, 0),
(432, '1701-00352', '20913', 'sadasd', '2026-05-21 11:05:12', 1, NULL, NULL, 0, NULL, 0),
(433, '20913', '1701-00352', 'sadas', '2026-05-21 11:05:19', 1, NULL, NULL, 0, NULL, 0),
(434, '1701-00352', '20913', 'sdsds', '2026-05-21 11:12:46', 1, NULL, NULL, 0, NULL, 0),
(435, '20913', '1701-00352', 'dfd', '2026-05-21 12:01:50', 1, NULL, NULL, 0, NULL, 0),
(436, '1701-00352', '20913', 'dfdf', '2026-05-21 12:01:52', 1, NULL, NULL, 0, NULL, 0),
(437, '1701-00352', '20913', 'dfdf', '2026-05-21 12:02:00', 1, NULL, NULL, 0, NULL, 0),
(438, '20913', '1701-00352', 'love', '2026-05-21 12:02:40', 1, NULL, NULL, 0, NULL, 0),
(439, '20913', '1701-00352', 'nasaan ka', '2026-05-21 12:02:50', 1, NULL, NULL, 0, NULL, 0),
(440, '20913', '1701-00352', 'love', '2026-05-21 12:03:04', 1, NULL, NULL, 0, NULL, 0),
(441, '1701-00352', '20913', 'k;m.l', '2026-05-21 14:44:51', 1, NULL, NULL, 0, NULL, 0),
(442, '1701-00352', '2301-000111', 'changed the theme to Midnight Sky', '2026-05-21 22:44:07', 0, NULL, 'system', 0, NULL, 0),
(443, '20913', '1701-00352', 'bebe', '2026-05-22 09:49:52', 1, NULL, NULL, 0, NULL, 0),
(444, '20913', '1701-00352', 'bebe', '2026-05-22 09:50:07', 1, NULL, NULL, 0, NULL, 0),
(445, '1701-00352', '20913', 'fgdgdfgdfgdfdfgdf', '2026-05-22 09:51:11', 1, NULL, NULL, 0, NULL, 0),
(446, '20913', '1701-00352', 'love', '2026-05-22 09:51:35', 1, NULL, NULL, 0, NULL, 0),
(447, '20913', '1701-00352', 'nasaan ka', '2026-05-22 09:51:51', 1, NULL, NULL, 0, NULL, 0),
(448, '1701-00352', '20913', 'jvygy', '2026-05-22 10:46:25', 1, NULL, NULL, 0, NULL, 0),
(451, '1701-00352', '20913', 'wereewrewerwerewr', '2026-05-22 11:03:08', 1, NULL, NULL, 0, NULL, 0),
(452, '1701-00352', '20913', 'uikdjfsgfdjjkfmfuifvbnfmgfngdf', '2026-05-22 12:04:41', 1, NULL, NULL, 0, NULL, 0),
(453, '20913', '1701-00352', 'changed the theme to Midnight Sky', '2026-05-22 12:07:06', 1, NULL, 'system', 0, NULL, 0),
(454, '20913', '1701-00352', 'changed the theme to Celestial Space', '2026-05-22 12:14:17', 1, NULL, 'system', 0, NULL, 0),
(456, '20913', '1701-00352', 'changed the theme to Midnight Sky', '2026-05-22 17:55:48', 1, NULL, 'system', 0, NULL, 0),
(457, '20913', '1701-00352', 'changed the theme to Celestial Space', '2026-05-22 17:55:56', 1, NULL, 'system', 0, NULL, 0),
(458, '20913', '1701-00352', '', '2026-05-22 17:56:37', 1, NULL, NULL, 0, NULL, 0),
(459, '1701-00352', '20913', '', '2026-05-23 11:05:29', 1, NULL, NULL, 0, NULL, 0),
(461, '1701-00352', '147852369', 'qweqwe', '2026-05-23 15:30:42', 0, NULL, NULL, 0, NULL, 0),
(462, '1701-00352', '2301-000076', 'qweqw', '2026-05-23 15:30:47', 1, NULL, NULL, 0, NULL, 0),
(464, '1701-00352', 'A23-8725', 'qweqeq', '2026-05-23 15:30:55', 0, NULL, NULL, 0, NULL, 0),
(465, '1701-00352', 'T-17', 'erewrwer', '2026-05-23 15:31:06', 0, NULL, NULL, 0, NULL, 0),
(466, '1701-00352', '2401-00186', 'ghghghg', '2026-05-24 01:18:52', 1, NULL, NULL, 0, NULL, 0),
(467, '2401-00186', '1701-00352', 'changed the theme to Celestial Space', '2026-05-24 01:19:15', 1, NULL, 'system', 0, NULL, 0),
(468, '2401-00186', '1701-00352', 'dfdddghghghghgh', '2026-05-24 01:19:29', 1, NULL, NULL, 0, NULL, 0),
(469, '2401-00186', '1701-00352', 'fggfgfg', '2026-05-24 01:19:35', 1, NULL, NULL, 0, NULL, 0),
(470, '1701-00352', '20913', 'beb', '2026-05-27 11:14:44', 1, NULL, NULL, 0, NULL, 0),
(471, '1701-00352', '20913', 'sdfsfsdfsdsdfsdfs', '2026-05-28 12:42:46', 1, NULL, NULL, 0, NULL, 0),
(472, '1701-00352', '20913', 'Love nasaan kana', '2026-05-30 13:53:04', 1, NULL, NULL, 0, NULL, 0),
(473, '20913', '1701-00352', 'nandito pa sa bus nag aantay pa ako bebe', '2026-05-30 13:53:28', 1, NULL, NULL, 0, NULL, 0),
(474, '1701-00352', '20913', 'okay bebe', '2026-05-30 13:54:45', 1, NULL, NULL, 0, NULL, 0),
(475, '1701-00352', '20913', 'wait nalng kita maka uwi tawag ka nalng sa messenger at walng videocall dito sa ginawa ko', '2026-05-30 13:55:19', 1, NULL, NULL, 0, NULL, 0),
(476, '20913', '1701-00352', 'okay love wait mo nalng ako tumawag', '2026-05-30 13:55:58', 1, NULL, NULL, 0, NULL, 0),
(477, '20913', '1701-00352', 'may bus na rin bebe', '2026-05-30 13:56:06', 1, NULL, NULL, 0, NULL, 0),
(478, '1701-00352', '20913', 'love nasaan kanang part', '2026-05-30 14:23:28', 1, NULL, NULL, 0, NULL, 0),
(479, '1701-00352', '20913', 'iniintay kasi kita eh', '2026-05-30 14:24:00', 1, NULL, NULL, 0, NULL, 0),
(480, '20913', '1701-00352', 'malapit na bebe', '2026-05-30 14:24:13', 1, NULL, NULL, 0, NULL, 0),
(489, '1701-00352', '20913', 'sdsd', '2026-06-05 20:24:28', 1, NULL, NULL, 0, NULL, 0),
(490, '1701-00352', '20913', '', '2026-06-05 20:24:43', 1, NULL, NULL, 0, NULL, 1),
(492, '1701-00352', '20913', '', '2026-06-06 08:54:46', 1, NULL, NULL, 0, NULL, 1),
(493, '1701-00352', '20913', '', '2026-06-09 12:39:29', 1, NULL, NULL, 0, NULL, 1),
(494, '1701-00352', '20913', '', '2026-06-09 12:39:38', 1, NULL, NULL, 0, NULL, 1),
(495, '1701-00352', '20913', 'changed the theme to Celestial Space', '2026-06-09 16:06:23', 0, NULL, 'system', 0, NULL, 0),
(496, '2301-000428', '20913', 'hello', '2026-06-11 16:01:37', 1, NULL, NULL, 0, NULL, 0),
(497, '2301-000428', '20913', 'changed the theme to Celestial Space', '2026-06-11 16:02:07', 1, NULL, 'system', 0, NULL, 0),
(498, '2301-000428', '20913', 'heheheh', '2026-06-11 16:02:18', 1, NULL, NULL, 0, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `date_evaluated` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluations`
--

INSERT INTO `evaluations` (`id`, `student_id`, `teacher_id`, `rating`, `comments`, `date_evaluated`) VALUES
(1, '2301-000428', 15, 4, '', '2026-03-08 16:44:51'),
(4, '1701-00352', 15, 5, 'Best Teacher EVER!!!', '2026-03-08 17:33:42'),
(5, '123456789', 15, 5, '', '2026-03-08 17:36:16'),
(6, '20913', 15, 5, 'Ang galing ninyo po mag turo and madali lng naming ma unawaan ang lesson maam!!!', '2026-03-08 22:25:44'),
(7, '123456', 15, 5, '', '2026-03-08 22:27:34'),
(8, '2301-000111', 15, 5, '', '2026-03-08 22:31:38'),
(9, '2202-000012', 15, 5, '', '2026-03-08 22:32:29'),
(10, '2302-000019', 15, 5, '', '2026-03-08 22:33:27'),
(12, '1701-00352', 17, 2, '', '2026-04-15 14:42:29'),
(14, '1701-00352', 18, 5, '', '2026-05-04 23:10:14');

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_answers`
--

CREATE TABLE `evaluation_answers` (
  `id` int(11) NOT NULL,
  `evaluation_id` int(11) DEFAULT NULL,
  `question_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluation_answers`
--

INSERT INTO `evaluation_answers` (`id`, `evaluation_id`, `question_id`, `rating`) VALUES
(1, 1, 1, 3),
(2, 1, 2, 5),
(3, 1, 3, 4),
(44, 4, 13, 5),
(45, 4, 14, 5),
(46, 4, 15, 5),
(47, 4, 16, 5),
(48, 4, 17, 5),
(49, 4, 18, 5),
(50, 4, 23, 5),
(51, 4, 25, 5),
(52, 4, 26, 5),
(53, 4, 27, 5),
(54, 4, 28, 5),
(55, 4, 7, 5),
(56, 4, 9, 5),
(57, 4, 10, 5),
(58, 4, 11, 5),
(59, 4, 12, 5),
(60, 4, 19, 5),
(61, 4, 20, 5),
(62, 4, 21, 5),
(63, 4, 22, 5),
(64, 5, 13, 5),
(65, 5, 14, 5),
(66, 5, 15, 5),
(67, 5, 16, 5),
(68, 5, 17, 5),
(69, 5, 18, 5),
(70, 5, 23, 5),
(71, 5, 25, 5),
(72, 5, 26, 5),
(73, 5, 27, 5),
(74, 5, 28, 5),
(75, 5, 7, 5),
(76, 5, 9, 5),
(77, 5, 10, 5),
(78, 5, 11, 5),
(79, 5, 12, 5),
(80, 5, 19, 5),
(81, 5, 20, 5),
(82, 5, 21, 5),
(83, 5, 22, 5),
(84, 6, 13, 5),
(85, 6, 14, 5),
(86, 6, 15, 5),
(87, 6, 16, 5),
(88, 6, 17, 5),
(89, 6, 18, 5),
(90, 6, 23, 5),
(91, 6, 25, 5),
(92, 6, 26, 5),
(93, 6, 27, 5),
(94, 6, 28, 5),
(95, 6, 7, 5),
(96, 6, 9, 5),
(97, 6, 10, 5),
(98, 6, 11, 5),
(99, 6, 12, 5),
(100, 6, 19, 5),
(101, 6, 20, 5),
(102, 6, 21, 5),
(103, 6, 22, 5),
(104, 7, 13, 5),
(105, 7, 14, 5),
(106, 7, 15, 5),
(107, 7, 16, 5),
(108, 7, 17, 5),
(109, 7, 18, 5),
(110, 7, 23, 5),
(111, 7, 25, 5),
(112, 7, 26, 5),
(113, 7, 27, 5),
(114, 7, 28, 5),
(115, 7, 7, 5),
(116, 7, 9, 5),
(117, 7, 10, 5),
(118, 7, 11, 5),
(119, 7, 12, 5),
(120, 7, 19, 5),
(121, 7, 20, 5),
(122, 7, 21, 5),
(123, 7, 22, 5),
(124, 8, 13, 5),
(125, 8, 14, 5),
(126, 8, 15, 5),
(127, 8, 16, 5),
(128, 8, 17, 5),
(129, 8, 18, 5),
(130, 8, 23, 5),
(131, 8, 25, 5),
(132, 8, 26, 5),
(133, 8, 27, 5),
(134, 8, 28, 5),
(135, 8, 7, 5),
(136, 8, 9, 5),
(137, 8, 10, 5),
(138, 8, 11, 5),
(139, 8, 12, 5),
(140, 8, 19, 5),
(141, 8, 20, 5),
(142, 8, 21, 5),
(143, 8, 22, 5),
(144, 9, 13, 5),
(145, 9, 14, 5),
(146, 9, 15, 5),
(147, 9, 16, 5),
(148, 9, 17, 5),
(149, 9, 18, 5),
(150, 9, 23, 5),
(151, 9, 25, 5),
(152, 9, 26, 5),
(153, 9, 27, 5),
(154, 9, 28, 5),
(155, 9, 7, 5),
(156, 9, 9, 5),
(157, 9, 10, 5),
(158, 9, 11, 5),
(159, 9, 12, 5),
(160, 9, 19, 5),
(161, 9, 20, 5),
(162, 9, 21, 5),
(163, 9, 22, 5),
(164, 10, 13, 5),
(165, 10, 14, 5),
(166, 10, 15, 5),
(167, 10, 16, 5),
(168, 10, 17, 5),
(169, 10, 18, 5),
(170, 10, 23, 5),
(171, 10, 25, 5),
(172, 10, 26, 5),
(173, 10, 27, 5),
(174, 10, 28, 5),
(175, 10, 7, 5),
(176, 10, 9, 5),
(177, 10, 10, 5),
(178, 10, 11, 5),
(179, 10, 12, 5),
(180, 10, 19, 5),
(181, 10, 20, 5),
(182, 10, 21, 5),
(183, 10, 22, 5),
(204, 12, 13, 5),
(205, 12, 14, 2),
(206, 12, 15, 2),
(207, 12, 16, 2),
(208, 12, 17, 2),
(209, 12, 18, 2),
(210, 12, 23, 2),
(211, 12, 25, 2),
(212, 12, 26, 2),
(213, 12, 27, 2),
(214, 12, 28, 2),
(215, 12, 7, 2),
(216, 12, 9, 2),
(217, 12, 10, 2),
(218, 12, 11, 2),
(219, 12, 12, 2),
(220, 12, 19, 2),
(221, 12, 20, 2),
(222, 12, 21, 2),
(223, 12, 22, 2),
(244, 14, 13, 5),
(245, 14, 14, 5),
(246, 14, 15, 5),
(247, 14, 16, 5),
(248, 14, 17, 5),
(249, 14, 18, 5),
(250, 14, 23, 5),
(251, 14, 25, 5),
(252, 14, 26, 5),
(253, 14, 27, 5),
(254, 14, 28, 5),
(255, 14, 7, 5),
(256, 14, 9, 5),
(257, 14, 10, 4),
(258, 14, 11, 5),
(259, 14, 12, 5),
(260, 14, 19, 5),
(261, 14, 20, 5),
(262, 14, 21, 5),
(263, 14, 22, 5);

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_questions`
--

CREATE TABLE `evaluation_questions` (
  `id` int(11) NOT NULL,
  `question` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `category` varchar(255) DEFAULT 'General'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluation_questions`
--

INSERT INTO `evaluation_questions` (`id`, `question`, `created_at`, `category`) VALUES
(7, 'Does the teacher explain the lessons clearly?', '2026-03-08 17:24:13', 'Teaching Effectiveness'),
(9, 'Do you understand the topics taught by the teacher?', '2026-03-08 17:24:46', 'Teaching Effectiveness'),
(10, 'Does the teacher use different teaching strategies to help students understand the lesson?', '2026-03-08 17:25:09', 'Teaching Effectiveness'),
(11, 'Does the teacher relate the lesson to real-life situations?', '2026-03-08 17:25:18', 'Teaching Effectiveness'),
(12, 'Does the teacher make the lesson interesting and engaging?', '2026-03-08 17:25:27', 'Teaching Effectiveness'),
(13, 'Does the teacher maintain discipline and order in the classroom?', '2026-03-08 17:25:45', 'Classroom Management'),
(14, 'Does the teacher treat students with respect?', '2026-03-08 17:26:00', 'Classroom Management'),
(15, 'Does the teacher listen to students’ questions and opinions?', '2026-03-08 17:26:10', 'Classroom Management'),
(16, 'Does the teacher treat all students fairly?', '2026-03-08 17:26:32', 'Classroom Management'),
(17, 'Does the teacher create a safe and comfortable learning environment?', '2026-03-08 17:26:41', 'Classroom Management'),
(18, 'Is the teacher approachable when students need help?', '2026-03-08 17:26:59', 'Communication and Support'),
(19, 'Does the teacher give clear instructions for activities and assignments?', '2026-03-08 17:27:08', 'Teaching Effectiveness'),
(20, 'Does the teacher provide helpful feedback on assignments and tests?', '2026-03-08 17:27:16', 'Teaching Effectiveness'),
(21, 'Does the teacher encourage students to participate in class?', '2026-03-08 17:27:26', 'Teaching Effectiveness'),
(22, 'Does the teacher help students improve their understanding of the subject?', '2026-03-08 17:27:36', 'Teaching Effectiveness'),
(23, 'Does the teacher come to class on time?', '2026-03-08 17:27:52', 'Professionalism'),
(25, 'Does the teacher demonstrate professionalism and good behavior?', '2026-03-08 17:28:19', 'Professionalism'),
(26, 'Does the teacher follow school policies and rules?', '2026-03-08 17:28:30', 'Professionalism'),
(27, 'Overall, how satisfied are you with the teacher’s teaching?', '2026-03-08 17:28:40', 'Professionalism'),
(28, 'Is the teacher well-prepared for each lesson?', '2026-03-08 17:29:14', 'Professionalism');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `is_notified` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_chats`
--

CREATE TABLE `group_chats` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `creator_id` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `group_icon` varchar(255) DEFAULT '',
  `theme` varchar(50) DEFAULT 'default'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_chats`
--

INSERT INTO `group_chats` (`id`, `name`, `creator_id`, `created_at`, `group_icon`, `theme`) VALUES
(4, 'Programming', '092025', '2026-02-17 00:09:25', 'group_1771258165_6993413571f2c.png', 'default'),
(5, 'Study Group', '1701-00352', '2026-02-17 00:58:57', 'group_1771261137_69934cd13ba1e.jpg', 'geometric'),
(6, 'Study Group1', '1701-00352', '2026-02-17 18:35:12', 'group_1771324512_699444603dffe.jpg', 'default'),
(7, 'SASEC', '123456789', '2026-02-19 00:09:29', 'group_1771430969_6995e439e536f.jpg', 'midnight'),
(20, 'HUUH', '2301-000076', '2026-03-03 12:05:19', '', 'default'),
(21, 'asdasdas', 'T-15', '2026-03-11 10:08:13', '', 'default'),
(22, 'Study Group1', 'T-15', '2026-03-23 14:41:15', 'group_1774248075_69c0e08bd190d.png', 'default'),
(23, 'asdsa', 'T-15', '2026-05-15 18:57:49', '', 'flashlight');

-- --------------------------------------------------------

--
-- Table structure for table `group_chat_members`
--

CREATE TABLE `group_chat_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `added_at` datetime DEFAULT current_timestamp(),
  `last_read` datetime DEFAULT current_timestamp(),
  `cleared_at` datetime DEFAULT '1000-01-01 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_chat_members`
--

INSERT INTO `group_chat_members` (`id`, `group_id`, `user_id`, `added_at`, `last_read`, `cleared_at`) VALUES
(1, 1, '092025', '2026-02-16 23:01:16', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(2, 1, '1701-00352', '2026-02-16 23:01:16', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(3, 1, '123456', '2026-02-16 23:01:16', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(4, 2, '092025', '2026-02-16 23:09:39', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(5, 2, '1701-00352', '2026-02-16 23:09:39', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(6, 2, '123456', '2026-02-16 23:09:39', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(7, 3, '092025', '2026-02-16 23:15:42', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(8, 3, '1701-00352', '2026-02-16 23:15:42', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(9, 3, '123456', '2026-02-16 23:15:42', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(10, 4, '092025', '2026-02-17 00:09:25', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(12, 4, '123456', '2026-02-17 00:09:25', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(13, 5, '1701-00352', '2026-02-17 00:58:57', '2026-05-23 15:24:14', '1000-01-01 00:00:00'),
(14, 5, '2301-000111', '2026-02-17 00:58:57', '2026-03-08 22:30:50', '1000-01-01 00:00:00'),
(15, 5, '2202-000012', '2026-02-17 00:58:57', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(16, 5, '2301-000474', '2026-02-17 00:58:57', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(17, 5, '2302-000019', '2026-02-17 00:58:57', '2026-03-08 22:33:27', '1000-01-01 00:00:00'),
(19, 6, '2301-000111', '2026-02-17 18:35:12', '2026-03-08 22:30:50', '1000-01-01 00:00:00'),
(20, 6, '2202-000012', '2026-02-17 18:35:12', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(21, 6, '2301-000474', '2026-02-17 18:35:12', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(22, 6, '1234-123456', '2026-02-17 18:35:12', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(23, 6, '092025', '2026-02-17 18:35:12', '2026-03-06 18:05:14', '1000-01-01 00:00:00'),
(24, 6, '123456', '2026-02-17 18:35:12', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(25, 6, '2302-000019', '2026-02-17 18:35:12', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(26, 7, '123456789', '2026-02-19 00:09:29', '2026-03-06 08:09:33', '1000-01-01 00:00:00'),
(27, 7, '1701-00352', '2026-02-19 00:09:29', '2026-06-08 17:39:35', '1000-01-01 00:00:00'),
(30, 8, '2301-000111', '2026-02-19 14:55:33', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(31, 8, '2202-000012', '2026-02-19 14:55:33', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(33, 9, '2301-000111', '2026-02-21 16:48:36', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(34, 9, '2202-000012', '2026-02-21 16:48:36', '2026-02-24 06:11:59', '1000-01-01 00:00:00'),
(45, 5, '123456789', '2026-02-24 06:39:19', '2026-03-07 09:12:14', '1000-01-01 00:00:00'),
(46, 20, '2301-000076', '2026-03-03 12:05:19', '2026-03-03 12:06:37', '1000-01-01 00:00:00'),
(47, 20, '2301-000111', '2026-03-03 12:05:19', '2026-03-08 22:30:43', '1000-01-01 00:00:00'),
(49, 21, '2301-000111', '2026-03-11 10:08:13', '2026-03-11 10:08:13', '1000-01-01 00:00:00'),
(50, 21, '2202-000012', '2026-03-11 10:08:13', '2026-03-11 10:08:13', '1000-01-01 00:00:00'),
(52, 22, '2301-000111', '2026-03-23 14:41:15', '2026-03-23 14:41:15', '1000-01-01 00:00:00'),
(53, 22, '2202-000012', '2026-03-23 14:41:15', '2026-03-23 14:41:15', '1000-01-01 00:00:00'),
(54, 7, '20913', '2026-04-15 14:26:59', '2026-05-22 19:06:35', '2026-05-19 21:19:41'),
(55, 23, 'T-15', '2026-05-15 18:57:49', '2026-05-22 17:54:42', '1000-01-01 00:00:00'),
(56, 23, '1701-00352', '2026-05-15 18:57:49', '2026-06-18 16:17:06', '1000-01-01 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `group_chat_mentions`
--

CREATE TABLE `group_chat_mentions` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_chat_messages`
--

CREATE TABLE `group_chat_messages` (
  `id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `sender_id` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `media` varchar(255) DEFAULT NULL,
  `media_type` varchar(20) DEFAULT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `media_content` longblob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_chat_messages`
--

INSERT INTO `group_chat_messages` (`id`, `group_id`, `sender_id`, `message`, `timestamp`, `media`, `media_type`, `is_pinned`, `media_content`) VALUES
(1, 3, '092025', 'hi', '2026-02-16 23:30:21', NULL, NULL, 0, NULL),
(2, 3, '1701-00352', 'hi', '2026-02-16 23:30:33', NULL, NULL, 0, NULL),
(3, 3, '1701-00352', 'how you', '2026-02-16 23:30:43', NULL, NULL, 0, NULL),
(4, 3, '123456', 'hi', '2026-02-16 23:31:29', NULL, NULL, 0, NULL),
(5, 3, '092025', 'ere', '2026-02-16 23:52:26', NULL, NULL, 0, NULL),
(6, 3, '092025', 'we', '2026-02-16 23:52:44', NULL, NULL, 0, NULL),
(7, 3, '1701-00352', 'love', '2026-02-17 00:01:16', NULL, NULL, 0, NULL),
(8, 4, '092025', 'Hi Classmate', '2026-02-17 00:12:45', NULL, NULL, 0, NULL),
(9, 4, '1701-00352', 'Hello Princess', '2026-02-17 00:13:09', NULL, NULL, 0, NULL),
(10, 4, '1701-00352', 'Princess', '2026-02-17 13:33:19', NULL, NULL, 0, NULL),
(11, 4, '092025', 'yes', '2026-02-17 13:33:25', NULL, NULL, 0, NULL),
(12, 4, '092025', 'assignment po', '2026-02-17 17:59:10', NULL, NULL, 0, NULL),
(13, 4, '1701-00352', 'ergterg', '2026-02-17 19:19:44', NULL, NULL, 0, NULL),
(18, 7, '123456789', 'hi', '2026-02-24 06:26:12', NULL, NULL, 0, NULL),
(19, 7, '123456789', 'hi', '2026-02-24 06:26:32', NULL, NULL, 0, NULL),
(20, 5, '123456789', 'ds', '2026-02-24 06:39:29', NULL, NULL, 0, NULL),
(21, 7, '123456789', 'sad', '2026-02-24 08:09:09', NULL, NULL, 0, NULL),
(22, 5, '123456789', 'asdas', '2026-02-24 08:09:12', NULL, NULL, 0, NULL),
(23, 20, '2301-000076', '', '2026-03-03 12:06:33', 'uploads/gc_1772510793_69a65e4912a3b.pdf', 'file', 0, NULL),
(24, 5, '123456789', 'sdfsd', '2026-03-07 09:12:02', NULL, NULL, 0, NULL),
(25, 5, '123456789', 'sdfsd', '2026-03-07 09:12:09', NULL, NULL, 0, NULL),
(26, 7, '20913', 'love', '2026-04-15 14:27:14', NULL, NULL, 0, NULL),
(27, 7, '20913', 'dasdasdas', '2026-04-15 14:31:03', NULL, NULL, 0, NULL),
(28, 7, '20913', 'asdasdasd', '2026-04-15 14:39:00', NULL, NULL, 0, NULL),
(29, 7, '20913', 'asdasd', '2026-04-15 14:39:08', NULL, NULL, 0, NULL),
(30, 7, '1701-00352', 'asdasdasd', '2026-04-15 14:40:52', NULL, NULL, 0, NULL),
(31, 7, '1701-00352', 'dasdasdasd', '2026-04-15 14:41:01', NULL, NULL, 0, NULL),
(32, 7, '20913', 'asdasdasd', '2026-04-15 14:42:51', NULL, NULL, 0, NULL),
(33, 7, '20913', 'asdasdasd', '2026-04-15 14:42:59', NULL, NULL, 0, NULL),
(34, 7, '1701-00352', 'sdfsdfsdfsdf', '2026-04-16 00:01:42', NULL, NULL, 0, NULL),
(35, 7, '1701-00352', 'sdfsdfsdfsdf', '2026-04-16 00:02:02', NULL, NULL, 0, NULL),
(36, 7, '1701-00352', 'chgchcghgcghj', '2026-04-20 17:20:59', NULL, NULL, 0, NULL),
(37, 23, '1701-00352', 'hi evryone', '2026-05-15 18:58:25', NULL, NULL, 0, NULL),
(38, 23, '1701-00352', 'ughhhh', '2026-05-15 18:58:31', NULL, NULL, 0, NULL),
(39, 23, 'T-15', 'asjdhaslkdas', '2026-05-15 18:58:43', NULL, NULL, 0, NULL),
(40, 23, 'T-15', 'dsfsfsdfs', '2026-05-15 18:58:54', NULL, NULL, 0, NULL),
(41, 23, '1701-00352', '', '2026-05-15 18:59:45', 'uploads/gc_1778842785_6a06fca1ac065.pdf', 'file', 0, NULL),
(42, 7, '1701-00352', 'hello', '2026-05-17 18:32:28', NULL, NULL, 0, NULL),
(44, 5, '1701-00352', 'fgd', '2026-05-17 19:36:35', NULL, NULL, 0, NULL),
(45, 7, '1701-00352', 'fdgdgd', '2026-05-17 20:57:54', NULL, NULL, 0, NULL),
(46, 23, '1701-00352', 'ascas', '2026-05-17 22:05:53', NULL, NULL, 0, NULL),
(47, 23, '1701-00352', 'asca', '2026-05-17 22:05:54', NULL, NULL, 0, NULL),
(48, 23, '1701-00352', 'asc', '2026-05-17 22:05:55', NULL, NULL, 0, NULL),
(49, 23, '1701-00352', 'acasc', '2026-05-17 22:05:55', NULL, NULL, 0, NULL),
(50, 23, '1701-00352', 'acas', '2026-05-17 22:05:56', NULL, NULL, 0, NULL),
(51, 23, '1701-00352', 'cac', '2026-05-17 22:05:57', NULL, NULL, 0, NULL),
(52, 23, '1701-00352', 'aca', '2026-05-17 22:05:57', NULL, NULL, 0, NULL),
(53, 23, '1701-00352', 'cac', '2026-05-17 22:05:57', NULL, NULL, 0, NULL),
(54, 7, '1701-00352', '', '2026-05-18 12:57:15', 'uploads/gc_1779080235_6a0a9c2b47df7.jpg', 'photo', 0, NULL),
(55, 7, '1701-00352', 'dfsdvdsvdsv', '2026-05-18 13:03:49', NULL, NULL, 0, NULL),
(56, 7, '20913', '', '2026-05-19 06:42:34', 'uploads/gc_1779144154_6a0b95da6f8c5.jpg', 'photo', 0, NULL),
(58, 7, '20913', 'changed the theme to Celestial Space', '2026-05-19 10:59:48', NULL, 'system', 0, NULL),
(59, 7, '20913', 'changed the theme to Celestial Space', '2026-05-19 13:00:32', NULL, 'system', 0, NULL),
(60, 23, '1701-00352', 'zscas', '2026-05-19 18:58:56', NULL, NULL, 0, NULL),
(64, 7, '20913', ',n,', '2026-05-19 21:20:14', NULL, NULL, 0, NULL),
(65, 7, '20913', 'mk', '2026-05-19 21:20:15', NULL, NULL, 0, NULL),
(66, 7, '20913', 'km', '2026-05-19 21:20:16', NULL, NULL, 0, NULL),
(67, 7, '20913', 'km', '2026-05-19 21:20:17', NULL, NULL, 0, NULL),
(68, 7, '20913', 'km', '2026-05-19 21:20:17', NULL, NULL, 0, NULL),
(69, 7, '20913', 'km', '2026-05-19 21:20:18', NULL, NULL, 0, NULL),
(70, 7, '20913', 'km', '2026-05-19 21:20:19', NULL, NULL, 0, NULL),
(71, 7, '20913', 'jnk', '2026-05-19 21:20:20', NULL, NULL, 0, NULL),
(72, 7, '20913', 'sdcsdc', '2026-05-19 22:01:28', NULL, NULL, 0, NULL),
(73, 7, '20913', 'sdc', '2026-05-19 22:01:29', NULL, NULL, 0, NULL),
(74, 7, '20913', 'sdc', '2026-05-19 22:01:29', NULL, NULL, 0, NULL),
(75, 23, '1701-00352', 'dsfsdf', '2026-05-20 11:54:41', NULL, NULL, 0, NULL),
(76, 5, '1701-00352', 'changed the theme to Night Geometric', '2026-05-21 22:54:34', NULL, 'system', 0, NULL),
(77, 7, '20913', 'changed the theme to Default Neural', '2026-05-22 10:57:15', NULL, 'system', 0, NULL),
(78, 7, '20913', 'changed the theme to Stormy Rain', '2026-05-22 10:57:33', NULL, 'system', 0, NULL),
(79, 7, '20913', 'changed the theme to Midnight Sky', '2026-05-22 19:06:26', NULL, 'system', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `last_attempt` datetime DEFAULT NULL,
  `lockout_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `location` varchar(255) DEFAULT 'Unknown'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_history`
--

INSERT INTO `login_history` (`id`, `student_id`, `device_info`, `ip_address`, `login_time`, `location`) VALUES
(5, '092025', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-21 18:22:33', 'Localhost'),
(11, '092025', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-21 20:45:15', 'Localhost'),
(13, '092025', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-22 09:25:33', 'Localhost'),
(18, '2301-000428', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-23 07:37:13', 'Localhost'),
(23, '321', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-23 14:28:04', 'Localhost'),
(39, '092025', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-25 21:10:50', 'Localhost'),
(44, '092025', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-26 22:08:49', 'Localhost'),
(45, '092025', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-26 22:10:03', 'Localhost'),
(46, '122334', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-26 23:56:45', 'Localhost'),
(49, '2301-000076', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-03-03 12:01:51', 'Localhost'),
(57, '092025', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '::1', '2026-03-06 13:25:54', 'Localhost'),
(64, '124578235689', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-03-06 17:52:07', 'Localhost'),
(94, '2301-000428', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-03-08 15:29:26', 'Localhost'),
(103, '123456', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-03-08 22:26:49', 'Localhost'),
(104, '2301-000111', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-03-08 22:28:18', 'Localhost'),
(105, '2202-000012', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-03-08 22:32:00', 'Localhost'),
(106, '2302-000019', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-03-08 22:32:59', 'Localhost'),
(117, '123456789', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-03-09 23:49:06', 'Localhost'),
(183, '123456789', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-03-17 10:44:02', 'Localhost'),
(231, '123456789', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '2026-03-21 12:21:16', 'Localhost'),
(232, '123456789', 'LOGOUT', '::1', '2026-03-21 12:38:28', 'N/A'),
(239, '123456789', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '::1', '2026-03-22 15:05:55', 'Localhost'),
(253, '123456asd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '::1', '2026-03-24 13:39:15', 'Localhost'),
(254, '123456asd', 'LOGOUT', '::1', '2026-03-24 14:37:48', 'N/A'),
(255, '123456asd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '::1', '2026-03-24 14:40:59', 'Localhost'),
(256, '123456asd', 'LOGOUT', '::1', '2026-03-24 14:41:08', 'N/A'),
(293, '2302-000019', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '2026-04-16 12:36:27', 'Localhost'),
(335, '2301-000076', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', '2026-04-18 21:30:59', 'Localhost'),
(336, '2301-000076', 'LOGOUT', '::1', '2026-04-18 21:31:28', 'N/A'),
(404, 'A23-8725', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', '2026-04-21 16:35:06', 'Localhost'),
(405, 'A23-8725', 'LOGOUT', '::1', '2026-04-21 17:14:36', 'N/A'),
(410, '124578', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', '2026-04-21 21:25:14', 'Localhost'),
(411, '124578', 'LOGOUT', '::1', '2026-04-21 21:25:25', 'N/A'),
(412, '124578', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', '2026-04-21 21:26:04', 'Localhost'),
(413, '124578', 'LOGOUT', '::1', '2026-04-21 21:39:59', 'N/A'),
(452, '271813', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '2026-04-27 21:04:28', 'Localhost'),
(455, '271813', 'LOGOUT', '::1', '2026-04-27 22:04:15', 'N/A'),
(544, '147852369', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '2026-05-06 08:49:12', 'Flores Subdivision, Lucena, '),
(545, '147852369', 'LOGOUT', '::1', '2026-05-06 08:50:16', 'N/A'),
(550, '2301-000076', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '2026-05-06 13:16:36', 'Localhost'),
(551, '2301-000076', 'LOGOUT', '::1', '2026-05-06 13:18:28', 'N/A'),
(614, 'Admin', 'LOGOUT', '::1', '2026-05-17 15:31:09', 'N/A'),
(635, 'Admin', 'LOGOUT', '::1', '2026-05-22 09:48:27', 'N/A'),
(638, 'T-15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '::1', '2026-05-22 12:23:20', 'Flores Subdivision, Lucena, '),
(641, 'T-15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '::1', '2026-05-22 17:51:00', 'Flores Subdivision, Lucena, '),
(643, 'T-15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '::1', '2026-05-23 10:16:18', 'Flores Subdivision, Lucena, '),
(645, 'T-15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '::1', '2026-05-23 11:29:47', 'Poblacion, Batangas City, '),
(649, 'Justin', 'LOGOUT', '::1', '2026-05-24 01:14:01', 'N/A'),
(650, '2401-00186', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '::1', '2026-05-24 01:15:33', 'Better Living, Lucena, '),
(655, 'Admin', 'LOGOUT', '::1', '2026-05-26 22:18:40', 'N/A'),
(658, 'Justin', 'LOGOUT', '::1', '2026-05-27 11:07:24', 'N/A'),
(663, 'Admin', 'LOGOUT', '::1', '2026-05-27 16:43:10', 'N/A'),
(674, 'Justin', 'LOGOUT', '::1', '2026-05-27 23:16:49', 'N/A'),
(675, 'Justin', 'LOGOUT', '::1', '2026-05-27 23:46:10', 'N/A'),
(681, 'T-15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '::1', '2026-05-29 18:03:31', 'Barangay 7, Lucena, '),
(682, 'T-15', 'LOGOUT', '::1', '2026-05-29 18:24:40', 'N/A'),
(687, 'Justin', 'LOGOUT', '::1', '2026-05-30 13:47:23', 'N/A'),
(691, '123456789', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '::1', '2026-05-30 15:14:37', 'Flores Subdivision, Lucena, '),
(692, '123456789', 'LOGOUT', '::1', '2026-05-30 15:23:06', 'N/A'),
(698, '123456789', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '::1', '2026-05-31 00:49:07', 'Localhost'),
(699, '123456789', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '::1', '2026-05-31 00:50:09', 'Flores Subdivision, Lucena, '),
(717, 'Admin', 'LOGOUT', '::1', '2026-06-05 14:45:47', 'N/A'),
(718, 'Justin', 'LOGOUT', '::1', '2026-06-05 14:47:03', 'N/A'),
(720, '20913', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '::1', '2026-06-05 21:07:25', 'Flores Subdivision, Lucena, '),
(726, 'Justin', 'LOGOUT', '::1', '2026-06-09 12:38:40', 'N/A'),
(727, '20913', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-06-09 12:39:06', 'Localhost'),
(729, 'Justin', 'LOGOUT', '::1', '2026-06-09 15:18:25', 'N/A'),
(730, '2301-000076', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-06-09 15:18:44', 'Localhost'),
(732, 'T-15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-06-09 15:45:23', 'San Pablo, '),
(734, 'T-15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-06-09 21:07:56', 'Barangay 7, Lucena, '),
(735, '20913', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-06-09 21:47:52', 'Flores Subdivision, Lucena, '),
(736, 'Justin', 'LOGOUT', '::1', '2026-06-09 21:51:01', 'N/A'),
(737, '2301-000111', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-06-09 21:52:19', 'Localhost'),
(741, '2301-000428', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-06-11 16:01:03', 'Lucena, '),
(742, '2301-000428', 'LOGOUT', '::1', '2026-06-11 16:06:54', 'N/A'),
(752, '20913', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-06-15 15:09:30', 'Flores Subdivision, Lucena, '),
(753, '20913', 'LOGOUT', '::1', '2026-06-15 15:10:05', 'N/A'),
(761, 'Admin', 'LOGOUT', '::1', '2026-06-16 03:49:14', 'N/A'),
(764, '20913', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-06-16 03:50:10', 'Barangay 7, Lucena, '),
(765, '20913', 'LOGOUT', '::1', '2026-06-16 03:50:24', 'N/A'),
(771, '1701-00352', 'LOGOUT', '::1', '2026-06-18 12:04:27', 'N/A'),
(772, '1701-00352', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-06-18 12:04:36', 'Barangay 7, Lucena, '),
(773, '1701-00352', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-06-18 12:11:08', 'Barangay 7, Lucena, '),
(774, '1701-00352', 'LOGOUT', '::1', '2026-06-18 12:11:27', 'N/A'),
(775, '20913', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-06-18 12:11:36', 'Barangay 7, Lucena, '),
(776, '20913', 'LOGOUT', '::1', '2026-06-18 12:12:04', 'N/A'),
(777, 'T-15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-06-18 12:12:10', 'Barangay 7, Lucena, '),
(778, '1701-00352', 'LOGOUT', '::1', '2026-06-18 12:12:25', 'N/A'),
(779, '1701-00352', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-06-18 13:02:32', 'Barangay 7, Lucena, '),
(780, '1701-00352', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-06-18 16:15:09', 'Localhost'),
(781, '1701-00352', 'LOGOUT', '::1', '2026-06-18 16:15:24', 'N/A'),
(782, '1701-00352', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-06-19 14:10:41', 'Localhost');

-- --------------------------------------------------------

--
-- Table structure for table `message_deletions`
--

CREATE TABLE `message_deletions` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `chat_type` enum('direct','group') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message_deletions`
--

INSERT INTO `message_deletions` (`id`, `message_id`, `user_id`, `chat_type`) VALUES
(1, 489, '1701-00352', 'direct'),
(2, 493, '1701-00352', 'direct');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `actor_id` varchar(50) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `post_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `actor_id`, `type`, `post_id`, `is_read`, `timestamp`) VALUES
(43, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 13:30:24'),
(44, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 13:30:24'),
(45, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 13:30:24'),
(46, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 13:30:24'),
(47, '092025', 'Admin', 'event', 0, 1, '2026-02-16 13:33:04'),
(48, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 13:33:04'),
(49, '123456', 'Admin', 'event', 0, 0, '2026-02-16 13:33:04'),
(51, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 13:33:04'),
(52, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 13:33:04'),
(53, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 13:33:04'),
(54, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 13:33:04'),
(55, '092025', 'Admin', 'event', 0, 1, '2026-02-16 13:36:30'),
(56, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 13:36:30'),
(57, '123456', 'Admin', 'event', 0, 0, '2026-02-16 13:36:30'),
(59, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 13:36:30'),
(60, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 13:36:30'),
(61, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 13:36:30'),
(62, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 13:36:30'),
(63, '092025', 'Admin', 'event', 0, 1, '2026-02-16 14:00:08'),
(64, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 14:00:08'),
(65, '123456', 'Admin', 'event', 0, 0, '2026-02-16 14:00:08'),
(67, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 14:00:08'),
(68, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 14:00:08'),
(69, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 14:00:08'),
(70, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 14:00:08'),
(71, '092025', 'Admin', 'event', 0, 1, '2026-02-16 14:13:15'),
(72, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 14:13:15'),
(73, '123456', 'Admin', 'event', 0, 0, '2026-02-16 14:13:15'),
(75, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 14:13:15'),
(76, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 14:13:15'),
(77, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 14:13:15'),
(78, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 14:13:15'),
(79, '092025', 'Admin', 'event', 0, 1, '2026-02-16 19:55:59'),
(80, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 19:55:59'),
(81, '123456', 'Admin', 'event', 0, 0, '2026-02-16 19:55:59'),
(83, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 19:55:59'),
(84, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 19:55:59'),
(85, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 19:55:59'),
(86, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 19:56:00'),
(87, '092025', 'Admin', 'event', 0, 1, '2026-02-16 19:56:04'),
(88, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 19:56:04'),
(89, '123456', 'Admin', 'event', 0, 0, '2026-02-16 19:56:04'),
(91, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 19:56:04'),
(92, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 19:56:04'),
(93, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 19:56:04'),
(94, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 19:56:04'),
(95, '092025', 'Admin', 'event', 0, 1, '2026-02-16 21:26:21'),
(96, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 21:26:21'),
(97, '123456', 'Admin', 'event', 0, 0, '2026-02-16 21:26:21'),
(99, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 21:26:21'),
(100, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 21:26:21'),
(101, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 21:26:21'),
(102, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 21:26:21'),
(103, '092025', 'Admin', 'event', 0, 1, '2026-02-16 21:34:34'),
(104, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 21:34:34'),
(105, '123456', 'Admin', 'event', 0, 0, '2026-02-16 21:34:34'),
(107, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 21:34:34'),
(108, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 21:34:34'),
(109, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 21:34:34'),
(110, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 21:34:34'),
(111, '092025', 'Admin', 'event', 0, 1, '2026-02-16 21:34:38'),
(112, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 21:34:38'),
(113, '123456', 'Admin', 'event', 0, 0, '2026-02-16 21:34:38'),
(115, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 21:34:38'),
(116, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 21:34:38'),
(117, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 21:34:38'),
(118, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 21:34:38'),
(119, '092025', 'Admin', 'event', 0, 1, '2026-02-16 21:37:17'),
(120, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 21:37:17'),
(121, '123456', 'Admin', 'event', 0, 0, '2026-02-16 21:37:17'),
(123, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 21:37:17'),
(124, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 21:37:17'),
(125, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 21:37:17'),
(126, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 21:37:17'),
(127, '092025', 'Admin', 'event', 0, 1, '2026-02-16 21:38:09'),
(128, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 21:38:09'),
(129, '123456', 'Admin', 'event', 0, 0, '2026-02-16 21:38:09'),
(131, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 21:38:09'),
(132, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 21:38:09'),
(133, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 21:38:09'),
(134, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 21:38:09'),
(135, '092025', 'Admin', 'event', 0, 1, '2026-02-16 21:38:41'),
(136, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 21:38:41'),
(137, '123456', 'Admin', 'event', 0, 0, '2026-02-16 21:38:41'),
(139, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 21:38:41'),
(140, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 21:38:41'),
(141, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 21:38:41'),
(142, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 21:38:41'),
(143, '092025', 'Admin', 'event', 0, 1, '2026-02-16 21:39:12'),
(144, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 21:39:12'),
(145, '123456', 'Admin', 'event', 0, 0, '2026-02-16 21:39:12'),
(147, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 21:39:12'),
(148, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 21:39:12'),
(149, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 21:39:12'),
(150, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 21:39:12'),
(151, '092025', 'Admin', 'event', 0, 1, '2026-02-16 21:39:40'),
(152, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 21:39:40'),
(153, '123456', 'Admin', 'event', 0, 0, '2026-02-16 21:39:40'),
(155, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 21:39:40'),
(156, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 21:39:40'),
(157, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 21:39:40'),
(158, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 21:39:40'),
(159, '092025', 'Admin', 'event', 0, 1, '2026-02-16 21:39:57'),
(160, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 21:39:57'),
(161, '123456', 'Admin', 'event', 0, 0, '2026-02-16 21:39:57'),
(163, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 21:39:57'),
(164, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 21:39:57'),
(165, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 21:39:57'),
(166, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 21:39:57'),
(167, '092025', 'Admin', 'event', 0, 1, '2026-02-16 21:40:36'),
(168, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 21:40:36'),
(169, '123456', 'Admin', 'event', 0, 0, '2026-02-16 21:40:36'),
(171, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 21:40:36'),
(172, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 21:40:36'),
(173, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 21:40:36'),
(174, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 21:40:36'),
(175, '092025', 'Admin', 'event', 0, 1, '2026-02-16 21:47:37'),
(176, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 21:47:37'),
(177, '123456', 'Admin', 'event', 0, 0, '2026-02-16 21:47:37'),
(179, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 21:47:37'),
(180, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 21:47:37'),
(181, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 21:47:37'),
(182, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 21:47:37'),
(183, '092025', 'Admin', 'event', 0, 1, '2026-02-16 21:48:55'),
(184, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 21:48:55'),
(185, '123456', 'Admin', 'event', 0, 0, '2026-02-16 21:48:55'),
(187, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 21:48:55'),
(188, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 21:48:55'),
(189, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 21:48:55'),
(190, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 21:48:55'),
(191, '092025', 'Admin', 'event', 0, 1, '2026-02-16 21:49:57'),
(192, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 21:49:57'),
(193, '123456', 'Admin', 'event', 0, 0, '2026-02-16 21:49:57'),
(195, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 21:49:57'),
(196, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 21:49:57'),
(197, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 21:49:57'),
(198, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 21:49:57'),
(199, '092025', 'Admin', 'event', 0, 1, '2026-02-16 21:51:08'),
(200, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 21:51:08'),
(201, '123456', 'Admin', 'event', 0, 0, '2026-02-16 21:51:08'),
(203, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 21:51:08'),
(204, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 21:51:08'),
(205, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 21:51:08'),
(206, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 21:51:08'),
(207, '092025', 'Admin', 'event', 0, 1, '2026-02-16 21:54:40'),
(208, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 21:54:40'),
(209, '123456', 'Admin', 'event', 0, 0, '2026-02-16 21:54:40'),
(211, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 21:54:40'),
(212, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 21:54:40'),
(213, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 21:54:40'),
(214, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 21:54:40'),
(215, '092025', 'Admin', 'event', 0, 1, '2026-02-16 21:55:23'),
(216, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 21:55:23'),
(217, '123456', 'Admin', 'event', 0, 0, '2026-02-16 21:55:23'),
(219, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 21:55:23'),
(220, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 21:55:23'),
(221, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 21:55:23'),
(222, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 21:55:23'),
(223, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:23:31'),
(224, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:23:31'),
(225, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:23:31'),
(227, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:23:31'),
(228, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:23:31'),
(229, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:23:31'),
(230, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:23:31'),
(231, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:24:07'),
(232, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:24:07'),
(233, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:24:07'),
(235, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:24:07'),
(236, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:24:07'),
(237, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:24:07'),
(238, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:24:07'),
(239, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:25:17'),
(240, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:25:17'),
(241, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:25:17'),
(243, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:25:17'),
(244, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:25:17'),
(245, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:25:17'),
(246, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:25:17'),
(247, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:28:00'),
(248, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:28:00'),
(249, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:28:00'),
(251, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:28:00'),
(252, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:28:00'),
(253, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:28:00'),
(254, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:28:00'),
(255, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:28:38'),
(256, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:28:38'),
(257, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:28:38'),
(259, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:28:38'),
(260, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:28:38'),
(261, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:28:38'),
(262, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:28:38'),
(263, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:29:05'),
(264, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:29:05'),
(265, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:29:05'),
(267, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:29:05'),
(268, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:29:05'),
(269, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:29:05'),
(270, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:29:05'),
(271, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:29:21'),
(272, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:29:21'),
(273, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:29:21'),
(275, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:29:21'),
(276, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:29:21'),
(277, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:29:21'),
(278, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:29:21'),
(279, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:29:54'),
(280, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:29:54'),
(281, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:29:54'),
(283, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:29:54'),
(284, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:29:54'),
(285, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:29:54'),
(286, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:29:54'),
(287, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:30:42'),
(288, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:30:42'),
(289, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:30:42'),
(291, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:30:42'),
(292, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:30:42'),
(293, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:30:42'),
(294, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:30:42'),
(295, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:31:16'),
(296, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:31:16'),
(297, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:31:16'),
(299, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:31:16'),
(300, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:31:16'),
(301, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:31:16'),
(302, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:31:16'),
(303, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:31:36'),
(304, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:31:36'),
(305, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:31:36'),
(307, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:31:36'),
(308, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:31:36'),
(309, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:31:36'),
(310, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:31:36'),
(311, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:31:57'),
(312, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:31:57'),
(313, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:31:57'),
(315, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:31:57'),
(316, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:31:57'),
(317, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:31:57'),
(318, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:31:57'),
(319, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:32:18'),
(320, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:32:18'),
(321, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:32:18'),
(323, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:32:18'),
(324, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:32:18'),
(325, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:32:18'),
(326, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:32:18'),
(327, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:33:21'),
(328, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:33:21'),
(329, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:33:21'),
(331, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:33:21'),
(332, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:33:21'),
(333, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:33:21'),
(334, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:33:21'),
(335, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:33:47'),
(336, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:33:47'),
(337, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:33:47'),
(339, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:33:47'),
(340, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:33:47'),
(341, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:33:47'),
(342, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:33:47'),
(343, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:34:23'),
(344, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:34:23'),
(345, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:34:23'),
(347, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:34:23'),
(348, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:34:24'),
(349, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:34:24'),
(350, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:34:24'),
(351, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:34:54'),
(352, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:34:54'),
(353, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:34:54'),
(355, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:34:54'),
(356, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:34:54'),
(357, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:34:54'),
(358, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:34:54'),
(359, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:35:08'),
(360, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:35:08'),
(361, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:35:08'),
(363, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:35:08'),
(364, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:35:08'),
(365, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:35:08'),
(366, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:35:08'),
(367, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:35:36'),
(368, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:35:36'),
(369, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:35:36'),
(371, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:35:36'),
(372, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:35:36'),
(373, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:35:36'),
(374, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:35:36'),
(375, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:35:57'),
(376, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:35:57'),
(377, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:35:57'),
(379, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:35:57'),
(380, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:35:57'),
(381, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:35:57'),
(382, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:35:57'),
(383, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:36:19'),
(384, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:36:19'),
(385, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:36:19'),
(387, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:36:19'),
(388, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:36:19'),
(389, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:36:19'),
(390, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:36:19'),
(391, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:37:10'),
(392, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:37:10'),
(393, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:37:10'),
(395, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:37:10'),
(396, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:37:10'),
(397, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:37:10'),
(398, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:37:10'),
(399, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:37:28'),
(400, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:37:28'),
(401, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:37:29'),
(403, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:37:29'),
(404, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:37:29'),
(405, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:37:29'),
(406, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:37:29'),
(407, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:37:48'),
(408, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:37:48'),
(409, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:37:48'),
(411, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:37:48'),
(412, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:37:48'),
(413, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:37:48'),
(414, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:37:48'),
(415, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:38:18'),
(416, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:38:18'),
(417, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:38:18'),
(419, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:38:18'),
(420, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:38:18'),
(421, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:38:18'),
(422, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:38:18'),
(423, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:38:40'),
(424, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:38:40'),
(425, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:38:40'),
(427, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:38:40'),
(428, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:38:40'),
(429, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:38:40'),
(430, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:38:40'),
(431, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:39:30'),
(432, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:39:30'),
(433, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:39:30'),
(435, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:39:30'),
(436, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:39:30'),
(437, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:39:30'),
(438, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:39:30'),
(439, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:40:00'),
(440, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:40:00'),
(441, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:40:00'),
(443, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:40:00'),
(444, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:40:00'),
(445, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:40:00'),
(446, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:40:00'),
(447, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:40:30'),
(448, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:40:30'),
(449, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:40:30'),
(451, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:40:31'),
(452, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:40:31'),
(453, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:40:31'),
(454, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:40:31'),
(455, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:41:25'),
(456, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:41:25'),
(457, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:41:25'),
(459, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:41:25'),
(460, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:41:25'),
(461, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:41:25'),
(462, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:41:25'),
(463, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:41:54'),
(464, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:41:54'),
(465, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:41:54'),
(467, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:41:54'),
(468, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:41:54'),
(469, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:41:54'),
(470, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:41:54'),
(471, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:42:13'),
(472, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:42:13'),
(473, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:42:13'),
(475, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:42:13'),
(476, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:42:13'),
(477, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:42:13'),
(478, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:42:13'),
(479, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:42:53'),
(480, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:42:53'),
(481, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:42:53'),
(483, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:42:53'),
(484, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:42:53'),
(485, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:42:53'),
(486, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:42:53'),
(487, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:43:23'),
(488, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:43:23'),
(489, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:43:23'),
(491, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:43:23'),
(492, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:43:23'),
(493, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:43:23'),
(494, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:43:23'),
(495, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:43:49'),
(496, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:43:49'),
(497, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:43:49'),
(499, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:43:49'),
(500, '2301-000111', 'Admin', 'event', 0, 1, '2026-02-16 22:43:49'),
(501, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:43:49'),
(502, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:43:49'),
(503, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:44:28'),
(504, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:44:28'),
(505, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:44:28'),
(507, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:44:28'),
(508, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:44:28'),
(509, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:44:28'),
(510, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:44:28'),
(511, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:45:09'),
(512, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:45:09'),
(513, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:45:09'),
(515, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:45:09'),
(516, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:45:09'),
(517, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:45:09'),
(518, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:45:09'),
(519, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:45:41'),
(520, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:45:41'),
(521, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:45:41'),
(523, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:45:41'),
(524, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:45:41'),
(525, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:45:41'),
(526, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:45:41'),
(527, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:46:34'),
(528, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:46:34'),
(529, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:46:34'),
(531, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:46:34'),
(532, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:46:34'),
(533, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:46:34'),
(534, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:46:34'),
(535, '092025', 'Admin', 'event', 0, 1, '2026-02-16 22:47:02'),
(536, '1234-123456', 'Admin', 'event', 0, 0, '2026-02-16 22:47:02'),
(537, '123456', 'Admin', 'event', 0, 0, '2026-02-16 22:47:02'),
(539, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-16 22:47:02'),
(540, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-16 22:47:02'),
(541, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-16 22:47:02'),
(542, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-16 22:47:02'),
(564, '092025', 'Admin', 'event', 0, 1, '2026-02-19 16:14:55'),
(565, '123456', 'Admin', 'event', 0, 0, '2026-02-19 16:14:55'),
(566, '123456789', 'Admin', 'event', 0, 1, '2026-02-19 16:14:55'),
(568, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-19 16:14:55'),
(569, '2301-000076', 'Admin', 'event', 0, 1, '2026-02-19 16:14:55'),
(570, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-19 16:14:55'),
(571, '2301-000428', 'Admin', 'event', 0, 1, '2026-02-19 16:14:55'),
(572, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-19 16:14:55'),
(573, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-19 16:14:55'),
(574, '092025', 'Admin', 'event', 0, 1, '2026-02-19 16:15:03'),
(575, '123456', 'Admin', 'event', 0, 0, '2026-02-19 16:15:03'),
(576, '123456789', 'Admin', 'event', 0, 1, '2026-02-19 16:15:03'),
(578, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-19 16:15:03'),
(579, '2301-000076', 'Admin', 'event', 0, 1, '2026-02-19 16:15:03'),
(580, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-19 16:15:03'),
(581, '2301-000428', 'Admin', 'event', 0, 1, '2026-02-19 16:15:03'),
(582, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-19 16:15:03'),
(583, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-19 16:15:03'),
(584, '092025', 'Admin', 'event', 0, 1, '2026-02-20 10:18:34'),
(585, '123456', 'Admin', 'event', 0, 0, '2026-02-20 10:18:35'),
(586, '123456789', 'Admin', 'event', 0, 1, '2026-02-20 10:18:35'),
(588, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-20 10:18:35'),
(589, '2301-000076', 'Admin', 'event', 0, 1, '2026-02-20 10:18:35'),
(590, '2301-000111', 'Admin', 'event', 0, 0, '2026-02-20 10:18:35'),
(591, '2301-000428', 'Admin', 'event', 0, 1, '2026-02-20 10:18:35'),
(592, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-20 10:18:35'),
(593, '2302-000019', 'Admin', 'event', 0, 0, '2026-02-20 10:18:35'),
(629, '092025', 'Admin', 'event', 0, 1, '2026-02-21 00:59:44'),
(630, '123456', 'Admin', 'event', 0, 0, '2026-02-21 00:59:44'),
(631, '123456789', 'Admin', 'event', 0, 1, '2026-02-21 00:59:44'),
(633, '2202-000012', 'Admin', 'event', 0, 0, '2026-02-21 00:59:44'),
(634, '2301-000076', 'Admin', 'event', 0, 1, '2026-02-21 00:59:44'),
(635, '2301-000111', 'Admin', 'event', 0, 1, '2026-02-21 00:59:44'),
(636, '2301-000428', 'Admin', 'event', 0, 1, '2026-02-21 00:59:44'),
(637, '2301-000474', 'Admin', 'event', 0, 0, '2026-02-21 00:59:44'),
(638, '2302-000019', 'Admin', 'event', 0, 1, '2026-02-21 00:59:44'),
(651, '123456789', '2301-000076', 'reaction', 45, 1, '2026-03-03 12:02:48'),
(652, '123456789', 'Justin', 'pass_change_request', 1, 1, '2026-03-06 00:29:23'),
(653, '123456789', 'Justin', 'pass_change_request', 2, 1, '2026-03-06 00:42:30'),
(654, '123456789', 'Justin', 'pass_change_request', 3, 1, '2026-03-06 01:01:47'),
(655, '123456789', 'Justin', 'pass_change_request', 4, 1, '2026-03-06 01:02:39'),
(656, '123456789', 'Justin', 'pass_change_request', 5, 1, '2026-03-06 08:17:50'),
(657, '123456789', 'Justin', 'pass_change_request', 6, 1, '2026-03-06 10:10:26'),
(658, '123456789', 'Justin', 'pass_change_request', 7, 1, '2026-03-06 10:27:12'),
(659, '123456789', 'Justin', 'pass_change_request', 8, 1, '2026-03-06 11:00:08'),
(660, '123456789', 'Justin', 'pass_change_request', 9, 1, '2026-03-06 12:01:39'),
(678, '123456789', 'Justin', 'pass_change_request', 10, 1, '2026-03-08 21:39:45'),
(679, '123456789', 'Justin', 'pass_change_request', 11, 1, '2026-03-08 21:40:43'),
(680, '20913', 'Justin', 'pass_change_request', 12, 1, '2026-03-08 22:09:34'),
(681, '20913', 'Justin', 'pass_change_request', 13, 1, '2026-03-08 22:23:21'),
(682, '123456789', '20913', 'comment', 90, 1, '2026-03-09 09:58:18'),
(690, '123456789', '20913', 'comment', 90, 1, '2026-03-09 17:31:50'),
(691, '123456789', 'T-15', 'comment', 90, 1, '2026-03-10 20:37:16'),
(692, '123456789', 'T-15', 'comment', 90, 1, '2026-03-10 20:39:37'),
(693, '123456789', 'T-15', 'comment', 90, 1, '2026-03-10 20:55:04'),
(694, '123456789', 'T-15', 'comment', 90, 1, '2026-03-10 20:55:06'),
(695, '123456789', 'T-15', 'comment', 90, 1, '2026-03-11 10:04:34'),
(696, '123456789', 'T-15', 'comment', 90, 1, '2026-03-11 10:04:35'),
(697, '123456789', 'T-15', 'comment', 90, 1, '2026-03-11 10:04:35'),
(698, '123456789', 'T-15', 'comment', 90, 1, '2026-03-11 10:04:35'),
(699, '123456789', 'T-15', 'comment', 90, 1, '2026-03-11 10:04:35'),
(700, '123456789', 'T-15', 'comment', 90, 1, '2026-03-11 10:04:36'),
(701, '123456789', 'T-15', 'comment', 90, 1, '2026-03-11 10:05:23'),
(702, '123456789', 'T-15', 'reaction', 90, 1, '2026-03-11 10:10:15'),
(703, '123456789', 'T-15', 'reaction', 90, 1, '2026-03-11 10:10:21'),
(710, '123456789', 'T-15', 'reaction', 90, 1, '2026-03-20 13:09:06'),
(711, 'T-15', '123456789', 'room_join', 7, 1, '2026-03-21 12:22:03'),
(722, 'T-15', 'A23-8725', 'room_join', 9, 1, '2026-04-21 16:39:35'),
(723, 'A23-8725', 'T-15', 'room_post', 10, 0, '2026-04-21 16:49:19'),
(734, '2301-000111', '124578', 'announcement', 149, 0, '2026-04-21 21:36:16'),
(735, '2301-000474', '124578', 'announcement', 149, 0, '2026-04-21 21:36:16'),
(736, '2301-000428', '124578', 'announcement', 149, 0, '2026-04-21 21:36:16'),
(737, '2202-000012', '124578', 'announcement', 149, 0, '2026-04-21 21:36:16'),
(738, '2302-000019', '124578', 'announcement', 149, 0, '2026-04-21 21:36:16'),
(739, '123456', '124578', 'announcement', 149, 0, '2026-04-21 21:36:16'),
(740, '2301-000076', '124578', 'announcement', 149, 1, '2026-04-21 21:36:16'),
(741, '123456789', '124578', 'announcement', 149, 1, '2026-04-21 21:36:21'),
(742, '20913', '124578', 'announcement', 149, 1, '2026-04-21 21:36:21'),
(743, 'A23-8725', '124578', 'announcement', 149, 0, '2026-04-21 21:36:26'),
(744, '20913', 'T-15', 'room_post', 11, 1, '2026-04-22 00:57:28'),
(746, '20913', 'T-15', 'room_post', 12, 1, '2026-04-22 01:07:07'),
(748, '20913', 'T-15', 'room_post', 13, 1, '2026-04-22 01:18:40'),
(750, '20913', 'T-15', 'room_post', 14, 1, '2026-04-22 01:23:40'),
(752, '20913', 'T-15', 'room_post', 15, 1, '2026-04-22 01:23:56'),
(754, '20913', 'T-15', 'room_post', 16, 1, '2026-04-22 01:24:50'),
(756, '20913', 'T-15', 'room_post', 17, 1, '2026-04-22 10:55:45'),
(774, '20913', 'T-15', 'room_post', 18, 1, '2026-04-28 20:32:55'),
(817, '2301-000111', '20913', 'announcement', 161, 0, '2026-05-05 13:25:34'),
(818, '2301-000474', '20913', 'announcement', 161, 0, '2026-05-05 13:25:34'),
(819, '2301-000428', '20913', 'announcement', 161, 0, '2026-05-05 13:25:34'),
(820, '2202-000012', '20913', 'announcement', 161, 0, '2026-05-05 13:25:34'),
(821, '2302-000019', '20913', 'announcement', 161, 0, '2026-05-05 13:25:34'),
(822, '123456', '20913', 'announcement', 161, 0, '2026-05-05 13:25:38'),
(823, '2301-000076', '20913', 'announcement', 161, 1, '2026-05-05 13:25:38'),
(824, '123456789', '20913', 'announcement', 161, 1, '2026-05-05 13:25:38'),
(825, 'A23-8725', '20913', 'announcement', 161, 0, '2026-05-05 13:25:38'),
(826, '124578', '20913', 'announcement', 161, 0, '2026-05-05 13:25:43'),
(827, '789', '20913', 'announcement', 161, 0, '2026-05-05 13:25:48'),
(828, '271813', '20913', 'announcement', 161, 0, '2026-05-05 13:25:52'),
(855, '20913', 'T-15', 'room_post', 19, 1, '2026-05-15 18:53:50'),
(858, 'T-15', '20913', 'room_join', 9, 1, '2026-05-22 13:21:06'),
(859, 'T-15', '1701-00352', 'room_join', 2, 1, '2026-05-24 01:06:29'),
(860, 'T-15', '1701-00352', 'room_join', 4, 1, '2026-05-24 01:06:30'),
(861, 'T-15', '1701-00352', 'room_join', 7, 1, '2026-05-24 01:06:31'),
(862, 'T-15', '1701-00352', 'room_join', 9, 1, '2026-05-24 01:06:32'),
(863, '123456789', '1701-00352', 'reaction', 177, 1, '2026-05-31 00:50:25'),
(864, '123456789', '1701-00352', 'comment', 134, 0, '2026-06-03 10:21:27'),
(865, '123456789', '1701-00352', 'comment', 134, 0, '2026-06-03 10:21:32'),
(866, '123456789', '1701-00352', 'comment', 134, 0, '2026-06-03 10:21:33'),
(867, '123456789', '1701-00352', 'comment', 134, 0, '2026-06-03 10:21:34'),
(868, '123456789', '1701-00352', 'comment', 177, 0, '2026-06-05 08:55:05'),
(869, '123456789', '1701-00352', 'comment', 177, 0, '2026-06-05 08:55:11'),
(870, '123456789', '1701-00352', 'comment', 177, 0, '2026-06-05 08:55:18'),
(871, '123456789', '1701-00352', 'comment', 177, 0, '2026-06-05 08:55:22'),
(872, '123456789', '1701-00352', 'reaction', 89, 0, '2026-06-05 12:04:14'),
(903, 'T-15', '1701-00352', 'room_join', 10, 0, '2026-06-09 16:10:28'),
(904, '1701-00352', 'T-15', 'room_post', 20, 1, '2026-06-09 16:11:14'),
(905, 'T-15', '1701-00352', 'room_submission', 20, 0, '2026-06-09 16:12:38'),
(906, '1701-00352', 'T-15', 'room_post', 21, 1, '2026-06-09 21:09:00'),
(907, '1701-00352', 'T-15', 'room_post', 22, 1, '2026-06-09 21:40:02'),
(908, '1701-00352', 'T-15', 'room_post', 23, 1, '2026-06-09 21:40:44'),
(909, '1701-00352', 'T-15', 'room_post', 24, 1, '2026-06-09 21:41:35'),
(910, 'T-15', '1701-00352', 'room_submission', 24, 0, '2026-06-09 21:46:27'),
(911, 'T-15', '20913', 'room_join', 10, 0, '2026-06-09 21:48:33'),
(912, 'T-15', '2301-000111', 'room_join', 10, 0, '2026-06-09 21:52:50'),
(913, 'T-15', '2301-000111', 'room_submission', 24, 0, '2026-06-09 21:53:09'),
(914, 'T-15', '20913', 'room_submission', 24, 0, '2026-06-09 21:53:35'),
(915, '123456789', '1701-00352', 'comment', 177, 0, '2026-06-11 07:37:46'),
(916, '123456789', '2301-000428', 'comment', 177, 0, '2026-06-11 16:02:39'),
(917, '123456789', '2301-000428', 'comment', 177, 0, '2026-06-11 16:02:43'),
(918, '123456789', '2301-000428', 'comment', 177, 0, '2026-06-11 16:02:47'),
(919, '123456789', '2301-000428', 'comment', 134, 0, '2026-06-11 16:02:55'),
(920, 'T-15', '2301-000428', 'room_join', 10, 0, '2026-06-11 16:04:29'),
(921, '123456789', '1701-00352', 'comment', 177, 0, '2026-06-11 22:33:23');

-- --------------------------------------------------------

--
-- Table structure for table `password_change_requests`
--

CREATE TABLE `password_change_requests` (
  `id` int(11) NOT NULL,
  `admin_username` varchar(50) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `new_password` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `timestamp` datetime DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_change_requests`
--

INSERT INTO `password_change_requests` (`id`, `admin_username`, `student_id`, `new_password`, `status`, `timestamp`, `approved_at`) VALUES
(14, 'Justin', '1701-00352', '123', 'pending', '2026-03-14 10:09:08', NULL),
(15, 'JUSTIN', '111222', '1701-00352', 'pending', '2026-04-21 09:34:57', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `pending_profile_changes`
--

CREATE TABLE `pending_profile_changes` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `change_type` enum('email','phone') NOT NULL,
  `new_value` varchar(255) NOT NULL,
  `verification_token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `poll_options`
--

CREATE TABLE `poll_options` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `option_text` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `poll_votes`
--

CREATE TABLE `poll_votes` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `student_name` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `media` varchar(255) DEFAULT NULL,
  `type` enum('photo','video','text') DEFAULT 'text',
  `timestamp` datetime DEFAULT current_timestamp(),
  `category` varchar(50) DEFAULT 'General',
  `views` int(11) DEFAULT 0,
  `post_type` enum('text','poll') DEFAULT 'text'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `student_name`, `content`, `media`, `type`, `timestamp`, `category`, `views`, `post_type`) VALUES
(41, 'Sacli Official', '✨ Clean & Motivational\r\nPrelim Ready, Success Steady ✨\r\nGood luck in your Prelim Examination! Stay focused, trust your preparation, and give it your absolute best. You’ve got this! 💪✨\r\n#SACLIat40 #PrelimExam #Saclians', '', 'text', '2026-02-18 22:48:10', 'General', 0, 'text'),
(42, 'Sacli Official', 'Saclians, as we enter the Lenten season, let’s come together in faith. Join us for our Ash Wednesday Service as we pause, pray, and prepare our hearts.\r\n📍 Where: SACLI Chapel\r\n🕘 When: 9:00 AM\r\n✨ What: Ash Wednesday Service & Distribution of Ashes', '', 'text', '2026-02-18 22:49:03', 'Announcement', 0, 'text'),
(43, 'Sacli Official', '🔥 GAME ON, SACLians! 🔥\r\nGood luck to our SACLIAN Athletes as they compete in PRISAA 2026! 🏆\r\nMay your strength be unmatched, your teamwork unshaken, and your determination louder than the crowd. You carry not just your uniforms, but the pride, passion, and fighting spirit of SACLI.\r\nPlay with heart. Compete with honor. Bring home the victory — and make us proud! 💚🔥\r\n#SACLIPride #PRISAA2026 #GameOnSACLians #SACLIStrong', '', 'text', '2026-02-18 22:50:11', 'Announcement', 0, 'text'),
(45, 'Sacli Official', '🎉 Celebrate 40 Glorious Years of SACLI! 🎉\r\nThis March 3-6, 2026, join us as we honor four decades of excellence, growth, and unwavering SACLian pride! 💛💙\r\nFrom our humble beginnings to becoming a beacon of learning and community, this milestone is more than a celebration—it’s a tribute to our shared journey, achievements, and the bright future ahead. ✨\r\n📌 Kick off Day 1 with the official schedule of activities— Let us come together for fun, memories, and unforgettable festivities! 🎊\r\n📅 Save the dates and be part of SACLI’s biggest celebration yet!\r\n#SACLIat40 #40YearsOfExcellence #EmpoweringMindsShapingFutures #SACLIPride', '', 'text', '2026-02-19 00:16:48', 'General', 0, 'text'),
(46, 'Sacli Official', '🎉 Day 2 is Here — SACLI at 40! 🎉\r\nThe celebration continues as we unveil the official schedule for Day 2 of SACLI’s 40th Foundation Anniversary! 💛💙\r\n📌 Check out the schedule and join the fun!\r\n#SACLIat40 #SACLIPride #40YearsStrong #FoundationDay', '', 'text', '2026-02-19 00:44:02', 'Announcement', 0, 'text'),
(88, 'Sacli Official', 'IN PHOTOS 📸\r\nFrom the opening program of SACLI’s 40th Foundation Anniversary — marking the official opening of this milestone celebration. As the Foundation formally begins its 40th year, we celebrate four decades of excellence, unity, and service, honoring our legacy while inspiring a stronger, brighter future ahead. ✨\r\n#sacliat40', '', 'text', '2026-03-05 22:35:20', 'General', 0, 'text'),
(89, 'Sacli Official', 'IN PHOTOS | Day 1 of SACLI’s 40th Foundation Celebration 🎉\r\nWhat an incredible start! The energy, the unity, and the pride set the tone for a remarkable celebration of 40 years strong.\r\n#sacliat40', '', 'text', '2026-03-05 22:36:25', 'General', 0, 'text'),
(90, 'Sacli Official', 'Mr. and Ms. St. Anne 2026\r\nLIVE | Mr. and Ms. St. Anne 2026', '', 'text', '2026-03-05 23:03:24', 'General', 91, 'text'),
(134, 'Sacli Official', 'St Anne College Lucena City', '', 'text', '2026-03-17 10:46:24', 'General', 1, 'text'),
(177, 'Sacli Official', 'A night of excellence, gratitude, and new beginnings. ✨\r\nSt. Anne College Lucena, Inc. proudly celebrated the 2nd Quality Awards Night 2026 and the Blessing of the New IBED Building last May 22, 2026, together with the SACLI Family and valued stakeholders.\r\nThe momentous occasion was graced by the presence of CHED Region IV-A Director Dr. Rogelio Galera, alongside principals and representatives from selected public and private institutions, whose support and commitment continue to inspire SACLI in its pursuit of quality education and holistic development.\r\nAs we honor outstanding achievements and open the doors to new opportunities through our new IBED Building, we reaffirm our dedication to excellence, innovation, and service for the next generation of SACLIANS.\r\nTogether, we build brighter futures. 💚💚\r\n🎥📹 Mark Dique and Alvaro Garcia\r\n#SACLI\r\n#sacliibed \r\n#BeASACLIAN', NULL, 'text', '2026-05-30 15:17:40', 'General', 3, 'text');

-- --------------------------------------------------------

--
-- Table structure for table `post_comments`
--

CREATE TABLE `post_comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `is_pinned` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post_comments`
--

INSERT INTO `post_comments` (`id`, `post_id`, `student_id`, `comment`, `timestamp`, `is_pinned`) VALUES
(40, 90, 'T-15', 'Nice Go Sbeat!!!', '2026-03-10 20:37:16', 0),
(55, 156, 'T-15', 'sasax', '2026-04-23 11:19:09', 0),
(56, 156, 'T-15', 'sasax', '2026-04-23 11:20:17', 0),
(57, 156, 'T-15', 'sdcs', '2026-04-23 11:20:28', 0),
(58, 156, 'T-15', 'sdcs', '2026-04-23 11:20:38', 0),
(59, 156, 'T-15', 'sdcs', '2026-04-23 11:20:49', 0),
(60, 156, 'T-15', 'sdcs', '2026-04-23 11:20:53', 0),
(61, 156, 'T-15', 'sdcs', '2026-04-23 11:21:18', 0),
(62, 156, 'T-15', 'sdcs', '2026-04-23 11:22:12', 0),
(63, 156, 'T-15', 'sdcs', '2026-04-23 11:22:17', 0),
(93, 134, '1701-00352', 'zxcz', '2026-06-03 10:21:27', 0),
(94, 134, '1701-00352', 'qsaSSDASD', '2026-06-03 10:21:32', 0),
(95, 134, '1701-00352', 'DRGDFG', '2026-06-03 10:21:33', 0),
(96, 134, '1701-00352', 'SEF', '2026-06-03 10:21:34', 0),
(97, 177, '1701-00352', 'testing Commrnt 1', '2026-06-05 08:55:04', 0),
(98, 177, '1701-00352', 'testing Commrnt 2', '2026-06-05 08:55:11', 0),
(99, 177, '1701-00352', 'testing Commrnt 3', '2026-06-05 08:55:18', 0),
(100, 177, '1701-00352', 'testing Commrnt 4', '2026-06-05 08:55:22', 0),
(101, 177, '1701-00352', 'jjjj', '2026-06-11 07:37:46', 0),
(102, 177, '2301-000428', 'woww', '2026-06-11 16:02:39', 0),
(103, 177, '2301-000428', 'woww', '2026-06-11 16:02:43', 0),
(104, 177, '2301-000428', 'wow', '2026-06-11 16:02:47', 0),
(105, 134, '2301-000428', 'koko', '2026-06-11 16:02:55', 0),
(106, 177, '1701-00352', 'sdfdf', '2026-06-11 22:33:23', 0);

-- --------------------------------------------------------

--
-- Table structure for table `post_media`
--

CREATE TABLE `post_media` (
  `id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_type` varchar(20) DEFAULT NULL,
  `file_content` longblob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post_media`
--

INSERT INTO `post_media` (`id`, `post_id`, `file_path`, `file_type`, `file_content`) VALUES
(1, 37, 'uploads/post_37_6995777d71009.jpg', 'photo', NULL),
(2, 37, 'uploads/post_37_6995777d73c66.jpg', 'photo', NULL),
(3, 37, 'uploads/post_37_6995777d758c3.jpg', 'photo', NULL),
(4, 38, 'uploads/post_38_69957794c1c53.jpg', 'photo', NULL),
(5, 38, 'uploads/post_38_69957794c4094.jpg', 'photo', NULL),
(6, 38, 'uploads/post_38_69957794c7469.jpg', 'photo', NULL),
(7, 38, 'uploads/post_38_69957794c9a30.jpg', 'photo', NULL),
(8, 38, 'uploads/post_38_69957794cb7c5.jpg', 'photo', NULL),
(9, 39, 'uploads/post_39_6995786503024.jpg', 'photo', NULL),
(10, 39, 'uploads/post_39_6995786504917.jpg', 'photo', NULL),
(11, 39, 'uploads/post_39_69957865061d8.jpg', 'photo', NULL),
(12, 39, 'uploads/post_39_6995786508385.jpg', 'photo', NULL),
(13, 39, 'uploads/post_39_699578650a6c5.jpg', 'photo', NULL),
(14, 40, 'uploads/post_40_6995d108101cd.jpg', 'photo', NULL),
(15, 41, 'uploads/post_41_6995d12a9edef.jpg', 'photo', NULL),
(16, 42, 'uploads/post_42_6995d15f7cc91.jpg', 'photo', NULL),
(17, 43, 'uploads/post_43_6995d1a368e52.jpg', 'photo', NULL),
(18, 44, 'uploads/post_44_6995e52b5cd44.jpg', 'photo', NULL),
(19, 44, 'uploads/post_44_6995e52b5e572.jpg', 'photo', NULL),
(20, 45, 'uploads/post_45_6995e5f00a9d4.jpg', 'photo', NULL),
(21, 45, 'uploads/post_45_6995e5f00fd43.jpg', 'photo', NULL),
(22, 45, 'uploads/post_45_6995e5f01189e.jpg', 'photo', NULL),
(23, 45, 'uploads/post_45_6995e5f0131e5.jpg', 'photo', NULL),
(24, 45, 'uploads/post_45_6995e5f015412.jpg', 'photo', NULL),
(25, 45, 'uploads/post_45_6995e5f0172a2.jpg', 'photo', NULL),
(26, 45, 'uploads/post_45_6995e5f018956.jpg', 'photo', NULL),
(27, 46, 'uploads/post_46_6995ec52b40a7.jpg', 'photo', NULL),
(28, 46, 'uploads/post_46_6995ec52b54e4.jpg', 'photo', NULL),
(29, 46, 'uploads/post_46_6995ec52b6b61.jpg', 'photo', NULL),
(30, 46, 'uploads/post_46_6995ec52b84d7.jpg', 'photo', NULL),
(31, 46, 'uploads/post_46_6995ec52b9877.jpg', 'photo', NULL),
(52, 88, 'uploads/post_88_69a994a8ec2d1.jpg', 'photo', NULL),
(53, 88, 'uploads/post_88_69a994a8ee7e3.jpg', 'photo', NULL),
(54, 88, 'uploads/post_88_69a994a8f04f3.jpg', 'photo', NULL),
(55, 88, 'uploads/post_88_69a994a8f1d10.jpg', 'photo', NULL),
(56, 88, 'uploads/post_88_69a994a8f3623.jpg', 'photo', NULL),
(57, 89, 'uploads/post_89_69a994e92e67b.jpg', 'photo', NULL),
(58, 89, 'uploads/post_89_69a994e930071.jpg', 'photo', NULL),
(59, 89, 'uploads/post_89_69a994e93167c.jpg', 'photo', NULL),
(60, 89, 'uploads/post_89_69a994e933088.jpg', 'photo', NULL),
(61, 89, 'uploads/post_89_69a994e934ef2.jpg', 'photo', NULL),
(62, 90, 'uploads/post_90_69a99b3c6d98b.mp4', 'video', NULL),
(162, 115, 'uploads/post_115_69ae9369d124b.jpg', 'photo', NULL),
(163, 115, 'uploads/post_115_69ae9369d2f4d.jpg', 'photo', NULL),
(164, 115, 'uploads/post_115_69ae9369d487b.jpg', 'photo', NULL),
(165, 115, 'uploads/post_115_69ae9369d5fd2.jpg', 'photo', NULL),
(166, 115, 'uploads/post_115_69ae9369d7668.jpg', 'photo', NULL),
(167, 115, 'uploads/post_115_69ae9369d8cb0.jpg', 'photo', NULL),
(198, 129, 'uploads/post_129_69b2bd7012f50_St_Anne_College_Lucena_Inc.mp4', 'video', NULL),
(199, 134, 'uploads/post_134_69b8c080d6320_650218542_122182542692788541_6683336791685270075_n.jpg', 'photo', NULL),
(200, 134, 'uploads/post_134_69b8c080d802b_650223241_122182542776788541_7787240181103795767_n.jpg', 'photo', NULL),
(201, 134, 'uploads/post_134_69b8c080d984b_649276881_122182542806788541_3063760144860568667_n.jpg', 'photo', NULL),
(202, 134, 'uploads/post_134_69b8c080db4d7_650384829_122182542674788541_4393176556204612884_n.jpg', 'photo', NULL),
(203, 134, 'uploads/post_134_69b8c080dcba8_650332381_122182542722788541_6985798705808680093_n.jpg', 'photo', NULL),
(204, 134, 'uploads/post_134_69b8c080de6ba_650451089_122182542866788541_3551386303726740241_n.jpg', 'photo', NULL),
(217, 144, 'uploads/post_144_69e58f936f317_587507566_17925725586179089_8227554172079805731_n.jpg', 'photo', NULL),
(218, 144, 'uploads/post_144_69e58f93720df_544615199_17918123565179089_3352008008443960741_n.jpg', 'photo', NULL),
(219, 144, 'uploads/post_144_69e58f937b80f_547960528_796872453119820_207879741290099069_n.jpg', 'photo', NULL),
(220, 144, 'uploads/post_144_69e58f937e939_547103051_1458281408552671_3759545447072927958_n.png', 'photo', NULL),
(221, 144, 'uploads/post_144_69e58f9380fdc_546587312_1695005811212649_1388128326423530774_n.jpg', 'photo', NULL),
(224, 148, 'uploads/post_148_69e77bc137da0_587507566_17925725586179089_8227554172079805731_n.jpg', 'photo', NULL),
(225, 148, 'uploads/post_148_69e77bc13987d_544615199_17918123565179089_3352008008443960741_n.jpg', 'photo', NULL),
(226, 148, 'uploads/post_148_69e77bc13b07c_547960528_796872453119820_207879741290099069_n.jpg', 'photo', NULL),
(227, 148, 'uploads/post_148_69e77bc13d2f2_547103051_1458281408552671_3759545447072927958_n.png', 'photo', NULL),
(228, 148, 'uploads/post_148_69e77bc13f2a0_546587312_1695005811212649_1388128326423530774_n.jpg', 'photo', NULL),
(229, 149, 'uploads/post_149_69e77d5f18a6b_White_and_Red_Modern_We_Are_Hiring_Poster_423651b60a.png', 'photo', NULL),
(230, 149, 'uploads/post_149_69e77d5f1ab1e_job-hiring-poster-template-design-3313392b81ecc7ec6694c74f0fd1c5f4_screen.jpg', 'photo', NULL),
(242, 177, 'uploads/post_177_6a1a8f1501bf2.mp4', 'video', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `post_reactions`
--

CREATE TABLE `post_reactions` (
  `id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `type` varchar(20) DEFAULT 'heart',
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post_reactions`
--

INSERT INTO `post_reactions` (`id`, `post_id`, `student_id`, `type`, `timestamp`) VALUES
(97, 45, '2301-000076', 'heart', '2026-03-03 12:02:48'),
(123, 146, 'A23-8725', 'heart', '2026-04-21 17:12:02'),
(134, 156, 'T-15', 'heart', '2026-04-23 11:24:56'),
(138, 177, '1701-00352', 'heart', '2026-05-31 00:50:25'),
(139, 89, '1701-00352', 'heart', '2026-06-05 12:04:14');

-- --------------------------------------------------------

--
-- Table structure for table `post_tags`
--

CREATE TABLE `post_tags` (
  `id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post_tags`
--

INSERT INTO `post_tags` (`id`, `post_id`, `student_id`) VALUES
(1, 99, '1701-00352'),
(2, 99, '092025'),
(3, 105, '1701-00352'),
(4, 106, '1701-00352'),
(5, 136, 'T-15'),
(6, 137, '1701-00352'),
(7, 162, '2301-000111'),
(8, 162, '2301-000076'),
(9, 163, 'T-17'),
(10, 164, '20913'),
(11, 164, 'T-18'),
(12, 164, 'T-17'),
(13, 165, '2301-000428'),
(14, 165, '2202-000012'),
(15, 166, '20913'),
(16, 167, '2301-000111'),
(17, 219, '20913'),
(18, 220, '20913'),
(19, 221, '20913');

-- --------------------------------------------------------

--
-- Table structure for table `post_views_tracking`
--

CREATE TABLE `post_views_tracking` (
  `id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post_views_tracking`
--

INSERT INTO `post_views_tracking` (`id`, `post_id`, `student_id`, `timestamp`) VALUES
(1, 90, '111222', '2026-04-22 01:26:19'),
(2, 90, '271813', '2026-04-27 21:06:28'),
(3, 158, '111222', '2026-05-03 17:16:33'),
(4, 90, '1701-00352', '2026-05-05 20:12:42'),
(5, 90, 'T-15', '2026-05-15 19:01:35'),
(6, 90, '20913', '2026-05-22 19:03:53'),
(7, 177, '123456789', '2026-05-30 15:19:22'),
(8, 177, '1701-00352', '2026-05-30 15:31:19'),
(9, 177, '20913', '2026-05-30 20:28:35'),
(10, 197, '1701-00352', '2026-06-03 20:44:21'),
(11, 198, '1701-00352', '2026-06-03 22:08:34'),
(12, 134, '1701-00352', '2026-06-07 16:17:41'),
(13, 90, '2301-000428', '2026-06-11 16:03:03');

-- --------------------------------------------------------

--
-- Table structure for table `sacli_meetings`
--

CREATE TABLE `sacli_meetings` (
  `id` int(11) NOT NULL,
  `meeting_code` varchar(20) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sacli_meetings`
--

INSERT INTO `sacli_meetings` (`id`, `meeting_code`, `host_id`, `created_at`, `is_active`) VALUES
(1, 'ROOM-336-361', 'T-15', '2026-03-22 13:48:03', 1),
(2, 'ROOM-288-723', '1701-00352', '2026-03-22 13:48:36', 1),
(3, 'ROOM-236-770', '1701-00352', '2026-03-22 13:48:37', 1),
(4, 'ROOM-898-601', '1701-00352', '2026-03-22 13:48:37', 1),
(5, 'MEET-07ECFA', 'T-15', '2026-03-22 14:16:40', 1),
(6, 'MEET-300187', 'T-15', '2026-03-22 15:04:54', 1),
(7, 'MEET-8928B3', '1701-00352', '2026-03-22 15:24:12', 1),
(8, 'MEET-A365E9', 'T-15', '2026-03-22 16:06:48', 1),
(9, 'MEET-1F24E6', 'T-15', '2026-03-22 16:10:52', 1),
(10, 'MEET-D7E732', '1701-00352', '2026-03-22 16:16:26', 1),
(11, 'MEET-1283F0', '1701-00352', '2026-03-22 16:34:59', 1),
(12, 'MEET-CBA45B', '1701-00352', '2026-03-22 17:23:08', 1),
(13, 'MEET-389E4D', '1701-00352', '2026-03-22 17:45:01', 1),
(14, 'MEET-85CE31', '1701-00352', '2026-03-22 18:37:27', 1),
(15, 'MEET-8578EF', '1701-00352', '2026-03-22 19:08:04', 1),
(16, 'MEET-4AAA15', '1701-00352', '2026-03-22 19:08:15', 1),
(17, 'MEET-8EB107', '1701-00352', '2026-03-22 19:19:50', 1),
(18, 'MEET-DE5894', '1701-00352', '2026-03-22 20:00:14', 1),
(19, 'MEET-7D3DAC', '1701-00352', '2026-03-22 20:28:43', 1),
(20, 'MEET-2623EA', '1701-00352', '2026-03-22 20:28:46', 1),
(21, 'MEET-A3F50F', 'T-15', '2026-03-23 11:27:13', 1),
(22, 'MEET-B716A1', 'T-15', '2026-03-23 11:27:19', 1),
(23, 'MEET-363B1B', 'T-15', '2026-03-23 13:42:28', 1),
(24, 'MEET-C31D4D', 'T-15', '2026-03-23 13:43:05', 1),
(25, 'MEET-7374F6', 'T-15', '2026-03-23 13:51:50', 1),
(26, 'MEET-EC7520', 'T-15', '2026-03-23 13:52:02', 1),
(27, 'MEET-5E9D85', 'T-15', '2026-03-23 13:55:04', 1),
(28, 'MEET-B60B97', 'T-15', '2026-03-23 13:55:36', 1),
(29, 'MEET-0BD94A', '1701-00352', '2026-03-23 14:13:31', 1),
(30, 'MEET-56D20C', '1701-00352', '2026-03-23 14:13:32', 1),
(31, 'MEET-FB1B0E', '1701-00352', '2026-03-23 14:16:05', 1),
(32, 'MEET-183168', 'T-15', '2026-03-23 14:39:24', 1),
(33, 'MEET-4B2D71', 'T-15', '2026-03-23 20:29:50', 1),
(34, 'MEET-CDE8A7', '1701-00352', '2026-03-23 20:32:29', 1),
(35, 'MEET-C80C96', 'T-15', '2026-03-23 20:34:01', 1),
(36, 'MEET-6B3B33', 'T-15', '2026-03-23 20:34:21', 1),
(37, 'MEET-EB7465', 'T-15', '2026-03-23 20:34:41', 1),
(38, 'MEET-EC86B6', 'T-15', '2026-03-23 20:35:21', 1),
(39, 'MEET-D34B80', 'T-15', '2026-03-23 20:35:37', 1),
(40, 'MEET-FB75AA', 'T-15', '2026-03-23 20:36:04', 1),
(41, 'MEET-5E3A53', 'T-15', '2026-03-23 20:37:20', 1),
(42, 'MEET-0207A4', 'T-15', '2026-03-23 20:38:03', 1),
(43, 'MEET-FBAD49', 'T-15', '2026-03-23 20:38:05', 1),
(44, 'MEET-54DDB5', 'T-15', '2026-03-23 20:39:03', 1),
(45, 'MEET-7F3583', 'T-15', '2026-03-23 20:42:17', 1),
(46, 'MEET-040D9D', 'T-15', '2026-03-23 20:42:51', 1),
(47, 'MEET-8DAA57', 'T-15', '2026-03-23 20:42:54', 1),
(48, 'MEET-CDCB7B', 'T-15', '2026-03-23 20:43:00', 1),
(49, 'MEET-563BF8', 'T-15', '2026-03-23 20:43:19', 1),
(50, 'MEET-7D6A7C', 'T-15', '2026-03-23 20:43:24', 1),
(51, 'MEET-10A980', 'T-15', '2026-03-23 20:43:40', 1),
(52, 'MEET-BF753F', 'T-15', '2026-03-23 20:44:01', 1),
(53, 'MEET-809F94', 'T-15', '2026-03-23 20:58:58', 1),
(54, 'MEET-ACDADD', 'T-15', '2026-03-23 20:59:10', 1),
(55, 'MEET-A49AB6', 'T-15', '2026-03-23 20:59:29', 1),
(56, 'MEET-21011A', 'T-15', '2026-03-23 20:59:47', 1),
(57, 'MEET-DF4C8B', 'T-15', '2026-03-23 21:00:23', 1),
(58, 'MEET-D85CB4', 'T-15', '2026-03-23 21:01:10', 1),
(59, 'MEET-7F5896', 'T-15', '2026-03-23 21:01:20', 1),
(60, 'MEET-39EA5A', 'T-15', '2026-03-23 21:01:36', 1),
(61, 'MEET-A09779', 'T-15', '2026-03-23 21:02:23', 1),
(62, 'MEET-3CBCDD', 'T-15', '2026-03-23 21:05:39', 1),
(63, 'MEET-D5EA14', 'T-15', '2026-03-23 21:05:43', 1),
(64, 'MEET-EE1BA5', 'T-15', '2026-03-23 21:13:00', 1),
(65, 'MEET-E09B12', '1701-00352', '2026-03-23 21:15:10', 1),
(66, 'MEET-67A0B3', 'T-15', '2026-03-23 23:51:13', 1),
(67, 'MEET-E3A4BE', 'T-15', '2026-03-23 23:52:52', 1),
(68, 'MEET-285DE1', '1701-00352', '2026-03-23 23:52:58', 1),
(69, 'MEET-21044E', 'T-15', '2026-03-23 23:54:26', 1),
(70, 'MEET-AC3D40', '1701-00352', '2026-03-23 23:55:30', 1),
(71, 'MEET-770CD6', '1701-00352', '2026-03-24 14:44:20', 1),
(72, 'MEET-00547C', '20913', '2026-03-24 16:25:55', 1),
(73, 'MEET-93EDF7', '1701-00352', '2026-03-24 16:26:11', 1),
(74, 'MEET-80D7AA', '1701-00352', '2026-03-24 16:26:40', 1),
(75, 'MEET-1072DB', '20913', '2026-03-24 16:28:08', 1),
(76, 'MEET-6D1089', '1701-00352', '2026-03-24 22:03:59', 1),
(77, 'MEET-E45275', '1701-00352', '2026-03-24 22:04:01', 1),
(78, 'MEET-CCA989', '1701-00352', '2026-03-24 22:33:39', 1),
(79, 'MEET-94DE21', '1701-00352', '2026-03-25 06:21:13', 1),
(80, 'MEET-076199', '1701-00352', '2026-03-25 06:21:18', 1),
(81, 'MEET-0E5103', '1701-00352', '2026-03-25 09:12:58', 1),
(82, 'MEET-C2E3B5', '1701-00352', '2026-03-25 09:24:33', 1),
(83, 'MEET-0D0EAD', '1701-00352', '2026-03-25 09:35:37', 1),
(84, 'MEET-B12CE3', '1701-00352', '2026-03-25 09:38:14', 1),
(85, 'MEET-4C83E9', '1701-00352', '2026-03-25 09:42:42', 1),
(86, 'MEET-C20107', '1701-00352', '2026-03-25 09:47:09', 1),
(87, 'MEET-5A640B', 'T-15', '2026-03-25 22:21:47', 1),
(88, 'MEET-1F86FD', 'T-15', '2026-03-25 22:25:19', 1),
(89, 'SACLI-676764', '1701-00352', '2026-04-17 19:42:51', 1),
(90, 'SACLI-0CB29B', '1701-00352', '2026-04-17 20:28:43', 1),
(91, 'SACLI-D81338', '1701-00352', '2026-04-17 20:41:52', 1),
(92, 'SACLI-CC2F88', '1701-00352', '2026-04-17 20:43:33', 1),
(93, 'SACLI-BA04A7', '1701-00352', '2026-04-18 03:30:35', 1),
(94, 'SACLI-DDC48E', '20913', '2026-04-18 03:34:32', 1),
(95, 'SACLI-2991EB', '1701-00352', '2026-04-18 13:08:28', 1),
(96, 'SACLI-B34A82', '1701-00352', '2026-04-18 13:18:09', 1),
(97, 'SACLI-C8F8AF', '1701-00352', '2026-04-18 13:28:58', 1),
(98, 'SACLI-65B446', '1701-00352', '2026-04-18 13:40:22', 1),
(99, 'SACLI-20D994', '1701-00352', '2026-04-18 13:40:55', 1),
(100, 'SACLI-4A96DE', '1701-00352', '2026-04-18 17:05:28', 1),
(101, 'SACLI-61610D', '1701-00352', '2026-04-18 17:07:12', 1),
(102, 'SACLI-198C38', '1701-00352', '2026-04-18 17:09:33', 1),
(103, 'SACLI-157F72', '111222', '2026-04-20 17:18:38', 1),
(104, 'SACLI-84C526', '111222', '2026-04-21 09:32:34', 1),
(105, 'SACLI-CF1F7D', '111222', '2026-04-21 14:02:58', 1),
(106, 'SACLI-E21B36', '111222', '2026-04-23 11:29:16', 1),
(107, 'SACLI-1E55FB', '111222', '2026-05-03 16:32:05', 1),
(108, 'SACLI-2E28B8', '1701-00352', '2026-05-15 18:10:57', 1),
(109, 'SACLI-1C4717', 'Admin', '2026-05-18 08:33:43', 1),
(110, 'SACLI-F6A064', '20913', '2026-05-22 18:04:23', 1),
(111, 'SACLI-A96D15', '1701-00352', '2026-05-24 01:07:15', 1),
(112, 'SACLI-FAD601', '1701-00352', '2026-05-29 09:56:53', 1),
(113, 'SACLI-641638', '1701-00352', '2026-06-09 16:13:52', 1),
(114, 'SACLI-C8469B', '2301-000428', '2026-06-11 16:05:10', 1);

-- --------------------------------------------------------

--
-- Table structure for table `sacli_meeting_logs`
--

CREATE TABLE `sacli_meeting_logs` (
  `id` int(11) NOT NULL,
  `room_code` varchar(50) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `joined_at` datetime DEFAULT NULL,
  `left_at` datetime DEFAULT NULL,
  `host_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sacli_meeting_logs`
--

INSERT INTO `sacli_meeting_logs` (`id`, `room_code`, `student_id`, `joined_at`, `left_at`, `host_name`) VALUES
(1, 'SACLI-V3G6BA', '1701-00352', '2026-03-30 17:38:14', '2026-03-30 17:38:59', NULL),
(2, 'SACLI-MIVZR5', '1701-00352', '2026-03-30 17:48:27', '2026-03-30 18:16:07', NULL),
(3, 'SACLI-6PMRNG', '1701-00352', '2026-03-30 18:22:24', '2026-03-30 18:27:08', NULL),
(4, 'SACLI-9YSI0D', '1701-00352', '2026-04-03 03:51:50', '2026-04-03 03:52:02', NULL),
(5, 'SACLI-N9O4GB', '1701-00352', '2026-04-13 05:38:11', '2026-04-13 05:38:33', NULL),
(6, 'SACLI-QSTNW3', '1701-00352', '2026-04-14 15:56:07', '2026-04-14 15:56:12', NULL),
(7, 'SACLI-QKEODF', '1701-00352', '2026-04-14 15:56:17', '2026-04-14 15:56:19', NULL),
(8, 'MEET-8928B3', '1701-00352', '2026-04-14 15:56:22', '2026-04-14 15:56:23', NULL),
(9, 'SACLI-M11J61', '1701-00352', '2026-04-15 18:02:33', '2026-04-15 18:02:48', 'Justin Ritardo'),
(10, 'SACLI-ZYLI07', '20913', '2026-04-16 16:01:11', '2026-04-16 16:03:29', 'Princess Ritardo'),
(11, 'SACLI-676764', '1701-00352', '2026-04-17 13:42:53', '2026-04-17 14:28:33', 'Justin Ritardo'),
(12, 'SACLI-676764', '1701-00352', '2026-04-17 14:28:35', '2026-04-17 14:28:40', 'Justin Ritardo'),
(13, 'SACLI-676764', '1701-00352', '2026-04-17 14:28:41', '2026-04-17 14:28:41', 'Justin Ritardo'),
(14, 'SACLI-0CB29B', '1701-00352', '2026-04-17 14:28:44', '2026-04-17 14:40:25', 'Justin Ritardo'),
(15, 'SACLI-0CB29B', '1701-00352', '2026-04-17 14:40:26', '2026-04-17 14:41:39', 'Justin Ritardo'),
(16, 'SACLI-D81338', '1701-00352', '2026-04-17 14:41:53', '2026-04-17 14:42:35', 'Justin Ritardo'),
(17, 'SACLI-CC2F88', '1701-00352', '2026-04-17 14:43:34', '2026-04-17 14:44:00', 'Justin Ritardo'),
(18, 'SACLI-CC2F88', '1701-00352', '2026-04-17 14:44:01', '2026-04-17 14:47:01', 'Justin Ritardo'),
(19, 'SACLI-CC2F88', '1701-00352', '2026-04-17 14:47:02', '2026-04-17 14:56:23', 'Justin Ritardo'),
(20, 'SACLI-CC2F88', '1701-00352', '2026-04-17 14:56:25', '2026-04-17 16:18:07', 'Justin Ritardo'),
(21, 'SACLI-CC2F88', '1701-00352', '2026-04-17 16:18:08', '2026-04-17 16:54:00', 'Justin Ritardo'),
(22, 'SACLI-BA04A7', '1701-00352', '2026-04-17 21:30:36', '2026-04-17 21:36:26', 'Justin Ritardo'),
(23, 'SACLI-2991EB', '1701-00352', '2026-04-18 07:08:30', '2026-04-18 07:11:44', 'Justin Ritardo'),
(24, 'SACLI-2991EB', '1701-00352', '2026-04-18 07:11:45', '2026-04-18 07:16:30', 'Justin Ritardo'),
(25, 'SACLI-B34A82', '1701-00352', '2026-04-18 07:18:10', '2026-04-18 07:19:20', 'Justin Ritardo'),
(26, 'SACLI-B34A82', '1701-00352', '2026-04-18 07:19:24', '2026-04-18 07:20:39', 'Justin Ritardo'),
(27, 'SACLI-C8F8AF', '1701-00352', '2026-04-18 07:29:00', '2026-04-18 07:29:51', 'Justin Ritardo'),
(28, 'SACLI-C8F8AF', '1701-00352', '2026-04-18 07:29:52', '2026-04-18 07:29:52', 'Justin Ritardo'),
(29, 'SACLI-C8F8AF', '1701-00352', '2026-04-18 07:29:53', '2026-04-18 07:30:42', 'Justin Ritardo'),
(30, 'SACLI-C8F8AF', '1701-00352', '2026-04-18 07:30:43', '2026-04-18 07:40:15', 'Justin Ritardo'),
(31, 'SACLI-C8F8AF', '1701-00352', '2026-04-18 07:40:17', '2026-04-18 07:40:17', 'Justin Ritardo'),
(32, 'SACLI-C8F8AF', '1701-00352', '2026-04-18 07:40:18', '2026-04-18 07:40:20', 'Justin Ritardo'),
(33, 'SACLI-65B446', '1701-00352', '2026-04-18 07:40:23', '2026-04-18 07:40:38', 'Justin Ritardo'),
(34, 'SACLI-20D994', '1701-00352', '2026-04-18 07:40:55', '2026-04-18 07:40:58', 'Justin Ritardo'),
(35, 'SACLI-4A96DE', '1701-00352', '2026-04-18 11:05:30', '2026-04-18 11:05:53', 'Justin Ritardo'),
(36, 'SACLI-61610D', '1701-00352', '2026-04-18 11:07:14', '2026-04-18 11:08:05', 'Justin Ritardo'),
(37, 'SACLI-61610D', '1701-00352', '2026-04-18 11:08:07', '2026-04-18 11:09:28', 'Justin Ritardo'),
(38, 'SACLI-198C38', '1701-00352', '2026-04-18 11:09:34', '2026-04-18 11:15:43', 'Justin Ritardo'),
(39, 'SACLI-157F72', '1701-00352', '2026-04-20 11:18:40', '2026-04-20 11:19:30', 'Justin Ritardo'),
(40, 'SACLI-84C526', '1701-00352', '2026-04-21 03:32:36', '2026-04-21 03:33:41', 'Justin Ritardo'),
(41, 'SACLI-CF1F7D', '1701-00352', '2026-04-21 08:02:59', '2026-04-21 08:03:01', 'Justin Ritardo'),
(42, 'SACLI-E21B36', '1701-00352', '2026-04-23 05:29:17', '2026-04-23 05:29:22', 'Justin Ritardo'),
(43, 'SACLI-1E55FB', '1701-00352', '2026-05-03 10:32:06', '2026-05-03 10:32:32', 'Justin Ritardo'),
(44, 'SACLI-2E28B8', '1701-00352', '2026-05-15 12:10:58', '2026-05-15 12:11:20', 'Justin Ritardo'),
(45, 'SACLI-1C4717', 'Admin', '2026-05-18 02:33:44', '2026-05-18 02:34:46', 'Admin'),
(46, 'SACLI-F6A064', '20913', '2026-05-22 12:04:27', '2026-05-22 12:04:47', 'Princess Ritardo'),
(47, 'SACLI-A96D15', '1701-00352', '2026-05-23 19:07:16', '2026-05-23 19:07:28', 'Justin Ritardo'),
(48, 'SACLI-FAD601', '1701-00352', '2026-05-29 03:57:05', '2026-05-29 04:07:08', 'Justin Ritardo'),
(49, 'SACLI-641638', '1701-00352', '2026-06-09 10:13:53', '2026-06-09 10:14:26', 'Justin Ritardo'),
(50, 'SACLI-C8469B', '2301-000428', '2026-06-11 10:05:11', '2026-06-11 10:05:40', 'Kory Chester Atendido');

-- --------------------------------------------------------

--
-- Table structure for table `sacli_meeting_participants`
--

CREATE TABLE `sacli_meeting_participants` (
  `id` int(11) NOT NULL,
  `meeting_code` varchar(20) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `status` enum('waiting','admitted','denied') DEFAULT 'waiting',
  `joined_at` datetime DEFAULT current_timestamp(),
  `is_cam_on` tinyint(1) DEFAULT 1,
  `is_mic_on` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sacli_meeting_participants`
--

INSERT INTO `sacli_meeting_participants` (`id`, `meeting_code`, `student_id`, `status`, `joined_at`, `is_cam_on`, `is_mic_on`) VALUES
(1, 'ROOM-336-361', 'T-15', 'admitted', '2026-03-22 13:48:03', 1, 1),
(2, 'ROOM-288-723', '1701-00352', 'admitted', '2026-03-22 13:48:36', 1, 1),
(3, 'ROOM-236-770', '1701-00352', 'admitted', '2026-03-22 13:48:37', 1, 1),
(4, 'ROOM-898-601', '1701-00352', 'admitted', '2026-03-22 13:48:37', 1, 1),
(5, 'MEET-07ECFA', '1701-00352', 'admitted', '2026-03-22 14:16:52', 1, 1),
(6, 'MEET-300187', '1701-00352', 'admitted', '2026-03-22 15:05:02', 1, 1),
(7, 'MEET-300187', '123456789', 'admitted', '2026-03-22 15:06:09', 1, 1),
(9, 'MEET-8928B3', 'T-15', 'admitted', '2026-03-22 15:24:47', 1, 1),
(10, 'MEET-A365E9', '1701-00352', 'admitted', '2026-03-22 16:07:00', 1, 1),
(11, 'MEET-1F24E6', '1701-00352', 'admitted', '2026-03-22 16:11:17', 1, 1),
(12, 'MEET-D7E732', 'T-15', 'admitted', '2026-03-22 16:16:46', 1, 1),
(13, 'MEET-1283F0', 'T-15', 'admitted', '2026-03-22 16:35:17', 1, 1),
(14, 'MEET-CBA45B', 'T-15', 'admitted', '2026-03-22 17:23:26', 1, 1),
(15, 'MEET-389E4D', 'T-15', 'admitted', '2026-03-22 17:45:09', 1, 1),
(16, 'MEET-85CE31', 'T-15', 'admitted', '2026-03-22 18:37:38', 1, 1),
(17, 'MEET-EC7520', '1701-00352', 'admitted', '2026-03-23 13:52:26', 1, 1),
(19, 'MEET-56D20C', 'T-15', 'admitted', '2026-03-23 14:13:46', 1, 1),
(20, 'MEET-FB1B0E', 'T-15', 'admitted', '2026-03-23 14:16:23', 1, 1),
(21, 'MEET-E09B12', 'T-15', 'admitted', '2026-03-23 21:15:20', 1, 1),
(22, 'MEET-67A0B3', 'T-15', 'admitted', '2026-03-23 23:51:13', 0, 1),
(23, 'MEET-67A0B3', '1701-00352', 'admitted', '2026-03-23 23:51:22', 1, 1),
(26, 'MEET-E3A4BE', 'T-15', 'admitted', '2026-03-23 23:52:53', 0, 1),
(27, 'MEET-285DE1', '1701-00352', 'admitted', '2026-03-23 23:52:59', 0, 1),
(28, 'MEET-285DE1', 'T-15', 'admitted', '2026-03-23 23:53:19', 0, 1),
(41, 'MEET-21044E', 'T-15', 'admitted', '2026-03-23 23:54:26', 1, 1),
(42, 'MEET-21044E', '1701-00352', 'admitted', '2026-03-23 23:54:35', 1, 1),
(56, 'MEET-AC3D40', '1701-00352', 'admitted', '2026-03-23 23:55:30', 1, 1),
(57, 'MEET-770CD6', '1701-00352', 'admitted', '2026-03-24 14:44:24', 1, 1),
(58, 'MEET-00547C', '20913', 'admitted', '2026-03-24 16:25:56', 1, 1),
(59, 'MEET-00547C', '1701-00352', 'admitted', '2026-03-24 16:26:14', 1, 1),
(65, 'MEET-80D7AA', '1701-00352', 'admitted', '2026-03-24 16:26:41', 1, 1),
(66, 'MEET-80D7AA', '20913', 'admitted', '2026-03-24 16:26:51', 1, 1),
(67, 'MEET-1072DB', '20913', 'admitted', '2026-03-24 16:28:09', 1, 1),
(68, 'MEET-1072DB', '1701-00352', 'admitted', '2026-03-24 16:28:17', 0, 1),
(83, 'MEET-6D1089', '1701-00352', 'admitted', '2026-03-24 22:04:02', 1, 1),
(84, 'MEET-E45275', '1701-00352', 'admitted', '2026-03-24 22:04:02', 1, 1),
(85, 'MEET-CCA989', '1701-00352', 'admitted', '2026-03-24 22:33:41', 1, 1),
(86, 'MEET-CCA989', '20913', 'admitted', '2026-03-24 22:34:12', 1, 1),
(94, 'MEET-076199', '1701-00352', 'admitted', '2026-03-25 06:21:19', 1, 1),
(95, 'MEET-0E5103', '1701-00352', 'admitted', '2026-03-25 09:13:00', 1, 1),
(96, 'MEET-C2E3B5', '1701-00352', 'admitted', '2026-03-25 09:24:35', 1, 1),
(97, 'MEET-0D0EAD', '1701-00352', 'admitted', '2026-03-25 09:35:39', 1, 1),
(98, 'MEET-B12CE3', '1701-00352', 'admitted', '2026-03-25 09:38:16', 1, 1),
(99, 'MEET-4C83E9', '1701-00352', 'admitted', '2026-03-25 09:42:44', 1, 1),
(100, 'MEET-C20107', '1701-00352', 'admitted', '2026-03-25 09:47:10', 1, 1),
(101, 'MEET-5A640B', 'T-15', 'admitted', '2026-03-25 22:21:49', 1, 1),
(102, 'MEET-5A640B', '1701-00352', 'admitted', '2026-03-25 22:22:00', 1, 1),
(108, 'MEET-1F86FD', 'T-15', 'admitted', '2026-03-25 22:25:20', 1, 1),
(109, 'MEET-1F86FD', '1701-00352', 'waiting', '2026-03-25 22:25:34', 1, 1),
(110, 'SACLI-BA04A7', '20913', 'waiting', '2026-04-18 03:34:07', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `sacli_rooms`
--

CREATE TABLE `sacli_rooms` (
  `id` int(11) NOT NULL,
  `teacher_id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `room_code` varchar(10) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sacli_rooms`
--

INSERT INTO `sacli_rooms` (`id`, `teacher_id`, `name`, `description`, `room_code`, `created_at`) VALUES
(2, 'T-15', 'Programing', '', 'JASDDS', '2026-03-10 18:38:33'),
(4, 'T-15', 'English', '', '123456789', '2026-03-10 21:58:38'),
(7, 'T-15', 'math', '', '123456', '2026-03-12 15:47:09'),
(9, 'T-15', 'Civil', 'engr', 'A2323', '2026-04-21 16:39:16'),
(10, 'T-15', 'English', '', '123789', '2026-06-09 16:10:09');

-- --------------------------------------------------------

--
-- Table structure for table `sacli_room_invitations`
--

CREATE TABLE `sacli_room_invitations` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `teacher_id` varchar(50) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `status` enum('pending','accepted','declined') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sacli_room_invitations`
--

INSERT INTO `sacli_room_invitations` (`id`, `room_id`, `teacher_id`, `student_id`, `status`, `created_at`) VALUES
(1, 9, 'T-15', '20913', 'accepted', '2026-05-22 13:07:06'),
(12, 9, 'T-15', '1701-00352', 'accepted', '2026-05-23 10:46:54'),
(13, 7, 'T-15', '1701-00352', 'accepted', '2026-05-23 11:30:21'),
(14, 4, 'T-15', '1701-00352', 'accepted', '2026-05-23 11:30:41'),
(15, 2, 'T-15', '1701-00352', 'accepted', '2026-05-23 11:30:58');

-- --------------------------------------------------------

--
-- Table structure for table `sacli_room_members`
--

CREATE TABLE `sacli_room_members` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `role` enum('teacher','student') DEFAULT 'student',
  `joined_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sacli_room_members`
--

INSERT INTO `sacli_room_members` (`id`, `room_id`, `student_id`, `role`, `joined_at`) VALUES
(2, 2, 'T-15', 'teacher', '2026-03-10 18:38:33'),
(4, 4, 'T-15', 'teacher', '2026-03-10 21:58:38'),
(9, 7, 'T-15', 'teacher', '2026-03-12 15:47:09'),
(12, 7, '20913', 'student', '2026-03-12 23:45:19'),
(17, 9, 'T-15', 'teacher', '2026-04-21 16:39:16'),
(20, 2, '1701-00352', 'student', '2026-05-24 01:06:29'),
(21, 4, '1701-00352', 'student', '2026-05-24 01:06:30'),
(22, 7, '1701-00352', 'student', '2026-05-24 01:06:31'),
(23, 9, '1701-00352', 'student', '2026-05-24 01:06:32'),
(24, 10, 'T-15', 'teacher', '2026-06-09 16:10:09'),
(25, 10, '1701-00352', 'student', '2026-06-09 16:10:28'),
(26, 10, '20913', 'student', '2026-06-09 21:48:33'),
(27, 10, '2301-000111', 'student', '2026-06-09 21:52:50'),
(28, 10, '2301-000428', 'student', '2026-06-11 16:04:29');

-- --------------------------------------------------------

--
-- Table structure for table `sacli_room_posts`
--

CREATE TABLE `sacli_room_posts` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sacli_room_posts`
--

INSERT INTO `sacli_room_posts` (`id`, `room_id`, `user_id`, `title`, `content`, `due_date`, `created_at`) VALUES
(17, 7, 'T-15', 'Programing', 'pakibilisan bwiset', '2026-04-30 15:59:00', '2026-04-22 10:55:45'),
(18, 7, 'T-15', 'Data Data', 'Until Tom', '2026-04-29 23:59:00', '2026-04-28 20:32:55'),
(21, 10, 'T-15', 'mvjhvj', 'jgvhvb', '2026-05-22 23:59:00', '2026-06-09 21:09:00'),
(22, 10, 'T-15', 'sdsdf', 'sfsdf', '2026-06-09 12:42:00', '2026-06-09 21:40:02'),
(23, 10, 'T-15', 'sdfsdfsdf', 'dsfsfs', '2026-02-20 23:59:00', '2026-06-09 21:40:44'),
(24, 10, 'T-15', 'asdasd', 'sdfsdf', '2026-02-12 23:59:00', '2026-06-09 21:41:35');

-- --------------------------------------------------------

--
-- Table structure for table `sacli_room_post_attachments`
--

CREATE TABLE `sacli_room_post_attachments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_content` longblob DEFAULT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sacli_room_post_attachments`
--

INSERT INTO `sacli_room_post_attachments` (`id`, `post_id`, `file_path`, `file_content`, `original_filename`, `file_type`, `uploaded_at`) VALUES
(6, 21, 'uploads/roompost_21_6a28107031670_TimDan.pdf', NULL, 'TimDan.pdf', 'application/pdf', '2026-06-09 21:09:04'),
(7, 23, 'uploads/roompost_23_6a2817e0246ef_TimDan.pdf', NULL, 'TimDan.pdf', 'application/pdf', '2026-06-09 21:40:48'),
(8, 24, 'uploads/roompost_24_6a281812e83c6_CTF_Activity.docx', NULL, 'CTF_Activity.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '2026-06-09 21:41:38');

-- --------------------------------------------------------

--
-- Table structure for table `sacli_room_submissions`
--

CREATE TABLE `sacli_room_submissions` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_content` longblob DEFAULT NULL,
  `submitted_at` datetime DEFAULT current_timestamp(),
  `grade` varchar(10) DEFAULT NULL,
  `comments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sacli_room_submissions`
--

INSERT INTO `sacli_room_submissions` (`id`, `post_id`, `student_id`, `file_path`, `file_content`, `submitted_at`, `grade`, `comments`) VALUES
(5, 24, '1701-00352', 'uploads/submissions/sub_24_1701-00352_6a281933a0d8e_TimDan.pdf', NULL, '2026-06-09 21:46:27', '30/50', NULL),
(6, 24, '2301-000111', 'uploads/submissions/sub_24_2301-000111_6a281ac52d15a_TimDan.pdf', NULL, '2026-06-09 21:53:09', '35/50', NULL),
(7, 24, '20913', 'uploads/submissions/sub_24_20913_6a281adfa9145_TimDan.pdf', NULL, '2026-06-09 21:53:35', '50/50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `security_audit_logs`
--

CREATE TABLE `security_audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `event_type` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'low',
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_audit_logs`
--

INSERT INTO `security_audit_logs` (`id`, `user_id`, `event_type`, `ip_address`, `user_agent`, `severity`, `timestamp`) VALUES
(1, '1701-00352', 'PASSWORD_CHANGE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'medium', '2026-04-19 18:38:51'),
(2, '1701-00352', 'PASSWORD_CHANGE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'medium', '2026-04-21 09:37:37'),
(3, '1701-00352', 'PASSWORD_CHANGE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'medium', '2026-04-21 09:40:46'),
(4, '1701-00352', 'PASSWORD_CHANGE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'medium', '2026-05-04 22:48:01');

-- --------------------------------------------------------

--
-- Table structure for table `sidebar_menu`
--

CREATE TABLE `sidebar_menu` (
  `id` int(11) NOT NULL,
  `label` varchar(100) NOT NULL,
  `icon` varchar(255) DEFAULT '',
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sidebar_menu`
--

INSERT INTO `sidebar_menu` (`id`, `label`, `icon`, `sort_order`) VALUES
(308, 'Dashboard', '1icons8-dashboard-50.png', 1),
(309, 'Announcements', '2icons8-announcement-50.png', 2),
(310, 'Students', '3icons8-student-64.png', 3),
(311, 'Teachers', '4icons8-teacher-50.png', 4),
(312, 'Alumni', 'book1.png', 5),
(313, 'Achievements', '5icons8-assignment-50.png', 6),
(314, 'Calendar', '6icons8-calendar-50.png', 7),
(315, 'Organizations', '7icons8-organization-64.png', 8),
(316, 'History and Password', '8icons8-setting-50.png', 9),
(317, 'Dashboard', '1icons8-dashboard-50.png', 1),
(318, 'Announcements', '2icons8-announcement-50.png', 2),
(319, 'Students', '3icons8-student-64.png', 3),
(320, 'Teachers', '4icons8-teacher-50.png', 4),
(321, 'Alumni', 'book1.png', 5),
(322, 'Achievements', '5icons8-assignment-50.png', 6),
(323, 'Calendar', '6icons8-calendar-50.png', 7),
(324, 'Organizations', '7icons8-organization-64.png', 8),
(325, 'History and Password', '8icons8-setting-50.png', 9);

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('blackout_mode', '0'),
('christmas_video', 'uploads/christmas_video_1780146150.mp4'),
('evaluation_locked', '0'),
('halloween_video', 'uploads/halloween_video_1777894456.mp4'),
('login_bg_logo1', 'uploads/bg_logo1_1778841164.png'),
('login_video', 'uploads/login_video_1780114994.mp4'),
('login_video_muted', '0'),
('new_year_video', 'uploads/new_year_video_1778416596.mp4'),
('signup_enabled', '1'),
('site_theme', 'default');

-- --------------------------------------------------------

--
-- Table structure for table `storage_shares`
--

CREATE TABLE `storage_shares` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `file_id` int(11) DEFAULT 0,
  `share_token` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `storage_shares`
--

INSERT INTO `storage_shares` (`id`, `user_id`, `file_id`, `share_token`, `created_at`) VALUES
(1, '1701-00352', 0, 'c6bdd590dcf6019c142448ac49d16195', '2026-05-23 12:03:27'),
(2, '1701-00352', 0, 'f06887c807794c6005a9fad4512cbd48', '2026-05-23 17:31:57'),
(3, '1701-00352', 0, '5cb22805acd3e2627417f1af5e7b2877', '2026-05-29 11:00:38');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `year_level` varchar(50) DEFAULT '',
  `course` varchar(100) DEFAULT '',
  `bio` text DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT '',
  `email` varchar(100) DEFAULT '',
  `last_active` datetime DEFAULT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `status` varchar(50) DEFAULT 'Student',
  `batch_year` varchar(20) DEFAULT '',
  `is_alumni` tinyint(1) DEFAULT 0,
  `password` varchar(255) DEFAULT NULL,
  `cover_pic1` varchar(255) DEFAULT '',
  `cover_pic2` varchar(255) DEFAULT '',
  `cover_pic3` varchar(255) DEFAULT '',
  `is_restricted` tinyint(1) DEFAULT 0,
  `restriction_end_date` datetime DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `mfa_enabled` tinyint(1) DEFAULT 0,
  `mfa_secret` varchar(255) DEFAULT NULL,
  `recovery_email` varchar(255) DEFAULT NULL,
  `cover_photo` varchar(255) DEFAULT '',
  `cover_offset` int(11) DEFAULT 0,
  `location` varchar(255) DEFAULT '',
  `birthdate` date DEFAULT NULL,
  `gender` varchar(20) DEFAULT '',
  `phone` varchar(20) DEFAULT NULL,
  `hide_phone` tinyint(1) DEFAULT 0,
  `force_logout` tinyint(1) DEFAULT 0,
  `logout_token` varchar(255) DEFAULT NULL,
  `profile_pic_content` longblob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_name`, `student_id`, `created_at`, `year_level`, `course`, `bio`, `profile_pic`, `email`, `last_active`, `is_online`, `status`, `batch_year`, `is_alumni`, `password`, `cover_pic1`, `cover_pic2`, `cover_pic3`, `is_restricted`, `restriction_end_date`, `last_activity`, `mfa_enabled`, `mfa_secret`, `recovery_email`, `cover_photo`, `cover_offset`, `location`, `birthdate`, `gender`, `phone`, `hide_phone`, `force_logout`, `logout_token`, `profile_pic_content`) VALUES
(5, 'Benjamine Andrei Panganiban', '2301-000111', '2026-02-13 16:16:13', '3rd Year', 'BS Information Technology', '', 'profile_2301-000111_1771159050.jpg', 'lackaluckplayzz@gmail.com', NULL, 0, 'Student', '', 0, '$2y$10$i4yfArHqxlJ7wXSaaOkENejh/nDUnaZQYUUdBBLB4hLamp8R4Xrje', '', '', '', 0, NULL, '2026-06-09 21:52:19', 0, NULL, NULL, '', 0, '', NULL, '', '', 0, 0, 'bdf1af49a92dba11bda5f089c5bcea37', NULL),
(6, 'James Berniel Deligente', '2301-000474', '2026-02-13 16:17:04', '3rd Year', 'BS Information Technology', '', 'profile_2301-000474_1771159102.jpg', 'testing12@gmail', NULL, 0, 'Student', '', 0, '$2y$10$6iYWyeWClVT1xuecqbja5uY6Agn4SfGS0Vur98BiseXc7s3IGJQOO', '', '', '', 0, NULL, NULL, 0, NULL, NULL, '', 0, '', NULL, '', '', 0, 0, NULL, NULL),
(7, 'Kory Chester Atendido', '2301-000428', '2026-02-13 16:17:45', '3rd Year', 'BS Information Technology', '', 'profile_1234-123456_1771159153.jpg', 'testing122@gmail', NULL, 0, 'Student', '', 0, NULL, '', '', '', 0, NULL, '2026-06-11 16:06:30', 0, NULL, NULL, '', 0, '', NULL, '', NULL, 0, 0, '07d18d3f597576fefd4011a2dc9a10e9', NULL),
(8, 'Carl Vincent Remolin ', '2202-000012', '2026-02-13 16:18:13', '3rd Year', 'BS Information Technology', '', 'profile_2202-000012_1771159194.jpg', 'testing12@gmail', NULL, 0, 'Student', '', 0, '$2y$10$2sd02tjLQ0/wuzG3hUg/zOYaZ3gue0148d9TFjmArmj9WVRBpI.PG', '', '', '', 0, NULL, NULL, 0, NULL, NULL, '', 0, '', NULL, '', '', 0, 0, NULL, NULL),
(9, 'Warien Atienza', '2302-000019', '2026-02-13 16:18:43', '3rd Year', 'BS Information Technology', '', 'profile_2302-000019_1771159456.jpg', 'atienzawarien@gmail.com', NULL, 0, 'Student', '', 0, '$2y$10$rVR6PXuFpDQxbZlojm78JO0qdeqNKeHXPsalrfg5q2G0.QOAl4daG', '', '', '', 0, NULL, '2026-04-16 12:37:23', 0, NULL, NULL, '', 0, '', NULL, '', '', 0, 0, NULL, NULL),
(12, 'Santino boleche', '123456', '2026-02-15 23:51:56', '4th Year', 'BS Information Technology', '', 'profile_123456_1771199583.jpg', 'testing12@gmail', NULL, 0, 'Student', '', 0, NULL, '', '', '', 0, NULL, NULL, 0, NULL, NULL, '', 0, '', NULL, '', NULL, 0, 0, NULL, NULL),
(23, 'Dan Cedreck Monsalve', '2301-000076', '2026-02-18 03:10:26', '3rd Year', 'BS Information Technology', '', 'profile_2301-000076_1776519078.jpg', 'dancedreck4566@gmail.com', NULL, 0, 'Student', '', 0, '$2y$10$vqjeuBEiVle46hGCMdlUdO2JNY7kMc4DyFByu3pY4dnhSKWI8WOb2', '', '', '', 0, NULL, '2026-06-09 15:18:44', 0, NULL, NULL, '', 0, '', NULL, '', '', 0, 0, 'bcab20572553d3b541ecc86e5502a26e', NULL),
(25, 'Sacli Official', '123456789', '2026-02-18 14:45:25', '1st Year', '', '', 'profile_123456789_1771426443.jpg', 'testing12@gmail', NULL, 0, 'Student', '', 0, NULL, '', '', '', 0, NULL, '2026-05-31 00:54:53', 0, NULL, NULL, '', 0, '', NULL, '', '', 0, 0, 'fda6b5a4849affd9b8c5b1d966164bbc', NULL),
(32, 'Princess Ritardo', '20913', '2026-03-08 13:54:40', '1st Year', 'BS Hospitality Management', '', 'profile_20913_1772978080.png', 'pbasa22@gmail.com', NULL, 0, 'Student', '', 0, NULL, '', '', '', 0, NULL, '2026-06-18 12:11:36', 0, NULL, NULL, '', 0, '', NULL, '', NULL, 0, 0, 'da6af8e3b7f2fa4541512ede134806a5', NULL),
(35, 'Pandoy Ritardo', 'A23-8725', '2026-04-21 08:32:37', '3rd Year', 'BS Civil Engineering', '', '', 'ritardojoshandreii@gmail.com', NULL, 0, 'Student', '', 0, NULL, '', '', '', 0, NULL, '2026-04-21 16:52:32', 0, NULL, NULL, '', 0, '', NULL, '', '', 0, 0, NULL, NULL),
(37, 'Timmy Ritardo', '124578', '2026-04-21 13:25:04', 'Alumni', 'BS Information Technology', NULL, '', 'timmy12@fake.com', NULL, 0, 'Student', '', 1, '$2y$10$3Mvtgmt8Wu39FY/4zW/1l.Tulf50VKmzBBW5gnP8pCdsOp.rJUggi', '', '', '', 0, NULL, '2026-04-21 21:36:31', 0, NULL, NULL, '', 0, '', NULL, '', NULL, 0, 0, NULL, NULL),
(38, 'Testing', '789', '2026-04-21 18:06:10', 'Alumni', 'BS Nursing', NULL, '', 'timritardo11@gmail.com', NULL, 0, 'Student', '', 1, '$2y$10$gzYOLbh/DcEba8YT1.yVVuOxI3oO0BWHPSrppar9n7rnJyHRvicv6', '', '', '', 0, NULL, NULL, 0, NULL, NULL, '', 0, '', NULL, '', '', 0, 0, NULL, NULL),
(39, 'shaun carlo c ritardo', '271813', '2026-04-27 13:03:30', '1st Year', 'BS Information Technology', '', '', 'tintincdjj@gmail.com', NULL, 0, 'Student', '', 0, NULL, '', '', '', 0, NULL, '2026-04-27 22:04:10', 0, NULL, NULL, '', 0, '', NULL, '', '', 0, 0, '5b25d2760f02bbbf76138fc9da3edf28', NULL),
(41, 'bugoy Na Koykoy', '147852369', '2026-05-06 00:48:22', '2nd Year', 'BS Information Technology', NULL, '', 'testing@gmail.com', NULL, 0, 'Student', '', 0, '$2y$10$d7R3IqNGhfAu4SUbe7XyQOWYPFseOALBz3Gje04r8bdvJ2Fz5deou', '', '', '', 0, NULL, '2026-05-06 08:49:12', 0, NULL, NULL, '', 0, '', NULL, '', NULL, 0, 0, 'c1f74075e80294f532f78ab860690524', NULL),
(43, 'Justin Ritardo', '1701-00352', '2026-05-16 00:53:12', '3rd Year', 'BS Information Technology', '\"Creator Of Sacli Connect !!!\"', 'profile_1701-00352_1778892792.png', 'timritardo1@gmail.com', NULL, 1, 'Student', '', 0, '222111', '', '', '', 0, NULL, '2026-06-19 14:21:04', 0, NULL, NULL, 'cover_1701-00352_1778892971.png', 0, 'Flores Subdivision, Lucena,', '2002-12-03', 'Male', '09950962488', 1, 0, '98737d575fe2f60974cfc0b3a1587e00', NULL),
(44, 'Charlaine Mae B. Cervantes', '2401-00186', '2026-05-23 17:15:02', '2nd Year', 'BS Nursing', '', '', 'charlainebicocervantess@gmail.com', NULL, 0, 'Student', '', 0, '$2y$10$pW0foxdRj5mXs7r/zGVV9ORpJBwpAJUdp50wpJWkAx4U4YjmtHVHS', '', '', '', 0, NULL, '2026-05-24 01:16:51', 0, NULL, NULL, '', 0, '', NULL, '', '', 0, 0, 'db5c3b0fa0440866774d2237ebcdd7a7', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `subject_chats`
--

CREATE TABLE `subject_chats` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `url` varchar(255) DEFAULT '#',
  `icon` varchar(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subject_chats`
--

INSERT INTO `subject_chats` (`id`, `name`, `is_online`, `sort_order`, `url`, `icon`) VALUES
(197, 'Sacli Portal', 0, 1, '', 'link_icon_1771604341_0.png'),
(198, 'Sacli Facebook Page', 1, 2, 'https://www.facebook.com/search/top?q=st.%20anne%20college%20lucena%2C%20inc.', 'link_icon_1771604341_1.png'),
(199, 'Sacli Youtube Page', 1, 3, 'https://www.youtube.com/results?search_query=st+anne+college+lucena+inc', 'link_icon_1771604341_2.png'),
(200, 'Sacli Sasec', 1, 4, 'https://www.facebook.com/profile.php?id=100090108551031', 'link_icon_1772709907_3.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `system_file_registry`
--

CREATE TABLE `system_file_registry` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `source_folder` enum('uploads','storage','submissions') NOT NULL,
  `file_size` varchar(50) DEFAULT NULL,
  `status` enum('present','purged') DEFAULT 'present',
  `registered_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_file_registry`
--

INSERT INTO `system_file_registry` (`id`, `file_name`, `file_path`, `file_type`, `source_folder`, `file_size`, `status`, `registered_at`) VALUES
(1, 'achievement_1772035796.png', 'uploads/achievement_1772035796.png', 'image/png', 'uploads', '760.93 KB', 'present', '2026-06-04 05:18:23'),
(2, 'admin_6_1772758601.jpg', 'uploads/admin_6_1772758601.jpg', 'image/jpeg', 'uploads', '226.45 KB', 'present', '2026-06-04 05:18:23'),
(3, 'alumni_1771306342.jpg', 'uploads/alumni_1771306342.jpg', 'image/jpeg', 'uploads', '306.5 KB', 'present', '2026-06-04 05:18:23'),
(4, 'alumni_1771496924.png', 'uploads/alumni_1771496924.png', 'image/png', 'uploads', '760.93 KB', 'present', '2026-06-04 05:18:23'),
(5, 'alumni_1771497049.png', 'uploads/alumni_1771497049.png', 'image/png', 'uploads', '198.31 KB', 'present', '2026-06-04 05:18:23'),
(6, 'alumni_1771509306.png', 'uploads/alumni_1771509306.png', 'image/png', 'uploads', '392.44 KB', 'present', '2026-06-04 05:18:23'),
(7, 'alumni_1772114255.png', 'uploads/alumni_1772114255.png', 'image/png', 'uploads', '760.93 KB', 'present', '2026-06-04 05:18:23'),
(8, 'alumni_1772156578.png', 'uploads/alumni_1772156578.png', 'image/png', 'uploads', '122.79 KB', 'present', '2026-06-04 05:18:23'),
(9, 'alumni_1772510921.png', 'uploads/alumni_1772510921.png', 'image/png', 'uploads', '760.93 KB', 'present', '2026-06-04 05:18:23'),
(10, 'alumni_1772804296.png', 'uploads/alumni_1772804296.png', 'image/png', 'uploads', '207.94 KB', 'present', '2026-06-04 05:18:23'),
(11, 'alumni_1773631803.png', 'uploads/alumni_1773631803.png', 'image/png', 'uploads', '760.93 KB', 'present', '2026-06-04 05:18:23'),
(12, 'alumni_1773739022.jpg', 'uploads/alumni_1773739022.jpg', 'image/jpeg', 'uploads', '99.35 KB', 'present', '2026-06-04 05:18:23'),
(13, 'alumni_1773851759.jpg', 'uploads/alumni_1773851759.jpg', 'image/jpeg', 'uploads', '99.35 KB', 'present', '2026-06-04 05:18:23'),
(14, 'alumni_1776777654.jpg', 'uploads/alumni_1776777654.jpg', 'image/jpeg', 'uploads', '26.62 KB', 'present', '2026-06-04 05:18:23'),
(15, 'bg_logo1_1778841164.png', 'uploads/bg_logo1_1778841164.png', 'image/png', 'uploads', '4209.63 KB', 'present', '2026-06-04 05:18:23'),
(16, 'chat_1771887091_699cd9f39e39c.jpg', 'uploads/chat_1771887091_699cd9f39e39c.jpg', 'image/jpeg', 'uploads', '70.73 KB', 'present', '2026-06-04 05:18:23'),
(17, 'chat_1771888754_699ce072a04d4.pptx', 'uploads/chat_1771888754_699ce072a04d4.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'uploads', '52.07 KB', 'present', '2026-06-04 05:18:23'),
(18, 'chat_1771889206_699ce2365f857.pptx', 'uploads/chat_1771889206_699ce2365f857.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'uploads', '52.07 KB', 'present', '2026-06-04 05:18:23'),
(19, 'chat_1771989150_699e689e635eb.pdf', 'uploads/chat_1771989150_699e689e635eb.pdf', 'application/pdf', 'uploads', '44.96 KB', 'present', '2026-06-04 05:18:23'),
(20, 'chat_1774802030_69c9546ec806f.docx', 'uploads/chat_1774802030_69c9546ec806f.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'uploads', '854.05 KB', 'present', '2026-06-04 05:18:23'),
(21, 'chat_1779248006_6a0d2b8642202.jpg', 'uploads/chat_1779248006_6a0d2b8642202.jpg', 'image/jpeg', 'uploads', '179.62 KB', 'present', '2026-06-04 05:18:23'),
(22, 'chat_1779248013_6a0d2b8dba4ce.jpg', 'uploads/chat_1779248013_6a0d2b8dba4ce.jpg', 'image/jpeg', 'uploads', '179.62 KB', 'present', '2026-06-04 05:18:23'),
(23, 'chat_1779249303_6a0d30976d4a5.jpg', 'uploads/chat_1779249303_6a0d30976d4a5.jpg', 'image/jpeg', 'uploads', '131.37 KB', 'present', '2026-06-04 05:18:23'),
(24, 'chat_1779249328_6a0d30b0c66f9.jpg', 'uploads/chat_1779249328_6a0d30b0c66f9.jpg', 'image/jpeg', 'uploads', '179.62 KB', 'present', '2026-06-04 05:18:23'),
(25, 'chat_1779249328_6a0d30b0c8061.jpg', 'uploads/chat_1779249328_6a0d30b0c8061.jpg', 'image/jpeg', 'uploads', '179.62 KB', 'present', '2026-06-04 05:18:23'),
(26, 'chat_1779249328_6a0d30b0c9bd4.jpg', 'uploads/chat_1779249328_6a0d30b0c9bd4.jpg', 'image/jpeg', 'uploads', '179.62 KB', 'present', '2026-06-04 05:18:23'),
(27, 'chat_1779276793_6a0d9bf9e7dcb.mp4', 'uploads/chat_1779276793_6a0d9bf9e7dcb.mp4', 'video/mp4', 'uploads', '5610.77 KB', 'present', '2026-06-04 05:18:23'),
(28, 'chat_1779317042_6a0e39325676c.png', 'uploads/chat_1779317042_6a0e39325676c.png', 'image/png', 'uploads', '760.93 KB', 'present', '2026-06-04 05:18:23'),
(29, 'chat_1779317042_6a0e39325896a.png', 'uploads/chat_1779317042_6a0e39325896a.png', 'image/png', 'uploads', '808.53 KB', 'present', '2026-06-04 05:18:23'),
(30, 'chat_1779317042_6a0e39325bd90.jpg', 'uploads/chat_1779317042_6a0e39325bd90.jpg', 'image/jpeg', 'uploads', '304.62 KB', 'present', '2026-06-04 05:18:23'),
(31, 'chat_1779317042_6a0e39325e4a3.jpg', 'uploads/chat_1779317042_6a0e39325e4a3.jpg', 'image/jpeg', 'uploads', '237.09 KB', 'present', '2026-06-04 05:18:23'),
(32, 'chat_1779317042_6a0e3932605ac.jpg', 'uploads/chat_1779317042_6a0e3932605ac.jpg', 'image/jpeg', 'uploads', '26.62 KB', 'present', '2026-06-04 05:18:23'),
(33, 'chat_1779317042_6a0e3932629c6.jpg', 'uploads/chat_1779317042_6a0e3932629c6.jpg', 'image/jpeg', 'uploads', '238 KB', 'present', '2026-06-04 05:18:23'),
(34, 'chat_1779317042_6a0e3932651c7.jpg', 'uploads/chat_1779317042_6a0e3932651c7.jpg', 'image/jpeg', 'uploads', '52.26 KB', 'present', '2026-06-04 05:18:23'),
(35, 'chat_1779317091_6a0e3963ceadd.jpg', 'uploads/chat_1779317091_6a0e3963ceadd.jpg', 'image/jpeg', 'uploads', '179.62 KB', 'present', '2026-06-04 05:18:23'),
(36, 'chat_1779317091_6a0e3963d02ee.jpg', 'uploads/chat_1779317091_6a0e3963d02ee.jpg', 'image/jpeg', 'uploads', '179.62 KB', 'present', '2026-06-04 05:18:23'),
(37, 'chat_1779317091_6a0e3963d1a1f.jpg', 'uploads/chat_1779317091_6a0e3963d1a1f.jpg', 'image/jpeg', 'uploads', '103.23 KB', 'present', '2026-06-04 05:18:23'),
(38, 'chat_1779443797_6a102855de9bd.pdf', 'uploads/chat_1779443797_6a102855de9bd.pdf', 'application/pdf', 'uploads', '4794.14 KB', 'present', '2026-06-04 05:18:23'),
(39, 'chat_1779505529_6a111979307b7.pptx', 'uploads/chat_1779505529_6a111979307b7.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'uploads', '32364.99 KB', 'present', '2026-06-04 05:18:23'),
(40, 'christmas_video_1778464945.mp4', 'uploads/christmas_video_1778464945.mp4', 'video/mp4', 'uploads', '295956.72 KB', 'present', '2026-06-04 05:18:23'),
(41, 'christmas_video_1780146150.mp4', 'uploads/christmas_video_1780146150.mp4', 'video/mp4', 'uploads', '28779.23 KB', 'present', '2026-06-04 05:18:23'),
(42, 'cover_111222_1776610201.jpg', 'uploads/cover_111222_1776610201.jpg', 'image/jpeg', 'uploads', '304.62 KB', 'present', '2026-06-04 05:18:23'),
(43, 'cover_111222_1776706587.png', 'uploads/cover_111222_1776706587.png', 'image/png', 'uploads', '663.67 KB', 'present', '2026-06-04 05:18:23'),
(44, 'cover_111222_1776706591.png', 'uploads/cover_111222_1776706591.png', 'image/png', 'uploads', '663.67 KB', 'present', '2026-06-04 05:18:23'),
(45, 'cover_15_1776865387.jpg', 'uploads/cover_15_1776865387.jpg', 'image/jpeg', 'uploads', '1273.21 KB', 'present', '2026-06-04 05:18:23'),
(46, 'cover_1701-00352_1778892971.png', 'uploads/cover_1701-00352_1778892971.png', 'image/png', 'uploads', '663.67 KB', 'present', '2026-06-04 05:18:23'),
(47, 'event_1771249653.jpg', 'uploads/event_1771249653.jpg', 'image/jpeg', 'uploads', '18.22 KB', 'present', '2026-06-04 05:18:23'),
(48, 'event_1771249864.jpg', 'uploads/event_1771249864.jpg', 'image/jpeg', 'uploads', '327.15 KB', 'present', '2026-06-04 05:18:23'),
(49, 'event_1771250119.jpg', 'uploads/event_1771250119.jpg', 'image/jpeg', 'uploads', '327.15 KB', 'present', '2026-06-04 05:18:23'),
(50, 'event_1771251807.jpg', 'uploads/event_1771251807.jpg', 'image/jpeg', 'uploads', '327.15 KB', 'present', '2026-06-04 05:18:23'),
(51, 'event_1771251913.jpg', 'uploads/event_1771251913.jpg', 'image/jpeg', 'uploads', '327.15 KB', 'present', '2026-06-04 05:18:23'),
(52, 'event_1771488895.jpg', 'uploads/event_1771488895.jpg', 'image/jpeg', 'uploads', '7331.56 KB', 'present', '2026-06-04 05:18:23'),
(53, 'event_1771553906.jpg', 'uploads/event_1771553906.jpg', 'image/jpeg', 'uploads', '6819.36 KB', 'present', '2026-06-04 05:18:23'),
(54, 'gc_1772510793_69a65e4912a3b.pdf', 'uploads/gc_1772510793_69a65e4912a3b.pdf', 'application/pdf', 'uploads', '44.96 KB', 'present', '2026-06-04 05:18:23'),
(55, 'gc_1778842785_6a06fca1ac065.pdf', 'uploads/gc_1778842785_6a06fca1ac065.pdf', 'application/pdf', 'uploads', '3238.3 KB', 'present', '2026-06-04 05:18:23'),
(56, 'gc_1779080235_6a0a9c2b47df7.jpg', 'uploads/gc_1779080235_6a0a9c2b47df7.jpg', 'image/jpeg', 'uploads', '179.62 KB', 'present', '2026-06-04 05:18:23'),
(57, 'gc_1779144154_6a0b95da6f8c5.jpg', 'uploads/gc_1779144154_6a0b95da6f8c5.jpg', 'image/jpeg', 'uploads', '304.62 KB', 'present', '2026-06-04 05:18:23'),
(58, 'group_1771254942_6993349e74495.jpg', 'uploads/group_1771254942_6993349e74495.jpg', 'image/jpeg', 'uploads', '226.45 KB', 'present', '2026-06-04 05:18:23'),
(59, 'group_1771258165_6993413571f2c.png', 'uploads/group_1771258165_6993413571f2c.png', 'image/png', 'uploads', '108.71 KB', 'present', '2026-06-04 05:18:23'),
(60, 'group_1771261137_69934cd13ba1e.jpg', 'uploads/group_1771261137_69934cd13ba1e.jpg', 'image/jpeg', 'uploads', '41.65 KB', 'present', '2026-06-04 05:18:23'),
(61, 'group_1771324512_699444603dffe.jpg', 'uploads/group_1771324512_699444603dffe.jpg', 'image/jpeg', 'uploads', '226.45 KB', 'present', '2026-06-04 05:18:23'),
(62, 'group_1771430969_6995e439e536f.jpg', 'uploads/group_1771430969_6995e439e536f.jpg', 'image/jpeg', 'uploads', '327.15 KB', 'present', '2026-06-04 05:18:23'),
(63, 'group_1771484133_6996b3e543e4e.jpg', 'uploads/group_1771484133_6996b3e543e4e.jpg', 'image/jpeg', 'uploads', '96.58 KB', 'present', '2026-06-04 05:18:23'),
(64, 'group_1774248075_69c0e08bd190d.png', 'uploads/group_1774248075_69c0e08bd190d.png', 'image/png', 'uploads', '760.93 KB', 'present', '2026-06-04 05:18:23'),
(65, 'halloween_video_1777894456.mp4', 'uploads/halloween_video_1777894456.mp4', 'video/mp4', 'uploads', '45655.03 KB', 'present', '2026-06-04 05:18:23'),
(66, 'link_icon_1771604341_0.png', 'uploads/link_icon_1771604341_0.png', 'image/png', 'uploads', '874.61 KB', 'present', '2026-06-04 05:18:23'),
(67, 'link_icon_1771604341_1.png', 'uploads/link_icon_1771604341_1.png', 'image/png', 'uploads', '65.21 KB', 'present', '2026-06-04 05:18:23'),
(68, 'link_icon_1771604341_2.png', 'uploads/link_icon_1771604341_2.png', 'image/png', 'uploads', '67.51 KB', 'present', '2026-06-04 05:18:23'),
(69, 'link_icon_1771604341_3.png', 'uploads/link_icon_1771604341_3.png', 'image/png', 'uploads', '874.61 KB', 'present', '2026-06-04 05:18:23'),
(70, 'link_icon_1772709907_3.jpg', 'uploads/link_icon_1772709907_3.jpg', 'image/jpeg', 'uploads', '51.23 KB', 'present', '2026-06-04 05:18:23'),
(71, 'login_video_1780114994.mp4', 'uploads/login_video_1780114994.mp4', 'video/mp4', 'uploads', '159031.24 KB', 'present', '2026-06-04 05:18:24'),
(72, 'new_year_video_1778416596.mp4', 'uploads/new_year_video_1778416596.mp4', 'video/mp4', 'uploads', '5610.77 KB', 'present', '2026-06-04 05:18:24'),
(73, 'post_115_69ae9369d124b.jpg', 'uploads/post_115_69ae9369d124b.jpg', 'image/jpeg', 'uploads', '460.41 KB', 'present', '2026-06-04 05:18:24'),
(74, 'post_115_69ae9369d2f4d.jpg', 'uploads/post_115_69ae9369d2f4d.jpg', 'image/jpeg', 'uploads', '439.88 KB', 'present', '2026-06-04 05:18:24'),
(75, 'post_115_69ae9369d487b.jpg', 'uploads/post_115_69ae9369d487b.jpg', 'image/jpeg', 'uploads', '884.05 KB', 'present', '2026-06-04 05:18:24'),
(76, 'post_115_69ae9369d5fd2.jpg', 'uploads/post_115_69ae9369d5fd2.jpg', 'image/jpeg', 'uploads', '432.41 KB', 'present', '2026-06-04 05:18:24'),
(77, 'post_115_69ae9369d7668.jpg', 'uploads/post_115_69ae9369d7668.jpg', 'image/jpeg', 'uploads', '840 KB', 'present', '2026-06-04 05:18:24'),
(78, 'post_115_69ae9369d8cb0.jpg', 'uploads/post_115_69ae9369d8cb0.jpg', 'image/jpeg', 'uploads', '800.48 KB', 'present', '2026-06-04 05:18:24'),
(79, 'post_118_69b026c0c2115.png', 'uploads/post_118_69b026c0c2115.png', 'image/png', 'uploads', '310.78 KB', 'present', '2026-06-04 05:18:24'),
(80, 'post_118_69b026c0c5e6d.jpg', 'uploads/post_118_69b026c0c5e6d.jpg', 'image/jpeg', 'uploads', '85.35 KB', 'present', '2026-06-04 05:18:24'),
(81, 'post_118_69b026c0ca4eb.jpg', 'uploads/post_118_69b026c0ca4eb.jpg', 'image/jpeg', 'uploads', '1271.93 KB', 'present', '2026-06-04 05:18:24'),
(82, 'post_118_69b026c0cc761.jpg', 'uploads/post_118_69b026c0cc761.jpg', 'image/jpeg', 'uploads', '603.33 KB', 'present', '2026-06-04 05:18:24'),
(83, 'post_118_69b026c0cef17.jpg', 'uploads/post_118_69b026c0cef17.jpg', 'image/jpeg', 'uploads', '460.41 KB', 'present', '2026-06-04 05:18:24'),
(84, 'post_118_69b026c0d13a9.jpg', 'uploads/post_118_69b026c0d13a9.jpg', 'image/jpeg', 'uploads', '439.88 KB', 'present', '2026-06-04 05:18:24'),
(85, 'post_118_69b026c0d3644.jpg', 'uploads/post_118_69b026c0d3644.jpg', 'image/jpeg', 'uploads', '884.05 KB', 'present', '2026-06-04 05:18:24'),
(86, 'post_118_69b026c0d602c.jpg', 'uploads/post_118_69b026c0d602c.jpg', 'image/jpeg', 'uploads', '1297.56 KB', 'present', '2026-06-04 05:18:24'),
(87, 'post_118_69b026c0d8730.jpg', 'uploads/post_118_69b026c0d8730.jpg', 'image/jpeg', 'uploads', '840 KB', 'present', '2026-06-04 05:18:24'),
(88, 'post_118_69b026c0da881.jpg', 'uploads/post_118_69b026c0da881.jpg', 'image/jpeg', 'uploads', '800.48 KB', 'present', '2026-06-04 05:18:24'),
(89, 'post_118_69b026c0dd319.jpg', 'uploads/post_118_69b026c0dd319.jpg', 'image/jpeg', 'uploads', '51.23 KB', 'present', '2026-06-04 05:18:24'),
(90, 'post_119_69b02ab3f3cda.jpg', 'uploads/post_119_69b02ab3f3cda.jpg', 'image/jpeg', 'uploads', '1271.93 KB', 'present', '2026-06-04 05:18:24'),
(91, 'post_120_69b030ad302a4.jpg', 'uploads/post_120_69b030ad302a4.jpg', 'image/jpeg', 'uploads', '884.05 KB', 'present', '2026-06-04 05:18:24'),
(92, 'post_121_69b2bd69242b2_St_Anne_College_Lucena_Inc.mp4', 'uploads/post_121_69b2bd69242b2_St_Anne_College_Lucena_Inc.mp4', 'video/mp4', 'uploads', '11196.44 KB', 'present', '2026-06-04 05:18:24'),
(93, 'post_122_69b2bd6b8de78_St_Anne_College_Lucena_Inc.mp4', 'uploads/post_122_69b2bd6b8de78_St_Anne_College_Lucena_Inc.mp4', 'video/mp4', 'uploads', '11196.44 KB', 'present', '2026-06-04 05:18:24'),
(94, 'post_124_69b2bd6c3ffc7_St_Anne_College_Lucena_Inc.mp4', 'uploads/post_124_69b2bd6c3ffc7_St_Anne_College_Lucena_Inc.mp4', 'video/mp4', 'uploads', '11196.44 KB', 'present', '2026-06-04 05:18:24'),
(95, 'post_128_69b2bd6fd81e0_St_Anne_College_Lucena_Inc.mp4', 'uploads/post_128_69b2bd6fd81e0_St_Anne_College_Lucena_Inc.mp4', 'video/mp4', 'uploads', '11196.44 KB', 'present', '2026-06-04 05:18:24'),
(96, 'post_129_69b2bd7012f50_St_Anne_College_Lucena_Inc.mp4', 'uploads/post_129_69b2bd7012f50_St_Anne_College_Lucena_Inc.mp4', 'video/mp4', 'uploads', '11196.44 KB', 'present', '2026-06-04 05:18:24'),
(97, 'post_134_69b8c080d6320_650218542_122182542692788541_6683336791685270075_n.jpg', 'uploads/post_134_69b8c080d6320_650218542_122182542692788541_6683336791685270075_n.jpg', 'image/jpeg', 'uploads', '499.21 KB', 'present', '2026-06-04 05:18:24'),
(98, 'post_134_69b8c080d802b_650223241_122182542776788541_7787240181103795767_n.jpg', 'uploads/post_134_69b8c080d802b_650223241_122182542776788541_7787240181103795767_n.jpg', 'image/jpeg', 'uploads', '647.1 KB', 'present', '2026-06-04 05:18:24'),
(99, 'post_134_69b8c080d984b_649276881_122182542806788541_3063760144860568667_n.jpg', 'uploads/post_134_69b8c080d984b_649276881_122182542806788541_3063760144860568667_n.jpg', 'image/jpeg', 'uploads', '586.52 KB', 'present', '2026-06-04 05:18:24'),
(100, 'post_134_69b8c080db4d7_650384829_122182542674788541_4393176556204612884_n.jpg', 'uploads/post_134_69b8c080db4d7_650384829_122182542674788541_4393176556204612884_n.jpg', 'image/jpeg', 'uploads', '441.4 KB', 'present', '2026-06-04 05:18:24'),
(101, 'post_134_69b8c080dcba8_650332381_122182542722788541_6985798705808680093_n.jpg', 'uploads/post_134_69b8c080dcba8_650332381_122182542722788541_6985798705808680093_n.jpg', 'image/jpeg', 'uploads', '524.97 KB', 'present', '2026-06-04 05:18:24'),
(102, 'post_134_69b8c080de6ba_650451089_122182542866788541_3551386303726740241_n.jpg', 'uploads/post_134_69b8c080de6ba_650451089_122182542866788541_3551386303726740241_n.jpg', 'image/jpeg', 'uploads', '811.58 KB', 'present', '2026-06-04 05:18:24'),
(103, 'post_140_69e1a8628f09c_VideoCapture_20250714-190933.jpg', 'uploads/post_140_69e1a8628f09c_VideoCapture_20250714-190933.jpg', 'image/jpeg', 'uploads', '462.03 KB', 'present', '2026-06-04 05:18:24'),
(104, 'post_141_69e1a8a900355_587507566_17925725586179089_8227554172079805731_n.jpg', 'uploads/post_141_69e1a8a900355_587507566_17925725586179089_8227554172079805731_n.jpg', 'image/jpeg', 'uploads', '304.62 KB', 'present', '2026-06-04 05:18:24'),
(105, 'post_141_69e1a8a90275f_547960528_796872453119820_207879741290099069_n.jpg', 'uploads/post_141_69e1a8a90275f_547960528_796872453119820_207879741290099069_n.jpg', 'image/jpeg', 'uploads', '172.56 KB', 'present', '2026-06-04 05:18:24'),
(106, 'post_141_69e1a8a9042c4_547103051_1458281408552671_3759545447072927958_n.png', 'uploads/post_141_69e1a8a9042c4_547103051_1458281408552671_3759545447072927958_n.png', 'image/png', 'uploads', '642.89 KB', 'present', '2026-06-04 05:18:24'),
(107, 'post_141_69e1a8a905f40_546587312_1695005811212649_1388128326423530774_n.jpg', 'uploads/post_141_69e1a8a905f40_546587312_1695005811212649_1388128326423530774_n.jpg', 'image/jpeg', 'uploads', '393.73 KB', 'present', '2026-06-04 05:18:24'),
(108, 'post_141_69e1a8a907d02_545978558_724661800623489_17383983947086306_n.jpg', 'uploads/post_141_69e1a8a907d02_545978558_724661800623489_17383983947086306_n.jpg', 'image/jpeg', 'uploads', '380.45 KB', 'present', '2026-06-04 05:18:24'),
(109, 'post_141_69e1a8a9097a5_544615199_17918123565179089_3352008008443960741_n.jpg', 'uploads/post_141_69e1a8a9097a5_544615199_17918123565179089_3352008008443960741_n.jpg', 'image/jpeg', 'uploads', '237.09 KB', 'present', '2026-06-04 05:18:24'),
(110, 'post_142_69e1d47d75cd5_587507566_17925725586179089_8227554172079805731_n.jpg', 'uploads/post_142_69e1d47d75cd5_587507566_17925725586179089_8227554172079805731_n.jpg', 'image/jpeg', 'uploads', '304.62 KB', 'present', '2026-06-04 05:18:24'),
(111, 'post_142_69e1d47d78711_544615199_17918123565179089_3352008008443960741_n.jpg', 'uploads/post_142_69e1d47d78711_544615199_17918123565179089_3352008008443960741_n.jpg', 'image/jpeg', 'uploads', '237.09 KB', 'present', '2026-06-04 05:18:24'),
(112, 'post_142_69e1d47d7b065_547960528_796872453119820_207879741290099069_n.jpg', 'uploads/post_142_69e1d47d7b065_547960528_796872453119820_207879741290099069_n.jpg', 'image/jpeg', 'uploads', '172.56 KB', 'present', '2026-06-04 05:18:24'),
(113, 'post_142_69e1d47d7d1cc_547103051_1458281408552671_3759545447072927958_n.png', 'uploads/post_142_69e1d47d7d1cc_547103051_1458281408552671_3759545447072927958_n.png', 'image/png', 'uploads', '642.89 KB', 'present', '2026-06-04 05:18:24'),
(114, 'post_142_69e1d47d7f33e_545978558_724661800623489_17383983947086306_n.jpg', 'uploads/post_142_69e1d47d7f33e_545978558_724661800623489_17383983947086306_n.jpg', 'image/jpeg', 'uploads', '380.45 KB', 'present', '2026-06-04 05:18:24'),
(115, 'post_144_69e58f936f317_587507566_17925725586179089_8227554172079805731_n.jpg', 'uploads/post_144_69e58f936f317_587507566_17925725586179089_8227554172079805731_n.jpg', 'image/jpeg', 'uploads', '304.62 KB', 'present', '2026-06-04 05:18:24'),
(116, 'post_144_69e58f93720df_544615199_17918123565179089_3352008008443960741_n.jpg', 'uploads/post_144_69e58f93720df_544615199_17918123565179089_3352008008443960741_n.jpg', 'image/jpeg', 'uploads', '237.09 KB', 'present', '2026-06-04 05:18:24'),
(117, 'post_144_69e58f937b80f_547960528_796872453119820_207879741290099069_n.jpg', 'uploads/post_144_69e58f937b80f_547960528_796872453119820_207879741290099069_n.jpg', 'image/jpeg', 'uploads', '172.56 KB', 'present', '2026-06-04 05:18:24'),
(118, 'post_144_69e58f937e939_547103051_1458281408552671_3759545447072927958_n.png', 'uploads/post_144_69e58f937e939_547103051_1458281408552671_3759545447072927958_n.png', 'image/png', 'uploads', '642.89 KB', 'present', '2026-06-04 05:18:24'),
(119, 'post_144_69e58f9380fdc_546587312_1695005811212649_1388128326423530774_n.jpg', 'uploads/post_144_69e58f9380fdc_546587312_1695005811212649_1388128326423530774_n.jpg', 'image/jpeg', 'uploads', '393.73 KB', 'present', '2026-06-04 05:18:24'),
(120, 'post_147_69e77b5bcd741_587507566_17925725586179089_8227554172079805731_n.jpg', 'uploads/post_147_69e77b5bcd741_587507566_17925725586179089_8227554172079805731_n.jpg', 'image/jpeg', 'uploads', '304.62 KB', 'present', '2026-06-04 05:18:24'),
(121, 'post_147_69e77b5bcfb8b_544615199_17918123565179089_3352008008443960741_n.jpg', 'uploads/post_147_69e77b5bcfb8b_544615199_17918123565179089_3352008008443960741_n.jpg', 'image/jpeg', 'uploads', '237.09 KB', 'present', '2026-06-04 05:18:24'),
(122, 'post_148_69e77bc137da0_587507566_17925725586179089_8227554172079805731_n.jpg', 'uploads/post_148_69e77bc137da0_587507566_17925725586179089_8227554172079805731_n.jpg', 'image/jpeg', 'uploads', '304.62 KB', 'present', '2026-06-04 05:18:24'),
(123, 'post_148_69e77bc13987d_544615199_17918123565179089_3352008008443960741_n.jpg', 'uploads/post_148_69e77bc13987d_544615199_17918123565179089_3352008008443960741_n.jpg', 'image/jpeg', 'uploads', '237.09 KB', 'present', '2026-06-04 05:18:24'),
(124, 'post_148_69e77bc13b07c_547960528_796872453119820_207879741290099069_n.jpg', 'uploads/post_148_69e77bc13b07c_547960528_796872453119820_207879741290099069_n.jpg', 'image/jpeg', 'uploads', '172.56 KB', 'present', '2026-06-04 05:18:24'),
(125, 'post_148_69e77bc13d2f2_547103051_1458281408552671_3759545447072927958_n.png', 'uploads/post_148_69e77bc13d2f2_547103051_1458281408552671_3759545447072927958_n.png', 'image/png', 'uploads', '642.89 KB', 'present', '2026-06-04 05:18:24'),
(126, 'post_148_69e77bc13f2a0_546587312_1695005811212649_1388128326423530774_n.jpg', 'uploads/post_148_69e77bc13f2a0_546587312_1695005811212649_1388128326423530774_n.jpg', 'image/jpeg', 'uploads', '393.73 KB', 'present', '2026-06-04 05:18:24'),
(127, 'post_149_69e77d5f18a6b_White_and_Red_Modern_We_Are_Hiring_Poster_423651b60a.png', 'uploads/post_149_69e77d5f18a6b_White_and_Red_Modern_We_Are_Hiring_Poster_423651b60a.png', 'image/png', 'uploads', '310.78 KB', 'present', '2026-06-04 05:18:24'),
(128, 'post_149_69e77d5f1ab1e_job-hiring-poster-template-design-3313392b81ecc7ec6694c74f0fd1c5f4_screen.jpg', 'uploads/post_149_69e77d5f1ab1e_job-hiring-poster-template-design-3313392b81ecc7ec6694c74f0fd1c5f4_screen.jpg', 'image/jpeg', 'uploads', '85.35 KB', 'present', '2026-06-04 05:18:24'),
(129, 'post_158_69f71072b709e_Screen_Recording_2025-02-16_171835.mp4', 'uploads/post_158_69f71072b709e_Screen_Recording_2025-02-16_171835.mp4', 'video/mp4', 'uploads', '10763.63 KB', 'present', '2026-06-04 05:18:24'),
(130, 'post_168_6a07c07ce2f63_student_cover_1701-00352_1772803715.png', 'uploads/post_168_6a07c07ce2f63_student_cover_1701-00352_1772803715.png', 'image/png', 'uploads', '760.93 KB', 'present', '2026-06-04 05:18:24'),
(131, 'post_168_6a07c07ce4ef6_587507566_17925725586179089_8227554172079805731_n.jpg', 'uploads/post_168_6a07c07ce4ef6_587507566_17925725586179089_8227554172079805731_n.jpg', 'image/jpeg', 'uploads', '304.62 KB', 'present', '2026-06-04 05:18:24'),
(132, 'post_168_6a07c07ce651f_547960528_796872453119820_207879741290099069_n.jpg', 'uploads/post_168_6a07c07ce651f_547960528_796872453119820_207879741290099069_n.jpg', 'image/jpeg', 'uploads', '172.56 KB', 'present', '2026-06-04 05:18:24'),
(133, 'post_168_6a07c07ce7a05_544615199_17918123565179089_3352008008443960741_n.jpg', 'uploads/post_168_6a07c07ce7a05_544615199_17918123565179089_3352008008443960741_n.jpg', 'image/jpeg', 'uploads', '237.09 KB', 'present', '2026-06-04 05:18:24'),
(134, 'post_168_6a07c07ce9038_547103051_1458281408552671_3759545447072927958_n.png', 'uploads/post_168_6a07c07ce9038_547103051_1458281408552671_3759545447072927958_n.png', 'image/png', 'uploads', '642.89 KB', 'present', '2026-06-04 05:18:24'),
(135, 'post_175_6a1156e95f45a.jpg', 'uploads/post_175_6a1156e95f45a.jpg', 'image/jpeg', 'uploads', '131.37 KB', 'present', '2026-06-04 05:18:24'),
(136, 'post_175_6a1156e960cae.png', 'uploads/post_175_6a1156e960cae.png', 'image/png', 'uploads', '108.72 KB', 'present', '2026-06-04 05:18:24'),
(137, 'post_175_6a1156e962f0c.png', 'uploads/post_175_6a1156e962f0c.png', 'image/png', 'uploads', '12.01 KB', 'present', '2026-06-04 05:18:24'),
(138, 'post_175_6a1156e964859.jpg', 'uploads/post_175_6a1156e964859.jpg', 'image/jpeg', 'uploads', '179.62 KB', 'present', '2026-06-04 05:18:24'),
(139, 'post_177_6a1a8f1501bf2.mp4', 'uploads/post_177_6a1a8f1501bf2.mp4', 'video/mp4', 'uploads', '159031.24 KB', 'present', '2026-06-04 05:18:24'),
(140, 'post_195_6a1fd69033443.mp4', 'uploads/post_195_6a1fd69033443.mp4', 'video/mp4', 'uploads', '159031.24 KB', 'present', '2026-06-04 05:18:24'),
(141, 'post_196_6a2020d1e731b.mp4', 'uploads/post_196_6a2020d1e731b.mp4', 'video/mp4', 'uploads', '124778.76 KB', 'present', '2026-06-04 05:18:24'),
(142, 'post_198_6a20354193a32.mp4', 'uploads/post_198_6a20354193a32.mp4', 'video/mp4', 'uploads', '124778.76 KB', 'present', '2026-06-04 05:18:24'),
(143, 'post_198_6a20354199666.mp4', 'uploads/post_198_6a20354199666.mp4', 'video/mp4', 'uploads', '2053.84 KB', 'present', '2026-06-04 05:18:24'),
(144, 'post_198_6a2035419ba83.mp4', 'uploads/post_198_6a2035419ba83.mp4', 'video/mp4', 'uploads', '11591.64 KB', 'present', '2026-06-04 05:18:24'),
(145, 'post_198_6a2035419dd53.mp4', 'uploads/post_198_6a2035419dd53.mp4', 'video/mp4', 'uploads', '8907.39 KB', 'present', '2026-06-04 05:18:24'),
(146, 'post_198_6a203541a11ef.mp4', 'uploads/post_198_6a203541a11ef.mp4', 'video/mp4', 'uploads', '7006.75 KB', 'present', '2026-06-04 05:18:24'),
(147, 'post_37_6995777d71009.jpg', 'uploads/post_37_6995777d71009.jpg', 'image/jpeg', 'uploads', '150.35 KB', 'present', '2026-06-04 05:18:24'),
(148, 'post_37_6995777d73c66.jpg', 'uploads/post_37_6995777d73c66.jpg', 'image/jpeg', 'uploads', '70.73 KB', 'present', '2026-06-04 05:18:24'),
(149, 'post_37_6995777d758c3.jpg', 'uploads/post_37_6995777d758c3.jpg', 'image/jpeg', 'uploads', '125.31 KB', 'present', '2026-06-04 05:18:24'),
(150, 'post_38_69957794c1c53.jpg', 'uploads/post_38_69957794c1c53.jpg', 'image/jpeg', 'uploads', '194.79 KB', 'present', '2026-06-04 05:18:24'),
(151, 'post_38_69957794c4094.jpg', 'uploads/post_38_69957794c4094.jpg', 'image/jpeg', 'uploads', '18.22 KB', 'present', '2026-06-04 05:18:24'),
(152, 'post_38_69957794c7469.jpg', 'uploads/post_38_69957794c7469.jpg', 'image/jpeg', 'uploads', '150.35 KB', 'present', '2026-06-04 05:18:24'),
(153, 'post_38_69957794c9a30.jpg', 'uploads/post_38_69957794c9a30.jpg', 'image/jpeg', 'uploads', '306.5 KB', 'present', '2026-06-04 05:18:24'),
(154, 'post_38_69957794cb7c5.jpg', 'uploads/post_38_69957794cb7c5.jpg', 'image/jpeg', 'uploads', '103.13 KB', 'present', '2026-06-04 05:18:24'),
(155, 'post_39_6995786503024.jpg', 'uploads/post_39_6995786503024.jpg', 'image/jpeg', 'uploads', '194.79 KB', 'present', '2026-06-04 05:18:24'),
(156, 'post_39_6995786504917.jpg', 'uploads/post_39_6995786504917.jpg', 'image/jpeg', 'uploads', '18.22 KB', 'present', '2026-06-04 05:18:24'),
(157, 'post_39_69957865061d8.jpg', 'uploads/post_39_69957865061d8.jpg', 'image/jpeg', 'uploads', '150.35 KB', 'present', '2026-06-04 05:18:25'),
(158, 'post_39_6995786508385.jpg', 'uploads/post_39_6995786508385.jpg', 'image/jpeg', 'uploads', '70.73 KB', 'present', '2026-06-04 05:18:25'),
(159, 'post_39_699578650a6c5.jpg', 'uploads/post_39_699578650a6c5.jpg', 'image/jpeg', 'uploads', '125.31 KB', 'present', '2026-06-04 05:18:25'),
(160, 'post_40_6995d108101cd.jpg', 'uploads/post_40_6995d108101cd.jpg', 'image/jpeg', 'uploads', '62.86 KB', 'present', '2026-06-04 05:18:25'),
(161, 'post_41_6995d12a9edef.jpg', 'uploads/post_41_6995d12a9edef.jpg', 'image/jpeg', 'uploads', '209.77 KB', 'present', '2026-06-04 05:18:25'),
(162, 'post_42_6995d15f7cc91.jpg', 'uploads/post_42_6995d15f7cc91.jpg', 'image/jpeg', 'uploads', '110.83 KB', 'present', '2026-06-04 05:18:25'),
(163, 'post_43_6995d1a368e52.jpg', 'uploads/post_43_6995d1a368e52.jpg', 'image/jpeg', 'uploads', '388.95 KB', 'present', '2026-06-04 05:18:25'),
(164, 'post_44_6995e52b5cd44.jpg', 'uploads/post_44_6995e52b5cd44.jpg', 'image/jpeg', 'uploads', '1408.36 KB', 'present', '2026-06-04 05:18:25'),
(165, 'post_44_6995e52b5e572.jpg', 'uploads/post_44_6995e52b5e572.jpg', 'image/jpeg', 'uploads', '1760.1 KB', 'present', '2026-06-04 05:18:25'),
(166, 'post_45_6995e5f00a9d4.jpg', 'uploads/post_45_6995e5f00a9d4.jpg', 'image/jpeg', 'uploads', '1408.36 KB', 'present', '2026-06-04 05:18:25'),
(167, 'post_45_6995e5f00fd43.jpg', 'uploads/post_45_6995e5f00fd43.jpg', 'image/jpeg', 'uploads', '1760.1 KB', 'present', '2026-06-04 05:18:25'),
(168, 'post_45_6995e5f01189e.jpg', 'uploads/post_45_6995e5f01189e.jpg', 'image/jpeg', 'uploads', '1177.79 KB', 'present', '2026-06-04 05:18:25'),
(169, 'post_45_6995e5f0131e5.jpg', 'uploads/post_45_6995e5f0131e5.jpg', 'image/jpeg', 'uploads', '1756.91 KB', 'present', '2026-06-04 05:18:25'),
(170, 'post_45_6995e5f015412.jpg', 'uploads/post_45_6995e5f015412.jpg', 'image/jpeg', 'uploads', '1516.85 KB', 'present', '2026-06-04 05:18:25'),
(171, 'post_45_6995e5f0172a2.jpg', 'uploads/post_45_6995e5f0172a2.jpg', 'image/jpeg', 'uploads', '1677.69 KB', 'present', '2026-06-04 05:18:25'),
(172, 'post_45_6995e5f018956.jpg', 'uploads/post_45_6995e5f018956.jpg', 'image/jpeg', 'uploads', '1628.93 KB', 'present', '2026-06-04 05:18:25'),
(173, 'post_46_6995ec52b40a7.jpg', 'uploads/post_46_6995ec52b40a7.jpg', 'image/jpeg', 'uploads', '1662.75 KB', 'present', '2026-06-04 05:18:25'),
(174, 'post_46_6995ec52b54e4.jpg', 'uploads/post_46_6995ec52b54e4.jpg', 'image/jpeg', 'uploads', '1398.02 KB', 'present', '2026-06-04 05:18:25'),
(175, 'post_46_6995ec52b6b61.jpg', 'uploads/post_46_6995ec52b6b61.jpg', 'image/jpeg', 'uploads', '1470.98 KB', 'present', '2026-06-04 05:18:25'),
(176, 'post_46_6995ec52b84d7.jpg', 'uploads/post_46_6995ec52b84d7.jpg', 'image/jpeg', 'uploads', '1860.59 KB', 'present', '2026-06-04 05:18:25'),
(177, 'post_46_6995ec52b9877.jpg', 'uploads/post_46_6995ec52b9877.jpg', 'image/jpeg', 'uploads', '1770.48 KB', 'present', '2026-06-04 05:18:25'),
(178, 'post_88_69a994a8ec2d1.jpg', 'uploads/post_88_69a994a8ec2d1.jpg', 'image/jpeg', 'uploads', '800.48 KB', 'present', '2026-06-04 05:18:25'),
(179, 'post_88_69a994a8ee7e3.jpg', 'uploads/post_88_69a994a8ee7e3.jpg', 'image/jpeg', 'uploads', '461.16 KB', 'present', '2026-06-04 05:18:25'),
(180, 'post_88_69a994a8f04f3.jpg', 'uploads/post_88_69a994a8f04f3.jpg', 'image/jpeg', 'uploads', '460.41 KB', 'present', '2026-06-04 05:18:25'),
(181, 'post_88_69a994a8f1d10.jpg', 'uploads/post_88_69a994a8f1d10.jpg', 'image/jpeg', 'uploads', '432.41 KB', 'present', '2026-06-04 05:18:25'),
(182, 'post_88_69a994a8f3623.jpg', 'uploads/post_88_69a994a8f3623.jpg', 'image/jpeg', 'uploads', '840 KB', 'present', '2026-06-04 05:18:25'),
(183, 'post_89_69a994e92e67b.jpg', 'uploads/post_89_69a994e92e67b.jpg', 'image/jpeg', 'uploads', '1271.93 KB', 'present', '2026-06-04 05:18:25'),
(184, 'post_89_69a994e930071.jpg', 'uploads/post_89_69a994e930071.jpg', 'image/jpeg', 'uploads', '1297.56 KB', 'present', '2026-06-04 05:18:25'),
(185, 'post_89_69a994e93167c.jpg', 'uploads/post_89_69a994e93167c.jpg', 'image/jpeg', 'uploads', '439.88 KB', 'present', '2026-06-04 05:18:25'),
(186, 'post_89_69a994e933088.jpg', 'uploads/post_89_69a994e933088.jpg', 'image/jpeg', 'uploads', '884.05 KB', 'present', '2026-06-04 05:18:25'),
(187, 'post_89_69a994e934ef2.jpg', 'uploads/post_89_69a994e934ef2.jpg', 'image/jpeg', 'uploads', '603.33 KB', 'present', '2026-06-04 05:18:25'),
(188, 'post_90_69a99b3c6d98b.mp4', 'uploads/post_90_69a99b3c6d98b.mp4', 'video/mp4', 'uploads', '53678.01 KB', 'present', '2026-06-04 05:18:25'),
(189, 'profile_1234-123456_1771159153.jpg', 'uploads/profile_1234-123456_1771159153.jpg', 'image/jpeg', 'uploads', '306.5 KB', 'present', '2026-06-04 05:18:25'),
(190, 'profile_123456789_1771426436.jpg', 'uploads/profile_123456789_1771426436.jpg', 'image/jpeg', 'uploads', '70.64 KB', 'present', '2026-06-04 05:18:25'),
(191, 'profile_123456789_1771426443.jpg', 'uploads/profile_123456789_1771426443.jpg', 'image/jpeg', 'uploads', '70.64 KB', 'present', '2026-06-04 05:18:25'),
(192, 'profile_123456_1771199583.jpg', 'uploads/profile_123456_1771199583.jpg', 'image/jpeg', 'uploads', '1803.4 KB', 'present', '2026-06-04 05:18:25'),
(193, 'profile_1701-00352_1778892792.png', 'uploads/profile_1701-00352_1778892792.png', 'image/png', 'uploads', '760.93 KB', 'present', '2026-06-04 05:18:25'),
(194, 'profile_20913_1772978080.png', 'uploads/profile_20913_1772978080.png', 'image/png', 'uploads', '198.31 KB', 'present', '2026-06-04 05:18:25'),
(195, 'profile_2202-000012_1771159194.jpg', 'uploads/profile_2202-000012_1771159194.jpg', 'image/jpeg', 'uploads', '103.13 KB', 'present', '2026-06-04 05:18:25'),
(196, 'profile_2301-000076_1776519078.jpg', 'uploads/profile_2301-000076_1776519078.jpg', 'image/jpeg', 'uploads', '57 KB', 'present', '2026-06-04 05:18:25'),
(197, 'profile_2301-000111_1771159050.jpg', 'uploads/profile_2301-000111_1771159050.jpg', 'image/jpeg', 'uploads', '70.73 KB', 'present', '2026-06-04 05:18:25'),
(198, 'profile_2301-000474_1771159102.jpg', 'uploads/profile_2301-000474_1771159102.jpg', 'image/jpeg', 'uploads', '125.31 KB', 'present', '2026-06-04 05:18:25'),
(199, 'profile_2302-000019_1771159456.jpg', 'uploads/profile_2302-000019_1771159456.jpg', 'image/jpeg', 'uploads', '150.35 KB', 'present', '2026-06-04 05:18:25'),
(200, 'student_cover_1701-00352_1772803715.png', 'uploads/student_cover_1701-00352_1772803715.png', 'image/png', 'uploads', '760.93 KB', 'present', '2026-06-04 05:18:25'),
(201, 'teacher_1771223799.jpg', 'uploads/teacher_1771223799.jpg', 'image/jpeg', 'uploads', '306.5 KB', 'present', '2026-06-04 05:18:25'),
(202, 'teacher_1771244017.jpg', 'uploads/teacher_1771244017.jpg', 'image/jpeg', 'uploads', '226.45 KB', 'present', '2026-06-04 05:18:25'),
(203, 'teacher_1771244082.jpg', 'uploads/teacher_1771244082.jpg', 'image/jpeg', 'uploads', '70.64 KB', 'present', '2026-06-04 05:18:25'),
(204, 'teacher_1771244115.jpg', 'uploads/teacher_1771244115.jpg', 'image/jpeg', 'uploads', '327.15 KB', 'present', '2026-06-04 05:18:25'),
(205, 'teacher_1771244275.jpg', 'uploads/teacher_1771244275.jpg', 'image/jpeg', 'uploads', '70.64 KB', 'present', '2026-06-04 05:18:25'),
(206, 'teacher_1771244296.jpg', 'uploads/teacher_1771244296.jpg', 'image/jpeg', 'uploads', '226.45 KB', 'present', '2026-06-04 05:18:25'),
(207, 'teacher_1771583631.jpg', 'uploads/teacher_1771583631.jpg', 'image/jpeg', 'uploads', '70.64 KB', 'present', '2026-06-04 05:18:25'),
(208, 'teacher_1771583677.jpg', 'uploads/teacher_1771583677.jpg', 'image/jpeg', 'uploads', '70.64 KB', 'present', '2026-06-04 05:18:25'),
(209, 'teacher_1771583695.jpg', 'uploads/teacher_1771583695.jpg', 'image/jpeg', 'uploads', '70.64 KB', 'present', '2026-06-04 05:18:25'),
(210, 'teacher_1771583717.jpg', 'uploads/teacher_1771583717.jpg', 'image/jpeg', 'uploads', '70.64 KB', 'present', '2026-06-04 05:18:25'),
(211, 'teacher_1773490680.png', 'uploads/teacher_1773490680.png', 'image/png', 'uploads', '888.33 KB', 'present', '2026-06-04 05:18:25'),
(212, 'teacher_1773490696.png', 'uploads/teacher_1773490696.png', 'image/png', 'uploads', '888.33 KB', 'present', '2026-06-04 05:18:25'),
(213, 'teacher_1773490709.png', 'uploads/teacher_1773490709.png', 'image/png', 'uploads', '888.33 KB', 'present', '2026-06-04 05:18:25'),
(214, 'teacher_1773490720.png', 'uploads/teacher_1773490720.png', 'image/png', 'uploads', '888.33 KB', 'present', '2026-06-04 05:18:25'),
(215, 'sc_1779507613_6a11219d515f9_Visitor-Log-System-MIT-App-Inventor-Google-Sheets.pptx', 'uploads/storage/sc_1779507613_6a11219d515f9_Visitor-Log-System-MIT-App-Inventor-Google-Sheets.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'storage', '32364.99 KB', 'present', '2026-06-04 05:18:25'),
(216, 'sc_1779507613_6a11219d53cb8_Visitor-Log-System-MIT-App-Inventor-Google-Sheets.pdf', 'uploads/storage/sc_1779507613_6a11219d53cb8_Visitor-Log-System-MIT-App-Inventor-Google-Sheets.pdf', 'application/pdf', 'storage', '4794.14 KB', 'present', '2026-06-04 05:18:25'),
(217, 'sc_1779507613_6a11219d56549_CAPSTONE-FORMAT-FOR-IT-ENGR.docx', 'uploads/storage/sc_1779507613_6a11219d56549_CAPSTONE-FORMAT-FOR-IT-ENGR.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'storage', '59.35 KB', 'present', '2026-06-04 05:18:25'),
(364, 'post_200_6a209ca7c207a.mp4', 'uploads/post_200_6a209ca7c207a.mp4', 'video/mp4', 'uploads', '16948.04 KB', 'present', '2026-06-09 21:50:01'),
(365, 'post_202_6a2519b966a06.mp4', 'uploads/post_202_6a2519b966a06.mp4', 'video/mp4', 'uploads', '16948.04 KB', 'present', '2026-06-09 21:50:01'),
(366, 'post_202_6a2519b96894c.jpg', 'uploads/post_202_6a2519b96894c.jpg', 'image/jpeg', 'uploads', '131.37 KB', 'present', '2026-06-09 21:50:01'),
(367, 'post_202_6a2519b96b3d3.png', 'uploads/post_202_6a2519b96b3d3.png', 'image/png', 'uploads', '108.72 KB', 'present', '2026-06-09 21:50:01'),
(421, 'roompost_21_6a28107031670_TimDan.pdf', 'uploads/roompost_21_6a28107031670_TimDan.pdf', 'application/pdf', 'uploads', '218.11 KB', 'present', '2026-06-09 21:50:01'),
(422, 'roompost_23_6a2817e0246ef_TimDan.pdf', 'uploads/roompost_23_6a2817e0246ef_TimDan.pdf', 'application/pdf', 'uploads', '218.11 KB', 'present', '2026-06-09 21:50:01'),
(423, 'roompost_24_6a281812e83c6_CTF_Activity.docx', 'uploads/roompost_24_6a281812e83c6_CTF_Activity.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'uploads', '31.75 KB', 'present', '2026-06-09 21:50:01');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `cover_pic1` varchar(255) DEFAULT '',
  `cover_pic2` varchar(255) DEFAULT '',
  `cover_pic3` varchar(255) DEFAULT '',
  `is_restricted` tinyint(1) DEFAULT 0,
  `restriction_end_date` datetime DEFAULT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `last_activity` datetime DEFAULT NULL,
  `mfa_enabled` tinyint(1) DEFAULT 0,
  `mfa_secret` varchar(255) DEFAULT NULL,
  `recovery_email` varchar(255) DEFAULT NULL,
  `otp_code` varchar(255) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `cover_photo` varchar(255) DEFAULT '',
  `cover_offset` int(11) DEFAULT 0,
  `location` varchar(255) DEFAULT '',
  `birthdate` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `phone` varchar(20) DEFAULT NULL,
  `hide_phone` tinyint(1) DEFAULT 0,
  `force_logout` tinyint(1) DEFAULT 0,
  `logout_token` varchar(255) DEFAULT NULL,
  `profile_pic_content` longblob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `name`, `department`, `position`, `profile_pic`, `email`, `password`, `bio`, `cover_pic1`, `cover_pic2`, `cover_pic3`, `is_restricted`, `restriction_end_date`, `is_online`, `last_activity`, `mfa_enabled`, `mfa_secret`, `recovery_email`, `otp_code`, `otp_expiry`, `cover_photo`, `cover_offset`, `location`, `birthdate`, `created_at`, `phone`, `hide_phone`, `force_logout`, `logout_token`, `profile_pic_content`) VALUES
(15, 'Maam', 'Elementary Department', 'Math Teacher', 'teacher_1773490696.png', 'testing12@gmail', '123', '', '', '', '', 0, NULL, 0, '2026-06-18 12:12:10', 0, NULL, NULL, NULL, NULL, 'cover_15_1776865387.jpg', 0, '', NULL, '2026-04-21 04:52:10', '', 0, 0, '8d3c0ad27cd64d8e8f0b45a15f48249a', NULL),
(16, 'Ser', 'Junior High School', 'English Teacher', 'teacher_1773490709.png', 'testing122@gmail', '1234', NULL, '', '', '', 0, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, '', 0, '', NULL, '2026-04-21 04:52:10', NULL, 0, 0, NULL, NULL),
(17, 'Mem', 'Senior High School', 'Basic Calculus', 'teacher_1773490720.png', 'testing12@gmail', '12345', NULL, '', '', '', 0, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, '', 0, '', NULL, '2026-04-21 04:52:10', NULL, 0, 0, NULL, NULL),
(18, 'Prof', 'College Department', 'Programing 1 Teacher', 'teacher_1773490680.png', 'testing12@gmail', '12345', NULL, '', '', '', 0, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, '', 0, '', NULL, '2026-04-21 04:52:10', NULL, 0, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `typing_status`
--

CREATE TABLE `typing_status` (
  `user_id` varchar(50) NOT NULL,
  `typing_to` varchar(50) DEFAULT NULL,
  `type` enum('direct','group') DEFAULT 'direct',
  `last_typed` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_active_sessions`
--

CREATE TABLE `user_active_sessions` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  `last_activity` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `is_current_device` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_resources`
--

CREATE TABLE `user_resources` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('file','link') NOT NULL,
  `path_or_link` varchar(255) NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_storage`
--

CREATE TABLE `user_storage` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `parent_id` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_storage`
--

INSERT INTO `user_storage` (`id`, `user_id`, `file_name`, `file_path`, `file_type`, `file_size`, `uploaded_at`, `parent_id`) VALUES
(3, '1701-00352', '1 sem file', 'DIR_NODE', 'folder', 0, '2026-05-22 22:11:17', 0),
(5, '1701-00352', 'Visitor-Log-System-MIT-App-Inventor-Google-Sheets.pptx', 'uploads/storage/sc_1779507613_6a11219d515f9_Visitor-Log-System-MIT-App-Inventor-Google-Sheets.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 33141748, '2026-05-23 11:40:13', 3),
(6, '1701-00352', 'Visitor-Log-System-MIT-App-Inventor-Google-Sheets.pdf', 'uploads/storage/sc_1779507613_6a11219d53cb8_Visitor-Log-System-MIT-App-Inventor-Google-Sheets.pdf', 'application/pdf', 4909195, '2026-05-23 11:40:13', 3),
(7, '1701-00352', 'CAPSTONE-FORMAT-FOR-IT-ENGR.docx', 'uploads/storage/sc_1779507613_6a11219d56549_CAPSTONE-FORMAT-FOR-IT-ENGR.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 60773, '2026-05-23 11:40:13', 3);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achievements`
--
ALTER TABLE `achievements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `active_meetings`
--
ALTER TABLE `active_meetings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `meeting_code` (`meeting_code`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `admins1`
--
ALTER TABLE `admins1`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `admins2`
--
ALTER TABLE `admins2`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `admin_concerns`
--
ALTER TABLE `admin_concerns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `admin_security`
--
ALTER TABLE `admin_security`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `alumni`
--
ALTER TABLE `alumni`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `calendar_events`
--
ALTER TABLE `calendar_events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_clear_history`
--
ALTER TABLE `chat_clear_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`other_id`);

--
-- Indexes for table `chat_media`
--
ALTER TABLE `chat_media`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `direct_chat_themes`
--
ALTER TABLE `direct_chat_themes`
  ADD PRIMARY KEY (`user1_id`,`user2_id`);

--
-- Indexes for table `direct_messages`
--
ALTER TABLE `direct_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `evaluation_answers`
--
ALTER TABLE `evaluation_answers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `evaluation_questions`
--
ALTER TABLE `evaluation_questions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `group_chats`
--
ALTER TABLE `group_chats`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `group_chat_members`
--
ALTER TABLE `group_chat_members`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `group_chat_mentions`
--
ALTER TABLE `group_chat_mentions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `message_id` (`message_id`);

--
-- Indexes for table `group_chat_messages`
--
ALTER TABLE `group_chat_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`ip_address`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `message_deletions`
--
ALTER TABLE `message_deletions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `message_id` (`message_id`,`user_id`,`chat_type`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_change_requests`
--
ALTER TABLE `password_change_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pending_profile_changes`
--
ALTER TABLE `pending_profile_changes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `verification_token` (`verification_token`);

--
-- Indexes for table `poll_options`
--
ALTER TABLE `poll_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `poll_votes`
--
ALTER TABLE `poll_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `post_id` (`post_id`,`user_id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `post_comments`
--
ALTER TABLE `post_comments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `post_media`
--
ALTER TABLE `post_media`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `post_reactions`
--
ALTER TABLE `post_reactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `post_tags`
--
ALTER TABLE `post_tags`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `post_views_tracking`
--
ALTER TABLE `post_views_tracking`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `post_id` (`post_id`,`student_id`);

--
-- Indexes for table `sacli_meetings`
--
ALTER TABLE `sacli_meetings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `meeting_code` (`meeting_code`);

--
-- Indexes for table `sacli_meeting_logs`
--
ALTER TABLE `sacli_meeting_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `room_code` (`room_code`);

--
-- Indexes for table `sacli_meeting_participants`
--
ALTER TABLE `sacli_meeting_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `meeting_code` (`meeting_code`,`student_id`);

--
-- Indexes for table `sacli_rooms`
--
ALTER TABLE `sacli_rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_code` (`room_code`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `room_code_2` (`room_code`);

--
-- Indexes for table `sacli_room_invitations`
--
ALTER TABLE `sacli_room_invitations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_id` (`room_id`,`student_id`);

--
-- Indexes for table `sacli_room_members`
--
ALTER TABLE `sacli_room_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_id` (`room_id`,`student_id`);

--
-- Indexes for table `sacli_room_posts`
--
ALTER TABLE `sacli_room_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `sacli_room_post_attachments`
--
ALTER TABLE `sacli_room_post_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `sacli_room_submissions`
--
ALTER TABLE `sacli_room_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `post_id` (`post_id`,`student_id`);

--
-- Indexes for table `security_audit_logs`
--
ALTER TABLE `security_audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sidebar_menu`
--
ALTER TABLE `sidebar_menu`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `storage_shares`
--
ALTER TABLE `storage_shares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `share_token` (`share_token`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `subject_chats`
--
ALTER TABLE `subject_chats`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_file_registry`
--
ALTER TABLE `system_file_registry`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `file_path` (`file_path`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `typing_status`
--
ALTER TABLE `typing_status`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `user_active_sessions`
--
ALTER TABLE `user_active_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`);

--
-- Indexes for table `user_resources`
--
ALTER TABLE `user_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_storage`
--
ALTER TABLE `user_storage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `achievements`
--
ALTER TABLE `achievements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `active_meetings`
--
ALTER TABLE `active_meetings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admins1`
--
ALTER TABLE `admins1`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admins2`
--
ALTER TABLE `admins2`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `admin_concerns`
--
ALTER TABLE `admin_concerns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `admin_security`
--
ALTER TABLE `admin_security`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `alumni`
--
ALTER TABLE `alumni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `calendar_events`
--
ALTER TABLE `calendar_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `chat_clear_history`
--
ALTER TABLE `chat_clear_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `chat_media`
--
ALTER TABLE `chat_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `direct_messages`
--
ALTER TABLE `direct_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=499;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `evaluation_answers`
--
ALTER TABLE `evaluation_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=264;

--
-- AUTO_INCREMENT for table `evaluation_questions`
--
ALTER TABLE `evaluation_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_chats`
--
ALTER TABLE `group_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `group_chat_members`
--
ALTER TABLE `group_chat_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `group_chat_mentions`
--
ALTER TABLE `group_chat_mentions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_chat_messages`
--
ALTER TABLE `group_chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=783;

--
-- AUTO_INCREMENT for table `message_deletions`
--
ALTER TABLE `message_deletions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=940;

--
-- AUTO_INCREMENT for table `password_change_requests`
--
ALTER TABLE `password_change_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `pending_profile_changes`
--
ALTER TABLE `pending_profile_changes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `poll_options`
--
ALTER TABLE `poll_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `poll_votes`
--
ALTER TABLE `poll_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=222;

--
-- AUTO_INCREMENT for table `post_comments`
--
ALTER TABLE `post_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `post_media`
--
ALTER TABLE `post_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=273;

--
-- AUTO_INCREMENT for table `post_reactions`
--
ALTER TABLE `post_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

--
-- AUTO_INCREMENT for table `post_tags`
--
ALTER TABLE `post_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `post_views_tracking`
--
ALTER TABLE `post_views_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `sacli_meetings`
--
ALTER TABLE `sacli_meetings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- AUTO_INCREMENT for table `sacli_meeting_logs`
--
ALTER TABLE `sacli_meeting_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `sacli_meeting_participants`
--
ALTER TABLE `sacli_meeting_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `sacli_rooms`
--
ALTER TABLE `sacli_rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `sacli_room_invitations`
--
ALTER TABLE `sacli_room_invitations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `sacli_room_members`
--
ALTER TABLE `sacli_room_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `sacli_room_posts`
--
ALTER TABLE `sacli_room_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `sacli_room_post_attachments`
--
ALTER TABLE `sacli_room_post_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `sacli_room_submissions`
--
ALTER TABLE `sacli_room_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `security_audit_logs`
--
ALTER TABLE `security_audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sidebar_menu`
--
ALTER TABLE `sidebar_menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=326;

--
-- AUTO_INCREMENT for table `storage_shares`
--
ALTER TABLE `storage_shares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `subject_chats`
--
ALTER TABLE `subject_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

--
-- AUTO_INCREMENT for table `system_file_registry`
--
ALTER TABLE `system_file_registry`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=666;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `user_active_sessions`
--
ALTER TABLE `user_active_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_resources`
--
ALTER TABLE `user_resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_storage`
--
ALTER TABLE `user_storage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `group_chat_mentions`
--
ALTER TABLE `group_chat_mentions`
  ADD CONSTRAINT `group_chat_mentions_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `group_chat_messages` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
