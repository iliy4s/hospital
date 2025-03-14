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

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate first name
    if (empty($_POST['patientFirstName'])) {
        $errors['patientFirstName'] = 'First name is required';
    } else {
        $patientFirstName = test_input($_POST['patientFirstName']);
    }

    // Validate last name
    if (empty($_POST['patientLastName'])) {
        $errors['patientLastName'] = 'Last name is required';
    } else {
        $patientLastName = test_input($_POST['patientLastName']);
    }

    // Process preferred name (optional)
    $patientPreferredName = test_input($_POST['patientPreferredName'] ?? '');

    // Validate date of birth
    if (empty($_POST['dobMonth']) || empty($_POST['dobDay']) || empty($_POST['dobYear'])) {
        $errors['dob'] = 'Complete date of birth is required';
    } else {
        $dobMonth = $_POST['dobMonth'];
        $dobDay = $_POST['dobDay'];
        $dobYear = $_POST['dobYear'];
        
        // Validate date format
        if (!checkdate($dobMonth, $dobDay, $dobYear)) {
            $errors['dob'] = 'Please enter a valid date of birth';
        } else {
            $dob = $dobYear . '-' . str_pad($dobMonth, 2, '0', STR_PAD_LEFT) . '-' . str_pad($dobDay, 2, '0', STR_PAD_LEFT);
        }
    }

    // Validate contact number
    if (empty($_POST['contactNumber'])) {
        $errors['contactNumber'] = 'Contact number is required';
    } else {
        $contactNumber = test_input($_POST['contactNumber']);
        // Simple validation for phone number format
        if (!preg_match("/^[0-9\-\(\)\/\+\s]*$/", $contactNumber)) {
            $errors['contactNumber'] = 'Please enter a valid contact number';
        }
    }

    // Validate email
    if (empty($_POST['email'])) {
        $errors['email'] = 'Email is required';
    } else {
        $email = test_input($_POST['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        }
    }

    // Process specialty (optional)
    $preferredSpecialty = test_input($_POST['preferredSpecialty'] ?? '');

    // Validate reason for appointment
    if (empty($_POST['reasonForAppointment'])) {
        $errors['reasonForAppointment'] = 'Reason for appointment is required';
    } else {
        $reasonForAppointment = test_input($_POST['reasonForAppointment']);
    }

    // If no errors, proceed with form submission
    if (empty($errors)) {
        // Start transaction for data consistency
        $conn->begin_transaction();
        
        try {
            // Check one more time if the slot is still available - CRITICAL CHECK
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                // Get details about the conflicting appointment for logging
                $conflictRow = $checkResult->fetch_assoc();
                $conflictId = $conflictRow['id'] ?? 'unknown';
                
                // Enhanced error logging
                error_log("BOOKING CONFLICT DURING FORM SUBMISSION: Date: $appointmentDate, Time: $appointmentTime was booked while user was filling form (conflict ID: $conflictId)");
                
                // Rollback any changes
                $conn->rollback();
                
                // Slot was taken during form completion
                $errors['slotTaken'] = "This time slot has been booked by someone else while you were filling the form. Please select another time.";
                // Redirect back to slot booking
                $_SESSION['booking_error'] = $errors['slotTaken'];
                header("Location: slot-booking.php?error=race_condition");
                exit;
            }
            
            // Insert the appointment with default status of 'confirmed'
            $query = "INSERT INTO appointments (
                appointment_date, 
                appointment_time, 
                patient_first_name, 
                patient_last_name, 
                patient_preferred_name, 
                dob, 
                contact_number, 
                email, 
                preferred_specialty, 
                reason_for_appointment,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')";
            
            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception("Database prepare error: " . $conn->error);
            }
            
            $stmt->bind_param(
                "ssssssssss", 
                $appointmentDate, 
                $appointmentTime, 
                $patientFirstName, 
                $patientLastName, 
                $patientPreferredName, 
                $dob, 
                $contactNumber, 
                $email, 
                $preferredSpecialty, 
                $reasonForAppointment
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Database execute error: " . $stmt->error);
            }
            
            // Commit the transaction
            $conn->commit();
            
            // Log successful booking with appointment ID
            $newAppointmentId = $conn->insert_id;
            error_log("SUCCESS: Appointment #$newAppointmentId booked for Date: $appointmentDate, Time: $appointmentTime, Patient: $patientFirstName $patientLastName");
            
            // Clear session appointment data
            unset($_SESSION['appointment_date']);
            unset($_SESSION['appointment_time']);
            
            // Set submission flag for success message
            $formSubmitted = true;
        } catch (Exception $e) {
            // Rollback on any exception
            $conn->rollback();
            $errors['database'] = 'An error occurred while processing your request.';
            error_log("Exception during appointment booking: " . $e->getMessage());
        }
    }
}

// Function to sanitize input data
function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
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
    <style>
        .error-message {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: -0.5rem;
            margin-bottom: 0.5rem;
        }
        .form-control.is-invalid {
            border-color: #dc3545;
        }
        .appointment-summary {
            background-color: #f4e6fa;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .appointment-date {
            font-weight: 600;
            color: #7047d1;
        }
        .appointment-time {
            font-weight: 600;
            color: #7047d1;
        }
    </style>
</head>
<body class="bg-gray-100 flex justify-center items-center min-h-screen py-8">
    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-lg mx-4">
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
                <h3 class="text-lg font-bold mb-2">Appointment Details</h3>
                <p>Date: <span class="appointment-date"><?php echo htmlspecialchars($formattedDate); ?></span> Time: <span class="appointment-time"><?php echo htmlspecialchars($appointmentTime); ?></span></p>
            </div>

            <h2 class="text-xl font-bold mb-4 text-center">Patient Information</h2>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <!-- Hidden fields to preserve appointment information -->
                <input type="hidden" name="appointmentDate" value="<?php echo htmlspecialchars($appointmentDate); ?>">
                <input type="hidden" name="appointmentTime" value="<?php echo htmlspecialchars($appointmentTime); ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <!-- First Name -->
                    <div>
                        <label for="patientFirstName" class="block mb-1 font-medium text-gray-700">First Name *</label>
                        <input type="text" id="patientFirstName" name="patientFirstName" value="<?php echo htmlspecialchars($patientFirstName); ?>" 
                            class="form-control w-full px-4 py-2 border rounded-lg <?php echo isset($errors['patientFirstName']) ? 'is-invalid border-red-500' : ''; ?>">
                        <?php if (isset($errors['patientFirstName'])): ?>
                            <div class="error-message"><?php echo $errors['patientFirstName']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Last Name -->
                    <div>
                        <label for="patientLastName" class="block mb-1 font-medium text-gray-700">Last Name *</label>
                        <input type="text" id="patientLastName" name="patientLastName" value="<?php echo htmlspecialchars($patientLastName); ?>" 
                            class="form-control w-full px-4 py-2 border rounded-lg <?php echo isset($errors['patientLastName']) ? 'is-invalid border-red-500' : ''; ?>">
                        <?php if (isset($errors['patientLastName'])): ?>
                            <div class="error-message"><?php echo $errors['patientLastName']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Preferred Name -->
                    <div>
                        <label for="patientPreferredName" class="block mb-1 font-medium text-gray-700">Preferred Name (Optional)</label>
                        <input type="text" id="patientPreferredName" name="patientPreferredName" value="<?php echo htmlspecialchars($patientPreferredName); ?>" 
                            class="form-control w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <!-- Date of Birth -->
                    <div>
                        <label class="block mb-1 font-medium text-gray-700">Date of Birth *</label>
                        <div class="grid grid-cols-3 gap-2">
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
                        <?php if (isset($errors['dob'])): ?>
                            <div class="error-message"><?php echo $errors['dob']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <!-- Contact Number -->
                    <div>
                        <label for="contactNumber" class="block mb-1 font-medium text-gray-700">Contact Number *</label>
                        <input type="tel" id="contactNumber" name="contactNumber" value="<?php echo htmlspecialchars($contactNumber); ?>" 
                            class="form-control w-full px-4 py-2 border rounded-lg <?php echo isset($errors['contactNumber']) ? 'is-invalid border-red-500' : ''; ?>" 
                            placeholder="Contact number">
                        <?php if (isset($errors['contactNumber'])): ?>
                            <div class="error-message"><?php echo $errors['contactNumber']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Email -->
                    <div>
                        <label for="email" class="block mb-1 font-medium text-gray-700">Email *</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" 
                            class="form-control w-full px-4 py-2 border rounded-lg <?php echo isset($errors['email']) ? 'is-invalid border-red-500' : ''; ?>" 
                            placeholder="your@email.com">
                        <?php if (isset($errors['email'])): ?>
                            <div class="error-message"><?php echo $errors['email']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Preferred Specialty -->
                <div class="mb-4">
                    <label for="preferredSpecialty" class="block mb-1 font-medium text-gray-700">Preferred Specialty</label>
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
                <div class="mb-6">
                    <label for="reasonForAppointment" class="block mb-1 font-medium text-gray-700">Reason for Appointment *</label>
                    <textarea id="reasonForAppointment" name="reasonForAppointment" rows="4" 
                        class="form-control w-full px-4 py-2 border rounded-lg <?php echo isset($errors['reasonForAppointment']) ? 'is-invalid border-red-500' : ''; ?>" 
                        placeholder="Please briefly describe your symptoms or reason for visit"><?php echo htmlspecialchars($reasonForAppointment); ?></textarea>
                    <?php if (isset($errors['reasonForAppointment'])): ?>
                        <div class="error-message"><?php echo $errors['reasonForAppointment']; ?></div>
                    <?php endif; ?>
                </div>
                
                <!-- General error message for appointment slot -->
                <?php if (isset($errors['appointmentSlot'])): ?>
                    <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                        <?php echo $errors['appointmentSlot']; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Submit Button -->
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 px-4 rounded-lg font-medium transition">
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
        
        // Periodically check if the slot is still available
        <?php if (!$formSubmitted): ?>
        let checkInterval = setInterval(function() {
            fetch('check_slot.php?date=<?php echo urlencode($appointmentDate); ?>&time=<?php echo urlencode($appointmentTime); ?>&_=' + new Date().getTime())
                .then(response => response.json())
                .then(data => {
                    if (!data.available) {
                        clearInterval(checkInterval);
                        alert('This slot has just been booked by someone else. You will be redirected to select another time.');
                        window.location.href = 'slot-booking.php?error=slot_taken_during_form';
                    }
                })
                .catch(error => console.error('Error checking slot availability:', error));
        }, 10000); // Check every 10 seconds
        <?php endif; ?>
    </script>
</body>
</html>