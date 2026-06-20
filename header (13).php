<?php 
error_reporting(E_ERROR | E_WARNING | E_PARSE);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once('database.php');
require_once('library.php');
require_once('funciones.php');
require 'requirelanguage.php';
require 'requirelanguage_image.php';

isUser();

// ═══════════════════════════════════════════════════════════════
// NOTIFICATION SYSTEM
// ═══════════════════════════════════════════════════════════════
$ticket_count  = 0;
$ticket_items  = [];

if (isset($_SESSION['user_name'])) {
    $hdr_pat  = trim($_SESSION['user_name']) . '%';
    $emp_stmt = mysqli_prepare($dbConn,
        "SELECT id FROM hr_employees WHERE name LIKE ? AND status='active' LIMIT 1");
    if ($emp_stmt) {
        mysqli_stmt_bind_param($emp_stmt, "s", $hdr_pat);
        mysqli_stmt_execute($emp_stmt);
        $emp_row = mysqli_fetch_assoc(mysqli_stmt_get_result($emp_stmt));
        mysqli_stmt_close($emp_stmt);

        if ($emp_row) {
            $hdr_eid = (int)$emp_row['id'];
            $cs = mysqli_prepare($dbConn,
                "SELECT COUNT(*) AS total FROM tickets
                 WHERE assigned_to = ?
                   AND status NOT IN ('closed','resolved','Approved','Rejected')");
            if ($cs) {
                mysqli_stmt_bind_param($cs, "i", $hdr_eid);
                mysqli_stmt_execute($cs);
                $cr = mysqli_fetch_assoc(mysqli_stmt_get_result($cs));
                $ticket_count = (int)($cr['total'] ?? 0);
                mysqli_stmt_close($cs);
            }
            $ps = mysqli_prepare($dbConn,
                "SELECT id, ticket_number, subject, priority, status, created_at
                 FROM tickets
                 WHERE assigned_to = ?
                   AND status NOT IN ('closed','resolved','Approved','Rejected')
                 ORDER BY id DESC LIMIT 5");
            if ($ps) {
                mysqli_stmt_bind_param($ps, "i", $hdr_eid);
                mysqli_stmt_execute($ps);
                $pr = mysqli_stmt_get_result($ps);
                while ($r = mysqli_fetch_assoc($pr)) $ticket_items[] = $r;
                mysqli_stmt_close($ps);
            }
        }
    }
}

$total_notification_count = $ticket_count;

// User permissions
$user_id = 0; $permissions = [];
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'Administrator') {
    $r = mysqli_query($dbConn, "SELECT cid FROM manager_admin WHERE name='".$_SESSION["user_name"]."' LIMIT 1");
    if ($rw = mysqli_fetch_array($r)) $user_id = $rw['cid'];
} elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'Employee') {
    $r = mysqli_query($dbConn, "SELECT cid FROM manager_user WHERE name='".$_SESSION["user_name"]."' LIMIT 1");
    if ($rw = mysqli_fetch_array($r)) $user_id = $rw['cid'];
}
if ($user_id > 0) {
    $stmt = $dbConn->prepare("SELECT section_name,can_access FROM manager_permissions WHERE user_id=?");
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $permissions[$row['section_name']] = $row['can_access'];
    $stmt->close();
}

// Greeting
date_default_timezone_set('Asia/Kolkata');
$t = date("H");
if ($t < 12)      $mensaje = $L_['Goodmorning'];
else if ($t < 18) $mensaje = $L_['Goodafternoon'];
else              $mensaje = $L_['Goodnight'];

$is_admin    = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'Administrator';
$is_employee = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'Employee';

// Define the Global "Back to Dashboard" URL Logic
$dashboard_url = 'db-dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title><?php echo $_SESSION['ge_cname']; ?> | <?php echo $Paneladministracion; ?></title>
  <meta name="description" content="<?php echo $_SESSION['ge_description']; ?>"/>
  <meta name="keywords" content="<?php echo $_SESSION['ge_keywords']; ?>" />
  <meta name="author" content="Jaomweb">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />

  <link rel="shortcut icon" type="image/png" href="img/favicon.png"/>
  <link rel="stylesheet" href="../bower_components/bootstrap/dist/css/bootstrap.css" type="text/css" />
  <link rel="stylesheet" href="../bower_components/animate.css/animate.css" type="text/css" />
  <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css" type="text/css" />
  <link rel="stylesheet" href="../bower_components/simple-line-icons/css/simple-line-icons.css" type="text/css" />
  <link rel="stylesheet" href="css/font.css" type="text/css" />
  <link rel="stylesheet" href="css/app.css" type="text/css" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
  /* ════════════════════════════════════════════════════
     ROOT VARIABLES — Navy Blue Theme
  ════════════════════════════════════════════════════ */
  :root {
    --navy-900: #0a1628;
    --navy-800: #0d1f3c;
    --navy-700: #112347;
    --navy-600: #153060;
    --navy-500: #1a3a72;
    --navy-400: #1e4d9b;
    --navy-300: #2563c4;
    --navy-200: #4a8ae8;
    --navy-100: #93b8f5;
    --navy-50:  #dce9fc;
    --accent:   #f59e0b;
    --accent-2: #10b981;
    --danger:   #ef4444;
    --white:    #ffffff;
    --text-muted: rgba(255,255,255,0.55);
    --border:   rgba(255,255,255,0.08);
    --sidebar-w: 240px;
    --topbar-h:  68px;
    --font: 'Outfit', sans-serif;
  }

  * { box-sizing: border-box; }
  body, html { font-family: var(--font) !important; margin: 0; padding: 0; }

  /* ════════════════════════════════════════════════════
     TOP BAR — full white
  ════════════════════════════════════════════════════ */
  #main-topbar {
    position: fixed;
    top: 0; left: 240px; right: 0;
    height: var(--topbar-h);
    background: #ffffff;
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px 0 16px;
    z-index: 1000;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
  }

  /* Topbar carousel — takes all middle space */
  .topbar-carousel-wrap {
    flex: 1;
    overflow: hidden;
    background: #ffffff;
    border-radius: 0;
    margin: 0 16px;
    height: 100%;
    display: flex;
    align-items: center;
    border: none;
  }
  .logo-carousel { overflow: hidden; white-space: nowrap; width: 100%; }
  .logo-track {
    display: inline-flex;
    align-items: center;
    animation: scroll-left 15s linear infinite;
  }
  .logo-track img { height: 52px; width: auto; margin: 0 24px; object-fit: contain; }
  @keyframes scroll-left {
    0%   { transform: translateX(0); }
    100% { transform: translateX(-50%); }
  }

  /* Clock on white topbar */
  .topbar-clock {
    font-size: 13px;
    color: var(--navy-700);
    font-weight: 600;
    padding: 5px 12px;
    background: #f1f5f9;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    margin-right: 6px;
  }

  /* Bell on white topbar */
  .notif-bell-wrap {
    position: relative;
    width: 40px; height: 40px;
    border-radius: 10px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: background .2s;
  }
  .notif-bell-wrap:hover { background: #e2e8f0; }
  .notif-bell-wrap .fa-bell { font-size: 17px; color: var(--navy-700); }
  .notif-bell-wrap.has-notif .fa-bell { color: var(--accent); animation: bell-ring .7s ease; }

  /* User button on white topbar */
  .topbar-user-btn {
    display: flex; align-items: center; gap: 9px;
    padding: 5px 12px 5px 6px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
    transition: background .2s;
    text-decoration: none !important;
  }
  .topbar-user-btn:hover { background: #e2e8f0; }
  .topbar-user-greet { font-size: 10px; color: #64748b; display: block; }
  .topbar-user-name  { font-size: 13px; font-weight: 700; color: var(--navy-800); display: block; }
  .topbar-user-caret { color: #94a3b8; font-size: 11px; margin-left: 2px; }

  /* Mobile menu button on white topbar */
  .mob-menu-btn {
    display: none;
    width: 38px; height: 38px;
    border-radius: 10px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    align-items: center; justify-content: center;
    cursor: pointer; margin-right: 8px;
    color: var(--navy-800); font-size: 16px;
  }

  /* Right side controls */
  .topbar-right {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
  }

  @keyframes bell-ring {
    0%,100%{transform:rotate(0)} 20%,60%{transform:rotate(-18deg)} 40%,80%{transform:rotate(18deg)}
  }
  .notif-badge {
    position: absolute;
    top: -4px; right: -4px;
    background: var(--danger);
    color: #fff;
    border-radius: 10px;
    min-width: 16px; height: 16px;
    font-size: 9px; font-weight: 800;
    line-height: 16px; text-align: center;
    padding: 0 4px;
    border: 2px solid #fff;
    display: none;
  }

  /* Notification dropdown */
  .notif-drop {
    display: none;
    position: absolute;
    top: calc(100% + 8px); right: 0;
    width: 320px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 12px 40px rgba(0,0,0,.22);
    border: 1px solid #e2e8f0;
    overflow: hidden;
    z-index: 9999;
  }
  .notif-drop.open { display: block; animation: drop-in .18s ease; }
  @keyframes drop-in { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }

  .notif-drop-head {
    background: linear-gradient(135deg, var(--navy-700), var(--navy-500));
    padding: 12px 16px;
    display: flex; align-items: center; justify-content: space-between;
  }
  .notif-drop-head .nd-title { color: #fff; font-weight: 700; font-size: 13px; }
  .notif-drop-head .nd-pill {
    background: rgba(255,255,255,.18);
    color: #fff; font-size: 10px; font-weight: 800;
    border-radius: 20px; padding: 2px 9px;
  }
  .notif-row {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    text-decoration: none !important;
    background: #fff;
    transition: background .12s;
  }
  .notif-row:hover { background: #f0f7ff; }
  .notif-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--navy-300); flex-shrink: 0; margin-top: 4px; }
  .notif-info { flex: 1; min-width: 0; }
  .notif-meta { display: flex; justify-content: space-between; margin-bottom: 2px; }
  .notif-num  { font-size: 11px; font-weight: 700; color: var(--navy-400); }
  .notif-age  { font-size: 10px; color: #94a3b8; }
  .notif-subj { font-size: 12px; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; margin-bottom: 3px; }
  .notif-prio { display: inline-block; padding: 1px 7px; border-radius: 5px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
  .np-h { background: #fde8e8; color: #c0392b; }
  .np-m { background: #fef3cd; color: #d68910; }
  .np-l { background: #d5f5e3; color: #1e8449; }
  .notif-empty { padding: 30px 20px; text-align: center; }
  .notif-empty i { font-size: 32px; color: #cbd5e1; display: block; margin-bottom: 10px; }
  .notif-empty h6 { color: #475569; font-weight: 700; font-size: 13px; margin: 0 0 4px; }
  .notif-empty p  { color: #94a3b8; font-size: 11px; margin: 0; }
  .notif-footer {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    padding: 10px; background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    color: var(--navy-400); font-weight: 700; font-size: 12px;
    text-decoration: none !important;
    transition: background .12s;
  }
  .notif-footer:hover { background: #eff6ff; }

  /* Toast */
  .notif-toast {
    position: fixed;
    top: 80px; right: 20px;
    background: var(--navy-500);
    color: #fff;
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 13px; font-weight: 600;
    box-shadow: 0 6px 20px rgba(0,0,0,.2);
    z-index: 99999;
    display: none;
    animation: toast-in .3s ease;
    border-left: 4px solid var(--accent);
  }
  .notif-toast i { margin-right: 8px; color: var(--accent); }
  @keyframes toast-in { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }

  /* User avatar */
  .topbar-user-avatar {
    width: 32px; height: 32px;
    border-radius: 8px;
    object-fit: cover;
    background: #e2e8f0;
  }
  .topbar-user-info { line-height: 1.2; }

  /* User dropdown menu */
  .topbar-user-drop {
    position: absolute;
    top: calc(100% + 8px); right: 0;
    width: 210px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 12px 40px rgba(0,0,0,.18);
    border: 1px solid #e2e8f0;
    overflow: hidden;
    display: none;
    z-index: 9999;
    animation: drop-in .18s ease;
  }
  .topbar-user-drop.open { display: block; }
  .topbar-user-drop-head {
    padding: 14px 16px;
    background: linear-gradient(135deg, var(--navy-800), var(--navy-600));
    border-bottom: 1px solid var(--border);
  }
  .topbar-user-drop-head span { color: #fff; font-size: 13px; font-weight: 600; display: block; }
  .topbar-user-drop-head small { color: var(--navy-100); font-size: 11px; }
  .topbar-user-drop a {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 16px;
    font-size: 13px; font-weight: 500; color: #334155;
    text-decoration: none !important;
    transition: background .12s;
    border-bottom: 1px solid #f1f5f9;
  }
  .topbar-user-drop a:hover { background: #f0f7ff; color: var(--navy-400); }
  .topbar-user-drop a i { width: 16px; text-align: center; color: var(--navy-300); }
  .topbar-user-drop a.logout { color: var(--danger); }
  .topbar-user-drop a.logout i { color: var(--danger); }
  .topbar-user-drop a.logout:hover { background: #fef2f2; }

  /* ════════════════════════════════════════════════════
     SIDEBAR
  ════════════════════════════════════════════════════ */
  #main-sidebar {
    position: fixed;
    top: 0; left: 0;
    width: auto;
    min-width: 240px;
    max-width: 300px;
    height: 100vh;
    background: var(--navy-900);
    border-right: none;
    display: flex;
    flex-direction: column;
    z-index: 1001;
    overflow: visible;
    transition: width .25s ease;
  }

  /* Sidebar brand */
  .sidebar-brand {
    height: var(--topbar-h);
    display: flex; align-items: center;
    padding: 0 16px;
    background: #ffffff;
    border-bottom: 2px solid #e2e8f0;
    gap: 10px;
    flex-shrink: 0;
  }
  .sidebar-brand img { height: 38px; width: auto; border-radius: 6px; flex-shrink: 0; }
  .sidebar-brand-text { font-size: 15px; font-weight: 800; color: var(--navy-800); letter-spacing: 0.3px; white-space: nowrap; }

  /* Sidebar scroll area */
  .sidebar-nav {
    flex: 1;
    overflow-y: auto;
    overflow-x: visible;
    padding: 10px 0 20px;
  }
  .sidebar-nav::-webkit-scrollbar { width: 4px; }
  .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
  .sidebar-nav::-webkit-scrollbar-thumb { background: var(--navy-600); border-radius: 4px; }

  /* Section labels */
  .nav-label {
    font-size: 9px; font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 1.2px;
    padding: 14px 16px 4px;
  }

  /* Nav items */
  .snav { list-style: none; margin: 0; padding: 0; }
  .snav li { position: relative; }

  .snav > li > a,
  .snav .sub-menu li > a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 16px;
    font-size: 17px;
    font-weight: 700;
    color: #ffffff;
    text-decoration: none !important;
    border-radius: 0;
    transition: background .15s, color .15s;
    white-space: normal;
    word-break: break-word;
    cursor: pointer;
    border-left: 3px solid transparent;
    letter-spacing: 0.2px;
  }
  .snav > li > a:hover,
  .snav .sub-menu li > a:hover {
    background: rgba(255,255,255,0.06);
    color: #fff;
    border-left-color: var(--navy-300);
  }
  .snav > li.active > a {
    background: rgba(37,99,196,0.22);
    color: #fff;
    border-left-color: var(--navy-200);
  }
  .snav > li > a i.nav-icon {
    width: 20px; text-align: center;
    font-size: 17px;
    flex-shrink: 0;
    transition: color .15s;
  }

  .nav-arrow {
    margin-left: auto;
    font-size: 11px;
    color: rgba(255,255,255,0.3);
    transition: transform .2s;
    flex-shrink: 0;
  }
  .snav li.open > a .nav-arrow { transform: rotate(90deg); color: var(--navy-200); }

  /* Sub menu — hidden by default at ALL levels */
  .sub-menu {
    list-style: none;
    margin: 0; padding: 0;
    background: rgba(0,0,0,0.18);
    display: none;
  }
  /* Open at ANY level — top, second, third */
  .snav li.open > .sub-menu { display: block; animation: sub-in .15s ease; }
  @keyframes sub-in { from{opacity:0;transform:translateY(-4px)} to{opacity:1;transform:translateY(0)} }

  .sub-menu li > a {
    padding: 9px 16px 9px 40px !important;
    font-size: 15px !important;
    font-weight: 600 !important;
    color: rgba(255,255,255,0.90) !important;
    border-left: 3px solid transparent !important;
  }
  .sub-menu li > a:hover {
    color: #fff !important;
    background: rgba(255,255,255,0.07) !important;
    border-left-color: var(--navy-300) !important;
  }
  .sub-menu li > a i { width: 18px; text-align: center; font-size: 15px; flex-shrink: 0; }
  .sub-menu .sub-menu li > a { padding-left: 58px !important; font-size: 14px !important; }
  .sub-menu .sub-menu li > a i { font-size: 14px; width: 16px; }

  /* ── Icon colors — every icon vivid color ── */
  .snav > li > a i.nav-icon { color: rgba(255,255,255,0.75); }
  .snav > li > a:hover i.nav-icon, .snav > li.active > a i.nav-icon { color: #fff; }

  .fa-bar-chart      { color: #60a5fa; }
  .fa-barcode        { color: #94a3b8; }
  .fa-bell           { color: #fbbf24; }
  .fa-bell-slash     { color: #94a3b8; }
  .fa-bicycle        { color: #4ade80; }
  .fa-briefcase      { color: #fbbf24; }
  .fa-building       { color: #60a5fa; }
  .fa-bullhorn       { color: #facc15; }
  .fa-bullseye       { color: #f87171; }
  .fa-calculator     { color: #60a5fa; }
  .fa-calendar       { color: #fb923c; }
  .fa-car            { color: #60a5fa; }
  .fa-check          { color: #4ade80; }
  .fa-check-circle   { color: #4ade80; }
  .fa-check-square   { color: #34d399; }
  .fa-clock-o        { color: #f472b6; }
  .fa-cog            { color: #94a3b8; }
  .fa-cogs           { color: #94a3b8; }
  .fa-commenting     { color: #25D366; }
  .fa-comments       { color: #34d399; }
  .fa-credit-card    { color: #34d399; }
  .fa-cube           { color: #c084fc; }
  .fa-cubes          { color: #fb923c; }
  .fa-cutlery        { color: #f87171; }
  .fa-database       { color: #38bdf8; }
  .fa-download       { color: #60a5fa; }
  .fa-envelope       { color: #60a5fa; }
  .fa-exchange       { color: #34d399; }
  .fa-file-image-o   { color: #c084fc; }
  .fa-file-text      { color: #60a5fa; }
  .fa-filter         { color: #818cf8; }
  .fa-folder-open    { color: #fbbf24; }
  .fa-globe          { color: #4ade80; }
  .fa-headphones     { color: #f472b6; }
  .fa-list           { color: #38bdf8; }
  .fa-list-alt       { color: #38bdf8; }
  .fa-magic          { color: #c084fc; }
  .fa-map-marker     { color: #f87171; }
  .fa-money          { color: #4ade80; }
  .fa-plane          { color: #fb923c; }
  .fa-plus-circle    { color: #4ade80; }
  .fa-question-circle { color: #a78bfa; }
  .fa-refresh        { color: #34d399; }
  .fa-reply          { color: #f87171; }
  .fa-road           { color: #94a3b8; }
  .fa-sign-in        { color: #4ade80; }
  .fa-sign-out       { color: #fb923c; }
  .fa-sitemap        { color: #38bdf8; }
  .fa-star           { color: #fbbf24; }
  .fa-comment        { color: #25D366; }
  .fa-tablet         { color: #60a5fa; }
  .fa-tag            { color: #c084fc; }
  .fa-ticket         { color: #f472b6; }
  .fa-times-circle   { color: #f87171; }
  .fa-truck          { color: #fb923c; }
  .fa-undo           { color: #fb923c; }
  .fa-upload         { color: #38bdf8; }
  .fa-user           { color: #a78bfa; }
  .fa-users          { color: #a78bfa; }
  .fa-wrench         { color: #94a3b8; }
  .fa-archive        { color: #fbbf24; }
  .fa-arrow-right    { color: #60a5fa; }
  .fa-angle-right    { color: rgba(255,255,255,0.30) !important; }

  /* Nested sub-sub menu */
  .sub-menu .sub-menu li > a { padding-left: 58px !important; font-size: 13px !important; }

  /* Logout bottom */
  .sidebar-logout {
    flex-shrink: 0;
    padding: 10px 12px;
    border-top: 1px solid var(--border);
  }
  .sidebar-logout a {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 14px;
    border-radius: 8px;
    font-size: 13px; font-weight: 500;
    color: rgba(255,100,100,0.85);
    text-decoration: none !important;
    transition: background .15s;
  }
  .sidebar-logout a:hover { background: rgba(239,68,68,0.12); color: #ff7070; }
  .sidebar-logout a i { font-size: 14px; }

  /* ════════════════════════════════════════════════════
     CONTENT OFFSET
  ════════════════════════════════════════════════════ */
  .app-content, #content {
    margin-left: 240px !important;
    margin-top: var(--topbar-h) !important;
  }

  /* ════════════════════════════════════════════════════
     RESPONSIVE
  ════════════════════════════════════════════════════ */
  @media (max-width: 768px) {
    #main-sidebar { transform: translateX(-240px); width: 240px; }
    #main-sidebar.mobile-open { transform: translateX(0); }
    #main-topbar { left: 0; }
    .app-content, #content { margin-left: 0 !important; }
    .mob-menu-btn { display: flex !important; }
  }

  /* Hide original header/aside */
  #header.app-header, #aside.app-aside { display: none !important; }
  </style>
</head>
<body>
<div class="app">

<!-- ══════════════════════════════════════════
     TOAST
══════════════════════════════════════════ -->
<div class="notif-toast" id="notifToast">
  <i class="fa fa-bell"></i><span id="notifToastText">You have a new notification!</span>
</div>

<!-- ══════════════════════════════════════════
     SIDEBAR
══════════════════════════════════════════ -->
<aside id="main-sidebar">

  <!-- Brand -->
  <div class="sidebar-brand">
    <img src="logo-image/logo1.jpeg" alt="Logo">
    <span class="sidebar-brand-text">ABRA Group</span>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">
    <ul class="snav">

      <?php if (!empty($permissions['dashboard']) && $permissions['dashboard'] == 1): ?>
      <li><a href="index.php"><i class="fa fa-bar-chart nav-icon"></i> Dashboard</a></li>
      <?php endif; ?>

      <!-- ── HRM ── -->
      <?php if ($is_admin || (!empty($permissions['hrm']) && $permissions['hrm']==1)): ?>
      <li>
        <a><i class="fa fa-users nav-icon"></i> HRM <i class="fa fa-angle-right nav-arrow"></i></a>
        <ul class="sub-menu">
          <?php if ($is_admin || (!empty($permissions['hrm_screening_hub']) && $permissions['hrm_screening_hub']==1)): ?>
          <li><a><i class="fa fa-filter"></i> Screening Hub <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
            <ul class="sub-menu">
              <?php if ($is_admin || (!empty($permissions['hrm_career']) && $permissions['hrm_career']==1)): ?><li><a href="hr-career.php"><i class="fa fa-briefcase"></i> Career</a></li><?php endif; ?>
            </ul>
          </li>
          <?php endif; ?>
          <?php if (!empty($permissions['hrm_employees'])      && $permissions['hrm_employees']      ==1): ?><li><a href="hr-employees-list.php"><i class="fa fa-user"></i> Employees</a></li><?php endif; ?>
          <?php if (!empty($permissions['hrm_departments'])    && $permissions['hrm_departments']    ==1): ?><li><a href="hr-departments-list.php"><i class="fa fa-sitemap"></i> Departments</a></li><?php endif; ?>
          <?php if (!empty($permissions['hrm_attendance'])     && $permissions['hrm_attendance']     ==1): ?><li><a href="hr-attendance-list.php"><i class="fa fa-clock-o"></i> Attendance</a></li><?php endif; ?>
          <?php if (!empty($permissions['hrm_leave_requests']) && $permissions['hrm_leave_requests'] ==1): ?><li><a href="hr-leave-requests.php"><i class="fa fa-calendar"></i> Leave Requests</a></li><?php endif; ?>
          <?php if (!empty($permissions['hrm_payroll'])        && $permissions['hrm_payroll']        ==1): ?><li><a href="hr-payroll.php"><i class="fa fa-money"></i> Payroll</a></li><?php endif; ?>
          <?php if (!empty($permissions['hrm_notice_board'])   && $permissions['hrm_notice_board']   ==1): ?><li><a href="hr-notices.php"><i class="fa fa-bullhorn"></i> Notice Board</a></li><?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['hrm_kpi']) && $permissions['hrm_kpi']==1)): ?>
          <li><a><i class="fa fa-bar-chart"></i> KPI <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
            <ul class="sub-menu">
              <?php if ($is_admin || (!empty($permissions['hrm_kpq'])            && $permissions['hrm_kpq']           ==1)): ?><li><a href="hr-kpq.php"><i class="fa fa-question-circle"></i> KPQ</a></li><?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['hrm_kpi_evaluation']) && $permissions['hrm_kpi_evaluation']==1)): ?><li><a href="hr-kpi-evaluation.php"><i class="fa fa-star"></i> KPI Evaluation</a></li><?php endif; ?>
            </ul>
          </li>
          <?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['hrm_feedback']) && $permissions['hrm_feedback']==1)): ?><li><a href="hr-feedback.php"><i class="fa fa-comments"></i> Feedback</a></li><?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>

      <!-- ── Abra Design and Build ── -->
      <?php if ($is_admin || (!empty($permissions['abra_design_build']) && $permissions['abra_design_build']==1)): ?>
      <li>
        <a href="db-dashboard.php"><i class="fa fa-building nav-icon"></i> Abra Design & Build <i class="fa fa-angle-right nav-arrow"></i></a>
        <ul class="sub-menu">
          <?php if ($is_admin || (!empty($permissions['adb_leads']) && $permissions['adb_leads']==1)): ?>
          <li><a><i class="fa fa-users"></i> Leads <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
            <ul class="sub-menu">
              <li><a href="db-leads-list.php"><i class="fa fa-list"></i> Leads List</a></li>
              <li><a href="lead-dashboard.php"><i class="fa fa-tachometer"></i> Lead Dashboard</a></li>
            </ul>
          </li>
          <?php endif; ?>
          
          <?php if ($is_admin || (!empty($permissions['adb_projects']) && $permissions['adb_projects']==1)): ?>
          <li><a><i class="fa fa-folder-open"></i> Projects <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
            <ul class="sub-menu">
              <?php if ($is_admin || (!empty($permissions['adb_projects_list']) && $permissions['adb_projects_list']==1)): ?>
              <li><a href="db-projects-list.php"><i class="fa fa-list"></i> Projects List</a></li>
              <?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['adb_proj_tasks']) && $permissions['adb_proj_tasks']==1)): ?>
              <li><a href="db-project-tasks.php"><i class="fa fa-check-square"></i> Tasks</a></li>
              <?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['adb_proj_proof']) && $permissions['adb_proj_proof']==1)): ?>
              <li><a href="db-work-proof.php"><i class="fa fa-file-image-o"></i> Work Proofs</a></li>
              <?php endif; ?>
            </ul>
          </li>
          <?php endif; ?>
          
          <?php 
          $has_any_finance = $is_admin || (!empty($permissions['adb_finance']) && $permissions['adb_finance']==1) || 
                             (!empty($permissions['adb_proj_finance']) && $permissions['adb_proj_finance']==1) || 
                             (!empty($permissions['adb_proj_fin_dash']) && $permissions['adb_proj_fin_dash']==1) || 
                             (!empty($permissions['adb_proj_fin_budg']) && $permissions['adb_proj_fin_budg']==1) || 
                             (!empty($permissions['adb_proj_fin_exp']) && $permissions['adb_proj_fin_exp']==1) || 
                             (!empty($permissions['adb_proj_fin_pay']) && $permissions['adb_proj_fin_pay']==1);
                             
          $has_finance_projects = $is_admin || (!empty($permissions['adb_finance_projects']) && $permissions['adb_finance_projects']==1) ||
                                  (!empty($permissions['adb_proj_finance']) && $permissions['adb_proj_finance']==1) || 
                                  (!empty($permissions['adb_proj_fin_dash']) && $permissions['adb_proj_fin_dash']==1) || 
                                  (!empty($permissions['adb_proj_fin_budg']) && $permissions['adb_proj_fin_budg']==1) || 
                                  (!empty($permissions['adb_proj_fin_exp']) && $permissions['adb_proj_fin_exp']==1) || 
                                  (!empty($permissions['adb_proj_fin_pay']) && $permissions['adb_proj_fin_pay']==1);
          ?>
          <?php if ($has_any_finance): ?>
          <li><a><i class="fa fa-money"></i> Finance <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
            <ul class="sub-menu">
              <?php if ($has_finance_projects): ?>
              <li><a href="db-financial-info.php"><i class="fa fa-wallet"></i> Financial Dashboard</a></li>
              <?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['adb_finance_reports']) && $permissions['adb_finance_reports']==1)): ?>
              <?php // <li><a href="db-reports-page.php"><i class="fa fa-file-text"></i> Reports</a></li> ?>
              <?php endif; ?>
            </ul>
          </li>
          <?php endif; ?>
          
          <?php if ($is_admin || (!empty($permissions['adb_customer_db']) && $permissions['adb_customer_db']==1)): ?>
          <?php // <li><a href="db-customer-project-converted.php"><i class="fa fa-database"></i> Converted Projects</a></li> ?>
          <?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>

      <!-- ── Sales ── -->
      <?php if ($is_admin || (!empty($permissions['sales']) && $permissions['sales']==1)): ?>
      <li>
        <a><i class="fa fa-bar-chart nav-icon"></i> Sales <i class="fa fa-angle-right nav-arrow"></i></a>
        <ul class="sub-menu">
          <?php
          $leads_icon_map = ['db'=>'fa-database','al'=>'fa-truck','rp'=>'fa-refresh','at'=>'fa-plane','agt'=>'fa-globe','afw'=>'fa-cutlery'];
          foreach(['db'=>'DB','al'=>'AL','rp'=>'RP','at'=>'AT','agt'=>'AGT','afw'=>'AFW'] as $bk=>$bl):
            if (!($is_admin || (!empty($permissions[$bk.'_leads']) && $permissions[$bk.'_leads']==1))) continue;
            $bk_icon = $leads_icon_map[$bk] ?? 'fa-bullseye';
          ?>
          <li><a><i class="fa <?=$bk_icon?>"></i> <?=$bl?> Leads <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
            <ul class="sub-menu">
              <li><a href="tt-sale-leads.php?b=<?=$bk?>"><i class="fa fa-bullseye"></i> Sale Leads</a></li>
              <?php if ($is_admin || (!empty($permissions[$bk.'_leads_database']) && $permissions[$bk.'_leads_database']==1)): ?><li><a href="tt-customer-database.php?b=<?=$bk?>"><i class="fa fa-database"></i> Customer Database</a></li><?php endif; ?>
            </ul>
          </li>
          <?php endforeach; ?>
          <?php if ($is_admin || (!empty($permissions['az_leads']) && $permissions['az_leads']==1)): ?>
          <li><a><i class="fa fa-tag"></i> AZ Leads <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
            <ul class="sub-menu">
              <li><a href="tt-sale-leads.php?b=az"><i class="fa fa-bullseye"></i> Sale Leads</a></li>
              <li><a href="abra_sales_leads.php"><i class="fa fa-globe"></i> Sales Leads - Website</a></li>
              <li><a href="abra_vendors.php"><i class="fa fa-exchange"></i> Vendor Management</a></li>
              <?php if ($is_admin || (!empty($permissions['az_leads_database']) && $permissions['az_leads_database']==1)): ?><li><a href="tt-customer-database.php?b=az"><i class="fa fa-database"></i> Customer Database</a></li><?php endif; ?>
            </ul>
          </li>
          <?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>

      <!-- ── Operations ── -->
      <?php if ($is_admin || (!empty($permissions['operations']) && $permissions['operations']==1)): ?>
      <li>
        <a><i class="fa fa-wrench nav-icon"></i> Operations <i class="fa fa-angle-right nav-arrow"></i></a>
        <ul class="sub-menu">
          <?php if ($is_admin || (!empty($permissions['fleet_management']) && $permissions['fleet_management']==1)): ?>
          <li><a><i class="fa fa-truck"></i> Fleet Management <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
            <ul class="sub-menu">
              <?php if ($is_admin || (!empty($permissions['fleet_vehicles']) && $permissions['fleet_vehicles']==1)): ?>
              <li><a><i class="fa fa-car"></i> Vehicles <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
                <ul class="sub-menu">
                  <li><a href="fleet_vehicles_list.php"><i class="fa fa-car"></i> Vehicles</a></li>
                  <?php if ($is_admin || (!empty($permissions['fleet_maintenance']) && $permissions['fleet_maintenance']==1)): ?><li><a href="fleet_maintenance_list.php"><i class="fa fa-wrench"></i> Maintenance</a></li><?php endif; ?>
                  <?php if ($is_admin || (!empty($permissions['customer_fleet'])    && $permissions['customer_fleet']   ==1)): ?><li><a href="customer_fleet_list.php"><i class="fa fa-users"></i> Customer Fleet</a></li><?php endif; ?>
                </ul>
              </li>
              <?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['fleet_drivers'])      && $permissions['fleet_drivers']     ==1)): ?><li><a href="fleet_drivers_list.php"><i class="fa fa-user"></i> Drivers</a></li><?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['fleet_gps_tracking']) && $permissions['fleet_gps_tracking']==1)): ?><li><a href="fleet_gps_tracking.php"><i class="fa fa-map-marker"></i> GPS Tracking</a></li><?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['fleet_trips'])        && $permissions['fleet_trips']       ==1)): ?><li><a href="fleet_trips_list.php"><i class="fa fa-road"></i> Trips & Deliveries</a></li><?php endif; ?>
            </ul>
          </li>
          <?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['storage']) && $permissions['storage']==1) || (!empty($permissions['warehouses']) && $permissions['warehouses']==1)): ?>
          <li><a><i class="fa fa-building"></i> Warehouse <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
            <ul class="sub-menu">
              <?php if ($is_admin || (!empty($permissions['warehouses'])           && $permissions['warehouses']          ==1)): ?><li><a href="warehouses.php"><i class="fa fa-building"></i> Warehouses</a></li><?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['products'])             && $permissions['products']            ==1)): ?><li><a href="product.php"><i class="fa fa-cube"></i> Products</a></li><?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['storage'])              && $permissions['storage']             ==1)): ?><li><a href="storage.php"><i class="fa fa-archive"></i> Storage</a></li><?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['fulfillment'])          && $permissions['fulfillment']         ==1)): ?><li><a href="fulfillment.php"><i class="fa fa-check-circle"></i> Fulfillment</a></li><?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['inventory_management']) && $permissions['inventory_management']==1)): ?><li><a href="inventory.php"><i class="fa fa-list-alt"></i> Inventory</a></li><?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['fleet_list'])           && $permissions['fleet_list']          ==1)): ?><li><a href="fleet_list.php"><i class="fa fa-truck"></i> Fleet</a></li><?php endif; ?>
            </ul>
          </li>
          <?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['pickup_orders']) && $permissions['pickup_orders']==1)): ?>
          <li><a><i class="fa fa-plus-circle"></i> Pickup <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
            <ul class="sub-menu">
              <?php if ($is_admin || (!empty($permissions['pickup_orders'])  && $permissions['pickup_orders'] ==1)): ?><li><a href="pickup-orders.php"><i class="fa fa-plus-circle"></i> Pickup Orders</a></li><?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['pickup_reports']) && $permissions['pickup_reports']==1)): ?><li><a href="pickup-reports.php"><i class="fa fa-file-text"></i> Pickup Reports</a></li><?php endif; ?>
            </ul>
          </li>
          <?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['process_of_scanning']) && $permissions['process_of_scanning']==1)): ?><li><a href="process-of-scanning.php"><i class="fa fa-barcode"></i> Process of Scanning</a></li><?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['order_update'])        && $permissions['order_update']       ==1)): ?><li><a href="order-update.php"><i class="fa fa-refresh"></i> Order Update</a></li><?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['rts'])                 && $permissions['rts']                ==1)): ?><li><a href="rts.php"><i class="fa fa-undo"></i> RTS</a></li><?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['rto']) && $permissions['rto']==1)): ?>
          <li><a><i class="fa fa-reply"></i> RTO <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
            <ul class="sub-menu">
              <?php if ($is_admin || (!empty($permissions['rto'])             && $permissions['rto']            ==1)): ?><li><a href="rto.php"><i class="fa fa-reply"></i> RTO</a></li><?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['process_for_rto']) && $permissions['process_for_rto']==1)): ?><li><a href="process-for-rto.php"><i class="fa fa-cogs"></i> Process for RTO</a></li><?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['rto_inscan'])      && $permissions['rto_inscan']     ==1)): ?><li><a href="rto-inscan.php"><i class="fa fa-sign-in"></i> RTO Inscan</a></li><?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['rto_outscan'])     && $permissions['rto_outscan']    ==1)): ?><li><a href="rto-outscan.php"><i class="fa fa-sign-out"></i> RTO Outscan</a></li><?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['rto_delivered'])   && $permissions['rto_delivered']  ==1)): ?><li><a href="rto-delivered.php"><i class="fa fa-check"></i> RTO Delivered</a></li><?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['cancel_shipment']) && $permissions['cancel_shipment']==1)): ?><li><a href="cancel-shipment.php"><i class="fa fa-times-circle"></i> Cancel Shipment</a></li><?php endif; ?>
            </ul>
          </li>
          <?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['pod_pickup']) && $permissions['pod_pickup']==1) || (!empty($permissions['pod_delivery']) && $permissions['pod_delivery']==1)): ?>
          <li><a><i class="fa fa-file-image-o"></i> POD <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
            <ul class="sub-menu">
              <?php if ($is_admin || (!empty($permissions['pod_pickup'])   && $permissions['pod_pickup']  ==1)): ?><li><a href="pickup-pod.php"><i class="fa fa-upload"></i> Pickup POD</a></li><?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['pod_delivery']) && $permissions['pod_delivery']==1)): ?><li><a href="delivery-pod.php"><i class="fa fa-download"></i> Delivery POD</a></li><?php endif; ?>
            </ul>
          </li>
          <?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>

      <?php if (!empty($permissions['shipment_reports']) && $permissions['shipment_reports']==1): ?><li><a href="shipment-reports.php"><i class="fa fa-file-text nav-icon"></i> Shipment Reports</a></li><?php endif; ?>
      <?php if (!empty($permissions['delivery_agent'])   && $permissions['delivery_agent']  ==1): ?><li><a href="delivery-agent.php"><i class="fa fa-bicycle nav-icon"></i> Delivery Agent</a></li><?php endif; ?>

      <!-- ── Vendors ── -->
      <?php if (!empty($permissions['vendors']) && $permissions['vendors']==1): ?>
      <li>
        <a><i class="fa fa-exchange nav-icon"></i> Vendors <i class="fa fa-angle-right nav-arrow"></i></a>
        <ul class="sub-menu">
          <?php if (!empty($permissions['at_vendors'])  && $permissions['at_vendors'] ==1): ?><li><a href="vendors.php?b=at"><i class="fa fa-plane"></i> AT Vendors</a></li><?php endif; ?>
          <?php if (!empty($permissions['db_vendors'])  && $permissions['db_vendors'] ==1): ?><li><a href="vendors.php?b=db"><i class="fa fa-database"></i> DB Vendors</a></li><?php endif; ?>
          <?php if (!empty($permissions['al_vendors'])  && $permissions['al_vendors'] ==1): ?>
          <li><a><i class="fa fa-truck"></i> AL Vendors <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
            <ul class="sub-menu">
              <?php if (!empty($permissions['truck_union_clients']) && $permissions['truck_union_clients']==1): ?>
              <li><a href="truck-union-clients.php"><i class="fa fa-globe"></i> Online Vendors</a></li>
              <?php endif; ?>
              <?php if (!empty($permissions['al_vendors_offline']) && $permissions['al_vendors_offline']==1): ?>
              <li><a href="vendors.php?b=al"><i class="fa fa-users"></i> Offline Vendors</a></li>
              <?php endif; ?>
            </ul>
          </li>
          <?php endif; ?>
          <?php if (!empty($permissions['rp_vendors'])  && $permissions['rp_vendors'] ==1): ?><li><a href="vendors.php?b=rp"><i class="fa fa-refresh"></i> RP Vendors</a></li><?php endif; ?>
          <?php if (!empty($permissions['az_vendors'])  && $permissions['az_vendors'] ==1): ?><li><a href="vendors.php?b=az"><i class="fa fa-tag"></i> AZ Vendors</a></li><?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['abra_service_vendors']) && $permissions['abra_service_vendors']==1)): ?><li><a href="vendors.php?b=asv"><i class="fa fa-wrench"></i> Abra Service Vendors</a></li><?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['abra_global_trading'])  && $permissions['abra_global_trading'] ==1)): ?><li><a href="vendors.php?b=agt"><i class="fa fa-globe"></i> Abra Global Trading</a></li><?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['abra_food_works'])      && $permissions['abra_food_works']    ==1)): ?><li><a href="vendors.php?b=afw"><i class="fa fa-cutlery"></i> Abra Food Works</a></li><?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['vendor_onboarding'])    && $permissions['vendor_onboarding']  ==1)): ?><li><a href="vendor-onboarding.php"><i class="fa fa-plus-circle"></i> Vendor Onboarding</a></li><?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>

      <!-- ── Customer Service ── -->
      <?php if (!empty($permissions['customer_service']) && $permissions['customer_service']==1): ?>
      <li>
        <a><i class="fa fa-headphones nav-icon"></i> Customer Service <i class="fa fa-angle-right nav-arrow"></i></a>
        <ul class="sub-menu">
          <?php if (!empty($permissions['website_contacts'])   && $permissions['website_contacts']  ==1): ?><li><a href="website-contacts.php"><i class="fa fa-envelope"></i> Website Contacts</a></li><?php endif; ?>
          <?php if (!empty($permissions['enquiry_sheet'])      && $permissions['enquiry_sheet']     ==1): ?><li><a href="cs-enquiry-list.php"><i class="fa fa-question-circle"></i> Enquiry Sheet</a></li><?php endif; ?>
          <?php if (!empty($permissions['shipments'])          && $permissions['shipments']         ==1): ?><li><a href="active-shipments.php"><i class="fa fa-truck"></i> Shipments</a></li><?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['whatsapp_inbox']) && $permissions['whatsapp_inbox']==1)): ?><li><a href="whatsapp-inbox.php"><i class="fa fa-comment" style="color:#25D366"></i> WhatsApp Inbox</a></li><?php endif; ?>
          <?php if (!empty($permissions['az_orders'])          && $permissions['az_orders']         ==1): ?><li><a href="abra-zylo-orders.php"><i class="fa fa-tag"></i> AZ Orders</a></li><?php endif; ?>
          <?php if (!empty($permissions['enquiries_shipments_export']) && $permissions['enquiries_shipments_export']==1): ?><li><a href="enquiries-shipments-export.php"><i class="fa fa-download"></i> Enquiries Export</a></li><?php endif; ?>
          <?php if (!empty($permissions['customer_service_cancel_shipment']) && $permissions['customer_service_cancel_shipment']==1): ?><li><a href="cancel-shipment.php"><i class="fa fa-times-circle"></i> Cancel Shipment</a></li><?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>

      <!-- ── Abra Service ── -->
      <?php
      $service_map   = ['home_service'=>'Home Service','general_repair'=>'General Repair Services','transport_porter'=>'Transport & Porter Services','health_wellness'=>'Health & Wellness','fitness_lifestyle'=>'Fitness & Lifestyle','subscription_consumables'=>'Subscription-Based Consumables','skill_development'=>'Skill Development & Coaching Services','professional_services'=>'Professional Services','technology_software'=>'Technology & Software','financial_insurance'=>'Financial & Insurance Services','real_estate'=>'Real Estate & Construction','logistics_supply'=>'Logistics & Supply Chain','service_report'=>'Service Report'];
      $service_icons = ['home_service'=>'fa-cog','general_repair'=>'fa-wrench','transport_porter'=>'fa-truck','health_wellness'=>'fa-plus-circle','fitness_lifestyle'=>'fa-bicycle','subscription_consumables'=>'fa-refresh','skill_development'=>'fa-star','professional_services'=>'fa-briefcase','technology_software'=>'fa-database','financial_insurance'=>'fa-money','real_estate'=>'fa-building','logistics_supply'=>'fa-cubes','service_report'=>'fa-file-text'];
      if (!empty($permissions['abra_service']) && $permissions['abra_service']==1):
      ?>
      <li>
        <a><i class="fa fa-cogs nav-icon"></i> Abra Service <i class="fa fa-angle-right nav-arrow"></i></a>
        <ul class="sub-menu">
          <?php foreach ($service_map as $svc_key => $svc_label):
            if (!($is_admin || (!empty($permissions[$svc_key]) && $permissions[$svc_key]==1))) continue;
            $svc_icon = $service_icons[$svc_key] ?? 'fa-cog';
          ?>
          <li><a href="abra-service.php?service=<?=urlencode($svc_label)?>"><i class="fa <?=$svc_icon?>"></i> <?=htmlspecialchars($svc_label)?></a></li>
          <?php endforeach; ?>
        </ul>
      </li>
      <?php endif; ?>

      <!-- ── Accounting ── -->
      <?php if ($is_admin || (!empty($permissions['accounting']) && $permissions['accounting']==1)): ?>
      <li>
        <a><i class="fa fa-calculator nav-icon"></i> Accounting <i class="fa fa-angle-right nav-arrow"></i></a>
        <ul class="sub-menu">
          <?php if ($is_admin || (!empty($permissions['verification']) && $permissions['verification']==1)): ?>
          <li><a><i class="fa fa-check-circle"></i> Verification <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
            <ul class="sub-menu">
              <?php if ($is_admin || (!empty($permissions['customer_verification']) && $permissions['customer_verification']==1)): ?><li><a href="customer-verification.php"><i class="fa fa-user"></i> Customer</a></li><?php endif; ?>
              <?php if ($is_admin || (!empty($permissions['vendor_verification'])   && $permissions['vendor_verification']  ==1)): ?><li><a href="vendor-verification.php"><i class="fa fa-exchange"></i> Vendor</a></li><?php endif; ?>
            </ul>
          </li>
          <?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['al_accounts']) && $permissions['al_accounts']==1)): ?><li><a href="al-accounts.php"><i class="fa fa-folder-open"></i> AL Accounts</a></li><?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['assets'])      && $permissions['assets']     ==1)): ?><li><a href="assets.php"><i class="fa fa-cubes"></i> Assets</a></li><?php endif; ?>
          <?php if ($is_admin || (!empty($permissions['rate_cards'])  && $permissions['rate_cards'] ==1)): ?>
          <li><a><i class="fa fa-credit-card"></i> Global Rate Cards <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
            <ul class="sub-menu">
              <?php
              $rc_icon_map = ['al'=>'fa-ship','db'=>'fa-database','agt'=>'fa-globe','afw'=>'fa-cutlery'];
              foreach (['al'=>'AL','db'=>'DB','agt'=>'AGT','afw'=>'AFW'] as $rk=>$rl):
                if (!($is_admin || (!empty($permissions[$rk.'_rate_cards']) && $permissions[$rk.'_rate_cards']==1) || (!empty($permissions[$rk.'_rate_calculation']) && $permissions[$rk.'_rate_calculation']==1))) continue;
                $rk_icon = $rc_icon_map[$rk] ?? 'fa-credit-card';
              ?>
              <li><a><i class="fa <?=$rk_icon?>"></i> <?=$rl?> Rate Cards <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
                <ul class="sub-menu">
                  <?php if ($is_admin || (!empty($permissions[$rk.'_rate_cards'])      && $permissions[$rk.'_rate_cards']     ==1)): ?><li><a href="<?=$rk?>-rate-cards.php"><i class="fa fa-credit-card"></i> <?=$rl?> Rate Cards</a></li><?php endif; ?>
                  <?php if ($is_admin || (!empty($permissions[$rk.'_rate_calculation']) && $permissions[$rk.'_rate_calculation']==1)): ?><li><a href="<?=$rk?>-rate-calculation.php"><i class="fa fa-calculator"></i> Rate Calculation</a></li><?php endif; ?>
                </ul>
              </li>
              <?php endforeach; ?>
            </ul>
          </li>
          <?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>

      <!-- ── TMS ── -->
      <?php if (!empty($permissions['tickets']) && $permissions['tickets']==1): ?>
      <li>
        <a><i class="fa fa-ticket nav-icon"></i> TMS <i class="fa fa-angle-right nav-arrow"></i></a>
        <ul class="sub-menu">
          <?php if (!empty($permissions['raise_a_ticket']) && $permissions['raise_a_ticket']==1): ?><li><a href="raise-a-ticket.php"><i class="fa fa-plus-circle"></i> Raise a Ticket</a></li><?php endif; ?>
          <?php if (!empty($permissions['my_tickets'])     && $permissions['my_tickets']    ==1): ?><li><a href="my-tickets.php"><i class="fa fa-user"></i> My Tickets</a></li><?php endif; ?>
          <?php if (!empty($permissions['all_tickets'])    && $permissions['all_tickets']   ==1): ?><li><a href="reports.php"><i class="fa fa-list"></i> All Tickets</a></li><?php endif; ?>
          <?php if (!empty($permissions['reports'])        && $permissions['reports']       ==1): ?><li><a href="reports.php"><i class="fa fa-bar-chart"></i> Ticket Reports</a></li><?php endif; ?>
          <?php if (!empty($permissions['closed_tickets']) && $permissions['closed_tickets']==1): ?><li><a href="closed-tickets.php"><i class="fa fa-check-square"></i> Closed Tickets</a></li><?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>

      <!-- ── Configurations ── -->
      <?php if (!empty($permissions['configurations']) && $permissions['configurations']==1): ?>
      <li>
        <a><i class="fa fa-cog nav-icon"></i> Configurations <i class="fa fa-angle-right nav-arrow"></i></a>
        <ul class="sub-menu">
          <?php if (!empty($permissions['offices'])         && $permissions['offices']        ==1): ?><li><a href="add-office.php"><i class="fa fa-building"></i> Offices</a></li><?php endif; ?>
          <?php if (!empty($permissions['erp_users'])       && $permissions['erp_users']      ==1): ?><li><a href="add-new-users.php"><i class="fa fa-user"></i> ERP Users</a></li><?php endif; ?>

          <!-- ── Clients ── -->
          <?php if ($is_admin || (!empty($permissions['clients']) && $permissions['clients']==1) || (!empty($permissions['az_clients']) && $permissions['az_clients']==1) || (!empty($permissions['application_clients']) && $permissions['application_clients']==1)): ?>
          <li><a><i class="fa fa-users"></i> Clients <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
            <ul class="sub-menu">

              <!-- AL Clients -->
              <?php if ($is_admin || (!empty($permissions['clients']) && $permissions['clients']==1)): ?>
              <li><a href="management-client.php"><i class="fa fa-user"></i> AL Clients</a></li>
              <?php endif; ?>

              <!-- AZ Clients -->
              <?php if ($is_admin || (!empty($permissions['az_clients']) && $permissions['az_clients']==1)): ?>
              <li><a href="management-client-az.php"><i class="fa fa-tag"></i> AZ Clients</a></li>
              <?php endif; ?>

              <!-- Application Clients -->
              <?php if ($is_admin || (!empty($permissions['application_clients']) && $permissions['application_clients']==1)): ?>
              <li><a><i class="fa fa-tablet"></i> Application Clients <i class="fa fa-angle-right nav-arrow" style="margin-left:auto"></i></a>
                <ul class="sub-menu">
                  <?php if ($is_admin || (!empty($permissions['global_shipping_clients']) && $permissions['global_shipping_clients']==1)): ?>
                  <li><a href="global-shipping-clients.php"><i class="fa fa-globe"></i> Abra Global Shipping</a></li>
                  <?php endif; ?>
                </ul>
              </li>
              <?php endif; ?>

            </ul>
          </li>
          <?php endif; ?>

          <?php if (!empty($permissions['types_packaging'])   && $permissions['types_packaging']  ==1): ?><li><a href="typeshipments.php"><i class="fa fa-cube"></i> Types Packaging</a></li><?php endif; ?>
          <?php if (!empty($permissions['types_shipments'])   && $permissions['types_shipments']  ==1): ?><li><a href="modebookings.php"><i class="fa fa-truck"></i> Types Shipments</a></li><?php endif; ?>
          <?php if (!empty($permissions['styles_status'])     && $permissions['styles_status']    ==1): ?><li><a href="styles.php"><i class="fa fa-magic"></i> Styles Status</a></li><?php endif; ?>
          <?php if (!empty($permissions['ship_calculations']) && $permissions['ship_calculations']==1): ?><li><a href="shipping-charge.php"><i class="fa fa-calculator"></i> Ship Calculations</a></li><?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>

    </ul>
  </nav>

  <!-- Logout -->
  <div class="sidebar-logout">
    <a href="library.php?action=logOut"><i class="fa fa-sign-out"></i> Logout</a>
  </div>
</aside>

<!-- ══════════════════════════════════════════
     TOP BAR
══════════════════════════════════════════ -->
<header id="main-topbar">

  <!-- Left: mobile menu + carousel -->
  <div style="display:flex;align-items:center;flex:1;min-width:0;">
    <div class="mob-menu-btn" id="mobMenuBtn" onclick="document.getElementById('main-sidebar').classList.toggle('mobile-open')">
      <i class="fa fa-bars"></i>
    </div>
    <div class="topbar-carousel-wrap">
      <div class="logo-carousel">
        <div class="logo-track">
          <img src="logo-image/group-logo.png" alt="Logo">
          <img src="logo-image/group-logo.png" alt="Logo">
          <img src="logo-image/group-logo.png" alt="Logo">
          <img src="logo-image/group-logo.png" alt="Logo">
          <img src="logo-image/group-logo.png" alt="Logo">
          <img src="logo-image/group-logo.png" alt="Logo">
        </div>
      </div>
    </div>
  </div>

  <!-- Right: clock + bell + user -->
  <div class="topbar-right">

    <!-- Clock -->
    <div class="topbar-clock"><span id="tick2"></span></div>

    <!-- Notification Bell -->
    <div style="position:relative;">
      <div class="notif-bell-wrap <?php echo $total_notification_count > 0 ? 'has-notif' : ''; ?>" id="notifBellBtn">
        <i class="fa fa-bell"></i>
        <span class="notif-badge" id="notifBadge"
              style="<?php echo $total_notification_count > 0 ? 'display:inline-block;' : ''; ?>">
          <?php echo $total_notification_count > 99 ? '99+' : $total_notification_count; ?>
        </span>
      </div>

      <div class="notif-drop" id="notifDrop">
        <div class="notif-drop-head">
          <span class="nd-title"><i class="fa fa-bell" style="margin-right:6px;"></i>Notifications</span>
          <span class="nd-pill" id="notifPill" style="<?php echo $total_notification_count > 0 ? '' : 'display:none;'; ?>">
            <?php echo $total_notification_count; ?> pending
          </span>
        </div>
        <div id="notifContent">
          <?php if ($total_notification_count > 0): ?>
            <?php foreach ($ticket_items as $t):
              $p  = strtolower($t['priority'] ?? 'low');
              $pc = in_array($p, ['high','urgent','critical']) ? 'np-h' : (in_array($p, ['medium','normal']) ? 'np-m' : 'np-l');
              $diff = time() - strtotime($t['created_at']);
              $ago  = $diff < 3600 ? floor($diff/60).'m ago' : ($diff < 86400 ? floor($diff/3600).'h ago' : floor($diff/86400).'d ago');
            ?>
            <a href="my-tickets.php" class="notif-row">
              <div class="notif-dot"></div>
              <div class="notif-info">
                <div class="notif-meta">
                  <span class="notif-num"><?php echo htmlspecialchars($t['ticket_number'] ?? 'TKT-'.$t['id']); ?></span>
                  <span class="notif-age"><?php echo $ago; ?></span>
                </div>
                <span class="notif-subj"><?php echo htmlspecialchars($t['subject'] ?? 'No Subject'); ?></span>
                <span class="notif-prio <?php echo $pc; ?>"><?php echo ucfirst($p); ?></span>
              </div>
            </a>
            <?php endforeach; ?>
            <?php if ($ticket_count > 5): ?>
            <div style="padding:6px;text-align:center;background:#f8fafc;">
              <span style="color:#2563c4;font-size:11px;">+<?php echo $ticket_count - 5; ?> more</span>
            </div>
            <?php endif; ?>
            <a href="my-tickets.php" class="notif-footer"><i class="fa fa-arrow-right"></i> View All Notifications</a>
          <?php else: ?>
            <div class="notif-empty">
              <i class="fa fa-bell-slash"></i>
              <h6>No Notifications</h6>
              <p>You're all caught up!</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- User Menu -->
    <div style="position:relative;">
      <?php
      if ($is_admin) {
        $r3 = mysqli_query($dbConn, "SELECT * FROM manager_admin WHERE name='".$_SESSION["user_name"]."' LIMIT 1");
        $pu = mysqli_fetch_array($r3);
        $profile_link = "edit-profile.php?cid=" . codificar($pu['cid'] ?? '');
      } else {
        $r3 = mysqli_query($dbConn, "SELECT * FROM manager_user WHERE name='".$_SESSION["user_name"]."' LIMIT 1");
        $pu = mysqli_fetch_array($r3);
        $profile_link = "edit-profile-user.php?cid=" . codificar($pu['cid'] ?? '');
      }
      ?>
      <div class="topbar-user-btn" id="userMenuBtn">
        <img src="logo-image/user.png" class="topbar-user-avatar" alt="User">
        <div class="topbar-user-info">
          <span class="topbar-user-greet"><?php echo $mensaje; ?></span>
          <span class="topbar-user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        </div>
        <i class="fa fa-angle-down topbar-user-caret"></i>
      </div>

      <div class="topbar-user-drop" id="userMenuDrop">
        <div class="topbar-user-drop-head">
          <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
          <small><?php echo $is_admin ? 'Administrator' : 'Employee'; ?></small>
        </div>
        <a href="<?php echo $profile_link; ?>"><i class="fa fa-user"></i> My Profile</a>
        <a href="library.php?action=logOut" class="logout"><i class="fa fa-sign-out"></i> Logout</a>
      </div>
    </div>

  </div>
</header>

<!-- Scripts -->
<script src="../bower_components/jquery/dist/jquery.min.js"></script>
<script src="../bower_components/bootstrap/dist/js/bootstrap.js"></script>

<script>
// ── Clock ────────────────────────────────────────────────────
function show2(){
  var el = document.getElementById("tick2"); if(!el) return;
  var d=new Date(), h=d.getHours(), m=d.getMinutes(), s=d.getSeconds(), dn=h>=12?"PM":"AM";
  if(h>12)h-=12; if(h===0)h=12;
  if(m<=9)m="0"+m; if(s<=9)s="0"+s;
  el.innerHTML = h+":"+m+":"+s+" "+dn;
  setTimeout(show2, 1000);
}
show2();

// ── Sidebar accordion — event delegation (works at ALL nesting levels) ──
document.getElementById('main-sidebar').addEventListener('click', function(e) {
  // Walk up from the clicked element to find the nearest <a>
  var link = e.target.closest('a');
  if (!link) return;

  // Only handle links that have a sibling sub-menu
  var li  = link.parentElement;
  if (!li) return;
  var sub = link.nextElementSibling;

  // If this link has NO sub-menu child, it's a real page link — let it navigate
  if (!sub || !sub.classList.contains('sub-menu')) return;

  // Has sub-menu — toggle open/close
  if (!link.getAttribute('href') || link.getAttribute('href') === '#' || link.getAttribute('href') === '') {
      e.preventDefault();
  }
  e.stopPropagation();

  var isOpen = li.classList.contains('open');

  // Close all sibling <li>s at this same level
  var siblings = li.parentElement ? li.parentElement.children : [];
  for (var i = 0; i < siblings.length; i++) {
    if (siblings[i] !== li) {
      siblings[i].classList.remove('open');
    }
  }

  // Toggle this one
  if (isOpen) {
    li.classList.remove('open');
  } else {
    li.classList.add('open');
  }
});

// Highlight active page
(function(){
  var path = window.location.pathname.split('/').pop();
  document.querySelectorAll('#main-sidebar a[href]').forEach(function(a){
    if (a.getAttribute('href') === path || a.href === window.location.href) {
      a.parentElement.classList.add('active');
      
      // If the link itself has a sub-menu child, open it (for parent links like db-dashboard.php)
      if (a.nextElementSibling && a.nextElementSibling.classList.contains('sub-menu')) {
        a.parentElement.classList.add('open');
      }

      // Open parent sub-menus (if this is a child link)
      var parent = a.closest('.sub-menu');
      while (parent) {
        var parentLi = parent.parentElement;
        if (parentLi) parentLi.classList.add('open');
        parent = parentLi ? parentLi.closest('.sub-menu') : null;
      }
    }
  });
})();

// ── Notification Bell ────────────────────────────────────────
(function(){
  var btn       = document.getElementById('notifBellBtn');
  var drop      = document.getElementById('notifDrop');
  var badge     = document.getElementById('notifBadge');
  var pill      = document.getElementById('notifPill');
  var toast     = document.getElementById('notifToast');
  var toastText = document.getElementById('notifToastText');
  var lastCount = <?php echo (int)$total_notification_count; ?>;

  function playSound(){
    try {
      var a = document.getElementById('hdrSound');
      if (!a){ a=document.createElement('audio'); a.id='hdrSound'; a.src='tms.wav'; a.preload='auto'; document.body.appendChild(a); }
      a.currentTime=0; a.volume=1; a.play().catch(function(){});
    } catch(e){}
  }
  function updateBadge(n){
    if(n>0){ badge.textContent=n>99?'99+':n; badge.style.display='inline-block'; pill.textContent=n+' pending'; pill.style.display='inline-block'; btn.classList.add('has-notif'); }
    else  { badge.style.display='none'; pill.style.display='none'; btn.classList.remove('has-notif'); }
  }
  function showToast(n, diff){
    toastText.textContent = diff===1 ? '1 new notification!' : diff+' new notifications!';
    toast.style.display='block';
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.style.display='none'; }, 4000);
  }
  function poll(){
    fetch('get_notification_count.php',{credentials:'same-origin'})
      .then(function(r){return r.json();})
      .then(function(d){
        var n=parseInt(d.count)||0;
        if(n>lastCount){ playSound(); showToast(n,n-lastCount); }
        if(n!==lastCount){ updateBadge(n); lastCount=n; }
      }).catch(function(){});
  }
  setInterval(poll, 10000);
  window.refreshNotificationCount = poll;

  btn.addEventListener('click',function(e){ e.stopPropagation(); drop.classList.toggle('open'); });
  document.addEventListener('click',function(e){
    if(!btn.contains(e.target)&&!drop.contains(e.target)) drop.classList.remove('open');
  });
})();

// ── User Menu ────────────────────────────────────────────────
(function(){
  var btn  = document.getElementById('userMenuBtn');
  var drop = document.getElementById('userMenuDrop');
  btn.addEventListener('click',function(e){ e.stopPropagation(); drop.classList.toggle('open'); });
  document.addEventListener('click',function(e){
    if(!btn.contains(e.target)&&!drop.contains(e.target)) drop.classList.remove('open');
  });
})();
</script>