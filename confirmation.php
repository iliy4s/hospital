<?php
session_start();

// Check if booking reference exists in session
if (empty($_SESSION['booking_reference']) || empty($_SESSION['appointment_id'])) {
    // Redirect to booking page if no booking reference found
    header("Location: slot-booking.php");
    exit;
}

// Get booking details from session
$bookingReference = $_SESSION['booking_reference'];
$appointmentId = $_SESSION['appointment_id'];
$patientName = $_SESSION['patient_name'] ?? 'Patient';
$appointmentDate = $_SESSION['appointment_date'] ?? '';
$appointmentTime = $_SESSION['appointment_time'] ?? '';

// Clear session variables after use
unset($_SESSION['booking_reference']);
unset($_SESSION['appointment_id']);
unset($_SESSION['patient_name']);
unset($_SESSION['appointment_date']);
unset($_SESSION['appointment_time']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Appointment Confirmation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 600px;
            margin: 2rem auto;
        }
        .confirmation-card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background-color: #d1fae5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .success-icon svg {
            width: 40px;
            height: 40px;
            color: #10b981;
        }
        .reference-number {
            background-color: #f4e6fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 1.5rem 0;
            text-align: center;
        }
        .reference-number p {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: #7047d1;
        }
        .appointment-details {
            margin: 1.5rem 0;
            padding: 0.5rem;
        }
        .appointment-details p {
            margin-bottom: 0.5rem;
        }
        .action-buttons {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        .print-button {
            background-color: #e0e7ff;
            color: #4338ca;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        .print-button:hover {
            background-color: #c7d2fe;
        }
        .home-button {
            background-color: #7047d1;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        .home-button:hover {
            background-color: #5f3dc4;
        }
        @media print {
            .action-buttons {
                display: none;
            }
            body {
                background-color: white;
            }
            .confirmation-card {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="confirmation-card">
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            
            <h1 class="text-center text-2xl font-bold mb-2">Appointment Confirmed!</h1>
            <p class="text-center text-gray-600 mb-4">
                Thank you, <?php echo htmlspecialchars($patientName); ?>! Your appointment has been successfully scheduled.
            </p>
            
            <div class="reference-number">
                <p>Booking Reference: <?php echo htmlspecialchars($bookingReference); ?></p>
            </div>
            
            <div class="appointment-details">
                <p><strong>Date:</strong> <?php echo htmlspecialchars($appointmentDate); ?></p>
                <p><strong>Time:</strong> <?php echo htmlspecialchars($appointmentTime); ?></p>
                <p><strong>Appointment ID:</strong> <?php echo htmlspecialchars($appointmentId); ?></p>
            </div>
            
            <p class="text-gray-600 text-sm">A confirmation email has been sent to your email address with these details.</p>
            
            <div class="action-buttons">
                <button onclick="window.print()" class="print-button">
                    Print Confirmation
                </button>
                <a href="index.php" class="home-button">Return to Home</a>
            </div>
        </div>
    </div>
</body>
</html> 