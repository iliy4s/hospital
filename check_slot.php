<?php
// Start session and include database connection
session_start();
require_once 'connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Initialize response
$response = [
    'available' => true,
    'message' => ''
];

// Verify inputs
if (empty($_GET['date']) || empty($_GET['time'])) {
    $response['available'] = false;
    $response['message'] = 'Missing date or time parameters';
    echo json_encode($response);
    exit;
}

// Sanitize inputs
$date = trim($_GET['date']);
$time = trim($_GET['time']);

// Validate date format
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
    $response['available'] = false;
    $response['message'] = 'Invalid date format';
    echo json_encode($response);
    exit;
}

// Check if the slot is available
try {
    $checkQuery = "SELECT id FROM appointments 
                  WHERE appointment_date = ? 
                  AND appointment_time = ?
                  AND status != 'cancelled'";
    
    $checkStmt = $conn->prepare($checkQuery);
    
    if (!$checkStmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $checkStmt->bind_param("ss", $date, $time);
    
    if (!$checkStmt->execute()) {
        throw new Exception("Database execute error: " . $checkStmt->error);
    }
    
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $response['available'] = false;
        $response['message'] = 'Slot already booked';
    }
    
} catch (Exception $e) {
    // Log error but return generic message to client
    error_log("Error checking slot availability: " . $e->getMessage());
    $response['available'] = false;
    $response['message'] = 'Error checking slot availability';
}

echo json_encode($response);
?> 