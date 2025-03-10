<?php
// Initialize variables
$today = new DateTime('now');
$todayFormatted = $today->format('Y-m-d');

// Process form submission if applicable
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['selected_date']) && isset($_POST['selected_time'])) {
        $selectedDate = $_POST['selected_date'];
        $selectedTime = $_POST['selected_time'];
        
        // Redirect to the form page with the selected date and time
        header("Location: patient_form.php?date=$selectedDate&time=$selectedTime");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Scheduling Interface</title>
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      background-color: #f4e6fa;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }
    
    .modal {
      background-color: white;
      border-radius: 16px;
      width: 90%;
      max-width: 600px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      position: relative;
      padding: 24px;
    }
    
    .close-button {
      position: absolute;
      top: 20px;
      right: 20px;
      font-size: 24px;
      cursor: pointer;
      background: none;
      border: none;
      color: #555;
    }
    
    h1 {
      text-align: center;
      font-size: 28px;
      margin-top: 10px;
      margin-bottom: 30px;
      color: #222;
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
      border: 1px solid #e0e0e0;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: white;
      color: #7047d1;
      font-size: 20px;
      cursor: pointer;
      margin: 0 10px;
      position: absolute;
      z-index: 10;
    }
    
    .nav-button.prev {
      left: 0;
    }
    
    .nav-button.next {
      right: 0;
    }
    
    .calendar {
      border: 1px solid #e0e0e0;
      border-radius: 12px;
      overflow: hidden;
      width: 100%;
      max-width: 460px;
      background-color: #f8f8f8;
      padding-top: 10px;
    }
    
    .weekdays {
      display: flex;
      border-bottom: 1px solid #e0e0e0;
    }
    
    .weekday {
      flex: 1;
      text-align: center;
      padding: 12px 0;
      font-weight: 500;
      color: #555;
      font-size: 16px;
    }
    
    .dates {
      display: flex;
      background-color: white;
      height: 90px;
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
    }
    
    .date.selected {
      background-color: #7047d1;
    }
    
    .date.disabled {
      color: #ccc;
      cursor: not-allowed;
    }
    
    .date-number {
      font-size: 30px;
      font-weight: 600;
      line-height: 1.2;
    }
    
    .date.selected .date-number,
    .date.selected .date-month {
      color: white;
    }
    
    .date-month {
      font-size: 16px;
      margin-top: 4px;
    }
    
    .date:not(.disabled):not(.selected) .date-number {
      color: #7047d1;
    }
    
    .date:not(.disabled):not(.selected) .date-month {
      color: #7047d1;
    }
    
    .date.disabled .date-number,
    .date.disabled .date-month {
      color: #aaa;
    }
    
    .today-indicator {
      position: absolute;
      top: 5px;
      right: 5px;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background-color: #f44336;
    }
    
    h2 {
      text-align: center;
      font-size: 24px;
      margin-bottom: 20px;
      color: #333;
    }
    
    .timezone-selector {
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 30px;
      gap: 10px;
      color: #7047d1;
    }
    
    .timezone-dropdown {
      display: flex;
      align-items: center;
      font-weight: 500;
    }
    
    .dropdown-icon {
      margin-left: 5px;
    }
    
    .meeting-duration {
      display: flex;
      align-items: center;
      color: #666;
    }
    
    .clock-icon {
      margin-right: 5px;
    }
    
    .time-slots {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
      margin-bottom: 30px;
      max-height: 300px;
      overflow-y: auto;
      padding-right: 5px;
    }
    
    .time-slot {
      padding: 12px 8px;
      text-align: center;
      border: 1px solid #e0e0e0;
      border-radius: 12px;
      cursor: pointer;
      color: #7047d1;
      font-size: 16px;
      font-weight: 500;
      transition: background-color 0.2s;
    }
    
    .time-slot:hover {
      background-color: #f9f5ff;
    }
    
    .time-slot.selected {
      background-color: #7047d1;
      color: white;
      border-color: #7047d1;
    }
    
    .footer {
      text-align: center;
      padding-top: 20px;
      border-top: 1px solid #eee;
      color: #555;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .logo {
      margin-left: 10px;
      font-weight: 500;
      color: #555;
    }
    
    .month-year {
      text-align: center;
      font-size: 18px;
      font-weight: 500;
      margin-bottom: 10px;
      color: #555;
    }
    
    .time-slots-container {
      margin-bottom: 30px;
    }
    
    .no-slots-message {
      text-align: center;
      color: #666;
      font-style: italic;
      padding: 20px;
      display: none;
    }
    
    .submit-button {
      display: block;
      width: 100%;
      background-color: #7047d1;
      color: white;
      padding: 14px;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 500;
      cursor: pointer;
      transition: background-color 0.2s;
    }
    
    .submit-button:hover {
      background-color: #5e3ab1;
    }
    
    .submit-button:disabled {
      background-color: #ccc;
      cursor: not-allowed;
    }
  </style>
</head>
<body>
  <div class="modal">
    <a href="index.php" class="close-button">×</a>
    <h1>What time works best for a quick call?</h1>
    
    <form id="scheduleForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
      <input type="hidden" name="selected_date" id="selected_date_input">
      <input type="hidden" name="selected_time" id="selected_time_input">
      
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
      
      <h2>Select a time slot</h2>
      
      <div class="time-slots-container">
        <div class="time-slots" id="time-slots-container">
          <!-- Time slots will be generated by JavaScript -->
        </div>
        <div class="no-slots-message" id="no-slots-message">
          No available time slots for this date.
        </div>
      </div>
      
      <button type="submit" class="submit-button" id="continue-button" disabled>Continue to Patient Details</button>
      
      <div class="footer">
        <span class="logo">Dr. Kiran Hospitals</span>
      </div>
    </form>
  </div>

  <script>
    // Current date
    const today = new Date(<?php echo date('Y'); ?>, <?php echo date('n')-1; ?>, <?php echo date('j'); ?>);
    let currentWeekStart = new Date(today);
    let selectedDate = new Date(today);
    let selectedTimeSlot = null;
    
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
      generateTimeSlots();
      
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
      });
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
            generateTimeSlots();
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
      
      // Generate time slots for the selected date
      generateTimeSlots();
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
    
    // Generate time slots
    function generateTimeSlots() {
      const timeSlotsContainer = document.getElementById('time-slots-container');
      const noSlotsMessage = document.getElementById('no-slots-message');
      const continueButton = document.getElementById('continue-button');
      
      timeSlotsContainer.innerHTML = '';
      continueButton.disabled = true;
      selectedTimeSlot = null;
      
      // Get current time in minutes if the selected date is today
      let currentTimeMinutes = 0;
      const isSelectedDateToday = isSameDate(selectedDate, today);
      
      if (isSelectedDateToday) {
        currentTimeMinutes = today.getHours() * 60 + today.getMinutes();
        // Round up to the next 15-minute interval
        currentTimeMinutes = Math.ceil(currentTimeMinutes / interval) * interval;
      }
      
      // Generate time slots for morning
      let hasAvailableSlots = false;
      
      for (let minutes = morningStartTime; minutes < morningEndTime; minutes += interval) {
        // Skip time slots in the past for today
        if (isSelectedDateToday && minutes < currentTimeMinutes) {
          continue;
        }
        
        hasAvailableSlots = true;
        
        const hour = Math.floor(minutes / 60);
        const minute = minutes % 60;
        
        // Format time as 12-hour with AM/PM
        const period = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour % 12 || 12;
        const displayMinute = minute.toString().padStart(2, '0');
        const timeDisplay = `${displayHour}:${displayMinute} ${period}`;
        
        const timeSlot = document.createElement('div');
        timeSlot.className = 'time-slot';
        timeSlot.textContent = timeDisplay;
        timeSlot.dataset.time = timeDisplay;
        
        timeSlot.addEventListener('click', function() {
          document.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.remove('selected');
          });
          this.classList.add('selected');
          selectedTimeSlot = this.dataset.time;
          continueButton.disabled = false;
        });
        
        timeSlotsContainer.appendChild(timeSlot);
      }
      
      // Generate time slots for evening
      for (let minutes = eveningStartTime; minutes < eveningEndTime; minutes += interval) {
        // Skip time slots in the past for today
        if (isSelectedDateToday && minutes < currentTimeMinutes) {
          continue;
        }
        
        hasAvailableSlots = true;
        
        const hour = Math.floor(minutes / 60);
        const minute = minutes % 60;
        
        // Format time as 12-hour with AM/PM
        const period = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour % 12 || 12;
        const displayMinute = minute.toString().padStart(2, '0');
        const timeDisplay = `${displayHour}:${displayMinute} ${period}`;
        
        const timeSlot = document.createElement('div');
        timeSlot.className = 'time-slot';
        timeSlot.textContent = timeDisplay;
        timeSlot.dataset.time = timeDisplay;
        
        timeSlot.addEventListener('click', function() {
          document.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.remove('selected');
          });
          this.classList.add('selected');
          selectedTimeSlot = this.dataset.time;
          continueButton.disabled = false;
        });
        
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
  </script>
</body>
</html>