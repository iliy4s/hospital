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

// Log successful access
error_log("Admin calendar accessed by: " . ($_SESSION['admin_username'] ?? 'unknown'));

// Get the current month and year
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

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
      /* Calendar styling */
:root {
    --primary: #3b6af5;
    --primary-light: #5880f7;
    --secondary: #88bbcc;
    --light-bg: #f8f9fa;
    --accent: #7047d1;
    --accent-light: #9a7ae8;
    --success: #28a745;
    --warning: #ffc107;
    --danger: #dc3545;
}

body {
    background-color: #f0f4fa;
    font-family: 'Poppins', sans-serif;
    color: #333;
}

.calendar-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    overflow: hidden;
    margin-bottom: 30px;
}

.calendar-header {
    background: linear-gradient(100deg, var(--primary), var(--primary-light));
    color: white;
    padding: 24px;
    text-align: center;
    position: relative;
}

.month-title {
    font-size: 28px;
    font-weight: 600;
    margin: 0;
    letter-spacing: 0.5px;
}

.calendar-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    font-size: 16px;
    color: white;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.2s;
}

.calendar-nav:hover {
    background: rgba(255,255,255,0.35);
    color: white;
    transform: translateY(-50%) scale(1.05);
}

.calendar-nav.prev {
    left: 24px;
}

.calendar-nav.next {
    right: 24px;
}

.calendar-body {
    padding: 24px;
}

.weekdays {
    display: flex;
    margin-bottom: 16px;
    border-bottom: 1px solid #f0f0f0;
    padding-bottom: 12px;
}

.weekday {
    flex: 1;
    text-align: center;
    font-weight: 600;
    color: #8c9db5;
    padding: 10px;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.days-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 12px;
}

.day-cell {
    aspect-ratio: 1;
    border-radius: 12px;
    background: #f9fafc;
    padding: 12px;
    position: relative;
    transition: all 0.25s;
    border: 1px solid #eaedf2;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    overflow: hidden;
}

.day-cell:hover {
    background: #f0f4f9;
    transform: translateY(-3px);
    box-shadow: 0 6px 14px rgba(0,0,0,0.08);
    z-index: 2;
}

.day-cell.empty {
    background: transparent;
    border: none;
    box-shadow: none;
}

.day-cell.today {
    background: #e8f0ff;
    border: 1px solid #c5d7f2;
}

.day-number {
    font-weight: 600;
    font-size: 16px;
    color: #444;
    margin-bottom: 8px;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.today .day-number {
    background: var(--primary);
    color: white;
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(59, 106, 245, 0.3);
}

.appointment-count {
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--accent);
    color: white;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(112, 71, 209, 0.3);
}

.appointments-container {
    margin-top: 10px;
    max-height: calc(100% - 40px);
    overflow-y: auto;
}

.appointment {
    background: linear-gradient(to right, #e8f4ff, #f0f7ff);
    border-left: 3px solid var(--primary);
    border-radius: 8px;
    padding: 8px 10px;
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.appointment:hover {
    background: linear-gradient(to right, #d0e6ff, #e8f4ff);
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 3px 8px rgba(0,0,0,0.08);
}

.appointment i {
    color: var(--primary);
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 5px;
    height: 5px;
}

::-webkit-scrollbar-track {
    background: #f5f5f5;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: #c5d0e0;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: #a8b5c7;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .days-grid {
        gap: 8px;
    }
    
    .day-cell {
        padding: 8px;
    }
    
    .day-number {
        font-size: 14px;
    }
    
    .appointment {
        font-size: 11px;
        padding: 6px 8px;
    }
}

/* Additional styling for the admin header */
.admin-header {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}

.admin-welcome {
    color: #6c757d;
    font-size: 14px;
}

.info-box {
    background: white;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

/* Modal styling improvements */
.modal-header {
    background: linear-gradient(100deg, var(--primary), var(--primary-light));
    color: white;
}

.modal-content {
    border-radius: 12px;
    overflow: hidden;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Admin Header -->
        <div class="admin-header p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0"><i class="fas fa-user-md me-2"></i> Admin Dashboard</h2>
                    <p class="admin-welcome mb-0">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
                </div>
                <div>
                    <a href="admin_login.php?logout=1" class="btn btn-light">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
        
        <div class="info-box mb-4">
            <h5 class="mb-2"><i class="fas fa-info-circle me-2"></i> Month Overview</h5>
            <p class="mb-0">Showing appointments for <strong><?php echo $monthName . ' ' . $year; ?></strong>. 
               Click on any appointment to view details.</p>
        </div>
        
        <!-- Calendar Container -->
        <div class="calendar-container">
            <!-- Calendar Header -->
            <div class="calendar-header">
                <a href="?month=<?php echo $month-1; ?>&year=<?php echo $year; ?>" class="calendar-nav prev">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <h2 class="month-title"><?php echo $monthName . ' ' . $year; ?></h2>
                <a href="?month=<?php echo $month+1; ?>&year=<?php echo $year; ?>" class="calendar-nav next">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            
            <!-- Calendar Body -->
            <div class="calendar-body">
                <!-- Weekdays Header -->
                <div class="weekdays">
                    <div class="weekday">Sun</div>
                    <div class="weekday">Mon</div>
                    <div class="weekday">Tue</div>
                    <div class="weekday">Wed</div>
                    <div class="weekday">Thu</div>
                    <div class="weekday">Fri</div>
                    <div class="weekday">Sat</div>
                </div>
                
                <!-- Days Grid -->
                <div class="days-grid">
                    <?php
                    // Empty cells for days before the start of the month
                    for ($i = 0; $i < $dayOfWeek; $i++) {
                        echo '<div class="day-cell empty"></div>';
                    }
                    
                    // Days of the month
                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $date = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
                        $isToday = (date('Y-m-d') == $date) ? ' today' : '';
                        
                        echo '<div class="day-cell' . $isToday . '">';
                        echo '<div class="day-number">' . $day . '</div>';
                        
                        // Display appointments for this day
                        if (isset($appointments[$date])) {
                            $count = count($appointments[$date]);
                            echo '<div class="appointment-count">' . $count . '</div>';
                            
                            echo '<div class="appointments-container">';
                            foreach ($appointments[$date] as $appointment) {
                                $patientName = htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']);
                                $time = htmlspecialchars($appointment['appointment_time']);
                                $reason = htmlspecialchars($appointment['reason_for_appointment']);
                                $contact = htmlspecialchars($appointment['contact_number']);
                                $email = htmlspecialchars($appointment['email']);
                                $specialty = htmlspecialchars($appointment['preferred_specialty'] ?: 'Not specified');
                                
                                echo '<div class="appointment" 
                                      data-bs-toggle="modal" 
                                      data-bs-target="#appointmentModal" 
                                      data-patient="' . $patientName . '"
                                      data-time="' . $time . '"
                                      data-reason="' . $reason . '"
                                      data-contact="' . $contact . '"
                                      data-specialty="' . $specialty . '"
                                      data-date="' . $date . '">';
                                echo '<i class="far fa-clock me-1"></i> ' . 
                                     $time . ' - ' . 
                                     $patientName;
                                echo '</div>';
                            }
                            echo '</div>';
                        }
                        
                        echo '</div>';
                    }
                    
                    // Empty cells for days after the end of the month
                    $totalCells = $dayOfWeek + $daysInMonth;
                    $remainingCells = 7 - ($totalCells % 7);
                    if ($remainingCells < 7) {
                        for ($i = 0; $i < $remainingCells; $i++) {
                            echo '<div class="day-cell empty"></div>';
                        }
                    }
                    ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent caching issues
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log("Admin calendar loaded successfully");
            
            const modal = document.getElementById('appointmentModal');
            const appointments = document.querySelectorAll('.appointment');
            
            if (appointments.length > 0) {
                console.log(`Found ${appointments.length} appointments to display`);
            } else {
                console.log("No appointments found for this month");
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
    </script>
</body>
</html>