<?php
// Start session
session_start();

// Include database connection
require_once 'db_connection.php';

// Set response header to JSON
header('Content-Type: application/json');

// Initialize response array
$response = array('success' => false, 'message' => '', 'application_id' => null);

// Check if form is submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Collect form data
        $student_name = isset($_POST['studentName']) ? $conn->real_escape_string($_POST['studentName']) : '';
        $admission_std = isset($_POST['admissionStd']) ? $conn->real_escape_string($_POST['admissionStd']) : '';
        $age = isset($_POST['age']) ? intval($_POST['age']) : 0;
        $dob = isset($_POST['dob']) ? $_POST['dob'] : '';
        $previous_school = isset($_POST['previousSchool']) ? $conn->real_escape_string($_POST['previousSchool']) : '';
        $leaving_reason = isset($_POST['leavingReason']) ? $conn->real_escape_string($_POST['leavingReason']) : '';
        
        $father_name = isset($_POST['fatherName']) ? $conn->real_escape_string($_POST['fatherName']) : '';
        $father_qualification = isset($_POST['fatherQualification']) ? $conn->real_escape_string($_POST['fatherQualification']) : '';
        $father_occupation = isset($_POST['fatherOccupation']) ? $conn->real_escape_string($_POST['fatherOccupation']) : '';
        $father_annual_income = isset($_POST['fatherAnnualIncome']) ? floatval($_POST['fatherAnnualIncome']) : 0;
        $father_email = isset($_POST['fatherEmail']) ? $conn->real_escape_string($_POST['fatherEmail']) : '';
        $father_mobile = isset($_POST['fatherMobile']) ? $conn->real_escape_string($_POST['fatherMobile']) : '';
        $father_whatsapp = isset($_POST['fatherWhatsApp']) ? $conn->real_escape_string($_POST['fatherWhatsApp']) : '';
        
        $mother_name = isset($_POST['motherName']) ? $conn->real_escape_string($_POST['motherName']) : '';
        $mother_qualification = isset($_POST['motherQualification']) ? $conn->real_escape_string($_POST['motherQualification']) : '';
        $mother_occupation = isset($_POST['motherOccupation']) ? $conn->real_escape_string($_POST['motherOccupation']) : '';
        $mother_annual_income = isset($_POST['motherAnnualIncome']) ? floatval($_POST['motherAnnualIncome']) : 0;
        $mother_email = isset($_POST['motherEmail']) ? $conn->real_escape_string($_POST['motherEmail']) : '';
        $mother_mobile = isset($_POST['motherMobile']) ? $conn->real_escape_string($_POST['motherMobile']) : '';
        $mother_whatsapp = isset($_POST['motherWhatsApp']) ? $conn->real_escape_string($_POST['motherWhatsApp']) : '';
        
        $residence_address = isset($_POST['residenceAddress']) ? $conn->real_escape_string($_POST['residenceAddress']) : '';
        
        $referral_source = isset($_POST['referralSource']) ? $conn->real_escape_string($_POST['referralSource']) : '';
        $staff_name = isset($_POST['staffName']) ? $conn->real_escape_string($_POST['staffName']) : '';
        $transport_required = isset($_POST['transportRequired']) ? $conn->real_escape_string($_POST['transportRequired']) : '';
        $pickup_point = isset($_POST['pickupPoint']) ? $conn->real_escape_string($_POST['pickupPoint']) : '';
        
        // Validate required fields
        if (empty($student_name) || empty($admission_std) || empty($father_name) || empty($mother_name) || 
            empty($father_mobile) || empty($mother_mobile) || empty($residence_address) ||
            empty($transport_required)) {
            $response['message'] = 'Please fill all required fields';
            echo json_encode($response);
            exit();
        }

        // Create table if it doesn't exist
        $table_check = $conn->query("SHOW TABLES LIKE 'enrollment_data'");
        if ($table_check->num_rows == 0) {
            $create_table_sql = "CREATE TABLE enrollment_data (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_name VARCHAR(100) NOT NULL,
                admission_std VARCHAR(50) NOT NULL,
                age INT NOT NULL,
                dob DATE NOT NULL,
                previous_school VARCHAR(200),
                leaving_reason TEXT,
                father_name VARCHAR(100) NOT NULL,
                father_qualification VARCHAR(100),
                father_occupation VARCHAR(100),
                father_annual_income DECIMAL(12,2) DEFAULT 0,
                father_email VARCHAR(100),
                father_mobile VARCHAR(20) NOT NULL,
                father_whatsapp VARCHAR(20),
                mother_name VARCHAR(100) NOT NULL,
                mother_qualification VARCHAR(100),
                mother_occupation VARCHAR(100),
                mother_annual_income DECIMAL(12,2),
                mother_email VARCHAR(100),
                mother_mobile VARCHAR(20) NOT NULL,
                mother_whatsapp VARCHAR(20),
                residence_address TEXT NOT NULL,
                referral_source VARCHAR(100) NOT NULL,
                staff_name VARCHAR(100),
                transport_required ENUM('Yes', 'No') NOT NULL,
                pickup_point VARCHAR(200),
                submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            
            $conn->query($create_table_sql);
        }

        // Insert data using direct SQL (simpler approach)
        $sql = "INSERT INTO enrollment_data (
            student_name, admission_std, age, dob, previous_school, leaving_reason,
            father_name, father_qualification, father_occupation, father_annual_income, 
            father_email, father_mobile, father_whatsapp,
            mother_name, mother_qualification, mother_occupation, mother_annual_income,
            mother_email, mother_mobile, mother_whatsapp,
            residence_address, referral_source, staff_name, transport_required, pickup_point
        ) VALUES (
            '$student_name', '$admission_std', $age, '$dob', '$previous_school', '$leaving_reason',
            '$father_name', '$father_qualification', '$father_occupation', $father_annual_income,
            '$father_email', '$father_mobile', '$father_whatsapp',
            '$mother_name', '$mother_qualification', '$mother_occupation', $mother_annual_income,
            '$mother_email', '$mother_mobile', '$mother_whatsapp',
            '$residence_address', '$referral_source', '$staff_name', '$transport_required', '$pickup_point'
        )";
        
        if ($conn->query($sql) === TRUE) {
            $last_id = $conn->insert_id;
            $response['success'] = true;
            $response['message'] = 'Application submitted successfully! Your Application ID: ' . $last_id;
            $response['application_id'] = $last_id;
        } else {
            $response['message'] = 'Error submitting form: ' . $conn->error;
        }
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
} else {
    // Not a POST request
    $response['message'] = 'Invalid request method';
}

// Close connection
$conn->close();

// Return JSON response
echo json_encode($response);
exit();
?>