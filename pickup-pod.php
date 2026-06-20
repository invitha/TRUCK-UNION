<?php
/**
 * ABRA LOGISTICS - PICKUP MANAGEMENT
 * POD Management for Picked Up & Failed Pickup (tsp_milestones integration)
 */

error_reporting(E_ERROR | E_WARNING | E_PARSE);
session_start();

include('database.php');
include('funciones.php');
include('library.php');

if (!isset($_SESSION['user_name'])) {
    header('Location: index.php');
    exit;
}

if (isset($_SESSION['ge_timezone'])) {
    date_default_timezone_set($_SESSION['ge_timezone']);
} else {
    date_default_timezone_set('UTC');
}

$currency = $_SESSION['ge_curr'] ?? '$';

$_SESSION['return_to_pickup'] = '/dashboard/pickup-pod.php';

$userType = $_SESSION['user_type'] ?? 'Employee';
if ($userType === 'Administrator') {
    $backUrl = '/dashboard/';
} else {
    $backUrl = '/dashboard/raise-a-ticket.php';
}

$isAdmin = ($_SESSION['user_name'] === 'abhishek' || $userType === 'Administrator');

// =========================================================================
// HELPER: detect if a string is a direct URL (from Flutter app uploads)
// =========================================================================
function isPodDirectUrl($str) {
    return (strpos($str, 'http://') === 0 || strpos($str, 'https://') === 0);
}

// =========================================================================
// POD IMAGE LOADING - From tsp_milestones (Picked Up / Failed Pickup)
// Handles 3 storage formats:
//   A) Direct URL  (Flutter app): "https://abra-flowxai.abragroup.in/uploads/..."
//   B) JSON array  (web upload):  [{"image":"data:image/jpeg;base64,..."}]
//   C) Legacy plain base64 string
// =========================================================================
if (isset($_GET['get_pod_image'])) {
    $tracking  = mysqli_real_escape_string($dbConn, $_GET['get_pod_image']);
    $pod_index = isset($_GET['pod_index']) ? intval($_GET['pod_index']) : 0;

    $query = "SELECT trPODimage FROM tsp_milestones
              WHERE tracking = ? AND trStatus IN ('Picked Up','Failed Pickup')
              AND trPODimage IS NOT NULL AND trPODimage != ''
              ORDER BY trID DESC LIMIT 1";
    $stmt = mysqli_prepare($dbConn, $query);
    mysqli_stmt_bind_param($stmt, "s", $tracking);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row    = mysqli_fetch_assoc($result);

    if ($row && !empty($row['trPODimage'])) {
        $raw = $row['trPODimage'];

        // Case A: direct URL stored by Flutter app — redirect browser to it
        if (isPodDirectUrl($raw)) {
            mysqli_stmt_close($stmt);
            header('Location: ' . $raw);
            exit;
        }

        // Case B: JSON array of pods
        $pods_data = json_decode($raw, true);
        $img_data  = '';

        if (is_array($pods_data)) {
            $entry    = $pods_data[$pod_index] ?? $pods_data[0];
            $img_data = $entry['image'] ?? '';
            // Entry image might itself be a direct URL
            if (isPodDirectUrl($img_data)) {
                mysqli_stmt_close($stmt);
                header('Location: ' . $img_data);
                exit;
            }
        } else {
            // Case C: legacy plain base64
            $img_data = $raw;
        }

        if (!empty($img_data)) {
            $is_pdf = (strpos($img_data, 'application/pdf') !== false);
            if (strpos($img_data, ',') !== false) {
                $img_data = explode(',', $img_data)[1];
            }
            $img_data = preg_replace('/\s+/', '', $img_data);
            header($is_pdf ? 'Content-Type: application/pdf' : 'Content-Type: image/jpeg');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
            echo base64_decode($img_data);
            mysqli_stmt_close($stmt);
            exit;
        }
    }

    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    mysqli_stmt_close($stmt);
    exit;
}

// =========================================================================
// AJAX Get POD — returns is_url flag so JS can use URL directly
// =========================================================================
if (isset($_GET['ajax_get_pod']) && $_GET['ajax_get_pod'] == '1') {
    header('Content-Type: application/json');
    $tracking = mysqli_real_escape_string($dbConn, $_GET['tracking']);

    $query = "SELECT trPODimage FROM tsp_milestones
              WHERE tracking = ? AND trStatus IN ('Picked Up','Failed Pickup')
              AND trPODimage IS NOT NULL AND trPODimage != ''
              ORDER BY trID DESC LIMIT 1";
    $stmt = mysqli_prepare($dbConn, $query);
    mysqli_stmt_bind_param($stmt, "s", $tracking);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row    = mysqli_fetch_assoc($result);

    $pods = [];
    if ($row && !empty($row['trPODimage'])) {
        $raw = $row['trPODimage'];

        // Case A: direct URL (Flutter app upload)
        if (isPodDirectUrl($raw)) {
            $pods = [[
                'image'       => $raw,
                'is_url'      => true,
                'timestamp'   => 'Driver Upload',
                'uploaded_by' => 'Driver App'
            ]];
        } else {
            // Case B: JSON array
            $pods_data = json_decode($raw, true);
            if (is_array($pods_data)) {
                foreach ($pods_data as $pod) {
                    if (isset($pod['image'])) {
                        $isUrl = isPodDirectUrl($pod['image']);
                        $pods[] = [
                            'image'       => $pod['image'],
                            'is_url'      => $isUrl,
                            'timestamp'   => $pod['timestamp']   ?? 'Date Unknown',
                            'uploaded_by' => $pod['uploaded_by'] ?? 'Unknown'
                        ];
                    }
                }
            } else {
                // Case C: legacy plain base64
                $pods = [[
                    'image'       => $raw,
                    'is_url'      => false,
                    'timestamp'   => 'Legacy Upload',
                    'uploaded_by' => 'Driver/System'
                ]];
            }
        }
    }

    echo json_encode([
        'success'  => true,
        'has_pod'  => count($pods) > 0,
        'pods'     => $pods,
        'tracking' => $tracking,
        'is_admin' => $isAdmin
    ]);
    mysqli_stmt_close($stmt);
    exit;
}

// =========================================================================
// POD Upload
// =========================================================================
if (isset($_POST['upload_pod'])) {
    header('Content-Type: application/json');
    $tracking = mysqli_real_escape_string($dbConn, $_POST['tracking']);

    if (isset($_FILES['pod_image']) && $_FILES['pod_image']['error'] === UPLOAD_ERR_OK) {
        $image_data          = file_get_contents($_FILES['pod_image']['tmp_name']);
        $base64_image        = base64_encode($image_data);
        $image_type          = $_FILES['pod_image']['type'];
        $base64_with_prefix  = 'data:' . $image_type . ';base64,' . $base64_image;

        $check_query = "SELECT trID, trPODimage FROM tsp_milestones 
                        WHERE tracking = ? AND trStatus IN ('Picked Up','Failed Pickup')
                        ORDER BY trID DESC LIMIT 1";
        $stmt = mysqli_prepare($dbConn, $check_query);
        mysqli_stmt_bind_param($stmt, "s", $tracking);
        mysqli_stmt_execute($stmt);
        $result    = mysqli_stmt_get_result($stmt);
        $pods_array = [];

        if (mysqli_num_rows($result) > 0) {
            $row            = mysqli_fetch_assoc($result);
            $existing_pods  = $row['trPODimage'];
            $trID           = $row['trID'];

            if (!empty($existing_pods)) {
                $decoded = json_decode($existing_pods, true);
                if (is_array($decoded)) {
                    $pods_array = $decoded;
                } else {
                    $pods_array = [[
                        'image'       => $existing_pods,
                        'timestamp'   => 'Migrated - Date Unknown',
                        'uploaded_by' => 'Legacy Upload'
                    ]];
                }
            }

            $pods_array[] = [
                'image'       => $base64_with_prefix,
                'timestamp'   => date('Y-m-d H:i:s'),
                'uploaded_by' => $_SESSION['user_name'] ?? 'Unknown'
            ];
            $pods_json = json_encode($pods_array);

            $update_query = "UPDATE tsp_milestones SET trPODimage = ? WHERE trID = ? AND tracking = ?";
            $stmt = mysqli_prepare($dbConn, $update_query);
            mysqli_stmt_bind_param($stmt, "sis", $pods_json, $trID, $tracking);
            mysqli_stmt_execute($stmt);
        } else {
            $pods_array = [[
                'image'       => $base64_with_prefix,
                'timestamp'   => date('Y-m-d H:i:s'),
                'uploaded_by' => $_SESSION['user_name'] ?? 'Unknown'
            ]];
            $pods_json = json_encode($pods_array);

            $insert_query = "INSERT INTO tsp_milestones (tracking, trPODimage, trStatus, creation_time) 
                             VALUES (?, ?, 'Picked Up', NOW())";
            $stmt = mysqli_prepare($dbConn, $insert_query);
            mysqli_stmt_bind_param($stmt, "ss", $tracking, $pods_json);
            mysqli_stmt_execute($stmt);
        }

        echo json_encode(['success' => true, 'message' => 'POD uploaded successfully!', 'pod_count' => count($pods_array)]);
        mysqli_stmt_close($stmt);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'No file selected.']);
    exit;
}

// =========================================================================
// POD Delete
// =========================================================================
if (isset($_POST['delete_pod'])) {
    header('Content-Type: application/json');
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        exit;
    }

    $tracking  = mysqli_real_escape_string($dbConn, $_POST['tracking']);
    $pod_index = isset($_POST['pod_index']) ? intval($_POST['pod_index']) : -1;

    $query = "SELECT trID, trPODimage FROM tsp_milestones 
              WHERE tracking = ? AND trStatus IN ('Picked Up','Failed Pickup')
              ORDER BY trID DESC LIMIT 1";
    $stmt = mysqli_prepare($dbConn, $query);
    mysqli_stmt_bind_param($stmt, "s", $tracking);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row    = mysqli_fetch_assoc($result);

    if ($row && !empty($row['trPODimage'])) {
        $trID      = $row['trID'];
        $pods_data = json_decode($row['trPODimage'], true);
        if (!is_array($pods_data)) {
            $pods_data = [['image' => $row['trPODimage'], 'timestamp' => 'Legacy', 'uploaded_by' => 'System']];
        }

        array_splice($pods_data, $pod_index, 1);

        if (count($pods_data) > 0) {
            $pods_json    = json_encode($pods_data);
            $update_query = "UPDATE tsp_milestones SET trPODimage = ? WHERE trID = ? AND tracking = ?";
            $stmt = mysqli_prepare($dbConn, $update_query);
            mysqli_stmt_bind_param($stmt, "sis", $pods_json, $trID, $tracking);
        } else {
            $update_query = "UPDATE tsp_milestones SET trPODimage = NULL WHERE trID = ? AND tracking = ?";
            $stmt = mysqli_prepare($dbConn, $update_query);
            mysqli_stmt_bind_param($stmt, "is", $trID, $tracking);
        }
        mysqli_stmt_execute($stmt);
        echo json_encode(['success' => true, 'message' => 'Deleted successfully!', 'remaining' => count($pods_data)]);
        mysqli_stmt_close($stmt);
        exit;
    }
    exit;
}

// =========================================================================
// POD Download
// =========================================================================
if (isset($_GET['download_pod'])) {
    $tracking  = mysqli_real_escape_string($dbConn, $_GET['download_pod']);
    $pod_index = isset($_GET['pod_index']) ? intval($_GET['pod_index']) : 0;

    $query = "SELECT trPODimage FROM tsp_milestones 
              WHERE tracking = ? AND trStatus IN ('Picked Up','Failed Pickup')
              AND trPODimage IS NOT NULL AND trPODimage != ''
              ORDER BY trID DESC LIMIT 1";
    $stmt = mysqli_prepare($dbConn, $query);
    mysqli_stmt_bind_param($stmt, "s", $tracking);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row    = mysqli_fetch_assoc($result);

    if ($row && !empty($row['trPODimage'])) {
        $pods_data = json_decode($row['trPODimage'], true);
        $img_data  = is_array($pods_data) ? $pods_data[$pod_index]['image'] : $row['trPODimage'];

        if (!empty($img_data)) {
            $is_pdf = (strpos($img_data, 'application/pdf') !== false);
            $ext    = $is_pdf ? 'pdf' : 'jpg';
            $mime   = $is_pdf ? 'application/pdf' : 'image/jpeg';

            if (strpos($img_data, ',') !== false) {
                $img_data = explode(',', $img_data)[1];
            }
            $img_data = preg_replace('/\s+/', '', $img_data);

            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="PICKUP_POD_' . $tracking . '_' . ($pod_index + 1) . '.' . $ext . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            echo base64_decode($img_data);
            mysqli_stmt_close($stmt);
            exit;
        }
    }
    mysqli_stmt_close($stmt);
}

// =========================================================================
// AJAX Load Table Data
// =========================================================================
if (isset($_GET['ajax_load_data']) && $_GET['ajax_load_data'] == '1') {
    header('Content-Type: application/json');

    // Get all shipments that have a tsp_milestones record with Picked Up OR Failed Pickup
    $query = "SELECT c.cid, c.tracking, c.invoice_no, c.invoice_id, c.reference_no, c.delivery_aggregator,
                     c.officename, c.ship_name, c.phone, c.correo, c.s_add, c.cc, c.zipcode,
                     c.sender_locality, c.ciudad, c.state,
                     c.rev_name, c.r_phone, c.telefono1, c.email, c.r_add, c.paisdestino, c.zipcode1,
                     c.receiver_locality, c.city1, c.state1,
                     c.qty, c.shipping_subtotal, c.paymode, c.type, c.mode, c.shipping_method,
                     c.dom_internation, c.pesoreal, c.altura, c.ancho, c.longitud, c.totalpeso,
                     c.comments, c.pick_date, c.schedule, c.status, c.book_date,
                     m.trStatus as pickup_status
              FROM courier c
              INNER JOIN tsp_milestones m ON m.tracking = c.tracking
              WHERE m.trStatus IN ('Picked Up', 'Failed Pickup')
              GROUP BY c.cid
              ORDER BY c.book_date DESC";
    $result = mysqli_query($dbConn, $query);
    $data   = [];

    while ($row = mysqli_fetch_assoc($result)) {
        // Check POD for this specific tracking in pickup statuses
        $pod_check_query = "SELECT trPODimage FROM tsp_milestones 
                            WHERE tracking = ? AND trStatus IN ('Picked Up','Failed Pickup')
                            AND trPODimage IS NOT NULL AND trPODimage != ''
                            ORDER BY trID DESC LIMIT 1";
        $pod_stmt = mysqli_prepare($dbConn, $pod_check_query);
        mysqli_stmt_bind_param($pod_stmt, "s", $row['tracking']);
        mysqli_stmt_execute($pod_stmt);
        $pod_result = mysqli_stmt_get_result($pod_stmt);
        $pod_row    = mysqli_fetch_assoc($pod_result);

        $pod_count = 0;
        $pod_types = ['image' => 0, 'pdf' => 0];

        if ($pod_row && !empty($pod_row['trPODimage'])) {
            $pods_data = json_decode($pod_row['trPODimage'], true);
            if (is_array($pods_data)) {
                $pod_count = count($pods_data);
                foreach ($pods_data as $pod) {
                    if (isset($pod['image'])) {
                        if (strpos($pod['image'], 'application/pdf') !== false) {
                            $pod_types['pdf']++;
                        } else {
                            $pod_types['image']++;
                        }
                    }
                }
            } else {
                $pod_count = 1;
                if (strpos($pod_row['trPODimage'], 'application/pdf') !== false) {
                    $pod_types['pdf']++;
                } else {
                    $pod_types['image']++;
                }
            }
        }
        mysqli_stmt_close($pod_stmt);

        $data[] = [
            'cid'                => $row['cid'],
            'tracking'           => $row['tracking'],
            'invoice_no'         => $row['invoice_no']         ?? '',
            'invoice_id'         => $row['invoice_id']         ?? '0.00',
            'reference_no'       => $row['reference_no']       ?? '',
            'delivery_aggregator'=> $row['delivery_aggregator'] ?? '',
            'officename'         => $row['officename']         ?? '',
            'ship_name'          => $row['ship_name']          ?? '',
            'status'             => $row['status']             ?? '',
            'pickup_status'      => $row['pickup_status']      ?? '',
            'phone'              => $row['phone']              ?? '',
            'correo'             => $row['correo']             ?? '',
            's_add'              => $row['s_add']              ?? '',
            'cc'                 => $row['cc']                 ?? '',
            'zipcode'            => $row['zipcode']            ?? '',
            'sender_locality'    => $row['sender_locality']    ?? '',
            'ciudad'             => $row['ciudad']             ?? '',
            'state'              => $row['state']              ?? '',
            'rev_name'           => $row['rev_name']           ?? '',
            'r_phone'            => $row['r_phone']            ?? '',
            'telefono1'          => $row['telefono1']          ?? '',
            'email'              => $row['email']              ?? '',
            'r_add'              => $row['r_add']              ?? '',
            'paisdestino'        => $row['paisdestino']        ?? '',
            'zipcode1'           => $row['zipcode1']           ?? '',
            'receiver_locality'  => $row['receiver_locality']  ?? '',
            'city1'              => $row['city1']              ?? '',
            'state1'             => $row['state1']             ?? '',
            'qty'                => $row['qty']                ?? '1',
            'shipping_subtotal'  => $row['shipping_subtotal']  ?? '0.00',
            'paymode'            => $row['paymode']            ?? '',
            'type'               => $row['type']               ?? '',
            'mode'               => $row['mode']               ?? '',
            'shipping_method'    => $row['shipping_method']    ?? '',
            'dom_internation'    => $row['dom_internation']    ?? '',
            'pesoreal'           => $row['pesoreal']           ?? '0.00',
            'altura'             => $row['altura']             ?? '0.00',
            'ancho'              => $row['ancho']              ?? '0.00',
            'longitud'           => $row['longitud']           ?? '0.00',
            'totalpeso'          => $row['totalpeso']          ?? '0.00',
            'comments'           => $row['comments']           ?? '',
            'pick_date'          => $row['pick_date']          ?? '',
            'schedule'           => $row['schedule']           ?? '',
            'book_date'          => $row['book_date']          ?? '',
            'has_pod'            => ($pod_count > 0),
            'pod_count'          => $pod_count,
            'pod_images'         => $pod_types['image'],
            'pod_pdfs'           => $pod_types['pdf']
        ];
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// =========================================================================
// Export Logic
// =========================================================================
if (isset($_POST['export_excel']) || isset($_POST['export_selected'])) {
    if (ob_get_level()) ob_end_clean();
    $exportType = isset($_POST['export_selected']) ? 'selected' : 'all';

    $baseQuery = "SELECT c.* FROM courier c
                  INNER JOIN tsp_milestones m ON m.tracking = c.tracking
                  WHERE m.trStatus IN ('Picked Up','Failed Pickup')";

    if ($exportType === 'selected' && !empty($_POST['selected_tracking'])) {
        $selectedTrackings = array_map(function($t) use ($dbConn) {
            return "'" . mysqli_real_escape_string($dbConn, $t) . "'";
        }, $_POST['selected_tracking']);
        $baseQuery .= " AND c.tracking IN (" . implode(',', $selectedTrackings) . ")";
        $filename = "selected_pickups_" . date('Y-m-d_His') . ".xls";
    } else {
        $filename = "all_pickups_" . date('Y-m-d_His') . ".xls";
    }
    $baseQuery .= " GROUP BY c.cid ORDER BY c.book_date DESC";
    $result = mysqli_query($dbConn, $baseQuery);

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo '<table border="1"><tr>
        <th>Tracking</th><th>Invoice No.</th><th>Invoice Value</th><th>Client</th>
        <th>Date</th><th>Reference</th><th>Aggregator</th><th>Recipient</th>
        <th>Type</th><th>Origin</th><th>Destination</th><th>Amount</th>
        <th>Status</th><th>Method</th>
    </tr>';
    while ($row = mysqli_fetch_assoc($result)) {
        echo '<tr>
            <td>' . htmlspecialchars($row['tracking']) . '</td>
            <td>' . htmlspecialchars($row['invoice_no'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['invoice_id'] ?? '0.00') . '</td>
            <td>' . htmlspecialchars($row['ship_name']) . '</td>
            <td>' . htmlspecialchars($row['book_date']) . '</td>
            <td>' . htmlspecialchars($row['reference_no']) . '</td>
            <td>' . htmlspecialchars($row['delivery_aggregator']) . '</td>
            <td>' . htmlspecialchars($row['rev_name']) . '</td>
            <td>' . htmlspecialchars($row['dom_internation']) . '</td>
            <td>' . htmlspecialchars($row['ciudad']) . '</td>
            <td>' . htmlspecialchars($row['city1']) . '</td>
            <td>' . htmlspecialchars($row['shipping_subtotal']) . '</td>
            <td>' . htmlspecialchars($row['status']) . '</td>
            <td>' . htmlspecialchars($row['shipping_method']) . '</td>
        </tr>';
    }
    echo '</table>';
    exit;
}

// =========================================================================
// Page stats
// =========================================================================
$total_pickup_query = mysqli_query($dbConn,
    "SELECT COUNT(*) as total FROM tsp_milestones WHERE trStatus = 'Picked Up'"
);
$total_pickup = mysqli_fetch_assoc($total_pickup_query)['total'];

$total_failed_query = mysqli_query($dbConn,
    "SELECT COUNT(*) as total FROM tsp_milestones WHERE trStatus = 'Failed Pickup'"
);
$total_failed = mysqli_fetch_assoc($total_failed_query)['total'];

$shippingMethods = mysqli_fetch_all(mysqli_query($dbConn,
    "SELECT DISTINCT c.shipping_method FROM courier c
     INNER JOIN tsp_milestones m ON m.tracking = c.tracking
     WHERE m.trStatus IN ('Picked Up','Failed Pickup') AND c.shipping_method != ''
     ORDER BY c.shipping_method ASC"), MYSQLI_ASSOC);

$aggregators = mysqli_fetch_all(mysqli_query($dbConn,
    "SELECT DISTINCT c.delivery_aggregator FROM courier c
     INNER JOIN tsp_milestones m ON m.tracking = c.tracking
     WHERE m.trStatus IN ('Picked Up','Failed Pickup') AND c.delivery_aggregator != ''
     ORDER BY c.delivery_aggregator ASC"), MYSQLI_ASSOC);

$types = mysqli_fetch_all(mysqli_query($dbConn,
    "SELECT DISTINCT c.dom_internation FROM courier c
     INNER JOIN tsp_milestones m ON m.tracking = c.tracking
     WHERE m.trStatus IN ('Picked Up','Failed Pickup') AND c.dom_internation != ''
     ORDER BY c.dom_internation ASC"), MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Pickup Management - ABRA Logistics</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <link rel="shortcut icon" type="image/png" href="img/favicon.png"/>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap.min.css">

  <style>
    :root {
        --primary: #667eea;
        --primary-dark: #5a67d8;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #3b82f6;
        --dark: #1e293b;
        --light: #f8fafc;
        /* Pickup theme colours */
        --pickup-color: #3b82f6;   /* blue  - Picked Up   */
        --failed-color: #f59e0b;   /* amber - Failed Pickup */
        --modal-color: #3b82f6;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #001f3f 0%, #003d7a 100%);
        min-height: 100vh;
        color: #334155;
    }

    /* ── HEADER ─────────────────────────────────────────────── */
    .page-header-bar {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: white;
        padding: 16px 0;
        box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        position: sticky;
        top: 0;
        z-index: 1000;
        border-bottom: 3px solid var(--pickup-color);
    }
    .header-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .brand-area h1 {
        font-size: 24px;
        font-weight: 900;
        background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        letter-spacing: -0.5px;
        margin: 0;
    }
    .btn-back {
        background: linear-gradient(135deg, var(--info) 0%, #2563eb 100%);
        border: none;
        padding: 10px 24px;
        border-radius: 12px;
        color: white;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(59,130,246,0.3);
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
    }
    .btn-back:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(59,130,246,0.4);
        color: white;
        text-decoration: none;
    }

    /* ── MAIN ───────────────────────────────────────────────── */
    .main-wrapper { max-width: 1400px; margin: 30px auto; padding: 0 30px; }

    .page-intro {
        background: white;
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 25px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    }
    .intro-title {
        font-size: 32px;
        font-weight: 900;
        color: var(--dark);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .intro-title i { color: var(--pickup-color); font-size: 36px; }

    /* ── STATS ──────────────────────────────────────────────── */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 25px;
    }
    .stat-box {
        background: white;
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: all 0.3s;
    }
    .stat-box:hover { transform: translateY(-5px); box-shadow: 0 15px 50px rgba(0,0,0,0.12); }
    .stat-icon {
        width: 70px; height: 70px;
        border-radius: 16px;
        display: flex; align-items: center; justify-content: center;
        font-size: 32px; color: white;
    }
    .stat-box:nth-child(1) .stat-icon { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
    .stat-box:nth-child(2) .stat-icon { background: linear-gradient(135deg, var(--pickup-color) 0%, #2563eb 100%); }
    .stat-box:nth-child(3) .stat-icon { background: linear-gradient(135deg, var(--failed-color) 0%, #d97706 100%); }
    .stat-content h3 { margin: 0; font-size: 32px; font-weight: 900; color: var(--dark); line-height: 1; }
    .stat-content p  { margin: 5px 0 0 0; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }

    /* ── CONTROL PANEL ──────────────────────────────────────── */
    .control-box {
        background: white;
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 25px;
        box-shadow: 0 5px 25px rgba(0,0,0,0.08);
    }
    .control-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .control-title  { margin: 0; font-weight: 800; font-size: 18px; color: var(--dark); }
    .control-title i { color: var(--primary); margin-right: 8px; }

    .export-wrapper { position: relative; display: inline-block; }
    .btn-export {
        background: linear-gradient(135deg, var(--info) 0%, #2563eb 100%);
        border: none; padding: 12px 28px; border-radius: 12px;
        color: white; font-weight: 700; font-size: 14px;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(59,130,246,0.3);
        cursor: pointer;
    }
    .btn-export:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,0.4); }

    .export-menu {
        position: absolute; top: 100%; right: 0;
        background: white; border-radius: 14px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        z-index: 1000; min-width: 240px;
        display: none; margin-top: 10px;
        overflow: hidden; border: 1px solid #e2e8f0;
    }
    .export-menu.active { display: block; animation: slideDown 0.3s ease; }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .export-menu button {
        width: 100%; text-align: left; border: none; background: none;
        padding: 14px 20px; color: #334155; font-size: 13px; font-weight: 600;
        transition: all 0.2s; border-bottom: 1px solid #f1f5f9; cursor: pointer;
    }
    .export-menu button:last-child { border-bottom: none; }
    .export-menu button:hover { background: #f8fafc; color: var(--info); padding-left: 24px; }

    /* ── FILTERS ────────────────────────────────────────────── */
    .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; }
    .filter-item label { font-weight: 700; font-size: 11px; color: #64748b; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; display: block; }
    .filter-input, .filter-select {
        width: 100%; padding: 12px 16px;
        border: 2px solid #e2e8f0; border-radius: 10px;
        font-size: 13px; font-weight: 500; transition: all 0.3s; background: white;
    }
    .filter-input:focus, .filter-select:focus {
        border-color: var(--pickup-color);
        outline: none;
        box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
    }
    .btn-clear {
        background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
        border: none; padding: 12px 24px; border-radius: 10px;
        color: white; font-weight: 700; font-size: 13px;
        cursor: pointer; transition: all 0.3s; margin-top: 28px; width: 100%;
    }
    .btn-clear:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(239,68,68,0.3); }

    /* ── TABLE ──────────────────────────────────────────────── */
    .table-box {
        background: white; border-radius: 20px; padding: 30px;
        box-shadow: 0 5px 25px rgba(0,0,0,0.08); overflow: hidden;
    }
    .table-responsive { overflow-x: auto; }
    .data-table { width: 100%; margin-bottom: 0; }
    .data-table thead th {
        background: linear-gradient(135deg, #334155 0%, #1e293b 100%);
        color: white; font-weight: 800; font-size: 11px;
        text-transform: uppercase; letter-spacing: 0.5px;
        padding: 16px 14px !important; border: none; white-space: nowrap;
    }
    .data-table tbody td { padding: 16px 14px !important; vertical-align: middle; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
    .data-table tbody tr:hover { background: #f8fafc; }

    .tracking-link { color: var(--info); font-weight: 700; text-decoration: none; transition: all 0.2s; }
    .tracking-link:hover { color: #2563eb; text-decoration: underline; }

    /* Pickup status badges */
    .badge-picked-up {
        background: linear-gradient(135deg, var(--pickup-color) 0%, #2563eb 100%);
        color: white; padding: 6px 14px; border-radius: 20px;
        font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
    }
    .badge-failed-pickup {
        background: linear-gradient(135deg, var(--failed-color) 0%, #d97706 100%);
        color: white; padding: 6px 14px; border-radius: 20px;
        font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
    }

    .icon-action { width: 28px; height: 28px; transition: all 0.3s; cursor: pointer; display: inline-block; }
    .icon-action:hover { transform: scale(1.2); filter: brightness(1.15); }

    /* ── POD ICONS ──────────────────────────────────────────── */
    .pod-icon-wrapper {
        position: relative; display: inline-flex;
        align-items: center; justify-content: center;
        cursor: pointer; width: 50px; height: 50px;
    }
    .pod-icon-main { font-size: 28px; transition: all 0.3s; }
    .pod-icon-wrapper:hover .pod-icon-main { transform: scale(1.2); }

    .pod-icon-none  { color: #cbd5e1; }
    .pod-icon-none:hover { color: #94a3b8; }
    .pod-icon-image { color: #3b82f6; }   /* blue for pickup images  */
    .pod-icon-pdf   { color: #ef4444; }
    .pod-icon-mixed { color: #f59e0b; }

    .pod-notification-badge {
        position: absolute; top: -5px; right: -5px;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white; border-radius: 50%;
        min-width: 24px; height: 24px;
        display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 800;
        border: 2px solid white;
        box-shadow: 0 2px 8px rgba(239,68,68,0.4);
        animation: pulseNotification 2s infinite;
    }
    @keyframes pulseNotification {
        0%, 100% { transform: scale(1); }
        50%      { transform: scale(1.1); }
    }

    /* ── MODAL ──────────────────────────────────────────────── */
    .pod-modal {
        display: none; position: fixed; top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.85); z-index: 9999;
        align-items: center; justify-content: center;
        backdrop-filter: blur(5px);
    }
    .modal-box {
        background: white; width: 90%; max-width: 750px;
        max-height: 90vh; border-radius: 24px; overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        display: flex; flex-direction: column;
    }
    .modal-header-bar {
        background: linear-gradient(135deg, var(--modal-color) 0%, #2563eb 100%);
        color: white; padding: 24px 30px;
        display: flex; justify-content: space-between; align-items: center;
    }
    .modal-header-bar h4 { margin: 0; font-weight: 800; font-size: 18px; }
    .btn-modal-close {
        background: rgba(255,255,255,0.2); border: none; color: white;
        font-size: 24px; width: 38px; height: 38px;
        border-radius: 50%; cursor: pointer; transition: all 0.3s;
    }
    .btn-modal-close:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }

    .modal-content-area {
        padding: 30px; overflow-y: auto;
        max-height: calc(90vh - 80px);
    }
    .modal-content-area::-webkit-scrollbar { width: 8px; }
    .modal-content-area::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
    .modal-content-area::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .modal-content-area::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    .pod-gallery-item {
        position: relative; background: #f8fafc;
        border-radius: 12px; overflow: hidden;
        border: 2px solid #e2e8f0; transition: all 0.3s;
    }
    .pod-gallery-item:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.15); border-color: var(--modal-color); }
    .pod-gallery-item img { width: 100%; height: 180px; object-fit: cover; cursor: pointer; }
    .pod-gallery-info { padding: 12px; background: white; }
    .pod-gallery-info .pod-timestamp { font-size: 11px; color: #64748b; font-weight: 600; margin-bottom: 5px; }
    .pod-gallery-info .pod-uploader  { font-size: 10px; color: #94a3b8; margin-bottom: 10px; }
    .pod-gallery-actions { display: flex; gap: 8px; }
    .pod-gallery-actions button,
    .pod-gallery-actions a {
        flex: 1; padding: 8px 12px; border-radius: 8px; border: none;
        font-size: 11px; font-weight: 700; cursor: pointer;
        transition: all 0.3s; text-decoration: none; text-align: center;
    }
    .pod-gallery-btn-download {
        background: linear-gradient(135deg, var(--success) 0%, #059669 100%); color: white;
    }
    .pod-gallery-btn-download:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16,185,129,0.3); color: white; }
    .pod-gallery-btn-delete {
        background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%); color: white;
    }
    .pod-gallery-btn-delete:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(239,68,68,0.3); }

    .no-pod-msg { text-align: center; padding: 50px 20px; color: #94a3b8; }
    .no-pod-msg i { font-size: 72px; margin-bottom: 18px; opacity: 0.4; }
    .no-pod-msg h4 { font-weight: 700; color: #64748b; }

    .pdf-placeholder {
        height: 180px; display: flex; align-items: center; justify-content: center;
        background: #f1f5f9; color: #ef4444; font-size: 54px; cursor: pointer;
    }
    .pdf-placeholder:hover { background: #e2e8f0; }

    /* ── UPLOAD AREA ────────────────────────────────────────── */
    .upload-area {
        background: #f8fafc; padding: 24px; border-radius: 16px; border: 2px dashed #cbd5e1;
    }
    .upload-area h5 { margin: 0 0 18px 0; font-weight: 800; font-size: 15px; color: var(--dark); }
    .file-input-wrap { position: relative; overflow: hidden; display: inline-block; width: 100%; }
    .btn-file-select {
        background: linear-gradient(135deg, var(--info) 0%, #2563eb 100%);
        color: white; padding: 14px 28px; border-radius: 10px;
        font-weight: 700; cursor: pointer; display: inline-block;
        transition: all 0.3s; box-shadow: 0 4px 15px rgba(59,130,246,0.3);
    }
    .btn-file-select:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,0.4); }
    .file-input-wrap input[type=file] { position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
    .file-selected-name { margin-top: 15px; font-size: 13px; color: #64748b; font-weight: 500; }
    .btn-upload-submit {
        background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
        color: white; border: none; padding: 14px 32px; border-radius: 10px;
        font-weight: 700; font-size: 14px; cursor: pointer;
        transition: all 0.3s; margin-top: 18px;
        box-shadow: 0 4px 15px rgba(16,185,129,0.3);
    }
    .btn-upload-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16,185,129,0.4); }
    .btn-upload-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

    /* ── COURIER SLIDE PANEL ────────────────────────────────── */
    .courier-panel-overlay {
        position: fixed; inset: 0;
        background: rgba(10,18,40,0.6); backdrop-filter: blur(4px);
        z-index: 9998; opacity: 0; pointer-events: none; transition: opacity 0.3s;
    }
    .courier-panel-overlay.open { opacity: 1; pointer-events: all; }
    .courier-panel-container {
        position: fixed; top: 0; right: 0; height: 100vh; width: 900px; max-width: 95vw;
        background: #ffffff; box-shadow: -4px 0 24px rgba(0,0,0,0.15);
        z-index: 9999; transform: translateX(100%);
        transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
        display: flex; flex-direction: column;
    }
    .courier-panel-container.open { transform: translateX(0); }
    .courier-panel-header {
        padding: 20px 30px;
        background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
        color: #ffffff; display: flex; justify-content: space-between; align-items: center;
        flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .courier-panel-header h3 { margin: 0; font-size: 1.25rem; font-weight: 800; display: flex; align-items: center; gap: 10px; }
    .courier-panel-close {
        background: rgba(255,255,255,0.2); border: none; color: #ffffff;
        font-size: 28px; width: 40px; height: 40px; border-radius: 8px;
        cursor: pointer; display: flex; align-items: center; justify-content: center;
        transition: all 0.2s; line-height: 1; padding: 0;
    }
    .courier-panel-close:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }
    .courier-panel-content { flex: 1; overflow-y: auto; overflow-x: hidden; }
  </style>
</head>
<body>

<!-- ── HEADER ─────────────────────────────────────────────────── -->
<div class="page-header-bar">
    <div class="header-container">
        <div class="brand-area">
            <h1>ABRA LOGISTICS</h1>
        </div>
        <a href="<?php echo $backUrl; ?>" class="btn-back"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
    </div>
</div>

<div class="main-wrapper">

    <div class="page-intro">
        <h1 class="intro-title"><i class="fa fa-truck-loading"></i> Pickup Management</h1>
    </div>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-icon"><i class="fa fa-database"></i></div>
            <div class="stat-content">
                <h3><?php echo number_format($total_pickup + $total_failed); ?></h3>
                <p>Total Pickups</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fa fa-truck"></i></div>
            <div class="stat-content">
                <h3><?php echo number_format($total_pickup); ?></h3>
                <p>Picked Up</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fa fa-exclamation-triangle"></i></div>
            <div class="stat-content">
                <h3><?php echo number_format($total_failed); ?></h3>
                <p>Failed Pickup</p>
            </div>
        </div>
    </div>

    <form method="post" id="shipmentsForm">
        <!-- FILTERS & EXPORT -->
        <div class="control-box">
            <div class="control-header">
                <h3 class="control-title"><i class="fa fa-filter"></i> Filters &amp; Export</h3>
                <div class="export-wrapper">
                    <button type="button" class="btn-export" id="exportToggle">
                        <i class="fa fa-file-excel"></i> Export <i class="fa fa-caret-down"></i>
                    </button>
                    <div class="export-menu" id="exportMenu">
                        <button type="button" id="btnExportAll">
                            <i class="fa fa-download"></i> Export All Records
                        </button>
                        <button type="button" id="btnExportSelected">
                            <i class="fa fa-check-square"></i> Export Selected (<span id="countSelected">0</span>)
                        </button>
                    </div>
                </div>
            </div>

            <div class="filter-grid">
                <div class="filter-item">
                    <label>Global Search</label>
                    <input type="text" id="searchGlobal" class="filter-input" placeholder="Search all fields...">
                </div>
                <div class="filter-item">
                    <label>Pickup Status</label>
                    <select id="filterPickupStatus" class="filter-select">
                        <option value="">All Statuses</option>
                        <option value="Picked Up">Picked Up</option>
                        <option value="Failed Pickup">Failed Pickup</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Shipping Method</label>
                    <select id="filterMethod" class="filter-select">
                        <option value="">All Methods</option>
                        <?php foreach ($shippingMethods as $m): ?>
                            <option value="<?php echo htmlspecialchars($m['shipping_method']); ?>">
                                <?php echo htmlspecialchars($m['shipping_method']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Delivery Aggregator</label>
                    <select id="filterAggregator" class="filter-select">
                        <option value="">All Aggregators</option>
                        <?php foreach ($aggregators as $a): ?>
                            <option value="<?php echo htmlspecialchars($a['delivery_aggregator']); ?>">
                                <?php echo htmlspecialchars($a['delivery_aggregator']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Shipment Type</label>
                    <select id="filterType" class="filter-select">
                        <option value="">All Types</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?php echo htmlspecialchars($t['dom_internation']); ?>">
                                <?php echo htmlspecialchars($t['dom_internation']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label>From Date</label>
                    <input type="date" id="filterDateFrom" class="filter-input">
                </div>
                <div class="filter-item">
                    <label>To Date</label>
                    <input type="date" id="filterDateTo" class="filter-input">
                </div>
                <div class="filter-item">
                    <button type="button" class="btn-clear" id="btnClearAll">
                        <i class="fa fa-times"></i> Clear All Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="table-box">
            <div class="table-responsive">
                <table id="mainTable" class="data-table table table-striped">
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" id="selectAll"></th>
                            <th>Tracking</th>
                            <th>Invoice No.</th>
                            <th>Invoice Value</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Aggregator</th>
                            <th>Recipient</th>
                            <th>Type</th>
                            <th>Origin</th>
                            <th>Destination</th>
                            <th>Amount</th>
                            <th>Pickup Status</th>
                            <th>Method</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr id="loadingRow">
                            <td colspan="16" style="text-align:center; padding:40px;">
                                <i class="fa fa-spinner fa-spin" style="font-size:36px; color:var(--pickup-color); margin-bottom:15px;"></i>
                                <p style="font-weight:600; color:#64748b; font-size:15px; margin:0;">Loading pickups...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<!-- ── POD MODAL ──────────────────────────────────────────────── -->
<div id="podModal" class="pod-modal" onclick="closePodModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header-bar">
            <h4>
                <i class="fa fa-truck"></i>
                Pickup POD - <span id="trackingDisplay"></span>
                <span id="podCount" style="font-size:12px; opacity:0.8;"></span>
            </h4>
            <button class="btn-modal-close" onclick="closePodModal()">&times;</button>
        </div>
        <div class="modal-content-area">
            <div id="podGallery" style="display:none;">
                <h5 style="margin:0 0 15px 0; font-weight:800; color:var(--dark);">
                    <i class="fa fa-images"></i> POD Gallery
                    (<span id="galleryCount">0</span> documents)
                </h5>
                <div id="podGalleryGrid"
                     style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px,1fr)); gap:15px; margin-bottom:25px;">
                </div>
            </div>
            <div id="noPodView" class="no-pod-msg" style="display:none;">
                <i class="fa fa-truck"></i>
                <h4>No POD Available</h4>
                <p>Upload a pickup proof (Image or PDF) using the form below</p>
            </div>
            <div class="upload-area">
                <h5><i class="fa fa-cloud-upload-alt"></i> <span id="uploadTitle">Upload Pickup Proof</span></h5>
                <input type="hidden" id="uploadTracking">
                <div class="file-input-wrap">
                    <label class="btn-file-select"><i class="fa fa-folder-open"></i> Choose Image or PDF File</label>
                    <input type="file" id="fileInput" accept="image/*,application/pdf" onchange="displayFileName()">
                </div>
                <div id="fileName" class="file-selected-name"></div>
                <button onclick="uploadPodFile()" class="btn-upload-submit" id="btnUpload">
                    <i class="fa fa-upload"></i> Upload Document
                </button>
                <div id="uploadProgressBar" style="display:none; margin-top:18px;">
                    <div style="background:#e2e8f0; border-radius:10px; height:22px; overflow:hidden;">
                        <div id="progressBarFill" style="background:linear-gradient(135deg, var(--success) 0%, #059669 100%); height:100%; width:0%; transition:width 0.3s;"></div>
                    </div>
                    <p id="uploadStatusText" style="margin-top:12px; font-weight:700; color:var(--success); font-size:13px;"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── COURIER SLIDE PANEL ────────────────────────────────────── -->
<div id="courierPanelOverlay" class="courier-panel-overlay" onclick="closeCourierPanel()"></div>
<div id="courierPanelContainer" class="courier-panel-container">
    <div class="courier-panel-header">
        <h3><i class="fa fa-pencil-alt"></i> Edit Shipment</h3>
        <button class="courier-panel-close" onclick="closeCourierPanel()" title="Close (Esc)">&times;</button>
    </div>
    <div id="courierPanelContent" class="courier-panel-content"></div>
</div>

<!-- ── SCRIPTS ─────────────────────────────────────────────────── -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap.min.js"></script>

<script>
var dataTable;
var currency  = '<?php echo $currency; ?>';
var isAdmin   = <?php echo $isAdmin ? 'true' : 'false'; ?>;
var isAbhishek = isAdmin;

// ── Date filter ────────────────────────────────────────────────
$.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
    var dateFrom = $('#filterDateFrom').val();
    var dateTo   = $('#filterDateTo').val();
    var bookDate = data[5] || '';          // col 5 = Date
    if (!dateFrom && !dateTo) return true;
    var d  = new Date(bookDate);
    var df = dateFrom ? new Date(dateFrom) : null;
    var dt = dateTo   ? new Date(dateTo)   : null;
    if (df && d < df) return false;
    if (dt && d > dt) return false;
    return true;
});

$(document).ready(function() {
    loadTableData();

    $('#exportToggle').on('click', function(e) { e.stopPropagation(); $('#exportMenu').toggleClass('active'); });
    $(document).on('click', function() { $('#exportMenu').removeClass('active'); });

    $('#btnExportAll').on('click', function() {
        $('<form method="POST"><input type="hidden" name="export_excel" value="1"></form>').appendTo('body').submit();
    });
    $('#btnExportSelected').on('click', function() {
        var selected = $('.select-item:checked');
        if (selected.length === 0) { alert('Please select records!'); return; }
        var form = $('<form method="POST"><input type="hidden" name="export_selected" value="1"></form>');
        selected.each(function() {
            form.append('<input type="hidden" name="selected_tracking[]" value="' + $(this).val() + '">');
        });
        form.appendTo('body').submit();
    });
});

// ── Load table ─────────────────────────────────────────────────
function loadTableData() {
    $.ajax({
        url: '?ajax_load_data=1',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                $('#loadingRow').remove();
                var tableData = [];

                response.data.forEach(function(row) {
                    var encodedCid = btoa(row.cid);
                    var podCell    = generatePodIcon(row);

                    var invoiceIcon = '<a href="print-invoice/invoice-print.php?cid=' + encodedCid + '" target="_blank">' +
                        '<img src="img/print.png" class="icon-action" title="Print Invoice" style="width:28px;height:28px;"></a>';

                    // Pickup status badge
                    var pStatus = row.pickup_status || '';
                    var badgeClass = (pStatus === 'Picked Up') ? 'badge-picked-up' : 'badge-failed-pickup';
                    var pickupBadge = '<span class="' + badgeClass + '">' + escapeHtml(pStatus) + '</span>';

                    tableData.push([
                        '<input type="checkbox" name="selected_tracking[]" class="select-item" value="' + escapeHtml(row.tracking) + '">',
                        '<a href="https://www.abra-logistic.com/tracking.php?shipping=' + encodeURIComponent(row.tracking) + '" target="_blank" class="tracking-link">' + escapeHtml(row.tracking) + '</a>',
                        escapeHtml(row.invoice_no || '—'),
                        '<strong>' + currency + parseFloat(row.invoice_id || 0).toFixed(2) + '</strong>',
                        '<strong>' + escapeHtml(row.ship_name) + '</strong>',
                        escapeHtml(row.book_date),
                        escapeHtml(row.reference_no),
                        escapeHtml(row.delivery_aggregator),
                        escapeHtml(row.rev_name),
                        escapeHtml(row.dom_internation),
                        escapeHtml(row.ciudad),
                        escapeHtml(row.city1),
                        '<strong>' + currency + escapeHtml(row.shipping_subtotal) + '</strong>',
                        pickupBadge,
                        escapeHtml(row.shipping_method),
                        '<div style="display:flex;gap:8px;flex-wrap:nowrap;align-items:center;padding:5px 0;">' +
                        '<a href="javascript:void(0)" onclick="openEditCourierPanel(' + row.cid + ')">' +
                            '<img src="img/edit.png" class="icon-action" title="Edit" style="width:28px;height:28px;"></a>' +
                        invoiceIcon +
                        '<a href="print-invoice/ticket-print.php?cid=' + encodedCid + '" target="_blank">' +
                            '<img src="img/ticket.png" class="icon-action" title="Shipping Label" style="width:28px;height:28px;"></a>' +
                        podCell +
                        (isAbhishek ?
                            '<a href="#" onclick="if(confirm(\'Delete shipment?\')) window.location=\'deletes/delete_shipment.php?cid=' + row.cid + '\'; return false;">' +
                            '<img src="img/delete.png" class="icon-action" title="Delete" style="width:28px;height:28px;"></a>'
                            : '') +
                        '</div>'
                    ]);
                });

                dataTable = $('#mainTable').DataTable({
                    data: tableData,
                    pageLength: 50,
                    order: [[5, 'desc']],
                    scrollX: true,
                    autoWidth: false,
                    columnDefs: [
                        { orderable: false, targets: [0, 15] },
                        { width:  '50px', targets: 0  },
                        { width: '150px', targets: 1  },
                        { width: '140px', targets: 2  },
                        { width: '120px', targets: 3  },
                        { width: '180px', targets: 4  },
                        { width: '120px', targets: 5  },
                        { width: '140px', targets: 6  },
                        { width: '140px', targets: 7  },
                        { width: '180px', targets: 8  },
                        { width: '100px', targets: 9  },
                        { width: '140px', targets: 10 },
                        { width: '140px', targets: 11 },
                        { width: '100px', targets: 12 },
                        { width: '140px', targets: 13 },
                        { width: '140px', targets: 14 },
                        { width: '280px', targets: 15 }
                    ],
                    drawCallback: function() { updateCount(); },
                    language: { emptyTable: "No pickup records found" }
                });
                initializeFilters();
            }
        }
    });
}

// ── Generate POD icon ──────────────────────────────────────────
function generatePodIcon(row) {
    var tracking  = escapeHtml(row.tracking);
    var podCount  = row.pod_count  || 0;
    var imageCount = row.pod_images || 0;
    var pdfCount  = row.pod_pdfs   || 0;

    if (podCount === 0) {
        return '<div class="pod-icon-wrapper" onclick="openPodModal(\'' + tracking + '\')" ' +
               'data-tracking="' + tracking + '" data-pod-count="0">' +
               '<i class="fa fa-truck pod-icon-main pod-icon-none" title="No POD - Click to upload"></i></div>';
    }

    var iconClass = '';
    var iconTitle = '';
    if (imageCount > 0 && pdfCount > 0) {
        iconClass = 'fa fa-folder-open pod-icon-main pod-icon-mixed';
        iconTitle = imageCount + ' Image(s) + ' + pdfCount + ' PDF(s)';
    } else if (pdfCount > 0) {
        iconClass = 'fa fa-file-pdf pod-icon-main pod-icon-pdf';
        iconTitle = pdfCount + ' PDF Document(s)';
    } else {
        iconClass = 'fa fa-truck pod-icon-main pod-icon-image';
        iconTitle = imageCount + ' Image(s)';
    }

    return '<div class="pod-icon-wrapper" onclick="openPodModal(\'' + tracking + '\')" ' +
           'data-tracking="' + tracking + '" data-pod-count="' + podCount + '" title="' + iconTitle + '">' +
           '<i class="' + iconClass + '"></i>' +
           '<span class="pod-notification-badge">' + podCount + '</span>' +
           '</div>';
}

function escapeHtml(text) {
    if (!text) return '';
    var map = { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

// ── Filters ────────────────────────────────────────────────────
function initializeFilters() {
    $('#searchGlobal').on('keyup', function() { dataTable.search(this.value).draw(); });

    // Pickup status filter searches column 13 (pickup status badge text)
    $('#filterPickupStatus').on('change', function() { dataTable.column(13).search(this.value).draw(); });
    $('#filterMethod').on('change',       function() { dataTable.column(14).search(this.value).draw(); });
    $('#filterAggregator').on('change',   function() { dataTable.column(7).search(this.value).draw(); });
    $('#filterType').on('change',         function() { dataTable.column(9).search(this.value).draw(); });
    $('#filterDateFrom, #filterDateTo').on('change', function() { dataTable.draw(); });

    $('#btnClearAll').on('click', function() {
        $('#searchGlobal, #filterPickupStatus, #filterMethod, #filterAggregator, #filterType, #filterDateFrom, #filterDateTo').val('');
        dataTable.search('').columns().search('').draw();
    });

    $('#selectAll').on('click', function() { $('.select-item').prop('checked', this.checked); updateCount(); });
    $(document).on('change', '.select-item', updateCount);
}

function updateCount() { $('#countSelected').text($('.select-item:checked').length); }

// ── POD Modal ──────────────────────────────────────────────────
var currentTrackingNum = '';
var currentPods = [];

function openPodModal(tracking) {
    currentTrackingNum = tracking;
    $('#uploadTracking').val(tracking);
    $('#trackingDisplay').text(tracking);
    $('#fileInput').val('');
    $('#fileName').text('');
    $('#uploadProgressBar').hide();
    $('#btnUpload').prop('disabled', false).html('<i class="fa fa-upload"></i> Upload Document');

    $.ajax({
        url: '?ajax_get_pod=1&tracking=' + encodeURIComponent(tracking),
        dataType: 'json',
        success: function(res) {
            if (res.success && res.has_pod && res.pods.length > 0) {
                currentPods = res.pods;
                displayPodGallery(res.pods, res.is_admin);
                $('#podGallery').show();
                $('#noPodView').hide();
                $('#uploadTitle').text('Add Another Proof');
                $('#podCount').text('(' + res.pods.length + ' Docs)');
            } else {
                currentPods = [];
                $('#podGallery').hide();
                $('#noPodView').show();
                $('#uploadTitle').text('Upload Pickup Proof');
                $('#podCount').text('');
            }
            $('.modal-content-area').scrollTop(0);
        }
    });
    document.getElementById('podModal').style.display = 'flex';
}

function displayPodGallery(pods, showDeleteButton) {
    var grid = $('#podGalleryGrid');
    grid.empty();
    $('#galleryCount').text(pods.length);

    pods.forEach(function(pod, index) {
        var timestamp = pod.timestamp   || 'N/A';
        var uploader  = pod.uploaded_by || 'Unknown';
        var isPdf     = (pod.image && pod.image.indexOf('application/pdf') !== -1);
        var isUrl     = pod.is_url || (pod.image && (pod.image.indexOf('http://') === 0 || pod.image.indexOf('https://') === 0));

        // Use URL directly if available, else route through get_pod_image endpoint
        var imgSrc = isUrl
            ? pod.image
            : '?get_pod_image=' + encodeURIComponent(currentTrackingNum) + '&pod_index=' + index + '&t=' + Date.now();

        var thumbContent = isPdf
            ? '<div class="pdf-placeholder" onclick="viewFullPod(' + index + ')"><i class="fa fa-file-pdf"></i><div style="font-size:12px;margin-top:5px;">PDF Document</div></div>'
            : '<img src="' + imgSrc + '" onclick="viewFullPod(' + index + ')" onerror="this.style.opacity=\'0.3\'">';

        var deleteBtn = showDeleteButton
            ? '<button onclick="deletePodConfirm(' + index + ')" class="pod-gallery-btn-delete"><i class="fa fa-trash"></i></button>'
            : '';

        var downloadHref = isUrl
            ? pod.image
            : '?download_pod=' + encodeURIComponent(currentTrackingNum) + '&pod_index=' + index;

        grid.append(
            '<div class="pod-gallery-item">' +
            thumbContent +
            '<div class="pod-gallery-info">' +
            '<div class="pod-timestamp"><i class="fa fa-clock"></i> ' + escapeHtml(timestamp) + '</div>' +
            '<div class="pod-uploader"><i class="fa fa-user"></i> '   + escapeHtml(uploader)  + '</div>' +
            '<div class="pod-gallery-actions">' +
            '<a href="' + downloadHref + '" ' + (isUrl ? 'target="_blank"' : '') + ' class="pod-gallery-btn-download"><i class="fa fa-download"></i></a>' +
            deleteBtn +
            '</div></div></div>'
        );
    });
}

function viewFullPod(index) {
    if (!currentPods[index]) return;
    var pod   = currentPods[index];
    var isPdf = (pod.image && pod.image.indexOf('application/pdf') !== -1);
    var isUrl = pod.is_url || (pod.image && (pod.image.indexOf('http://') === 0 || pod.image.indexOf('https://') === 0));

    var imgSrc = isUrl
        ? pod.image
        : '?get_pod_image=' + encodeURIComponent(currentTrackingNum) + '&pod_index=' + index + '&t=' + Date.now();

    if (isPdf) {
        window.open(imgSrc, '_blank');
    } else {
        var modal = $(
            '<div class="pod-modal" style="display:flex;">' +
            '<div class="modal-box" style="max-width:900px;">' +
            '<div class="modal-header-bar"><h4><i class="fa fa-image"></i> POD #' + (index + 1) + '</h4>' +
            '<button class="btn-modal-close" onclick="$(this).closest(\'.pod-modal\').remove()">&times;</button></div>' +
            '<div class="modal-content-area" style="text-align:center;">' +
            '<img src="' + imgSrc + '" style="max-width:100%;max-height:80vh;border-radius:12px;">' +
            '</div></div></div>'
        );
        modal.on('click', function(e) { if (e.target === this) $(this).remove(); });
        $('body').append(modal);
    }
}

function closePodModal() { document.getElementById('podModal').style.display = 'none'; }

function displayFileName() {
    var file = document.getElementById('fileInput').files[0];
    $('#fileName').text(file ? '✓ ' + file.name : '');
}

function uploadPodFile() {
    var file = document.getElementById('fileInput').files[0];
    if (!file) { alert('Please select a file!'); return; }

    var formData = new FormData();
    formData.append('tracking',   currentTrackingNum);
    formData.append('pod_image',  file);
    formData.append('upload_pod', '1');

    $('#uploadProgressBar').show();
    $('#btnUpload').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Uploading...');

    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        xhr: function() {
            var xhr = new XMLHttpRequest();
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    var pct = (e.loaded / e.total) * 100;
                    $('#progressBarFill').css('width', pct + '%');
                    $('#uploadStatusText').text(Math.round(pct) + '% uploaded');
                }
            });
            return xhr;
        },
        success: function(res) {
            var data = (typeof res === 'string') ? JSON.parse(res) : res;
            if (data.success) {
                $('#uploadStatusText').html('<i class="fa fa-check-circle"></i> ' + data.message);
                setTimeout(function() {
                    if (dataTable) dataTable.destroy();
                    loadTableData();
                    openPodModal(currentTrackingNum);
                }, 1000);
            } else {
                $('#btnUpload').prop('disabled', false).html('<i class="fa fa-upload"></i> Upload Document');
                alert(data.message);
            }
        }
    });
}

function deletePodConfirm(podIndex) {
    if (!isAdmin) return alert('Unauthorized.');
    if (!confirm('Delete this POD?')) return;
    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: { delete_pod: 1, tracking: currentTrackingNum, pod_index: podIndex },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                if (dataTable) dataTable.destroy();
                loadTableData();
                openPodModal(currentTrackingNum);
            }
        }
    });
}

// ── Courier edit panel ─────────────────────────────────────────
function openEditCourierPanel(cid) {
    $('#courierPanelOverlay').addClass('open');
    $('#courierPanelContainer').addClass('open');
    $('body').css('overflow', 'hidden');
    $('.courier-panel-header h3').html('<i class="fa fa-pencil-alt"></i> Edit Shipment');
    $('#courierPanelContent').html('<div style="text-align:center;padding:50px;"><i class="fa fa-spinner fa-spin" style="font-size:32px;color:#1e3a8a;"></i><p style="margin-top:15px;color:#64748b;">Loading shipment...</p></div>');
    $.ajax({
        url: 'edit-courier.php',
        method: 'GET',
        data: { cid: btoa(cid), ajax_load: 1 },
        success: function(response) {
            $('#courierPanelContent').html(response);
            initCourierForm();
        },
        error: function() {
            $('#courierPanelContent').html('<div style="text-align:center;padding:50px;color:#dc2626;"><i class="fa fa-exclamation-circle" style="font-size:32px;"></i><p style="margin-top:15px;">Failed to load. Please try again.</p></div>');
        }
    });
}

function closeCourierPanel() {
    $('#courierPanelOverlay').removeClass('open');
    $('#courierPanelContainer').removeClass('open');
    $('body').css('overflow', '');
    setTimeout(function() { $('#courierPanelContent').html(''); }, 300);
}

function initCourierForm() {
    var isSubmitting = false;
    $('#editCourierForm').off('submit').on('submit', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        if (isSubmitting) return false;
        var form      = this;
        var submitBtn = $(form).find('button[type="submit"]');
        if (submitBtn.prop('disabled')) return false;
        isSubmitting = true;
        var formData = new FormData(form);
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Updating...');
        $.ajax({
            url: 'edit-courier.php', method: 'POST',
            data: formData, processData: false, contentType: false, dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(response) {
                closeCourierPanel();
                if (response && response.success) alert(response.message || 'Shipment updated!');
                location.reload();
            },
            error: function() { closeCourierPanel(); alert('Server error. Reloading...'); location.reload(); }
        });
        return false;
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closePodModal(); closeCourierPanel(); }
});
</script>
</body>
</html>