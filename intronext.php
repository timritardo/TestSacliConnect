<?php
session_start();

if (!isset($_SESSION['student_name'])) {
    header("Location: SacliConnect_LOG_IN.php");
    exit();
}

require_once __DIR__ . '/config/database.php';
$theme_q = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='site_theme'");
$current_theme = ($theme_q && $theme_q->num_rows > 0) ? $theme_q->fetch_assoc()['setting_value'] : 'default';
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
<title>SacliConnect Intro</title>
<link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600&display=swap" rel="stylesheet">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI', sans-serif;
}

body.theme-default{
    background: linear-gradient(-45deg, #05100c, #0a1f16, #0d2b1f, #05100c);
    background-size: 400% 400%;
    animation: backgroundFlow 15s ease infinite;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    overflow:hidden;
    position:relative;
    box-shadow: inset 0 0 200px rgba(0,0,0,0.9); /* Cinematic Vignette */
    perspective: 1000px;
}

body.theme-default::after {
    content: "";
    position: absolute; 
    top: -50%; left: -50%; width: 200%; height: 200%;
    background: 
        radial-gradient(circle at 20% 30%, rgba(0, 255, 170, 0.07) 0%, transparent 40%), 
        radial-gradient(circle at 80% 20%, rgba(0, 255, 170, 0.05) 0%, transparent 40%), 
        radial-gradient(circle at 40% 80%, rgba(0, 255, 170, 0.06) 0%, transparent 40%);
    animation: bokehFlow 40s linear infinite; 
    z-index: -1; 
    pointer-events: none; 
    filter: blur(40px);
}

body.theme-default.revealing {
    animation: backgroundFlow 15s ease infinite, bodyZoom 3s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

@keyframes backgroundFlow {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

@keyframes bodyZoom {
    0% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* Atmospheric Data Motes (Floating particles) */
body.theme-default::before {
    content: "";
    position: absolute; 
    top: 0; left: 0; width: 100%; height: 100%;
    background-image: 
        radial-gradient(1.5px 1.5px at 20% 30%, rgba(0, 255, 170, 0.4), transparent), 
        radial-gradient(1px 1px at 50% 70%, #fff, transparent), 
        radial-gradient(2px 2px at 80% 40%, rgba(0, 255, 170, 0.3), transparent), 
        radial-gradient(3px 3px at 25% 75%, rgba(255, 255, 255, 0.7), transparent);
    background-size: 600px 600px;
    animation: driftingMotes 40s linear infinite, motesEntrance 3s cubic-bezier(0.22, 1, 0.36, 1) forwards, twinkleMotes 5s ease-in-out infinite 3s;
    z-index: 1; 
    opacity: 0.6; 
    pointer-events: none; 
    transform: translate(var(--mx, 0), var(--my, 0)); 
    transition: transform 0.2s ease-out;
}

@keyframes bokehFlow { from { transform: rotate(0deg) scale(1); } 50% { transform: rotate(180deg) scale(1.1); } to { transform: rotate(360deg) scale(1); } }

@keyframes motesEntrance {
    0% { opacity: 0; transform: scale(1.5); filter: blur(10px); }
    60% { opacity: 1; transform: scale(0.95); filter: blur(0px); }
    100% { opacity: 0.6; transform: scale(1); filter: blur(0px); }
}

@keyframes twinkleMotes {
    0%, 100% { opacity: 0.6; filter: brightness(1) drop-shadow(0 0 0px transparent); }
    50% { opacity: 0.25; filter: brightness(1.4) drop-shadow(0 0 3px #00ffaa); }
}

@keyframes driftingMotes {
    from { background-position: 0 0; }
    to { background-position: 600px -1200px; }
}

/* 🔥 Logo Animation (No Rotation) */
.logo{
    opacity:0;
    transform:scale(0.7);
    animation: logoReveal 3s ease forwards, logoBreath 4s ease-in-out infinite 3s;
    z-index:2;
}

.logo img{
    width:300px;
}


@keyframes shineMove {
    0% { left: -150%; opacity: 0; }
    20% { opacity: 1; }
    100% { left: 250%; opacity: 0; }
}

/* Digital Aberration (Glitch) layers during reveal */
.logo::before, .logo::after {
    content: "";
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    background: url('St.Anne_logo.png') no-repeat center;
    background-size: contain;
    opacity: 0;
    z-index: -1;
    pointer-events: none;
}

.logo::before {
    animation: glitchRed 3s ease forwards;
    filter: drop-shadow(-3px 0 rgba(255,0,0,0.4));
}

.logo::after {
    animation: glitchBlue 3s ease forwards;
    filter: drop-shadow(3px 0 rgba(0,0,255,0.4));
}

@keyframes logoReveal{
    0%{
        opacity:0;
        transform:scale(0.6);
        filter: drop-shadow(0 0 0px transparent) blur(5px);
    }
    60%{
        opacity:1;
        transform:scale(1.05);
        filter: drop-shadow(0 0 20px #00ffaa);
    }
    80% {
        filter: drop-shadow(-5px 0 #ff0055) drop-shadow(5px 0 #00ccff);
    }
    100% {
        opacity:1;
        transform:scale(1);
        filter: drop-shadow(0 0 15px #00ffaa);
    }
}

@keyframes logoBreath {
    0%, 100% { transform: scale(1); filter: brightness(1) drop-shadow(0 0 15px #00ffaa); }
    50% { transform: scale(1.025); filter: brightness(1.15) drop-shadow(0 0 30px #00ffaa); }
}

@keyframes glitchRed {
    0%, 100% { opacity: 0; transform: translateX(0); }
    15%, 25% { opacity: 0.6; transform: translateX(-4px) skewX(5deg); }
    20% { opacity: 0; transform: translateX(0); }
    26% { opacity: 0; transform: translateX(0); }
}

@keyframes glitchBlue {
    0%, 100% { opacity: 0; transform: translateX(0); }
    15%, 25% { opacity: 0.6; transform: translateX(4px) skewX(-5deg); }
    20% { opacity: 0; transform: translateX(0); }
    26% { opacity: 0; transform: translateX(0); }
}

/* Status Msg Styling */
.status-msg {
    position: absolute;
    bottom: 15%;
    color: #00ffaa;
    font-family: 'Consolas', 'Courier New', monospace;
    font-size: 13px;
    letter-spacing: 3px;
    text-transform: uppercase;
    opacity: 0.7;
    text-shadow: 0 0 8px rgba(0, 255, 170, 0.5);
    pointer-events: none;
    z-index: 3;
}

/* 🔥 Fade Layer */
.fade-layer.theme-default{
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

/* --- THEME STYLES --- */
body.theme-halloween { background: #050202; display:flex; justify-content:center; align-items:center; height:100vh; overflow:hidden; position: relative; }
body.theme-halloween::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to bottom, rgba(0,0,0,0.6), rgba(20,0,0,0.9)), repeating-linear-gradient(45deg, rgba(20,0,0,0.1) 0px, rgba(20,0,0,0.1) 2px, transparent 2px, transparent 10px), radial-gradient(circle at 50% 50%, rgba(40,0,0,0.2), transparent 80%); background-size: cover; z-index: 1; pointer-events: none; }
body.theme-halloween::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(circle at 50% 0%, rgba(255, 0, 0, 0.1), transparent 70%); opacity: 0.6; animation: lightningFlash 10s infinite; pointer-events: none; z-index: 1; mix-blend-mode: overlay; }
@keyframes lightningFlash { 0%, 85% { opacity: 0.3; background-color: transparent; } 86% { opacity: 0.8; background-color: rgba(255, 0, 0, 0.15); } 87% { opacity: 0.3; background-color: transparent; } 92% { opacity: 0.3; background-color: transparent; } 93% { opacity: 1; background-color: rgba(255, 50, 50, 0.2); } 94% { opacity: 0.3; background-color: transparent; } 100% { opacity: 0.3; } }
.fade-layer.theme-halloween { background: #050202; position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; transition:opacity 1.2s ease; z-index:5; }

body.theme-christmas { background: linear-gradient(to bottom, #0f2027, #203a43, #2c5364); display:flex; justify-content:center; align-items:center; height:100vh; overflow:hidden; position: relative; }
body.theme-christmas::before { content: ''; position: fixed; top: -50px; left: 0; width: 10px; height: 10px; border-radius: 50%; background: transparent; box-shadow: 5vw 10vh 2px 2px #fff, 15vw 25vh 1px 3px #fff, 25vw 5vh 3px 2px #fff, 35vw 15vh 1px 1px #fff, 45vw 10vh 2px 2px #fff, 55vw 25vh 1px 3px #fff, 65vw 5vh 3px 2px #fff, 75vw 15vh 1px 1px #fff, 85vw 10vh 2px 2px #fff, 95vw 25vh 1px 3px #fff, 10vw 40vh 2px 2px #fff, 30vw 60vh 1px 3px #fff, 50vw 50vh 3px 2px #fff, 70vw 70vh 1px 1px #fff, 90vw 80vh 2px 2px #fff; opacity: 0.8; pointer-events: none; animation: snow 10s linear infinite; z-index: 1; }
body.theme-christmas::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 100%; background: url('christmas_tree.png') no-repeat bottom right 5% / 350px auto, url('christmas_tree.png') no-repeat bottom left 5% / 250px auto, url('christmas_tree.png') no-repeat bottom left 20% / 180px auto; z-index: 1; filter: drop-shadow(0 0 15px rgba(255,255,255,0.3)); pointer-events: none; }
@keyframes snow { 0% { transform: translateY(-10vh); } 100% { transform: translateY(110vh); } }
.fade-layer.theme-christmas { background: #0f3d0f; position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; transition:opacity 1.2s ease; z-index:5; }

body.theme-summer { background: #2980b9; display:flex; justify-content:center; align-items:center; height:100vh; overflow:hidden; }
body.theme-summer::before { content:''; position:absolute; width:600px; height:600px; background:radial-gradient(circle, #ffcc00 0%, transparent 60%); opacity:0.4; animation:pulseGlow 4s infinite; }
.fade-layer.theme-summer { background: #2980b9; position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; transition:opacity 1.2s ease; z-index:5; }

body.theme-new_year { background: radial-gradient(ellipse at bottom, #1b2735 0%, #090a0f 100%); display:flex; justify-content:center; align-items:center; height:100vh; overflow:hidden; }
.fade-layer.theme-new_year { background: #090a0f; position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; transition:opacity 1.2s ease; z-index:5; }

/* Animation Classes */
.firework-particle { position: absolute; width: 4px; height: 4px; border-radius: 50%; pointer-events: none; animation: explode 1.5s ease-out forwards; z-index: 1; }
@keyframes explode { 0% { transform: translate(0, 0) scale(1); opacity: 1; } 50% { opacity: 1; } 100% { transform: translate(var(--tx), var(--ty)) scale(0); opacity: 0; } }
@keyframes twinkle { 0% { opacity: 0.3; } 50% { opacity: 1; } 100% { opacity: 0.3; } }
.ash { position: absolute; background: rgba(255, 255, 255, 0.7); border-radius: 50%; pointer-events: none; z-index: 2; box-shadow: 0 0 5px rgba(255,255,255,0.5); animation: fallAsh linear forwards; }
@keyframes fallAsh { 0% { transform: translateY(-10vh) translateX(0) rotate(0deg); opacity: 0; } 10% { opacity: 0.8; } 100% { transform: translateY(110vh) translateX(20px) rotate(180deg); opacity: 0; } }

@media (max-width: 480px) {
    .logo img { width: 200px; }
}
</style>
</head>

<body class="theme-<?php echo htmlspecialchars($current_theme); ?>">

<script>
document.addEventListener('mousemove', (e) => {
    if (document.body.classList.contains('theme-default')) {
        // Mouse Parallax for Atmosphere
        const moveX = (e.clientX - window.innerWidth / 2) * 0.1;
        const moveY = (e.clientY - window.innerHeight / 2) * 0.1;
        document.body.style.setProperty('--mx', `${moveX}px`);
        document.body.style.setProperty('--my', `${moveY}px`);
    }
});
</script>

<div class="logo">
    <div class="flare"></div>
    <img src="assets/images/St.Anne_logo.png" alt="Logo">
</div>
<div id="statusMsg" class="status-msg"></div>

<div id="fadeLayer" class="fade-layer theme-<?php echo htmlspecialchars($current_theme); ?>"></div>

<audio id="introSound" src="sound intro.mp3"></audio>
<audio id="finishSound" src="digital-chime.mp3"></audio>

<script>
window.addEventListener("load", function () {

    const sound = document.getElementById("introSound");
    const finishSound = document.getElementById("finishSound");
    const fadeLayer = document.getElementById("fadeLayer");
    const statusMsg = document.getElementById("statusMsg");

    sound.volume = 0.7;

    function proceed() {
        // Start fade after logo animation
        setTimeout(() => {
            fadeLayer.style.opacity = "1";
        }, 3500);

        // Redirect
        setTimeout(() => {
            window.location.href = "SacliConnect.php";
        }, 4800);
    }

    function typeStatus(text, i = 0) {
        if (i < text.length) {
            statusMsg.innerHTML += text.charAt(i);
            setTimeout(() => typeStatus(text, i + 1), 40);
        }
    }

    // Play high-tech sound when logo animation finishes
    const logo = document.querySelector('.logo');
    logo.addEventListener('animationend', (e) => {
        if (e.animationName === 'logoReveal') {
            finishSound.play().catch(err => console.log("Finish sound blocked"));
            statusMsg.innerHTML = "";
            typeStatus("Link Established. Welcome To SacliConnect.");
        }
    });

    // ⏳ 1 second delay bago tumunog
    setTimeout(() => {

        sound.play().then(() => {
            document.body.classList.add('revealing');
            typeStatus("Initializing Secure Connection...");
            proceed();
        }).catch((error) => {
            console.log("Autoplay blocked:", error);
            proceed(); // Proceed anyway
        });

    }, 800); // ← 1 second delay

});

// --- THEME ANIMATIONS ---
if (document.body.classList.contains('theme-new_year')) {
    // Create stars background
    for(let i=0; i<100; i++){
        let star = document.createElement('div');
        star.style.position = 'absolute';
        star.style.left = Math.random() * 100 + '%';
        star.style.top = Math.random() * 100 + '%';
        star.style.width = Math.random() * 2 + 'px';
        star.style.height = star.style.width;
        star.style.background = '#fff';
        star.style.borderRadius = '50%';
        star.style.opacity = Math.random();
        star.style.animation = `twinkle ${Math.random()*3+2}s infinite`;
        document.body.appendChild(star);
    }

    setInterval(() => {
        const x = Math.random() * window.innerWidth;
        const y = Math.random() * (window.innerHeight * 0.5);
        const colors = ['#ff0000', '#ffd700', '#00ff00', '#00ffff', '#ff00ff', '#ffffff'];
        const color = colors[Math.floor(Math.random() * colors.length)];
        for(let i=0; i<50; i++) {
            const p = document.createElement('div');
            p.classList.add('firework-particle');
            p.style.left = x + 'px';
            p.style.top = y + 'px';
            p.style.backgroundColor = color;
            p.style.boxShadow = `0 0 10px ${color}, 0 0 20px ${color}`;
            const angle = Math.random() * Math.PI * 2;
            const velocity = Math.random() * 200 + 50;
            p.style.setProperty('--tx', Math.cos(angle) * velocity + 'px');
            p.style.setProperty('--ty', Math.sin(angle) * velocity + 'px');
            document.body.appendChild(p);
            setTimeout(() => p.remove(), 1500);
        }
    }, 1000);
}

if (document.body.classList.contains('theme-halloween')) {

    setInterval(() => {
        const p = document.createElement('div');
        p.classList.add('ash');
        p.style.left = Math.random() * window.innerWidth + 'px';
        p.style.top = -10 + 'px';
        p.style.width = p.style.height = (Math.random() * 3 + 2) + 'px';
        p.style.animationDuration = (Math.random() * 5 + 5) + 's';
        document.body.appendChild(p);
        setTimeout(() => p.remove(), 10000);
    }, 100);
}
</script>



</body>
</html>
