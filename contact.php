<?php
session_start();
include 'db.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = clean($_POST['name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $subject = clean($_POST['subject'] ?? '');
    $message = clean($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $message === '') {
        $error = "يرجى ملء جميع الحقول المطلوبة";
    } elseif (!valid_email($email)) {
        $error = "صيغة البريد الإلكتروني غير صحيحة";
    } else {
        $stmt = $conn->prepare("INSERT INTO messages (name, email, phone, subject, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('sssss', $name, $email, $phone, $subject, $message);
        if ($stmt->execute()) {
            $success = "تم استقبال رسالتك بنجاح! سيتم التواصل معك قريباً.";
        } else {
            $error = "حدث خطأ في إرسال الرسالة، حاول مرة أخرى.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تواصل معنا - روح ينبع</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Markazi+Text:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- NAVBAR -->
    <?php require __DIR__ . '/nav.php'; ?>

    <!-- HERO -->
    <section class="hero">
        <div class="container">
            <h2>تواصل معنا</h2>
            <p>نحن هنا للإجابة على جميع استفسارتك</p>
        </div>
    </section>

    <!-- CONTACT SECTION -->
    <section class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin: 40px 0;">
            <!-- CONTACT INFO -->
            <div>
                <h2 style="color: var(--deep); margin-bottom: 30px;">معلومات التواصل</h2>
                
                <div style="background: white; padding: 25px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                    <h3 style="color: var(--deep); margin-bottom: 10px;">📍 العنوان</h3>
                    <p>ينبع، المملكة العربية السعودية</p>
                </div>

                <div style="background: white; padding: 25px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                    <h3 style="color: var(--deep); margin-bottom: 10px;">📱 الهاتف</h3>
                    <p>+96656789455</p>
                </div>

                <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                    <h3 style="color: var(--deep); margin-bottom: 10px;">📧 البريد الإلكتروني</h3>
                    <p>jf26tvtc@gmail.com</p>
                </div>
            </div>

            <!-- CONTACT FORM -->
            <div>
                <form method="POST" class="contact-form">
                    <?php if($success) { ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php } ?>
                    <?php if($error) { ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php } ?>

                    <input type="text" name="name" placeholder="اسمك الكامل" required>
                    <input type="email" name="email" placeholder="بريدك الإلكتروني" required>
                    <input type="tel" name="phone" placeholder="رقم الهاتف">
                    <input type="text" name="subject" placeholder="الموضوع">
                    <textarea name="message" placeholder="رسالتك..." rows="6" required></textarea>
                    <button type="submit" class="btn">إرسال الرسالة 📤</button>
                </form>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <p>&copy; 2026 روح ينبع | جميع الحقوق محفوظة</p>
            <p>📧 jf26tvtc@gmail.com | 📱 +96656789455</p>
        </div>
    </footer>
</body>
</html>