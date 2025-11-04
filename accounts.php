<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "debt_manager";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}

// --- معالجة البحث ---
$search_results = [];
$search_query = $_GET['search_query'] ?? '';
$debt_type_filter = $_GET['debt_type'] ?? 'all';
$currency_filter = $_GET['currency_filter'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$search_type_filter = $_GET['search_type'] ?? 'all'; // الحقل الجديد

if (isset($_GET['search'])) {
    // بناء استعلامات البحث بناءً على النوع المحدد
    if ($search_type_filter == 'all' || $search_type_filter == 'debt') {
        // --- بناء استعلام البحث عن الديون ---
        $query_debts = "SELECT * FROM debts WHERE 1";
        $params_debts = [];
        $types_debts = "";

        if (!empty($search_query)) {
            $query_debts .= " AND (name LIKE ? OR notes LIKE ?)";
            $params_debts[] = "%" . $search_query . "%";
            $params_debts[] = "%" . $search_query . "%";
            $types_debts .= "ss";
        }

        if ($debt_type_filter != 'all') {
            $query_debts .= " AND type = ?";
            $params_debts[] = $debt_type_filter;
            $types_debts .= "s";
        }
        
        if ($currency_filter != 'all') {
            $query_debts .= " AND currency = ?";
            $params_debts[] = $currency_filter;
            $types_debts .= "s";
        }

        if (!empty($start_date)) {
            $query_debts .= " AND date >= ?";
            $params_debts[] = $start_date;
            $types_debts .= "s";
        }

        if (!empty($end_date)) {
            $query_debts .= " AND date <= ?";
            $params_debts[] = $end_date;
            $types_debts .= "s";
        }

        $stmt_debts = $conn->prepare($query_debts);
        if (!empty($params_debts)) {
            $stmt_debts->bind_param($types_debts, ...$params_debts);
        }
        $stmt_debts->execute();
        $debts_result = $stmt_debts->get_result();
        while ($row = $debts_result->fetch_assoc()) {
            $search_results[] = ['type' => 'debt', 'data' => $row];
        }
    }
    
    if ($search_type_filter == 'all' || $search_type_filter == 'payment') {
        // --- بناء استعلام البحث عن الدفعات ---
        $query_payments = "SELECT p.*, d.name AS debt_name, d.type AS debt_type FROM payments p JOIN debts d ON p.debt_id = d.id WHERE 1";
        $params_payments = [];
        $types_payments = "";

        if (!empty($search_query)) {
            $query_payments .= " AND (p.notes LIKE ? OR d.name LIKE ?)";
            $params_payments[] = "%" . $search_query . "%";
            $params_payments[] = "%" . $search_query . "%";
            $types_payments .= "ss";
        }

        if ($debt_type_filter != 'all') {
            $query_payments .= " AND d.type = ?";
            $params_payments[] = $debt_type_filter;
            $types_payments .= "s";
        }
        
        if ($currency_filter != 'all') {
            $query_payments .= " AND p.currency = ?";
            $params_payments[] = $currency_filter;
            $types_payments .= "s";
        }

        if (!empty($start_date)) {
            $query_payments .= " AND p.payment_date >= ?";
            $params_payments[] = $start_date;
            $types_payments .= "s";
        }

        if (!empty($end_date)) {
            $query_payments .= " AND p.payment_date <= ?";
            $params_payments[] = $end_date;
            $types_payments .= "s";
        }

        $stmt_payments = $conn->prepare($query_payments);
        if (!empty($params_payments)) {
            $stmt_payments->bind_param($types_payments, ...$params_payments);
        }
        $stmt_payments->execute();
        $payments_result = $stmt_payments->get_result();
        while ($row = $payments_result->fetch_assoc()) {
            $search_results[] = ['type' => 'payment', 'data' => $row];
        }
    }
}

// --- جلب جميع الديون والدفعات للتقارير ---
$total_debts_on_iqd = 0;
$total_debts_on_usd = 0;
$total_debts_for_iqd = 0;
$total_debts_for_usd = 0;

$sql_total_on = "SELECT currency, SUM(remaining_amount) as total FROM debts WHERE type = 'عليّ' GROUP BY currency";
$result_total_on = $conn->query($sql_total_on);
while($row = $result_total_on->fetch_assoc()) {
    if ($row['currency'] == 'IQD') {
        $total_debts_on_iqd = $row['total'];
    } elseif ($row['currency'] == 'USD') {
        $total_debts_on_usd = $row['total'];
    }
}

$sql_total_for = "SELECT currency, SUM(remaining_amount) as total FROM debts WHERE type = 'لي' GROUP BY currency";
$result_total_for = $conn->query($sql_total_for);
while($row = $result_total_for->fetch_assoc()) {
    if ($row['currency'] == 'IQD') {
        $total_debts_for_iqd = $row['total'];
    } elseif ($row['currency'] == 'USD') {
        $total_debts_for_usd = $row['total'];
    }
}

$all_debts_on = $conn->query("SELECT * FROM debts WHERE type = 'عليّ' ORDER BY date DESC");
$all_debts_for = $conn->query("SELECT * FROM debts WHERE type = 'لي' ORDER BY date DESC");
$all_payments = $conn->query("SELECT p.*, d.name AS debt_name, d.type AS debt_type FROM payments p JOIN debts d ON p.debt_id = d.id ORDER BY p.payment_date DESC");

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>الحسابات والتقارير</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h1, h2 { text-align: center; color: #333; }
        .summary-box { display: flex; justify-content: space-around; margin-bottom: 30px; }
        .summary-item { padding: 20px; border-radius: 8px; text-align: center; color: #fff; font-size: 20px; font-weight: bold; }
        .on-me { background-color: #dc3545; }
        .for-me { background-color: #28a745; }
        .search-form { text-align: center; margin-bottom: 30px; }
        .search-form input, .search-form select { padding: 10px; border-radius: 5px; border: 1px solid #ccc; margin-top: 10px; }
        .search-form button { padding: 10px 20px; border: none; border-radius: 5px; background-color: #007bff; color: white; cursor: pointer; }
        table { width: 100%; margin-top: 20px; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        th { background-color: #6c757d; color: #fff; }
        .table-on-me th { background-color: #dc3545; }
        .table-for-me th { background-color: #28a745; }
        a { text-decoration: none; color: #007bff; }
        .export-buttons { text-align: right; margin-bottom: 15px; }
        .export-buttons a { margin-left: 10px; padding: 8px 15px; border-radius: 5px; color: white; }
        .export-csv { background-color: #28a745; }
        .export-pdf { background-color: #dc3545; }
        .total-row { font-weight: bold; background-color: #e9e9e9; }
    </style>
</head>
<body>

<div class="container">
    <h1>📊 الحسابات والتقارير</h1>
    <a href="index.php" class="btn btn-secondary mb-4">⬅️ الرجوع للرئيسية</a>

    <div class="summary-box">
        <div class="summary-item on-me">
            الديون عليّ:<br>
            <?php if ($total_debts_on_iqd > 0) echo number_format($total_debts_on_iqd, 2) . " دينار عراقي<br>"; ?>
            <?php if ($total_debts_on_usd > 0) echo number_format($total_debts_on_usd, 2) . " دولار أمريكي"; ?>
        </div>
        <div class="summary-item for-me">
            الديون لي:<br>
            <?php if ($total_debts_for_iqd > 0) echo number_format($total_debts_for_iqd, 2) . " دينار عراقي<br>"; ?>
            <?php if ($total_debts_for_usd > 0) echo number_format($total_debts_for_usd, 2) . " دولار أمريكي"; ?>
        </div>
    </div>
    
    <hr>
    
    <h2>بحث شامل</h2>
    <div class="search-form">
        <form method="GET">
            <input type="text" name="search_query" placeholder="ابحث عن اسم أو ملاحظات..." value="<?php echo htmlspecialchars($search_query); ?>">
            <br>
            <label for="search_type">النوع:</label>
            <select name="search_type" id="search_type">
                <option value="all" <?php if($search_type_filter == 'all') echo 'selected'; ?>>الكل</option>
                <option value="debt" <?php if($search_type_filter == 'debt') echo 'selected'; ?>>دين</option>
                <option value="payment" <?php if($search_type_filter == 'payment') echo 'selected'; ?>>دفعة</option>
            </select>
            <br>
            <label for="debt_type">نوع الدين:</label>
            <select name="debt_type" id="debt_type">
                <option value="all" <?php if($debt_type_filter == 'all') echo 'selected'; ?>>الكل</option>
                <option value="عليّ" <?php if($debt_type_filter == 'عليّ') echo 'selected'; ?>>عليّ</option>
                <option value="لي" <?php if($debt_type_filter == 'لي') echo 'selected'; ?>>لي</option>
            </select>
            <br>
            <label for="currency_filter">العملة:</label>
            <select name="currency_filter" id="currency_filter">
                <option value="all" <?php if($currency_filter == 'all') echo 'selected'; ?>>الكل</option>
                <option value="IQD" <?php if($currency_filter == 'IQD') echo 'selected'; ?>>IQD</option>
                <option value="USD" <?php if($currency_filter == 'USD') echo 'selected'; ?>>USD</option>
            </select>
            <br>
            <label for="start_date">من تاريخ:</label>
            <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            <label for="end_date">إلى تاريخ:</label>
            <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            <br>
            <button type="submit" name="search" class="mt-3">بحث</button>
        </form>
    </div>

    <?php if (isset($_GET['search'])) { ?>
        <h3>نتائج البحث</h3>
        <?php if (!empty($search_results)) { 
            // بناء رابط التصدير مع جميع معايير البحث
            $export_url_params = http_build_query($_GET);

            // --- الكود الجديد لحساب المجاميع حسب نوع الدين والعملة ---
            $total_on_me_iqd = 0;
            $total_on_me_usd = 0;
            $total_for_me_iqd = 0;
            $total_for_me_usd = 0;

            foreach ($search_results as $item) {
                // تحديد المبلغ والعملة ونوع الدين
                // يتم استخدام amount للدفعات و remaining_amount للديون (في حالة كانت ديون)
                $amount = ($item['type'] == 'debt') ? $item['data']['remaining_amount'] : $item['data']['amount'];
                $currency = $item['data']['currency'];
                $debt_type = $item['data']['debt_type'] ?? $item['data']['type'];

                // عند عرض الديون نجمع المتبقي، وعند عرض الدفعات نجمع المبلغ المدفوع
                if ($debt_type == 'عليّ') {
                    if ($currency == 'IQD') {
                        $total_on_me_iqd += $amount;
                    } elseif ($currency == 'USD') {
                        $total_on_me_usd += $amount;
                    }
                } elseif ($debt_type == 'لي') {
                    if ($currency == 'IQD') {
                        $total_for_me_iqd += $amount;
                    } elseif ($currency == 'USD') {
                        $total_for_me_usd += $amount;
                    }
                }
            }
            // --- نهاية الكود الجديد ---
        ?>
            <div class="export-buttons">
                <a href="export_data.php?export_type=csv&<?php echo $export_url_params; ?>" class="btn export-csv">تصدير CSV</a>
                <a href="export_data.php?export_type=pdf&<?php echo $export_url_params; ?>" class="btn export-pdf">تصدير PDF</a>
            </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>النوع</th>
                        <th>الاسم/الجهة</th>
                        <th>المبلغ</th>
                        <th>التاريخ</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($search_results as $item) { ?>
                        <tr>
                            <td><?php echo ($item['type'] == 'debt') ? 'دين ' . htmlspecialchars($item['data']['type']) : 'دفعة ' . htmlspecialchars($item['data']['debt_type']); ?></td>
                            <td><?php echo htmlspecialchars($item['data']['debt_name'] ?? $item['data']['name']); ?></td>
                            <td><?php echo number_format($item['data']['amount'] ?? $item['data']['remaining_amount'], 2) . ' ' . $item['data']['currency']; ?></td>
                            <td><?php echo htmlspecialchars($item['data']['payment_date'] ?? $item['data']['date']); ?></td>
                            <td><?php echo htmlspecialchars($item['data']['notes']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3">المجموع الكلي للديون لي:</td>
                        <td colspan="2">
                            <?php if ($total_for_me_iqd > 0) echo number_format($total_for_me_iqd, 2) . ' دينار عراقي'; ?>
                            <?php if ($total_for_me_iqd > 0 && $total_for_me_usd > 0) echo '<br>'; ?>
                            <?php if ($total_for_me_usd > 0) echo number_format($total_for_me_usd, 2) . ' دولار أمريكي'; ?>
                        </td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3">المجموع الكلي للديون عليّ:</td>
                        <td colspan="2">
                            <?php if ($total_on_me_iqd > 0) echo number_format($total_on_me_iqd, 2) . ' دينار عراقي'; ?>
                            <?php if ($total_on_me_iqd > 0 && $total_on_me_usd > 0) echo '<br>'; ?>
                            <?php if ($total_on_me_usd > 0) echo number_format($total_on_me_usd, 2) . ' دولار أمريكي'; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        <?php } else { ?>
            <p class="text-center text-danger">لا توجد نتائج مطابقة.</p>
        <?php } ?>
    <?php } ?>

    <hr>
    
    <h2>تقارير شاملة</h2>

    <h3>جميع الديون عليّ</h3>
    <?php if ($all_debts_on->num_rows > 0) { ?>
    <table class="table table-striped table-on-me">
        <thead>
            <tr>
                <th>الاسم/الجهة</th>
                <th>المبلغ الكلي</th>
                <th>المبلغ المتبقي</th>
                <th>التاريخ</th>
                <th>ملاحظات</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $all_debts_on->fetch_assoc()) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo number_format($row['total_amount'], 2) . ' ' . $row['currency']; ?></td>
                <td><?php echo number_format($row['remaining_amount'], 2) . ' ' . $row['currency']; ?></td>
                <td><?php echo htmlspecialchars($row['date']); ?></td>
                <td><?php echo htmlspecialchars($row['notes']); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
    <?php } else { ?>
    <p class="text-center">لا توجد ديون مسجلة عليّ.</p>
    <?php } ?>
    
    <h3>جميع الديون لي</h3>
    <?php if ($all_debts_for->num_rows > 0) { ?>
    <table class="table table-striped table-for-me">
        <thead>
            <tr>
                <th>الاسم/الجهة</th>
                <th>المبلغ الكلي</th>
                <th>المبلغ المتبقي</th>
                <th>التاريخ</th>
                <th>ملاحظات</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $all_debts_for->fetch_assoc()) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo number_format($row['total_amount'], 2) . ' ' . $row['currency']; ?></td>
                <td><?php echo number_format($row['remaining_amount'], 2) . ' ' . $row['currency']; ?></td>
                <td><?php echo htmlspecialchars($row['date']); ?></td>
                <td><?php echo htmlspecialchars($row['notes']); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
    <?php } else { ?>
    <p class="text-center">لا توجد ديون مسجلة لي.</p>
    <?php } ?>
    
    <h3>جميع الدفعات المسجلة</h3>
    <?php if ($all_payments->num_rows > 0) { ?>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>الجهة/الشخص</th>
                <th>نوع الدين</th>
                <th>المبلغ المدفوع</th>
                <th>تاريخ الدفعة</th>
                <th>ملاحظات</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $all_payments->fetch_assoc()) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['debt_name']); ?></td>
                <td><?php echo htmlspecialchars($row['debt_type']); ?></td>
                <td><?php echo number_format($row['amount'], 2) . ' ' . $row['currency']; ?></td>
                <td><?php echo htmlspecialchars($row['payment_date']); ?></td>
                <td><?php echo htmlspecialchars($row['notes']); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
    <?php } else { ?>
    <p class="text-center">لا توجد دفعات مسجلة.</p>
    <?php } ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>