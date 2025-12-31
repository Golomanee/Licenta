<?php
session_start();
require_once 'config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: edu.php');
    exit();
}

// Handle POST actions: comment, like, dislike
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['flash_error'] = 'Trebuie să fii autentificat.';
        header('Location: post.php?id=' . $id);
        exit();
    }

    $user_id = $_SESSION['user_id'];

    if (isset($_POST['action']) && $_POST['action'] === 'comment') {
        $comment_text = trim($_POST['comment'] ?? '');
        $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        if ($comment_text !== '') {
            $stmt = $conn->prepare("INSERT INTO Comments (post_id, user_id, content, parent_comment_id) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('iisi', $id, $user_id, $comment_text, $parent_id);
                if (!$stmt->execute()) {
                    $_SESSION['flash_error'] = 'Eroare la adăugarea comentariului: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $_SESSION['flash_error'] = 'Eroare la pregătirea comentariului: ' . $conn->error;
            }
        }
        header('Location: post.php?id=' . $id);
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'like') {
        $type = $_POST['like_type'] === 'dislike' ? 'dislike' : 'like';
        $check = $conn->prepare("SELECT id, type FROM Likes WHERE post_id = ? AND user_id = ? LIMIT 1");
        if ($check) {
            $check->bind_param('ii', $id, $user_id);
            $check->execute();
            $r = $check->get_result();
            if ($r->num_rows > 0) {
                $row = $r->fetch_assoc();
                if ($row['type'] === $type) {
                    // unlike/undislike
                    $del = $conn->prepare("DELETE FROM Likes WHERE id = ?");
                    if ($del) {
                        $del->bind_param('i', $row['id']);
                        $del->execute();
                        $del->close();
                    }
                } else {
                    // switch type
                    $upd = $conn->prepare("UPDATE Likes SET type = ? WHERE id = ?");
                    if ($upd) {
                        $upd->bind_param('si', $type, $row['id']);
                        $upd->execute();
                        $upd->close();
                    }
                }
            } else {
                $ins = $conn->prepare("INSERT INTO Likes (post_id, user_id, type) VALUES (?, ?, ?)");
                if ($ins) {
                    $ins->bind_param('iis', $id, $user_id, $type);
                    $ins->execute();
                    $ins->close();
                }
            }
            $check->close();
        }
        header('Location: post.php?id=' . $id);
        exit();
    }
}

// Fetch post with profile image
$stmt = $conn->prepare("SELECT p.*, u.id as user_id, ud.name as author_name FROM EduPosts p
LEFT JOIN User u ON p.creator_id = u.id
LEFT JOIN UserDetails ud ON u.id = ud.userid
WHERE p.id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$post = $res->fetch_assoc();
$stmt->close();

if (!$post) {
    header('Location: edu.php');
    exit();
}

// Fetch top-level comments with profile images
$comments = [];
$cs = $conn->prepare("SELECT c.id, c.post_id, c.user_id, c.content, c.parent_comment_id, c.created_at, ud.name as author_name FROM Comments c
LEFT JOIN User u ON c.user_id = u.id
LEFT JOIN UserDetails ud ON u.id = ud.userid
WHERE c.post_id = ? AND c.parent_comment_id IS NULL
ORDER BY c.created_at DESC");
if ($cs) {
    $cs->bind_param('i', $id);
    $cs->execute();
    $cres = $cs->get_result();
    $comments = $cres->fetch_all(MYSQLI_ASSOC);
    $cs->close();
}

// Count likes/dislikes
$likes_count = 0;
$dislikes_count = 0;
$lk = $conn->prepare("SELECT type, COUNT(*) as cnt FROM Likes WHERE post_id = ? GROUP BY type");
if ($lk) {
    $lk->bind_param('i', $id);
    $lk->execute();
    $lres = $lk->get_result();
    while ($row = $lres->fetch_assoc()) {
        if ($row['type'] === 'like') $likes_count = $row['cnt'];
        if ($row['type'] === 'dislike') $dislikes_count = $row['cnt'];
    }
    $lk->close();
}

// Check user like status
$user_like_type = null;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $ch = $conn->prepare("SELECT type FROM Likes WHERE post_id = ? AND user_id = ? LIMIT 1");
    if ($ch) {
        $ch->bind_param('ii', $id, $uid);
        $ch->execute();
        $r = $ch->get_result();
        if ($row = $r->fetch_assoc()) {
            $user_like_type = $row['type'];
        }
        $ch->close();
    }
}

// Function to get replies for a comment
function getReplies($conn, $parent_id) {
    $rs = $conn->prepare("SELECT c.id, c.post_id, c.user_id, c.content, c.parent_comment_id, c.created_at, ud.name as author_name FROM Comments c
    LEFT JOIN User u ON c.user_id = u.id
    LEFT JOIN UserDetails ud ON u.id = ud.userid
    WHERE c.parent_comment_id = ?
    ORDER BY c.created_at ASC");
    $rs->bind_param('i', $parent_id);
    $rs->execute();
    return $rs->get_result()->fetch_all(MYSQLI_ASSOC);
}

?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars($post['title']); ?> - EDU</title>
  <link rel="stylesheet" href="css/base.css">
  <link rel="stylesheet" href="css/pages/edu.css">
  <link rel="stylesheet" href="css/components/navbar.css">
  <link rel="stylesheet" href="css/pages/post.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<main>
  <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="error-message" style="background: #fee; color: #c00; padding: 15px; margin-bottom: 20px; border-radius: 4px; border-left: 4px solid #c00;">
      <?php echo htmlspecialchars($_SESSION['flash_error']); ?>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>
  
  <article class="post-hero">
    <h1><?php echo htmlspecialchars($post['title']); ?></h1>
    <div class="post-meta">
      <img src="<?php echo !empty($post['user_id']) ? 'image.php?id=' . $post['user_id'] : 'https://via.placeholder.com/40'; ?>" alt="Avatar" class="author-avatar">
      <div>
        <div class="author-name"><?php echo htmlspecialchars($post['author_name'] ?? 'Autor'); ?></div>
        <div class="post-date"><?php echo date('d M Y H:i', strtotime($post['created_at'])); ?></div>
      </div>
    </div>

    <?php if (!empty($post['image']) && file_exists($post['image'])): ?>
      <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="" class="post-image">
    <?php endif; ?>

    <div class="post-content"><?php echo $post['text']; ?></div>

    <div class="post-actions">
      <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="like">
        <input type="hidden" name="like_type" value="like">
        <button type="submit" class="btn-like <?php echo $user_like_type === 'like' ? 'active' : ''; ?>">
          👍 Like (<?php echo $likes_count; ?>)
        </button>
      </form>
      
      <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="like">
        <input type="hidden" name="like_type" value="dislike">
        <button type="submit" class="btn-dislike <?php echo $user_like_type === 'dislike' ? 'active' : ''; ?>">
          👎 Dislike (<?php echo $dislikes_count; ?>)
        </button>
      </form>
    </div>
  </article>

  <section class="comments-section">
    <h2>Comentarii (<?php echo count($comments); ?>)</h2>

    <!-- Form for new comment -->
    <?php if (isset($_SESSION['user_id'])): ?>
      <form method="POST" class="comment-form">
        <input type="hidden" name="action" value="comment">
        <textarea name="comment" placeholder="Adaugă un comentariu..." required></textarea>
        <button type="submit" class="btn-submit">Trimite</button>
      </form>
    <?php else: ?>
      <p class="login-prompt">Trebuie să fii <a href="login.php">autentificat</a> pentru a comenta.</p>
    <?php endif; ?>

    <!-- Comments list -->
    <div class="comments-list">
      <?php foreach ($comments as $comment): ?>
        <div class="comment">
          <div class="comment-header">
            <img src="<?php echo !empty($comment['user_id']) ? 'image.php?id=' . $comment['user_id'] : 'https://via.placeholder.com/32'; ?>" alt="Avatar" class="comment-avatar">
            <div class="comment-info">
              <div class="comment-author"><?php echo htmlspecialchars($comment['author_name'] ?? 'Utilizator'); ?></div>
              <div class="comment-date"><?php echo date('d M Y H:i', strtotime($comment['created_at'])); ?></div>
            </div>
          </div>
          <div class="comment-body"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></div>
          
          <?php if (isset($_SESSION['user_id'])): ?>
            <div class="comment-actions">
              <button class="btn-reply" onclick="toggleReplyForm(<?php echo $comment['id']; ?>)">Răspunde</button>
            </div>
            <form method="POST" class="reply-form" id="reply-form-<?php echo $comment['id']; ?>" style="display:none;">
              <input type="hidden" name="action" value="comment">
              <input type="hidden" name="parent_id" value="<?php echo $comment['id']; ?>">
              <textarea name="comment" placeholder="Răspunde la comentariu..." required></textarea>
              <button type="submit" class="btn-submit">Trimite răspuns</button>
            </form>
          <?php endif; ?>

          <!-- Replies -->
          <?php 
            $replies = getReplies($conn, $comment['id']);
            if (count($replies) > 0):
          ?>
            <div class="replies">
              <?php foreach ($replies as $reply): ?>
                <div class="reply">
                  <div class="comment-header">
                    <img src="<?php echo !empty($reply['user_id']) ? 'image.php?id=' . $reply['user_id'] : 'https://via.placeholder.com/32'; ?>" alt="Avatar" class="comment-avatar">
                    <div class="comment-info">
                      <div class="comment-author"><?php echo htmlspecialchars($reply['author_name'] ?? 'Utilizator'); ?></div>
                      <div class="comment-date"><?php echo date('d M Y H:i', strtotime($reply['created_at'])); ?></div>
                    </div>
                  </div>
                  <div class="comment-body"><?php echo nl2br(htmlspecialchars($reply['content'])); ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</main>

<script>
function toggleReplyForm(commentId) {
  const form = document.getElementById('reply-form-' + commentId);
  form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>

</body>
</html>
