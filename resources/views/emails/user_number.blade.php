<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>رقمك المميز</title>
</head>
<body style="font-family: Arial, sans-serif; text-align: right; direction: rtl; padding: 20px; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
        <h2 style="color: #601B8C;">مرحباً {{ $username }}!</h2>
        <p>تم التحقق من بريدك الإلكتروني بنجاح.</p>
        <p>هذا هو رقم المستخدم المميز الخاص بك والذي ستستخدمه لتسجيل الدخول إلى التطبيق بدلاً من البريد الإلكتروني:</p>
        <div style="font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #601B8C; margin: 20px 0; text-align: center; background: #eee; padding: 10px; border-radius: 4px;">
            {{ $userNumber }}
        </div>
        <p>يرجى الاحتفاظ بهذا الرقم في مكان آمن.</p>
        <p style="color: #888; font-size: 12px; margin-top: 30px;">فريق شركة التحويلات</p>
    </div>
</body>
</html>
