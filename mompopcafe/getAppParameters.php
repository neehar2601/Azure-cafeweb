<?php
  // Database configuration for Azure Container Group
  $showServerInfo = "false";
  $timeZone = "America/New_York";
  $currency = "$";
  
  // Use environment variables with fallback to localhost
  $db_url = getenv('DB_HOST') ?: "localhost";
  $db_name = getenv('DB_NAME') ?: "cafedb";
  $db_user = getenv('DB_USER') ?: "cafeuser";
  $db_password = getenv('DB_PASSWORD') ?: "CafeUserPassword123!";
?>
 
