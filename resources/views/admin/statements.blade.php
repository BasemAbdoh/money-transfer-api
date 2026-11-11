@extends('admin.layout')

@section('title', 'كشف الحساب التفصيلي والسندات')

@section('content')
<style>
    .statement-hero {
        background: linear-gradient(135deg, #064e3b 0%, #047857 50%, #1e40af 100%);
        border-radius: 22px;
        color: #fff;
        padding: 26px 30px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 12px 30px rgba(6, 78, 59, 0.2);
        animation: fadeInUp 0.45s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .statement-hero::before {
        content: '';
        position: absolute;
        left: -50px;
        bottom: -50px;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.07);
        border-radius: 50%;
        animation: floatBubble 5.5s ease-in-out infinite;
    }
    @keyframes floatBubble {
        0%, 100% { transform: translateY(0) scale(1); }
        50% { transform: translateY(-14px) scale(1.07); }
    }
    .summary-kpi {
        border-radius: 16px;
        border: 1px solid rgba(0,0,0,0.06);
        background: #fff;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .summary-kpi:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.08);
    }
    .debit-cell { color: #dc2626; font-weight: 700; }
    .credit-cell { color: #059669; font-weight: 700; }
    @media print {
        .no-print, .sidebar { display: none !important; }
        .main-content { padding: 0 !important; }
    }
</style>

<div class="statement-hero mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3 no-print">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-file-invoice-dollar me-2"></i> كشف الحساب التفصيلي (مدين / دائن)</h4>
        <p class="mb-0 text-white-50">استعراض الرصيد الافتتاحي، التسلسل الزمني للحركات، الرصيد التراكمي، وطباعة سندات القبض والصرف الرسمية.</p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-light fw-bold rounded-pill px-3 shadow-sm">
            <i class="fas fa-print text-primary me-1"></i> طباعة الكشف
        </button>
        <a href="{{ route('admin.statements', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-warning fw-bold rounded-pill px-3 shadow-sm">
            <i class="fas fa-file-csv me-1"></i> تصدير CSV / Excel
        </a>
    </div>
</div>

<!-- فلاتر البحث -->
<div class="card table-card animate-stagger-1 mb-4 no-print">
    <div class="card-body">
        <form action="{{ route('admin.statements') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">الموظف / المستخدم</label>
                <select name="user_id" class="form-select">
                    <option value="">-- جميع المستخدمين --</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ (string)$userId === (string)$u->id ? 'selected' : '' }}>
                            {{ $u->username }} ({{ $u->user_number }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">أو حسب الفرع</label>
                <select name="branch_id" class="form-select">
                    <option value="">-- جميع الفروع --</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ (string)$branchId === (string)$b->id ? 'selected' : '' }}>
                            {{ $b->name }} ({{ $b->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">من تاريخ</label>
                <input type="date" name="start_date" value="{{ $startDate }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">إلى تاريخ</label>
                <input type="date" name="end_date" value="{{ $endDate }}" class="form-control">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i> عرض الكشف
                </button>
                <a href="{{ route('admin.statements') }}" class="btn btn-outline-secondary"><i class="fas fa-undo"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- بطاقات ملخص الكشف -->
<div class="row g-3 mb-4 animate-stagger-2">
    <div class="col-md-3">
        <div class="summary-kpi p-3 border-start border-4 border-secondary">
            <small class="text-muted d-block">الرصيد الافتتاحي للفترة</small>
            <h4 class="fw-bold mb-0 mt-1 text-secondary">{{ number_format($openingBalance, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-kpi p-3 border-start border-4 border-danger">
            <small class="text-muted d-block">إجمالي المدين (Debit - مسحوبات/إرسال)</small>
            <h4 class="fw-bold mb-0 mt-1 text-danger">- {{ number_format($totalDebit, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-kpi p-3 border-start border-4 border-success">
            <small class="text-muted d-block">إجمالي الدائن (Credit - إيداعات/صرف)</small>
            <h4 class="fw-bold mb-0 mt-1 text-success">+ {{ number_format($totalCredit, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-kpi p-3 border-start border-4 border-primary">
            <small class="text-muted d-block">الرصيد الختامي (Closing Balance)</small>
            <h4 class="fw-bold mb-0 mt-1 text-primary">{{ number_format($closingBalance, 2) }}</h4>
        </div>
    </div>
</div>

<!-- جدول الحركات المحاسبية -->
<div class="card table-card animate-stagger-3">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">
            <i class="fas fa-list-ol text-primary me-2"></i> جدول حركات كشف الحساب ({{ $transactions->count() }} حركة)
        </h6>
        <small class="text-muted">الفترة: {{ $startDate }} إلى {{ $endDate }}</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th># السند</th>
                        <th>التاريخ والوقت</th>
                        <th>الموظف</th>
                        <th>المرجع / الحوالة</th>
                        <th>البيان والتفاصيل</th>
                        <th class="text-end">مدين (Debit)</th>
                        <th class="text-end">دائن (Credit)</th>
                        <th class="text-end">الرصيد التراكمي</th>
                        <th class="text-center no-print">سند رسمي</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $tx)
                        <tr>
                            <td><code>VCH-{{ $tx->id }}</code></td>
                            <td class="small text-muted">{{ $tx->created_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                <span class="fw-semibold">{{ $tx->user?->username ?? '-' }}</span>
                                <small class="text-muted d-block">{{ $tx->user?->user_number }}</small>
                            </td>
                            <td><code>{{ $tx->trans_number ?: 'سند قيد' }}</code></td>
                            <td>{{ $tx->description }}</td>
                            <td class="text-end debit-cell">
                                {{ $tx->type === 'debit' ? number_format($tx->amount, 2) : '-' }}
                            </td>
                            <td class="text-end credit-cell">
                                {{ $tx->type === 'credit' ? number_format($tx->amount, 2) : '-' }}
                            </td>
                            <td class="text-end fw-bold">{{ number_format($tx->balance_after, 2) }}</td>
                            <td class="text-center no-print">
                                <a href="{{ route('admin.transactions.voucher', $tx->id) }}" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill px-3" title="طباعة سند قبض/صرف">
                                    <i class="fas fa-print me-1"></i> سند
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">لا توجد حركات مالية مسجلة خلال هذه الفترة</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

