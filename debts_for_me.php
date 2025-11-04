<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "debt_manager";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

// Check connection
if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}

// --- معالجة حذف الدين بالكامل ---
if (isset($_GET['delete_debt_id'])) {
    $delete_id = $_GET['delete_debt_id'];
    $conn->begin_transaction();

    try {
        // Find all payments associated with the debt to delete attachments
        $stmt_payments = $conn->prepare("SELECT attachment FROM payments WHERE debt_id = ?");
        $stmt_payments->bind_param("i", $delete_id);
        $stmt_payments->execute();
        $payments_result = $stmt_payments->get_result();

        while ($payment_row = $payments_result->fetch_assoc()) {
            $attachments = json_decode($payment_row['attachment'], true);
            if ($attachments) {
                foreach ($attachments as $file_name) {
                    if (file_exists("uploads/" . $file_name)) {
                        unlink("uploads/" . $file_name);
                    }
                }
            }
        }
        $stmt_payments->close();

        // Find all debt attachments to delete them
        $stmt_debt_attachments = $conn->prepare("SELECT file_name FROM debt_attachments WHERE debt_id = ?");
        $stmt_debt_attachments->bind_param("i", $delete_id);
        $stmt_debt_attachments->execute();
        $debt_attachments_result = $stmt_debt_attachments->get_result();

        while ($attachment_row = $debt_attachments_result->fetch_assoc()) {
            if (file_exists("uploads/debt_attachments/" . $attachment_row['file_name'])) {
                unlink("uploads/debt_attachments/" . $attachment_row['file_name']);
            }
        }
        $stmt_debt_attachments->close();

        // Delete all payments for the debt
        $stmt_delete_payments = $conn->prepare("DELETE FROM payments WHERE debt_id = ?");
        $stmt_delete_payments->bind_param("i", $delete_id);
        $stmt_delete_payments->execute();
        $stmt_delete_payments->close();

        // Delete all debt attachments
        $stmt_delete_debt_attachments = $conn->prepare("DELETE FROM debt_attachments WHERE debt_id = ?");
        $stmt_delete_debt_attachments->bind_param("i", $delete_id);
        $stmt_delete_debt_attachments->execute();
        $stmt_delete_debt_attachments->close();

        // Delete the debt itself
        $stmt_delete_debt = $conn->prepare("DELETE FROM debts WHERE id = ?");
        $stmt_delete_debt->bind_param("i", $delete_id);
        $stmt_delete_debt->execute();
        $stmt_delete_debt->close();

        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        // يمكنك تسجيل الخطأ هنا إذا أردت
    }

    header("Location: debts_for_me.php");
    exit;
}

// --- معالجة تعديل الدين بالكامل ---
if (isset($_POST['edit_debt'])) {
    $edit_id = $_POST['edit_id'];
    $name = $_POST['name'];
    $total_amount = $_POST['total_amount'];
    $currency = $_POST['currency'];
    $date = $_POST['date'];
    $notes = $_POST['notes'];
    $phone = $_POST['phone']; // جلب حقل الهاتف

    // Get the old debt amount and remaining amount
    $stmt_old_debt = $conn->prepare("SELECT total_amount, remaining_amount FROM debts WHERE id = ?");
    $stmt_old_debt->bind_param("i", $edit_id);
    $stmt_old_debt->execute();
    $old_debt = $stmt_old_debt->get_result()->fetch_assoc();
    $stmt_old_debt->close();

    // Calculate the difference in total amount and update the remaining amount
    $diff_amount = $total_amount - $old_debt['total_amount'];
    $new_remaining = $old_debt['remaining_amount'] + $diff_amount;

    $conn->begin_transaction();
    try {
        // Update debt details with the new phone number column
        $stmt = $conn->prepare("UPDATE debts SET name=?, total_amount=?, remaining_amount=?, currency=?, date=?, notes=?, phone=? WHERE id=?");
        $stmt->bind_param("sddssssi", $name, $total_amount, $new_remaining, $currency, $date, $notes, $phone, $edit_id);
        $stmt->execute();
        $stmt->close();

        // Handle new file uploads for the debt
        if (isset($_FILES['attachment']) && count($_FILES['attachment']['name']) > 0) {
            $total_files = count($_FILES['attachment']['name']);
            for ($i = 0; $i < $total_files; $i++) {
                if ($_FILES['attachment']['error'][$i] == 0) {
                    $original_name = basename($_FILES['attachment']['name'][$i]);
                    $mime_type = $_FILES['attachment']['type'][$i];
                    $file_name = time() . '_' . $original_name;
                    move_uploaded_file($_FILES['attachment']['tmp_name'][$i], "uploads/debt_attachments/" . $file_name);

                    $stmt_insert_attachment = $conn->prepare("INSERT INTO debt_attachments (debt_id, file_name, original_name, mime_type) VALUES (?, ?, ?, ?)");
                    $stmt_insert_attachment->bind_param("isss", $edit_id, $file_name, $original_name, $mime_type);
                    $stmt_insert_attachment->execute();
                    $stmt_insert_attachment->close();
                }
            }
        }
        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
    }

    header("Location: debts_for_me.php");
    exit;
}

// --- معالجة حذف دفعة معينة ---
if (isset($_GET['delete_payment_id'])) {
    $delete_id = $_GET['delete_payment_id'];
    $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($payment) {
        $stmt2 = $conn->prepare("UPDATE debts SET remaining_amount = remaining_amount + ? WHERE id = ?");
        $stmt2->bind_param("di", $payment['amount'], $payment['debt_id']);
        $stmt2->execute();
        $stmt2->close();

        $attachments = json_decode($payment['attachment'], true);
        if ($attachments) {
            foreach ($attachments as $file_name) {
                if (file_exists("uploads/" . $file_name)) {
                    unlink("uploads/" . $file_name);
                }
            }
        }
        $stmt3 = $conn->prepare("DELETE FROM payments WHERE id = ?");
        $stmt3->bind_param("i", $delete_id);
        $stmt3->execute();
        $stmt3->close();
    }
    header("Location: debts_for_me.php");
    exit;
}

// --- معالجة تعديل الدفعة ---
if (isset($_POST['edit_payment'])) {
    $edit_id = $_POST['edit_id'];
    $amount = $_POST['amount'];
    $currency = $_POST['currency'];
    $payment_date = $_POST['payment_date'];
    $notes = $_POST['notes'];

    $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $old_payment = $stmt->get_result()->fetch_assoc();
    $old_attachments = json_decode($old_payment['attachment'], true) ?? [];
    $stmt->close();

    if ($old_payment) {
        $diff = $amount - $old_payment['amount'];
        $stmt2 = $conn->prepare("UPDATE debts SET remaining_amount = remaining_amount - ? WHERE id = ?");
        $stmt2->bind_param("di", $diff, $old_payment['debt_id']);
        $stmt2->execute();
        $stmt2->close();

        $new_attachments = [];
        if (isset($_FILES['attachment']) && count($_FILES['attachment']['name']) > 0) {
            $total_files = count($_FILES['attachment']['name']);
            for ($i = 0; $i < $total_files; $i++) {
                if ($_FILES['attachment']['error'][$i] == 0) {
                    $attachment_name = time() . '_' . basename($_FILES['attachment']['name'][$i]);
                    move_uploaded_file($_FILES['attachment']['tmp_name'][$i], "uploads/" . $attachment_name);
                    $new_attachments[] = $attachment_name;
                }
            }
        }

        $all_attachments = array_merge($old_attachments, $new_attachments);
        $attachments_json = json_encode(array_values($all_attachments));

        $stmt3 = $conn->prepare("UPDATE payments SET amount=?, currency=?, payment_date=?, notes=?, attachment=? WHERE id=?");
        $stmt3->bind_param("dssssi", $amount, $currency, $payment_date, $notes, $attachments_json, $edit_id);
        $stmt3->execute();
        $stmt3->close();
    }
    header("Location: debts_for_me.php");
    exit;
}

// --- معالجة حذف ملف معين عبر AJAX (للدفعات)---
if (isset($_POST['delete_single_file_payment'])) {
    $file_to_delete = $_POST['file_name'];
    $payment_id = $_POST['payment_id'];

    $stmt = $conn->prepare("SELECT attachment FROM payments WHERE id = ?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    $stmt->close();

    if ($payment) {
        $attachments = json_decode($payment['attachment'], true);
        if (($key = array_search($file_to_delete, $attachments)) !== false) {
            unset($attachments[$key]);

            if (file_exists("uploads/" . $file_to_delete)) {
                unlink("uploads/" . $file_to_delete);
            }

            $new_attachments_json = json_encode(array_values($attachments));
            $stmt_update = $conn->prepare("UPDATE payments SET attachment = ? WHERE id = ?");
            $stmt_update->bind_param("si", $new_attachments_json, $payment_id);
            $stmt_update->execute();
            $stmt_update->close();
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'File not found.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Payment not found.']);
    }
    exit;
}


// --- معالجة حذف ملف معين عبر AJAX (للدين)---
if (isset($_POST['delete_single_file_debt'])) {
    $file_to_delete = $_POST['file_name'];
    $debt_id = $_POST['debt_id'];

    $stmt = $conn->prepare("SELECT * FROM debt_attachments WHERE debt_id = ? AND file_name = ?");
    $stmt->bind_param("is", $debt_id, $file_to_delete);
    $stmt->execute();
    $attachment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($attachment) {
        if (file_exists("uploads/debt_attachments/" . $file_to_delete)) {
            unlink("uploads/debt_attachments/" . $file_to_delete);
        }

        $stmt_delete = $conn->prepare("DELETE FROM debt_attachments WHERE id = ?");
        $stmt_delete->bind_param("i", $attachment['id']);
        $stmt_delete->execute();
        $stmt_delete->close();
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File not found.']);
    }
    exit;
}


// --- جلب الديون ومجموعها ---
$sql_debts = "SELECT * FROM debts WHERE type = 'لي'";
$result_debts = $conn->query($sql_debts);

$total_remaining_usd = 0;
$total_remaining_iqd = 0;

$sql_total = "SELECT currency, SUM(remaining_amount) as total FROM debts WHERE type = 'لي' GROUP BY currency";
$result_total = $conn->query($sql_total);
if ($result_total->num_rows > 0) {
    while($row_total = $result_total->fetch_assoc()) {
        if ($row_total['currency'] == 'IQD') {
            $total_remaining_iqd = $row_total['total'];
        } else if ($row_total['currency'] == 'USD') {
            $total_remaining_usd = $row_total['total'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>الديون لي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', Arial, sans-serif; background: #f4f4f4; text-align: center; padding: 20px; }
        .total-summary {
            font-size: 1.2em;
            margin-top: 10px;
            padding: 10px;
            background: #e9ecef;
            border-radius: 5px;
            display: inline-block;
        }
        table { width: 100%; max-width: 900px; margin: 20px auto; border-collapse: collapse; background: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        th { background-color: #28a745; color: #fff; }
        tr:nth-child(even){ background: #f9f9f9; }
        tr:hover { background: #f1f1f1; }
        a { color: #007bff; text-decoration: none; font-weight: bold; }
        a:hover { text-decoration: underline; }
        a.delete-btn { color: #dc3545; }
        .sub-table { margin-top: 10px; width: 100%; }
        button { padding: 5px 10px; margin:2px; border-radius:5px; border:none; cursor:pointer; }
        button.save { background:#28a745; color:white; }
        button.cancel { background:#dc3545; color:white; }
        input, select, textarea { width: 100%; padding:5px; margin-top:3px; }
        form.inline { text-align:right; }
        .file-list { list-style: none; padding: 0; }
        .file-list li { display: flex; align-items: center; padding: 5px; border-bottom: 1px solid #eee; }
        .file-list li:last-child { border-bottom: none; }
        .delete-file-btn { color: red; cursor: pointer; font-weight: bold; margin-right: auto; }
        .file-list .file-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 5px; margin-left: 10px; }
        .file-name { margin-left: auto; }
        .modal-img{width:100%;height:80vh;object-fit:contain;}
        .modal-doc{width:100%;height:80vh;}
    </style>
</head>
<body>

<h2>الديون لي</h2>
<a href="add_debt.php">➕ إضافة دين جديد</a> | <a href="index.php">الصفحة الرئيسية</a>

<div class="total-summary">
    <strong>المجموع الكلي للديون المتبقية لي:</strong>
    <?php echo number_format($total_remaining_iqd, 2) . " دينار عراقي" . ($total_remaining_usd > 0 ? " | " : ""); ?>
    <?php if ($total_remaining_usd > 0) echo number_format($total_remaining_usd, 2) . " دولار أمريكي"; ?>
</div>

<?php
if ($result_debts->num_rows > 0) {
    while ($row = $result_debts->fetch_assoc()) {
        $edit_debt_mode = (isset($_GET['edit_debt_id']) && $_GET['edit_debt_id'] == $row['id']);
        echo "<table>";
        echo "<tr>";
        if ($edit_debt_mode) {
            // Fetch debt attachments for editing
            $stmt_attachments = $conn->prepare("SELECT file_name, original_name FROM debt_attachments WHERE debt_id = ?");
            $stmt_attachments->bind_param("i", $row['id']);
            $stmt_attachments->execute();
            $debt_attachments = $stmt_attachments->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_attachments->close();

            echo "<td colspan='6'>";
            echo "<form method='POST' class='inline' enctype='multipart/form-data'>";
            echo "<input type='hidden' name='edit_id' value='{$row['id']}'>";
            echo "<label>اسم المدين:</label><input type='text' name='name' value='{$row['name']}' required>";
            echo "<label>المبلغ الكلي:</label><input type='number' step='0.01' name='total_amount' value='{$row['total_amount']}' required>";
            echo "<label>العملة:</label><select name='currency'>";
            echo "<option value='IQD' " . ($row['currency'] == 'IQD' ? 'selected' : '') . ">دينار عراقي</option>";
            echo "<option value='USD' " . ($row['currency'] == 'USD' ? 'selected' : '') . ">دولار أمريكي</option>";
            echo "</select>";
            echo "<label>التاريخ:</label><input type='date' name='date' value='{$row['date']}' required>";
            echo "<label>رقم الهاتف:</label><input type='tel' name='phone' value='{$row['phone']}'>";
            echo "<label>ملاحظات:</label><textarea name='notes'>{$row['notes']}</textarea>";
            echo "<label>الملفات الحالية:</label>";
            if(!empty($debt_attachments)) {
                echo "<ul class='file-list'>";
                foreach($debt_attachments as $file) {
                    $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                    $filePath = "uploads/debt_attachments/" . $file['file_name'];
                    echo "<li>";
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        echo "<img src='{$filePath}' class='file-thumb'>";
                    } else if ($ext === 'pdf') {
                        echo "<span class='file-thumb'>PDF</span>";
                    }
                    echo "<span class='file-name'>" . htmlspecialchars($file['original_name']) . "</span>";
                    echo "<span class='delete-file-btn' onclick='deleteFile(\"{$row['id']}\", \"{$file['file_name']}\", \"debt\")'>حذف &times;</span>";
                    echo "</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>لا يوجد ملفات حالية.</p>";
            }
            echo "<label>إضافة ملفات جديدة:</label><input type='file' name='attachment[]' multiple>";
            echo "<button type='submit' name='edit_debt' class='save'>حفظ تعديل الدين</button>";
            echo "<a href='debts_for_me.php'><button type='button' class='cancel'>إلغاء</button></a>";
            echo "</form>";
            echo "</td>";
        } else {
            // New part: Check if there are attachments for the debt
            $stmt_count_attachments = $conn->prepare("SELECT COUNT(*) AS count FROM debt_attachments WHERE debt_id = ?");
            $stmt_count_attachments->bind_param("i", $row['id']);
            $stmt_count_attachments->execute();
            $attachments_count = $stmt_count_attachments->get_result()->fetch_assoc()['count'];
            $stmt_count_attachments->close();

            echo "<th colspan='6'>{$row['name']} - المبلغ الكلي: " . number_format($row['total_amount'], 2) . " {$row['currency']} - المتبقي: " . number_format($row['remaining_amount'], 2) . " {$row['currency']}</th>";
            echo "</tr>";
            echo "<tr>";
            echo "<th>التاريخ</th>";
            echo "<th>رقم الهاتف</th>"; // عمود جديد
            echo "<th>ملاحظات</th>";
            echo "<th>إجراء</th>";
            echo "</tr>";
            echo "<tr>";
            echo "<td>{$row['date']}</td>";
            echo "<td>{$row['phone']}</td>"; // عرض قيمة رقم الهاتف
            echo "<td>{$row['notes']}</td>";
            echo "<td>";
            // Get attachments
            $stmt_debt_attachments = $conn->prepare("SELECT file_name, original_name, mime_type FROM debt_attachments WHERE debt_id = ?");
            $stmt_debt_attachments->bind_param("i", $row['id']);
            $stmt_debt_attachments->execute();
            $debt_attachments_result = $stmt_debt_attachments->get_result();
            $debt_attachments = [];
            while ($attachment_row = $debt_attachments_result->fetch_assoc()) {
                $debt_attachments[] = $attachment_row;
            }
            $stmt_debt_attachments->close();
            // Add the new button for debt attachments
            if (!empty($debt_attachments)) {
                echo "<button onclick='openModal(" . json_encode($debt_attachments) . ", 0, \"debt\")' style='background: #17a2b8; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 5px;'>عرض المرفقات</button> | ";
            }
            echo "<a href='add_payment.php?debt_id={$row['id']}'>تسجيل دفعة</a> | ";
            echo "<a href='debts_for_me.php?edit_debt_id={$row['id']}'>تعديل الدين</a> | ";
            echo "<a href='debts_for_me.php?delete_debt_id={$row['id']}' onclick='return confirm(\"هل أنت متأكد من حذف هذا الدين وكل دفعاته؟\")' class='delete-btn'>حذف الدين</a>";
            echo "</td>";
            echo "</tr>";
        }
        echo "<tr><td colspan='6'>";
        // --- جلب الدفعات ---
        $stmt = $conn->prepare("SELECT * FROM payments WHERE debt_id=? ORDER BY payment_date DESC");
        $stmt->bind_param("i", $row['id']);
        $stmt->execute();
        $payments = $stmt->get_result();
        $stmt->close();

        if ($payments->num_rows > 0) {
            echo "<table class='sub-table'>";
            echo "<tr>";
            echo "<th>المبلغ</th>";
            echo "<th>العملة</th>";
            echo "<th>التاريخ</th>";
            echo "<th>ملاحظات</th>";
            echo "<th>الملفات المرفقة</th>";
            echo "<th>إجراءات</th>";
            echo "</tr>";
            while ($p = $payments->fetch_assoc()) {
                $edit_mode = (isset($_GET['edit_id']) && $_GET['edit_id'] == $p['id']);
                echo "<tr>";
                if ($edit_mode) {
                    $attachments = json_decode($p['attachment'], true) ?? [];
                    echo "<td colspan='6'>";
                    echo "<form method='POST' class='inline' enctype='multipart/form-data'>";
                    echo "<input type='hidden' name='edit_id' value='{$p['id']}'>";
                    echo "<label>المبلغ:</label><input type='number' step='0.01' name='amount' value='{$p['amount']}' required>";
                    echo "<label>العملة:</label>";
                    echo "<select name='currency'>";
                    echo "<option value='IQD' " . ($p['currency'] == 'IQD' ? 'selected' : '') . ">دينار عراقي</option>";
                    echo "<option value='USD' " . ($p['currency'] == 'USD' ? 'selected' : '') . ">دولار أمريكي</option>";
                    echo "</select>";
                    echo "<label>التاريخ:</label><input type='date' name='payment_date' value='{$p['payment_date']}' required>";
                    echo "<label>ملاحظات:</label><textarea name='notes'>{$p['notes']}</textarea>";
                    echo "<label>الملفات الحالية:</label>";
                    if(!empty($attachments)) {
                        echo "<ul class='file-list'>";
                        foreach($attachments as $file) {
                            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                            $filePath = "uploads/" . $file;
                            echo "<li>";
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                echo "<img src='{$filePath}' class='file-thumb'>";
                            } else if ($ext === 'pdf') {
                                echo "<span class='file-thumb'>PDF</span>";
                            }
                            echo "<span class='file-name'>" . htmlspecialchars($file) . "</span>";
                            echo "<span class='delete-file-btn' onclick='deleteFile(\"{$p['id']}\", \"{$file}\", \"payment\")'>حذف &times;</span>";
                            echo "</li>";
                        }
                        echo "</ul>";
                    } else {
                        echo "<p>لا يوجد ملفات حالية.</p>";
                    }

                    echo "<label>إضافة ملفات جديدة:</label><input type='file' name='attachment[]' multiple>";
                    echo "<button type='submit' name='edit_payment' class='save'>حفظ</button>";
                    echo "<a href='debts_for_me.php'><button type='button' class='cancel'>إلغاء</button></a>";
                    echo "</form>";
                    echo "</td>";
                } else {
                    $attachments = json_decode($p['attachment'], true) ?? [];
                    echo "<td>" . number_format($p['amount'], 2) . "</td>";
                    echo "<td>{$p['currency']}</td>";
                    echo "<td>{$p['payment_date']}</td>";
                    echo "<td>{$p['notes']}</td>";
                    echo "<td>";
                    if (!empty($attachments)) {
                        $files_json_array = [];
                        foreach ($attachments as $file_name) {
                            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            $files_json_array[] = ['original_name' => $file_name, 'file_name' => $file_name, 'mime_type' => $ext];
                        }
                        echo "<button onclick='openModal(" . json_encode($files_json_array) . ", 0, \"payment\")' style='background: #17a2b8; color: white;'>عرض المرفقات (" . count($attachments) . ")</button>";
                    } else {
                        echo "-";
                    }
                    echo "</td>";
                    echo "<td>";
                    echo "<a href='debts_for_me.php?edit_id={$p['id']}'>تعديل</a> | ";
                    echo "<a href='debts_for_me.php?delete_payment_id={$p['id']}' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' class='delete-btn'>حذف</a>";
                    echo "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>لا توجد دفعات مسجلة لهذا الدين.</p>";
        }
        echo "</td></tr>";
        echo "</table><br>";
    }
} else {
    echo "<p>لا يوجد ديون حالياً</p>";
}
?>

<div class="modal fade" id="fileModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">عرض الملف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" class="modal-img d-none">
                <iframe id="modalPDF" class="modal-doc d-none"></iframe>
                <div id="modalDocDownload" class="d-none">
                    <a id="downloadLink" href="#" class="btn btn-primary mt-3" download>تحميل الملف</a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="prevFile()">السابق</button>
                <button type="button" class="btn btn-secondary" onclick="nextFile()">التالي</button>
                <button type="button" class="btn btn-success" onclick="printFile()">طباعة</button>
                <a id="downloadBtn" class="btn btn-info" download>تنزيل</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let files = [];
    let currentIndex = 0;
    let fileType = 'payment'; // 'payment' or 'debt'

    function openModal(fileArray, index, type) {
        files = fileArray;
        currentIndex = index;
        fileType = type;
        showFile();
        new bootstrap.Modal(document.getElementById('fileModal')).show();
    }

    function showFile() {
        const img = document.getElementById('modalImage');
        const pdf = document.getElementById('modalPDF');
        const doc = document.getElementById('modalDocDownload');
        const dl = document.getElementById('downloadBtn');
        const prevBtn = document.querySelector("#fileModal .modal-footer .btn-secondary:nth-of-type(1)");
        const nextBtn = document.querySelector("#fileModal .modal-footer .btn-secondary:nth-of-type(2)");
        const printBtn = document.querySelector("#fileModal .modal-footer .btn-success");

        if (!files[currentIndex]) return;

        let f = files[currentIndex];
        let ext = f.original_name.split('.').pop().toLowerCase();
        let path = (fileType === 'debt' ? 'uploads/debt_attachments/' : 'uploads/') + f.file_name;

        img.classList.add('d-none');
        pdf.classList.add('d-none');
        doc.classList.add('d-none');
        dl.style.display = 'none';
        printBtn.style.display = 'none';

        if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
            img.src = path;
            img.classList.remove('d-none');
            dl.style.display = 'inline-block';
            printBtn.style.display = 'inline-block';
        } else if (ext === 'pdf') {
            pdf.src = path;
            pdf.classList.remove('d-none');
            dl.style.display = 'inline-block';
            printBtn.style.display = 'inline-block';
        } else {
            doc.classList.remove('d-none');
            document.getElementById('downloadLink').href = path;
            dl.style.display = 'inline-block';
            printBtn.style.display = 'none';
        }

        dl.href = path;
        dl.download = f.original_name;

        prevBtn.disabled = currentIndex === 0;
        nextBtn.disabled = currentIndex === files.length - 1;
    }

    function prevFile() {
        if (currentIndex > 0) {
            currentIndex--;
            showFile();
        }
    }

    function nextFile() {
        if (currentIndex < files.length - 1) {
            currentIndex++;
            showFile();
        }
    }

    function printFile() {
        if (!files[currentIndex]) return;
        let f = files[currentIndex];
        let ext = f.original_name.split('.').pop().toLowerCase();
        let path = (fileType === 'debt' ? 'uploads/debt_attachments/' : 'uploads/') + f.file_name;
        if (['jpg', 'jpeg', 'png', 'gif', 'pdf'].includes(ext)) {
            let win = window.open(path, '_blank');
            win.onload = function() {
                win.print();
            };
        } else {
            alert("لا يمكن طباعة هذا النوع من الملفات");
        }
    }

    function deleteFile(id, fileName, type) {
        if (confirm('هل أنت متأكد من حذف هذا الملف؟')) {
            const formData = new FormData();
            formData.append('file_name', fileName);
            if (type === 'payment') {
                formData.append('delete_single_file_payment', '1');
                formData.append('payment_id', id);
            } else { // type === 'debt'
                formData.append('delete_single_file_debt', '1');
                formData.append('debt_id', id);
            }

            fetch('debts_for_me.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    alert('حدث خطأ أثناء حذف الملف.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ في الاتصال بالخادم.');
            });
        }
    }
</script>

</body>
</html>