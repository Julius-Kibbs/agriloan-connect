<?php
session_start();
include '../../database/connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$mysqli = db_agriloan_connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    try {
        $user_id = $_SESSION['user_id'];
        $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $purpose = filter_input(INPUT_POST, 'purpose', FILTER_SANITIZE_STRING);
        $period = filter_input(INPUT_POST, 'repayment_period', FILTER_VALIDATE_INT);

        // Validate inputs
        if (!in_array($category, ['money', 'utilities', 'equipment'])) {
            throw new Exception('Invalid loan category.');
        }
        if ($amount < 1000 || $amount > 1000000) {
            throw new Exception('Amount must be between 1,000 and 1,000,000 TZS.');
        }
        if (strlen($purpose) < 10 || strlen($purpose) > 1000) {
            throw new Exception('Purpose must be 10-1000 characters.');
        }
        if ($period < 1 || $period > 60) {
            throw new Exception('Period must be between 1 and 60 months.');
        }

        // Set interest rate based on category
        $interest_rate = $category === 'money' ? 5.00 : ($category === 'utilities' ? 7.00 : 10.00);

        // Insert loan application
        $stmt = $mysqli->prepare('INSERT INTO loans (user_id, category, amount, purpose, repayment_period, interest_rate, status) VALUES (?, ?, ?, ?, ?, ?, "pending")');
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $mysqli->error);
        }
        $stmt->bind_param('isdssi', $user_id, $category, $amount, $purpose, $period, $interest_rate);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        $stmt->close();

        // Debug: Log successful application
        file_put_contents('debug.log', "Loan application: User $user_id, category: $category, amount: $amount, session_id: " . session_id() . "\n", FILE_APPEND);

        // Show success message and redirect
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Success!',
                    text: 'Loan application submitted! Await approval.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = 'dashboard.php';
                });
            });
        </script>";
    } catch (Exception $e) {
        file_put_contents('debug.log', "Loan application error: " . $e->getMessage() . "\n", FILE_APPEND);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.js"></script>
</head>
<body>
</body>
</html>