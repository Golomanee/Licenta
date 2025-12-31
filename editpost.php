<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$can_edit = true;
if ($id <= 0) {
    $error = 'ID articol invalid.';
    $can_edit = false;
} else {
    // Fetch post
    $stmt = $conn->prepare("SELECT * FROM EduPosts WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $post = $res->fetch_assoc();
    $stmt->close();

    if (!$post) {
        $error = 'Articolul nu a fost găsit.';
        $can_edit = false;
    } elseif ($post['creator_id'] != $user_id) {
        $error = 'Nu ai permisiunea de a edita acest articol.';
        $can_edit = false;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? $_POST['content'] : '';

    if (empty($title) || strlen($title) < 3) {
        $error = 'Titlul este obligatoriu și trebuie să aibă cel puțin 3 caractere.';
    } elseif (empty($content) || strlen(strip_tags($content)) < 10) {
        $error = 'Conținutul este obligatoriu și trebuie să aibă cel puțin 10 caractere.';
    } else {
        // handle optional image upload
        $image_path = $post['image'];
        if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/posts/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $file_tmp = $_FILES['post_image']['tmp_name'];
            $file_name = $_FILES['post_image']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg','jpeg','png','gif','webp'];
            if (in_array($file_ext, $allowed_ext)) {
                $new_filename = uniqid() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // optionally delete old image file
                    if (!empty($post['image']) && file_exists($post['image'])) {
                        @unlink($post['image']);
                    }
                    $image_path = $upload_path;
                } else {
                    $error = 'Eroare la încărcarea imaginii.';
                }
            } else {
                $error = 'Extensie imagine neacceptată.';
            }
        }

        if (empty($error)) {
            $upd = $conn->prepare("UPDATE EduPosts SET title = ?, text = ?, image = ? WHERE id = ? AND creator_id = ?");
            if ($upd) {
                $upd->bind_param('sssii', $title, $content, $image_path, $id, $user_id);
                if ($upd->execute()) {
                    $success = 'Articolul a fost actualizat.';
                    // refresh post data
                    $post['title'] = $title;
                    $post['text'] = $content;
                    $post['image'] = $image_path;
                } else {
                    $error = 'Eroare la actualizare: ' . $upd->error;
                }
                $upd->close();
            } else {
                $error = 'Eroare la pregătirea update-ului: ' . $conn->error;
            }
        }
    }
}
?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Editează articol - Spital</title>
  <link rel="stylesheet" href="css/base.css">
  <link rel="stylesheet" href="css/pages/admin.css">
    <link rel="stylesheet" href="css/components/navbar.css">
  <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="page-container">
  <h1 class="page-title">Editează articol</h1>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><strong>Eroare:</strong> <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><strong>Succes:</strong> <?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <?php if ($can_edit): ?>
  <form method="POST" enctype="multipart/form-data" id="editPostForm">
    <div class="form-group">
      <label for="title">Titlu *</label>
      <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
    </div>

    <div class="form-group">
      <label for="post_image">Imagine (opțional)</label>
      <?php if (!empty($post['image']) && file_exists($post['image'])): ?>
        <div style="margin-bottom:8px;"><img src="<?php echo htmlspecialchars($post['image']); ?>" alt="img" style="max-width:200px; height:auto; border-radius:6px;"></div>
      <?php endif; ?>
      <input type="file" id="post_image" name="post_image" accept="image/*">
    </div>

    <div class="form-group">
      <label for="content">Conținut *</label>
      <textarea id="content" name="content"><?php echo htmlspecialchars($post['text']); ?></textarea>
    </div>

    <div class="button-group">
      <a href="doctordashboard.php" class="btn btn-cancel">Anulează</a>
      <button type="submit" class="btn btn-submit">Salvează modificările</button>
    </div>
  </form>
  <?php else: ?>
    <p style="margin-top:20px;"><a href="doctordashboard.php">Înapoi la dashboard</a></p>
  <?php endif; ?>
</div>

<script>
tinymce.init({ selector: '#content', height: 400, plugins: ['advlist','autolink','lists','link','image','charmap','code','fullscreen','media','table','preview','help','wordcount'], toolbar: 'undo redo | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code | fullscreen' });
</script>
</body>
</html>