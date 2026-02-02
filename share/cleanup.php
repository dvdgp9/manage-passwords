<?php
require 'includes/db.php';

// Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting cleanup...\n";

// Check if $pdo exists
if (!isset($pdo)) {
    die("ERROR: Database connection not established (\$pdo not defined)\n");
}

echo "Database connection OK\n";

try {
    // Count entries before cleanup
    $stmt = $pdo->query("SELECT COUNT(*) FROM passwords");
    $beforeCount = $stmt->fetchColumn();
    echo "Total entries before cleanup: $beforeCount\n";
    
    // Show entries older than 7 days
    $stmt = $pdo->query("SELECT COUNT(*) FROM passwords WHERE created_at < NOW() - INTERVAL 7 DAY");
    $oldCount = $stmt->fetchColumn();
    echo "Entries older than 7 days: $oldCount\n";
    
    // Show oldest entry
    $stmt = $pdo->query("SELECT MIN(created_at) as oldest FROM passwords");
    $oldest = $stmt->fetchColumn();
    echo "Oldest entry: " . ($oldest ?: 'none') . "\n";
    
    // Delete passwords older than 7 days
    $stmt = $pdo->prepare("DELETE FROM passwords WHERE created_at < NOW() - INTERVAL 7 DAY");
    $stmt->execute();
    $deletedCount = $stmt->rowCount();
    
    echo "Deleted $deletedCount old entries\n";
    
    // Count entries after cleanup
    $stmt = $pdo->query("SELECT COUNT(*) FROM passwords");
    $afterCount = $stmt->fetchColumn();
    echo "Total entries after cleanup: $afterCount\n";
    
} catch (PDOException $e) {
    die("ERROR: " . $e->getMessage() . "\n");
}
?>
