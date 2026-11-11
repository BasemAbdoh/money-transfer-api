@extends('admin.layout')

@section('title', 'مركز الرقابة والأمان وطلبات التعديل والاسترداد')

@section('content')
<style>
    .security-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #7f1d1d 100%);
        border-radius: 22px;
        color: #fff;
        padding: 26px 30px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.25);
        animation: fadeInUp 0.45s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .radar-ring {
        position: absolute;
        left: -40px;
        top: -40px;
        width: 180px;
        height: 180px;
        border: 2px solid rgba(239, 68, 68, 0.35);
        border-radius: 50%;
        animation: radarPulse 3.2s ease-out infinite;
        pointer-events: none;
    }
    .radar-ring-2 {
        animation-delay: 1.6s;
    }
    @keyframes radarPulse {
        0% { transform: scale(0.4); opacity: 0.9; }
        100% { transform: scale(1.8); opacity: 0; }
    }
    .sec-card {
        border-radius: 16px;
        border: 1px solid rgba(0,0,0,0.06);
        transition: all 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
        background: #fff;
    }
    .sec-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }
    .blocked-card {
        border-right: 4px solid #dc3545;
        background: linear-gradient(90deg, #fff 0%, #fff5f5 100%);
    }
    .request-card-pending {
        border-right: 4px solid #f59e0b;
        background: linear-gradient(90deg, #fff 0%, #fffbeb 100%);
    }
    .live-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: #10b981;
        display: inline-block;
        animation: liveBlink 1.5s infinite;
    }
    @keyframes liveBlink {
        0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); }
        50% { opacity: 0.5; box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    }
</style>

<!-- ترويسة مركز الرقابة والأمان -->
<div class="security-hero mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div class="radar-ring"></div>
    <div class="radar-ring radar-ring-2"></div>
    <div class="position-relative">
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="live-dot"></span>
            <small class="text-info fw-bold">المراقبة الأمنية الفورية نشطة</small>
        </div>
        <h4 class="fw-bold mb-1"><i class="fas fa-user-shield me-2"></i> مركز الرقابة والأمان وطلبات التعديل والاسترداد</h4>
        <p class="mb-0 text-white-50">إدارة الحسابات المحظورة، الجلسات النشطة، القائمة السوداء (Blacklist)، ومراجعة طلبات تعديل أو استرداد الحوالات.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 position-relative">
        <span class="badge bg-danger px-3 py-2 rounded-pill shadow-sm">
            <i class="fas fa-user-lock me-1"></i> محظورون حالياً: <strong>{{ $blockedIdentifiers->count() }}</strong>
        </span>
        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm">
            <i class="fas fa-file-signature me-1"></i> طلبات معلقة: <strong>{{ $transferRequests->where('status', 'pending')->count() }}</strong>
        </span>
        <span class="badge bg-dark border border-secondary px-3 py-2 rounded-pill shadow-sm">
            <i class="fas fa-ban text-danger me-1"></i> القائمة السوداء: <strong>{{ $blacklist->where('is_active', true)->count() }}</strong>
        </span>
    </div>
</div>

<!-- القسم الأول: طلبات تعديل المستلم وإلغاء/استرداد الحوالات -->
<div class="card table-card animate-stagger-1 mb-4">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fas fa-exchange-alt text-warning me-2"></i> طلبات تعديل المستلم واسترداد الحوالات ({{ $transferRequests->count() }})
        </h6>
        <span class="badge bg-warning text-dark">بانتظار المراجعة: {{ $transferRequests->where('status', 'pending')->count() }}</span>
    </div>
    <div class="card-body">
        @if($transferRequests->isEmpty())
            <div class="text-center text-muted py-4">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <div>لا توجد طلبات تعديل أو استرداد حالياً</div>
            </div>
        @else
            <div class="row g-3">
                @foreach($transferRequests as $req)
                    <div class="col-md-6">
                        <div class="sec-card p-3 {{ $req->status === 'pending' ? 'request-card-pending' : '' }}">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge {{ $req->request_type === 'edit_receiver' ? 'bg-primary' : 'bg-danger' }} mb-1">
                                        {{ $req->request_type === 'edit_receiver' ? '✏️ طلب تعديل اسم المستلم' : '🔄 طلب إلغاء واسترداد رصيد' }}
                                    </span>
                                    <div class="fw-bold">
                                        حوالة رقم: <code>{{ $req->transfer?->trans_number ?? '#' }}</code>
                                        <span class="text-success ms-2">({{ number_format($req->transfer?->amount ?? 0, 2) }} {{ $req->transfer?->currency }})</span>
                                    </div>
                                </div>
                                @if($req->status === 'pending')
                                    <span class="badge bg-warning text-dark pulse-badge">قيد المراجعة</span>
                                @elseif($req->status === 'approved')
                                    <span class="badge bg-success">معتمد</span>
                                @else
                                    <span class="badge bg-secondary">مرفوض</span>
                                @endif
                            </div>

                            <div class="small text-muted mb-2">
                                <div><strong>مقدم الطلب:</strong> {{ $req->user?->username }} ({{ $req->user?->user_number }})</div>
                                <div><strong>المستلم الحالي:</strong> {{ $req->transfer?->receiver_name }}</div>
                                @if($req->request_type === 'edit_receiver')
                                    <div class="text-primary fw-bold"><strong>المستلم الجديد المطلوب:</strong> {{ $req->new_receiver_name }}</div>
                                @endif
                                <div><strong>السبب:</strong> {{ $req->reason }}</div>
                                <div><strong>التاريخ:</strong> {{ $req->created_at?->format('Y-m-d H:i') }}</div>
                            </div>

                            @if($req->status === 'pending')
                                <div class="d-flex gap-2 justify-content-end mt-2 pt-2 border-top">
                                    <form action="{{ route('admin.transfer-requests.review', $req->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-sm btn-success rounded-pill px-3">
                                            <i class="fas fa-check me-1"></i> اعتماد وتنفيذ الأثر المالي
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.transfer-requests.review', $req->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                            <i class="fas fa-times me-1"></i> رفض
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="small text-muted border-top pt-2 mt-2">
                                    تمت المراجعة بواسطة: <strong>{{ $req->reviewer?->username ?? 'الإدارة' }}</strong>
                                    في {{ $req->reviewed_at?->format('Y-m-d H:i') }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- القسم الثاني: الحسابات المحظورة حالياً + فك الحظر الفوري -->
    <div class="col-lg-6">
        <div class="card table-card animate-stagger-2 h-100">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-danger">
                    <i class="fas fa-user-lock me-2"></i> الحسابات المحظورة مؤقتاً ({{ $blockedIdentifiers->count() }})
                </h6>
                <small class="text-muted">السياسة: {{ $maxAttempts }} محاولات / {{ $lockoutMinutes }} دقيقة</small>
            </div>
            <div class="card-body">
                @if($blockedIdentifiers->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                        <div>لا توجد حسابات محظورة بسبب محاولات الدخول حالياً</div>
                    </div>
                @else
                    <div class="d-flex flex-column gap-2">
                        @foreach($blockedIdentifiers as $item)
                            <div class="sec-card blocked-card p-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold text-danger">
                                        رقم المستخدم: <code>{{ $item['identifier'] }}</code>
                                        @if($item['user'])
                                            <span class="text-dark ms-1">({{ $item['user']->username }})</span>
                                        @endif
                                    </div>
                                    <small class="text-muted d-block">
                                        محاولات فاشلة: <strong>{{ $item['failed_count'] }}</strong> | آخر IP: <code>{{ $item['last_ip'] }}</code>
                                    </small>
                                </div>
                                <form action="{{ route('admin.security.unblock') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="identifier" value="{{ $item['identifier'] }}">
                                    <button type="submit" class="btn btn-sm btn-danger rounded-pill px-3 shadow-sm">
                                        <i class="fas fa-unlock me-1"></i> فك الحظر الفوري
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif

                <hr class="my-4">
                <h6 class="fw-bold mb-3"><i class="fas fa-laptop-house text-primary me-2"></i> الجلسات النشطة حالياً (Sanctum Tokens)</h6>
                <div class="d-flex flex-column gap-2">
                    @forelse($activeSessions as $u)
                        <div class="sec-card p-2 px-3 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold">{{ $u->username }}</span>
                                <span class="badge bg-light text-dark border ms-1">{{ $u->role }}</span>
                                <small class="text-muted d-block">رقم الحساب: <code>{{ $u->user_number }}</code> • أجهزة متصلة: <strong>{{ $u->tokens_count }}</strong></small>
                            </div>
                            <form action="{{ route('admin.security.revoke', $u->id) }}" method="POST" onsubmit="return confirm('إخراج {{ $u->username }} من جميع الأجهزة المتصلة فوراً؟');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                    <i class="fas fa-power-off me-1"></i> إنهاء الجلسات
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="text-center text-muted py-2">لا توجد جلسات أجهزة نشطة حالياً</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- القسم الثالث: القائمة السوداء (Blacklist) -->
    <div class="col-lg-6">
        <div class="card table-card animate-stagger-3 h-100">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">
                    <i class="fas fa-ban text-danger me-2"></i> قائمة الحظر والمراقبة السوداء ({{ $blacklist->count() }})
                </h6>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.blacklist.store') }}" method="POST" class="row g-2 mb-3 p-3 rounded-3 bg-light border">
                    @csrf
                    <div class="col-sm-3">
                        <select name="type" class="form-select" required>
                            <option value="name">اسم مستلم</option>
                            <option value="account_number">رقم حساب</option>
                            <option value="phone">رقم جوال</option>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <input type="text" name="value" class="form-control" placeholder="الاسم أو الرقم المحظور" required>
                    </div>
                    <div class="col-sm-3">
                        <input type="text" name="reason" class="form-control" placeholder="سبب الحظر">
                    </div>
                    <div class="col-sm-2 d-grid">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-plus"></i> حظر
                        </button>
                    </div>
                </form>

                <div class="d-flex flex-column gap-2" style="max-height: 360px; overflow-y: auto;">
                    @forelse($blacklist as $b)
                        <div class="sec-card p-3 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-dark">
                                        {{ $b->type === 'name' ? 'اسم' : ($b->type === 'phone' ? 'هاتف' : 'حساب') }}
                                    </span>
                                    <span class="fw-bold text-danger">{{ $b->value }}</span>
                                </div>
                                <small class="text-muted">السبب: {{ $b->reason ?: 'غير محدد' }} • أُضيف بواسطة: {{ $b->creator?->username ?? 'النظام' }}</small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <form action="{{ route('admin.blacklist.toggle', $b->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $b->is_active ? 'btn-outline-danger' : 'btn-outline-secondary' }} rounded-pill px-3">
                                        {{ $b->is_active ? 'محظور' : 'موقوف' }}
                                    </button>
                                </form>
                                <form action="{{ route('admin.blacklist.delete', $b->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-dark rounded-circle">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">القائمة السوداء فارغة حالياً</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<!-- القسم الرابع: سجل محاولات تسجيل الدخول وعناوين IP -->
<div class="card table-card animate-stagger-4">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">
            <i class="fas fa-network-wired text-primary me-2"></i> سجل محاولات الدخول الأخيرة وعناوين IP
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>الرقم المميز</th>
                        <th>المستخدم</th>
                        <th>عنوان IP</th>
                        <th>النتيجة</th>
                        <th>التوقيت</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentAttempts as $att)
                        <tr>
                            <td><code>{{ $att->identifier }}</code></td>
                            <td>
                                @if($att->user_info)
                                    <span class="fw-semibold">{{ $att->user_info->username }}</span>
                                    <small class="text-muted">({{ $att->user_info->role }})</small>
                                @else
                                    <span class="text-muted">غير معروف</span>
                                @endif
                            </td>
                            <td><code>{{ $att->ip_address }}</code></td>
                            <td>
                                @if($att->success)
                                    <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill">
                                        <i class="fas fa-check-circle me-1"></i> دخول ناجح
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger px-3 py-1 rounded-pill">
                                        <i class="fas fa-times-circle me-1"></i> محاولة فاشلة
                                    </span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $att->attempted_at?->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">لا توجد محاولات دخول مسجلة</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

