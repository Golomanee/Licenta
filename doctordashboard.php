<?php
session_start();
require_once 'config/database.php';

// Verificare dacă utilizatorul este logat și este doctor
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$appointment_success = '';
$appointment_error = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'edu';

// Procesare actualizare programare
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $diagnostic = trim($_POST['diagnostic'] ?? '');
    $mark_complete = isset($_POST['mark_complete']) ? true : false;
    
    // Verifică că programarea aparține acestui doctor
    $check = $conn->prepare("SELECT id FROM Appointments WHERE id = ? AND doctor_id = ?");
    $check->bind_param("ii", $appointment_id, $user_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $appointment_error = 'Programare invalidă.';
    } else {
        // Procesare fișier rezultate
        $results_file = null;
        if (isset($_FILES['results_file']) && $_FILES['results_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['results_file'];
            if ($file['type'] === 'application/pdf') {
                $filename = 'results_' . $appointment_id . '_' . time() . '.pdf';
                $filepath = 'uploads/appointments/' . $filename;
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $results_file = $filepath;
                }
            } else {
                $appointment_error = 'Doar fișiere PDF sunt acceptate pentru rezultate.';
            }
        }
        
        // Procesare fișier rețetă
        $prescription_file = null;
        if (isset($_FILES['prescription_file']) && $_FILES['prescription_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['prescription_file'];
            if ($file['type'] === 'application/pdf') {
                $filename = 'prescription_' . $appointment_id . '_' . time() . '.pdf';
                $filepath = 'uploads/appointments/' . $filename;
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $prescription_file = $filepath;
                }
            } else {
                $appointment_error = 'Doar fișiere PDF sunt acceptate pentru rețetă.';
            }
        }
        
        if (empty($appointment_error)) {
            // Construiește query-ul dinamic
            $updates = [];
            $params = [];
            $types = '';
            
            if (!empty($diagnostic)) {
                $updates[] = "diagnostic = ?";
                $params[] = $diagnostic;
                $types .= 's';
            }
            
            if ($results_file) {
                $updates[] = "results_file = ?";
                $params[] = $results_file;
                $types .= 's';
            }
            
            if ($prescription_file) {
                $updates[] = "prescription_file = ?";
                $params[] = $prescription_file;
                $types .= 's';
            }
            
            if ($mark_complete) {
                $updates[] = "status = 'completed'";
            }
            
            if (count($updates) > 0) {
                $sql = "UPDATE Appointments SET " . implode(", ", $updates) . " WHERE id = ?";
                $params[] = $appointment_id;
                $types .= 'i';
                
                $stmt = $conn->prepare($sql);
                if (!empty($types)) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $stmt->close();
                
                $appointment_success = $mark_complete ? 'Programare finalizată cu succes!' : 'Programare actualizată cu succes!';
            }
        }
    }
    $check->close();
    
    // Redirect pentru a evita resubmiterea formularului
    if (empty($appointment_error)) {
        $redirect_date = $_POST['current_date'] ?? date('Y-m-d');
        header("Location: doctordashboard.php?tab=appointments&date=" . $redirect_date . "&msg=success");
        exit();
    }
}

// Procesare formular POST pentru articole
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    
    // Validare
    if (empty($title)) {
        $error = 'Titlul este obligatoriu!';
    } elseif (strlen($title) < 3) {
        $error = 'Titlul trebuie să aibă cel puțin 3 caractere!';
    } elseif (empty($content)) {
        $error = 'Conținutul este obligatoriu!';
    } elseif (strlen($content) < 10) {
        $error = 'Conținutul trebuie să aibă cel puțin 10 caractere!';
    } else {
        // Procesare imagine
        $image_path = '';
        if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/posts/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_tmp = $_FILES['post_image']['tmp_name'];
            $file_name = $_FILES['post_image']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validare extensie
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($file_ext, $allowed_ext)) {
                // Renumire fișier
                $new_filename = uniqid() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $image_path = $upload_path;
                } else {
                    $error = 'Eroare la încărcarea imaginii!';
                }
            } else {
                $error = 'Tip de fișier neacceptat! Doar JPG, PNG, GIF și WEBP sunt permise.';
            }
        }
        
        // Dacă nu sunt erori, inserare în bază de date
        if (empty($error)) {
            try {
                $query = "INSERT INTO EduPosts (creator_id, title, text, image, created_at) 
                         VALUES (?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($query);
                
                if (!$stmt) {
                    $error = 'Eroare la pregătirea cererii: ' . $conn->error;
                } else {
                    $stmt->bind_param('isss', $user_id, $title, $content, $image_path);
                    
                    if ($stmt->execute()) {
                        $success = 'Articolul a fost publicat cu succes!';
                        // Curățare formular
                        $title = '';
                        $content = '';
                    } else {
                        $error = 'Eroare la salvarea articolului: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            } catch (Exception $e) {
                $error = 'Eroare: ' . $e->getMessage();
            }
        }
    }
}

// Preluare articole create de doctor
$articles_query = "SELECT * FROM EduPosts WHERE creator_id = ? ORDER BY created_at DESC LIMIT 10";
$stmt = $conn->prepare($articles_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$articles_result = $stmt->get_result();
$articles = $articles_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Preluare programări doctor
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$dateObj = new DateTime($selected_date);
$dayOfWeek = (int)$dateObj->format('N');

// Zilele săptămânii în română
$zileSaptamana = [1 => 'Luni', 2 => 'Marți', 3 => 'Miercuri', 4 => 'Joi', 5 => 'Vineri', 6 => 'Sâmbătă', 7 => 'Duminică'];
$luniRomana = [1 => 'Ianuarie', 2 => 'Februarie', 3 => 'Martie', 4 => 'Aprilie', 5 => 'Mai', 6 => 'Iunie', 7 => 'Iulie', 8 => 'August', 9 => 'Septembrie', 10 => 'Octombrie', 11 => 'Noiembrie', 12 => 'Decembrie'];

$ziuaFormatata = $zileSaptamana[$dayOfWeek] . ', ' . $dateObj->format('d') . ' ' . $luniRomana[(int)$dateObj->format('n')];

// Preluare program doctor pentru ziua selectată
$schedule_query = "SELECT * FROM DoctorSchedule WHERE doctor_id = ? AND day_of_week = ?";
$stmt = $conn->prepare($schedule_query);
$stmt->bind_param('ii', $user_id, $dayOfWeek);
$stmt->execute();
$schedule_result = $stmt->get_result();
$doctor_schedule = $schedule_result->fetch_assoc();
$stmt->close();

// Preluare programări pentru ziua selectată
$appointments_query = "SELECT a.*, ud.name as patient_name, u.email as patient_email 
                       FROM Appointments a 
                       LEFT JOIN User u ON a.patient_id = u.id 
                       LEFT JOIN UserDetails ud ON u.id = ud.userid 
                       WHERE a.doctor_id = ? AND a.appointment_date = ?
                       ORDER BY a.appointment_time";
$stmt = $conn->prepare($appointments_query);
$stmt->bind_param('is', $user_id, $selected_date);
$stmt->execute();
$appointments_result = $stmt->get_result();
$appointments = [];
while ($row = $appointments_result->fetch_assoc()) {
    $appointments[$row['appointment_time']] = $row;
}
$stmt->close();

// Generare sloturi
$time_slots = [];
if ($doctor_schedule) {
    $start = new DateTime($doctor_schedule['start_time']);
    $end = new DateTime($doctor_schedule['end_time']);
    $duration = $doctor_schedule['slot_duration'];
    
    while ($start < $end) {
        $timeStr = $start->format('H:i');
        $timeDb = $start->format('H:i:s');
        
        $slot = [
            'time' => $timeStr,
            'time_db' => $timeDb,
            'status' => 'available',
            'appointment' => null
        ];
        
        if (isset($appointments[$timeDb])) {
            $slot['status'] = $appointments[$timeDb]['status'];
            $slot['appointment'] = $appointments[$timeDb];
        }
        
        $time_slots[] = $slot;
        $start->add(new DateInterval('PT' . $duration . 'M'));
    }
}

// Specialități
$specialitati = [
    'cardiologie' => 'Cardiologie',
    'laborator' => 'Analize de Laborator',
    'imagistica' => 'Imagistică Medicală',
    'gastroenterologie' => 'Gastroenterologie',
    'pneumologie' => 'Pneumologie'
];

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
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Doctor - Spital</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/pages/doctordashboard.css">
    <link rel="stylesheet" href="css/components/navbar.css">
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="doctor-dashboard">
        <!-- Tabs -->
        <div class="admin-tabs">
            <a href="?tab=appointments" class="tab-btn <?php echo $active_tab === 'appointments' ? 'active' : ''; ?>">
                Programări
            </a>
            <a href="?tab=edu" class="tab-btn <?php echo $active_tab === 'edu' ? 'active' : ''; ?>">
                EDU
            </a>
        </div>

        <div class="dashboard-content">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <strong>Eroare:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <strong>Succes:</strong> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
                <div class="alert alert-success">
                    <strong>Succes:</strong> Programarea a fost actualizată cu succes!
                </div>
            <?php endif; ?>

            <!-- TAB: EDU -->
            <?php if ($active_tab === 'edu'): ?>
                <div class="tab-content">
                    <div class="section-title">
                        <h2>Adaugă articol</h2>
                    </div>

                    <div class="add-article-section">
                        <div class="article-form-header">
                            <h3>Introdu datele articolului:</h3>
                        </div>

                        <form method="POST" enctype="multipart/form-data" id="addArticleForm">
                            <!-- Titlu -->
                            <div class="form-group">
                                <input 
                                    type="text" 
                                    id="title" 
                                    name="title" 
                                    placeholder="Adauga titlu"
                                    value="<?php echo htmlspecialchars($title ?? ''); ?>"
                                    required
                                    class="input-field"
                                >
                            </div>

                            <!-- Conținut -->
                            <div class="form-group">
                                <textarea 
                                    id="content" 
                                    name="content" 
                                    placeholder="Continut"
                                    required
                                    class="input-field"
                                ><?php echo htmlspecialchars($content ?? ''); ?></textarea>
                            </div>

                            <!-- Imagine -->
                            <div class="form-group">
                                <input 
                                    type="file" 
                                    id="post_image" 
                                    name="post_image" 
                                    accept="image/jpeg,image/png,image/gif,image/webp"
                                    class="input-field"
                                >
                            </div>

                            <!-- Buton Submit -->
                            <div class="form-group">
                                <button type="submit" class="btn-submit">
                                    Publică articol
                                </button>
                            </div>
                        </form>

                        <!-- Articole publicate -->
                        <div class="articles-list-section">
                            <h3>Articolele tale</h3>
                            <?php if (count($articles) > 0): ?>
                                <div class="articles-grid">
                                    <?php foreach ($articles as $article): ?>
                                        <div class="article-card">
                                            <?php if (!empty($article['image'])): ?>
                                                <div class="article-image">
                                                    <img src="<?php echo htmlspecialchars($article['image']); ?>" 
                                                         alt="<?php echo htmlspecialchars($article['title']); ?>">
                                                </div>
                                            <?php endif; ?>
                                            <div class="article-content">
                                                <h4><?php echo htmlspecialchars($article['title']); ?></h4>
                                                <p class="article-date">
                                                    <?php echo date('d M Y', strtotime($article['created_at'])); ?>
                                                </p>
                                                <div class="article-actions" style="margin-top:10px;">
                                                    <a href="editpost.php?id=<?php echo $article['id']; ?>" class="btn-edit">Editează</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="no-articles">Nu ai publicat încă niciun articol.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <!-- TAB: APPOINTMENTS -->
            <?php elseif ($active_tab === 'appointments'): ?>
                <div class="tab-content">
                    <!-- Navigare date -->
                    <div class="date-navigation">
                        <?php
                        $prevDate = (clone $dateObj)->sub(new DateInterval('P1D'))->format('Y-m-d');
                        $nextDate = (clone $dateObj)->add(new DateInterval('P1D'))->format('Y-m-d');
                        $prevDateDisplay = (clone $dateObj)->sub(new DateInterval('P1D'))->format('d') . ' ' . $luniRomana[(int)(clone $dateObj)->sub(new DateInterval('P1D'))->format('n')];
                        $nextDateDisplay = (clone $dateObj)->add(new DateInterval('P1D'))->format('d') . ' ' . $luniRomana[(int)(clone $dateObj)->add(new DateInterval('P1D'))->format('n')];
                        $currentDateDisplay = $dateObj->format('d') . ' ' . $luniRomana[(int)$dateObj->format('n')];
                        ?>
                        <a href="?tab=appointments&date=<?php echo $prevDate; ?>" class="date-nav-link"><?php echo $prevDateDisplay; ?></a>
                        <a href="?tab=appointments&date=<?php echo $prevDate; ?>" class="date-arrow">←</a>
                        <span class="current-date-label"><?php echo $currentDateDisplay; ?></span>
                        <a href="?tab=appointments&date=<?php echo $nextDate; ?>" class="date-arrow date-arrow-next">→</a>
                        <a href="?tab=appointments&date=<?php echo $nextDate; ?>" class="date-nav-link"><?php echo $nextDateDisplay; ?></a>
                    </div>
                    
                    <div class="appointments-section">
                        <div class="section-header">
                            <h2><?php echo $ziuaFormatata; ?></h2>
                        </div>
                        
                        <?php if (count($time_slots) > 0): ?>
                            <div class="time-slots-grid">
                                <?php foreach ($time_slots as $index => $slot): ?>
                                    <?php
                                    $statusClass = '';
                                    $icon = '✓';
                                    if ($slot['status'] === 'pending' || $slot['status'] === 'confirmed') {
                                        $statusClass = 'slot-booked';
                                        $icon = '✓';
                                    } elseif ($slot['status'] === 'cancelled') {
                                        $statusClass = 'slot-cancelled';
                                        $icon = '✕';
                                    } elseif ($slot['status'] === 'completed') {
                                        $statusClass = 'slot-completed';
                                        $icon = '✓';
                                    }
                                    ?>
                                    <div class="time-slot-item <?php echo $statusClass; ?>" 
                                         <?php if ($slot['appointment']): ?>
                                         onclick="showAppointmentDetails(<?php echo htmlspecialchars(json_encode($slot['appointment'])); ?>, '<?php echo $servicii[$slot['appointment']['service_key']] ?? $slot['appointment']['service_key']; ?>')"
                                         <?php endif; ?>>
                                        <span class="slot-time"><?php echo $slot['time']; ?></span>
                                        <span class="slot-icon <?php echo $statusClass; ?>"><?php echo $icon; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-schedule">
                                <p>Nu ai program de lucru setat pentru această zi.</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="load-more-container">
                            <a href="?tab=appointments&date=<?php echo $nextDate; ?>" class="btn-load-more">Încarcă ziua următoare</a>
                        </div>
                    </div>
                    
                    <!-- Panel detalii programare -->
                    <div class="appointment-details-panel" id="appointmentPanel">
                        <form method="POST" enctype="multipart/form-data" id="appointmentForm">
                            <input type="hidden" name="update_appointment" value="1">
                            <input type="hidden" name="appointment_id" id="appointmentId" value="">
                            <input type="hidden" name="current_date" value="<?php echo $selected_date; ?>">
                            
                            <div class="panel-header">
                                <h3 id="patientName">Nume pacient</h3>
                                <button type="button" class="close-panel" onclick="closePanel()">×</button>
                            </div>
                            <div class="panel-content">
                                <div class="detail-item">
                                    <label>Programat pentru:</label>
                                    <span id="appointmentService">-</span>
                                </div>
                                <div class="detail-item">
                                    <label>Data și ora:</label>
                                    <span id="appointmentDateTime">-</span>
                                </div>
                                <div class="detail-item">
                                    <label>Status:</label>
                                    <span id="appointmentStatus" class="status-badge">-</span>
                                </div>
                                <div class="detail-item" id="ratingContainer" style="display: none;">
                                    <label>Rating pacient:</label>
                                    <span id="appointmentRating" class="rating-display">-</span>
                                </div>
                                
                                <div class="form-section">
                                    <label for="diagnostic">Diagnostic:</label>
                                    <textarea name="diagnostic" id="diagnosticField" rows="3" placeholder="Introduceți diagnosticul pacientului..."></textarea>
                                    <div id="existingDiagnostic" class="existing-value"></div>
                                </div>
                                
                                <div class="form-section">
                                    <label for="results_file">Fișier rezultate (PDF):</label>
                                    <input type="file" name="results_file" id="resultsFile" accept="application/pdf">
                                    <div id="existingResults" class="existing-file"></div>
                                </div>
                                
                                <div class="form-section">
                                    <label for="prescription_file">Rețetă (PDF):</label>
                                    <input type="file" name="prescription_file" id="prescriptionFile" accept="application/pdf">
                                    <div id="existingPrescription" class="existing-file"></div>
                                </div>
                                
                                <div class="panel-actions">
                                    <button type="submit" class="panel-btn btn-save">
                                        <span>💾</span> Salvează
                                    </button>
                                    <button type="submit" name="mark_complete" value="1" class="panel-btn btn-complete" id="completeBtn">
                                        <span>✓</span> Finalizează consultația
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Inițializare TinyMCE Editor
        tinymce.init({
            selector: '#content',
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'preview', 'help', 'wordcount',
                'emoticons'
            ],
            toolbar: 'undo redo | formatselect | bold italic underline strikethrough | ' +
                     'forecolor backcolor | alignleft aligncenter alignright alignjustify | ' +
                     'bullist numlist outdent indent | link image media | ' +
                     'blockquote codesample table emoticons | ' +
                     'searchreplace visualblocks fullscreen help',
            
            menubar: 'file edit view insert format tools table help',
            
            height: 300,
            
            content_style: `
                body {
                    font-family: Arial, sans-serif;
                    font-size: 14px;
                }
                h1 { font-size: 24px; }
                h2 { font-size: 20px; }
                h3 { font-size: 18px; }
            `,
            
            automatic_uploads: false,
            
            skin: 'oxide',
            content_css: 'default',
            
            relative_urls: false,
            remove_script_host: false,
            
            setup: function(editor) {
                editor.on('change', function() {
                    tinymce.triggerSave();
                });
            }
        });

        // Validare form înainte de trimitere
        const articleForm = document.getElementById('addArticleForm');
        if (articleForm) {
            articleForm.addEventListener('submit', function(e) {
                const title = document.getElementById('title').value.trim();
                const content = tinymce.get('content').getContent({format: 'text'}).trim();
                
                if (!title) {
                    e.preventDefault();
                    alert('Titlul este obligatoriu!');
                    return;
                }
                
                if (title.length < 3) {
                    e.preventDefault();
                    alert('Titlul trebuie să aibă cel puțin 3 caractere!');
                    return;
                }
                
                if (!content || content.length < 10) {
                    e.preventDefault();
                    alert('Conținutul trebuie să aibă cel puțin 10 caractere!');
                    return;
                }
            });
        }
        
        // Funcții pentru panelul de programări
        let currentAppointment = null;
        
        function showAppointmentDetails(appointment, serviceName) {
            currentAppointment = appointment;
            
            // Populează informațiile de bază
            document.getElementById('appointmentId').value = appointment.id;
            document.getElementById('patientName').textContent = appointment.patient_name || 'Pacient necunoscut';
            document.getElementById('appointmentService').textContent = serviceName;
            document.getElementById('appointmentDateTime').textContent = appointment.appointment_date + ' la ' + appointment.appointment_time.substring(0, 5);
            
            // Status cu badge colorat
            let statusText = '';
            let statusClass = '';
            switch(appointment.status) {
                case 'pending': 
                    statusText = '⏳ În așteptare'; 
                    statusClass = 'status-pending';
                    break;
                case 'confirmed': 
                    statusText = '✓ Confirmată'; 
                    statusClass = 'status-confirmed';
                    break;
                case 'cancelled': 
                    statusText = '✕ Anulată'; 
                    statusClass = 'status-cancelled';
                    break;
                case 'completed': 
                    statusText = '✓ Finalizată'; 
                    statusClass = 'status-completed';
                    break;
                default: 
                    statusText = appointment.status;
                    statusClass = '';
            }
            const statusEl = document.getElementById('appointmentStatus');
            statusEl.textContent = statusText;
            statusEl.className = 'status-badge ' + statusClass;
            
            // Afișează rating-ul dacă există
            const ratingContainer = document.getElementById('ratingContainer');
            const ratingEl = document.getElementById('appointmentRating');
            if (appointment.rating) {
                let stars = '';
                for (let i = 1; i <= 5; i++) {
                    stars += i <= appointment.rating ? '★' : '☆';
                }
                ratingEl.innerHTML = '<span class="stars">' + stars + '</span> (' + appointment.rating + '/5)';
                ratingContainer.style.display = 'flex';
            } else {
                ratingEl.textContent = 'Neevaluat încă';
                ratingContainer.style.display = appointment.status === 'completed' ? 'flex' : 'none';
            }
            
            // Populează diagnosticul existent
            const diagnosticField = document.getElementById('diagnosticField');
            const existingDiagnostic = document.getElementById('existingDiagnostic');
            if (appointment.diagnostic) {
                diagnosticField.value = appointment.diagnostic;
                existingDiagnostic.innerHTML = '<strong>Diagnostic salvat:</strong> ' + appointment.diagnostic;
                existingDiagnostic.style.display = 'block';
            } else {
                diagnosticField.value = '';
                existingDiagnostic.style.display = 'none';
            }
            
            // Afișează fișierele existente
            const existingResults = document.getElementById('existingResults');
            if (appointment.results_file) {
                existingResults.innerHTML = '<a href="' + appointment.results_file + '" target="_blank">📄 Vezi rezultatele</a>';
                existingResults.style.display = 'block';
            } else {
                existingResults.style.display = 'none';
            }
            
            const existingPrescription = document.getElementById('existingPrescription');
            if (appointment.prescription_file) {
                existingPrescription.innerHTML = '<a href="' + appointment.prescription_file + '" target="_blank">📄 Vezi rețeta</a>';
                existingPrescription.style.display = 'block';
            } else {
                existingPrescription.style.display = 'none';
            }
            
            // Ascunde butonul de finalizare dacă e deja finalizată
            const completeBtn = document.getElementById('completeBtn');
            if (appointment.status === 'completed') {
                completeBtn.style.display = 'none';
            } else {
                completeBtn.style.display = 'flex';
            }
            
            // Resetează câmpurile de fișiere
            document.getElementById('resultsFile').value = '';
            document.getElementById('prescriptionFile').value = '';
            
            document.getElementById('appointmentPanel').classList.add('active');
        }
        
        function closePanel() {
            document.getElementById('appointmentPanel').classList.remove('active');
            currentAppointment = null;
        }
        
        // Confirmare înainte de finalizare
        document.getElementById('completeBtn')?.addEventListener('click', function(e) {
            if (!confirm('Ești sigur că vrei să finalizezi această consultație?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
