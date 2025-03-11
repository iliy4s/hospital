

-- Insert default time slot configurations
INSERT INTO time_slots_config (slot_type, start_time, end_time, interval_minutes, max_appointments) VALUES
('morning', 540, 780, 15, 1), -- 9:00 AM to 1:00 PM
('evening', 1080, 1200, 15, 1); 
this is "dr_kiran_appointments.sql"
so when we choosing to book the slot and selecting the date and time it and then after clicking the "continue with patient details" button it should navigate to the "registration-form.php"
and the session should have time and date after filling the form the form details should be store into the database after booking is done the slected date and time slot booking should be disable beacuse once we book the slot at particular time at the same slot another person should be book the slot on same timinng