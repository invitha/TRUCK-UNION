<?php
/**
 * PATCH FILE - Copy the two fixed handler blocks into pickup-pod.php on the server
 * Replace the existing "GET pod_image" and "ajax_get_pod" sections with these.
 *
 * The fix: trPODimage now contains either:
 *   A) A direct URL  (from Flutter app):  "https://abra-flowxai.abragroup.in/uploads/..."
 *   B) JSON array    (from web dashboard): [{"image":"data:image/jpeg;base64,...","timestamp":"..."}]
 *   C) Legacy base64 string (old uploads)
 *
 * We detect which type and handle accordingly.
 */

// =========================================================================
// HELPER: normalise a single pod entry into ['url'=>..., 'timestamp'=>..., 'uploaded_by'=>...]
// Works for all three storage formats above.
// =========================================================================
function normalisePod($raw) {
    // Already an associative array entry (JSON array case)
    if (is_array($raw)) {
        $image = $raw['image'] ?? '';
        return [
            'url'         => resolveImageUrl($image),
            'is_url'      => isDirectUrl($image),
            'raw_image'   => $image,
            'timestamp'   => $raw['timestamp']   ?? 'Unknown',
            'uploaded_by' => $raw['uploaded_by'] ?? 'Driver/System',
        ];
    }
    // Plain string — URL or base64
    return [
        'url'         => resolveImageUrl($raw),
        'is_url'      => isDirectUrl($raw),
        'raw_image'   => $raw,
        'timestamp'   => 'Legacy Upload',
        'uploaded_by' => 'Driver/System',
    ];
}

function isDirectUrl($str) {
    return (strpos($str, 'http://') === 0 || strpos($str, 'https://') === 0);
}

function resolveImageUrl($imageData) {
    if (isDirectUrl($imageData)) {
        return $imageData;   // Already a URL — use directly
    }
    // It's base64 (with or without data: prefix) — serve via get_pod_image endpoint
    return null;  // Caller will use the endpoint URL
}

// =========================================================================
// POD IMAGE LOADING — Fixed to handle both URL and base64
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

        // ── Case A: direct URL stored (from Flutter app) ─────────────────
        if (isDirectUrl($raw)) {
            mysqli_stmt_close($stmt);
            header('Location: ' . $raw);
            exit;
        }

        // ── Case B: JSON array of pods ────────────────────────────────────
        $pods_data = json_decode($raw, true);
        $img_data  = '';

        if (is_array($pods_data)) {
            $entry = $pods_data[$pod_index] ?? $pods_data[0];
            $img_data = $entry['image'] ?? '';

            // Entry image might itself be a URL
            if (isDirectUrl($img_data)) {
                mysqli_stmt_close($stmt);
                header('Location: ' . $img_data);
                exit;
            }
        } else {
            // ── Case C: legacy plain base64 string ────────────────────────
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
            echo base64_decode($img_data);
            mysqli_stmt_close($stmt);
            exit;
        }
    }

    // Fallback: 1x1 transparent PNG
    header('Content-Type: image/png');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    mysqli_stmt_close($stmt);
    exit;
}

// =========================================================================
// AJAX Get POD — Fixed to return direct URL when available
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

        // ── Case A: direct URL (Flutter app upload) ───────────────────────
        if (isDirectUrl($raw)) {
            $pods = [[
                'image'       => $raw,          // direct URL
                'is_url'      => true,
                'timestamp'   => 'Driver Upload',
                'uploaded_by' => 'Driver App'
            ]];
        } else {
            // ── Case B: JSON array ─────────────────────────────────────────
            $pods_data = json_decode($raw, true);
            if (is_array($pods_data)) {
                foreach ($pods_data as $pod) {
                    if (isset($pod['image'])) {
                        $isUrl = isDirectUrl($pod['image']);
                        $pods[] = [
                            'image'       => $pod['image'],
                            'is_url'      => $isUrl,
                            'timestamp'   => $pod['timestamp']   ?? 'Unknown',
                            'uploaded_by' => $pod['uploaded_by'] ?? 'Driver/System'
                        ];
                    }
                }
            } else {
                // ── Case C: legacy plain base64 ────────────────────────────
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
?>
