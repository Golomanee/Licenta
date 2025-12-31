<?php
session_start();
require_once 'config/database.php';

// Search and pagination
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 9;
$offset = ($page - 1) * $perPage;

$params = [];
$where = "WHERE 1";
if ($search !== '') {
    $where .= " AND (title LIKE ? OR text LIKE ?)\n";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
}

// Count total
$countSql = "SELECT COUNT(*) as cnt FROM EduPosts " . $where;
$stmt = $conn->prepare($countSql);
if ($search !== '') {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$total = 0;
if ($row = $res->fetch_assoc()) {
    $total = (int)$row['cnt'];
}
$stmt->close();

$sql = "SELECT p.*, u.id as user_id,\n  COALESCE(ud.name, u.email, 'Autor necunoscut') AS author_name,\n  CASE WHEN ud.profileimage IS NOT NULL THEN 1 ELSE 0 END AS has_profile_image,\n  COALESCE(l.likes_count,0) AS likes_count,\n  COALESCE(d.dislikes_count,0) AS dislikes_count,\n  (COALESCE(l.likes_count,0) - COALESCE(d.dislikes_count,0)) AS net_score\nFROM EduPosts p\nLEFT JOIN (SELECT post_id, COUNT(*) AS likes_count FROM Likes WHERE type = 'like' GROUP BY post_id) l ON l.post_id = p.id\nLEFT JOIN (SELECT post_id, COUNT(*) AS dislikes_count FROM Likes WHERE type = 'dislike' GROUP BY post_id) d ON d.post_id = p.id\nLEFT JOIN User u ON p.creator_id = u.id\nLEFT JOIN UserDetails ud ON u.id = ud.userid\n" . $where . " ORDER BY net_score DESC, p.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}
// bind params dynamically
$types = '';
$bindValues = [];
if ($search !== '') {
    $types .= 'ss';
    $bindValues[] = $like;
    $bindValues[] = $like;
}
$types .= 'ii';
$bindValues[] = $perPage;
$bindValues[] = $offset;
$stmt->bind_param($types, ...$bindValues);
$stmt->execute();
$postsRes = $stmt->get_result();
$posts = $postsRes->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));

?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EDU - Spital</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/pages/edu.css">
    <link rel="stylesheet" href="css/components/navbar.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <header class="edu-header">
        <h1>EDU</h1>
    </header>

    <main class="edu-container">
        <form class="edu-search" method="GET" action="edu.php">
            <input type="text" name="q" placeholder="Cauta" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">🔍</button>
        </form>

        <section class="edu-grid">
            <?php if (count($posts) === 0): ?>
                <p class="no-posts">Nu există articole.</p>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <article class="edu-card">
                        <a href="post.php?id=<?php echo $post['id']; ?>" class="card-link">
                            <div class="card-image">
                                <?php if (!empty($post['image']) && file_exists($post['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                                <?php else: ?>
                                    <div class="placeholder-image"></div>
                                <?php endif; ?>
                            </div>

                            <div class="card-body">
                                <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                                <div class="card-meta">
                                    <div class="card-author">
                                        <div class="card-avatar">
                                            <?php if (!empty($post['has_profile_image'])): ?>
                                                <img src="image.php?id=<?php echo $post['creator_id']; ?>" alt="<?php echo htmlspecialchars($post['author_name']); ?>">
                                            <?php else: ?>
                                                <span class="card-avatar-fallback"><?php echo strtoupper(substr($post['author_name'], 0, 1)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-author-text">
                                            <span class="card-author-name"><?php echo htmlspecialchars($post['author_name']); ?></span>
                                            <span class="card-date"><?php echo date('d M Y', strtotime($post['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="card-stats">
                                      <span class="stat-item">👍 <?php echo $post['likes_count']; ?></span>
                                      <span class="stat-item">👎 <?php echo $post['dislikes_count']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <nav class="edu-pagination">
            <?php if ($page > 1): ?>
                <a href="?q=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>" class="page-prev">&laquo; Prev</a>
            <?php endif; ?>

            <span class="page-info">Pagina <?php echo $page; ?> din <?php echo $totalPages; ?></span>

            <?php if ($page < $totalPages): ?>
                <a href="?q=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>" class="page-next">Next &raquo;</a>
            <?php endif; ?>
        </nav>
    </main>
</body>
</html>
