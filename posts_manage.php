<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';

// Check if posts table exists, if not create it
$table_check = $mysqli->query("SHOW TABLES LIKE 'posts'");
if ($table_check->num_rows == 0) {
    $create_table_sql = "
        CREATE TABLE posts (
            post_id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            content TEXT,
            image VARCHAR(255),
            is_published TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ";
    $mysqli->query($create_table_sql);
}

// Handle post deletion
if (isset($_GET['delete']) && check_csrf($_GET['_csrf'] ?? '')) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $mysqli->prepare("DELETE FROM posts WHERE post_id = ?");
    $stmt->bind_param('i', $delete_id);
    $stmt->execute();
    header('Location: posts_manage.php');
    exit;
}

// Handle publish/unpublish
if (isset($_GET['toggle_publish']) && check_csrf($_GET['_csrf'] ?? '')) {
    $toggle_id = (int)$_GET['toggle_publish'];
    $stmt = $mysqli->prepare("UPDATE posts SET is_published = NOT is_published WHERE post_id = ?");
    $stmt->bind_param('i', $toggle_id);
    $stmt->execute();
    header('Location: posts_manage.php');
    exit;
}

// Get all posts
$res = $mysqli->query("SELECT * FROM posts ORDER BY created_at DESC");
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3>Blog Posts Management</h3>
        <a href="posts_list.php" class="btn" style="background: #111; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none;">
            + Create New Post
        </a>
    </div>

    <?php if ($res->num_rows > 0): ?>
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">ID</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Title</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Status</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Created</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($post = $res->fetch_assoc()): ?>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid #ddd;"><?= e($post['post_id']) ?></td>
                        <td style="padding: 12px; border-bottom: 1px solid #ddd;">
                            <strong><?= e($post['title']) ?></strong>
                            <?php if (!empty($post['image'])): ?>
                                <br><small>📷 Has image</small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #ddd;">
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; 
                                background: <?= $post['is_published'] ? '#10b981' : '#6b7280' ?>; color: white;">
                                <?= $post['is_published'] ? 'Published' : 'Draft' ?>
                            </span>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #ddd;">
                            <?= e(date('M j, Y', strtotime($post['created_at']))) ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #ddd;">
                            <a href="posts_list.php?id=<?= e($post['post_id']) ?>" style="color: #3b82f6; text-decoration: none; margin-right: 10px;">Edit</a>
                            <a href="?toggle_publish=<?= e($post['post_id']) ?>&_csrf=<?= csrf() ?>" 
                               style="color: <?= $post['is_published'] ? '#f59e0b' : '#10b981' ?>; text-decoration: none; margin-right: 10px;"
                               onclick="return confirm('<?= $post['is_published'] ? 'Unpublish' : 'Publish' ?> this post?')">
                                <?= $post['is_published'] ? 'Unpublish' : 'Publish' ?>
                            </a>
                            <a href="?delete=<?= e($post['post_id']) ?>&_csrf=<?= csrf() ?>" 
                               style="color: #ef4444; text-decoration: none;"
                               onclick="return confirm('Delete this post permanently?')">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #6b7280;">
            <p>No blog posts yet.</p>
            <a href="posts_list.php" class="btn" style="background: #111; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block; margin-top: 10px;">
                Create Your First Post
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>