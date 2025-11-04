<?php
// إعدادات الاتصال بقاعدة البيانات
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "debt_manager";
$upload_dir = 'uploads/';
$backup_dir = 'backups/';

// إنشاء الاتصال
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

// التحقق من الاتصال
if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}

// متغير لتخزين رسائل النظام
$message = '';
$message_type = '';

// إنشاء مجلدات التخزين إذا لم تكن موجودة
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// دالة لحذف محتويات مجلد بشكل تكراري
function deleteDirContent($dir) {
    if (!is_dir($dir)) {
        return;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
}

// --- معالجة النسخ الاحتياطي (SQL فقط) ---
if (isset($_GET['action']) && $_GET['action'] == 'sql_backup') {
    // 1. إنشاء ملف SQL للنسخة الاحتياطية
    $tables_to_backup = ['debts', 'payments', 'debt_attachments'];
    $sql_dump = '';
    
    foreach ($tables_to_backup as $table) {
        $create_table_result = $conn->query("SHOW CREATE TABLE " . $conn->real_escape_string($table));
        if ($create_table_result && $create_table_row = $create_table_result->fetch_assoc()) {
            $sql_dump .= "DROP TABLE IF EXISTS `" . $table . "`;\n";
            $sql_dump .= $create_table_row['Create Table'] . ";\n\n";
    
            $data_result = $conn->query("SELECT * FROM " . $conn->real_escape_string($table));
            if ($data_result) {
                while ($row = $data_result->fetch_assoc()) {
                    $sql_dump .= "INSERT INTO `" . $table . "` VALUES (";
                    $values = [];
                    foreach ($row as $value) {
                        $values[] = is_null($value) ? "NULL" : "'" . $conn->real_escape_string($value) . "'";
                    }
                    $sql_dump .= implode(', ', $values) . ");\n";
                }
                $sql_dump .= "\n";
            }
        }
    }

    // إعداد مسار المجلد واسم الملف
    $filename = 'debt_manager_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $file_path = $backup_dir . $filename;

    // حفظ البيانات في ملف على الخادم
    if (file_put_contents($file_path, $sql_dump)) {
        $message = "تم حفظ نسخة احتياطية من قاعدة البيانات بنجاح! يمكن العثور عليها في المجلد **" . $backup_dir . "**.";
        $message_type = 'success';
    } else {
        $message = "حدث خطأ أثناء حفظ النسخة الاحتياطية. يرجى التأكد من وجود أذونات الكتابة للمجلد.";
        $message_type = 'danger';
    }
}

// --- معالجة النسخ الاحتياطي الكامل (ZIP) ---
if (isset($_GET['action']) && $_GET['action'] == 'full_backup') {
    // 1. إنشاء ملف SQL مؤقت
    $temp_sql_filename = 'database_dump.sql';
    $temp_sql_path = sys_get_temp_dir() . '/' . $temp_sql_filename;
    
    $tables_to_backup = ['debts', 'payments', 'debt_attachments'];
    $sql_dump = '';
    
    foreach ($tables_to_backup as $table) {
        $create_table_result = $conn->query("SHOW CREATE TABLE " . $conn->real_escape_string($table));
        if ($create_table_result && $create_table_row = $create_table_result->fetch_assoc()) {
            $sql_dump .= "DROP TABLE IF EXISTS `" . $table . "`;\n";
            $sql_dump .= $create_table_row['Create Table'] . ";\n\n";

            $data_result = $conn->query("SELECT * FROM " . $conn->real_escape_string($table));
            if ($data_result) {
                while ($row = $data_result->fetch_assoc()) {
                    $sql_dump .= "INSERT INTO `" . $table . "` VALUES (";
                    $values = [];
                    foreach ($row as $value) {
                        $values[] = is_null($value) ? "NULL" : "'" . $conn->real_escape_string($value) . "'";
                    }
                    $sql_dump .= implode(', ', $values) . ");\n";
                }
                $sql_dump .= "\n";
            }
        }
    }
    file_put_contents($temp_sql_path, $sql_dump);

    // 2. إنشاء ملف ZIP جديد
    $zip = new ZipArchive();
    $zip_filename = 'debt_manager_full_backup_' . date('Y-m-d_H-i-s') . '.zip';
    $zip_path = $backup_dir . $zip_filename;

    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        // 3. إضافة ملف SQL إلى ZIP
        $zip->addFile($temp_sql_path, $temp_sql_filename);

        // 4. إضافة محتويات مجلد uploads إلى ZIP بدون المجلد الرئيسي
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($upload_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file_info) {
            $file_path = $file_info->getRealPath();
            // إصلاح الخطأ: استخدام getSubPathName() على كائن Iterator
            $relativePath = $files->getSubPathName(); 

            if ($file_info->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($file_path, $relativePath);
            }
        }
        
        $zip->close();
        unlink($temp_sql_path); // حذف الملف المؤقت

        $message = "تم إنشاء نسخة احتياطية كاملة (ZIP) بنجاح! يمكن العثور عليها في المجلد **" . $backup_dir . "**.";
        $message_type = 'success';
    } else {
        $message = "حدث خطأ أثناء إنشاء ملف ZIP.";
        $message_type = 'danger';
    }
}

// --- معالجة الاستعادة (ZIP) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file'];

    // التحقق من عدم وجود أخطاء في الرفع
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_path = $file['tmp_name'];
        $zip = new ZipArchive;

        if ($zip->open($file_path) === TRUE) {
            $temp_dir = 'temp_restore_' . time();
            mkdir($temp_dir, 0755, true);
            
            // فك ضغط الأرشيف
            $zip->extractTo($temp_dir);
            $zip->close();

            $sql_file = $temp_dir . '/database_dump.sql';
            if (file_exists($sql_file)) {
                $file_content = file_get_contents($sql_file);

                // 1. استعادة قاعدة البيانات
                $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
                if ($conn->multi_query($file_content)) {
                    do {
                        if ($result = $conn->store_result()) {
                            $result->free();
                        }
                    } while ($conn->more_results() && $conn->next_result());
                    
                    // 2. مسح محتويات مجلد الرفع الحالي
                    deleteDirContent($upload_dir);
                    
                    // 3. نسخ محتويات المجلد المؤقت إلى مجلد uploads
                    $temp_files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($temp_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );

                    foreach ($temp_files as $file_info) {
                        $source_path = $file_info->getRealPath();
                        // تجنب نسخ ملف dump.sql
                        if ($file_info->getFilename() === 'database_dump.sql') {
                            continue;
                        }
                        
                        // إصلاح الخطأ: استخدام getSubPathName() على كائن Iterator
                        $relativePath = $temp_files->getSubPathName();
                        $dest_path = $upload_dir . $relativePath;
                        
                        if ($file_info->isDir()) {
                            if (!is_dir($dest_path)) {
                                mkdir($dest_path, 0755, true);
                            }
                        } else {
                            copy($source_path, $dest_path);
                        }
                    }
                    
                    $message = "تمت استعادة البيانات والملفات بنجاح!";
                    $message_type = 'success';
                } else {
                    $message = "حدث خطأ أثناء تنفيذ الاستعادة: " . $conn->error;
                    $message_type = 'danger';
                }
                $conn->query("SET FOREIGN_KEY_CHECKS = 1;");

            } else {
                $message = "الملف المرفوع ليس ملف نسخ احتياطي صحيح (لا يحتوي على database_dump.sql).";
                $message_type = 'danger';
            }
            
            // 4. مسح المجلد المؤقت
            deleteDirContent($temp_dir);
            rmdir($temp_dir);

        } else {
            $message = "فشل في فتح ملف ZIP. يرجى التأكد من أن الملف سليم.";
            $message_type = 'danger';
        }
    } else {
        $message = "حدث خطأ أثناء رفع الملف. يرجى المحاولة مرة أخرى.";
        $message_type = 'danger';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>النسخ الاحتياطي والاستعادة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@200;300;400;500;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Tajawal', Arial, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        h1, h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .btn-action {
            display: inline-block;
            padding: 15px 35px;
            margin: 15px;
            font-size: 18px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            color: #fff;
            background-color: #8B4513; /* بني */
        }
        .btn-action:hover {
            opacity: 1;
            transform: scale(1.03);
            background-color: #6a340f;
            color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .form-control {
            margin-bottom: 15px;
        }
        .alert {
            margin-top: 20px;
        }
        /* CSS للنافذة المنبثقة (Modal) */
        .modal-overlay {
            display: none; /* إخفاء المودل بشكل افتراضي */
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5); /* خلفية معتمة */
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 400px;
            border-radius: 8px;
            text-align: center;
        }
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        .mt-3 {
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>💿 النسخ الاحتياطي والاستعادة</h1>
        <a href="index.php" class="btn btn-secondary mb-4">⬅️ الرجوع للرئيسية</a>
        
        <?php if (!empty($message)) { ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php } ?>

        <hr>

        <h2>النسخ الاحتياطي</h2>
        
        <a href="#" onclick="showLoadingModal(); window.location.href='backup.php?action=full_backup'" class="btn btn-action">إنشاء نسخة احتياطية كاملة</a>

        <hr>

        <h2>الاستعادة</h2>
        <p class="text-danger">
            <strong>تحذير:</strong> سيتم مسح جميع البيانات والملفات الحالية عند استعادة نسخة احتياطية.
            يرجى التأكد من أنك ترفع الملف الصحيح.
        </p>
        <form id="restoreForm" action="backup.php" method="POST" enctype="multipart/form-data" onsubmit="return showConfirmRestoreModal()">
            <div class="mb-3">
                <label for="backupFile" class="form-label">اختر ملف النسخ الاحتياطي (.zip)</label>
                <input type="file" class="form-control" id="backupFile" name="backup_file" accept=".zip" required>
            </div>
            <button type="submit" class="btn btn-action">استعادة</button>
        </form>
    </div>

    <!-- نافذة التحميل المنبثقة (Loading Modal) -->
    <div id="loadingModal" class="modal-overlay">
        <div class="modal-content">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">يتم معالجة طلبك، يرجى الانتظار...</p>
        </div>
    </div>

    <!-- نافذة التأكيد المنبثقة (Confirmation Modal) -->
    <div id="confirmModal" class="modal-overlay">
        <div class="modal-content">
            <h4>تحذير!</h4>
            <p>عملية الاستعادة ستقوم بمسح جميع البيانات والملفات الحالية واستبدالها بالبيانات الموجودة في الملف.</p>
            <p>هل أنت متأكد من المتابعة؟</p>
            <button type="button" class="btn btn-secondary" onclick="hideConfirmRestoreModal()">إلغاء</button>
            <button type="button" class="btn btn-danger" onclick="submitRestoreForm()">متابعة</button>
        </div>
    </div>

    <script>
        // دالة لإظهار نافذة التحميل
        function showLoadingModal() {
            document.getElementById('loadingModal').style.display = 'flex';
        }

        // دالة لإخفاء نافذة التحميل
        function hideLoadingModal() {
            document.getElementById('loadingModal').style.display = 'none';
        }

        // دالة لإظهار نافذة التأكيد المخصصة
        function showConfirmRestoreModal() {
            const fileInput = document.getElementById('backupFile');
            if (!fileInput.files.length) {
                // منع الإرسال إذا لم يتم اختيار ملف
                alert('يرجى اختيار ملف نسخ احتياطي أولاً.');
                return false;
            }
            document.getElementById('confirmModal').style.display = 'flex';
            // نمنع إرسال الفورم هنا، وسيتم إرساله عند الضغط على "متابعة"
            return false; 
        }
        
        // دالة لإخفاء نافذة التأكيد
        function hideConfirmRestoreModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }
        
        // دالة لإرسال الفورم بعد التأكيد
        function submitRestoreForm() {
            hideConfirmRestoreModal();
            showLoadingModal(); // إظهار نافذة التحميل قبل الإرسال
            document.getElementById('restoreForm').submit();
        }

        // إضافة حدث لإخفاء نافذة التحميل عند تحميل الصفحة بعد إرسال الفورم
        window.addEventListener('load', (event) => {
            // تحقق من وجود رسالة نجاح أو فشل من الخادم لإخفاء المودل
            const urlParams = new URLSearchParams(window.location.search);
            const action = urlParams.get('action');
            if (action && (action === 'sql_backup' || action === 'full_backup')) {
                hideLoadingModal();
            }
            // يمكن إضافة شروط أخرى هنا إذا كان هناك رسائل نجاح أو فشل بعد الـ POST
        });
    </script>
</body>
</html>
