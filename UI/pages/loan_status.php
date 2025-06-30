<?php
session_start();
include '../../database/connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$mysqli = db_agriloan_connect();

// Fetch user's loans
$stmt = $mysqli->prepare('SELECT loan_id, category, amount, repayment_period, interest_rate, status, application_date, approval_date, rejection_date, repayment_due_date, rejection_reason FROM loans WHERE user_id = ?');
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$loans = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch payment schedules for approved loans
$approved_loan_ids = array_column(array_filter($loans, fn($loan) => $loan['status'] === 'approved'), 'loan_id');
$schedules = [];
if (!empty($approved_loan_ids)) {
    $placeholders = implode(',', array_fill(0, count($approved_loan_ids), '?'));
    $stmt = $mysqli->prepare("SELECT schedule_id, loan_id, installment_number, due_date, amount_due, status FROM payment_schedules WHERE loan_id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($approved_loan_ids)), ...$approved_loan_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $schedules = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
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
    <script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</head>
<body class="">
    <nav class="navbar navbar-expand-lg position-absolute top-0 z-index-3 w-100 shadow-none my-3 navbar-transparent mt-4">
        <div class="container">
            <a class="navbar-brand font-weight-bolder ms-lg-0 ms-3 text-white" href="dashboard.php">Agri-Loan Connect</a>
            <button class="navbar-toggler shadow-none ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#navigation" aria-controls="navigation" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon mt-2">
                    <span class="navbar-toggler-bar bar1"></span>
                    <span class="navbar-toggler-bar bar2"></span>
                    <span class="navbar-toggler-bar bar3"></span>
                </span>
            </button>
            <div class="collapse navbar-collapse" id="navigation">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link text-white" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="loan_application.php">Apply Loan</a></li>
                    <?php if (in_array($_SESSION['role'], ['approver', 'super_admin'])): ?>
                        <li class="nav-item"><a class="nav-link text-white" href="admin_loans.php">Manage Loans</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link text-white" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <main class="main-content mt-0">
        <div class="page-header align-items-start min-vh-50 pt-5 pb-11 m-3 border-radius-lg" style="background-image: url('../assets/img/agriloan-bg.jpg'); background-position: top;">
            <span class="mask bg-gradient-dark opacity-6"></span>
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-5 text-center mx-auto">
                        <h1 class="text-white mb-2 mt-5">Your Dashboard</h1>
                        <p class="text-lead text-white">View the status of your loan applications and repayment schedules</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="row mt-lg-n10 mt-md-n11 mt-n10 justify-content-center">
                <div class="col-xl-10 col-lg-11 col-md-12 mx-auto">
                    <div class="card z-index-0">
                        <div class="card-header text-center pt-4">
                            <h5>Your Loan Applications</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($loans)): ?>
                                <p class="text-center">No loan applications found. <a href="loan_application.php">Apply now</a>.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Loan ID</th>
                                                <th>Category</th>
                                                <th>Amount (TZS)</th>
                                                <th>Repayment Period</th>
                                                <th>Interest Rate</th>
                                                <th>Status</th>
                                                <th>Applied On</th>
                                                <th>Approval/Rejection Date</th>
                                                <th>Repayment Due</th>
                                                <th>Rejection Reason</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($loans as $loan): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($loan['loan_id']); ?></td>
                                                    <td><?php echo htmlspecialchars(ucfirst($loan['category'])); ?></td>
                                                    <td><?php echo number_format($loan['amount'], 2); ?></td>
                                                    <td><?php echo htmlspecialchars($loan['repayment_period']); ?> months</td>
                                                    <td><?php echo number_format($loan['interest_rate'], 2); ?>%</td>
                                                    <td><?php echo htmlspecialchars(ucfirst($loan['status'])); ?></td>
                                                    <td><?php echo htmlspecialchars($loan['application_date'] ? date('Y-m-d', strtotime($loan['application_date'])) : '-'); ?></td>
                                                    <td>
                                                        <?php
                                                        if ($loan['approval_date']) {
                                                            echo htmlspecialchars(date('Y-m-d', strtotime($loan['approval_date'])));
                                                        } elseif ($loan['rejection_date']) {
                                                            echo htmlspecialchars(date('Y-m-d', strtotime($loan['rejection_date'])));
                                                        } else {
                                                            echo '-';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($loan['repayment_due_date'] ? date('Y-m-d', strtotime($loan['repayment_due_date'])) : '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($loan['rejection_reason'] ?: '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($schedules)): ?>
                        <div class="card z-index-0 mt-4">
                            <div class="card-header text-center pt-4">
                                <h5>Your Repayment Schedules</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Loan ID</th>
                                                <th>Installment</th>
                                                <th>Due Date</th>
                                                <th>Amount Due (TZS)</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($schedules as $schedule): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($schedule['loan_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($schedule['installment_number']); ?></td>
                                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($schedule['due_date']))); ?></td>
                                                    <td><?php echo number_format($schedule['amount_due'], 2); ?></td>
                                                    <td><?php echo htmlspecialchars(ucfirst($schedule['status'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <footer class="footer py-5">
        <div class="container">
            <div class="row">
                <div class="col-8 mx-auto text-center">
                    <a href="javascript:;" class="text-secondary me-xl-5 me-3 mb-sm-0 mb-2">About Us</a>
                    <a href="javascript:;" class="text-secondary me-xl-5 me-3 mb-sm-0 mb-2">Team</a>
                </div>
            </div>
            <div class="row">
                <div class="col-8 mx-auto text-center mt-1">
                    <div class="text-secondary text-center">
                        © <script>document.write(new Date().getFullYear())</script> Agri-Loan Connect. All Rights Reserved.
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>
    <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.js"></script>
    <script>
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