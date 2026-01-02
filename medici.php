<?php
session_start();
require_once 'config/database.php';

// Filtre
$filter_specialty = isset($_GET['specialty']) ? $_GET['specialty'] : '';
$filter_name = isset($_GET['name']) ? trim($_GET['name']) : '';
$filter_rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;

// Query pentru doctori cu rating mediu
$sql = "SELECT u.id, ud.name, ud.specialty, ud.profileimage,
               AVG(a.rating) as avg_rating, 
               COUNT(a.rating) as rating_count
        FROM User u 
        INNER JOIN UserDetails ud ON u.id = ud.userid 
        LEFT JOIN Appointments a ON u.id = a.doctor_id AND a.rating IS NOT NULL
        WHERE u.role = 'doctor'";

$params = [];
$types = '';

// Filtru specialitate
if (!empty($filter_specialty)) {
    $sql .= " AND ud.specialty = ?";
    $params[] = $filter_specialty;
    $types .= 's';
}

// Filtru nume
if (!empty($filter_name)) {
    $sql .= " AND ud.name LIKE ?";
    $params[] = '%' . $filter_name . '%';
    $types .= 's';
}

$sql .= " GROUP BY u.id, ud.name, ud.specialty, ud.profileimage";

// Filtru rating minim
if ($filter_rating > 0) {
    $sql .= " HAVING avg_rating >= ? OR avg_rating IS NULL";
    $params[] = $filter_rating;
    $types .= 'i';
}

$sql .= " ORDER BY avg_rating DESC, ud.name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$doctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Specialități pentru dropdown
$specialtyLabels = [
    'cardiolog' => 'Cardiologie',
    'radiolog' => 'Radiologie',
    'gastroenterolog' => 'Gastroenterologie',
    'pneumolog' => 'Pneumologie',
    'medicina_laborator' => 'Medicină de Laborator'
];
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicii noștri - Spital</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/components/navbar.css">
    <link rel="stylesheet" href="css/pages/medici.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="page-container">
        <div class="page-header">
            <h1>Medicii noștri</h1>
            <p>Găsește medicul potrivit pentru nevoile tale</p>
        </div>
        
        <!-- Filtre -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="name">Caută după nume</label>
                    <input type="text" id="name" name="name" placeholder="Numele medicului..." 
                           value="<?php echo htmlspecialchars($filter_name); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="specialty">Specialitate</label>
                    <select id="specialty" name="specialty">
                        <option value="">Toate specialitățile</option>
                        <?php foreach ($specialtyLabels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $filter_specialty === $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="rating">Rating minim</label>
                    <select id="rating" name="rating">
                        <option value="0" <?php echo $filter_rating == 0 ? 'selected' : ''; ?>>Orice rating</option>
                        <option value="4" <?php echo $filter_rating == 4 ? 'selected' : ''; ?>>★★★★ 4+</option>
                        <option value="3" <?php echo $filter_rating == 3 ? 'selected' : ''; ?>>★★★ 3+</option>
                        <option value="2" <?php echo $filter_rating == 2 ? 'selected' : ''; ?>>★★ 2+</option>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-red">Caută</button>
                    <a href="medici.php" class="btn btn-grey">Resetează</a>
                </div>
            </form>
        </div>
        
        <!-- Rezultate -->
        <div class="results-info">
            <span><?php echo count($doctors); ?> medici găsiți</span>
        </div>
        
        <!-- Grid doctori -->
        <?php if (count($doctors) > 0): ?>
            <div class="doctors-grid">
                <?php foreach ($doctors as $doctor): ?>
                    <div class="doctor-card">
                        <div class="doctor-avatar">
                            <?php if (!empty($doctor['profileimage'])): ?>
                                <img src="image.php?id=<?php echo $doctor['id']; ?>" alt="Dr. <?php echo htmlspecialchars($doctor['name']); ?>">
                            <?php else: ?>
                                <?php echo strtoupper(substr($doctor['name'] ?? 'D', 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="doctor-info">
                            <h3>Dr. <?php echo htmlspecialchars($doctor['name']); ?></h3>
                            <span class="doctor-specialty"><?php echo htmlspecialchars($specialtyLabels[$doctor['specialty']] ?? 'Medic'); ?></span>
                            
                            <div class="doctor-rating">
                                <?php if ($doctor['avg_rating']): ?>
                                    <span class="stars">
                                        <?php 
                                        $avgRating = round($doctor['avg_rating'], 1);
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= round($avgRating) ? '★' : '☆';
                                        }
                                        ?>
                                    </span>
                                    <span class="rating-value"><?php echo $avgRating; ?></span>
                                    <span class="rating-count">(<?php echo $doctor['rating_count']; ?>)</span>
                                <?php else: ?>
                                    <span class="stars no-rating">☆☆☆☆☆</span>
                                    <span class="rating-count">Fără evaluări</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="doctor-actions">
                            <a href="verificareprogramare.php?doctor=<?php echo $doctor['id']; ?>" class="btn btn-red">Programează-te</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🔍</div>
                <h3>Niciun medic găsit</h3>
                <p>Încearcă să modifici criteriile de căutare.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
