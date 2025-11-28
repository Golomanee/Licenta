<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$userId = $user['id'];

// Initialize variables
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']) ?: null;
    $birthday = $_POST['birthday'] ?: null;
    $country = trim($_POST['country']) ?: null;
    $city = trim($_POST['city']) ?: null;
    $height = $_POST['height'] ?: null;
    $weight = $_POST['weight'] ?: null;
    $profileImageData = null;
    
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        $fileType = $_FILES['profile_picture']['type'];
        $fileSize = $_FILES['profile_picture']['size'];
        $fileTmpName = $_FILES['profile_picture']['tmp_name'];
        
        if (!in_array($fileType, $allowedTypes)) {
            $error = 'Tipul fișierului nu este valid. Te rugăm să încarci o imagine (JPG, PNG, GIF).';
        } elseif ($fileSize > $maxSize) {
            $error = 'Fișierul este prea mare. Dimensiunea maximă este 5MB.';
        } else {
            // Read file content as binary
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
    } else {
        // Check if email is already used by another user
        $stmt = $conn->prepare("SELECT id FROM User WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $userId);
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
                $stmt = $conn->prepare("UPDATE User SET email = ? WHERE id = ?");
                $stmt->bind_param("si", $email, $userId);
                $stmt->execute();
                $stmt->close();
                
                // Check if UserDetails entry exists
                $stmt = $conn->prepare("SELECT id FROM UserDetails WHERE userid = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $detailsExist = $result->num_rows > 0;
                $stmt->close();
                
                if ($detailsExist) {
                    // Update UserDetails
                    if ($profileImageData !== null) {
                        $stmt = $conn->prepare("UPDATE UserDetails SET name = ?, birthday = ?, phone = ?, country = ?, city = ?, height = ?, weight = ?, profileimage = ? WHERE userid = ?");
                        $stmt->bind_param("sssssssbi", $name, $birthday, $phone, $country, $city, $height, $weight, $null, $userId);
                        $stmt->send_long_data(7, $profileImageData);
                    } else {
                        $stmt = $conn->prepare("UPDATE UserDetails SET name = ?, birthday = ?, phone = ?, country = ?, city = ?, height = ?, weight = ? WHERE userid = ?");
                        $stmt->bind_param("ssssssii", $name, $birthday, $phone, $country, $city, $height, $weight, $userId);
                    }
                } else {
                    // Insert UserDetails
                    if ($profileImageData !== null) {
                        $stmt = $conn->prepare("INSERT INTO UserDetails (userid, name, birthday, phone, country, city, height, weight, profileimage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("isssssiib", $userId, $name, $birthday, $phone, $country, $city, $height, $weight, $null);
                        $stmt->send_long_data(8, $profileImageData);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO UserDetails (userid, name, birthday, phone, country, city, height, weight) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("isssssii", $userId, $name, $birthday, $phone, $country, $city, $height, $weight);
                    }
                }
                
                $stmt->execute();
                $stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                // Update session name
                $_SESSION['user']['name'] = $name;
                
                $success = 'Profilul a fost actualizat cu succes!';
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $error = 'A apărut o eroare la actualizarea profilului. Te rugăm să încerci din nou.';
            }
        }
    }
}

// Fetch current user details
$stmt = $conn->prepare("SELECT u.*, ud.name, ud.birthday, ud.phone, ud.country, ud.city, ud.height, ud.weight, ud.profileimage FROM User u LEFT JOIN UserDetails ud ON u.id = ud.userid WHERE u.id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userDetails = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Setări - Spital</title>
    <link rel="stylesheet" type="text/css" href="css/base.css">
    <link rel="stylesheet" type="text/css" href="css/components/navbar.css">
    <link rel="stylesheet" type="text/css" href="css/pages/settings.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="page-container">
        <h1 class="page-title">Setări profil</h1>
        
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
            <form method="POST" action="settings.php" class="settings-form" enctype="multipart/form-data">
                <!-- Profile Picture Section -->
                <div class="form-section profile-picture-section">
                    <h2 class="section-title">
                        <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                        Poză de profil
                    </h2>
                    
                    <div class="profile-picture-container">
                        <div class="current-picture">
                            <?php if (!empty($userDetails['profileimage'])): ?>
                                <img src="image.php?id=<?php echo $userId; ?>" alt="Profile Picture" id="preview-image">
                            <?php else: ?>
                                <div class="default-avatar" id="preview-avatar">
                                    <?php echo strtoupper(substr($userDetails['name'] ?? 'U', 0, 1)); ?>
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
                
                <!-- Personal Information Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        Informații personale
                    </h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Nume complet <span class="required">*</span></label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($userDetails['name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userDetails['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="birthday">Data nașterii</label>
                            <input type="date" id="birthday" name="birthday" value="<?php echo htmlspecialchars($userDetails['birthday'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Telefon</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userDetails['phone'] ?? ''); ?>" placeholder="+40712345678">
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
                        Locație
                    </h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="country">Țară</label>
                            <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($userDetails['country'] ?? ''); ?>" placeholder="România">
                        </div>
                        
                        <div class="form-group">
                            <label for="city">Oraș</label>
                            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($userDetails['city'] ?? ''); ?>" placeholder="București">
                        </div>
                    </div>
                </div>
                
                <!-- Physical Information Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                        </svg>
                        Informații fizice
                    </h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="height">Înălțime (cm)</label>
                            <input type="number" id="height" name="height" value="<?php echo htmlspecialchars($userDetails['height'] ?? ''); ?>" min="50" max="250" placeholder="175">
                        </div>
                        
                        <div class="form-group">
                            <label for="weight">Greutate (kg)</label>
                            <input type="number" id="weight" name="weight" value="<?php echo htmlspecialchars($userDetails['weight'] ?? ''); ?>" min="20" max="300" placeholder="70">
                        </div>
                    </div>
                </div>
                
                <!-- Account Information (Read-only) -->
                <div class="form-section info-section">
                    <h2 class="section-title">
                        <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        Informații cont
                    </h2>
                    
                    <div class="info-box">
                        <div class="info-item">
                            <span class="info-label">Rol:</span>
                            <span class="info-value role-badge role-<?php echo htmlspecialchars($userDetails['role']); ?>">
                                <?php echo htmlspecialchars(ucfirst($userDetails['role'])); ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Status email:</span>
                            <span class="info-value">
                                <?php if ($userDetails['email_verified']): ?>
                                    <span class="status-badge verified">✓ Verificat</span>
                                <?php else: ?>
                                    <span class="status-badge unverified">✗ Neverificat</span>
                                <?php endif; ?>
                            </span>
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
                        Salvează modificările
                    </button>
                    <a href="profile.php" class="btn btn-secondary">
                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        Anulează
                    </a>
                    <a href="change-password.php" class="btn btn-outline">
                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        Schimbă parola
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Preview image before upload
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const fileNameDisplay = document.getElementById('file-name');
            
            if (file) {
                fileNameDisplay.textContent = 'Fișier selectat: ' + file.name;
                
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
