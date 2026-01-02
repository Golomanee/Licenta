<?php
session_start();

// Prevent caching - force revalidation
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$userId = $user['id'];
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profil';

// Procesare rating
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate_appointment'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $rating = (int)$_POST['rating'];
    
    if ($rating >= 1 && $rating <= 5) {
        // Verifică că programarea aparține acestui pacient și este completă
        $check = $conn->prepare("SELECT id FROM Appointments WHERE id = ? AND patient_id = ? AND status = 'completed'");
        $check->bind_param("ii", $appointment_id, $userId);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE Appointments SET rating = ? WHERE id = ?");
            $stmt->bind_param("ii", $rating, $appointment_id);
            $stmt->execute();
            $stmt->close();
        }
        $check->close();
    }
    
    header("Location: profile.php?tab=istoric");
    exit();
}

// Fetch full user details from database (join User and UserDetails tables)
$stmt = $conn->prepare("SELECT u.*, ud.name, ud.birthday, ud.phone, ud.country, ud.city, ud.height, ud.weight, ud.profileimage, ud.specialty FROM User u LEFT JOIN UserDetails ud ON u.id = ud.userid WHERE u.id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userDetails = $result->fetch_assoc();
$stmt->close();

// Calculate age from birthday
$age = null;
if ($userDetails['birthday']) {
    $birthDate = new DateTime($userDetails['birthday']);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
}

// Calculează rating-ul mediu pentru doctori
$doctorRating = null;
$ratingCount = 0;
if ($userDetails['role'] === 'doctor') {
    $rating_query = "SELECT AVG(rating) as avg_rating, COUNT(rating) as rating_count 
                     FROM Appointments 
                     WHERE doctor_id = ? AND rating IS NOT NULL";
    $stmt = $conn->prepare($rating_query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rating_result = $stmt->get_result()->fetch_assoc();
    $doctorRating = $rating_result['avg_rating'] ? round($rating_result['avg_rating'], 1) : null;
    $ratingCount = $rating_result['rating_count'] ?? 0;
    $stmt->close();
}

// Preluare consultații pentru pacient
$consultations = [];
$upcomingAppointments = [];
if ($userDetails['role'] === 'patient') {
    // Consultații finalizate (istoric)
    $consultations_query = "SELECT a.*, ud.name as doctor_name, ud.specialty as doctor_specialty, ud.profileimage as doctor_image
                            FROM Appointments a 
                            LEFT JOIN UserDetails ud ON a.doctor_id = ud.userid 
                            WHERE a.patient_id = ? AND a.status = 'completed'
                            ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    $stmt = $conn->prepare($consultations_query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $consultations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Programări viitoare
    $upcoming_query = "SELECT a.*, ud.name as doctor_name, ud.specialty as doctor_specialty
                       FROM Appointments a 
                       LEFT JOIN UserDetails ud ON a.doctor_id = ud.userid 
                       WHERE a.patient_id = ? AND a.status IN ('pending', 'confirmed') 
                       AND (a.appointment_date > CURDATE() OR (a.appointment_date = CURDATE() AND a.appointment_time >= CURTIME()))
                       ORDER BY a.appointment_date ASC, a.appointment_time ASC";
    $stmt = $conn->prepare($upcoming_query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $upcomingAppointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Servicii pentru afișare
$servicii = [
    'ekg' => 'Electrocardiogramă (EKG)',
    'ecocardiografie' => 'Ecocardiografie Doppler',
    'test_efort' => 'Test de efort computerizat',
    'hemograma' => 'Hemogramă completă',
    'vsh' => 'VSH',
    'glicemie' => 'Glicemie a jeun',
    'radiografie' => 'Radiografie toracică',
    'rmn' => 'RMN',
    'ecografie' => 'Ecografie abdominală',
    'endoscopie' => 'Endoscopie digestivă superioară',
    'colonoscopie' => 'Colonoscopie',
    'helicobacter' => 'Test antigen Helicobacter Pylori',
    'spirometrie' => 'Spirometrie',
    'gazometrie' => 'Gazometrie arterială',
    'bronhoscopie' => 'Bronhoscopie'
];

$specialtyLabels = [
    'cardiolog' => 'Cardiologie',
    'radiolog' => 'Radiologie',
    'gastroenterolog' => 'Gastroenterologie',
    'pneumolog' => 'Pneumologie',
    'medicina_laborator' => 'Medicină de Laborator'
];

$months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'];
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilul meu - Spital</title>
    <link rel="stylesheet" type="text/css" href="css/base.css">
    <link rel="stylesheet" type="text/css" href="css/components/navbar.css">
    <link rel="stylesheet" type="text/css" href="css/pages/profile.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="page-container profile-dashboard">
        <!-- Tabs pentru pacienți -->
        <?php if ($userDetails['role'] === 'patient'): ?>
        <div class="admin-tabs">
            <a href="?tab=profil" class="tab-btn <?php echo $active_tab === 'profil' ? 'active' : ''; ?>">
                Profil
            </a>
            <a href="?tab=programari" class="tab-btn <?php echo $active_tab === 'programari' ? 'active' : ''; ?>">
                Programări
            </a>
            <a href="?tab=istoric" class="tab-btn <?php echo $active_tab === 'istoric' ? 'active' : ''; ?>">
                Istoric Consultații
            </a>
        </div>
        <?php endif; ?>

        <div class="dashboard-content">
            <!-- TAB: PROFIL -->
            <?php if ($active_tab === 'profil'): ?>
                <div class="tab-content">
                    <h1 class="page-title">Profilul meu</h1>
                    
                    <div class="profile-box">
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <?php if (!empty($userDetails['profileimage'])): ?>
                                    <img src="image.php?id=<?php echo $userId; ?>" alt="Profile Picture" class="profile-image">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($userDetails['name'] ?? 'U', 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="profile-info">
                                <h2><?php echo htmlspecialchars($userDetails['name'] ?? 'Utilizator'); ?></h2>
                                <div class="user-badges">
                                    <span class="user-type-badge"><?php echo htmlspecialchars(ucfirst($userDetails['role'])); ?></span>
                                    <?php if ($userDetails['role'] === 'doctor' && !empty($userDetails['specialty'])): ?>
                                        <span class="user-specialty-badge"><?php 
                                            echo htmlspecialchars($specialtyLabels[$userDetails['specialty']] ?? $userDetails['specialty']); 
                                        ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($userDetails['role'] === 'doctor' && $doctorRating !== null): ?>
                                    <div class="doctor-rating-display">
                                        <span class="stars">
                                            <?php 
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= round($doctorRating) ? '★' : '☆';
                                            }
                                            ?>
                                        </span>
                                        <span class="rating-value"><?php echo $doctorRating; ?>/5</span>
                                        <span class="rating-count">(<?php echo $ratingCount; ?> evaluări)</span>
                                    </div>
                                <?php elseif ($userDetails['role'] === 'doctor'): ?>
                                    <div class="doctor-rating-display no-rating">
                                        <span class="stars">☆☆☆☆☆</span>
                                        <span class="rating-count">Nicio evaluare încă</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="profile-details">
                            <?php if ($userDetails['role'] === 'doctor'): ?>
                            <div class="details-section professional-section">
                                <h3 class="section-title">Informații profesionale</h3>
                                <div class="detail-row">
                                    <span class="detail-label">Specializare:</span>
                                    <span class="detail-value"><?php 
                                        echo !empty($userDetails['specialty']) 
                                            ? htmlspecialchars($specialtyLabels[$userDetails['specialty']] ?? $userDetails['specialty'])
                                            : 'Nu este setată';
                                    ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="details-section">
                                <h3 class="section-title">Informații personale</h3>
                                <div class="detail-row">
                                    <span class="detail-label">Email:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($userDetails['email']); ?></span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="detail-label">Data nașterii:</span>
                                    <span class="detail-value"><?php echo $userDetails['birthday'] ? htmlspecialchars(date('d.m.Y', strtotime($userDetails['birthday']))) : 'Nu este setată'; ?></span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="detail-label">Vârstă:</span>
                                    <span class="detail-value"><?php echo $age ? $age . ' ani' : 'Nu este setată'; ?></span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="detail-label">Telefon:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($userDetails['phone'] ?? 'Nu este setat'); ?></span>
                                </div>
                            </div>
                            
                            <div class="details-section">
                                <h3 class="section-title">Locație</h3>
                                <div class="detail-row">
                                    <span class="detail-label">Țară:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($userDetails['country'] ?? 'Nu este setată'); ?></span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="detail-label">Oraș:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($userDetails['city'] ?? 'Nu este setat'); ?></span>
                                </div>
                            </div>
                            
                            <div class="details-section">
                                <h3 class="section-title">Informații fizice</h3>
                                <div class="detail-row">
                                    <span class="detail-label">Înălțime:</span>
                                    <span class="detail-value"><?php echo $userDetails['height'] ? htmlspecialchars($userDetails['height']) . ' cm' : 'Nu este setată'; ?></span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="detail-label">Greutate:</span>
                                    <span class="detail-value"><?php echo $userDetails['weight'] ? htmlspecialchars($userDetails['weight']) . ' kg' : 'Nu este setată'; ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="profile-actions">
                            <a href="settings.php" class="btn btn-red">Editează profilul</a>
                            <a href="change-password.php" class="btn btn-grey">Schimbă parola</a>
                        </div>
                    </div>
                </div>

            <!-- TAB: PROGRAMĂRI -->
            <?php elseif ($active_tab === 'programari'): ?>
                <div class="tab-content">
                    <div class="section-header-row">
                        <h1 class="page-title">Programările mele</h1>
                        <a href="verificareprogramare.php" class="btn btn-red">+ Programare nouă</a>
                    </div>
                    
                    <?php if (count($upcomingAppointments) > 0): ?>
                        <div class="appointments-list">
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                <?php
                                $date = new DateTime($appointment['appointment_date']);
                                $dayName = ['Dum', 'Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sâm'][(int)$date->format('w')];
                                ?>
                                <div class="appointment-card-horizontal">
                                    <div class="appointment-date-box">
                                        <span class="day-name"><?php echo $dayName; ?></span>
                                        <span class="day-number"><?php echo $date->format('d'); ?></span>
                                        <span class="month-name"><?php echo $months[(int)$date->format('n') - 1]; ?></span>
                                    </div>
                                    <div class="appointment-info">
                                        <div class="appointment-time">
                                            <span class="time-icon">🕐</span>
                                            <span><?php echo substr($appointment['appointment_time'], 0, 5); ?></span>
                                        </div>
                                        <h3>Dr. <?php echo htmlspecialchars($appointment['doctor_name'] ?? 'Necunoscut'); ?></h3>
                                        <p class="specialty"><?php echo htmlspecialchars($specialtyLabels[$appointment['doctor_specialty']] ?? 'Medicină Generală'); ?></p>
                                        <p class="service"><?php echo htmlspecialchars($servicii[$appointment['service_key']] ?? $appointment['service_key']); ?></p>
                                    </div>
                                    <div class="appointment-status">
                                        <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                            <?php 
                                            $statusLabels = ['pending' => 'În așteptare', 'confirmed' => 'Confirmată'];
                                            echo $statusLabels[$appointment['status']] ?? $appointment['status']; 
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">📅</div>
                            <h3>Nu ai programări viitoare</h3>
                            <p>Fă o programare nouă pentru a consulta un medic.</p>
                            <a href="verificareprogramare.php" class="btn btn-red">Programează-te acum</a>
                        </div>
                    <?php endif; ?>
                </div>

            <!-- TAB: ISTORIC CONSULTAȚII -->
            <?php elseif ($active_tab === 'istoric'): ?>
                <div class="tab-content">
                    <h1 class="page-title">Istoric consultații (<?php echo count($consultations); ?>)</h1>
                    
                    <?php if (count($consultations) > 0): ?>
                        <div class="consultations-grid">
                            <?php foreach ($consultations as $consultation): ?>
                                <div class="consultation-card">
                                    <div class="card-header">
                                        <div class="doctor-avatar">
                                            <?php if (!empty($consultation['doctor_image'])): ?>
                                                <img src="image.php?id=<?php echo $consultation['doctor_id']; ?>" alt="Dr. <?php echo htmlspecialchars($consultation['doctor_name']); ?>">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($consultation['doctor_name'] ?? 'D', 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="doctor-info">
                                            <h3>Dr. <?php echo htmlspecialchars($consultation['doctor_name'] ?? 'Necunoscut'); ?></h3>
                                            <span class="doctor-specialty"><?php echo htmlspecialchars($specialtyLabels[$consultation['doctor_specialty']] ?? 'Medicină Generală'); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="card-details">
                                        <div class="detail-col">
                                            <span class="detail-label">Data vizitei</span>
                                            <span class="detail-value"><?php 
                                                $date = new DateTime($consultation['appointment_date']);
                                                echo $date->format('d') . ' ' . $months[(int)$date->format('n') - 1] . ' ' . $date->format('Y');
                                            ?></span>
                                        </div>
                                        <div class="detail-col">
                                            <span class="detail-label">Serviciu</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($servicii[$consultation['service_key']] ?? $consultation['service_key']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="card-diagnostic">
                                        <span class="detail-label">Diagnostic</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($consultation['diagnostic'] ?? '-'); ?></span>
                                    </div>
                                    
                                    <div class="card-attachments">
                                        <?php if (!empty($consultation['results_file'])): ?>
                                            <a href="<?php echo htmlspecialchars($consultation['results_file']); ?>" target="_blank" class="attachment-link">📄 Rezultate</a>
                                        <?php endif; ?>
                                        <?php if (!empty($consultation['prescription_file'])): ?>
                                            <a href="<?php echo htmlspecialchars($consultation['prescription_file']); ?>" target="_blank" class="attachment-link">💊 Rețetă</a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="card-rating">
                                        <span class="detail-label">Evaluează doctorul</span>
                                        <form method="POST" class="rating-form">
                                            <input type="hidden" name="rate_appointment" value="1">
                                            <input type="hidden" name="appointment_id" value="<?php echo $consultation['id']; ?>">
                                            <div class="star-rating">
                                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>_<?php echo $consultation['id']; ?>" 
                                                           <?php echo ($consultation['rating'] == $i) ? 'checked' : ''; ?>
                                                           onchange="this.form.submit()">
                                                    <label for="star<?php echo $i; ?>_<?php echo $consultation['id']; ?>" title="<?php echo $i; ?> stele">★</label>
                                                <?php endfor; ?>
                                            </div>
                                            <?php if ($consultation['rating']): ?>
                                                <span class="rating-text"><?php echo $consultation['rating']; ?>/5</span>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                    
                                    <a href="consultatie.php?id=<?php echo $consultation['id']; ?>" class="btn-view-details">Vezi detalii raport</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">📋</div>
                            <h3>Nu ai consultații finalizate</h3>
                            <p>Istoricul consultațiilor tale va apărea aici după vizitele la medic.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
