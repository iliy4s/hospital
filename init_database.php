<?php
// Show all errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Initialization Script</h2>";

// Include database connection
try {
    require_once 'connect.php';
    echo "<p>✅ Successfully connected to database</p>";
} catch (Exception $e) {
    die("<p>❌ Database connection failed: " . $e->getMessage() . "</p>");
}

// Create tables from SQL file
try {
    $sql = file_get_contents('dr_kiran_appointments.sql');
    
    // Split into individual queries
    $queries = explode(';', $sql);
    
    // Execute each query
    $successCount = 0;
    $totalQueries = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;
        
        $totalQueries++;
        if ($conn->query($query)) {
            $successCount++;
        } else {
            echo "<p>❌ Error executing query: " . $conn->error . "</p>";
            echo "<pre>" . htmlspecialchars($query) . "</pre>";
        }
    }
    
    echo "<p>✅ Executed $successCount/$totalQueries queries successfully</p>";
} catch (Exception $e) {
    echo "<p>❌ Error processing SQL file: " . $e->getMessage() . "</p>";
}

// Create admin user with proper password hashing
try {
    $username = 'admin';
    $password = 'admin123';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if admin exists
    $query = "SELECT * FROM admin_users WHERE username = 'admin'";
    $result = $conn->query($query);
    
    if ($result->num_rows == 0) {
        // Insert new admin
        $query = "INSERT INTO admin_users (username, password) VALUES (?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $username, $hashedPassword);
        
        if ($stmt->execute()) {
            echo "<p>✅ Created admin user successfully</p>";
        } else {
            echo "<p>❌ Failed to create admin user: " . $stmt->error . "</p>";
        }
    } else {
        // Update admin password
        $query = "UPDATE admin_users SET password = ? WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $hashedPassword, $username);
        
        if ($stmt->execute()) {
            echo "<p>✅ Updated admin password successfully</p>";
        } else {
            echo "<p>❌ Failed to update admin password: " . $stmt->error . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p>❌ Error setting up admin user: " . $e->getMessage() . "</p>";
}

echo "<p>Database initialization complete! You can now <a href='admin_login.php'>login to the admin area</a>.</p>";
?> 