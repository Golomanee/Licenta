<?php
session_start();

require_once 'config/database.php';

// Verifică parametrii
$specialitate = $_GET['specialitate'] ?? '';
$serviciu = $_GET['serviciu'] ?? '';
$data = $_GET['data'] ?? date('Y-m-d');
$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : null;

// Specialitățile și serviciile asociate
$specialitati = [
    'cardiologie' => [
        'nume' => 'Cardiologie',
        'db_specialty' => 'cardiolog',
        'servicii' => [
            'ekg' => 'Electrocardiogramă (EKG)',
            'ecocardiografie' => 'Ecocardiografie Doppler',
            'test_efort' => 'Test de efort computerizat'
        ]
    ],
    'laborator' => [
        'nume' => 'Analize de Laborator',
        'db_specialty' => 'medicina_laborator',
        'servicii' => [
            'hemograma' => 'Hemogramă completă',
            'vsh' => 'VSH (Viteza de sedimentare a eritrocitelor)',
            'glicemie' => 'Glicemie a jeun'
        ]
    ],
    'imagistica' => [
        'nume' => 'Imagistică Medicală',
        'db_specialty' => 'radiolog',
        'servicii' => [
            'radiografie' => 'Radiografie toracică',
            'rmn' => 'RMN (Rezonanță Magnetică Nucleară)',
            'ecografie' => 'Ecografie abdominală'
        ]
    ],
    'gastroenterologie' => [
        'nume' => 'Gastroenterologie',
        'db_specialty' => 'gastroenterolog',
        'servicii' => [
            'endoscopie' => 'Endoscopie digestivă superioară',
            'colonoscopie' => 'Colonoscopie',
            'helicobacter' => 'Test antigen Helicobacter Pylori'
        ]
    ],
    'pneumologie' => [
        'nume' => 'Pneumologie',
        'db_specialty' => 'pneumolog',
        'servicii' => [
            'spirometrie' => 'Spirometrie',
            'gazometrie' => 'Gazometrie arterială',
            'bronhoscopie' => 'Bronhoscopie'
        ]
    ]
];

// Validare
if (empty($specialitate) || !isset($specialitati[$specialitate])) {
    header('Location: verificareprogramare.php');
    exit;
}

$numeSpecialitate = $specialitati[$specialitate]['nume'];
$numeServiciu = $specialitati[$specialitate]['servicii'][$serviciu] ?? 'Consultație';
$dbSpecialty = $specialitati[$specialitate]['db_specialty'];

// Obține ziua săptămânii (1=Luni, 7=Duminica)
$dateObj = new DateTime($data);
$dayOfWeek = (int)$dateObj->format('N');

// Zilele săptămânii în română
$zileSaptamana = [
    1 => 'Luni',
    2 => 'Marți', 
    3 => 'Miercuri',
    4 => 'Joi',
    5 => 'Vineri',
    6 => 'Sâmbătă',
    7 => 'Duminică'
];

$luniRomana = [
    1 => 'Ianuarie', 2 => 'Februarie', 3 => 'Martie', 4 => 'Aprilie',
    5 => 'Mai', 6 => 'Iunie', 7 => 'Iulie', 8 => 'August',
    9 => 'Septembrie', 10 => 'Octombrie', 11 => 'Noiembrie', 12 => 'Decembrie'
];

$ziuaFormatata = $zileSaptamana[$dayOfWeek] . ', ' . $dateObj->format('d') . ' ' . $luniRomana[(int)$dateObj->format('n')];

// Mapare specialty către nume afișabil
$specialtyNames = [
    'cardiolog' => 'Cardiolog',
    'radiolog' => 'Radiolog',
    'gastroenterolog' => 'Gastroenterolog',
    'pneumolog' => 'Pneumolog',
    'medicina_laborator' => 'Medic de laborator'
];

// Query pentru medici din specialitatea respectivă care lucrează în ziua selectată
$sql = "SELECT u.id, ud.name, ud.specialty, ud.profileimage,
        ds.start_time, ds.end_time, ds.slot_duration,
        COALESCE(AVG(a.rating), 0) as avg_rating,
        COUNT(a.rating) as rating_count
        FROM User u
        INNER JOIN UserDetails ud ON u.id = ud.userid
        INNER JOIN DoctorSchedule ds ON u.id = ds.doctor_id
        LEFT JOIN Appointments a ON u.id = a.doctor_id AND a.rating IS NOT NULL
        WHERE u.role = 'doctor' 
        AND ud.specialty = ?
        AND ds.day_of_week = ?";

// Dacă avem un doctor preselectat, filtrăm doar după el
if ($doctorId) {
    $sql .= " AND u.id = ?";
}

$sql .= " GROUP BY u.id, ud.name, ud.specialty, ud.profileimage, ds.start_time, ds.end_time, ds.slot_duration";
$sql .= " ORDER BY ud.name";

$stmt = $conn->prepare($sql);
if ($doctorId) {
    $stmt->bind_param("sii", $dbSpecialty, $dayOfWeek, $doctorId);
} else {
    $stmt->bind_param("si", $dbSpecialty, $dayOfWeek);
}
$stmt->execute();
$result = $stmt->get_result();
$medici = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Funcție pentru a genera sloturile de timp
function generateTimeSlots($startTime, $endTime, $duration, $doctorId, $date, $conn) {
    $slots = [];
    $start = new DateTime($startTime);
    $end = new DateTime($endTime);
    
    // Obține programările existente pentru acest doctor în această zi
    $stmt = $conn->prepare("SELECT appointment_time FROM Appointments WHERE doctor_id = ? AND appointment_date = ? AND status != 'cancelled'");
    $stmt->bind_param("is", $doctorId, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookedSlots = [];
    while ($row = $result->fetch_assoc()) {
        $bookedSlots[] = $row['appointment_time'];
    }
    $stmt->close();
    
    while ($start < $end) {
        $timeStr = $start->format('H:i');
        $timeDb = $start->format('H:i:s');
        $isBooked = in_array($timeDb, $bookedSlots);
        
        $slots[] = [
            'time' => $timeStr,
            'time_db' => $timeDb,
            'available' => !$isBooked
        ];
        
        $start->add(new DateInterval('PT' . $duration . 'M'));
    }
    
    return $slots;
}

// Procesare programare
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    if (!isset($_SESSION['user'])) {
        $message = 'Trebuie să fii autentificat pentru a face o programare.';
        $messageType = 'error';
    } else {
        $doctorId = (int)$_POST['doctor_id'];
        $appointmentTime = $_POST['appointment_time'];
        $patientId = $_SESSION['user']['id'];
        
        // Verifică dacă slotul este încă disponibil
        $checkStmt = $conn->prepare("SELECT id FROM Appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'");
        $checkStmt->bind_param("iss", $doctorId, $data, $appointmentTime);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $message = 'Ne pare rău, acest interval a fost rezervat între timp. Te rugăm să alegi altul.';
            $messageType = 'error';
        } else {
            // Inserează programarea
            $insertStmt = $conn->prepare("INSERT INTO Appointments (patient_id, doctor_id, specialty_key, service_key, appointment_date, appointment_time) VALUES (?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("iissss", $patientId, $doctorId, $specialitate, $serviciu, $data, $appointmentTime);
            
            if ($insertStmt->execute()) {
                $message = 'Programarea a fost efectuată cu succes! Vei fi contactat pentru confirmare.';
                $messageType = 'success';
            } else {
                $message = 'A apărut o eroare. Te rugăm să încerci din nou.';
                $messageType = 'error';
            }
            $insertStmt->close();
        }
        $checkStmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Disponibilitate Medici - <?php echo htmlspecialchars($numeSpecialitate); ?></title>
    <link rel="stylesheet" type="text/css" href="css/base.css">
    <link rel="stylesheet" type="text/css" href="css/components/navbar.css">
    <style>
        .results-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .results-header {
            margin-bottom: 30px;
        }
        
        .results-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1f2b3d;
            margin-bottom: 10px;
        }
        
        .results-subtitle {
            color: #666;
            font-size: 15px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #d82323;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .date-navigation {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            background: #fff;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .date-nav-btn {
            padding: 10px 20px;
            background: #f5f5f5;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .date-nav-btn:hover {
            background: #e0e0e0;
        }
        
        .current-date {
            font-size: 18px;
            font-weight: 600;
            color: #1f2b3d;
        }
        
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        
        .doctor-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        
        .doctor-header {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .doctor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            color: #999;
            overflow: hidden;
        }
        
        .doctor-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .doctor-name {
            font-size: 16px;
            font-weight: 700;
            color: #1f2b3d;
            margin-bottom: 2px;
        }
        
        .doctor-specialty {
            font-size: 13px;
            color: #666;
            margin-bottom: 4px;
        }
        
        .doctor-rating {
            font-size: 13px;
            color: #1a5f7a;
            font-weight: 600;
        }
        
        .doctor-location {
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px solid #eee;
        }
        
        .location-label {
            font-size: 11px;
            font-weight: 700;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .location-value {
            font-size: 14px;
            color: #1a5f7a;
        }
        
        .time-slots {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .time-slot {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
            color: #333;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .time-slot:hover {
            border-color: #28a745;
            background: #e6ffe6;
        }
        
        .time-slot.selected {
            border-color: #28a745;
            background: #28a745;
            color: #fff;
        }
        
        .time-slot.booked {
            background: #f5f5f5;
            color: #999;
            cursor: not-allowed;
            text-decoration: line-through;
        }
        
        .time-slot .check-icon {
            color: #28a745;
        }
        
        .time-slot.selected .check-icon {
            color: #fff;
        }
        
        .see-all-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .see-all-btn:hover {
            border-color: #d82323;
            color: #d82323;
        }
        
        .load-more-btn {
            display: block;
            margin: 40px auto;
            padding: 15px 40px;
            background: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            color: #333;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .load-more-btn:hover {
            border-color: #333;
        }
        
        .no-doctors {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        
        .no-doctors h3 {
            color: #1f2b3d;
            margin-bottom: 10px;
        }
        
        .no-doctors p {
            color: #666;
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
        
        .book-form {
            display: none;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .book-form.active {
            display: block;
        }
        
        .book-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .book-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .hidden-slots {
            display: none;
        }
        
        .hidden-slots.show {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="results-container">
        <a href="verificareprogramare.php<?php echo $doctorId ? '?doctor=' . urlencode($doctorId) : ''; ?>" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Înapoi la căutare
        </a>
        
        <div class="results-header">
            <h1><?php echo htmlspecialchars($ziuaFormatata); ?></h1>
            <p class="results-subtitle"><?php echo htmlspecialchars($numeSpecialitate); ?> - <?php echo htmlspecialchars($numeServiciu); ?></p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="date-navigation">
            <?php
            $prevDate = (clone $dateObj)->sub(new DateInterval('P1D'))->format('Y-m-d');
            $nextDate = (clone $dateObj)->add(new DateInterval('P1D'))->format('Y-m-d');
            $today = date('Y-m-d');
            $doctorParam = $doctorId ? '&doctor_id=' . urlencode($doctorId) : '';
            ?>
            <?php if ($data > $today): ?>
                <a href="?specialitate=<?php echo urlencode($specialitate); ?>&serviciu=<?php echo urlencode($serviciu); ?>&data=<?php echo $prevDate; ?><?php echo $doctorParam; ?>" class="date-nav-btn">← Ziua anterioară</a>
            <?php endif; ?>
            <span class="current-date"><?php echo htmlspecialchars($ziuaFormatata); ?></span>
            <a href="?specialitate=<?php echo urlencode($specialitate); ?>&serviciu=<?php echo urlencode($serviciu); ?>&data=<?php echo $nextDate; ?><?php echo $doctorParam; ?>" class="date-nav-btn">Ziua următoare →</a>
        </div>
        
        <?php if (count($medici) > 0): ?>
            <div class="doctors-grid">
                <?php foreach ($medici as $medic): ?>
                    <?php 
                    $slots = generateTimeSlots($medic['start_time'], $medic['end_time'], $medic['slot_duration'], $medic['id'], $data, $conn);
                    $visibleSlots = array_slice($slots, 0, 6);
                    $hiddenSlots = array_slice($slots, 6);
                    ?>
                    <div class="doctor-card">
                        <div class="doctor-header">
                            <div class="doctor-avatar">
                                <?php if (!empty($medic['profileimage'])): ?>
                                    <img src="image.php?id=<?php echo $medic['id']; ?>" alt="<?php echo htmlspecialchars($medic['name']); ?>">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($medic['name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="doctor-name"><?php echo htmlspecialchars($medic['name']); ?></div>
                                <div class="doctor-specialty"><?php echo htmlspecialchars($specialtyNames[$medic['specialty']] ?? $medic['specialty']); ?></div>
                                <div class="doctor-rating">
                                    <?php if ($medic['rating_count'] > 0): ?>
                                        Nota <?php echo number_format($medic['avg_rating'], 2); ?> · <?php echo $medic['rating_count']; ?> recenzii
                                    <?php else: ?>
                                        Fără recenzii
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="doctor-location">
                            <div class="location-label">LOCAȚIA</div>
                            <div class="location-value">Campus medical<br>Baia Mare</div>
                        </div>
                        
                        <div class="time-slots" id="slots-<?php echo $medic['id']; ?>">
                            <?php foreach ($visibleSlots as $slot): ?>
                                <?php if ($slot['available']): ?>
                                    <label class="time-slot" onclick="selectSlot(this, <?php echo $medic['id']; ?>, '<?php echo $slot['time_db']; ?>')">
                                        <?php echo $slot['time']; ?>
                                        <span class="check-icon">✓</span>
                                    </label>
                                <?php else: ?>
                                    <span class="time-slot booked"><?php echo $slot['time']; ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($hiddenSlots) > 0): ?>
                            <div class="hidden-slots" id="hidden-slots-<?php echo $medic['id']; ?>">
                                <?php foreach ($hiddenSlots as $slot): ?>
                                    <?php if ($slot['available']): ?>
                                        <label class="time-slot" onclick="selectSlot(this, <?php echo $medic['id']; ?>, '<?php echo $slot['time_db']; ?>')">
                                            <?php echo $slot['time']; ?>
                                            <span class="check-icon">✓</span>
                                        </label>
                                    <?php else: ?>
                                        <span class="time-slot booked"><?php echo $slot['time']; ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="see-all-btn" onclick="toggleSlots(<?php echo $medic['id']; ?>)">
                                Vezi toate intervalele disponibile
                            </button>
                        <?php endif; ?>
                        
                        <form method="POST" class="book-form" id="book-form-<?php echo $medic['id']; ?>">
                            <input type="hidden" name="doctor_id" value="<?php echo $medic['id']; ?>">
                            <input type="hidden" name="appointment_time" id="time-input-<?php echo $medic['id']; ?>" value="">
                            <input type="hidden" name="book_appointment" value="1">
                            <button type="submit" class="book-btn">Confirmă programarea</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <button class="load-more-btn" onclick="loadNextDay()">Încarcă ziua următoare</button>
        <?php else: ?>
            <div class="no-doctors">
                <h3>Nu există medici disponibili</h3>
                <p>Nu am găsit medici din specialitatea <?php echo htmlspecialchars($numeSpecialitate); ?> care să lucreze în această zi.</p>
                <p>Încearcă să selectezi o altă zi sau altă specialitate.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        let selectedSlots = {};
        
        function selectSlot(element, doctorId, time) {
            // Deselectează slotul anterior pentru acest doctor
            const container = document.getElementById('slots-' + doctorId);
            const hiddenContainer = document.getElementById('hidden-slots-' + doctorId);
            
            container.querySelectorAll('.time-slot').forEach(slot => {
                slot.classList.remove('selected');
            });
            
            if (hiddenContainer) {
                hiddenContainer.querySelectorAll('.time-slot').forEach(slot => {
                    slot.classList.remove('selected');
                });
            }
            
            // Selectează noul slot
            element.classList.add('selected');
            selectedSlots[doctorId] = time;
            
            // Actualizează formularul și îl afișează
            document.getElementById('time-input-' + doctorId).value = time;
            document.getElementById('book-form-' + doctorId).classList.add('active');
        }
        
        function toggleSlots(doctorId) {
            const hiddenSlots = document.getElementById('hidden-slots-' + doctorId);
            const btn = event.target;
            
            if (hiddenSlots.classList.contains('show')) {
                hiddenSlots.classList.remove('show');
                btn.textContent = 'Vezi toate intervalele disponibile';
            } else {
                hiddenSlots.classList.add('show');
                btn.textContent = 'Ascunde intervalele';
            }
        }
        
        function loadNextDay() {
            const nextDate = '<?php echo $nextDate; ?>';
            window.location.href = '?specialitate=<?php echo urlencode($specialitate); ?>&serviciu=<?php echo urlencode($serviciu); ?>&data=' + nextDate;
        }
    </script>
</body>
</html>
