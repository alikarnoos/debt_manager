<?php
// إعداد الاتصال بقاعدة البيانات
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "debt_manager";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}

// استلام معايير البحث من الرابط
$export_type       = $_GET['export_type'] ?? 'csv';
$search_query      = $_GET['search_query'] ?? '';
$debt_type_filter  = $_GET['debt_type'] ?? 'all';
$currency_filter   = $_GET['currency_filter'] ?? 'all';
$start_date        = $_GET['start_date'] ?? '';
$end_date          = $_GET['end_date'] ?? '';
$search_type_filter = $_GET['search_type'] ?? 'all';

// مصفوفة لتخزين نتائج البحث
$search_results = [];

// ================= البحث في الديون =================
if ($search_type_filter == 'all' || $search_type_filter == 'debt') {
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

// ================= البحث في الدفعات =================
if ($search_type_filter == 'all' || $search_type_filter == 'payment') {
    $query_payments = "SELECT p.*, d.name AS debt_name, d.type AS debt_type, d.currency AS debt_currency 
                       FROM payments p 
                       JOIN debts d ON p.debt_id = d.id 
                       WHERE 1";
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

// ================= التصدير =================
if ($export_type == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=debt_report_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM لدعم العربية

    fputcsv($output, ['النوع', 'الاسم/الجهة', 'المبلغ', 'التاريخ', 'ملاحظات']);

    foreach ($search_results as $item) {
        $amount   = ($item['type'] == 'debt') ? $item['data']['remaining_amount'] : $item['data']['amount'];
        $currency = ($item['type'] == 'debt') ? $item['data']['currency'] : ($item['data']['debt_currency'] ?? $item['data']['currency']);
        $debt_type_label = ($item['type'] == 'debt') ? 'دين ' . $item['data']['type'] : 'دفعة ' . ($item['data']['debt_type'] ?? '');
        $name_label = $item['data']['debt_name'] ?? $item['data']['name'];
        $date_label = $item['data']['payment_date'] ?? $item['data']['date'];

        fputcsv($output, [
            $debt_type_label,
            $name_label,
            number_format($amount, 2) . ' ' . $currency,
            $date_label,
            $item['data']['notes']
        ]);
    }
    fclose($output);
    exit();

} elseif ($export_type == 'pdf') {
    require_once('TCPDF/tcpdf.php');

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('نظام إدارة الديون');
    $pdf->SetTitle('تقرير الديون والدفعات');

    $pdf->setRTL(true);
    $pdf->SetFont('aealarabiya', '', 14);
    $pdf->AddPage();

    $html = '
    <h1 style="text-align:center;">تقرير الديون والدفعات</h1>
    <table border="1" cellpadding="5" cellspacing="0" style="width:100%;">
        <thead>
            <tr style="background-color:#6c757d; color:#fff;">
                <th style="width:15%;">النوع</th>
                <th style="width:25%;">الاسم/الجهة</th>
                <th style="width:15%;">المبلغ</th>
                <th style="width:10%;">العملة</th>
                <th style="width:15%;">التاريخ</th>
                <th style="width:20%;">ملاحظات</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($search_results as $item) {
        $amount   = ($item['type'] == 'debt') ? $item['data']['remaining_amount'] : $item['data']['amount'];
        $currency = ($item['type'] == 'debt') ? $item['data']['currency'] : ($item['data']['debt_currency'] ?? $item['data']['currency']);
        $debt_type_label = ($item['type'] == 'debt') ? 'دين ' . $item['data']['type'] : 'دفعة ' . ($item['data']['debt_type'] ?? '');
        $name_label = $item['data']['debt_name'] ?? $item['data']['name'];
        $date_label = $item['data']['payment_date'] ?? $item['data']['date'];

        $html .= '
            <tr>
                <td>'. htmlspecialchars($debt_type_label) .'</td>
                <td>'. htmlspecialchars($name_label) .'</td>
                <td>'. number_format($amount, 2) .'</td>
                <td>'. htmlspecialchars($currency) .'</td>
                <td>'. htmlspecialchars($date_label) .'</td>
                <td>'. htmlspecialchars($item['data']['notes']) .'</td>
            </tr>';
    }

    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('تقرير_الديون.pdf', 'D');
}

$conn->close();
?>
