<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users in API response

// Set content type to JSON
header('Content-Type: application/json');

// Include database connection
require 'connect.php';

// Function to standardize time format
function standardizeTimeFormat($timeStr) {
    // Remove extra spaces
    $timeStr = trim($timeStr);
    
    // Check if there's a space before AM/PM
    if (preg_match('/(\d+:\d+)\s*(AM|PM)/i', $timeStr, $matches)) {
        return $matches[1] . ' ' . strtoupper($matches[2]);
    }
    
    // If no space before AM/PM, add one
    if (preg_match('/(\d+:\d+)(AM|PM)/i', $timeStr, $matches)) {
        return $matches[1] . ' ' . strtoupper($matches[2]);
    }
    
    // Return original if no pattern matched
    return $timeStr;
}

// Initialize response
$response = [
    'available' => false,
    'error' => null
];

// Check if date and time parameters are provided
if (isset($_GET['date']) && isset($_GET['time'])) {
    $date = $_GET['date'];
    $time = standardizeTimeFormat($_GET['time']);
    
    // Check if the time is 11:00 AM (which should be disabled)
    if (preg_match('/^11:00\s*AM$/i', $time)) {
        $response['available'] = false;
        $response['error'] = "Appointments at 11:00 AM are not available.";
        error_log("API check for disabled 11:00 AM slot: Date: $date");
        echo json_encode($response);
        exit;
    }
    
    // Check if the selected date and time are in the past or too close to current time
    try {
        $selectedDateTime = new DateTime($date . ' ' . $time);
        $currentDateTime = new DateTime();
        $currentDateTime->modify('+4 minutes'); // Reduced from 15 to 4 minutes buffer
        
        if ($selectedDateTime < $currentDateTime) {
            $response['available'] = false;
            $response['error'] = "Cannot book appointments in the past or too close to the current time.";
            error_log("API check for past time: Date: $date, Time: $time");
            echo json_encode($response);
            exit;
        }
    } catch (Exception $e) {
        error_log("Error parsing date/time in check_slot.php: " . $e->getMessage());
        // Continue with the rest of the checks even if date parsing fails
    }
    
    try {
        // Check if the slot is already booked
        $query = "SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND appointment_time = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $stmt->bind_param("ss", $date, $time);
        
        if (!$stmt->execute()) {
            throw new Exception("Database execute error: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // If count is 0, the slot is available
        $response['available'] = ($row['count'] == 0);
        
        // Log the check for debugging
        error_log("Slot availability check: Date: $date, Time: $time, Available: " . ($response['available'] ? 'Yes' : 'No'));
        
    } catch (Exception $e) {
        $response['error'] = "An error occurred while checking slot availability.";
        error_log("Error in check_slot.php: " . $e->getMessage());
    }
} else {
    $response['error'] = "Date and time parameters are required.";
    error_log("Missing parameters in check_slot.php: date=" . ($_GET['date'] ?? 'missing') . ", time=" . ($_GET['time'] ?? 'missing'));
}

// Return JSON response
echo json_encode($response);
exit;
?> 