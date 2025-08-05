<?php
session_start();
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (!isset($_SESSION['employee_email']) || !isset($_SESSION['employee_id'])) {
    header("Location: ../index.html?error=Please login first");
    exit();
}

require_once '../db/config.php';

$employee_id = $_SESSION['employee_id'];
$full_name = $_SESSION['employee_name'] ?? '';
$email = $_SESSION['employee_email'] ?? '';

$errors = [];

$upload_dir = '../Uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
if (!is_writable($upload_dir)) {
    $errors[] = "Upload directory is not writable. Please check permissions.";
    error_log("Upload directory ($upload_dir) is not writable for employee_id: $employee_id");
}

// Fetch employee_details
$stmt = $mysqli->prepare("SELECT full_name, email, phone, date_of_joining, department_id, role_id, location_id, bond_years, created_at, status FROM employee_details WHERE employee_id = ?");
if (!$stmt) {
    $errors[] = "Database error: " . $mysqli->error;
} else {
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->num_rows == 1 ? $result->fetch_assoc() : [];
    $stmt->close();
}

// Fetch employee_details2
$stmt = $mysqli->prepare("SELECT * FROM employee_details2 WHERE employee_id = ?");
if (!$stmt) {
    $errors[] = "Database error: " . $mysqli->error;
} else {
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile2 = $result->num_rows == 1 ? $result->fetch_assoc() : [];
    $stmt->close();
}

// Fetch education_details
$education_details = [];
$stmt = $mysqli->prepare("SELECT * FROM employee_education WHERE employee_id = ?");
if ($stmt) {
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $education_details[] = $row;
    }
    error_log("Fetched " . count($education_details) . " education records for employee_id: $employee_id");
    $stmt->close();
}

// Fetch experience_details
$experience_details = [];
$is_fresher_db = 0; // Default to non-fresher
$stmt = $mysqli->prepare("SELECT * FROM employee_experience WHERE employee_id = ?");
if ($stmt) {
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $experience_details[] = $row;
        if (isset($row['is_fresher']) && $row['is_fresher'] == 1) {
            $is_fresher_db = 1;
        }
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = $_POST['step'] ?? '';
    $next_step = $_POST['next_step'] ?? '';

    if ($step === 'basic') {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $marital_status = $_POST['marital_status'] ?? '';
        $blood_group = $_POST['blood_group'] ?? '';
        $emergency_contact_relationship = $_POST['emergency_contact_relationship'] ?? '';
        $emergency_contact_name = $_POST['emergency_contact_name'] ?? '';
        $emergency_phone = $_POST['emergency_phone'] ?? '';
        $alternate_phone = $_POST['alternate_phone'] ?? '';
        $alternate_email = $_POST['alternate_email'] ?? '';
        $address_current = $_POST['address_current'] ?? '';
        $address_permanent = $_POST['address_permanent'] ?? '';
        $same_as_current = isset($_POST['same_as_current']) && $_POST['same_as_current'] === 'on';
        $photo = $_FILES['photo']['name'] ?? '';
        $aadhar = $_FILES['aadhar']['name'] ?? '';
        $pancard = $_FILES['pancard']['name'] ?? '';

        // Combine relationship and name for storage
        $emergency_contact = $emergency_contact_relationship ? "$emergency_contact_relationship: $emergency_contact_name" : $emergency_contact_name;

        // Validate employee_id exists
        $stmt = $mysqli->prepare("SELECT employee_id FROM employee_details WHERE employee_id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $employee_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                $errors[] = "Invalid employee ID: $employee_id";
            }
            $stmt->close();
        } else {
            $errors[] = "Database error checking employee ID: " . $mysqli->error;
        }

        // Validate required fields (excluding files)
        if (empty($date_of_birth) || empty($gender) || empty($marital_status) || empty($blood_group) || 
            empty($emergency_contact_name) || empty($emergency_phone) || empty($alternate_phone) || 
            empty($alternate_email) || empty($address_current) || empty($address_permanent)) {
            $errors[] = "All basic details fields are required except file uploads.";
        } elseif ($same_as_current && $address_permanent !== $address_current) {
            $errors[] = "Permanent address must match current address when 'Same as Current Address' is checked.";
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
            $max_size = 5 * 1024 * 1024;

            // Handle photo upload (optional)
            if (!empty($photo) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                if (!in_array($_FILES['photo']['type'], $allowed_types) || $_FILES['photo']['size'] > $max_size) {
                    $errors[] = "Invalid photo file. Must be JPEG/PNG/PDF and less than 5MB.";
                } else {
                    $photo_path = $upload_dir . uniqid() . '_' . basename($_FILES['photo']['name']);
                    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                        $errors[] = "Failed to upload photo.";
                        error_log("Failed to upload photo for employee_id: $employee_id to $photo_path");
                    } else {
                        $photo = $photo_path;
                    }
                }
            } else {
                $photo = $profile2['photo'] ?? '';
            }

            // Handle aadhar upload (optional)
            if (!empty($aadhar) && $_FILES['aadhar']['error'] === UPLOAD_ERR_OK) {
                if (!in_array($_FILES['aadhar']['type'], $allowed_types) || $_FILES['aadhar']['size'] > $max_size) {
                    $errors[] = "Invalid Aadhar file. Must be JPEG/PNG/PDF and less than 5MB.";
                } else {
                    $aadhar_path = $upload_dir . uniqid() . '_' . basename($_FILES['aadhar']['name']);
                    if (!move_uploaded_file($_FILES['aadhar']['tmp_name'], $aadhar_path)) {
                        $errors[] = "Failed to upload Aadhar.";
                        error_log("Failed to upload Aadhar for employee_id: $employee_id to $aadhar_path");
                    } else {
                        $aadhar = $aadhar_path;
                    }
                }
            } else {
                $aadhar = $profile2['aadhar'] ?? '';
            }

            // Handle pancard upload (optional)
            if (!empty($pancard) && $_FILES['pancard']['error'] === UPLOAD_ERR_OK) {
                if (!in_array($_FILES['pancard']['type'], $allowed_types) || $_FILES['pancard']['size'] > $max_size) {
                    $errors[] = "Invalid Pancard file. Must be JPEG/PNG/PDF and less than 5MB.";
                } else {
                    $pancard_path = $upload_dir . uniqid() . '_' . basename($_FILES['pancard']['name']);
                    if (!move_uploaded_file($_FILES['pancard']['tmp_name'], $pancard_path)) {
                        $errors[] = "Failed to upload Pancard.";
                        error_log("Failed to upload Pancard for employee_id: $employee_id to $pancard_path");
                    } else {
                        $pancard = $pancard_path;
                    }
                }
            } else {
                $pancard = $profile2['pancard'] ?? '';
            }

            if (empty($errors)) {
                $stmt = $mysqli->prepare("INSERT INTO employee_details2 (employee_id, date_of_birth, gender, marital_status, blood_group, emergency_contact, emergency_phone, alternate_phone, alternate_email, address_current, address_permanent, photo, aadhar, pancard) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE date_of_birth = VALUES(date_of_birth), gender = VALUES(gender), marital_status = VALUES(marital_status), blood_group = VALUES(blood_group), emergency_phone = VALUES(emergency_phone), emergency_contact = VALUES(emergency_contact), alternate_phone = VALUES(alternate_phone), alternate_email = VALUES(alternate_email), address_current = VALUES(address_current), address_permanent = VALUES(address_permanent), photo = VALUES(photo), aadhar = VALUES(aadhar), pancard = VALUES(pancard), updated_at = NOW()");
                if ($stmt) {
                    $stmt->bind_param("ssssssssssssss", $employee_id, $date_of_birth, $gender, $marital_status, $blood_group, $emergency_contact, $emergency_phone, $alternate_phone, $alternate_email, $address_current, $address_permanent, $photo, $aadhar, $pancard);
                    if ($stmt->execute()) {
                        error_log("Basic details saved/updated for employee_id: $employee_id, navigating to: $next_step");
                        $_SESSION['active_form'] = $next_step;
                        session_write_close();
                        header("Location: my_profile.php?form=$next_step");
                        exit();
                    } else {
                        $errors[] = "Error saving basic details: " . $stmt->error;
                        error_log("Error saving basic details for employee_id: $employee_id - " . $stmt->error);
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Database error preparing statement: " . $mysqli->error;
                    error_log("Database error preparing statement for employee_id: $employee_id - " . $mysqli->error);
                }
            }
        }
    }

    if ($step === 'education') {
        $graduations = $_POST['graduation'] ?? [];
        $branches = $_POST['branch'] ?? [];
        $scores = $_POST['score'] ?? [];
        $passed_out_years = $_POST['passed_out_year'] ?? [];
        $certificates = $_FILES['certificate'] ?? [];

        $has_valid_row = false;
        for ($i = 0; $i < count($graduations); $i++) {
            if (!empty($graduations[$i]) && !empty($branches[$i]) && !empty($scores[$i]) && !empty($passed_out_years[$i])) {
                $has_valid_row = true;
                break;
            }
        }

        if (!$has_valid_row) {
            $errors[] = "Please fill at least one complete education row (graduation, branch, score, passed out year).";
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
            $max_size = 5 * 1024 * 1024;

            $stmt = $mysqli->prepare("DELETE FROM employee_education WHERE employee_id = ?");
            if ($stmt) {
                $stmt->bind_param("s", $employee_id);
                $stmt->execute();
                $stmt->close();

                for ($i = 0; $i < count($graduations); $i++) {
                    if (!empty($graduations[$i]) && !empty($branches[$i]) && !empty($scores[$i]) && !empty($passed_out_years[$i])) {
                        $certificate = '';
                        if (isset($certificates['name'][$i]) && $certificates['error'][$i] === UPLOAD_ERR_OK) {
                            if (!in_array($certificates['type'][$i], $allowed_types) || $certificates['size'][$i] > $max_size) {
                                $errors[] = "Invalid certificate file at row " . ($i + 1) . ". Must be JPEG/PNG/PDF and less than 5MB.";
                            } else {
                                $certificate = $upload_dir . uniqid() . '_' . basename($certificates['name'][$i]);
                                if (!move_uploaded_file($certificates['tmp_name'][$i], $certificate)) {
                                    $errors[] = "Failed to upload certificate at row " . ($i + 1) . ".";
                                    error_log("Failed to upload certificate for employee_id: $employee_id at row " . ($i + 1) . " to $certificate");
                                }
                            }
                        } else {
                            $certificate = $education_details[$i]['certificate'] ?? '';
                        }

                        if (empty($errors)) {
                            $stmt = $mysqli->prepare("INSERT INTO employee_education (employee_id, graduation, branch, score, passed_out_year, upload_certificate) VALUES (?, ?, ?, ?, ?, ?)");
                            if ($stmt) {
                                $stmt->bind_param("ssssss", $employee_id, $graduations[$i], $branches[$i], $scores[$i], $passed_out_years[$i], $certificate);
                                if (!$stmt->execute()) {
                                    $errors[] = "Error saving education details at row " . ($i + 1) . ": " . $stmt->error;
                                    error_log("Error saving education details for employee_id: $employee_id at row " . ($i + 1) . " - " . $stmt->error);
                                }
                                $stmt->close();
                            } else {
                                $errors[] = "Database error preparing education insert: " . $mysqli->error;
                            }
                        }
                    }
                }
                if (empty($errors) && $next_step) {
                    error_log("Education details saved for employee_id: $employee_id, navigating to: $next_step");
                    $_SESSION['active_form'] = $next_step;
                    session_write_close();
                    header("Location: my_profile.php?form=$next_step");
                    exit();
                }
            } else {
                $errors[] = "Database error deleting education records: " . $mysqli->error;
                error_log("Database error deleting education records for employee_id: $employee_id - " . $mysqli->error);
            }
        }
    }

    if ($step === 'experience') {
        $action = $_POST['action'] ?? 'save';
        $companies = $_POST['company'] ?? [];
        $roles = $_POST['role'] ?? [];
        $ctcs = $_POST['ctc'] ?? [];
        $start_dates = $_POST['start_date'] ?? [];
        $end_dates = $_POST['end_date'] ?? [];
        $documents = $_FILES['document'] ?? [];
        $is_fresher = isset($_POST['fresher']) && $_POST['fresher'] === 'on' ? 1 : 0;

        $stmt = $mysqli->prepare("DELETE FROM employee_experience WHERE employee_id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $employee_id);
            $stmt->execute();
            $stmt->close();

            if ($is_fresher) {
                // Insert a single row with is_fresher = 1 and null for other fields
                $stmt = $mysqli->prepare("INSERT INTO employee_experience (employee_id, is_fresher) VALUES (?, ?)");
                if ($stmt) {
                    $stmt->bind_param("si", $employee_id, $is_fresher);
                    if (!$stmt->execute()) {
                        $errors[] = "Error saving fresher status: " . $stmt->error;
                        error_log("Error saving fresher status for employee_id: $employee_id - " . $stmt->error);
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Database error preparing fresher insert: " . $mysqli->error;
                }
            } else {
                $has_valid_row = false;
                $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
                $max_size = 5 * 1024 * 1024;

                for ($i = 0; $i < count($companies); $i++) {
                    if (!empty($companies[$i]) && !empty($roles[$i]) && !empty($ctcs[$i]) && !empty($start_dates[$i]) && !empty($end_dates[$i])) {
                        $has_valid_row = true;
                        $document = '';
                        if (isset($documents['name'][$i]) && $documents['error'][$i] === UPLOAD_ERR_OK) {
                            if (!in_array($documents['type'][$i], $allowed_types) || $documents['size'][$i] > $max_size) {
                                $errors[] = "Invalid document file at row " . ($i + 1) . ". Must be JPEG/PNG/PDF and less than 5MB.";
                            } else {
                                $document = $upload_dir . uniqid() . '_' . basename($documents['name'][$i]);
                                if (!move_uploaded_file($documents['tmp_name'][$i], $document)) {
                                    $errors[] = "Failed to upload document at row " . ($i + 1) . ".";
                                    error_log("Failed to upload document for employee_id: $employee_id at row " . ($i + 1) . " to $document");
                                }
                            }
                        } else {
                            $document = $experience_details[$i]['document'] ?? '';
                        }

                        if (empty($errors)) {
                            $stmt = $mysqli->prepare("INSERT INTO employee_experience (employee_id, company, role, ctc, start_date, end_date, document, is_fresher) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            if ($stmt) {
                                $zero = 0;
                                $stmt->bind_param("sssssssi", $employee_id, $companies[$i], $roles[$i], $ctcs[$i], $start_dates[$i], $end_dates[$i], $document, $zero);
                                if (!$stmt->execute()) {
                                    $errors[] = "Error saving experience details at row " . ($i + 1) . ": " . $stmt->error;
                                    error_log("Error saving experience details for employee_id: $employee_id at row " . ($i + 1) . " - " . $stmt->error);
                                }
                                $stmt->close();
                            } else {
                                $errors[] = "Database error preparing experience insert: " . $mysqli->error;
                            }
                        }
                    }
                }
                if (!$has_valid_row && $action === 'submit') {
                    $errors[] = "Please fill at least one complete experience row (company, role, CTC, start date, end date) unless marked as fresher.";
                }
            }

            if (empty($errors)) {
                error_log("Experience details saved for employee_id: $employee_id, action: $action, fresher: " . ($is_fresher ? 'yes' : 'no'));
                // Refresh experience_details
                $stmt = $mysqli->prepare("SELECT * FROM employee_experience WHERE employee_id = ?");
                if ($stmt) {
                    $stmt->bind_param("s", $employee_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $experience_details = [];
                    $is_fresher_db = 0;
                    while ($row = $result->fetch_assoc()) {
                        $experience_details[] = $row;
                        if (isset($row['is_fresher']) && $row['is_fresher'] == 1) {
                            $is_fresher_db = 1;
                        }
                    }
                    $stmt->close();
                }
                $_SESSION['active_form'] = 'experience-form';
                if ($action === 'submit') {
                    $_SESSION['show_popup'] = true;
                    error_log("Popup flag set for employee_id: $employee_id");
                } else {
                    unset($_SESSION['show_popup']);
                    error_log("Popup flag unset for employee_id: $employee_id");
                }
                session_write_close();
                header("Location: my_profile.php?form=experience-form");
                exit();
            } else {
                error_log("Errors occurred: " . implode(", ", $errors));
            }
        } else {
            $errors[] = "Database error deleting experience records: " . $mysqli->error;
            error_log("Database error deleting experience records for employee_id: $employee_id - " . $mysqli->error);
        }
    }
}

$mysqli->close();

// Progress calculation
$progress = 25; // Organization details always complete
$basic_complete = !empty($profile2['date_of_birth']) && 
                  !empty($profile2['gender']) && 
                  !empty($profile2['marital_status']) && 
                  !empty($profile2['blood_group']) && 
                  !empty($profile2['emergency_contact']) && 
                  !empty($profile2['emergency_phone']) && 
                  !empty($profile2['alternate_phone']) && 
                  !empty($profile2['alternate_email']) && 
                  !empty($profile2['address_current']) && 
                  !empty($profile2['address_permanent']);
if ($basic_complete) $progress += 25;

$education_complete = !empty($education_details) && array_filter($education_details, fn($edu) => !empty($edu['graduation']) && !empty($edu['branch']) && !empty($edu['score']) && !empty($edu['passed_out_year']));
if ($education_complete) $progress += 25;

$is_fresher = isset($_POST['fresher']) && $_POST['fresher'] === 'on' ? 1 : $is_fresher_db;
$experience_complete = $is_fresher || (!empty($experience_details) && array_filter($experience_details, fn($exp) => !empty($exp['company']) && !empty($exp['role']) && !empty($exp['ctc']) && !empty($exp['start_date']) && !empty($exp['end_date'])));
if ($experience_complete) $progress += 25;

$active_form = $_SESSION['active_form'] ?? $_GET['form'] ?? 'organization-form';
$show_popup = isset($_SESSION['show_popup']) && $_SESSION['show_popup'] === true;
if ($show_popup) {
    unset($_SESSION['show_popup']);
    error_log("Popup displayed and flag cleared for employee_id: $employee_id");
}

// Split emergency_contact into relationship and name for display
$emergency_contact_relationship = '';
$emergency_contact_name = '';
if (!empty($profile2['emergency_contact'])) {
    $parts = explode(': ', $profile2['emergency_contact'], 2);
    $emergency_contact_relationship = $parts[0] ?? '';
    $emergency_contact_name = $parts[1] ?? $profile2['emergency_contact'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/my_profile.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
    <a href="http://localhost/allyted%20project/Employee/dashboard.php" class="back-to-dashboard">
        <svg viewBox="0 0 24 24">
            <path d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6z"/>
        </svg>
        Back to Dashboard
    </a>

    <div class="container mx-auto">
        <!-- Navbar -->
        <div class="navbar">
            <div class="flex justify-between items-center">
                <div class="flex-1 text-center">
                    <div class="step-circle mx-auto completed <?php echo $active_form === 'organization-form' ? 'active' : ''; ?>" onclick="navigateToForm('organization-form')" aria-label="Employee Organization Form">1</div>
                    <span class="block mt-1 text-sm font-medium text-gray-800">Emp Organization</span>
                </div>
                <div class="flex-1 text-center">
                    <div class="step-circle mx-auto <?php echo $basic_complete ? 'completed' : ''; ?> <?php echo $active_form === 'basic-form' ? 'active' : ''; ?>" onclick="navigateToForm('basic-form')" aria-label="Basic Details Form">2</div>
                    <span class="block mt-1 text-sm font-medium text-gray-800">Basic</span>
                </div>
                <div class="flex-1 text-center">
                    <div class="step-circle mx-auto <?php echo $education_complete ? 'completed' : ''; ?> <?php echo $active_form === 'education-form' ? 'active' : ''; ?>" onclick="navigateToForm('education-form')" aria-label="Education Details Form">3</div>
                    <span class="block mt-1 text-sm font-medium text-gray-800">Education</span>
                </div>
                <div class="flex-1 text-center">
                    <div class="step-circle mx-auto <?php echo $experience_complete ? 'completed' : ''; ?> <?php echo $active_form === 'experience-form' ? 'active' : ''; ?>" onclick="navigateToForm('experience-form')" aria-label="Experience Details Form">4</div>
                    <span class="block mt-1 text-sm font-medium text-gray-800">Experience</span>
                </div>
            </div>
            <div class="progress-percentage" id="progress-percentage" style="--progress-width: <?php echo $progress; ?>%;"><?php echo $progress; ?>%</div>
            <div class="progress-container" data-progress="<?php echo $progress; ?>">
                <div class="w-full h-full bg-gray-200 rounded-full">
                    <div class="progress-line" id="progress-bar" data-progress="<?php echo $progress; ?>" style="width: <?php echo $progress; ?>%;"></div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($errors)): ?>
            <div class="mt-4 bg-red-50 border-l-4 border-red-500 text-red-700 p-3 rounded">
                <?php foreach ($errors as $error): ?>
                    <p class="error"><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Success Popup -->
        <?php if ($show_popup): ?>
            <div class="popup active">
                <div class="popup-content">
                    <h3>Profile Submitted Successfully!</h3>
                    <p>Your profile has been completed and submitted successfully.</p>
                    <a href="http://localhost/allyted%20project/Employee/dashboard.php">Back to Dashboard</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form Sections -->
        <div class="relative mt-4 flex-grow">
            <!-- Organization Details -->
            <div id="organization-form" class="form-section <?php echo $active_form === 'organization-form' ? 'active' : 'hidden'; ?>">
                <h4 class="section-header">Organization Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Full Name</label>
                        <input type="text" value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-sm" readonly aria-label="Full Name">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-sm" readonly aria-label="Email">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Phone</label>
                        <input type="text" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-sm" readonly aria-label="Phone">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Date of Joining</label>
                        <input type="date" value="<?php echo htmlspecialchars($profile['date_of_joining'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-sm" readonly aria-label="Date of Joining">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Department ID</label>
                        <input type="text" value="<?php echo htmlspecialchars($profile['department_id'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-sm" readonly aria-label="Department ID">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Role ID</label>
                        <input type="text" value="<?php echo htmlspecialchars($profile['role_id'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-sm" readonly aria-label="Role ID">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Location ID</label>
                        <input type="text" value="<?php echo htmlspecialchars($profile['location_id'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-sm" readonly aria-label="Location ID">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Bond Years</label>
                        <input type="text" value="<?php echo htmlspecialchars($profile['bond_years'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-sm" readonly aria-label="Bond Years">
                    </div>
                </div>
                <div class="text-center mt-6">
                    <button type="button" onclick="navigateToForm('basic-form')" class="save-button"><span>Next</span></button>
                </div>
            </div>

            <!-- Basic Details -->
            <form id="basic-form" action="my_profile.php" method="POST" enctype="multipart/form-data" class="form-section <?php echo $active_form === 'basic-form' ? 'active' : 'hidden'; ?>">
                <input type="hidden" name="step" value="basic">
                <input type="hidden" name="next_step" value="education-form">
                <h4 class="section-header">Basic Details</h4>
                <div class="input-grid grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Date of Birth <span class="text-red-500">*</span></label>
                        <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($profile2['date_of_birth'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md text-sm" required aria-label="Date of Birth">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Gender <span class="text-red-500">*</span></label>
                        <select name="gender" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md text-sm" required aria-label="Gender">
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo (isset($profile2['gender']) && $profile2['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (isset($profile2['gender']) && $profile2['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo (isset($profile2['gender']) && $profile2['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Marital Status <span class="text-red-500">*</span></label>
                        <select name="marital_status" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md text-sm" required aria-label="Marital Status">
                            <option value="">Select Marital Status</option>
                            <option value="Single" <?php echo (isset($profile2['marital_status']) && $profile2['marital_status'] === 'Single') ? 'selected' : ''; ?>>Single</option>
                            <option value="Married" <?php echo (isset($profile2['marital_status']) && $profile2['marital_status'] === 'Married') ? 'selected' : ''; ?>>Married</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Blood Group <span class="text-red-500">*</span></label>
                        <input type="text" name="blood_group" value="<?php echo htmlspecialchars($profile2['blood_group'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md text-sm" required aria-label="Blood Group">
                    </div>
                </div>
                <div class="input-grid grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Alternate Phone <span class="text-red-500">*</span></label>
                        <input type="tel" name="alternate_phone" value="<?php echo htmlspecialchars($profile2['alternate_phone'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md text-sm" required aria-label="Alternate Phone Number">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Alternate Email <span class="text-red-500">*</span></label>
                        <input type="email" name="alternate_email" value="<?php echo htmlspecialchars($profile2['alternate_email'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md text-sm" required aria-label="Alternate Email ID">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Emergency Contact <span class="text-red-500">*</span></label>
                        <div class="emergency-contact-container mt-1">
                            <select name="emergency_contact_relationship" class="px-3 py-2 border border-gray-200 rounded-md text-sm" required aria-label="Emergency Contact Relationship">
                                <option value="">Select</option>
                                <option value="Mother" <?php echo ($emergency_contact_relationship === 'Mother') ? 'selected' : ''; ?>>Mother</option>
                                <option value="Father" <?php echo ($emergency_contact_relationship === 'Father') ? 'selected' : ''; ?>>Father</option>
                                <option value="Sister" <?php echo ($emergency_contact_relationship === 'Sister') ? 'selected' : ''; ?>>Sister</option>
                                <option value="Brother" <?php echo ($emergency_contact_relationship === 'Brother') ? 'selected' : ''; ?>>Brother</option>
                            </select>
                            <input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($emergency_contact_name); ?>" class="px-3 py-2 border border-gray-200 rounded-md text-sm flex-1" placeholder="Enter Name" required aria-label="Emergency Contact Name">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Emergency Phone <span class="text-red-500">*</span></label>
                        <input type="tel" name="emergency_phone" value="<?php echo htmlspecialchars($profile2['emergency_phone'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md text-sm" required aria-label="Emergency Phone Number">
                    </div>
                </div>
                <div class="input-grid grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Current Address <span class="text-red-500">*</span></label>
                        <textarea name="address_current" id="address_current" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md text-sm" required aria-label="Current Address"><?php echo htmlspecialchars($profile2['address_current'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Permanent Address <span class="text-red-500">*</span></label>
                        <textarea name="address_permanent" id="address_permanent" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md text-sm" required aria-label="Permanent Address"><?php echo htmlspecialchars($profile2['address_permanent'] ?? ''); ?></textarea>
                        <label class="checkbox-label">
                            <input type="checkbox" name="same_as_current" id="same_as_current" onchange="copyAddress()" <?php echo (isset($profile2['address_current'], $profile2['address_permanent']) && $profile2['address_current'] === $profile2['address_permanent'] && !empty($profile2['address_current'])) ? 'checked' : ''; ?>> Same as Current Address
                        </label>
                    </div>
                </div>
                <div class="input-grid grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Photo</label>
                        <input type="file" name="photo" accept="image/jpeg,image/png" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md text-sm" aria-label="Photo Upload">
                        <?php if (!empty($profile2['photo'])): ?>
                            <p class="text-sm text-gray-600 mt-1">Current: <?php echo htmlspecialchars(basename($profile2['photo'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Aadhar Card</label>
                        <input type="file" name="aadhar" accept="image/jpeg,image/png,application/pdf" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md text-sm" aria-label="Aadhar Card Upload">
                        <?php if (!empty($profile2['aadhar'])): ?>
                            <p class="text-sm text-gray-600 mt-1">Current: <?php echo htmlspecialchars(basename($profile2['aadhar'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">Pancard</label>
                        <input type="file" name="pancard" accept="image/jpeg,image/png,application/pdf" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md text-sm" aria-label="Pancard Upload">
                        <?php if (!empty($profile2['pancard'])): ?>
                            <p class="text-sm text-gray-600 mt-1">Current: <?php echo htmlspecialchars(basename($profile2['pancard'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-center mt-6">
                    <button type="submit" class="save-button"><span>Save & Next</span></button>
                </div>
            </form>

            <!-- Education Details -->
            <form id="education-form" action="my_profile.php" method="POST" enctype="multipart/form-data" class="form-section <?php echo $active_form === 'education-form' ? 'active' : 'hidden'; ?>">
                <input type="hidden" name="step" value="education">
                <input type="hidden" name="next_step" value="experience-form">
                <h4 class="section-header">Education Details</h4>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse mb-4">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border p-2 text-left text-sm font-semibold text-gray-800">Graduation</th>
                                <th class="border p-2 text-left text-sm font-semibold text-gray-800">Branch</th>
                                <th class="border p-2 text-left text-sm font-semibold text-gray-800">Score</th>
                                <th class="border p-2 text-left text-sm font-semibold text-gray-800">Passed Out Year</th>
                                <th class="border p-2 text-left text-sm font-semibold text-gray-800">Upload Certificate</th>
                                <th class="border p-2 text-left text-sm font-semibold text-gray-800">Action</th>
                            </tr>
                        </thead>
                        <tbody id="education-table">
                            <?php for ($i = 0; $i < max(4, count($education_details)); $i++): ?>
                                <tr>
                                    <td class="border p-2"><input type="text" name="graduation[]" value="<?php echo htmlspecialchars($education_details[$i]['graduation'] ?? ''); ?>" class="table-input" aria-label="Graduation"></td>
                                    <td class="border p-2"><input type="text" name="branch[]" value="<?php echo htmlspecialchars($education_details[$i]['branch'] ?? ''); ?>" class="table-input" aria-label="Branch"></td>
                                    <td class="border p-2"><input type="text" name="score[]" value="<?php echo htmlspecialchars($education_details[$i]['score'] ?? ''); ?>" class="table-input" aria-label="Score"></td>
                                    <td class="border p-2"><input type="number" name="passed_out_year[]" value="<?php echo htmlspecialchars($education_details[$i]['passed_out_year'] ?? ''); ?>" class="table-input" min="1900" max="<?php echo date('Y'); ?>" aria-label="Passed Out Year"></td>
                                    <td class="border p-2">
                                        <input type="file" name="certificate[]" accept="image/jpeg,image/png,application/pdf" class="table-input" aria-label="Certificate Upload">
                                        <?php if (!empty($education_details[$i]['certificate'])): ?>
                                            <p class="text-sm text-gray-600 mt-1">Current: <?php echo htmlspecialchars(basename($education_details[$i]['certificate'])); ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="border p-2 flex items-center gap-2">
                                        <button type="button" onclick="removeRow(this, 'education-table')" class="action-btn remove-btn" title="Remove Row">-</button>
                                        <?php if ($i == max(3, count($education_details) - 1)): ?>
                                            <button type="button" onclick="addEducationRow()" class="action-btn add-btn" title="Add Row">+</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-6">
                    <button type="submit" class="save-button"><span>Save & Next</span></button>
                </div>
            </form>

            <!-- Experience Details -->
            <form id="experience-form" action="my_profile.php" method="POST" enctype="multipart/form-data" class="form-section <?php echo $active_form === 'experience-form' ? 'active' : 'hidden'; ?>">
                <input type="hidden" name="step" value="experience">
                <input type="hidden" name="action" id="form-action" value="save">
                <h4 class="section-header">Experience Details</h4>
                <div class="checkbox-label mb-4">
                    <input type="checkbox" name="fresher" id="fresher" onchange="toggleExperience(this)" <?php echo $is_fresher_db ? 'checked' : ''; ?>> I am a Fresher
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse mb-4">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border p-2 text-left text-sm font-semibold text-gray-800">Company</th>
                                <th class="border p-2 text-left text-sm font-semibold text-gray-800">Role</th>
                                <th class="border p-2 text-left text-sm font-semibold text-gray-800">CTC</th>
                                <th class="border p-2 text-left text-sm font-semibold text-gray-800">Start Date</th>
                                <th class="border p-2 text-left text-sm font-semibold text-gray-800">End Date</th>
                                <th class="border p-2 text-left text-sm font-semibold text-gray-800">Document</th>
                                <th class="border p-2 text-left text-sm font-semibold text-gray-800">Action</th>
                            </tr>
                        </thead>
                        <tbody id="experience-table">
                            <?php for ($i = 0; $i < max(10, count($experience_details)); $i++): ?>
                                <tr>
                                    <td class="border p-2"><input type="text" name="company[]" value="<?php echo htmlspecialchars($experience_details[$i]['company'] ?? ''); ?>" class="table-input" <?php echo $is_fresher_db ? 'disabled' : ''; ?> aria-label="Company"></td>
                                    <td class="border p-2"><input type="text" name="role[]" value="<?php echo htmlspecialchars($experience_details[$i]['role'] ?? ''); ?>" class="table-input" <?php echo $is_fresher_db ? 'disabled' : ''; ?> aria-label="Role"></td>
                                    <td class="border p-2"><input type="text" name="ctc[]" value="<?php echo htmlspecialchars($experience_details[$i]['ctc'] ?? ''); ?>" class="table-input" <?php echo $is_fresher_db ? 'disabled' : ''; ?> aria-label="CTC"></td>
                                    <td class="border p-2"><input type="date" name="start_date[]" value="<?php echo htmlspecialchars($experience_details[$i]['start_date'] ?? ''); ?>" class="table-input" <?php echo $is_fresher_db ? 'disabled' : ''; ?> aria-label="Start Date"></td>
                                    <td class="border p-2"><input type="date" name="end_date[]" value="<?php echo htmlspecialchars($experience_details[$i]['end_date'] ?? ''); ?>" class="table-input" <?php echo $is_fresher_db ? 'disabled' : ''; ?> aria-label="End Date"></td>
                                    <td class="border p-2">
                                        <input type="file" name="document[]" accept="image/jpeg,image/png,application/pdf" class="table-input" <?php echo $is_fresher_db ? 'disabled' : ''; ?> aria-label="Document Upload">
                                        <?php if (!empty($experience_details[$i]['document'])): ?>
                                            <p class="text-sm text-gray-600 mt-1">Current: <?php echo htmlspecialchars(basename($experience_details[$i]['document'])); ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="border p-2 flex items-center gap-2">
                                        <button type="button" onclick="removeRow(this, 'experience-table')" class="action-btn remove-btn" <?php echo $is_fresher_db ? 'disabled' : ''; ?> title="Remove Row">-</button>
                                        <?php if ($i == max(9, count($experience_details) - 1)): ?>
                                            <button type="button" onclick="addExperienceRow()" class="action-btn add-btn" <?php echo $is_fresher_db ? 'disabled' : ''; ?> title="Add Row">+</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-6 flex justify-center gap-4">
                    <button type="submit" class="save-button" onclick="document.getElementById('form-action').value='save'"><span>Save</span></button>
                    <button type="submit" class="submit-button" onclick="document.getElementById('form-action').value='submit'"><span>Submit</span></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function navigateToForm(formId) {
            console.log('Navigating to form:', formId);
            const currentForm = document.querySelector('.form-section.active');
            if (currentForm) {
                currentForm.classList.remove('active');
                currentForm.classList.add('slide-out');
                setTimeout(() => {
                    currentForm.classList.add('hidden');
                    currentForm.classList.remove('slide-out');
                }, 300);
            }
            const nextForm = document.getElementById(formId);
            if (nextForm) {
                nextForm.classList.remove('hidden');
                nextForm.classList.add('slide-in');
                setTimeout(() => {
                    nextForm.classList.add('active');
                    nextForm.classList.remove('slide-in');
                    document.querySelectorAll('.step-circle').forEach(circle => circle.classList.remove('active'));
                    document.querySelector(`[onclick="navigateToForm('${formId}')"]`)?.classList.add('active');
                }, 300);
                window.scrollTo({ top: 0, behavior: 'smooth' });
                window.history.pushState({}, '', `?form=${formId}`);
            } else {
                console.error('Form not found:', formId);
            }
        }

        function copyAddress() {
            const checkbox = document.getElementById('same_as_current');
            const currentAddress = document.getElementById('address_current');
            const permanentAddress = document.getElementById('address_permanent');
            if (checkbox.checked) {
                permanentAddress.value = currentAddress.value;
                permanentAddress.readOnly = true;
            } else {
                permanentAddress.value = '<?php echo htmlspecialchars($profile2['address_permanent'] ?? ''); ?>';
                permanentAddress.readOnly = false;
            }
        }

        function addEducationRow() {
            const table = document.getElementById('education-table');
            const rowCount = table.rows.length;
            document.querySelectorAll('#education-table .add-btn').forEach(btn => btn.remove());
            const row = table.insertRow();
            row.innerHTML = `
                <td class="border p-2"><input type="text" name="graduation[]" class="table-input" aria-label="Graduation"></td>
                <td class="border p-2"><input type="text" name="branch[]" class="table-input" aria-label="Branch"></td>
                <td class="border p-2"><input type="text" name="score[]" class="table-input" aria-label="Score"></td>
                <td class="border p-2"><input type="number" name="passed_out_year[]" class="table-input" min="1900" max="<?php echo date('Y'); ?>" aria-label="Passed Out Year"></td>
                <td class="border p-2"><input type="file" name="certificate[]" accept="image/jpeg,image/png,application/pdf" class="table-input" aria-label="Certificate Upload"></td>
                <td class="border p-2 flex items-center gap-2">
                    <button type="button" onclick="removeRow(this, 'education-table')" class="action-btn remove-btn" title="Remove Row">-</button>
                    <button type="button" onclick="addEducationRow()" class="action-btn add-btn" title="Add Row">+</button>
                </td>
            `;
        }

        function addExperienceRow() {
            const table = document.getElementById('experience-table');
            const rowCount = table.rows.length;
            document.querySelectorAll('#experience-table .add-btn').forEach(btn => btn.remove());
            const isFresher = document.getElementById('fresher').checked;
            const row = table.insertRow();
            row.innerHTML = `
                <td class="border p-2"><input type="text" name="company[]" class="table-input" ${isFresher ? 'disabled' : ''} aria-label="Company"></td>
                <td class="border p-2"><input type="text" name="role[]" class="table-input" ${isFresher ? 'disabled' : ''} aria-label="Role"></td>
                <td class="border p-2"><input type="text" name="ctc[]" class="table-input" ${isFresher ? 'disabled' : ''} aria-label="CTC"></td>
                <td class="border p-2"><input type="date" name="start_date[]" class="table-input" ${isFresher ? 'disabled' : ''} aria-label="Start Date"></td>
                <td class="border p-2"><input type="date" name="end_date[]" class="table-input" ${isFresher ? 'disabled' : ''} aria-label="End Date"></td>
                <td class="border p-2"><input type="file" name="document[]" accept="image/jpeg,image/png,application/pdf" class="table-input" ${isFresher ? 'disabled' : ''} aria-label="Document Upload"></td>
                <td class="border p-2 flex items-center gap-2">
                    <button type="button" onclick="removeRow(this, 'experience-table')" class="action-btn remove-btn" ${isFresher ? 'disabled' : ''} title="Remove Row">-</button>
                    <button type="button" onclick="addExperienceRow()" class="action-btn add-btn" ${isFresher ? 'disabled' : ''} title="Add Row">+</button>
                </td>
            `;
        }

        function removeRow(btn, tableId) {
            const table = document.getElementById(tableId);
            const rowCount = table.rows.length;
            if (rowCount > 1) {
                btn.closest('tr').remove();
                const lastRow = table.rows[table.rows.length - 1];
                if (!lastRow.querySelector('.add-btn')) {
                    const actionCell = lastRow.querySelector('td:last-child');
                    const addButton = document.createElement('button');
                    addButton.type = 'button';
                    addButton.className = 'action-btn add-btn';
                    addButton.title = 'Add Row';
                    addButton.textContent = '+';
                    addButton.onclick = tableId === 'education-table' ? addEducationRow : addExperienceRow;
                    addButton.disabled = document.getElementById('fresher')?.checked && tableId === 'experience-table';
                    actionCell.appendChild(addButton);
                }
            }
        }

        function toggleExperience(checkbox) {
            const table = document.getElementById('experience-table');
            const inputs = table.querySelectorAll('input, select, textarea');
            const buttons = table.querySelectorAll('.action-btn');
            inputs.forEach(input => {
                input.disabled = checkbox.checked;
            });
            buttons.forEach(btn => {
                btn.disabled = checkbox.checked;
            });
        }

        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (form.tagName !== 'FORM') return true;
            let isValid = true;
            const inputs = form.querySelectorAll('input[required]:not([type="file"]), select[required], textarea[required]');
            const sameAsCurrent = document.getElementById('same_as_current');
            const currentAddress = document.getElementById('address_current');
            const permanentAddress = document.getElementById('address_permanent');

            inputs.forEach(input => {
                if (!input.value.trim() && !input.disabled) {
                    input.classList.add('invalid');
                    isValid = false;
                } else {
                    input.classList.remove('invalid');
                }
            });

            if (formId === 'basic-form') {
                if (sameAsCurrent && sameAsCurrent.checked && permanentAddress.value !== currentAddress.value) {
                    permanentAddress.classList.add('invalid');
                    alert('Permanent address must match current address when "Same as Current Address" is checked.');
                    isValid = false;
                }
            }

            if (formId === 'education-form') {
                const table = document.getElementById('education-table');
                let hasValidRow = false;
                table.querySelectorAll('tr').forEach(row => {
                    const inputs = row.querySelectorAll('input:not([type="file"])');
                    const allFilled = Array.from(inputs).every(input => input.value.trim());
                    if (allFilled) hasValidRow = true;
                    inputs.forEach(input => {
                        if (!input.value.trim() && !hasValidRow) {
                            input.classList.add('invalid');
                        } else {
                            input.classList.remove('invalid');
                        }
                    });
                });
                if (!hasValidRow) {
                    isValid = false;
                    alert('Please fill at least one complete education row.');
                }
            }

            return isValid;
        }
    </script>
</body>
</html>