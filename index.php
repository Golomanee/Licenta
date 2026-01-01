<?php
session_start();
require_once 'config/database.php';

// Prevent caching - force revalidation
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Fetch top liked posts
$topPostsSql = "SELECT p.*, u.id as user_id,
  COALESCE(ud.name, u.email, 'Autor necunoscut') AS author_name,
  CASE WHEN ud.profileimage IS NOT NULL THEN 1 ELSE 0 END AS has_profile_image,
  COALESCE(l.likes_count,0) AS likes_count,
  COALESCE(d.dislikes_count,0) AS dislikes_count,
  (COALESCE(l.likes_count,0) - COALESCE(d.dislikes_count,0)) AS net_score
FROM EduPosts p
LEFT JOIN (SELECT post_id, COUNT(*) AS likes_count FROM Likes WHERE type = 'like' GROUP BY post_id) l ON l.post_id = p.id
LEFT JOIN (SELECT post_id, COUNT(*) AS dislikes_count FROM Likes WHERE type = 'dislike' GROUP BY post_id) d ON d.post_id = p.id
LEFT JOIN User u ON p.creator_id = u.id
LEFT JOIN UserDetails ud ON u.id = ud.userid
ORDER BY net_score DESC, p.created_at DESC
LIMIT 6";
$topPostsResult = $conn->query($topPostsSql);
$topPosts = $topPostsResult ? $topPostsResult->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Spital - Acasă</title>
    <link rel="stylesheet" type="text/css" href="css/base.css">
    <link rel="stylesheet" type="text/css" href="css/components/navbar.css">
    <link rel="stylesheet" type="text/css" href="css/components/appointment.css">
    <link rel="stylesheet" type="text/css" href="css/pages/edu.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<div class="home-container">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1>Programează-te online</h1>
            <p>Accesează serviciile noastre medicale rapid și simplu din contul tău.</p>
            
            <div class="hero-buttons">
                <?php if (isset($_SESSION['user'])): ?>
                    <a href="profile.php" class="btn btn-red">Contul meu</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-red">Intră în cont</a>
                    <a href="register.php" class="btn btn-grey">Creează cont</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <!-- Top Posts Section -->
    <?php if (count($topPosts) > 0): ?>
    <section class="top-posts-section">
        <div class="section-header">
            <h2>Articole populare</h2>
            <a href="edu.php" class="view-all-link">Vezi toate &rarr;</a>
        </div>
        
        <div class="edu-grid">
            <?php foreach ($topPosts as $post): ?>
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
        </div>
    </section>
    <?php endif; ?>
</div>

</body>
</html>