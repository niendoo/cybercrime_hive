<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__) . '/config/database.php';
    require_once dirname(__DIR__) . '/config/config.php';
    require_once dirname(__DIR__) . '/includes/functions.php';
    require_once dirname(__DIR__) . '/includes/id_generator.php';
} catch (Exception $e) {
    die("Configuration error: " . $e->getMessage());
} catch (Error $e) {
    die("Fatal error: " . $e->getMessage());
}

// Check if user is logged in
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    // Fallback SITE_URL if not defined
    $site_url = defined('SITE_URL') ? SITE_URL : 'http://' . $_SERVER['HTTP_HOST'];
    
    // Store current page to redirect after login
    $_SESSION['redirect_after_login'] = $site_url . '/reports/submit.php';
    
    // Redirect to login page
    header("Location: " . $site_url . "/auth/login.php");
    exit();
}

$error_message = '';
$success_message = '';
$tracking_code = '';

// Check for success in URL parameters
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = 'Report submitted successfully!';
    $tracking_code = isset($_GET['tracking_code']) ? $_GET['tracking_code'] : '';
}

// Check for attachment error in URL parameters
if (isset($_GET['attachment_error'])) {
    $error_message = urldecode($_GET['attachment_error']);
}

// Process report submission form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);
    $category = sanitize_input($_POST['category']);
    $incident_date = sanitize_input($_POST['incident_date']);
    $region_id = intval($_POST['region']);
    $district_id = intval($_POST['district']);
    $user_id = $_SESSION['user_id'];
    
    // Validate inputs
    if (empty($title) || empty($description) || empty($category) || empty($incident_date) || empty($region_id) || empty($district_id)) {
        $error_message = 'All fields are required.';
    } else {
        // Generate unique tracking code
        $tracking_code = generate_tracking_code();
        
        // Format the date correctly for MySQL
        $incident_timestamp = date('Y-m-d H:i:s', strtotime($incident_date));
        $created_at = date('Y-m-d H:i:s');
        $updated_at = $created_at;
        
        // Insert report into database with robust ID generation
        $conn = get_database_connection();
        
        // Log debug information for production troubleshooting
        $debug_info = [
            'user_id' => $user_id,
            'title' => $title,
            'category' => $category,
            'tracking_code' => $tracking_code,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        error_log("Report submission attempt: " . json_encode($debug_info));
        
        try {
            // Use the robust ID generator for production-safe insertion
            $id_generator = new IdGenerator($conn);
            
            // Prepare data for insertion
            $report_data = [
                'user_id' => $user_id,
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'incident_date' => $incident_timestamp,
                'region_id' => $region_id,
                'district_id' => $district_id,
                'tracking_code' => $tracking_code,
                'created_at' => $created_at,
                'updated_at' => $updated_at
            ];
            
            // First attempt: Try normal AUTO_INCREMENT insertion
            $stmt = $conn->prepare("INSERT INTO reports (user_id, title, description, category, incident_date, region_id, district_id, tracking_code, created_at, updated_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if (!$stmt) {
                throw new Exception('Database prepare error: ' . $conn->error);
            }
            
            $stmt->bind_param("isssiissss", $user_id, $title, $description, $category, $incident_timestamp, $region_id, $district_id, $tracking_code, $created_at, $updated_at);
            
            if ($stmt->execute()) {
                $report_id = $conn->insert_id;
                
                // Check if AUTO_INCREMENT worked properly
                if ($report_id > 0) {
                    error_log("Report inserted successfully with AUTO_INCREMENT - ID: $report_id, Tracking: $tracking_code");
                } else {
                    // AUTO_INCREMENT failed, use manual ID generation
                    error_log("AUTO_INCREMENT failed (ID: $report_id), switching to manual ID generation");
                    $stmt->close();
                    
                    // Generate manual ID and insert with explicit ID
                    $manual_id = $id_generator->getNextId('reports', 'report_id');
                    
                    $stmt = $conn->prepare("INSERT INTO reports (report_id, user_id, title, description, category, incident_date, region_id, district_id, tracking_code, created_at, updated_at) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    if (!$stmt) {
                        throw new Exception('Database prepare error for manual ID: ' . $conn->error);
                    }
                    
                    $stmt->bind_param("iisssiissss", $manual_id, $user_id, $title, $description, $category, $incident_timestamp, $region_id, $district_id, $tracking_code, $created_at, $updated_at);
                    
                    if ($stmt->execute()) {
                        $report_id = $manual_id;
                        error_log("Report inserted successfully with manual ID - ID: $report_id, Tracking: $tracking_code");
                    } else {
                        throw new Exception('Manual ID insertion failed: ' . $stmt->error);
                    }
                }
            } else {
                throw new Exception('Initial insertion failed: ' . $stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            error_log("Report submission error: " . $e->getMessage());
            $error_message = 'Failed to submit report. Please try again.';
            if (isset($stmt)) $stmt->close();
            $conn->close();
            goto skip_success_redirect;
        }
        
        // Handle file attachments
        $attachment_error = '';
        if (!empty($_FILES['attachments']['name'][0])) {
            $attachments_status = handle_attachments($report_id);
            if (!$attachments_status['success']) {
                $attachment_error = '&attachment_error=' . urlencode('There was an issue with one or more attachments: ' . $attachments_status['message']);
            }
        }
        
        // Send notification
        $notification_message = "Your cybercrime report has been submitted successfully. Your tracking code is: $tracking_code. Use this code to track the status of your report.";
        create_notification($report_id, $user_id, 'Email', $notification_message);
        
        // Redirect to the same page with success parameters to maintain the success message
        $site_url = defined('SITE_URL') ? SITE_URL : 'http://' . $_SERVER['HTTP_HOST'];
        header("Location: " . $site_url . "/reports/submit.php?success=1&tracking_code=" . $tracking_code . $attachment_error);
        exit();
        
        skip_success_redirect:
        
        $conn->close();
    }
}

/**
 * Handle file attachments for a report
 * @param int $report_id Report ID
 * @return array Status array with success flag and message
 */
function handle_attachments($report_id) {
    $allowed_extensions = ALLOWED_EXTENSIONS;
    $max_file_size = MAX_FILE_SIZE;
    $upload_path = UPLOAD_PATH_ATTACHMENTS;
    $status = ['success' => true, 'message' => ''];
    
    // Make sure the upload directory exists
    if (!file_exists($upload_path)) {
        mkdir($upload_path, 0755, true);
    }
    
    // Connect to database
    $conn = get_database_connection();
    
    // Loop through each uploaded file
    foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['attachments']['name'][$key];
            $file_size = $_FILES['attachments']['size'][$key];
            $file_tmp = $_FILES['attachments']['tmp_name'][$key];
            $file_type = $_FILES['attachments']['type'][$key];
            
            // Get file extension
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Check file extension
            if (!in_array($file_ext, $allowed_extensions)) {
                $status['success'] = false;
                $status['message'] .= "File type not allowed: $file_name. ";
                continue;
            }
            
            // Check file size
            if ($file_size > $max_file_size) {
                $status['success'] = false;
                $status['message'] .= "File too large: $file_name. ";
                continue;
            }
            
            // Generate unique file name
            $new_file_name = 'attachment_' . $report_id . '_' . uniqid() . '.' . $file_ext;
            $file_path = $upload_path . $new_file_name;
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $file_path)) {
                // Save file path to database
                $uploaded_at = date('Y-m-d H:i:s');
                $rel_path = '/cybercrime_hive/uploads/attachments/' . $new_file_name; // Store relative path
                
                $stmt = $conn->prepare("INSERT INTO attachments (report_id, file_path, uploaded_at) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $report_id, $rel_path, $uploaded_at);
                
                if (!$stmt->execute()) {
                    $status['success'] = false;
                    $status['message'] .= "Database error for file: $file_name. ";
                }
                
                $stmt->close();
            } else {
                $status['success'] = false;
                $status['message'] .= "Failed to upload file: $file_name. ";
            }
        } else if ($_FILES['attachments']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
            $status['success'] = false;
            $status['message'] .= "Upload error for file #{$key}. ";
        }
    }
    
    $conn->close();
    return $status;
}

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row my-4 px-4">
    <div class="col-12 col-md-10 col-lg-8 col-xl-7 mx-auto">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo defined('SITE_URL') ? SITE_URL : 'http://' . $_SERVER['HTTP_HOST']; ?>/index.php">Home</a></li>
                <li class="breadcrumb-item active">Submit Report</li>
            </ol>
        </nav>
        
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-file-alt me-2"></i>Submit Cybercrime Report</h4>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="card border-danger">
                        <div class="card-body">
                            <div class="text-danger"><?php echo $error_message; ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="card border-success">
                        <div class="card-body">
                            <h5 class="card-title text-success"><?php echo $success_message; ?></h5>
                            <p class="card-text">Your tracking code is: <strong><?php echo $tracking_code; ?></strong></p>
                            <p class="card-text">Please save this code to track the status of your report.</p>
                            <div class="mt-3">
                                <a href="<?php echo defined('SITE_URL') ? SITE_URL : 'http://' . $_SERVER['HTTP_HOST']; ?>/reports/track.php?code=<?php echo $tracking_code; ?>" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Track This Report
                                </a>
                                <a href="<?php echo defined('SITE_URL') ? SITE_URL : 'http://' . $_SERVER['HTTP_HOST']; ?>/reports/submit.php" class="btn btn-outline-primary ms-2">
                                    <i class="fas fa-plus me-2"></i>Submit Another Report
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card border-info">
                        <div class="card-body">
                            <h5 class="card-title text-info"><i class="fas fa-info-circle me-2"></i>Reporting Instructions</h5>
                            <p class="card-text">Please provide as much detail as possible about the cybercrime incident. All fields marked with an asterisk (*) are required.</p>
                            <p class="card-text">You can attach relevant files (images, screenshots, documents) to support your report.</p>
                        </div>
                    </div>
                    
                    <form method="post" action="" enctype="multipart/form-data" id="reportForm" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="title" class="form-label">Report Title *</label>
                            <input type="text" class="form-control" id="title" name="title" required maxlength="150" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                            <div class="form-text">Provide a brief, descriptive title for your report.</div>
                            <div class="invalid-feedback">Please provide a report title.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="6" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            <div class="form-text">Describe what happened in detail, including any relevant information such as websites, email addresses, or suspicious activities.</div>
                            <div class="invalid-feedback">Please describe the incident.</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Category *</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="" selected disabled>-- Select Category --</option>
                                    <?php
                                    // Fetch all available categories from the database
                                    $conn = get_database_connection();
                                    $query = "SHOW COLUMNS FROM reports LIKE 'category'";
                                    $result = $conn->query($query);
                                    if ($result && $result->num_rows > 0) {
                                        $row = $result->fetch_assoc();
                                        // Parse ENUM values: ENUM('val1','val2',...)
                                        $enum_str = $row['Type'];
                                        preg_match_all("/\\'(.*?)\\'/", $enum_str, $matches);
                                        
                                        // Group categories by type for better organization
                                        $categories = [];
                                        $category_groups = [
                                            'Fraud & Financial' => ['Online Banking Fraud', 'Credit Card Fraud', 'Identity Theft', 'Phishing', 
                                               'Advance Fee Fraud', 'Investment Scam', 'Online Auction Fraud', 'Fake Job Offer Scam', 
                                               'Lottery Scam', 'Ransomware Attack', 'Fraud'],
                                            'Email & Communication' => ['Email Spoofing', 'Business Email Compromise', 'SMS Phishing (Smishing)', 
                                               'Voice Phishing (Vishing)', 'Social Engineering', 'Fake Social Media Profiles', 'Spam Messaging'],
                                            'Hacking & Access' => ['Unauthorized Access', 'Password Cracking', 'Website Defacement', 
                                               'Database Breach', 'Brute Force Attack', 'Keylogging', 'Malware Distribution', 
                                               'Trojan/Rootkit Infection', 'Network Intrusion', 'Hacking'],
                                            'Impersonation & Deception' => ['Social Media Account Hijacking', 'Impersonation', 
                                               'Online Dating Scam', 'Deepfake Distribution', 'Fake Apps or Websites'],
                                            'Harassment & Abuse' => ['Cyberbullying', 'Cyberstalking', 'Online Defamation', 
                                               'Doxxing', 'Revenge Porn', 'Online Threats'],
                                            'Piracy & Content' => ['Software Piracy', 'Illegal Downloads', 'Copyright Infringement', 
                                               'Hacked Game Distribution', 'Cracked Software'],
                                            'DoS Attacks' => ['DDoS Attack', 'Email Bombing', 'Ping of Death', 'Botnet Activity'],
                                            'Dark Web/Illicit' => ['Dark Web Drug Trade', 'Human Trafficking', 'Stolen Data Sales', 
                                               'Weapons Trade', 'Fake Document Sales'],
                                            'Government/Political' => ['Cyber Espionage', 'Hacktivism', 'Disinformation Campaign', 
                                               'Gov Infrastructure Attack', 'State Data Leak'],
                                            'Other' => ['Other']
                                        ];
                                        
                                        if (!empty($matches[1])) {
                                            $all_categories = $matches[1];
                                            
                                            // Output categories grouped by type
                                            foreach ($category_groups as $group => $group_categories) {
                                                echo "<optgroup label='$group'>\n";
                                                foreach ($group_categories as $cat) {
                                                    if (in_array($cat, $all_categories)) {
                                                        $selected = (isset($_POST['category']) && $_POST['category'] === $cat) ? ' selected' : '';
                                                        echo "<option value='$cat'$selected>$cat</option>\n";
                                                    }
                                                }
                                                echo "</optgroup>\n";
                                            }
                                        }
                                    }
                                    $conn->close();
                                    ?>
                                </select>
                                <div class="invalid-feedback">Please select a category.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="incident_date" class="form-label">Incident Date/Time *</label>
                                <input type="datetime-local" class="form-control" id="incident_date" name="incident_date" required value="<?php echo isset($_POST['incident_date']) ? htmlspecialchars($_POST['incident_date']) : ''; ?>">
                                <div class="invalid-feedback">Please specify the incident date and time.</div>
                            </div>
                        </div>
                        
                        <!-- Ghana Location Fields -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="region" class="form-label">Region *</label>
                                <select class="form-select" id="region" name="region" required>
                                    <option value="" selected disabled>-- Select Region --</option>
                                    <?php
                                    require_once __DIR__ . '/../includes/location_data.php';
                                    $regions = get_all_regions();
                                    foreach ($regions as $region) {
                                        echo '<option value="' . htmlspecialchars($region['id']) . '"' . (isset($_POST['region']) && (int)$_POST['region'] === (int)$region['id'] ? ' selected' : '') . '>' . htmlspecialchars($region['name']) . '</option>';
                                    }
                                    ?>
                                </select>
                                <div class="invalid-feedback">Please select a region.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="district" class="form-label">District *</label>
                                <select class="form-select" id="district" name="district" required>
                                    <option value="" selected disabled>-- Select District --</option>
                                </select>
                                <div class="invalid-feedback">Please select a district.</div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="attachments" class="form-label">Attachments</label>
                            <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                            <div class="form-text">
                                Allowed file types: <?php echo implode(', ', ALLOWED_EXTENSIONS); ?><br>
                                Maximum file size: <?php echo MAX_FILE_SIZE / 1024 / 1024; ?> MB
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Submit Report
                            </button>
                        </div>
                    </form>
                    
                    <script>
                    // Dynamic district loading via AJAX
                    document.getElementById('region').addEventListener('change', function() {
                        const regionSelect = this;
                        const districtSelect = document.getElementById('district');
                        const selectedRegion = regionSelect.value;
                        
                        // Clear existing options
                        districtSelect.innerHTML = '<option value="" selected disabled>-- Select District --</option>';
                        
                        if (selectedRegion) {
                            // Fetch districts via AJAX
                            fetch('../includes/get_districts.php?region_id=' + selectedRegion)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success && data.districts) {
                                        data.districts.forEach(function(district) {
                                            const option = document.createElement('option');
                                            option.value = district.id;
                                            option.textContent = district.name;
                                            districtSelect.appendChild(option);
                                        });
                                        districtSelect.disabled = false;
                                    }
                                })
                                .catch(error => {
                                    console.error('Error loading districts:', error);
                                });
                        } else {
                            districtSelect.disabled = true;
                        }
                    });
                    
                    // Initialize district dropdown as disabled
                    document.getElementById('district').disabled = true;

                    // Restore previously selected district after validation error
                    document.addEventListener('DOMContentLoaded', function() {
                        const regionSelect = document.getElementById('region');
                        const districtSelect = document.getElementById('district');
                        const postedRegion = <?php echo isset($_POST['region']) ? (int)$_POST['region'] : 'null'; ?>;
                        const postedDistrict = <?php echo isset($_POST['district']) ? (int)$_POST['district'] : 'null'; ?>;
                        if (postedRegion) {
                            regionSelect.value = String(postedRegion);
                            districtSelect.innerHTML = '<option value="" selected disabled>-- Select District --</option>';
                            fetch('../includes/get_districts.php?region_id=' + postedRegion)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success && data.districts) {
                                        data.districts.forEach(function(district) {
                                            const option = document.createElement('option');
                                            option.value = district.id;
                                            option.textContent = district.name;
                                            districtSelect.appendChild(option);
                                        });
                                        if (postedDistrict) {
                                            districtSelect.value = String(postedDistrict);
                                        }
                                        districtSelect.disabled = false;
                                    }
                                })
                                .catch(error => {
                                    console.error('Error restoring districts:', error);
                                });
                        }
                    });

                    // Bootstrap validation handling
                    (function() {
                        const form = document.getElementById('reportForm');
                        function focusFirstInvalid() {
                            const firstInvalid = form.querySelector(':invalid');
                            if (firstInvalid && typeof firstInvalid.focus === 'function') {
                                firstInvalid.focus();
                            }
                        }
                        form.addEventListener('submit', function(event) {
                            if (!form.checkValidity()) {
                                event.preventDefault();
                                event.stopPropagation();
                                form.classList.add('was-validated');
                                focusFirstInvalid();
                            }
                        }, false);

                        const hadServerError = <?php echo ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($error_message)) ? 'true' : 'false'; ?>;
                        if (hadServerError) {
                            form.classList.add('was-validated');
                            focusFirstInvalid();
                        }
                    })();
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/cybercrime_hive/includes/footer.php'; ?>
