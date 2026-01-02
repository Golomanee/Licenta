<?php
session_start();
require_once 'config/database.php';

// Verificare autentificare
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$userId = $user['id'];

// Verificare ID consultație
if (!isset($_GET['id'])) {
    header('Location: profile.php');
    exit;
}

$consultationId = (int)$_GET['id'];

// Preluare detalii consultație
$query = "SELECT a.*, 
          ud_doc.name as doctor_name, ud_doc.specialty as doctor_specialty,
          ud_pat.name as patient_name
          FROM Appointments a 
          LEFT JOIN UserDetails ud_doc ON a.doctor_id = ud_doc.userid 
          LEFT JOIN UserDetails ud_pat ON a.patient_id = ud_pat.userid 
          WHERE a.id = ? AND a.patient_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $consultationId, $userId);
$stmt->execute();
$consultation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$consultation) {
    header('Location: profile.php');
    exit;
}

// Servicii
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

$months = ['Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];
$date = new DateTime($consultation['appointment_date']);
$formattedDate = $date->format('d') . ' ' . $months[(int)$date->format('n') - 1] . ' ' . $date->format('Y');

$statusLabels = [
    'pending' => 'În așteptare',
    'confirmed' => 'Confirmată',
    'completed' => 'Finalizată',
    'cancelled' => 'Anulată'
];
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalii Consultație - Spital</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/components/navbar.css">
    <link rel="stylesheet" href="css/pages/consultatie.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="page-container">
        <div class="page-header">
            <a href="profile.php" class="back-link">← Înapoi la profil</a>
            <h1>Raport consultație</h1>
        </div>
        
        <div class="consultation-detail-card">
            <div class="detail-header">
                <div class="doctor-info">
                    <h2>Dr. <?php echo htmlspecialchars($consultation['doctor_name'] ?? 'Necunoscut'); ?></h2>
                    <span class="specialty"><?php echo htmlspecialchars($specialtyLabels[$consultation['doctor_specialty']] ?? 'Medicină Generală'); ?></span>
                </div>
                <div class="status-badge status-<?php echo $consultation['status']; ?>">
                    <?php echo $statusLabels[$consultation['status']] ?? $consultation['status']; ?>
                </div>
            </div>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="label">Data consultației</span>
                    <span class="value"><?php echo $formattedDate; ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Ora</span>
                    <span class="value"><?php echo substr($consultation['appointment_time'], 0, 5); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Serviciu</span>
                    <span class="value"><?php echo htmlspecialchars($servicii[$consultation['service_key']] ?? $consultation['service_key']); ?></span>
                </div>
            </div>
            
            <div class="section">
                <h3>Diagnostic</h3>
                <div class="section-content">
                    <?php if (!empty($consultation['diagnostic'])): ?>
                        <p><?php echo nl2br(htmlspecialchars($consultation['diagnostic'])); ?></p>
                    <?php else: ?>
                        <p class="no-data">Diagnosticul nu a fost încă completat.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($consultation['notes'])): ?>
            <div class="section">
                <h3>Observații</h3>
                <div class="section-content">
                    <p><?php echo nl2br(htmlspecialchars($consultation['notes'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="attachments-section">
                <h3>Documente atașate</h3>
                <div class="attachments-grid">
                    <?php if (!empty($consultation['results_file'])): ?>
                        <a href="<?php echo htmlspecialchars($consultation['results_file']); ?>" target="_blank" class="attachment-card">
                            <span class="attachment-icon">📄</span>
                            <span class="attachment-name">Rezultate analize</span>
                            <span class="attachment-action">Descarcă PDF</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($consultation['prescription_file'])): ?>
                        <a href="<?php echo htmlspecialchars($consultation['prescription_file']); ?>" target="_blank" class="attachment-card">
                            <span class="attachment-icon">💊</span>
                            <span class="attachment-name">Rețetă medicală</span>
                            <span class="attachment-action">Descarcă PDF</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (empty($consultation['results_file']) && empty($consultation['prescription_file'])): ?>
                        <p class="no-data">Nu există documente atașate.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
