<?php
// Database connection
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM vendor_kyc WHERE kyc_status = 'verified' ORDER BY account_type ASC, verified_at DESC");
    $stmt->execute();
    $all_verified = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $individual_vendors = array_values(array_filter($all_verified, fn($k) => $k['account_type'] === 'individual'));
    $business_vendors = array_values(array_filter($all_verified, fn($k) => $k['account_type'] === 'business'));
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Verified Vendors - TRUCK UNION</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" />
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />
<link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Poppins', sans-serif; background: #f0f4f8; min-height: 100vh; padding: 20px 0; }
.container-fluid { max-width: 1600px; margin: 0 auto; padding: 0 20px; }
.page-header { background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); padding: 20px 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(30, 58, 138, 0.3); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
.page-header h1 { color: white; font-weight: 600; margin: 0; font-size: 1.8rem; }
.header-stats { display: flex; gap: 20px; align-items: center; }
.stat-box { text-align: center; padding: 8px 20px; background: rgba(255, 255, 255, 0.15); border-radius: 8px; }
.stat-box .stat-number { font-size: 1.8rem; font-weight: 700; color: white; }
.stat-box .stat-label { font-size: 0.85rem; color: rgba(255, 255, 255, 0.9); margin-top: 4px; }
.tabs-container { background: white; border-radius: 20px; padding: 8px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); display: flex; gap: 8px; }
.tab-button { flex: 1; padding: 18px 32px; border: none; border-radius: 14px; font-size: 16px; font-weight: 800; cursor: pointer; transition: all 0.3s; background: transparent; color: #64748B; display: flex; align-items: center; justify-content: center; gap: 12px; }
.tab-button:hover { background: #F8FAFC; }
.tab-button.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); }
.tab-content { display: none; }
.tab-content.active { display: block; }
#initial-loader { position: absolute; top: 200px; left: 50%; transform: translateX(-50%); z-index: 50; text-align: center; background: rgba(255,255,255,0.95); padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
.table-container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); overflow: visible; opacity: 0; transition: opacity 0.5s ease-in-out; }
.dataTables_scrollBody { max-height: 70vh !important; overflow-y: auto !important; }
.dataTables_wrapper { width: 100% !important; }
table.dataTable { margin: 0 !important; border-collapse: separate !important; border-spacing: 0 !important; width: 100% !important; table-layout: fixed !important; }
.dataTables_scrollBody thead { visibility: collapse !important; height: 0 !important; }
.dataTables_scrollBody thead tr { height: 0 !important; border: none !important; }
.dataTables_scrollBody thead th { height: 0 !important; padding: 0 !important; margin: 0 !important; border: none !important; line-height: 0 !important; }
table.dataTable thead th { background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%) !important; color: white !important; font-weight: 700 !important; white-space: nowrap !important; border: 1px solid #1e3a8a !important; border-bottom: 4px solid #1e3a8a !important; padding: 15px 10px !important; vertical-align: middle !important; height: 50px !important; text-align: left !important; font-size: 13px !important; }
table.dataTable tbody td { border: 1px solid #1e3a8a !important; padding: 12px 10px !important; vertical-align: middle !important; font-weight: 600 !important; color: #1e293b !important; white-space: nowrap !important; font-size: 13px !important; }
table.dataTable tbody tr { background-color: #ffffff !important; transition: background-color 0.2s ease; }
table.dataTable tbody tr:hover { background-color: #f1f5f9 !important; }
table.dataTable tbody tr td:first-child { border-left: 3px solid #1e3a8a !important; font-weight: 700 !important; background: #f8fafc !important; }
table.dataTable tbody tr:hover td:first-child { background: #e2e8f0 !important; }
.dataTables_wrapper .top-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 15px; background: #f8fafc; border-radius: 10px; border: 2px solid #e2e8f0; }
.dataTables_wrapper .bottom-controls { margin-top: 25px; padding: 20px; background: #f8fafc; border-radius: 10px; border: 2px solid #e2e8f0; }
.dataTables_wrapper .dataTables_length { padding: 0 !important; font-weight: 600 !important; color: #475569 !important; display: flex !important; align-items: center !important; gap: 10px !important; }
.dataTables_wrapper .dataTables_length select { padding: 10px 35px 10px 15px !important; border: 2px solid #e2e8f0 !important; border-radius: 8px !important; font-weight: 700 !important; color: #1e3a8a !important; font-size: 15px !important; cursor: pointer !important; background: white !important; min-width: 100px !important; }
.dataTables_wrapper .dataTables_filter { display: none; }
.dataTables_wrapper .dataTables_paginate { text-align: center !important; padding: 0 !important; margin: 0 !important; }
.dataTables_wrapper .dataTables_paginate .paginate_button { padding: 12px 20px !important; margin: 0 6px !important; border-radius: 10px !important; border: 2px solid #e2e8f0 !important; background: white !important; color: #1e3a8a !important; font-weight: 700 !important; font-size: 15px !important; cursor: pointer !important; transition: all 0.3s ease !important; display: inline-block !important; min-width: 45px !important; text-align: center !important; }
.dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: #1e40af !important; color: white !important; border-color: #1e40af !important; transform: translateY(-3px) !important; box-shadow: 0 6px 20px rgba(30, 64, 175, 0.4) !important; }
.dataTables_wrapper .dataTables_paginate .paginate_button.current { background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%) !important; color: white !important; border-color: #1e3a8a !important; box-shadow: 0 4px 15px rgba(30, 58, 138, 0.4) !important; transform: scale(1.1) !important; }
.dataTables_wrapper .dataTables_info { padding: 0 !important; color: #1e3a8a !important; font-weight: 700 !important; font-size: 15px !important; text-align: center !important; margin: 0 0 15px 0 !important; }
.dataTables_scrollBody::-webkit-scrollbar { width: 12px; height: 12px; }
.dataTables_scrollBody::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
.dataTables_scrollBody::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); border-radius: 10px; border: 2px solid #f1f5f9; }
.spinner { border: 4px solid #f3f3f3; border-top: 4px solid #1e3a8a; border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; margin: 0 auto 15px; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
.company-highlight { background: linear-gradient(135deg, #D1FAE5, #A7F3D0); padding: 8px 12px; border-radius: 8px; border-left: 4px solid #10B981; font-weight: 800; color: #065F46; }
</style>
</head>
<body>
<div class="container-fluid">
<div class="page-header">
<h1><i class="fas fa-check-circle"></i> Verified Vendors</h1>
<div class="header-stats">
<div class="stat-box">
<div class="stat-number"><?php echo count($all_verified); ?></div>
<div class="stat-label">Total Verified</div>
</div>
<div class="stat-box">
<div class="stat-number"><?php echo count($individual_vendors); ?></div>
<div class="stat-label">Individual</div>
</div>
<div class="stat-box">
<div class="stat-number"><?php echo count($business_vendors); ?></div>
<div class="stat-label">Business</div>
</div>
</div>
</div>

<div class="tabs-container">
<button class="tab-button active" onclick="switchTab('individual')">
<span style="font-size: 24px;">👤</span>
<span>Individual Vendors (<?php echo count($individual_vendors); ?>)</span>
</button>
<button class="tab-button" onclick="switchTab('business')">
<span style="font-size: 24px;">🏢</span>
<span>Business Vendors (<?php echo count($business_vendors); ?>)</span>
</button>
</div>

<div id="initial-loader">
<div class="spinner"></div>
<p style="font-weight: 700; color: #1e3a8a; font-size: 16px; margin: 0;">Loading Verified Vendors...</p>
</div>

<div id="individual-tab" class="tab-content active">
<div class="table-container">
<table id="individualTable" class="display nowrap" style="width:100%">
<thead>
<tr>
<th>Sl.No</th>
<th>Name</th>
<th>Email</th>
<th>Phone</th>
<th>Aadhaar</th>
<th>PAN</th>
<th>Bank Account Name</th>
<th>Bank Account Number</th>
<th>IFSC Code</th>
<th>Verified Date</th>
</tr>
</thead>
<tbody>
<?php $sl = 1; foreach($individual_vendors as $v): ?>
<tr>
<td><?php echo $sl++; ?></td>
<td><strong><?php echo htmlspecialchars($v['name']); ?></strong></td>
<td><?php echo htmlspecialchars($v['email']); ?></td>
<td><?php echo htmlspecialchars($v['phone']); ?></td>
<td><?php echo htmlspecialchars($v['aadhaar_number']); ?></td>
<td><?php echo htmlspecialchars($v['pan_number']); ?></td>
<td><?php echo htmlspecialchars($v['bank_account_name']); ?></td>
<td><?php echo htmlspecialchars($v['bank_account_number']); ?></td>
<td><?php echo htmlspecialchars($v['ifsc_code']); ?></td>
<td><?php echo date('d-M-Y', strtotime($v['verified_at'])); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<div id="business-tab" class="tab-content">
<div class="table-container">
<table id="businessTable" class="display nowrap" style="width:100%">
<thead>
<tr>
<th>Sl.No</th>
<th>Name</th>
<th>Company Name</th>
<th>Email</th>
<th>Phone</th>
<th>GST Number</th>
<th>Business Address</th>
<th>Aadhaar</th>
<th>PAN</th>
<th>Bank Account Name</th>
<th>Bank Account Number</th>
<th>IFSC Code</th>
<th>Verified Date</th>
</tr>
</thead>
<tbody>
<?php $sl = 1; foreach($business_vendors as $v): ?>
<tr>
<td><?php echo $sl++; ?></td>
<td><strong><?php echo htmlspecialchars($v['name']); ?></strong></td>
<td><span class="company-highlight"><?php echo htmlspecialchars($v['company_name']); ?></span></td>
<td><?php echo htmlspecialchars($v['email']); ?></td>
<td><?php echo htmlspecialchars($v['phone']); ?></td>
<td><?php echo htmlspecialchars($v['gst_number']); ?></td>
<td><?php echo htmlspecialchars($v['address']); ?></td>
<td><?php echo htmlspecialchars($v['aadhaar_number']); ?></td>
<td><?php echo htmlspecialchars($v['pan_number']); ?></td>
<td><?php echo htmlspecialchars($v['bank_account_name']); ?></td>
<td><?php echo htmlspecialchars($v['bank_account_number']); ?></td>
<td><?php echo htmlspecialchars($v['ifsc_code']); ?></td>
<td><?php echo date('d-M-Y', strtotime($v['verified_at'])); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>
<script>
var individualTable, businessTable;

$(document).ready(function() {
individualTable = $('#individualTable').DataTable({
dom: '<"top-controls"l>rt<"bottom-controls"ip>',
order: [[0, 'asc']],
pageLength: 50,
lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
scrollX: true,
scrollY: '60vh',
scrollCollapse: true,
fixedColumns: { left: 2 },
autoWidth: false,
columnDefs: [
{ width: "80px", targets: 0 },
{ width: "220px", targets: 1 },
{ width: "280px", targets: 2 },
{ width: "150px", targets: 3 },
{ width: "170px", targets: 4 },
{ width: "150px", targets: 5 },
{ width: "220px", targets: 6 },
{ width: "200px", targets: 7 },
{ width: "150px", targets: 8 },
{ width: "150px", targets: 9 }
],
initComplete: function() {
$('#initial-loader').fadeOut(400, function() {
$('.table-container').css('opacity', '1');
});
}
});

businessTable = $('#businessTable').DataTable({
dom: '<"top-controls"l>rt<"bottom-controls"ip>',
order: [[0, 'asc']],
pageLength: 50,
lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
scrollX: true,
scrollY: '60vh',
scrollCollapse: true,
fixedColumns: { left: 2 },
autoWidth: false,
columnDefs: [
{ width: "80px", targets: 0 },
{ width: "220px", targets: 1 },
{ width: "280px", targets: 2 },
{ width: "280px", targets: 3 },
{ width: "150px", targets: 4 },
{ width: "180px", targets: 5 },
{ width: "350px", targets: 6 },
{ width: "170px", targets: 7 },
{ width: "150px", targets: 8 },
{ width: "220px", targets: 9 },
{ width: "200px", targets: 10 },
{ width: "150px", targets: 11 },
{ width: "150px", targets: 12 }
]
});
});

function switchTab(tabName) {
$('.tab-content').removeClass('active');
$('.tab-button').removeClass('active');
$('#' + tabName + '-tab').addClass('active');
event.target.closest('.tab-button').classList.add('active');

setTimeout(function() {
if (tabName === 'individual') {
individualTable.columns.adjust().draw(false);
} else {
businessTable.columns.adjust().draw(false);
}
}, 100);
}
</script>
</body>
</html>
