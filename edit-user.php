<?php
session_start();

// Prevent caching - force revalidation
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Check if user ID is provided
if (!isset($_GET['id'])) {
    header('Location: admindashboard.php');
    exit;
}

$editUserId = intval($_GET['id']);

// Initialize variables
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $email_verified = isset($_POST['email_verified']) ? 1 : 0;
    $phone = trim($_POST['phone']) ?: null;
    $birthday = $_POST['birthday'] ?: null;
    $country = trim($_POST['country']) ?: null;
    $city = trim($_POST['city']) ?: null;
    $height = $_POST['height'] ?: null;
    $weight = $_POST['weight'] ?: null;
    $specialty = ($role === 'doctor' && !empty($_POST['specialty'])) ? $_POST['specialty'] : null;
    $profileImageData = null;
    
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        $fileType = $_FILES['profile_picture']['type'];
        $fileSize = $_FILES['profile_picture']['size'];
        $fileTmpName = $_FILES['profile_picture']['tmp_name'];
        
        if (!in_array($fileType, $allowedTypes)) {
            $error = 'Tipul fisierului nu este valid. Te rugam sa incarci o imagine (JPG, PNG, GIF).';
        } elseif ($fileSize > $maxSize) {
            $error = 'Fisierul este prea mare. Dimensiunea maxima este 5MB.';
        } else {
            $profileImageData = file_get_contents($fileTmpName);
        }
    }
    
    // Validate required fields
    if (empty($name)) {
        $error = 'Numele este obligatoriu.';
    } elseif (empty($email)) {
        $error = 'Email-ul este obligatoriu.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email-ul nu este valid.';
    } elseif (!in_array($role, ['patient', 'doctor', 'admin'])) {
        $error = 'Rol invalid.';
    } else {
        // Check if email is already used by another user
        $stmt = $conn->prepare("SELECT id FROM User WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $editUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Acest email este deja folosit de alt utilizator.';
            $stmt->close();
        } else {
            $stmt->close();
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Update User table
                $stmt = $conn->prepare("UPDATE User SET email = ?, role = ?, email_verified = ? WHERE id = ?");
                $stmt->bind_param("ssii", $email, $role, $email_verified, $editUserId);
                $stmt->execute();
                $stmt->close();
                
                // Check if UserDetails entry exists
                $stmt = $conn->prepare("SELECT id FROM UserDetails WHERE userid = ?");
                $stmt->bind_param("i", $editUserId);
                $stmt->execute();
                $result = $stmt->get_result();
                $detailsExist = $result->num_rows > 0;
                $stmt->close();
                
                if ($detailsExist) {
                    // Update UserDetails
                    if ($profileImageData !== null) {
                        $stmt = $conn->prepare("UPDATE UserDetails SET name = ?, birthday = ?, phone = ?, country = ?, city = ?, height = ?, weight = ?, specialty = ?, profileimage = ? WHERE userid = ?");
                        $stmt->bind_param("sssssssssi", $name, $birthday, $phone, $country, $city, $height, $weight, $specialty, $null, $editUserId);
                        $stmt->send_long_data(8, $profileImageData);
                    } else {
                        $stmt = $conn->prepare("UPDATE UserDetails SET name = ?, birthday = ?, phone = ?, country = ?, city = ?, height = ?, weight = ?, specialty = ? WHERE userid = ?");
                        $stmt->bind_param("ssssssssi", $name, $birthday, $phone, $country, $city, $height, $weight, $specialty, $editUserId);
                    }
                } else {
                    // Insert UserDetails
                    if ($profileImageData !== null) {
                        $stmt = $conn->prepare("INSERT INTO UserDetails (userid, name, birthday, phone, country, city, height, weight, specialty, profileimage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("issssssssi", $editUserId, $name, $birthday, $phone, $country, $city, $height, $weight, $specialty, $null);
                        $stmt->send_long_data(9, $profileImageData);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO UserDetails (userid, name, birthday, phone, country, city, height, weight, specialty) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("issssssss", $editUserId, $name, $birthday, $phone, $country, $city, $height, $weight, $specialty);
                    }
                }
                
                $stmt->execute();
                $stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                $success = 'Utilizatorul a fost actualizat cu succes!';
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $error = 'A aparut o eroare la actualizarea utilizatorului. Te rugam sa incerci din nou.';
            }
        }
    }
}

// Fetch user details
$stmt = $conn->prepare("SELECT u.*, ud.name, ud.birthday, ud.phone, ud.country, ud.city, ud.height, ud.weight, ud.specialty FROM User u LEFT JOIN UserDetails ud ON u.id = ud.userid WHERE u.id = ?");
$stmt->bind_param("i", $editUserId);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

// If user not found, redirect
if (!$userData) {
    header('Location: admindashboard.php');
    exit;
}

// Check if user has profile image
$stmt = $conn->prepare("SELECT IF(profileimage IS NOT NULL, 1, 0) as has_image FROM UserDetails WHERE userid = ?");
$stmt->bind_param("i", $editUserId);
$stmt->execute();
$imageResult = $stmt->get_result();
$imageData = $imageResult->fetch_assoc();
$hasImage = $imageData ? $imageData['has_image'] : 0;
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Editeaza utilizator - Admin</title>
    <link rel="stylesheet" type="text/css" href="css/base.css">
    <link rel="stylesheet" type="text/css" href="css/components/navbar.css">
    <link rel="stylesheet" type="text/css" href="css/pages/settings.css">
    <style>
        .admin-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }
        .back-btn:hover {
            background: #5a6268;
        }
        .user-id-badge {
            padding: 8px 16px;
            background: #f0f0f0;
            border-radius: 6px;
            font-weight: 600;
            color: #333;
        }
        .admin-note {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #856404;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="page-container">
        <div class="admin-header">
            <a href="admindashboard.php" class="back-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Inapoi la dashboard
            </a>
            <span class="user-id-badge">ID: <?php echo $editUserId; ?></span>
        </div>
        
        <h1 class="page-title">Editeaza utilizator</h1>
        
        <div class="admin-note">
            <strong>Atentie:</strong> Esti in modul administrator. Modificarile vor afecta direct contul utilizatorului.
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="settings-container">
            <form method="POST" action="" enctype="multipart/form-data" class="settings-form">
                <!-- Profile Picture Section -->
                <div class="form-section profile-picture-section">
                    <h2 class="section-title">
                        <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                        Poza de profil
                    </h2>
                    
                    <div class="profile-picture-container">
                        <div class="current-picture">
                            <?php if ($hasImage): ?>
                                <img src="image.php?id=<?php echo $editUserId; ?>" alt="Profile Picture" id="preview-image">
                            <?php else: ?>
                                <div class="default-avatar" id="preview-avatar">
                                    <?php echo strtoupper(substr($userData['name'] ?? 'U', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="picture-upload">
                            <input type="file" id="profile_picture" name="profile_picture" accept="image/*" style="display: none;">
                            <label for="profile_picture" class="upload-btn">
                                <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                                Alege o imagine
                            </label>
                            <p class="upload-info">JPG, PNG sau GIF. Maxim 5MB.</p>
                            <p id="file-name" class="file-name"></p>
                        </div>
                    </div>
                </div>
                
                <!-- Account Information Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <polyline points="17 11 19 13 23 9"></polyline>
                        </svg>
                        Informatii cont
                    </h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Rol <span class="required">*</span></label>
                            <select id="role" name="role" required style="padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; width: 100%;">
                                <option value="patient" <?php echo $userData['role'] === 'patient' ? 'selected' : ''; ?>>Pacient</option>
                                <option value="doctor" <?php echo $userData['role'] === 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                                <option value="admin" <?php echo $userData['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" id="email_verified" name="email_verified" <?php echo $userData['email_verified'] ? 'checked' : ''; ?> style="width: 20px; height: 20px;">
                                <span>Email verificat</span>
                            </label>
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">Bifează pentru a marca emailul ca verificat</p>
                        </div>
                    </div>
                    
                    <div class="form-row specialty-row" id="specialty-row" style="<?php echo $userData['role'] === 'doctor' ? '' : 'display: none;'; ?>">
                        <div class="form-group">
                            <label for="specialty">Specializare medicală</label>
                            <select id="specialty" name="specialty" style="padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; width: 100%;">
                                <option value="">-- Selectează specializarea --</option>
                                <option value="cardiolog" <?php echo ($userData['specialty'] ?? '') === 'cardiolog' ? 'selected' : ''; ?>>Cardiolog</option>
                                <option value="radiolog" <?php echo ($userData['specialty'] ?? '') === 'radiolog' ? 'selected' : ''; ?>>Radiolog</option>
                                <option value="gastroenterolog" <?php echo ($userData['specialty'] ?? '') === 'gastroenterolog' ? 'selected' : ''; ?>>Gastroenterolog</option>
                                <option value="pneumolog" <?php echo ($userData['specialty'] ?? '') === 'pneumolog' ? 'selected' : ''; ?>>Pneumolog</option>
                                <option value="medicina_laborator" <?php echo ($userData['specialty'] ?? '') === 'medicina_laborator' ? 'selected' : ''; ?>>Medicină de Laborator</option>
                            </select>
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">Selectează domeniul medical al doctorului</p>
                        </div>
                    </div>
                </div>
                
                <!-- Personal Information Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        Informatii personale
                    </h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Nume complet <span class="required">*</span></label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($userData['name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Telefon</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>" placeholder="+40712345678">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="birthday">Data nasterii</label>
                            <input type="date" id="birthday" name="birthday" value="<?php echo htmlspecialchars($userData['birthday'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Location Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        Locatie
                    </h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="country">Tara</label>
                            <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($userData['country'] ?? ''); ?>" placeholder="Romania">
                        </div>
                        
                        <div class="form-group">
                            <label for="city">Oras</label>
                            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($userData['city'] ?? ''); ?>" placeholder="Bucuresti">
                        </div>
                    </div>
                </div>
                
                <!-- Physical Information Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                        </svg>
                        Informatii fizice
                    </h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="height">Inaltime (cm)</label>
                            <input type="number" id="height" name="height" value="<?php echo htmlspecialchars($userData['height'] ?? ''); ?>" min="50" max="250" placeholder="175">
                        </div>
                        
                        <div class="form-group">
                            <label for="weight">Greutate (kg)</label>
                            <input type="number" id="weight" name="weight" value="<?php echo htmlspecialchars($userData['weight'] ?? ''); ?>" min="20" max="300" placeholder="70">
                        </div>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        Salveaza modificarile
                    </button>
                    <a href="admindashboard.php" class="btn btn-secondary">
                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        Anuleaza
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Toggle specialty field based on role
        document.getElementById('role').addEventListener('change', function() {
            const specialtyRow = document.getElementById('specialty-row');
            if (this.value === 'doctor') {
                specialtyRow.style.display = '';
            } else {
                specialtyRow.style.display = 'none';
                document.getElementById('specialty').value = '';
            }
        });
        
        // Preview image before upload
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const fileNameDisplay = document.getElementById('file-name');
            
            if (file) {
                fileNameDisplay.textContent = 'Fisier selectat: ' + file.name;
                
                // Create preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewImage = document.getElementById('preview-image');
                    const previewAvatar = document.getElementById('preview-avatar');
                    
                    if (previewImage) {
                        previewImage.src = e.target.result;
                    } else if (previewAvatar) {
                        // Replace avatar with image
                        const currentPicture = document.querySelector('.current-picture');
                        currentPicture.innerHTML = '<img src="' + e.target.result + '" alt="Profile Picture" id="preview-image">';
                    }
                };
                reader.readAsDataURL(file);
            } else {
                fileNameDisplay.textContent = '';
            }
        });
    </script>
</body>
</html>
