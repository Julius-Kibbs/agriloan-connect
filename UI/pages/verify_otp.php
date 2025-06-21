<?php
session_start();
include '../../database/connection.php';

$db = db_agriloan_connect();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input_otp = filter_input(INPUT_POST, 'otp', FILTER_SANITIZE_STRING);
        $phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING);

        if (!isset($_SESSION['temp_user']) || $_SESSION['temp_user']['phone_number'] !== $phone_number) {
            throw new Exception('Invalid session or phone number.');
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
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
