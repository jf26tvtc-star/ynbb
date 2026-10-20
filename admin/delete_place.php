<?php
session_start(); include '../db.php'; check_admin();
$id=(int)($_GET['id']??0);
if($id>0){
 $q=$conn->prepare('SELECT image_path FROM images WHERE place_id=?'); $q->bind_param('i',$id); $q->execute(); $imgs=$q->get_result();
 $d=$conn->prepare('DELETE FROM places WHERE id=?'); $d->bind_param('i',$id);
 if($d->execute()){while($r=$imgs->fetch_assoc()){delete_place_image_file($r['image_path']);}}
}
header('Location: places-list.php'); exit();
?>
