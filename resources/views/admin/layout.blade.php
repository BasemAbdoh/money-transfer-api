<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم الإدارية - @yield('title', 'الرئيسية')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; width: 260px; flex-shrink: 0; }
        .sidebar .nav-link { color: rgba(255,255,255,0.85); padding: 12px 18px; border-radius: 10px; margin: 3px 8px; transition: all 0.25s; display: flex; align-items: center; justify-content: space-between; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.22); color: white; font-weight: 600; transform: translateX(-4px); }
        .sidebar .nav-link i { width: 22px; transition: transform 0.25s ease; }
        .sidebar .nav-link:hover i { transform: scale(1.15); }
        .main-content { flex: 1; padding: 24px; min-width: 0; }
        .stat-card { border-radius: 16px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.08); transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease; animation: fadeInUp 0.5s ease; }
        .stat-card:hover { transform: translateY(-5px) scale(1.01); box-shadow: 0 12px 24px rgba(102, 126, 234, 0.16); }
        .table-card { border-radius: 16px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.08); transition: box-shadow 0.25s ease; }
        .table-card:hover { box-shadow: 0 8px 22px rgba(0,0,0,0.1); }
        .modal { z-index: 1065 !important; }
        .modal-backdrop { z-index: 1055 !important; }
        .animate-stagger-1 { animation-delay: 0.06s; }
        .animate-stagger-2 { animation-delay: 0.12s; }
        .animate-stagger-3 { animation-delay: 0.18s; }
        .animate-stagger-4 { animation-delay: 0.24s; }
        .pulse-badge { animation: pulseGlow 2s infinite; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: none; }
        }
        @keyframes pulseGlow {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.45); }
            50% { transform: scale(1.06); box-shadow: 0 0 0 6px rgba(220, 53, 69, 0); }
        }
        .badge-pending { background-color: #fff3cd; color: #856404; }
        .badge-paid { background-color: #d1e7dd; color: #0f5132; }
        .badge-cancelled { background-color: #f8d7da; color: #842029; }
        @media (max-width: 768px) { .sidebar { display: none; } }
    </style>
</head>
<body>
@php
    $navPendingApprovals = \App\Models\Transfer::where('approval_status', 'pending_approval')->count();
    $navPendingPhones = \App\Models\User::where('phone_status', 'pending')->whereNotNull('phone')->count();
    $navMaintenanceMode = (string) \App\Models\SystemSetting::getValue('system_maintenance_mode', '0') === '1';
    $navPendingRequests = \Illuminate\Support\Facades\Schema::hasTable('transfer_requests')
        ? \App\Models\TransferRequest::where('status', 'pending')->count()
        : 0;
@endphp
<div class="d-flex">
    <!-- الشريط الجانبي -->
    <div class="sidebar d-none d-md-flex flex-column p-3">
        <div class="text-center mb-4 mt-2">
            <h5 class="text-white fw-bold mb-1">🏦 لوحة التحكم</h5>
            <small class="text-white-50">مرحباً، {{ session('admin_username', 'مدير النظام') }}</small>
            @if($navMaintenanceMode)
                <div class="mt-2">
                    <span class="badge bg-danger pulse-badge"><i class="fas fa-tools me-1"></i> وضع الصيانة مفعل</span>
                </div>
            @endif
        </div>
        <nav class="nav flex-column gap-1">
            <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <span><i class="fas fa-tachometer-alt me-2"></i> الرئيسية</span>
            </a>
            <a href="{{ route('admin.users') }}" class="nav-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                <span><i class="fas fa-users me-2"></i> المستخدمون</span>
                @if($navPendingPhones > 0)
                    <span class="badge rounded-pill bg-warning text-dark pulse-badge" title="بانتظار تأكيد الهاتف">{{ $navPendingPhones }}</span>
                @endif
            </a>
            <a href="{{ route('admin.branches') }}" class="nav-link {{ request()->routeIs('admin.branches*') ? 'active' : '' }}">
                <span><i class="fas fa-building me-2"></i> الفروع</span>
            </a>
            <a href="{{ route('admin.transfers') }}" class="nav-link {{ request()->routeIs('admin.transfers*') ? 'active' : '' }}">
                <span><i class="fas fa-exchange-alt me-2"></i> الحوالات</span>
            </a>
            <a href="{{ route('admin.approvals') }}" class="nav-link {{ request()->routeIs('admin.approvals*') ? 'active' : '' }}">
                <span><i class="fas fa-check-double me-2"></i> الموافقات المعلقة</span>
                @if($navPendingApprovals > 0)
                    <span class="badge rounded-pill bg-danger pulse-badge">{{ $navPendingApprovals }}</span>
                @endif
            </a>
            <a href="{{ route('admin.financial') }}" class="nav-link {{ request()->routeIs('admin.financial*') ? 'active' : '' }}">
                <span><i class="fas fa-coins me-2"></i> المالية والعمولات</span>
            </a>
            <a href="{{ route('admin.settings') }}" class="nav-link {{ request()->routeIs('admin.settings*') || request()->routeIs('admin.companies*') || request()->routeIs('admin.destinations*') ? 'active' : '' }}">
                <span><i class="fas fa-sliders-h me-2"></i> إعدادات النظام والشركات</span>
            </a>
            <a href="{{ route('admin.security') }}" class="nav-link {{ request()->routeIs('admin.security*') || request()->routeIs('admin.blacklist*') || request()->routeIs('admin.transfer-requests*') ? 'active' : '' }}">
                <span><i class="fas fa-user-shield me-2"></i> الرقابة والأمان والطلبات</span>
                @if($navPendingRequests > 0)
                    <span class="badge rounded-pill bg-info text-dark pulse-badge" title="طلبات تعديل واسترداد معلقة">{{ $navPendingRequests }}</span>
                @endif
            </a>
            <a href="{{ route('admin.statements') }}" class="nav-link {{ request()->routeIs('admin.statements*') ? 'active' : '' }}">
                <span><i class="fas fa-file-invoice-dollar me-2"></i> كشوف الحساب والسندات</span>
            </a>
            <a href="{{ route('admin.daily-closing') }}" class="nav-link {{ request()->routeIs('admin.daily-closing*') ? 'active' : '' }}">
                <span><i class="fas fa-cash-register me-2"></i> الإقفال والجرد اليومي</span>
            </a>
            <a href="{{ route('admin.audit') }}" class="nav-link {{ request()->routeIs('admin.audit*') ? 'active' : '' }}">
                <span><i class="fas fa-history me-2"></i> سجلات التدقيق</span>
            </a>
            <hr class="border-white-50 my-3">
            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button type="submit" class="nav-link border-0 bg-transparent w-100 text-start">
                    <span><i class="fas fa-sign-out-alt me-2"></i> تسجيل الخروج</span>
                </button>
            </form>
        </nav>
    </div>
    <!-- المحتوى الرئيسي -->
    <div class="main-content">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>{{ $errors->first() }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @yield('content')
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    (function () {
        function detachModalsToBody() {
            document.querySelectorAll('.modal').forEach(function (modalEl) {
                if (modalEl.parentElement !== document.body) {
                    document.body.appendChild(modalEl);
                }
            });
        }
        detachModalsToBody();
        document.addEventListener('DOMContentLoaded', detachModalsToBody);
    })();
</script>
@yield('scripts')
</body>
</html>
