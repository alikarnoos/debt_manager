<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>نظام إدارة الديون</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: "Tajawal", sans-serif;
            background: linear-gradient(135deg, #e8f0ff, #fafafa);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            width: 95%;
            max-width: 900px;
            background: #fff;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            animation: fadeIn 0.7s ease;
            text-align: center;
        }

        h1 {
            font-size: 32px;
            margin-bottom: 30px;
            color: #333;
            font-weight: 700;
        }

        .buttons {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .btn {
            padding: 15px 25px;
            font-size: 18px;
            border-radius: 12px;
            text-decoration: none;
            color: #fff;
            width: 260px;
            text-align: center;
            transition: 0.3s;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
        }

        .btn:hover {
            transform: translateY(-4px);
            opacity: 0.9;
        }

        /* زر أحمر */
        .btn-debt-on {
            background: #ff4d4d;
        }

        /* زر أخضر */
        .btn-debt-for {
            background: #28c76f;
        }

        /* زر أزرق */
        .btn-add {
            background: #007bff;
        }

        /* زر رمادي */
        .btn-accounts {
            background: #6c757d;
        }

        /* زر بني */
        .btn-backup {
            background: #8B4513;
        }

        /* تأثير دخول الصفحة */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* تأثيرات موجية جميلة عند الضغط */
        .btn:active::after {
            content: "";
            position: absolute;
            width: 300%;
            height: 300%;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            left: 50%;
            top: 50%;
            transform: translate(-50%,-50%);
            animation: ripple 0.5s linear;
        }

        @keyframes ripple {
            from { opacity: 1; transform: translate(-50%,-50%) scale(0.1); }
            to { opacity: 0; transform: translate(-50%,-50%) scale(1.5); }
        }

        /* موبايل */
        @media (max-width: 600px) {
            h1 { font-size: 26px; }
            .btn { width: 100%; font-size: 17px; }
        }

    </style>
</head>

<body>

    <div class="container">
        <h1>نظام إدارة الديون</h1>

        <div class="buttons">
            <a href="debts_on_me.php" class="btn btn-debt-on">الديون عليَّ</a>
            <a href="debts_for_me.php" class="btn btn-debt-for">الديون لي</a>
            <a href="add_debt.php" class="btn btn-add">➕ إضافة دين جديد</a>
            <a href="accounts.php" class="btn btn-accounts">📊 الحسابات والتقارير</a>
            <a href="backup.php" class="btn btn-backup">💿 النسخ الاحتياطي والاستعادة</a>
        </div>
    </div>

</body>
</html>
