<?php 
// Start session with secure cookie settings
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

require 'connect.php';

// Disable error display in output and enable error logging
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Function to handle JSON responses with proper headers
function sendJsonResponse($success, $message = '', $errors = []) {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    // Set security and cache headers
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Access-Control-Allow-Origin: same-origin');
    
    // Create response array
    $response = [
        'success' => $success,
        'message' => $message,
        'errors' => $errors,
        'timestamp' => time()
    ];
    
    // Log response for debugging
    error_log("Sending JSON response: " . json_encode($response));
    
    // Send JSON response
    echo json_encode($response);
    exit;
}

// Function to verify reCAPTCHA response with error handling
function verifyRecaptcha($recaptchaResponse) {
    try {
        $secretKey = "6LfniQwrAAAAACQeUZh84dkPvYtV5Fxa5baBHa-D"; // Updated server secret key
        $url = "https://www.google.com/recaptcha/api/siteverify";
        
        // Use cURL instead of file_get_contents for better error handling
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'secret' => $secretKey,
                'response' => $recaptchaResponse
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($error) {
            error_log("reCAPTCHA cURL Error: " . $error);
            return false;
        }
        
        if ($httpCode !== 200) {
            error_log("reCAPTCHA HTTP Error: " . $httpCode);
            return false;
        }
        
        $responseData = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("reCAPTCHA JSON Error: " . json_last_error_msg());
            return false;
        }
        
        // Log reCAPTCHA response for debugging
        error_log("reCAPTCHA Response: " . print_r($responseData, true));
        
        return $responseData["success"] ?? false;
    } catch (Exception $e) {
        error_log("reCAPTCHA verification error: " . $e->getMessage());
        return false;
    }
}

// Initialize variables to retain form values after submission
$patientFirstName = $patientLastName = $patientPreferredName = $contactNumber = $email = '';
$dobMonth = $dobDay = $dobYear = $preferredSpecialty = $reasonForAppointment = '';
$errors = [];
$formSubmitted = false;

// Store appointment date and time in session from URL parameters if provided
if (isset($_GET['date']) && isset($_GET['time'])) {
    $_SESSION['appointment_date'] = $_GET['date'];
    $_SESSION['appointment_time'] = $_GET['time'];
}

// Get appointment details from session
$appointmentDate = $_SESSION['appointment_date'] ?? '';
$appointmentTime = $_SESSION['appointment_time'] ?? '';

// Display error or redirect if session data is missing
if (!$appointmentDate || !$appointmentTime) {
    error_log("Missing appointment data in session. Date: " . ($appointmentDate ?: 'empty') . ", Time: " . ($appointmentTime ?: 'empty'));
    $_SESSION['booking_error'] = "Your session has expired or is invalid. Please select a time slot again.";
    header("Location: slot-booking.php");
    exit;
}

// Standardize time format for consistency
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

// Standardize the appointment time format
$appointmentTime = standardizeTimeFormat($appointmentTime);

// Check if the selected date and time are in the past or too close to current time
try {
    $selectedDateTime = new DateTime($appointmentDate . ' ' . $appointmentTime);
    $currentDateTime = new DateTime();
    $currentDateTime->modify('+4 minutes'); // Reduced from 15 to 4 minutes buffer
    
    if ($selectedDateTime < $currentDateTime) {
        error_log("Registration attempt for past time: Date: $appointmentDate, Time: $appointmentTime");
        $_SESSION['booking_error'] = "Sorry, you cannot book appointments in the past or too close to the current time. Please select a future time slot.";
        header("Location: slot-booking.php");
        exit;
    }
} catch (Exception $e) {
    error_log("Error parsing date/time in registration-form.php: " . $e->getMessage());
    // Continue with the rest of the checks even if date parsing fails
}

// First, verify the slot is still available before processing - improved query with error handling
try {
    $checkQuery = "SELECT * FROM appointments 
                  WHERE appointment_date = ? 
                  AND appointment_time = ?";  // Removed status filter to check ALL appointments
    $checkStmt = $conn->prepare($checkQuery);
    
    if (!$checkStmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $checkStmt->bind_param("ss", $appointmentDate, $appointmentTime);
    
    if (!$checkStmt->execute()) {
        throw new Exception("Database execute error: " . $checkStmt->error);
    }
    
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Get details about the conflicting appointment for logging
        $conflictRow = $checkResult->fetch_assoc();
        $conflictId = $conflictRow['id'] ?? 'unknown';
        
        // Enhanced error logging
        error_log("BOOKING CONFLICT: Attempted to access registration form for date: $appointmentDate, time: $appointmentTime, which is already booked (appointment ID: $conflictId)");
        
        // Slot already booked - redirect back with error
        $_SESSION['booking_error'] = "Sorry, this time slot has already been booked by someone else. Please select another time.";
        header("Location: slot-booking.php?error=already_booked&time=" . urlencode($appointmentTime));
        exit;
    }
} catch (Exception $e) {
    // Log the error and show a user-friendly message
    error_log("Error checking slot availability: " . $e->getMessage());
    $_SESSION['booking_error'] = "We encountered a technical issue. Please try again or contact support.";
    header("Location: slot-booking.php");
    exit;
}

// Log successful availability check
error_log("Slot availability verified for date: $appointmentDate, time: $appointmentTime - proceeding to registration form");

// Format the selected date for display
$formattedDate = '';
if (!empty($appointmentDate)) {
    $dateObj = new DateTime($appointmentDate);
    $formattedDate = $dateObj->format('l, F j, Y'); // e.g., Monday, March 10, 2025
}

// Enhanced test_input function
function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $data;
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Clear any previous output
        if (ob_get_length()) ob_clean();
        
        // Initialize response array
        $response = [
            'success' => false,
            'message' => '',
            'errors' => []
        ];

        // Log POST data for debugging (excluding sensitive information)
        $debugPostData = $_POST;
        unset($debugPostData['g-recaptcha-response']); // Remove sensitive data for logging
        error_log("Form submission received: " . json_encode($debugPostData));

        // Verify reCAPTCHA first
        $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
        
        if (empty($recaptchaResponse)) {
            error_log("reCAPTCHA response missing");
            sendJsonResponse(false, 'Please complete the reCAPTCHA verification.');
            exit;
        }

        error_log("reCAPTCHA response received, verifying...");
        // Verify reCAPTCHA with improved error handling
        $recaptchaResult = verifyRecaptcha($recaptchaResponse);
        if (!$recaptchaResult) {
            error_log("reCAPTCHA verification failed");
            sendJsonResponse(false, 'reCAPTCHA verification failed. Please try again.');
            exit;
        }
        error_log("reCAPTCHA verification successful");

        // CSRF protection
        if (!isset($_SESSION['csrf_token']) || !isset($_POST['csrf_token']) || 
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            error_log("CSRF token validation failed");
            sendJsonResponse(false, 'Invalid form submission. Please refresh the page and try again.');
            exit;
        }

        // Process form data
        $patientFirstName = test_input($_POST['patientFirstName'] ?? '');
        $patientLastName = test_input($_POST['patientLastName'] ?? '');
        $contactNumber = test_input($_POST['contactNumber'] ?? '');
        $email = test_input($_POST['email'] ?? '');
        $reasonForAppointment = test_input($_POST['reasonForAppointment'] ?? '');
        $patientPreferredName = test_input($_POST['patientPreferredName'] ?? '');
        $preferredSpecialty = test_input($_POST['preferredSpecialty'] ?? '');

        // Validate required fields
        $errors = [];
        if (empty($patientFirstName) || !preg_match("/^[a-zA-Z-' ]{1,50}$/", $patientFirstName)) {
            $errors['patientFirstName'] = 'First name is required and must contain only letters';
        }
        if (empty($patientLastName) || !preg_match("/^[a-zA-Z-' ]{1,50}$/", $patientLastName)) {
            $errors['patientLastName'] = 'Last name is required and must contain only letters';
        }
        if (empty($contactNumber) || !preg_match("/^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/", $contactNumber)) {
            $errors['contactNumber'] = 'Please enter a valid phone number';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        }
        if (empty($reasonForAppointment)) {
            $errors['reasonForAppointment'] = 'Reason for appointment is required';
        }

        // Process DOB
        $dobMonth = $_POST['dobMonth'] ?? '';
        $dobDay = $_POST['dobDay'] ?? '';
        $dobYear = $_POST['dobYear'] ?? '';
        
        if (!empty($dobMonth) && !empty($dobDay) && !empty($dobYear)) {
            $dob = sprintf('%04d-%02d-%02d', $dobYear, $dobMonth, $dobDay);
        } else {
            $dob = null;
        }

        // If there are validation errors, return them
        if (!empty($errors)) {
            sendJsonResponse(false, 'Please correct the errors and try again.', $errors);
            exit;
        }

        try {
            $conn->begin_transaction();

            // Check slot availability
            $checkSlotSql = "SELECT id FROM appointments 
                           WHERE appointment_date = ? 
                           AND appointment_time = ?
                           AND status != 'cancelled'
                           FOR UPDATE";
            
            $checkStmt = $conn->prepare($checkSlotSql);
            if (!$checkStmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $checkStmt->bind_param("ss", $appointmentDate, $appointmentTime);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Slot already booked");
            }

            // Generate booking reference
            $bookingReference = 'APT-' . date('Ymd') . '-' . substr(uniqid(), -5);
            
            // Insert appointment
            $insertSql = "INSERT INTO appointments (
                appointment_date, appointment_time, patient_first_name, patient_last_name, 
                patient_preferred_name, dob, contact_number, email, preferred_specialty, 
                reason_for_appointment, status, created_at, booking_reference
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW(), ?)";
            
            $stmt = $conn->prepare($insertSql);
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $stmt->bind_param(
                "sssssssssss", 
                $appointmentDate, $appointmentTime, $patientFirstName, $patientLastName, 
                $patientPreferredName, $dob, $contactNumber, $email, $preferredSpecialty, 
                $reasonForAppointment, $bookingReference
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to save appointment: " . $stmt->error);
            }
            
            $newAppointmentId = $stmt->insert_id;
            
            // Log success and commit transaction
            error_log("Appointment booked successfully - ID: $newAppointmentId, Ref: $bookingReference");
            $conn->commit();
            
            // Store booking info in session
            $_SESSION['booking_reference'] = $bookingReference;
            $_SESSION['appointment_id'] = $newAppointmentId;
            
            // Clear appointment session data
            unset($_SESSION['appointment_date']);
            unset($_SESSION['appointment_time']);
            
            // Send confirmation email
            try {
                sendConfirmationEmail($email, [
                    'reference' => $bookingReference,
                    'date' => $formattedDate,
                    'time' => $appointmentTime,
                    'name' => $patientFirstName
                ]);
            } catch (Exception $e) {
                error_log("Failed to send confirmation email: " . $e->getMessage());
            }
            
            sendJsonResponse(true, 'Appointment booked successfully!');
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Appointment booking failed: " . $e->getMessage());
            
            if ($e->getMessage() === "Slot already booked") {
                sendJsonResponse(false, "This slot was just taken. Please select another time.");
            } else {
                sendJsonResponse(false, "An error occurred while processing your request. Please try again.");
            }
        }
        
    } catch (Exception $e) {
        error_log("Form submission error: " . $e->getMessage());
        sendJsonResponse(false, 'An error occurred while processing your request. Please try again.');
    }
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$months = [
    1 => "January", 2 => "February", 3 => "March", 4 => "April",
    5 => "May", 6 => "June", 7 => "July", 8 => "August",
    9 => "September", 10 => "October", 11 => "November", 12 => "December"
];

$specialties = [
    "Headache", "Fits", "Giddiness", "Paralysis", "Memory loss",
    "Neck and back pain", "Parkinson's", "Hands and legs tingling"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Appointment Request Form</title>
    <script src="https://www.google.com/recaptcha/api.js?render=explicit" async defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
    <link rel="stylesheet" href="css/animations.css"><?php // Include our custom animations ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Close button styles */
        .close-button {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #7047d1;
            color: white;
            border: none;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s ease;
            z-index: 1000;
        }
        
        .close-button:hover {
            background-color: #5d3cb5;
        }
        
        /* Position relative for the container to properly position the close button */
        .bg-white {
            position: relative;
        }

        /* Existing styles */
        .error-message {
            color: #dc3545;
            font-size: 0.9375rem;
            margin-top: 0.25rem;
            margin-bottom: 0.25rem;
            display: block;
            clear: both;
        }
        .form-control.is-invalid {
            border-color: #dc3545 !important;
            border-width: 2px !important;
            background-image: none !important;
        }
        .appointment-summary {
            background-color: #f4e6fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 12px;
            border-left: 4px solid #7047d1;
            font-size: 1rem;
        }
        .appointment-date {
            font-weight: 700;
            color: #7047d1;
            font-size: 1.05rem;
        }
        .appointment-time {
            font-weight: 700;
            color: #7047d1;
            font-size: 1.05rem;
        }
        body {
            margin: 0;
            padding: 12px;
            background: #f8f9fa;
            color: #333;
        }
        .container {
            max-width: 100%;
            padding: 0;
        }
        .card {
            margin: 0;
            box-shadow: none;
            padding: 0.5rem;
        }
        .btn {
            padding: 0.5rem 1rem;
        }
        @media (max-width: 768px) {
            body {
                padding: 6px;
            }
            .card {
                padding: 0.5rem;
            }
            .form-group {
                margin-bottom: 0.75rem;
                position: relative;
            }
        }
        .spinner-border-sm {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 0.2em solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner-border .75s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spinner-border {
            to { transform: rotate(360deg); }
        }
        
        /* Add styles for success animation container */
        .success-animation {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            max-width: 300px;
        }
        
        .hidden {
            display: none;
        }
        
        /* Form field containers - REDUCED MARGINS */
        .form-field-container {
            margin-bottom: 0.5rem;
            position: relative;
        }
        
        /* Validation icon positioning - REMOVED */
        .validation-icon {
            display: none; /* Hide validation icons */
        }
        
        /* Alternative error indication with border */
        .form-control.is-invalid {
            border-color: #dc3545 !important;
            border-width: 2px !important;
            background-image: none !important;
        }
        
        /* Ensure error messages appear below inputs - REDUCED HEIGHT */
        .error-message-container {
            min-height: 16px;
            margin-top: 0.125rem;
        }
        
        /* Form grid spacing */
        .form-grid {
            display: grid;
            grid-gap: 0.5rem;
        }
        
        /* Reduce label spacing */
        label {
            margin-bottom: 0.125rem !important;
            display: block;
            font-size: 0.9375rem;
            font-weight: 600 !important;
            color: #4a5568;
        }
        
        /* Form inputs padding */
        .form-control {
            padding: 0.375rem 0.625rem !important;
            height: auto !important;
            border-radius: 6px !important;
            border: 1px solid #e2e8f0 !important;
            transition: border-color 0.15s ease-in-out;
            font-weight: 500;
            font-size: 1rem !important;
        }
        
        .form-control:focus {
            border-color: #7047d1 !important;
            box-shadow: 0 0 0 3px rgba(112, 71, 209, 0.15) !important;
            outline: none;
        }
        
        /* Content spacing */
        .bg-white {
            padding: 1rem !important;
            border-radius: 8px !important;
        }
        
        /* Section headers */
        h2.text-xl {
            margin-bottom: 0.75rem !important;
            color: #4a5568;
            font-size: 1.375rem !important;
            font-weight: 700 !important;
        }
        
        /* Adjust spacing for DOB selects */
        .dob-container select {
            padding: 0.375rem 0.5rem !important;
        }
        
        /* Submit button styling */
        button[type="submit"] {
            background-color: #7047d1 !important;
            transition: all 0.2s ease;
            font-weight: 600 !important;
            letter-spacing: 0.025em;
            font-size: 1.0625rem !important;
        }
        
        button[type="submit"]:hover {
            background-color: #5d3cb5 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(112, 71, 209, 0.1);
        }
        
        /* Form sections */
        .form-section {
            margin-bottom: 0.75rem;
            padding-bottom: 0.375rem;
        }
        
        .form-section-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #4a5568;
            margin-bottom: 0.5rem;
        }
        
        textarea.form-control {
            min-height: 70px;
        }
        
        /* Show form message */
        #formMessage {
            font-size: 1rem !important;
            padding: 0.5rem 1rem !important;
        }
    </style>
</head>
<body class="bg-gray-100 flex justify-center items-center min-h-screen py-4">
    <div class="bg-white p-4 rounded-lg shadow-lg w-full max-w-lg mx-4">
        <button class="close-button" onclick="window.location.href='slot-booking.php';">×</button>
        <?php if ($formSubmitted): ?>
            <!-- Success message -->
            <div class="text-center py-8">
                <svg class="mx-auto h-16 w-16 text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h2 class="text-2xl font-bold mb-2 text-green-600">Appointment Request Submitted</h2>
                <p class="mb-6 text-gray-600">Thank you, <?php echo htmlspecialchars($patientFirstName); ?>! We'll contact you shortly to confirm your appointment for <?php echo htmlspecialchars($formattedDate); ?> at <?php echo htmlspecialchars($appointmentTime); ?>.</p>
                <a href="index.php" class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition">Return to Home</a>
            </div>
        <?php else: ?>
            <!-- Appointment Summary -->
            <div class="appointment-summary">
                <h3 class="text-lg font-bold mb-1" style="font-size: 1.125rem;">Appointment Details</h3>
                <p class="font-medium">Date: <span class="appointment-date"><?php echo htmlspecialchars($formattedDate); ?></span></p>
                <p class="font-medium">Time: <span class="appointment-time"><?php echo htmlspecialchars($appointmentTime); ?></span></p>
            </div>

            <h2 class="text-xl font-bold mb-3 text-center">Patient Information</h2>
            
            <!-- Success animation container (hidden by default) -->
            <div id="successAnimation" class="success-animation hidden fade-in">
                <dotlottie-player src="https://lottie.host/66a77625-724a-4d65-ae1e-4011b2c2aa67/MUBSjtzlHu.lottie" background="transparent" speed="1" style="width: 300px; height: 300px" loop autoplay></dotlottie-player>
                <h3 class="text-xl font-bold text-green-600 mt-4">Appointment Booked Successfully!</h3>
                <p class="text-gray-600 mb-2">Your appointment has been confirmed.</p>
                <p class="text-gray-500 text-sm">You will be redirected to confirmation page...</p>
            </div>
            
            <form id="registrationForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <!-- Hidden fields to preserve appointment information -->
                <input type="hidden" name="appointmentDate" value="<?php echo htmlspecialchars($appointmentDate); ?>">
                <input type="hidden" name="appointmentTime" value="<?php echo htmlspecialchars($appointmentTime); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <!-- Form message container for showing success/error messages -->
                <div id="formMessage" class="hidden mb-3 p-2 rounded"></div>

                <div class="form-section">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-2 form-grid">
                        <!-- First Name -->
                        <div class="form-field-container">
                            <label for="patientFirstName" class="block mb-1 font-semibold text-gray-700">First Name *</label>
                            <div class="relative">
                                <input type="text" id="patientFirstName" name="patientFirstName" value="<?php echo htmlspecialchars($patientFirstName); ?>" 
                                    class="form-control w-full px-4 py-2 border rounded-lg <?php echo isset($errors['patientFirstName']) ? 'is-invalid border-red-500' : ''; ?>"
                                    placeholder="Enter first name">
                            </div>
                            <div class="error-message-container">
                                <?php if (isset($errors['patientFirstName'])): ?>
                                    <div class="error-message"><?php echo $errors['patientFirstName']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Last Name -->
                        <div class="form-field-container">
                            <label for="patientLastName" class="block mb-1 font-semibold text-gray-700">Last Name *</label>
                            <div class="relative">
                                <input type="text" id="patientLastName" name="patientLastName" value="<?php echo htmlspecialchars($patientLastName); ?>" 
                                    class="form-control w-full px-4 py-2 border rounded-lg <?php echo isset($errors['patientLastName']) ? 'is-invalid border-red-500' : ''; ?>"
                                    placeholder="Enter last name">
                            </div>
                            <div class="error-message-container">
                                <?php if (isset($errors['patientLastName'])): ?>
                                    <div class="error-message"><?php echo $errors['patientLastName']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Preferred Name -->
                        <div class="form-field-container">
                            <label for="patientPreferredName" class="block mb-1 font-semibold text-gray-700">Preferred Name (Optional)</label>
                            <input type="text" id="patientPreferredName" name="patientPreferredName" value="<?php echo htmlspecialchars($patientPreferredName); ?>" 
                                class="form-control w-full px-4 py-2 border rounded-lg"
                                placeholder="Nickname (optional)">
                        </div>
                        
                        <!-- Date of Birth -->
                        <div class="form-field-container">
                            <label class="block mb-1 font-semibold text-gray-700">Date of Birth *</label>
                            <div class="grid grid-cols-3 gap-1 dob-container">
                                <select name="dobMonth" class="form-control px-2 py-2 border rounded-lg <?php echo isset($errors['dob']) ? 'is-invalid border-red-500' : ''; ?>">
                                    <option value="">Month</option>
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" <?php echo $dobMonth == $num ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <select name="dobDay" class="form-control px-2 py-2 border rounded-lg <?php echo isset($errors['dob']) ? 'is-invalid border-red-500' : ''; ?>">
                                    <option value="">Day</option>
                                    <?php for ($i = 1; $i <= 31; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $dobDay == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                                
                                <select name="dobYear" class="form-control px-2 py-2 border rounded-lg <?php echo isset($errors['dob']) ? 'is-invalid border-red-500' : ''; ?>">
                                    <option value="">Year</option>
                                    <?php 
                                    $currentYear = date('Y');
                                    for ($i = $currentYear; $i >= $currentYear - 100; $i--): 
                                    ?>
                                        <option value="<?php echo $i; ?>" <?php echo $dobYear == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="error-message-container">
                                <?php if (isset($errors['dob'])): ?>
                                    <div class="error-message"><?php echo $errors['dob']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-2 form-grid">
                        <!-- Contact Number -->
                        <div class="form-field-container">
                            <label for="contactNumber" class="block mb-1 font-semibold text-gray-700">Contact Number *</label>
                            <div class="relative">
                                <input type="tel" id="contactNumber" name="contactNumber" value="<?php echo htmlspecialchars($contactNumber); ?>" 
                                    class="form-control w-full px-4 py-2 border rounded-lg <?php echo isset($errors['contactNumber']) ? 'is-invalid border-red-500' : ''; ?>" 
                                    placeholder="Phone number">
                            </div>
                            <div class="error-message-container">
                                <?php if (isset($errors['contactNumber'])): ?>
                                    <div class="error-message"><?php echo $errors['contactNumber']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Email -->
                        <div class="form-field-container">
                            <label for="email" class="block mb-1 font-semibold text-gray-700">Email *</label>
                            <div class="relative">
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" 
                                    class="form-control w-full px-4 py-2 border rounded-lg <?php echo isset($errors['email']) ? 'is-invalid border-red-500' : ''; ?>" 
                                    placeholder="your@email.com">
                            </div>
                            <div class="error-message-container">
                                <?php if (isset($errors['email'])): ?>
                                    <div class="error-message"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <!-- Preferred Specialty -->
                    <div class="form-field-container mb-2">
                        <label for="preferredSpecialty" class="block mb-1 font-semibold text-gray-700">Preferred Specialty</label>
                        <select id="preferredSpecialty" name="preferredSpecialty" class="form-control w-full px-4 py-2 border rounded-lg">
                            <option value="">Select a specialty (optional)</option>
                            <?php foreach ($specialties as $specialty): ?>
                                <option value="<?php echo htmlspecialchars($specialty); ?>" <?php echo $preferredSpecialty == $specialty ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($specialty); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Reason for Appointment -->
                    <div class="form-field-container mb-3">
                        <label for="reasonForAppointment" class="block mb-1 font-semibold text-gray-700">Reason for Appointment *</label>
                        <div class="relative">
                            <textarea id="reasonForAppointment" name="reasonForAppointment" rows="3" 
                                class="form-control w-full px-4 py-2 border rounded-lg <?php echo isset($errors['reasonForAppointment']) ? 'is-invalid border-red-500' : ''; ?>" 
                                placeholder="Describe your symptoms or reason for visit"><?php echo htmlspecialchars($reasonForAppointment); ?></textarea>
                        </div>
                        <div class="error-message-container">
                            <?php if (isset($errors['reasonForAppointment'])): ?>
                                <div class="error-message"><?php echo $errors['reasonForAppointment']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- General error message for appointment slot -->
                <?php if (isset($errors['appointmentSlot'])): ?>
                    <div class="mb-3 p-2 bg-red-100 border border-red-400 text-red-700 rounded">
                        <?php echo $errors['appointmentSlot']; ?>
                    </div>
                <?php endif; ?>

                <!-- Add reCAPTCHA container -->
                <div id="recaptcha-container" class="mb-3 flex justify-center"></div>

                <!-- Submit Button -->
                <button type="submit" id="submitBtn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg font-semibold transition" disabled>
                    Submit Appointment Request
                </button>

                <!-- reCAPTCHA error message -->
                <div class="error-message-container mt-2">
                    <div class="error-message" id="recaptchaError" style="display: none;"></div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        let recaptchaWidget = null;
        
        // Initialize reCAPTCHA
        function initRecaptcha() {
            try {
                recaptchaWidget = grecaptcha.render('recaptcha-container', {
                    'sitekey': '6LfniQwrAAAAAILA5s7Wi65u0rIgH4IzwqIomfcN', // Updated client site key
                    'callback': enableSubmit,
                    'expired-callback': disableSubmit,
                    'error-callback': handleRecaptchaError,
                    'theme': 'light',
                    'size': 'normal'
                });
                console.log('reCAPTCHA initialized successfully');
            } catch (error) {
                console.error('Error initializing reCAPTCHA:', error);
                showRecaptchaError('Error loading reCAPTCHA. Please refresh the page.');
            }
        }
        
        // Load reCAPTCHA when the page is ready
        window.onload = function() {
            if (typeof grecaptcha === 'undefined') {
                showRecaptchaError('reCAPTCHA failed to load. Please check your internet connection and refresh the page.');
                return;
            }
            initRecaptcha();
        };
        
        // reCAPTCHA callback functions
        function enableSubmit(response) {
            if (response) {
                document.getElementById('submitBtn').disabled = false;
                document.getElementById('recaptchaError').style.display = 'none';
                console.log('reCAPTCHA verified successfully');
            }
        }
        
        function disableSubmit() {
            document.getElementById('submitBtn').disabled = true;
            showRecaptchaError('reCAPTCHA verification expired. Please verify again.');
        }
        
        function handleRecaptchaError() {
            document.getElementById('submitBtn').disabled = true;
            showRecaptchaError('reCAPTCHA verification failed. Please try again.');
        }
        
        function showRecaptchaError(message) {
            const errorElement = document.getElementById('recaptchaError');
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            console.error('reCAPTCHA error:', message);
        }
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const registrationForm = document.getElementById('registrationForm');
            const formMessage = document.getElementById('formMessage');
            const submitBtn = document.getElementById('submitBtn');
            
            if (registrationForm) {
                // Form submission with AJAX
                registrationForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    // Validate form before submission
                    if (!validateForm()) {
                        return false;
                    }

                    // Verify reCAPTCHA
                    const recaptchaResponse = grecaptcha.getResponse(recaptchaWidget);
                    if (!recaptchaResponse) {
                        showRecaptchaError('Please complete the reCAPTCHA verification.');
                        return false;
                    }
                    
                    try {
                        // Show loading state
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border-sm"></span> Processing...';
                        
                        // Clear previous messages
                        formMessage.innerHTML = '';
                        formMessage.className = 'hidden mb-4 p-4 rounded';
                        document.getElementById('recaptchaError').style.display = 'none';
                        
                        // Get form data
                        const formData = new FormData(this);
                        
                        // Add reCAPTCHA response to form data
                        formData.append('g-recaptcha-response', recaptchaResponse);
                        
                        console.log('Submitting form with reCAPTCHA response');
                        
                        // Send AJAX request
                        const response = await fetch(registrationForm.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin'
                        });

                        if (!response.ok) {
                            throw new Error(`Network response error: ${response.status} ${response.statusText}`);
                        }

                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            console.error('Invalid response format:', contentType);
                            throw new Error('Invalid response format from server. Expected JSON.');
                        }

                        const data = await response.json();
                        console.log('Server response:', data);
                        
                        if (data.success) {
                            // Hide form and show success animation
                            registrationForm.style.display = 'none';
                            const successAnim = document.getElementById('successAnimation');
                            if (successAnim) {
                                successAnim.classList.remove('hidden');
                            }
                            
                            // Show success message
                            showFormMessage('success', data.message || 'Appointment booked successfully!');
                            
                            // Redirect to confirmation page after animation
                            setTimeout(() => {
                                window.location.href = 'confirmation.php';
                            }, 3000);
                        } else {
                            handleFormErrors(data);
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showFormMessage('error', 'An error occurred while processing your request. Please try again.');
                        
                        // Log detailed error information
                        console.error('Full error details:', error.stack || error);
                    } finally {
                        // Reset form state
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Submit Appointment Request';
                        grecaptcha.reset(recaptchaWidget);
                    }
                });
                
                // Add validation listeners to fields
                addFieldValidationListeners();
                
                // Start slot availability checker
                initSlotAvailabilityChecker();
            }
        });
        
        // Handle form errors
        function handleFormErrors(data) {
            if (data.errors && Object.keys(data.errors).length > 0) {
                clearAllErrors();
                
                for (const [field, errorMessage] of Object.entries(data.errors)) {
                    const fieldElement = document.getElementById(field);
                    if (fieldElement) {
                        showError(fieldElement, errorMessage);
                    }
                }
            }
            
            showFormMessage('error', data.message || 'Please correct the errors and try again.');
        }
        
        // Initialize slot availability checker
        function initSlotAvailabilityChecker() {
            <?php if (!$formSubmitted): ?>
            let checkInterval = setInterval(async function() {
                try {
                    const response = await fetch('check_slot.php?date=<?php echo urlencode($appointmentDate); ?>&time=<?php echo urlencode($appointmentTime); ?>&_=' + new Date().getTime(), {
                        headers: {
                            'Cache-Control': 'no-cache'
                        }
                    });

                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }

                    const data = await response.json();
                    if (!data.available) {
                        clearInterval(checkInterval);
                        showFormMessage('error', 'This slot has just been booked by someone else. You will be redirected to select another time.');
                        setTimeout(() => {
                            window.location.href = 'slot-booking.php?error=slot_taken_during_form';
                        }, 2000);
                    }
                } catch (error) {
                    console.error('Error checking slot availability:', error);
                    clearInterval(checkInterval);
                }
            }, 30000);
            <?php endif; ?>
        }
        
        // Helper function to show form messages
        function showFormMessage(type, message) {
            const formMessage = document.getElementById('formMessage');
            formMessage.innerHTML = message;
            formMessage.classList.remove('hidden');
            
            if (type === 'success') {
                formMessage.className = 'mb-3 p-2 rounded bg-green-100 text-green-700 border border-green-300';
            } else if (type === 'error') {
                formMessage.className = 'mb-3 p-2 rounded bg-red-100 text-red-700 border border-red-300';
            } else {
                formMessage.className = 'mb-3 p-2 rounded bg-blue-100 text-blue-700 border border-blue-300';
            }
            
            // Scroll to message
            formMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        // Validation functions
        function validateForm() {
            clearAllErrors();
            
            let isValid = true;
            let firstErrorField = null;
            
            // Required fields validation
            const validations = [
                { field: 'patientFirstName', validate: validateName, errorMsg: 'First name is required and must contain only letters' },
                { field: 'patientLastName', validate: validateName, errorMsg: 'Last name is required and must contain only letters' },
                { field: 'contactNumber', validate: validatePhone, errorMsg: 'Please enter a valid phone number' },
                { field: 'email', validate: validateEmail, errorMsg: 'Please enter a valid email address' },
                { field: 'reasonForAppointment', validate: (value) => value.trim().length > 0, errorMsg: 'Reason for appointment is required' }
            ];
            
            // Run all validations
            for (const v of validations) {
                const field = document.getElementById(v.field);
                if (field && !v.validate(field.value)) {
                    showError(field, v.errorMsg);
                    isValid = false;
                    if (!firstErrorField) firstErrorField = field;
                }
            }
            
            // Date of birth validation
            const dobMonth = document.querySelector('select[name="dobMonth"]');
            const dobDay = document.querySelector('select[name="dobDay"]');
            const dobYear = document.querySelector('select[name="dobYear"]');
            
            if (dobMonth && dobDay && dobYear) {
                if (!validateDOB(dobMonth.value, dobDay.value, dobYear.value)) {
                    const fields = [dobMonth, dobDay, dobYear];
                    fields.forEach(field => field.classList.add('is-invalid', 'border-red-500'));
                    showError(dobMonth, 'Please enter a valid date of birth', true);
                    isValid = false;
                    if (!firstErrorField) firstErrorField = dobMonth;
                }
            }
            
            // Scroll to first error
            if (firstErrorField) {
                // Scroll to the containing form-field-container, not just the input
                const container = firstErrorField.closest('.form-field-container');
                if (container) {
                    container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                firstErrorField.focus();
            }
            
            return isValid;
        }
        
        // Helper validation functions
        function validateName(value) {
            return value.trim().length > 0 && /^[a-zA-Z\-\' ]{1,50}$/.test(value);
        }
        
        function validatePhone(value) {
            return /^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/.test(value);
        }
        
        function validateEmail(value) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        }
        
        function validateDOB(month, day, year) {
            // Basic date validation
            if (!month || !day || !year) return false;
            
            // Check if date is valid
            const dob = new Date(year, month - 1, day);
            if (dob == 'Invalid Date') return false;
            
            // Check if date is not in the future
            const today = new Date();
            if (dob > today) return false;
            
            // Check if age is reasonable (less than 120 years)
            const maxAge = 120;
            const minDate = new Date();
            minDate.setFullYear(today.getFullYear() - maxAge);
            if (dob < minDate) return false;
            
            return true;
        }
        
        // Error display functions
        function showError(field, message, isDOB = false) {
            field.classList.add('is-invalid', 'border-red-500');
            
            // Find the parent container
            let container = field.closest('.form-field-container');
            if (!container) {
                container = field.parentNode;
            }
            
            // We no longer add validation icons
            
            // Find or create error message container
            let errorContainer = container.querySelector('.error-message-container');
            if (!errorContainer) {
                errorContainer = document.createElement('div');
                errorContainer.className = 'error-message-container';
                container.appendChild(errorContainer);
            }
            
            // Create or update error message
            let errorElement = errorContainer.querySelector('.error-message');
            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.className = 'error-message';
                errorContainer.appendChild(errorElement);
            }
            
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            
            // Add shake animation to highlight the error
            container.classList.add('shake');
            setTimeout(() => container.classList.remove('shake'), 500);
        }
        
        function clearError(field) {
            field.classList.remove('is-invalid', 'border-red-500');
            
            // Find the parent container
            let container = field.closest('.form-field-container');
            if (!container) {
                container = field.parentNode;
            }
            
            // We no longer need to remove validation icons
            
            // Clear error message
            const errorContainer = container.querySelector('.error-message-container');
            if (errorContainer) {
                const errorElement = errorContainer.querySelector('.error-message');
                if (errorElement) {
                    errorElement.style.display = 'none';
                }
            }
        }
        
        function clearAllErrors() {
            // Remove is-invalid class from all fields
            document.querySelectorAll('.is-invalid').forEach(field => {
                field.classList.remove('is-invalid', 'border-red-500');
            });
            
            // We no longer need to remove validation icons since they're not added
            
            // Hide all error messages
            document.querySelectorAll('.error-message').forEach(element => {
                element.style.display = 'none';
            });
            
            // Remove shake animation
            document.querySelectorAll('.shake').forEach(element => {
                element.classList.remove('shake');
            });
        }
        
        // Add field validation functions
        function addFieldValidationListeners() {
            // Add blur validation for text inputs
            const textFields = ['patientFirstName', 'patientLastName', 'contactNumber', 'email', 'reasonForAppointment'];
            textFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.addEventListener('blur', function() {
                        validateField(this);
                    });
                }
            });
            
            // Add change validation for select elements
            const dobSelects = document.querySelectorAll('select[name^="dob"]');
            dobSelects.forEach(select => {
                select.addEventListener('change', function() {
                    const month = document.querySelector('select[name="dobMonth"]').value;
                    const day = document.querySelector('select[name="dobDay"]').value;
                    const year = document.querySelector('select[name="dobYear"]').value;
                    
                    // Only validate if all fields have values
                    if (month && day && year) {
                        const isValid = validateDOB(month, day, year);
                        
                        // Update all DOB fields
                        const fields = [
                            document.querySelector('select[name="dobMonth"]'),
                            document.querySelector('select[name="dobDay"]'),
                            document.querySelector('select[name="dobYear"]')
                        ];
                        
                        if (isValid) {
                            // Clear errors if valid
                            fields.forEach(field => clearError(field));
                        } else {
                            // Show error if invalid
                            fields.forEach(field => field.classList.add('is-invalid', 'border-red-500'));
                            showError(fields[0], 'Please enter a valid date of birth', true);
                        }
                    }
                });
            });
        }
        
        // Validate a specific field
        function validateField(field) {
            clearError(field);
            
            switch(field.id) {
                case 'patientFirstName':
                case 'patientLastName':
                    if (!validateName(field.value)) {
                        showError(field, 'Please enter a valid name (letters only)');
                        return false;
                    }
                    break;
                case 'contactNumber':
                    if (!validatePhone(field.value)) {
                        showError(field, 'Please enter a valid phone number');
                        return false;
                    }
                    break;
                case 'email':
                    if (!validateEmail(field.value)) {
                        showError(field, 'Please enter a valid email address');
                        return false;
                    }
                    break;
                case 'reasonForAppointment':
                    if (field.value.trim().length === 0) {
                        showError(field, 'Reason for appointment is required');
                        return false;
                    }
                    break;
            }
            
            return true;
        }
    </script>
</body>
</html>

<?php
// Simplified sendConfirmationEmail function
function sendConfirmationEmail($to, $data) {
    // Use PHPMailer for more reliable email delivery
    require_once 'php/php-mailer/src/PHPMailer.php';
    require_once 'php/php-mailer/src/SMTP.php';
    require_once 'php/php-mailer/src/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Recipients
        $mail->setFrom('appointments@hospital.com', 'Hospital Appointments');
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "Appointment Confirmation - Ref: " . $data['reference'];
        
        // HTML message with simplified styling
        $htmlMessage = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #7047d1; color: white; padding: 15px; text-align: center; }
                .content { padding: 20px; }
                .appointment-details { background-color: #f4e6fa; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .reference { font-weight: bold; color: #7047d1; }
                .footer { font-size: 0.8em; color: #777; margin-top: 30px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Appointment Confirmation</h2>
                </div>
                <div class='content'>
                    <p>Dear " . htmlspecialchars($data['name']) . ",</p>
                    <p>Your appointment request has been received and confirmed.</p>
                    
                    <div class='appointment-details'>
                        <p><strong>Date:</strong> " . htmlspecialchars($data['date']) . "</p>
                        <p><strong>Time:</strong> " . htmlspecialchars($data['time']) . "</p>
                        <p><strong>Reference:</strong> <span class='reference'>" . htmlspecialchars($data['reference']) . "</span></p>
                    </div>
                    
                    <p>Please keep this reference number for your records.</p>
                    <p>We look forward to seeing you!</p>
                    
                    <div class='footer'>
                        <p>© " . date('Y') . " Hospital Appointment System</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
        
        // Plain text alternative
        $textMessage = "Dear " . $data['name'] . ",\n\n";
        $textMessage .= "Your appointment has been confirmed for:\n";
        $textMessage .= "Date: " . $data['date'] . "\n";
        $textMessage .= "Time: " . $data['time'] . "\n";
        $textMessage .= "Reference: " . $data['reference'] . "\n\n";
        $textMessage .= "We look forward to seeing you!\n\n";
        
        $mail->Body = $htmlMessage;
        $mail->AltBody = $textMessage;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        throw new Exception("Failed to send confirmation email");
    }
}
?>