<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: SacliConnect_LOG_IN.php");
    exit();
}

$my_id = $_SESSION['student_id'];
$is_admin = ($_SESSION['student_name'] === 'Admin');
$is_teacher = (strpos($my_id, 'T-') === 0);
$is_verified_for_update = (isset($_SESSION['profile_update_verified']) && $_SESSION['profile_update_verified'] === true) || $is_admin;

// --- FETCH CURRENT DATA ---
if ($is_teacher) {
    $real_id = substr($my_id, 2);
    $user = $conn->query("SELECT *, name as student_name, department as course, position as year_level FROM teachers WHERE id = '$real_id'")->fetch_assoc();
} else {
    $user = $conn->query("SELECT * FROM students WHERE student_id = '$my_id'")->fetch_assoc();
}

if (!$user) die("User node not found.");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Identity // SacliConnect</title>
    <link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Segoe+UI:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: radial-gradient(circle at center, #0d2b1f 0%, #020806 100%);
            color: #e4e6eb;
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow-x: hidden; /* Prevent horizontal scroll */
            position: relative;
        }
        /* Animated Background Scanline */
        body::after {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to bottom, transparent 50%, rgba(0, 255, 170, 0.05) 51%, transparent 51%);
            background-size: 100% 4px;
            pointer-events: none;
            z-index: -1;
            animation: scanlineFlow 10s linear infinite;
        }
        @keyframes scanlineFlow { 0% { background-position: 0 0; } 100% { background-position: 0 100%; } }

        /* Tech Grid Overlay (Existing) */
        body::before {
            content: ""; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: linear-gradient(rgba(0, 255, 170, 0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(0, 255, 170, 0.05) 1px, transparent 1px);
            background-size: 30px 30px; z-index: -1;
        }

        .edit-container {
            width: 90%; /* Use percentage for better responsiveness */
            max-width: 1100px; /* Increased max-width */
            background: rgba(16, 46, 34, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 255, 170, 0.2);
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
            animation: slideUp 0.6s cubic-bezier(0.22, 1, 0.36, 1), containerPulse 5s infinite alternate;
            position: relative; /* For holographic corners */
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes containerPulse {
            0% { border-color: rgba(0, 255, 170, 0.2); box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
            50% { border-color: rgba(0, 255, 170, 0.5); box-shadow: 0 25px 50px rgba(0,0,0,0.6), 0 0 30px rgba(0,255,170,0.3); }
            100% { border-color: rgba(0, 255, 170, 0.2); box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
        }
        /* Holographic Corners for Edit Container */
        .edit-container::before, .edit-container::after { content: ''; position: absolute; width: 30px; height: 30px; border-color: #00ffaa; border-style: solid; opacity: 0.7; }
        .edit-container::before { top: -2px; left: -2px; border-width: 3px 0 0 3px; border-radius: 20px 0 0 0; }
        .edit-container::after { bottom: -2px; right: -2px; border-width: 0 3px 3px 0; border-radius: 0 0 20px 0; }




        .edit-header {
            padding: 30px;
            background: linear-gradient(to right, rgba(0, 255, 170, 0.1), transparent);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex; justify-content: space-between; align-items: center;
        }
        .edit-header h2 {
            margin: 0; font-family: 'Orbitron', sans-serif; font-size: 20px;
            letter-spacing: 2px; color: #00ffaa; text-transform: uppercase;
        }
        .back-link { color: #aaa; text-decoration: none; font-size: 13px; font-weight: bold; transition: 0.3s; }
        .back-link:hover { color: #fff; }

        .edit-body { padding: 40px; display: grid; grid-template-columns: 280px 1fr; gap: 40px; }

        /* Left Column: Visuals */
        .visual-col { text-align: center; }
        .pfp-editor {
            width: 180px;
            height: 180px;
            margin: 0 auto 25px;
            border-radius: 50%; border: 4px solid #00ffaa;
            position: relative; overflow: hidden; cursor: pointer;
            box-shadow: 0 0 20px rgba(0, 255, 170, 0.3);
            animation: pfpPulse 3s infinite alternate;
        }
        @keyframes pfpPulse {
            0% { box-shadow: 0 0 20px rgba(0, 255, 170, 0.3); border-color: #00ffaa; }
            100% { box-shadow: 0 0 35px rgba(0, 255, 170, 0.6); border-color: #fff; }
        }

        .pfp-editor img { width: 100%; height: 100%; object-fit: cover; }
        .pfp-overlay {
            position: absolute; inset: 0; background: rgba(0,0,0,0.6);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: 0.3s; color: #00ffaa; font-weight: bold; font-size: 12px;
        }
        .pfp-editor:hover .pfp-overlay { opacity: 1; }

        /* Right Column: Form */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-row { grid-column: span 2; }

        .form-group { margin-bottom: 15px; }
        .form-label { display: block; font-size: 11px; text-transform: uppercase; color: #00ffaa; margin: 0 0 8px 5px; font-weight: 700; letter-spacing: 1px; text-shadow: 0 0 5px rgba(0,255,170,0.2); }
        .form-input {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(0, 255, 170, 0.2); border-radius: 10px;
            color: #fff; outline: none; transition: 0.3s; font-size: 14px;
            text-shadow: 0 0 5px rgba(0,255,170,0.2);
        }
        .form-input:focus { border-color: #00ffaa; box-shadow: 0 0 15px rgba(0,255,170,0.1); background: rgba(0,0,0,0.5); }
        .form-input[readonly] { color: #666; cursor: not-allowed; background: rgba(0,0,0,0.1); border-color: rgba(0,255,170,0.1); }


        .verification-bar {
            background: rgba(0, 255, 170, 0.05); padding: 12px 15px;
            border-radius: 10px; margin-top: 10px; border: 1px dashed rgba(0, 255, 170, 0.3);
            display: flex; justify-content: space-between; align-items: center;
        }
        .verify-link { color: #00ffaa; font-size: 11px; font-weight: 800; cursor: pointer; text-decoration: none; text-transform: uppercase; }
        .verify-link:hover { text-shadow: 0 0 10px #00ffaa; }

        .save-btn {
            grid-column: span 2; padding: 18px; margin-top: 20px;
            background: linear-gradient(45deg, #00ffaa, #00cc88);
            border: none; border-radius: 12px; color: #0a1f16; /* Dark text for neon */
            font-family: 'Orbitron', sans-serif; font-weight: 900;
            cursor: pointer; transition: 0.3s; letter-spacing: 2px;
        }
        .save-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0, 255, 170, 0.4); filter: brightness(1.1); }

        @media (max-width: 800px) {
            .edit-body { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .full-row { grid-column: span 1; }
            .save-btn { grid-column: span 1; }
        }

        /* Map Modal Styles */
        .modal {
            display: none; position: fixed; z-index: 10000; inset: 0;
            background: rgba(0,0,0,0.9); align-items: center; justify-content: center; 
            backdrop-filter: blur(10px);
        }
        .modal-content {
            max-width: 800px; width: 90%; background: #1a3d2f; border: 1px solid #00ffaa;
            padding: 0; border-radius: 15px; overflow: hidden;
            position: relative; /* For holographic corners */
        }
        #map { width: 100%; height: 400px; background: #000; }
        .modal-footer {
            padding: 20px; display: flex; flex-direction: column; gap: 10px; background: rgba(0,0,0,0.2);
        }
        .action-btn-pill {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            color: white; padding: 10px 20px; border-radius: 10px; cursor: pointer;
            font-weight: 700; font-size: 12px; transition: 0.3s; display: flex; align-items: center; gap: 8px;
            text-transform: uppercase; font-family: 'Orbitron';
        }
        .action-btn-pill:hover { background: #00ffaa; color: #0a1f16; }
    </style>
    <style>
        /* Additional styles for the map modal corners */
        .modal-content::before, .modal-content::after { content: ''; position: absolute; width: 20px; height: 20px; border-color: #00ffaa; border-style: solid; opacity: 0.5; }
        .modal-content::before { top: -1px; left: -1px; border-width: 2px 0 0 2px; border-radius: 10px 0 0 0; }
        .modal-content::after { bottom: -1px; right: -1px; border-width: 0 2px 2px 0; border-radius: 0 0 10px 0; }
    </style>
</head>
<body>

    <div class="edit-container">
        <div class="edit-header">
            <h2>// UPDATE_IDENTITY_NODE</h2>
            <a href="Student_Profile.php?id=<?php echo $my_id; ?>" class="back-link">CANCEL_EXIT</a>
        </div>

        <div class="edit-body">
            <div class="visual-col">
                <form action="Student_Profile.php?id=<?php echo $my_id; ?>" method="POST" enctype="multipart/form-data" id="mainEditForm">
                    <div class="pfp-editor" onclick="document.getElementById('pfpInput').click()">
                        <?php $pic = !empty($user['profile_pic']) ? "uploads/".$user['profile_pic'] : "assets/images/3icons8-student-64.png"; ?>
                        <img src="<?php echo htmlspecialchars($pic); ?>" id="pfpPreview">
                        <div class="pfp-overlay">UPLOAD_PHOTO</div>
                    </div>
                    <input type="file" name="profile_pic" id="pfpInput" style="display:none;" onchange="previewImage(this)">
                    
                    <div class="form-group">
                        <label class="form-label">Privacy Toggle</label>
                        <div class="verification-bar" style="border-style: solid; display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-radius: 10px; background: rgba(0, 255, 170, 0.05);">
                            <span style="font-size:12px; color:#b0fce0;">Hide Phone Number</span>
                            <label class="switch" style="position:relative; display:inline-block; width:40px; height:20px;">
                                <input type="checkbox" name="hide_phone" <?php echo ($user['hide_phone'] == 1) ? 'checked' : ''; ?> style="opacity:0; width:0; height:0;">
                                <span class="slider round" style="position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background-color:#333; transition:.4s; border-radius:34px;"></span>
                            </label>
                        </div>
                            <span style="font-size:12px; color:#b0fce0;">Hide Phone Number</span>
                            <input type="checkbox" name="hide_phone" <?php echo ($user['hide_phone'] == 1) ? 'checked' : ''; ?> style="accent-color:#00ffaa; width:18px; height:18px;">
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; text-align: left; font-size: 11px; color: #509b83; font-family: 'Courier New', monospace;">
                        >> SYSTEM_ID: <?php echo $my_id; ?><br>
                        >> ENCRYPTION: AES_256<br>
                        >> STATUS: UPLINK_READY
                    </div> 
            </div>

            <div class="form-col">
                <div class="form-grid">
                    <div class="form-group full-row">
                        <label class="form-label">Biography</label>
                        <textarea name="bio" class="form-input" rows="3" placeholder="Tell the community about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo $is_teacher ? 'Position' : 'Year Level'; ?></label>
                        <?php if($is_teacher): ?>
                            <input type="text" name="year_level" class="form-input" value="<?php echo htmlspecialchars($user['year_level'] ?? ''); ?>">
                        <?php else: ?>
                            <select name="year_level" class="form-input" style="background:#0a1f16;">
                                <option value="1st Year" <?php echo ($user['year_level'] == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                <option value="2nd Year" <?php echo ($user['year_level'] == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                <option value="3rd Year" <?php echo ($user['year_level'] == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                <option value="4th Year" <?php echo ($user['year_level'] == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                <option value="Alumni" <?php echo ($user['year_level'] == 'Alumni') ? 'selected' : ''; ?>>Alumni</option>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo $is_teacher ? 'Department' : 'Course'; ?></label>
                        <input type="text" name="course" class="form-input" value="<?php echo htmlspecialchars($user['course'] ?? ''); ?>">
                    </div>

                    <div class="form-group full-row">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" id="emailInp" class="form-input" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" <?php echo $is_verified_for_update ? '' : 'readonly'; ?>>
                        <?php if(!$is_verified_for_update): ?>
                        <div class="verification-bar">
                            <span style="font-size:11px; color:#888;">Security verification required to change.</span>
                            <span class="verify-link" onclick="triggerSecurity()">Authorize</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group full-row">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" id="phoneInp" class="form-input" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" <?php echo $is_verified_for_update ? '' : 'readonly'; ?>>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Birthdate</label>
                        <input type="date" name="birthdate" class="form-input" value="<?php echo $user['birthdate'] ?? ''; ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-input" style="background:#0a1f16;">
                            <option value="Male" <?php echo ($user['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($user['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($user['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group full-row">
                        <label class="form-label">Location</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" name="location" id="locInp" class="form-input" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>" placeholder="Lucena City, Philippines" readonly>
                            <button type="button" class="save-btn" style="margin: 0; padding: 0 15px; width: auto; font-size: 10px;" onclick="openMapModal()">MAP_PIN</button>
                        </div>
                    </div>

                    <button type="submit" class="save-btn">AUTHORIZE_DATA_COMMIT</button>
                </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Map Selection Modal -->
    <div class="modal" id="mapModal">
        <div class="modal-content">
            <div style="padding: 15px; border-bottom: 1px solid rgba(0,255,170,0.2); display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 1;">
                <h3 style="margin:0; color:#00ffaa; font-family: 'Orbitron'; font-size: 14px;">SELECT_GEOGRAPHIC_NODE</h3>
                <span style="color:#ff5555; cursor:pointer; font-size:24px;" onclick="closeMapModal()">&times;</span>
            </div>
            <div id="map"></div>
            <div class="modal-footer">
                <div id="addressPreview" style="font-size: 13px; color: #fff; font-weight: bold; min-height: 20px; font-family: 'Courier New';"></div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="action-btn-pill" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2);" onclick="closeMapModal()">CANCEL</button>
                    <button type="button" id="confirmLocationBtn" class="action-btn-pill" style="background: #00ffaa; color: #0a1f16;" disabled onclick="confirmLocation()">LINK_LOCATION</button>
                </div>
            </div>
        </div>
    </div>

    <!-- --- SCRIPTS --- -->
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('pfpPreview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function triggerSecurity() {
            // Redirect to verify flow or open modal from post_interaction.php logic
            if(confirm("To change sensitive info, a verification code will be sent to your registered email. Proceed?")) {
                let formData = new FormData();
                formData.append('action', 'profile_verify_send');
                fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        let otp = prompt("Enter 6-digit code sent to " + data.email);
                        if(otp) {
                            let vd = new FormData();
                            vd.append('action', 'profile_verify_otp');
                            vd.append('otp', otp);
                            fetch('handlers/post_interaction.php', { method: 'POST', body: vd })
                            .then(r => r.json())
                            .then(res => {
                                if(res.status === 'success') {
                                    alert("Identity Verified! Fields unlocked.");
                                    location.reload();
                                } else { alert(res.message); }
                            });
                        }
                    } else { alert(data.message); }
                });
            }
        }

        // --- MAP FUNCTIONS ---
        let map, marker;
        let selectedAddress = "";

        function openMapModal() {
            document.getElementById('mapModal').style.display = 'flex';
            setTimeout(() => {
                if (!map) {
                    map = L.map('map').setView([13.9373, 121.6146], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(map);

                    map.on('click', function(e) {
                        updateMarker(e.latlng.lat, e.latlng.lng);
                    });
                }
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(position => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        map.setView([lat, lng], 16);
                        updateMarker(lat, lng);
                    });
                }
                map.invalidateSize();
            }, 300);
        }

        function closeMapModal() { document.getElementById('mapModal').style.display = 'none'; }

        function updateMarker(lat, lng) {
            if (marker) marker.setLatLng([lat, lng]);
            else marker = L.marker([lat, lng], {draggable: true}).addTo(map);
            
            document.getElementById('confirmLocationBtn').disabled = true;
            document.getElementById('addressPreview').innerText = "RESOLVING_COORDINATES...";

            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
            .then(res => res.json())
            .then(data => {
                let parts = data.address;
                selectedAddress = (parts.suburb || parts.neighbourhood || "") + ", " + (parts.city || parts.town || "") + ", " + (parts.province || "");
                selectedAddress = selectedAddress.replace(/^, /, "");
                document.getElementById('addressPreview').innerText = "📍 " + selectedAddress;
                document.getElementById('confirmLocationBtn').disabled = false;
            });
        }

        function confirmLocation() {
            document.getElementById('locInp').value = selectedAddress;
            closeMapModal();
        }
    </script>
</body>
</html>