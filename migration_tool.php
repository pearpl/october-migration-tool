<?php
/**
 * Simple OctoberCMS Migration Tool
 *
 * This script provides a simple interface to:
 * 1. Zip all files and folders in the current directory
 * 2. Unzip an archive in the current directory
 * 3. Create a MySQL database dump for OctoberCMS
 *
 * @author Łukasz Kosma aka AlienatedAlien
 * @website https://www.pear.pl
 * @email hello@pear.pl
 * @license MIT
 */

// Handle file downloads
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $filename = $_GET['download'];
    $availableFiles = listAvailableFiles(dirname(__FILE__) . DIRECTORY_SEPARATOR);
    
    // Find the file in our list of available files
    $fileToDownload = null;
    foreach ($availableFiles as $file) {
        if ($file['name'] === $filename) {
            $fileToDownload = $file;
            break;
        }
    }
    
    if ($fileToDownload && file_exists($fileToDownload['path'])) {
        // Set headers for download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($fileToDownload['path']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($fileToDownload['path']));
        
        // Clear output buffer
        ob_clean();
        flush();
        
        // Read file and output to browser
        readfile($fileToDownload['path']);
        exit;
    }
}

// Start session before any output
session_start();

// Simple password protection
$access_password = "migration2025"; // Change this to your desired password
$authenticated = false;

// Check if password is submitted
if (isset($_POST['auth_password'])) {
    if ($_POST['auth_password'] === $access_password) {
        $authenticated = true;
        $_SESSION['authenticated'] = true;
    }
} else {
    // Check if already authenticated via session
    if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
        $authenticated = true;
    }
}

// If not authenticated, show login form and exit
if (!$authenticated && !isset($_POST['check_versions'])) {
    showLoginForm();
    exit;
}

// Error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set maximum execution time to 0 (no time limit)
set_time_limit(0);

// Increase memory limit
ini_set('memory_limit', '512M');

// Increase upload file size limit to 100MB
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '100M');

// PHP Version compatibility check
$versionFile = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'php_versions.json';
$currentPhpVersion = phpversion();
$currentCliVersion = PHP_SAPI;

// Check if this is a version check request
if (isset($_POST['check_versions'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'php_version' => $currentPhpVersion,
        'cli_version' => $currentCliVersion
    ]);
    exit;
}

// Store or compare PHP versions
$versionCompatible = true;
$versionMessage = "";

if (!file_exists($versionFile)) {
    // First run - create the version file
    $versionData = [
        'source_php_version' => $currentPhpVersion,
        'source_cli_version' => $currentCliVersion,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Check if directory is writable
    if (is_writable(dirname($versionFile))) {
        file_put_contents($versionFile, json_encode($versionData, JSON_PRETTY_PRINT));
        $versionMessage = "PHP version information stored for future comparison.";
    } else {
        $versionMessage = "Warning: Unable to write version file. Directory not writable.";
    }
} else {
    // Compare versions
    $versionData = json_decode(file_get_contents($versionFile), true);
    
    if ($versionData['source_php_version'] !== $currentPhpVersion) {
        $versionCompatible = false;
        $versionMessage = "Warning: PHP version mismatch. Source: {$versionData['source_php_version']}, Current: {$currentPhpVersion}";
    }
    
    if ($versionData['source_cli_version'] !== $currentCliVersion) {
        $versionCompatible = false;
        $versionMessage .= ($versionMessage ? "<br>" : "") . "Warning: CLI version mismatch. Source: {$versionData['source_cli_version']}, Current: {$currentCliVersion}";
    }
    
    if ($versionCompatible) {
        $versionMessage = "PHP and CLI versions match the source environment.";
    }
}

// Get the current script directory path and parent path
$currentPath = dirname(__FILE__) . DIRECTORY_SEPARATOR;
$parentPath = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR;

// Check if we're in a migration subfolder
$inMigrationFolder = (basename(dirname(__FILE__)) === 'migration');
$sourcePath = $inMigrationFolder ? $parentPath : $currentPath;
$extractPath = $inMigrationFolder ? $parentPath : $currentPath;

$zipFilename = 'site_backup_' . date('Y-m-d_H-i-s') . '.zip';
$dbDumpFilename = 'db_backup_' . date('Y-m-d_H-i-s') . '.sql';
$result = '';

// Check if we're in the zip process
if (isset($_POST['action']) && $_POST['action'] === 'zip') {
    // Show the progress UI first
    if (!isset($_POST['start_zip'])) {
        // First step - just show the UI
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creating Zip Archive - OctoberCMS Migration Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .progress-container {
            margin-top: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .progress-bar {
            width: 100%;
            background-color: #e0e0e0;
            height: 20px;
            border-radius: 10px;
            margin-bottom: 10px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background-color: #4CAF50;
            width: 0%;
            animation: progress-animation 2s infinite;
        }
        @keyframes progress-animation {
            0% { width: 0%; }
            50% { width: 50%; }
            100% { width: 100%; }
        }
        .progress-text {
            font-size: 14px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>OctoberCMS Migration Tool</h1>
        
        <div class="progress-container">
            <h3>Creating Zip Archive...</h3>
            <div class="progress-bar"><div class="progress-fill"></div></div>
            <div class="progress-text">Please wait while the zip archive is being created. This may take several minutes depending on the size of your site.</div>
            <p>The page will automatically refresh when the process is complete.</p>
        </div>
        
        <form method="post" id="zip-form" style="display:none;">
            <input type="hidden" name="action" value="zip">
            <input type="hidden" name="start_zip" value="1">
        </form>
        
        <script>
            // Submit the form after a short delay to allow the page to render
            setTimeout(function() {
                document.getElementById('zip-form').submit();
            }, 1000);
        </script>
    </div>
</body>
</html>
        <?php
        exit;
    }
}

// Process form submission
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'zip':
            if (isset($_POST['start_zip'])) {
                $result = zipFiles($sourcePath, $currentPath . $zipFilename);
            }
            break;
            
        case 'unzip':
            if (isset($_FILES['zipfile']) && $_FILES['zipfile']['error'] === UPLOAD_ERR_OK) {
                $uploadedFile = $_FILES['zipfile']['tmp_name'];
                $result = unzipFiles($uploadedFile, $extractPath);
            } elseif (isset($_POST['zipfilename']) && !empty($_POST['zipfilename'])) {
                $zipFile = $currentPath . $_POST['zipfilename'];
                if (file_exists($zipFile)) {
                    $result = unzipFiles($zipFile, $extractPath);
                } else {
                    $result = "Error: Zip file not found: " . $_POST['zipfilename'];
                }
            } else {
                $result = "Please upload a zip file or specify a valid zip filename";
            }
            break;
            
        case 'dbdump':
            // Try to detect OctoberCMS database configuration
            $dbConfig = detectOctoberDbConfig($sourcePath);
            
            // Use POST values if provided, otherwise use detected values or defaults
            // Make sure to check if the POST values are empty strings too
            $host = (!empty($_POST['host'])) ? $_POST['host'] : ($dbConfig['host'] ?? 'localhost');
            $username = (!empty($_POST['username'])) ? $_POST['username'] : ($dbConfig['username'] ?? 'root');
            $password = (!empty($_POST['password'])) ? $_POST['password'] : ($dbConfig['password'] ?? '');
            $database = (!empty($_POST['database'])) ? $_POST['database'] : ($dbConfig['database'] ?? 'october');
            
            $result = createDatabaseDump($host, $username, $password, $database, $currentPath . $dbDumpFilename);
            break;
    }
}

/**
 * Detect OctoberCMS database configuration
 */
function detectOctoberDbConfig($sourcePath) {
    $dbConfig = [];
    
    // Check for .env file first (safer than trying to include PHP files)
    $envPaths = [
        $sourcePath . '.env',
        $sourcePath . '../.env',
        $sourcePath . 'october/.env'
    ];
    
    foreach ($envPaths as $envPath) {
        if (file_exists($envPath)) {
            // Read the entire file content
            $envContent = file_get_contents($envPath);
            
            // Improved regex patterns with better handling of different formats
            // Look for lines that start with DB_HOST=, allowing for spaces around the equals sign
            preg_match('/^\s*DB_HOST\s*=\s*([^\r\n]+)/m', $envContent, $hostMatches);
            preg_match('/^\s*DB_DATABASE\s*=\s*([^\r\n]+)/m', $envContent, $dbMatches);
            preg_match('/^\s*DB_USERNAME\s*=\s*([^\r\n]+)/m', $envContent, $userMatches);
            preg_match('/^\s*DB_PASSWORD\s*=\s*([^\r\n]+)/m', $envContent, $passMatches);
            
            // Make sure to remove quotes that might be present in .env values
            if (!empty($hostMatches[1])) {
                $hostValue = trim($hostMatches[1]);
                $dbConfig['host'] = trim($hostValue, '"\''); // Remove quotes if present
            }
            if (!empty($dbMatches[1])) {
                $dbValue = trim($dbMatches[1]);
                $dbConfig['database'] = trim($dbValue, '"\'');
            }
            if (!empty($userMatches[1])) {
                $userValue = trim($userMatches[1]);
                $dbConfig['username'] = trim($userValue, '"\'');
            }
            if (!empty($passMatches[1])) {
                $passValue = trim($passMatches[1]);
                $dbConfig['password'] = trim($passValue, '"\'');
            }
            
            if (!empty($dbConfig)) {
                return $dbConfig;
            }
        }
    }
    
    // If .env file not found or doesn't contain DB info, try to parse database.php
    $configPaths = [
        $sourcePath . 'config/database.php',
        $sourcePath . '../config/database.php',
        $sourcePath . 'october/config/database.php'
    ];
    
    foreach ($configPaths as $configPath) {
        if (file_exists($configPath)) {
            // Read the file content instead of including it
            $content = file_get_contents($configPath);
            
            // Extract MySQL connection details using regex
            if (preg_match("/'mysql'.*?'host'.*?=>\s*['\"]([^'\"]+)['\"].*?'database'.*?=>\s*['\"]([^'\"]+)['\"].*?'username'.*?=>\s*['\"]([^'\"]+)['\"].*?'password'.*?=>\s*['\"]([^'\"]*)['\"].*?/s", $content, $matches)) {
                $dbConfig['host'] = $matches[1];
                $dbConfig['database'] = $matches[2];
                $dbConfig['username'] = $matches[3];
                $dbConfig['password'] = $matches[4];
                return $dbConfig;
            }
        }
    }
    
    return $dbConfig;
}

/**
 * Zip files and folders
 */
function zipFiles($sourcePath, $zipFilename) {
    $zip = new ZipArchive();
    $result = $zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    if ($result !== true) {
        return "Failed to create zip file: " . $result;
    }
    
    // Create recursive directory iterator
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourcePath),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    $totalFiles = 0;
    $processedFiles = 0;
    $excludeFiles = [basename(__FILE__), basename($zipFilename)];
    
    // Directories to exclude (cache and resize directories)
    $excludeDirs = [
        'storage/app/cache',
        'storage/app/resized',
        'storage/cms/cache',
        'storage/framework/cache',
        'storage/temp',
        'storage/logs',
        'storage/cms/combiner',
        'storage/cms/twig',
        'cache',
        'resize',
        'resized',
        'tmp',
        'migration' // Skip the migration folder itself
    ];
    
    // First pass: count files
    foreach ($files as $name => $file) {
        if (!$file->isDir() && !in_array(basename($file), $excludeFiles)) {
            // Check if file is in an excluded directory
            $relativePath = substr($file->getRealPath(), strlen($sourcePath));
            $skipFile = false;
            foreach ($excludeDirs as $excludeDir) {
                // Normalize paths for comparison
                $normalizedPath = str_replace('\\', '/', $relativePath);
                $normalizedExcludeDir = str_replace('\\', '/', $excludeDir);
                
                // Check if the path starts with the exclude dir or contains it as a directory
                if (strpos($normalizedPath, '/' . $normalizedExcludeDir . '/') !== false ||
                    strpos($normalizedPath, $normalizedExcludeDir . '/') === 0) {
                    $skipFile = true;
                    break;
                }
            }
            
            if (!$skipFile) {
                $totalFiles++;
            }
        }
    }
    
    // Second pass: add files to zip
    foreach ($files as $name => $file) {
        // Skip directories and the script itself and the zip file
        if ($file->isDir() || in_array(basename($file), $excludeFiles)) {
            continue;
        }
        
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($sourcePath));
        
        // Skip files in excluded directories
        $skipFile = false;
        foreach ($excludeDirs as $excludeDir) {
            // Normalize paths for comparison
            $normalizedPath = str_replace('\\', '/', $relativePath);
            $normalizedExcludeDir = str_replace('\\', '/', $excludeDir);
            
            // Check if the path starts with the exclude dir or contains it as a directory
            if (strpos($normalizedPath, '/' . $normalizedExcludeDir . '/') !== false ||
                strpos($normalizedPath, $normalizedExcludeDir . '/') === 0) {
                $skipFile = true;
                break;
            }
        }
        
        if ($skipFile) {
            continue;
        }
        
        // Add file to zip
        $zip->addFile($filePath, $relativePath);
        $processedFiles++;
    }
    
    $zip->close();
    
    return "Successfully created zip file: " . basename($zipFilename) . " with $processedFiles files (excluding cache and resized files)";
}

/**
 * Unzip files
 */
function unzipFiles($zipFilename, $extractPath) {
    $zip = new ZipArchive();
    $result = $zip->open($zipFilename);
    
    if ($result !== true) {
        return "Failed to open zip file: " . $result;
    }
    
    // Extract zip file
    $result = $zip->extractTo($extractPath);
    $zip->close();
    
    if ($result !== true) {
        return "Failed to extract zip file";
    }
    
    return "Successfully extracted zip file: " . basename($zipFilename) . " to " . $extractPath;
}

/**
 * Create database dump
 */
function createDatabaseDump($host, $username, $password, $database, $outputFile) {
    // Connect to database
    try {
        // Add options with compatibility checks
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];
        
        // Only add timeout options if they exist in this PHP version
        if (defined('PDO::ATTR_TIMEOUT')) {
            $options[PDO::ATTR_TIMEOUT] = 30; // 30 seconds timeout
        }
        
        if (defined('PDO::ATTR_DEFAULT_FETCH_MODE')) {
            $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
        }
        
        // Check if host contains port information
        $port = 3306; // Default MySQL port
        if (strpos($host, ':') !== false) {
            list($host, $port) = explode(':', $host);
        }
        
        $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, $options);
    } catch (PDOException $e) {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        $troubleshooting = "";
        
        // Provide specific guidance based on common error codes
        if ($errorCode == 1045) { // Access denied
            $troubleshooting = "<br><br><strong>Troubleshooting:</strong><ul>
                <li>Check if the username and password are correct</li>
                <li>Verify that the user has permission to connect from this host</li>
                <li>For MySQL 5.7 and earlier: <code>GRANT ALL PRIVILEGES ON $database.* TO '$username'@'%' IDENTIFIED BY 'your_password';</code></li>
                <li>For MySQL 8.0+: <code>CREATE USER IF NOT EXISTS '$username'@'%' IDENTIFIED BY 'your_password'; GRANT ALL PRIVILEGES ON $database.* TO '$username'@'%';</code></li>
                <li>Followed by: <code>FLUSH PRIVILEGES;</code></li>
            </ul>";
        } elseif ($errorCode == 2002) { // Connection refused
            $troubleshooting = "<br><br><strong>Troubleshooting:</strong><ul>
                <li>Check if the MySQL server is running</li>
                <li>Verify that the host address is correct</li>
                <li>Make sure the MySQL server allows remote connections (check bind-address in my.cnf)</li>
                <li>Check if any firewall is blocking port $port</li>
            </ul>";
        } elseif ($errorCode == 1049) { // Unknown database
            $troubleshooting = "<br><br><strong>Troubleshooting:</strong><ul>
                <li>Verify that the database '$database' exists</li>
                <li>Check if the user has access to this database</li>
            </ul>";
        }
        
        return "<div class='error-details'>Database connection failed: " . $errorMessage . $troubleshooting . "</div>";
    }
    
    // Get all tables
    $tables = [];
    $result = $pdo->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    if (empty($tables)) {
        return "No tables found in database";
    }
    
    // Start output buffering
    ob_start();
    
    // Add dump header
    echo "-- OctoberCMS Database Dump\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Server version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
    echo "-- PHP Version: " . phpversion() . "\n\n";
    
    echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    echo "SET time_zone = \"+00:00\";\n\n";
    
    echo "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
    echo "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
    echo "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
    echo "/*!40101 SET NAMES utf8mb4 */;\n\n";
    
    // Process each table
    foreach ($tables as $table) {
        // Get create table statement
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        
        echo "-- Table structure for table `$table`\n\n";
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo $row[1] . ";\n\n";
        
        // Get table data
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $columnCount = $stmt->columnCount();
        
        if ($stmt->rowCount() > 0) {
            echo "-- Dumping data for table `$table`\n";
            echo "INSERT INTO `$table` VALUES\n";
            
            $rowCount = 0;
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $rowCount++;
                echo "(";
                
                for ($i = 0; $i < $columnCount; $i++) {
                    if ($row[$i] === null) {
                        echo "NULL";
                    } else {
                        echo "'" . addslashes($row[$i]) . "'";
                    }
                    
                    if ($i < ($columnCount - 1)) {
                        echo ", ";
                    }
                }
                
                echo ")";
                
                if ($rowCount < $stmt->rowCount()) {
                    echo ",\n";
                } else {
                    echo ";\n";
                }
            }
        }
        
        echo "\n\n";
    }
    
    // Get output buffer content
    $output = ob_get_clean();
    
    // Write to file
    if (file_put_contents($outputFile, $output) === false) {
        return "Failed to write database dump to file";
    }
    
    return "Successfully created database dump: " . basename($outputFile);
}

/**
 * Show login form
 */
function showLoginForm() {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - OctoberCMS Migration Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 400px;
            margin: 100px auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button[type="submit"] {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        button[type="submit"]:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>OctoberCMS Migration Tool</h1>
        <form method="post">
            <div class="form-group">
                <label for="auth_password">Password:</label>
                <input type="password" id="auth_password" name="auth_password" required>
            </div>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
    <?php
}

/**
 * List available zip and database files
 */
function listAvailableFiles($currentPath) {
    $files = [];
    
    // Get all zip and sql files in the current directory
    $directories = [$currentPath];
    
    foreach ($directories as $dir) {
        if ($handle = opendir($dir)) {
            while (false !== ($entry = readdir($handle))) {
                $ext = pathinfo($entry, PATHINFO_EXTENSION);
                if ($ext === 'zip' || $ext === 'sql' || $ext === 'json') {
                    $filePath = $dir . $entry;
                    $files[] = [
                        'name' => $entry,
                        'path' => $filePath,
                        'size' => filesize($filePath),
                        'date' => date('Y-m-d H:i:s', filemtime($filePath)),
                        'type' => $ext
                    ];
                }
            }
            closedir($handle);
        }
    }
    
    return $files;
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Get detected database configuration
$dbConfig = detectOctoberDbConfig($sourcePath);

// Make sure the detected values are properly set
$dbHost = isset($dbConfig['host']) && !empty($dbConfig['host']) ? $dbConfig['host'] : 'localhost';
$dbUsername = isset($dbConfig['username']) && !empty($dbConfig['username']) ? $dbConfig['username'] : 'root';
$dbPassword = isset($dbConfig['password']) && !empty($dbConfig['password']) ? $dbConfig['password'] : '';
$dbName = isset($dbConfig['database']) && !empty($dbConfig['database']) ? $dbConfig['database'] : 'october';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>OctoberCMS Migration Tool</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="https://www.pear.pl/favicon.png">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="file"] {
            margin-top: 5px;
        }
        button[type="submit"] {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button[type="submit"]:hover {
            background-color: #45a049;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            background-color: #e8f5e9;
            border-left: 4px solid #4CAF50;
            color: #2e7d32;
        }
        .info {
            margin-top: 20px;
            padding: 10px;
            background-color: #e3f2fd;
            border-left: 4px solid #2196F3;
            color: #0d47a1;
        }
        .warning {
            margin-top: 20px;
            padding: 10px;
            background-color: #fff8e1;
            border-left: 4px solid #ffc107;
            color: #ff6f00;
        }
        .error {
            margin-top: 20px;
            padding: 10px;
            background-color: #ffebee;
            border-left: 4px solid #f44336;
            color: #c62828;
        }
        .detected {
            color: #4CAF50;
            font-size: 0.9em;
            margin-left: 5px;
        }
        .error-details {
            margin-top: 10px;
            font-size: 0.95em;
        }
        .error-details ul {
            margin-top: 5px;
            padding-left: 20px;
        }
        .error-details code {
            background-color: #f5f5f5;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: monospace;
        }
        code {
            background-color: #f0f0f0;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>OctoberCMS Migration Tool</h1>
        
        <div class="warning">
            <strong>Important:</strong> If you have trouble accessing this script due to OctoberCMS's .htaccess file, you have two options:
            <ol>
                <li>Add the following line to your .htaccess file (before the RewriteEngine line):<br>
                <code>RewriteRule ^migration_tool\.php$ - [L]</code></li>
                <li>Temporarily rename the .htaccess file to .htaccess.bak, use the tool, then rename it back when done.</li>
            </ol>
        </div>
        
        <div class="info">
            <p><strong>Current Directory:</strong> <?php echo $currentPath; ?></p>
            <?php if ($inMigrationFolder): ?>
            <p><strong>Source Directory:</strong> <?php echo $sourcePath; ?> (parent directory)</p>
            <?php endif; ?>
            <?php if (!empty($dbConfig)): ?>
            <p><strong>OctoberCMS Database:</strong> Detected</p>
            <p><small>Host: <?php echo $dbConfig['host'] ?? 'Not detected'; ?><br>
            Database: <?php echo $dbConfig['database'] ?? 'Not detected'; ?><br>
            Username: <?php echo $dbConfig['username'] ?? 'Not detected'; ?></small></p>
            <?php endif; ?>
            
            <?php if (!empty($versionMessage)): ?>
            <p><strong>PHP Compatibility:</strong> <?php echo $versionMessage; ?></p>
            <?php endif; ?>
            
        </div>
        
        <?php if (!empty($result)): ?>
            <?php if (strpos($result, 'failed') !== false || strpos($result, 'error') !== false || strpos($result, 'Error') !== false): ?>
                <div class="error">
                    <h3>Error:</h3>
                    <?php echo $result; ?>
                </div>
            <?php else: ?>
                <div class="result">
                    <h3>Success:</h3>
                    <p><?php echo $result; ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Zip Section -->
        <div class="section">
            <h2>Zip All Files and Folders</h2>
            <p>This will create a zip archive of all files and folders <?php echo $inMigrationFolder ? 'in the parent directory' : 'in the current directory'; ?> (excluding cache, resized files, and the migration folder).</p>
            <form method="post">
                <input type="hidden" name="action" value="zip">
                <button type="submit">Create Zip Archive</button>
            </form>
        </div>
        
        <!-- Unzip Section -->
        <div class="section">
            <h2>Unzip Archive</h2>
            <p>This will extract a zip archive to the <?php echo $inMigrationFolder ? 'parent directory' : 'current directory'; ?>.</p>
            <p><small>Note: You can upload files up to 100MB in size.</small></p>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="unzip">
                <div class="form-group">
                    <label for="zipfile">Upload Zip File (max 100MB):</label>
                    <input type="file" id="zipfile" name="zipfile">
                </div>
                <p>OR</p>
                <div class="form-group">
                    <label for="zipfilename">Specify Existing Zip Filename:</label>
                    <input type="text" id="zipfilename" name="zipfilename" placeholder="example.zip">
                </div>
                <button type="submit">Extract Zip Archive</button>
            </form>
        </div>
        
        <!-- Database Dump Section -->
        <div class="section">
            <h2>Create Database Dump</h2>
            <p>This will create a SQL dump of the OctoberCMS database.</p>
            <form method="post">
                <input type="hidden" name="action" value="dbdump">
                <div class="form-group">
                    <label for="host">Database Host:</label>
                    <input type="text" id="host" name="host" value="<?php echo htmlspecialchars($dbHost); ?>" placeholder="localhost or hostname:port">
                    <?php if (!empty($dbConfig['host'])): ?>
                    <span class="detected">(Detected from OctoberCMS)</span>
                    <?php endif; ?>
                    <small style="display: block; margin-top: 5px; color: #666;">For remote connections, you may need to specify the port: hostname:3306</small>
                </div>
                <div class="form-group">
                    <label for="username">Database Username:</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($dbUsername); ?>">
                    <?php if (!empty($dbConfig['username'])): ?>
                    <span class="detected">(Detected from OctoberCMS)</span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="password">Database Password:</label>
                    <input type="password" id="password" name="password" value="<?php echo htmlspecialchars($dbPassword); ?>">
                    <?php if (!empty($dbConfig['password'])): ?>
                    <span class="detected">(Detected from OctoberCMS)</span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="database">Database Name:</label>
                    <input type="text" id="database" name="database" value="<?php echo htmlspecialchars($dbName); ?>">
                    <?php if (!empty($dbConfig['database'])): ?>
                    <span class="detected">(Detected from OctoberCMS)</span>
                    <?php endif; ?>
                </div>
                <button type="submit">Create Database Dump</button>
            </form>
        </div>
        
        <!-- Available Files Section -->
        <div class="section">
            <h2>Available Files for Download</h2>
            <?php
            $availableFiles = listAvailableFiles($currentPath);
            if (empty($availableFiles)) {
                echo "<p>No zip or database files found in the current directory.</p>";
            } else {
                echo "<table style='width:100%; border-collapse: collapse;'>";
                echo "<tr style='background-color: #f2f2f2;'>";
                echo "<th style='text-align: left; padding: 8px; border: 1px solid #ddd;'>Filename</th>";
                echo "<th style='text-align: left; padding: 8px; border: 1px solid #ddd;'>Type</th>";
                echo "<th style='text-align: left; padding: 8px; border: 1px solid #ddd;'>Size</th>";
                echo "<th style='text-align: left; padding: 8px; border: 1px solid #ddd;'>Date</th>";
                echo "<th style='text-align: left; padding: 8px; border: 1px solid #ddd;'>Action</th>";
                echo "</tr>";
                
                foreach ($availableFiles as $file) {
                    echo "<tr>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$file['name']}</td>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . strtoupper($file['type']) . "</td>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . formatFileSize($file['size']) . "</td>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$file['date']}</td>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>";
                    echo "<a href='?download=" . urlencode($file['name']) . "' style='color: #4CAF50; text-decoration: none;'>Download</a>";
                    echo "</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
            }
            
            // No need for formatFileSize function here as it's now defined globally
            ?>
        </div>
        
        <!-- Footer -->
        <div style="margin-top: 30px; text-align: center; font-size: 0.9em; color: #666; border-top: 1px solid #ddd; padding-top: 15px;">
            <p>
                <a href="https://buymeacoffee.com/alienatedalien" target="_blank" style="color: #4CAF50; text-decoration: none;">Buy me a coffee</a> if you like this script
            </p>
            <p style="font-size: 0.8em;">
                This script is released under the MIT License. Feel free to use, modify, and distribute it as needed.
            </p>
        </div>
    </div>
</body>
</html>