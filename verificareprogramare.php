<?php
session_start();

require_once 'config/database.php';

// Verifică dacă vine un doctor din URL
$preselectedDoctor = isset($_GET['doctor']) ? (int)$_GET['doctor'] : null;
$doctorInfo = null;
$doctorSpecialty = null;

// Mapare specialty din DB către key-ul specialitate
$specialtyToKey = [
    'cardiolog' => 'cardiologie',
    'medicina_laborator' => 'laborator',
    'radiolog' => 'imagistica',
    'gastroenterolog' => 'gastroenterologie',
    'pneumolog' => 'pneumologie'
];

if ($preselectedDoctor) {
    // Preia informațiile doctorului
    $stmt = $conn->prepare("SELECT u.id, ud.name, ud.specialty FROM User u 
                            INNER JOIN UserDetails ud ON u.id = ud.userid 
                            WHERE u.id = ? AND u.role = 'doctor'");
    $stmt->bind_param('i', $preselectedDoctor);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $doctorInfo = $result->fetch_assoc();
        $doctorSpecialty = $specialtyToKey[$doctorInfo['specialty']] ?? null;
    }
    $stmt->close();
}

// Specialitățile și serviciile asociate
$specialitati = [
    'cardiologie' => [
        'nume' => 'Cardiologie',
        'servicii' => [
            'ekg' => 'Electrocardiogramă (EKG)',
            'ecocardiografie' => 'Ecocardiografie Doppler',
            'test_efort' => 'Test de efort computerizat'
        ]
    ],
    'laborator' => [
        'nume' => 'Analize de Laborator',
        'servicii' => [
            'hemograma' => 'Hemogramă completă',
            'vsh' => 'VSH (Viteza de sedimentare a eritrocitelor)',
            'glicemie' => 'Glicemie a jeun'
        ]
    ],
    'imagistica' => [
        'nume' => 'Imagistică Medicală',
        'servicii' => [
            'radiografie' => 'Radiografie toracică',
            'rmn' => 'RMN (Rezonanță Magnetică Nucleară)',
            'ecografie' => 'Ecografie abdominală'
        ]
    ],
    'gastroenterologie' => [
        'nume' => 'Gastroenterologie',
        'servicii' => [
            'endoscopie' => 'Endoscopie digestivă superioară',
            'colonoscopie' => 'Colonoscopie',
            'helicobacter' => 'Test antigen Helicobacter Pylori'
        ]
    ],
    'pneumologie' => [
        'nume' => 'Pneumologie',
        'servicii' => [
            'spirometrie' => 'Spirometrie',
            'gazometrie' => 'Gazometrie arterială',
            'bronhoscopie' => 'Bronhoscopie'
        ]
    ]
];

// Procesare formular
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $specialitate = $_POST['specialitate'] ?? '';
    $serviciu = $_POST['serviciu'] ?? '';
    $doctorId = $_POST['doctor_id'] ?? '';
    
    if (empty($specialitate)) {
        $message = 'Te rugăm să selectezi o specialitate.';
        $messageType = 'error';
    } elseif (empty($serviciu)) {
        $message = 'Te rugăm să selectezi un serviciu.';
        $messageType = 'error';
    } else {
        // Redirecționează către pagina de disponibilitate
        $url = 'disponibilitate.php?specialitate=' . urlencode($specialitate) . '&serviciu=' . urlencode($serviciu);
        if ($doctorId) {
            $url .= '&doctor_id=' . urlencode($doctorId);
        }
        header('Location: ' . $url);
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verifică Programare - Spital</title>
    <link rel="stylesheet" type="text/css" href="css/base.css">
    <link rel="stylesheet" type="text/css" href="css/components/navbar.css">
    <link rel="stylesheet" type="text/css" href="css/components/appointment.css">
    <style>
        .programare-container {
            max-width: 700px;
            margin: 50px auto;
            padding: 0 20px;
        }
        
        .programare-title {
            font-size: 32px;
            font-weight: 700;
            color: #1f2b3d;
            margin-bottom: 30px;
        }
        
        .programare-box {
            background: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
        }
        
        .programare-subtitle {
            font-size: 22px;
            font-weight: 600;
            color: #1f2b3d;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #555;
            margin-bottom: 10px;
        }
        
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            color: #333;
            background: #fff;
            cursor: pointer;
            transition: border-color 0.3s;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
        }
        
        .form-group select:focus {
            outline: none;
            border-color: #d82323;
        }
        
        .form-group select:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }
        
        .btn-verificare {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #ffb3b3, #ffc4c4);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            color: #333;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-verificare:hover {
            background: linear-gradient(135deg, #ff9999, #ffb3b3);
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #e6ffe6;
            color: #28a745;
            border: 1px solid #28a745;
        }
        
        .alert-error {
            background: #ffe6e6;
            color: #d82323;
            border: 1px solid #d82323;
        }
        
        .serviciu-icon {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            vertical-align: middle;
            opacity: 0.6;
        }
        
        .doctor-preselected {
            display: flex;
            align-items: center;
            gap: 15px;
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #81c784;
        }
        
        .doctor-preselected .doctor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #4caf50;
        }
        
        .doctor-preselected-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .doctor-preselected-info strong {
            font-size: 18px;
            color: #2e7d32;
        }
        
        .doctor-preselected-info span {
            color: #558b2f;
            font-size: 14px;
        }
        
        .specialty-locked {
            background: #f5f5f5;
            padding: 14px;
            border-radius: 8px;
            color: #666;
            font-weight: 500;
            border: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="programare-container">
        <h1 class="programare-title">Programează-te</h1>
        
        <div class="programare-box">
            <h2 class="programare-subtitle">Introdu detaliile programării</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($doctorInfo): ?>
                <div class="doctor-preselected">
                    <?php
                    // Verifică dacă doctorul are imagine de profil
                    $avatarStmt = $conn->prepare("SELECT IF(profileimage IS NOT NULL, 1, 0) as has_image FROM UserDetails WHERE userid = ?");
                    $avatarStmt->bind_param('i', $doctorInfo['id']);
                    $avatarStmt->execute();
                    $avatarResult = $avatarStmt->get_result();
                    $avatarRow = $avatarResult->fetch_assoc();
                    $hasImage = $avatarRow['has_image'] ?? 0;
                    $avatarStmt->close();
                    ?>
                    <?php if ($hasImage): ?>
                        <img src="image.php?id=<?php echo $doctorInfo['id']; ?>" 
                             alt="<?php echo htmlspecialchars($doctorInfo['name']); ?>" class="doctor-avatar">
                    <?php else: ?>
                        <img src="images/default-avatar.png" 
                             alt="<?php echo htmlspecialchars($doctorInfo['name']); ?>" class="doctor-avatar">
                    <?php endif; ?>
                    <div class="doctor-preselected-info">
                        <strong>Dr. <?php echo htmlspecialchars($doctorInfo['name']); ?></strong>
                        <span><?php echo htmlspecialchars($specialitati[$doctorSpecialty]['nume'] ?? $doctorInfo['specialty']); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?php if ($doctorInfo): ?>
                    <input type="hidden" name="doctor_id" value="<?php echo $doctorInfo['id']; ?>">
                    <input type="hidden" name="specialitate" value="<?php echo htmlspecialchars($doctorSpecialty); ?>">
                    
                    <div class="form-group">
                        <label>Specialitate</label>
                        <div class="specialty-locked">
                            <?php echo htmlspecialchars($specialitati[$doctorSpecialty]['nume'] ?? $doctorInfo['specialty']); ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="serviciu">Alege servicii, investigații</label>
                        <select id="serviciu" name="serviciu" required>
                            <option value="">🏥 Selectează serviciul</option>
                            <?php if ($doctorSpecialty && isset($specialitati[$doctorSpecialty])): ?>
                                <?php foreach ($specialitati[$doctorSpecialty]['servicii'] as $key => $serviciu): ?>
                                    <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($serviciu); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label for="specialitate">Alege specialitatea</label>
                        <select id="specialitate" name="specialitate" required>
                            <option value="">Toate specialitățile</option>
                            <?php foreach ($specialitati as $key => $spec): ?>
                                <option value="<?php echo $key; ?>" <?php echo (isset($_POST['specialitate']) && $_POST['specialitate'] === $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec['nume']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="serviciu">Alege servicii, investigații</label>
                        <select id="serviciu" name="serviciu" required disabled>
                            <option value="">🏥 Toate serviciile</option>
                        </select>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-verificare">Verifică disponibilitatea</button>
            </form>
        </div>
    </div>
    
    <script>
        <?php if (!$doctorInfo): ?>
        // Date pentru servicii
        const serviciiData = <?php echo json_encode($specialitati); ?>;
        
        const specialitateSelect = document.getElementById('specialitate');
        const serviciuSelect = document.getElementById('serviciu');
        
        specialitateSelect.addEventListener('change', function() {
            const specialitate = this.value;
            
            // Resetează serviciul
            serviciuSelect.innerHTML = '<option value="">🏥 Toate serviciile</option>';
            
            if (specialitate && serviciiData[specialitate]) {
                serviciuSelect.disabled = false;
                
                const servicii = serviciiData[specialitate].servicii;
                for (const [key, nume] of Object.entries(servicii)) {
                    const option = document.createElement('option');
                    option.value = key;
                    option.textContent = nume;
                    serviciuSelect.appendChild(option);
                }
            } else {
                serviciuSelect.disabled = true;
            }
        });
        
        // Dacă există o specialitate selectată la încărcarea paginii
        <?php if (isset($_POST['specialitate']) && !empty($_POST['specialitate'])): ?>
            specialitateSelect.dispatchEvent(new Event('change'));
            <?php if (isset($_POST['serviciu']) && !empty($_POST['serviciu'])): ?>
                serviciuSelect.value = '<?php echo htmlspecialchars($_POST['serviciu']); ?>';
            <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>
