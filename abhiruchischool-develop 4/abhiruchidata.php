<?php
session_start();
// Simple authentication (you should implement proper authentication)
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit();
}

require_once 'db_connection.php';

// Handle Bulk Delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_ids'])) {
    $selected_ids = $_POST['selected_ids'];
    if (!empty($selected_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $sql = "UPDATE enrollment_data SET actionflag = '0' WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            // Bind parameters
            $types = str_repeat('i', count($selected_ids));
            $stmt->bind_param($types, ...$selected_ids);

            if ($stmt->execute()) {
                $delete_count = $stmt->affected_rows;
                $_SESSION['success_message'] = "$delete_count records moved to trash successfully.";
            } else {
                $_SESSION['error_message'] = "Error deleting records: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Error preparing delete statement: " . $conn->error;
        }

        // Refresh page to show updated data
        header("Location: " . $_SERVER['PHP_SELF'] . (isset($_GET['view']) && $_GET['view'] == 'trash' ? '?view=trash' : ''));
        exit();
    }
}

// Handle Single Delete
if (isset($_POST['delete_single'])) {
    $id = intval($_POST['id']);
    $sql = "UPDATE enrollment_data SET actionflag = '0' WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Record moved to trash successfully.";
        } else {
            $_SESSION['error_message'] = "Error deleting record: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Error preparing delete statement: " . $conn->error;
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle Restore from Trash
if (isset($_POST['restore_single'])) {
    $id = intval($_POST['id']);
    $sql = "UPDATE enrollment_data SET actionflag = '1' WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Record restored successfully.";
        } else {
            $_SESSION['error_message'] = "Error restoring record: " . $stmt->error;
        }
        $stmt->close();
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?view=trash");
    exit();
}

// Handle Bulk Restore
if (isset($_POST['bulk_restore']) && isset($_POST['selected_ids'])) {
    $selected_ids = $_POST['selected_ids'];
    if (!empty($selected_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $sql = "UPDATE enrollment_data SET actionflag = '1' WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $types = str_repeat('i', count($selected_ids));
            $stmt->bind_param($types, ...$selected_ids);

            if ($stmt->execute()) {
                $restore_count = $stmt->affected_rows;
                $_SESSION['success_message'] = "$restore_count records restored successfully.";
            } else {
                $_SESSION['error_message'] = "Error restoring records: " . $stmt->error;
            }
            $stmt->close();
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "?view=trash");
        exit();
    }
}

// Handle Permanent Delete
if (isset($_POST['permanent_delete'])) {
    $id = intval($_POST['id']);
    $sql = "DELETE FROM enrollment_data WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Record permanently deleted.";
        } else {
            $_SESSION['error_message'] = "Error deleting record: " . $stmt->error;
        }
        $stmt->close();
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?view=trash");
    exit();
}

// Handle Bulk Permanent Delete
if (isset($_POST['bulk_permanent_delete']) && isset($_POST['selected_ids'])) {
    $selected_ids = $_POST['selected_ids'];
    if (!empty($selected_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $sql = "DELETE FROM enrollment_data WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $types = str_repeat('i', count($selected_ids));
            $stmt->bind_param($types, ...$selected_ids);

            if ($stmt->execute()) {
                $delete_count = $stmt->affected_rows;
                $_SESSION['success_message'] = "$delete_count records permanently deleted.";
            } else {
                $_SESSION['error_message'] = "Error deleting records: " . $stmt->error;
            }
            $stmt->close();
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "?view=trash");
        exit();
    }
}

// Handle Excel download WITH FILTERS
if (isset($_GET['download_excel'])) {
    header('Content-Type: application/vnd.ms-excel');

    // Create filename based on filter
    $filename = "enrollment_data_";

    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $filename .= strtolower($_GET['status']) . "_";
    }

    if (isset($_GET['view']) && $_GET['view'] == 'trash') {
        $filename .= "trash_";
    }

    $filename .= date('Y-m-d') . '.xls';

    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Excel header
    echo "ID\tStudent Name\tAdmission Std\tAge\tDOB\tFather Name\tFather Mobile\tMother Name\tMother Mobile\tAddress\tReferral Source\tTransport\tSubmission Date\tStatus\n";

    // Build SQL query based on current filters
    $excel_sql = "SELECT * FROM enrollment_data WHERE 1=1";

    // Apply actionflag filter
    if (isset($_GET['view']) && $_GET['view'] == 'trash') {
        $excel_sql .= " AND (actionflag = '0' OR actionflag IS NULL OR actionflag = '')";
    } else {
        $excel_sql .= " AND actionflag = '1'";
    }

    // Apply status filter if set
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $filter_status = mysqli_real_escape_string($conn, $_GET['status']);
        $excel_sql .= " AND status = '$filter_status'";
    }

    // Add sorting
    $excel_sql .= " ORDER BY submission_date DESC";

    $result = $conn->query($excel_sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo $row['id'] . "\t";
            echo $row['student_name'] . "\t";
            echo $row['admission_std'] . "\t";
            echo $row['age'] . "\t";
            echo $row['dob'] . "\t";
            echo $row['father_name'] . "\t";
            echo $row['father_mobile'] . "\t";
            echo $row['mother_name'] . "\t";
            echo $row['mother_mobile'] . "\t";
            echo str_replace(["\r\n", "\n", "\r"], ' ', $row['residence_address']) . "\t";
            echo $row['referral_source'] . "\t";
            echo $row['transport_required'] . "\t";
            echo $row['submission_date'] . "\t";
            echo $row['status'] . "\n";
        }
    }
    exit();
}

// Handle status update
if (isset($_POST['update_status'])) {
    $id = intval($_POST['id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, $_POST['notes']) : NULL;

    $sql = "UPDATE enrollment_data SET status = ?, notes = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ssi", $status, $notes, $id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Status updated successfully.";
        } else {
            $_SESSION['error_message'] = "Error updating status: " . $stmt->error;
        }
        $stmt->close();
    }

    $redirect_url = $_SERVER['PHP_SELF'];
    if (isset($_GET['view']) && $_GET['view'] == 'trash') {
        $redirect_url .= '?view=trash';
    }
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&') . 'status=' . $_GET['status'];
    }
    header("Location: " . $redirect_url);
    exit();
}

// Check if viewing trash
$view_trash = isset($_GET['view']) && $_GET['view'] == 'trash';
$actionflag_filter = $view_trash ? "actionflag = '0'" : "actionflag = '1'";

// Filter by status if specified
$status_filter = '';
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filter_status = mysqli_real_escape_string($conn, $_GET['status']);
    $status_filter = " AND status = '$filter_status'";
}

// Build SQL query to fetch all data
$sql = "SELECT 
    `id`, 
    `student_name`, 
    `admission_std`, 
    `age`, 
    `dob`, 
    `previous_school`, 
    `leaving_reason`, 
    `father_name`, 
    `father_qualification`, 
    `father_occupation`, 
    `father_annual_income`, 
    `father_email`, 
    `father_mobile`, 
    `father_whatsapp`, 
    `mother_name`, 
    `mother_qualification`, 
    `mother_occupation`, 
    `mother_annual_income`, 
    `mother_email`, 
    `mother_mobile`, 
    `mother_whatsapp`, 
    `residence_address`, 
    `referral_source`, 
    `staff_name`, 
    `transport_required`, 
    `pickup_point`, 
    `submission_date`, 
    `status`, 
    `actionflag`, 
    `notes` 
    FROM `enrollment_data` 
    WHERE $actionflag_filter $status_filter 
    ORDER BY submission_date DESC";

$result = $conn->query($sql);

if (!$result) {
    die("Error fetching data: " . $conn->error);
}

$total_applications = $result->num_rows;

// Get statistics - Only for active records (actionflag = '1')
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Contacted' THEN 1 ELSE 0 END) as contacted,
    SUM(CASE WHEN status = 'Admitted' THEN 1 ELSE 0 END) as admitted,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'takenform' THEN 1 ELSE 0 END) as takenform
    FROM enrollment_data WHERE actionflag = '1'";

$stats_result = $conn->query($stats_sql);

if (!$stats_result) {
    die("Error fetching statistics: " . $conn->error);
}

$stats = $stats_result->fetch_assoc();

// Get trash count
$trash_sql = "SELECT COUNT(*) as trash_count FROM enrollment_data WHERE actionflag = '0'";
$trash_result = $conn->query($trash_sql);

if (!$trash_result) {
    die("Error fetching trash count: " . $conn->error);
}

$trash_data = $trash_result->fetch_assoc();
$trash_count = $trash_data['trash_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abhiruchi Gurukul - Enrollment Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.svg" />
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            background-color: #6C3526;
            min-height: 100vh;
            color: white;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 12px 15px;
            display: block;
            transition: all 0.3s;
        }

        .sidebar a.active {
            background-color: rgba(255, 255, 255, 0.2);
            font-weight: bold;
            border-left: 4px solid white;
        }

        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .stat-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card p {
            margin-bottom: 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .stat-pending {
            background: linear-gradient(135deg, #ffc107, #ff9800);
        }

        .stat-contacted {
            background: linear-gradient(135deg, #17a2b8, #007bff);
        }

        .stat-admitted {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .stat-rejected {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
        }

        .stat-takenform {
            background: linear-gradient(135deg, #6f42c1, #9c27b0);
        }

        .stat-trash {
            background: linear-gradient(135deg, #6c757d, #495057);
        }

        .view-btn {
            background-color: #6C3526;
            color: white;
            border: none;
        }

        .view-btn:hover {
            background-color: #5a2b1e;
            color: white;
        }

        .btn-danger-light {
            background-color: #dc3545;
            color: white;
        }

        .btn-danger-light:hover {
            background-color: #c82333;
            color: white;
        }

        .bg-purple {
            background-color: #6f42c1 !important;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .badge {
            font-size: 12px;
            padding: 5px 10px;
            font-weight: 500;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(108, 53, 38, 0.05);
        }

        .select-all-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .action-buttons {
            margin-bottom: 15px;
        }

        .trash-row {
            background-color: rgba(220, 53, 69, 0.05);
        }

        .checkbox-cell {
            width: 40px;
        }

        .status-badge {
            min-width: 85px;
            display: inline-block;
            text-align: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        .sidebar-toggle {
            display: none;
        }

        .card {
            border: none;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.08);
            border-radius: 10px;
        }

        .table {
            margin-bottom: 0;
        }

        .table td,
        .table th {
            vertical-align: middle;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                z-index: 1000;
                width: 280px;
                transform: translateX(-100%);
                transition: transform 0.3s;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .sidebar-toggle {
                display: block;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1001;
                background: #6C3526;
                color: white;
                border: none;
                padding: 10px 15px;
                border-radius: 5px;
            }

            .col-md-10 {
                padding-left: 0 !important;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card h3 {
                font-size: 22px;
            }

            .table-responsive {
                font-size: 14px;
            }
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }

        .empty-state i {
            font-size: 60px;
            color: #6c757d;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .modal-header {
            background-color: #6C3526;
            color: white;
        }

        .btn-group .btn {
            margin-right: 5px;
        }

        .btn-group .btn:last-child {
            margin-right: 0;
        }

        .text-muted {
            color: #6c757d !important;
        }

        h2 {
            color: #333;
            font-weight: 600;
        }
        
        .income-display {
            font-weight: bold;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0 sidebar" id="sidebar">
                <div class="p-3" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <h4 class="mb-1">Abhiruchi Gurukul</h4>
                    <p class="text-muted mb-0" style="opacity: 0.7;">Admin Panel</p>
                </div>
                <nav class="nav flex-column mt-3">
                    <?php
                    $current_status = isset($_GET['status']) ? $_GET['status'] : '';
                    $current_view = isset($_GET['view']) ? $_GET['view'] : '';
                    ?>
                    <a href="abhiruchidata.php" class="<?php echo ($current_status == '' && $current_view == '') ? 'active' : ''; ?>">
                        <i class="fas fa-home me-2"></i> Dashboard
                    </a>
                    <a href="abhiruchidata.php" class="<?php echo ($current_status == '' && $current_view == '') ? 'active' : ''; ?>">
                        <i class="fas fa-users me-2"></i> All Applications
                    </a>
                    <a href="abhiruchidata.php?status=Pending" class="<?php echo $current_status == 'Pending' ? 'active' : ''; ?>">
                        <i class="fas fa-clock me-2"></i> Pending
                    </a>
                    <a href="abhiruchidata.php?status=Contacted" class="<?php echo $current_status == 'Contacted' ? 'active' : ''; ?>">
                        <i class="fas fa-phone me-2"></i> Contacted
                    </a>
                    <a href="abhiruchidata.php?status=Admitted" class="<?php echo $current_status == 'Admitted' ? 'active' : ''; ?>">
                        <i class="fas fa-check me-2"></i> Admitted
                    </a>
                    <a href="abhiruchidata.php?status=Rejected" class="<?php echo $current_status == 'Rejected' ? 'active' : ''; ?>">
                        <i class="fas fa-times me-2"></i> Rejected
                    </a>
                    <a href="abhiruchidata.php?status=takenform" class="<?php echo $current_status == 'takenform' ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt me-2"></i> Taken Form
                    </a>
                    <a href="abhiruchidata.php?view=trash" class="<?php echo $current_view == 'trash' ? 'active' : ''; ?>">
                        <i class="fas fa-trash me-2"></i> Trash
                        <?php if ($trash_count > 0): ?>
                            <span class="badge bg-danger float-end mt-1"><?php echo $trash_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="mt-4 pt-3" style="border-top: 1px solid rgba(255,255,255,0.1);">
                        <a href="logout.php" class="text-danger">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10">
                <div class="container-fluid mt-3">
                    <!-- Success/Error Messages -->
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $_SESSION['success_message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $_SESSION['error_message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>

                    <!-- Page Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0">
                            <i class="fas fa-users me-2"></i>
                            <?php echo $view_trash ? 'Trash - Deleted Applications' : 'Enrollment Applications'; ?>
                        </h2>
                    </div>

                    <!-- Statistics Cards -->
                    <?php if (!$view_trash): ?>
                        <div class="row mt-4">
                            <div class="col-md-2 col-6 mb-3">
                                <div class="stat-card stat-pending">
                                    <h3><?php echo $stats['pending'] ?? 0; ?></h3>
                                    <p><i class="fas fa-clock me-1"></i> Pending</p>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <div class="stat-card stat-contacted">
                                    <h3><?php echo $stats['contacted'] ?? 0; ?></h3>
                                    <p><i class="fas fa-phone me-1"></i> Contacted</p>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <div class="stat-card stat-admitted">
                                    <h3><?php echo $stats['admitted'] ?? 0; ?></h3>
                                    <p><i class="fas fa-check me-1"></i> Admitted</p>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <div class="stat-card stat-rejected">
                                    <h3><?php echo $stats['rejected'] ?? 0; ?></h3>
                                    <p><i class="fas fa-times me-1"></i> Rejected</p>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <div class="stat-card stat-takenform">
                                    <h3><?php echo $stats['takenform'] ?? 0; ?></h3>
                                    <p><i class="fas fa-file-alt me-1"></i> Taken Form</p>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <div class="stat-card stat-trash">
                                    <h3><?php echo $trash_count; ?></h3>
                                    <p><i class="fas fa-trash me-1"></i> In Trash</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="row mb-3">
                        <div class="col-md-12 action-buttons">
                            <?php if (!$view_trash): ?>
                                <a href="abhiruchidata.php?download_excel=1<?php
                                // Add current filter parameters to download link
                                if (isset($_GET['status']) && !empty($_GET['status'])) {
                                    echo '&status=' . htmlspecialchars($_GET['status']);
                                }
                                if (isset($_GET['view']) && $_GET['view'] == 'trash') {
                                    echo '&view=trash';
                                }
                                ?>" class="btn btn-success mb-2">
                                    <i class="fas fa-file-excel"></i> Download Excel
                                </a>
                            <?php endif; ?>

                            <span class="badge bg-secondary ms-2 mb-2 p-2">
                                <i class="fas fa-database me-1"></i>
                                <?php echo $view_trash ? 'Trashed Applications' : 'Total Applications'; ?>: <strong><?php echo $total_applications; ?></strong>
                            </span>

                            <?php if (isset($_GET['status']) && !empty($_GET['status'])): ?>
                                <span class="badge bg-primary ms-2 mb-2 p-2">
                                    <i class="fas fa-filter me-1"></i>
                                    Filtered by: <?php echo htmlspecialchars($_GET['status']); ?>
                                </span>
                                <a href="abhiruchidata.php<?php echo $view_trash ? '?view=trash' : ''; ?>" class="btn btn-sm btn-outline-secondary ms-2 mb-2">
                                    <i class="fas fa-times"></i> Clear Filter
                                </a>
                            <?php endif; ?>

                            <!-- Bulk Action Buttons -->
                            <?php if ($total_applications > 0): ?>
                                <div class="d-inline-block ms-2 mb-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllBtn">
                                        <i class="fas fa-check-square"></i> Select All
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllBtn">
                                        <i class="fas fa-square"></i> Deselect All
                                    </button>

                                    <?php if (!$view_trash): ?>
                                        <button type="button" class="btn btn-sm btn-danger-light" id="deleteSelectedBtn" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
                                            <i class="fas fa-trash"></i> Move to Trash
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-success" id="restoreSelectedBtn" data-bs-toggle="modal" data-bs-target="#confirmRestoreModal">
                                            <i class="fas fa-undo"></i> Restore Selected
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" id="permanentDeleteSelectedBtn" data-bs-toggle="modal" data-bs-target="#confirmPermanentDeleteModal">
                                            <i class="fas fa-trash-alt"></i> Delete Permanently
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Applications Table -->
                    <div class="card">
                        <div class="card-body p-0">
                            <?php if ($total_applications > 0): ?>
                                <form id="bulkActionForm" method="POST">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="checkbox-cell">
                                                        <input type="checkbox" id="selectAll" class="select-all-checkbox">
                                                    </th>
                                                    <th>ID</th>
                                                    <th>Student Name</th>
                                                    <th>Std</th>
                                                    <th>Age</th>
                                                    <th>Father</th>
                                                    <th>Mobile</th>
                                                    <th>Address</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if ($result && $result->num_rows > 0) {
                                                    while ($row = $result->fetch_assoc()):
                                                ?>
                                                        <tr class="<?php echo $view_trash ? 'trash-row' : ''; ?>">
                                                            <td class="checkbox-cell">
                                                                <input type="checkbox" name="selected_ids[]" value="<?php echo $row['id']; ?>" class="row-checkbox">
                                                            </td>
                                                            <td><strong>#<?php echo $row['id']; ?></strong></td>
                                                            <td>
                                                                <div class="fw-medium"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                                                <?php if (!empty($row['previous_school'])): ?>
                                                                    <small class="text-muted">Previous: <?php echo htmlspecialchars($row['previous_school']); ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($row['admission_std']); ?></span></td>
                                                            <td><?php echo $row['age']; ?> yrs</td>
                                                            <td>
                                                                <div><?php echo htmlspecialchars($row['father_name']); ?></div>
                                                                <?php if (!empty($row['father_occupation'])): ?>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($row['father_occupation']); ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <div><small class="text-muted">Father: <?php echo htmlspecialchars($row['father_mobile']); ?></small></div>
                                                                <?php if (!empty($row['mother_mobile'])): ?>
                                                                    <small class="text-muted">Mother:<?php echo htmlspecialchars($row['mother_mobile']); ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td title="<?php echo htmlspecialchars($row['residence_address']); ?>">
                                                                <?php
                                                                $address = $row['residence_address'];
                                                                if (strlen($address) > 30) {
                                                                    echo substr($address, 0, 30) . '...';
                                                                } else {
                                                                    echo $address;
                                                                }
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <span class="status-badge
                                                        <?php
                                                        $status = $row['status'];
                                                        switch ($status) {
                                                            case 'Pending':
                                                                echo 'bg-warning text-dark';
                                                                break;
                                                            case 'Contacted':
                                                                echo 'bg-info text-white';
                                                                break;
                                                            case 'Admitted':
                                                                echo 'bg-success text-white';
                                                                break;
                                                            case 'Rejected':
                                                                echo 'bg-danger text-white';
                                                                break;
                                                            case 'takenform':
                                                                echo 'bg-purple text-white';
                                                                break;
                                                            default:
                                                                echo 'bg-secondary text-white';
                                                        }
                                                        ?>">
                                                                    <?php echo $status; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div><?php echo date('d/m/Y', strtotime($row['submission_date'])); ?></div>
                                                                <small class="text-muted"><?php echo date('H:i', strtotime($row['submission_date'])); ?></small>
                                                            </td>
                                                            <td>
                                                                <?php if ($view_trash): ?>
                                                                    <!-- Trash Actions -->
                                                                    <div class="btn-group">
                                                                        <form method="POST" style="display: inline;">
                                                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                                            <button type="submit" name="restore_single" class="btn btn-sm btn-success" title="Restore">
                                                                                <i class="fas fa-undo"></i>
                                                                            </button>
                                                                        </form>
                                                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#permanentDeleteModal<?php echo $row['id']; ?>" title="Delete Permanently">
                                                                            <i class="fas fa-trash-alt"></i>
                                                                        </button>
                                                                    </div>

                                                                    <!-- Permanent Delete Modal -->
                                                                    <div class="modal fade" id="permanentDeleteModal<?php echo $row['id']; ?>" tabindex="-1">
                                                                        <div class="modal-dialog">
                                                                            <div class="modal-content">
                                                                                <div class="modal-header bg-danger text-white">
                                                                                    <h5 class="modal-title">Confirm Permanent Delete</h5>
                                                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                                                </div>
                                                                                <div class="modal-body">
                                                                                    <p>Are you sure you want to permanently delete this record?</p>
                                                                                    <p><strong>Student:</strong> <?php echo htmlspecialchars($row['student_name']); ?></p>
                                                                                    <p><strong>ID:</strong> <?php echo $row['id']; ?></p>
                                                                                    <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone!</p>
                                                                                </div>
                                                                                <div class="modal-footer">
                                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                                    <form method="POST" style="display: inline;">
                                                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                                                        <button type="submit" name="permanent_delete" class="btn btn-danger">Delete Permanently</button>
                                                                                    </form>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <!-- Normal Actions -->
                                                                    <div class="btn-group">
                                                                        <button type="button" class="btn btn-sm view-btn" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $row['id']; ?>" title="View Details">
                                                                            <i class="fas fa-eye"></i>
                                                                        </button>
                                                                        <button type="button" class="btn btn-sm btn-danger-light" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $row['id']; ?>" title="Move to Trash">
                                                                            <i class="fas fa-trash"></i>
                                                                        </button>
                                                                    </div>

                                                                    <!-- Delete Modal -->
                                                                    <div class="modal fade" id="deleteModal<?php echo $row['id']; ?>" tabindex="-1">
                                                                        <div class="modal-dialog">
                                                                            <div class="modal-content">
                                                                                <div class="modal-header bg-warning">
                                                                                    <h5 class="modal-title">Move to Trash</h5>
                                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                                </div>
                                                                                <div class="modal-body">
                                                                                    <p>Are you sure you want to move this record to trash?</p>
                                                                                    <p><strong>Student:</strong> <?php echo htmlspecialchars($row['student_name']); ?></p>
                                                                                    <p><strong>ID:</strong> <?php echo $row['id']; ?></p>
                                                                                    <p class="text-muted">You can restore it from the trash later.</p>
                                                                                </div>
                                                                                <div class="modal-footer">
                                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                                    <form method="POST" style="display: inline;">
                                                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                                                        <button type="submit" name="delete_single" class="btn btn-warning">Move to Trash</button>
                                                                                    </form>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>

                                                        <!-- View Modal (only for non-trash) -->
                                                        <?php if (!$view_trash): ?>
                                                            <div class="modal fade" id="viewModal<?php echo $row['id']; ?>" tabindex="-1">
                                                                <div class="modal-dialog modal-lg">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header" style="background-color: #6C3526; color: white;">
                                                                            <h5 class="modal-title">
                                                                                <i class="fas fa-user-graduate me-2"></i>
                                                                                Application Details - ID: <?php echo $row['id']; ?>
                                                                            </h5>
                                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <!-- Application Details -->
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="card mb-3">
                                                                                        <div class="card-header bg-light">
                                                                                            <h6 class="mb-0"><i class="fas fa-child me-2"></i>Child Information</h6>
                                                                                        </div>
                                                                                        <div class="card-body">
                                                                                            <p><strong>Name:</strong> <?php echo htmlspecialchars($row['student_name']); ?></p>
                                                                                            <p><strong>Standard:</strong> <?php echo htmlspecialchars($row['admission_std']); ?></p>
                                                                                            <p><strong>Age:</strong> <?php echo $row['age']; ?> years</p>
                                                                                            <p><strong>DOB:</strong> <?php echo date('d/m/Y', strtotime($row['dob'])); ?></p>
                                                                                            <?php if (!empty($row['previous_school'])): ?>
                                                                                                <p><strong>Previous School:</strong> <?php echo htmlspecialchars($row['previous_school']); ?></p>
                                                                                            <?php endif; ?>
                                                                                            <?php if (!empty($row['leaving_reason'])): ?>
                                                                                                <p><strong>Leaving Reason:</strong> <?php echo htmlspecialchars($row['leaving_reason']); ?></p>
                                                                                            <?php endif; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="card mb-3">
                                                                                        <div class="card-header bg-light">
                                                                                            <h6 class="mb-0"><i class="fas fa-male me-2"></i>Father's Details</h6>
                                                                                        </div>
                                                                                        <div class="card-body">
                                                                                            <p><strong>Name:</strong> <?php echo htmlspecialchars($row['father_name']); ?></p>
                                                                                            <p><strong>Mobile:</strong> <?php echo htmlspecialchars($row['father_mobile']); ?></p>
                                                                                            <?php if (!empty($row['father_whatsapp'])): ?>
                                                                                                <p><strong>WhatsApp:</strong> <?php echo htmlspecialchars($row['father_whatsapp']); ?></p>
                                                                                            <?php endif; ?>
                                                                                            <?php if (!empty($row['father_email'])): ?>
                                                                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($row['father_email']); ?></p>
                                                                                            <?php endif; ?>
                                                                                            <?php if (!empty($row['father_qualification'])): ?>
                                                                                                <p><strong>Qualification:</strong> <?php echo htmlspecialchars($row['father_qualification']); ?></p>
                                                                                            <?php endif; ?>
                                                                                            <?php if (!empty($row['father_occupation'])): ?>
                                                                                                <p><strong>Occupation:</strong> <?php echo htmlspecialchars($row['father_occupation']); ?></p>
                                                                                            <?php endif; ?>
                                                                                            <?php if (!empty($row['father_annual_income'])): ?>
                                                                                                <p><strong>Annual Income:</strong> <span class="income-display">
                                                                                                    <?php 
                                                                                                    // Display in LPA format
                                                                                                    $father_income = floatval($row['father_annual_income']);
                                                                                                    echo $father_income . " LPA (₹" . number_format($father_income * 100000, 0) . ")";
                                                                                                    ?>
                                                                                                </span></p>
                                                                                            <?php endif; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="card mb-3">
                                                                                        <div class="card-header bg-light">
                                                                                            <h6 class="mb-0"><i class="fas fa-female me-2"></i>Mother's Details</h6>
                                                                                        </div>
                                                                                        <div class="card-body">
                                                                                            <p><strong>Name:</strong> <?php echo htmlspecialchars($row['mother_name']); ?></p>
                                                                                            <p><strong>Mobile:</strong> <?php echo htmlspecialchars($row['mother_mobile']); ?></p>
                                                                                            <?php if (!empty($row['mother_whatsapp'])): ?>
                                                                                                <p><strong>WhatsApp:</strong> <?php echo htmlspecialchars($row['mother_whatsapp']); ?></p>
                                                                                            <?php endif; ?>
                                                                                            <?php if (!empty($row['mother_email'])): ?>
                                                                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($row['mother_email']); ?></p>
                                                                                            <?php endif; ?>
                                                                                            <?php if (!empty($row['mother_qualification'])): ?>
                                                                                                <p><strong>Qualification:</strong> <?php echo htmlspecialchars($row['mother_qualification']); ?></p>
                                                                                            <?php endif; ?>
                                                                                            <?php if (!empty($row['mother_occupation'])): ?>
                                                                                                <p><strong>Occupation:</strong> <?php echo htmlspecialchars($row['mother_occupation']); ?></p>
                                                                                            <?php endif; ?>
                                                                                            <?php if (!empty($row['mother_annual_income'])): ?>
                                                                                                <p><strong>Annual Income:</strong> <span class="income-display">
                                                                                                    <?php 
                                                                                                    // Display in LPA format
                                                                                                    $mother_income = floatval($row['mother_annual_income']);
                                                                                                    echo $mother_income . " LPA (₹" . number_format($mother_income * 100000, 0) . ")";
                                                                                                    ?>
                                                                                                </span></p>
                                                                                            <?php endif; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="card mb-3">
                                                                                        <div class="card-header bg-light">
                                                                                            <h6 class="mb-0"><i class="fas fa-home me-2"></i>Other Details</h6>
                                                                                        </div>
                                                                                        <div class="card-body">
                                                                                            <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($row['residence_address'])); ?></p>
                                                                                            <p><strong>Referral Source:</strong> <?php echo htmlspecialchars($row['referral_source']); ?></p>
                                                                                            <?php if (!empty($row['staff_name'])): ?>
                                                                                                <p><strong>Staff Name:</strong> <?php echo htmlspecialchars($row['staff_name']); ?></p>
                                                                                            <?php endif; ?>
                                                                                            <p><strong>Transport Required:</strong> <?php echo $row['transport_required']; ?></p>
                                                                                            <?php if (!empty($row['pickup_point'])): ?>
                                                                                                <p><strong>Pickup Point:</strong> <?php echo htmlspecialchars($row['pickup_point']); ?></p>
                                                                                            <?php endif; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="card">
                                                                                <div class="card-header bg-light">
                                                                                    <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Update Status & Notes</h6>
                                                                                </div>
                                                                                <div class="card-body">
                                                                                    <form method="POST">
                                                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                                                        <div class="row">
                                                                                            <div class="col-md-6">
                                                                                                <label class="form-label">Update Status:</label>
                                                                                                <select name="status" class="form-select">
                                                                                                    <option value="Pending" <?php echo $row['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                                                                    <option value="TakenForm" <?php echo $row['status'] == 'takenform' ? 'selected' : ''; ?>>Taken Form</option>
                                                                                                    <option value="Contacted" <?php echo $row['status'] == 'Contacted' ? 'selected' : ''; ?>>Contacted</option>
                                                                                                    <option value="Admitted" <?php echo $row['status'] == 'Admitted' ? 'selected' : ''; ?>>Admitted</option>
                                                                                                    <option value="Rejected" <?php echo $row['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                                                                </select>
                                                                                            </div>
                                                                                            <div class="col-md-6">
                                                                                                <label class="form-label">Notes:</label>
                                                                                                <textarea name="notes" class="form-control" rows="3" placeholder="Add notes..."><?php echo htmlspecialchars($row['notes'] ?? ''); ?></textarea>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="mt-3">
                                                                                            <button type="submit" name="update_status" class="btn view-btn">
                                                                                                <i class="fas fa-save me-2"></i>Update Status
                                                                                            </button>
                                                                                        </div>
                                                                                    </form>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                <?php endwhile;
                                                } else {
                                                    echo "<tr><td colspan='11' class='text-center py-4'>No applications found</td></tr>";
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Bulk Action Confirmation Modals -->
                                    <?php if (!$view_trash): ?>
                                        <!-- Bulk Delete Modal -->
                                        <div class="modal fade" id="confirmDeleteModal" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-warning">
                                                        <h5 class="modal-title">Move Selected to Trash</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to move the selected records to trash?</p>
                                                        <p class="text-muted">Selected records will be moved to trash and can be restored later.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="bulk_delete" class="btn btn-warning">Move to Trash</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <!-- Bulk Restore Modal -->
                                        <div class="modal fade" id="confirmRestoreModal" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-success text-white">
                                                        <h5 class="modal-title">Restore Selected Records</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to restore the selected records?</p>
                                                        <p class="text-muted">Selected records will be restored to active applications.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="bulk_restore" class="btn btn-success">Restore Selected</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Bulk Permanent Delete Modal -->
                                        <div class="modal fade" id="confirmPermanentDeleteModal" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title">Permanently Delete Selected</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to permanently delete the selected records?</p>
                                                        <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone!</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="bulk_permanent_delete" class="btn btn-danger">Delete Permanently</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-<?php echo $view_trash ? 'trash' : 'inbox'; ?>"></i>
                                    <h4 class="mb-3">No applications found</h4>
                                    <p class="text-muted mb-4">
                                        <?php
                                        if ($view_trash) {
                                            echo "Trash is empty";
                                        } elseif (isset($_GET['status'])) {
                                            echo "No applications with status '" . htmlspecialchars($_GET['status']) . "'";
                                        } else {
                                            echo "No applications submitted yet";
                                        }
                                        ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.sidebar-toggle');

            if (window.innerWidth <= 768 &&
                !sidebar.contains(event.target) &&
                event.target !== toggleBtn &&
                !toggleBtn.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        });

        // Select/Deselect All functionality
        const selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.row-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateButtonStates();
            });
        }

        const selectAllBtn = document.getElementById('selectAllBtn');
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                const checkboxes = document.querySelectorAll('.row-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = true;
                });
                if (selectAllCheckbox) selectAllCheckbox.checked = true;
                updateButtonStates();
            });
        }

        const deselectAllBtn = document.getElementById('deselectAllBtn');
        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', function() {
                const checkboxes = document.querySelectorAll('.row-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                if (selectAllCheckbox) selectAllCheckbox.checked = false;
                updateButtonStates();
            });
        }

        // Update select all checkbox when individual checkboxes change
        document.querySelectorAll('.row-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.row-checkbox');
                const selectAll = document.getElementById('selectAll');
                if (selectAll) {
                    selectAll.checked = Array.from(allCheckboxes).every(cb => cb.checked);
                }
                updateButtonStates();
            });
        });

        function updateButtonStates() {
            const selectedCount = document.querySelectorAll('.row-checkbox:checked').length;
            const deleteBtn = document.getElementById('deleteSelectedBtn');
            const restoreBtn = document.getElementById('restoreSelectedBtn');
            const permanentDeleteBtn = document.getElementById('permanentDeleteSelectedBtn');

            if (deleteBtn) deleteBtn.disabled = selectedCount === 0;
            if (restoreBtn) restoreBtn.disabled = selectedCount === 0;
            if (permanentDeleteBtn) permanentDeleteBtn.disabled = selectedCount === 0;
        }

        // Initialize button states
        updateButtonStates();

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
<?php $conn->close(); ?>