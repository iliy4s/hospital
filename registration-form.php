<?php 
session_start();
require 'connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Check if the time is 11:00 AM (which should be disabled)
if (preg_match('/^11:00\s*AM$/i', $appointmentTime)) {
    error_log("Registration attempt for disabled 11:00 AM slot: Date: $appointmentDate");
    $_SESSION['booking_error'] = "Sorry, appointments at 11:00 AM are not available. Please select another time.";
    header("Location: slot-booking.php");
    exit;
}

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
    // Add CSRF protection
    if (!isset($_SESSION['csrf_token']) || !isset($_POST['csrf_token']) || 
        $_SESSION['csrf_token'] !== $_POST['csrf_token']) {
        $errors['csrf'] = 'Invalid form submission';
        error_log("CSRF token validation failed");
        exit;
    }

    // Add submission timestamp check to prevent double submission
    if (isset($_SESSION['last_submission_time']) && 
        time() - $_SESSION['last_submission_time'] < 5) {
        $errors['submission'] = 'Please wait before submitting again.';
    } else {
        $_SESSION['last_submission_time'] = time();
        
        // Validate all required fields
        $validations = [
            'patientFirstName' => [
                'value' => $_POST['patientFirstName'] ?? '',
                'pattern' => "/^[a-zA-Z-' ]{1,50}$/",
                'error' => 'Only letters, hyphens and spaces allowed'
            ],
            'patientLastName' => [
                'value' => $_POST['patientLastName'] ?? '',
                'pattern' => "/^[a-zA-Z-' ]{1,50}$/",
                'error' => 'Only letters, hyphens and spaces allowed'
            ],
            'contactNumber' => [
                'value' => $_POST['contactNumber'] ?? '',
                'pattern' => "/^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/",
                'error' => 'Please enter a valid phone number'
            ],
            'email' => [
                'value' => $_POST['email'] ?? '',
                'validator' => function($email) {
                    return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 254;
                },
                'error' => 'Please enter a valid email address'
            ],
            'reasonForAppointment' => [
                'value' => $_POST['reasonForAppointment'] ?? '',
                'validator' => function($reason) {
                    return !empty($reason) && strlen($reason) <= 1000;
                },
                'error' => 'Reason is required and must be less than 1000 characters'
            ]
        ];
        
        // Process validations
        foreach ($validations as $field => $config) {
            if (empty($config['value'])) {
                $errors[$field] = ucfirst($field) . ' is required';
            } else {
                ${$field} = test_input($config['value']);
                
                if (isset($config['pattern'])) {
                    if (!preg_match($config['pattern'], ${$field})) {
                        $errors[$field] = $config['error'];
                    }
                } else if (isset($config['validator'])) {
                    if (!$config['validator'](${$field})) {
                        $errors[$field] = $config['error'];
                    }
                }
            }
        }
        
        // Optional fields
        if (!empty($_POST['patientPreferredName'])) {
            $patientPreferredName = test_input($_POST['patientPreferredName']);
        }
        
        if (!empty($_POST['preferredSpecialty'])) {
            $preferredSpecialty = test_input($_POST['preferredSpecialty']);
            if (!in_array($preferredSpecialty, $specialties)) {
                $errors['preferredSpecialty'] = 'Please select a valid specialty';
            }
        }
        
        // Date of birth validation
        if (empty($_POST['dobMonth']) || empty($_POST['dobDay']) || empty($_POST['dobYear'])) {
            $errors['dob'] = 'Complete date of birth is required';
        } else {
            $dobMonth = $_POST['dobMonth'];
            $dobDay = $_POST['dobDay'];
            $dobYear = $_POST['dobYear'];
            
            if (!checkdate($dobMonth, $dobDay, $dobYear)) {
                $errors['dob'] = 'Please enter a valid date of birth';
            } else {
                $dob = $dobYear . '-' . str_pad($dobMonth, 2, '0', STR_PAD_LEFT) . '-' . str_pad($dobDay, 2, '0', STR_PAD_LEFT);
            }
        }
        
        // Appointment validation
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $appointmentDate)) {
            $errors['appointment'] = 'Invalid appointment date format';
        }
        
        if (!preg_match("/^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(?:AM|PM)$/i", $appointmentTime)) {
            $errors['appointment'] = 'Invalid appointment time format';
        }

        // If no errors, proceed with database insertion
        if (empty($errors)) {
            try {
                $conn->begin_transaction();

                // Double check slot availability with locking
                $checkSlotSql = "SELECT id FROM appointments 
                               WHERE appointment_date = ? 
                               AND appointment_time = ?
                               AND status != 'cancelled'
                               FOR UPDATE";
                
                $checkStmt = $conn->prepare($checkSlotSql);
                if (!$checkStmt) {
                    throw new Exception("Failed to prepare slot check: " . $conn->error);
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
                    throw new Exception("Failed to prepare insert: " . $conn->error);
                }
                
                $stmt->bind_param(
                    "ssssssssss", 
                    $appointmentDate, $appointmentTime, $patientFirstName, $patientLastName, 
                    $patientPreferredName, $dob, $contactNumber, $email, $preferredSpecialty, 
                    $reasonForAppointment, $bookingReference
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert appointment: " . $stmt->error);
                }
                
                $newAppointmentId = $stmt->insert_id;
                
                // Log and commit
                error_log("Appointment booked successfully - ID: $newAppointmentId, Ref: $bookingReference");
                $conn->commit();
                
                // Store booking info in session
                $_SESSION['booking_reference'] = $bookingReference;
                $_SESSION['appointment_id'] = $newAppointmentId;
                
                // Clear form session data
                unset($_SESSION['appointment_date']);
                unset($_SESSION['appointment_time']);
                
                // Success flag
                $formSubmitted = true;
                
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
                
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Appointment booking failed: " . $e->getMessage());
                
                if ($e->getMessage() === "Slot already booked") {
                    $_SESSION['booking_error'] = "This slot was just taken. Please select another time.";
                    header("Location: slot-booking.php");
                    exit;
                }
                
                $errors['database'] = "Failed to process your request. Please try again.";
            }
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get list of months, days, and years for dropdowns
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
    <link rel="stylesheet" href="css/animations.css"><?php // Include our custom animations ?>
    <style>
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
            
            <form id="registrationForm" method="POST" action="process_appointment.php">
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
                
                <!-- Submit Button with loading state support -->
                <button type="submit" id="submitBtn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg font-semibold transition">
                    Submit Appointment Request
                </button>
            </form>
        <?php endif; ?>
    </div>

    <script>
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
                registrationForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Validate form before submission
                    if (!validateForm()) {
                        return false;
                    }
                    
                    // Show loading state
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border-sm"></span> Processing...';
                    
                    // Clear previous messages
                    formMessage.innerHTML = '';
                    formMessage.className = 'hidden mb-4 p-4 rounded';
                    
                    // Get form data
                    const formData = new FormData(this);
                    
                    // Send AJAX request with timeout
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout
                    
                    fetch('process_appointment.php', {
                        method: 'POST',
                        body: formData,
                        signal: controller.signal
                    })
                    .then(response => {
                        clearTimeout(timeoutId);
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Hide form and show success animation
                            document.getElementById('registrationForm').style.display = 'none';
                            document.getElementById('successAnimation').classList.remove('hidden');
                            
                            // Show success message
                            showFormMessage('success', 'Appointment booked successfully! Redirecting to confirmation page...');
                            
                            // Redirect to confirmation page after a short delay (longer to see animation)
                            setTimeout(() => {
                                window.location.href = 'confirmation.php';
                            }, 3000);
                        } else {
                            // Show error message
                            let errorMsg = data.message || 'An error occurred. Please try again.';
                            
                            // Add field-specific errors if any
                            if (data.errors && Object.keys(data.errors).length > 0) {
                                // Display field errors
                                for (const [field, error] of Object.entries(data.errors)) {
                                    const fieldElement = document.getElementById(field) || 
                                                        document.querySelector(`[name="${field}"]`);
                                    if (fieldElement) {
                                        showError(fieldElement, error);
                                    }
                                }
                                errorMsg = 'Please correct the errors and try again.';
                            }
                            
                            showFormMessage('error', errorMsg);
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = 'Submit Appointment Request';
                        }
                    })
                    .catch(error => {
                        clearTimeout(timeoutId);
                        console.error('Error:', error);
                        if (error.name === 'AbortError') {
                            showFormMessage('error', 'Request timed out. The server is taking too long to respond.');
                        } else if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
                            showFormMessage('error', 'Network error. Please check your internet connection and try again.');
                        } else {
                            showFormMessage('error', 'Error processing your request. Please try again or contact support.');
                        }
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Submit Appointment Request';
                    });
                });
                
                // Add validation listeners to fields
                addFieldValidationListeners();
            }
            
            // Check slot availability periodically
            <?php if (!$formSubmitted): ?>
            let checkInterval = setInterval(function() {
                fetch('check_slot.php?date=<?php echo urlencode($appointmentDate); ?>&time=<?php echo urlencode($appointmentTime); ?>&_=' + new Date().getTime())
                    .then(response => response.json())
                    .then(data => {
                        if (!data.available) {
                            clearInterval(checkInterval);
                            showFormMessage('error', 'This slot has just been booked by someone else. You will be redirected to select another time.');
                            setTimeout(() => {
                                window.location.href = 'slot-booking.php?error=slot_taken_during_form';
                            }, 2000);
                        }
                    })
                    .catch(error => console.error('Error checking slot availability:', error));
            }, 30000); // Check every 30 seconds
            <?php endif; ?>
        });
        
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