<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';
$errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST'){
  if (!check_csrf($_POST['_csrf']??'')) $errors[]='Bad token';
  else {
    $question=trim($_POST['question']);
    $answer=trim($_POST['answer']);
    $id=(int)($_POST['faq_id']??0);
    if ($id){
      $stmt=$mysqli->prepare("UPDATE faqs SET question=?,answer=? WHERE faq_id=?");
      $stmt->bind_param('ssi',$question,$answer,$id);
      $stmt->execute();
    } else {
      $stmt=$mysqli->prepare("INSERT INTO faqs (question,answer) VALUES (?,?)");
      $stmt->bind_param('ss',$question,$answer);
      $stmt->execute();
    }
    header('Location: faqs_list.php');exit;
  }
}
if (isset($_GET['delete'])){
  if (check_csrf($_GET['_csrf']??'')){
    $id=(int)$_GET['delete'];
    $stmt=$mysqli->prepare("DELETE FROM faqs WHERE faq_id=?");
    $stmt->bind_param('i',$id);
    $stmt->execute();
    header('Location: faqs_list.php');exit;
  }
}
$res=$mysqli->query("SELECT * FROM faqs ORDER BY created_at DESC");
?>
<div class="card">
  <h3>FAQs / Announcements</h3>
  <table class="table"><thead><tr><th>ID</th><th>Question</th><th>Created</th><th>Actions</th></tr></thead>
  <tbody><?php while($f=$res->fetch_assoc()):?>
    <tr><td><?=$f['faq_id']?></td><td><?=e($f['question'])?></td><td><?=$f['created_at']?></td><td><a href="?edit=<?=$f['faq_id']?>">Edit</a> | <a href="?delete=<?=$f['faq_id']?>&_csrf=<?=csrf()?>">Delete</a></td></tr>
  <?php endwhile;?></tbody></table>

  <?php if(isset($_GET['edit'])): 
    $id=(int)$_GET['edit'];$stmt=$mysqli->prepare("SELECT * FROM faqs WHERE faq_id=?");$stmt->bind_param('i',$id);$stmt->execute();$faq=$stmt->get_result()->fetch_assoc();
  endif;?>
  <h4><?=isset($faq)?'Edit':'Add'?> FAQ</h4>
  <?php if($errors):?><div style="color:red"><?=implode('<br>',$errors)?></div><?php endif;?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?=csrf()?>">
    <?php if(isset($faq)):?><input type="hidden" name="faq_id" value="<?=$faq['faq_id']?>"><?php endif;?>
    <div class="form-row"><label>Question</label><input type="text" name="question" value="<?=e($faq['question']??'')?>" required></div>
    <div class="form-row"><label>Answer</label><textarea name="answer" rows="4"><?=e($faq['answer']??'')?></textarea></div>
    <button class="btn" type="submit">Save</button>
  </form>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
