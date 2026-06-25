<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: SacliConnect_LOG_IN.php");
    exit();
}

// --- DATABASE AUTO-FIX FOR THEMES ---
$conn->query("CREATE TABLE IF NOT EXISTS direct_chat_themes (user1_id VARCHAR(50), user2_id VARCHAR(50), theme VARCHAR(50), PRIMARY KEY (user1_id, user2_id))");
$conn->query("ALTER TABLE group_chats ADD COLUMN IF NOT EXISTS theme VARCHAR(50) DEFAULT 'default'");
// ------------------------------------

$my_id = $_SESSION['student_id'];
$active_chat_id = $_GET['id'] ?? null;
$active_chat_type = $_GET['type'] ?? 'direct';

// Blackout Protocol Check
$blackout_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='blackout_mode'");
$blackout_active = ($blackout_res && $blackout_res->num_rows > 0 && $blackout_res->fetch_assoc()['setting_value'] == '1');

// Fetch Site Theme
$theme_q = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='site_theme'");
$current_theme = ($theme_q && $theme_q->num_rows > 0) ? $theme_q->fetch_assoc()['setting_value'] : 'default';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SACLICONNECT_V4 // MESSENGER</title>
    <link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/1_SacliConnect.css">
    <style>
        :root {
            --neon-green: #00ffaa;
            --neon-blue: #00ccff;
            --glass-bg: rgba(10, 31, 22, 0.85);
            --border-glow: rgba(0, 255, 170, 0.3);
            --terminal-font: 'Courier New', Courier, monospace;
            --transition-main: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }

        @font-face {
            font-family: 'Orbitron';
            src: url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap');
        }

        body {
            margin: 0; padding: 0; 
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: #020806 !important;
            overflow: hidden;
            color: #e4e6eb;
            position: relative;
        }
        /* Animated Neural Background */
        body::before {
            content: "";
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle at center, #0d2b1f 0%, #020806 100%);
            z-index: -2;
        }
        body::after {
            content: " ";
            position: absolute; top: 0; left: 0; bottom: 0; right: 0;
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 255, 170, 0.03) 50%), 
                        linear-gradient(90deg, rgba(255, 0, 0, 0.01), rgba(0, 255, 0, 0.01), rgba(0, 0, 255, 0.01));
            z-index: 100; background-size: 100% 4px, 3px 100%; pointer-events: none; opacity: 0.3;
        }
        .messenger-header {
            height: 65px;
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid var(--border-glow);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 20px; z-index: 10;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
        }
        .messenger-container {
            display: flex;
            flex: 1;
            overflow: hidden;
            height: calc(100vh - 65px); /* Header height offset */
            padding: 10px;
            gap: 10px;
            position: relative;
        }
        /* Animated Neural Sweep Light sa Background */
        .messenger-container::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -120%;
            width: 50%;
            height: 200%;
            background: linear-gradient(to right, transparent, rgba(0, 255, 170, 0.4), transparent);
            transform: rotate(25deg);
            pointer-events: none;
            z-index: 0;
            animation: neuralSweep 6s infinite linear;
            filter: blur(80px);
        }
        @keyframes neuralSweep {
            0% { left: -120%; }
            100% { left: 220%; }
        }
        .chat-sidebar {
            width: 360px;
            background: rgba(0, 20, 15, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-glow);
            border-radius: 15px;
            display: flex; flex-direction: column;
            position: relative;
            z-index: 1;
        }
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: rgba(0, 10, 5, 0.4);
            border: 1px solid var(--border-glow);
            border-radius: 15px;
            position: relative;
            z-index: 1;
        }
        .chat-header {
            padding: 15px 25px;
            border-bottom: 1px solid var(--border-glow);
            display: flex; align-items: center; gap: 15px;
            background: rgba(16, 46, 34, 0.3);
            backdrop-filter: blur(10px);
            position: relative;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        }
        .chat-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px; /* Adjusted padding to give messages more space */
            display: flex;
            flex-direction: column;
            gap: 12px;
            background-image: 
                linear-gradient(rgba(0, 255, 170, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 255, 170, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: gridFlow 60s linear infinite;
        }
        /* Message Timestamp Separator */
        .msg-timestamp-separator {
            text-align: center;
            margin: 20px 0;
            color: #fff;
            font-size: 11px;
            font-family: var(--terminal-font);
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding-left: 20px;
        }
        /* Add smooth transition for theme changes */
        #fullChatBody {
            transition: background-color 0.8s ease;
        }
        #chatView {
            transition: background 0.8s ease;
        }

        /* New Message Scroll Button */
        #newMsgScrollBtn {
            position: absolute;
            bottom: 85px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: var(--neon-green);
            color: #0a1f16;
            padding: 10px 20px;
            border-radius: 30px;
            font-family: var(--terminal-font);
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            z-index: 1000;
            display: none;
            align-items: center;
            gap: 10px;
            box-shadow: 0 5px 20px rgba(0, 255, 170, 0.5);
            border: 2px solid #fff;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            opacity: 0;
            pointer-events: auto;
        }
        #newMsgScrollBtn.show {
            display: flex;
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        #newMsgScrollBtn:hover { transform: translateX(-50%) scale(1.1); background: #fff; }

        /* --- FLASHLIGHT THEME (User Provided) --- */
        .chat-body.theme-flashlight {
            --color-0: #fff; --color-1: #111; --color-2: #222; --color-3: #333;
            --color-4: #2e2e2e; --color-5: #d2b48c; --color-6: #b22222;
            --color-7: #871a1a; --color-8: #ff6347; --color-9: #ff3814;
            background-color: var(--color-1) !important;
            background-image: 
                linear-gradient(to top, var(--color-2) 5%, var(--color-1) 6%, var(--color-1) 7%, transparent 7%),
                linear-gradient(to bottom, var(--color-1) 30%, transparent 80%),
                linear-gradient(to right, var(--color-2), var(--color-4) 5%, transparent 5%),
                linear-gradient(to right, transparent 6%, var(--color-2) 6%, var(--color-4) 9%, transparent 9%),
                linear-gradient(to right, transparent 27%, var(--color-2) 27%, var(--color-4) 34%, transparent 34%),
                linear-gradient(to right, transparent 51%, var(--color-2) 51%, var(--color-4) 57%, transparent 57%),
                linear-gradient(to bottom, var(--color-1) 35%, transparent 35%),
                linear-gradient(to right, transparent 42%, var(--color-2) 42%, var(--color-4) 44%, transparent 44%),
                linear-gradient(to right, transparent 45%, var(--color-2) 45%, var(--color-4) 47%, transparent 47%),
                linear-gradient(to right, transparent 48%, var(--color-2) 48%, var(--color-4) 50%, transparent 50%),
                linear-gradient(to right, transparent 87%, var(--color-2) 87%, var(--color-4) 91%, transparent 91%),
                linear-gradient(to bottom, var(--color-1) 37.5%, transparent 37.5%),
                linear-gradient(to right, transparent 14%, var(--color-2) 14%, var(--color-4) 20%, transparent 20%),
                linear-gradient(to bottom, var(--color-1) 40%, transparent 40%),
                linear-gradient(to right, transparent 10%, var(--color-2) 10%, var(--color-4) 13%, transparent 13%),
                linear-gradient(to right, transparent 21%, var(--color-2) 21%, #1a1a1a 25%, transparent 25%),
                linear-gradient(to right, transparent 58%, var(--color-2) 58%, var(--color-4) 64%, transparent 64%),
                linear-gradient(to right, transparent 92%, var(--color-2) 92%, var(--color-4) 95%, transparent 95%),
                linear-gradient(to bottom, var(--color-1) 48%, transparent 48%),
                linear-gradient(to right, transparent 96%, var(--color-2) 96%, #1a1a1a 99%, transparent 99%),
                linear-gradient(to bottom, transparent 68.5%, transparent 76%, var(--color-1) 76%, var(--color-1) 77.5%, transparent 77.5%, transparent 86%, var(--color-1) 86%, var(--color-1) 87.5%, transparent 87.5%),
                linear-gradient(to right, transparent 35%, var(--color-2) 35%, var(--color-4) 41%, transparent 41%),
                linear-gradient(to bottom, var(--color-1) 68%, transparent 68%),
                linear-gradient(to right, transparent 78%, var(--color-3) 78%, var(--color-3) 80%, transparent 80%, transparent 82%, var(--color-3) 82%, var(--color-3) 83%, transparent 83%),
                linear-gradient(to right, transparent 66%, var(--color-2) 66%, var(--color-4) 85%, transparent 85%) !important;
            background-size: 300px 150px !important;
            background-position: center bottom !important;
            position: relative;
            z-index: 1;
        }
        .chat-body.theme-flashlight::before {
            content: ""; width: 100%; height: 100%; position: absolute; inset: 0;
            background-color: var(--color-1);
            background-image: 
                linear-gradient(to top, var(--color-5) 5%, var(--color-1) 6%, var(--color-1) 7%, transparent 7%),
                linear-gradient(to bottom, var(--color-1) 30%, transparent 30%),
                linear-gradient(to right, var(--color-6), var(--color-7) 5%, transparent 5%),
                linear-gradient(to right, transparent 6%, var(--color-8) 6%, var(--color-9) 9%, transparent 9%),
                linear-gradient(to right, transparent 27%, #556b2f 27%, #39481f 34%, transparent 34%),
                linear-gradient(to right, transparent 51%, #fa8072 51%, #f85441 57%, transparent 57%),
                linear-gradient(to bottom, var(--color-1) 35%, transparent 35%),
                linear-gradient(to right, transparent 42%, #008080 42%, #004d4d 44%, transparent 44%),
                linear-gradient(to right, transparent 45%, #008080 45%, #004d4d 47%, transparent 47%),
                linear-gradient(to right, transparent 48%, #008080 48%, #004d4d 50%, transparent 50%),
                linear-gradient(to right, transparent 87%, #789 87%, #4f5d6a 91%, transparent 91%),
                linear-gradient(to bottom, var(--color-1) 37.5%, transparent 37.5%),
                linear-gradient(to right, transparent 14%, #bdb76b 14%, #989244 20%, transparent 20%),
                linear-gradient(to bottom, var(--color-1) 40%, transparent 40%),
                linear-gradient(to right, transparent 10%, #808000 10%, #4d4d00 13%, transparent 13%),
                linear-gradient(to right, transparent 21%, #8b4513 21%, #5e2f0d 25%, transparent 25%),
                linear-gradient(to right, transparent 58%, #8b4513 58%, #5e2f0d 64%, transparent 64%),
                linear-gradient(to right, transparent 92%, #2f4f4f 92%, #1c2f2f 95%, transparent 95%),
                linear-gradient(to bottom, var(--color-1) 48%, transparent 48%),
                linear-gradient(to right, transparent 96%, #2f4f4f 96%, #1c2f2f 99%, transparent 99%),
                linear-gradient(to bottom, transparent 68.5%, transparent 76%, var(--color-1) 76%, var(--color-1) 77.5%, transparent 77.5%, transparent 86%, var(--color-1) 86%, var(--color-1) 87.5%, transparent 87.5%),
                linear-gradient(to right, transparent 35%, #cd5c5c 35%, #bc3a3a 41%, transparent 41%),
                linear-gradient(to bottom, var(--color-1) 68%, transparent 68%),
                linear-gradient(to right, transparent 78%, #bc8f8f 78%, #bc8f8f 80%, transparent 80%, transparent 82%, #bc8f8f 82%, #bc8f8f 83%, transparent 83%),
                linear-gradient(to right, transparent 66%, #a52a2a 66%, #7c2020 85%, transparent 85%);
            background-size: 300px 150px; background-position: center bottom;
            clip-path: circle(150px at center center); animation: flashlight 20s ease infinite;
            z-index: -1;
        }
        .chat-body.theme-flashlight::after {
            content: ""; width: 25px; height: 10px; position: absolute; left: calc(50% + 59px); bottom: 100px;
            background-repeat: no-repeat;
            background-image: radial-gradient(circle, #fff 50%, transparent 50%), radial-gradient(circle, #fff 50%, transparent 50%);
            background-size: 10px 10px; background-position: left center, right center;
            animation: eyes 20s infinite; z-index: -1;
        }
        @keyframes flashlight {
            0% { clip-path: circle(150px at -25% 10%); }
            38% { clip-path: circle(150px at 60% 20%); }
            39% { opacity: 1; clip-path: circle(150px at 60% 86%); }
            40% { opacity: 0; clip-path: circle(150px at 60% 86%); }
            41% { opacity: 1; clip-path: circle(150px at 60% 86%); }
            42% { opacity: 0; clip-path: circle(150px at 60% 86%); }
            54% { opacity: 0; clip-path: circle(150px at 60% 86%); }
            55% { opacity: 1; clip-path: circle(150px at 60% 86%); }
            59% { opacity: 1; clip-path: circle(150px at 60% 86%); }
            64% { clip-path: circle(150px at 45% 78%); }
            68% { clip-path: circle(150px at 85% 89%); }
            72% { clip-path: circle(150px at 60% 86%); }
            74% { clip-path: circle(150px at 60% 86%); }
            100% { clip-path: circle(150px at 150% 50%); }
        }
        @keyframes eyes {
            0%, 38% { opacity: 0; }
            39%, 41% { opacity: 1; transform: scaleY(1); }
            40% { transform: scaleY(0); filter: none; background-image: radial-gradient(circle, #fff 50%, transparent 50%), radial-gradient(circle, #fff 50%, transparent 50%); }
            41% { transform: scaleY(1); background-image: radial-gradient(circle, #ff0000 50%, transparent 50%), radial-gradient(circle, #ff0000 50%, transparent 50%); filter: drop-shadow(0 0 4px #ff8686); }
            42%, 100% { opacity: 0; }
        }
        /* Ensure messages stay above theme effects */
        .chat-body.theme-flashlight .msg-container { z-index: 5; }
        
        /* --- RAIN THEME (User Provided) --- */
        #chatView.theme-rain {
            background-color: #000 !important;
            position: relative;
            overflow: hidden;
        }
        #chatView.theme-rain #fullChatBody { background: transparent !important; z-index: 2; position: relative; }
        #rainLayer { display: none; position: absolute; inset: 0; pointer-events: none; z-index: 0; }
        .theme-rain #rainLayer { 
            display: block; 
            --c: #09f;
            background-image: 
                radial-gradient(4px 100px at 0px 235px, var(--c), #0000),
                radial-gradient(4px 100px at 300px 235px, var(--c), #0000),
                radial-gradient(1.5px 1.5px at 150px 117.5px, var(--c) 100%, #0000 150%),
                radial-gradient(4px 100px at 0px 252px, var(--c), #0000),
                radial-gradient(4px 100px at 300px 252px, var(--c), #0000),
                radial-gradient(1.5px 1.5px at 150px 126px, var(--c) 100%, #0000 150%),
                radial-gradient(4px 100px at 0px 150px, var(--c), #0000),
                radial-gradient(4px 100px at 300px 150px, var(--c), #0000),
                radial-gradient(1.5px 1.5px at 150px 75px, var(--c) 100%, #0000 150%),
                radial-gradient(4px 100px at 0px 253px, var(--c), #0000),
                radial-gradient(4px 100px at 300px 253px, var(--c), #0000),
                radial-gradient(1.5px 1.5px at 150px 126.5px, var(--c) 100%, #0000 150%),
                radial-gradient(4px 100px at 0px 204px, var(--c), #0000),
                radial-gradient(4px 100px at 300px 204px, var(--c), #0000),
                radial-gradient(1.5px 1.5px at 150px 102px, var(--c) 100%, #0000 150%),
                radial-gradient(4px 100px at 0px 134px, var(--c), #0000),
                radial-gradient(4px 100px at 300px 134px, var(--c), #0000),
                radial-gradient(1.5px 1.5px at 150px 67px, var(--c) 100%, #0000 150%),
                radial-gradient(4px 100px at 0px 179px, var(--c), #0000),
                radial-gradient(4px 100px at 300px 179px, var(--c), #0000),
                radial-gradient(1.5px 1.5px at 150px 89.5px, var(--c) 100%, #0000 150%),
                radial-gradient(4px 100px at 0px 299px, var(--c), #0000),
                radial-gradient(4px 100px at 300px 299px, var(--c), #0000),
                radial-gradient(1.5px 1.5px at 150px 149.5px, var(--c) 100%, #0000 150%),
                radial-gradient(4px 100px at 0px 215px, var(--c), #0000),
                radial-gradient(4px 100px at 300px 215px, var(--c), #0000),
                radial-gradient(1.5px 1.5px at 150px 107.5px, var(--c) 100%, #0000 150%),
                radial-gradient(4px 100px at 0px 281px, var(--c), #0000),
                radial-gradient(4px 100px at 300px 281px, var(--c), #0000),
                radial-gradient(1.5px 1.5px at 150px 140.5px, var(--c) 100%, #0000 150%),
                radial-gradient(4px 100px at 0px 158px, var(--c), #0000),
                radial-gradient(4px 100px at 300px 158px, var(--c), #0000),
                radial-gradient(1.5px 1.5px at 150px 79px, var(--c) 100%, #0000 150%),
                radial-gradient(4px 100px at 0px 210px, var(--c), #0000),
                radial-gradient(4px 100px at 300px 210px, var(--c), #0000),
                radial-gradient(1.5px 1.5px at 150px 105px, var(--c) 100%, #0000 150%);
            background-size:
                300px 235px, 300px 235px, 300px 235px, 300px 252px, 300px 252px, 300px 252px, 300px 150px, 300px 150px, 300px 150px, 300px 253px, 300px 253px, 300px 253px, 300px 204px, 300px 204px, 300px 204px, 300px 134px, 300px 134px, 300px 134px, 300px 179px, 300px 179px, 300px 179px, 300px 299px, 300px 299px, 300px 299px, 300px 215px, 300px 215px, 300px 215px, 300px 281px, 300px 281px, 300px 281px, 300px 158px, 300px 158px, 300px 158px, 300px 210px, 300px 210px, 300px 210px;
            animation: rainEffect 150s linear infinite;
        }
        #rainLayer::after {
            content: ""; position: absolute; inset: 0; z-index: 1; pointer-events: none;
            backdrop-filter: blur(1em) brightness(6);
            background-image: radial-gradient(circle at 50% 50%, #0000 0, #0000 2px, hsl(0 0 4%) 2px);
            background-size: 8px 8px;
        }

        @keyframes rainEffect {
            0% {
                background-position:
                0px 220px, 3px 220px, 151.5px 337.5px, 25px 24px, 28px 24px, 176.5px 150px, 50px 16px, 53px 16px, 201.5px 91px, 75px 224px, 78px 224px, 226.5px 350.5px, 100px 19px, 103px 19px, 251.5px 121px, 125px 120px, 128px 120px, 276.5px 187px, 150px 31px, 153px 31px, 301.5px 120.5px, 175px 235px, 178px 235px, 326.5px 384.5px, 200px 121px, 203px 121px, 351.5px 228.5px, 225px 224px, 228px 224px, 376.5px 364.5px, 250px 26px, 253px 26px, 401.5px 105px, 275px 75px, 278px 75px, 426.5px 180px;
            }
            to {
                background-position:
                0px 6800px, 3px 6800px, 151.5px 6917.5px, 25px 13632px, 28px 13632px, 176.5px 13758px, 50px 5416px, 53px 5416px, 201.5px 5491px, 75px 17175px, 78px 17175px, 226.5px 17301.5px, 100px 5119px, 103px 5119px, 251.5px 5221px, 125px 8428px, 128px 8428px, 276.5px 8495px, 150px 9876px, 153px 9876px, 301.5px 9965.5px, 175px 13391px, 178px 13391px, 326.5px 13540.5px, 200px 14741px, 203px 14741px, 351.5px 14848.5px, 225px 18770px, 228px 18770px, 376.5px 18910.5px, 250px 5082px, 253px 5082px, 401.5px 5161px, 275px 6375px, 278px 6375px, 426.5px 6480px;
            }
        }

        /* --- SPACE THEME (User Provided) --- */
        #chatView.theme-space {
          background: radial-gradient(ellipse at bottom, #1b2735 0%, #090a0f 100%) !important;
          overflow: hidden;
          position: relative;
        }

        #chatView.theme-space #fullChatBody {
          background: transparent !important;
          z-index: 1;
          position: relative;
        }

        #stars, #stars2, #stars3 {
          display: none;
          position: absolute;
          top: 0;
          left: 0;
          pointer-events: none;
          z-index: 0;
        }

        .theme-space #stars, .theme-space #stars2, .theme-space #stars3 { display: block; }

        #stars {
          width: 1px;
          height: 1px;
          background: transparent;
          box-shadow:
            501px 811px #fff,
            1450px 1324px #fff,
            1093px 1780px #fff,
            1469px 678px #fff,
            904px 741px #fff,
            1160px 781px #fff,
            1841px 1962px #fff,
            1630px 1667px #fff,
            1788px 676px #fff,
            367px 1734px #fff,
            1343px 156px #fff,
            1283px 1142px #fff,
            1062px 378px #fff,
            1395px 467px #fff,
            1017px 1891px #fff,
            137px 1114px #fff,
            1767px 1403px #fff,
            1543px 11px #fff,
            1078px 181px #fff,
            1189px 1574px #fff,
            1697px 1551px #fff,
            439px 472px #fff,
            1491px 677px #fff,
            1364px 599px #fff,
            34px 382px #fff,
            1221px 1584px #fff,
            1266px 1499px #fff,
            169px 1907px #fff,
            1219px 1125px #fff,
            659px 18px #fff,
            1731px 1959px #fff,
            332px 1216px #fff,
            1913px 788px #fff,
            80px 712px #fff,
            326px 1605px #fff,
            574px 1502px #fff,
            473px 1653px #fff,
            404px 975px #fff,
            322px 1797px #fff,
            425px 1321px #fff,
            1121px 1797px #fff,
            731px 647px #fff,
            891px 1584px #fff,
            1523px 109px #fff,
            1379px 244px #fff,
            865px 1064px #fff,
            493px 956px #fff,
            624px 1380px #fff,
            440px 619px #fff,
            1630px 767px #fff,
            955px 1196px #fff,
            62px 729px #fff,
            126px 946px #fff,
            1256px 896px #fff,
            1444px 256px #fff,
            661px 1628px #fff,
            1078px 1716px #fff,
            300px 737px #fff,
            1734px 413px #fff,
            1296px 129px #fff,
            1771px 1678px #fff,
            977px 1764px #fff,
            1879px 549px #fff,
            665px 1531px #fff,
            89px 701px #fff,
            1084px 1183px #fff,
            1597px 1576px #fff,
            1354px 1774px #fff,
            554px 1471px #fff,
            1469px 287px #fff,
            887px 106px #fff,
            1962px 766px #fff,
            638px 805px #fff,
            1651px 741px #fff,
            1517px 1826px #fff,
            24px 1152px #fff,
            507px 558px #fff,
            1262px 652px #fff,
            246px 1048px #fff,
            1077px 421px #fff,
            1866px 1847px #fff,
            1986px 1561px #fff,
            704px 632px #fff,
            1991px 1875px #fff,
            1227px 395px #fff,
            45px 1116px #fff,
            247px 786px #fff,
            890px 607px #fff,
            787px 1235px #fff,
            557px 524px #fff,
            1582px 1285px #fff,
            1725px 1366px #fff,
            952px 747px #fff,
            251px 458px #fff,
            1500px 1250px #fff,
            1999px 1734px #fff,
            1336px 1955px #fff,
            1705px 1464px #fff,
            728px 697px #fff,
            594px 510px #fff,
            1345px 1990px #fff,
            1919px 1803px #fff,
            1117px 966px #fff,
            1629px 97px #fff,
            1046px 1196px #fff,
            810px 1092px #fff,
            722px 976px #fff,
            406px 18px #fff,
            1665px 1860px #fff,
            1758px 1628px #fff,
            1183px 463px #fff,
            564px 239px #fff,
            13px 1767px #fff,
            1482px 1472px #fff,
            1700px 347px #fff,
            1362px 244px #fff,
            1141px 1708px #fff,
            22px 885px #fff,
            374px 1309px #fff,
            1034px 1037px #fff,
            1725px 1086px #fff,
            1343px 1921px #fff,
            596px 903px #fff,
            1061px 478px #fff,
            18px 1409px #fff,
            729px 1364px #fff,
            264px 911px #fff,
            677px 1442px #fff,
            123px 33px #fff,
            1303px 646px #fff,
            1945px 792px #fff,
            1305px 938px #fff,
            918px 1536px #fff,
            620px 948px #fff,
            183px 646px #fff,
            695px 687px #fff,
            881px 272px #fff,
            1521px 1212px #fff,
            1423px 1022px #fff,
            1545px 1271px #fff,
            1393px 348px #fff,
            685px 1910px #fff,
            1446px 856px #fff,
            73px 1201px #fff,
            736px 999px #fff,
            673px 796px #fff,
            469px 850px #fff,
            1912px 142px #fff,
            1278px 664px #fff,
            184px 1990px #fff,
            1173px 1312px #fff,
            782px 1879px #fff,
            323px 1035px #fff,
            611px 908px #fff,
            565px 1449px #fff,
            748px 1713px #fff,
            1047px 490px #fff,
            1040px 1872px #fff,
            1818px 1659px #fff,
            1806px 1327px #fff,
            386px 575px #fff,
            1550px 463px #fff,
            148px 687px #fff,
            651px 1683px #fff,
            1588px 1194px #fff,
            1831px 2px #fff,
            581px 876px #fff,
            1396px 1743px #fff,
            1212px 1810px #fff,
            421px 1920px #fff,
            658px 1461px #fff,
            1859px 1809px #fff,
            1456px 388px #fff,
            186px 1627px #fff,
            1528px 1145px #fff,
            171px 97px #fff,
            674px 1072px #fff,
            676px 1052px #fff,
            1165px 1131px #fff,
            1088px 781px #fff,
            1231px 948px #fff,
            330px 257px #fff,
            426px 1046px #fff,
            549px 652px #fff,
            1338px 74px #fff,
            1749px 364px #fff,
            931px 369px #fff,
            383px 1428px #fff,
            1558px 389px #fff,
            927px 133px #fff,
            234px 1888px #fff,
            1785px 1617px #fff,
            556px 643px #fff,
            401px 275px #fff,
            406px 1644px #fff,
            1253px 1852px #fff,
            1599px 883px #fff,
            744px 1721px #fff,
            524px 1297px #fff,
            1226px 1177px #fff,
            1679px 55px #fff,
            874px 1811px #fff,
            838px 790px #fff,
            1241px 430px #fff,
            1676px 652px #fff,
            1191px 568px #fff,
            53px 1990px #fff,
            1163px 237px #fff,
            61px 223px #fff,
            592px 456px #fff,
            1844px 271px #fff,
            1324px 1488px #fff,
            1373px 717px #fff,
            1822px 709px #fff,
            1464px 941px #fff,
            1445px 1118px #fff,
            991px 1414px #fff,
            1964px 1076px #fff,
            108px 172px #fff,
            641px 1722px #fff,
            1539px 427px #fff,
            1697px 45px #fff,
            1301px 1353px #fff,
            1060px 329px #fff,
            967px 1396px #fff,
            493px 301px #fff,
            1228px 1406px #fff,
            1211px 1653px #fff,
            444px 1822px #fff,
            1746px 353px #fff,
            1449px 381px #fff,
            671px 887px #fff,
            650px 138px #fff,
            30px 1839px #fff,
            1094px 1405px #fff,
            273px 796px #fff,
            1618px 1964px #fff,
            1045px 1849px #fff,
            1472px 1155px #fff,
            1529px 1312px #fff,
            728px 448px #fff,
            44px 1908px #fff,
            691px 818px #fff,
            254px 293px #fff,
            1981px 1133px #fff,
            1307px 375px #fff,
            196px 316px #fff,
            1241px 1975px #fff,
            1138px 1706px #fff,
            1769px 463px #fff,
            1768px 1428px #fff,
            1730px 590px #fff,
            1780px 523px #fff,
            1862px 1526px #fff,
            1613px 909px #fff,
            1266px 1781px #fff,
            470px 352px #fff,
            699px 1682px #fff,
            1002px 614px #fff,
            1209px 133px #fff,
            1842px 518px #fff,
            1422px 1836px #fff,
            1720px 1901px #fff,
            470px 1788px #fff,
            1355px 1387px #fff,
            146px 1162px #fff,
            933px 80px #fff,
            681px 1063px #fff,
            313px 1341px #fff,
            740px 1498px #fff,
            168px 1014px #fff,
            345px 1355px #fff,
            1498px 1562px #fff,
            1626px 1358px #fff,
            890px 403px #fff,
            663px 562px #fff,
            1481px 168px #fff,
            22px 719px #fff,
            774px 1041px #fff,
            1899px 829px #fff,
            430px 158px #fff,
            430px 361px #fff,
            1592px 1334px #fff,
            224px 323px #fff,
            1639px 1131px #fff,
            7px 271px #fff,
            1646px 1514px #fff,
            1605px 1444px #fff,
            1820px 1665px #fff,
            1549px 1641px #fff,
            1609px 1377px #fff,
            486px 1098px #fff,
            229px 613px #fff,
            542px 1694px #fff,
            318px 256px #fff,
            1861px 918px #fff,
            889px 892px #fff,
            442px 1524px #fff,
            19px 422px #fff,
            1935px 1908px #fff,
            828px 109px #fff,
            862px 1248px #fff,
            1275px 560px #fff,
            906px 63px #fff,
            337px 1605px #fff,
            1691px 918px #fff,
            1414px 679px #fff,
            1726px 749px #fff,
            1540px 1149px #fff,
            1337px 1466px #fff,
            446px 430px #fff,
            676px 1616px #fff,
            840px 326px #fff,
            976px 977px #fff,
            1840px 642px #fff,
            1273px 804px #fff,
            1071px 928px #fff,
            1292px 1675px #fff,
            29px 1148px #fff,
            1585px 135px #fff,
            1007px 563px #fff,
            1035px 78px #fff,
            1174px 574px #fff,
            120px 1304px #fff,
            845px 1292px #fff,
            861px 540px #fff,
            234px 232px #fff,
            1940px 1367px #fff,
            759px 639px #fff,
            1775px 1381px #fff,
            906px 372px #fff,
            1104px 1165px #fff,
            1524px 911px #fff,
            1882px 330px #fff,
            1389px 700px #fff,
            300px 1629px #fff,
            220px 1614px #fff,
            563px 140px #fff,
            1611px 1586px #fff,
            793px 1316px #fff,
            325px 1070px #fff,
            1722px 1462px #fff,
            1406px 1120px #fff,
            1169px 1768px #fff,
            1956px 1053px #fff,
            959px 1587px #fff,
            585px 1566px #fff,
            370px 204px #fff,
            1606px 1416px #fff,
            443px 1606px #fff,
            1499px 1102px #fff,
            1943px 105px #fff,
            1121px 1594px #fff,
            1512px 32px #fff,
            871px 1425px #fff,
            433px 100px #fff,
            294px 1471px #fff,
            1688px 1755px #fff,
            1666px 591px #fff,
            1034px 300px #fff,
            734px 1178px #fff,
            1342px 313px #fff,
            1616px 1590px #fff,
            1763px 1472px #fff,
            632px 1935px #fff,
            1708px 872px #fff,
            1871px 915px #fff,
            1829px 1020px #fff,
            1599px 578px #fff,
            42px 585px #fff,
            1163px 1382px #fff,
            1744px 1272px #fff,
            984px 1426px #fff,
            1786px 1584px #fff,
            1813px 379px #fff,
            1867px 1127px #fff,
            97px 567px #fff,
            626px 988px #fff,
            1178px 79px #fff,
            1703px 211px #fff,
            961px 1785px #fff,
            110px 975px #fff,
            953px 1941px #fff,
            1027px 1790px #fff,
            1665px 107px #fff,
            11px 964px #fff,
            1718px 1147px #fff,
            21px 1728px #fff,
            1358px 1922px #fff,
            872px 65px #fff,
            1191px 1635px #fff,
            762px 681px #fff,
            1519px 1033px #fff,
            906px 566px #fff,
            1074px 657px #fff,
            1093px 415px #fff,
            51px 198px #fff,
            1075px 1418px #fff,
            1547px 1070px #fff,
            225px 920px #fff,
            850px 1974px #fff,
            981px 595px #fff,
            1425px 131px #fff,
            460px 917px #fff,
            56px 495px #fff,
            714px 428px #fff,
            920px 493px #fff,
            470px 1521px #fff,
            532px 821px #fff,
            1905px 71px #fff,
            883px 1501px #fff,
            294px 196px #fff,
            381px 1999px #fff,
            332px 793px #fff,
            1246px 408px #fff,
            233px 149px #fff,
            315px 231px #fff,
            1594px 1302px #fff,
            696px 1585px #fff,
            791px 136px #fff,
            479px 199px #fff,
            1627px 1413px #fff,
            1824px 924px #fff,
            1631px 342px #fff,
            1251px 1151px #fff,
            284px 1781px #fff,
            497px 1052px #fff,
            204px 1161px #fff,
            646px 1499px #fff,
            1762px 558px #fff,
            854px 1833px #fff,
            883px 945px #fff,
            44px 982px #fff,
            1101px 834px #fff,
            515px 1748px #fff,
            1578px 1435px #fff,
            819px 1258px #fff,
            776px 670px #fff,
            115px 385px #fff,
            1478px 434px #fff,
            885px 20px #fff,
            192px 1513px #fff,
            78px 1129px #fff,
            1774px 1105px #fff,
            955px 1149px #fff,
            1817px 1929px #fff,
            1106px 1832px #fff,
            1107px 1997px #fff,
            94px 23px #fff,
            243px 982px #fff,
            43px 1972px #fff,
            1798px 673px #fff,
            1131px 1589px #fff,
            841px 14px #fff,
            826px 345px #fff,
            687px 56px #fff,
            1084px 32px #fff,
            1887px 1878px #fff,
            153px 526px #fff,
            1828px 253px #fff,
            1947px 1105px #fff,
            886px 700px #fff,
            1307px 1723px #fff,
            1274px 651px #fff,
            1530px 837px #fff,
            1699px 1637px #fff,
            1703px 1331px #fff,
            1929px 1557px #fff,
            1763px 737px #fff,
            1118px 1680px #fff,
            1545px 692px #fff,
            1462px 1092px #fff,
            208px 1667px #fff,
            1393px 859px #fff,
            186px 1794px #fff,
            351px 1199px #fff,
            642px 1995px #fff,
            1061px 1726px #fff,
            1708px 115px #fff,
            1233px 1305px #fff,
            637px 1786px #fff,
            1730px 603px #fff,
            75px 1240px #fff,
            1704px 1326px #fff,
            584px 346px #fff,
            438px 1554px #fff,
            561px 513px #fff,
            1382px 225px #fff,
            467px 1674px #fff,
            1403px 815px #fff,
            1546px 1835px #fff,
            127px 1119px #fff,
            276px 591px #fff,
            688px 1458px #fff,
            765px 646px #fff,
            474px 984px #fff,
            171px 361px #fff,
            94px 1480px #fff,
            1962px 1666px #fff,
            909px 1037px #fff,
            1725px 222px #fff,
            253px 1355px #fff,
            1892px 1901px #fff,
            275px 1847px #fff,
            28px 1184px #fff,
            1725px 1382px #fff,
            882px 647px #fff,
            1935px 1046px #fff,
            10px 344px #fff,
            292px 1328px #fff,
            127px 1352px #fff,
            752px 929px #fff,
            1589px 384px #fff,
            284px 1829px #fff,
            381px 820px #fff,
            1229px 1125px #fff,
            777px 429px #fff,
            1811px 1499px #fff,
            1573px 287px #fff,
            295px 756px #fff,
            389px 616px #fff,
            781px 41px #fff,
            1092px 333px #fff,
            794px 1588px #fff,
            386px 1847px #fff,
            1802px 710px #fff,
            662px 60px #fff,
            640px 264px #fff,
            463px 746px #fff,
            1859px 799px #fff,
            763px 37px #fff,
            639px 396px #fff,
            357px 1071px #fff,
            1190px 1430px #fff,
            1814px 257px #fff,
            1382px 235px #fff,
            606px 1304px #fff,
            1939px 1470px #fff,
            1124px 349px #fff,
            307px 1567px #fff,
            310px 1323px #fff,
            1145px 922px #fff,
            1196px 1922px #fff,
            1647px 544px #fff,
            788px 1337px #fff,
            257px 632px #fff,
            1413px 414px #fff,
            590px 620px #fff,
            582px 794px #fff,
            1702px 1481px #fff,
            1055px 53px #fff,
            157px 346px #fff,
            50px 1901px #fff,
            1038px 1369px #fff,
            796px 1941px #fff,
            215px 194px #fff,
            1567px 1538px #fff,
            367px 800px #fff,
            1044px 489px #fff,
            1109px 1712px #fff,
            524px 327px #fff,
            525px 1252px #fff,
            1475px 1240px #fff,
            529px 436px #fff,
            795px 834px #fff,
            122px 1371px #fff,
            79px 482px #fff,
            520px 1249px #fff,
            336px 1878px #fff,
            188px 944px #fff,
            325px 1259px #fff,
            1491px 1942px #fff,
            620px 1054px #fff,
            1606px 1153px #fff,
            1448px 502px #fff,
            53px 1381px #fff,
            107px 1670px #fff,
            1380px 618px #fff,
            967px 1557px #fff,
            1116px 1722px #fff,
            1174px 1044px #fff,
            1805px 717px #fff,
            663px 394px #fff,
            1848px 1007px #fff,
            389px 802px #fff,
            49px 392px #fff,
            1650px 852px #fff,
            1678px 1012px #fff,
            335px 1009px #fff,
            1818px 1631px #fff,
            1568px 742px #fff,
            1162px 1991px #fff,
            52px 1190px #fff,
            1401px 928px #fff,
            119px 1549px #fff,
            537px 1529px #fff,
            2px 1709px #fff,
            122px 387px #fff,
            543px 2px #fff,
            27px 1971px #fff,
            507px 1377px #fff,
            1362px 1080px #fff,
            1031px 1544px #fff,
            1631px 1174px #fff,
            1603px 312px #fff,
            1626px 1422px #fff,
            1430px 615px #fff,
            1958px 1431px #fff,
            1946px 1412px #fff,
            1848px 247px #fff,
            984px 1808px #fff,
            1396px 225px #fff,
            319px 717px #fff,
            1252px 875px #fff,
            1619px 156px #fff,
            951px 1971px #fff,
            386px 355px #fff,
            1406px 1151px #fff,
            273px 1538px #fff,
            844px 1570px #fff,
            947px 151px #fff,
            1363px 525px #fff,
            209px 307px #fff,
            1923px 1718px #fff,
            993px 1741px #fff,
            1513px 353px #fff,
            1353px 61px #fff,
            664px 352px #fff,
            1382px 359px #fff,
            1487px 1707px #fff,
            657px 1045px #fff,
            1107px 490px #fff,
            1834px 1176px #fff,
            837px 1438px #fff,
            1947px 448px #fff,
            1196px 333px #fff,
            151px 555px #fff,
            18px 992px #fff,
            458px 748px #fff,
            1801px 890px #fff,
            1093px 1012px #fff,
            315px 1101px #fff,
            194px 323px #fff,
            754px 292px #fff,
            1737px 7px #fff,
            40px 840px #fff,
            1170px 805px #fff,
            176px 1753px #fff,
            805px 1148px #fff,
            1578px 1271px #fff,
            367px 1494px #fff,
            363px 1111px #fff,
            1955px 243px #fff,
            1451px 1093px #fff,
            375px 617px #fff,
            1223px 720px #fff,
            1178px 13px #fff,
            1456px 865px #fff,
            1440px 49px #fff,
            186px 1569px #fff,
            320px 1853px #fff,
            300px 539px #fff,
            1559px 509px #fff,
            1985px 1108px #fff,
            1588px 828px #fff,
            525px 1432px #fff,
            831px 363px #fff,
            141px 281px #fff,
            1319px 402px #fff,
            40px 456px #fff,
            1955px 478px #fff,
            1758px 818px #fff,
            1924px 688px #fff,
            1030px 953px #fff,
            1982px 210px #fff,
            917px 1401px #fff,
            1051px 1837px #fff,
            1045px 463px #fff,
            1744px 573px #fff,
            529px 1530px #fff,
            542px 469px #fff,
            1982px 324px #fff,
            1902px 1422px #fff,
            1968px 782px #fff,
            1666px 1561px #fff,
            955px 304px #fff,
            323px 778px #fff,
            272px 443px #fff,
            485px 581px #fff,
            1353px 1058px #fff,
            1257px 131px #fff,
            434px 98px #fff,
            1587px 1953px #fff,
            1749px 68px #fff,
            1984px 839px #fff,
            1518px 183px #fff,
            1071px 855px #fff,
            1662px 1994px #fff,
            1111px 106px #fff,
            1954px 838px #fff;
          animation: animStar 50s linear infinite;
        }
        #stars:after {
          content: " ";
          position: absolute;
          top: 2000px;
          width: 1px;
          height: 1px;
          background: transparent;
          box-shadow: inherit;
        }

        #stars2 {
          width: 2px;
          height: 2px;
          background: transparent;
          box-shadow:
            1925px 1320px #fff,
            693px 1778px #fff,
            1016px 711px #fff,
            1171px 563px #fff,
            661px 1919px #fff,
            1610px 44px #fff,
            1275px 140px #fff,
            1208px 1802px #fff,
            1473px 1587px #fff,
            11px 1117px #fff,
            853px 1757px #fff,
            1149px 937px #fff,
            1353px 428px #fff,
            270px 279px #fff,
            258px 1404px #fff,
            417px 1188px #fff,
            286px 561px #fff,
            393px 1765px #fff,
            147px 881px #fff,
            666px 1097px #fff,
            1425px 1278px #fff,
            806px 156px #fff,
            1252px 561px #fff,
            218px 52px #fff,
            1371px 1980px #fff,
            171px 745px #fff,
            1424px 89px #fff,
            137px 244px #fff,
            939px 1922px #fff,
            137px 1080px #fff,
            1757px 50px #fff,
            904px 536px #fff,
            1938px 1001px #fff,
            1172px 440px #fff,
            72px 1475px #fff,
            102px 121px #fff,
            804px 1671px #fff,
            1314px 270px #fff,
            440px 1341px #fff,
            1216px 511px #fff,
            1061px 1523px #fff,
            97px 274px #fff,
            704px 1318px #fff,
            52px 1872px #fff,
            1962px 296px #fff,
            111px 289px #fff,
            1157px 1236px #fff,
            1347px 1451px #fff,
            820px 286px #fff,
            1389px 1169px #fff,
            644px 841px #fff,
            1286px 522px #fff,
            955px 659px #fff,
            428px 1805px #fff,
            237px 557px #fff,
            1689px 1058px #fff,
            636px 1882px #fff,
            1349px 1664px #fff,
            1548px 432px #fff,
            1841px 504px #fff,
            302px 252px #fff,
            827px 1765px #fff,
            620px 123px #fff,
            207px 748px #fff,
            1454px 1234px #fff,
            1967px 1790px #fff,
            542px 33px #fff,
            742px 1214px #fff,
            255px 1402px #fff,
            74px 1772px #fff,
            699px 475px #fff,
            980px 1253px #fff,
            534px 1676px #fff,
            909px 202px #fff,
            1498px 1251px #fff,
            1796px 120px #fff,
            1409px 1263px #fff,
            1627px 995px #fff,
            969px 710px #fff,
            1674px 676px #fff,
            1832px 759px #fff,
            1623px 563px #fff,
            251px 1790px #fff,
            96px 1688px #fff,
            886px 239px #fff,
            778px 150px #fff,
            1767px 430px #fff,
            765px 1259px #fff,
            1189px 877px #fff,
            444px 1629px #fff,
            1560px 324px #fff,
            1952px 1097px #fff,
            712px 1173px #fff,
            541px 911px #fff,
            827px 1420px #fff,
            1233px 285px #fff,
            784px 546px #fff,
            645px 285px #fff,
            1273px 1255px #fff,
            1821px 174px #fff,
            221px 1795px #fff,
            1004px 456px #fff,
            1298px 941px #fff,
            274px 387px #fff,
            174px 376px #fff,
            1491px 258px #fff,
            1489px 1946px #fff,
            1134px 1382px #fff,
            1289px 1145px #fff,
            464px 358px #fff,
            1249px 1842px #fff,
            1665px 831px #fff,
            1982px 84px #fff,
            541px 774px #fff,
            1994px 523px #fff,
            762px 1644px #fff,
            1730px 867px #fff,
            1951px 1287px #fff,
            911px 1691px #fff,
            1454px 725px #fff,
            1287px 1940px #fff,
            70px 564px #fff,
            1980px 638px #fff,
            1674px 1774px #fff,
            1720px 116px #fff,
            1747px 182px #fff,
            1040px 450px #fff,
            1795px 375px #fff,
            857px 1471px #fff,
            1326px 1730px #fff,
            915px 274px #fff,
            1224px 358px #fff,
            1808px 60px #fff,
            43px 1870px #fff,
            1810px 1536px #fff,
            1564px 1719px #fff,
            731px 1388px #fff,
            1953px 1967px #fff,
            1744px 1119px #fff,
            794px 1384px #fff,
            959px 714px #fff,
            18px 1932px #fff,
            1358px 1437px #fff,
            355px 939px #fff,
            1355px 1648px #fff,
            608px 719px #fff,
            383px 758px #fff,
            1164px 1681px #fff,
            1045px 253px #fff,
            424px 1279px #fff,
            1899px 359px #fff,
            379px 488px #fff,
            214px 465px #fff,
            179px 905px #fff,
            830px 1993px #fff,
            448px 1077px #fff,
            1880px 1354px #fff,
            1973px 347px #fff,
            745px 1025px #fff,
            788px 1007px #fff,
            1377px 883px #fff,
            6px 290px #fff,
            1312px 407px #fff,
            1398px 622px #fff,
            1405px 339px #fff,
            1198px 1709px #fff,
            988px 1226px #fff,
            87px 1459px #fff,
            1113px 1698px #fff,
            997px 732px #fff,
            708px 331px #fff,
            1876px 1112px #fff,
            1729px 1797px #fff,
            719px 703px #fff,
            1295px 522px #fff,
            758px 1061px #fff,
            1309px 1014px #fff,
            1327px 1365px #fff,
            854px 1317px #fff,
            531px 1001px #fff,
            1751px 1040px #fff,
            1354px 190px #fff,
            800px 1538px #fff,
            88px 1455px #fff,
            668px 39px #fff,
            1379px 41px #fff,
            892px 524px #fff,
            54px 649px #fff,
            1289px 730px #fff,
            727px 488px #fff,
            181px 842px #fff,
            1230px 64px #fff,
            3px 857px #fff,
            292px 1201px #fff,
            1343px 673px #fff,
            1096px 1412px #fff,
            1520px 292px #fff,
            104px 1683px #fff,
            934px 1387px #fff,
            314px 739px #fff;
          animation: animStar 100s linear infinite;
        }
        #stars2:after {
          content: " ";
          position: absolute;
          top: 2000px;
          width: 2px;
          height: 2px;
          background: transparent;
          box-shadow: inherit;
        }

        #stars3 {
          width: 3px;
          height: 3px;
          background: transparent;
          box-shadow:
            200px 981px #fff,
            1731px 521px #fff,
            132px 1039px #fff,
            1888px 1547px #fff,
            899px 1226px #fff,
            1887px 580px #fff,
            1548px 1092px #fff,
            1626px 689px #fff,
            254px 1072px #fff,
            1684px 1211px #fff,
            672px 1267px #fff,
            939px 668px #fff,
            1969px 645px #fff,
            1126px 983px #fff,
            457px 568px #fff,
            476px 876px #fff,
            829px 1896px #fff,
            1364px 1846px #fff,
            1507px 1120px #fff,
            936px 1948px #fff,
            1833px 832px #fff,
            1424px 285px #fff,
            1377px 1596px #fff,
            432px 153px #fff,
            1348px 1410px #fff,
            1529px 954px #fff,
            1102px 387px #fff,
            264px 297px #fff,
            811px 977px #fff,
            1931px 673px #fff,
            1734px 978px #fff,
            1772px 1567px #fff,
            1197px 1400px #fff,
            764px 282px #fff,
            1103px 822px #fff,
            872px 1803px #fff,
            1057px 1763px #fff,
            52px 1299px #fff,
            1312px 1236px #fff,
            235px 1082px #fff,
            299px 1086px #fff,
            1017px 1602px #fff,
            1950px 626px #fff,
            1306px 132px #fff,
            1358px 1618px #fff,
            1873px 1718px #fff,
            1447px 940px #fff,
            1888px 1195px #fff,
            1704px 1765px #fff,
            872px 1357px #fff,
            1555px 1120px #fff,
            250px 1415px #fff,
            450px 415px #fff,
            492px 901px #fff,
            170px 1641px #fff,
            56px 1129px #fff,
            627px 1514px #fff,
            1221px 500px #fff,
            324px 1895px #fff,
            1397px 1775px #fff,
            1966px 598px #fff,
            1550px 763px #fff,
            326px 1605px #fff,
            261px 969px #fff,
            890px 281px #fff,
            736px 544px #fff,
            589px 1262px #fff,
            1581px 368px #fff,
            1900px 1132px #fff,
            1914px 585px #fff,
            1864px 1517px #fff,
            241px 217px #fff,
            859px 787px #fff,
            996px 1729px #fff,
            741px 121px #fff,
            418px 414px #fff,
            142px 967px #fff,
            387px 896px #fff,
            703px 562px #fff,
            968px 1136px #fff,
            1682px 332px #fff,
            1287px 846px #fff,
            256px 1427px #fff,
            1885px 432px #fff,
            1739px 1458px #fff,
            345px 1769px #fff,
            1140px 1612px #fff,
            192px 1921px #fff,
            920px 471px #fff,
            834px 881px #fff,
            917px 1803px #fff,
            466px 1266px #fff,
            483px 1108px #fff,
            689px 986px #fff,
            1279px 786px #fff,
            458px 910px #fff,
            1250px 870px #fff,
            785px 1654px #fff,
            1543px 1757px #fff,
            287px 1272px #fff;
          animation: animStar 150s linear infinite;
        }
        #stars3:after {
          content: " ";
          position: absolute;
          top: 2000px;
          width: 3px;
          height: 3px;
          background: transparent;
          box-shadow: inherit;
        }

        @keyframes animStar {
          from { transform: translateY(0px); }
          to { transform: translateY(-2000px); }
        }

        /* --- MIDNIGHT SKY THEME --- */
        #chatView.theme-midnight {
          background: linear-gradient(to bottom, #020107 0%, #050b1a 100%) !important;
          position: relative;
          overflow: hidden;
        }
        #chatView.theme-midnight #fullChatBody { background: transparent !important; z-index: 2; position: relative; }
        #midnightSkyLayer { display: none; position: absolute; inset: 0; pointer-events: none; z-index: 0; }
        .theme-midnight #midnightSkyLayer { display: block; }

        .theme-midnight .stars { position: absolute; inset: 0; background-repeat: repeat; }
        .theme-midnight .stars-1 {
          background-image: radial-gradient(1px 1px at 10% 10%, #fff, transparent),
            radial-gradient(1px 1px at 30% 20%, #fff, transparent),
            radial-gradient(1px 1px at 50% 50%, #fff, transparent),
            radial-gradient(1px 1px at 70% 30%, #fff, transparent),
            radial-gradient(1px 1px at 90% 10%, #fff, transparent);
          background-size: 200px 200px;
          animation: twinkle 3s ease-in-out infinite;
        }
        .theme-midnight .stars-2 {
          background-image: radial-gradient(1.5px 1.5px at 20% 40%, #fff, transparent),
            radial-gradient(1.5px 1.5px at 60% 85%, #fff, transparent),
            radial-gradient(1.5px 1.5px at 85% 65%, #fff, transparent);
          background-size: 300px 300px;
          animation: twinkle 5s ease-in-out infinite 1s;
        }
        .theme-midnight .stars-3 {
          background-image: radial-gradient(2px 2px at 40% 70%, #fff, transparent),
            radial-gradient(2px 2px at 10% 80%, #fff, transparent),
            radial-gradient(2px 2px at 80% 40%, #fff, transparent);
          background-size: 400px 400px;
          animation: twinkle 7s ease-in-out infinite 2s;
        }
        .theme-midnight .meteor {
          position: absolute; width: 2px; height: 2px; background: #fff; border-radius: 50%;
          box-shadow: 0 0 10px 2px rgba(255, 255, 255, 0.5); opacity: 0;
        }
        .theme-midnight .meteor::after {
          content: ""; position: absolute; top: 50%; transform: translateY(-50%);
          width: 80px; height: 1px; background: linear-gradient(90deg, #fff, transparent);
        }
        .theme-midnight .m1 { top: 10%; left: 110%; animation: shoot 8s linear infinite; }
        .theme-midnight .m2 { top: 30%; left: 110%; animation: shoot 12s linear infinite 4s; }
        .theme-midnight .m3 { top: 50%; left: 110%; animation: shoot 10s linear infinite 2s; }
        .theme-midnight .moon {
          position: absolute; top: 15%; right: 15%; width: 80px; height: 80px; border-radius: 50%;
          background: transparent; box-shadow: 15px 15px 0 0 #fdfbd3;
          filter: drop-shadow(0 0 15px rgba(253, 251, 211, 0.4)); z-index: 10;
        }
        @keyframes twinkle { 0%, 100% { opacity: 1; } 50% { opacity: 0.2; } }
        @keyframes shoot { 0% { transform: translateX(0) translateY(0) rotate(-35deg); opacity: 0; } 5% { opacity: 1; } 15% { transform: translateX(-1500px) translateY(1000px) rotate(-35deg); opacity: 0; } 100% { transform: translateX(-1500px) translateY(1000px) rotate(-35deg); opacity: 0; } }

        /* --- GEOMETRIC NIGHT THEME --- */
        #chatView.theme-geometric {
          --s: 84px;
          --c1: #1a2634; /* Dark Blue-Gray */
          --c2: #111a24; /* Deeper Night */
          --c3: #080d14; /* Obsidian */
          --_g: 0 120deg, #0000 0;
          background: conic-gradient(from 0deg at calc(500% / 6) calc(100% / 3), var(--c3) var(--_g)),
            conic-gradient(from -120deg at calc(100% / 6) calc(100% / 3), var(--c2) var(--_g)),
            conic-gradient(from 120deg at calc(100% / 3) calc(500% / 6), var(--c1) var(--_g)),
            conic-gradient(from 120deg at calc(200% / 3) calc(500% / 6), var(--c1) var(--_g)),
            conic-gradient(from -180deg at calc(100% / 3) 50%, var(--c2) 60deg, var(--c1) var(--_g)),
            conic-gradient(from 60deg at calc(200% / 3) 50%, var(--c1) 60deg, var(--c3) var(--_g)),
            conic-gradient(from -60deg at 50% calc(100% / 3), var(--c1) 120deg, var(--c2) 0 240deg, var(--c3) 0) !important;
          background-size: calc(var(--s) * 1.732) var(--s) !important;
          animation: moveGeo 40s linear infinite;
        }
        #chatView.theme-geometric #fullChatBody { background: transparent !important; }
        @keyframes moveGeo { from { background-position: 0 0; } to { background-position: calc(var(--s) * 1.732) var(--s); } }

        /* Theme Preview Visuals */
        .theme-preview-card { cursor: pointer; padding: 12px 20px; transition: 0.3s; }
        .theme-preview-img { 
            width: 100%; height: 90px; border-radius: 12px; object-fit: cover; 
            border: 2px solid rgba(0, 255, 170, 0.1); transition: 0.3s; margin-bottom: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .theme-preview-card:hover .theme-preview-img { border-color: var(--neon-green); box-shadow: 0 0 20px rgba(0,255,170,0.3); transform: scale(1.02); }
        .theme-preview-card:active .theme-preview-img { transform: scale(0.98); }
        .theme-preview-label { display: block; text-align: center; font-size: 11px; color: #aaa; font-family: var(--terminal-font); text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }

        @keyframes gridFlow { from { background-position: 0 0; } to { background-position: 50px 50px; } }
        .chat-footer {
            padding: 15px 35px;
            flex-shrink: 0;
            background: rgba(5, 15, 10, 0.9);
            backdrop-filter: blur(10px);
            border-top: 1px solid var(--border-glow);
            display: flex; align-items: center; gap: 15px;
        }
        .sidebar-search { padding: 15px; }
        .sidebar-search input {
            width: 100%; padding: 12px 18px; border-radius: 8px;
            background: rgba(0, 0, 0, 0.4); border: 1px solid var(--border-glow);
            color: white; outline: none; transition: var(--transition-main);
            font-family: var(--terminal-font);
        }
        .sidebar-search input:focus { border-color: var(--neon-green); box-shadow: 0 0 20px rgba(0,255,170,0.2); }
        
        .conv-list { flex: 1; overflow-y: auto; padding: 5px; }
        .conv-item {
            display: flex; align-items: center; gap: 15px;
            padding: 12px 15px; cursor: pointer; transition: var(--transition-main);
            border: 1px solid transparent;
            border-radius: 12px; margin: 5px 10px;
            animation: slideInLeft 0.4s ease-out backwards;
        }
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .conv-item:hover { 
            background: rgba(0, 255, 170, 0.05); 
            border-color: rgba(0, 255, 170, 0.2);
            transform: scale(1.02);
        }
        .conv-item.active { 
            background: rgba(0, 255, 170, 0.1); 
            border: 1px solid var(--neon-green);
            box-shadow: 0 0 20px rgba(0, 255, 170, 0.15);
        }
        .conv-item img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid var(--neon-green); transition: 0.3s; }
        .conv-item:hover img { box-shadow: 0 0 15px var(--neon-green); }

        /* --- ACTIVE NOW BAR --- */
        .active-now-bar {
            padding: 10px 15px;
            display: flex;
            gap: 15px;
            overflow-x: auto;
            scrollbar-width: none;
            border-bottom: 1px solid var(--border-glow);
            background: rgba(0, 255, 170, 0.02);
        }
        .active-now-bar::-webkit-scrollbar { display: none; }
        .active-user-node {
            display: flex; flex-direction: column; align-items: center; gap: 5px;
            min-width: 60px; cursor: pointer; transition: 0.3s;
        }
        .active-user-node:hover { transform: scale(1.1); }
        .active-user-node img {
            width: 45px; height: 45px; border-radius: 50%;
            border: 2px solid var(--neon-green); padding: 2px;
            box-shadow: 0 0 10px rgba(0, 255, 170, 0.3);
        }
        .active-user-node span {
            font-size: 10px; color: #aaa; white-space: nowrap;
            overflow: hidden; text-overflow: ellipsis; max-width: 60px;
            font-family: var(--terminal-font);
        }

        /* --- SIDEBAR FILTER PILLS --- */
        .sidebar-filters { display: flex; padding: 10px 15px; gap: 10px; border-bottom: 1px solid rgba(255,255,255,0.03); }
        .filter-pill {
            padding: 6px 14px; border-radius: 20px; background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1); color: #888; font-size: 10px;
            font-weight: bold; cursor: pointer; transition: 0.3s; text-transform: uppercase;
            font-family: var(--terminal-font);
        }
        .filter-pill.active { background: rgba(0, 255, 170, 0.15); border-color: var(--neon-green); color: var(--neon-green); box-shadow: 0 0 10px rgba(0, 255, 170, 0.2); }

        /* --- Presence Indicator for Sidebar --- */
        .conv-avatar-box { position: relative; width: 48px; height: 48px; flex-shrink: 0; }
        .conv-status-dot { 
            position: absolute; bottom: 2px; right: 2px; width: 12px; height: 12px; 
            border-radius: 50%; border: 2px solid #0a1f16; z-index: 2;
        }
        .conv-status-dot.online { background: #00ffaa; box-shadow: 0 0 10px #00ffaa; }
        .conv-status-dot.offline { background: #555; }

        .conv-info { flex: 1; }
        .conv-info strong { display: block; color: #fff; font-size: 15px; letter-spacing: 0.5px; }
        .conv-info small { color: #aaa; font-size: 12px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 180px; }
        
        /* --- ENHANCED MESSAGE BUBBLES --- */
        .msg-container { max-width: 75% !important; margin-bottom: 12px !important; display: flex !important; gap: 10px; width: fit-content; }
        .msg-container.mine { align-self: flex-end !important; flex-direction: row-reverse; }
        .msg-container.other { align-self: flex-start !important; flex-direction: row; }
        
        .msg { 
            padding: 12px 18px !important; border-radius: 20px !important; font-size: 15px !important; 
            animation: popIn 0.4s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            position: relative;
            line-height: 1.5;
            word-break: break-word;
            overflow-wrap: break-word;
            white-space: pre-wrap;
        }
        @keyframes popIn {
            from { opacity: 0; transform: translateY(20px) scale(0.9); filter: blur(10px); }
            to { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); }
        }
        .my-msg { 
            background: linear-gradient(135deg, var(--neon-green), #00cc88) !important; 
            color: #0a1f16 !important; border-bottom-right-radius: 4px !important; 
            font-weight: 500; box-shadow: 0 4px 15px rgba(0,255,170,0.3); 
        }
        .other-msg { 
            background: rgba(255, 255, 255, 0.08) !important; 
            color: #fff !important; border-bottom-left-radius: 2px !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            backdrop-filter: blur(5px);
        }
        
        .msg-avatar { width: 42px !important; height: 42px !important; border-radius: 50% !important; object-fit: cover !important; border: 2px solid var(--neon-green) !important; box-shadow: 0 0 10px rgba(0,255,170,0.2); }
        
        /* Message Options Menu */
        .msg-options-wrapper { position: relative; display: flex; align-items: center; }
        .msg-dots-btn {
            background: none; border: none; color: #aaa; cursor: pointer; padding: 5px;
            font-size: 16px; opacity: 0; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }
        .msg-container:hover .msg-dots-btn { opacity: 1; }
        .msg-dots-btn:hover { background: rgba(255,255,255,0.1); color: #fff; transform: scale(1.2) rotate(90deg); }
        
        .msg-menu {
            display: none; position: absolute; bottom: 100%; right: 0;
            background: #102e22; border: 1px solid var(--neon-green); border-radius: 8px;
            padding: 5px; z-index: 100; box-shadow: 0 5px 15px rgba(0,0,0,0.5); min-width: 90px;
        }
        .msg-menu.active { display: block; }
        .msg-menu div { padding: 8px 12px; color: #fff; font-size: 13px; cursor: pointer; border-radius: 5px; text-align: left; transition: 0.2s; }
        .msg-menu div:hover { background: rgba(0,255,170,0.15); color: #00ffaa; }

        /* Conversation Options Menu */
        .conv-options-wrapper { position: relative; display: flex; align-items: center; }
        .conv-dots-btn {
            background: none; border: none; color: #aaa; cursor: pointer; padding: 0 5px;
            font-size: 18px; opacity: 1; /* Always visible */ transition: all 0.3s; border-radius: 50%; color: var(--neon-green); /* Make dots clearly visible */
            display: flex; align-items: center; justify-content: center;
        }
        .conv-dots-btn:hover { background: rgba(0,255,170,0.1); color: #fff; } /* Hover effect */
        .conv-menu {
            display: none; position: absolute; top: 100%; right: 0; /* Appear below the button, aligned right */
            transform: translateY(5px); /* Small offset to separate from button */
            background: #102e22; border: 1px solid var(--neon-green); border-radius: 8px;
            padding: 5px; z-index: 10005; /* Increased z-index to ensure visibility */ box-shadow: 0 5px 15px rgba(0,0,0,0.5); min-width: 150px;
        }
        .conv-menu.active { display: block; }
        .conv-menu div { padding: 8px 12px; color: #fff; font-size: 13px; cursor: pointer; border-radius: 5px; text-align: left; transition: 0.2s; }
        .conv-menu div:hover { background: rgba(255, 71, 87, 0.15); color: #ff4757; }

        /* --- Messenger-style Profile Sidebar --- */
        .profile-sidebar {
            position: absolute; top: 0; right: -100%; width: 340px; height: 100%;
            background: var(--glass-bg); backdrop-filter: blur(20px); z-index: 2000;
            transition: right 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            display: flex; flex-direction: column;
            box-shadow: -10px 0 40px rgba(0,0,0,0.8);
            overflow-y: auto; scrollbar-width: none;
        }
        .profile-sidebar::-webkit-scrollbar { display: none; }
        .profile-sidebar.open { right: 0; }

        .ps-header { padding: 50px 20px 30px; text-align: center; position: relative; }
        .ps-close-btn { 
            position: absolute; top: 15px; left: 15px; background: rgba(255,255,255,0.05); 
            border: none; color: #fff; width: 32px; height: 32px; border-radius: 50%;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: 0.3s; z-index: 10;
        }
        .ps-close-btn:hover { background: rgba(255,255,255,0.15); }

        .ps-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 18px; border: 2px solid rgba(255,255,255,0.1); }
        .ps-name { font-size: 24px; font-weight: 700; color: #fff; margin: 0; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .ps-status { font-size: 13px; color: #888; margin-top: 6px; }
        
        .ps-encryption-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,0.04); padding: 7px 16px;
            border-radius: 30px; font-size: 11px; color: #aaa;
            margin-top: 22px; border: 1px solid rgba(255,255,255,0.05);
        }

        .ps-actions { display: flex; justify-content: center; gap: 35px; margin-top: 35px; }
        .ps-action-item { display: flex; flex-direction: column; align-items: center; gap: 10px; cursor: pointer; transition: 0.2s; }
        .ps-action-item:hover { transform: translateY(-2px); }
        .ps-action-btn { width: 40px; height: 40px; border-radius: 50%; background: #222; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .ps-action-label { font-size: 11px; color: #fff; font-weight: 500; }

        .ps-section { border-top: 1px solid rgba(255,255,255,0.05); }
        .ps-section-title { 
            padding: 18px 25px; font-size: 14px; font-weight: 700; color: #fff;
            display: flex; justify-content: space-between; align-items: center;
            cursor: pointer; transition: 0.2s;
        }
        .ps-section-title:hover { background: rgba(255,255,255,0.03); }
        .ps-section-title svg { transition: transform 0.3s; opacity: 0.5; }
        .ps-section.active .ps-section-title svg { transform: rotate(180deg); }

        .ps-section-content { display: none; padding: 0 0 10px; }
        .ps-section.active .ps-section-content { display: block; }

        .ps-option-item { 
            padding: 12px 25px; display: flex; align-items: center; gap: 15px; 
            color: #eee; font-size: 14px; cursor: pointer; transition: 0.2s;
        }
        .ps-option-item:hover { background: rgba(255,255,255,0.05); }
        .ps-option-icon { font-size: 18px; opacity: 0.8; width: 24px; text-align: center; }

        .ps-media-grid { display: none; grid-template-columns: repeat(3, 1fr); gap: 4px; padding: 10px 20px; }
        @keyframes psItemEntry {
            from { opacity: 0; transform: scale(0.8); filter: brightness(2); }
            to { opacity: 1; transform: scale(1); filter: brightness(1); }
        }
        .ps-media-item { 
            aspect-ratio: 1; border-radius: 4px; overflow: hidden; cursor: pointer;
            border: 1px solid rgba(255,255,255,0.1); background: #111; position: relative;
            animation: psItemEntry 0.4s cubic-bezier(0.22, 1, 0.36, 1) backwards;
        }
        .ps-media-item img, .ps-media-item video { width: 100%; height: 100%; object-fit: cover; }
        .ps-media-overlay { 
            position: absolute; inset: 0; 
            background: radial-gradient(circle at center, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.9) 100%),
                        linear-gradient(rgba(0, 255, 170, 0.05) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(0, 255, 170, 0.05) 1px, transparent 1px),
                        /* Mini Tactical Corners */
                        linear-gradient(to right, var(--neon-green) 1px, transparent 1px) 0 0,
                        linear-gradient(to bottom, var(--neon-green) 1px, transparent 1px) 0 0,
                        linear-gradient(to left, var(--neon-green) 1px, transparent 1px) 100% 0,
                        linear-gradient(to bottom, var(--neon-green) 1px, transparent 1px) 100% 0,
                        linear-gradient(to right, var(--neon-green) 1px, transparent 1px) 0 100%,
                        linear-gradient(to top, var(--neon-green) 1px, transparent 1px) 0 100%,
                        linear-gradient(to left, var(--neon-green) 1px, transparent 1px) 100% 100%,
                        linear-gradient(to top, var(--neon-green) 1px, transparent 1px) 100% 100%;
            background-repeat: no-repeat, repeat, repeat, no-repeat, no-repeat, no-repeat, no-repeat, no-repeat, no-repeat, no-repeat, no-repeat;
            background-size: 100% 100%, 15px 15px, 15px 15px, 8px 8px, 8px 8px, 8px 8px, 8px 8px, 8px 8px, 8px 8px, 8px 8px, 8px 8px;
            background-position: center, center, center, 5px 5px, 5px 5px, calc(100% - 5px) 5px, calc(100% - 5px) 5px, 5px calc(100% - 5px), 5px calc(100% - 5px), calc(100% - 5px) calc(100% - 5px), calc(100% - 5px) calc(100% - 5px);
            display: flex; flex-direction: column; align-items: center; justify-content: center; 
            opacity: 0; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
            pointer-events: none;
            border-radius: 4px;
            border: 2px solid transparent;
            gap: 10px;
            overflow: hidden;
        }
        .ps-media-item:hover .ps-media-overlay { 
            opacity: 1; border-color: var(--neon-green); backdrop-filter: blur(4px);
            box-shadow: inset 0 0 20px rgba(0,255,170,0.2);
        }
        .ps-media-overlay::after {
            content: ""; position: absolute; top: -100%; left: 0; width: 100%; height: 2px;
            background: rgba(0, 255, 170, 0.4); box-shadow: 0 0 10px var(--neon-green);
            opacity: 0;
        }
        .ps-media-item:hover .ps-media-overlay::after { opacity: 0.3; animation: scanMove 3s linear infinite; }
        .ps-media-date { color: #00ffaa; font-size: 9px; font-family: var(--terminal-font); font-weight: bold; text-shadow: 0 0 5px rgba(0,0,0,0.9); text-align: center; }

        /* Themed Play Icon for sidebar media */
        .ps-media-item video + .ps-media-overlay::before {
            content: '▶'; font-size: 24px; color: #fff;
            filter: drop-shadow(0 0 10px var(--neon-green));
            transform: scale(0.8); transition: 0.3s;
            margin-bottom: 5px;
        }
        .ps-media-item:hover video + .ps-media-overlay::before { transform: scale(1.2); }

        /* --- Message Media Grid Styles --- */
        .message-media-grid {
            display: grid;
            gap: 4px;
            margin-top: 8px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .message-media-grid.grid-1 { grid-template-columns: 1fr; }
        .message-media-grid.grid-2 { grid-template-columns: 1fr 1fr; }
        .message-media-grid.grid-3 { grid-template-columns: 1fr 1fr 1fr; }
        .message-media-grid.grid-4 { grid-template-columns: 1fr 1fr; }
        .message-media-grid.grid-4 .message-media-item { aspect-ratio: 1; }
        .message-media-grid.grid-5, .message-media-grid.grid-6 { grid-template-columns: 1fr 1fr 1fr; }
        .message-media-grid.grid-5 .message-media-item:nth-child(1), .message-media-grid.grid-5 .message-media-item:nth-child(2) { grid-column: span 2; }

        .message-media-item {
            position: relative;
            aspect-ratio: 16/9; /* Default aspect ratio */
            overflow: hidden;
            background: #000;
            cursor: pointer;
        }
        .message-media-item img, .message-media-item video { width: 100%; height: 100%; object-fit: cover; }
        .message-media-item .media-play-icon { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 30px; color: white; text-shadow: 0 0 10px rgba(0,0,0,0.8); }
        .message-media-item .file-attachment-preview { display: flex; align-items: center; justify-content: center; height: 100%; background: rgba(0,255,170,0.1); color: #00ffaa; font-family: var(--terminal-font); font-size: 12px; padding: 10px; text-align: center; }
        .message-media-item .media-hover-overlay {
             position: absolute; inset: 0;
             background: radial-gradient(circle at center, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.95) 100%),
                         linear-gradient(rgba(0, 255, 170, 0.05) 1px, transparent 1px),
                         linear-gradient(90deg, rgba(0, 255, 170, 0.05) 1px, transparent 1px),
                         /* Tactical Corners */
                         linear-gradient(to right, var(--neon-green) 2px, transparent 2px) 0 0,
                         linear-gradient(to bottom, var(--neon-green) 2px, transparent 2px) 0 0,
                         linear-gradient(to left, var(--neon-green) 2px, transparent 2px) 100% 0,
                         linear-gradient(to bottom, var(--neon-green) 2px, transparent 2px) 100% 0,
                         linear-gradient(to right, var(--neon-green) 2px, transparent 2px) 0 100%,
                         linear-gradient(to top, var(--neon-green) 2px, transparent 2px) 0 100%,
                         linear-gradient(to left, var(--neon-green) 2px, transparent 2px) 100% 100%,
                         linear-gradient(to top, var(--neon-green) 2px, transparent 2px) 100% 100%;
             background-repeat: no-repeat, repeat, repeat, no-repeat, no-repeat, no-repeat, no-repeat, no-repeat, no-repeat, no-repeat, no-repeat;
             background-size: 100% 100%, 20px 20px, 20px 20px, 15px 15px, 15px 15px, 15px 15px, 15px 15px, 15px 15px, 15px 15px, 15px 15px, 15px 15px;
             background-position: center, center, center, 10px 10px, 10px 10px, calc(100% - 10px) 10px, calc(100% - 10px) 10px, 10px calc(100% - 10px), 10px calc(100% - 10px), calc(100% - 10px) calc(100% - 10px), calc(100% - 10px) calc(100% - 10px);
             display: flex; flex-direction: column; align-items: center; justify-content: center;
             opacity: 0; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
             gap: 8px; border: 1px solid transparent;
             overflow: hidden;
         }
         .message-media-item:hover .media-hover-overlay { 
             opacity: 1; border-color: rgba(0, 255, 170, 0.4); backdrop-filter: blur(8px);
             box-shadow: inset 0 0 30px rgba(0,255,170,0.3);
         }
         /* Enhanced Scanline */
         .message-media-item .media-hover-overlay::after {
             content: "SCANNING_NODE..."; position: absolute; top: -100%; left: 0; width: 100%; height: 20px;
             background: linear-gradient(180deg, var(--neon-green), transparent);
             color: #0a1f16; font-size: 8px; font-family: var(--terminal-font); font-weight: 900;
             display: flex; align-items: center; justify-content: center;
             opacity: 0; pointer-events: none;
         }
         .message-media-item:hover .media-hover-overlay::after { opacity: 0.4; animation: scanMove 2.5s linear infinite; }
         @keyframes scanMove { 0% { top: -10%; } 100% { top: 110%; } }
         
         /* Modern Preview Icon for Images in Overlay */
         .message-media-item img + .media-hover-overlay::before {
             content: 'DATA_FILE_IMG'; 
             width: 80px; height: 80px;
             padding-top: 55px;
             font-family: var(--terminal-font); font-size: 10px; color: var(--neon-green); font-weight: 900;
             letter-spacing: 1px; text-align: center;
             background: rgba(0, 255, 170, 0.1);
             border: 2px solid var(--neon-green);
             border-radius: 15px;
             display: flex; align-items: center; justify-content: center;
             background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2300ffaa' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z'/%3E%3Cpolyline points='13 2 13 9 20 9'/%3E%3C/svg%3E");
             background-repeat: no-repeat; background-position: center 15px; background-size: 30px;
             box-shadow: 0 0 15px rgba(0, 255, 170, 0.3);
             transform: scale(0.8); transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
         }
         .message-media-item:hover img + .media-hover-overlay::before { transform: scale(1.1); background-color: rgba(0, 255, 170, 0.2); }

         /* Modern Play Button for the Overlay */
         .message-media-item video + .media-hover-overlay::before {
             content: 'STREAM_NODE';
             width: 90px; height: 90px;
             padding-top: 65px;
             font-family: var(--terminal-font); font-size: 10px; color: var(--neon-green); font-weight: 900;
             letter-spacing: 1px; text-align: center;
             background: rgba(0, 255, 170, 0.2);
             border: 2px solid var(--neon-green);
             border-radius: 50%;
             display: flex; align-items: center; justify-content: center;
             background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2300ffaa'%3E%3Cpath d='M8 5v14l11-7z'/%3E%3C/svg%3E");
             background-repeat: no-repeat; background-position: 55% 20px; background-size: 35px;
             box-shadow: 0 0 20px rgba(0, 255, 170, 0.4);
             transform: scale(0.8); transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
         }
         .message-media-item:hover video + .media-hover-overlay::before { transform: scale(1.1); background-color: var(--neon-green); background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%230a1f16'%3E%3Cpath d='M8 5v14l11-7z'/%3E%3C/svg%3E"); }
 
         .message-media-item .media-date { color: #00ffaa; font-size: 10px; font-family: var(--terminal-font); font-weight: bold; }
 
         /* Seasonal Overrides for Media Hover Overlays */
         body.theme-halloween .ps-media-overlay, body.theme-halloween .media-hover-overlay { border-color: #ff5500 !important; }
         body.theme-halloween .media-hover-overlay::before { background-color: rgba(255, 85, 0, 0.2) !important; border-color: #ff5500 !important; box-shadow: 0 0 20px #ff5500; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ff5500'%3E%3Cpath d='M8 5v14l11-7z'/%3E%3C/svg%3E"); }
         body.theme-halloween .ps-media-date, body.theme-halloween .media-date { color: #ff5500 !important; }
 
         body.theme-christmas .ps-media-overlay, body.theme-christmas .media-hover-overlay { border-color: #fff !important; }
         body.theme-christmas .media-hover-overlay::before { background-color: rgba(255, 255, 255, 0.2) !important; border-color: #fff !important; box-shadow: 0 0 20px #fff; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23fff'%3E%3Cpath d='M8 5v14l11-7z'/%3E%3C/svg%3E"); }
         body.theme-christmas .ps-media-date, body.theme-christmas .media-date { color: #fff !important; }
 
         body.theme-summer .ps-media-overlay, body.theme-summer .media-hover-overlay { border-color: #ffcc00 !important; }
         body.theme-summer .media-hover-overlay::before { background-color: rgba(255, 204, 0, 0.2) !important; border-color: #ffcc00 !important; box-shadow: 0 0 20px #ffcc00; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ffcc00'%3E%3Cpath d='M8 5v14l11-7z'/%3E%3C/svg%3E"); }
         body.theme-summer .ps-media-date, body.theme-summer .media-date { color: #ffcc00 !important; }
 
         body.theme-new_year .ps-media-overlay, body.theme-new_year .media-hover-overlay { border-color: #ffd700 !important; }
         body.theme-new_year .media-hover-overlay::before { background-color: rgba(255, 215, 0, 0.2) !important; border-color: #ffd700 !important; box-shadow: 0 0 20px #ffd700; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ffd700'%3E%3Cpath d='M8 5v14l11-7z'/%3E%3C/svg%3E"); }
         body.theme-new_year .ps-media-date, body.theme-new_year .media-date { color: #ffd700 !important; }

        /* --- Messenger-style Lightbox --- */
        .lightbox-modal { display: none; position: fixed; z-index: 10000; inset: 0; background: rgba(0,0,0,0.95); backdrop-filter: blur(10px); }
        .lightbox-content { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; position: relative; }
        .lightbox-media { 
            max-width: 90%; max-height: 90vh; border-radius: 10px; 
            box-shadow: 0 0 50px rgba(0,255,170,0.3); border: 1px solid var(--border-glow); 
            object-fit: contain;
            background: #000;
        }
        .lightbox-close { position: absolute; top: 20px; right: 30px; color: #fff; font-size: 40px; cursor: pointer; transition: 0.3s; z-index: 10001; }
        .lightbox-close:hover { color: #ff4757; transform: rotate(90deg); }
        .lightbox-media:focus { outline: none; }
        .lightbox-nav { 
            position: absolute; top: 50%; transform: translateY(-50%); 
            background: rgba(255,255,255,0.1); color: white; border: none; 
            padding: 20px 15px; font-size: 24px; cursor: pointer; 
            border-radius: 10px; transition: 0.3s; z-index: 10001; 
            backdrop-filter: blur(5px);
        }
        .lightbox-nav:hover { background: var(--neon-green); color: #0a1f16; box-shadow: 0 0 20px var(--neon-green); }
        .lightbox-prev { left: 20px; }
        .lightbox-next { right: 20px; }
        .lightbox-counter { position: absolute; bottom: 30px; color: #aaa; font-family: var(--terminal-font); font-size: 11px; width: 100%; text-align: center; letter-spacing: 2px; }

        /* --- MOBILE LIGHTBOX OPTIMIZATION --- */
        @media (max-width: 768px) {
            .lightbox-media { max-width: 100%; max-height: 100vh; border-radius: 0; border: none; }
            .lightbox-nav { 
                top: auto; bottom: 30px; transform: none; 
                padding: 15px 30px; font-size: 20px; 
                background: rgba(0, 255, 170, 0.15);
                border: 1px solid var(--neon-green);
                border-radius: 50px;
            }
            .lightbox-prev { left: 20px; }
            .lightbox-next { right: 20px; }
            .lightbox-close { top: 15px; right: 20px; font-size: 35px; background: rgba(0,0,0,0.5); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
            .lightbox-counter { bottom: 90px; font-size: 10px; font-weight: bold; color: #fff; text-shadow: 0 0 10px #000; }
        }

        .ps-files-list { display: none; flex-direction: column; gap: 8px; padding: 10px 20px; }
        .ps-file-link { display: flex; align-items: center; gap: 12px; color: #fff; text-decoration: none; font-size: 12px; background: rgba(255,255,255,0.03); padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); transition: 0.2s; }
        .ps-file-link:hover { background: rgba(255,255,255,0.1); border-color: var(--neon-green); }

        /* Full Chat Attachments & Footer Styling */
        .chat-footer label { cursor: pointer; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; transition: 0.3s; }
        .chat-footer label:hover { background: rgba(0, 255, 170, 0.1); }
        .chat-footer label img { width: 24px; height: 24px; }
        
        #fullChatFilePreviewArea { 
            display:none; 
            padding:15px; 
            background: rgba(0, 10, 5, 0.9); 
            border-top: 1px solid var(--border-glow); 
            position: relative;
            flex-wrap: wrap;
            gap: 12px;
            max-height: 180px;
            overflow-y: auto;
        }
        .chat-preview-item {
            position: relative;
            width: 75px;
            height: 75px;
            border-radius: 8px;
            overflow: hidden;
            border: 1.5px solid var(--neon-green);
            background: #000;
            animation: popIn 0.3s ease-out;
        }
        .chat-preview-item img, .chat-preview-item video { width: 100%; height: 100%; object-fit: cover; }
        .chat-preview-item .remove-preview {
            position: absolute; top: 2px; right: 2px;
            background: rgba(255, 71, 87, 0.9); color: white; border: none; border-radius: 50%;
            width: 18px; height: 18px; cursor: pointer; font-size: 12px;
            display: flex; align-items: center; justify-content: center; z-index: 10;
        }
        .chat-preview-item .file-name-tag { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.8); color: #00ffaa; font-size: 8px; padding: 2px 4px; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: var(--terminal-font); }
        
        /* Media inside Chat Bubbles */
        .msg img, .msg video {
            max-width: 100%;
            max-height: 250px;
            border-radius: 12px;
            display: block;
            margin-top: 8px;
            cursor: pointer;
            transition: 0.3s;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .msg img:hover, .msg video:hover { filter: brightness(1.1); transform: scale(1.02); }

        /* --- ENHANCED FILE ATTACHMENT ANIMATION --- */
        @keyframes fileNodeEntry {
            from { opacity: 0; transform: translateY(15px) scale(0.9); filter: blur(5px); }
            to { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); }
        }
        @keyframes fileIconPulse {
            0%, 100% { transform: scale(1); filter: drop-shadow(0 0 2px var(--neon-green)); }
            50% { transform: scale(1.2); filter: drop-shadow(0 0 8px var(--neon-green)); }
        }

        .file-attachment {
            background: rgba(0, 255, 170, 0.05) !important; 
            border: 1.5px solid rgba(0, 255, 170, 0.2) !important; 
            padding: 14px 20px !important;
            border-radius: 14px !important; 
            margin-top: 12px !important; 
            cursor: pointer; 
            display: flex !important; 
            align-items: center !important; 
            gap: 15px !important;
            font-size: 14px !important; 
            color: #fff !important; 
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
            animation: fileNodeEntry 0.6s cubic-bezier(0.22, 1, 0.36, 1) backwards;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
            position: relative;
            overflow: hidden;
            font-family: var(--terminal-font);
        }
        .file-attachment:hover { 
            background: rgba(0, 255, 170, 0.12) !important; 
            border-color: var(--neon-green) !important;
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 10px 30px rgba(0, 255, 170, 0.25) !important;
        }
        .file-attachment span:first-child { 
            font-size: 22px; 
            animation: fileIconPulse 2.5s infinite ease-in-out;
            display: inline-block;
        }
        /* Holographic Scanline Effect on Hover */
        .file-attachment::before {
            content: '';
            position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
            transition: 0.6s;
            pointer-events: none;
        }
        .file-attachment:hover::before { left: 100%; }

        /* Messenger-style Typing Animation */
        .typing-indicator-dots {
            display: flex; align-items: center; gap: 8px; padding: 10px 18px;
            background: rgba(255, 255, 255, 0.08); border-radius: 20px;
            border-bottom-left-radius: 4px; width: fit-content;
            margin: 15px 25px; border: 1px solid rgba(0, 255, 170, 0.2);
            margin: 10px 25px 20px; border: 1px solid rgba(0, 255, 170, 0.2);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            animation: indicatorReveal 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }
        @keyframes indicatorReveal { from { opacity: 0; transform: translateY(15px); scale: 0.95; } to { opacity: 1; transform: translateY(0); scale: 1; } }
        .typing-label { font-size: 11px; color: var(--neon-green); font-family: var(--terminal-font); margin-right: 5px; }
        .typing-indicator-dots div span {
            width: 6px; height: 6px; background: var(--neon-green);
            border-radius: 50%; animation: typingPulse 0.8s infinite ease-in-out; opacity: 0.5;
            display: inline-block;
        }
        .typing-indicator-dots div span:nth-child(2) { animation-delay: 0.12s; }
        .typing-indicator-dots div span:nth-child(3) { animation-delay: 0.24s; }
        @keyframes typingPulse { 0%, 100% { transform: translateY(0); opacity: 0.4; } 50% { transform: translateY(-5px); opacity: 1; } }

        @media (max-width: 480px) {
            .profile-sidebar { width: 100%; }
        }

        /* Flash Message Style */
        .flash-message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            background: rgba(0, 255, 170, 0.9);
            color: #05100c;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 800;
            z-index: 10000;
            box-shadow: 0 0 20px rgba(0, 255, 170, 0.4);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            pointer-events: none;
            border: 1px solid #fff;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-family: var(--terminal-font);
        }
        .flash-message.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .flash-message.error { background: rgba(255, 71, 87, 0.9); color: white; border-color: #ff4757; }

        /* --- New Message Highlight Animation --- */
        @keyframes newMsgFlash {
            0% { background: rgba(0, 255, 170, 0.5); border-left-color: #fff; } /* Mas maliwanag sa simula */
            50% { background: rgba(0, 255, 170, 0.2); border-left-color: #00ffaa; }
            100% { background: rgba(0, 255, 170, 0.1); border-left-color: #00ffaa; }
        }
        .conv-item.new-message-highlight { animation: newMsgFlash 2s ease-out forwards; }
        /* --- SEASONAL THEME OVERRIDES --- */
        /* Halloween Theme */
        body.theme-halloween { background: #050202 !important; }
        body.theme-halloween::before { background: linear-gradient(to bottom, rgba(0,0,0,0.6), rgba(20,0,0,0.9)), radial-gradient(circle at 50% 50%, rgba(40,0,0,0.2), transparent 80%) !important; }
        body.theme-halloween::after { background: radial-gradient(circle at 50% 0%, rgba(255, 0, 0, 0.1), transparent 70%); animation: lightningFlash 10s infinite; opacity: 0.6; mix-blend-mode: overlay; }
        @keyframes lightningFlash { 0%, 85% { opacity: 0.3; background-color: transparent; } 86% { opacity: 0.8; background-color: rgba(255, 0, 0, 0.15); } 93% { opacity: 1; background-color: rgba(255, 50, 50, 0.2); } 100% { opacity: 0.3; } }
        body.theme-halloween .messenger-header, body.theme-halloween .chat-sidebar, body.theme-halloween .chat-header, body.theme-halloween .profile-sidebar { border-color: #800000 !important; background: rgba(10, 5, 5, 0.95) !important; }
        body.theme-halloween .chat-main { border-color: #800000 !important; }
        body.theme-halloween .my-msg { background: linear-gradient(135deg, #800000, #ff0000) !important; color: #fff !important; }
        body.theme-halloween .conv-item.active { border-color: #ff5555 !important; background: rgba(255, 85, 85, 0.1) !important; }

        /* Christmas Theme */
        body.theme-christmas { 
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #87ceeb 100%) !important; 
        }
        body.theme-christmas::before { 
            content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: 
                radial-gradient(circle, #fff 1.2px, transparent 1.2px),
                radial-gradient(circle, rgba(255,255,255,0.7) 2px, transparent 2px),
                radial-gradient(circle, #fff 1.5px, transparent 1.5px);
            background-size: 50px 50px, 120px 120px, 80px 80px;
            animation: snow-fall 25s linear infinite;
            z-index: 101; pointer-events: none; opacity: 0.8;
        }
        body.theme-christmas::after { 
            content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle at 50% 0%, rgba(255, 255, 255, 0.3) 0%, transparent 80%);
            pointer-events: none; z-index: 102; mix-blend-mode: overlay;
        }
        @keyframes snow-fall {
            0% { background-position: 0 0, 0 0, 0 0; }
            100% { background-position: 300px 800px, 150px 400px, 400px 600px; }
        }
        body.theme-christmas .messenger-header, body.theme-christmas .chat-sidebar, body.theme-christmas .chat-header, body.theme-christmas .profile-sidebar { border-color: rgba(255,255,255,0.4) !important; background: rgba(135, 206, 235, 0.2) !important; backdrop-filter: blur(20px); color: #fff !important; }
        body.theme-christmas .chat-main { border-color: rgba(255,255,255,0.3) !important; }
        body.theme-christmas .my-msg { background: linear-gradient(135deg, #fff, #87ceeb) !important; color: #1e3c72 !important; font-weight: 800; box-shadow: 0 5px 20px rgba(135, 206, 235, 0.5); border: 1px solid #fff !important; }
        body.theme-christmas .other-msg { background: rgba(255, 255, 255, 0.25) !important; color: #fff !important; border: 1px solid rgba(255,255,255,0.4) !important; backdrop-filter: blur(10px); }
        body.theme-christmas .conv-item.active { border-color: #fff !important; background: rgba(255, 255, 255, 0.25) !important; box-shadow: 0 0 20px rgba(255, 255, 255, 0.3); }
        body.theme-christmas .send-btn-full { background: linear-gradient(135deg, #fff, #00ccff) !important; color: #1e3c72 !important; box-shadow: 0 0 15px rgba(255,255,255,0.6); }
        body.theme-christmas .full-input { background: rgba(255, 255, 255, 0.2) !important; border-color: #fff !important; color: #fff !important; }
        body.theme-christmas .full-input::placeholder { color: rgba(255,255,255,0.8); }
        body.theme-christmas .tech-corner { border-color: #fff !important; }

        /* Summer Theme */
        body.theme-summer { background: #2980b9 !important; }
        body.theme-summer::before { background: radial-gradient(circle, #ffcc00 0%, transparent 60%) !important; opacity: 0.4 !important; animation: pulseGlow 4s infinite !important; }
        @keyframes pulseGlow { 0% { transform: scale(0.8); opacity: 0.3; } 50% { transform: scale(1.2); opacity: 0.5; } 100% { transform: scale(0.8); opacity: 0.3; } }
        body.theme-summer .messenger-header, body.theme-summer .chat-sidebar, body.theme-summer .chat-header, body.theme-summer .profile-sidebar { border-color: #ffcc00 !important; background: rgba(41, 128, 185, 0.95) !important; }
        body.theme-summer .my-msg { background: linear-gradient(135deg, #f1c40f, #ffcc00) !important; color: #000 !important; }
        body.theme-summer .conv-item.active { border-color: #ffcc00 !important; background: rgba(255, 204, 0, 0.2) !important; }

        /* New Year Theme */
        body.theme-new_year { background: radial-gradient(ellipse at bottom, #1b2735 0%, #090a0f 100%) !important; }
        body.theme-new_year .messenger-header, body.theme-new_year .chat-sidebar, body.theme-new_year .chat-header, body.theme-new_year .profile-sidebar { border-color: #ffd700 !important; background: rgba(10, 10, 10, 0.95) !important; }
        body.theme-new_year .my-msg { background: linear-gradient(135deg, #ffd700, #b8860b) !important; color: #000 !important; }
        body.theme-new_year .conv-item.active { border-color: #ffd700 !important; background: rgba(255, 215, 0, 0.15) !important; }
        /* Sparkle for New Year */
        .firework-particle { position: fixed; width: 4px; height: 4px; border-radius: 50%; pointer-events: none; animation: explode 1.5s ease-out forwards; z-index: 9999; }
        @keyframes explode { 0% { transform: translate(0, 0) scale(1); opacity: 1; } 100% { transform: translate(var(--tx), var(--ty)) scale(0); opacity: 0; } }

        .full-input {
            width: 100%; padding: 12px 20px; border-radius: 20px;
            background: rgba(0,0,0,0.5); border: 1px solid var(--border-glow);
            color: white; font-size: 15px; outline: none; transition: border-color 0.3s;
            font-family: var(--terminal-font);
            margin: 0;
            resize: none; overflow-y: hidden; min-height: 45px; max-height: 150px;
            line-height: 1.4; display: block;
        }
        .full-input:focus { 
            border-color: var(--neon-green); 
            box-shadow: 0 0 25px rgba(0,255,170,0.2); 
            background: rgba(0,0,0,0.7);
        }

        .send-btn-full {
            background: linear-gradient(135deg, var(--neon-green), #00cc88); color: #0a1f16; border: none;
            width: 50px; height: 50px; border-radius: 50%;
            cursor: pointer; font-weight: bold; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 20px rgba(0, 255, 170, 0.3);
            transition: 0.3s;
        }
        .send-btn-full:hover { transform: scale(1.1) rotate(10deg); box-shadow: 0 0 25px rgba(0, 255, 170, 0.5); }
        
        /* HUD Decorative Corners */
        .tech-corner {
            position: absolute; width: 15px; height: 15px; border-color: var(--neon-green); border-style: solid; opacity: 0.4; pointer-events: none;
            transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .chat-main:hover .tech-corner { width: 40px; height: 40px; opacity: 0.8; }
        .tl { top: 10px; left: 10px; border-width: 2px 0 0 2px; }
        .tr { top: 0; right: 0; border-width: 2px 2px 0 0; }
        .bl { bottom: 0; left: 0; border-width: 0 0 2px 2px; }
        .br { bottom: 0; right: 0; border-width: 0 2px 2px 0; }

        .typing-pill {
            background: rgba(0, 255, 170, 0.1);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 11px;
            font-family: var(--terminal-font);
            display: inline-block;
            margin: 10px 25px;
            border: 1px solid var(--border-glow);
            animation: pulseGlow 2s infinite alternate;
        }

        /* Scrollbar Sidebar */
        .conv-list::-webkit-scrollbar { width: 5px; }
        .conv-list::-webkit-scrollbar-track { background: transparent; }
        .conv-list::-webkit-scrollbar-thumb { background: rgba(0, 255, 170, 0.2); border-radius: 10px; }

        .loading-nodes {
            animation: pulseGlow 2s infinite alternate;
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
        }
        @keyframes pulseGlow {
            from { opacity: 0.3; text-shadow: 0 0 5px #00ffaa; }
            to { opacity: 1; text-shadow: 0 0 20px #00ffaa; }
        }

        /* --- SPA View Transitions --- */
        #welcomeView, #chatView {
            transition: all 0.5s ease;
        }
        .fade-in-view {
            animation: viewReveal 0.6s forwards;
        }
        @keyframes viewReveal {
            from { opacity: 0; transform: translateY(20px) scale(0.98); filter: blur(10px); }
            to { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); }
        }
        .msg-status { animation: fadeIn 1s forwards; }

        /* --- Pinned Messages Modal --- */
        .pinned-modal { display: none; position: fixed; z-index: 6000; inset: 0; background: rgba(0,0,0,0.85); align-items: center; justify-content: center; backdrop-filter: blur(10px); }
        .pinned-content { width: 500px; max-height: 80vh; background: #0a1f16; border: 1px solid var(--neon-green); border-radius: 15px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 0 50px rgba(0,255,170,0.2); }
        .pinned-header { padding: 20px; border-bottom: 1px solid var(--border-glow); display: flex; justify-content: space-between; align-items: center; background: rgba(0, 255, 170, 0.05); }
        .pinned-list { flex: 1; overflow-y: auto; padding: 20px; }
        .pinned-item {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px;
            padding: 15px; margin-bottom: 15px; cursor: pointer; transition: 0.3s;
            position: relative; overflow: hidden;
        }
        .pinned-item:hover { background: rgba(0, 255, 170, 0.05); border-color: var(--neon-green); transform: translateX(5px); }
        .pinned-item::before { content: '📌'; position: absolute; top: 10px; right: 10px; font-size: 14px; opacity: 0.5; }
        .pinned-item-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .pinned-item-meta img { width: 30px; height: 30px; border-radius: 50%; border: 1px solid var(--neon-green); }
        .pinned-item-meta strong { color: #fff; font-size: 13px; }
        .pinned-item-meta small { color: #aaa; font-size: 10px; font-family: var(--terminal-font); }
        .pinned-item-text { color: #eee; font-size: 14px; line-height: 1.5; white-space: pre-wrap; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        
        /* Jump to Message Animation */
        @keyframes jumpFlash {
            0% { background: rgba(0, 255, 170, 0.8); box-shadow: 0 0 30px #00ffaa; transform: scale(1.05); }
            100% { background: initial; box-shadow: none; transform: scale(1); }
        }
        .msg-jump-target { animation: jumpFlash 2s cubic-bezier(0.22, 1, 0.36, 1) forwards !important; }

        /* Mention Highlight */
        .mention-highlight {
            color: var(--neon-green);
            font-weight: bold;
            background: rgba(0, 255, 170, 0.1);
            padding: 2px 4px;
            border-radius: 4px;
            text-decoration: none;
        }
        .mention-dropdown-full {
            position: absolute;
            bottom: 70px; /* Above the input area */
            left: 0;
            width: 100%;
            background: #1a3d2f;
            border: 1px solid var(--border-glow);
            border-radius: 8px;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            z-index: 100;
            box-shadow: 0 -5px 15px rgba(0,0,0,0.3);
        }
        .mention-item-full {
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #eee;
            cursor: pointer;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .mention-item-full:hover {
            background: rgba(0, 255, 170, 0.1);
        }
        .mention-item-full img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--neon-green);
        }
        
        /* --- Directory Modal Styles --- */
        .dir-modal { display: none; position: fixed; z-index: 5000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); align-items: center; justify-content: center; backdrop-filter: blur(10px); }
        .dir-content { width: 450px; max-height: 80vh; background: #0a1f16; border: 1px solid var(--neon-green); border-radius: 15px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 0 50px rgba(0,255,170,0.2); animation: popIn 0.4s cubic-bezier(0.22, 1, 0.36, 1); }
        .dir-header { padding: 20px; border-bottom: 1px solid var(--border-glow); display: flex; justify-content: space-between; align-items: center; background: rgba(0, 255, 170, 0.05); }
        
        /* --- Directory Tabs Animation --- */
        .dir-tabs { position: relative; display: flex; background: rgba(0, 0, 0, 0.2); padding: 5px; border-bottom: 1px solid var(--border-glow); }
        .dir-tab { 
            flex: 1; padding: 12px; text-align: center; cursor: pointer; color: #666; 
            font-family: var(--terminal-font); font-size: 11px; transition: 0.3s; 
            text-transform: uppercase; letter-spacing: 1px; font-weight: bold; 
            position: relative; z-index: 2; 
        }
        .dir-tab.active { color: #0a1f16; }
        .dir-slidebar {
            position: absolute; top: 5px; left: 5px;
            width: calc((100% - 10px) / 3); height: calc(100% - 10px);
            background: var(--neon-green); border-radius: 8px;
            z-index: 1; transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1);
            box-shadow: 0 0 15px var(--neon-green);
        }

        .dir-search { padding: 15px; background: rgba(0,0,0,0.1); }
        .dir-search input { width: 100%; padding: 12px 18px; border-radius: 25px; background: rgba(0,0,0,0.4); border: 1px solid var(--border-glow); color: white; outline: none; font-family: var(--terminal-font); font-size: 13px; }
        .dir-search input:focus { border-color: var(--neon-green); box-shadow: 0 0 15px rgba(0,255,170,0.2); }
        
        @keyframes dirListAppear {
            from { opacity: 0; transform: scale(0.98); }
            to { opacity: 1; transform: scale(1); }
        }
        .dir-list { flex: 1; overflow-y: auto; padding: 10px; scrollbar-width: thin; }
        
        @keyframes dirItemFade {
            from { opacity: 0; transform: translateY(15px); filter: blur(5px); }
            to { opacity: 1; transform: translateY(0); filter: blur(0); }
        }
        .dir-item { display: flex; align-items: center; gap: 15px; padding: 12px; cursor: pointer; border-radius: 12px; transition: 0.2s; margin-bottom: 5px; border: 1px solid transparent; opacity: 0; animation: dirItemFade 0.6s cubic-bezier(0.22, 1, 0.36, 1) forwards; }
        .dir-item:hover { background: rgba(0, 255, 170, 0.08); border-color: rgba(0, 255, 170, 0.2); transform: scale(1.02); }
        
        .dir-avatar-box { position: relative; width: 45px; height: 45px; flex-shrink: 0; }
        .dir-avatar { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-glow); }
        .dir-status-dot { position: absolute; bottom: 2px; right: 2px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #0a1f16; }
        .dir-status-dot.online { background: #00ffaa; box-shadow: 0 0 10px #00ffaa; }
        .dir-status-dot.offline { background: #555; }
        .dir-info { flex: 1; overflow: hidden; }
        .dir-info strong { display: block; font-size: 14px; color: #fff; text-transform: capitalize; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dir-info small { font-size: 10px; color: #00ffaa; text-transform: uppercase; opacity: 0.7; font-family: var(--terminal-font); }
        .close-dir { color: #aaa; cursor: pointer; font-size: 24px; transition: 0.3s; }
        .close-dir:hover { color: #ff4757; transform: rotate(90deg); }
        .loading-dir { text-align: center; padding: 30px; color: var(--neon-green); font-family: var(--terminal-font); font-size: 12px; }

        /* --- MOBILE RESPONSIVENESS UPDATES --- */
        @media (max-width: 768px) {
            .messenger-container { padding: 0; gap: 0; }
            .chat-sidebar {
                width: 100% !important;
                border-radius: 0;
                border: none;
                display: flex;
            }
            .chat-main {
                width: 100% !important;
                border-radius: 0;
                border: none;
                position: fixed;
                top: 65px;
                left: 0;
                height: calc(100vh - 65px);
                z-index: 100;
                display: none; /* Default hidden until chat is opened */
            }
            /* Mobile View Switcher */
            body.show-chat .chat-main { display: flex; }
            body.show-chat .chat-sidebar { display: none; }

            .messenger-header h2 { font-size: 14px; letter-spacing: 1px; }
            .messenger-header span, .messenger-header .hologram-glow { display: none; }
            #newChatTrigger span { display: none; }
            #newChatTrigger { padding: 8px; }
            
            .chat-footer { padding: 10px 15px; gap: 8px; }
            .full-input { margin: 0 5px; padding: 10px 15px; font-size: 14px; }
            .send-btn-full { width: 45px; height: 45px; }
            
            .msg-container { max-width: 85% !important; }
            .msg { padding: 10px 14px !important; font-size: 14px !important; }

            .back-icon-btn { display: flex !important; }
            .back-text-link { display: none !important; }
        }

        /* Back Icon Button Style */
        .back-icon-btn {
            display: none;
            align-items: center; justify-content: center;
            width: 38px; height: 38px;
            border-radius: 50%;
            background: rgba(0, 255, 170, 0.1);
            border: 1px solid var(--neon-green);
            color: var(--neon-green);
            cursor: pointer;
            transition: 0.3s;
        }
        .back-icon-btn:hover { background: var(--neon-green); color: #0a1f16; }

        /* CP-style Notification Pop-up */
        #mobileNotifPop {
            position: fixed;
            top: -120px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 400px;
            background: rgba(10, 31, 22, 0.98);
            border: 2px solid var(--neon-green);
            border-radius: 15px;
            padding: 15px;
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.9);
            transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            backdrop-filter: blur(15px);
        }
        #mobileNotifPop.show { top: 20px; }
        @keyframes statusPulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 71, 87, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 71, 87, 0); }
        }

        @media (max-width: 480px) {
            .profile-sidebar { width: 100%; }
            .dir-content, .pinned-content { width: 95%; max-height: 90vh; }
        }
    </style>
</head>
<body class="theme-<?php echo $current_theme; ?> <?php echo $active_chat_id ? 'show-chat' : ''; ?>">

    <div class="messenger-header">
        <div style="display:flex; align-items:center; gap:15px;">
            <div class="back-icon-btn" onclick="goBack()" title="Back to List">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </div>
            <a href="javascript:void(0)" onclick="goBack()" class="back-text-link" style="color:#00ffaa; text-decoration:none; font-weight:bold;"> ← BACK</a>
            <h2 style="font-family: 'Courier New'; letter-spacing: 2px; margin: 0;">||SC MESSENGER ROOM||</h2>
        </div>
        <div style="display:flex; align-items:center; gap:20px;">
            <div id="newChatTrigger" style="cursor:pointer; display:flex; align-items:center; gap:8px; padding: 8px 15px; border: 1px solid var(--border-glow); border-radius: 20px; background: rgba(0,255,170,0.05); transition: 0.3s;" onclick="openDirectoryModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: #00ffaa;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path><line x1="12" y1="7" x2="12" y2="13"></line><line x1="9" y1="10" x2="15" y2="10"></line></svg>
                <span style="font-family: var(--terminal-font); font-size: 11px; color: var(--neon-green); font-weight: bold; letter-spacing: 1px;">NEW UPLINK</span>
            </div>
            <div style="display:flex; align-items:center; gap:10px;" class="hologram-glow">
                <img src="assets/images/St.Anne_logo.png" style="width:35px; opacity:0.8;">
            </div>
            <span style="color:#00ffaa; font-size:12px; font-weight:bold;">SECURE</span>
        </div>
    </div>

    <div class="messenger-container">
        <!-- Sidebar: List of Conversations -->
        <div class="chat-sidebar">
            <div class="sidebar-search">
                <input type="text" id="sideSearch" placeholder="Search Messenger..." onkeyup="filterSidebar()">
            </div>
            <!-- NEW: Active Now Scroll -->
            <div class="active-now-bar" id="activeNowBar"></div>
            
            <!-- NEW: Filter Pills -->
            <div class="sidebar-filters">
                <div class="filter-pill active" onclick="setSidebarFilter('all', this)">All</div>
                <div class="filter-pill" onclick="setSidebarFilter('unread', this)">Unread</div>
                <div class="filter-pill" onclick="setSidebarFilter('groups', this)">Groups</div>
                <div class="filter-pill" onclick="setSidebarFilter('invites', this)" id="inviteFilterPill">Invitations</div>
            </div>
            <div class="conv-list" id="convList">
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="chat-main">
            <!-- Welcome View (SPA Placeholder) -->
            <div id="welcomeView" style="flex:1; display:<?php echo $active_chat_id ? 'none' : 'flex'; ?>; flex-direction:column; align-items:center; justify-content:center; opacity:0.5; height: 100%;">
                <img src="communication.png" style="width:100px; filter: grayscale(1) invert(1);">
                <h2 style="color:#fff; margin-top:20px; font-family:'Courier New';">SELECT TO BEGIN</h2>
            </div>

            <!-- Chat View -->
            <div id="chatView" style="display:<?php echo $active_chat_id ? 'flex' : 'none'; ?>; flex:1; flex-direction:column; height: 100%; overflow: hidden;">
                <div id="rainLayer"></div>
                <div id="stars"></div>
                <div id="stars2"></div>
                <div id="stars3"></div>
                <div id="midnightSkyLayer">
                    <div class="stars stars-1"></div>
                    <div class="stars stars-2"></div>
                    <div class="stars stars-3"></div>
                    <div class="meteor m1"></div>
                    <div class="meteor m2"></div>
                    <div class="meteor m3"></div>
                    <div class="moon"></div>
                </div>
                <div class="chat-header">
                    <div class="tech-corner tl"></div>
                    <img id="activePic" src="assets/images/3icons8-student-64.png" style="width:40px; height:40px; border-radius:50%; border:2px solid #00ffaa;">
                    <div>
                        <strong id="activeName" style="font-size:18px; color:#fff; text-shadow: 0 0 5px rgba(0,255,170,0.3);">Loading...</strong>
                        <small id="activeStatus" style="display:block; color:#00ffaa; font-family: 'Courier New', monospace; font-size: 10px;">UPLINK STABLE</small>
                    </div>
                    <!-- Sidebar Info Button -->
                    <div style="margin-left: auto; cursor: pointer; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: rgba(255,255,255,0.05);" onclick="toggleProfileSidebar()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: #00ffaa;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    </div>
                </div>
                               
                <!-- Messenger Messaging Profile Sidebar -->
                <div class="profile-sidebar" id="profileSidebar">
                    <div class="ps-header">
                        <button class="ps-close-btn" onclick="toggleProfileSidebar()">✕</button>
                        <img id="psActivePic" src="assets/images/3icons8-student-64.png" class="ps-avatar" onclick="viewActiveProfile()" style="cursor:pointer;">
                        <h2 class="ps-name" onclick="viewActiveProfile()" style="cursor:pointer;"><span id="psActiveName">Username</span></h2>
                        <div class="ps-status">Active 15h ago</div>
                        
                        <div class="ps-encryption-badge">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            End-to-end encrypted
                        </div>

                        <div class="ps-actions">
                            <div class="ps-action-item" onclick="viewActiveProfile()">
                                <div class="ps-action-btn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div>
                                <div class="ps-action-label">Profile</div>
                            </div>
                            <div class="ps-action-item" onclick="togglePsSectionById('ps-themes')">
                                <div class="ps-action-btn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 2v20"></path><path d="M12 12l4.6-4.6"></path><path d="M12 12l4.6 4.6"></path></svg></div>
                                <div class="ps-action-label">Theme</div>
                            </div>
                            <div class="ps-action-item" onclick="toggleMuteChat()">
                                <div class="ps-action-btn" id="psMuteBtn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4V5z"></path><path d="M15.89 8.44L18.72 11.27M18.72 8.44L15.89 11.27"></path></svg></div>
                                <div class="ps-action-label" id="psMuteLabel">Mute</div>
                            </div>
                            <div class="ps-action-item" onclick="togglePsSectionById('ps-search-messages')">
                                <div class="ps-action-btn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></div>
                                <div class="ps-action-label">Search</div>
                            </div>
                        </div>
                    </div>

                    <div class="ps-section">
                        <div class="ps-section-title" onclick="togglePsSection(this)">
                            Chat info
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
                        </div>
                        <div class="ps-section-content">
                            <div class="ps-option-item" onclick="viewPinnedMessages()">View pinned messages</div>
                        </div>
                    </div>

                    <div class="ps-section">
                        <div class="ps-section-title" onclick="togglePsSection(this)">
                            Customize chat
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
                        </div>
                        <div class="ps-section-content">
                            <div class="ps-option-item">Theme</div>
                            <div class="ps-option-item">Nicknames</div>
                        </div>
                    </div>

                    <div class="ps-section" id="ps-themes">
                        <div class="ps-section-title" onclick="togglePsSection(this)">
                            Chat themes
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
                        </div>
                        <div class="ps-section-content">
                            <div class="theme-preview-card" onclick="setChatTheme('default')">
                                <div class="theme-preview-img" style="background: radial-gradient(circle at center, #0d2b1f 0%, #020806 100%); background-image: linear-gradient(rgba(0, 255, 170, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(0, 255, 170, 0.03) 1px, transparent 1px); background-size: 20px 20px;"></div>
                                <span class="theme-preview-label">Default Neural</span>
                            </div>

                            <div class="theme-preview-card" onclick="setChatTheme('flashlight')">
                                <img src="ztheme1.png" class="theme-preview-img" alt="Flashlight Theme">
                                <span class="theme-preview-label">Flashlight Dark</span>
                            </div>

                            <div class="theme-preview-card" onclick="setChatTheme('space')">
                                <div class="theme-preview-img" style="background: radial-gradient(ellipse at bottom, #1b2735 0%, #090a0f 100%); overflow: hidden; position: relative;"><div style="width: 1px; height: 1px; background: white; box-shadow: 10px 10px white, 50px 40px white, 20px 80px white, 70px 10px white, 80px 60px white;"></div></div>
                                <span class="theme-preview-label">Celestial Space</span>
                            </div>

                            <div class="theme-preview-card" onclick="setChatTheme('rain')">
                                <div class="theme-preview-img" style="background: #000; overflow: hidden; position: relative;"><div style="width: 2px; height: 10px; background: #09f; box-shadow: 5px 10px #09f, 20px 5px #09f, 15px 20px #09f, 30px 15px #09f; opacity: 0.6; position: absolute; top: 20px; left: 20px;"></div></div>
                                <span class="theme-preview-label">Stormy Rain</span>
                            </div>

                            <div class="theme-preview-card" onclick="setChatTheme('midnight')">
                                <div class="theme-preview-img" style="background: linear-gradient(to bottom, #020107 0%, #050b1a 100%); overflow: hidden; position: relative;"><div style="position: absolute; top: 20%; right: 20%; width: 20px; height: 20px; border-radius: 50%; box-shadow: 5px 5px 0 0 #fdfbd3;"></div></div>
                                <span class="theme-preview-label">Midnight Sky</span>
                            </div>

                            <div class="theme-preview-card" onclick="setChatTheme('geometric')">
                                <div class="theme-preview-img" style="--s:20px; --c1:#1a2634; --c2:#111a24; --c3:#080d14; --_g: 0 120deg, #0000 0; background: conic-gradient(from 0deg at calc(500% / 6) calc(100% / 3), var(--c3) var(--_g)), conic-gradient(from -120deg at calc(100% / 6) calc(100% / 3), var(--c2) var(--_g)), conic-gradient(from 120deg at calc(100% / 3) calc(500% / 6), var(--c1) var(--_g)), conic-gradient(from 120deg at calc(200% / 3) calc(500% / 6), var(--c1) var(--_g)), conic-gradient(from -180deg at calc(100% / 3) 50%, var(--c2) 60deg, var(--c1) var(--_g)), conic-gradient(from 60deg at calc(200% / 3) 50%, var(--c1) 60deg, var(--c3) var(--_g)), conic-gradient(from -60deg at 50% calc(100% / 3), var(--c1) 120deg, var(--c2) 0 240deg, var(--c3) 0); background-size: calc(var(--s) * 1.732) var(--s);"></div>
                                <span class="theme-preview-label">Night Geometric</span>
                            </div>
                        </div>
                    </div>

                    <div class="ps-section" id="ps-search-messages">
                        <div class="ps-section-title" onclick="togglePsSection(this)">
                            Search in conversation
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
                        </div>
                        <div class="ps-section-content" style="padding: 10px 20px;">
                            <input type="text" id="msgSearchInput" class="full-input" placeholder="Search messages..." onkeyup="searchMessagesInChat()" style="margin: 0; width: 100%;">
                            <div id="msgSearchResults" style="margin-top: 15px; max-height: 300px; overflow-y: auto;"></div>
                        </div>
                    </div>

                    <div class="ps-section active">
                        <div class="ps-section-title" onclick="togglePsSection(this)">
                            Media & files
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
                        </div>
                        <div class="ps-section-content">
                            <div class="ps-option-item" onclick="togglePsSubSection('media')"><span class="ps-option-icon">🖼️</span> Media</div>
                            <div id="psMediaGrid" class="ps-media-grid"></div>
                            <div class="ps-option-item" onclick="togglePsSubSection('files')"><span class="ps-option-icon">📄</span> Files</div>
                            <div id="psFilesList" class="ps-files-list"></div>
                        </div>
                    </div>
                </div>

                <div class="chat-body" id="fullChatBody"></div>

                <button id="newMsgScrollBtn" onclick="scrollToBottomManual()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>
                    NEW MESSAGE
                </button>

                <!-- New Typing Indicator UI -->
                <div id="typingIndicator" style="display:none;">
                    <div class="typing-indicator-dots">
                        <img id="typingPic" src="" style="width:24px; height:24px; border-radius:50%; border:1.5px solid var(--neon-green); object-fit: cover; display:none;">
                        <span id="typingName" class="typing-label"></span>
                        <div style="display:flex; gap:4px;"><span></span><span></span><span></span></div>
                    </div>
                </div>
                
                <!-- File Preview Area -->
                <div id="fullChatFilePreviewArea"></div>

                <div class="chat-footer">
                    <label for="fullChatFileInput" title="Send Photo/Video"><img src="gallery.png" alt="Gallery"></label>
                    <input type="file" id="fullChatFileInput" style="display:none;" accept="image/*,video/*" multiple onchange="previewFullChatFile('media')">

        <label for="fullChatDocInput" title="Send File"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--neon-green)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg></label>
                    <input type="file" id="fullChatDocInput" style="display:none;" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar" multiple onchange="previewFullChatFile('doc')">

        <div style="position:relative; flex:1;">
            <div id="mentionSuggestionsFull" class="mention-dropdown-full"></div>
            <textarea id="fullChatInput" class="full-input" placeholder="Type a message..." rows="1" oninput="autoResize(this); handleFullChatInputMention();" onkeydown="handleFullChatKey(event)"></textarea>
            <div id="fullChatMentionedUsers" style="display:none;"></div> <!-- Hidden container for mentioned user IDs -->
        </div>
                    <button class="send-btn-full" onclick="sendMessageFull()">➤</button>
                </div>
            </div>
        </div>
    </div>

    <!-- CP Style Notification Pop-up -->
    <div id="mobileNotifPop" onclick="switchToInvitesTab()">
        <div style="background: rgba(0, 255, 170, 0.1); width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--neon-green);">
            <img src="folder.png" style="width: 25px; height: 25px;">
        </div>
        <div style="flex: 1;">
            <div style="color: var(--neon-green); font-weight: 800; font-size: 10px; font-family: var(--terminal-font); letter-spacing: 1px;">SYSTEM INBOUND UPLINK</div>
            <div id="mobileNotifText" style="color: #fff; font-size: 14px; font-weight: 500; margin-top: 2px;">New Room Invitation Received</div>
        </div>
    </div>

    <!-- Media Lightbox Modal -->
    <div id="sidebarLightbox" class="lightbox-modal" onclick="if(event.target==this) closeSidebarLightbox()">
        <span class="lightbox-close" onclick="closeSidebarLightbox()">&times;</span>
        <div class="lightbox-content">
            <button class="lightbox-nav lightbox-prev" onclick="prevSidebarSlide()">&#10094;</button>
            <div id="lightboxMediaContainer"></div>
            <button class="lightbox-nav lightbox-next" onclick="nextSidebarSlide()">&#10095;</button>
        </div>
        <div id="sidebarLightboxCounter" class="lightbox-counter"></div>
    </div>

    <!-- Message Media Lightbox Modal -->
    <div id="messageLightbox" class="lightbox-modal" onclick="if(event.target==this) closeMessageLightbox()">
        <span class="lightbox-close" onclick="closeMessageLightbox()">&times;</span>
        <div class="lightbox-content">
            <button class="lightbox-nav lightbox-prev" onclick="prevMessageSlide()">&#10094;</button>
            <div id="messageLightboxMediaContainer"></div>
            <button class="lightbox-nav lightbox-next" onclick="nextMessageSlide()">&#10095;</button>
        </div>
        <div id="messageLightboxCounter" class="lightbox-counter"></div>
    </div>


    <!-- Directory Modal -->
    <div id="directoryModal" class="dir-modal" onclick="if(event.target==this) closeDirectoryModal()">
        <div class="dir-content">
            <div class="dir-header">
                <h3 style="margin:0; color:var(--neon-green); font-family:var(--terminal-font); font-size:14px; letter-spacing:2px;">// SELECT_NODE</h3>
                <span class="close-dir" onclick="closeDirectoryModal()">&times;</span>
            </div>
            <div class="dir-tabs">
                <div class="dir-tab active" data-type="student" onclick="switchDirTab('student')">Students</div>
                <div class="dir-tab" data-type="teacher" onclick="switchDirTab('teacher')">Teachers</div>
                <div class="dir-tab" data-type="alumni" onclick="switchDirTab('alumni')">Alumni</div>
                <div class="dir-slidebar" id="dirSlidebar"></div>
            </div>
            <div class="dir-search">
                <input type="text" id="dirSearchInput" placeholder="Search by name or ID..." onkeyup="filterDirectory()">
            </div>
            <div class="dir-list" id="dirListContainer">
                <div class="loading-dir">INITIALIZING DIRECTORY...</div>
            </div>
            <div style="padding: 10px; background: rgba(0,255,170,0.02); text-align: center; border-top: 1px solid var(--border-glow);">
                <small style="color: #555; font-size: 9px; font-family: var(--terminal-font);">UPLINK STABLE // SECURE HANDSHAKE READY</small>
            </div>
        </div>
    </div>

    <!-- Messenger Style Delete Modal -->
    <div id="messengerDeleteModal" style="display:none; position:fixed; z-index:20000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.85); align-items:center; justify-content:center; backdrop-filter: blur(5px);">
        <div class="modal-content" style="max-width: 400px; padding: 30px; text-align: center; background: #0a1f16; border: 1px solid var(--neon-green); border-radius: 15px; box-shadow: 0 0 50px rgba(0,255,170,0.2);">
            <h3 style="color: var(--neon-green); margin-top: 0; font-family: var(--terminal-font); letter-spacing: 1px; font-size: 16px;">Who would you like to remove this message for?</h3>
            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 25px;">
                <button id="unsendBtn" style="width:100%; background: #ff4757; color: white; border: none; padding: 14px; border-radius: 8px; font-weight: 800; cursor: pointer; transition: 0.3s; text-transform: uppercase;">Unsend for everyone</button>
                <button id="deleteForMeBtn" style="width:100%; background: rgba(255,255,255,0.05); color: white; border: 1px solid rgba(255,255,255,0.1); padding: 14px; border-radius: 8px; font-weight: 800; cursor: pointer; transition: 0.3s; text-transform: uppercase;">Remove for you</button>
                <button onclick="closeMessengerDeleteModal()" style="width:100%; background: transparent; color: #aaa; border: none; padding: 10px; cursor: pointer; font-family: var(--terminal-font); font-size: 12px;">CANCEL</button>
            </div>
        </div>
    </div>

    <!-- Pinned Messages Modal -->
    <div id="pinnedModal" class="pinned-modal" onclick="if(event.target==this) closePinnedModal()">
        <div class="pinned-content">
            <div class="pinned-header">
                <h3 style="margin:0; color:var(--neon-green); font-family:var(--terminal-font); font-size:14px; letter-spacing:2px;">// PINNED LOGS</h3>
                <span class="close-dir" onclick="closePinnedModal()">&times;</span>
            </div>
            <div class="pinned-list" id="pinnedListContainer">
                <!-- Pinned items here -->
            </div>
        </div>
    </div>

    <!-- Custom Confirm Modal -->
    <div id="customConfirmModal" style="display:none; position:fixed; z-index:20000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.85); align-items:center; justify-content:center; backdrop-filter: blur(5px);">
        <div class="modal-content" style="max-width: 400px; padding: 35px; text-align: center; background: #0a1f16; border: 1px solid var(--neon-green); border-radius: 15px; box-shadow: 0 0 50px rgba(0,255,170,0.2);">
            <h3 style="color: var(--neon-green); margin-top: 0; font-family: var(--terminal-font); letter-spacing: 2px;">CONFIRM \DELETION</h3>
            <p id="customConfirmText" style="color: #e4e6eb; margin-bottom: 30px; font-family: var(--terminal-font); font-size: 14px; line-height: 1.6;"></p>
            <div style="display: flex; gap: 15px; justify-content: center;">
                <button id="confirmYesBtn" style="flex:1; background: #ff4757; color: white; border: none; padding: 14px; border-radius: 8px; font-weight: 800; cursor: pointer; transition: 0.3s; text-transform: uppercase;">DELETE</button>
                <button onclick="closeCustomConfirm()" style="flex:1; background: rgba(255,255,255,0.05); color: white; border: 1px solid rgba(255,255,255,0.1); padding: 14px; border-radius: 8px; font-weight: 800; cursor: pointer; transition: 0.3s; text-transform: uppercase;">CANCEL</button>
            </div>
        </div>
    </div>

    <!-- Audio for Notifications -->
    <audio id="notifSound" src="https://assets.mixkit.co/active_storage/sfx/2358/2358-preview.mp3" preload="auto"></audio>
    <audio id="uiClickSound" src="https://assets.mixkit.co/active_storage/sfx/2568/2568-preview.mp3" preload="auto"></audio>

    <script>
        let activeId = "<?php echo $active_chat_id; ?>";
        let activeType = "<?php echo $active_chat_type; ?>";
        let chatInterval = null;
        let typingInterval = null;
        let sidebarInterval = null; // Polling interval for sidebar
        let previousUnreadCounts = {}; // Global object to store previous unread counts for highlight logic
        let knownInviteIds = new Set();
        let firstInviteCheck = true;

        function checkInvitations() {
            fetch('handlers/sacli_room_handler.php', { method: 'POST', body: new URLSearchParams('action=get_my_invitations') })
            .then(r => r.json())
            .then(invites => {
                let hasNew = false;
                let lastRoomName = "";
                
                invites.forEach(inv => {
                    if (!knownInviteIds.has(inv.id)) {
                        knownInviteIds.add(inv.id);
                        if (!firstInviteCheck) {
                            hasNew = true;
                            lastRoomName = inv.room_name;
                        }
                    }
                });

                if (hasNew) {
                    showCPNotification("New Invite: " + lastRoomName);
                }
                firstInviteCheck = false;
                
                // Update pill badge
                const pill = document.getElementById('inviteFilterPill');
                if (pill) {
                    pill.innerHTML = invites.length > 0 ? `<span style="background:#ff4757; color:white; font-size:10px; padding:2px 6px; border-radius:10px; margin-right:8px; font-weight:bold; box-shadow:0 0 10px rgba(255,71,87,0.5); display:inline-block;">${invites.length}</span> Invitations` : "Invitations";
                    pill.style.display = 'inline-flex';
                    pill.style.alignItems = 'center';
                }
            });
        }

        function showCPNotification(text) {
            const pop = document.getElementById('mobileNotifPop');
            document.getElementById('mobileNotifText').innerText = text;
            pop.classList.add('show');
            playNotifSound();
            setTimeout(() => { pop.classList.remove('show'); }, 6000);
        }

        function switchToInvitesTab() {
            const pill = document.getElementById('inviteFilterPill');
            if (pill) setSidebarFilter('invites', pill);
            document.getElementById('mobileNotifPop').classList.remove('show');
        }

        function playClickSound() {
            const sound = document.getElementById('uiClickSound');
            if (sound) sound.play().catch(e => {});
        }

        // Mobile Back Logic
        function goBack() {
            if (window.innerWidth <= 768 && document.body.classList.contains('show-chat')) {
                document.body.classList.remove('show-chat');
                activeId = null;
                window.history.pushState({}, '', 'SacliChat_Full.php');
            } else {
                window.location.href = 'SacliConnect.php';
            }
        }

        // Helper function to format time for sidebar
        function formatSidebarTime(ts) {
            if (!ts) return "";
            const date = new Date(ts);
            const now = new Date();
            const diff = now - date;
            const diffDays = Math.floor(diff / (1000 * 60 * 60 * 24));

            if (diffDays === 0) {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } else if (diffDays === 1) {
                return "Yesterday";
            } else if (diffDays < 7) {
                return date.toLocaleDateString([], { weekday: 'short' });
            } else {
                return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
            }
        }

        let currentSidebarCategory = 'all';
        function setSidebarFilter(cat, el) {
            currentSidebarCategory = cat;
            document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
            el.classList.add('active');
            
            // I-clear ang lastHash para pilitin ang UI na mag-refresh agad sa click
            const container = document.getElementById('convList');
            if(container) delete container.dataset.lastHash;
            
            // Magpakita ng immediate loading feedback base sa category
            if(container) {
                if(cat === 'invites') {
                    container.innerHTML = '<div style="padding:25px; font-size:11px; color:#555; font-family:var(--terminal-font); text-align:center;">// SCANNING_FOR_ROOM_INVITATIONS...</div>';
                } else if(cat === 'unread') {
                    container.innerHTML = '<div style="padding:25px; font-size:11px; color:#555; font-family:var(--terminal-font); text-align:center;">// FILTERING_UNREAD_MESSAGES...</div>';
                } else if(cat === 'groups') {
                    container.innerHTML = '<div style="padding:25px; font-size:11px; color:#555; font-family:var(--terminal-font); text-align:center;">// LOADING_NEURAL_GROUPS...</div>';
                } else {
                    container.innerHTML = '<div style="padding:25px; font-size:11px; color:#555; font-family:var(--terminal-font); text-align:center;">// ACCESSING_CONVERSATION_HISTORY...</div>';
                }
            }

            loadSidebar();
        }

        function loadSidebar() {
            const pinnedChats = JSON.parse(localStorage.getItem('pinned_convs') || '[]');
            let combinedList = [];
            let currentUnreadCounts = {};

            checkInvitations(); // Refresh invitation status

            fetch('handlers/post_interaction.php', { method: 'POST', body: new URLSearchParams('action=fetch_msgs') })
            .then(res => res.json())
            .then(data => {
                data.forEach(m => {
                    m.key = `direct-${m.other_id}`;
                    m.type = 'direct';
                    m.sort_ts = m.timestamp;
                    combinedList.push(m);
                });
                
                fetch('handlers/group_chat_handler.php', { method: 'POST', body: new URLSearchParams('action=get_my_groups') })
                .then(res => res.json())
                .then(groups => {
                    groups.forEach(g => {
                        g.key = `group-${g.id}`;
                        g.type = 'group';
                        g.sort_ts = g.last_ts;
                        g.other_id = g.id;
                        g.student_name = g.name;
                        g.profile_pic = g.group_icon;
                        combinedList.push(g);
                    });

                    // Update "Active Now" Bar (DMs Only)
                    const activeBar = document.getElementById('activeNowBar');
                    let activeHTML = '';
                    const onlineUsers = combinedList.filter(u => u.is_online == 1 && u.type === 'direct');
                    onlineUsers.forEach(u => {
                        const pic = u.profile_pic ? "uploads/"+u.profile_pic : "assets/images/3icons8-student-64.png";
                        activeHTML += `<div class="active-user-node" onclick="openFullChat('${u.other_id}', 'direct', '${u.student_name.replace(/'/g, "\\'")}', '${pic}')">
                            <img src="${pic}"><span>${u.student_name.split(' ')[0]}</span>
                        </div>`;
                    });
                    activeBar.innerHTML = activeHTML || '<div style="font-size:10px; color:#555; padding-left:5px;">NO ACTIVE ACCOUNTS</div>';

                    // SORTING LOGIC (Pinned First, then Timestamp)
 combinedList.sort((a, b) => {
     const aIndex = pinnedChats.indexOf(a.key);
     const bIndex = pinnedChats.indexOf(b.key);
     
     const aPinned = aIndex > -1;
     const bPinned = bIndex > -1;

     if (aPinned && !bPinned) return -1;
     if (!aPinned && bPinned) return 1;
     if (aPinned && bPinned) {
         // Kung parehong pinned, i-sort base sa pagkakasunod-sunod sa array
         return aIndex - bIndex; 
     }
     return new Date(b.sort_ts || 0) - new Date(a.sort_ts || 0);
 });

                    const container = document.getElementById('convList');
                    let newHTML = '';

                    combinedList.forEach(item => {
                        // FILTERING LOGIC
                        if (currentSidebarCategory === 'unread' && item.unread_count == 0) return;
                        if (currentSidebarCategory === 'groups' && item.type !== 'group') return;
                        if (currentSidebarCategory === 'invites') return; // Separate logic needed for invites

                        const isGroup = item.type === 'group';
                        const pic = item.profile_pic ? "uploads/"+item.profile_pic : (isGroup ? "7icons8-organization-64.png" : "assets/images/3icons8-student-64.png");
                        const isActive = (activeId == item.other_id && activeType == item.type) ? 'active' : '';
                        const isPinned = pinnedChats.includes(item.key);
                        const isMuted = isChatMuted(item.other_id, item.type);
                        
                        const unreadBadge = item.unread_count > 0 ? `<span style="background:#00ffaa; color:#0a1f16; font-size:10px; padding:2px 6px; border-radius:10px; margin-left:5px; font-weight:bold; box-shadow: 0 0 10px #00ffaa;">${item.unread_count}</span>` : '';
                        const muteIcon = isMuted ? '🔇' : '';
                        const pinIcon = isPinned ? '<span class="pin-indicator">📌</span>' : '';
                        
                        const timeStr = formatSidebarTime(item.sort_ts); // This is the time string for the last message
                        const prefix = (item.type === 'direct' && item.sender_id === "<?php echo $my_id; ?>") || (isGroup && item.last_sender === "<?php echo $my_id; ?>") ? "You: " : "";
                        const lastMsg = isGroup ? (item.last_msg || 'Group chat initialized') : item.message;
                        const snippet = prefix + lastMsg;

                        const isNewlyUnread = item.unread_count > 0 && (previousUnreadCounts[item.key] === undefined || item.unread_count > previousUnreadCounts[item.key]);
                        const highlightClass = isNewlyUnread ? ' new-message-highlight' : '';
                        currentUnreadCounts[item.key] = item.unread_count; // Update for next comparison
                        const unreadTextStyle = item.unread_count > 0 ? 'color: #fff; font-weight: bold;' : 'color: #aaa;';

                        newHTML += `
                            <div class="conv-item ${isActive}${highlightClass} ${isGroup?'group-type':'direct-type'} ${isPinned?'is-pinned':''}" data-conv-id="${item.key}" onclick="openFullChat('${item.other_id}', '${item.type}', '${item.student_name.replace(/'/g, "\\'")}', '${pic}')">
                                <div class="conv-avatar-box">
                                    <img src="${pic}">
                                    <div class="conv-status-dot ${item.is_online==1?'online':'offline'}"></div> 
                                </div>
                                <div class="conv-info" style="flex: 1; overflow: hidden;"> <!-- Removed overflow: hidden from here -->
                                    <div style="display:flex; flex-direction:column; min-width:0;"> <!-- New wrapper for name and snippet -->
                                        <strong style="text-transform: capitalize; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:flex; align-items:center; gap:3px; ${unreadTextStyle}">
                                            ${item.student_name.toLowerCase()} ${isGroup?'(GC)':''} ${pinIcon} ${unreadBadge} ${muteIcon} 
                                        </strong>
                                        <small style="${unreadTextStyle}">${snippet}</small> 
                                    </div>
                                </div>
                                <div style="display:flex; flex-direction:column; align-items:flex-end; justify-content:space-between; flex-shrink:0;"> <!-- New wrapper for time and options -->
                                    <div class="conv-options-wrapper">
                                        <button class="conv-dots-btn" onclick="toggleConvMenu(event, '${item.key}')">⋮</button>
                                        <div class="conv-menu" id="conv-menu-${item.key}">
                                            <div onclick="event.stopPropagation(); togglePinChat('${item.key}')">${isPinned?'Unpin Chat':'Pin Chat'}</div>
                                            <div onclick="event.stopPropagation(); deleteConvoForMe('${item.other_id}', '${item.type}')">Delete Conversation</div>
                                        </div>
                                    </div>
                                    <small style="color: #666; font-size: 10px; flex-shrink: 0;">${timeStr}</small> 
                                </div>
                            </div>
                        `;
                    });
                    
                    // INVITATION LIST LOGIC
                    if (currentSidebarCategory === 'invites') {
                        fetch('handlers/sacli_room_handler.php', { method: 'POST', body: new URLSearchParams('action=get_my_invitations') })
                        .then(r => r.json())
                        .then(invites => {
                            if(invites.length === 0) {
                                container.innerHTML = '<div style="text-align:center; padding:40px; color:#555; font-family:var(--terminal-font);">NO_PENDING_INVITATIONS</div>';
                                return;
                            }
                            let inviteHTML = '';
                            invites.forEach(inv => {
                                inviteHTML += `
                                    <div class="conv-item active" style="display:flex; align-items:flex-start; gap:15px; cursor:default; padding: 15px;">
                                        <div style="position:relative; width:45px; height:45px; flex-shrink:0;">
                                            <img src="folder.png" style="width:100%; height:100%; border-radius:10px; border: 1.5px solid var(--neon-green); background:rgba(0,255,170,0.1); padding:6px; box-shadow: 0 0 10px rgba(0,255,170,0.2);">
                                            <div style="position:absolute; top:-5px; left:-5px; background:#ff4757; width:16px; height:16px; border-radius:50%; border:2px solid #0a1f16; color:white; font-size:10px; font-weight:bold; display:flex; align-items:center; justify-content:center; animation: statusPulse 2s infinite; box-shadow: 0 0 10px #ff4757;">!</div>
                                        </div>
                                        <div style="flex:1; overflow: hidden;">
                                            <strong style="font-size:14px; color:var(--neon-green); display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-transform: uppercase; letter-spacing: 0.5px;">${inv.room_name}</strong>
                                            <small style="display:block; color:#aaa; margin-bottom: 12px; font-size:11px;">Invited by ${inv.teacher_name}</small>
                                            <div style="display:flex; gap:8px; width:100%;">
                                                <button class="filter-pill active" style="flex:1; border-radius:6px; padding: 8px 0; font-size: 10px;" onclick="respondInvite(${inv.room_id}, 'accept')">ACCEPT</button>
                                                <button class="filter-pill" style="flex:1; border-radius:6px; padding: 8px 0; font-size: 10px; background:rgba(255,71,87,0.1); border-color:#ff4757; color:#ff4757;" onclick="respondInvite(${inv.room_id}, 'decline')">DECLINE</button>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            container.innerHTML = inviteHTML;
                        });
                        return; // Exit main loop to let the invite fetcher handle it
                    }

                    // Update only if content changed to avoid flickering
                    if(container.dataset.lastHash !== newHTML) {
                        container.innerHTML = newHTML;
                        container.dataset.lastHash = newHTML;

                        // Remove highlight class after animation for newly added elements
                        document.querySelectorAll('.new-message-highlight').forEach(el => {
                            setTimeout(() => {
                                el.classList.remove('new-message-highlight');
                            }, 2000); // Match animation duration
                        });
                    }
                    previousUnreadCounts = currentUnreadCounts; // Update global state for next fetch
                });
            });
        }

        function respondInvite(roomId, response) {
            // I-disable muna ang buttons para hindi ma-double click
            showFlash(response === 'accept' ? "Joining room..." : "Declining invitation...", "success");
            
            let fd = new FormData();
            fd.append('action', 'respond_room_invite');
            fd.append('room_id', roomId);
            fd.append('response', response);
            
            fetch('handlers/sacli_room_handler.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => { 
                showFlash(data.message, data.status === 'success' ? 'success' : 'error'); 
                loadSidebar(); // Refresh para mawala ang item sa listahan
            });
        }

        // --- MUTE LOGIC ---
        function isChatMuted(id, type) {
            const muted = JSON.parse(localStorage.getItem('muted_chats') || '{}');
            return muted[`${type}-${id}`] === true;
        }

        function toggleMuteChat() {
            if (!activeId) return;
            const muted = JSON.parse(localStorage.getItem('muted_chats') || '{}');
            const key = `${activeType}-${activeId}`;
            
            if (muted[key]) {
                delete muted[key];
                showFlash("Chat Unmuted");
            } else {
                muted[key] = true;
                showFlash("Chat Muted");
            }
            
            localStorage.setItem('muted_chats', JSON.stringify(muted));
            updateMuteUI();
            loadSidebar(); // Refresh sidebar UI immediately
        }

        function updateMuteUI() {
            const isMuted = isChatMuted(activeId, activeType);
            const label = document.getElementById('psMuteLabel');
            const btn = document.getElementById('psMuteBtn');
            
            label.innerText = isMuted ? 'Unmute' : 'Mute';
            btn.style.background = isMuted ? 'var(--neon-green)' : '#222';
            btn.querySelector('svg').style.stroke = isMuted ? '#0a1f16' : 'white';
        }

        function openFullChat(id, type, name, pic) {
            activeId = id;
            activeType = type;

            // Trigger mobile view switch
            if (window.innerWidth <= 768) document.body.classList.add('show-chat');
            
            // Update URL without reload
            const newUrl = `SacliChat_Full.php?id=${id}&type=${type}`;
            window.history.pushState({path:newUrl},'',newUrl);

            // Clear current chat view to prepare for new data
            const body = document.getElementById('fullChatBody');
            body.innerHTML = '';
            delete body.dataset.lastContent; // Force update on next fetch

            // SPA Switch: Hide welcome, show chat view with animation
            document.getElementById('welcomeView').style.display = 'none';
            const chatView = document.getElementById('chatView');
            chatView.style.display = 'flex';
            chatView.classList.add('fade-in-view');
            setTimeout(() => chatView.classList.remove('fade-in-view'), 600);

            document.getElementById('activeName').innerText = name;
            document.getElementById('activePic').src = pic;

            document.getElementById('psActiveName').innerText = name;
            document.getElementById('psActivePic').src = pic;
            document.getElementById('psMediaGrid').style.display = 'none';
            document.getElementById('psFilesList').style.display = 'none';
            
            updateMuteUI();
            loadChatParticipants(); // Load participants for mention suggestions
            currentAppliedTheme = ''; // Force update on switch
            fetchChatTheme();
            fetchMessages(true);
            checkTypingStatus(); // Initial check
            if(chatInterval) clearInterval(chatInterval);
            if(typingInterval) clearInterval(typingInterval);
            chatInterval = setInterval(fetchMessages, 3000);
            typingInterval = setInterval(checkTypingStatus, 1000);
            loadSidebar(); 
        }

        function fetchMessages(forceScroll = false) {
            if(!activeId) return;
            let formData = new FormData();
            let handler = (activeType === 'group') ? 'handlers/group_chat_handler.php' : 'handlers/chat_handler.php';
            
            formData.append('action', 'fetch');
            if(activeType === 'group') formData.append('group_id', activeId);
            else formData.append('receiver_id', activeId);

            fetch(handler, { method: 'POST', body: formData })
            .then(res => res.text())
            .then(html => {
                const body = document.getElementById('fullChatBody');

                // Gumamit ng temporary element para i-parse ang bagong HTML at ikumpara ang structure
                const temp = document.createElement('div');
                temp.innerHTML = html;
                const newMsgs = temp.querySelectorAll('.msg-container');
                const currentMsgs = body.querySelectorAll('.msg-container');

                // I-detect kung may totoong pagbabago: bilang ng messages O kung nag-update ang huling message (Seen/Delivered)
                let needsUpdate = (newMsgs.length !== currentMsgs.length);
                
                // Kung pareho ang bilang, i-check kung ang huling message ay nagbago ang content (status update o edit)
                if (!needsUpdate && newMsgs.length > 0) {
                    const lastIdx = newMsgs.length - 1;
                    if (newMsgs[lastIdx].innerHTML !== currentMsgs[lastIdx].innerHTML) {
                        needsUpdate = true;
                    }
                }

                if (needsUpdate || forceScroll) {
                    const isAtBottom = (body.scrollHeight - body.scrollTop - body.clientHeight) < 250;
                    const oldMsgsCount = currentMsgs.length;
                    body.innerHTML = html;

                    // I-play ang sound kung may bagong message na pumasok galing sa ka-chat
                    if (newMsgs.length > oldMsgsCount && !forceScroll) {
                        const latest = body.querySelector('.msg-container:last-child');
                        if (latest && latest.classList.contains('other')) {
                            playNotifSound();
                            // Kung hindi naka-scroll sa baba O nag-ta-type ang user, ipakita ang "New Message" button
                            if (!isAtBottom || document.activeElement.id === 'fullChatInput') {
                                document.getElementById('newMsgScrollBtn').classList.add('show');
                            }
                        }
                    }

                    if(forceScroll || (isAtBottom && document.activeElement.id !== 'fullChatInput')) {
                        setTimeout(() => { body.scrollTop = body.scrollHeight; }, 50);
                        document.getElementById('newMsgScrollBtn').classList.remove('show');
                    }
                }

                // I-sync ang theme para mag-auto update pag binago ng ka-chat (3s interval)
                fetchChatTheme();
            });
        }

        function scrollToBottomManual() {
            const body = document.getElementById('fullChatBody');
            body.scrollTo({ top: body.scrollHeight, behavior: 'smooth' });
            document.getElementById('newMsgScrollBtn').classList.remove('show');
        }

        let typingSignalTimeout = null;
        function autoResize(textarea) {
            textarea.style.height = '45px'; // I-reset muna sa baseline
            let newHeight = textarea.scrollHeight;
            textarea.style.height = newHeight + 'px';
            if (newHeight > 150) {
                textarea.style.overflowY = 'auto'; // Lalabas ang scrollbar pag lumampas sa 150px
            } else {
                textarea.style.overflowY = 'hidden';
            }
        }

        function handleFullChatKey(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault(); // Iwasan ang bagong linya sa Enter (Send mode)
                sendMessageFull();
            }
        }

        let isTypingActive = false; 
        let lastSignalTime = 0;
        function signalTyping() {
            if(!activeId) return;
            const now = Date.now();

            // I-send ang signal kung wala pang active session O kung lumipas na ang 2 segundo para ma-refresh ang status
            if (!isTypingActive || (now - lastSignalTime > 2000)) {
                isTypingActive = true;
                lastSignalTime = now;
                let formData = new FormData();
                formData.append('action', 'update_typing');
                formData.append('target_id', activeId);
                formData.append('chat_type', activeType);
                fetch('handlers/chat_handler.php', { method: 'POST', body: formData });
            }

            clearTimeout(typingSignalTimeout);
            typingSignalTimeout = setTimeout(() => {
                stopTyping();
                isTypingActive = false;
            }, 3000); // Itaas sa 3 segundo para hindi mawala agad ang dots habang nag-iisip ang user
        }

        function stopTyping() {
            let formData = new FormData();
            formData.append('action', 'stop_typing');
            fetch('handlers/chat_handler.php', { method: 'POST', body: formData });
        }

        function checkTypingStatus() {
            if(!activeId) return;
            let formData = new FormData();
            formData.append('action', 'check_typing');
            formData.append('receiver_id', activeId);
            formData.append('chat_type', activeType);
            fetch('handlers/chat_handler.php', { method: 'POST', body: formData })
            .then(res => res.text()).then(status => {
                const indicator = document.getElementById('typingIndicator');
                const nameLabel = document.getElementById('typingName');
                const picImg = document.getElementById('typingPic');
                const chatBody = document.getElementById('fullChatBody');

                if(status === 'false') {
                    indicator.style.display = 'none';
                } else {
                    const isAtBottom = (chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight) < 250;
                    indicator.style.display = 'block';

                    if(activeType === 'direct') {
                        nameLabel.innerText = ''; 
                        picImg.src = document.getElementById('activePic').src;
                        picImg.style.display = 'block';
                    } else {
                        // Group Chat
                        nameLabel.innerText = status;
                        const participant = currentChatParticipants.find(p => p.name.toLowerCase() === status.toLowerCase());
                        if(participant) {
                            picImg.src = participant.profile_pic ? "uploads/" + participant.profile_pic : (participant.id.startsWith('T-') ? "4icons8-teacher-50.png" : "assets/images/3icons8-student-64.png");
                            picImg.style.display = 'block';
                        } else {
                            picImg.style.display = 'none';
                        }
                    }
                    if(isAtBottom) {
                        setTimeout(() => { chatBody.scrollTop = chatBody.scrollHeight; }, 50);
                    }
                }
            });
        }

        function playNotifSound() {
            const sound = document.getElementById('notifSound');
            if (sound) sound.play().catch(e => console.log("Audio play blocked by browser."));
        }

        let fullChatPendingFiles = [];

        function previewFullChatFile(type) {
            const input = (type === 'doc') ? document.getElementById('fullChatDocInput') : document.getElementById('fullChatFileInput');
            const files = Array.from(input.files);
            
            files.forEach(file => {
                fullChatPendingFiles.push(file);
            });
            
            input.value = '';
            renderFullChatPreviews();
        }

        function renderFullChatPreviews() {
            const area = document.getElementById('fullChatFilePreviewArea');
            if (fullChatPendingFiles.length === 0) {
                area.style.display = 'none';
                return;
            }
            
            area.style.display = 'flex';
            area.innerHTML = '';
            
            fullChatPendingFiles.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'chat-preview-item';
                
                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-preview';
                removeBtn.innerHTML = '×';
                removeBtn.onclick = () => {
                    fullChatPendingFiles.splice(index, 1);
                    renderFullChatPreviews();
                };
                item.appendChild(removeBtn);
                
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    item.appendChild(img);
                } else if (file.type.startsWith('video/')) {
                    const vid = document.createElement('video');
                    vid.src = URL.createObjectURL(file);
                    item.appendChild(vid);
                } else {
                    const icon = document.createElement('div');
                    icon.style.cssText = 'display:flex; align-items:center; justify-content:center; height:100%; font-size:24px;';
                    icon.innerHTML = '📄';
                    item.appendChild(icon);
                    
                    const label = document.createElement('div');
                    label.className = 'file-name-tag';
                    label.innerText = file.name;
                    item.appendChild(label);
                }
                area.appendChild(item);
            });
        }

        function clearFullChatFile() {
            fullChatPendingFiles = [];
            document.getElementById('fullChatFilePreviewArea').style.display = 'none';
        }

        function sendMessageFull() {
            const input = document.getElementById('fullChatInput');
            const mentionedUsersContainer = document.getElementById('fullChatMentionedUsers');

            const msg = input.value.trim();

            if(!msg && fullChatPendingFiles.length === 0) return;
            if(!activeId) return;

            let formData = new FormData();
            let handler = (activeType === 'group') ? 'handlers/group_chat_handler.php' : 'handlers/chat_handler.php';
            
            formData.append('action', 'send');
            formData.append('message', msg);
            if(activeType === 'group') formData.append('group_id', activeId);
            else formData.append('receiver_id', activeId);

            fullChatPendingFiles.forEach(file => formData.append('media[]', file));

            // Append mentioned user IDs
            const mentionedUserIds = Array.from(mentionedUsersContainer.children).map(el => el.value);
            mentionedUserIds.forEach(id => formData.append('mentioned_users[]', id));

            // Clear mentioned users after sending
            mentionedUsersContainer.innerHTML = '';
            mentionSuggestionsFull.style.display = 'none';

            stopTyping(); // Stop typing indicator immediately when sent
            isTypingActive = false;

            fetch(handler, { method: 'POST', body: formData }).then(() => {
                input.value = '';
                input.style.height = '45px'; // I-reset ang height pagka-send
                input.style.overflowY = 'hidden';
                clearFullChatFile();
                fetchMessages(true);
            });
        }

        function deleteConvoForMe(otherId, type) {
            showCustomConfirm("Clear this conversation history? This will only remove the messages for you.", () => {
                let formData = new FormData();
                formData.append('action', 'delete_convo_for_me');
                formData.append('other_id', otherId);
                formData.append('chat_type', type);
                
                fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
                .then(res => res.text())
                .then(resp => {
                    if(resp.trim() === 'success') {
                        showFlash("Conversation deleted");
                        // Reset view if the deleted convo was the active one
                        if(activeId == otherId && activeType == type) {
                            activeId = null;
                            document.getElementById('chatView').style.display = 'none';
                            document.getElementById('welcomeView').style.display = 'flex';
                            const newUrl = `SacliChat_Full.php`;
                            window.history.pushState({path:newUrl},'',newUrl);
                        }
                        loadSidebar();
                    }
                });
            });
        }

        function filterSidebar() {
            const q = document.getElementById('sideSearch').value.toLowerCase();
            const items = document.querySelectorAll('.conv-item');
            
            if (q.trim() === '') {
                items.forEach(i => i.style.display = 'flex');
                return;
            }

            // Step 1: Local filter (Pangalan at ang nakikitang huling message)
            items.forEach(item => {
                const name = item.querySelector('strong').innerText.toLowerCase();
                const snippet = item.querySelector('small').innerText.toLowerCase();
                if (name.includes(q) || snippet.includes(q)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });

            // Step 2: Deep Search (I-scan ang buong history sa database)
            clearTimeout(window.searchTimer);
            if (q.length >= 3) { // Magsisimulang mag-search sa database kapag may 3 characters na
                window.searchTimer = setTimeout(() => {
                    let formData = new FormData();
                    formData.append('action', 'search_conversations_deep');
                    formData.append('query', q);
                    
                    fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(results => {
                        results.forEach(res => {
                            const el = document.querySelector(`.conv-item[data-chat-id="${res.id}"][data-chat-id="${res.id}"][data-chat-type="${res.type}"]`);
                            if (el) el.style.display = 'flex';
                        });
                    });
                }, 500);
            }
        }

        function toggleMsgMenu(event, id) {
            event.stopPropagation();
            document.querySelectorAll('.msg-menu.active').forEach(m => {
                if(m.id !== 'msg-menu-'+id) m.classList.remove('active');
            });
            const menu = document.getElementById('msg-menu-' + id);
            if(menu) menu.classList.toggle('active');
        }

        // --- Persistent Theme Logic ---
        let currentAppliedTheme = 'default';
        function applyThemeClasses(theme) {
            if (theme === currentAppliedTheme) return;
            currentAppliedTheme = theme;

            const body = document.getElementById('fullChatBody');
            const view = document.getElementById('chatView');
            
            body.classList.remove('theme-flashlight', 'theme-rain');
            view.classList.remove('theme-space', 'theme-rain', 'theme-midnight', 'theme-geometric');
            
            if(theme === 'flashlight') {
                body.classList.add('theme-flashlight');
            } else if(theme === 'space') {
                view.classList.add('theme-space');
            } else if(theme === 'rain') {
                view.classList.add('theme-rain');
                body.classList.add('theme-rain');
            } else if(theme === 'midnight') {
                view.classList.add('theme-midnight');
            } else if(theme === 'geometric') {
                view.classList.add('theme-geometric');
            }
        }

        function toggleProfileSidebar() {
            const sidebar = document.getElementById('profileSidebar');
            const isClosing = sidebar.classList.contains('open');
            sidebar.classList.toggle('open');
            
            // Pag sinara ang sidebar at may bagong theme na napili
            if (isClosing && window.pendingThemeChange) {
                sendThemeSystemMessage(window.pendingThemeChange);
                window.pendingThemeChange = null;
            }
        }

        function viewActiveProfile() {
            if (activeId && activeType === 'direct') {
                window.location.href = 'Student_Profile.php?id=' + activeId;
            }
        }

        function sendThemeSystemMessage(themeName) {
            if(!activeId) return;
            let formData = new FormData();
            let handler = (activeType === 'group') ? 'handlers/group_chat_handler.php' : 'handlers/chat_handler.php';
            formData.append('action', 'send_system_message');
            formData.append('message', `changed the theme to ${themeName}`);
            if(activeType === 'group') formData.append('group_id', activeId);
            else formData.append('receiver_id', activeId);

            fetch(handler, { method: 'POST', body: formData }).then(() => fetchMessages(true));
        }

        function fetchChatTheme() {
            if(!activeId) return;
            let formData = new FormData();
            let handler = (activeType === 'group') ? 'handlers/group_chat_handler.php' : 'handlers/chat_handler.php';
            formData.append('action', 'get_theme');
            if(activeType === 'group') formData.append('group_id', activeId);
            else formData.append('receiver_id', activeId);

            fetch(handler, { method: 'POST', body: formData })
            .then(res => res.text())
            .then(theme => {
                applyThemeClasses(theme.trim());
            });
        }

        function pinMessage(msgId, pinVal) {
            let formData = new FormData();
            let handler = (activeType === 'group') ? 'handlers/group_chat_handler.php' : 'handlers/chat_handler.php';
            formData.append('action', 'pin_message');
            formData.append('msg_id', msgId);
            formData.append('is_pinned', pinVal);

            fetch(handler, { method: 'POST', body: formData })
            .then(res => res.text())
            .then(resp => {
                if(resp.trim() === 'success') {
                    showFlash(pinVal ? "Message pinned" : "Message unpinned");
                    fetchMessages(false);
                }
            });
        }

        function viewPinnedMessages() {
            if(!activeId) return;
            document.getElementById('pinnedModal').style.display = 'flex';
            const container = document.getElementById('pinnedListContainer');
            container.innerHTML = '<div style="text-align:center; padding:20px; color:var(--neon-green);" class="loading-nodes">RETRIEVING_DATA...</div>';

            let formData = new FormData();
            let handler = (activeType === 'group') ? 'handlers/group_chat_handler.php' : 'handlers/chat_handler.php';
            formData.append('action', 'fetch_pinned_messages');
            if(activeType === 'group') formData.append('group_id', activeId);
            else formData.append('receiver_id', activeId);

            fetch(handler, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                container.innerHTML = '';
                if(data.length === 0) {
                    container.innerHTML = '<div style="text-align:center; color:#555; padding:20px;">NO_PINNED_MESSAGES</div>';
                    return;
                }
                data.forEach(m => {
                    const pic = m.profile_pic ? "uploads/"+m.profile_pic : (m.sender_id.startsWith('T-') ? "4icons8-teacher-50.png" : "assets/images/3icons8-student-64.png");
                    const div = document.createElement('div');
                    div.className = 'pinned-item';
                    div.onclick = () => {
                        closePinnedModal();
                        const el = document.getElementById('msg-anchor-' + m.id);
                        if(el) {
                            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            el.classList.add('msg-jump-target');
                            setTimeout(() => el.classList.remove('msg-jump-target'), 2500);
                        } else {
                            showFlash("Original message not found in history.", "error");
                        }
                    };
                    div.innerHTML = `
                        <div class="pinned-item-meta">
                            <img src="${pic}">
                            <strong style="text-transform: capitalize;">${m.sender_name.toLowerCase()}</strong>
                            <small>${new Date(m.timestamp).toLocaleString()}</small>
                        </div>
                        <div class="pinned-item-text">${m.message}</div>
                    `;
                    container.appendChild(div);
                });
            });
        }

        function closePinnedModal() {
            document.getElementById('pinnedModal').style.display = 'none';
        }

        function togglePsSectionById(id) {
            const sidebar = document.getElementById('profileSidebar');
            // Siguraduhin na bukas ang sidebar
            if (!sidebar.classList.contains('open')) {
                toggleProfileSidebar();
            }
            const sec = document.getElementById(id);
            if(sec) {
                sec.classList.add('active');
                // Mag-scroll papunta sa section title sa loob ng sidebar
                setTimeout(() => {
                    sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 300);
            }
            if(id === 'ps-search-messages') {
                setTimeout(() => { document.getElementById('msgSearchInput').focus(); }, 500);
            }
        }

        window.searchMessagesInChat = function() {
            const query = document.getElementById('msgSearchInput').value.toLowerCase();
            const resultsContainer = document.getElementById('msgSearchResults');
            resultsContainer.innerHTML = '';
            
            if (query.trim() === '') return;

            const messages = document.querySelectorAll('#fullChatBody .msg');
            let foundCount = 0;

            messages.forEach((msgEl) => {
                const text = msgEl.innerText.toLowerCase();
                if (text.includes(query)) {
                    foundCount++;
                    const resultItem = document.createElement('div');
                    resultItem.className = 'ps-option-item';
                    resultItem.style.cssText = 'padding: 12px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.05); cursor: pointer; display: block;';
                    
                    let snippet = msgEl.innerText;
                    resultItem.innerHTML = `
                        <div style="color:var(--neon-green); font-size:10px; margin-bottom:4px; font-family:var(--terminal-font);">MATCH_FOUND:</div>
                        <div style="color:#eee; line-height:1.4;">${snippet}</div>
                    `;
                    resultItem.onclick = () => {
                        msgEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        msgEl.style.filter = 'brightness(2) drop-shadow(0 0 10px #00ffaa)';
                        setTimeout(() => { msgEl.style.filter = 'none'; }, 2000);
                    };
                    resultsContainer.appendChild(resultItem);
                }
            });

            if (foundCount === 0) {
                resultsContainer.innerHTML = '<div style="text-align:center; padding:15px; color:#555; font-family:var(--terminal-font); font-size:11px;">NO_MATCHES_FOUND</div>';
            }
        }

        // --- MENTION LOGIC FOR FULL CHAT INPUT ---
        const fullChatInput = document.getElementById('fullChatInput');
        const mentionSuggestionsFull = document.getElementById('mentionSuggestionsFull');
        const fullChatMentionedUsers = document.getElementById('fullChatMentionedUsers');

        let currentChatParticipants = []; // To store participants for mention suggestions

        // Function to load current chat participants (for mention suggestions)
        function loadChatParticipants() {
            if (!activeId) return;
            let formData = new FormData();
            let handler = (activeType === 'group') ? 'handlers/group_chat_handler.php' : 'handlers/chat_handler.php'; // Assuming chat_handler can also fetch participants for direct
            
            formData.append('action', 'get_chat_participants'); // New action needed in handlers
            if (activeType === 'group') formData.append('group_id', activeId);
            else formData.append('receiver_id', activeId);

            fetch(handler, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                currentChatParticipants = data;
            })
            .catch(e => console.error("Error loading chat participants:", e));
        }

        function handleFullChatInputMention() {
            const text = fullChatInput.value;
            const cursorPosition = fullChatInput.selectionStart;
            const textBeforeCursor = text.substring(0, cursorPosition);
            const lastAt = textBeforeCursor.lastIndexOf('@');

            if (lastAt !== -1) {
                const charBeforeAt = lastAt > 0 ? textBeforeCursor[lastAt - 1] : ' ';
                if (charBeforeAt === ' ' || charBeforeAt === '\n' || lastAt === 0) {
                    const query = textBeforeCursor.substring(lastAt + 1);
                    if (!query.includes(' ')) { // Ensure query doesn't contain spaces
                        const filteredUsers = currentChatParticipants.filter(p => 
                            p.name.toLowerCase().startsWith(query.toLowerCase()) && p.id !== "<?php echo $my_id; ?>"
                        );

                        mentionSuggestionsFull.innerHTML = '';
                        if (filteredUsers.length > 0) {
                            mentionSuggestionsFull.style.display = 'block';
                            filteredUsers.forEach(user => {
                                const pic = user.profile_pic ? "uploads/" + user.profile_pic : (user.id.startsWith('T-') ? "4icons8-teacher-50.png" : "assets/images/3icons8-student-64.png");
                                const div = document.createElement('div');
                                div.className = 'mention-item-full';
                                div.innerHTML = `<img src="${pic}"><span>${user.name}</span>`;
                                div.onclick = () => selectFullChatMention(user, lastAt, query.length);
                                mentionSuggestionsFull.appendChild(div);
                            });
                        } else {
                            mentionSuggestionsFull.style.display = 'none';
                        }
                        return;
                    }
                }
            }
            mentionSuggestionsFull.style.display = 'none';
        }

        function selectFullChatMention(user, atIndex, queryLen) {
            const text = fullChatInput.value;
            const before = text.substring(0, atIndex);
            const after = text.substring(atIndex + 1 + queryLen);
            fullChatInput.value = before + '@' + user.name + ' ' + after;
            mentionSuggestionsFull.style.display = 'none';
            fullChatInput.focus();

            // Add hidden input for backend processing
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'mentioned_users[]';
            input.value = user.id;
            fullChatMentionedUsers.appendChild(input);
        }

        function togglePsSection(titleEl) {
            titleEl.parentElement.classList.toggle('active');
        }

        window.pendingThemeChange = null;
        function setChatTheme(theme) {
            if(!activeId) return;
            applyThemeClasses(theme);
            
            // I-map ang keys sa readable names
            const themeNames = {
                'default': 'Default Neural',
                'flashlight': 'Flashlight Dark',
                'space': 'Celestial Space',
                'rain': 'Stormy Rain',
                'midnight': 'Midnight Sky',
                'geometric': 'Night Geometric'
            };
            window.pendingThemeChange = themeNames[theme] || theme;

            let formData = new FormData();
            let handler = (activeType === 'group') ? 'handlers/group_chat_handler.php' : 'handlers/chat_handler.php';
            formData.append('action', 'save_theme');
            formData.append('theme', theme);
            if(activeType === 'group') formData.append('group_id', activeId);
            else formData.append('receiver_id', activeId);

            fetch(handler, { method: 'POST', body: formData });
            showFlash("Theme updated: " + theme);
        }

        function togglePsSubSection(type) {
            const grid = document.getElementById('psMediaGrid');
            const list = document.getElementById('psFilesList');
            
            if (type === 'media') {
                if (grid.style.display === 'grid') {
                    grid.style.display = 'none';
                } else {
                    grid.style.display = 'grid';
                    list.style.display = 'none';
                    fetchSidebarAssets();
                }
            } else {
                if (list.style.display === 'flex') {
                    list.style.display = 'none';
                } else {
                    list.style.display = 'flex';
                    grid.style.display = 'none';
                    fetchSidebarAssets();
                }
            }
        }

        function fetchSidebarAssets() {
            if(!activeId) return;
            let formData = new FormData();
            let handler = (activeType === 'group') ? 'handlers/group_chat_handler.php' : 'handlers/chat_handler.php';
            
            formData.append('action', 'fetch_sidebar_assets');
            if(activeType === 'group') formData.append('group_id', activeId);
            else formData.append('receiver_id', activeId);

            fetch(handler, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                const grid = document.getElementById('psMediaGrid');
                const list = document.getElementById('psFilesList');
                
                grid.innerHTML = '';
                list.innerHTML = '';

                let mediaCount = 0;
                let filesCount = 0;

                const mediaItems = data.filter(item => item.type === 'photo' || item.type === 'video');
                const fileItems = data.filter(item => item.type !== 'photo' && item.type !== 'video');
                window.sidebarMediaAssets = mediaItems;

                mediaItems.forEach((item, idx) => {
                    mediaCount++;
                    const div = document.createElement('div');
                    div.className = 'ps-media-item';
                    div.style.animationDelay = (idx * 0.05) + 's';
                    div.onclick = () => openSidebarLightbox(idx);

                    let content = item.type === 'photo' ? `<img src="${item.path}">` : `<video src="${item.path}"></video>`;
                    div.innerHTML = `${content} <div class="ps-media-overlay"><span class="ps-media-date">${item.date}</span></div>`;
                    grid.appendChild(div);
                });

                fileItems.forEach(item => {
                    filesCount++;
                    const a = document.createElement('a');
                    a.href = item.path; a.target = '_blank';
                    a.className = 'ps-file-link';
                    a.innerHTML = `<span class="ps-option-icon">📄</span> <div><div style="font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:180px;">${item.name}</div><small style="opacity:0.5;">${item.date}</small></div>`;
                    list.appendChild(a);
                });

                if (mediaCount === 0) grid.innerHTML = '<div style="grid-column: span 3; font-size: 11px; opacity: 0.5; padding: 10px 5px;">NO_MEDIA_FOUND</div>';
                if (filesCount === 0) list.innerHTML = '<div style="font-size: 11px; opacity: 0.5; padding: 5px 0;">NO_FILES_FOUND</div>';
            }).catch(e => console.error("Asset sync failed"));
        }

        // --- Sidebar Media Lightbox Logic ---
        let currentSidebarMediaIdx = 0;
        function openSidebarLightbox(idx) {
            playClickSound();
            currentSidebarMediaIdx = idx;
            showSidebarMedia();
            document.getElementById('sidebarLightbox').style.display = 'block';
        }
        function closeSidebarLightbox() {
            document.getElementById('sidebarLightbox').style.display = 'none';
            document.getElementById('lightboxMediaContainer').innerHTML = '';
        }
        function showSidebarMedia() {
            const item = window.sidebarMediaAssets[currentSidebarMediaIdx];
            const container = document.getElementById('lightboxMediaContainer');
            const counter = document.getElementById('sidebarLightboxCounter');
            
            let contentHTML = '';
            if (item.type === 'photo') {
                contentHTML = `<img src="${item.path}" class="lightbox-media">`;
            } else if (item.type === 'video') {
                contentHTML = `<video src="${item.path}" class="lightbox-media" controls autoplay></video>`;
            } else {
                const ext = item.path.split('.').pop().toLowerCase();
                if (ext === 'pdf') {
                    contentHTML = `<iframe src="${item.path}" class="lightbox-media" style="width:80vw; height:80vh; border:none; background:#fff; border-radius:10px;"></iframe>`;
                } else {
                    contentHTML = `
                        <div style="text-align:center; padding:40px; background:rgba(0, 255, 170, 0.05); border: 2px dashed var(--neon-green); border-radius: 20px; color:#fff; min-width:300px;">
                            <div style="font-size:60px; margin-bottom:20px;">📄</div>
                            <div style="font-size:18px; font-family:var(--terminal-font); margin-bottom:25px; word-break:break-all;">${item.name || 'SECURE_DATA_NODE'}</div>
                            <a href="${item.path}" download class="filter-pill active" style="text-decoration:none; padding:15px 40px; font-size:14px; display:inline-block; border-radius:10px;">DOWNLOAD_PAYLOAD</a>
                        </div>`;
                }
            }

            container.innerHTML = contentHTML;
            counter.innerText = `DATA_STREAM: ${currentSidebarMediaIdx + 1} / ${window.sidebarMediaAssets.length} // SENT: ${item.date}`;
        }
        function nextSidebarSlide() {
            currentSidebarMediaIdx = (currentSidebarMediaIdx + 1) % window.sidebarMediaAssets.length;
            showSidebarMedia();
        }
        function prevSidebarSlide() {
            currentSidebarMediaIdx = (currentSidebarMediaIdx - 1 + window.sidebarMediaAssets.length) % window.sidebarMediaAssets.length;
            showSidebarMedia();
        }

        // --- Message Media Lightbox Logic ---
        let currentMessageMediaList = [];
        let currentMessageMediaIdx = 0;
        function openMessageLightbox(messageId, idx, chatType) {
            playClickSound();
            currentMessageMediaIdx = idx;
            
            let formData = new FormData();
            formData.append('action', 'fetch_message_media');
            formData.append('message_id', messageId);
            formData.append('chat_type', chatType);

            fetch('handlers/chat_handler.php', { method: 'POST', body: formData }) // Use chat_handler for both direct/group
            .then(res => res.json())
            .then(data => {
                currentMessageMediaList = data;
                showMessageMedia();
                document.getElementById('messageLightbox').style.display = 'block';
            });
        }
        function closeMessageLightbox() {
            document.getElementById('messageLightbox').style.display = 'none';
            document.getElementById('messageLightboxMediaContainer').innerHTML = '';
        }
        function showMessageMedia() {
            const item = currentMessageMediaList[currentMessageMediaIdx];
            const container = document.getElementById('messageLightboxMediaContainer');
            const counter = document.getElementById('messageLightboxCounter');
            
            let contentHTML = '';
            if (item.file_type === 'photo') {
                contentHTML = `<img src="${item.file_path}" class="lightbox-media">`;
            } else if (item.file_type === 'video') {
                contentHTML = `<video src="${item.file_path}" class="lightbox-media" controls autoplay></video>`;
            } else {
                const ext = item.file_path.split('.').pop().toLowerCase();
                if (ext === 'pdf') {
                    contentHTML = `<iframe src="${item.file_path}" class="lightbox-media" style="width:80vw; height:80vh; border:none; background:#fff; border-radius:10px;"></iframe>`;
                } else {
                    contentHTML = `
                        <div style="text-align:center; padding:40px; background:rgba(0, 255, 170, 0.05); border: 2px dashed var(--neon-green); border-radius: 20px; color:#fff; min-width:300px;">
                            <div style="font-size:60px; margin-bottom:20px;">📄</div>
                            <div style="font-size:16px; font-family:var(--terminal-font); margin-bottom:25px; word-break:break-all;">${item.original_filename || 'DATA_PACKET_ENCRYPTED'}</div>
                            <a href="${item.file_path}" download class="filter-pill active" style="text-decoration:none; padding:15px 40px; font-size:14px; display:inline-block; border-radius:10px;">EXTRACT_CONTENT</a>
                        </div>`;
                }
            }

            container.innerHTML = contentHTML;
            counter.innerText = `DATA_STREAM: ${currentMessageMediaIdx + 1} / ${currentMessageMediaList.length} // SENT: ${item.date}`;
        }
        function nextMessageSlide() { currentMessageMediaIdx = (currentMessageMediaIdx + 1) % currentMessageMediaList.length; showMessageMedia(); }
        function prevMessageSlide() { currentMessageMediaIdx = (currentMessageMediaIdx - 1 + currentMessageMediaList.length) % currentMessageMediaList.length; showMessageMedia(); }

        function showFlash(msg, type = 'success') {
            let flash = document.createElement('div');
            flash.className = 'flash-message ' + type;
            flash.innerText = msg;
            document.body.appendChild(flash);
            void flash.offsetWidth;
            flash.classList.add('show');
            setTimeout(() => {
                flash.classList.remove('show');
                setTimeout(() => flash.remove(), 500);
            }, 3000);
        }

        function togglePinChat(key) {
            let pinned = JSON.parse(localStorage.getItem('pinned_convs') || '[]');
            const index = pinned.indexOf(key);
            if (index > -1) {
                pinned.splice(index, 1);
                showFlash("Conversation unpinned");
            } else {
                pinned.unshift(key); // New pin goes to index 0 (top)
                showFlash("Conversation pinned to top");
            }
            localStorage.setItem('pinned_convs', JSON.stringify(pinned));
            playClickSound();
            loadSidebar();
        }

        function toggleConvMenu(event, id) {
            event.stopPropagation();
            document.querySelectorAll('.conv-menu.active').forEach(m => {
                if(m.id !== 'conv-menu-'+id) m.classList.remove('active');
            });
            const menu = document.getElementById('conv-menu-' + id);
            if(menu) menu.classList.toggle('active');
        }

        // --- Directory Logic ---
        let currentDirType = 'student';
        function openDirectoryModal() {
            document.getElementById('directoryModal').style.display = 'flex';
            fetchDirectory();
        }
        function closeDirectoryModal() {
            document.getElementById('directoryModal').style.display = 'none';
            document.getElementById('dirSearchInput').value = '';
        }
        function switchDirTab(type) {
            currentDirType = type;
            document.querySelectorAll('.dir-tab').forEach(t => t.classList.remove('active'));
            const activeTab = document.querySelector(`.dir-tab[data-type="${type}"]`);
            activeTab.classList.add('active');
            
            // Update Slidebar Position
            const slider = document.getElementById('dirSlidebar');
            const index = ['student', 'teacher', 'alumni'].indexOf(type);
            slider.style.transform = `translateX(${index * 100}%)`;

            fetchDirectory();
        }

        function fetchDirectory() {
            const container = document.getElementById('dirListContainer');
            container.innerHTML = '<div class="loading-dir">SYNCHRONIZING_DATA...</div>';
            
            fetch(`chat_directory_fetch.php?type=${currentDirType}`)
            .then(res => res.json())
            .then(data => {
                container.innerHTML = '';
                if(data.length === 0) {
                    container.innerHTML = '<div style="text-align:center; padding:20px; color:#555;">NO_NODES_FOUND</div>';
                    return;
                }
                
                // Add Container Entrance Animation
                container.style.animation = 'none';
                void container.offsetWidth; // trigger reflow
                container.style.animation = 'dirListAppear 0.5s ease-out forwards';

                data.forEach((u, index) => {
                    const pic = u.profile_pic ? "uploads/"+u.profile_pic : (currentDirType == 'teacher' ? "4icons8-teacher-50.png" : "assets/images/3icons8-student-64.png");
                    const statusClass = u.is_online == 1 ? 'online' : 'offline';
                    const div = document.createElement('div');
                    div.className = 'dir-item';
                    div.style.animationDelay = (index * 0.06) + 's'; // Staggered entry
                    div.setAttribute('data-name', u.name.toLowerCase());
                    div.onclick = () => {
                        openFullChat(u.id, u.chat_type, u.name, pic);
                        closeDirectoryModal();
                    };
                    div.innerHTML = `
                        <div class="dir-avatar-box">
                            <img src="${pic}" class="dir-avatar">
                            <div class="dir-status-dot ${statusClass}"></div>
                        </div>
                        <div class="dir-info">
                            <strong>${u.name.toLowerCase()}</strong>
                            <small>${u.is_online == 1 ? 'CONNECTED' : 'OFFLINE'}</small>
                        </div>
                    `;
                    container.appendChild(div);
                });
            });
        }
        function filterDirectory() {
            const q = document.getElementById('dirSearchInput').value.toLowerCase();
            document.querySelectorAll('.dir-item').forEach(item => {
                const name = item.getAttribute('data-name');
                item.style.display = name.includes(q) ? 'flex' : 'none';
            });
        }


        function deleteMsg(id) {
            const msgElement = document.getElementById('msg-anchor-' + id);
            const container = msgElement ? msgElement.closest('.msg-container') : document.querySelector(`.message-media-grid[data-message-id="${id}"]`)?.closest('.msg-container');
            const isMine = container ? container.classList.contains('mine') : false;
            
            const modal = document.getElementById('messengerDeleteModal');
            const unsendBtn = document.getElementById('unsendBtn');
            const deleteForMeBtn = document.getElementById('deleteForMeBtn');

            // User can only unsend their own messages
            unsendBtn.style.display = isMine ? 'block' : 'none';
            
            unsendBtn.onclick = () => executeUnsend(id);
            deleteForMeBtn.onclick = () => executeDeleteForMe(id);
            
            modal.style.display = 'flex';
        }

        function closeMessengerDeleteModal() {
            document.getElementById('messengerDeleteModal').style.display = 'none';
        }

        function executeUnsend(id) {
            let formData = new FormData();
            formData.append('action', 'unsend');
            formData.append('msg_id', id);
            let handler = (activeType === 'group') ? 'handlers/group_chat_handler.php' : 'handlers/chat_handler.php';
            fetch(handler, { method: 'POST', body: formData }).then(() => {
                showFlash("Message unsent");
                closeMessengerDeleteModal();
                fetchMessages(true);
            });
        }

        function executeDeleteForMe(id) {
            let formData = new FormData();
            formData.append('action', 'delete_for_me');
            formData.append('msg_id', id);
            formData.append('chat_type', activeType);
            let handler = (activeType === 'group') ? 'handlers/group_chat_handler.php' : 'handlers/chat_handler.php';
            fetch(handler, { method: 'POST', body: formData }).then(() => {
                showFlash("Message removed for you");
                closeMessengerDeleteModal();
                fetchMessages(true);
            });
        }

        let confirmCallback = null;
        function showCustomConfirm(msg, callback) {
            document.getElementById('customConfirmText').innerText = msg;
            document.getElementById('customConfirmModal').style.display = 'flex';
            confirmCallback = callback;
        }
        function closeCustomConfirm() {
            document.getElementById('customConfirmModal').style.display = 'none';
            confirmCallback = null;
        }
        document.getElementById('confirmYesBtn').onclick = () => { if(confirmCallback) confirmCallback(); closeCustomConfirm(); };


        function editMsg(id, oldText) {
            let newText = prompt("Edit message:", oldText);
            if(newText !== null && newText.trim() !== "") {
                let formData = new FormData();
                formData.append('action', 'edit');
                formData.append('msg_id', id);
                formData.append('message', newText);
                let handler = (activeType === 'group') ? 'handlers/group_chat_handler.php' : 'handlers/chat_handler.php';
                fetch(handler, { method: 'POST', body: formData }).then(() => fetchMessages(true));
            }
        }

        document.addEventListener('click', () => {
            document.querySelectorAll('.msg-menu.active').forEach(m => m.classList.remove('active'));
            document.querySelectorAll('.conv-menu.active').forEach(m => m.classList.remove('active'));
            if(event && event.target == document.getElementById('messengerDeleteModal')) closeMessengerDeleteModal();
        });

        // Initial Load
        document.addEventListener('DOMContentLoaded', () => {
            // Typing Listener
            const inputEl = document.getElementById('fullChatInput');
            if(inputEl) {
                inputEl.addEventListener('input', () => {
                    signalTyping();
                });
            }
            
            // Listener para itago ang New Message button pag nag-scroll na sa baba ang user
            const chatBody = document.getElementById('fullChatBody');
            chatBody.addEventListener('scroll', () => {
                const isAtBottom = (chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight) < 100;
                if (isAtBottom) {
                    document.getElementById('newMsgScrollBtn').classList.remove('show');
                }
            });

            // Animation for New Year
            if (document.body.classList.contains('theme-new_year')) {
                setInterval(() => {
                    const x = Math.random() * window.innerWidth;
                    const y = Math.random() * (window.innerHeight * 0.5);
                    const colors = ['#ff0000', '#ffd700', '#00ff00', '#00ffff', '#ff00ff', '#ffffff'];
                    const color = colors[Math.floor(Math.random() * colors.length)];
                    for(let i=0; i<30; i++) {
                        const p = document.createElement('div');
                        p.classList.add('firework-particle');
                        p.style.left = x + 'px'; p.style.top = y + 'px';
                        p.style.backgroundColor = color;
                        p.style.boxShadow = `0 0 10px ${color}`;
                        const angle = Math.random() * Math.PI * 2;
                        const velocity = Math.random() * 150 + 50;
                        p.style.setProperty('--tx', Math.cos(angle) * velocity + 'px');
                        p.style.setProperty('--ty', Math.sin(angle) * velocity + 'px');
                        document.body.appendChild(p);
                        setTimeout(() => p.remove(), 1500);
                    }
                }, 2000);
            }

            loadSidebar();
            // I-load ang sidebar bawat 5 segundo para sa mga bagong messages galing sa ibang tao
            sidebarInterval = setInterval(loadSidebar, 5000);

            if(activeId) {
                fetchMessages(true);
                chatInterval = setInterval(fetchMessages, 3000);
                typingInterval = setInterval(checkTypingStatus, 1000); // Hiwalay na interval para mas mabilis ma-detect ang typing
                loadChatParticipants();
            }
        });
    </script>
</body>
</html>   