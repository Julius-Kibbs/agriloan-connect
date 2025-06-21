<?php
ob_start(); // Start output buffering
session_start();
ini_set('display_errors', 0); // Disable error display
include '../../database/connection.php';

header('Content-Type: application/json');

// Debug: Log server info
file_put_contents('debug.log', "Verify OTP: DOCUMENT_ROOT=" . $_SERVER['DOCUMENT_ROOT'] . ", SCRIPT_FILENAME=" . $_SERVER['SCRIPT_FILENAME'] . "\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Parse JSON input
        $raw_input = file_get_contents('php://input');
        file_put_contents('debug.log', "Verify OTP: Raw input: $raw_input\n", FILE_APPEND);
        $input = json_decode($raw_input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }
        if (!isset($input['otp']) || !isset($input['phone_number'])) {
            throw new Exception('Missing OTP or phone number in request.');
        }

        $input_otp = filter_var($input['otp'], FILTER_SANITIZE_STRING);
        $phone_number = filter_var($input['phone_number'], FILTER_SANITIZE_STRING);

        // Debug: Log request and session data
        file_put_contents('debug.log', "Verify OTP: phone=$phone_number, otp=$input_otp, session_id=" . session_id() . ", session=" . print_r($_SESSION, true) . "\n", FILE_APPEND);

        if (!isset($_SESSION['temp_user']) || $_SESSION['temp_user']['phone_number'] !== $phone_number) {
            throw new Exception('Invalid session or phone number.');
        }

        try {
            $db = db_agriloan_connect();
        } catch (Exception $e) {
            file_put_contents('debug.log', "Verify OTP: Database connection error: " . $e->getMessage() . "\n", FILE_APPEND);
            throw new Exception('Database connection failed.');
        }

        $stmt = $db->prepare('SELECT otp_code, otp_expiry FROM users WHERE phone_number = ?');
        $stmt->execute([$phone_number]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception('User not found.');
        }

        if (strtotime($user['otp_expiry']) < time()) {
            throw new Exception('OTP has expired.');
        }

        if ($user['otp_code'] !== $input_otp) {
            throw new Exception('Invalid OTP.');
        }

        // Clear OTP after verification
        $stmt = $db->prepare('UPDATE users SET otp_code = NULL, otp_expiry = NULL WHERE phone_number = ?');
        $stmt->execute([$phone_number]);

        // Clear temp session
        unset($_SESSION['temp_user']);

        echo json_encode(['success' => true, 'message' => 'OTP verified successfully']);
    } catch (Exception $e) {
        file_put_contents('debug.log', "Verify OTP error: " . $e->getMessage() . "\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    file_put_contents('debug.log', "Verify OTP: Invalid request method: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

ob_end_flush(); // End output buffering
?>