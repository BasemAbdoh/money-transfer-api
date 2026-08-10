<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تأكيد البريد الإلكتروني</title>
</head>
<body style="font-family: Arial, sans-serif; text-align: right; direction: rtl; padding: 20px; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
        <h2 style="color: #601B8C;">مرحباً بك في تطبيق شركة التحويلات!</h2>
        <p>لقد قمت بإنشاء حساب جديد، ونحن بحاجة إلى التأكد من أن هذا البريد الإلكتروني يعود لك.</p>
        <p>رمز التأكيد الخاص بك هو:</p>
        <div style="font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #333; margin: 20px 0; text-align: center; background: #eee; padding: 10px; border-radius: 4px;">
            {{ $otp }}
        </div>
        <p>يرجى إدخال هذا الرمز في التطبيق لإكمال عملية التحقق.</p>
        <p style="color: #888; font-size: 12px; margin-top: 30px;">إذا لم تقم بالتسجيل في تطبيقنا، يرجى تجاهل هذه الرسالة.</p>
    </div>
</body>
</html>
