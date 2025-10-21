<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/image_helpers.php';

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
    
    if ($mysqli->query($create_table_sql)) {
        echo "<div class='alert alert-success'>Posts table created successfully!</div>";
    } else {
        die("<div class='alert alert-error'>Error creating posts table: " . $mysqli->error . "</div>");
    }
}

$id = (int)($_GET['id'] ?? 0);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!check_csrf($_POST['_csrf'] ?? '')) $errors[]='bad token';
  $title = trim($_POST['title']); $slug = trim($_POST['slug'] ?: strtolower(preg_replace('/[^a-z0-9]+/','-', $title)));
  $content = $_POST['content'] ?? ''; $published = isset($_POST['is_published'])?1:0;
  if ($title=='') $errors[]='Title required';
  if (empty($errors)) {
    if ($id) {
      $stmt = $mysqli->prepare("UPDATE posts SET title=?, slug=?, content=?, is_published=?, updated_at=NOW() WHERE post_id=?");
      $stmt->bind_param('sssii',$title,$slug,$content,$published,$id); $stmt->execute();
    } else {
      $stmt = $mysqli->prepare("INSERT INTO posts (title,slug,content,is_published) VALUES (?,?,?,?)");
      $stmt->bind_param('sssi',$title,$slug,$content,$published); $stmt->execute();
      $id = $mysqli->insert_id;
    }
    // handle image upload single
    if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
      $v = validate_image_upload($_FILES['image']);
      if ($v !== null) $errors[] = $v;
      else {
        $finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($_FILES['image']['tmp_name']);
        $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp']; $ext = $extMap[$mime] ?? 'jpg';
        $safe = make_safe_filename($_FILES['image']['name'],$ext);
        $uploadDir = __DIR__.'/uploads/posts'; if (!is_dir($uploadDir)) mkdir($uploadDir,0755,true);
        move_uploaded_file($_FILES['image']['tmp_name'],$uploadDir.'/'.$safe);
        // update post record
        $stmt = $mysqli->prepare("UPDATE posts SET image = ? WHERE post_id = ?");
        $stmt->bind_param('si',$safe,$id); $stmt->execute();
      }
    }
    if (empty($errors)) header('Location: posts_list.php');
  }
}
// load if edit
$post=null;
if ($id) { $stmt = $mysqli->prepare("SELECT * FROM posts WHERE post_id = ? LIMIT 1"); $stmt->bind_param('i',$id); $stmt->execute(); $post = $stmt->get_result()->fetch_assoc(); }
?>
<div class="card">
  <h3><?= $id ? 'Edit' : 'Create' ?> Post</h3>
  <?php if ($errors): ?><div style="color:#b91c1c"><?= e(implode('<br>',$errors)) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= csrf() ?>">
    <div class="form-row"><label>Title</label><input name="title" value="<?= e($post['title'] ?? '') ?>" required></div>
    <div class="form-row"><label>Slug</label><input name="slug" value="<?= e($post['slug'] ?? '') ?>"></div>
    <div class="form-row"><label>Image</label><input type="file" name="image" accept="image/*"></div>
    <?php if (!empty($post['image'])): ?><img src="uploads/posts/<?= e($post['image']) ?>" style="max-width:200px"><?php endif; ?>
    <div class="form-row"><label>Content</label><textarea name="content"><?= e($post['content'] ?? '') ?></textarea></div>
    <div class="form-row"><label><input type="checkbox" name="is_published" <?= !empty($post['is_published'])?'checked':'' ?>> Published</label></div>
    <div><button class="btn" type="submit">Save</button></div>
  </form>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>