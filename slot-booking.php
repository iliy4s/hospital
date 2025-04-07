<?php
session_start();
require 'connect.php';
// Initialize variables
$today = new DateTime('now');
$todayFormatted = $today->format('Y-m-d');

// Process form submission if applicable
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['selected_date']) && isset($_POST['selected_time'])) {
        $selectedDate = $_POST['selected_date'];
        $selectedTime = $_POST['selected_time'];
        
        // Standardize time format to prevent inconsistencies
        $selectedTime = standardizeTimeFormat($selectedTime);
        
        // Check if the selected time is 11:00 AM (which should be disabled)
        if (preg_match('/^11:00\s*AM$/i', $selectedTime)) {
            $_SESSION['booking_error'] = "Sorry, appointments at 11:00 AM are not available. Please select another time.";
            error_log("Booking attempt for disabled 11:00 AM slot: Date: $selectedDate");
            header("Location: slot-booking.php");
            exit;
        }
        
        // Check if the selected date and time are in the past
        $selectedDateTime = new DateTime($selectedDate . ' ' . $selectedTime);
        $currentDateTime = new DateTime();
        $currentDateTime->modify('+4 minutes'); // Reduced from 15 to 4 minutes buffer
        
        if ($selectedDateTime < $currentDateTime) {
            $_SESSION['booking_error'] = "Sorry, you cannot book appointments in the past or too close to the current time. Please select a future time slot.";
            error_log("Booking attempt for past time: Date: $selectedDate, Time: $selectedTime");
            header("Location: slot-booking.php");
            exit;
        }
        
        // Check if slot is already booked before proceeding - IMPROVED QUERY with error handling
        try {
            $checkQuery = "SELECT * FROM appointments 
                          WHERE appointment_date = ? 
                          AND appointment_time = ?";  // Removed status filter to check ALL appointments
            $checkStmt = $conn->prepare($checkQuery);
            
            if (!$checkStmt) {
                throw new Exception("Database prepare error: " . $conn->error);
            }
            
            $checkStmt->bind_param("ss", $selectedDate, $selectedTime);
            
            if (!$checkStmt->execute()) {
                throw new Exception("Database execute error: " . $checkStmt->error);
            }
            
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                // Slot already booked - redirect back with error
                $_SESSION['booking_error'] = "Sorry, this time slot has already been booked. Please select another time.";
                error_log("Booking attempt for already booked slot: Date: $selectedDate, Time: $selectedTime");
                header("Location: slot-booking.php");
                exit;
            }
            
            // Store in session and redirect to the form page
            $_SESSION['appointment_date'] = $selectedDate;
            $_SESSION['appointment_time'] = $selectedTime;
            header("Location: registration-form.php");
            exit;
        } catch (Exception $e) {
            // Log the error and show a user-friendly message
            error_log("Error in slot booking: " . $e->getMessage());
            $_SESSION['booking_error'] = "We encountered a technical issue. Please try again or contact support.";
            header("Location: slot-booking.php");
            exit;
        }
    }
}

// Function to standardize time format (e.g., "10:30 AM" vs "10:30AM")
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

// Fetch booked slots from database with error handling
$bookedSlots = [];
try {
    $query = "SELECT appointment_date, appointment_time FROM appointments"; // Get ALL appointments regardless of status
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Database query error: " . $conn->error);
    }
    
    while ($row = $result->fetch_assoc()) {
        // Standardize time format for consistency
        $standardizedTime = standardizeTimeFormat($row['appointment_time']);
        // Store the exact format that will be used for comparison in JavaScript
        $bookedSlots[] = $row['appointment_date'] . ' ' . $standardizedTime;
    }
    
    // Debug - Log the number of booked slots found and the specific slots
    error_log("Found " . count($bookedSlots) . " booked time slots");
    foreach ($bookedSlots as $slot) {
        error_log("Booked slot: " . $slot);
    }
} catch (Exception $e) {
    // Log the error but continue with empty booked slots
    error_log("Error fetching booked slots: " . $e->getMessage());
    // Initialize empty array to prevent JavaScript errors
    $bookedSlots = [];
}

// Add a cache-busting timestamp to prevent browser caching of booked slots
$cacheBuster = time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>Dr. Kiran Hospitals - Schedule Appointment</title>
 <style>
  :root {
  /* Primary palette - Medical theme with soothing blues and teals */
  --primary-color: #2b86c5;
  --primary-dark: #1a5f8d;
  --primary-light: #e1f2fd;
  --primary-gradient: linear-gradient(135deg, #2b86c5, #36a3dc);
  
  /* Secondary palette */
  --secondary-color: #7d68de;
  --secondary-dark: #5a48c2;
  --secondary-light: #f0edff;
  --secondary-gradient: linear-gradient(135deg, #7d68de, #9a8aec);
  
  /* Accent colors */
  --accent-color: #1db895;
  --accent-dark: #0a9b7c;
  --accent-light: #e6f9f5;
  --accent-gradient: linear-gradient(135deg, #1db895, #28d9b1);
  
  /* Status colors */
  --warning-color: #f7b055;
  --error-color: #f25757;
  
  /* Neutrals */
  --text-dark: #2d3748;
  --text-medium: #596577;
  --text-light: #8896ab;
  --border-color: #e4eaf2;
  --background-light: #f5faff;
  --white: #ffffff;
  
  /* Shadows */
  --shadow-sm: 0 2px 4px rgba(30, 80, 150, 0.08);
  --shadow-md: 0 4px 12px rgba(30, 80, 150, 0.12);
  --shadow-lg: 0 8px 24px rgba(30, 80, 150, 0.15);
  
  /* Card effects */
  --card-border-radius: 16px;
  --button-border-radius: 12px;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  background: linear-gradient(135deg, #e6f3fa, #f0f8ff);
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
  margin: 0;
  color: var(--text-dark);
}

.modal {
  background-color: var(--white);
  border-radius: var(--card-border-radius);
  width: 90%;
  max-width: 600px;
  box-shadow: var(--shadow-lg);
  position: relative;
  padding: 32px;
  border: 1px solid rgba(230, 240, 255, 0.5);
}

.close-button {
  position: absolute;
  top: 18px;
  right: 18px;
  font-size: 24px;
  cursor: pointer;
  background: #f5f7fa;
  border: none;
  color: var(--text-medium);
  text-decoration: none;
  transition: all 0.2s;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-button:hover {
  background-color: #f0f3f9;
  color: var(--error-color);
  box-shadow: var(--shadow-sm);
}

h1 {
  text-align: center;
  font-size: 28px;
  margin-top: 10px;
  margin-bottom: 32px;
  color: var(--primary-dark);
  font-weight: 600;
  position: relative;
}

h1:after {
  content: "";
  position: absolute;
  bottom: -10px;
  left: 50%;
  transform: translateX(-50%);
  width: 80px;
  height: 3px;
  background: var(--primary-gradient);
  border-radius: 3px;
}

.calendar-container {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 40px;
  position: relative;
}

.nav-button {
  width: 40px;
  height: 40px;
  border: none;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--white);
  color: var(--primary-color);
  font-size: 22px;
  cursor: pointer;
  margin: 0 10px;
  position: absolute;
  z-index: 10;
  transition: all 0.3s;
  box-shadow: var(--shadow-sm);
}

.nav-button:hover {
  background-color: var(--primary-light);
  color: var(--primary-dark);
  box-shadow: var(--shadow-md);
  transform: scale(1.05);
}

.nav-button.prev {
  left: -8px;
}

.nav-button.next {
  right: -8px;
}

.calendar {
  border: none;
  border-radius: var(--card-border-radius);
  overflow: hidden;
  width: 100%;
  max-width: 460px;
  background-color: var(--background-light);
  padding: 15px 12px;
  box-shadow: var(--shadow-md);
}

.month-year {
  text-align: center;
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 12px;
  color: var(--primary-dark);
  padding: 5px 0;
}

.weekdays {
  display: flex;
  border-bottom: 1px solid var(--border-color);
  background-color: rgba(230, 240, 255, 0.4);
  border-radius: 8px 8px 0 0;
}

.weekday {
  flex: 1;
  text-align: center;
  padding: 12px 0;
  font-weight: 600;
  color: var(--text-medium);
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.dates {
  display: flex;
  background-color: var(--white);
  height: 95px;
  border-radius: 0 0 8px 8px;
}

.date {
  flex: 1;
  text-align: center;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  height: 100%;
  position: relative;
  transition: all 0.3s;
  border-radius: 12px;
  margin: 2px;
}

.date:not(.disabled):hover {
  background-color: var(--primary-light);
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}

.date.selected {
  background: var(--primary-gradient);
  box-shadow: var(--shadow-md);
  transform: translateY(-3px);
}

.date.disabled {
  color: var(--text-light);
  cursor: not-allowed;
  opacity: 0.6;
}

.date-number {
  font-size: 30px;
  font-weight: 700;
  line-height: 1.2;
  position: relative;
  z-index: 1;
}

.date.selected .date-number,
.date.selected .date-month {
  color: var(--white);
}

.date-month {
  font-size: 14px;
  margin-top: 4px;
  font-weight: 500;
}

.date:not(.disabled):not(.selected) .date-number {
  color: var(--primary-color);
}

.date:not(.disabled):not(.selected) .date-month {
  color: var(--primary-color);
}

.date.disabled .date-number,
.date.disabled .date-month {
  color: var(--text-light);
}

.today-indicator {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: var(--error-color);
  box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.6);
}

h2 {
  text-align: center;
  font-size: 22px;
  margin-top: 5px;
  margin-bottom: 22px;
  color: var(--secondary-dark);
  font-weight: 600;
}

.time-period-selection {
  display: flex;
  justify-content: center;
  gap: 16px;
  margin-bottom: 26px;
}

.time-period-button {
  padding: 12px 28px;
  border: none;
  border-radius: var(--button-border-radius);
  background-color: var(--secondary-light);
  color: var(--secondary-dark);
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  min-width: 140px;
  box-shadow: var(--shadow-sm);
}

.time-period-button:hover {
  background-color: #e7e2ff;
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.time-period-button.selected {
  background: var(--secondary-gradient);
  color: var(--white);
  box-shadow: var(--shadow-md);
}

.time-slots-container {
  margin-bottom: 30px;
}

.time-slots {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 30px;
  max-height: 300px;
  overflow-y: auto;
  padding-right: 8px;
  padding-bottom: 4px;
}

.time-slot {
  padding: 12px 8px;
  text-align: center;
  border: none;
  border-radius: var(--button-border-radius);
  cursor: pointer;
  color: var(--primary-dark);
  font-size: 15px;
  font-weight: 600;
  transition: all 0.3s;
  box-shadow: var(--shadow-sm);
  background-color: var(--primary-light);
}

.time-slot:hover {
  background-color: #d0eafc;
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.time-slot.selected {
  background: var(--primary-gradient);
  color: var(--white);
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.time-slot.disabled {
  background-color: #f3f5f9;
  color: var(--text-light);
  cursor: not-allowed;
  pointer-events: none;
  box-shadow: none;
  opacity: 0.7;
}

.no-slots-message {
  text-align: center;
  color: var(--text-medium);
  font-style: italic;
  padding: 20px;
  background-color: #f0f5fa;
  border-radius: var(--button-border-radius);
  box-shadow: var(--shadow-sm);
  border-left: 4px solid var(--primary-color);
}

.submit-button {
  display: block;
  width: 100%;
  background: var(--accent-gradient);
  color: var(--white);
  padding: 16px;
  border: none;
  border-radius: var(--button-border-radius);
  font-size: 17px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  box-shadow: var(--shadow-md);
  position: relative;
  overflow: hidden;
}

.submit-button:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-3px);
}

.submit-button:before {
  content: "";
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(255, 255, 255, 0.2),
    transparent
  );
  transition: 0.5s;
}

.submit-button:hover:before {
  left: 100%;
}

.submit-button:disabled {
  background: #c6d2e0;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.footer {
  text-align: center;
  padding-top: 25px;
  margin-top: 25px;
  border-top: 1px solid var(--border-color);
  color: var(--text-medium);
  display: flex;
  align-items: center;
  justify-content: center;
}

.logo {
  margin-left: 10px;
  font-weight: 600;
  color: var(--primary-dark);
  position: relative;
}

.logo:before {
  content: "•";
  position: absolute;
  left: -12px;
  color: var(--accent-color);
  font-size: 20px;
}

/* Custom scrollbar for time slots */
.time-slots::-webkit-scrollbar {
  width: 8px;
}

.time-slots::-webkit-scrollbar-track {
  background-color: #edf2f7;
  border-radius: 8px;
}

.time-slots::-webkit-scrollbar-thumb {
  background-color: var(--secondary-light);
  border-radius: 8px;
  border: 2px solid #edf2f7;
}

.time-slots::-webkit-scrollbar-thumb:hover {
  background-color: var(--secondary-color);
}

/* Add subtle animation effects */
@keyframes pulse {
  0% { box-shadow: 0 0 0 0 rgba(43, 134, 197, 0.4); }
  70% { box-shadow: 0 0 0 10px rgba(43, 134, 197, 0); }
  100% { box-shadow: 0 0 0 0 rgba(43, 134, 197, 0); }
}

.date.selected {
  animation: pulse 2s infinite;
}

/* Additional responsive touches */
@media (max-width: 480px) {
  .time-slots {
    grid-template-columns: repeat(3, 1fr);
  }
  
  .modal {
    padding: 24px 18px;
  }
  
  h1 {
    font-size: 24px;
  }
  
  h2 {
    font-size: 20px;
  }
}
  </style>
</head>
<body>
  <div class="modal">
    <button class="close-button" onclick="window.location.href='index.php';">×</button>
    <h1>What time works best for a quick call?</h1>
    
    <?php if (isset($_SESSION['booking_error'])): ?>
      <div style="background-color: #ffebee; color: #c62828; padding: 10px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #c62828;">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['booking_error']; ?>
      </div>
      <?php unset($_SESSION['booking_error']); ?>
    <?php endif; ?>
    
    <form id="scheduleForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
      <input type="hidden" name="selected_date" id="selected_date_input">
      <input type="hidden" name="selected_time" id="selected_time_input">
      <input type="hidden" name="cache_buster" value="<?php echo $cacheBuster; ?>">
      
      <div class="calendar-container">
        <button type="button" class="nav-button prev" onclick="navigateWeek(-1)">‹</button>
        
        <div class="calendar">
          <div class="month-year" id="month-year-display">March 2025</div>
          <div class="weekdays">
            <div class="weekday">Mon</div>
            <div class="weekday">Tue</div>
            <div class="weekday">Wed</div>
            <div class="weekday">Thu</div>
            <div class="weekday">Fri</div>
            <div class="weekday">Sat</div>
          </div>
          
          <div class="dates" id="dates-container">
            <!-- Calendar dates will be generated by JavaScript -->
          </div>
        </div>
        
        <button type="button" class="nav-button next" onclick="navigateWeek(1)">›</button>
      </div>
      
      <h2>Select appointment time</h2>
      
      <div class="time-period-selection">
        <button type="button" id="morning-button" class="time-period-button">Morning</button>
        <button type="button" id="evening-button" class="time-period-button">Evening</button>
      </div>
      
      <div class="time-slots-container">
        <div class="time-slots" id="time-slots-container">
          <!-- Time slots will be generated by JavaScript -->
        </div>
        <div class="no-slots-message" id="no-slots-message">
          No available time slots for this date and time period.
        </div>
      </div>
      
      <button type="submit" class="submit-button" id="continue-button" disabled >Continue to Patient Details</button>
      
      <div class="footer">
        <span class="logo">Dr. Kiran Hospitals</span>
      </div>
    </form>
  </div>

  <script>
    // Cache buster to prevent stale data
    const cacheBuster = '<?php echo $cacheBuster; ?>';
    
    const today = new Date(<?php echo date('Y'); ?>, <?php echo date('n')-1; ?>, <?php echo date('j'); ?>);
    let currentWeekStart = new Date(today);
    let selectedDate = new Date(today);
    let selectedTimeSlot = null;
    let selectedTimePeriod = null; // 'morning' or 'evening'
    
    // Get booked slots from PHP and format check
    const bookedSlots = <?php echo json_encode($bookedSlots); ?>;
    console.log("Booked slots:", bookedSlots); // Debug
    
    // Adjust to the start of the week (Monday)
    const dayOfWeek = today.getDay(); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
    const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek; // If Sunday, go back 6 days, otherwise adjust to Monday
    currentWeekStart.setDate(today.getDate() + diff);
    
    // Format options for displaying dates
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    const fullMonthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    
    // Time slot generation parameters
    const morningStartTime = 9 * 60; // 9:00 AM in minutes
    const morningEndTime = 13 * 60; // 1:00 PM in minutes
    const eveningStartTime = 18 * 60; // 6:00 PM in minutes
    const eveningEndTime = 20 * 60; // 8:00 PM in minutes
    const interval = 15; // 15 minutes
    
    // Initialize the calendar
    function initCalendar() {
      renderWeek();
      
      // Set up time period button handlers
      document.getElementById('morning-button').addEventListener('click', function() {
        selectTimePeriod('morning');
      });
      
      document.getElementById('evening-button').addEventListener('click', function() {
        selectTimePeriod('evening');
      });
      
      // Set up form submission handler
      document.getElementById('scheduleForm').addEventListener('submit', function(e) {
        if (!selectedTimeSlot) {
          e.preventDefault();
          alert('Please select a time slot before continuing.');
          return false;
        }
        
        // Format selected date for submission
        const formattedDate = `${selectedDate.getFullYear()}-${(selectedDate.getMonth() + 1).toString().padStart(2, '0')}-${selectedDate.getDate().toString().padStart(2, '0')}`;
        document.getElementById('selected_date_input').value = formattedDate;
        document.getElementById('selected_time_input').value = selectedTimeSlot;
        
        // Extra check before form submission to prevent double booking
        const slotKey = `${formattedDate} ${selectedTimeSlot}`;
        if (isTimeSlotBooked(formattedDate, selectedTimeSlot)) {
          e.preventDefault();
          alert('This slot has already been booked. Please select another time.');
          return false;
        }
      });
      
      // Initially hide time slots until a time period is selected
      document.getElementById('time-slots-container').style.display = 'none';
      document.getElementById('no-slots-message').style.display = 'none';
    }
    
    // Select time period (morning or evening)
    function selectTimePeriod(period) {
      selectedTimePeriod = period;
      
      // Update UI for selected time period button
      const morningButton = document.getElementById('morning-button');
      const eveningButton = document.getElementById('evening-button');
      
      morningButton.classList.remove('selected');
      eveningButton.classList.remove('selected');
      
      if (period === 'morning') {
        morningButton.classList.add('selected');
      } else {
        eveningButton.classList.add('selected');
      }
      
      // Reset selected time slot
      selectedTimeSlot = null;
      document.getElementById('continue-button').disabled = true;
      
      // Generate time slots for the selected period
      generateTimeSlots();
    }
    
    // Render the current week
    function renderWeek() {
      const datesContainer = document.getElementById('dates-container');
      datesContainer.innerHTML = '';
      
      // Update month-year display based on current week
      updateMonthYearDisplay();
      
      // Track if we've found a selectable date yet
      let foundSelectableDate = false;
      
      for (let i = 0; i < 6; i++) { // Showing 6 days Mon-Sat
        const date = new Date(currentWeekStart);
        date.setDate(currentWeekStart.getDate() + i);
        
        const dateNumber = date.getDate();
        const monthShort = monthNames[date.getMonth()];
        const isToday = isSameDate(date, today);
        const isPast = date < today && !isToday;
        const isSelectedDate = isSameDate(date, selectedDate);
        
        // Create date element
        const dateElement = document.createElement('div');
        dateElement.className = `date${isSelectedDate ? ' selected' : ''}${isPast ? ' disabled' : ''}`;
        
        // Store date information as data attributes
        dateElement.dataset.date = date.getDate();
        dateElement.dataset.month = date.getMonth();
        dateElement.dataset.year = date.getFullYear();
        dateElement.dataset.fulldate = `${date.getFullYear()}-${(date.getMonth() + 1).toString().padStart(2, '0')}-${date.getDate().toString().padStart(2, '0')}`;
        
        if (!isPast) {
          dateElement.onclick = function() { 
            selectDate(this); 
            const selectedDateObj = new Date(
              parseInt(this.dataset.year),
              parseInt(this.dataset.month),
              parseInt(this.dataset.date)
            );
            selectedDate = selectedDateObj;
            
            // If a time period is already selected, regenerate time slots
            if (selectedTimePeriod) {
              generateTimeSlots();
            }
          };
          
          // If this is the first selectable date and no date is currently selected
          if (!foundSelectableDate && (!isSelectedDate || isPast)) {
            foundSelectableDate = true;
            if (!isToday) { // Only auto-select if it's not already today
              selectedDate = new Date(date);
            }
          }
        }
        
        // Date number
        const dateNumberElement = document.createElement('div');
        dateNumberElement.className = 'date-number';
        dateNumberElement.textContent = dateNumber;
        
        // Date month
        const dateMonthElement = document.createElement('div');
        dateMonthElement.className = 'date-month';
        dateMonthElement.textContent = monthShort;
        
        // Today indicator
        if (isToday) {
          const todayIndicator = document.createElement('div');
          todayIndicator.className = 'today-indicator';
          dateElement.appendChild(todayIndicator);
        }
        
        dateElement.appendChild(dateNumberElement);
        dateElement.appendChild(dateMonthElement);
        datesContainer.appendChild(dateElement);
      }
    }
    
    // Update the month and year display
    function updateMonthYearDisplay() {
      const firstDate = new Date(currentWeekStart);
      const lastDate = new Date(currentWeekStart);
      lastDate.setDate(currentWeekStart.getDate() + 5); // For 6 days (Mon-Sat)
      
      let displayText = '';
      
      if (firstDate.getMonth() === lastDate.getMonth()) {
        // Same month
        displayText = `${fullMonthNames[firstDate.getMonth()]} ${firstDate.getFullYear()}`;
      } else {
        // Different months
        displayText = `${monthNames[firstDate.getMonth()]} - ${monthNames[lastDate.getMonth()]} ${lastDate.getFullYear()}`;
      }
      
      document.getElementById('month-year-display').textContent = displayText;
    }
    
    // Navigate weeks
    function navigateWeek(direction) {
      const newWeekStart = new Date(currentWeekStart);
      newWeekStart.setDate(currentWeekStart.getDate() + (direction * 7));
      
      // Only allow navigating to future weeks, not past weeks
      if (direction < 0) {
        // For backward navigation, ensure we don't go before the current week
        const todayMonday = new Date(today);
        const diff = today.getDay() === 0 ? -6 : 1 - today.getDay();
        todayMonday.setDate(today.getDate() + diff);
        
        if (newWeekStart < todayMonday) {
          return; // Don't navigate to past weeks
        }
      }
      
      currentWeekStart = newWeekStart;
      renderWeek();
      
      // If a time period is selected, regenerate the time slots
      if (selectedTimePeriod) {
        generateTimeSlots();
      }
    }
    
    // Select a date
    function selectDate(element) {
      // Skip if clicking on disabled date
      if (element.classList.contains('disabled')) {
        return;
      }
      
      // Remove selected class from all date elements
      document.querySelectorAll('.date').forEach(date => {
        date.classList.remove('selected');
      });
      
      // Add selected class to clicked element
      element.classList.add('selected');
      
      // Reset selected time slot
      selectedTimeSlot = null;
      document.getElementById('continue-button').disabled = true;
    }
    
    // Check if a time slot is booked - IMPROVED function with exact matching and error handling
    function isTimeSlotBooked(dateStr, timeStr) {
        // Make sure time format is consistent by removing extra spaces and standardizing
        const formattedTime = standardizeTimeFormat(timeStr);
        const slotDateTime = `${dateStr} ${formattedTime}`;
        
        // Debug logging for better troubleshooting
        console.log(`Checking if slot ${slotDateTime} is booked`);
        
        // Check exact match first
        const isBooked = bookedSlots.includes(slotDateTime);
        
        // Enhanced debugging
        if (isBooked) {
            console.log(`BOOKED: ${slotDateTime} is booked!`);
            return true;
        }
        
        // Also check for time format variations (like '10:30 AM' vs '10:30AM')
        for (let i = 0; i < bookedSlots.length; i++) {
            const bookedSlot = bookedSlots[i];
            const bookedDate = bookedSlot.split(' ')[0];
            
            // Skip if dates don't match
            if (bookedDate !== dateStr) {
                continue;
            }
            
            // Extract time part (everything after the date)
            let bookedTime = bookedSlot.substring(bookedDate.length).trim();
            
            // Compare standardized formats
            if (standardizeTimeFormat(bookedTime) === formattedTime) {
                console.log(`BOOKED (alternative format): ${slotDateTime} matches ${bookedSlot}`);
                return true;
            }
        }
        
        return false;
    }
    
    // Helper function to standardize time format in JavaScript
    function standardizeTimeFormat(timeStr) {
        timeStr = timeStr.trim();
        
        // Check if there's a space before AM/PM
        const timeRegex = /(\d+:\d+)\s*(AM|PM)/i;
        const match = timeStr.match(timeRegex);
        
        if (match) {
            return `${match[1]} ${match[2].toUpperCase()}`;
        }
        
        // If no space before AM/PM, add one
        const noSpaceRegex = /(\d+:\d+)(AM|PM)/i;
        const noSpaceMatch = timeStr.match(noSpaceRegex);
        
        if (noSpaceMatch) {
            return `${noSpaceMatch[1]} ${noSpaceMatch[2].toUpperCase()}`;
        }
        
        // Return original if no pattern matched
        return timeStr;
    }
    
    // Generate time slots - MODIFIED for better booked slot detection
    function generateTimeSlots() {
        const timeSlotsContainer = document.getElementById('time-slots-container');
        const noSlotsMessage = document.getElementById('no-slots-message');
        const continueButton = document.getElementById('continue-button');
        
        // Exit if no time period is selected yet
        if (!selectedTimePeriod) {
            timeSlotsContainer.style.display = 'none';
            noSlotsMessage.style.display = 'none';
            return;
        }
        
        timeSlotsContainer.innerHTML = '';
        continueButton.disabled = true;
        selectedTimeSlot = null;
        
        // Format selected date for checking booked slots - ENSURING YYYY-MM-DD FORMAT
        const year = selectedDate.getFullYear();
        const month = (selectedDate.getMonth() + 1).toString().padStart(2, '0');
        const day = selectedDate.getDate().toString().padStart(2, '0');
        const formattedDate = `${year}-${month}-${day}`;
        
        console.log("Checking slots for date:", formattedDate); // Debug
        
        // Get current time in minutes if the selected date is today
        let currentTimeMinutes = 0;
        const isSelectedDateToday = isSameDate(selectedDate, today);
        
        if (isSelectedDateToday) {
            // Get current time and add a buffer to prevent booking too close to current time
            const now = new Date();
            currentTimeMinutes = now.getHours() * 60 + now.getMinutes() + 4; // Reduced from 15 to 4 min buffer
            // Round up to the next 15-minute interval
            currentTimeMinutes = Math.ceil(currentTimeMinutes / interval) * interval;
            console.log("Current time in minutes (with buffer):", currentTimeMinutes);
        }
        
        let hasAvailableSlots = false;
        
        // Generate time slots based on selected period (morning or evening)
        let startTime, endTime;
        
        if (selectedTimePeriod === 'morning') {
            startTime = morningStartTime;
            endTime = morningEndTime;
        } else {
            startTime = eveningStartTime;
            endTime = eveningEndTime;
        }
        
        for (let minutes = startTime; minutes < endTime; minutes += interval) {
            // Skip time slots in the past for today
            if (isSelectedDateToday && minutes < currentTimeMinutes) {
                console.log(`Skipping past time slot: ${Math.floor(minutes/60)}:${(minutes%60).toString().padStart(2, '0')} (${minutes} min) < current time (${currentTimeMinutes} min)`);
                continue;
            }
            
            const hour = Math.floor(minutes / 60);
            const minute = minutes % 60;
            
            // Format time as 12-hour with AM/PM
            const period = hour >= 12 ? 'PM' : 'AM';
            const displayHour = hour % 12 || 12;
            const displayMinute = minute.toString().padStart(2, '0');
            const timeDisplay = `${displayHour}:${displayMinute} ${period}`;
            
            // Special case: Always disable 11:00 AM slots
            const is11AM = (hour === 11 && minute === 0);
            
            // Check if this time slot is booked
            const isBooked = isTimeSlotBooked(formattedDate, timeDisplay) || is11AM;
            
            const timeSlot = document.createElement('div');
            timeSlot.className = `time-slot${isBooked ? ' disabled' : ''}`;
            timeSlot.textContent = timeDisplay;
            timeSlot.dataset.time = timeDisplay;
            timeSlot.dataset.datetime = `${formattedDate} ${timeDisplay}`;
            
            // Only add click handler if the slot is not booked and not 11:00 AM
            if (!isBooked) {
                hasAvailableSlots = true;
                
                timeSlot.addEventListener('click', function() {
                    // Double-check if the slot is still available
                    if (isTimeSlotBooked(formattedDate, timeDisplay)) {
                        alert('Sorry, this slot was just booked. Please select another time.');
                        this.classList.add('disabled');
                        return;
                    }
                    
                    document.querySelectorAll('.time-slot').forEach(slot => {
                        slot.classList.remove('selected');
                    });
                    this.classList.add('selected');
                    selectedTimeSlot = this.dataset.time;
                    continueButton.disabled = false;
                });
            }
            
            timeSlotsContainer.appendChild(timeSlot);
        }
        
        // Show or hide the "no slots available" message
        if (hasAvailableSlots) {
            noSlotsMessage.style.display = 'none';
            timeSlotsContainer.style.display = 'grid';
        } else {
            noSlotsMessage.style.display = 'block';
            timeSlotsContainer.style.display = 'none';
        }
    }
    
    // Check if two dates are the same (ignoring time)
    function isSameDate(date1, date2) {
        return date1.getDate() === date2.getDate() &&
                date1.getMonth() === date2.getMonth() &&
                date1.getFullYear() === date2.getFullYear();
    }
    
    // Initialize calendar when the page loads
    window.addEventListener('DOMContentLoaded', initCalendar);
    
    // Add a refresh mechanism to prevent stale data
    window.addEventListener('focus', function() {
        // Reload the page when it regains focus to get fresh booking data
        window.location.href = 'slot-booking.php?refresh=' + new Date().getTime();
    });
    
    // Add periodic refresh to prevent stale data
    let refreshTimer;
    
    function startRefreshTimer() {
        // Refresh every 30 seconds to get updated booking data
        refreshTimer = setTimeout(function() {
            // Only refresh if the user hasn't selected a time slot yet
            if (!selectedTimeSlot) {
                window.location.href = 'slot-booking.php?refresh=' + new Date().getTime();
            }
        }, 30000); // 30 seconds
    }
    
    // Start the timer when the page loads
    window.addEventListener('DOMContentLoaded', function() {
        initCalendar();
        startRefreshTimer();
    });
    
    // Clear the timer when the user is about to leave the page
    window.addEventListener('beforeunload', function() {
        clearTimeout(refreshTimer);
    });
  </script>
</body>
</html>