<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: SacliConnect_LOG_IN.php");
    exit();
}

$my_id = $_SESSION['student_id'];
$room_code = $_GET['code'] ?? 'SACLI-OFFLINE';
$student_name = $_SESSION['student_name'];

// Fetch my profile pic for the avatar placeholder
$user_type = $_SESSION['user_type'] ?? 'student';
if ($user_type === 'teacher') {
    $me_q = $conn->query("SELECT profile_pic FROM teachers WHERE id='".str_replace("T-", "", $my_id)."'");
} else {
    $me_q = $conn->query("SELECT profile_pic FROM students WHERE student_id='$my_id'");
}
$me_data = $me_q->fetch_assoc();
$my_pic = !empty($me_data['profile_pic']) ? "uploads/".$me_data['profile_pic'] : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SacliMeet HUD - <?php echo htmlspecialchars($room_code); ?></title>
    <link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0; padding: 0;
            background: radial-gradient(circle at 50% 50%, #0a1f16 0%, #05100c 100%);
            color: #e4e6eb;
            font-family: 'Segoe UI', sans-serif;
            height: 100vh;
            overflow: hidden;
        }

        /* Hologram Scanlines Effect */
        body::after {
            content: " ";
            position: absolute; top: 0; left: 0; bottom: 0; right: 0;
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 255, 170, 0.02) 50%), linear-gradient(90deg, rgba(255, 0, 0, 0.01), rgba(0, 255, 0, 0.005), rgba(0, 0, 255, 0.01));
            z-index: 100; background-size: 100% 4px, 3px 100%; pointer-events: none; animation: pulseScan 4s infinite linear;
        }

        @keyframes pulseScan { 0%, 100% { opacity: 0.5; } 50% { opacity: 0.8; } }

        #meetingRoom { 
            height: 100%; display: grid; 
            grid-template-columns: 1fr; grid-template-rows: 60px 1fr 100px;
            transition: 0.3s ease;
        }
        #meetingRoom.sidebar-open {
            grid-template-columns: 1fr 350px;
        }

        .meeting-top-bar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 30px; background: rgba(0,0,0,0.3); border-bottom: 1px solid rgba(0,255,170,0.2); grid-column: 1 / -1;
        }

        .meeting-main-area { display: flex; gap: 20px; padding: 20px; overflow: hidden; }

        .video-grid-container {
            flex: 1; display: grid; gap: 15px; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            align-content: center; justify-items: center;
            transition: 0.3s;
        }

        /* Single video style when alone */
        .video-grid-container.alone {
            grid-template-columns: 1fr;
        }

        .video-tile {
            background: rgba(10, 31, 22, 0.6); border-radius: 24px; overflow: hidden;
            position: relative; aspect-ratio: 16/9; border: 1px solid rgba(0, 255, 170, 0.2);
            box-shadow: 0 10px 30px rgba(0,0,0,0.4); width: 100%; max-width: 700px;
        }

        video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }

        .avatar-placeholder {
            position: absolute; inset: 0; background: #05100c; display: none;
            align-items: center; justify-content: center; z-index: 1;
        }
        .avatar-placeholder img { width: 120px; height: 120px; border-radius: 50%; border: 3px solid #00ffaa; object-fit: cover; }
        .avatar-placeholder .initials { width: 120px; height: 120px; border-radius: 50%; background: #1a3d2f; color: #00ffaa; display: flex; align-items: center; justify-content: center; font-size: 48px; font-family: 'Orbitron'; border: 3px solid #00ffaa; }
        
        .user-label {
            position: absolute; bottom: 20px; left: 20px;
            background: rgba(0,0,0,0.6); padding: 8px 16px; border-radius: 12px;
            font-size: 12px; color: #00ffaa; border: 1px solid rgba(0,255,170,0.3); font-family: 'Orbitron';
            display: flex; align-items: center; gap: 8px; z-index: 2;
        }

        .mute-icon { color: #ff4757; display: none; }

        /* Sidebar Styles */
        .meeting-sidebar {
            background: rgba(10, 31, 22, 0.95); border-left: 1px solid rgba(0,255,170,0.2);
            display: none; flex-direction: column; height: 100%; overflow: hidden;
        }
        .sidebar-header { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; }
        .sidebar-tabs { display: flex; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .tab-btn { flex: 1; padding: 15px; background: none; border: none; color: #aaa; cursor: pointer; font-family: 'Orbitron'; font-size: 10px; transition: 0.3s; }
        .tab-btn.active { color: #00ffaa; border-bottom: 2px solid #00ffaa; }
        .sidebar-content { flex: 1; overflow-y: auto; padding: 20px; }
        
        /* Chat Styles */
        .chat-msg { margin-bottom: 15px; font-size: 13px; }
        .chat-msg b { color: #00ffaa; display: block; font-size: 11px; margin-bottom: 2px; }
        .chat-input-area { padding: 15px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; gap: 10px; }
        .chat-input { flex: 1; background: rgba(0,0,0,0.3); border: 1px solid rgba(0,255,170,0.3); border-radius: 20px; padding: 10px 15px; color: white; outline: none; }

        /* Participants Styles */
        .participant-item { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .participant-item img { width: 30px; height: 30px; border-radius: 50%; border: 1px solid #00ffaa; }

        .control-dock {
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
            background: rgba(15, 30, 25, 0.85); backdrop-filter: blur(25px);
            border: 1px solid rgba(0,255,170,0.3); border-radius: 30px; padding: 12px 20px;
            display: flex; gap: 15px; align-items: center; z-index: 1000;
        }

        .dock-btn {
            width: 52px; height: 52px; border-radius: 18px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            color: #e4e6eb; cursor: pointer; transition: 0.3s;
            display: flex; align-items: center; justify-content: center; font-size: 20px;
        }
        .dock-btn:hover { background: rgba(0, 255, 170, 0.2); border-color: #00ffaa; transform: translateY(-5px); color: #00ffaa; }
        .dock-btn.active { background: #00ffaa; color: #0a1f16; box-shadow: 0 0 15px #00ffaa; }
        .dock-btn.danger { background: #ff4757; color: white; width: 70px; border-radius: 20px; }

        /* Reactions Dock */
        .reaction-dock { display: flex; gap: 8px; padding: 0 15px; border-left: 1px solid rgba(255,255,255,0.1); }
        .react-btn { background: none; border: none; font-size: 24px; cursor: pointer; transition: 0.2s; }
        .react-btn:hover { transform: scale(1.3); }

        .rec-dot { width: 10px; height: 10px; background: #ff4757; border-radius: 50%; animation: recBlink 1s infinite; margin-right: 8px; }
        @keyframes recBlink { 50% { opacity: 0.3; } }

        /* Reaction Animation */
        .floating-emoji { 
            position: fixed; bottom: 100px; pointer-events: none; z-index: 2000; animation: floatUp 2s ease-out forwards;
            background: rgba(0, 255, 170, 0.2); backdrop-filter: blur(10px); padding: 8px 15px; border-radius: 30px; border: 1px solid rgba(0, 255, 170, 0.4); display: flex; align-items: center; gap: 8px; white-space: nowrap;
        }
        @keyframes floatUp { 0% { transform: translateY(0); opacity: 0; } 20% { opacity: 1; } 100% { transform: translateY(-500px) translateX(var(--tx)); opacity: 0; } }
    </style>
</head>
<body>

<div id="meetingRoom" class="">
    <div class="meeting-top-bar">
        <div style="display:flex; align-items:center;">
            <div class="rec-dot"></div>
            <span style="font-family: 'Orbitron'; font-size: 12px; color:#00ffaa;">UPLINK_ACTIVE <span id="timer" style="color:#fff; margin-left:10px;">00:00:00</span></span>
        </div>
        <div style="background:rgba(0,255,170,0.1); padding:8px 20px; border-radius:5px; border:1px solid #00ffaa; font-family:'Orbitron'; font-size:12px; letter-spacing:2px;">
            CODE: <b id="roomDisplay"><?php echo htmlspecialchars($room_code); ?></b>
        </div>
        <div style="display:flex; gap:10px;">
            <button class="dock-btn" style="width:40px; height:40px; font-size:16px;" onclick="toggleSidebar('people')" title="Participants">👥</button>
            <button class="dock-btn" style="width:40px; height:40px; font-size:16px;" onclick="toggleSidebar('chat')" title="Chat">💬</button>
        </div>
    </div>

    <div class="meeting-main-area">
        <div class="video-grid-container alone" id="videoGrid">
            <div class="video-tile">
                <video id="localVideo" autoplay muted playsinline></video>
                <div id="localAvatar" class="avatar-placeholder">
                    <?php if($my_pic): ?>
                        <img src="<?php echo $my_pic; ?>" alt="Avatar">
                    <?php else: ?>
                        <div class="initials"><?php echo strtoupper(substr($student_name, 0, 1)); ?></div>
                    <?php endif; ?>
                </div>
                <div class="user-label">
                    <span class="mute-icon" id="localMuteIcon">🔇</span>
                    <?php echo htmlspecialchars($student_name); ?> (You)
                </div>
            </div>
            <div id="peerPlaceholder" class="video-tile" style="display:none; opacity:0.3; border-style:dashed; align-items:center; justify-content:center;">
                <div style="font-family:'Orbitron'; color:#555;">WAITING_FOR_PEER_LINK...</div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="meeting-sidebar" id="meetingSidebar">
        <div class="sidebar-header">
            <span id="sidebarTitle" style="color:#00ffaa; font-family:'Orbitron'; font-size:12px;">CHAT</span>
            <button onclick="toggleSidebar()" style="background:none; border:none; color:#ff4757; cursor:pointer; font-size:20px;">&times;</button>
        </div>
        <div class="sidebar-tabs">
            <button class="tab-btn active" id="tabChat" onclick="switchTab('chat')">MESSAGES</button>
            <button class="tab-btn" id="tabPeople" onclick="switchTab('people')">PEOPLE</button>
        </div>
        <div class="sidebar-content" id="sidebarContent">
            <!-- Dynamic Content -->
        </div>
        <div class="chat-input-area" id="chatInputArea">
            <input type="text" id="meetingChatInput" class="chat-input" placeholder="Send a message..." onkeypress="if(event.key==='Enter') sendChatMessage()">
            <button class="dock-btn active" style="width:40px; height:40px; border-radius:50%;" onclick="sendChatMessage()">➤</button>
        </div>
    </div>

    <div class="control-dock">
        <button class="dock-btn active" id="btnMic" onclick="toggleMic()">
            <img id="micIcon" src="mic_24dp_000000_FILL0_wght400_GRAD0_opsz24.png" style="width:24px; height:24px;">
        </button>
        <button class="dock-btn active" id="btnCam" onclick="toggleCam()">
            <img id="camIcon" src="videocam_24dp_000000_FILL0_wght400_GRAD0_opsz24.png" style="width:24px; height:24px;">
        </button>
        <button class="dock-btn" id="btnShare" onclick="toggleShare()" title="Share Screen">📺</button>
        
        <div class="reaction-dock">
            <button class="react-btn" onclick="sendReaction('❤️')">❤️</button>
            <button class="react-btn" onclick="sendReaction('👏')">👏</button>
            <button class="react-btn" onclick="sendReaction('🔥')">🔥</button>
            <button class="react-btn" onclick="sendReaction('😂')">😂</button>
        </div>

        <div style="padding-left: 15px; border-left: 1px solid rgba(255,255,255,0.1);">
            <button class="dock-btn danger" onclick="location.href='SacliConnect.php?page=meeting'" title="Leave Meeting">🚫</button>
        </div>
    </div>
</div>

<script>
    let localStream = null;
    let isMicOn = true;
    let isCamOn = true;
    let seconds = 0;
    let logId = null;
    let currentTab = 'chat';
    let isSharing = false;
    let isHost = false;
    let checkStatusInterval = null;
    let pollRequestsInterval = null;
    const roomCode = '<?php echo $room_code; ?>';

    async function initMeeting() {
        // 1. Check if user is host or admitted
        let fd = new FormData();
        fd.append('action', 'request_join');
        fd.append('code', roomCode);
        
        const res = await fetch('handlers/meeting_handler.php', { method: 'POST', body: fd });
        const data = await res.json();

        if(data.role === 'host') {
            isHost = true;
            startMainMeeting();
            startHostPolling();
        } else {
            if(data.status === 'admitted') {
                startMainMeeting();
            } else {
                document.getElementById('waitingOverlay').style.display = 'flex';
                startStatusPolling();
            }
        }
    }

    function startStatusPolling() {
        checkStatusInterval = setInterval(() => {
            let fd = new FormData();
            fd.append('action', 'check_status');
            fd.append('code', roomCode);
            fetch('handlers/meeting_handler.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if(data.status === 'admitted') {
                    clearInterval(checkStatusInterval);
                    document.getElementById('waitingOverlay').style.display = 'none';
                    startMainMeeting();
                } else if(data.status === 'denied') {
                    clearInterval(checkStatusInterval);
                    alert("The host has denied your request to join.");
                    location.href = 'SacliConnect.php?page=meeting';
                }
            });
        }, 3000);
    }

    

    function startHostPolling() {
        pollRequestsInterval = setInterval(() => {
            let fd = new FormData();
            fd.append('action', 'get_waiting_list');
            fd.append('code', roomCode);
            fetch('handlers/meeting_handler.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(list => {
                if(list.length > 0) {
                    const user = list[0]; // Process one by one
                    const reqDiv = document.getElementById('admissionRequest');
                    document.getElementById('reqUserName').innerText = user.name;
                    document.getElementById('reqUserPic').src = user.profile_pic ? 'uploads/'+user.profile_pic : 'assets/images/3icons8-student-64.png';
                    
                    document.getElementById('btnAdmit').onclick = () => decideUser(user.student_id, 'admitted');
                    document.getElementById('btnDeny').onclick = () => decideUser(user.student_id, 'denied');
                    
                    reqDiv.style.display = 'block';
                } else {
                    document.getElementById('admissionRequest').style.display = 'none';
                }
            });
        }, 4000);
    }

    function decideUser(uid, decision) {
        let fd = new FormData();
        fd.append('action', 'decide_participant');
        fd.append('code', roomCode);
        fd.append('target_id', uid);
        fd.append('decision', decision);
        fetch('handlers/meeting_handler.php', { method: 'POST', body: fd }).then(() => {
            document.getElementById('admissionRequest').style.display = 'none';
        });
    }

    async function startMainMeeting() {
        try {
            localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
            document.getElementById('localVideo').srcObject = localStream;
            
            startTimer();
            logEntry();
            updateParticipants();
            setInterval(updateParticipants, 5000); // Auto-update participant list every 5s
        } catch (e) {
            alert("Terminal Error: Media sensors not found. Please allow permissions.");
            console.error(e);
        }
    }

    function startTimer() {
        setInterval(() => {
            seconds++;
            let h = Math.floor(seconds / 3600).toString().padStart(2, '0');
            let m = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
            let s = (seconds % 60).toString().padStart(2, '0');
            document.getElementById('timer').innerText = `${h}:${m}:${s}`;
        }, 1000);
    }

    function toggleMic() {
        if (localStream && localStream.getAudioTracks().length > 0) {
            isMicOn = !isMicOn;
            localStream.getAudioTracks()[0].enabled = isMicOn;
            const btn = document.getElementById('btnMic');
            const micIcon = document.getElementById('micIcon');
            btn.classList.toggle('active', isMicOn);
            micIcon.src = isMicOn ? 'mic_24dp_000000_FILL0_wght400_GRAD0_opsz24.png' : 'mic_off_24dp_E3E3E3_FILL0_wght400_GRAD0_opsz24.png';
            document.getElementById('localMuteIcon').style.display = isMicOn ? 'none' : 'inline';
        }
    }

    async function toggleCam() {
        if (localStream && localStream.getVideoTracks().length > 0) {
            isCamOn = !isCamOn;
            localStream.getVideoTracks()[0].enabled = isCamOn;
            const btn = document.getElementById('btnCam');
            const camIcon = document.getElementById('camIcon');
            btn.classList.toggle('active', isCamOn);
            camIcon.src = isCamOn ? 'videocam_24dp_000000_FILL0_wght400_GRAD0_opsz24.png' : 'videocam_off_24dp_FFFFFF_FILL0_wght400_GRAD0_opsz24.png';
            document.getElementById('localVideo').style.opacity = isCamOn ? "1" : "0";
            document.getElementById('localAvatar').style.display = isCamOn ? "none" : "flex";
        }
    }

    async function toggleShare() {
        if (!isSharing) {
            try {
                const screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
                document.getElementById('localVideo').srcObject = screenStream;
                isSharing = true;
                document.getElementById('btnShare').classList.add('active');
                
                screenStream.getVideoTracks()[0].onended = () => {
                    stopSharing();
                };
            } catch (e) { console.error(e); }
        } else {
            stopSharing();
        }
    }

    function stopSharing() {
        document.getElementById('localVideo').srcObject = localStream;
        isSharing = false;
        document.getElementById('btnShare').classList.remove('active');
    }

    function sendReaction(emoji) {
        const div = document.createElement('div');
        div.className = 'floating-emoji';
        div.innerHTML = `<span style="color:#00ffaa; font-family:'Orbitron'; font-size:11px; font-weight:bold;"><?php echo htmlspecialchars($student_name); ?></span> <span style="font-size:22px;">${emoji}</span>`;
        div.style.left = (window.innerWidth / 2 + (Math.random() * 200 - 100)) + 'px';
        div.style.setProperty('--tx', (Math.random() * 100 - 50) + 'px');
        document.body.appendChild(div);
        setTimeout(() => div.remove(), 2000);
    }

    function toggleSidebar(type) {
        const room = document.getElementById('meetingRoom');
        const sidebar = document.getElementById('meetingSidebar');
        
        if (room.classList.contains('sidebar-open') && currentTab === type) {
            room.classList.remove('sidebar-open');
            sidebar.style.display = 'none';
        } else {
            room.classList.add('sidebar-open');
            sidebar.style.display = 'flex';
            if(type) switchTab(type);
        }
    }

    function switchTab(type) {
        currentTab = type;
        document.getElementById('tabChat').classList.toggle('active', type === 'chat');
        document.getElementById('tabPeople').classList.toggle('active', type === 'people');
        document.getElementById('sidebarTitle').innerText = type.toUpperCase();
        document.getElementById('chatInputArea').style.display = type === 'chat' ? 'flex' : 'none';
        
        const content = document.getElementById('sidebarContent');
        if (type === 'chat') {
            content.innerHTML = '<div style="color:#888; font-size:11px; text-align:center;">Messages are only visible to people in this meeting.</div>';
        } else {
            updateParticipants();
        }
    }

    function sendChatMessage() {
        const input = document.getElementById('meetingChatInput');
        const text = input.value.trim();
        if (!text) return;
        
        const content = document.getElementById('sidebarContent');
        const msgDiv = document.createElement('div');
        msgDiv.className = 'chat-msg';
        msgDiv.innerHTML = `<b>You</b><span>${text}</span>`;
        content.appendChild(msgDiv);
        input.value = '';
        content.scrollTop = content.scrollHeight;
    }

    function updateParticipants() {
        if (currentTab !== 'people') return;
        
        let fd = new FormData();
        fd.append('action', 'get_active_meeting_participants');
        fd.append('room_code', '<?php echo $room_code; ?>');
        
        fetch('handlers/sacli_room_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            const content = document.getElementById('sidebarContent');
            if (data.status === 'success') {
                content.innerHTML = data.participants.map(p => {
                    const pic = p.profile_pic ? 'uploads/'+p.profile_pic : 'assets/images/3icons8-student-64.png';
                    const isMe = p.student_id === "<?php echo $my_id; ?>";
                    return `<div class="participant-item"><img src="${pic}"><span>${p.name}${isMe ? ' (You)' : ''}</span></div>`;
                }).join('');
            }
        });
    }

    function logEntry() {
        let fd = new FormData();
        fd.append('action', 'log_meeting_entry');
        fd.append('room_code', '<?php echo $room_code; ?>');
        fd.append('host_name', '<?php echo addslashes($student_name); ?>');
        fetch('handlers/sacli_room_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { logId = d.log_id; });
    }

    // Log exit when tab is closed
    window.onbeforeunload = function() {
        if(logId) {
            let fd = new FormData();
            fd.append('action', 'log_meeting_exit');
            fd.append('log_id', logId);
            navigator.sendBeacon('handlers/sacli_room_handler.php', fd);
        }
    };

    initMeeting();
</script>

</body>
</html>