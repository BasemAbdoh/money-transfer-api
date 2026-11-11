<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>
        @if($mode === 'transfer')
            إيصال حوالة مالية رقم {{ $transfer->trans_number }}
        @else
            سند {{ $transaction->type === 'credit' ? 'قبض / إيداع' : 'صرف / قيد مدين' }} رقم VCH-{{ $transaction->id }}
        @endif
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f1f5f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 30px 15px;
        }
        .voucher-sheet {
            max-width: 820px;
            margin: 0 auto;
            background: #fff;
            border-radius: 20px;
            border: 2px solid #1e293b;
            padding: 32px;
            position: relative;
            box-shadow: 0 15px 35px rgba(15, 23, 42, 0.12);
            animation: popIn 0.45s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes popIn {
            from { opacity: 0; transform: scale(0.96) translateY(15px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .voucher-header {
            border-bottom: 3px double #1e293b;
            padding-bottom: 18px;
            margin-bottom: 24px;
        }
        .voucher-badge {
            background: linear-gradient(135deg, #1e293b, #3b82f6);
            color: #fff;
            padding: 8px 22px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.05rem;
            display: inline-block;
        }
        .info-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 14px 18px;
        }
        .stamp-box {
            width: 140px;
            height: 90px;
            border: 2px dashed #94a3b8;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 0.85rem;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .voucher-sheet { box-shadow: none; border: 2px solid #000; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="text-center mb-3 no-print">
    <button onclick="window.print()" class="btn btn-primary px-4 rounded-pill fw-bold shadow-sm">
        <i class="fas fa-print me-2"></i> طباعة السند / الإيصال الآن
    </button>
    <button onclick="window.close()" class="btn btn-outline-secondary px-3 rounded-pill ms-2">
        إغلاق
    </button>
</div>

<div class="voucher-sheet">
    <div class="voucher-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1">🏦 شركة الحوالات والصرافة المالية</h4>
            <small class="text-muted">نظام إدارة الحوالات المالية الموحد - الفروع والوكلاء</small>
        </div>
        <div class="text-center">
            @if($mode === 'transfer')
                <span class="voucher-badge">إيصال إرسال حوالة مالية</span>
            @else
                <span class="voucher-badge">
                    {{ $transaction->type === 'credit' ? 'سند قبض / إيداع مالي' : 'سند صرف / قيد مدين' }}
                </span>
            @endif
        </div>
        <div class="text-end small">
            <div><strong>تاريخ الإصدار:</strong> {{ now()->format('Y-m-d H:i') }}</div>
            @if($mode === 'transfer')
                <div><strong>رقم الحوالة:</strong> <code>{{ $transfer->trans_number }}</code></div>
            @else
                <div><strong>رقم السند:</strong> <code>VCH-{{ $transaction->id }}</code></div>
            @endif
        </div>
    </div>

    @if($mode === 'transfer')
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="info-box h-100">
                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-2"><i class="fas fa-user-check me-1"></i> بيانات المرسل والفرع</h6>
                    <div class="mb-1"><strong>الموظف المرسل:</strong> {{ $transfer->created_by }}</div>
                    <div class="mb-1"><strong>الفرع المصدر:</strong> {{ $transfer->branch?->name ?? 'المركز الرئيسي' }}</div>
                    <div class="mb-1"><strong>رقم الحساب:</strong> <code>{{ $transfer->account_number }}</code></div>
                    <div><strong>تاريخ العملية:</strong> {{ $transfer->created_at?->format('Y-m-d H:i') }}</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-box h-100">
                    <h6 class="fw-bold text-success border-bottom pb-2 mb-2"><i class="fas fa-hand-holding-usd me-1"></i> بيانات المستفيد والوجهة</h6>
                    <div class="mb-1"><strong>اسم المستفيد:</strong> {{ $transfer->receiver_name }}</div>
                    <div class="mb-1"><strong>شركة التحويل:</strong> {{ $transfer->company_type }}</div>
                    <div class="mb-1"><strong>وجهة الصرف:</strong> {{ $transfer->destination }}</div>
                    <div><strong>حالة الحوالة:</strong> {{ $transfer->status === 'paid' ? 'مدفوعة' : ($transfer->status === 'cancelled' ? 'ملغاة' : 'قيد الانتظار') }}</div>
                </div>
            </div>
        </div>

        <div class="info-box mb-4 bg-light">
            <div class="row text-center align-items-center">
                <div class="col-md-4 border-end">
                    <small class="text-muted d-block">مبلغ الحوالة الأساسي</small>
                    <h4 class="fw-bold text-primary mb-0">{{ number_format($transfer->amount, 2) }} {{ $transfer->currency }}</h4>
                </div>
                <div class="col-md-4 border-end">
                    <small class="text-muted d-block">عمولة التحويل</small>
                    <h5 class="fw-bold text-warning mb-0">{{ number_format($transfer->commission_amount ?? 0, 2) }} {{ $transfer->currency }}</h5>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">صافي المبلغ</small>
                    <h4 class="fw-bold text-success mb-0">{{ number_format($transfer->net_amount ?? $transfer->amount, 2) }} {{ $transfer->currency }}</h4>
                </div>
            </div>
        </div>
    @else
        <div class="info-box mb-4">
            <div class="row g-3">
                <div class="col-md-6"><strong>صاحب الحساب / الموظف:</strong> {{ $transaction->user?->username }} ({{ $transaction->user?->user_number }})</div>
                <div class="col-md-6"><strong>الفرع:</strong> {{ $transaction->user?->branch?->name ?? 'المركز الرئيسي' }}</div>
                <div class="col-md-6"><strong>نوع القيد:</strong> {{ $transaction->type === 'credit' ? 'دائن (إيداع / قبض)' : 'مدين (سحب / صرف)' }}</div>
                <div class="col-md-6"><strong>المرجع المرتبط:</strong> <code>{{ $transaction->trans_number ?: 'قيد إداري مباشر' }}</code></div>
                <div class="col-12"><strong>البيان والتفاصيل:</strong> {{ $transaction->description }}</div>
            </div>
        </div>

        <div class="info-box mb-4 bg-light">
            <div class="row text-center">
                <div class="col-md-4 border-end">
                    <small class="text-muted d-block">الرصيد قبل الحركة</small>
                    <h5 class="fw-bold mb-0">{{ number_format($transaction->balance_before, 2) }}</h5>
                </div>
                <div class="col-md-4 border-end">
                    <small class="text-muted d-block">مبلغ السند</small>
                    <h4 class="fw-bold {{ $transaction->type === 'credit' ? 'text-success' : 'text-danger' }} mb-0">
                        {{ number_format($transaction->amount, 2) }}
                    </h4>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">الرصيد بعد الحركة</small>
                    <h5 class="fw-bold text-primary mb-0">{{ number_format($transaction->balance_after, 2) }}</h5>
                </div>
            </div>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
        <div class="text-center">
            <div class="fw-bold mb-4">توقيع الموظف المختص</div>
            <div class="text-muted">.............................</div>
        </div>
        <div class="stamp-box">
            ختم الشركة الرسمي
        </div>
        <div class="text-center">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=95x95&data={{ urlencode($mode === 'transfer' ? $transfer->trans_number : 'VCH-'.$transaction->id) }}"
                 alt="QR Code" width="90" height="90" class="border p-1 rounded">
            <div class="small text-muted mt-1">امسح الرمز للتحقق</div>
        </div>
    </div>
</div>

</body>
</html>

