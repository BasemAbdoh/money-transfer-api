@extends('admin.layout')
@section('title', 'إدارة المستخدمين')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1">👥 إدارة المستخدمين والصلاحيات والأرصدة</h4>
        <small class="text-muted">إضافة موظفين، شحن/خصم الأرصدة، تعديل الأدوار والحدود اليومية وتأكيد الهواتف</small>
    </div>
    <button class="btn btn-primary fw-bold px-4" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="fas fa-user-plus me-1"></i> إضافة موظف / مستخدم جديد
    </button>
</div>

<!-- شريط الفلترة والبحث -->
<div class="card table-card mb-4">
    <div class="card-body">
        <form action="{{ route('admin.users') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="بحث بالاسم، البريد، رقم الدخول، أو الهاتف...">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select">
                    <option value="">كل الأدوار</option>
                    <option value="Admin" {{ ($roleFilter ?? '') === 'Admin' ? 'selected' : '' }}>مدير (Admin)</option>
                    <option value="Supervisor" {{ ($roleFilter ?? '') === 'Supervisor' ? 'selected' : '' }}>مشرف (Supervisor)</option>
                    <option value="User" {{ ($roleFilter ?? '') === 'User' ? 'selected' : '' }}>موظف (User)</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="branch_id" class="form-select">
                    <option value="">كل الفروع</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ (string)($branchFilter ?? '') === (string)$b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> بحث</button>
                <a href="{{ route('admin.users') }}" class="btn btn-outline-secondary" title="إعادة تعيين"><i class="fas fa-undo"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- جدول المستخدمين -->
<div class="card table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>رقم الدخول</th>
                        <th>المستخدم</th>
                        <th>الفرع / الدور</th>
                        <th>الرصيد الحالي</th>
                        <th>الحد اليومي</th>
                        <th>الجوال</th>
                        <th>الحالة</th>
                        <th class="text-center">الإجراءات التحكمية</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        <td><code class="fw-bold text-primary">{{ $user->user_number ?? '-' }}</code></td>
                        <td>
                            <div class="fw-bold">{{ $user->username }}</div>
                            <small class="text-muted">{{ $user->email }}</small>
                        </td>
                        <td>
                            <span class="badge {{ $user->role === 'Admin' ? 'bg-danger' : ($user->role === 'Supervisor' ? 'bg-warning text-dark' : 'bg-primary') }}">
                                {{ $user->role === 'Admin' ? 'مدير' : ($user->role === 'Supervisor' ? 'مشرف' : 'موظف') }}
                            </span>
                            <div class="small text-muted mt-1">
                                <i class="fas fa-building me-1"></i>{{ $user->branch?->name ?? 'بدون فرع' }}
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-success fs-6">{{ number_format($user->balance, 2) }}</span>
                        </td>
                        <td>
                            @if($user->daily_transfer_limit > 0 || $user->daily_transfer_count_limit > 0)
                                <div class="small">المبلغ: <strong>{{ $user->daily_transfer_limit > 0 ? number_format($user->daily_transfer_limit) : 'مفتوح' }}</strong></div>
                                <div class="small text-muted">العدد: {{ $user->daily_transfer_count_limit > 0 ? $user->daily_transfer_count_limit . ' حوالة' : 'مفتوح' }}</div>
                            @else
                                <span class="badge bg-light text-secondary">بدون حد</span>
                            @endif
                        </td>
                        <td>
                            <div><small>{{ $user->phone ?? 'غير مسجل' }}</small></div>
                            @if($user->phone_status === 'confirmed')
                                <span class="badge bg-success-subtle text-success">مؤكد</span>
                            @elseif($user->phone_status === 'pending' && $user->phone)
                                <span class="badge bg-warning-subtle text-warning">بانتظار التأكيد</span>
                            @elseif($user->phone_status === 'rejected')
                                <span class="badge bg-danger-subtle text-danger">مرفوض</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $user->is_active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $user->is_active ? 'نشط' : 'موقوف' }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1 flex-wrap">
                                <!-- زر شحن / خصم الرصيد -->
                                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#balanceModal{{ $user->id }}" title="شحن أو خصم رصيد">
                                    <i class="fas fa-wallet"></i> الرصيد
                                </button>
                                <!-- زر تعديل الصلاحيات والحد اليومي -->
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#settingsModal{{ $user->id }}" title="تعديل الدور والفرع والحد اليومي">
                                    <i class="fas fa-user-cog"></i> إعدادات
                                </button>
                                <!-- زر تأكيد الهاتف السريع إن كان معلقاً -->
                                @if($user->phone && $user->phone_status === 'pending')
                                    <form action="{{ route('admin.users.phone', $user->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="action" value="confirm">
                                        <button type="submit" class="btn btn-sm btn-warning" title="تأكيد رقم الجوال">
                                            <i class="fas fa-phone-check"></i> تأكيد الجوال
                                        </button>
                                    </form>
                                @endif
                                <!-- زر تفعيل / إيقاف الحساب -->
                                <form action="{{ route('admin.users.toggle', $user->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $user->is_active ? 'btn-outline-danger' : 'btn-outline-secondary' }}" title="{{ $user->is_active ? 'إيقاف الحساب' : 'تفعيل الحساب' }}">
                                        <i class="fas {{ $user->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">لا يوجد مستخدمون مطابقون للبحث</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $users->appends(request()->query())->links() }}</div>
    </div>
</div>

<!-- نوافذ تعديل المستخدمين (خارج الجدول تماماً) -->
@foreach($users as $user)
<div class="modal fade" id="balanceModal{{ $user->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.users.balance', $user->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold">💰 إدارة رصيد: {{ $user->username }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2">
                    الرصيد الحالي: <strong>{{ number_format($user->balance, 2) }}</strong>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">نوع العملية</label>
                    <select name="operation" class="form-select" required>
                        <option value="credit">➕ إيداع / شحن رصيد (إضافة للرصيد الحالي)</option>
                        <option value="debit">➖ خصم / سحب رصيد (إنقاص من الرصيد الحالي)</option>
                        <option value="set">✏️ تعيين الرصيد مباشرة إلى القيمة المدخلة</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">المبلغ</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="أدخل المبلغ..." required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">البيان / سبب العملية</label>
                    <input type="text" name="description" class="form-control" placeholder="مثال: تغذية صندوق الفرع الصباحية">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-success fw-bold">تنفيذ العملية</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="settingsModal{{ $user->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('admin.users.settings', $user->id) }}" method="POST" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title fw-bold">⚙️ إعدادات وصلاحيات: {{ $user->username }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">اسم المستخدم</label>
                        <input type="text" name="username" value="{{ $user->username }}" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">رقم الجوال</label>
                        <input type="text" name="phone" value="{{ $user->phone }}" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">الدور الوظيفي (RBAC)</label>
                        <select name="role" class="form-select" required>
                            <option value="User" {{ $user->role === 'User' ? 'selected' : '' }}>موظف (User)</option>
                            <option value="Supervisor" {{ $user->role === 'Supervisor' ? 'selected' : '' }}>مشرف فرع (Supervisor)</option>
                            <option value="Admin" {{ $user->role === 'Admin' ? 'selected' : '' }}>مدير نظام (Admin)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">الفرع التابع له</label>
                        <select name="branch_id" class="form-select">
                            <option value="">-- بدون فرع محدد --</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" {{ $user->branch_id === $b->id ? 'selected' : '' }}>{{ $b->name }} ({{ $b->city ?? '-' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">الحد اليومي لمجموع المبالغ (0 = بدون حد)</label>
                        <input type="number" step="0.01" min="0" name="daily_transfer_limit" value="{{ $user->daily_transfer_limit ?? 0 }}" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">الحد اليومي لعدد الحوالات (0 = بدون حد)</label>
                        <input type="number" min="0" name="daily_transfer_count_limit" value="{{ $user->daily_transfer_count_limit ?? 0 }}" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">حالة تأكيد الجوال</label>
                        <select name="phone_status" class="form-select" required>
                            <option value="confirmed" {{ $user->phone_status === 'confirmed' ? 'selected' : '' }}>مؤكد (Confirmed)</option>
                            <option value="pending" {{ $user->phone_status === 'pending' ? 'selected' : '' }}>قيد الانتظار (Pending)</option>
                            <option value="rejected" {{ $user->phone_status === 'rejected' ? 'selected' : '' }}>مرفوض (Rejected)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">حالة الحساب</label>
                        <select name="is_active" class="form-select" required>
                            <option value="1" {{ $user->is_active ? 'selected' : '' }}>نشط ومفعل</option>
                            <option value="0" {{ !$user->is_active ? 'selected' : '' }}>موقوف / محظور</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-primary fw-bold">حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>
@endforeach

<!-- نافذة إنشاء موظف / مستخدم جديد -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('admin.users.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold">➕ إضافة موظف / مستخدم جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">اسم المستخدم *</label>
                        <input type="text" name="username" class="form-control" placeholder="الاسم الكامل للموظف" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">البريد الإلكتروني *</label>
                        <input type="email" name="email" class="form-control" placeholder="user@company.com" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">كلمة المرور *</label>
                        <input type="password" name="password" class="form-control" placeholder="6 أحرف على الأقل" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">رقم الجوال</label>
                        <input type="text" name="phone" class="form-control" placeholder="77xxxxxxx">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">الدور الوظيفي *</label>
                        <select name="role" class="form-select" required>
                            <option value="User">موظف (User)</option>
                            <option value="Supervisor">مشرف فرع (Supervisor)</option>
                            <option value="Admin">مدير نظام (Admin)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">الفرع</label>
                        <select name="branch_id" class="form-select">
                            <option value="">-- بدون فرع --</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">الرصيد الافتتاحي</label>
                        <input type="number" step="0.01" min="0" name="balance" value="0" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">الحد اليومي للمبلغ (0 = مفتوح)</label>
                        <input type="number" step="0.01" min="0" name="daily_transfer_limit" value="0" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">الحد اليومي لعدد الحوالات (0 = مفتوح)</label>
                        <input type="number" min="0" name="daily_transfer_count_limit" value="0" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-primary fw-bold">إنشاء الحساب وتوليد رقم الدخول</button>
            </div>
        </form>
    </div>
</div>
@endsection
