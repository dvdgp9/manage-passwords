<?php
require 'includes/db.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/cleanup.log');

try {
    // Log start
    error_log("[CLEANUP] Started at " . date('Y-m-d H:i:s'));
    
    // Delete passwords older than 7 days
    $stmt = $pdo->prepare("DELETE FROM passwords WHERE created_at < NOW() - INTERVAL 7 DAY");
    $stmt->execute();
    $deletedCount = $stmt->rowCount();
    
    $message = "[CLEANUP] Deleted $deletedCount old password entries.";
    echo $message . "\n";
    error_log($message);
    
    // Also log how many entries remain
    $stmt = $pdo->query("SELECT COUNT(*) FROM passwords");
    $remaining = $stmt->fetchColumn();
    error_log("[CLEANUP] Remaining entries: $remaining");
    
    // Log oldest entry
    $stmt = $pdo->query("SELECT MIN(created_at) as oldest FROM passwords");
    $oldest = $stmt->fetchColumn();
    error_log("[CLEANUP] Oldest entry: " . ($oldest ?: 'none'));
    
} catch (PDOException $e) {
    $error = "[CLEANUP ERROR] " . $e->getMessage();
    error_log($error);
    die($error . "\n");
}
?>
