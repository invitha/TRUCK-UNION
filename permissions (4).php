<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
session_start();
require_once('database.php');
require_once('database-settings.php');
require_once('library.php');
require_once('funciones.php');
require 'requirelanguage.php';
$con = conexion();

date_default_timezone_set($_SESSION['ge_timezone']);

if(isset($_GET['cid'])){
    $cid_encrypt = $_GET['cid'];    
    $cid = decodificar($cid_encrypt);
} else {
    $cid = 0; 
}

isUser();                                                        
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title><?php echo $_SESSION['ge_cname']; ?> | Permissions Management</title>
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

  <style type="text/css">
    .parsley-error { border-color: #ff5d48 !important; }
    .parsley-errors-list { display: none; margin: 0; padding: 0; }
    .parsley-errors-list.filled { display: block; }
    .parsley-errors-list > li { font-size: 12px; list-style: none; color: #ff5d48; margin-top: 5px; }
    
    .permissions-container {
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .module-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 15px;
        font-weight: bold;
        font-size: 16px;
        border-radius: 5px;
        margin-top: 20px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .sub-module-header {
        background: #f8f9fa;
        padding: 10px 15px;
        font-weight: bold;
        font-size: 14px;
        border-left: 4px solid #667eea;
        margin-top: 15px;
        margin-bottom: 8px;
    }
    .sub-sub-module-header {
        background: #eef0ff;
        padding: 8px 15px 8px 30px;
        font-weight: bold;
        font-size: 13px;
        border-left: 4px solid #a0aef0;
        margin-top: 10px;
        margin-bottom: 6px;
    }
    .permission-row {
        padding: 8px 15px;
        border-bottom: 1px solid #e9ecef;
        transition: background-color 0.2s;
    }
    .permission-row:hover { background-color: #f8f9fa; }
    .permission-name { font-size: 14px; color: #495057; padding-left: 30px; }
    .permission-name-deep { font-size: 13px; color: #495057; padding-left: 50px; }
    .checkbox-cell { text-align: center; vertical-align: middle; }
    .permission-checkbox { width: 18px; height: 18px; cursor: pointer; }
    .table-header { background: #343a40; color: white; position: sticky; top: 0; z-index: 10; }
    .table-header th { padding: 15px; font-weight: bold; text-align: center; border-right: 1px solid rgba(255,255,255,0.1); }
    .btn-update {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 40px;
        border: none;
        border-radius: 5px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        margin-top: 20px;
        transition: all 0.3s;
    }
    .btn-update:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,0.4); }
    .module-select-checkbox { width: 18px; height: 18px; cursor: pointer; }
    .module-select-label {
        font-weight: normal;
        font-size: 13px;
        margin-bottom: 0;
        margin-right: 10px;
        color: rgba(255,255,255,0.9);
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
    }
  </style>
</head>
<body>
<?php include("header.php"); ?>
  
<div id="content" class="app-content" role="main">
  <div class="app-content-body">     
    <div class="hbox hbox-auto-xs hbox-auto-sm">
      <div class="col">
        <div class="bg-light lter b-b wrapper-md"></div>
        <div class="wrapper-md">
          <div class="panel hbox hbox-auto-xs no-border">
            <div class="col wrapper">
              <div class="permissions-container">
                <div class="row">
                  <div class="col-xs-12" align="center">
                    <?php
                    $name = "Unknown User";
                    if($cid > 0) {
                        $user_details = mysqli_query($dbConn, "SELECT * FROM manager_user WHERE cid = $cid");
                        if($row = mysqli_fetch_array($user_details)){
                            $name = $row['name_parson'];
                        }
                    }
                    ?>
                    <h2 style="color:#667eea;font-weight:bold;">Manage Permissions for <?php echo $name; ?></h2>
                    <p style="color:#6c757d;">Select the modules and features this user can access</p>
                    <br>
                  </div>
                </div>

                <div class="row">
                  <div class="col-xs-12">
                    <?php
                    // ═══════════════════════════════════════════════════
                    // PERMISSION STRUCTURE — matches new nav exactly
                    // ═══════════════════════════════════════════════════
                    $permission_structure = [

                        'Dashboard' => [
                            'permissions' => [
                                'dashboard' => 'Dashboard Access'
                            ]
                        ],

                        'HRM (Human Resource Management)' => [
                            'parent' => 'hrm',
                            'parent_label' => 'HRM Module Access',
                            'permissions' => [
                                'hrm_screening_hub'    => 'Screening Hub',
                                'hrm_career'           => 'Career',
                                'hrm_employees'        => 'Employees',
                                'hrm_departments'      => 'Departments',
                                'hrm_attendance'       => 'Attendance',
                                'hrm_attendance_export'=> 'Attendance Export',
                                'hrm_leave_requests'   => 'Leave Requests',
                                'hrm_payroll'          => 'Payroll',
                                'hrm_notice_board'     => 'Notice Board',
                                'hrm_feedback'         => 'Feedback Management'
                            ],
                            'sub_modules' => [
                                'KPI Management' => [
                                    'parent'       => 'hrm_kpi',
                                    'parent_label' => 'KPI Module Access',
                                    'permissions'  => [
                                        'hrm_kpq'            => 'KPQ',
                                        'hrm_kpi_evaluation' => 'KPI Evaluation'
                                    ]
                                ]
                            ]
                        ],

                        'Sales' => [
                            'parent'       => 'sales',
                            'parent_label' => 'Sales Module Access',
                            'sub_modules'  => [
                                'AT Leads' => ['parent'=>'at_leads','parent_label'=>'AT Leads Access','permissions'=>['at_leads_database'=>'Customer Database']],
                                'DB Leads' => ['parent'=>'db_leads','parent_label'=>'DB Leads Access','permissions'=>['db_leads_database'=>'Customer Database']],
                                'AL Leads' => ['parent'=>'al_leads','parent_label'=>'AL Leads Access','permissions'=>['al_leads_database'=>'Customer Database']],
                                'RP Leads' => ['parent'=>'rp_leads','parent_label'=>'RP Leads Access','permissions'=>['rp_leads_database'=>'Customer Database']],
                                'AZ Leads' => ['parent'=>'az_leads','parent_label'=>'AZ Leads Access','permissions'=>['az_leads_database'=>'Customer Database']],
                                'AGT Leads'=> ['parent'=>'agt_leads','parent_label'=>'AGT Leads Access','permissions'=>['agt_leads_database'=>'Customer Database']],
                                'AFW Leads'=> ['parent'=>'afw_leads','parent_label'=>'AFW Leads Access','permissions'=>['afw_leads_database'=>'Customer Database']]
                            ]
                        ],

                        'Operations' => [
                            'parent'       => 'operations',
                            'parent_label' => 'Operations Module Access',
                            'sub_modules'  => [
                                'Fleet Management' => [
                                    'parent'       => 'fleet_management',
                                    'parent_label' => 'Fleet Management Access',
                                    'permissions'  => [
                                        'fleet_vehicles'   => 'Vehicles Management',
                                        'fleet_drivers'    => 'Drivers Management',
                                        'fleet_gps_tracking'=>'GPS Tracking',
                                        'fleet_trips'      => 'Trips & Deliveries',
                                        'fleet_maintenance'=> 'Vehicle Maintenance',
                                        'customer_fleet'   => 'Customer Fleet'
                                    ]
                                ],
                                'Warehouse' => [
                                    'permissions' => [
                                        'warehouses'           => 'Warehouses',
                                        'products'             => 'Products',
                                        'storage'              => 'Storage',
                                        'fulfillment'          => 'Fulfillment',
                                        'inventory_management' => 'Inventory Management',
                                        'fleet_list'           => 'Fleet'
                                    ]
                                ],
                                'Pickup & Delivery' => [
                                    'permissions' => [
                                        'pickup_orders'  => 'Pickup Orders',
                                        'pickup_reports' => 'Pickup Reports'
                                    ]
                                ],
                                'Process of Scanning' => [
                                    'permissions' => ['process_of_scanning' => 'Process of Scanning']
                                ],
                                'Shipment Updates & RTS' => [
                                    'permissions' => [
                                        'order_update' => 'Order Update',
                                        'rts'          => 'RTS (Return to Sender)'
                                    ]
                                ],
                                'RTO (Return to Origin)' => [
                                    'parent'       => 'rto',
                                    'parent_label' => 'RTO Module Access',
                                    'permissions'  => [
                                        'process_for_rto' => 'Process for RTO',
                                        'rto_inscan'      => 'RTO Inscan',
                                        'rto_outscan'     => 'RTO Outscan',
                                        'rto_delivered'   => 'RTO Delivered',
                                        'cancel_shipment' => 'Cancel Shipment'
                                    ]
                                ],
                                'POD (Proof of Delivery)' => [
                                    'permissions' => [
                                        'pod_pickup'   => 'Pickup POD',
                                        'pod_delivery' => 'Delivery POD'
                                    ]
                                ]
                            ]
                        ],

                        'Customer Service' => [
                            'parent'       => 'customer_service',
                            'parent_label' => 'Customer Service Access',
                            'permissions'  => [
                                'website_contacts'                  => 'Website Contacts',
                                'enquiry_sheet'                     => 'Enquiry Sheet',
                                'shipments'                         => 'Shipments',
                                'whatsapp_inbox'                    => 'WhatsApp Inbox',
                                'az_orders'                         => 'AZ Orders',
                                'enquiries_shipments_export'        => 'Enquiries Shipments Export',
                                'customer_service_cancel_shipment'  => 'Cancel Shipment'
                            ]
                        ],

                        'Reports & Accounts' => [
                            'permissions' => [
                                'shipment_reports' => 'Shipment Reports',
                                'al_accounts'      => 'AL Accounts',
                                'delivery_agent'   => 'Delivery Agent'
                            ]
                        ],

                        'Vendors' => [
                            'parent'       => 'vendors',
                            'parent_label' => 'Vendors Module Access',
                            'permissions'  => [
                                'at_vendors'           => 'AT Vendors',
                                'db_vendors'           => 'DB Vendors',
                                'rp_vendors'           => 'RP Vendors',
                                'az_vendors'           => 'AZ Vendors',
                                'abra_service_vendors' => 'Abra Service Vendors',
                                'abra_global_trading'  => 'Abra Global Trading',
                                'abra_food_works'      => 'Abra Food Works',
                                'vendor_onboarding'    => 'Vendor Onboarding'
                            ],
                            'sub_modules' => [
                                'AL Vendors' => [
                                    'parent'       => 'al_vendors',
                                    'parent_label' => 'AL Vendors Access',
                                    'permissions'  => [
                                        'truck_union_clients' => 'Online Vendors (Truck Union)',
                                        'al_vendors_offline'  => 'Offline Vendors'
                                    ]
                                ]
                            ]
                        ],

                        'Abra Design and Build' => [
                            'parent'       => 'abra_design_build',
                            'parent_label' => 'Abra Design and Build Access',
                            'permissions'  => [
                                'adb_customer_db' => 'Customer Database'
                            ],
                            'sub_modules' => [
                                'Leads' => [
                                    'parent'       => 'adb_leads',
                                    'parent_label' => 'Leads Access (Leads List + Lead Dashboard)',
                                    'permissions'  => [
                                        'adb_lead_dashboard'    => 'Lead Dashboard Access',
                                        'adb_lead_overview'     => 'Overview Tab',
                                        'adb_lead_site_visit'   => 'Site Visits Tab',
                                        'adb_lead_requirements' => 'Requirements Tab',
                                        'adb_lead_quotations'   => 'Quotations Tab',
                                        'adb_lead_design'       => 'Design Tab',
                                        'adb_lead_contract'     => 'Contracts Tab',
                                        'adb_lead_close'        => 'Close Lead Tab',
                                        'adb_lead_history'      => 'History Tab'
                                    ]
                                ],
                                'Projects' => [
                                    'parent'       => 'adb_projects',
                                    'parent_label' => 'Projects Access',
                                    'permissions'  => [
                                        'adb_projects_list' => 'Project List',
                                        'adb_proj_edit'     => 'Edit Project',
                                        'adb_proj_docs'     => 'Documents',
                                        'adb_proj_tasks'    => 'Tasks',
                                        'adb_proj_proof'    => 'Work Proofs',
                                        'adb_proj_handover' => 'Handover',
                                        'adb_proj_delete'   => 'Delete Project'
                                    ]
                                ],
                                'Finance' => [
                                    'parent'       => 'adb_finance',
                                    'parent_label' => 'Finance Access',
                                    'permissions'  => [
                                        'adb_finance_projects' => 'Financial Dashboard',
                                        'adb_finance_reports'  => 'Reports'
                                    ],
                                    'sub_modules' => [
                                        'Project Financials' => [
                                            'parent'       => 'adb_proj_finance',
                                            'parent_label' => 'Finance Dashboard (Overall)',
                                            'permissions'  => [
                                                'adb_proj_fin_dash' => 'Dashboard Tab',
                                                'adb_proj_fin_budg' => 'Stage Budgets',
                                                'adb_proj_fin_exp'  => 'Upload Expenses',
                                                'adb_proj_fin_pay'  => 'Customer Payments',
                                                'adb_proj_fin_rep'  => 'System Reports'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],

                        'Abra Service' => [
                            'parent'       => 'abra_service',
                            'parent_label' => 'Abra Service Access',
                            'permissions'  => [
                                'home_service'            => 'Home Service',
                                'general_repair'          => 'General Repair Services',
                                'transport_porter'        => 'Transport & Porter Services',
                                'health_wellness'         => 'Health & Wellness',
                                'fitness_lifestyle'       => 'Fitness & Lifestyle',
                                'subscription_consumables'=> 'Subscription-Based Consumables',
                                'skill_development'       => 'Skill Development & Coaching',
                                'professional_services'   => 'Professional Services',
                                'technology_software'     => 'Technology & Software',
                                'financial_insurance'     => 'Financial & Insurance Services',
                                'real_estate'             => 'Real Estate & Construction',
                                'logistics_supply'        => 'Logistics & Supply Chain',
                                'service_report'          => 'Service Report'
                            ]
                        ],

                        'Accounting' => [
                            'parent'       => 'accounting',
                            'parent_label' => 'Accounting Module Access',
                            'permissions'  => [
                                'assets' => 'Assets'
                            ],
                            'sub_modules' => [
                                'Verification' => [
                                    'parent'       => 'verification',
                                    'parent_label' => 'Verification Access',
                                    'permissions'  => [
                                        'customer_verification' => 'Customer Verification',
                                        'vendor_verification'   => 'Vendor Verification'
                                    ]
                                ],
                                'AL Rate Cards' => [
                                    'parent'       => 'al_rate_cards',
                                    'parent_label' => 'AL Rate Cards Access',
                                    'permissions'  => ['al_rate_calculation' => 'AL Rate Calculation']
                                ],
                                'DB Rate Cards' => [
                                    'parent'       => 'db_rate_cards',
                                    'parent_label' => 'DB Rate Cards Access',
                                    'permissions'  => ['db_rate_calculation' => 'DB Rate Calculation']
                                ],
                                'AGT Rate Cards' => [
                                    'parent'       => 'agt_rate_cards',
                                    'parent_label' => 'AGT Rate Cards Access',
                                    'permissions'  => ['agt_rate_calculation' => 'AGT Rate Calculation']
                                ],
                                'AFW Rate Cards' => [
                                    'parent'       => 'afw_rate_cards',
                                    'parent_label' => 'AFW Rate Cards Access',
                                    'permissions'  => ['afw_rate_calculation' => 'AFW Rate Calculation']
                                ],
                                'Global Rate Cards' => [
                                    'parent'       => 'rate_cards',
                                    'parent_label' => 'Global Rate Cards Access'
                                ]
                            ]
                        ],

                        'TMS (Ticket Management System)' => [
                            'parent'       => 'tickets',
                            'parent_label' => 'TMS Module Access',
                            'permissions'  => [
                                'raise_a_ticket' => 'Raise a Ticket',
                                'my_tickets'     => 'My Tickets',
                                'all_tickets'    => 'All Tickets',
                                'closed_tickets' => 'Closed Tickets'
                            ]
                        ],

                        'Configurations' => [
                            'parent'       => 'configurations',
                            'parent_label' => 'Configurations Access',
                            'permissions'  => [
                                'offices'          => 'Offices',
                                'erp_users'        => 'ERP Users',
                                'types_packaging'  => 'Types of Packaging',
                                'types_shipments'  => 'Types of Shipments',
                                'styles_status'    => 'Styles & Status',
                                'ship_calculations'=> 'Shipping Calculations'
                            ],
                            'sub_modules' => [
                                'Clients' => [
                                    'parent'       => 'clients',
                                    'parent_label' => 'Clients Module Access',
                                    'permissions'  => [
                                        'az_clients' => 'AZ Clients'
                                        // AL Clients uses same 'clients' permission (parent)
                                    ],
                                    'sub_modules' => [
                                        'Application Clients' => [
                                            'parent'       => 'application_clients',
                                            'parent_label' => 'Application Clients Access',
                                            'permissions'  => [
                                                'global_shipping_clients' => 'Abra Global Shipping'
                                                // truck_union_clients moved to AL Vendors
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]

                    ]; // end $permission_structure

                    // Fetch current permissions
                    $user_permissions = [];
                    if($cid > 0) {
                        $res = mysqli_query($dbConn, "SELECT section_name, can_access, edit_delete FROM manager_permissions WHERE user_id = $cid");
                        while ($row = mysqli_fetch_assoc($res)) {
                            $user_permissions[$row['section_name']] = [
                                'can_access'  => $row['can_access'],
                                'edit_delete' => $row['edit_delete']
                            ];
                        }
                    }

                    // Helper: render a single permission row
                    function renderPermRow($perm_key, $perm_label, $groupId, $parent_key, $user_permissions, $depth=0) {
                        $pad_class = $depth > 1 ? 'permission-name-deep' : 'permission-name';
                        $access  = (isset($user_permissions[$perm_key]['can_access'])  && $user_permissions[$perm_key]['can_access']  == 1) ? 'checked' : '';
                        $edit    = (isset($user_permissions[$perm_key]['edit_delete']) && $user_permissions[$perm_key]['edit_delete'] == 1) ? 'checked' : '';
                        echo '<tr class="permission-row">';
                        echo '<td class="'.$pad_class.'">'.$perm_label.'</td>';
                        echo '<td class="checkbox-cell"><input type="checkbox" class="permission-checkbox group-'.$groupId.' child-perm" data-parent-target="'.$parent_key.'" name="permissions['.$perm_key.'][can_access]" value="1" '.$access.'></td>';
                        echo '<td class="checkbox-cell"><input type="checkbox" class="permission-checkbox group-'.$groupId.'" name="permissions['.$perm_key.'][edit_delete]" value="1" '.$edit.'></td>';
                        echo '</tr>';
                    }

                    // Helper: render sub_modules recursively
                    function renderSubModules($sub_modules, $groupId, $module_parent_key, $user_permissions, $depth=0) {
                        foreach ($sub_modules as $sub_name => $sub_data) {
                            $header_class = $depth > 0 ? 'sub-sub-module-header' : 'sub-module-header';
                            echo '<tr class="sub-module-row"><td colspan="3"><div class="'.$header_class.'"><i class="fa fa-folder"></i> '.$sub_name.'</div></td></tr>';

                            if (isset($sub_data['parent'])) {
                                $pk = $sub_data['parent'];
                                $pl = $sub_data['parent_label'];
                                $access = (isset($user_permissions[$pk]['can_access'])  && $user_permissions[$pk]['can_access']  == 1) ? 'checked' : '';
                                $edit   = (isset($user_permissions[$pk]['edit_delete']) && $user_permissions[$pk]['edit_delete'] == 1) ? 'checked' : '';
                                echo '<tr class="permission-row">';
                                echo '<td class="permission-name"><strong>'.$pl.'</strong></td>';
                                echo '<td class="checkbox-cell"><input type="checkbox" class="permission-checkbox group-'.$groupId.' parent-perm" data-parent-id="'.$pk.'" name="permissions['.$pk.'][can_access]" value="1" '.$access.'></td>';
                                echo '<td class="checkbox-cell"><input type="checkbox" class="permission-checkbox group-'.$groupId.'" name="permissions['.$pk.'][edit_delete]" value="1" '.$edit.'></td>';
                                echo '</tr>';
                            }

                            if (isset($sub_data['permissions'])) {
                                foreach ($sub_data['permissions'] as $pk => $pl) {
                                    renderPermRow($pk, $pl, $groupId, $sub_data['parent'] ?? $module_parent_key, $user_permissions, $depth+1);
                                }
                            }

                            if (isset($sub_data['sub_modules'])) {
                                renderSubModules($sub_data['sub_modules'], $groupId, $sub_data['parent'] ?? $module_parent_key, $user_permissions, $depth+1);
                            }
                        }
                    }
                    ?>

                    <!-- Search -->
                    <div style="margin-bottom:20px;">
                        <input type="text" id="permission-search" placeholder="🔍 Search permissions..." style="width:100%;padding:12px 20px;font-size:14px;border:2px solid #667eea;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.1);">
                    </div>

                    <form method="POST" action="settings/hr-employee/permissions-update.php">
                        <table style="width:100%;border-collapse:collapse;" id="permissions-table">
                            <thead class="table-header">
                                <tr>
                                    <th style="text-align:left;width:60%;">Module / Permission</th>
                                    <th style="width:20%;">Can Access<br><input type="checkbox" id="global-select-all-access" title="Select All Access"></th>
                                    <th style="width:20%;">Edit/Delete<br><input type="checkbox" id="global-select-all-edit" title="Select All Edit"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($permission_structure as $module_name => $module_data):
                                $groupId = md5($module_name);
                            ?>
                                <!-- Module Header -->
                                <tr class="module-row" data-module="<?php echo strtolower($module_name); ?>">
                                    <td colspan="3">
                                        <div class="module-header">
                                            <span><i class="fa fa-folder-open"></i> <?php echo $module_name; ?></span>
                                            <label class="module-select-label">
                                                <input type="checkbox" class="module-select-checkbox" data-group="<?php echo $groupId; ?>"> Select All
                                            </label>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Module parent permission -->
                                <?php if (isset($module_data['parent'])): ?>
                                <tr class="permission-row">
                                    <td class="permission-name"><strong><?php echo $module_data['parent_label']; ?></strong></td>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" class="permission-checkbox group-<?php echo $groupId; ?> parent-perm"
                                               data-parent-id="<?php echo $module_data['parent']; ?>"
                                               name="permissions[<?php echo $module_data['parent']; ?>][can_access]" value="1"
                                               <?php echo (isset($user_permissions[$module_data['parent']]['can_access']) && $user_permissions[$module_data['parent']]['can_access']==1) ? 'checked' : ''; ?>>
                                    </td>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" class="permission-checkbox group-<?php echo $groupId; ?>"
                                               name="permissions[<?php echo $module_data['parent']; ?>][edit_delete]" value="1"
                                               <?php echo (isset($user_permissions[$module_data['parent']]['edit_delete']) && $user_permissions[$module_data['parent']]['edit_delete']==1) ? 'checked' : ''; ?>>
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <!-- Direct permissions -->
                                <?php if (isset($module_data['permissions'])): ?>
                                    <?php foreach ($module_data['permissions'] as $perm_key => $perm_label): ?>
                                    <?php renderPermRow($perm_key, $perm_label, $groupId, $module_data['parent'] ?? '', $user_permissions); ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <!-- Sub modules -->
                                <?php if (isset($module_data['sub_modules'])): ?>
                                    <?php renderSubModules($module_data['sub_modules'], $groupId, $module_data['parent'] ?? '', $user_permissions); ?>
                                <?php endif; ?>

                            <?php endforeach; ?>
                            </tbody>
                        </table>

                        <br>
                        <div style="text-align:center;">
                            <input type="hidden" name="user_id" value="<?php echo $cid; ?>">
                            <button type="submit" class="btn-update"><i class="fa fa-save"></i> Update Permissions</button>
                        </div>
                    </form>
                    <br><br>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include("footer.php"); ?>

<script src="../bower_components/jquery/dist/jquery.min.js"></script>
<script src="../bower_components/bootstrap/dist/js/bootstrap.js"></script>
<script src="js/ui-load.js"></script>
<script src="js/ui-jp.config.js"></script>
<script src="js/ui-jp.js"></script>
<script src="js/ui-nav.js"></script>
<script src="js/ui-toggle.js"></script>
<script src="js/delivery.js"></script>
<script type="text/javascript" src="js/parsley.min.js"></script>

<script type="text/javascript">
$(document).ready(function() {
    $('form').parsley();

    // Group Select All
    $('.module-select-checkbox').on('change', function() {
        var groupId = $(this).data('group');
        $('.group-' + groupId).prop('checked', $(this).is(':checked'));
    });

    // Global Select All Access
    $('#global-select-all-access').on('change', function() {
        $('input[name$="[can_access]"]').not('[type=hidden]').prop('checked', $(this).is(':checked'));
    });

    // Global Select All Edit
    $('#global-select-all-edit').on('change', function() {
        $('input[name$="[edit_delete]"]').not('[type=hidden]').prop('checked', $(this).is(':checked'));
    });

    // Search
    $('#permission-search').on('keyup input', function() {
        var searchText = $(this).val().toLowerCase().trim();
        if(searchText === '') {
            $('#permissions-table tbody tr').show();
        } else {
            $('#permissions-table tbody tr').hide();
            $('#permissions-table tbody tr').each(function() {
                if($(this).text().toLowerCase().indexOf(searchText) > -1) {
                    $(this).show();
                    if($(this).hasClass('permission-row') || $(this).hasClass('sub-module-row')) {
                        $(this).prevAll('.module-row').first().show();
                    }
                }
            });
        }
    });

    // Auto-check parent if child checked
    $('.child-perm').on('change', function() {
        if($(this).is(':checked')) {
            var parentTarget = $(this).data('parent-target');
            if(parentTarget) {
                $('input[name="permissions['+parentTarget+'][can_access]"]').prop('checked', true);
            }
        }
    });
});
</script>
</body>
</html>