<?php
session_start();
require_once 'config/database.php';

// Verificare dacă utilizatorul este logat
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

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
                        $success = 'Postarea a fost creată cu succes!';
                        // Curățare formular
                        $title = '';
                        $content = '';
                    } else {
                        $error = 'Eroare la salvarea postării: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            } catch (Exception $e) {
                $error = 'Eroare: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creează Postare - Spital</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/pages/admin.css">
    <link rel="stylesheet" href="css/pages/createpost.css">
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js"></script>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="create-post-container">
        <h1>Creează o Postare Nouă</h1>

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

        <div class="form-info">
            <strong>Sfat:</strong> Completează toate câmpurile. Poți folosi editorul text pentru a adăuga formatare (headers, bold, italic, liste, etc.).
        </div>

        <form method="POST" enctype="multipart/form-data" id="createPostForm">
            <!-- Titlu -->
            <div class="form-group">
                <label for="title">Titlu *</label>
                <input 
                    type="text" 
                    id="title" 
                    name="title" 
                    placeholder="Introduceți titlul postării..."
                    value="<?php echo htmlspecialchars($title ?? ''); ?>"
                    required
                >
            </div>

            <!-- Imagine -->
            <div class="form-group">
                <label for="post_image">Imagine (opțional)</label>
                <input 
                    type="file" 
                    id="post_image" 
                    name="post_image" 
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    onchange="previewImage(this)"
                >
                <small style="color: #666; margin-top: 5px; display: block;">
                    Formate acceptate: JPG, PNG, GIF, WEBP. Dimensiune maximă: 5MB
                </small>
                <div id="imagePreview" class="image-preview"></div>
            </div>

            <!-- Conținut -->
            <div class="form-group">
                <label for="content">Conținut *</label>
                <textarea 
                    id="content" 
                    name="content" 
                    placeholder="Scrieți conținutul postării aici..."
                    required
                ><?php echo htmlspecialchars($content ?? ''); ?></textarea>
            </div>

            <!-- Butoane -->
            <div class="button-group">
                <button type="button" class="btn btn-cancel" onclick="window.history.back();">
                    Anulare
                </button>
                <button type="submit" class="btn btn-submit">
                    Publică Postarea
                </button>
            </div>
        </form>
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
            
            height: 400,
            
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

        // Previzualizare imagine
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                if (file.size > maxSize) {
                    alert('Fișierul este prea mare! Dimensiune maximă: 5MB');
                    input.value = '';
                    preview.innerHTML = '';
                    return;
                }
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Previzualizare imagine">
                        <small style="display: block; margin-top: 8px; color: #666;">
                            Imagine selectată: ${file.name}
                        </small>
                    `;
                };
                
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        }

        // Validare form înainte de trimitere
        document.getElementById('createPostForm').addEventListener('submit', function(e) {
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
