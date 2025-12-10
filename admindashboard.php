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

// Handle delete user
if (isset($_POST['delete_user'])) {
    $userId = $_POST['user_id'];
    $stmt = $conn->prepare("DELETE FROM User WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
    header('Location: admindashboard.php');
    exit;
}

// Get search and filter parameters
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$roleFilter = isset($_GET['role']) ? $_GET['role'] : '';

// Build SQL query with search and filter
$sql = "SELECT u.id, u.email, u.role, u.email_verified, ud.name, IF(ud.profileimage IS NOT NULL, 1, 0) as has_image 
        FROM User u 
        LEFT JOIN UserDetails ud ON u.id = ud.userid 
        WHERE 1=1";

$params = [];
$types = '';

// Add search condition
if (!empty($searchQuery)) {
    $sql .= " AND (ud.name LIKE ? OR u.email LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

// Add role filter
if (!empty($roleFilter) && in_array($roleFilter, ['patient', 'doctor', 'admin'])) {
    $sql .= " AND u.role = ?";
    $params[] = $roleFilter;
    $types .= 's';
}

$sql .= " ORDER BY u.id";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $result = $conn->query($sql);
    $users = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - Spital</title>
    <link rel="stylesheet" type="text/css" href="css/base.css">
    <link rel="stylesheet" type="text/css" href="css/components/navbar.css">
    <link rel="stylesheet" type="text/css" href="css/pages/admin.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="page-container">
        <h1 class="page-title">Administreaza conturi</h1>
        
        <div class="admin-tabs">
            <button class="tab-btn active" onclick="showTab('users')">Administreaza conturi</button>
            <button class="tab-btn" onclick="showTab('posts')">Editeaza postari</button>
        </div>
        
        <div id="users-tab" class="tab-content active">
            <!-- Search and Filter Section -->
            <div class="search-filter-container">
                <form method="GET" action="admindashboard.php" class="search-filter-form">
                    <div class="search-box">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        <input type="text" name="search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($searchQuery); ?>" class="search-input">
                    </div>
                    
                    <div class="filter-box">
                        <svg class="filter-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                        </svg>
                        <select name="role" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Roles</option>
                            <option value="patient" <?php echo $roleFilter === 'patient' ? 'selected' : ''; ?>>Patient</option>
                            <option value="doctor" <?php echo $roleFilter === 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                            <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="search-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        Search
                    </button>
                    
                    <?php if (!empty($searchQuery) || !empty($roleFilter)): ?>
                        <a href="admindashboard.php" class="clear-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Clear
                        </a>
                    <?php endif; ?>
                </form>
                
                <div class="results-count">
                    <span><?php echo count($users); ?> user<?php echo count($users) !== 1 ? 's' : ''; ?> found</span>
                </div>
            </div>
            
            <div class="users-list">
                <?php foreach ($users as $user): ?>
                    <div class="user-card">
                        <div class="user-avatar">
                            <?php if ($user['has_image']): ?>
                                <img src="image.php?id=<?php echo $user['id']; ?>" alt="Profile" class="avatar-image">
                            <?php else: ?>
                                <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <h3><?php echo htmlspecialchars($user['name'] ?? 'Nume necunoscut'); ?></h3>
                            <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
                            <span class="user-role"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span>
                            <?php if ($user['email_verified']): ?>
                                <span class="verified-badge">✓ Verificat</span>
                            <?php endif; ?>
                        </div>
                        <div class="user-actions">
                            <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn-edit">Editeaza</a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Sigur vrei să ștergi acest utilizator?');">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="delete_user" class="btn-delete">Sterge</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div id="posts-tab" class="tab-content">
            <p style="text-align: center; color: #666; padding: 40px;">Funcționalitate în dezvoltare...</p>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
        
        // Prevent back button cache
        window.onpageshow = function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
    </script>
</body>
</html>
