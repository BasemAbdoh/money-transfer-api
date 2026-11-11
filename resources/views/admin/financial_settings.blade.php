@extends('admin.layout')
@section('title', 'المالية والعمولات وأسعار الصرف')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1">💰 النظام المالي والمحاسبي (العمولات، أسعار الصرف، وخزائن الفروع)</h4>
        <small class="text-muted">إدارة شرائح عمولات التحويل، تحديث أسعار صرف العملات اليومية، ومراقبة سيولة وسقوف الفروع</small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addRateModal">
            <i class="fas fa-dollar-sign me-1"></i> إضافة / تحديث عملة
        </button>
        <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addCommissionModal">
            <i class="fas fa-percentage me-1"></i> إضافة شريحة عمولة
        </button>
    </div>
</div>

<!-- ملخص الأرباح والعملات -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #f7971e, #ffd200)">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="fs-3 fw-bold">{{ number_format($totalCommissions, 2) }}</div>
                    <div class="small opacity-75">إجمالي أرباح العمولات المحصلة</div>
                </div>
                <i class="fas fa-hand-holding-usd fa-2x opacity-75"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #667eea, #764ba2)">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="fs-3 fw-bold">{{ $commissionRules->where('is_active', true)->count() }}</div>
                    <div class="small opacity-75">قواعد العمولات النشطة</div>
                </div>
                <i class="fas fa-sliders-h fa-2x opacity-75"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #11998e, #38ef7d)">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="fs-3 fw-bold">{{ number_format($branches->sum('balance'), 2) }}</div>
                    <div class="small opacity-75">إجمالي سيولة صناديق الفروع</div>
                </div>
                <i class="fas fa-vault fa-2x opacity-75"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- 1. أسعار الصرف اليومية -->
    <div class="col-lg-5">
        <div class="card table-card h-100">
            <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="fas fa-coins text-warning me-1"></i> أسعار الصرف اليومية (مقابل الريال اليمني)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>العملة</th>
                                <th>سعر الشراء</th>
                                <th>سعر البيع</th>
                                <th>تحديث سريع</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($exchangeRates as $rate)
                            <tr>
                                <td>
                                    <span class="badge bg-primary me-1">{{ $rate->currency_code }}</span>
                                    <strong>{{ $rate->currency_name }}</strong>
                                </td>
                                <td class="text-success fw-bold">{{ number_format($rate->buy_rate, 2) }}</td>
                                <td class="text-danger fw-bold">{{ number_format($rate->sell_rate, 2) }}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editRateModal{{ $rate->id }}">
                                        <i class="fas fa-edit"></i> تعديل
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- نوافذ تعديل أسعار الصرف (خارج الجدول تماماً) -->
    @foreach($exchangeRates as $rate)
    <div class="modal fade" id="editRateModal{{ $rate->id }}" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('admin.rates.upsert') }}" method="POST" class="modal-content">
                @csrf
                <input type="hidden" name="currency_code" value="{{ $rate->currency_code }}">
                <input type="hidden" name="currency_name" value="{{ $rate->currency_name }}">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">💱 تحديث سعر صرف: {{ $rate->currency_name }} ({{ $rate->currency_code }})</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">سعر الشراء</label>
                        <input type="number" step="0.01" min="0.01" name="buy_rate" value="{{ $rate->buy_rate }}" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">سعر البيع</label>
                        <input type="number" step="0.01" min="0.01" name="sell_rate" value="{{ $rate->sell_rate }}" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary fw-bold">حفظ السعر الجديد</button>
                </div>
            </form>
        </div>
    </div>
    @endforeach

    <!-- 2. قواعد وشرائح العمولات -->
    <div class="col-lg-7">
        <div class="card table-card h-100">
            <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="fas fa-percentage text-primary me-1"></i> شرائح وقواعد عمولات التحويل</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>الشركة / العملة</th>
                                <th>نطاق المبلغ</th>
                                <th>نوع وقيمة العمولة</th>
                                <th>الحالة</th>
                                <th class="text-center">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($commissionRules as $rule)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $rule->company_type ?? 'كل الشركات' }}</div>
                                    <small class="text-muted">{{ $rule->currency ?? 'كل العملات' }}</small>
                                </td>
                                <td>
                                    <small>من {{ number_format($rule->min_amount) }} إلى {{ $rule->max_amount ? number_format($rule->max_amount) : 'ما فوق' }}</small>
                                </td>
                                <td>
                                    @if($rule->fee_type === 'percentage')
                                        <span class="badge bg-info text-dark">{{ (float)$rule->fee_value }}% نسبة مئوية</span>
                                    @else
                                        <span class="badge bg-secondary">{{ number_format($rule->fee_value, 2) }} مبلغ ثابت</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $rule->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $rule->is_active ? 'مفعلة' : 'معطلة' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <form action="{{ route('admin.commissions.toggle', $rule->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="تفعيل/تعطيل">
                                            <i class="fas fa-power-off"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.commissions.delete', $rule->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">لا توجد قواعد عمولات مسجلة</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. خزائن الفروع وسقف الائتمان -->
    <div class="col-12">
        <div class="card table-card">
            <div class="card-header bg-white border-0 pt-3">
                <h6 class="fw-bold mb-0"><i class="fas fa-university text-success me-1"></i> صناديق سيولة الفروع وسقف الائتمان والتنبيهات</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>الفرع</th>
                                <th>المدينة</th>
                                <th>رصيد سيولة الصندوق الحالي</th>
                                <th>الحد الأدنى للتنبيه (Credit Limit)</th>
                                <th>حالة السيولة</th>
                                <th class="text-center">تغذية / تعديل الصندوق</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($branches as $branch)
                            @php
                                $isLow = $branch->credit_limit > 0 && $branch->balance < $branch->credit_limit;
                            @endphp
                            <tr class="{{ $isLow ? 'table-warning' : '' }}">
                                <td><strong>{{ $branch->name }}</strong></td>
                                <td>{{ $branch->city ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $isLow ? 'bg-danger' : 'bg-success' }} fs-6">
                                        {{ number_format($branch->balance, 2) }}
                                    </span>
                                </td>
                                <td>{{ number_format($branch->credit_limit, 2) }}</td>
                                <td>
                                    @if($isLow)
                                        <span class="badge bg-danger"><i class="fas fa-exclamation-circle me-1"></i> سيولة منخفضة - يحتاج تغذية</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success">سيولة آمنة</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#treasuryModal{{ $branch->id }}">
                                        <i class="fas fa-coins me-1"></i> تحديث الخزينة والسقف
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">لا توجد فروع مضافة بعد</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- نوافذ تعديل خزائن الفروع (خارج الجدول تماماً) -->
@foreach($branches as $branch)
<div class="modal fade" id="treasuryModal{{ $branch->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.branches.treasury', $branch->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold">🏦 خزينة وسقف ائتمان: {{ $branch->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">رصيد سيولة صندوق الفرع الحالي</label>
                    <input type="number" step="0.01" name="balance" value="{{ $branch->balance }}" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">الحد الأدنى للسيولة (يرسل تنبيهاً للمدير إذا قل الرصيد عنه)</label>
                    <input type="number" step="0.01" min="0" name="credit_limit" value="{{ $branch->credit_limit }}" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-success fw-bold">حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>
@endforeach

<!-- نافذة إضافة شريحة عمولة -->
<div class="modal fade" id="addCommissionModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.commissions.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold">➕ إضافة شريحة / قاعدة عمولة جديدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">الشركة (اختياري)</label>
                        <select name="company_type" class="form-select">
                            <option value="">كل الشركات</option>
                            @foreach($companies as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">العملة (اختياري)</label>
                        <select name="currency" class="form-select">
                            <option value="">كل العملات</option>
                            <option value="ريال يمني">ريال يمني (YER)</option>
                            <option value="ريال سعودي">ريال سعودي (SAR)</option>
                            <option value="دولار">دولار (USD)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">من مبلغ *</label>
                        <input type="number" step="0.01" min="0" name="min_amount" value="0" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">إلى مبلغ (اتركه فارغاً لبلا سقف)</label>
                        <input type="number" step="0.01" name="max_amount" class="form-control" placeholder="مثال: 100000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">نوع العمولة *</label>
                        <select name="fee_type" class="form-select" required>
                            <option value="percentage">نسبة مئوية (%)</option>
                            <option value="fixed">مبلغ ثابت مقطوع</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">قيمة العمولة *</label>
                        <input type="number" step="0.01" min="0" name="fee_value" value="1" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-primary fw-bold">إضافة القاعدة</button>
            </div>
        </form>
    </div>
</div>

<!-- نافذة إضافة / تحديث عملة -->
<div class="modal fade" id="addRateModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.rates.upsert') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold">💱 إضافة / تحديث عملة وسعر صرف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">رمز العملة (مثل SAR, USD, AED) *</label>
                    <input type="text" name="currency_code" class="form-control" placeholder="SAR" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">اسم العملة *</label>
                    <input type="text" name="currency_name" class="form-control" placeholder="ريال سعودي" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">سعر الشراء *</label>
                    <input type="number" step="0.01" min="0.01" name="buy_rate" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">سعر البيع *</label>
                    <input type="number" step="0.01" min="0.01" name="sell_rate" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-primary fw-bold">حفظ سعر الصرف</button>
            </div>
        </form>
    </div>
</div>
@endsection

