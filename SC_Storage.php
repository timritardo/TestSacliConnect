<?php
session_start();
require 'vendor/autoload.php'; // Load Composer libraries
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

require_once __DIR__ . '/config/database.php';

// --- S3 CLOUD CONFIGURATION ---
$s3_config = [
    'version' => 'latest',
    'region'  => 'auto', // Kadalasan 'auto' sa R2, o 'us-east-1' sa AWS
    'endpoint' => 'https://YOUR_ACCOUNT_ID.r2.cloudflarestorage.com', // Palitan ito
    'credentials' => [
        'key'    => 'YOUR_ACCESS_KEY',
        'secret' => 'YOUR_SECRET_KEY',
    ],
];
$s3Client = new S3Client($s3_config);
$bucket_name = 'sacliconnect-storage';

// --- SHARE TOKEN PROTOCOL ---
$public_view = false;
$shared_user_id = null;
$shared_file_id = 0;

if (isset($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);
    $share_q = $conn->query("SELECT user_id, file_id FROM storage_shares WHERE share_token = '$token'");
    if ($share_q && $share_q->num_rows > 0) {
        $share_data = $share_q->fetch_assoc();
        $public_view = true;
        $shared_user_id = $share_data['user_id'];
        $shared_file_id = (int)$share_data['file_id'];
    }
}

// --- HELPER FUNCTIONS ---
function formatSize($bytes) {
    if ($bytes >= 1073741824) { $bytes = number_format($bytes / 1073741824, 2) . ' GB'; }
    elseif ($bytes >= 1048576) { $bytes = number_format($bytes / 1048576, 2) . ' MB'; }
    elseif ($bytes >= 1024) { $bytes = number_format($bytes / 1024, 2) . ' KB'; }
    elseif ($bytes > 1) { $bytes = $bytes . ' bytes'; }
    elseif ($bytes == 1) { $bytes = $bytes . ' byte'; }
    else { $bytes = '0 bytes'; }
    return $bytes;
}

/**
 * Recursively calculates the total size of all files within a folder.
 */
function getFolderSize($conn, $user_id, $folder_id) {
    $size = 0;
    // Prepared statement for security and performance
    $stmt = $conn->prepare("SELECT id, file_type, file_size FROM user_storage WHERE user_id = ? AND parent_id = ?");
    $stmt->bind_param("si", $user_id, $folder_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res) {
        while ($item = $res->fetch_assoc()) {
            if ($item['file_type'] === 'folder') {
                // Drill down into subfolders
                $size += getFolderSize($conn, $user_id, $item['id']);
            } else {
                $size += (int)$item['file_size'];
            }
        }
    }
    $stmt->close();
    return $size;
}

if (!$public_view && !isset($_SESSION['student_id'])) {
    header("Location: SacliConnect_LOG_IN.php");
    exit();
}
$conn->query("CREATE TABLE IF NOT EXISTS user_storage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(100),
    file_size BIGINT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    parent_id INT DEFAULT 0,
    INDEX(user_id),
    INDEX(parent_id)
)");
// Ensure parent_id column exists for users with older database schemas
safeAddColumn($conn, 'user_storage', 'parent_id', "INT DEFAULT 0");

/**
 * Table to store unique share links for users.
 */
$check_shares = $conn->query("SHOW COLUMNS FROM storage_shares LIKE 'file_id'");
if($check_shares->num_rows == 0) {
    $conn->query("DROP TABLE IF EXISTS storage_shares");
    $conn->query("CREATE TABLE storage_shares (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        file_id INT DEFAULT 0,
        share_token VARCHAR(255) UNIQUE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}

// --- DIRECTORY CONTEXT ---
$current_folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : 0;
$current_token_suffix = $public_view ? "&token=" . $_GET['token'] : "";

// Initialize root for public view
if ($public_view && $current_folder_id == 0) {
    $current_folder_id = $shared_file_id;
}

// Security Check: Prevent public viewers from hopping to unauthorized directories
if ($public_view && $current_folder_id != $shared_file_id) {
    $vault_check = $conn->query("SELECT user_id FROM user_storage WHERE id = $current_folder_id");
    if (!$vault_check || $vault_check->fetch_assoc()['user_id'] !== $shared_user_id) {
        $current_folder_id = $shared_file_id; // Reset to the entry point of the share
    }
}

$folder_name_display = "ROO TDIRECTORY";

if ($current_folder_id > 0) {
    $folder_res = $conn->query("SELECT file_name FROM user_storage WHERE id = $current_folder_id AND file_type = 'folder'");
    if ($folder_res && $row = $folder_res->fetch_assoc()) {
        $folder_name_display = strtoupper(htmlspecialchars($row['file_name']));
    }
}

if ($public_view) {
    $my_id = $shared_user_id;
    $user_type = 'guest';
} else {
    $my_id = $_SESSION['student_id'];
    $user_type = $_SESSION['user_type'] ?? 'student';
}

// Fetch Site Theme
$theme_q = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='site_theme'");
$current_theme = ($theme_q && $theme_q->num_rows > 0) ? $theme_q->fetch_assoc()['setting_value'] : 'default';

// Handle Share Link Generation (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'generate_share_link') {
    $token = bin2hex(random_bytes(16));
    $fid = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
    
    // Create a new distinct share record for this specific file/folder
    $conn->query("INSERT INTO storage_shares (user_id, file_id, share_token) VALUES ('$my_id', $fid, '$token')");
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $share_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?token=" . $token;
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'url' => $share_url]);
    exit;
}

// Handle Folder Creation
if (isset($_POST['action']) && $_POST['action'] === 'create_folder' && !$public_view) {
    $folder_name = trim($conn->real_escape_string($_POST['folder_name']));
    $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
    if(!empty($folder_name)) {
        $conn->query("INSERT INTO user_storage (user_id, file_name, file_path, file_type, file_size, parent_id) VALUES ('$my_id', '$folder_name', 'DIR_NODE', 'folder', 0, $parent_id)");
    }
    header("Location: SC_Storage.php" . ($parent_id > 0 ? "?folder_id=$parent_id" : ""));
    exit;
}
// Handle File Upload
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files']) && !$public_view) {
    foreach ($_FILES['files']['name'] as $key => $val) {
        if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['files']['tmp_name'][$key];
            $name = basename($_FILES['files']['name'][$key]);
            $size = $_FILES['files']['size'][$key];
            $type = $_FILES['files']['type'][$key];
            
            $new_name = "sc_" . time() . "_" . uniqid() . "_" . $name;

            try {
                // Upload sa Cloud (S3)
                $result = $s3Client->putObject([
                    'Bucket' => $bucket_name,
                    'Key'    => "uploads/" . $new_name,
                    'SourceFile' => $tmp_name,
                    'ContentType' => $type
                ]);

                // Ang file_path ngayon ay ang Cloud URL
                $dest = $result['ObjectURL']; 

                $stmt = $conn->prepare("INSERT INTO user_storage (user_id, file_name, file_path, file_type, file_size, parent_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssii", $my_id, $name, $dest, $type, $size, $current_folder_id);
                $stmt->execute();
            } catch (AwsException $e) {
                $message = "Cloud Upload Error: " . $e->getAwsErrorMessage();
            }
        }
    }
    $message = "Handshake Successful: Files Uploaded to Node.";
}

// Handle Delete
if (isset($_GET['delete']) && !$public_view) {
    $fid = (int)$_GET['delete'];
    $res = $conn->query("SELECT file_path FROM user_storage WHERE id=$fid AND user_id='$my_id'");
    if ($row = $res->fetch_assoc()) {
        if (!empty($row['file_path']) && file_exists($row['file_path'])) @unlink($row['file_path']);
        $conn->query("DELETE FROM user_storage WHERE id=$fid");
    }
    $loc = "SC_Storage.php" . ($current_folder_id > 0 ? "?folder_id=$current_folder_id" : "");
    header("Location: $loc");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SC Storage // File Explorer</title>
    <link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/1_SacliConnect.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap');

        body {
            margin: 0;
            padding: 0;
            background: radial-gradient(ellipse at bottom, #1b2735 0%, #090a0f 100%) !important;
            overflow: hidden;
            font-family: 'Segoe UI', sans-serif;
        }

        /* --- Global Animations --- */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); filter: blur(10px); }
            to { opacity: 1; transform: translateY(0); filter: blur(0); }
        }

        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-50px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes pulseGlow {
            0% { box-shadow: 0 0 5px rgba(0, 255, 170, 0.2); }
            50% { box-shadow: 0 0 20px rgba(0, 255, 170, 0.5); }
            100% { box-shadow: 0 0 5px rgba(0, 255, 170, 0.2); }
        }

        /* --- Starfield Background --- */
        #stars, #stars2, #stars3 {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            width: 1px; height: 1px;
            background: transparent;
            z-index: -1;
        }
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
    1424px 1836px #fff,
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
        @keyframes animStar { from { transform: translateY(0px); } to { transform: translateY(-2000px); } }

        /* --- Layout Enhancements --- */
        .initial-load .sidebar-item { animation: slideInLeft 0.5s ease forwards; }
        .initial-load .file-card { animation: fadeInUp 0.6s cubic-bezier(0.22, 1, 0.36, 1) forwards; }

        header .nameschool {
            font-family: 'Orbitron', sans-serif !important;
        }

        .explorer-container {
            display: flex;
            height: calc(100vh - 60px);
            width: 100%;
            margin-top: 60px;
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(8px);
        }
        
        /* Shared View Styling */
        .public-banner {
            background: rgba(0, 204, 255, 0.1);
            border-bottom: 1px solid rgba(0, 204, 255, 0.3);
            padding: 10px 30px;
            color: #00ccff;
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
        }

        .explorer-sidebar {
            width: 200px;
            border-right: 1px solid rgba(0, 255, 170, 0.2);
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            background: rgba(10, 31, 22, 0.6);
            backdrop-filter: blur(20px);
        }

        .sidebar-item {
            padding: 10px 15px;
            border-radius: 8px;
            color: #b0fce0;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Orbitron', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .sidebar-item:hover, .sidebar-item.active {
            background: rgba(0, 255, 170, 0.1);
            color: #00ffaa;
        }

        /* Main Area: Explorer View */
        .explorer-main {
            flex: 1;
            width: 100%;
            display: flex;
            flex-direction: column;
            background: transparent;
        }
        .explorer-toolbar {
            padding: 15px 30px;
            border-bottom: 1px solid rgba(0, 255, 170, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(10, 31, 22, 0.4);
            backdrop-filter: blur(10px);
        }
        .breadcrumb {
            color: #509b83;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            letter-spacing: 1px;
        }

        .explorer-body {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 30px;
            align-content: start;
            scrollbar-width: thin;
        }

        .file-card {
            background: rgba(10, 31, 22, 0.6);
            backdrop-filter: blur(12px);
            text-align: center;
            padding: 20px 15px;
            border-radius: 15px;
            border: 1px solid rgba(0, 255, 170, 0.2);
            transition: 0.3s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            opacity: 0; /* for animation */
        }

        .file-card::before {
            content: '';
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(rgba(0, 255, 170, 0.05), transparent);
            opacity: 0; transition: 0.3s;
        }

        .file-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(0, 255, 170, 0.3);
            background: rgba(0, 255, 170, 0.05);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }
        .file-card:hover::before { opacity: 1; }

        .file-icon {
            font-size: 48px;
            margin-bottom: 10px;
            display: block;
            filter: drop-shadow(0 0 5px rgba(0, 255, 170, 0.2));
        }
        .file-name {
            font-size: 12px;
            color: #fff;
            word-break: break-all;
            display: block;
            line-height: 1.2;
            font-weight: 500;
            margin-top: 5px;
        }
        .file-size {
            font-size: 10px;
            color: #00ffaa;
            margin-top: 4px;
            display: block;
            opacity: 0.6;
            font-family: 'Courier New', monospace;
        }

        /* --- Folder Specific Styles --- */
        .folder-node {
            border-color: rgba(255, 215, 0, 0.3) !important;
            background: rgba(255, 215, 0, 0.03) !important;
        }
        .folder-node:hover {
            border-color: #ffd700 !important;
            background: rgba(255, 215, 0, 0.1) !important;
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.2) !important;
        }
        .folder-node .file-icon {
            color: #ffd700 !important;
            filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.5));
        }

        .btn-upload {
            background: linear-gradient(45deg, #00ffaa, #00cc88);
            color: #0a1f16;
            border: none;
            padding: 10px 25px;
            border-radius: 20px;
            font-weight: 900;
            cursor: pointer;
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            letter-spacing: 2px;
            transition: 0.3s;
            box-shadow: 0 0 15px rgba(0, 255, 170, 0.3);
        }
        .btn-upload:hover { 
            transform: scale(1.05);
            box-shadow: 0 0 25px rgba(0, 255, 170, 0.6);
            filter: brightness(1.2);
        }

        .file-actions {
            position: absolute;
            top: 10px; right: 10px;
            display: none;
            z-index: 5;
        }
        .file-card:hover .file-actions { display: block; }
        
        .action-del {
            background: rgba(255, 71, 87, 0.2); 
            color: #ff4757; 
            border: 1px solid #ff4757; 
            width: 26px; height: 26px; 
            border-radius: 50%; 
            cursor: pointer; font-size: 14px;
            transition: 0.2s;
        }
        .action-del:hover { background: #ff4757; color: white; transform: scale(1.1); }
        
        .action-share {
            background: rgba(0, 255, 170, 0.2);
            color: #00ffaa;
            border: 1px solid #00ffaa;
            width: 26px; height: 26px;
            border-radius: 50%;
            cursor: pointer; font-size: 12px;
            transition: 0.2s;
            margin-right: 5px;
        }
        .action-share:hover { background: #00ffaa; color: #0a1f16; transform: scale(1.1); }

        /* --- Modal for Folder Creation --- */
        .modal {
            display: none; position: fixed; z-index: 2000; inset: 0; 
            background: rgba(0,0,0,0.85); align-items: center; justify-content: center; 
            backdrop-filter: blur(10px); animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .modal-content {
            width: 400px; background: #0a1f16; border: 1px solid #00ffaa; 
            border-radius: 15px; padding: 30px; box-shadow: 0 0 50px rgba(0,255,170,0.2);
        }
        .modal-input {
            width: 100%; padding: 12px; background: rgba(0,0,0,0.4); 
            border: 1px solid #00ffaa; border-radius: 8px; color: white; 
            margin-bottom: 20px; outline: none; font-family: 'Courier New', monospace;
            box-sizing: border-box;
        }
        .modal-input:focus { box-shadow: 0 0 15px rgba(0, 255, 170, 0.4); }

        header { 
            position: fixed; top: 0; left: 0; width: 100%; height: 60px; 
            background: rgba(10, 31, 22, 0.95); 
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(0, 255, 170, 0.2); 
            display: flex; align-items: center; justify-content: space-between; 
            padding: 0 20px; z-index: 1000; 
            box-shadow: 0 2px 20px rgba(0,0,0,0.5);
        }

        .capacity-pill {
            background: rgba(0, 255, 170, 0.1);
            padding: 4px 10px;
            border-radius: 20px;
            border: 1px solid rgba(0, 255, 170, 0.3);
            font-family: 'Orbitron', sans-serif;
            font-size: 10px;
            color: #00ffaa;
        }

        /* Glassmorphism Scrollbar */
        .explorer-body::-webkit-scrollbar { width: 6px; }
        .explorer-body::-webkit-scrollbar-track { background: transparent; }
        .explorer-body::-webkit-scrollbar-thumb { background: rgba(0, 255, 170, 0.2); border-radius: 10px; }
        .explorer-body::-webkit-scrollbar-thumb:hover { background: rgba(0, 255, 170, 0.5); }
    </style>
</head>
<body class="theme-<?php echo $current_theme; ?> initial-load">
    <!-- Parallax Starfield -->
    <div id="stars"></div>
    <div id="stars2"></div>
    <div id="stars3"></div>

    <header>
        <div style="display:flex; align-items:center; gap:15px;">
            <?php if(!$public_view): ?>
                <a href="SacliConnect.php" style="color:#00ffaa; text-decoration:none; font-weight:bold; font-family:'Orbitron'; font-size: 12px; letter-spacing: 1px;">← BACK</a>
            <?php else: ?>
                <img src="assets/images/St.Anne_logo.png" style="width:30px;">
            <?php endif; ?>
            <div style="width: 2px; height: 20px; background: rgba(255,255,255,0.1);"></div>
            <h2 style="font-family: 'Orbitron'; font-size: 18px; color: #fff; margin: 0; letter-spacing: 3px; font-weight: 900;">SC_STORAGE <span style="color:#00ffaa; font-size: 0.6em; vertical-align: middle;">v1.0</span></h2>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <div class="capacity-pill">STATUS: <?php echo $public_view ? 'READ_ONLY' : 'ACTIVE'; ?></div>
            <div style="color:#00ffaa; font-family:'Courier New'; font-size:12px; font-weight: bold; background: rgba(0,0,0,0.3); padding: 5px 12px; border-radius: 5px; border: 1px solid rgba(0,255,170,0.2);">
                STUDENT ID: <?php echo $my_id; ?>
            </div>
        </div>
    </header>

    <div class="explorer-container">
        <?php if(!$public_view): ?>
        <aside class="explorer-sidebar">
            <div class="sidebar-item active">📂 My Drive</div>
            <div class="sidebar-item">🕒 Recent</div>
            <div class="sidebar-item">⭐ Starred</div>
            <div class="sidebar-item">🗑️ Trash</div>
            
            <?php
            // Accurate Drive Capacity Calculation
            $usage_q = $conn->query("SELECT SUM(file_size) as total FROM user_storage WHERE user_id = '$my_id' AND file_type != 'folder'");
            $used_bytes = ($usage_q) ? (int)$usage_q->fetch_assoc()['total'] : 0;
            $limit_bytes = 1024 * 1024 * 1024 * 1024; // 1TB
            $usage_pct = ($used_bytes > 0) ? min(100, ($used_bytes / $limit_bytes) * 100) : 0;
            
            $used_formatted = formatSize($used_bytes);
            $limit_formatted = formatSize($limit_bytes);
            ?>
            <div style="margin-top:auto; padding: 15px; background:rgba(0,255,170,0.05); border-radius:10px; border: 1px solid rgba(0,255,170,0.1);">
                <div style="font-size:10px; color:#509b83; margin-bottom:5px;">DRIVE_CAPACITY</div>
                <div style="height:4px; background:#111; border-radius:2px; overflow:hidden;">
                    <div style="width: <?php echo $usage_pct; ?>%; height:100%; background:#00ffaa; box-shadow: 0 0 10px #00ffaa;"></div>
                </div>
                <div style="font-size:9px; color:#aaa; margin-top:5px;"><?php echo $used_formatted; ?> of <?php echo $limit_formatted; ?> used</div>
            </div>
        </aside>
        <?php endif; ?>

        <main class="explorer-main">
            <?php if($public_view): ?>
                <div class="public-banner">📡 INCOMING_TRANSMISSION: SECURE_VIEWER_LINK_ESTABLISHED // ACCESS_MODE: RESTRICTED</div>
            <?php endif; ?>
            
            <div class="explorer-toolbar">
                <div class="breadcrumb" style="display: flex; align-items: center; gap: 8px;">
                    <?php if ($current_folder_id > 0 && (!$public_view || $current_folder_id != $shared_file_id)): 
                        $up_q = $conn->query("SELECT parent_id FROM user_storage WHERE id = $current_folder_id");
                        $up_id = ($up_q && $r = $up_q->fetch_assoc()) ? $r['parent_id'] : 0;
                    ?>
                        <!-- Go Back Button moved to toolbar -->
                        <button class="btn-upload" style="background:transparent; border:1px solid #ffd700; color:#ffd700; padding: 6px 15px; margin-right: 10px;" onclick="location.href='?folder_id=<?php echo $up_id . $current_token_suffix; ?>'">⬅ GO_BACK</button>
                    <?php endif; ?>

                    <span></span> <?php echo $public_view ? 'SHARED_DIRECTORY' : 'MY SC DRIVE'; ?> / <span style="color:#00ffaa; font-weight:bold;"><?php echo $folder_name_display; ?></span>
                </div>
                <?php if(!$public_view): ?>
                    <div style="display:flex; gap:10px;">
                        <button class="btn-upload" style="background:transparent; border:1px solid #00ffaa; color:#00ffaa;" onclick="openFolderModal()">+NEW FOLDER</button>
                        <button class="btn-upload" style="background:transparent; border:1px solid #00ffaa; color:#00ffaa;" onclick="generateShareLink(<?php echo $current_folder_id; ?>)">SHARE LINK</button>
                        <button class="btn-upload" onclick="document.getElementById('fileUpload').click()">UPLOAD FILE</button>
                        <form id="uploadForm" method="POST" enctype="multipart/form-data" style="display:none;">
                            <input type="file" name="files[]" id="fileUpload" multiple onchange="showUploadProgress(); document.getElementById('uploadForm').submit()">
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if($message): ?>
                <div style="background:rgba(0,255,170,0.1); color:#00ffaa; padding:10px 25px; font-size:12px; font-family:monospace; border-bottom:1px solid rgba(0,255,170,0.2);">
                    >> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="explorer-body">
                <?php
                function getIcon($type) {
                    if ($type === 'folder') return '📁';
                    if (strpos($type, 'image') !== false) return '🖼️';
                    if (strpos($type, 'video') !== false) return '🎬';
                    if (strpos($type, 'pdf') !== false) return '📕';
                    if (strpos($type, 'word') !== false) return '📄';
                    if (strpos($type, 'zip') !== false || strpos($type, 'rar') !== false) return '📦';
                    return '📄';
                }

                // Sort folders first, then files by upload date
                if ($public_view && $current_folder_id == $shared_file_id && $shared_file_id > 0) {
                    // Special case: If sharing a single file, display only that file card
                    $base_chk = $conn->query("SELECT file_type FROM user_storage WHERE id = $shared_file_id")->fetch_assoc();
                    if ($base_chk && $base_chk['file_type'] !== 'folder') {
                        $files = $conn->query("SELECT * FROM user_storage WHERE id = $shared_file_id");
                    } else {
                        $files = $conn->query("SELECT * FROM user_storage WHERE user_id = '$my_id' AND parent_id = $current_folder_id ORDER BY (file_type = 'folder') DESC, uploaded_at DESC");
                    }
                } else {
                    $files = $conn->query("SELECT * FROM user_storage WHERE user_id = '$my_id' AND parent_id = $current_folder_id ORDER BY (file_type = 'folder') DESC, uploaded_at DESC");
                }
                $count = 1; // Start at 1 to account for the "New Folder" card
                ?>

                <?php
                if ($files && $files->num_rows > 0):
                    while ($f = $files->fetch_assoc()):
                        $isFolder = ($f['file_type'] === 'folder');
                        // If folder, calculate nested content size. If file, use database value.
                        $display_size = $isFolder ? getFolderSize($conn, $my_id, $f['id']) : $f['file_size'];
                        $icon = getIcon($f['file_type']);
                        $clickAction = $isFolder ? "location.href='?folder_id=".$f['id'].$current_token_suffix."'" : "window.open('".$f['file_path']."', '_blank')";
                ?>
                <div class="file-card <?php echo $isFolder ? 'folder-node' : ''; ?>" style="animation-delay: <?php echo ($count++ * 0.05); ?>s" onclick="<?php echo $clickAction; ?>">
                    <div class="file-actions">
                        <?php if(!$public_view): ?>
                            <button class="action-share" title="Share individual item" onclick="event.stopPropagation(); generateShareLink(<?php echo $f['id']; ?>);">🔗</button>
                            <button class="action-del" title="Erase node" onclick="event.stopPropagation(); requestDelete(<?php echo $f['id']; ?>, '<?php echo htmlspecialchars(addslashes($f['file_name']), ENT_QUOTES); ?>')">×</button>
                        <?php else: ?>
                            <button class="action-share" title="Download" onclick="event.stopPropagation(); window.open('<?php echo $f['file_path']; ?>', '_blank')">⬇</button>
                        <?php endif; ?>
                    </div>
                    <span class="file-icon"><?php echo $icon; ?></span>
                    <span class="file-name" title="<?php echo htmlspecialchars($f['file_name']); ?>"><?php echo htmlspecialchars($f['file_name']); ?></span>
                    <span class="file-size"><?php echo formatSize($display_size); ?></span>
                </div>
                <?php endwhile; else: ?>
                    <div style="grid-column: 1/-1; text-align:center; padding:100px 0; color:#555; font-family:'Orbitron';">
                        <div style="font-size:40px; margin-bottom:10px;">☁️</div>
                        YOUR_CLOUD_IS_EMPTY
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Create Folder Modal UI -->
    <div id="folderModal" class="modal">
        <div class="modal-content">
            <h3 style="color:#00ffaa; font-family:'Orbitron'; font-size:16px; margin-top:0; letter-spacing: 2px;">// NEW DIRECTORY CONFIG</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_folder">
                <input type="hidden" name="parent_id" value="<?php echo $current_folder_id; ?>">
                <label style="color: #509b83; font-size: 11px; margin-bottom: 8px; display: block;">Enter Folder Identification:</label>
                <input type="text" name="folder_name" class="modal-input" placeholder="FOLDER NAME HERE..." required autocomplete="off">
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-upload" style="flex:1;">CREATE_FOLDER</button>
                    <button type="button" class="btn-upload" style="flex:1; background:transparent; border:1px solid #ff4757; color:#ff4757;" onclick="closeFolderModal()">CANCEL</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Custom Confirm Modal for Deletion -->
    <div id="customConfirmModal" class="modal">
        <div class="modal-content" style="text-align: center; border-color: #ff4757; box-shadow: 0 0 50px rgba(255, 71, 87, 0.2);">
            <h3 style="color:#ff4757; font-family:'Orbitron', sans-serif; font-size:16px; margin-top:0; letter-spacing: 2px;">// CONFIRM ERASURE</h3>
            <p id="confirmText" style="color: #e4e6eb; margin-bottom: 25px; font-size: 14px; line-height: 1.5; font-family: 'Courier New', monospace;"></p>
            <div style="display: flex; gap: 10px;">
                <button id="confirmYesBtn" class="btn-upload" style="flex:1; background:#ff4757; color:white; border-radius: 8px; box-shadow: 0 0 15px rgba(255, 71, 87, 0.4);">DELETE FOLDER</button>
                <button type="button" class="btn-upload" style="flex:1; background:transparent; border:1px solid #555; color:#aaa; border-radius: 8px;" onclick="closeConfirmModal()">CANCEL</button>
            </div>
        </div>
    </div>

    <script>
        function showUploadProgress() {
            const btn = document.querySelector('.btn-upload');
            btn.innerText = "SYNCHRONIZING...";
            btn.style.background = "#fff";
            btn.style.color = "#000";
            btn.disabled = true;
        }

        function openFolderModal() { document.getElementById('folderModal').style.display = 'flex'; }
        function closeFolderModal() { document.getElementById('folderModal').style.display = 'none'; }

        let deleteTargetId = null;
        function requestDelete(id, name) {
            deleteTargetId = id;
            document.getElementById('confirmText').innerText = `CAUTION: Are you sure you want to permanently erase the folder "${name}"? This sequence cannot be reversed.`;
            document.getElementById('customConfirmModal').style.display = 'flex';
        }

        function closeConfirmModal() {
            document.getElementById('customConfirmModal').style.display = 'none';
        }

        document.getElementById('confirmYesBtn').onclick = function() {
            if(deleteTargetId) {
                window.location.href = `?delete=${deleteTargetId}&folder_id=<?php echo $current_folder_id; ?>`;
            }
        };

        // Close modal when clicking outside
        window.onclick = (e) => { 
            if(e.target == document.getElementById('folderModal')) closeFolderModal(); 
            if(e.target == document.getElementById('customConfirmModal')) closeConfirmModal(); 
        }

        function generateShareLink(fileId = 0) {
            let fd = new FormData();
            fd.append('action', 'generate_share_link');
            fd.append('file_id', fileId);
            
            fetch('SC_Storage.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    copyToClipboard(data.url);
                    alert("LINK_COPIED: A secure access portal has been generated and copied to your clipboard.");
                }
            });
        }

        function copyToClipboard(text) {
            const el = document.createElement('textarea');
            el.value = text;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
            
            // Animation effect on button if exists
            const btn = document.querySelector('.btn-upload');
            btn.innerText = "COPIED!";
            setTimeout(() => { btn.innerText = "INITIALIZE_UPLOAD"; }, 2000);
        }

        // Simple click sound effect simulated
        document.querySelectorAll('.sidebar-item, .file-card').forEach(item => {
            item.addEventListener('click', () => {
                const audio = new Audio('<https://assets.mixkit.co/active_storage/sfx/2568/2568-preview.mp3>');
                audio.volume = 0.2;
                audio.play().catch(e => {});
            });
        });
    </script>
</body>
</html>