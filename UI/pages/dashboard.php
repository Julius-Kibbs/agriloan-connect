<?php
session_start();
include '../../database/connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$mysqli = db_agriloan_connect();

// Update overdue payments
$stmt = $mysqli->prepare('UPDATE payment_schedules SET status = "overdue" WHERE due_date < NOW() AND status = "pending"');
$stmt->execute();
$stmt->close();

if ($_SESSION['role'] === 'farmer') {
    // Farmer: Fetch user-specific loans
    $stmt = $mysqli->prepare('SELECT loan_id, category, amount, repayment_period, interest_rate, status, application_date, approval_date, rejection_date, repayment_due_date, rejection_reason FROM loans WHERE user_id = ?');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $loans = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Calculate card metrics
    $total_loan_taken = 0;
    $loan_status_summary = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    $latest_loan_status = 'No Loans';
    foreach ($loans as $loan) {
        if ($loan['status'] === 'approved') {
            $total_loan_taken += $loan['amount'];
        }
        $loan_status_summary[$loan['status']]++;
    }
    if ($loan_status_summary['pending'] > 0) {
        $latest_loan_status = 'Pending Approval';
    } elseif ($loan_status_summary['approved'] > 0) {
        $latest_loan_status = 'Approved';
    } elseif ($loan_status_summary['rejected'] > 0) {
        $latest_loan_status = 'Rejected';
    }

    // Fetch payment schedules for approved loans
    $approved_loan_ids = array_column(array_filter($loans, fn($loan) => $loan['status'] === 'approved'), 'loan_id');
    $schedules = [];
    $total_due = 0;
    $earliest_deadline = null;
    if (!empty($approved_loan_ids)) {
        $placeholders = implode(',', array_fill(0, count($approved_loan_ids), '?'));
        $stmt = $mysqli->prepare("SELECT schedule_id, loan_id, installment_number, due_date, amount_due, status FROM payment_schedules WHERE loan_id IN ($placeholders) ORDER BY loan_id, installment_number");
        $stmt->bind_param(str_repeat('i', count($approved_loan_ids)), ...$approved_loan_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        $schedules = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Calculate total amount due and earliest deadline
        foreach ($schedules as $schedule) {
            if (in_array($schedule['status'], ['pending', 'overdue'])) {
                $total_due += $schedule['amount_due'];
                $due_date = strtotime($schedule['due_date']);
                if ($earliest_deadline === null || $due_date < $earliest_deadline) {
                    $earliest_deadline = $due_date;
                }
            }
        }
    }
    $earliest_deadline = $earliest_deadline ? date('jS F', $earliest_deadline) : 'N/A';

    // Prepare chart data (user-specific)
    $chart_labels = [];
    $chart_data = [];
    $today = new DateTime();
    for ($i = 0; $i < 12; $i++) {
        $month = (clone $today)->modify("+$i months");
        $chart_labels[] = $month->format('M');
        $month_start = $month->format('Y-m-01');
        $month_end = $month->format('Y-m-t');
        $month_total = 0;
        foreach ($schedules as $schedule) {
            if ($schedule['status'] !== 'paid' && $schedule['due_date'] >= $month_start && $schedule['due_date'] <= $month_end) {
                $month_total += $schedule['amount_due'];
            }
        }
        $chart_data[] = $month_total;
    }

    // Handle CSV download (user-specific)
    if (isset($_GET['download_csv']) && !empty($schedules)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=payment_schedule_' . $_SESSION['user_id'] . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Loan ID', 'Installment', 'Due Date', 'Amount Due (TZS)', 'Status']);
        foreach ($schedules as $schedule) {
            fputcsv($output, [
                $schedule['loan_id'],
                $schedule['installment_number'],
                date('Y-m-d', strtotime($schedule['due_date'])),
                number_format($schedule['amount_due'], 2),
                ucfirst($schedule['status'])
            ]);
        }
        fclose($output);
        exit;
    }
} else {
    // Approver/Super Admin: Fetch all loans
    $stmt = $mysqli->prepare('SELECT l.loan_id, l.category, l.amount, l.status, l.application_date, u.user_id, u.full_name 
                              FROM loans l 
                              JOIN users u ON l.user_id = u.user_id');
    $stmt->execute();
    $result = $stmt->get_result();
    $loans = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Calculate card metrics
    $total_loan_taken = 0;
    $loan_status_summary = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    foreach ($loans as $loan) {
        if ($loan['status'] === 'approved') {
            $total_loan_taken += $loan['amount'];
        }
        $loan_status_summary[$loan['status']]++;
    }
    $latest_loan_status = 'System Overview';

    // Fetch all payment schedules
    $stmt = $mysqli->prepare('SELECT schedule_id, loan_id, installment_number, due_date, amount_due, status FROM payment_schedules ORDER BY loan_id, installment_number');
    $stmt->execute();
    $result = $stmt->get_result();
    $schedules = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Calculate total amount due and earliest deadline
    $total_due = 0;
    $earliest_deadline = null;
    foreach ($schedules as $schedule) {
        if (in_array($schedule['status'], ['pending', 'overdue'])) {
            $total_due += $schedule['amount_due'];
            $due_date = strtotime($schedule['due_date']);
            if ($earliest_deadline === null || $due_date < $earliest_deadline) {
                $earliest_deadline = $due_date;
            }
        }
    }
    $earliest_deadline = $earliest_deadline ? date('jS F', $earliest_deadline) : 'N/A';

    // Prepare chart data (system-wide)
    $chart_labels = [];
    $chart_data = [];
    $today = new DateTime();
    for ($i = 0; $i < 12; $i++) {
        $month = (clone $today)->modify("+$i months");
        $chart_labels[] = $month->format('M');
        $month_start = $month->format('Y-m-01');
        $month_end = $month->format('Y-m-t');
        $month_total = 0;
        foreach ($schedules as $schedule) {
            if ($schedule['status'] !== 'paid' && $schedule['due_date'] >= $month_start && $schedule['due_date'] <= $month_end) {
                $month_total += $schedule['amount_due'];
            }
        }
        $chart_data[] = $month_total;
    }

    // Handle CSV download (system-wide)
    if (isset($_GET['download_csv']) && !empty($schedules)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=system_payment_schedules.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Loan ID', 'Installment', 'Due Date', 'Amount Due (TZS)', 'Status']);
        foreach ($schedules as $schedule) {
            fputcsv($output, [
                $schedule['loan_id'],
                $schedule['installment_number'],
                date('Y-m-d', strtotime($schedule['due_date'])),
                number_format($schedule['amount_due'], 2),
                ucfirst($schedule['status'])
            ]);
        }
        fclose($output);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <title>Dashboard | Agri-Loan Connect</title>
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />
    <link href="https://demos.creative-tim.com/argon-dashboard-pro/assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="https://demos.creative-tim.com/argon-dashboard-pro/assets/css/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link id="pagestyle" href="../assets/css/argon-dashboard.css?v=2.1.0" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        .status-pending { background-color: #ffc107; color: #fff; }
        .status-paid { background-color: #28a745; color: #fff; }
        .status-overdue { background-color: #dc3545; color: #fff; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="../assets/js/plugins/chartjs.min.js"></script>
    <script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        $(document).ready(function() {
            $('#loansTable').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                pageLength: 10
            });
            $('#schedulesTable').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                pageLength: 10
            });
        });
    </script>
</head>
<body class="g-sidenav-show bg-gray-100">
<div class="min-height-300 bg-dark position-absolute w-100"></div>
<aside class="sidenav bg-white navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl my-3 fixed-start ms-4" id="sidenav-main">
    <div class="sidenav-header">
        <i class="fas fa-times p-3 cursor-pointer text-secondary opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
        <a class="navbar-brand m-0" href="dashboard.php">
            <img src="../assets/img/logo-ct-dark.png" width="26px" height="26px" class="navbar-brand-img h-100" alt="main_logo">
            <span class="ms-1 font-weight-bold">AgriLoan Connect</span>
        </a>
    </div>
    <hr class="horizontal dark mt-0">
    <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">
                    <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="ni ni-tv-2 text-dark text-sm opacity-10"></i>
                    </div>
                    <span class="nav-link-text ms-1">Dashboard</span>
                </a>
            </li>
            <?php if (isset($_SESSION['user_id']) && in_array($_SESSION['role'], ['farmer', 'super_admin'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="loan_application.php">
                        <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                            <i class="ni ni-calendar-grid-58 text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text ms-1">Loan Application</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if (isset($_SESSION['user_id']) && in_array($_SESSION['role'], ['approver', 'super_admin'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="loan_review.php">
                        <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                            <i class="ni ni-settings text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text ms-1">Loan Review</span>
                    </a>
                </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link" href="loan_status.php">
                    <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="ni ni-settings text-dark text-sm opacity-10"></i>
                    </div>
                    <span class="nav-link-text ms-1">Loan Status</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="user_review.php">
                    <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="ni ni-credit-card text-dark text-sm opacity-10"></i>
                    </div>
                    <span class="nav-link-text ms-1">User Approval</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="contact.php">
                    <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="ni ni-credit-card text-dark text-sm opacity-10"></i>
                    </div>
                    <span class="nav-link-text ms-1">Contact</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="profile.php">
                    <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="ni ni-single-02 text-dark text-sm opacity-10"></i>
                    </div>
                    <span class="nav-link-text ms-1">Profile</span>
                </a>
            </li>
        </ul>
    </div>
</aside>
<main class="main-content position-relative border-radius-lg">
    <nav class="navbar navbar-main navbar-expand-lg px-0 mx-4 shadow-none border-radius-xl" id="navbarBlur" data-scroll="false">
        <div class="container-fluid py-1 px-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                    <li class="breadcrumb-item text-sm"><a class="opacity-5 text-white" href="javascript:;">Pages</a></li>
                    <li class="breadcrumb-item text-sm text-white active" aria-current="page">Dashboard</li>
                </ol>
                <h6 class="font-weight-bolder text-white mb-0">Dashboard</h6>
            </nav>
            <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                <div class="ms-md-auto pe-md-3 d-flex align-items-center">
                    <div class="input-group">
                        <span class="input-group-text text-body"><i class="fas fa-search" aria-hidden="true"></i></span>
                        <input type="text" class="form-control" placeholder="Type here...">
                    </div>
                </div>
                <ul class="navbar-nav justify-content-end">
                    <li class="nav-item d-flex align-items-center">
                        <a href="index.php" class="nav-link text-white font-weight-bold px-0">
                            <i class="fa fa-user me-sm-1"></i>
                            <span class="d-sm-inline d-none">Sign Out</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Loan Taken</p>
                                    <h5 class="font-weight-bolder"><?php echo number_format($total_loan_taken, 2); ?> TZS</h5>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-primary shadow-primary text-center rounded-circle">
                                    <i class="ni ni-money-coins text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Loan Status</p>
                                    <h5 class="font-weight-bolder"><?php echo htmlspecialchars($latest_loan_status); ?></h5>
                                    <p class="mb-0">
                                            <span class="text-success text-sm font-weight-bolder">
                                                <?php echo "Pending: {$loan_status_summary['pending']}, Approved: {$loan_status_summary['approved']}, Rejected: {$loan_status_summary['rejected']}"; ?>
                                            </span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-danger shadow-danger text-center rounded-circle">
                                    <i class="ni ni-world text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Amount Due</p>
                                    <h5 class="font-weight-bolder"><?php echo number_format($total_due, 2); ?> TZS</h5>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-success shadow-success text-center rounded-circle">
                                    <i class="ni ni-paper-diploma text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Earliest Deadline</p>
                                    <h5 class="font-weight-bolder"><?php echo htmlspecialchars($earliest_deadline); ?></h5>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-warning shadow-warning text-center rounded-circle">
                                    <i class="ni ni-cart text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-lg-7 mb-lg-0 mb-4">
                <div class="card z-index-2 h-100">
                    <div class="card-header pb-0 pt-3 bg-transparent">
                        <h6 class="text-capitalize">Welcome <?php echo htmlspecialchars($_SESSION['role'] === 'farmer' ? 'Farmer' : ($_SESSION['role'] === 'approver' ? 'Approver' : 'Admin')); ?></h6>
                        <p class="text-sm mb-0">
                            <i class="fa fa-arrow-up text-success"></i>
                            <span class="font-weight-bold"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
                        </p>
                    </div>
                    <div class="card-body p-3">
                        <div class="chart">
                            <canvas id="chart-line" class="chart-canvas" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 mb-lg-0 mb-4">
                <div class="card">
                    <div class="card-header pb-0 p-3">
                        <h6 class="mb-0"><?php echo $_SESSION['role'] === 'farmer' ? 'Your Loan Applications' : 'All Loan Applications'; ?></h6>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($loans)): ?>
                            <p class="text-center"><?php echo $_SESSION['role'] === 'farmer' ? 'No loan applications found. <a href="loan_application.php">Apply now</a>.' : 'No loan applications found.'; ?></p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="loansTable" class="table table-striped">
                                    <thead>
                                    <tr>
                                        <?php if ($_SESSION['role'] !== 'farmer'): ?>
                                            <th>User</th>
                                        <?php endif; ?>
                                        <th>Loan ID</th>
                                        <th>Category</th>
                                        <th>Amount (TZS)</th>
                                        <th>Status</th>
                                        <th>Applied On</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($loans as $loan): ?>
                                        <tr>
                                            <?php if ($_SESSION['role'] !== 'farmer'): ?>
                                                <td><?php echo htmlspecialchars($loan['full_name']); ?></td>
                                            <?php endif; ?>
                                            <td><?php echo htmlspecialchars($loan['loan_id']); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($loan['category'])); ?></td>
                                            <td><?php echo number_format($loan['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($loan['status'])); ?></td>
                                            <td><?php echo htmlspecialchars($loan['application_date'] ? date('Y-m-d', strtotime($loan['application_date'])) : '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php if (!empty($schedules)): ?>
            <div class="row mt-4">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header pb-0 p-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><?php echo $_SESSION['role'] === 'farmer' ? 'Your Repayment Schedules' : 'All Repayment Schedules'; ?></h6>
                            <a href="?download_csv=1" class="btn btn-sm btn-primary">Download CSV</a>
                        </div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table id="schedulesTable" class="table table-striped">
                                    <thead>
                                    <tr>
                                        <?php if ($_SESSION['role'] !== 'farmer'): ?>
                                            <th>User</th>
                                        <?php endif; ?>
                                        <th>Loan ID</th>
                                        <th>Installment</th>
                                        <th>Due Date</th>
                                        <th>Amount Due (TZS)</th>
                                        <th>Status</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $user_ids = array_unique(array_column($schedules, 'loan_id'));
                                    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
                                    $stmt = $mysqli->prepare("SELECT l.loan_id, u.full_name FROM loans l JOIN users u ON l.user_id = u.user_id WHERE l.loan_id IN ($placeholders)");
                                    $stmt->bind_param(str_repeat('i', count($user_ids)), ...$user_ids);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    $user_map = [];
                                    while ($row = $result->fetch_assoc()) {
                                        $user_map[$row['loan_id']] = $row['full_name'];
                                    }
                                    $stmt->close();
                                    ?>
                                    <?php foreach ($schedules as $schedule): ?>
                                        <tr>
                                            <?php if ($_SESSION['role'] !== 'farmer'): ?>
                                                <td><?php echo htmlspecialchars($user_map[$schedule['loan_id']] ?? 'Unknown'); ?></td>
                                            <?php endif; ?>
                                            <td><?php echo htmlspecialchars($schedule['loan_id']); ?></td>
                                            <td><?php echo htmlspecialchars($schedule['installment_number']); ?></td>
                                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($schedule['due_date']))); ?></td>
                                            <td><?php echo number_format($schedule['amount_due'], 2); ?></td>
                                            <td>
                                                        <span class="badge status-<?php echo strtolower($schedule['status']); ?>">
                                                            <?php echo htmlspecialchars(ucfirst($schedule['status'])); ?>
                                                        </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="row mt-4">
            <div class="col-lg-7 mb-lg-0 mb-4">
                <div class="card">
                    <div class="card-header pb-0 p-3">
                        <h6 class="mb-2">Loan Guide Videos</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-items-center">
                            <tbody>
                            <tr></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header pb-0 p-3">
                        <h6 class="mb-0">Support Panel</h6>
                    </div>
                    <div class="card-body p-3">
                        <ul class="list-group">
                            <li class="list-group-item border-0 d-flex justify-content-between ps-0 mb-2 border-radius-lg">
                                <div class="d-flex align-items-center">
                                    <div class="icon icon-shape icon-sm me-3 bg-gradient-dark shadow text-center">
                                        <i class="ni ni-mobile-button text-white opacity-10"></i>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <h6 class="mb-1 text-dark text-sm">Juliana Kituli</h6>
                                        <span class="text-xs">Phone Number: <span class="font-weight-bold">+255 782 113 998</span></span>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <button class="btn btn-link btn-icon-only btn-rounded btn-sm text-dark icon-move-right my-auto"><i class="ni ni-bold-right" aria-hidden="true"></i></button>
                                </div>
                            </li>
                            <li class="list-group-item border-0 d-flex justify-content-between ps-0 mb-2 border-radius-lg">
                                <div class="d-flex align-items-center">
                                    <div class="icon icon-shape icon-sm me-3 bg-gradient-dark shadow text-center">
                                        <i class="ni ni-mobile-button text-white opacity-10"></i>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <h6 class="mb-1 text-dark text-sm">Jacob Ndege</h6>
                                        <span class="text-xs">Phone Number: <span class="font-weight-bold">+255 699 321 456</span></span>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <button class="btn btn-link btn-icon-only btn-rounded btn-sm text-dark icon-move-right my-auto"><i class="ni ni-bold-right" aria-hidden="true"></i></button>
                                </div>
                            </li>
                            <li class="list-group-item border-0 d-flex justify-content-between ps-0 mb-2 border-radius-lg">
                                <div class="d-flex align-items-center">
                                    <div class="icon icon-shape icon-sm me-3 bg-gradient-dark shadow text-center">
                                        <i class="ni ni-mobile-button text-white opacity-10"></i>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <h6 class="mb-1 text-dark text-sm">Ezra Edgar</h6>
                                        <span class="text-xs">Phone Number: <span class="font-weight-bold">+255 784 124 778</span></span>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <button class="btn btn-link btn-icon-only btn-rounded btn-sm text-dark icon-move-right my-auto"><i class="ni ni-bold-right" aria-hidden="true"></i></button>
                                </div>
                            </li>
                            <li class="list-group-item border-0 d-flex justify-content-between ps-0 border-radius-lg">
                                <div class="d-flex align-items-center">
                                    <div class="icon icon-shape icon-sm me-3 bg-gradient-dark shadow text-center">
                                        <i class="ni ni-mobile-button text-white opacity-10"></i>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <h6 class="mb-1 text-dark text-sm">Morris David</h6>
                                        <span class="text-xs font-weight-bold">Phone Number: +255 688 765 123</span>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <button class="btn btn-link btn-icon-only btn-rounded btn-sm text-dark icon-move-right my-auto"><i class="ni ni-bold-right" aria-hidden="true"></i></button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <footer class="footer pt-3">
            <div class="container-fluid">
                <div class="row align-items-center justify-content-lg-between">
                    <div class="col-lg-6 mb-lg-0 mb-4">
                        <div class="copyright text-center text-sm text-muted text-lg-start">
                            © <script>document.write(new Date().getFullYear())</script>, Agri-Loan Connect
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <ul class="nav nav-footer justify-content-center justify-content-lg-end"></ul>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</main>
<script src="../assets/js/core/popper.min.js"></script>
<script src="../assets/js/core/bootstrap.min.js"></script>
<script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
<script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.js"></script>
<script>
    var ctx1 = document.getElementById("chart-line").getContext("2d");
    var gradientStroke1 = ctx1.createLinearGradient(0, 230, 0, 50);
    gradientStroke1.addColorStop(1, 'rgba(94, 114, 228, 0.2)');
    gradientStroke1.addColorStop(0.2, 'rgba(94, 114, 228, 0.0)');
    gradientStroke1.addColorStop(0, 'rgba(94, 114, 228, 0)');
    new Chart(ctx1, {
        type: "line",
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: "Amount Due (TZS)",
                tension: 0.4,
                borderWidth: 0,
                pointRadius: 0,
                borderColor: "#5e72e4",
                backgroundColor: gradientStroke1,
                borderWidth: 3,
                fill: true,
                data: <?php echo json_encode($chart_data); ?>,
                maxBarThickness: 6
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'TZS ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            interaction: { intersect: false, mode: 'index' },
            scales: {
                y: {
                    grid: { drawBorder: false, display: true, drawOnChartArea: true, drawTicks: false, borderDash: [5, 5] },
                    ticks: { display: true, padding: 10, color: '#fbfbfb', font: { size: 11, family: "Open Sans", style: 'normal', lineHeight: 2 } }
                },
                x: {
                    grid: { drawBorder: false, display: false, drawOnChartArea: false, drawTicks: false, borderDash: [5, 5] },
                    ticks: { display: true, color: '#ccc', padding: 20, font: { size: 11, family: "Open Sans", style: 'normal', lineHeight: 2 } }
                }
            }
        }
    });
    var win = navigator.platform.indexOf('Win') > -1;
    if (win && document.querySelector('#sidenav-scrollbar')) {
        var options = { damping: '0.5' };
        Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
    }
</script>
<script async defer src="https://buttons.github.io/buttons.js"></script>
<script src="../assets/js/argon-dashboard.min.js?v=2.1.0"></script>
</body>
</html>