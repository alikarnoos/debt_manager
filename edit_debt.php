<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "debt_manager";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}

// Check if a debt ID is provided in the URL
if (!isset($_GET['debt_id'])) {
    die("لم يتم تحديد الدين المراد تعديله.");
}
$debt_id = $_GET['debt_id'];

// Fetch the existing debt data
$stmt = $conn->prepare("SELECT * FROM debts WHERE id = ?");
$stmt->bind_param("i", $debt_id);
$stmt->execute();
$debt = $stmt->get_result()->fetch_assoc();

if (!$debt) {
    die("الدين غير موجود.");
}

// --- Handle form submission for editing the debt ---
if (isset($_POST['edit_debt'])) {
    $name = $_POST['name'];
    $total_amount = $_POST['total_amount'];
    $currency = $_POST['currency'];
    $notes = $_POST['notes'];
    
    // Get the old total amount to calculate the difference
    $old_total_amount = $debt['total_amount'];
    
    // Fetch the sum of all payments for this debt
    $stmt_payments = $conn->prepare("SELECT SUM(amount) AS total_paid FROM payments WHERE debt_id = ?");
    $stmt_payments->bind_param("i", $debt_id);
    $stmt_payments->execute();
    $result_payments = $stmt_payments->get_result();
    $row_payments = $result_payments->fetch_assoc();
    $total_paid = $row_payments['total_paid'] ?? 0; // Use 0 if no payments exist
    
    // Calculate the new remaining amount
    $new_remaining_amount = $total_amount - $total_paid;
    
    // Update the debt in the database
    $stmt_update = $conn->prepare("UPDATE debts SET name = ?, total_amount = ?, remaining_amount = ?, currency = ?, notes = ? WHERE id = ?");
    $stmt_update->bind_param("sddssi", $name, $total_amount, $new_remaining_amount, $currency, $notes, $debt_id);
    
    if ($stmt_update->execute()) {
        header("Location: debts_on_me.php");
        exit;
    } else {
        echo "حدث خطأ: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعديل دين</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', Arial, sans-serif; background: #f4f4f4; text-align: center; padding: 20px; }
        .form-container { max-width: 500px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; text-align: right; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { padding: 10px 20px; background: #800020; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #66001a; }
        .back-link { margin-top: 20px; display: block; }
    </style>
</head>
<body>

<div class="form-container">
    <h2>تعديل دين</h2>
    <form action="" method="POST">
        <div class="form-group">
            <label for="name">الاسم:</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($debt['name']); ?>" required>
        </div>
        <div class="form-group">
            <label for="total_amount">المبلغ الإجمالي:</label>
            <input type="number" step="0.01" id="total_amount" name="total_amount" value="<?php echo htmlspecialchars($debt['total_amount']); ?>" required>
        </div>
        <div class="form-group">
            <label for="currency">العملة:</label>
            <select id="currency" name="currency">
                <option value="IQD" <?php echo ($debt['currency'] == 'IQD') ? 'selected' : ''; ?>>دينار عراقي</option>
                <option value="USD" <?php echo ($debt['currency'] == 'USD') ? 'selected' : ''; ?>>دولار أمريكي</option>
            </select>
        </div>
        <div class="form-group">
            <label for="notes">ملاحظات:</label>
            <textarea id="notes" name="notes"><?php echo htmlspecialchars($debt['notes']); ?></textarea>
        </div>
        <button type="submit" name="edit_debt">تعديل الدين</button>
    </form>
    <a href="debts_on_me.php" class="back-link">العودة إلى الديون عليّ</a>
</div>

</body>
</html>
