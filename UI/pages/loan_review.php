<?php
session_start();
include '../../database/connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$mysqli = db_agriloan_connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $loan_id = filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT);
        $action = $_POST['action'];

        if (!$loan_id) {
            throw new Exception('Invalid loan ID.');
        }

        if ($action === 'approve') {
            // Fetch loan details for payment schedule
            $stmt = $mysqli->prepare('SELECT amount, interest_rate, repayment_period, application_date FROM loans WHERE loan_id = ? AND status = "pending"');
            $stmt->bind_param('i', $loan_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $loan = $result->fetch_assoc();
            $stmt->close();

            if (!$loan) {
                throw new Exception('Loan not found or already processed.');
            }

            // Calculate payment schedule
            $amount = $loan['amount'];
            $interest_rate = $loan['interest_rate'] / 100;
            $repayment_period = $loan['repayment_period'];
            $application_date = $loan['application_date'];

            $total_interest = $amount * $interest_rate * ($repayment_period / 12);
            $total_amount = $amount + $total_interest;
            $monthly_installment = $total_amount / $repayment_period;

            // Begin transaction
            $mysqli->begin_transaction();

            // Update loan status
            $stmt = $mysqli->prepare('UPDATE loans SET status = "approved", approval_date = NOW(), repayment_due_date = DATE_ADD(application_date, INTERVAL repayment_period MONTH) WHERE loan_id = ?');
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $mysqli->error);
            }
            $stmt->bind_param('i', $loan_id);
            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            $stmt->close();

            // Generate payment schedules
            $stmt = $mysqli->prepare('INSERT INTO payment_schedules (loan_id, installment_number, due_date, amount_due, status) VALUES (?, ?, ?, ?, "pending")');
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $mysqli->error);
            }
            for ($i = 1; $i <= $repayment_period; $i++) {
                $due_date = date('Y-m-d', strtotime("$application_date + $i months"));
                $stmt->bind_param('iisd', $loan_id, $i, $due_date, $monthly_installment);
                if (!$stmt->execute()) {
                    throw new Exception('Execute failed: ' . $stmt->error);
                }
            }
            $stmt->close();

            // Commit transaction
            $mysqli->commit();

            file_put_contents('debug.log', "Loan approved: Loan ID $loan_id, user_id: {$_SESSION['user_id']}, schedules created: $repayment_period, session_id: " . session_id() . "\n", FILE_APPEND);

            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Loan approved and payment schedule created!',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.reload();
                    });
                });
            </script>";
        } elseif ($action === 'reject') {
            $rejection_reason = filter_input(INPUT_POST, 'rejection_reason', FILTER_SANITIZE_STRING);
            if (empty($rejection_reason) || strlen($rejection_reason) < 10) {
                throw new Exception('Rejection reason must be at least 10 characters.');
            }

            $stmt = $mysqli->prepare('UPDATE loans SET status = "rejected", rejection_reason = ?, rejection_date = NOW() WHERE loan_id = ? AND status = "pending"');
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $mysqli->error);
            }
            $stmt->bind_param('si', $rejection_reason, $loan_id);
            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            $affected_rows = $stmt->affected_rows;
            $stmt->close();

            if ($affected_rows === 0) {
                throw new Exception('Loan not found or already processed.');
            }

            file_put_contents('debug.log', "Loan rejected: Loan ID $loan_id, reason: $rejection_reason, user_id: {$_SESSION['user_id']}, session_id: " . session_id() . "\n", FILE_APPEND);

            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Loan rejected successfully!',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.reload();
                    });
                });
            </script>";
        } else {
            throw new Exception('Invalid action.');
        }
    } catch (Exception $e) {
        $mysqli->rollback();
        file_put_contents('debug.log', "Loan action error: " . $e->getMessage() . "\n", FILE_APPEND);
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Error!',
                    text: '" . addslashes($e->getMessage()) . "',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        </script>";
    }
}

// Fetch pending loans
$stmt = $mysqli->prepare('SELECT l.loan_id, u.full_name, l.category, l.amount, l.purpose, l.repayment_period, l.application_date FROM loans l JOIN users u ON l.user_id = u.user_id WHERE l.status = "pending"');
$stmt->execute();
$result = $stmt->get_result();
$loans = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <title>Admin Loans | Agri-Loan Connect</title>
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
        function showRejectModal(loanId) {
            Swal.fire({
                title: 'Reject Loan',
                input: 'textarea',
                inputLabel: 'Rejection Reason',
                inputPlaceholder: 'Enter reason for rejection...',
                showCancelButton: true,
                confirmButtonText: 'Reject',
                cancelButtonText: 'Cancel',
                preConfirm: (reason) => {
                    if (!reason || reason.length < 10) {
                        Swal.showValidationMessage('Reason must be at least 10 characters.');
                    }
                    return { loan_id: loanId, rejection_reason: reason };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    form.innerHTML = `
                        <input type="hidden" name="loan_id" value="${result.value.loan_id}">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="rejection_reason" value="${result.value.rejection_reason}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        function approveLoan(loanId) {
            Swal.fire({
                title: 'Approve Loan?',
                text: 'This will approve the loan and create a payment schedule.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Approve',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    form.innerHTML = `
                        <input type="hidden" name="loan_id" value="${loanId}">
                        <input type="hidden" name="action" value="approve">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
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
                    <li class="nav-item"><a class="nav-link text-white" href="admin_loans.php">Manage Loans</a></li>
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
                        <h1 class="text-white mb-2 mt-5">Manage Loans</h1>
                        <p class="text-lead text-white">Review and approve or reject loan applications</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="row mt-lg-n10 mt-md-n11 mt-n10 justify-content-center">
                <div class="col-xl-10 col-lg-11 col-md-12 mx-auto">
                    <div class="card z-index-0">
                        <div class="card-header text-center pt-4">
                            <h5>Pending Loan Applications</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($loans)): ?>
                                <p class="text-center">No pending loan applications.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Loan ID</th>
                                                <th>Applicant</th>
                                                <th>Category</th>
                                                <th>Amount (TZS)</th>
                                                <th>Purpose</th>
                                                <th>Repayment Period</th>
                                                <th>Applied On</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($loans as $loan): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($loan['loan_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($loan['full_name']); ?></td>
                                                    <td><?php echo htmlspecialchars(ucfirst($loan['category'])); ?></td>
                                                    <td><?php echo number_format($loan['amount'], 2); ?></td>
                                                    <td><?php echo htmlspecialchars(substr($loan['purpose'], 0, 50)) . (strlen($loan['purpose']) > 50 ? '...' : ''); ?></td>
                                                    <td><?php echo htmlspecialchars($loan['repayment_period']); ?> months</td>
                                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($loan['application_date']))); ?></td>
                                                    <td>
                                                        <button class="btn btn-success btn-sm" onclick="approveLoan(<?php echo $loan['loan_id']; ?>)">Approve</button>
                                                        <button class="btn btn-danger btn-sm" onclick="showRejectModal(<?php echo $loan['loan_id']; ?>)">Reject</button>
                                                    </td>
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