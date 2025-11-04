<?php
// add_debt.php

// الاتصال بقاعدة البيانات
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "debt_manager";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}

// مسار حفظ المرفقات
$uploadDir = 'uploads/debt_attachments/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// معالجة الحفظ عند إرسال النموذج
if (isset($_POST['save'])) {
    $type         = $_POST['type'];
    $name         = $_POST['name'];
    $phone        = $_POST['phone'];
    $total_amount = $_POST['total_amount'];
    $currency     = $_POST['currency'];
    $date         = $_POST['date'];
    $notes        = $_POST['notes'];
    $remaining    = $total_amount;

    // حفظ البيانات في جدول debts
    $stmt = $conn->prepare("INSERT INTO debts (type, name, phone, total_amount, remaining_amount, date, notes, currency)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssddsss", $type, $name, $phone, $total_amount, $remaining, $date, $notes, $currency);

    if ($stmt->execute()) {
        $last_debt_id = $stmt->insert_id; // جلب معرف الدين الذي تم إضافته للتو
        $success = true;

        // معالجة رفع المرفقات
        if (!empty($_FILES['attachments']['name'][0])) {
            $file_stmt = $conn->prepare("INSERT INTO debt_attachments (debt_id, file_name, original_name, mime_type) VALUES (?, ?, ?, ?)");
            
            foreach ($_FILES['attachments']['name'] as $key => $attachment_name) {
                if ($_FILES['attachments']['error'][$key] == UPLOAD_ERR_OK) {
                    $originalName = $_FILES['attachments']['name'][$key];
                    $tmpName      = $_FILES['attachments']['tmp_name'][$key];
                    $mimeType     = $_FILES['attachments']['type'][$key];

                    // إنشاء اسم فريد للملف
                    $fileExt  = pathinfo($originalName, PATHINFO_EXTENSION);
                    $fileName = uniqid() . '.' . $fileExt;
                    $filePath = $uploadDir . $fileName;

                    // نقل الملف من المسار المؤقت إلى المسار الدائم
                    if (move_uploaded_file($tmpName, $filePath)) {
                        $file_stmt->bind_param("isss", $last_debt_id, $fileName, $originalName, $mimeType);
                        if (!$file_stmt->execute()) {
                             $success = false;
                        }
                    } else {
                        $success = false;
                    }
                }
            }
        }

        if ($success) {
            echo "<p style='color: green; text-align: center;'>تم حفظ الدين والمرفقات بنجاح!</p>";
        } else {
            echo "<p style='color: orange; text-align: center;'>تم حفظ الدين، ولكن حدث خطأ في رفع بعض المرفقات.</p>";
        }
    } else {
        echo "<p style='color: red; text-align: center;'>حدث خطأ أثناء حفظ الدين.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إضافة دين جديد</title>
    <style>
        body {
            font-family: 'Tajawal', Arial, sans-serif;
            background-color: #f4f4f4;
            text-align: center;
            padding-top: 40px;
        }
        form {
            background: #fff;
            width: 400px;
            margin: auto;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        label {
            display: block;
            text-align: right;
            margin-top: 15px;
        }
        input, select, textarea, button {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            margin-top: 20px;
            font-size: 16px;
        }
        button:hover {
            background-color: #0056b3;
            cursor: pointer;
        }
        a.back {
            display: inline-block;
            margin-top: 15px;
            color: #007bff;
            text-decoration: none;
        }
        h2 {
            color: #333;
        }
    </style>
</head>
<body>

<h2>إضافة دين جديد</h2>

<form method="POST" enctype="multipart/form-data">

    <label>نوع الدَّين:</label>
    <select name="type" required>
        <option value="عليّ">دين عليَّ</option>
        <option value="لي">دين لي</option>
    </select>

    <label>اسم الشخص / الجهة:</label>
    <input type="text" name="name" required>

    <label>رقم الهاتف (اختياري):</label>
    <input type="text" name="phone">

    <label>المبلغ الإجمالي:</label>
    <input type="number" step="0.01" name="total_amount" required>

    <label>العملة:</label>
    <select name="currency" required>
        <option value="IQD">دينار عراقي (IQD)</option>
        <option value="USD">دولار أمريكي (USD)</option>
    </select>

    <label>التاريخ:</label>
    <input type="date" name="date" required>

    <label>ملاحظات:</label>
    <textarea name="notes" rows="3" placeholder="أي تفاصيل إضافية"></textarea>

    <label>المرفقات (صور أو PDF):</label>
    <input type="file" name="attachments[]" multiple accept="image/*,application/pdf">

    <button type="submit" name="save">حفظ الدين</button>
</form>

<a href="index.php" class="back">⬅ الرجوع للصفحة الرئيسية</a>

</body>
</html>