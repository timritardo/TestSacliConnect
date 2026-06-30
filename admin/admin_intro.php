<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../SacliConnect_LOG_IN.php?show=admin");
    exit();
}

require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$admin_id = $_SESSION['admin_id'];
$res = $conn->query("SELECT profile_pic FROM admins2 WHERE id='$admin_id'");
$admin_pic = "76946050_2554845197961929_5561337140505214976_n-removebg-preview.png"; // Default

if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    if (!empty($row['profile_pic'])) {
        $admin_pic = "uploads/" . $row['profile_pic'];
    }
}

// Blackout Protocol Check
$blackout_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='blackout_mode'");
$blackout_active = ($blackout_res && $blackout_res->num_rows > 0 && $blackout_res->fetch_assoc()['setting_value'] == '1');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
<title>Admin Access</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Segoe+UI:wght@400&display=swap" rel="stylesheet">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family: 'Orbitron', sans-serif;
}

body{
    background:#0a1f16;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    overflow:hidden;
    position:relative;
    /* Add a grid background */
    background-image: 
        linear-gradient(rgba(0, 255, 170, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0, 255, 170, 0.05) 1px, transparent 1px);
    background-size: 30px 30px;
}

/* 🔥 Glow Background Pulse */
body::before{
    content:"";
    position:absolute;
    width:500px;
    height:500px;
    background:radial-gradient(circle,#00ffaa33 0%, transparent 70%);
    animation:pulseGlow 3s ease-in-out infinite;
}

@keyframes pulseGlow{
    0%{transform:scale(0.8);opacity:0.6;}
    50%{transform:scale(1.2);opacity:1;}
    100%{transform:scale(0.8);opacity:0.6;}
}

.hud-container {
    position: relative;
    width: 350px;
    height: 450px;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Corner Brackets for HUD feel */
.hud-container::before, .hud-container::after {
    content: '';
    position: absolute;
    width: 40px;
    height: 40px;
    border-color: #00ffaa;
    border-style: solid;
    opacity: 0;
    animation: drawCorners 1.5s ease-out 0.5s forwards;
}
.hud-container::before { top: 0; left: 0; border-width: 3px 0 0 3px; }
.hud-container::after { bottom: 0; right: 0; border-width: 0 3px 3px 0; }

@keyframes drawCorners {
    0% { width: 0; height: 0; opacity: 0.5; }
    100% { width: 40px; height: 40px; opacity: 1; }
}

.intro-container {
    text-align: center;
    position: relative;
    z-index:2;
    opacity: 0;
    animation: fadeInContainer 1s ease 1s forwards;
}

@keyframes fadeInContainer {
    to { opacity: 1; }
}

.profile-ring {
    width:250px;
    height: 250px;
    border: 5px solid rgba(0, 255, 170, 0.1);
    border-radius: 50%;
    padding: 5px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 0 30px rgba(0, 255, 170, 0.1), inset 0 0 20px rgba(0, 255, 170, 0.1), 0 0 0px rgba(0, 255, 170, 0);
    transition: box-shadow 0.5s ease;
}

.profile-pic {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    opacity: 0.5; /* Slightly visible initially */
    transform: scale(1.2);
    transition: opacity 1s ease, transform 1s ease, filter 1s ease;
    filter: grayscale(80%) brightness(0.8);
}

.profile-ring.scanned .profile-pic {
    opacity: 1;
    transform: scale(1);
    filter: grayscale(0%) brightness(1);
}

.profile-ring.scanned {
    animation: ringPulse 2s infinite alternate 1s;
}

@keyframes ringPulse {
    to {
        box-shadow: 0 0 40px rgba(0, 255, 170, 0.3), inset 0 0 25px rgba(0, 255, 170, 0.2), 0 0 15px rgba(0, 255, 170, 0.5);
        border-color: rgba(0, 255, 170, 0.5);
    }
}

.scan-line {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, transparent, #00ffaa, transparent);
    box-shadow: 0 0 20px #00ffaa, 0 0 5px #fff;
    opacity: 0;
    animation: scan 2s cubic-bezier(0.4, 0, 0.2, 1) 1.5s forwards;
}

@keyframes scan {
    0% { top: 0; opacity: 0.5; }
    50% { opacity: 1; filter: brightness(1.5); }
    100% { top: 100%; opacity: 0; }
}

.status-text {
    color: #00ffaa;
    font-size: 22px;
    font-weight: bold;
    margin-top: 30px;
    text-transform: uppercase;
    letter-spacing: 3px;
    height: 30px; /* Reserve space */
    text-shadow: 0 0 10px rgba(0, 255, 170, 0.5);
    position: relative;
}

/* Typing cursor */
.status-text::after {
    content: '_';
    color: #00ffaa;
    animation: blink 0.7s infinite;
}

@keyframes blink {
    50% { opacity: 0; }
}

.status-text.final-text::after {
    display: none; /* Hide cursor on final text */
}

.status-text.final-text {
    animation: textFlicker 3s infinite;
}

@keyframes textFlicker {
    0%, 18%, 22%, 25%, 53%, 57%, 100% {
        text-shadow:
            0 0 4px #00ffaa,
            0 0 11px #00ffaa,
            0 0 19px #00ffaa,
            0 0 40px #00ffaa,
            0 0 80px #00ffaa;
        opacity: 1;
    }
    20%, 24%, 55% {
        text-shadow: none;
        opacity: 0.4;
    }
}

/* 🔥 Fade Layer */
.fade-layer{
    position:absolute;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:#0a1f16;
    opacity:0;
    transition: opacity 1.2s ease;
    z-index:5;
}

/* 🔥 Connection Animation Layer */
.connection-layer {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 1;
}

.conn-line {
    position: absolute;
    height: 2px;
    background: linear-gradient(90deg, #00ffaa, rgba(0, 255, 170, 0.1));
    transform-origin: 0 50%;
    width: 0;
    opacity: 0.8;
    transition: width 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    box-shadow: 0 0 8px #00ffaa;
}

.conn-node {
    position: absolute;
    width: 10px;
    height: 10px;
    background: #fff;
    border: 2px solid #00ffaa;
    border-radius: 50%;
    transform: translate(-50%, -50%) scale(0);
    box-shadow: 0 0 15px #00ffaa;
    animation: popNode 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards 0.3s;
    z-index: 2;
}

.conn-node::after {
    content: '';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 100%; height: 100%;
    border: 1px solid #00ffaa;
    border-radius: 50%;
    animation: nodeRipple 1.5s infinite;
}

.conn-label {
    position: absolute;
    color: #fff;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: bold;
    opacity: 0;
    transform: translate(-50%, 10px);
    background: rgba(0, 20, 10, 0.8);
    padding: 2px 6px;
    border: 1px solid rgba(0, 255, 170, 0.3);
    border-radius: 4px;
    text-shadow: 0 0 5px #00ffaa;
    animation: fadeInLabel 0.4s ease forwards 0.4s;
    z-index: 3;
}

/* Data Packet Animation */
.data-packet {
    position: absolute;
    top: 50%;
    right: 0; /* Start at the end (node) */
    width: 15px;
    height: 4px;
    background: #fff;
    border-radius: 2px;
    box-shadow: 0 0 10px #fff, 0 0 20px #00ffaa;
    transform: translateY(-50%);
    opacity: 0;
}

@keyframes popNode { to { transform: translate(-50%, -50%) scale(1); } }
@keyframes fadeInLabel { to { opacity: 0.8; } }
@keyframes nodeRipple { 0% { width: 100%; height: 100%; opacity: 0.8; } 100% { width: 300%; height: 300%; opacity: 0; } }
@keyframes receiveData { 
    0% { right: 0; opacity: 0; } 
    10% { opacity: 1; }
    100% { right: 100%; opacity: 0; } /* Move to left (center) */
}

@media (max-width: 480px) {
    .hud-container { transform: scale(0.8); }
    .status-text { font-size: 18px; }
}

body.blackout-protocol {
    background: #020806 !important;
    filter: brightness(0.3) grayscale(1) !important;
}
</style>
</head>
<body class="<?php echo $blackout_active ? 'blackout-protocol' : ''; ?>">
<div class="hud-container">
    <div class="intro-container">
        <div class="profile-ring">
            <img src="<?php echo $admin_pic; ?>" alt="Admin Profile" class="profile-pic">
            <div class="scan-line"></div>
        </div>
        <div class="status-text" id="statusText" data-text=""></div>
    </div>
</div>
<div id="connectionLayer" class="connection-layer"></div>

<div id="fadeLayer" class="fade-layer"></div>

<audio id="introSound" src="../assets/audio/hud-activation.mp3"></audio>
<!-- <audio id="typingSound" src="typing-sound.mp3" loop></audio> -->

<script>
window.addEventListener("load", function () {
    const sound = document.getElementById("introSound");
    // const typingSound = document.getElementById("typingSound");
    const fadeLayer = document.getElementById("fadeLayer");
    const profileRing = document.querySelector('.profile-ring');
    const statusText = document.getElementById('statusText');
    sound.volume = 0.7;
    // if(typingSound) typingSound.volume = 0.5;

    const text1 = "Initializing...";
    const text2 = "Access Granted";
    let typingSpeed = 150;

    function typeWriter(text, i, callback) {
        if (i < text.length) {
            statusText.innerHTML = text.substring(0, i + 1);
            statusText.dataset.text = text.substring(0, i + 1); // For glitch effect if used
            setTimeout(() => typeWriter(text, i + 1, callback), typingSpeed);
        } else if (callback) {
            setTimeout(callback, 500); // Pause after typing
        }
    }

    // --- Animation Sequence ---

    // 1. After scan animation (2s) + initial delay (1.5s) = 3.5s total
    // At this point, reveal the profile picture and play the main sound
    setTimeout(() => {
        profileRing.classList.add('scanned');
        sound.play().catch(e => console.warn("Audio autoplay was blocked."));
        
        // Start Connection Animation (Connecting to different places)
        const layer = document.getElementById('connectionLayer');
        const locations = ["Registrar", "Library", "Clinic", "Dean's Office", "IT Dept", "User Posting", "Alumni", "Student", "Teacher", "Password Manage"];
        const centerX = window.innerWidth / 2;
        const centerY = window.innerHeight / 2;

        locations.forEach((loc, index) => {
            setTimeout(() => {
                const angle = (index / locations.length) * 2 * Math.PI;
                const distance = 300 + Math.random() * 100; // Distance from center
                const targetX = centerX + Math.cos(angle) * distance;
                const targetY = centerY + Math.sin(angle) * distance;

                // Line
                const line = document.createElement('div');
                line.className = 'conn-line';
                line.style.left = centerX + 'px';
                line.style.top = centerY + 'px';
                line.style.transform = `rotate(${angle}rad)`;
                layer.appendChild(line);
                
                // Trigger line growth
                setTimeout(() => { 
                    line.style.width = distance + 'px'; 
                    
                    // Spawn Data Packet traveling back
                    const packet = document.createElement('div');
                    packet.className = 'data-packet';
                    packet.style.animation = `receiveData 1s cubic-bezier(0.4, 0, 0.2, 1) forwards`;
                    line.appendChild(packet);
                }, 50);

                // Node & Label
                const node = document.createElement('div'); node.className = 'conn-node'; node.style.left = targetX + 'px'; node.style.top = targetY + 'px'; layer.appendChild(node);
                
                const label = document.createElement('div'); label.className = 'conn-label'; label.innerText = loc; label.style.left = targetX + 'px'; label.style.top = (targetY + 10) + 'px'; layer.appendChild(label);

            }, index * 100); // Faster Staggered effect
        });
    }, 3500);

    // 2. Start typing the first text after a delay
    setTimeout(() => {
        // if(typingSound) typingSound.play();
        typeWriter(text1, 0, () => {
            // if(typingSound) typingSound.pause();
            // 3. After typing "Initializing...", type "Access Granted"
            setTimeout(() => {
                // if(typingSound) typingSound.play();
                typeWriter(text2, 0, () => {
                    // if(typingSound) typingSound.pause();
                    // 4. After everything, fade out and redirect
                    statusText.classList.add('final-text');
                    setTimeout(() => { fadeLayer.style.opacity = "1"; }, 1000);
                    setTimeout(() => { window.location.href = "SACLICONNECT2.php?intro=1"; }, 2200);
                });
            }, 500);
        });
    }, 4000); // Start typing after image is fully revealed

    // Fallback redirect in case JS or animations fail
    setTimeout(() => {
        if (window.location.pathname.includes('admin_intro.php')) {
             window.location.href = "SACLICONNECT2.php?intro=1";
        }
    }, 9000);
});
</script>
</body>
</html>