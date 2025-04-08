<?php
session_start();
require 'connect.php';

header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'appointment_id' => null,
    'errors' => []
];

// Verify CSRF token
if (!isset($_SESSION['csrf_token']) || !isset($_POST['csrf_token']) || 
    $_SESSION['csrf_token'] !== $_POST['csrf_token']) {
    $response['message'] = 'Invalid form submission';
    error_log("CSRF token validation failed");
    echo json_encode($response);
    exit;
}

// Function to sanitize input
function test_input($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Validate required fields and collect form data
$required_fields = [
    'patientFirstName', 'patientLastName', 'contactNumber', 
    'email', 'reasonForAppointment', 'appointmentDate', 'appointmentTime'
];

$form_data = [];
$errors = [];

// Check for required fields and sanitize input
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $errors[$field] = ucfirst(preg_replace('/([A-Z])/', ' $1', $field)) . ' is required';
    } else {
        $form_data[$field] = test_input($_POST[$field]);
    }
}

// Validate names
if (!empty($form_data['patientFirstName']) && !preg_match("/^[a-zA-Z-' ]{1,50}$/", $form_data['patientFirstName'])) {
    $errors['patientFirstName'] = 'Only letters, hyphens, and spaces allowed';
}

if (!empty($form_data['patientLastName']) && !preg_match("/^[a-zA-Z-' ]{1,50}$/", $form_data['patientLastName'])) {
    $errors['patientLastName'] = 'Only letters, hyphens, and spaces allowed';
}

// Validate phone
if (!empty($form_data['contactNumber']) && !preg_match("/^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/", $form_data['contactNumber'])) {
    $errors['contactNumber'] = 'Please enter a valid phone number';
}

// Validate email
if (!empty($form_data['email']) && (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL) || strlen($form_data['email']) > 254)) {
    $errors['email'] = 'Please enter a valid email address';
}

// Validate reason length
if (!empty($form_data['reasonForAppointment']) && strlen($form_data['reasonForAppointment']) > 1000) {
    $errors['reasonForAppointment'] = 'Reason must be less than 1000 characters';
}

// Validate date format
if (!empty($form_data['appointmentDate']) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $form_data['appointmentDate'])) {
    $errors['appointmentDate'] = 'Invalid date format';
}

// Validate time format
if (!empty($form_data['appointmentTime']) && !preg_match("/^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(?:AM|PM)$/i", $form_data['appointmentTime'])) {
    $errors['appointmentTime'] = 'Invalid time format';
}

// Validate DOB
$dob = null;
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

// Optional data
$patientPreferredName = !empty($_POST['patientPreferredName']) ? test_input($_POST['patientPreferredName']) : null;
$preferredSpecialty = !empty($_POST['preferredSpecialty']) ? test_input($_POST['preferredSpecialty']) : null;

// If there are validation errors, return them
if (!empty($errors)) {
    $response['errors'] = $errors;
    $response['message'] = 'Please correct the errors and try again.';
    echo json_encode($response);
    exit;
}

// Check appointment availability
try {
    // Log form data for debugging
    error_log("Processing appointment: Date: " . $form_data['appointmentDate'] . ", Time: " . $form_data['appointmentTime'] . ", Name: " . $form_data['patientFirstName'] . " " . $form_data['patientLastName']);
    
    // Start transaction
    $conn->begin_transaction();

    // Check if the slot is still available
    $checkSlotSql = "SELECT id FROM appointments 
                   WHERE appointment_date = ? 
                   AND appointment_time = ?
                   AND status != 'cancelled'
                   FOR UPDATE";
    
    $checkStmt = $conn->prepare($checkSlotSql);
    if (!$checkStmt) {
        throw new Exception("Failed to prepare slot check: " . $conn->error);
    }
    
    $checkStmt->bind_param("ss", $form_data['appointmentDate'], $form_data['appointmentTime']);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        $conn->rollback();
        $response['message'] = 'This slot has already been booked. Please select another time.';
        echo json_encode($response);
        exit;
    }

    // Generate booking reference
    $bookingReference = 'APT-' . date('Ymd') . '-' . substr(uniqid(), -5);
    
    // Ensure booking reference is not too long
    if (strlen($bookingReference) > 20) {
        $bookingReference = substr($bookingReference, 0, 20);
    }
    
    // Log the booking reference
    error_log("Generated booking reference: " . $bookingReference);
    
    // Insert the appointment
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
        "sssssssssss", 
        $form_data['appointmentDate'], $form_data['appointmentTime'], 
        $form_data['patientFirstName'], $form_data['patientLastName'], 
        $patientPreferredName, $dob, $form_data['contactNumber'], 
        $form_data['email'], $preferredSpecialty, 
        $form_data['reasonForAppointment'], $bookingReference
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert appointment: " . $stmt->error);
    }
    
    $newAppointmentId = $stmt->insert_id;
    
    // Commit the transaction
    $conn->commit();
    
    // Format date for display
    $dateObj = new DateTime($form_data['appointmentDate']);
    $formattedDate = $dateObj->format('l, F j, Y');
    
    // Try to send confirmation email
    try {
        // Create confirmation email directly rather than including registration-form.php
        $to = $form_data['email'];
        $subject = "Appointment Confirmation - Ref: " . $bookingReference;
        
        // HTML message
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
                    <p>Dear " . htmlspecialchars($form_data['patientFirstName']) . ",</p>
                    <p>Your appointment request has been received and confirmed.</p>
                    
                    <div class='appointment-details'>
                        <p><strong>Date:</strong> " . htmlspecialchars($formattedDate) . "</p>
                        <p><strong>Time:</strong> " . htmlspecialchars($form_data['appointmentTime']) . "</p>
                        <p><strong>Reference:</strong> <span class='reference'>" . htmlspecialchars($bookingReference) . "</span></p>
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
        $textMessage = "Dear " . $form_data['patientFirstName'] . ",\n\n";
        $textMessage .= "Your appointment has been confirmed for:\n";
        $textMessage .= "Date: " . $formattedDate . "\n";
        $textMessage .= "Time: " . $form_data['appointmentTime'] . "\n";
        $textMessage .= "Reference: " . $bookingReference . "\n\n";
        $textMessage .= "We look forward to seeing you!\n\n";
        
        // Email headers
        $headers = "From: appointments@hospital.com\r\n";
        $headers .= "Reply-To: appointments@hospital.com\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        // Send the email
        mail($to, $subject, $htmlMessage, $headers);
    } catch (Exception $e) {
        error_log("Failed to send confirmation email: " . $e->getMessage());
        // Continue without throwing exception as booking was successful
    }
    
    // Success response
    $response['success'] = true;
    $response['message'] = 'Appointment booked successfully!';
    $response['appointment_id'] = $newAppointmentId;
    $response['booking_reference'] = $bookingReference;

    // Store info in session for confirmation page
    $_SESSION['booking_reference'] = $bookingReference;
    $_SESSION['appointment_id'] = $newAppointmentId;
    $_SESSION['patient_name'] = $form_data['patientFirstName'];
    $_SESSION['appointment_date'] = $formattedDate;
    $_SESSION['appointment_time'] = $form_data['appointmentTime'];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Appointment booking failed: " . $e->getMessage() . " - " . $e->getTraceAsString());
    
    $response['message'] = "We encountered a technical issue. Please try again or contact support.";
    $response['debug'] = $e->getMessage(); // Include error message for debugging
    echo json_encode($response);
}
?> 