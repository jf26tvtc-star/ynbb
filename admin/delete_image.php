<?php
session_start();
include '../db.php';
check_admin();

$id = (int)($_GET['id'] ?? 0);
$place_id = (int)($_GET['place_id'] ?? 0);

if ($id > 0 && $place_id > 0) {
    // جعل هذه الصورة هي الرئيسية بدلاً من حذفها
    if (isset($_GET['make_main'])) {
        $conn->query('UPDATE images SET is_main=0 WHERE place_id=' . $place_id);
        $s = $conn->prepare('UPDATE images SET is_main=1 WHERE id=? AND place_id=?');
        $s->bind_param('ii', $id, $place_id);
        $s->execute();
    } else {
        $q = $conn->prepare('SELECT image_path, is_main FROM images WHERE id=? AND place_id=?');
        $q->bind_param('ii', $id, $place_id);
        $q->execute();
        $img = $q->get_result()->fetch_assoc();
        if ($img) {
            $d = $conn->prepare('DELETE FROM images WHERE id=? AND place_id=?');
            $d->bind_param('ii', $id, $place_id);
            if ($d->execute()) {
                delete_place_image_file($img['image_path']);
                // إذا كانت الصورة المحذوفة هي الرئيسية، نجعل أقدم صورة متبقية رئيسية بدلاً منها
                if ($img['is_main']) {
                    $next = $conn->query('SELECT id FROM images WHERE place_id=' . $place_id . ' ORDER BY id ASC LIMIT 1')->fetch_assoc();
                    if ($next) {
                        $conn->query('UPDATE images SET is_main=1 WHERE id=' . (int)$next['id']);
                    }
                }
            }
        }
    }
}

header('Location: edit_place.php?id=' . $place_id);
exit();
?>
