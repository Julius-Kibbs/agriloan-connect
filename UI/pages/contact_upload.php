<?php
session_start();
include '../../database/connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$conn = db_agriloan_connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    try {
        $user_id = $_SESSION['user_id'];
        $complaint = filter_input(INPUT_POST, 'complaints', FILTER_SANITIZE_STRING);
        
    // Validate inputs
        if( empty($complaint) || strlen($complaint) < 10 || strlen($complaint) > 1000) {
            throw new Exception('Complaint must be between 10 and 1000 characters.');
        }

        // Insert complaint
        $stmt = $conn->prepare('INSERT INTO complaints (user_id, complaint, submitted_at) VALUES (?,?, NOW())');
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param('is', $user_id, $complaint);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        $stmt->close();

          // Debug: Complaint application
        file_put_contents('debug.log', "Complaint application: User:$user_id, Complaint: $complaint," . session_id() . "\n", FILE_APPEND);

        // Show success message and redirect
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Success!',
                    text: 'Complaint submitted! Await feedback.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = 'dashboard.php';
                });
            });
        </script>";
    } catch (Exception $e) {
        file_put_contents('debug.log', "Complaint application error: " . $e->getMessage() . "\n", FILE_APPEND);
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
