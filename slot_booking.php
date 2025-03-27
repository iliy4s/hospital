<?php
// Start session and include database connection
session_start();

// Only include connection if it hasn't been included already
if (!function_exists('get_db_connection')) {
    // Check if connect.php exists
    if (file_exists('connect.php')) {
        require_once 'connect.php';
    } else {
        // If connect.php doesn't exist, display error
        die("Database connection file not found. Please make sure connect.php exists.");
    }
}

// Function to get database connection
function get_db_connection() {
    global $conn;
    
    // Check if we already have a connection from connect.php
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        return $conn;
    } else {
        // If $conn is not defined or has an error, create a connection here
        try {
            // Get database credentials from configuration or use defaults
            $host = defined('DB_HOST') ? DB_HOST : 'localhost';
            $user = defined('DB_USER') ? DB_USER : 'root';
            $pass = defined('DB_PASS') ? DB_PASS : '';
            $db = defined('DB_NAME') ? DB_NAME : 'hospital';
            
            $conn = new mysqli($host, $user, $pass, $db);
            
            if ($conn->connect_error) {
                throw new Exception("Database connection failed: " . $conn->connect_error);
            }
            
            // Set character set
            $conn->set_charset("utf8mb4");
            
            return $conn;
        } catch (Exception $e) {
            die("Database connection error: " . $e->getMessage());
        }
    }
}

// Helper function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// For POST requests, process the form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['direct_submit'])) {
    header('Content-Type: application/json');
    
    // Initialize response array
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Get database connection
        $conn = get_db_connection();
        
        // Validate required fields
        $requiredFields = ['patientFirstName', 'patientLastName', 'dobMonth', 'dobDay', 'dobYear', 'contactNumber', 'email', 'reasonForAppointment', 'selected_date', 'selected_time'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            throw new Exception("Please fill in all required fields: " . implode(', ', $missingFields));
        }

        // Sanitize and validate input
        $patientFirstName = sanitize_input($_POST['patientFirstName']);
        $patientLastName = sanitize_input($_POST['patientLastName']);
        $patientPreferredName = isset($_POST['patientPreferredName']) ? sanitize_input($_POST['patientPreferredName']) : '';
        $contactNumber = sanitize_input($_POST['contactNumber']);
        $email = sanitize_input($_POST['email']);
        $preferredSpecialty = isset($_POST['preferredSpecialty']) ? sanitize_input($_POST['preferredSpecialty']) : '';
        $reasonForAppointment = sanitize_input($_POST['reasonForAppointment']);
        $selectedDate = sanitize_input($_POST['selected_date']);
        $selectedTime = sanitize_input($_POST['selected_time']);

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        // Validate date of birth
        $dobMonth = (int)$_POST['dobMonth'];
        $dobDay = (int)$_POST['dobDay'];
        $dobYear = (int)$_POST['dobYear'];
        
        if (!checkdate($dobMonth, $dobDay, $dobYear)) {
            throw new Exception("Please enter a valid date of birth.");
        }

        // Format date of birth
        $dob = sprintf("%04d-%02d-%02d", $dobYear, $dobMonth, $dobDay);
        
        // Validate appointment date/time
        $currentDate = date('Y-m-d');
        if ($selectedDate < $currentDate) {
            throw new Exception("Cannot book appointments for past dates.");
        }
        
        // Validate time format 
        if (!preg_match('/^(0?[1-9]|1[0-2]):[0-5][0-9]\s*[APap][Mm]$/', $selectedTime)) {
            // Try to fix common time format issues
            if (preg_match('/^([0-9]{1,2})(:|.)([0-5][0-9])\s*([APap])[Mm]?$/', $selectedTime, $matches)) {
                // Reformat to ensure consistent format
                $hour = $matches[1];
                $minute = $matches[3];
                $ampm = strtoupper($matches[4]) . 'M';
                $selectedTime = sprintf('%02d:%02d %s', $hour, $minute, $ampm);
            } else {
                throw new Exception("Invalid time format. Please use format like '10:00 AM'.");
            }
        }

        // Make sure the appointments table exists
        ensureAppointmentsTableExists($conn);
        
        // Check if the selected time slot is still available
        $checkQuery = "SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND appointment_time = ?";
        $checkStmt = $conn->prepare($checkQuery);
        
        if (!$checkStmt) {
            throw new Exception("Database error while checking availability: " . $conn->error);
        }
        
        $checkStmt->bind_param("ss", $selectedDate, $selectedTime);
        
        if (!$checkStmt->execute()) {
            throw new Exception("Database error while checking availability: " . $checkStmt->error);
        }
        
        $result = $checkStmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['count'] > 0) {
            throw new Exception("This time slot is no longer available. Please select another time.");
        }
        
        // Start transaction
        $conn->begin_transaction();

        try {
            // Insert into database
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
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())";

            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception("Database error while processing your request: " . $conn->error);
            }

            $stmt->bind_param(
                "ssssssssss",
                $selectedDate,
                $selectedTime,
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
                throw new Exception("Database error while processing your request: " . $stmt->error);
            }
            
            // Get the new appointment ID
            $newAppointmentId = $conn->insert_id;
            
            // Commit the transaction
            $conn->commit();

            // Success response
            $response['success'] = true;
            $response['message'] = 'Appointment booked successfully!';
            $response['appointment_id'] = $newAppointmentId;
        } catch (Exception $e) {
            // Roll back the transaction on error
            $conn->rollback();
            throw $e;
        }

    } catch (Exception $e) {
        // If a transaction is active, roll it back
        if (isset($conn) && method_exists($conn, 'inTransaction') && $conn->inTransaction()) {
            $conn->rollback();
        }
        
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }

    // Send JSON response and exit
    echo json_encode($response);
    exit();
}

// Helper function to ensure the appointments table exists
function ensureAppointmentsTableExists($conn) {
    try {
        $tableExistsQuery = "SHOW TABLES LIKE 'appointments'";
        $result = $conn->query($tableExistsQuery);
        
        if (!$result) {
            throw new Exception("Database error: Could not check if required table exists.");
        }
        
        if ($result->num_rows == 0) {
            // Table doesn't exist, create it
            $createTableQuery = "CREATE TABLE IF NOT EXISTS appointments (
                id INT(11) NOT NULL AUTO_INCREMENT,
                appointment_date DATE NOT NULL,
                appointment_time VARCHAR(20) NOT NULL,
                patient_first_name VARCHAR(100) NOT NULL,
                patient_last_name VARCHAR(100) NOT NULL,
                patient_preferred_name VARCHAR(100),
                dob DATE NOT NULL,
                contact_number VARCHAR(20) NOT NULL,
                email VARCHAR(100) NOT NULL,
                preferred_specialty VARCHAR(100),
                reason_for_appointment TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX (appointment_date, appointment_time),
                INDEX (email),
                INDEX (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            if (!$conn->query($createTableQuery)) {
                throw new Exception("Database error: Could not create required table: " . $conn->error);
            }
        }
    } catch (Exception $e) {
        throw $e;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Booking</title>
</head>
<body>
    <!-- Registration Modal -->
    <div id="registrationModal" class="modal">
        <div class="modal-content registration-modal">
            <div class="modal-header">
                <h2>Patient Registration</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="appointment-summary">
                    <h3 class="text-lg font-bold mb-2">Appointment Details</h3>
                    <p>Date: <span class="appointment-date" data-raw-date="<?php echo isset($_GET['selected_date']) ? $_GET['selected_date'] : date('Y-m-d'); ?>"><?php echo isset($_GET['selected_date']) ? date('l, F j, Y', strtotime($_GET['selected_date'])) : date('l, F j, Y'); ?></span></p>
                    <p>Time: <span class="appointment-time"><?php echo isset($_GET['selected_time']) ? $_GET['selected_time'] : ''; ?></span></p>
                </div>

                <h2 class="text-xl font-bold mb-4 text-center">Patient Information</h2>
                <form id="registrationForm" method="POST" action="slot_booking.php">
                    <input type="hidden" name="form_submitted" value="1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <!-- First Name -->
                        <div>
                            <label for="patientFirstName" class="block mb-1 font-medium text-gray-700">First Name *</label>
                            <input type="text" id="patientFirstName" name="patientFirstName" required
                                class="form-control w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <!-- Last Name -->
                        <div>
                            <label for="patientLastName" class="block mb-1 font-medium text-gray-700">Last Name *</label>
                            <input type="text" id="patientLastName" name="patientLastName" required
                                class="form-control w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <!-- Preferred Name -->
                        <div>
                            <label for="patientPreferredName" class="block mb-1 font-medium text-gray-700">Preferred Name (Optional)</label>
                            <input type="text" id="patientPreferredName" name="patientPreferredName"
                                class="form-control w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <!-- Date of Birth -->
                        <div>
                            <label class="block mb-1 font-medium text-gray-700">Date of Birth *</label>
                            <div class="dob-inputs grid grid-cols-3 gap-2">
                                <select name="dobMonth" required class="form-control px-2 py-2 border rounded-lg">
                                    <option value="">Month</option>
                                    <?php
                                    $months = [
                                        1 => "January", 2 => "February", 3 => "March", 4 => "April",
                                        5 => "May", 6 => "June", 7 => "July", 8 => "August",
                                        9 => "September", 10 => "October", 11 => "November", 12 => "December"
                                    ];
                                    foreach ($months as $num => $name) {
                                        echo "<option value=\"$num\">$name</option>";
                                    }
                                    ?>
                                </select>
                                
                                <select name="dobDay" required class="form-control px-2 py-2 border rounded-lg">
                                    <option value="">Day</option>
                                    <?php for ($i = 1; $i <= 31; $i++) {
                                        echo "<option value=\"$i\">$i</option>";
                                    } ?>
                                </select>
                                
                                <select name="dobYear" required class="form-control px-2 py-2 border rounded-lg">
                                    <option value="">Year</option>
                                    <?php 
                                    $currentYear = date('Y');
                                    for ($i = $currentYear; $i >= $currentYear - 100; $i--) {
                                        echo "<option value=\"$i\">$i</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <!-- Contact Number -->
                        <div>
                            <label for="contactNumber" class="block mb-1 font-medium text-gray-700">Contact Number *</label>
                            <input type="tel" id="contactNumber" name="contactNumber" required
                                class="form-control w-full px-4 py-2 border rounded-lg" 
                                placeholder="Contact number">
                        </div>
                        
                        <!-- Email -->
                        <div>
                            <label for="email" class="block mb-1 font-medium text-gray-700">Email *</label>
                            <input type="email" id="email" name="email" required
                                class="form-control w-full px-4 py-2 border rounded-lg" 
                                placeholder="your@email.com">
                        </div>
                    </div>
                    
                    <!-- Preferred Specialty -->
                    <div class="mb-4">
                        <label for="preferredSpecialty" class="block mb-1 font-medium text-gray-700">Preferred Specialty</label>
                        <select id="preferredSpecialty" name="preferredSpecialty" class="form-control w-full px-4 py-2 border rounded-lg">
                            <option value="">Select a specialty (optional)</option>
                            <?php
                            $specialties = [
                                "Headache", "Fits", "Giddiness", "Paralysis", "Memory loss",
                                "Neck and back pain", "Parkinson's", "Hands and legs tingling"
                            ];
                            foreach ($specialties as $specialty) {
                                echo "<option value=\"" . htmlspecialchars($specialty) . "\">" . htmlspecialchars($specialty) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <!-- Reason for Appointment -->
                    <div class="mb-6">
                        <label for="reasonForAppointment" class="block mb-1 font-medium text-gray-700">Reason for Appointment *</label>
                        <textarea id="reasonForAppointment" name="reasonForAppointment" rows="4" required
                            class="form-control w-full px-4 py-2 border rounded-lg" 
                            placeholder="Please briefly describe your symptoms or reason for visit"></textarea>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 px-4 rounded-lg font-medium transition">
                        Submit Appointment Request
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="container mt-5">
        <h1 class="text-center mb-4">Book Your Appointment</h1>
        
        <div class="date-picker mb-4">
            <h3>Select a Date</h3>
            <div class="calendar-container">
                <!-- Simple date picker for demo purposes -->
                <div class="calendar-grid">
                    <?php
                    // Show next 7 days
                    $today = new DateTime();
                    for ($i = 0; $i < 7; $i++) {
                        $date = clone $today;
                        $date->modify("+$i days");
                        $dateStr = $date->format('Y-m-d');
                        $displayDate = $date->format('M d');
                        $dayName = $date->format('D');
                        
                        echo "<div class='date-item' data-date='$dateStr'>
                            <div class='day-name'>$dayName</div>
                            <div class='day-number'>$displayDate</div>
                        </div>";
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <div class="time-slots mb-4">
            <h3>Available Time Slots</h3>
            <div class="time-slot-container">
                <?php
                // Sample time slots
                $timeSlots = [
                    '09:00 AM', '09:30 AM', '10:00 AM', 
                    '10:30 AM', '11:00 AM', '11:30 AM',
                    '12:00 PM', '12:30 PM', '01:00 PM'
                ];
                
                // Get current date
                $currentDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
                
                foreach ($timeSlots as $time) {
                    echo "<div class='time-slot'>
                        <span class='time'>$time</span>
                        <button class='submit-button book-btn' data-time='$time' data-date='$currentDate'>
                            Book Appointment
                        </button>
                    </div>";
                }
                ?>
            </div>
        </div>
    </div>

    <style>
    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        overflow-y: auto;
    }

    .registration-modal {
        width: 90%;
        max-width: 800px;
        height: auto;
        max-height: 90vh;
        margin: 2% auto;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        overflow-y: auto;
        transition: transform 0.3s ease-in-out;
        animation: modalFadeIn 0.3s;
    }
    
    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .modal-header {
        padding: 1rem;
        background-color: #4169e1;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-radius: 8px 8px 0 0;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .modal-header h2 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .close {
        color: white;
        font-size: 1.5rem;
        font-weight: bold;
        cursor: pointer;
        padding: 0.25rem 0.5rem;
        transition: all 0.2s;
    }

    .close:hover {
        color: #f0f0f0;
        transform: scale(1.1);
    }

    .appointment-summary {
        background-color: #f4e6fa;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 24px;
        border-left: 4px solid #7047d1;
        box-shadow: 0 2px 4px rgba(112, 71, 209, 0.1);
        transition: all 0.2s;
    }
    
    .appointment-summary:hover {
        box-shadow: 0 4px 8px rgba(112, 71, 209, 0.2);
        transform: translateY(-2px);
    }

    .appointment-summary h3 {
        color: #4169e1;
        margin-bottom: 10px;
    }

    .appointment-date, .appointment-time {
        font-weight: 600;
        color: #7047d1;
    }

    .error-message {
        color: #dc3545;
        font-size: 0.875rem;
        margin-top: 0.25rem;
        margin-bottom: 0.5rem;
        animation: errorShake 0.5s;
    }
    
    @keyframes errorShake {
        0% { transform: translateX(0); }
        20% { transform: translateX(-5px); }
        40% { transform: translateX(5px); }
        60% { transform: translateX(-3px); }
        80% { transform: translateX(3px); }
        100% { transform: translateX(0); }
    }

    .form-control.is-invalid, .border-red-500 {
        border-color: #dc3545 !important;
        animation: errorBorderPulse 1s;
    }
    
    @keyframes errorBorderPulse {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
        70% { box-shadow: 0 0 0 5px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }

    .form-control:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        transition: all 0.2s;
    }

    .form-group, .mb-4 {
        margin-bottom: 1rem;
    }

    .form-control {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 1rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .grid {
        display: grid;
    }

    .grid-cols-1 {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }

    .grid-cols-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .gap-2 {
        gap: 0.5rem;
    }

    .gap-4 {
        gap: 1rem;
    }

    .w-full {
        width: 100%;
    }

    .px-4 {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .py-2 {
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }

    .py-3 {
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
    }

    .py-8 {
        padding-top: 2rem;
        padding-bottom: 2rem;
    }

    .rounded-lg {
        border-radius: 0.5rem;
    }

    .font-medium {
        font-weight: 500;
    }

    .text-center {
        text-align: center;
    }

    .text-gray-700 {
        color: #374151;
    }

    .text-white {
        color: white;
    }

    .bg-indigo-600 {
        background-color: #4f46e5;
    }

    .bg-indigo-700 {
        background-color: #4338ca;
    }

    .hover\:bg-indigo-700:hover {
        background-color: #4338ca;
    }

    .transition {
        transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 150ms;
    }

    .text-green-500 {
        color: #10b981;
    }

    .text-green-600 {
        color: #059669;
    }

    .text-gray-600 {
        color: #4b5563;
    }

    .bg-blue-600 {
        background-color: #2563eb;
    }

    .bg-blue-700 {
        background-color: #1d4ed8;
    }

    .hover\:bg-blue-700:hover {
        background-color: #1d4ed8;
    }

    /* Spinner for button loading state */
    .spinner-border {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        vertical-align: -0.125em;
        border: 0.2em solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        animation: spinner-border .75s linear infinite;
    }
    
    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
        border-width: 0.2em;
    }
    
    .spinner-border-lg {
        width: 2.5rem;
        height: 2.5rem;
        border-width: 0.25em;
    }

    .mr-2 {
        margin-right: 0.5rem;
    }

    @keyframes spinner-border {
        to { transform: rotate(360deg); }
    }

    /* Improve form layout on mobile */
    .dob-inputs {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
    }

    .hidden {
        display: none !important;
    }

    .mx-auto {
        margin-left: auto;
        margin-right: auto;
    }

    /* Additional styles for the success confirmation */
    .text-2xl {
        font-size: 1.5rem;
        line-height: 2rem;
    }

    .text-xl {
        font-size: 1.25rem;
        line-height: 1.75rem;
    }

    .text-lg {
        font-size: 1.125rem;
        line-height: 1.75rem;
    }

    .font-bold {
        font-weight: 700;
    }

    .mb-2 {
        margin-bottom: 0.5rem;
    }

    /* Form message container */
    #form-message-container {
        padding: 10px 15px;
        margin-bottom: 15px;
        border-radius: 4px;
        animation: messageFadeIn 0.3s ease-in;
    }
    
    @keyframes messageFadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Error and success colors */
    .bg-red-100 {
        background-color: #fee2e2;
    }

    .border-red-400 {
        border: 1px solid #f87171;
    }

    .text-red-700 {
        color: #b91c1c;
    }

    .bg-green-100 {
        background-color: #d1fae5;
    }

    .border-green-400 {
        border: 1px solid #34d399;
    }

    .text-green-700 {
        color: #047857;
    }

    /* Make date boxes more user-friendly */
    .date-item {
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 8px;
        text-align: center;
        background-color: #f9fafb;
    }

    .date-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        background-color: #f0f4ff;
        border-color: #bfdbfe;
    }

    .date-item.active {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        font-weight: bold;
        background-color: #dbeafe;
        border-color: #93c5fd;
        position: relative;
    }
    
    .date-item.active::after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 50%;
        transform: translateX(-50%);
        width: 10px;
        height: 10px;
        background-color: #dbeafe;
        border-right: 1px solid #93c5fd;
        border-bottom: 1px solid #93c5fd;
        transform: translateX(-50%) rotate(45deg);
    }
    
    .day-name {
        font-weight: 500;
        color: #4b5563;
    }
    
    .day-number {
        font-size: 1.1rem;
        font-weight: 600;
        color: #1f2937;
    }
    
    /* Time slot styling */
    .time-slot {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 8px;
        background-color: #f9fafb;
        border: 1px solid #e5e7eb;
        transition: all 0.2s;
    }
    
    .time-slot:hover {
        background-color: #f0f4ff;
        border-color: #bfdbfe;
        transform: translateX(5px);
    }
    
    .time {
        font-weight: 600;
        color: #1f2937;
    }
    
    .book-btn {
        background-color: #4f46e5;
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .book-btn:hover {
        background-color: #4338ca;
        transform: translateY(-2px);
        box-shadow: 0 2px 4px rgba(79, 70, 229, 0.3);
    }
    
    /* Success animation */
    @keyframes successCheckmark {
        0% {
            stroke-dashoffset: 66;
            opacity: 0;
        }
        50% {
            stroke-dashoffset: 33;
            opacity: 0.5;
        }
        100% {
            stroke-dashoffset: 0;
            opacity: 1;
        }
    }
    
    .success-checkmark {
        stroke-dasharray: 66;
        stroke-dashoffset: 66;
        animation: successCheckmark 0.8s ease-in-out forwards;
    }

    /* Improve modal responsiveness */
    @media (max-width: 768px) {
        .registration-modal {
            width: 95%;
            margin: 5% auto;
            max-height: 85vh;
        }
        
        .time-slot-container {
            grid-template-columns: 1fr;
        }
        
        .calendar-grid {
            gap: 8px;
        }
        
        .date-item {
            padding: 6px;
        }
        
        .day-name {
            font-size: 0.85rem;
        }
        
        .day-number {
            font-size: 0.95rem;
        }
    }
    
    @media (min-width: 768px) {
        .md\:grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
        }
        
        .time-slot-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('registrationModal');
        const closeBtn = document.querySelector('.close');
        const submitButtons = document.querySelectorAll('.submit-button');
        const registrationForm = document.getElementById('registrationForm');

        // Open modal when clicking submit button
        submitButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get selected time and date from the button's data attributes
                const selectedTime = this.getAttribute('data-time') || '';
                const selectedDate = this.getAttribute('data-date') || '<?php echo isset($_GET['selected_date']) ? $_GET['selected_date'] : date('Y-m-d'); ?>';
                
                // Format date for display
                let formattedDate = selectedDate;
                try {
                    const dateObj = new Date(selectedDate);
                    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                    formattedDate = dateObj.toLocaleDateString('en-US', options);
                } catch (e) {
                    console.error('Error formatting date:', e);
                }
                
                // Update the modal with selected date and time
                if (document.querySelector('.appointment-date')) {
                    document.querySelector('.appointment-date').setAttribute('data-raw-date', selectedDate);
                    document.querySelector('.appointment-date').textContent = formattedDate;
                }
                
                if (document.querySelector('.appointment-time')) {
                    document.querySelector('.appointment-time').textContent = selectedTime;
                }
                
                // Show the modal
                if (modal) {
                    modal.style.display = 'block';
                    console.log('Modal opened with date:', selectedDate, 'time:', selectedTime);
                } else {
                    console.error('Modal element not found');
                }
            });
        });

        // Close modal when clicking X button
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                if (modal) modal.style.display = 'none';
            });
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Form validation function
        function validateForm() {
            let isValid = true;
            
            try {
                // Helper function to get element value safely
                function getFieldValue(selector) {
                    const element = document.querySelector(selector);
                    return element ? element.value.trim() : '';
                }
                
                // Get all form input values
                const firstName = getFieldValue('#patientFirstName');
                const lastName = getFieldValue('#patientLastName');
                const dobMonth = getFieldValue('select[name="dobMonth"]');
                const dobDay = getFieldValue('select[name="dobDay"]');
                const dobYear = getFieldValue('select[name="dobYear"]');
                const contactNumber = getFieldValue('#contactNumber'); 
                const email = getFieldValue('#email');
                const reasonForAppointment = getFieldValue('#reasonForAppointment');
                
                console.log('Validating form fields:');
                console.log('First Name:', firstName);
                console.log('Last Name:', lastName);
                console.log('DOB:', `${dobMonth}/${dobDay}/${dobYear}`);
                console.log('Contact:', contactNumber);
                console.log('Email:', email);
                console.log('Reason:', reasonForAppointment);
                
                // Clear previous error messages
                document.querySelectorAll('.error-message').forEach(el => el.remove());
                document.querySelectorAll('.is-invalid, .border-red-500').forEach(el => {
                    el.classList.remove('is-invalid');
                    el.classList.remove('border-red-500');
                });
                
                // Validate first name
                if (!firstName) {
                    displayError('patientFirstName', 'First name is required');
                    isValid = false;
                }
                
                // Validate last name
                if (!lastName) {
                    displayError('patientLastName', 'Last name is required');
                    isValid = false;
                }
                
                // Validate date of birth
                if (!dobMonth || !dobDay || !dobYear) {
                    displayError('dobYear', 'Complete date of birth is required', true);
                    isValid = false;
                } else {
                    try {
                        // Basic validation to ensure the date is valid
                        const month = parseInt(dobMonth, 10);
                        const day = parseInt(dobDay, 10);
                        const year = parseInt(dobYear, 10);
                        
                        // Check if the date is valid using JavaScript Date object
                        const date = new Date(year, month - 1, day);
                        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
                            displayError('dobYear', 'Please enter a valid date of birth', true);
                            isValid = false;
                        }
                        
                        // Check if date is in the future
                        if (date > new Date()) {
                            displayError('dobYear', 'Date of birth cannot be in the future', true);
                            isValid = false;
                        }
                    } catch (error) {
                        console.error('Error validating date:', error);
                        displayError('dobYear', 'Please enter a valid date of birth', true);
                        isValid = false;
                    }
                }
                
                // Validate contact number
                if (!contactNumber) {
                    displayError('contactNumber', 'Contact number is required');
                    isValid = false;
                } else if (!/^[0-9\-\(\)\/\+\s]*$/.test(contactNumber)) {
                    displayError('contactNumber', 'Please enter a valid contact number');
                    isValid = false;
                }
                
                // Validate email
                if (!email) {
                    displayError('email', 'Email is required');
                    isValid = false;
                } else if (!/\S+@\S+\.\S+/.test(email)) {
                    displayError('email', 'Please enter a valid email address');
                    isValid = false;
                }
                
                // Validate reason for appointment
                if (!reasonForAppointment) {
                    displayError('reasonForAppointment', 'Reason for appointment is required');
                    isValid = false;
                }
                
                console.log('Form validation result:', isValid);
                return isValid;
            } catch (error) {
                console.error('Error during form validation:', error);
                showFormMessage('An error occurred during form validation. Please try again.');
                return false;
            }
        }
        
        // Function to show validation errors
        function displayError(fieldId, message, isDobField = false) {
            try {
                const field = document.getElementById(fieldId);
                if (!field) {
                    console.error(`Field with ID ${fieldId} not found`);
                    return;
                }
                
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.textContent = message;
                
                if (isDobField) {
                    // For DOB fields, add error after the parent div
                    const parentDiv = field.closest('.dob-inputs');
                    if (parentDiv) {
                        parentDiv.after(errorDiv);
                        
                        // Add error class to all select fields
                        document.querySelectorAll('.dob-inputs select').forEach(select => {
                            select.classList.add('is-invalid');
                            select.classList.add('border-red-500');
                        });
                    }
                } else {
                    // For other fields, add error after the field
                    field.classList.add('is-invalid');
                    field.classList.add('border-red-500');
                    field.after(errorDiv);
                }
            } catch (error) {
                console.error('Error displaying validation error:', error);
            }
        }

        // Function to show status message in the form
        function showFormMessage(message, isError = true) {
            try {
                // Check if there's an existing message container
                let messageContainer = document.getElementById('form-message-container');
                
                // Create one if it doesn't exist
                if (!messageContainer) {
                    messageContainer = document.createElement('div');
                    messageContainer.id = 'form-message-container';
                    messageContainer.className = 'mb-4 p-3 rounded';
                    
                    // Get the submit button to insert before it
                    const submitBtn = registrationForm ? registrationForm.querySelector('button[type="submit"]') : null;
                    if (submitBtn && submitBtn.parentNode) {
                        submitBtn.parentNode.insertBefore(messageContainer, submitBtn);
                    } else {
                        // If we can't find the submit button, try to append to the form
                        if (registrationForm) {
                            registrationForm.appendChild(messageContainer);
                        } else {
                            console.error('Cannot find registration form');
                            return;
                        }
                    }
                }
                
                // Set appropriate styles based on whether it's an error
                if (isError) {
                    messageContainer.className = 'mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded';
                } else {
                    messageContainer.className = 'mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded';
                }
                
                messageContainer.textContent = message;
                messageContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } catch (error) {
                console.error('Error showing form message:', error);
                alert(message); // Fallback to alert if we can't show the message in the form
            }
        }

        // Update the form submission event handler
        if (registrationForm) {
            registrationForm.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('Form submitted');
                
                try {
                    // Validate form
                    if (!validateForm()) {
                        console.log('Form validation failed');
                        showFormMessage('Please correct the errors in the form and try again.');
                        return;
                    }
                    
                    // Get form data
                    const formData = new FormData(this);
                    
                    // Get date and time info
                    const dateElement = document.querySelector('.appointment-date');
                    const timeElement = document.querySelector('.appointment-time');
                    
                    if (!dateElement || !timeElement) {
                        showFormMessage('Could not find date or time information. Please refresh the page and try again.');
                        console.error('Date or time elements not found');
                        return;
                    }
                    
                    const selectedDate = dateElement.getAttribute('data-raw-date') || 
                                        '<?php echo isset($_GET['selected_date']) ? $_GET['selected_date'] : date('Y-m-d'); ?>';
                    const selectedTime = timeElement.textContent.trim();
                    
                    if (!selectedDate || !selectedTime) {
                        showFormMessage('Please select a date and time for your appointment.');
                        console.error('Missing date or time');
                        return;
                    }
                    
                    // Ensure appointment is not in the past
                    const now = new Date();
                    const appointmentDate = new Date(selectedDate + ' ' + selectedTime);
                    if (appointmentDate < now) {
                        showFormMessage('Cannot book appointments in the past. Please select a future date and time.');
                        return;
                    }
                    
                    // Add date and time to form data
                    formData.append('selected_date', selectedDate);
                    formData.append('selected_time', selectedTime);
                    
                    // Log all form data for debugging
                    console.log('Form data being submitted:');
                    formData.forEach((value, key) => {
                        console.log(`${key}: ${value}`);
                    });
                    
                    // Clear any previous error messages
                    const existingMessage = document.getElementById('form-message-container');
                    if (existingMessage) {
                        existingMessage.remove();
                    }
                    
                    // Disable submit button to prevent double submission
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Processing...';
                    } else {
                        console.warn('Submit button not found');
                    }
                    
                    // Display a temporary processing message
                    showFormMessage('Processing your appointment request...', false);
                    
                    // Send form data to server with timeout to prevent hanging requests
                    const fetchPromise = fetch('slot_booking.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    // Add a timeout for the fetch request
                    const timeoutPromise = new Promise((_, reject) => {
                        setTimeout(() => reject(new Error('Request timed out')), 30000); // 30 second timeout
                    });
                    
                    // Race between the fetch and the timeout
                    Promise.race([fetchPromise, timeoutPromise])
                        .then(response => {
                            console.log('Response status:', response.status);
                            if (!response.ok) {
                                throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                            }
                            return response.json().catch(err => {
                                console.error('Error parsing JSON response:', err);
                                throw new Error('Server returned an invalid response format');
                            });
                        })
                        .then(data => {
                            console.log('Response data:', data);
                            
                            if (data && data.success) {
                                // Show success message
                                const formContainer = registrationForm.parentElement;
                                if (formContainer) {
                                    formContainer.innerHTML = `
                                        <div class="text-center py-8">
                                            <svg class="mx-auto h-16 w-16 text-green-500 mb-4" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path class="success-checkmark" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" opacity="0.2"></circle>
                                            </svg>
                                            <h2 class="text-2xl font-bold mb-2 text-green-600">Appointment Request Submitted</h2>
                                            <p class="mb-2 text-gray-600">Thank you! We'll contact you shortly to confirm your appointment for ${dateElement.textContent} at ${selectedTime}.</p>
                                            <p class="mb-6 text-gray-600"><span class="font-bold">Appointment ID:</span> ${data.appointment_id || 'N/A'}</p>
                                            <button onclick="window.location.reload()" class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition">Return to Booking</button>
                                        </div>
                                    `;
                                } else {
                                    showFormMessage('Appointment successfully booked! Your appointment ID is: ' + (data.appointment_id || 'N/A'), false);
                                    registrationForm.reset();
                                    
                                    // Re-enable submit button
                                    if (submitBtn) {
                                        submitBtn.disabled = false;
                                        submitBtn.innerHTML = 'Submit Appointment Request';
                                    }
                                }
                            } else {
                                // Show error message
                                const errorMessage = data && data.message ? data.message : 'Registration failed. Please try again.';
                                showFormMessage(errorMessage);
                                
                                // Re-enable the submit button
                                if (submitBtn) {
                                    submitBtn.disabled = false;
                                    submitBtn.innerHTML = 'Submit Appointment Request';
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error submitting form:', error);
                            showFormMessage('An error occurred while submitting the form: ' + error.message);
                            
                            // Re-enable the submit button
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = 'Submit Appointment Request';
                            }
                        });
                } catch (error) {
                    console.error('Error in form submission handler:', error);
                    showFormMessage('An unexpected error occurred. Please try again.');
                    
                    // Re-enable the submit button
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Submit Appointment Request';
                    }
                }
            });
        } else {
            console.error('Registration form not found!');
        }

        // Intercept all links and buttons that would go to registration-form.php
        const bookingLinks = document.querySelectorAll('a[href*="registration-form.php"], button[onclick*="registration-form.php"], a[href*="slot-booking.php?action=book"]');
        
        bookingLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default navigation
                console.log('Booking link clicked');
                
                // Extract date and time from URL parameters or data attributes
                let dateParam = '';
                let timeParam = '';
                
                // Check if there's an href attribute (for <a> tags)
                if (this.hasAttribute('href')) {
                    const url = this.getAttribute('href');
                    const urlParams = new URLSearchParams(url.split('?')[1] || '');
                    dateParam = urlParams.get('date') || '';
                    timeParam = urlParams.get('time') || '';
                }
                
                // Check data attributes (for any element)
                dateParam = this.getAttribute('data-date') || dateParam;
                timeParam = this.getAttribute('data-time') || timeParam;
                
                // Parse onclick attribute if it exists
                if (this.hasAttribute('onclick')) {
                    const onclickAttr = this.getAttribute('onclick');
                    if (onclickAttr.includes('date=')) {
                        const dateMatch = onclickAttr.match(/date=([^&'"]+)/);
                        if (dateMatch && dateMatch[1]) {
                            dateParam = decodeURIComponent(dateMatch[1]);
                        }
                    }
                    if (onclickAttr.includes('time=')) {
                        const timeMatch = onclickAttr.match(/time=([^&'"]+)/);
                        if (timeMatch && timeMatch[1]) {
                            timeParam = decodeURIComponent(timeMatch[1]);
                        }
                    }
                }
                
                console.log('Intercepted link with date:', dateParam, 'time:', timeParam);
                
                // If we have date/time, add them as data attributes to this element
                if (dateParam) {
                    this.setAttribute('data-date', dateParam);
                }
                if (timeParam) {
                    this.setAttribute('data-time', timeParam);
                }
                
                // Format the date for display
                let formattedDate = dateParam;
                try {
                    if (dateParam) {
                        const dateObj = new Date(dateParam);
                        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                        formattedDate = dateObj.toLocaleDateString('en-US', options);
                    }
                } catch (e) {
                    console.error('Error formatting date:', e);
                }
                
                // Update the modal with date and time information
                const dateElement = document.querySelector('.appointment-date');
                const timeElement = document.querySelector('.appointment-time');
                
                if (dateElement && timeElement) {
                    dateElement.setAttribute('data-raw-date', dateParam);
                    dateElement.textContent = formattedDate;
                    timeElement.textContent = timeParam;
                    
                    // Show the modal
                    if (modal) {
                        modal.style.display = 'block';
                    } else {
                        console.error('Modal element not found');
                    }
                } else {
                    console.error('Date or time element not found in the modal');
                }
            });
        });

        // Also intercept form submissions that go to registration-form.php
        const forms = document.querySelectorAll('form[action*="registration-form.php"]');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent the form from submitting normally
                console.log('Form submission intercepted');
                
                // Get date and time from hidden inputs if they exist
                let dateParam = '';
                let timeParam = '';
                
                // Look for hidden inputs with date and time
                const dateInput = this.querySelector('input[name="date"], input[name="appointment_date"]');
                const timeInput = this.querySelector('input[name="time"], input[name="appointment_time"]');
                
                if (dateInput) dateParam = dateInput.value || '';
                if (timeInput) timeParam = timeInput.value || '';
                
                console.log('Form intercept with date:', dateParam, 'time:', timeParam);
                
                if (!dateParam || !timeParam) {
                    console.error('Missing date or time in the form');
                    alert('Please select a date and time for your appointment.');
                    return;
                }
                
                // Format the date for display
                let formattedDate = dateParam;
                try {
                    if (dateParam) {
                        const dateObj = new Date(dateParam);
                        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                        formattedDate = dateObj.toLocaleDateString('en-US', options);
                    }
                } catch (e) {
                    console.error('Error formatting date:', e);
                }
                
                // Update the modal with date and time information
                const dateElement = document.querySelector('.appointment-date');
                const timeElement = document.querySelector('.appointment-time');
                
                if (dateElement && timeElement) {
                    dateElement.setAttribute('data-raw-date', dateParam);
                    dateElement.textContent = formattedDate;
                    timeElement.textContent = timeParam;
                    
                    // Show the modal
                    if (modal) {
                        modal.style.display = 'block';
                    } else {
                        console.error('Modal element not found');
                    }
                } else {
                    console.error('Date or time element not found in the modal');
                }
            });
        });

        // Add date selection functionality
        const dateItems = document.querySelectorAll('.date-item');
        
        dateItems.forEach(item => {
            item.addEventListener('click', function() {
                // Remove active class from all dates
                dateItems.forEach(d => d.classList.remove('active'));
                
                // Add active class to selected date
                this.classList.add('active');
                
                // Update all book buttons with the selected date
                const selectedDate = this.getAttribute('data-date');
                const bookButtons = document.querySelectorAll('.book-btn');
                
                bookButtons.forEach(btn => {
                    btn.setAttribute('data-date', selectedDate);
                });
            });
        });
        
        // Set first date as active by default
        if (dateItems.length > 0 && !document.querySelector('.date-item.active')) {
            dateItems[0].classList.add('active');
            
            // Update book buttons with the first date
            const selectedDate = dateItems[0].getAttribute('data-date');
            const bookButtons = document.querySelectorAll('.book-btn');
            
            bookButtons.forEach(btn => {
                btn.setAttribute('data-date', selectedDate);
            });
        }

        // Add explicit click event to the submit button to debug
        const submitBtn = document.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.addEventListener('click', function(e) {
                console.log('Submit button clicked directly');
                // Don't prevent default here, let the form submit handler handle it
            });
        }
        
        // Add additional debugging for network requests
        const originalFetch = window.fetch;
        window.fetch = function(url, options) {
            console.log('Fetch intercepted:', url, options);
            return originalFetch(url, options)
                .then(response => {
                    console.log('Fetch response status:', response.status);
                    return response;
                })
                .catch(error => {
                    console.error('Fetch error intercepted:', error);
                    throw error;
                });
        };

        // Debug form submit event to verify it's being caught
        if (registrationForm) {
            registrationForm.addEventListener('submit', function(e) {
                console.log('Form submit event fired');
                // Let the regular handler continue
            }, true);  // Use capture phase to see if event is being fired at all
        }

        // Debug submission button
        const debugSubmitBtn = document.getElementById('debugSubmit');
        if (debugSubmitBtn && registrationForm) {
            debugSubmitBtn.addEventListener('click', function() {
                console.log('Debug form submission clicked');
                
                // Validate form first
                if (!validateForm()) {
                    console.log('Form validation failed in debug mode');
                    showFormMessage('Please correct the errors in the form and try again.');
                    return;
                }
                
                // Get form data
                const formData = new FormData(registrationForm);
                
                // Get date and time info
                const dateElement = document.querySelector('.appointment-date');
                const timeElement = document.querySelector('.appointment-time');
                
                if (!dateElement || !timeElement) {
                    showFormMessage('Could not find date or time information. Please refresh the page and try again.');
                    console.error('Date or time elements not found in debug mode');
                    return;
                }
                
                const selectedDate = dateElement.getAttribute('data-raw-date') || '<?php echo date("Y-m-d"); ?>';
                const selectedTime = timeElement.textContent.trim();
                
                if (!selectedDate || !selectedTime) {
                    showFormMessage('Please select a date and time for your appointment.');
                    console.error('Missing date or time in debug mode');
                    return;
                }
                
                // Add date and time to form data
                formData.append('selected_date', selectedDate);
                formData.append('selected_time', selectedTime);
                formData.append('debug_mode', '1');
                
                // Show submitted data for debugging without saving to DB
                fetch('slot_booking.php?debug_submit=1', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Create a formatted display of the form data
                    const formContainer = registrationForm.parentElement;
                    if (formContainer) {
                        let debugResult = `
                            <div class="p-4 bg-yellow-100 border border-yellow-400 rounded">
                                <h3 class="text-xl font-bold mb-3 text-yellow-800">Debug Form Submission</h3>
                                <p class="mb-2">The form data was received successfully but not saved to the database.</p>
                                <div class="bg-white p-3 rounded shadow-inner mb-4 overflow-auto max-h-96">
                                    <pre class="text-sm">${JSON.stringify(data, null, 4)}</pre>
                                </div>
                                <p class="mb-2">Your form appears to be working correctly.</p>
                                <button type="button" onclick="location.reload()" class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition">
                                    Reset Form
                                </button>
                            </div>
                        `;
                        formContainer.innerHTML = debugResult;
                    } else {
                        showFormMessage('Debug mode: Form data was processed but not saved. Check console.');
                        console.log('Debug form data:', data);
                    }
                })
                .catch(error => {
                    showFormMessage('Error in debug mode: ' + error.message);
                    console.error('Debug error:', error);
                });
            });
        }

        // Test database connection button
        const testDbBtn = document.getElementById('testDbConnection');
        if (testDbBtn) {
            testDbBtn.addEventListener('click', function() {
                const resultDiv = document.getElementById('testResult');
                if (resultDiv) {
                    resultDiv.innerHTML = '<div class="spinner-border spinner-border-sm mr-2"></div>Testing database connection...';
                    resultDiv.className = 'mt-2 p-3 bg-blue-100 border border-blue-400 text-blue-700 rounded';
                    resultDiv.style.display = 'block';
                    
                    // Simple fetch to test DB connection
                    fetch('slot_booking.php?test_db=1')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                resultDiv.innerHTML = '✅ ' + data.message;
                                resultDiv.className = 'mt-2 p-3 bg-green-100 border border-green-400 text-green-700 rounded';
                            } else {
                                resultDiv.innerHTML = '❌ ' + data.message;
                                resultDiv.className = 'mt-2 p-3 bg-red-100 border border-red-400 text-red-700 rounded';
                            }
                        })
                        .catch(error => {
                            resultDiv.innerHTML = '❌ Error: ' + error.message;
                            resultDiv.className = 'mt-2 p-3 bg-red-100 border border-red-400 text-red-700 rounded';
                        });
                }
            });
        }
    });
    </script>
</body>
</html> 