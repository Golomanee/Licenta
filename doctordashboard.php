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
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'edu';

// Procesare formular POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        <div class="dashboard-tabs">
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
                    <div class="section-title">
                        <h2>Programările tale</h2>
                    </div>
                    <p style="text-align: center; color: #666; padding: 40px;">
                        Funcționalitate programări - în curs de dezvoltare
                    </p>
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
        document.getElementById('addArticleForm').addEventListener('submit', function(e) {
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
    </script>
</body>
</html>
