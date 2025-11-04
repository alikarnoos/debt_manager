<?php
$servername="localhost";
$username="root";
$password="";
$dbname="debt_manager";
$conn=new mysqli($servername,$username,$password,$dbname);
$conn->set_charset("utf8mb4");
if($conn->connect_error){die("فشل الاتصال: ".$conn->connect_error);}
if(!isset($_GET['debt_id'])){die("لم يتم تحديد الدين.");}
$debt_id=$_GET['debt_id'];
$stmt=$conn->prepare("SELECT * FROM debts WHERE id=?");
$stmt->bind_param("i",$debt_id);
$stmt->execute();
$result=$stmt->get_result();
if($result->num_rows==0){die("الدين غير موجود.");}
$debt=$result->fetch_assoc();
if(isset($_POST['save'])){
$amount=$_POST['amount'];
$payment_date=$_POST['payment_date'];
$currency=$_POST['currency'];
$notes=$_POST['notes'];
$attachment_names=[];
$allowed_ext=['jpg','jpeg','png','gif','pdf'];
if(isset($_FILES['attachment'])&&count($_FILES['attachment']['name'])>0){
$total_files=count($_FILES['attachment']['name']);
if(!is_dir('uploads')){mkdir('uploads',0777,true);}
for($i=0;$i<$total_files;$i++){
if($_FILES['attachment']['error'][$i]==0){
$file_name=$_FILES['attachment']['name'][$i];
$file_tmp=$_FILES['attachment']['tmp_name'][$i];
$ext=strtolower(pathinfo($file_name,PATHINFO_EXTENSION));
if(in_array($ext,$allowed_ext)){
$new_name=uniqid()."_".basename($file_name);
move_uploaded_file($file_tmp,"uploads/".$new_name);
$attachment_names[]=$new_name;
}else{
echo"<p style='color:red; text-align:center;'>نوع الملف ".htmlspecialchars($file_name)." غير مسموح به.</p>";
}
}
}
}
$attachments_json=json_encode($attachment_names);
if($amount>$debt['remaining_amount']){
echo"<p style='color:red; text-align:center;'>المبلغ أكبر من المتبقي!</p>";
}else{
$stmt2=$conn->prepare("INSERT INTO payments (debt_id,amount,payment_date,notes,currency,attachment) VALUES (?,?,?,?,?,?)");
$stmt2->bind_param("idssss",$debt_id,$amount,$payment_date,$notes,$currency,$attachments_json);
$stmt2->execute();
$new_remaining=$debt['remaining_amount']-$amount;
$stmt3=$conn->prepare("UPDATE debts SET remaining_amount=? WHERE id=?");
$stmt3->bind_param("di",$new_remaining,$debt_id);
$stmt3->execute();
echo"<p style='color:green; text-align:center;'>تم تسجيل الدفعة بنجاح!</p>";
$debt['remaining_amount']=$new_remaining;
$debt['currency']=$currency;
}
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تسجيل دفعة</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{font-family:'Tajawal',Arial,sans-serif;background-color:#f4f4f4;text-align:center;padding-top:40px;}
form{background:#fff;width:450px;margin:auto;padding:20px 30px;border-radius:8px;box-shadow:0 0 10px rgba(0,0,0,0.1);}
label{display:block;text-align:right;margin-top:15px;}
input,select,textarea,button{width:100%;padding:8px;margin-top:5px;border-radius:5px;border:1px solid #ccc;}
button{background-color:#28a745;color:white;border:none;margin-top:20px;font-size:16px;}
button:hover{background-color:#218838;cursor:pointer;}
a.back{display:inline-block;margin-top:15px;color:#007bff;text-decoration:none;}
h2{color:#333;}
.info{background:#e9ecef;padding:10px;border-radius:5px;margin-bottom:15px;}
</style>
</head>
<body>
<h2>تسجيل دفعة</h2>
<div class="info">
<p><strong>الجهة / الشخص:</strong> <?php echo $debt['name']; ?></p>
<p><strong>المبلغ المتبقي:</strong> <?php echo number_format($debt['remaining_amount'],2)." ".$debt['currency']; ?></p>
</div>
<form method="POST" enctype="multipart/form-data">
<label>المبلغ:</label>
<input type="number" step="0.01" name="amount" required>
<label>العملة:</label>
<select name="currency" required>
<option value="IQD" <?php if($debt['currency']=='IQD')echo'selected'; ?>>دينار عراقي (IQD)</option>
<option value="USD" <?php if($debt['currency']=='USD')echo'selected'; ?>>دولار أمريكي (USD)</option>
</select>
<label>التاريخ:</label>
<input type="date" name="payment_date" required>
<label>الملاحظات:</label>
<textarea name="notes" rows="3" placeholder="أي تفاصيل إضافية"></textarea>
<label>إرفاق ملفات (صور أو PDF):</label>
<input type="file" name="attachment[]" multiple>
<button type="submit" name="save">حفظ الدفعة</button>
</form>
<a href="index.php" class="back">⬅ الرجوع للصفحة الرئيسية</a>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
