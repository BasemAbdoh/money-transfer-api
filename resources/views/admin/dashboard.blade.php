@extends('admin.layout')
@section('title', 'الرئيسية')
@section('content')
<h4 class="fw-bold mb-4">📊 لوحة التحكم الرئيسية</h4>

<!-- بطاقات الملخص -->
@if(isset($lowLiquidityBranches) && $lowLiquidityBranches->count() > 0)
<div class="alert alert-warning d-flex justify-content-between align-items-center shadow-sm mb-4">
    <div>
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>تنبيه سيولة فروع منخفضة:</strong>
        الفروع التالية انخفض رصيدها عن الحد الأدنى للائتمان:
        @foreach($lowLiquidityBranches as $lb)
            <span class="badge bg-danger mx-1">{{ $lb->name }} (الرصيد: {{ number_format($lb->balance, 2) }} / الحد: {{ number_format($lb->credit_limit, 2) }})</span>
        @endforeach
    </div>
    <a href="{{ route('admin.financial') }}" class="btn btn-sm btn-dark">تغذية الفروع</a>
</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-2">
        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #667eea, #764ba2)">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fs-4 fw-bold">{{ $totalUsers }}</div>
                        <div class="small opacity-75">المستخدمون</div>
                    </div>
                    <i class="fas fa-users fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #f093fb, #f5576c)">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fs-4 fw-bold">{{ $totalTransfers }}</div>
                        <div class="small opacity-75">كل الحوالات</div>
                    </div>
                    <i class="fas fa-exchange-alt fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #4facfe, #00f2fe)">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fs-4 fw-bold">{{ $pendingCount }}</div>
                        <div class="small opacity-75">قيد الانتظار</div>
                    </div>
                    <i class="fas fa-hourglass-half fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #43e97b, #38f9d7)">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fs-4 fw-bold">{{ $paidCount }}</div>
                        <div class="small opacity-75">الحوالات المكتملة</div>
                    </div>
                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #f7971e, #ffd200)">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fs-4 fw-bold">{{ number_format($totalCommissions ?? 0, 2) }}</div>
                        <div class="small opacity-75">إجمالي أرباح العمولات</div>
                    </div>
                    <i class="fas fa-hand-holding-usd fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- رسم بياني -->
    <div class="col-md-7">
        <div class="card table-card">
            <div class="card-header bg-white border-0 fw-bold pt-3">📈 الحوالات حسب الشركة</div>
            <div class="card-body">
                <canvas id="companyChart" height="150"></canvas>
            </div>
        </div>
    </div>
    <!-- آخر الحوالات -->
    <div class="col-md-5">
        <div class="card table-card">
            <div class="card-header bg-white border-0 fw-bold pt-3">🕐 آخر الحوالات</div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @foreach($recentTransfers as $transfer)
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <small class="fw-bold">{{ $transfer->trans_number }}</small>
                            <span class="badge {{ $transfer->status === 'paid' ? 'badge-paid' : 'badge-pending' }}">
                                {{ $transfer->status === 'paid' ? '✅ مدفوع' : '⏳ انتظار' }}
                            </span>
                        </div>
                        <small class="text-muted">{{ $transfer->receiver_name }} - {{ $transfer->amount }} {{ $transfer->currency }}</small>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script>
const ctx = document.getElementById('companyChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: {!! json_encode($byCompany->pluck('company_type')) !!},
        datasets: [{
            label: 'عدد الحوالات',
            data: {!! json_encode($byCompany->pluck('count')) !!},
            backgroundColor: ['#667eea','#f093fb','#4facfe','#43e97b','#f5576c','#00f2fe','#38f9d7','#764ba2'],
            borderRadius: 8,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});
</script>
@endsection
