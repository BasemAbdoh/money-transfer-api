@extends('admin.layout')

@section('title', 'إعدادات النظام والشركات والوجهات')

@section('content')
<style>
    .config-hero {
        background: linear-gradient(135deg, #1e293b 0%, #3b82f6 100%);
        border-radius: 20px;
        color: #fff;
        padding: 24px 28px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(30, 41, 59, 0.18);
        animation: fadeInUp 0.45s ease backwards;
    }
    .config-hero::after {
        content: '';
        position: absolute;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
        top: -60px;
        left: -60px;
        animation: floatBubble 6s ease-in-out infinite;
    }
    @keyframes floatBubble {
        0%, 100% { transform: translateY(0) scale(1); }
        50% { transform: translateY(12px) scale(1.08); }
    }
    .company-chip {
        transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,0.06);
    }
    .company-chip:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    }
    .prefix-badge {
        font-family: monospace;
        letter-spacing: 1px;
        font-weight: 700;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #fff;
        padding: 5px 12px;
        border-radius: 8px;
    }
    .maintenance-banner {
        border-radius: 16px;
        background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
        color: #fff;
        animation: pulseGlow 2.2s infinite;
    }
</style>

<div class="config-hero mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-sliders-h me-2"></i> إعدادات النظام والشركات والوجهات الديناميكية</h4>
        <p class="mb-0 text-white-50">تحكم فوري في سياسات الموافقة المزدوجة، حماية تسجيل الدخول، وضع الصيانة، وشبكة شركات ووجهات الحوالات بدون تعديل الكود.</p>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-white text-dark px-3 py-2 rounded-pill shadow-sm">
            <i class="fas fa-building text-primary me-1"></i> الشركات النشطة: <strong>{{ $companies->where('is_active', true)->count() }}</strong> / {{ $companies->count() }}
        </span>
        <span class="badge bg-white text-dark px-3 py-2 rounded-pill shadow-sm">
            <i class="fas fa-map-marker-alt text-success me-1"></i> الوجهات النشطة: <strong>{{ $destinations->where('is_active', true)->count() }}</strong> / {{ $destinations->count() }}
        </span>
    </div>
</div>

@if($settings['system_maintenance_mode'])
    <div class="maintenance-banner p-3 mb-4 d-flex align-items-center justify-content-between shadow">
        <div>
            <i class="fas fa-exclamation-triangle fa-lg me-2"></i>
            <strong>تنبيه هام: وضع الصيانة المؤقت مفعل حالياً!</strong>
            <span class="ms-2">جميع عمليات إرسال الحوالات الجديدة موقوفة مؤقتاً للمستخدمين العاديين والمشرفين.</span>
        </div>
        <span class="badge bg-light text-danger fw-bold px-3 py-2">إيقاف مؤقت للإرسال</span>
    </div>
@endif

<!-- القسم الأول: السياسات العامة والأمان -->
<div class="card table-card animate-stagger-1 mb-4">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-primary">
            <i class="fas fa-shield-alt me-2"></i> السياسات العامة للنظام والحماية
        </h6>
        <small class="text-muted">تُطبَّق التغييرات فوراً على تطبيق الجوال والويب</small>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.settings.save') }}" method="POST" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-3">
                <label class="form-label fw-semibold">سقف الموافقة المزدوجة للحوالة</label>
                <div class="input-group">
                    <input type="number" step="0.01" min="0" name="approval_threshold_amount"
                           class="form-control" value="{{ $settings['approval_threshold_amount'] }}" required>
                    <span class="input-group-text">ريال</span>
                </div>
                <small class="text-muted">الحوالات الأعلى من هذا المبلغ تتطلب اعتماد مشرف/مدير</small>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold">الحد الأقصى لمحاولات الدخول الفاشلة</label>
                <div class="input-group">
                    <input type="number" min="1" max="50" name="max_login_attempts"
                           class="form-control" value="{{ $settings['max_login_attempts'] }}" required>
                    <span class="input-group-text">محاولة</span>
                </div>
                <small class="text-muted">عدد المحاولات الخاطئة قبل الحظر المؤقت</small>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold">مدة الحظر المؤقت للحساب</label>
                <div class="input-group">
                    <input type="number" min="1" max="1440" name="lockout_minutes"
                           class="form-control" value="{{ $settings['lockout_minutes'] }}" required>
                    <span class="input-group-text">دقيقة</span>
                </div>
                <small class="text-muted">الفترة الزمنية لحظر الدخول بعد استنفاد المحاولات</small>
            </div>

            <div class="col-md-3">
                <div class="form-check form-switch p-3 rounded-3 border bg-light d-flex align-items-center justify-content-between">
                    <label class="form-check-label fw-bold text-danger mb-0" for="maintenanceSwitch">
                        <i class="fas fa-tools me-1"></i> وضع الصيانة المؤقت
                    </label>
                    <input class="form-check-input ms-0" style="width: 2.8em; height: 1.4em; cursor: pointer;"
                           type="checkbox" id="maintenanceSwitch" name="system_maintenance_mode" value="1"
                           {{ $settings['system_maintenance_mode'] ? 'checked' : '' }}>
                </div>
            </div>

            <div class="col-12 text-end mt-3">
                <button type="submit" class="btn btn-primary px-4 rounded-pill shadow-sm">
                    <i class="fas fa-save me-2"></i> حفظ السياسات العامة
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <!-- القسم الثاني: شركات التحويل والبادئات -->
    <div class="col-lg-6">
        <div class="card table-card animate-stagger-2 h-100">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-indigo">
                    <i class="fas fa-university me-2 text-primary"></i> شركات وشبكات التحويل ({{ $companies->count() }})
                </h6>
            </div>
            <div class="card-body">
                <!-- نموذج إضافة شركة جديدة -->
                <form action="{{ route('admin.companies.store') }}" method="POST" class="row g-2 mb-4 p-3 rounded-3 bg-light border">
                    @csrf
                    <div class="col-sm-6">
                        <input type="text" name="name" class="form-control" placeholder="اسم الشركة (مثال: بنك الكريمي)" required>
                    </div>
                    <div class="col-sm-3">
                        <input type="text" name="prefix" maxlength="10" class="form-control text-uppercase" placeholder="البادئة (KRM)" required>
                    </div>
                    <div class="col-sm-3 d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> إضافة شركة
                        </button>
                    </div>
                </form>

                <!-- قائمة الشركات -->
                <div class="d-flex flex-column gap-2">
                    @forelse($companies as $company)
                        <div class="company-chip p-3 bg-white d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-3">
                                <span class="prefix-badge">{{ $company->prefix }}</span>
                                <div>
                                    <div class="fw-bold">{{ $company->name }}</div>
                                    <small class="text-muted">مثال رقم الحوالة: <code>{{ $company->prefix }}-1234567</code></small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <form action="{{ route('admin.companies.toggle', $company->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $company->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }} rounded-pill px-3">
                                        <i class="fas {{ $company->is_active ? 'fa-check-circle' : 'fa-ban' }} me-1"></i>
                                        {{ $company->is_active ? 'نشطة' : 'معطلة' }}
                                    </button>
                                </form>
                                <form action="{{ route('admin.companies.delete', $company->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف شركة {{ $company->name }}؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="حذف">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">لا توجد شركات مضافة حالياً</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- القسم الثالث: الوجهات والمحافظات -->
    <div class="col-lg-6">
        <div class="card table-card animate-stagger-3 h-100">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">
                    <i class="fas fa-map-marked-alt me-2 text-success"></i> وجهات ومكاتب التحويل ({{ $destinations->count() }})
                </h6>
            </div>
            <div class="card-body">
                <!-- نموذج إضافة وجهة جديدة -->
                <form action="{{ route('admin.destinations.store') }}" method="POST" class="row g-2 mb-4 p-3 rounded-3 bg-light border">
                    @csrf
                    <div class="col-sm-5">
                        <input type="text" name="name" class="form-control" placeholder="اسم الوجهة (مثال: عدن - المنصورة)" required>
                    </div>
                    <div class="col-sm-4">
                        <input type="text" name="province" class="form-control" placeholder="المحافظة (عدن)">
                    </div>
                    <div class="col-sm-3 d-grid">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i> إضافة وجهة
                        </button>
                    </div>
                </form>

                <!-- قائمة الوجهات -->
                <div class="d-flex flex-column gap-2">
                    @forelse($destinations as $dest)
                        <div class="company-chip p-3 bg-white d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">{{ $dest->name }}</div>
                                    <small class="text-muted">المحافظة: {{ $dest->province ?: 'عام' }}</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <form action="{{ route('admin.destinations.toggle', $dest->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $dest->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }} rounded-pill px-3">
                                        <i class="fas {{ $dest->is_active ? 'fa-check-circle' : 'fa-ban' }} me-1"></i>
                                        {{ $dest->is_active ? 'متاحة' : 'متوقفة' }}
                                    </button>
                                </form>
                                <form action="{{ route('admin.destinations.delete', $dest->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف الوجهة {{ $dest->name }}؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="حذف">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">لا توجد وجهات مضافة حالياً</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

