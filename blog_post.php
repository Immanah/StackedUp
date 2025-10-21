<?php
require_once 'db.php';

$id = (int)($_GET['id'] ?? 0);

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM posts WHERE post_id = ? AND is_published = 1 LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();
}

if (!$post) {
    header("HTTP/1.0 404 Not Found");
    echo "Post not found";
    exit;
}

$date = date('F j, Y', strtotime($post['created_at']));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= htmlspecialchars($post['title']) ?> — Ozyde Blog</title>
    <style>
        /* Add your styles from blog.php here */
        :root {
            --bg: #fff;
            --text: #222;
            --muted: #7a7a7a;
            --accent: #111;
            --max-width: 800px;
        }
        
        body {
            margin: 0;
            font-family: "Helvetica Neue", Arial, sans-serif;
            color: var(--text);
            background: var(--bg);
            -webkit-font-smoothing: antialiased;
            line-height: 1.6;
        }
        
        .container {
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .blog-header {
            padding: 60px 0 30px;
            border-bottom: 1px solid #eee;
            margin-bottom: 40px;
        }
        
        .blog-title {
            font-size: 2.5rem;
            margin: 0 0 1rem;
            color: var(--accent);
        }
        
        .blog-meta {
            color: var(--muted);
            font-size: 1rem;
        }
        
        .blog-content {
            font-size: 1.1rem;
            line-height: 1.8;
        }
        
        .blog-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 2rem 0;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--accent);
            text-decoration: none;
            margin-bottom: 2rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="blog.php" class="back-link">← Back to Blog</a>
        
        <article>
            <header class="blog-header">
                <h1 class="blog-title"><?= htmlspecialchars($post['title']) ?></h1>
                <div class="blog-meta">
                    Published on <?= $date ?> by Ozyde
                </div>
            </header>
            
            <div class="blog-content">
                <?php if (!empty($post['image'])): ?>
                    <img src="admin/uploads/posts/<?= htmlspecialchars($post['image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>">
                <?php endif; ?>
                
                <?= nl2br(htmlspecialchars($post['content'])) ?>
            </div>
        </article>
    </div>
</body>
</html>