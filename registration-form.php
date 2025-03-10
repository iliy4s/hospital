<?php 
// Initialize variables to retain form values after submission
$patientFirstName = $patientLastName = $patientPreferredName = $contactNumber = $email = '';
$dobMonth = $dobDay = $dobYear = $preferredSpecialty = $reasonForAppointment = '';
$errors = [];
$formSubmitted = false;

// Get the selected date and time from query parameters
$selectedDate = isset($_GET['date']) ? $_GET['date'] : '';
$selectedTime = isset($_GET['time']) ? $_GET['time'] : '';

// Format the selected date for display
$formattedDate = '';
if (!empty($selectedDate)) {
    $dateObj = new DateTime($selectedDate);
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
        // Validate date is valid
        if (!checkdate(intval($dobMonth), intval($dobDay), intval($dobYear))) {
            $errors['dob'] = 'Please enter a valid date';
        }
    }

    // Validate contact number
    if (empty($_POST['contactNumber'])) {
        $errors['contactNumber'] = 'Contact number is required';
    } else {
        $contactNumber = test_input($_POST['contactNumber']);
        if (!preg_match("/^[0-9\-\(\)\/\+\s]*$/", $contactNumber)) {
            $errors['contactNumber'] = 'Invalid phone number format';
        }
    }

    // Validate email
    if (empty($_POST['email'])) {
        $errors['email'] = 'Email is required';
    } else {
        $email = test_input($_POST['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }
    }

    // Process preferred specialty (optional)
    $preferredSpecialty = test_input($_POST['preferredSpecialty'] ?? '');

    // Validate reason for appointment
    if (empty($_POST['reasonForAppointment'])) {
        $errors['reasonForAppointment'] = 'Reason for appointment is required';
    } else {
        $reasonForAppointment = test_input($_POST['reasonForAppointment']);
    }

    // Save the appointment details
    $appointmentDate = test_input($_POST['appointmentDate'] ?? '');
    $appointmentTime = test_input($_POST['appointmentTime'] ?? '');

    // If no errors, process the form submission
    if (empty($errors)) {
        $formSubmitted = true;
        // Here you would typically:
        // 1. Save to database
        // 2. Send confirmation email
        // 3. Redirect to a thank you page
        // For this example, we'll just display a success message
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
                <p class="mb-6 text-gray-600">Thank you, <?php echo htmlspecialchars($patientFirstName); ?>! We'll contact you shortly to confirm your appointment for <?php echo htmlspecialchars($appointmentDate); ?> at <?php echo htmlspecialchars($appointmentTime); ?>.</p>
                <a href="index.php" class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition">Return to Home</a>
            </div>
        <?php else: ?>
            <!-- Appointment Summary -->
            <?php if (!empty($selectedDate) && !empty($selectedTime)): ?>
                <div class="appointment-summary">
                    <h3 class="text-lg font-bold mb-2">Appointment Details</h3>
                    <p>Date: <span class="appointment-date"><?php echo htmlspecialchars($formattedDate); ?></span> Time: <span class="appointment-time"><?php echo htmlspecialchars($selectedTime); ?></span></p>
                </div>
            <?php endif; ?>

            <h2 class="text-xl font-bold mb-4 text-center">Patient Information</h2>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . (!empty($selectedDate) && !empty($selectedTime) ? "?date=$selectedDate&time=$selectedTime" : "")); ?>">
                <!-- Hidden fields to preserve appointment information -->
                <input type="hidden" name="appointmentDate" value="<?php echo htmlspecialchars($selectedDate); ?>">
                <input type="hidden" name="appointmentTime" value="<?php echo htmlspecialchars($selectedTime); ?>">
                
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
                            placeholder="(123) 456-7890">
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
                
                <!-- Submit Button -->
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 px-4 rounded-lg font-medium transition">
                    Submit Appointment Request
                </button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        // Any client-side JavaScript for form validation or enhancements can go here
        document.addEventListener('DOMContentLoaded', function() {
            // Example: Form validation or interactive elements
        });
    </script>
</body>
</html>