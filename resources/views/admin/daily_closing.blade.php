@extends('admin.layout')

@section('title', 'تقرير الإقفال والجرد اليومي')

@section('content')
<style>
    .closing-hero {
        background: linear-gradient(135deg, #312e81 0%, #4338ca 50%, #0f766e 100%);
        border-radius: 22px;
        color: #fff;
        padding: 26px 30px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 12px 30px rgba(49, 46, 129, 0.22);
        animation: fadeInUp 0.45s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .closing-card {
        border-radius: 16px;
        background: #fff;
        border: 1px solid rgba(0,0,0,0.06);
        transition: all 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .closing-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.08);
    }
    @media print {
        .no-print, .sidebar { display: none !important; }
        .main-content { padding: 0 !important; }
    }
</style>

<div class="closing-hero mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-cash-register me-2"></i> تقرير الإقفال والجرد المحاسبي اليومي</h4>
        <p class="mb-0 text-white-50">ملخص شامل لحركة الصناديق اليومية، الحوالات الصادرة والمصروفة، وإجمالي العمولات لكل فرع وموظف.</p>
    </div>
    <form action="{{ route('admin.daily-closing') }}" method="GET" class="d-flex align-items-center gap-2 no-print">
        <input type="date" name="date" value="{{ $date }}" class="form-control rounded-pill px-3">
        <button type="submit" class="btn btn-warning fw-bold rounded-pill px-3">عرض الجرد</button>
        <button type="button" onclick="window.print()" class="btn btn-light fw-bold rounded-pill px-3">
            <i class="fas fa-print"></i>
        </button>
    </form>
</div>

<!-- ملخص الإقفال العام لليوم -->
<div class="row g-3 mb-4 animate-stagger-1">
    <div class="col-md-3">
        <div class="closing-card p-3 border-top border-4 border-primary">
            <small class="text-muted d-block">الحوالات المرسلة ({{ $summary['sent_count'] }})</small>
            <h4 class="fw-bold text-primary mt-1 mb-0">{{ number_format($summary['sent_amount'], 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="closing-card p-3 border-top border-4 border-success">
            <small class="text-muted d-block">الحوالات المصروفة ({{ $summary['paid_count'] }})</small>
            <h4 class="fw-bold text-success mt-1 mb-0">{{ number_format($summary['paid_amount'], 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="closing-card p-3 border-top border-4 border-warning">
            <small class="text-muted d-block">إجمالي أرباح العمولات لليوم</small>
            <h4 class="fw-bold text-warning mt-1 mb-0">{{ number_format($summary['commissions'], 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="closing-card p-3 border-top border-4 border-info">
            <small class="text-muted d-block">صافي حركة السيولة اليومية</small>
            <h4 class="fw-bold {{ $summary['net_movement'] >= 0 ? 'text-dark' : 'text-danger' }} mt-1 mb-0">
                {{ number_format($summary['net_movement'], 2) }}
            </h4>
        </div>
    </div>
</div>

<!-- جرد الفروع -->
<div class="card table-card animate-stagger-2 mb-4">
    <div class="card-header bg-white py-3 border-0">
        <h6 class="fw-bold mb-0"><i class="fas fa-building text-primary me-2"></i> جرد وإقفال الفروع ليوم ({{ $date }})</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>الفرع</th>
                        <th>عدد الصادر</th>
                        <th>إجمالي الصادر</th>
                        <th>عدد المصروف</th>
                        <th>إجمالي المصروف</th>
                        <th>العمولات المحققة</th>
                        <th>صافي الحركة</th>
                        <th>رصيد خزينة الفرع الحالي</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($branchClosing as $row)
                        <tr>
                            <td class="fw-bold">{{ $row['branch']->name }} <small class="text-muted">({{ $row['branch']->code }})</small></td>
                            <td>{{ $row['sent_count'] }}</td>
                            <td class="text-primary fw-semibold">{{ number_format($row['sent_amount'], 2) }}</td>
                            <td>{{ $row['paid_count'] }}</td>
                            <td class="text-success fw-semibold">{{ number_format($row['paid_amount'], 2) }}</td>
                            <td class="text-warning fw-bold">{{ number_format($row['commissions'], 2) }}</td>
                            <td class="fw-bold">{{ number_format($row['net_movement'], 2) }}</td>
                            <td class="fw-bold text-dark">{{ number_format($row['branch']->balance, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">لا توجد فروع مسجلة</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- جرد الموظفين -->
<div class="card table-card animate-stagger-3">
    <div class="card-header bg-white py-3 border-0">
        <h6 class="fw-bold mb-0"><i class="fas fa-user-tie text-success me-2"></i> جرد وإقفال الصرافين والمستخدمين ليوم ({{ $date }})</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>الموظف</th>
                        <th>الفرع</th>
                        <th>عدد الصادر</th>
                        <th>مبلغ الصادر</th>
                        <th>عدد المصروف</th>
                        <th>مبلغ المصروف</th>
                        <th>العمولات</th>
                        <th>الرصيد الحالي</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($userClosing as $uRow)
                        <tr>
                            <td>
                                <span class="fw-bold">{{ $uRow['user']->username }}</span>
                                <small class="text-muted d-block">{{ $uRow['user']->user_number }}</small>
                            </td>
                            <td>{{ $uRow['user']->branch?->name ?? 'الرئيسي' }}</td>
                            <td>{{ $uRow['sent_count'] }}</td>
                            <td class="text-primary fw-semibold">{{ number_format($uRow['sent_amount'], 2) }}</td>
                            <td>{{ $uRow['paid_count'] }}</td>
                            <td class="text-success fw-semibold">{{ number_format($uRow['paid_amount'], 2) }}</td>
                            <td class="text-warning fw-bold">{{ number_format($uRow['commissions'], 2) }}</td>
                            <td class="fw-bold">{{ number_format($uRow['user']->balance, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

