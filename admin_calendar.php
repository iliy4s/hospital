<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include database connection
require_once 'connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Log the unauthorized access attempt
    error_log("Unauthorized access attempt to admin_calendar.php. Session data: " . print_r($_SESSION, true));
    
    // Redirect to login page
    header('Location: admin_login.php?error=not_logged_in');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set header to return JSON
    header('Content-Type: application/json');
    
    // Initialize response array
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Validate required fields
        $requiredFields = ['patientFirstName', 'patientLastName', 'dobMonth', 'dobDay', 'dobYear', 'contactNumber', 'email', 'reasonForAppointment'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        // Sanitize and validate input
        $patientFirstName = filter_var($_POST['patientFirstName'], FILTER_SANITIZE_STRING);
        $patientLastName = filter_var($_POST['patientLastName'], FILTER_SANITIZE_STRING);
        $patientPreferredName = filter_var($_POST['patientPreferredName'] ?? '', FILTER_SANITIZE_STRING);
        $contactNumber = filter_var($_POST['contactNumber'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $preferredSpecialty = filter_var($_POST['preferredSpecialty'] ?? '', FILTER_SANITIZE_STRING);
        $reasonForAppointment = filter_var($_POST['reasonForAppointment'], FILTER_SANITIZE_STRING);

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

        // Get current date and time
        $appointmentDate = date('Y-m-d');
        $appointmentTime = date('H:i:s');

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

        // Success response
        $response['success'] = true;
        $response['message'] = 'Registration successful!';

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    // Send JSON response and exit
    echo json_encode($response);
    exit();
}

// Log successful access
error_log("Admin calendar accessed by: " . ($_SESSION['admin_username'] ?? 'unknown'));

// Get the current month and year
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Get the selected date (default to today if not set)
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Handle month overflow/underflow
if ($month > 12) {
    $month = 1;
    $year++;
} elseif ($month < 1) {
    $month = 12;
    $year--;
}

// Get month name
$monthName = date('F', mktime(0, 0, 0, $month, 1, $year));

// Get the first day of the month
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$dayOfWeek = date('w', $firstDay);

// Get appointments for the current month
$startDate = date('Y-m-01', $firstDay);
$endDate = date('Y-m-t', $firstDay);

// Initialize appointments array
$appointments = [];

try {
    // Improved query with detailed appointment information
    $query = "SELECT * FROM appointments 
              WHERE appointment_date BETWEEN ? AND ? 
              ORDER BY appointment_date, appointment_time";
              
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $startDate, $endDate);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    // Store appointments by date
    while ($row = $result->fetch_assoc()) {
        $date = $row['appointment_date'];
        if (!isset($appointments[$date])) {
            $appointments[$date] = [];
        }
        $appointments[$date][] = $row;
    }
    
    // Debug - Log the number of appointments found
    error_log("Found " . count($appointments) . " days with appointments in " . $monthName . " " . $year);
    
} catch (Exception $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
    // Initialize empty array if query fails
    $appointments = [];
}

// Get doctor list (if needed)
$doctors = [];
try {
    $doctorQuery = "SELECT * FROM doctors WHERE status = 'active' ORDER BY last_name";
    $doctorResult = $conn->query($doctorQuery);
    
    if ($doctorResult) {
        while ($row = $doctorResult->fetch_assoc()) {
            $doctors[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching doctors: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Admin Calendar - Dr. Kiran Neuro Centre</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
      }
      
      body {
        background-color: #f5f7f9;
      }
      
      .container {
        max-width: 100%;
        padding: 20px;
      }
      
      .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 0;
      }
      
      .title {
        font-size: 32px;
        color: #4169e1;
        font-weight: 500;
      }
      
      .check-request-btn {
        background-color: #4169e1;
        color: white;
        border: none;
        border-radius: 50px;
        padding: 10px 20px;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 5px;
        cursor: pointer;
      }
      
      .check-request-btn:hover {
        background-color: #3154b3;
      }
      
      .toolbar {
        display: flex;
        justify-content: space-between;
        padding: 16px 0;
        margin-bottom: 20px;
      }
      
      .toolbar-left {
        display: flex;
        align-items: center;
        gap: 20px;
      }
      
      .filter-btn, .monthly-btn, .download-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        background: none;
        border: none;
        padding: 8px 12px;
        font-size: 14px;
        color: #333;
        cursor: pointer;
        border-radius: 4px;
      }
      
      .filter-btn {
        color: #4169e1;
      }
      
      .toolbar-right {
        display: flex;
        align-items: center;
        gap: 20px;
      }
      
      .search-icon, .support-icon, .content-icon {
        width: 24px;
        height: 24px;
        color: #333;
        cursor: pointer;
      }
      
      /* Calendar container */
      .calendar-container {
        display: flex;
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        margin: 20px 0;
        overflow: hidden;
      }
      
      /* Sidebar styles */
      .sidebar {
        width: 320px;
        background-color: white;
        border-right: 1px solid #e6e6e6;
      }
      
      .sidebar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid #e6e6e6;
      }
      
      .sidebar-title {
        font-size: 18px;
        font-weight: 500;
        color: #333;
      }
      
      .nav-arrows {
        display: flex;
        gap: 5px;
      }
      
      .nav-btn {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: white;
        border: none;
        cursor: pointer;
      }
      
      .nav-prev {
        background-color: #4169e1;
        color: white;
      }
      
      /* Mini calendar styles */
      .calendar-grid {
        padding: 15px;
      }
      
      .weekdays {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        text-align: center;
        margin-bottom: 10px;
      }
      
      .weekday {
        font-size: 14px;
        font-weight: 500;
        color: #666;
        padding: 8px;
      }
      
      .days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 5px;
      }
      
      .day {
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        border-radius: 50%;
        cursor: pointer;
        position: relative;
        transition: all 0.2s;
      }
      
      .day:hover {
        background-color: #f3f4f6;
      }
      
      .day.active {
        background-color: #4169e1;
        color: white;
      }
      
      .day.selected {
        background-color: #4169e1;
        color: white;
      }
      
      .day.other-month {
        color: #ccc;
      }
      
      .day.sunday {
        color: #f44336;
      }
      
      .day.has-appointments:after {
        content: '';
        position: absolute;
        bottom: 5px;
        width: 6px;
        height: 6px;
        background-color: #4169e1;
        border-radius: 50%;
      }
      
      .no-appointments {
        text-align: center;
        padding: 20px;
        color: #6b7280;
        font-style: italic;
      }
      
      /* Main calendar styles */
      .main-calendar {
        flex-grow: 1;
        overflow-y: auto;
        position: relative;
      }
      
      .main-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        background-color: white;
        border-bottom: 1px solid #e6e6e6;
        position: sticky;
        top: 0;
        z-index: 10;
      }
      
      .month-nav {
        display: flex;
        align-items: center;
        gap: 15px;
      }
      
      .month-title {
        font-size: 18px;
        font-weight: 500;
      }
      
      .today-btn {
        background-color: white;
        border: 1px solid #e6e6e6;
        border-radius: 20px;
        padding: 5px 15px;
        font-size: 14px;
        color: #4169e1;
        cursor: pointer;
        text-decoration: none;
      }
      
      .today-btn:hover {
        background-color: #f3f4f6;
      }
      
      .month-nav-arrows {
        display: flex;
        gap: 5px;
      }
      
      .month-nav-btn {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: white;
        border: 1px solid #e6e6e6;
        color: #666;
        cursor: pointer;
        text-decoration: none;
      }
      
      .view-options {
        display: flex;
        align-items: center;
        gap: 15px;
      }
      
      .view-option {
        font-size: 14px;
        color: #666;
        padding: 5px 10px;
        border-radius: 20px;
        cursor: pointer;
      }
      
      .view-option.active {
        color: #4169e1;
        font-weight: 500;
      }
      
      .calendar-layout-btn {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: white;
        border: 1px solid #e6e6e6;
        color: #666;
        cursor: pointer;
      }
      
      /* Week view calendar grid */
      .calendar-grid-main {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        min-height: 700px;
      }
      
      .calendar-day {
        border-right: 1px solid #e6e6e6;
        border-bottom: 1px solid #e6e6e6;
        padding: 10px;
        position: relative;
      }
      
      .calendar-day-header {
        text-align: center;
        padding-bottom: 10px;
        border-bottom: 1px solid #e6e6e6;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 10px;
      }
      
      .day-number {
        font-weight: bold;
      }
      
      .today-indicator {
        display: inline-block;
        width: 24px;
        height: 24px;
        background-color: #4169e1;
        color: white;
        border-radius: 50%;
        line-height: 24px;
      }
      
      .time-slot {
        padding: 5px 0;
        border-bottom: 1px dotted #e6e6e6;
        font-size: 12px;
        color: #666;
        height: 30px;
        position: relative;
      }
      
      .time-slot:last-child {
        border-bottom: none;
      }
      
      /* Appointment styles */
      .appointment {
        position: absolute;
        border-radius: 8px;
        padding: 8px;
        font-size: 12px;
        width: 90%;
        z-index: 5;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
      }
      
      /* Appointment card styles matching the image */
      .appointment-blue {
        background-color: rgba(65, 105, 225, 0.2);
        border: 1px solid #4169e1;
      }
      
      .appointment-green {
        background-color: rgba(76, 175, 80, 0.2);
        border: 1px solid #4CAF50;
      }
      
      .appointment-purple {
        background-color: rgba(156, 39, 176, 0.2);
        border: 1px solid #9c27b0;
      }
      
      .appointment-yellow {
        background-color: rgba(255, 235, 59, 0.2);
        border: 1px solid #FFEB3B;
      }
      
      .appointment-red {
        background-color: rgba(244, 67, 54, 0.2);
        border: 1px solid #F44336;
      }
      
      .appointment-title {
        font-weight: 500;
        margin-bottom: 3px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
      
      .appointment-time {
        font-size: 11px;
        opacity: 0.8;
      }
      
      /* Legend section */
      .legend {
        display: flex;
        padding: 15px 20px;
        border-top: 1px solid #e6e6e6;
        justify-content: center;
        gap: 20px;
      }
      
      .legend-item {
        display: flex;
        align-items: center;
        font-size: 12px;
        color: #666;
      }
      
      .legend-color {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 5px;
      }
      
      .legend-blue { background-color: #4169e1; }
      .legend-green { background-color: #4CAF50; }
      .legend-purple { background-color: #9c27b0; }
      .legend-yellow { background-color: #FFEB3B; }
      .legend-red { background-color: #F44336; }
      
      /* Modal styles */
      .modal-header {
        background-color: #4169e1;
        color: white;
      }
      
      .modal-title {
        font-weight: 500;
      }
      
      .btn-close-white {
        filter: invert(1) grayscale(100%) brightness(200%);
      }
      
      .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
      }
      
      .registration-modal {
        width: 90%;
        max-width: 800px;
        height: 90vh;
        margin: 2% auto;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        overflow-y: auto;
      }
      
      .modal-header {
        padding: 1rem;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: #4169e1;
        color: white;
        border-radius: 8px 8px 0 0;
      }
      
      .modal-header h2 {
        margin: 0;
        font-size: 1.5rem;
      }
      
      .modal-body {
        padding: 1.5rem;
      }
      
      .close {
        font-size: 1.5rem;
        font-weight: bold;
        color: white;
        cursor: pointer;
        padding: 0.5rem;
      }
      
      .close:hover {
        color: #f0f0f0;
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
      
      @media (max-width: 768px) {
        .registration-modal {
          width: 95%;
          height: 95vh;
          margin: 1% auto;
        }
        
        .modal-header h2 {
          font-size: 1.2rem;
        }
      }
    </style>
</head>
<body>
  <div class="container">
    <header class="header">
      <h1 class="title">Appointment</h1>
      <button class="check-request-btn">
        <span>+</span>
        Check request
      </button>
    </header>
    
    <div class="toolbar">
      <div class="toolbar-right">
        <button class="continue-patient-btn" onclick="openRegistrationModal()">
          <i class="fas fa-user-plus"></i> Continue Patient
        </button>
      </div>
    </div>
    
    <div class="calendar-container">
      <div class="sidebar">
        <div class="sidebar-header">
          <div class="sidebar-title">Appointment Calendar</div>
          <div class="nav-arrows">
            <a href="?month=<?php echo $month-1; ?>&year=<?php echo $year; ?>&date=<?php echo $selectedDate; ?>" class="nav-btn nav-prev">&lt;</a>
            <a href="?month=<?php echo $month+1; ?>&year=<?php echo $year; ?>&date=<?php echo $selectedDate; ?>" class="nav-btn">&gt;</a>
          </div>
        </div>
        
        <div class="calendar-grid">
          <div class="weekdays">
            <?php
            $weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            foreach ($weekDays as $day) {
              echo '<div class="weekday">' . $day . '</div>';
            }
            ?>
          </div>
          
          <div class="days">
            <?php
            // Get the first day of the month
            $firstDay = mktime(0, 0, 0, $month, 1, $year);
            $daysInMonth = date('t', $firstDay);
            $dayOfWeek = date('w', $firstDay);
            
            // Adjust Sunday from 0 to 7
            if ($dayOfWeek == 0) $dayOfWeek = 7;
            
            // Add days from previous month
            $prevMonth = date('t', strtotime('-1 month', $firstDay));
            for ($i = $dayOfWeek - 1; $i > 0; $i--) {
              $prevMonthDate = date('Y-m-d', strtotime('-' . $i . ' days', $firstDay));
              $isSunday = date('w', strtotime($prevMonthDate)) == 0;
              
              if (!$isSunday) {
                echo '<div class="day other-month" data-date="' . $prevMonthDate . '">' . ($prevMonth - $i + 1) . '</div>';
              }
            }
            
            // Current month days
            for ($day = 1; $day <= $daysInMonth; $day++) {
              $currentDate = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
              $isSunday = date('w', strtotime($currentDate)) == 0;
              
              if (!$isSunday) {
                $isToday = $currentDate === date('Y-m-d');
                $isSelected = $currentDate === $selectedDate;
                $hasAppointments = isset($appointments[$currentDate]) && count($appointments[$currentDate]) > 0;
                
                $classes = 'day';
                if ($isToday) $classes .= ' active';
                if ($isSelected) $classes .= ' selected';
                if ($hasAppointments) $classes .= ' has-appointments';
                
                echo '<div class="' . $classes . '" data-date="' . $currentDate . '">' . $day . '</div>';
              }
            }
            
            // Add days from next month
            $lastDay = mktime(0, 0, 0, $month, $daysInMonth, $year);
            $remainingCells = 6 * 6 - ($dayOfWeek - 1 + $daysInMonth); // 6 rows x 6 columns (excluding Sunday)
            
            for ($i = 1; $i <= $remainingCells; $i++) {
              $nextMonthDate = date('Y-m-d', strtotime('+' . $i . ' days', $lastDay));
              $isSunday = date('w', strtotime($nextMonthDate)) == 0;
              
              if (!$isSunday) {
                echo '<div class="day other-month" data-date="' . $nextMonthDate . '">' . $i . '</div>';
              }
            }
            ?>
          </div>
        </div>
      </div>
      
      <div class="main-calendar">
        <!-- <div class="main-header">
          <div class="month-nav">
            <div class="month-title">
              <?php
              // Display selected date or current month
              if (isset($_GET['date'])) {
                echo date('F d, Y', strtotime($selectedDate));
              } else {
                echo $monthName . ' ' . $year;
              }
              ?>
            </div>
            <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>&date=<?php echo date('Y-m-d'); ?>" class="today-btn">Today</a>
            <div class="month-nav-arrows">
              <a href="?month=<?php echo $month-1; ?>&year=<?php echo $year; ?>&date=<?php echo $selectedDate; ?>" class="month-nav-btn">&lt;</a>
              <a href="?month=<?php echo $month+1; ?>&year=<?php echo $year; ?>&date=<?php echo $selectedDate; ?>" class="month-nav-btn">&gt;</a>
            </div>
          </div>
          
          <div class="view-options">
            <div class="view-option">None</div>
            <div class="view-option active">Priority</div>
            <div class="view-option">Deadline</div>
            <button class="calendar-layout-btn">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
              </svg>
            </button>
          </div>
        </div> -->
        
        <div class="calendar-grid-main">
          <?php
          if (isset($_GET['date'])) {
            // Single day view - show detailed appointments for selected date
            echo '<div class="calendar-day" style="grid-column: span 7; display: flex; flex-direction: column;">';
            echo '<div class="calendar-day-header" style="text-align: left; padding-left: 20px;">';
            echo date('l, F j, Y', strtotime($selectedDate));
            echo '</div>';
            
            echo '<div style="display: flex; flex-direction: column; gap: 8px; padding: 10px 20px;">';
            
            // Check if there are any appointments for this date
            if (isset($appointments[$selectedDate]) && count($appointments[$selectedDate]) > 0) {
              // Sort appointments by time
              usort($appointments[$selectedDate], function($a, $b) {
                return strcmp($a['appointment_time'], $b['appointment_time']);
              });
              
              // Display each appointment
              foreach ($appointments[$selectedDate] as $appointment) {
                // Determine color class based on specialty
                $colorClass = 'appointment-blue';
                $specialty = strtolower($appointment['preferred_specialty'] ?? '');
                
                if (strpos($specialty, 'cardiology') !== false) {
                  $colorClass = 'appointment-red';
                } elseif (strpos($specialty, 'pediatrics') !== false) {
                  $colorClass = 'appointment-green';
                } elseif (strpos($specialty, 'neurology') !== false) {
                  $colorClass = 'appointment-purple';
                } elseif (strpos($specialty, 'dental') !== false) {
                  $colorClass = 'appointment-yellow';
                }
                
                echo '<div class="' . $colorClass . '" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-radius: 8px; cursor: pointer;"
                          data-bs-toggle="modal" 
                          data-bs-target="#appointmentModal"
                          data-patient="' . htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']) . '"
                          data-time="' . htmlspecialchars($appointment['appointment_time']) . '"
                          data-date="' . $selectedDate . '"
                          data-specialty="' . htmlspecialchars($appointment['preferred_specialty']) . '"
                          data-contact="' . htmlspecialchars($appointment['contact_number']) . '"
                          data-reason="' . htmlspecialchars($appointment['reason_for_appointment']) . '">';
                
                echo '<div style="display: flex; align-items: center; gap: 15px;">';
                echo '<div style="min-width: 60px; font-weight: 500;">' . $appointment['appointment_time'] . '</div>';
                echo '<div>' . htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']) . '</div>';
                echo '</div>';
                
                echo '<div style="display: flex; align-items: center; gap: 15px;">';
                echo '<div>' . htmlspecialchars($appointment['preferred_specialty']) . '</div>';
                echo '<div>' . htmlspecialchars($appointment['contact_number']) . '</div>';
                echo '</div>';
                
                echo '</div>';
              }
            } else {
              echo '<div style="text-align: center; padding: 30px; color: #6c757d;">No appointments scheduled for this date</div>';
            }
            
            echo '</div>';
            echo '</div>';
            
          } else {
            // Week view - display the standard calendar grid
            // First row: Time column and days of week
            echo '<div class="calendar-day" style="width: 80px;"><div class="calendar-day-header">GMT+8</div></div>';
            
            // Get the current week
            $startOfWeek = date('Y-m-d', strtotime('this week monday'));
            
            // Create the day columns headers (excluding Sunday)
            for ($i = 0; $i < 6; $i++) {
              $currentDate = date('Y-m-d', strtotime($startOfWeek . " +$i days"));
              $dayName = date('D', strtotime($currentDate));
              $dayNum = date('j', strtotime($currentDate));
              $isToday = date('Y-m-d') === $currentDate;
              
              echo '<div class="calendar-day">';
              echo '<div class="calendar-day-header">';
              if ($isToday) {
                echo $dayName . ' <span class="today-indicator">' . $dayNum . '</span>';
              } else {
                echo $dayName . ' <span class="day-number">' . $dayNum . '</span>';
              }
              echo '</div>';
              echo '</div>';
            }
            
            // Time slots and appointments
            $timeSlots = [
              '09:00' => '09 AM', 
              '10:00' => '10 AM', 
              '11:00' => '11 AM', 
              '12:00' => '12 PM',
              '13:00' => '01 PM',
              '14:00' => '02 PM',
              '15:00' => '03 PM',
              '16:00' => '04 PM',
              '17:00' => '05 PM'
            ];
            
            foreach ($timeSlots as $timeValue => $timeDisplay) {
              // Time label
              echo '<div class="calendar-day" style="width: 80px;">';
              echo '<div class="time-slot">' . $timeDisplay . '</div>';
              echo '</div>';
              
              // Day cells for this time
              for ($i = 0; $i < 6; $i++) {
                $currentDate = date('Y-m-d', strtotime($startOfWeek . " +$i days"));
                echo '<div class="calendar-day">';
                
                // Check for appointments in this time slot
                $appointmentsInSlot = [];
                if (isset($appointments[$currentDate])) {
                  foreach ($appointments[$currentDate] as $appointment) {
                    $appointmentHour = substr($appointment['appointment_time'], 0, 2);
                    $slotHour = substr($timeValue, 0, 2);
                    
                    if ($appointmentHour == $slotHour) {
                      $appointmentsInSlot[] = $appointment;
                    }
                  }
                }
                
                // Display appointments
                foreach ($appointmentsInSlot as $index => $appointment) {
                  // Only show max 2 appointments per slot
                  if ($index >= 2) {
                    echo '<div class="small text-center mt-1">+' . (count($appointmentsInSlot) - 2) . ' more</div>';
                    break;
                  }
                  
                  // Determine color class
                  $colorClass = 'appointment-blue';
                  $specialty = strtolower($appointment['preferred_specialty'] ?? '');
                  
                  if (strpos($specialty, 'cardiology') !== false) {
                    $colorClass = 'appointment-red';
                  } elseif (strpos($specialty, 'pediatrics') !== false) {
                    $colorClass = 'appointment-green';
                  } elseif (strpos($specialty, 'neurology') !== false) {
                    $colorClass = 'appointment-purple';
                  } elseif (strpos($specialty, 'dental') !== false) {
                    $colorClass = 'appointment-yellow';
                  }
                  
                  // Create appointment box
                  $top = ((int)substr($appointment['appointment_time'], 3, 2)) * 60 / 60;
                  
                  echo '<div class="' . $colorClass . '" 
                             style="position: relative; margin: 2px 0; padding: 4px 6px; border-radius: 4px; cursor: pointer; font-size: 11px;"
                             data-bs-toggle="modal" 
                             data-bs-target="#appointmentModal"
                             data-patient="' . htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']) . '"
                             data-time="' . htmlspecialchars($appointment['appointment_time']) . '"
                             data-date="' . $currentDate . '"
                             data-specialty="' . htmlspecialchars($appointment['preferred_specialty']) . '"
                             data-contact="' . htmlspecialchars($appointment['contact_number']) . '"
                             data-reason="' . htmlspecialchars($appointment['reason_for_appointment']) . '">';
                  
                  // Compute patient name (shortened if needed)
                  $patientName = $appointment['patient_first_name'] . ' ' . $appointment['patient_last_name'];
                  if (strlen($patientName) > 12) {
                    $patientName = substr($patientName, 0, 10) . '...';
                  }
                  
                  echo '<div style="font-weight: 500;">' . htmlspecialchars($patientName) . '</div>';
                  echo '<div style="font-size: 10px;">' . $appointment['appointment_time'] . '</div>';
                  
                  echo '</div>';
                }
                
                echo '</div>';
              }
            }
          }
          ?>
        </div>
        
        <div class="legend">
          <div class="legend-item">
            <div class="legend-color legend-blue"></div>
            <span>None</span>
          </div>
          <div class="legend-item">
            <div class="legend-color legend-red"></div>
            <span>Priority</span>
          </div>
          <div class="legend-item">
            <div class="legend-color legend-yellow"></div>
            <span>Deadline</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Appointment Details Modal -->
  <div class="modal fade" id="appointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-calendar-check me-2"></i>Appointment Details</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="fw-bold"><i class="fas fa-calendar me-2"></i>Date:</label>
                <p id="appointmentDate"></p>
              </div>
              <div class="mb-3">
                <label class="fw-bold"><i class="fas fa-clock me-2"></i>Time:</label>
                <p id="appointmentTime"></p>
              </div>
              <div class="mb-3">
                <label class="fw-bold"><i class="fas fa-user me-2"></i>Patient Name:</label>
                <p id="patientName"></p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="fw-bold"><i class="fas fa-phone me-2"></i>Contact Number:</label>
                <p id="contactNumber"></p>
              </div>
              <div class="mb-3">
                <label class="fw-bold"><i class="fas fa-stethoscope me-2"></i>Preferred Specialty:</label>
                <p id="preferredSpecialty"></p>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="fw-bold"><i class="fas fa-comment-medical me-2"></i>Reason for Visit:</label>
            <p id="reason"></p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Replace the existing registration modal with this updated version -->
  <div id="registrationModal" class="modal">
    <div class="modal-content registration-modal">
        <div class="modal-header">
            <h2>Patient Registration</h2>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <div class="appointment-summary">
                <h3 class="text-lg font-bold mb-2">Appointment Details</h3>
                <p>Date: <span class="appointment-date"><?php echo date('l, F j, Y'); ?></span></p>
            </div>

            <h2 class="text-xl font-bold mb-4 text-center">Patient Information</h2>
            <form id="registrationForm">
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
                        <div class="grid grid-cols-3 gap-2">
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Prevent caching issues
    if (window.history.replaceState) {
      window.history.replaceState(null, null, window.location.href);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
      console.log("Admin calendar loaded successfully");
      
      // Day selection functionality
      const days = document.querySelectorAll('.day');
      days.forEach(day => {
        day.addEventListener('click', function() {
          const date = this.getAttribute('data-date');
          if (date) {
            window.location.href = 'admin_calendar.php?date=' + date + '&month=<?php echo $month; ?>&year=<?php echo $year; ?>';
          }
        });
      });
      
      const modal = document.getElementById('appointmentModal');
      const appointments = document.querySelectorAll('.appointment');
      
      if (appointments.length > 0) {
        console.log(`Found ${appointments.length} appointments to display`);
      } else {
        console.log("No appointments found for this view");
      }
      
      appointments.forEach(appointment => {
        appointment.addEventListener('click', function() {
          // Format date for display
          const appointmentDate = this.dataset.date;
          const dateParts = appointmentDate.split('-');
          const formattedDate = new Date(dateParts[0], dateParts[1]-1, dateParts[2])
            .toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
          
          document.getElementById('appointmentDate').textContent = formattedDate;
          document.getElementById('appointmentTime').textContent = this.dataset.time;
          document.getElementById('patientName').textContent = this.dataset.patient;
          document.getElementById('contactNumber').textContent = this.dataset.contact;
          document.getElementById('preferredSpecialty').textContent = this.dataset.specialty;
          document.getElementById('reason').textContent = this.dataset.reason;
          
          console.log(`Displaying details for appointment on ${formattedDate} at ${this.dataset.time}`);
        });
      });
    });

    function openRegistrationModal() {
      document.getElementById('registrationModal').style.display = 'block';
    }

    // Close modal when clicking the X button
    document.querySelector('.close').addEventListener('click', function() {
      document.getElementById('registrationModal').style.display = 'none';
    });

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
      const modal = document.getElementById('registrationModal');
      if (event.target === modal) {
        modal.style.display = 'none';
      }
    });

    // Handle form submission
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        const formData = new FormData(this);
        
        // Send form data to server
        fetch('admin_calendar.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close modal
                document.getElementById('registrationModal').style.display = 'none';
                // Show success message
                alert('Registration successful!');
                // Refresh the calendar
                location.reload();
            } else {
                alert(data.message || 'Registration failed. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    });
  </script>
</body>
</html>