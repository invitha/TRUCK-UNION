<?php
// =========================================================================
// ACTIVE SHIPMENTS PAGE - Fixed for Your Database Structure
// =========================================================================
// 
// OFFICE-BASED ACCESS CONTROL:
// - Admin users (user_type = 'admin') can see ALL shipments
// - Non-admin users can only see shipments from their office
// - Set $_SESSION['user_office'] during login to the user's office name
// - Office names come from the 'warehouses' table
// 
// Example in your login.php:
// $_SESSION['user_office'] = 'Mumbai Office'; // Get from user's profile
// $_SESSION['user_type'] = 'admin'; // or 'staff', 'manager', etc.
// =========================================================================

ob_start(); // Output buffering FIRST to prevent header errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Start session AFTER ob_start
session_start();

// =========================================================================
// DATABASE CONNECTION - Using Your Existing System Files
// =========================================================================
require_once('database.php');
require_once('database-settings.php');
require_once('library.php');
require_once('funciones.php');
require 'requirelanguage.php';

// Use your existing connection function
$con = conexion();
if (!$con) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($con, 'utf8mb4');

// Use $dbConn as alias for consistency
$dbConn = $con;

// =========================================================================
// DATABASE AUTO-FIX: Ensure phone columns are large enough
// =========================================================================
$phone_columns = ['r_phone', 'phone', 'telefono1'];
foreach($phone_columns as $col) {
    $check = mysqli_query($con, "SHOW COLUMNS FROM courier LIKE '$col'");
    if($check && mysqli_num_rows($check) > 0) {
        $info = mysqli_fetch_assoc($check);
        // If column is too small (less than VARCHAR(20)), expand it
        if(stripos($info['Type'], 'varchar') !== false) {
            preg_match('/varchar\((\d+)\)/i', $info['Type'], $matches);
            $current_size = isset($matches[1]) ? intval($matches[1]) : 0;
            if($current_size < 20) {
                mysqli_query($con, "ALTER TABLE courier MODIFY COLUMN $col VARCHAR(20) DEFAULT NULL");
            }
        }
    }
}

// Fix delivery_type column size
$check_dt = mysqli_query($con, "SHOW COLUMNS FROM courier LIKE 'delivery_type'");
if($check_dt && mysqli_num_rows($check_dt) > 0) {
    $info = mysqli_fetch_assoc($check_dt);
    if(stripos($info['Type'], 'varchar') !== false) {
        preg_match('/varchar\((\d+)\)/i', $info['Type'], $matches);
        $current_size = isset($matches[1]) ? intval($matches[1]) : 0;
        if($current_size < 50) {
            mysqli_query($con, "ALTER TABLE courier MODIFY COLUMN delivery_type VARCHAR(50) DEFAULT NULL");
        }
    }
}

// =========================================================================
// WHATSAPP HELPER FUNCTIONS
// =========================================================================
function wa_send_message($phone, $text) {
    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $phone,
        'type'              => 'text',
        'text'              => ['body' => $text],
    ];
    $ch = curl_init('https://graph.facebook.com/v19.0/1138160196037346/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer EAAU6AdOvUxYBRQvIwILhF2SlOTMxOZBuUQgzkmekeEd73nKYpbNh7UdZBACEIOGk9qCF6HxkiUZBVcuin733irPWNNioO0mIOojnzVAZC3UZAIurlZCcNnZBaWYwie9qxzZC22yiUL5YwznaCj1koJSzQ1ebnspZBqUTMrrApz5zbnLbAiIHam15D3eiSz22V8wZDZD',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    error_log("WA Finance MSG to $phone: " . $res);
    
    $result = json_decode($res, true);
    if (!empty($result['messages'][0]['id'])) {
        global $dbConn;
        $safe_phone = mysqli_real_escape_string($dbConn, $phone);
        $safe_text  = mysqli_real_escape_string($dbConn, mb_substr($text, 0, 2000));
        mysqli_query($dbConn,
            "INSERT INTO wa_messages (phone, direction, message_text, sent_by, created_at)
             VALUES ('{$safe_phone}', 'out', '{$safe_text}', 'bot', NOW())"
        );
    }
}

function wa_send_cta($phone, $bodyText, $buttonTitle, $url) {
    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $phone,
        'type'              => 'interactive',
        'interactive'       => [
            'type'   => 'cta_url',
            'body'   => ['text' => $bodyText],
            'action' => [
                'name'       => 'cta_url',
                'parameters' => [
                    'display_text' => mb_substr($buttonTitle, 0, 20),
                    'url'          => $url,
                ],
            ],
        ],
    ];
    $ch = curl_init('https://graph.facebook.com/v19.0/1138160196037346/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer EAAU6AdOvUxYBRQvIwILhF2SlOTMxOZBuUQgzkmekeEd73nKYpbNh7UdZBACEIOGk9qCF6HxkiUZBVcuin733irPWNNioO0mIOojnzVAZC3UZAIurlZCcNnZBaWYwie9qxzZC22yiUL5YwznaCj1koJSzQ1ebnspZBqUTMrrApz5zbnLbAiIHam15D3eiSz22V8wZDZD',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    error_log("WA Finance CTA to $phone: " . $res);
}

function wa_send_template($phone, $template_name, $variables) {
    $params = [];
    foreach ($variables as $v) {
        $params[] = ['type' => 'text', 'text' => (string)$v];
    }
    $templates_with_button = [];
    
    $components = [
        ['type' => 'body', 'parameters' => $params],
    ];
    
    if (in_array($template_name, $templates_with_button) && !empty($variables[0])) {
        // First button parameter (Download Invoice or Tracking Link)
        $components[] = [
            'type'       => 'button',
            'sub_type'   => 'url',
            'index'      => '0',
            'parameters' => [
                ['type' => 'text', 'text' => (string)$variables[0]],
            ],
        ];
    }
    
    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $phone,
        'type'              => 'template',
        'template'          => [
            'name'       => $template_name,
            'language'   => ['code' => 'en'],
            'components' => $components,
        ],
    ];
    $ch = curl_init('https://graph.facebook.com/v19.0/1138160196037346/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer EAAU6AdOvUxYBRQvIwILhF2SlOTMxOZBuUQgzkmekeEd73nKYpbNh7UdZBACEIOGk9qCF6HxkiUZBVcuin733irPWNNioO0mIOojnzVAZC3UZAIurlZCcNnZBaWYwie9qxzZC22yiUL5YwznaCj1koJSzQ1ebnspZBqUTMrrApz5zbnLbAiIHam15D3eiSz22V8wZDZD',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    error_log("WA Template to $phone [$template_name]: " . $res);
    
    $result = json_decode($res, true);
    if (!empty($result['messages'][0]['id'])) {
        global $dbConn;
        $safe_phone = mysqli_real_escape_string($dbConn, $phone);
        $template_labels = [
            'shipment_out_for_delivery_cod'      => '🚚 Out for Delivery',
            'shipment_out_for_delivery_prepaid'  => '🚚 Out for Delivery',
            'shipment_delivered_cod_v2'          => '✅ Delivered',
            'shipment_delivered_prepaid'         => '✅ Delivered',
            'shipment_arrived_facility_sender'   => '📦 Arrived at Facility',
            'shipment_failed_delivery_sender'    => '❌ Failed Delivery',
            'shipment_callback_confirm_'         => '📞 Callback Confirmed',
            'team_callback_alert'                => '🔔 Callback Alert',
        ];
        $label    = $template_labels[$template_name] ?? $template_name;
        $tracking = !empty($variables[0]) ? $variables[0] : '';
        $route    = (!empty($variables[1]) && !empty($variables[2])) ? $variables[1] . ' → ' . $variables[2] : '';
        $log_text = "📨 {$label}" . ($tracking ? " | {$tracking}" : '') . ($route ? " | {$route}" : '');
        $safe_text  = mysqli_real_escape_string($dbConn, mb_substr($log_text, 0, 2000));
        mysqli_query($dbConn,
            "INSERT INTO wa_messages (phone, direction, message_text, sent_by, created_at)
             VALUES ('{$safe_phone}', 'out', '{$safe_text}', 'bot', NOW())"
        );
    }
    
    return $res;
}

// =========================================================================
// HANDLE QUICK UPDATE (Must be before any HTML output)
// =========================================================================
if (isset($_POST['quick_update_submit'])) {
    try {
        error_log("Quick Update: Starting process");
        
        $tn = mysqli_real_escape_string($dbConn, trim($_POST['tracking_no'] ?? ''));
        $ns = mysqli_real_escape_string($dbConn, trim($_POST['status'] ?? ''));
        $nd = mysqli_real_escape_string($dbConn, trim($_POST['detailed_status'] ?? ''));
        $nr = mysqli_real_escape_string($dbConn, trim($_POST['remarks'] ?? ''));
        $user = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'System';
        
        error_log("Quick Update: TN=$tn, Status=$ns");
        
        if (!empty($tn) && !empty($ns)) {
            $stmt = mysqli_prepare($dbConn, "SELECT cid, ship_name, phone, correo, status FROM courier WHERE tracking = ? LIMIT 1");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $tn);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                
                if ($rl = mysqli_fetch_assoc($res)) {
                    $cid = $rl['cid'];
                    $current_status = $rl['status'];
                    error_log("Quick Update: Found shipment CID=$cid, Current Status=$current_status");
                    
                    $status_sequence = [
                        'AWB Created' => 'Order Created in System',
                        'Pickup Assigned' => 'Pickup has been assigned to delivery partner',
                        'Picked Up' => 'Package has been picked up from sender',
                        'Received at Office' => 'The consignment is Received in office',
                        'In Scan' => 'The consignment is process for delivery',
                        'In Transit' => 'The consignment is in transit to the delivery',
                        'In Warehouse' => 'The consignment is delivery at destination',
                        'Out for Delivery' => 'The consignment is out for delivery to the consignee',
                        'Delivered' => 'The consignment as delivered to the consignee'
                    ];
                    
                    // MANDATORY STATUSES - These must be done manually, never auto-filled
                    $mandatory_statuses = ['Pickup Assigned', 'Picked Up'];
                    
                    $status_names = array_keys($status_sequence);
                    
                    // Find current and new status positions (case-insensitive search)
                    $current_pos = false;
                    $new_pos = false;
                    
                    foreach ($status_names as $idx => $status_name) {
                        if (strcasecmp($status_name, $current_status) === 0) {
                            $current_pos = $idx;
                        }
                        if (strcasecmp($status_name, $ns) === 0) {
                            $new_pos = $idx;
                        }
                    }
                    
                    // If current position not found, assume it's at the beginning
                    if ($current_pos === false) $current_pos = 0;
                    
                    error_log("Quick Update: Current='$current_status' pos=$current_pos, New='$ns' pos=$new_pos");
                    
                    // Fetch existing statuses to avoid duplicates and check history
                    $existing_statuses = [];
                    $eq = mysqli_query($dbConn, "SELECT status FROM courier_track WHERE cid = $cid");
                    if ($eq) {
                        while ($er = mysqli_fetch_assoc($eq)) {
                            $existing_statuses[] = strtolower(trim($er['status']));
                        }
                    }

                    // Auto-fill and validation allows automatic insertion of pickup steps as requested.
                    
                    // Update courier table
                    $su = mysqli_prepare($dbConn, "UPDATE courier SET status = ?, detailed_status = ? WHERE tracking = ?");
                    if ($su) {
                        mysqli_stmt_bind_param($su, "sss", $ns, $nd, $tn);
                        
                        if (mysqli_stmt_execute($su)) {
                            error_log("Quick Update: Update successful");
                            

                            $now = date('Y-m-d H:i:s');
                            $letra = 'TR';
                            $pick_time = '00:00';
                            
                            // AUTO-FILL: Insert missing intermediate statuses
                            if ($new_pos !== false && $new_pos > $current_pos) {
                                error_log("Quick Update: Auto-filling intermediate statuses");
                                
                                // Fetch existing statuses to avoid duplicates and check history
                                $existing_statuses = [];
                                $eq = mysqli_query($dbConn, "SELECT status FROM courier_track WHERE cid = $cid");
                                if ($eq) {
                                    while ($er = mysqli_fetch_assoc($eq)) {
                                        // Normalize by removing all spaces and lowercase
                                        $norm = strtolower(preg_replace('/\s+/', '', $er['status']));
                                        $existing_statuses[] = $norm;
                                    }
                                }
                                
                                // Insert all intermediate statuses
                                for ($i = $current_pos + 1; $i <= $new_pos; $i++) {
                                    $intermediate_status = $status_names[$i];
                                    $norm_intermediate = strtolower(preg_replace('/\s+/', '', $intermediate_status));
                                    
                                    // Skip if intermediate status already exists in history
                                    // Or if it matches known variations
                                    $is_duplicate = false;
                                    if ($i < $new_pos) {
                                        if (in_array($norm_intermediate, $existing_statuses)) {
                                            $is_duplicate = true;
                                        }
                                        // Special checks for variations the user might have typed manually
                                        if ($norm_intermediate == 'receivedatoffice' && in_array('receivedoffice', $existing_statuses)) $is_duplicate = true;
                                        if ($norm_intermediate == 'inscan' && in_array('inscan', $existing_statuses)) $is_duplicate = true;
                                    }
                                    
                                    if ($is_duplicate) {
                                        error_log("Quick Update: Skipping $intermediate_status as it already exists in history");
                                        continue;
                                    }
                                    
                                    $standard_message = $status_sequence[$intermediate_status];
                                    
                                    // Backdate the time so the final status is exactly at $now
                                    // Subtract minutes based on how far it is from the new_pos
                                    $status_time = date('Y-m-d H:i:s', strtotime($now) - (($new_pos - $i) * 60));
                                    
                                    $si = mysqli_prepare($dbConn, "INSERT INTO courier_track (cid, cons_no, ship_name, phone, correo, status, detailed_status, comments, bk_time, user, letra, pick_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                    
                                    if ($si) {
                                        if ($i == $new_pos) {
                                            // Final status - user entered this one
                                            // detailed_status = status name (e.g., "In Transit")
                                            // comments = what user typed in remarks, or standard message
                                            $final_detailed = $intermediate_status;
                                            $final_comment = !empty($nr) ? $nr : $standard_message;
                                        } else {
                                            // Intermediate auto-filled status
                                            // detailed_status = status name (e.g., "In Scan")
                                            // comments = standard system message
                                            $final_detailed = $intermediate_status;
                                            $final_comment = $standard_message;
                                        }
                                        
                                        mysqli_stmt_bind_param($si, "isssssssssss", 
                                            $cid, $tn, $rl['ship_name'], $rl['phone'], $rl['correo'], 
                                            $intermediate_status, $final_detailed, $final_comment, 
                                            $status_time, $user, $letra, $pick_time);
                                        mysqli_stmt_execute($si);
                                        mysqli_stmt_close($si);
                                        error_log("Quick Update: Inserted status - $intermediate_status");
                                    }
                                }
                                
                                $_SESSION['update_message'] = array('type' => 'success', 'text' => "✓ Shipment #$tn updated to $ns with complete tracking history!");
                            } else {
                                // No auto-fill needed, just insert the new status
                                // detailed_status = status name (e.g., "In Transit")
                                // comments = what user typed in remarks, or standard message
                                $standard_message = $status_sequence[$ns] ?? '';
                                $user_remarks = !empty($nr) ? $nr : $standard_message;
                                
                                $si = mysqli_prepare($dbConn, "INSERT INTO courier_track (cid, cons_no, ship_name, phone, correo, status, detailed_status, comments, bk_time, user, letra, pick_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                
                                if ($si) {
                                    mysqli_stmt_bind_param($si, "isssssssssss", $cid, $tn, $rl['ship_name'], $rl['phone'], $rl['correo'], $ns, $ns, $user_remarks, $now, $user, $letra, $pick_time);
                                    mysqli_stmt_execute($si);
                                    mysqli_stmt_close($si);
                                    error_log("Quick Update: Tracking history inserted");
                                }
                                
                                $_SESSION['update_message'] = array('type' => 'success', 'text' => "Shipment #$tn updated successfully!");
                            }
                            
                            $_SESSION['scroll_to_tracking'] = $tn; // Store tracking number to scroll to
                            
                            // ── WhatsApp notification for status update ──────────────
                            $wa_tracking = $tn;
                            $wa_status   = $ns;
                            $wa_phone_sender   = $rl['phone'] ?? '';
                            $wa_phone_receiver = '';
                            $wa_paymode        = '';
                            $wa_origin_city    = '';
                            $wa_dest_city      = '';
                            
                            $pq = mysqli_query($dbConn, "SELECT paymode, phone, r_phone, ciudad, city1 FROM courier WHERE tracking='$tn' LIMIT 1");
                            if ($pq && $pr = mysqli_fetch_assoc($pq)) {
                                $wa_paymode        = strtolower(trim($pr['paymode'] ?? ''));
                                $wa_phone_sender   = !empty($pr['phone'])  ? $pr['phone']  : $wa_phone_sender;
                                $wa_phone_receiver = $pr['r_phone'] ?? '';
                                $wa_origin_city    = $pr['ciudad']  ?? '';
                                $wa_dest_city      = $pr['city1']   ?? '';
                            }
                            
                            if (!function_exists('wa_format_phone')) {
                                function wa_format_phone($p) {
                                    $p = preg_replace('/\D/', '', $p);
                                    if (strlen($p) === 10) $p = '91' . $p;
                                    return $p;
                                }
                            }
                            
                            $wa_sender_fmt   = wa_format_phone($wa_phone_sender);
                            $wa_receiver_fmt = wa_format_phone($wa_phone_receiver);
                            
                            $is_cod = ($wa_paymode === 'cod' || $wa_paymode === 'cash' || stripos($wa_paymode, 'cash') !== false);
                            $vars   = [$wa_tracking, $wa_origin_city, $wa_dest_city];
                            
                            error_log("WA NOTIFY: tracking=$wa_tracking status=$wa_status paymode=$wa_paymode sender=$wa_sender_fmt receiver=$wa_receiver_fmt is_cod=" . ($is_cod ? 'yes' : 'no'));
                            
                            $wa_debug = '';
                            if (stripos($wa_status, 'out for delivery') !== false) {
                                $tpl = $is_cod ? 'shipment_out_for_delivery_cod' : 'shipment_out_for_delivery_prepaid';
                                if ($wa_sender_fmt) {
                                    $wa_debug = wa_send_template($wa_sender_fmt, $tpl, $vars);
                                }
                            }
                            
                            if (stripos($wa_status, 'arrived at facility') !== false) {
                                if ($wa_sender_fmt) {
                                    wa_send_template($wa_sender_fmt, 'shipment_arrived_facility_sender', $vars);
                                }
                            }
                            
                            if (stripos($wa_status, 'failed delivery') !== false || stripos($wa_status, 'delivery failed') !== false) {
                                if ($wa_sender_fmt) {
                                    wa_send_template($wa_sender_fmt, 'shipment_failed_delivery_sender', $vars);
                                }
                            }
                            
                            if (stripos($wa_status, 'delivered') !== false && stripos($wa_status, 'out for') === false) {
                                error_log("WA DEBUG - ENTERED DELIVERED BLOCK");
                                $tpl = $is_cod ? 'shipment_delivered_cod_v2' : 'shipment_delivered_prepaid';
                                error_log("WA DEBUG - DELIVERED TPL: " . $tpl);
                                
                                if ($wa_sender_fmt) {
                                    error_log("WA DEBUG - Sending to SENDER: " . $wa_sender_fmt);
                                    $wa_debug = wa_send_template($wa_sender_fmt, $tpl, $vars);
                                } else {
                                    error_log("WA DEBUG - SENDER phone is empty");
                                }
                                
                                if ($wa_receiver_fmt && $wa_receiver_fmt !== $wa_sender_fmt) {
                                    error_log("WA DEBUG - Sending to RECEIVER: " . $wa_receiver_fmt);
                                    sleep(1);
                                    $wa_debug .= " | REC: " . wa_send_template($wa_receiver_fmt, $tpl, $vars);
                                } else {
                                    error_log("WA DEBUG - NOT sending to RECEIVER (empty or same as sender). Receiver=" . $wa_receiver_fmt . ", Sender=" . $wa_sender_fmt);
                                }
                            } else {
                                if (stripos($wa_status, 'delivered') !== false) {
                                    error_log("WA DEBUG - DELIVERED in string, BUT 'out for' also in string? status=" . $wa_status);
                                }
                            }
                            // ── End WhatsApp notifications ───────────────────────────
                            
                            // Append the WhatsApp API result to the green success banner so it shows on screen
                            if (isset($_SESSION['update_message']) && is_array($_SESSION['update_message'])) {
                                $_SESSION['update_message']['text'] .= " | WA: $wa_status | API: " . ($wa_debug ?? 'No Action');
                            }
                        } else {
                            error_log("Quick Update: Execute failed - " . mysqli_stmt_error($su));
                            $_SESSION['update_message'] = array('type' => 'danger', 'text' => 'Update failed');
                        }
                        mysqli_stmt_close($su);
                    }
                } else {
                    error_log("Quick Update: Shipment not found");
                    $_SESSION['update_message'] = array('type' => 'warning', 'text' => 'Shipment not found');
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            error_log("Quick Update: Missing required fields");
            $_SESSION['update_message'] = array('type' => 'danger', 'text' => 'Tracking number and status required');
        }
        
        error_log("Quick Update: About to redirect");
        
        // Redirect using output buffer
        $current_file = basename(__FILE__);
        header("Location: $current_file");
        exit();
        
    } catch (Exception $e) {
        error_log("Quick Update EXCEPTION: " . $e->getMessage());
        error_log("Quick Update TRACE: " . $e->getTraceAsString());
        $_SESSION['update_message'] = array('type' => 'danger', 'text' => 'Error: ' . $e->getMessage());
        $current_file = basename(__FILE__);
        header("Location: $current_file");
        exit();
    } catch (Error $e) {
        error_log("Quick Update ERROR: " . $e->getMessage());
        error_log("Quick Update TRACE: " . $e->getTraceAsString());
        die("Fatal error in quick update: " . $e->getMessage());
    }
}

// NOW auto-set user location and department from hr_employees if not already set
if (isset($_SESSION['user_name']) && (!isset($_SESSION['user_office']) || empty($_SESSION['user_office']) || !isset($_SESSION['user_department']))) {
    $user_name = mysqli_real_escape_string($dbConn, $_SESSION['user_name']);
    $query = "SELECT work_location, department FROM hr_employees WHERE name = '$user_name' LIMIT 1";
    $result = mysqli_query($dbConn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $user_data = mysqli_fetch_assoc($result);
        $_SESSION['user_office'] = trim($user_data['work_location'] ?? '');
        $_SESSION['user_department'] = trim($user_data['department'] ?? '');
        error_log("Active Shipments - Auto-set from DB - Office: " . $_SESSION['user_office'] . ", Department: " . $_SESSION['user_department']);
    }
}

// FIRST: Check if user is Admin by name (HIGHEST PRIORITY)
$currentUserName = isset($_SESSION['user_name']) ? trim($_SESSION['user_name']) : '';
$authorized_admin_names = array('Abishek Veeraswamy', 'Abishek', 'abishek');

$isAdmin = false;
foreach($authorized_admin_names as $admin_name) {
    if(stripos($currentUserName, $admin_name) !== false || stripos($admin_name, $currentUserName) !== false) {
        $isAdmin = true;
        break;
    }
}

// SECOND: Check if user is Manager by department
$is_manager = false;
if (isset($_SESSION['user_department'])) {
    $dept = trim($_SESSION['user_department']);
    if (strcasecmp($dept, 'Management') === 0) {
        $is_manager = true;
    }
}

// CRITICAL: If user is Admin OR Manager, they should see ALL shipments
$can_see_all_shipments = ($isAdmin || $is_manager);

error_log("Active Shipments - User: " . $currentUserName);
error_log("Active Shipments - Is Admin: " . ($isAdmin ? 'YES' : 'NO'));
error_log("Active Shipments - Is Manager: " . ($is_manager ? 'YES' : 'NO'));
error_log("Active Shipments - Can See All: " . ($can_see_all_shipments ? 'YES' : 'NO'));
error_log("Active Shipments - Department: " . ($_SESSION['user_department'] ?? 'NOT SET'));
error_log("Active Shipments - User Office: " . ($_SESSION['user_office'] ?? 'NOT SET'));

// Get user's work location from session (only used if NOT admin/manager)
$user_work_location = $_SESSION['user_office'] ?? '';
$user_work_location = trim($user_work_location);

// Debug: Log the filtering condition
if ($can_see_all_shipments) {
    error_log("Active Shipments - Admin/Manager viewing ALL shipments (no filter)");
} else if (!empty($user_work_location)) {
    error_log("Active Shipments - Regular user FILTERING by office: " . $user_work_location);
} else {
    error_log("Active Shipments - Regular user with NO office set (will see all)");
}

// Set timezone
date_default_timezone_set(isset($_SESSION['ge_timezone']) ? $_SESSION['ge_timezone'] : 'Asia/Kolkata');

// Set session defaults if not set
if (!isset($_SESSION['ge_timezone'])) $_SESSION['ge_timezone'] = 'Asia/Kolkata';
if (!isset($_SESSION['ge_cname'])) $_SESSION['ge_cname'] = 'ABRA Logistics';
if (!isset($_SESSION['ge_curr'])) $_SESSION['ge_curr'] = '₹';

/* Tracking number generation for add courier panel */
$prefix = $_SESSION['ge_prefix'] ?? 'AL';
$esc_pfx = mysqli_real_escape_string($dbConn, $prefix);
$pa = mysqli_query($dbConn, "SELECT MAX(cons_no) as m FROM c_tracking");
$rct = mysqli_fetch_array($pa);
$max_t = $rct['m'] ? intval($rct['m']) : 0;
$pa2 = mysqli_query($dbConn, "SELECT MAX(CAST(REPLACE(tracking,'{$esc_pfx}-','') AS UNSIGNED)) as m FROM courier WHERE tracking LIKE '{$esc_pfx}-%'");
$rco = mysqli_fetch_array($pa2);
$max_c = $rco['m'] ? intval($rco['m']) : 0;
$new_track = $prefix . '-' . (max($max_t, $max_c) + 1);

/* Countries list */
$ad_countries = [
    ['code'=>'IN','name'=>'India','flag'=>'🇮🇳'],
    ['code'=>'US','name'=>'United States','flag'=>'🇺🇸'],
    ['code'=>'AE','name'=>'United Arab Emirates','flag'=>'🇦🇪'],
    ['code'=>'SA','name'=>'Saudi Arabia','flag'=>'🇸🇦'],
    ['code'=>'GB','name'=>'United Kingdom','flag'=>'🇬🇧'],
    ['code'=>'CA','name'=>'Canada','flag'=>'🇨🇦'],
    ['code'=>'AU','name'=>'Australia','flag'=>'🇦🇺'],
    ['code'=>'SG','name'=>'Singapore','flag'=>'🇸🇬'],
    ['code'=>'QA','name'=>'Qatar','flag'=>'🇶🇦'],
    ['code'=>'KW','name'=>'Kuwait','flag'=>'🇰🇼'],
    ['code'=>'OM','name'=>'Oman','flag'=>'🇴🇲'],
    ['code'=>'BH','name'=>'Bahrain','flag'=>'🇧🇭'],
    ['code'=>'MY','name'=>'Malaysia','flag'=>'🇲🇾'],
    ['code'=>'LK','name'=>'Sri Lanka','flag'=>'🇱🇰'],
];

/* Warehouses */
$ad_warehouses = [];
$wf = __DIR__ . '/warehouse_data/warehouses.json';
if (file_exists($wf)) $ad_warehouses = json_decode(file_get_contents($wf), true) ?: [];

/* ── AJAX pincode lookup ─────────────────────────────────── */
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'lookup_pincode') {
    header('Content-Type: application/json');
    $pin = trim($_GET['pincode'] ?? '');
    $ctry = strtoupper(trim($_GET['country'] ?? 'IN'));
    
    if (empty($pin)) { 
        echo json_encode(['success'=>false,'message'=>'Pincode required']); 
        exit; 
    }
    
    if ($ctry === 'IN') {
        $opts = ['http'=>['method'=>'GET','header'=>"User-Agent: Mozilla/5.0\r\n",'timeout'=>10,'ignore_errors'=>true]];
        $ctx  = stream_context_create($opts);
        $res = @file_get_contents("https://api.postalpincode.in/pincode/{$pin}", false, $ctx);
        
        if ($res) {
            $d = json_decode($res, true);
            if (!empty($d[0]['Status']) && $d[0]['Status']==='Success' && !empty($d[0]['PostOffice'])) {
                $po = $d[0]['PostOffice'][0];
                
                $locality = trim($po['Name'] ?? '');
                $block = trim($po['Block'] ?? '');
                $district = trim($po['District'] ?? '');
                $state = trim($po['State'] ?? '');
                
                // FIX: Extract expression to variable first
                $blockVal = ($block && $block !== $locality && $block !== $district) ? $block : null;
                $parts = array_filter([$locality, $blockVal, $district, $state, 'India']);
                
                echo json_encode([
                    'success'=>true,
                    'locality'=>$locality,
                    'city'=>$block ? $block : $district,
                    'state'=>$state,
                    'display_text'=> implode(', ', $parts)
                ]);
                exit;
            }
        }
    }
    
    echo json_encode(['success'=>false,'message'=>'Location not found. Please enter manually.']);
    exit;
}

// PHP 5.6+ compatible escape function (defined once at top level)
if (!function_exists('escape_value')) {
    function escape_value($dbConn, $v) {
        return mysqli_real_escape_string($dbConn, trim($v ?? ''));
    }
}

// Date validation and formatting function for CSV import
if (!function_exists('validateAndFormatDate')) {
    function validateAndFormatDate($dateStr, $daysOffset = 0) {
        // Clean the date string
        $dateStr = trim($dateStr ?? '');
        
        // Check if empty or invalid values
        if (empty($dateStr) || 
            strtolower($dateStr) === 'india' || 
            $dateStr === '0000-00-00' || 
            $dateStr === '0000-00-00 00:00:00') {
            // Return today + offset
            return date('Y-m-d', strtotime("+{$daysOffset} days"));
        }
        
        // Try to parse the date
        $timestamp = strtotime($dateStr);
        
        // If parsing failed, return today + offset
        if ($timestamp === false || $timestamp < 0) {
            return date('Y-m-d', strtotime("+{$daysOffset} days"));
        }
        
        // Return formatted date
        return date('Y-m-d', $timestamp);
    }
}

/* ── ADD SHIPMENT ─────────── */
if (isset($_POST['add_shipment_inline'])) {
    // CRITICAL: Detect AJAX request
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    // For AJAX: Suppress ALL output except our JSON
    if ($is_ajax) {
        error_reporting(0);
        ini_set('display_errors', 0);
        while (ob_get_level()) ob_end_clean();
        ob_start();
    }
    
    error_log("=== ADD SHIPMENT START (AJAX: " . ($is_ajax ? 'YES' : 'NO') . ") ===");
    
    try {
        /* fresh tracking number */
        $pa3   = mysqli_query($dbConn, "SELECT MAX(cons_no) as m FROM c_tracking");
        $rct3  = mysqli_fetch_array($pa3);
        $mt3   = $rct3['m'] ? intval($rct3['m']) : 0;
        $pa4   = mysqli_query($dbConn, "SELECT MAX(CAST(REPLACE(tracking,'{$esc_pfx}-','') AS UNSIGNED)) as m FROM courier WHERE tracking LIKE '{$esc_pfx}-%'");
        $rco3  = mysqli_fetch_array($pa4);
        $mc3   = $rco3['m'] ? intval($rco3['m']) : 0;
        $cn    = max($mt3, $mc3) + 1;
        $trk   = $prefix . '-' . $cn;
        
        error_log("Generated tracking: $trk");
        
        $now = date('Y-m-d H:i:s');

        $sql = "INSERT INTO courier (
                    tracking, cons_no, letra, 
                    ship_name, correo, phone, s_add, cc,
                    zipcode, sender_locality, ciudad, state,
                    rev_name, email, r_phone, telefono1, r_add, paisdestino, cc_r,
                    zipcode1, receiver_locality, city1, state1,
                    reference_no, delivery_aggregator, invoice_no, invoice_id,
                    qty, comments, book_mode, paymode, type, mode,
                    shipping_method, dom_internation, shipping_subtotal,
                    pesoreal, altura, ancho, longitud, totalpeso,
                    pick_date, schedule, pick_time, status, book_date, user, officename, delivery_type
                ) VALUES (
                    '{$trk}', '{$cn}', '{$prefix}',
                    '".escape_value($dbConn, $_POST['Shippername'])."',
                    '".escape_value($dbConn, $_POST['Shipperemail'])."',
                    '".escape_value($dbConn, $_POST['Shipperphone'])."',
                    '".escape_value($dbConn, $_POST['Shipperaddress'])."',
                    '".escape_value($dbConn, $_POST['sender_country_code'])."',
                    '".escape_value($dbConn, $_POST['zipcode'])."',
                    '".escape_value($dbConn, $_POST['sender_locality'])."',
                    '".escape_value($dbConn, $_POST['ciudad'])."',
                    '".escape_value($dbConn, $_POST['state'])."',
                    '".escape_value($dbConn, $_POST['Receivername'])."',
                    '".escape_value($dbConn, $_POST['Receiveremail'])."',
                    '".escape_value($dbConn, $_POST['Receiverphone'])."',
                    '".escape_value($dbConn, $_POST['telefono1'])."',
                    '".escape_value($dbConn, $_POST['Receiveraddress'])."',
                    '".escape_value($dbConn, $_POST['receiver_country_code'])."',
                    '".escape_value($dbConn, $_POST['receiver_country_code'])."',
                    '".escape_value($dbConn, $_POST['zipcode1'])."',
                    '".escape_value($dbConn, $_POST['receiver_locality'])."',
                    '".escape_value($dbConn, $_POST['city1'])."',
                    '".escape_value($dbConn, $_POST['state1'])."',
                    '".escape_value($dbConn, $_POST['reference_no'] ?? '')."',
                    '".escape_value($dbConn, $_POST['delivery_aggregator'] ?? '')."',
                    '".escape_value($dbConn, $_POST['Shippercc'])."',
                    '".escape_value($dbConn, $_POST['invoice_value'] ?? $_POST['shipping_subtotal'])."',
                    '".escape_value($dbConn, $_POST['Qnty'])."',
                    '".escape_value($dbConn, $_POST['Comments'])."',
                    '".escape_value($dbConn, $_POST['bookingmode'])."',
                    '".escape_value($dbConn, $_POST['bookingmode'])."',
                    '".escape_value($dbConn, $_POST['Shiptype'])."',
                    '".escape_value($dbConn, $_POST['Mode'])."',
                    '".escape_value($dbConn, $_POST['shipping_method'])."',
                    '".escape_value($dbConn, $_POST['dom_internation'])."',
                    '".escape_value($dbConn, $_POST['shipping_subtotal'])."',
                    '".escape_value($dbConn, $_POST['pesoreal'])."',
                    '".escape_value($dbConn, $_POST['altura'])."',
                    '".escape_value($dbConn, $_POST['ancho'])."',
                    '".escape_value($dbConn, $_POST['longitud'])."',
                    '".escape_value($dbConn, $_POST['totalpeso'])."',
                    '".escape_value($dbConn, $_POST['Packupdate'])."',
                    '".escape_value($dbConn, $_POST['Schedule'])."',
                    '00:00',
                    'AWB Created', '{$now}',
                    '".escape_value($dbConn, $_SESSION['user_name'] ?? '')."',
                    '".escape_value($dbConn, $_POST['office_location'] ?? $_SESSION['user_office'] ?? '')."',
                    '".escape_value($dbConn, $_POST['delivery_type'] ?? 'General Delivery')."'
                )";

        if (mysqli_query($dbConn, $sql)) {
            $cid_new = mysqli_insert_id($dbConn);
            error_log("INSERT successful, CID: $cid_new");
            
            /* tracking history - OPTIONAL, don't crash if it fails */
            try {
                $si = mysqli_prepare($dbConn,
                    "INSERT INTO courier_track (cid, cons_no, letra, pick_time, status, detailed_status, comments, bk_time, ship_name, phone, correo, user)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($si) {
                    $un = $_SESSION['user_name'] ?? 'System';
                    $sn = trim($_POST['Shippername'] ?? '');
                    $sp = trim($_POST['Shipperphone'] ?? '');
                    $se = trim($_POST['Shipperemail'] ?? '');
                    $status = 'AWB Created';
                    $detailed_status = '';
                    $comments = 'Shipment created';
                    $letra = 'AL';
                    $pick_time = '00:00';
                    
                    // 12 parameters: i,s,s,s,s,s,s,s,s,s,s,s
                    mysqli_stmt_bind_param($si, 'isssssssssss', 
                        $cid_new, $trk, $letra, $pick_time, 
                        $status, $detailed_status, $comments, $now, 
                        $sn, $sp, $se, $un);
                    mysqli_stmt_execute($si);
                    mysqli_stmt_close($si);
                    error_log("Tracking history inserted successfully");
                } else {
                    error_log("Tracking history prepare failed: " . mysqli_error($dbConn));
                }
            } catch (Exception $track_ex) {
                error_log("Tracking history insert failed (non-fatal): " . $track_ex->getMessage());
            }
            
            // CRITICAL: Return JSON for AJAX and EXIT immediately
            if ($is_ajax) {
                ob_end_clean();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success'=>true, 'message'=>"✓ Shipment <strong>{$trk}</strong> created!"]);
                error_log("JSON response sent, exiting");
                exit();
            } else {
                $_SESSION['add_shipment_message'] = ['type'=>'success', 'text'=>"✓ Shipment <strong>{$trk}</strong> created!"];
            }
        } else {
            throw new Exception(mysqli_error($dbConn));
        }
    } catch (Exception $ex) {
        error_log("EXCEPTION in add_shipment_inline: " . $ex->getMessage());
        error_log("Stack trace: " . $ex->getTraceAsString());
        
        if ($is_ajax) {
            if (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>false, 'message'=>'Error: '.$ex->getMessage()]);
            error_log("JSON error response sent, exiting");
            exit();
        } else {
            $_SESSION['add_shipment_message'] = ['type'=>'danger', 'text'=>'Error: '.$ex->getMessage()];
        }
    }
    
    error_log("=== ADD SHIPMENT END ===");
    
    // Only redirect if NOT AJAX
    if (!$is_ajax) {
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit();
    }
}


/* ══════════════════════════════════════════════════════════════
   ADD COURIER INLINE FUNCTIONALITY
   ══════════════════════════════════════════════════════════════ */
/* Tracking number for the new shipment */
$prefix     = $_SESSION['ge_prefix'] ?? 'AL';
$esc_pfx    = mysqli_real_escape_string($dbConn, $prefix);
$pa         = mysqli_query($dbConn, "SELECT MAX(cons_no) as m FROM c_tracking");
$rct        = mysqli_fetch_array($pa);
$max_t      = $rct['m'] ? intval($rct['m']) : 0;
$pa2        = mysqli_query($dbConn, "SELECT MAX(CAST(REPLACE(tracking,'{$esc_pfx}-','') AS UNSIGNED)) as m FROM courier WHERE tracking LIKE '{$esc_pfx}-%'");
$rco        = mysqli_fetch_array($pa2);
$max_c      = $rco['m'] ? intval($rco['m']) : 0;
$new_track  = $prefix . '-' . (max($max_t, $max_c) + 1);

/* Countries list */
$ad_countries = [
    ['code'=>'IN','name'=>'India',               'flag'=>'🇮🇳'],
    ['code'=>'US','name'=>'United States',        'flag'=>'🇺🇸'],
    ['code'=>'AE','name'=>'United Arab Emirates', 'flag'=>'🇦🇪'],
    ['code'=>'SA','name'=>'Saudi Arabia',         'flag'=>'🇸🇦'],
    ['code'=>'GB','name'=>'United Kingdom',       'flag'=>'🇬🇧'],
    ['code'=>'CA','name'=>'Canada',               'flag'=>'🇨🇦'],
    ['code'=>'AU','name'=>'Australia',            'flag'=>'🇦🇺'],
    ['code'=>'SG','name'=>'Singapore',            'flag'=>'🇸🇬'],
    ['code'=>'QA','name'=>'Qatar',                'flag'=>'🇶🇦'],
    ['code'=>'KW','name'=>'Kuwait',               'flag'=>'🇰🇼'],
    ['code'=>'OM','name'=>'Oman',                 'flag'=>'🇴🇲'],
    ['code'=>'BH','name'=>'Bahrain',              'flag'=>'🇧🇭'],
    ['code'=>'MY','name'=>'Malaysia',             'flag'=>'🇲🇾'],
    ['code'=>'LK','name'=>'Sri Lanka',            'flag'=>'🇱🇰'],
];

/* Warehouses */
$ad_warehouses = [];
$wf = __DIR__ . '/warehouse_data/warehouses.json';
if (file_exists($wf)) $ad_warehouses = json_decode(file_get_contents($wf), true) ?: [];

// =========================================================================
// USER AUTHENTICATION & ADMIN CHECK
// =========================================================================
if (function_exists('isUser')) {
    isUser();
}

$currentUserName = isset($_SESSION['user_name']) ? trim($_SESSION['user_name']) : '';
$authorized_admin_names = array('Abishek Veeraswamy', 'Abishek', 'abishek');

$isAdmin = false;
foreach($authorized_admin_names as $admin_name) {
    if(stripos($currentUserName, $admin_name) !== false || stripos($admin_name, $currentUserName) !== false) {
        $isAdmin = true;
        break;
    }
}

$current_page = basename($_SERVER['PHP_SELF']);

// Simple input sanitization function
function sanitize_input($data) {
    global $dbConn;
    return mysqli_real_escape_string($dbConn, trim($data));
}

/* ══════════════════════════════════════════════════════════════
   IMPORT FUNCTIONALITY - Matches Add Shipment Fields Exactly
   ══════════════════════════════════════════════════════════════ */
if (isset($_POST['import_excel']) && isset($_FILES['import_file'])) {
    // CRITICAL: Ensure invoice_value column exists before import
    $col_check = mysqli_query($dbConn, "SHOW COLUMNS FROM courier LIKE 'invoice_value'");
    if ($col_check && mysqli_num_rows($col_check) === 0) {
        mysqli_query($dbConn, "ALTER TABLE courier ADD COLUMN invoice_value DECIMAL(12,2) DEFAULT 0.00 AFTER phone");
    }
    
    try {
        $file = $_FILES['import_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error');
        }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, ['csv'])) {
            throw new Exception('Invalid format. Please use CSV format only.');
        }
        
        // Process CSV
        $handle = fopen($file['tmp_name'], 'r');
        $rows = [];
        while (($data = fgetcsv($handle, 10000, ',')) !== FALSE) {
            $rows[] = $data;
        }
        fclose($handle);
        
        $ok = 0;
        $err = 0;
        $err_details = [];
        $now = date('Y-m-d H:i:s');
        
        // DETECT CSV TYPE: Check if it's invoice-only (2 columns) or full shipment (40+ columns)
        $first_data_row = $rows[1] ?? [];
        
        // Count non-empty columns to avoid false detection from trailing commas
        $non_empty_cols = 0;
        foreach ($first_data_row as $col) {
            if (trim($col) !== '') $non_empty_cols++;
        }
        
        $is_invoice_only = ($non_empty_cols <= 3); // 2-3 non-empty columns = invoice update only
        
        error_log("CSV Detection: Total cols=" . count($first_data_row) . " | Non-empty cols=$non_empty_cols | Invoice-only mode=" . ($is_invoice_only ? 'YES' : 'NO'));
        
        if ($is_invoice_only) {
            // INVOICE UPDATE MODE: Update existing shipments with invoice values
            error_log("=== INVOICE UPDATE MODE ACTIVATED ===");
            error_log("First row column count: " . count($first_data_row));
            
            for ($i = 1; $i < count($rows); $i++) {
                $r = $rows[$i];
                
                if (empty($r[0]) && empty($r[1])) continue;
                
                $invoice_no = trim($r[0]);
                $raw_value = $r[1] ?? '';
                $cleaned_value = str_replace([',', ' ', 'Rs.'], '', trim($raw_value));
                $invoice_value = !empty($cleaned_value) ? floatval($cleaned_value) : 0;
                
                error_log("Row $i: Invoice='$invoice_no' | Raw='$raw_value' | Cleaned='$cleaned_value' | Float=$invoice_value");
                
                if (empty($invoice_no)) continue;
                
                $escaped_invoice_no = mysqli_real_escape_string($dbConn, $invoice_no);
                
                $update_sql = "UPDATE courier 
                              SET invoice_value = $invoice_value, 
                                  invoice_id = '$invoice_value'
                              WHERE invoice_no = '$escaped_invoice_no'";
                
                error_log("SQL: $update_sql");
                
                if (mysqli_query($dbConn, $update_sql)) {
                    $affected = mysqli_affected_rows($dbConn);
                    error_log("Affected rows: $affected");
                    if ($affected > 0) {
                        $ok++;
                    } else {
                        $err++;
                        $err_details[] = "Row $i: Invoice '$invoice_no' not found";
                    }
                } else {
                    $err++;
                    $db_err = mysqli_error($dbConn);
                    error_log("SQL Error: $db_err");
                    $err_details[] = "Row $i: " . $db_err;
                }
            }
            
            $_SESSION['import_message'] = [
                'type' => $ok > 0 ? 'success' : 'warning',
                'text' => "✓ Invoice values updated: $ok records" . ($err > 0 ? " | ⚠ Not found/errors: $err" : "")
            ];
            
        } else {
            // FULL SHIPMENT MODE: Create new shipments
        
            // Skip header row, start from index 1
            for ($i = 1; $i < count($rows); $i++) {
                $r = $rows[$i];
                
                // Skip empty rows
                if (empty($r[0]) && empty($r[4])) {
                    continue;
                }
                
                try {
                    // Generate tracking number
                    $pa3 = mysqli_query($dbConn, "SELECT MAX(cons_no) as m FROM c_tracking");
                    $rct3 = mysqli_fetch_array($pa3);
                    $mt3 = $rct3['m'] ? intval($rct3['m']) : 0;
                    $pa4 = mysqli_query($dbConn, "SELECT MAX(CAST(REPLACE(tracking,'{$esc_pfx}-','') AS UNSIGNED)) as m FROM courier WHERE tracking LIKE '{$esc_pfx}-%'");
                    $rco3 = mysqli_fetch_array($pa4);
                    $mc3 = $rco3['m'] ? intval($rco3['m']) : 0;
                    $cn = max($mt3, $mc3) + 1;
                    $trk = $prefix . '-' . $cn;
                    
                    // Truncate phone numbers to 20 characters to prevent "Data too long" error
                    $sender_phone = substr($r[8] ?? '', 0, 20);
                    $receiver_phone = substr($r[17] ?? '', 0, 20);
                    $receiver_alt_phone = substr($r[18] ?? '', 0, 20);
                    
                    // Truncate delivery_type to 50 characters
                    $delivery_type = !empty($r[4]) ? substr($r[4], 0, 50) : 'General Delivery';
                    
                    // Clean and validate numeric fields - remove commas and spaces before converting
                    $invoice_value = !empty($r[1]) ? floatval(str_replace([',', ' '], '', trim($r[1]))) : 0;
                    $shipping_amount = !empty($r[32]) ? floatval(str_replace([',', ' '], '', trim($r[32]))) : 0;
                    
                    $sql = "INSERT INTO courier (
                                tracking, cons_no, letra,
                                ship_name, correo, phone, invoice_value, invoice_id, s_add, cc,
                                zipcode, sender_locality, ciudad, state,
                                rev_name, email, r_phone, telefono1, r_add, paisdestino,
                                zipcode1, receiver_locality, city1, state1,
                                reference_no, delivery_aggregator, delivery_type, invoice_no,
                                qty, comments, book_mode, paymode, type, mode,
                                shipping_method, dom_internation, shipping_subtotal,
                                pesoreal, altura, ancho, longitud, totalpeso,
                                pick_date, schedule, pick_time, status, book_date, user, officename
                            ) VALUES (
                                '{$trk}', '{$cn}', '{$prefix}',
                                '".escape_value($dbConn, $r[6])."',
                                '".escape_value($dbConn, $r[7])."',
                                '".escape_value($dbConn, $sender_phone)."',
                                '{$invoice_value}',
                                '{$invoice_value}',
                                '".escape_value($dbConn, $r[9])."',
                                '".escape_value($dbConn, $r[10])."',
                                '".escape_value($dbConn, $r[11])."',
                                '".escape_value($dbConn, $r[12])."',
                                '".escape_value($dbConn, $r[13])."',
                                '".escape_value($dbConn, $r[14])."',
                                '".escape_value($dbConn, $r[15])."',
                                '".escape_value($dbConn, $r[16])."',
                                '".escape_value($dbConn, $receiver_phone)."',
                                '".escape_value($dbConn, $receiver_alt_phone)."',
                                '".escape_value($dbConn, $r[19])."',
                                '".escape_value($dbConn, $r[20])."',
                                '".escape_value($dbConn, $r[21])."',
                                '".escape_value($dbConn, $r[22])."',
                                '".escape_value($dbConn, $r[23])."',
                                '".escape_value($dbConn, $r[24])."',
                                '".escape_value($dbConn, $r[2])."',
                                '".escape_value($dbConn, $r[3])."',
                                '".escape_value($dbConn, $delivery_type)."',
                                '".escape_value($dbConn, $r[0])."',
                                '".escape_value($dbConn, $r[25])."',
                                '".escape_value($dbConn, $r[26])."',
                                'CSV Import',
                                '".escape_value($dbConn, $r[27])."',
                                '".escape_value($dbConn, $r[28])."',
                                '".escape_value($dbConn, $r[29])."',
                                '".escape_value($dbConn, $r[30])."',
                                '".escape_value($dbConn, $r[31])."',
                                '{$shipping_amount}',
                                '".escape_value($dbConn, $r[33])."',
                                '".escape_value($dbConn, $r[34])."',
                                '".escape_value($dbConn, $r[35])."',
                                '".escape_value($dbConn, $r[36])."',
                                '".escape_value($dbConn, $r[37])."',
                                '".escape_value($dbConn, validateAndFormatDate($r[38] ?? ''))."',
                                '".escape_value($dbConn, validateAndFormatDate($r[39] ?? '', 3))."',
                                '00:00',
                                'AWB Created', '{$now}',
                                '".escape_value($dbConn, $_SESSION['user_name'] ?? '')."',
                                '".escape_value($dbConn, $r[5] ?? $_SESSION['user_office'] ?? '')."'
                            )";
                    
                    if (mysqli_query($dbConn, $sql)) {
                        $ok++;
                    } else {
                        $err++;
                        $db_error = mysqli_error($dbConn);
                        $err_details[] = "Row " . ($i+1) . ": " . $db_error;
                        error_log("Import error row $i: " . $db_error);
                    }
                } catch (Exception $row_ex) {
                    $err++;
                    $err_details[] = "Row " . ($i+1) . ": " . $row_ex->getMessage();
                    error_log("Import exception row $i: " . $row_ex->getMessage());
                }
            }
        
        $_SESSION['import_message'] = [
            'type' => $ok > 0 ? 'success' : 'danger',
            'text' => "✓ Import completed: $ok shipments imported" . ($err > 0 ? ", $err errors" : "")
        ];
        
        } // End of else block for full shipment mode
        
    } catch (Exception $e) {
        $_SESSION['import_message'] = [
            'type' => 'danger',
            'text' => 'Import failed: ' . $e->getMessage()
        ];
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/* ══════════════════════════════════════════════════════════════
   EXPORT FUNCTIONALITY (WITHOUT invoice_id)
   ══════════════════════════════════════════════════════════════ */
if (isset($_POST['export_excel']) || isset($_POST['export_selected'])) {
    if (ob_get_level()) ob_end_clean();
    
    $isSel = isset($_POST['export_selected']);
    
    $q = "SELECT c.tracking, c.invoice_no AS invoice_number, c.invoice_id AS invoice_value,
                 c.reference_no, c.delivery_aggregator, c.delivery_type,
                 c.ship_name, c.phone, c.correo, c.s_add,
                 c.rev_name, c.r_phone, c.email, c.r_add,
                 c.ciudad AS origin_city, c.state AS origin_state, c.sender_locality,
                 c.city1 AS dest_city, c.state1 AS dest_state, c.receiver_locality,
                 c.qty, c.shipping_subtotal, c.paymode, c.dom_internation,
                 c.shipping_method, c.status, c.detailed_status, c.book_date, c.pick_date
          FROM courier c 
          WHERE c.status NOT IN('Delivered', 'delivered', 'Enquiry', 'cancelled', 'Cancelled')";
    
    if ($isSel && !empty($_POST['selected_tracking'])) {
        $sel = array_map(function($t) use ($dbConn) {
            return "'" . mysqli_real_escape_string($dbConn, $t) . "'";
        }, $_POST['selected_tracking']);
        
        $q .= " AND c.tracking IN (" . implode(',', $sel) . ")";
        $pfx = "selected_shipments";
    } elseif ($isSel) {
        $_SESSION['export_error'] = "No items selected.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $pfx = "all_active_shipments";
    }
    
    $q .= " ORDER BY c.cid DESC";
    
    $result = mysqli_query($dbConn, $q);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        $_SESSION['export_error'] = "No data to export.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $pfx . '_' . date('Y-m-d_H-i-s') . '.xls"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    $heads = [
        'Tracking No.', 'Invoice Number', 'Invoice Value', 'Reference No.', 'Aggregator', 'Delivery Type',
        'Sender Name', 'Sender Phone', 'Sender Email', 'Sender Address',
        'Receiver Name', 'Receiver Phone', 'Receiver Email', 'Receiver Address',
        'Origin City', 'Origin State', 'Origin Locality', 'Dest City', 'Dest State', 'Dest Locality',
        'Qty', 'Amount', 'Payment Mode', 'Type', 'Shipping Method',
        'Status', 'Detailed Status', 'Booked Date', 'Pickup Date'
    ];
    
    echo '<table border="1"><tr>';
    foreach ($heads as $h) {
        echo '<th>' . htmlspecialchars($h) . '</th>';
    }
    echo '</tr>';
    
    while ($r = mysqli_fetch_assoc($result)) {
        echo '<tr>';
        foreach ($r as $v) {
            echo '<td>' . htmlspecialchars($v ?? '') . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}

/* ══════════════════════════════════════════════════════════════
   FLASH MESSAGES
   ══════════════════════════════════════════════════════════════ */
$msg = '';
foreach (['export_error', 'update_message', 'import_message', 'add_shipment_message'] as $k) {
    if (isset($_SESSION[$k])) {
        $type = is_array($_SESSION[$k]) ? $_SESSION[$k]['type'] : 'warning';
        $text = is_array($_SESSION[$k]) ? $_SESSION[$k]['text'] : $_SESSION[$k];
        $msg = "<div class='flash flash-{$type}'><i class='fas fa-info-circle'></i> {$text} <button class='flash-x' onclick=\"this.parentElement.remove()\">&times;</button></div>";
        unset($_SESSION[$k]);
        break;
    }
}

/* ══════════════════════════════════════════════════════════════
   HELPER FUNCTION FOR DISTINCT VALUES
   ══════════════════════════════════════════════════════════════ */
function as_dbDist($col) {
    global $dbConn;
    $col_esc = mysqli_real_escape_string($dbConn, $col);
    $out = [];
    $exc = "status NOT IN('Delivered', 'delivered', 'Enquiry', 'cancelled', 'Cancelled')";
    
    $r = @mysqli_query($dbConn, "SELECT DISTINCT `{$col_esc}` FROM courier WHERE {$exc} AND `{$col_esc}` IS NOT NULL AND `{$col_esc}` != '' ORDER BY `{$col_esc}`");
    
    if (!$r) return $out;
    
    while ($row = mysqli_fetch_row($r)) {
        if (isset($row[0]) && trim($row[0]) !== '') {
            $out[] = $row[0];
        }
    }
    
    return $out;
}

/* ══════════════════════════════════════════════════════════════
   GET FILTER DATA
   ══════════════════════════════════════════════════════════════ */
$shippingMethods = as_dbDist('shipping_method');
$aggregators = as_dbDist('delivery_aggregator');
$originCities = as_dbDist('ciudad');
$originStates = as_dbDist('state');
$destCities = as_dbDist('city1');
$destStates = as_dbDist('state1');
$payModes = as_dbDist('paymode');
$senderCountries = as_dbDist('cc');
$receiverCountries = as_dbDist('paisdestino');

// Get status options from service_mode table
$status_options = [];
$r = mysqli_query($dbConn, "SELECT servicemode FROM service_mode ORDER BY servicemode");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        $status_options[] = $row['servicemode'];
    }
}

// If no status options from table, use defaults
if (empty($status_options)) {
    $status_options = [
        'AWB Created',
        'Picked Up',
        'In Transit',
        'Out for Delivery',
        'Delivered',
        'RTO',
        'Cancelled'
    ];
}

/* ══════════════════════════════════════════════════════════════
   GET TOTAL ACTIVE SHIPMENTS
   ══════════════════════════════════════════════════════════════ */
$total_sql = "SELECT COUNT(*) as total FROM courier WHERE status NOT IN('Delivered', 'delivered', 'Enquiry', 'cancelled', 'Cancelled')";

// CRITICAL: Only filter by office if NOT admin/manager
if (!$can_see_all_shipments && !empty($user_work_location)) {
    $escaped_office = mysqli_real_escape_string($dbConn, $user_work_location);
    $total_sql .= " AND (officename = '$escaped_office' OR officename IS NULL OR officename = '' OR officename = 'employee')";
}

$cq = mysqli_query($dbConn, $total_sql);
$cqRow = $cq ? mysqli_fetch_assoc($cq) : ['total' => 0];
$totalActive = $cqRow['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<title><?php echo htmlspecialchars($_SESSION['ge_cname']); ?> | Active Shipments</title>
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1"/>
<link rel="shortcut icon" type="image/png" href="img/favicon.png"/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">
<style>
:root{
  --navy:#1e3a8a;--navy-mid:#1e40af;--navy-lt:#3b82f6;
  --teal:#17a2b8;--teal-dk:#0e6673;--green:#059669;--green-lt:#10b981;
  --amber:#d97706;--red:#dc2626;--violet:#7c3aed;
  --bg:#f0f4f8;--surface:#fff;--border:#e2e8f0;
  --text:#1e293b;--text-2:#475569;--muted:#94a3b8;
  --r-sm:6px;--r-md:10px;--r-lg:14px;--r-xl:18px;
  --sh-sm:0 2px 8px rgba(0,0,0,.09);--sh-md:0 4px 16px rgba(0,0,0,.12);--sh-lg:0 8px 32px rgba(0,0,0,.16);
  --sans:'Plus Jakarta Sans',system-ui,sans-serif;--mono:'JetBrains Mono',monospace;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--sans);background:var(--bg);color:var(--text);font-size:16px;line-height:1.65;padding:20px 0;}
.container-fluid{max-width:1750px;margin:0 auto;padding:0 22px;}
/* PAGE HEADER */
.pg-header{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 60%,var(--navy-lt) 100%);color:#fff;padding:22px 30px;border-radius:var(--r-lg);box-shadow:0 4px 20px rgba(30,58,138,.30);margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;}
.pg-header h1{font-size:2rem;font-weight:700;margin:0;display:flex;align-items:center;gap:.75rem;}
.stat-box{text-align:center;padding:8px 22px;background:rgba(255,255,255,.18);border-radius:var(--r-md);}
.stat-box .stat-num{font-size:2.2rem;font-weight:800;color:#fff;}
.stat-box .stat-lbl{font-size:.95rem;color:rgba(255,255,255,.85);margin-top:2px;}
/* ACTION BAR */
.action-bar{background:var(--surface);padding:18px 24px;border-radius:var(--r-lg);box-shadow:var(--sh-sm);margin-bottom:18px;display:flex;justify-content:center;align-items:center;flex-wrap:wrap;gap:14px;}
/* BUTTONS */
.btn-act{border:none;padding:14px 30px;border-radius:var(--r-lg);font-weight:700;font-size:16px;color:#fff;display:inline-flex;align-items:center;gap:10px;text-decoration:none;cursor:pointer;transition:transform .15s,box-shadow .15s,filter .15s;white-space:nowrap;line-height:1.4;}
.btn-act i{font-size:17px;flex-shrink:0;}
.btn-act:hover{transform:translateY(-2px);box-shadow:var(--sh-md);filter:brightness(1.08);color:#fff;text-decoration:none;}
.btn-act:active{transform:translateY(0);}
.btn-add   {background:linear-gradient(135deg,var(--navy),var(--navy-mid));box-shadow:0 4px 15px rgba(30,58,138,.3);}
.btn-import{background:linear-gradient(135deg,var(--green-lt),var(--green));box-shadow:0 4px 15px rgba(16,185,129,.3);}
.btn-tmpl  {background:linear-gradient(135deg,var(--amber),#b45309);box-shadow:0 4px 15px rgba(217,119,6,.3);}
.btn-export{background:linear-gradient(135deg,var(--violet),#6d28d9);box-shadow:0 4px 15px rgba(124,58,237,.3);}
.btn-ghost {background:transparent;color:var(--navy);border:2px solid var(--navy);border-radius:var(--r-lg);}
.btn-ghost:hover{background:var(--navy);color:#fff;}
.btn-teal  {background:linear-gradient(135deg,var(--teal),var(--teal-dk));box-shadow:0 4px 15px rgba(23,162,184,.3);}
/* EXPORT DROPDOWN */
.dd-wrap{position:relative;}
.dd-menu{position:absolute;top:calc(100% + 8px);right:0;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-md);box-shadow:var(--sh-lg);min-width:270px;z-index:9999;display:none;overflow:hidden;}
.dd-menu.open{display:block;animation:ddIn .14s ease;}
@keyframes ddIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.dd-item{display:flex;align-items:center;gap:.75rem;padding:.95rem 1.2rem;width:100%;background:none;border:none;font-family:var(--sans);font-size:15px;font-weight:600;color:var(--text);cursor:pointer;text-align:left;transition:background .12s;}
.dd-item i{color:var(--navy-mid);font-size:16px;}
.dd-item:hover{background:#eff6ff;}
.dd-badge{margin-left:auto;background:var(--navy);color:#fff;font-size:.75rem;font-weight:700;padding:.22rem .65rem;border-radius:20px;}
/* FILTER BAR */
.filter-bar{background:var(--surface);padding:22px 24px;border-radius:var(--r-lg);box-shadow:var(--sh-sm);margin-bottom:18px;}
.filter-bar h5{color:var(--navy);font-weight:700;margin-bottom:16px;font-size:17px;display:flex;align-items:center;gap:.5rem;}
.filter-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px 16px;align-items:end;}
.fg label{font-weight:700;color:var(--text-2);font-size:12.5px;margin-bottom:5px;display:block;text-transform:uppercase;letter-spacing:.06em;}
.fg select{width:100%;padding:11px 34px 11px 14px;border:2px solid var(--border);border-radius:var(--r-sm);font-size:15px;font-family:var(--sans);font-weight:500;color:var(--text);background:#fff;transition:border-color .15s,box-shadow .15s;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%231e3a8a' d='M6 9L1 4h10z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;}
.fg input{width:100%;padding:11px 14px;border:2px solid var(--border);border-radius:var(--r-sm);font-size:15px;font-family:var(--sans);font-weight:500;color:var(--text);background:#fff;transition:border-color .15s,box-shadow .15s;}
.fg input:focus,.fg select:focus{outline:none;border-color:var(--navy-mid);box-shadow:0 0 0 3px rgba(30,64,175,.1);}
.btn-reset-wrap{display:flex;align-items:flex-end;padding-bottom:2px;}
.btn-reset{padding:11px 24px;background:var(--red);color:#fff;border:none;border-radius:var(--r-sm);font-weight:700;font-size:15px;cursor:pointer;transition:all .2s;white-space:nowrap;}
.btn-reset:hover{background:#b91c1c;transform:translateY(-2px);}
/* LOADER */
#initial-loader{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999;text-align:center;background:rgba(255,255,255,.97);padding:38px 52px;border-radius:var(--r-xl);box-shadow:var(--sh-lg);}
.spinner{border:5px solid #e2e8f0;border-top:5px solid var(--navy);border-radius:50%;width:62px;height:62px;animation:spin 1s linear infinite;margin:0 auto 18px;}
@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
/* TABLE */
.table-container{background:var(--surface);padding:20px;border-radius:var(--r-lg);box-shadow:var(--sh-sm);opacity:0;transition:opacity .4s ease;overflow:visible;}
table.dataTable{border-collapse:collapse!important;border-spacing:0!important;width:100%!important;table-layout:auto!important;margin:0!important;border:1px solid #c7d4e8!important;}
table.dataTable thead th{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 100%)!important;color:#fff!important;font-weight:800!important;font-size:14px!important;text-transform:uppercase!important;letter-spacing:.08em!important;padding:16px 18px!important;border-right:1px solid rgba(255,255,255,.2)!important;border-bottom:3px solid var(--navy)!important;white-space:nowrap!important;vertical-align:middle!important;box-sizing:border-box!important;text-align:left!important;}
table.dataTable thead th:last-child{border-right:none!important;}
table.dataTable tbody td{border-right:1px solid #c7d4e8!important;border-bottom:1px solid #c7d4e8!important;padding:18px 20px!important;vertical-align:middle!important;font-weight:700!important;color:var(--text)!important;white-space:nowrap!important;font-size:16px!important;text-align:left!important;box-sizing:border-box!important;line-height:1.6!important;}
table.dataTable tbody td:last-child{border-right:none!important;}
table.dataTable tbody td.wrap-text{white-space:normal!important;word-wrap:break-word!important;}
table.dataTable tbody tr:nth-child(odd)  td{background:#ffffff!important;}
table.dataTable tbody tr:nth-child(even) td{background:#f8faff!important;}
table.dataTable tbody tr:hover           td{background:#eff6ff!important;}
table.dataTable tbody tr.row-sel         td{background:#dbeafe!important;}
table.dataTable tbody tr td:first-child{border-left:none!important;font-weight:800!important;background:#f0f5ff!important;}
table.dataTable tbody tr:hover   td:first-child{background:#dbeafe!important;}
table.dataTable tbody tr.row-sel td:first-child{background:#bfdbfe!important;}
.dataTables_scrollHead{overflow:visible!important;}
.dataTables_scrollHead table{table-layout:auto!important;width:100%!important;}
.dataTables_scrollBody{overflow-y:auto!important;overflow-x:auto!important;}
.dataTables_scrollBody table{table-layout:auto!important;width:100%!important;}
.dataTables_scrollBody thead{visibility:collapse!important;height:0!important;}
.dataTables_scrollBody thead tr{height:0!important;border:none!important;}
.dataTables_scrollBody thead th{height:0!important;padding:0!important;border:none!important;line-height:0!important;}
.dataTables_scrollBody{overflow-x:auto!important;}
.dataTables_scrollHead{overflow:hidden!important;}
.dataTables_scrollBody::-webkit-scrollbar{width:11px;height:11px;}
.dataTables_scrollBody::-webkit-scrollbar-track{background:#f1f5f9;border-radius:8px;}
.dataTables_scrollBody::-webkit-scrollbar-thumb{background:linear-gradient(135deg,var(--navy),var(--navy-mid));border-radius:8px;border:2px solid #f1f5f9;}
.dataTables_scrollBody::-webkit-scrollbar-thumb:hover{background:var(--navy-lt);}
/* Fixed Columns - Checkbox, Actions, Tracking sticky on left */
table.dataTable.DTFC_Cloned thead th:nth-child(1),
table.dataTable.DTFC_Cloned thead th:nth-child(2),
table.dataTable.DTFC_Cloned thead th:nth-child(3),
table.dataTable.DTFC_Cloned tbody td:nth-child(1),
table.dataTable.DTFC_Cloned tbody td:nth-child(2),
table.dataTable.DTFC_Cloned tbody td:nth-child(3){
  background:#fff!important;
  box-shadow:2px 0 8px rgba(0,0,0,.08)!important;
}
table.dataTable.DTFC_Cloned thead th:nth-child(1),
table.dataTable.DTFC_Cloned thead th:nth-child(2),
table.dataTable.DTFC_Cloned thead th:nth-child(3){
  background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 100%)!important;
}
.DTFC_LeftWrapper{
  box-shadow:2px 0 8px rgba(0,0,0,.08)!important;
}

/* Checkbox column - keep small */
table.dataTable thead th:nth-child(1),
table.dataTable tbody td:nth-child(1) {
  min-width: 50px!important;
  max-width: 50px!important;
  width: 50px!important;
  text-align: center!important;
}

/* Actions column - reduce width */
table.dataTable thead th:nth-child(2),
table.dataTable tbody td:nth-child(2) {
  min-width: 420px!important;
  max-width: 420px!important;
  width: 420px!important;
}
.dataTables_wrapper .top-controls{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;padding:14px 16px;background:#f8fafc;border-radius:var(--r-md);border:2px solid var(--border);}
.dataTables_wrapper .bottom-controls{margin-top:22px;padding:18px 20px;background:#f8fafc;border-radius:var(--r-md);border:2px solid var(--border);}
.dataTables_wrapper .dataTables_length{padding:0!important;}
.dataTables_wrapper .dataTables_length label{display:flex!important;align-items:center!important;gap:10px!important;margin:0!important;font-weight:700!important;color:var(--text-2)!important;font-size:16px!important;}
.dataTables_wrapper .dataTables_length select{padding:10px 32px 10px 14px!important;border:2px solid var(--border)!important;border-radius:var(--r-sm)!important;font-weight:700!important;color:var(--navy)!important;font-size:15px!important;cursor:pointer!important;background:#fff!important;appearance:none!important;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%231e3a8a' d='M6 9L1 4h10z'/%3E%3C/svg%3E")!important;background-repeat:no-repeat!important;background-position:right 10px center!important;min-width:90px!important;}
.dataTables_wrapper .dataTables_info{padding:0!important;color:var(--navy)!important;font-weight:700!important;font-size:15px!important;}
.dataTables_wrapper .dataTables_filter{display:none!important;}
.dataTables_wrapper .dataTables_paginate{text-align:center!important;padding:0!important;}
.dataTables_wrapper .dataTables_paginate .paginate_button{padding:12px 20px!important;margin:0 5px!important;border-radius:var(--r-md)!important;border:2px solid var(--border)!important;background:#fff!important;color:var(--navy)!important;font-weight:700!important;font-size:15px!important;cursor:pointer!important;transition:all .2s!important;display:inline-block!important;}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover{background:var(--navy-mid)!important;color:#fff!important;border-color:var(--navy-mid)!important;transform:translateY(-3px)!important;box-shadow:0 6px 18px rgba(30,64,175,.35)!important;}
.dataTables_wrapper .dataTables_paginate .paginate_button.current{background:linear-gradient(135deg,var(--navy),var(--navy-mid))!important;color:#fff!important;border-color:var(--navy)!important;box-shadow:0 4px 14px rgba(30,58,138,.4)!important;transform:scale(1.08)!important;}
.dataTables_wrapper .dataTables_paginate .paginate_button.current:hover{transform:scale(1.08)!important;}
.dataTables_wrapper .dataTables_paginate .paginate_button.disabled{opacity:.45!important;cursor:not-allowed!important;}
/* SPECIAL CELLS */
.t-chip{font-family:var(--mono);font-size:1.35rem;font-weight:900;color:#000000;text-decoration:none;background:#dbeafe;padding:.55rem 1.1rem;border-radius:6px;border:2px solid #93c5fd;display:inline-block;line-height:1.3;}
.t-chip:hover{background:var(--navy);color:#fff;text-decoration:none;border-color:var(--navy);}
.s-badge{display:inline-block;padding:.5rem 1.2rem;border-radius:22px;font-size:.95rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#fff;box-shadow:0 2px 8px rgba(0,0,0,.2);}
.s-badge-lg{padding:.85rem 1.8rem;font-size:1.35rem;font-weight:900;letter-spacing:.1em;border-radius:28px;box-shadow:0 4px 14px rgba(0,0,0,.28);min-width:220px;text-align:center;white-space:nowrap;line-height:1.4;}
.amt{font-weight:900!important;color:#000!important;font-size:1.15rem!important;}
.inv-val{font-family:var(--mono);font-size:1.35rem;color:#059669;font-weight:900!important;background:#d1fae5;padding:.55rem 1.1rem;border-radius:6px;border:2px solid #6ee7b7;display:inline-block;line-height:1.3;}
.inv-val:hover{background:#059669;color:#fff;border-color:#059669;}
.inv-num{font-family:var(--mono);font-size:1.4rem;color:#1e3a8a;font-weight:900!important;letter-spacing:0.02em;line-height:1.3;}
input[type="checkbox"]{width:17px;height:17px;accent-color:var(--navy);cursor:pointer;}
/* ACTION ICONS 50px */
.act-row{display:flex;gap:.55rem;align-items:center;flex-wrap:nowrap;}
.act-icon{display:inline-flex;align-items:center;justify-content:center;width:50px;height:50px;border-radius:var(--r-md);border:none;cursor:pointer;text-decoration:none;font-size:20px;transition:all .18s;flex-shrink:0;}
.act-icon:hover{transform:scale(1.15);text-decoration:none;box-shadow:var(--sh-md);}
.ai-update{background:#dbeafe;color:#1e40af;}.ai-update:hover{background:#1e40af;color:#fff;}
.ai-edit  {background:#e0e7ff;color:#7c3aed;}.ai-edit:hover  {background:#7c3aed;color:#fff;}
.ai-invoice{background:#fef3c7;color:#d97706;}.ai-invoice:hover{background:#d97706;color:#fff;}
.ai-print {background:#dcfce7;color:#059669;}.ai-print:hover {background:#059669;color:#fff;}
.ai-label {background:#fef9c3;color:#a16207;}.ai-label:hover {background:#a16207;color:#fff;}
.ai-del   {background:#fee2e2;color:#dc2626;}.ai-del:hover   {background:#dc2626;color:#fff;}
.ai-call  {color:#fff;}.ai-call:hover{transform:scale(1.15);box-shadow:var(--sh-md);}
.ai-whatsapp{color:#fff;}.ai-whatsapp:hover{transform:scale(1.15);box-shadow:var(--sh-md);}

/* FIXED COLUMNS - Make #, Checkbox, and Actions sticky on the left */
.dtfc-fixed-left {
  background: linear-gradient(90deg, rgba(248,250,252,1), rgba(248,250,252,0.95)) !important;
  box-shadow: 4px 0 12px rgba(0,0,0,0.08) !important;
  z-index: 5 !important;
}
.dtfc-fixed-left th,
.dtfc-fixed-left td {
  background: #f8fafc !important;
  border-right: 2px solid #e2e8f0 !important;
}

#toggleSort{background:rgba(255,255,255,.22);border:1px solid rgba(255,255,255,.38);color:#fff;border-radius:4px;padding:2px 8px;font-size:.78rem;cursor:pointer;margin-left:5px;transition:background .15s;}
#toggleSort:hover{background:rgba(255,255,255,.38);}
/* FLASH */
.flash{display:flex;align-items:center;gap:.75rem;padding:.95rem 1.4rem;border-radius:var(--r-md);font-size:16px;margin-bottom:16px;border-left:4px solid transparent;font-weight:600;position:relative;z-index:10000;}
.flash-success{background:#dcfce7;color:#14532d;border-color:#059669;}
.flash-danger {background:#fee2e2;color:#7f1d1d;border-color:#dc2626;}
.flash-warning{background:#fef9c3;color:#78350f;border-color:#d97706;}
.flash-info   {background:#dbeafe;color:#1e3a8a;border-color:#1e3a8a;}
.flash-x{margin-left:auto;background:none;border:none;font-size:1.25rem;cursor:pointer;opacity:.55;}
.flash-x:hover{opacity:1;}
/* MODALS */
.modal-content{border:none;border-radius:var(--r-xl);box-shadow:var(--sh-lg);font-family:var(--sans);overflow:hidden;}
.modal-header{background:linear-gradient(135deg,var(--navy),var(--navy-mid));color:#fff;padding:18px 26px;border:none;}
.modal-header h4{font-size:1.2rem;font-weight:700;margin:0;display:flex;align-items:center;gap:.55rem;}
.modal-header .close{color:#fff;opacity:.8;font-size:1.6rem;background:none;border:none;cursor:pointer;}
.modal-header .close:hover{opacity:1;}
.modal-body{padding:26px;}
.modal-footer{padding:14px 26px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:.65rem;}
.form-group{margin-bottom:18px;}
.form-group label{display:block;font-size:15px;font-weight:700;margin-bottom:.4rem;color:var(--text);}
.form-group .form-control{font-size:16px;padding:12px 16px;border:2px solid var(--border);border-radius:var(--r-sm);width:100%;font-family:var(--sans);transition:border-color .15s,box-shadow .15s;}
.form-group .form-control:focus{border-color:#1e40af;box-shadow:0 0 0 3px rgba(30,64,175,.1);outline:none;}
.req{color:#dc2626;}
/* UPLOAD */
.upload-zone{border:3px dashed #c7d2e8;border-radius:var(--r-md);padding:2.5rem 2rem;text-align:center;background:#f8faff;cursor:pointer;transition:all .2s;}
.upload-zone:hover{border-color:var(--navy);background:#eff6ff;}
.upload-zone .uz-icon{font-size:2.8rem;color:#1e40af;display:block;margin-bottom:.65rem;}
.upload-zone h5{font-size:1.05rem;font-weight:700;color:var(--navy);margin-bottom:.3rem;}
.upload-zone p{font-size:.88rem;color:var(--muted);}
.file-preview{display:none;margin-top:.9rem;padding:.9rem 1.15rem;background:#dcfce7;border:1px solid #bbf7d0;border-radius:var(--r-sm);font-size:15px;align-items:center;gap:.75rem;}
.file-preview.vis{display:flex;}
.fpi{color:#059669;font-size:1.1rem;}
/* CHART */
.chart-card{background:var(--surface);padding:18px 22px;border-radius:var(--r-lg);box-shadow:var(--sh-sm);margin-top:18px;}
@media(max-width:900px){.filter-grid{grid-template-columns:repeat(2,1fr);}.action-bar,.pg-header{flex-direction:column;}.pg-header h1{font-size:1.5rem;}}
@media(max-width:600px){.filter-grid{grid-template-columns:1fr;}}

/* SLIDE-IN PANEL FOR ADD COURIER */
.courier-overlay{
    position:fixed;inset:0;
    background:rgba(10,18,40,.55);
    backdrop-filter:blur(3px);
    z-index:2000;
    opacity:0;pointer-events:none;
    transition:opacity .3s;
}
.courier-overlay.open{opacity:1;pointer-events:all;}

.courier-panel{
    position:fixed;top:0;right:0;
    height:100vh;width:95vw;max-width:1200px;
    background:#fff;
    z-index:2001;
    transform:translateX(100%);
    transition:transform .38s cubic-bezier(.4,0,.2,1);
    display:flex;flex-direction:column;
    box-shadow:-12px 0 60px rgba(0,0,0,.25);
}
.courier-panel.open{transform:translateX(0);}

.courier-header{
    background:linear-gradient(135deg,#1e3a8a,#2563eb);
    color:#fff;padding:18px 24px;
    display:flex;align-items:center;justify-content:space-between;
    flex-shrink:0;
}
.courier-header h3{margin:0;font-size:1.2rem;font-weight:800;display:flex;align-items:center;gap:9px;}
.courier-close{
    background:rgba(255,255,255,.18);border:none;color:#fff;
    width:36px;height:36px;border-radius:50%;font-size:1.2rem;
    cursor:pointer;display:flex;align-items:center;justify-content:center;
    transition:background .2s;
}
.courier-close:hover{background:rgba(255,255,255,.35);}

.courier-iframe{
    flex:1;border:none;width:100%;height:100%;
}


/* ADD COURIER SLIDE-IN PANEL */
.adw-overlay{
    position:fixed;inset:0;
    background:rgba(10,18,40,.55);
    backdrop-filter:blur(3px);
    z-index:2000;
    opacity:0;pointer-events:none;
    transition:opacity .3s;
}
.adw-overlay.open{opacity:1;pointer-events:all;}

.adw-panel{
    position:fixed;top:0;right:0;
    height:100vh;width:900px;max-width:95vw;
    background:#fff;
    z-index:2001;
    transform:translateX(100%);
    transition:transform .38s cubic-bezier(.4,0,.2,1);
    display:flex;flex-direction:column;
    box-shadow:-12px 0 60px rgba(0,0,0,.25);
}
.adw-panel.open{transform:translateX(0);}

.adw-hd{
    background:linear-gradient(135deg,#1e3a8a,#2563eb);
    color:#fff;padding:18px 24px;
    display:flex;align-items:center;justify-content:space-between;flex-shrink:0;
}
.adw-hd h3{margin:0;font-size:1.2rem;font-weight:800;display:flex;align-items:center;gap:9px;}
.adw-hd-r{display:flex;align-items:center;gap:10px;}
.adw-dots{display:flex;gap:6px;align-items:center;}
.adw-dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.3);transition:all .2s;}
.adw-dot.on{background:#fff;transform:scale(1.4);}
.adw-xbtn{background:rgba(255,255,255,.18);border:none;color:#fff;width:36px;height:36px;
    border-radius:50%;font-size:1.2rem;cursor:pointer;
    display:flex;align-items:center;justify-content:center;transition:background .2s;}
.adw-xbtn:hover{background:rgba(255,255,255,.35);}

.adw-tabs{
    display:flex;background:#f1f5f9;
    border-bottom:2px solid #e2e8f0;flex-shrink:0;overflow-x:auto;
    scrollbar-width:none;
}
.adw-tabs::-webkit-scrollbar{display:none;}
.adw-tab{
    flex:1;min-width:95px;padding:12px 5px;
    text-align:center;font-weight:700;font-size:11.5px;
    color:#64748b;cursor:pointer;border:none;background:none;
    border-bottom:3px solid transparent;transition:all .2s;
    white-space:nowrap;
    display:flex;align-items:center;justify-content:center;gap:4px;
}
.adw-tab.on{color:#1e3a8a;border-bottom-color:#1e3a8a;background:#fff;}
.adw-tab:hover:not(.on){color:#1e3a8a;background:#e8f0fe;}

.adw-body{flex:1;overflow-y:auto;overflow-x:hidden;padding:22px 26px;scroll-behavior:smooth;max-height:calc(100vh - 200px);}
.adw-body::-webkit-scrollbar{width:8px;}
.adw-body::-webkit-scrollbar-track{background:#f1f5f9;}
.adw-body::-webkit-scrollbar-thumb{background:#1e40af;border-radius:4px;}
.adw-body::-webkit-scrollbar-thumb:hover{background:#1e3a8a;}

.adw-pane{display:none;}
.adw-pane.on{display:block;}

.adw-sec{
    font-weight:800;font-size:.8rem;color:#1e3a8a;
    text-transform:uppercase;letter-spacing:.07em;
    margin:0 0 15px;padding-bottom:8px;
    border-bottom:3px solid #1e3a8a;
    display:flex;align-items:center;gap:7px;
}

.adw-f{margin-bottom:13px;}
.adw-f label{
    display:block;font-weight:700;font-size:11px;
    color:#475569;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;
}
.adw-f .req{color:#dc2626;}
.adw-f input,.adw-f select,.adw-f textarea{
    width:100%;padding:10px 13px;
    border:2px solid #e2e8f0;border-radius:7px;
    font-size:14px;font-family:inherit;font-weight:500;color:#1e293b;background:#fff;
    transition:border-color .15s,box-shadow .15s;
}
.adw-f select{
    appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 12 12'%3E%3Cpath fill='%231e3a8a' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 12px center;padding-right:36px;
}
.adw-f textarea{min-height:70px;resize:vertical;}
.adw-f input:focus,.adw-f select:focus,.adw-f textarea:focus{
    outline:none;border-color:#1e40af;box-shadow:0 0 0 3px rgba(30,64,175,.1);}
.adw-f .ro{background:#f1f5f9;color:#64748b;cursor:not-allowed;}
.adw-f .hint{font-size:11px;color:#94a3b8;margin-top:3px;display:block;}

.adw-r2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.adw-r3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}
@media(max-width:580px){.adw-r2,.adw-r3{grid-template-columns:1fr;}}

.adw-pin{position:relative;}
.adw-pin input{padding-right:46px;}
.adw-pinbtn{
    position:absolute;right:0;top:0;bottom:0;
    background:linear-gradient(135deg,#1e3a8a,#1e40af);
    color:#fff;border:none;border-radius:0 7px 7px 0;
    padding:0 13px;cursor:pointer;font-size:13px;transition:opacity .2s;
}
.adw-pinbtn:hover{opacity:.85;}

.adw-loc{
    border:2px solid #e2e8f0;border-radius:7px;padding:12px 15px;
    margin-top:7px;min-height:50px;
    display:flex;align-items:center;gap:10px;
    font-size:14px;font-weight:600;background:#f8fafc;transition:all .2s;
    line-height:1.5;
}
.adw-loc.ok {border-color:#059669;background:#f0fdf4;color:#065f46;}
.adw-loc.err{border-color:#dc2626;background:#fef2f2;color:#7f1d1d;}
.adw-loc.ldg{border-color:#d97706;background:#fffbeb;color:#78350f;}

.adw-ft{
    padding:13px 24px;background:#f8fafc;
    border-top:2px solid #e2e8f0;
    display:flex;justify-content:space-between;align-items:center;flex-shrink:0;
}
.adw-ft-nav{display:flex;gap:8px;align-items:center;}
.adw-prevbtn,.adw-nextbtn{
    padding:10px 20px;border-radius:8px;font-weight:700;font-size:13px;
    cursor:pointer;border:2px solid #e2e8f0;background:#fff;color:#475569;
    transition:all .2s;display:flex;align-items:center;gap:6px;
}
.adw-prevbtn:hover,.adw-nextbtn:hover{border-color:#1e3a8a;color:#1e3a8a;}
.adw-nextbtn.go{
    background:linear-gradient(135deg,#1e3a8a,#1e40af);
    color:#fff;border-color:transparent;
}
.adw-nextbtn.go:hover{opacity:.9;color:#fff;}
.adw-savebtn{
    background:linear-gradient(135deg,#10b981,#059669);
    color:#fff;border:none;
    padding:11px 30px;border-radius:8px;
    font-weight:800;font-size:14.5px;
    cursor:pointer;display:flex;align-items:center;gap:8px;
    box-shadow:0 4px 14px rgba(5,150,105,.3);transition:all .2s;
}
.adw-savebtn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(5,150,105,.4);}

</style>
</head>
<body>

<div class="container-fluid">
<?php if(!empty($msg)) echo $msg; ?>

<div class="pg-header">
  <h1><i class="fas fa-plane"></i> Active Shipments</h1>
  <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
    <div class="stat-box">
      <div class="stat-num"><?php echo $totalActive; ?></div>
      <div class="stat-lbl">Active Shipments</div>
    </div>
    <?php if (!$can_see_all_shipments && !empty($user_work_location)): ?>
    <div class="stat-box" style="background:rgba(255,255,255,.12);">
      <div class="stat-lbl" style="font-size:.75rem;margin-bottom:3px;">Filtered by Office</div>
      <div style="font-size:1rem;font-weight:700;color:#fff;"><?php echo htmlspecialchars($user_work_location); ?></div>
    </div>
    <?php elseif ($can_see_all_shipments): ?>
    <div class="stat-box" style="background:rgba(16,185,129,.25);">
      <div style="font-size:.85rem;font-weight:700;color:#fff;"><i class="fa fa-check-circle"></i> Viewing All Offices</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="action-bar">
  <a href="<?php echo $isAdmin ? 'index.php' : 'raise-a-ticket.php'; ?>" class="btn-act" style="background: linear-gradient(135deg, #64748b 0%, #475569 100%); box-shadow: 0 4px 15px rgba(100, 116, 139, 0.3);">
    <i class="fa fa-arrow-left"></i> Back to Dashboard
  </a>
  <button type="button" class="btn-act btn-add" onclick="openAddCourierPanel()">
    <i class="fa fa-user-plus"></i> Add New Shipment
  </button>
  <button type="button" class="btn-act btn-import" data-toggle="modal" data-target="#importModal"><i class="fa fa-upload"></i> Import</button>
  <div class="dd-wrap">
    <button type="button" class="btn-act btn-export" id="exportDropdownBtn">
      <i class="fa fa-file-excel-o"></i> Export <i class="fa fa-caret-down" style="font-size:.82rem;margin-left:3px;"></i>
    </button>
    <div class="dd-menu" id="exportDropdown">
      <button type="button" class="dd-item" id="exportAllBtn"><i class="fa fa-download"></i> Export All Records</button>
      <button type="button" class="dd-item" id="exportSelectedBtn">
        <i class="fa fa-check-square-o"></i> Export Selected
        <span class="dd-badge" id="selectedCount">0</span>
      </button>
    </div>
  </div>
  <?php if($isAdmin): ?>
  <button type="button" class="btn-act" id="bulkDelete" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3); display:none;" onclick="bulkDeleteShipments()">
    <i class="fa fa-trash"></i> Delete Selected <span class="dd-badge" id="deleteCount">0</span>
  </button>
  <?php endif; ?>
</div>

<!-- ALL FILTERS -->
<div class="filter-bar">
  <h5><i class="fas fa-filter"></i> Filter Shipments</h5>
  <div class="filter-grid">

    <div class="fg" style="grid-column:span 2;">
      <label>&#128269; Global Search</label>
      <input type="text" id="globalSearch" placeholder="Search tracking, name, city, reference&hellip;">
    </div>

    <div class="fg">
      <label>Status</label>
      <select id="statusFilter">
        <option value="">All Statuses</option>
        <?php foreach($status_options as $s): ?>
        <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="fg">
      <label>Shipping Method</label>
      <select id="methodFilter">
        <option value="">All Methods</option>
        <?php foreach($shippingMethods as $m): ?>
        <option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="fg">
      <label>Shipment Type</label>
      <select id="typeFilter">
        <option value="">All Types</option>
        <option value="Domestic">Domestic</option>
        <option value="International">International</option>
      </select>
    </div>

    <div class="fg">
      <label>Aggregator</label>
      <select id="aggregatorFilter">
        <option value="">All Aggregators</option>
        <?php foreach($aggregators as $a): ?>
        <option value="<?php echo htmlspecialchars($a); ?>"><?php echo htmlspecialchars($a); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="fg">
      <label>Payment Mode</label>
      <select id="paymodeFilter">
        <option value="">All Payment Modes</option>
        <option value="Paid">Paid</option>
        <option value="Unpaid">Unpaid</option>
        <option value="">──────────</option>
        <?php foreach($payModes as $p): ?>
        <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="fg">
      <label>Origin City</label>
      <select id="originCityFilter">
        <option value="">All Origin Cities</option>
        <?php foreach($originCities as $c): ?>
        <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="fg">
      <label>Origin State</label>
      <select id="originStateFilter">
        <option value="">All Origin States</option>
        <?php foreach($originStates as $s): ?>
        <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="fg">
      <label>Destination City</label>
      <select id="destCityFilter">
        <option value="">All Dest. Cities</option>
        <?php foreach($destCities as $c): ?>
        <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="fg">
      <label>Destination State</label>
      <select id="destStateFilter">
        <option value="">All Dest. States</option>
        <?php foreach($destStates as $s): ?>
        <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="fg">
      <label>Sender Country</label>
      <select id="senderCountryFilter">
        <option value="">All Sender Countries</option>
        <?php foreach($senderCountries as $c): ?>
        <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="fg">
      <label>Receiver Country</label>
      <select id="receiverCountryFilter">
        <option value="">All Receiver Countries</option>
        <?php foreach($receiverCountries as $c): ?>
        <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="btn-reset-wrap">
      <button class="btn-reset" onclick="resetFilters()"><i class="fa fa-undo"></i> Reset All</button>
    </div>
  </div>
</div>

<div id="initial-loader">
  <div class="spinner"></div>
  <p style="font-weight:700;color:#1e3a8a;font-size:17px;margin:0;">Loading Active Shipments&hellip;</p>
</div>

<!-- TABLE (Now 42 columns - invoice_value added back) -->
<form method="post" id="shipmentsForm">
<?php
// PRE-CHECK: Count shipments before rendering table
$has_shipments_to_display = false;
if ($dbConn && !mysqli_connect_errno()) {
    // Build the WHERE clause for office filtering
    $office_filter = "";
    
    // CRITICAL: Only filter if user is NOT admin/manager
    if (!$can_see_all_shipments && !empty($user_work_location)) {
        $escaped_office = mysqli_real_escape_string($dbConn, $user_work_location);
        // Match exact office name OR NULL/empty office names
        $office_filter = " AND (c.officename = '$escaped_office' OR c.officename IS NULL OR c.officename = '' OR c.officename = 'employee')";
        error_log("Active Shipments - PRE-CHECK with office filter: " . $office_filter);
    } else {
        error_log("Active Shipments - PRE-CHECK without office filter (admin/manager or no office set)");
    }
    
    $precheck_sql = "SELECT COUNT(*) as total FROM courier c 
                     WHERE c.status NOT IN('Delivered','delivered','Enquiry','cancelled','Cancelled')
                     $office_filter";
    
    error_log("Active Shipments - PRE-CHECK SQL: " . $precheck_sql);
    
    $precheck_result = mysqli_query($dbConn, $precheck_sql);
    if ($precheck_result) {
        $precheck_row = mysqli_fetch_assoc($precheck_result);
        $has_shipments_to_display = ($precheck_row['total'] > 0);
        error_log("Active Shipments - PRE-CHECK found: " . $precheck_row['total'] . " shipments");
    } else {
        error_log("Active Shipments - PRE-CHECK query failed: " . mysqli_error($dbConn));
    }
}

// If NO shipments, show clean empty state OUTSIDE table
if (!$has_shipments_to_display):
?>
<div style="background:#fff;padding:80px 40px;border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.09);text-align:center;margin:40px auto;max-width:650px;">
    <i class="fa fa-inbox" style="font-size:80px;color:#cbd5e1;display:block;margin-bottom:25px;"></i>
    <h2 style="color:#1e3a8a;font-weight:800;font-size:28px;margin-bottom:15px;font-family:var(--sans);">No Active Shipments Found</h2>
    <p style="color:#64748b;font-size:17px;margin-bottom:30px;line-height:1.7;font-family:var(--sans);">You don't have any active shipments at the moment.<br>Get started by creating your first shipment!</p>
    <button type="button" class="btn-act btn-add" onclick="openAddCourierPanel()" style="display:inline-flex;font-size:18px;padding:16px 35px;">
        <i class="fa fa-plus-circle"></i> Create New Shipment
    </button>
</div>
<script>
// Hide loader immediately for empty state
$(document).ready(function(){
    $('#initial-loader').hide();
});
</script>
<?php
else:
// HAS shipments - render the full table
?>
<div class="table-container" id="tableContainer">
<table id="grid" class="display nowrap" style="width:100%">
<thead>
<tr>
  <th><input type="checkbox" id="checkAll" title="Select All"></th>
  <th>Actions</th>
  <th>Tracking No. <button type="button" id="toggleSort"><span id="sortIcon">&#9660;</span></button></th>
  <th>Source</th>
  <th>Status</th>
  <th>Reference No.</th>
  <th>Origin City</th>
  <th>Destination City</th>
  <th>Amount</th>
  <th>Shipping Method</th>
  <th>Invoice Number</th>
  <th>Invoice Value</th>
  <th>Aggregator</th>
  <th>Office Location</th>
  <th>Sender Name</th>
  <th>Sender Phone</th>
  <th>Sender Email</th>
  <th>Sender Address</th>
  <th>Sender Country</th>
  <th>Sender Pincode</th>
  <th>Sender Locality</th>
  <th>Sender State</th>
  <th>Receiver Name</th>
  <th>Receiver Phone</th>
  <th>Receiver Alt Phone</th>
  <th>Receiver Email</th>
  <th>Receiver Address</th>
  <th>Receiver Country</th>
  <th>Receiver Pincode</th>
  <th>Receiver Locality</th>
  <th>Receiver State</th>
  <th>Packages</th>
  <th>Payment Mode</th>
  <th>Product Type</th>
  <th>Service Mode</th>
  <th>Shipment Type</th>
  <th>Actual Weight (kg)</th>
  <th>Height (cm)</th>
  <th>Width (cm)</th>
  <th>Length (cm)</th>
  <th>Vol. Weight (kg)</th>
  <th>Description</th>
  <th>Pickup Date</th>
  <th>Est. Delivery</th>
</tr>
</thead>
<tbody>
<?php
// Debug: Check database connection
if (!$dbConn || mysqli_connect_errno()) {
    echo "<tr><td colspan='44' style='padding:60px;text-align:center;'>";
    echo "<div style='color:#dc2626;font-size:18px;font-weight:700;margin-bottom:10px;'><i class='fa fa-exclamation-triangle' style='font-size:48px;display:block;margin-bottom:15px;'></i>Database Connection Error</div>";
    echo "<p style='color:#64748b;font-size:14px;'>" . htmlspecialchars(mysqli_connect_error()) . "</p>";
    echo "</td></tr>";
    echo "<!-- DB Connection Failed -->";
} else {
    echo "<!-- DB Connection OK -->";
}

// Check if invoice_value column exists (new field), fallback to invoice_id (old field)
$check_col_new = mysqli_query($dbConn, "SHOW COLUMNS FROM courier LIKE 'invoice_value'");
$has_invoice_value = mysqli_num_rows($check_col_new) > 0;

$check_col_old = mysqli_query($dbConn, "SHOW COLUMNS FROM courier LIKE 'invoice_id'");
$has_invoice_id = mysqli_num_rows($check_col_old) > 0;

// Select BOTH columns for fallback logic
$invoice_select = "";
if ($has_invoice_value) {
    $invoice_select .= "c.invoice_value";
}
if ($has_invoice_id) {
    if ($invoice_select) $invoice_select .= ", ";
    $invoice_select .= "c.invoice_id";
}
if (!$invoice_select) {
    $invoice_select = "0 AS invoice_value, 0 AS invoice_id";
}

echo "<!-- Invoice fields: " . ($has_invoice_value ? 'invoice_value' : '') . ($has_invoice_value && $has_invoice_id ? '+' : '') . ($has_invoice_id ? 'invoice_id' : '') . " -->";

$sql = "SELECT c.cid, c.tracking,
          $invoice_select, c.invoice_no,
          c.reference_no, c.delivery_aggregator,
          c.ship_name, c.phone, c.correo, c.s_add, c.cc AS sender_country,
          c.zipcode AS sender_pincode, c.sender_locality, c.ciudad AS sender_city, c.state AS sender_state,
          c.rev_name, c.r_phone, c.telefono1 AS receiver_alt_phone, c.email, c.r_add, c.paisdestino AS receiver_country,
          c.zipcode1 AS receiver_pincode, c.receiver_locality, c.city1 AS receiver_city, c.state1 AS receiver_state,
          c.qty, c.shipping_subtotal, c.paymode, c.type AS product_type, c.mode AS service_mode,
          c.shipping_method, c.dom_internation, c.pesoreal,
          c.altura, c.ancho, c.longitud, c.totalpeso,
          c.status, c.detailed_status, c.comments,
          c.pick_date, c.schedule, c.officename, c.delivery_type,
          c.booking_source,
          s.color
   FROM courier c
   LEFT JOIN service_mode s ON s.servicemode = c.status
   WHERE c.status NOT IN('Delivered','delivered','Enquiry','cancelled','Cancelled')";

// CRITICAL: Only add office filter if user is NOT admin/manager
if (!$can_see_all_shipments && !empty($user_work_location)) {
    $escaped_office = mysqli_real_escape_string($dbConn, $user_work_location);
    // Match exact office name OR NULL/empty office names
    $sql .= " AND (c.officename = '$escaped_office' OR c.officename IS NULL OR c.officename = '' OR c.officename = 'employee')";
    error_log("Active Shipments - MAIN QUERY with office filter for: " . $user_work_location);
} else {
    error_log("Active Shipments - MAIN QUERY without office filter (admin/manager sees ALL)");
}

$sql .= " ORDER BY c.cid DESC, c.book_date DESC";

error_log("Active Shipments - MAIN SQL: " . $sql);

echo "<!-- SQL Query prepared -->";

$result3 = mysqli_query($dbConn, $sql);

if (!$result3) {
    $error_msg = mysqli_error($dbConn);
    echo "<!-- SQL Error: " . htmlspecialchars($error_msg) . " -->";
    echo "<tr><td colspan='43' style='padding:60px;text-align:center;'>";
    echo "<div style='color:#dc2626;font-size:18px;font-weight:700;margin-bottom:10px;'><i class='fa fa-exclamation-triangle' style='font-size:48px;display:block;margin-bottom:15px;'></i>Database Query Error</div>";
    echo "<p style='color:#64748b;font-size:14px;'>" . htmlspecialchars($error_msg) . "</p>";
    echo "</td></tr>";
} else {
    $row_count = mysqli_num_rows($result3);
    echo "<!-- Query successful, rows found: $row_count -->";
    
    // Since we pre-checked, we know there are rows, so just output them
    $output_count = 0;
    $row_index = 1; // Start index from 1
    while($row=mysqli_fetch_array($result3)):
      $output_count++;
      $sc=!empty($row['color'])?$row['color']:'1e40af';
      $curr=htmlspecialchars($_SESSION['ge_curr']);
      
      // Calculate text color based on background brightness for better contrast
      $bgColor = $sc;
      // Remove # if present
      $bgColor = ltrim($bgColor, '#');
      // Convert to RGB
      $r = hexdec(substr($bgColor, 0, 2));
      $g = hexdec(substr($bgColor, 2, 2));
      $b = hexdec(substr($bgColor, 4, 2));
      // Calculate perceived brightness (0-255)
      $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
      // Use white text for dark backgrounds, black for light backgrounds
      $textColor = ($brightness > 155) ? '#000000' : '#ffffff';
      
      // Check if this is an appointment delivery for red highlighting
      // Handle case where delivery_type column might not exist yet
      $isAppointmentDelivery = (isset($row['delivery_type']) && trim($row['delivery_type']) === 'Appointment Delivery');
      $rowStyle = $isAppointmentDelivery ? 'background-color: #fee2e2; border-left: 5px solid #dc2626;' : '';
?>
<tr id="row-<?php echo htmlspecialchars($row['tracking']); ?>" style="<?php echo $rowStyle; ?>">
  <td><input type="checkbox" name="selected_tracking[]" class="track-check" value="<?php echo htmlspecialchars($row['tracking']); ?>"></td>
  <td style="white-space:nowrap;min-width:500px;">
    <div class="act-row">
      <!-- Sender Contact Icons -->
      <a href="tel:<?php echo htmlspecialchars($row['phone']??''); ?>" class="act-icon ai-call" title="Call Sender: <?php echo htmlspecialchars($row['ship_name']??''); ?>" style="background:#10b981;"><i class="fa fa-phone"></i></a>
      <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $row['phone']??''); ?>?text=Hi%20<?php echo urlencode($row['ship_name']??''); ?>,%20regarding%20shipment%20<?php echo urlencode($row['tracking']); ?>" target="_blank" class="act-icon ai-whatsapp" title="WhatsApp Sender: <?php echo htmlspecialchars($row['ship_name']??''); ?>" style="background:#25d366;"><i class="fab fa-whatsapp"></i></a>
      
      <a href="#" class="act-icon ai-update quick-update-trigger"
         data-tracking="<?php echo htmlspecialchars($row['tracking']); ?>"
         data-status="<?php echo htmlspecialchars($row['status']); ?>"
         data-detailed="<?php echo htmlspecialchars($row['detailed_status']??''); ?>"
         title="Quick Update"><i class="fa fa-refresh"></i></a>
      <a href="#" class="act-icon ai-history view-history-trigger"
         data-tracking="<?php echo htmlspecialchars($row['tracking']); ?>"
         data-cid="<?php echo $row['cid']; ?>"
         title="View History" style="background:#9b59b6;"><i class="fa fa-history"></i></a>
      <?php if($isAdmin): ?>
      <a href="javascript:void(0)" onclick="openEditCourierPanel(<?php echo $row['cid']; ?>)" class="act-icon ai-edit" title="Edit"><i class="fa fa-pencil"></i></a>
      <?php endif; ?>
      <a href="print-invoice/invoice-print.php?cid=<?php echo codificar($row['cid']); ?>" target="_blank" class="act-icon ai-print" title="Print Invoice"><i class="fa fa-print"></i></a>
      <a href="print-invoice/ticket-print.php?cid=<?php echo codificar($row['cid']); ?>" target="_blank" class="act-icon ai-label" title="Shipping Label"><i class="fa fa-tag"></i></a>
      <?php if($isAdmin): ?>
      <a href="#" class="act-icon ai-del" onclick="if(confirm('⚠️ Delete this shipment?\n\nThis cannot be undone.')) window.location='deletes/delete_shipment.php?cid=<?php echo (int)$row['cid']; ?>'; return false;" title="Delete (Admin Only)"><i class="fa fa-trash"></i></a>
      <?php endif; ?>
    </div>
  </td>
  <td>
    <a href="https://www.abra-logistic.com/tracking.php?shipping=<?php echo urlencode($row['tracking']); ?>" target="_blank" class="t-chip" style="<?php echo $isAppointmentDelivery ? 'background:#fca5a5; border-color:#dc2626; color:#7f1d1d; font-weight:900;' : ''; ?>">
      <?php echo htmlspecialchars($row['tracking']); ?>
      <?php if($isAppointmentDelivery): ?>
        <i class="fa fa-calendar-check-o" style="margin-left:5px;" title="Appointment Delivery"></i>
      <?php endif; ?>
    </a>
  </td>
  <td><?php 
    // Detect source from booking_source column or reference_no fallback
    $source = trim($row['booking_source'] ?? '');
    
    // CRITICAL FIX: Also check reference_no if booking_source is empty OR "Manual"
    // because "Manual" might be a default value that's incorrect
    if (empty($source) || $source === 'Manual') {
        $ref = trim($row['reference_no'] ?? '');
        if (stripos($ref, 'website') !== false) $source = 'Website';
        elseif (stripos($ref, 'mobile') !== false || stripos($ref, 'app') !== false) $source = 'Application';
        elseif (stripos($ref, 'whatsapp') !== false) $source = 'WhatsApp';
        elseif (stripos($ref, 'internal') !== false) $source = 'Internal Team';
        elseif (stripos($ref, 'api') !== false) $source = 'API';
        else $source = 'Manual';
    }
    
    $source_config = [
        'Website' => ['bg' => '#3b82f6', 'icon' => '🌐', 'label' => 'Website'],
        'Application' => ['bg' => '#10b981', 'icon' => '📱', 'label' => 'App'],
        'WhatsApp' => ['bg' => '#25d366', 'icon' => '💬', 'label' => 'WhatsApp'],
        'Internal Team' => ['bg' => '#8b5cf6', 'icon' => '👥', 'label' => 'Internal'],
        'Manual' => ['bg' => '#64748b', 'icon' => '✏️', 'label' => 'Manual']
    ];
    
    $config = $source_config[$source] ?? $source_config['Manual'];
    echo '<span style="display:inline-flex;align-items:center;gap:6px;padding:7px 12px;background:' . $config['bg'] . ';color:white;border-radius:8px;font-weight:700;font-size:13px;white-space:nowrap;box-shadow:0 2px 4px rgba(0,0,0,0.1);">';
    echo '<span style="font-size:16px;">' . $config['icon'] . '</span>';
    echo '<span>' . $config['label'] . '</span>';
    echo '</span>';
  ?></td>
  <td><span class="s-badge s-badge-lg" style="background:#<?php echo htmlspecialchars($sc); ?>; color:<?php echo $textColor; ?>;"><?php echo htmlspecialchars($row['status']); ?></span></td>
  <td><?php echo htmlspecialchars($row['reference_no']??'—'); ?></td>
  <td style="font-weight:900!important;font-size:1.25rem!important;"><strong><?php echo htmlspecialchars($row['sender_city']??'—'); ?></strong></td>
  <td style="font-weight:900!important;font-size:1.25rem!important;"><strong><?php echo htmlspecialchars($row['receiver_city']??'—'); ?></strong></td>
  <td class="amt" style="font-weight:900!important;"><?php echo $curr.number_format(floatval($row['shipping_subtotal']??0),2); ?></td>
  <td><?php echo htmlspecialchars($row['shipping_method']??'—'); ?></td>
  <td><span class="inv-num"><?php echo htmlspecialchars($row['invoice_no']??'—'); ?></span></td>
  <td><span class="inv-val"><?php 
    // CRITICAL FIX: Check which columns exist and use the right one
    $inv_display = 0;
    if (isset($row['invoice_value'])) {
        $inv_display = floatval($row['invoice_value']);
    }
    if ($inv_display == 0 && isset($row['invoice_id'])) {
        $inv_display = floatval($row['invoice_id']);
    }
    echo $curr.number_format($inv_display,2); 
  ?></span></td>
  <td><?php echo htmlspecialchars($row['delivery_aggregator']??'—'); ?></td>
  <td><?php 
    $office = trim($row['officename'] ?? '');
    if (empty($office) || strtolower($office) === 'employee') {
        echo '<span style="color:#94a3b8;font-style:italic;">Not Set</span>';
    } else {
        echo htmlspecialchars($office);
    }
  ?></td>
  <td style="font-weight:900!important;"><strong><?php echo htmlspecialchars($row['ship_name']??'—'); ?></strong></td>
  <td><?php echo htmlspecialchars($row['phone']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['correo']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['s_add']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['sender_country']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['sender_pincode']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['sender_locality']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['sender_state']??'—'); ?></td>
  <td style="font-weight:900!important;"><strong><?php echo htmlspecialchars($row['rev_name']??'—'); ?></strong></td>
  <td><?php echo htmlspecialchars($row['r_phone']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['receiver_alt_phone']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['email']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['r_add']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['receiver_country']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['receiver_pincode']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['receiver_locality']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['receiver_state']??'—'); ?></td>
  <td style="text-align:center;font-weight:900!important;"><?php echo htmlspecialchars($row['qty']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['paymode']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['product_type']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['service_mode']??'—'); ?></td>
  <td><?php echo htmlspecialchars($row['dom_internation']??'—'); ?></td>
  <td><?php echo number_format(floatval($row['pesoreal']??0),2); ?></td>
  <td><?php echo number_format(floatval($row['altura']??0),2); ?></td>
  <td><?php echo number_format(floatval($row['ancho']??0),2); ?></td>
  <td><?php echo number_format(floatval($row['longitud']??0),2); ?></td>
  <td><?php echo number_format(floatval($row['totalpeso']??0),2); ?></td>
  <td><?php echo htmlspecialchars(substr($row['comments']??'—', 0, 50)); ?><?php echo strlen($row['comments']??'')>50?'...':''; ?></td>
  <td><?php 
    $pickDate = $row['pick_date'] ?? '';
    if (empty($pickDate) || $pickDate == '0000-00-00' || $pickDate == '0000-00-00 00:00:00' || $pickDate == 'India') {
      echo '—';
    } else {
      $timestamp = strtotime($pickDate);
      echo $timestamp ? date('Y-m-d', $timestamp) : '—';
    }
  ?></td>
  <td><?php 
    $scheduleDate = $row['schedule'] ?? '';
    if (empty($scheduleDate) || $scheduleDate == '0000-00-00' || $scheduleDate == '0000-00-00 00:00:00' || $scheduleDate == 'India') {
      echo '—';
    } else {
      $timestamp = strtotime($scheduleDate);
      echo $timestamp ? date('Y-m-d', $timestamp) : '—';
    }
  ?></td>
</tr>
<?php 
    endwhile;
    // Debug: output row count
    echo "<!-- DEBUG: Successfully output $output_count rows -->";
}
?>
</tbody>
</table>
</div>
<?php endif; // End of has_shipments_to_display check ?>
</form>
</div>

<!-- QUICK UPDATE MODAL -->
<div class="modal fade" id="quickUpdateModal" tabindex="-1" role="dialog">
<div class="modal-dialog" role="document"><div class="modal-content">
<form method="POST">
<div class="modal-header">
  <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
  <h4 class="modal-title"><i class="fa fa-refresh"></i> Quick Update Shipment</h4>
</div>
<div class="modal-body">
  <input type="hidden" name="tracking_no" id="modal_tracking_no">
  <div class="form-group">
    <label>Shipment Number</label>
    <input type="text" class="form-control" id="modal_tracking_display" readonly style="background:#eff6ff;font-family:'JetBrains Mono',monospace;font-weight:700;color:#1e3a8a;">
  </div>
  <div class="form-group">
    <label>New Status <span class="req">*</span></label>
    <select name="status" id="modal_status" class="form-control" required style="font-size:16px;padding:14px 16px;height:auto;">
      <option value="">&#8212; Select Status &#8212;</option>
      <?php foreach($status_options as $s): ?><option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label>Detailed Status <small style="color:#94a3b8;font-weight:400;">(Optional)</small></label>
    <input type="text" name="detailed_status" id="modal_detailed_status" class="form-control" placeholder="e.g., Out for delivery, In transit" style="font-size:16px;padding:14px 16px;">
  </div>
  <div class="form-group">
    <label>Remarks <small style="color:#94a3b8;font-weight:400;">(Optional)</small></label>
    <textarea name="remarks" id="modal_remarks" class="form-control" rows="3" placeholder="Add any remarks&hellip;"></textarea>
  </div>
</div>
<div class="modal-footer">
  <button type="button" class="btn-act btn-ghost" data-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>
  <button type="submit" name="quick_update_submit" class="btn-act btn-teal"><i class="fa fa-check"></i> Update Shipment</button>
</div>
</form>
</div></div></div>

<!-- TRACKING HISTORY MODAL -->
<div class="modal fade" id="historyModal" tabindex="-1" role="dialog">
<div class="modal-dialog modal-lg" role="document" style="max-width:900px;">
<div class="modal-content">
<div class="modal-header" style="background:linear-gradient(135deg,#9b59b6 0%,#8e44ad 100%);color:#fff;">
  <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.9;"><span>&times;</span></button>
  <h4 class="modal-title"><i class="fa fa-history"></i> Tracking History - <span id="history_tracking_no"></span></h4>
</div>
<div class="modal-body" style="max-height:600px;overflow-y:auto;">
  <div id="history_content">
    <div style="text-align:center;padding:40px;">
      <i class="fa fa-spinner fa-spin" style="font-size:32px;color:#9b59b6;"></i>
      <p style="margin-top:15px;color:#666;">Loading tracking history...</p>
    </div>
  </div>
</div>
<div class="modal-footer">
  <button type="button" class="btn-act btn-ghost" data-dismiss="modal"><i class="fa fa-times"></i> Close</button>
</div>
</div>
</div></div>

<style>
.history-timeline {
  position:relative;
  padding:20px 0;
}
.history-item {
  position:relative;
  padding:20px 20px 20px 60px;
  margin-bottom:20px;
  background:#f8f9fa;
  border-left:4px solid #9b59b6;
  border-radius:8px;
  box-shadow:0 2px 4px rgba(0,0,0,0.1);
}
.history-item:hover {
  background:#fff;
  box-shadow:0 4px 8px rgba(0,0,0,0.15);
}
.history-icon {
  position:absolute;
  left:15px;
  top:20px;
  width:36px;
  height:36px;
  background:#9b59b6;
  border-radius:50%;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#fff;
  font-size:16px;
  box-shadow:0 2px 4px rgba(0,0,0,0.2);
}
.history-status {
  font-size:18px;
  font-weight:800;
  color:#1e3a8a;
  margin-bottom:8px;
}
.history-detailed {
  font-size:14px;
  color:#666;
  margin-bottom:8px;
}
.history-comments {
  font-size:14px;
  color:#444;
  background:#fff;
  padding:10px;
  border-radius:5px;
  margin-bottom:8px;
  border-left:3px solid #9b59b6;
}
.history-meta {
  font-size:12px;
  color:#999;
  display:flex;
  gap:15px;
  flex-wrap:wrap;
}
.history-meta span {
  display:inline-flex;
  align-items:center;
  gap:5px;
}
.history-user {
  color:#9b59b6;
  font-weight:700;
}
</style>

<!-- IMPORT MODAL -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog">
<div class="modal-dialog modal-lg" role="document"><div class="modal-content">
<form method="POST" enctype="multipart/form-data">
<div class="modal-header">
  <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
  <h4 class="modal-title"><i class="fa fa-upload"></i> Import Shipments</h4>
</div>
<div class="modal-body">
  <div class="flash flash-info" style="margin-bottom:18px;">
    <i class="fa fa-info-circle"></i> Upload a CSV file with the following columns in order:<br>
    <strong>Invoice Number, Invoice Value, Reference No, Aggregator, Delivery Type, Office Location, Sender Name, Sender Email, Sender Phone, Sender Address, Sender Country, Sender Pincode, Sender Locality, Sender City, Sender State, Receiver Name, Receiver Email, Receiver Phone, Receiver Alt Phone, Receiver Address, Receiver Country, Receiver Pincode, Receiver Locality, Receiver City, Receiver State, Quantity, Comments, Payment Mode, Product Type, Service Mode, Shipping Method, Shipment Type, Amount, Actual Weight, Height, Width, Length, Total Weight, Pickup Date, Schedule Date</strong>
  </div>
  
  <!-- Download Sample Button -->
  <div style="text-align:center;margin-bottom:25px;padding:20px;background:#fffbeb;border:2px dashed #f59e0b;border-radius:8px;">
    <h5 style="margin:0 0 10px 0;color:#92400e;font-weight:700;"><i class="fa fa-download"></i> Need a Sample Template?</h5>
    <p style="margin:0 0 15px 0;color:#78350f;font-size:14px;">Download our sample CSV file to see the correct format</p>
    <button type="button" class="btn-act btn-tmpl" onclick="downloadSampleCSV()" style="padding:12px 30px;font-size:15px;">
      <i class="fa fa-file-excel-o"></i> Download Sample CSV
    </button>
  </div>
  
  <div class="form-group">
    <label style="font-size:15px;font-weight:700;margin-bottom:10px;display:block;"><i class="fa fa-file"></i> Select File to Import</label>
    <div class="upload-zone" id="uploadZone" onclick="document.getElementById('importFile').click();">
      <span class="uz-icon"><i class="fa fa-cloud-upload"></i></span>
      <h5>Click to choose file</h5>
      <p>Accepted format: CSV</p>
    </div>
    <input type="file" name="import_file" id="importFile" accept=".csv" style="display:none;" required onchange="handleFileSelect(this)">
    <div class="file-preview" id="filePreview">
      <i class="fa fa-file-excel-o fpi"></i>
      <span id="fileName" style="font-weight:700;"></span>
      <button type="button" id="removeFile" class="btn-act btn-ghost" style="padding:.4rem .9rem;font-size:.85rem;margin-left:auto;" onclick="removeSelectedFile()">
        <i class="fa fa-times"></i> Remove
      </button>
    </div>
  </div>
</div>
<div class="modal-footer">
  <button type="button" class="btn-act btn-ghost" data-dismiss="modal">Cancel</button>
  <button type="submit" name="import_excel" class="btn-act btn-import" id="importSubmitBtn" disabled>
    <i class="fa fa-upload"></i> Import Shipments
  </button>
</div>
</form>
</div></div></div>

<!-- Standalone Footer -->
<div style="background: #f8fafc; border-top: 2px solid #e2e8f0; padding: 20px 30px; margin-top: 30px; text-align: center;">
  <div style="max-width: 1750px; margin: 0 auto;">
    <p style="margin: 0; color: #64748b; font-size: 14px;">
      &copy; <?php echo date('Y'); ?> <strong style="color: #1e3a8a;"><?php echo htmlspecialchars($_SESSION['ge_cname']); ?></strong>. All Rights Reserved.
    </p>
    <p style="margin: 5px 0 0 0; color: #94a3b8; font-size: 12px;">
      Shipment Management System v2.0 | Powered by ABRA Logistics
    </p>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>
<script>
// TEXT-TO-SPEECH FUNCTION - Must be defined BEFORE use
function speakMessage(message) {
  console.log('speakMessage called with:', message);
  
  if (!('speechSynthesis' in window)) {
    console.log('Speech synthesis not supported');
    return;
  }
  
  // Cancel any ongoing speech
  window.speechSynthesis.cancel();
  
  var utterance = new SpeechSynthesisUtterance(message);
  utterance.rate = 0.95;
  utterance.pitch = 1.3;
  utterance.volume = 1;
  utterance.lang = 'en-US';
  
  // Set voice
  var setVoice = function() {
    var voices = window.speechSynthesis.getVoices();
    console.log('Available voices:', voices.length);
    
    var preferredVoices = [
      'Google UK English Female',
      'Google US English Female',
      'Microsoft Zira',
      'Samantha',
      'Karen'
    ];
    
    for (var i = 0; i < preferredVoices.length; i++) {
      var voice = voices.find(function(v) {
        return v.name.indexOf(preferredVoices[i]) !== -1;
      });
      if (voice) {
        utterance.voice = voice;
        console.log('Using voice:', voice.name);
        break;
      }
    }
    
    // Speak
    window.speechSynthesis.speak(utterance);
    console.log('Speech started');
  };
  
  var voices = window.speechSynthesis.getVoices();
  if (voices.length === 0) {
    window.speechSynthesis.onvoiceschanged = setVoice;
  } else {
    setVoice();
  }
}

var table;
var hasPlayedWelcome = false;

// VOICE ANNOUNCEMENT FUNCTION
function playWelcomeAnnouncement() {
  console.log('playWelcomeAnnouncement called, hasPlayedWelcome:', hasPlayedWelcome);
  
  if (hasPlayedWelcome) return;
  hasPlayedWelcome = true;
  
  var totalShipments = <?php echo $totalActive; ?>;
  var userName = '<?php echo isset($_SESSION['user_name']) ? addslashes($_SESSION['user_name']) : 'there'; ?>';
  var firstName = userName.split(' ')[0];
  
  console.log('Total shipments:', totalShipments, 'User:', firstName);
  
  var message = '';
  if (totalShipments === 0) {
    message = 'Hello ' + firstName + '! Excellent work! You have no pending shipments. Everything is completed and up to date!';
  } else if (totalShipments === 1) {
    message = 'Good day ' + firstName + '! You have 1 active shipment awaiting completion. Please process it at your earliest convenience.';
  } else if (totalShipments <= 5) {
    message = 'Hello ' + firstName + '! You have ' + totalShipments + ' active shipments. You are doing great! Please complete them soon.';
  } else if (totalShipments <= 10) {
    message = 'Good day ' + firstName + '! You have ' + totalShipments + ' active shipments pending. Keep up the excellent work and complete them as soon as possible.';
  } else {
    message = 'Hello ' + firstName + '! You have ' + totalShipments + ' active shipments. That is quite substantial! Let us work efficiently to complete them promptly.';
  }
  
  console.log('Message to speak:', message);
  speakMessage(message);
}

$(function(){
  console.log('Document ready - Starting initialization...');
  console.log('jQuery version:', $.fn.jquery);
  console.log('DataTable available:', typeof $.fn.DataTable);
  
  // RESTORE WINDOW SCROLL POSITION (for success messages at top)
  var savedWindowScroll = localStorage.getItem('activeShipments_window_scroll');
  if (savedWindowScroll !== null) {
    var scrollY = parseInt(savedWindowScroll);
    setTimeout(function() {
      window.scrollTo(0, scrollY);
      console.log('Restored window scroll to:', scrollY);
      // Clear it after restoring once
      localStorage.removeItem('activeShipments_window_scroll');
    }, 100);
  }
  
  // SAVE WINDOW SCROLL POSITION before page unload
  $(window).on('beforeunload', function() {
    var scrollY = window.pageYOffset || document.documentElement.scrollTop;
    localStorage.setItem('activeShipments_window_scroll', scrollY);
    console.log('Saved window scroll:', scrollY);
  });
  
  // =========================================================================
  // SCROLL TO EDITED ROW - Keep user at the same position after edit
  // =========================================================================
  <?php if(isset($_SESSION['scroll_to_tracking'])): ?>
  var trackingToScroll = '<?php echo addslashes($_SESSION['scroll_to_tracking']); ?>';
  console.log('Scrolling to tracking:', trackingToScroll);
  
  // Wait for DataTable to initialize, then scroll
  setTimeout(function() {
    var targetRow = $('#row-' + trackingToScroll);
    if(targetRow.length > 0) {
      console.log('Found target row, scrolling...');
      
      // Highlight the row briefly
      targetRow.css('background-color', '#fef3c7');
      
      // Scroll to the row with offset for header
      $('html, body').animate({
        scrollTop: targetRow.offset().top - 150
      }, 500, function() {
        // Remove highlight after 2 seconds
        setTimeout(function() {
          targetRow.css('background-color', '');
        }, 2000);
      });
    } else {
      console.log('Target row not found');
    }
  }, 1000); // Wait 1 second for DataTable to render
  <?php unset($_SESSION['scroll_to_tracking']); ?>
  <?php endif; ?>
  
  // AGGRESSIVE AUTO-PLAY: Multiple attempts
  console.log('Setting up voice auto-play...');
  
  // Attempt 1: Immediate
  playWelcomeAnnouncement();
  
  // Attempt 2: After 300ms
  setTimeout(function() {
    console.log('Attempt 2: 300ms timeout');
    playWelcomeAnnouncement();
  }, 300);
  
  // Attempt 3: After 1 second
  setTimeout(function() {
    console.log('Attempt 3: 1 second timeout');
    playWelcomeAnnouncement();
  }, 1000);
  
  // Attempt 4: On ANY user interaction
  var events = ['click', 'mousemove', 'keydown', 'touchstart', 'scroll'];
  events.forEach(function(eventType) {
    $(document).one(eventType, function(e) {
      console.log('User interaction detected:', e.type);
      playWelcomeAnnouncement();
    });
  });
  
  // Check if table exists and has data rows
  var tableElement = $('#grid');
  var dataRows = tableElement.find('tbody tr').not(':has(td[colspan])');
  console.log('Table element found:', tableElement.length > 0);
  console.log('Total tbody rows:', tableElement.find('tbody tr').length);
  console.log('Data rows (excluding colspan):', dataRows.length);
  
  // If no data rows, show empty state and skip DataTables initialization
  if (dataRows.length === 0) {
    console.log('No data rows found - skipping DataTables initialization');
    $('#initial-loader').fadeOut(350, function(){
      $('#tableContainer').css('opacity', '1');
    });
    
    // Still enable the add shipment button functionality
    return;
  }
  
  try {
    table=$('#grid').DataTable({
      dom:'<"top-controls"l>rt<"bottom-controls"ip>',
      order:[[2,'desc']],  // Sort by Tracking column (now column 2, was 3)
      pageLength:50,
      lengthMenu:[[10,25,50,100,250,-1],['10','25','50','100','250','All']],
      scrollX:true, 
      scrollCollapse:true,
      // FIXED: Only Tracking column sticky on the left
      fixedColumns: {
        left: 3,  // Fix first 3 columns: Checkbox, Actions, Tracking
        right: 0
      },
      autoWidth:true,
      stateSave: true,
      stateDuration: 60 * 60 * 24, // Save state for 24 hours
      stateSaveCallback: function(settings, data) {
        // Save scroll position separately
        var scrollBody = $('.dataTables_scrollBody');
        if (scrollBody.length) {
          var scrollLeft = scrollBody.scrollLeft();
          localStorage.setItem('DataTables_activeShipments_scroll', scrollLeft);
        }
        localStorage.setItem('DataTables_activeShipments', JSON.stringify(data));
      },
      stateLoadCallback: function(settings) {
        try {
          return JSON.parse(localStorage.getItem('DataTables_activeShipments'));
        } catch(e) {
          return null;
        }
      },
      columnDefs:[
        {targets:0, orderable:false},                          // Checkbox
        {targets:1, orderable:false},                          // Actions
        {targets:'_all', className:'dt-body-left dt-head-left'} // Align all columns left
      ], 
      paging:true, 
      info:true,
      language:{
        lengthMenu:'Show _MENU_ shipments per page',
        info:'Showing _START_ to _END_ of _TOTAL_ shipments',
        infoEmpty:'No shipments', 
        infoFiltered:'(filtered from _MAX_ total)',
        zeroRecords:'No matching shipments',
        paginate:{first:'&laquo; First',last:'Last &raquo;',next:'Next &rsaquo;',previous:'&lsaquo; Prev'}
      },
      initComplete:function(){
        console.log('DataTable initialized successfully');
        console.log('Total rows:', this.api().rows().count());
        
        // CRITICAL: Force column width synchronization between header and body
        var api = this.api();
        setTimeout(function() {
          // Get all header cells
          var headerCells = $('#grid thead th');
          var bodyCells = $('#grid tbody tr:first td');
          
          // Synchronize widths
          headerCells.each(function(index) {
            var headerWidth = $(this).outerWidth();
            var bodyCell = bodyCells.eq(index);
            if (bodyCell.length) {
              var bodyWidth = bodyCell.outerWidth();
              // Use the larger width to ensure alignment
              var maxWidth = Math.max(headerWidth, bodyWidth);
              $(this).css('width', maxWidth + 'px');
              bodyCell.css('width', maxWidth + 'px');
            }
          });
          
          // Force redraw
          api.columns.adjust().draw(false);
          console.log('Column widths synchronized');
        }, 100);
        
        $('#initial-loader').fadeOut(350,function(){
          $('#tableContainer').css('opacity','1');
          setTimeout(function(){
            if(table) table.columns.adjust().draw(false);
            console.log('Columns adjusted');
          },80);
        });
        
        // Restore scroll position ONCE after initial load
        var savedScroll = localStorage.getItem('DataTables_activeShipments_scroll');
        if (savedScroll !== null) {
          var scrollLeft = parseInt(savedScroll);
          setTimeout(function() {
            $('.dataTables_scrollBody').scrollLeft(scrollLeft);
            console.log('Restored scroll to:', scrollLeft);
          }, 300);
        }
        
        // Save scroll position when user scrolls horizontally
        var scrollTimeout;
        $('.dataTables_scrollBody').on('scroll', function() {
          clearTimeout(scrollTimeout);
          scrollTimeout = setTimeout(function() {
            var scrollLeft = $('.dataTables_scrollBody').scrollLeft();
            localStorage.setItem('DataTables_activeShipments_scroll', scrollLeft);
            console.log('Saved scroll:', scrollLeft);
          }, 100);
        });
      },
      drawCallback: function(settings) {
        var api = this.api();
        var rowCount = api.rows({page:'current'}).count();
        console.log('Table drawn, rows displayed:', rowCount);
        
        // Update select all checkbox state after pagination
        var totVisible = $('.track-check:visible').length;
        var chkVisible = $('.track-check:visible:checked').length;
        $('#checkAll').prop('indeterminate', chkVisible > 0 && chkVisible < totVisible)
                      .prop('checked', chkVisible === totVisible && totVisible > 0);
        syncSel();
        
        // If no rows, ensure empty message is visible
        if (rowCount === 0) {
          console.log('No rows to display');
        }
      },
      error: function(xhr, error, thrown) {
        console.error('DataTable error:', error, thrown);
        console.error('XHR:', xhr);
        $('#initial-loader').html('<div style="color:#dc2626;"><i class="fa fa-exclamation-triangle"></i><p>DataTable initialization failed</p><p style="font-size:14px;">Check browser console (F12) for details</p></div>');
      }
    });
    
    console.log('DataTable object created:', typeof table);
  } catch(e) {
    console.error('DataTable initialization error:', e);
    console.error('Error stack:', e.stack);
    $('#initial-loader').html('<div style="color:#dc2626;padding:20px;"><i class="fa fa-exclamation-triangle" style="font-size:32px;"></i><p style="margin-top:15px;font-weight:700;">Failed to initialize table</p><p style="font-size:14px;margin-top:10px;">Error: ' + e.message + '</p><p style="font-size:12px;margin-top:10px;">Check browser console (F12) for full details</p></div>');
    $('#tableContainer').css('opacity','1');
  }

  /* Multi-column filters */
  $.fn.dataTable.ext.search.push(function(settings,data){
    var st=$('#statusFilter').val(),      me=$('#methodFilter').val(),
        ty=$('#typeFilter').val(),         ag=$('#aggregatorFilter').val(),
        pa=$('#paymodeFilter').val(),      oc=$('#originCityFilter').val(),
        os=$('#originStateFilter').val(),  dc=$('#destCityFilter').val(),
        ds=$('#destStateFilter').val(),    sc=$('#senderCountryFilter').val(),
        rc=$('#receiverCountryFilter').val();
    
    // CORRECT Column indices (0-based) - WITH SOURCE COLUMN AFTER TRACKING:
    // 0=checkbox, 1=actions, 2=tracking, 3=source, 4=status, 5=reference_no, 6=origin_city, 7=dest_city
    // 8=amount, 9=shipping_method, 10=invoice_no, 11=invoice_value, 12=aggregator, 13=office
    // 14=sender_name, 15=sender_phone, 16=sender_email, 17=sender_addr, 18=sender_country, 19=sender_pincode
    // 20=sender_locality, 21=sender_state
    // 22=rcvr_name, 23=rcvr_phone, 24=rcvr_alt_phone, 25=rcvr_email, 26=rcvr_addr, 27=rcvr_country
    // 28=rcvr_pincode, 29=rcvr_locality, 30=rcvr_state
    // 31=packages, 32=paymode, 33=product_type, 34=service_mode, 35=shipment_type
    // 36-43=dimensions/dates
    
    // Use case-insensitive indexOf for partial matching (contains)
    if(st && data[4].toLowerCase().indexOf(st.toLowerCase()) === -1) return false;   // Status
    if(me && data[9].toLowerCase().indexOf(me.toLowerCase()) === -1) return false;   // Shipping Method
    if(ty && data[35].toLowerCase().indexOf(ty.toLowerCase()) === -1) return false;  // Shipment Type
    if(ag && data[12].toLowerCase().indexOf(ag.toLowerCase()) === -1) return false;  // Aggregator
    if(pa && data[32].toLowerCase().indexOf(pa.toLowerCase()) === -1) return false;  // Payment Mode
    if(oc && data[6].toLowerCase().indexOf(oc.toLowerCase()) === -1) return false;   // Origin City
    if(os && data[21].toLowerCase().indexOf(os.toLowerCase()) === -1) return false;  // Origin State (Sender State)
    if(dc && data[7].toLowerCase().indexOf(dc.toLowerCase()) === -1) return false;   // Destination City
    if(ds && data[30].toLowerCase().indexOf(ds.toLowerCase()) === -1) return false;  // Destination State (Receiver State)
    if(sc && data[18].toLowerCase().indexOf(sc.toLowerCase()) === -1) return false;  // Sender Country
    if(rc && data[27].toLowerCase().indexOf(rc.toLowerCase()) === -1) return false;  // Receiver Country
    return true;
  });
  $('#statusFilter,#methodFilter,#typeFilter,#aggregatorFilter,#paymodeFilter,#originCityFilter,#originStateFilter,#destCityFilter,#destStateFilter,#senderCountryFilter,#receiverCountryFilter').on('change',function(){
    if(table) table.draw();
  });
  $('#globalSearch').on('input',function(){
    if(table) table.search(this.value).draw();
  });

  /* Toggle sort */
  var isAsc=false;
  $('#toggleSort').on('click',function(e){
    e.stopPropagation(); 
    if(!table) return;
    isAsc=!isAsc;
    table.order([2,isAsc?'asc':'desc']).draw();  // Column 2 = Tracking Number
    $('#sortIcon').text(isAsc?'\u25B2':'\u25BC');
  });

  /* Select all */
  $('#checkAll').on('click',function(){
    // Only check/uncheck visible checkboxes on current page
    $('.track-check:visible').prop('checked',this.checked);
    syncSel();
  });
  $(document).on('change','.track-check',function(){
    // Count only visible checkboxes on current page
    var tot=$('.track-check:visible').length,chk=$('.track-check:visible:checked').length;
    $('#checkAll').prop('indeterminate',chk>0&&chk<tot).prop('checked',chk===tot);
    syncSel();
  });
  function syncSel(){
    var n=$('.track-check:checked').length;
    $('#selectedCount').text(n);
    $('#deleteCount').text(n);
    
    // Show/hide bulk delete button based on selection
    if(n > 0) {
      $('#bulkDelete').fadeIn(200);
    } else {
      $('#bulkDelete').fadeOut(200);
    }
    
    $('.track-check').each(function(){$(this).closest('tr').toggleClass('row-sel',$(this).is(':checked'));});
  }

  /* Export dropdown */
  $('#exportDropdownBtn').on('click',function(e){e.stopPropagation();$('#exportDropdown').toggleClass('open');});
  $(document).on('click',function(e){if(!$(e.target).closest('.dd-wrap').length)$('#exportDropdown').removeClass('open');});
  
  function postForm(fields){
    var f=$('<form>',{method:'POST',style:'display:none'});
    $.each(fields,function(name,val){(Array.isArray(val)?val:[val]).forEach(function(v){f.append($('<input>',{type:'hidden',name:name,value:v}));});});
    $('body').append(f);
    f.submit();
  }

  // View History Modal
  $(document).on('click', '.view-history-trigger', function(e) {
    e.preventDefault();
    var tracking = $(this).data('tracking');
    var cid = $(this).data('cid');
    
    $('#history_tracking_no').text(tracking);
    $('#history_content').html('<div style="text-align:center;padding:40px;"><i class="fa fa-spinner fa-spin" style="font-size:32px;color:#9b59b6;"></i><p style="margin-top:15px;color:#666;">Loading tracking history...</p></div>');
    $('#historyModal').modal('show');
    
    // Fetch history via AJAX
    $.ajax({
      url: 'get_tracking_history.php',
      method: 'GET',
      data: { cid: cid, tracking: tracking },
      dataType: 'json',
      success: function(response) {
        if (response.success && response.history && response.history.length > 0) {
          var html = '<div class="history-timeline">';
          response.history.forEach(function(item) {
            html += '<div class="history-item">';
            html += '<div class="history-icon"><i class="fa fa-check"></i></div>';
            html += '<div class="history-status">' + escapeHtml(item.status) + '</div>';
            if (item.detailed_status) {
              html += '<div class="history-detailed"><strong>Details:</strong> ' + escapeHtml(item.detailed_status) + '</div>';
            }
            if (item.comments) {
              html += '<div class="history-comments"><i class="fa fa-comment"></i> ' + escapeHtml(item.comments) + '</div>';
            }
            html += '<div class="history-meta">';
            html += '<span><i class="fa fa-user"></i> <span class="history-user">' + escapeHtml(item.user || 'System') + '</span></span>';
            html += '<span><i class="fa fa-clock-o"></i> ' + escapeHtml(item.bk_time) + '</span>';
            html += '</div>';
            html += '</div>';
          });
          html += '</div>';
          $('#history_content').html(html);
        } else {
          $('#history_content').html('<div style="text-align:center;padding:40px;color:#999;"><i class="fa fa-info-circle" style="font-size:48px;margin-bottom:15px;"></i><p>No tracking history found for this shipment.</p></div>');
        }
      },
      error: function() {
        $('#history_content').html('<div style="text-align:center;padding:40px;color:#dc3545;"><i class="fa fa-exclamation-triangle" style="font-size:48px;margin-bottom:15px;"></i><p>Failed to load tracking history. Please try again.</p></div>');
      }
    });
  });

  function escapeHtml(text) {
    if (!text) return '';
    var map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
  }

  
  $('#exportAllBtn').on('click',function(){
    $('#exportDropdown').removeClass('open');
    flashMsg('info','Preparing export&hellip;');
    postForm({export_excel:1});
  });
  $('#exportSelectedBtn').on('click',function(){
    var vals=$('.track-check:checked').map(function(){return this.value;}).get();
    if(!vals.length){flashMsg('warning','Please select at least one shipment.');return;}
    $('#exportDropdown').removeClass('open');
    flashMsg('info','Exporting '+vals.length+' records&hellip;');
    postForm({export_selected:1,'selected_tracking[]':vals});
  });

  /* Bulk Delete Shipments */
  window.bulkDeleteShipments = function() {
    var checked = $('.track-check:checked');
    if (checked.length === 0) {
      flashMsg('warning', 'Please select at least one shipment to delete.');
      return;
    }
    
    if (!confirm('⚠️ DELETE ' + checked.length + ' SHIPMENTS?\n\nThis action CANNOT be undone!\n\nAre you absolutely sure?')) {
      return;
    }
    
    // Get all CIDs from selected rows
    var cids = [];
    checked.each(function() {
      var row = $(this).closest('tr');
      var deleteLink = row.find('.ai-del').attr('onclick');
      if (deleteLink) {
        var cidMatch = deleteLink.match(/cid=(\d+)/);
        if (cidMatch && cidMatch[1]) {
          cids.push(cidMatch[1]);
        }
      }
    });
    
    if (cids.length === 0) {
      flashMsg('error', 'Could not find shipment IDs to delete.');
      return;
    }
    
    flashMsg('info', 'Deleting ' + cids.length + ' shipments...');
    
    // Delete each shipment
    var deletePromises = cids.map(function(cid) {
      return $.ajax({
        url: 'deletes/delete_shipment.php?cid=' + cid,
        method: 'GET'
      });
    });
    
    Promise.all(deletePromises).then(function() {
      flashMsg('success', cids.length + ' shipments deleted successfully!');
      setTimeout(function() {
        location.reload();
      }, 1500);
    }).catch(function() {
      flashMsg('error', 'Some shipments could not be deleted. Please refresh and try again.');
    });
  };

  /* Quick update modal */
  $(document).on('click','.quick-update-trigger',function(e){
    e.preventDefault();
    $('#modal_tracking_no').val($(this).data('tracking'));
    $('#modal_tracking_display').val($(this).data('tracking'));
    $('#modal_status').val($(this).data('status'));
    $('#modal_detailed_status').val($(this).data('detailed') || '');
    $('#modal_remarks').val('');
    $('#quickUpdateModal').modal('show');
  });

  /* Import picker - Using plain JavaScript for better compatibility */
  window.handleFileSelect = function(input) {
    console.log('File selected:', input.files);
    if(input.files.length){
      document.getElementById('fileName').textContent = input.files[0].name;
      document.getElementById('filePreview').classList.add('vis');
      var submitBtn = document.getElementById('importSubmitBtn');
      if(submitBtn) submitBtn.disabled = false;
    }
  };
  
  window.removeSelectedFile = function() {
    document.getElementById('importFile').value = '';
    document.getElementById('fileName').textContent = '';
    document.getElementById('filePreview').classList.remove('vis');
    var submitBtn = document.getElementById('importSubmitBtn');
    if(submitBtn) submitBtn.disabled = true;
  };
  $('#removeFile').on('click',function(e){
    e.stopPropagation();
    $('#importFile').val('');$('#filePreview').removeClass('vis');$('#importSubmitBtn').prop('disabled',true);
  });

  $(window).on('resize',function(){
    if(table) table.columns.adjust();
  });
  syncSel();
  
  // Fallback: If loader is still visible after 10 seconds, show error
  setTimeout(function() {
    if ($('#initial-loader').is(':visible')) {
      console.error('TIMEOUT: DataTable did not initialize within 10 seconds');
      $('#initial-loader').html('<div style="color:#dc2626;padding:20px;"><i class="fa fa-exclamation-triangle" style="font-size:32px;"></i><p style="margin-top:15px;font-weight:700;">Loading timeout</p><p style="font-size:14px;margin-top:10px;">The table is taking too long to load. This could be due to:</p><ul style="text-align:left;margin-top:10px;"><li>Too many records in database</li><li>Slow database connection</li><li>JavaScript error (check console with F12)</li></ul><button onclick="location.reload()" class="btn-act btn-teal" style="margin-top:15px;"><i class="fa fa-refresh"></i> Reload Page</button></div>');
    }
  }, 10000);
});

/* Flash helper - moved outside document.ready */
function flashMsg(type,text){
  var ico={success:'check-circle',danger:'exclamation-triangle',info:'info-circle',warning:'exclamation-circle'};
  var el=$('<div class="flash flash-'+type+'"><i class="fa fa-'+ico[type]+'"></i> '+text+' <button class="flash-x" onclick="this.parentElement.remove()">&times;</button></div>');
  el.hide().prependTo('.container-fluid').slideDown(220);
  setTimeout(function(){el.fadeOut(300,function(){el.remove();});},5500);
}

window.downloadSampleCSV = function() {
  // Empty template - just headers, no sample data
  // Users can enter their own data in any format they want
  var csvContent = "Invoice Number,Invoice Value,Reference No,Aggregator,Delivery Type,Office Location,Sender Name,Sender Email,Sender Phone,Sender Address,Sender Country,Sender Pincode,Sender Locality,Sender City,Sender State,Receiver Name,Receiver Email,Receiver Phone,Receiver Alt Phone,Receiver Address,Receiver Country,Receiver Pincode,Receiver Locality,Receiver City,Receiver State,No of Packages,Description,Payment Mode,Product Type,Service Mode,Shipping Method,Shipment Type,Amount,Actual Weight,Height,Width,Length,Volumetric Weight,Pickup Date,Est Delivery Date\n";
  csvContent += ",,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n";
  csvContent += ",,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n";
  csvContent += ",,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n";
  
  // Create blob and download
  var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  var link = document.createElement("a");
  var url = URL.createObjectURL(blob);
  
  link.setAttribute("href", url);
  link.setAttribute("download", "shipment_import_template.csv");
  link.style.visibility = 'hidden';
  
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  
  flashMsg('success', 'CSV template downloaded - Enter your data and upload!');
}

window.resetFilters = function(){
  $('#statusFilter,#methodFilter,#typeFilter,#aggregatorFilter,#paymodeFilter,#originCityFilter,#originStateFilter,#destCityFilter,#destStateFilter,#senderCountryFilter,#receiverCountryFilter').val('');
  $('#globalSearch').val('');
  if(table) table.search('').draw();
}

// AJAX Add Courier Panel
window.openAddCourierPanel = function() {
  $('#courierPanelOverlay').addClass('open');
  $('#courierPanelContainer').addClass('open');
  $('body').css('overflow', 'hidden');
  
  // Update header
  $('.courier-panel-header h3').html('<i class="fa fa-truck"></i> Add New Shipment');
  
  // Load add-courier.php content via AJAX
  $('#courierPanelContent').html('<div style="text-align:center;padding:50px;"><i class="fa fa-spinner fa-spin" style="font-size:32px;color:#1e3a8a;"></i><p style="margin-top:15px;color:#64748b;">Loading form...</p></div>');
  
  $.ajax({
    url: 'add-courier.php',
    method: 'GET',
    data: { ajax_load: 1 },
    success: function(response) {
      $('#courierPanelContent').html(response);
      initCourierForm();
    },
    error: function() {
      $('#courierPanelContent').html('<div style="text-align:center;padding:50px;color:#dc2626;"><i class="fa fa-exclamation-circle" style="font-size:32px;"></i><p style="margin-top:15px;">Failed to load form. Please try again.</p></div>');
    }
  });
}

// AJAX Edit Courier Panel
window.openEditCourierPanel = function(cid) {
  $('#courierPanelOverlay').addClass('open');
  $('#courierPanelContainer').addClass('open');
  $('body').css('overflow', 'hidden');
  
  // Update header
  $('.courier-panel-header h3').html('<i class="fa fa-pencil"></i> Edit Shipment');
  
  // Load edit-courier.php content via AJAX
  $('#courierPanelContent').html('<div style="text-align:center;padding:50px;"><i class="fa fa-spinner fa-spin" style="font-size:32px;color:#1e3a8a;"></i><p style="margin-top:15px;color:#64748b;">Loading shipment...</p></div>');
  
  $.ajax({
    url: 'edit-courier.php',
    method: 'GET',
    data: { cid: cid, ajax_load: 1 },
    success: function(response) {
      $('#courierPanelContent').html(response);
      initCourierForm();
    },
    error: function() {
      $('#courierPanelContent').html('<div style="text-align:center;padding:50px;color:#dc2626;"><i class="fa fa-exclamation-circle" style="font-size:32px;"></i><p style="margin-top:15px;">Failed to load shipment. Please try again.</p></div>');
    }
  });
}

window.closeCourierPanel = function() {
  $('#courierPanelOverlay').removeClass('open');
  $('#courierPanelContainer').removeClass('open');
  $('body').css('overflow', '');
  
  // CRITICAL: Clear the form content to prevent duplicate submissions
  setTimeout(function() {
    $('#courierPanelContent').html('');
  }, 300);
}

function initCourierForm() {
  // CRITICAL: Track if submission is in progress to prevent duplicates
  var isSubmitting = false;
  
  // Handle form submission via AJAX (works for both add and edit)
  $('#addCourierForm, #editCourierForm').off('submit').on('submit', function(e) {
    e.preventDefault();
    e.stopImmediatePropagation(); // Prevent multiple handlers
    
    // CRITICAL: Prevent duplicate submissions
    if (isSubmitting) {
      console.log('Submission already in progress, ignoring duplicate');
      return false;
    }
    
    var form = this;
    var isEdit = $(form).attr('id') === 'editCourierForm';
    
    // Prevent double submission
    var submitBtn = $(form).find('button[type="submit"]');
    if (submitBtn.prop('disabled')) {
      console.log('Submit button already disabled, ignoring');
      return false;
    }
    
    // Simple validation - just check if form is valid
    var isValid = true;
    var firstInvalidField = null;
    
    $(form).find('[required]').each(function() {
      if (!this.value || this.value.trim() === '') {
        isValid = false;
        if (!firstInvalidField) {
          firstInvalidField = this;
        }
      }
    });
    
    if (!isValid && firstInvalidField) {
      var label = $(firstInvalidField).closest('.form-group').find('label').text().replace('*', '').trim();
      flashMsg('danger', 'Please fill the required field: ' + label);
      
      // Find which tab this field is in and switch to it
      var paneIndex = $(firstInvalidField).closest('.form-pane').index('.form-pane');
      if (paneIndex >= 0 && typeof gotoTab === 'function') {
        gotoTab(paneIndex);
      }
      $(firstInvalidField).focus();
      return false;
    }
    
    // CRITICAL: Set flag to prevent duplicate submissions
    isSubmitting = true;
    console.log('Starting form submission...');
    
    var formData = new FormData(form);
    var actionUrl = isEdit ? 'edit-courier.php' : window.location.pathname.split('/').pop();
    
    if (!isEdit) {
      formData.append('add_shipment_inline', '1');
    }
    
    submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + (isEdit ? 'Updating...' : 'Creating...'));
    
    $.ajax({
      url: actionUrl,
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'  // CRITICAL: Tells server this is AJAX
      },
      success: function(response) {
        console.log('AJAX Success:', response);
        // IMMEDIATELY close panel and reload - don't wait
        closeCourierPanel();
        if (response && response.success) {
          flashMsg('success', response.message);
        } else {
          flashMsg('danger', (response && response.message) ? response.message : 'Operation failed');
        }
        // Reload immediately to prevent duplicate submissions
        setTimeout(function() {
          location.reload();
        }, 500);
      },
      error: function(xhr, status, error) {
        console.error('AJAX Error:', status, error);
        console.error('Response Text:', xhr.responseText);
        
        // Close panel and reload to prevent duplicates
        closeCourierPanel();
        flashMsg('danger', 'Server error: ' + error + '. Reloading to check if shipment was created...');
        setTimeout(function() {
          location.reload();
        }, 1500);
      }
    });
    
    return false;
  });
}

$(document).on('keydown', function(e) {
  if(e.key === 'Escape') closeCourierPanel();
});
</script>

<!-- Courier Panel Overlay -->
<div id="courierPanelOverlay" class="courier-panel-overlay" onclick="closeCourierPanel()"></div>

<!-- Courier Panel Container -->
<div id="courierPanelContainer" class="courier-panel-container">
  <div class="courier-panel-header">
    <h3><i class="fa fa-truck"></i> Add New Shipment</h3>
    <button class="courier-panel-close" onclick="closeCourierPanel()" title="Close (Esc)">&times;</button>
  </div>
  <div id="courierPanelContent" class="courier-panel-content"></div>
</div>

<style>
.courier-panel-overlay {
  position: fixed;
  inset: 0;
  background: rgba(10, 18, 40, 0.6);
  backdrop-filter: blur(4px);
  z-index: 9998;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s;
}
.courier-panel-overlay.open {
  opacity: 1;
  pointer-events: all;
}
.courier-panel-container {
  position: fixed;
  top: 0;
  right: 0;
  height: 100vh;
  width: 900px;
  max-width: 100vw;
  background: #fff;
  z-index: 9999;
  transform: translateX(100%);
  transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  flex-direction: column;
  box-shadow: -12px 0 60px rgba(0, 0, 0, 0.3);
}
.courier-panel-container.open {
  transform: translateX(0);
}
.courier-panel-header {
  background: linear-gradient(135deg, #1e3a8a, #2563eb);
  color: #fff;
  padding: 20px 28px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}
.courier-panel-header h3 {
  margin: 0;
  font-size: 1.3rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 10px;
}
.courier-panel-close {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: #fff;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  font-size: 1.3rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}
.courier-panel-close:hover {
  background: rgba(255, 255, 255, 0.35);
}
.courier-panel-content {
  flex: 1;
  overflow-y: auto;
  padding: 0;
}
.courier-panel-content::-webkit-scrollbar {
  width: 6px;
}
.courier-panel-content::-webkit-scrollbar-thumb {
  background: #1e40af;
  border-radius: 4px;
}
</style>

</body>
</html>