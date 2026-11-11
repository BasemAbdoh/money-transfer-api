@extends('admin.layout')
@section('title', 'إدارة الفروع')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1">🏢 إدارة الفروع وتوزيع الموظفين</h4>
        <small class="text-muted">إضافة الفروع، متابعة إحصائيات وحجم عمليات كل فرع، ونقل الموظفين بين الفروع</small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#transferUserModal">
            <i class="fas fa-user-exchange me-1"></i> نقل موظف لفرع
        </button>
        <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addBranchModal">
            <i class="fas fa-plus me-1"></i> إضافة فرع جديد
        </button>
    </div>
</div>

<!-- بطاقات ملخص الفروع -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #667eea, #764ba2)">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="fs-4 fw-bold">{{ $branches->count() }}</div>
                    <div class="small opacity-75">إجمالي الفروع</div>
                </div>
                <i class="fas fa-building fa-2x opacity-75"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #43e97b, #38f9d7)">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="fs-4 fw-bold">{{ $branches->where('is_active', true)->count() }}</div>
                    <div class="small opacity-75">الفروع النشطة</div>
                </div>
                <i class="fas fa-check-circle fa-2x opacity-75"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #4facfe, #00f2fe)">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="fs-4 fw-bold">{{ number_format($branches->sum('transfers_sum_amount'), 2) }}</div>
                    <div class="small opacity-75">إجمالي مبالغ حوالات الفروع</div>
                </div>
                <i class="fas fa-coins fa-2x opacity-75"></i>
            </div>
        </div>
    </div>
</div>

<!-- جدول الفروع -->
<div class="card table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>اسم الفرع</th>
                        <th>المدينة</th>
                        <th>عدد الموظفين</th>
                        <th>عدد الحوالات</th>
                        <th>إجمالي مبالغ الحوالات</th>
                        <th>الحالة</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($branches as $branch)
                    <tr>
                        <td>{{ $branch->id }}</td>
                        <td><strong>{{ $branch->name }}</strong></td>
                        <td>{{ $branch->city ?? 'غير محددة' }}</td>
                        <td><span class="badge bg-info text-dark">{{ $branch->users_count }} موظف</span></td>
                        <td><span class="badge bg-primary">{{ $branch->transfers_count }} حوالة</span></td>
                        <td><strong>{{ number_format($branch->transfers_sum_amount ?? 0, 2) }}</strong></td>
                        <td>
                            <span class="badge {{ $branch->is_active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $branch->is_active ? 'نشط' : 'معطل' }}
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editBranchModal{{ $branch->id }}">
                                <i class="fas fa-edit me-1"></i> تعديل
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">لا توجد فروع مسجلة بعد. اضغط على "إضافة فرع جديد" للبدء.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- نوافذ تعديل الفروع (خارج الجدول تماماً) -->
@foreach($branches as $branch)
<div class="modal fade" id="editBranchModal{{ $branch->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.branches.update', $branch->id) }}" method="POST" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title fw-bold">✏️ تعديل فرع: {{ $branch->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">اسم الفرع *</label>
                    <input type="text" name="name" value="{{ $branch->name }}" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">المدينة</label>
                    <input type="text" name="city" value="{{ $branch->city }}" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">حالة الفرع</label>
                    <select name="is_active" class="form-select">
                        <option value="1" {{ $branch->is_active ? 'selected' : '' }}>نشط</option>
                        <option value="0" {{ !$branch->is_active ? 'selected' : '' }}>معطل</option>
                    </select>
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

<!-- نافذة إضافة فرع جديد -->
<div class="modal fade" id="addBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.branches.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold">➕ إضافة فرع جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">اسم الفرع *</label>
                    <input type="text" name="name" class="form-control" placeholder="مثال: فرع صنعاء الرئيسي" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">المدينة</label>
                    <input type="text" name="city" class="form-control" placeholder="مثال: صنعاء">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-primary fw-bold">إضافة الفرع</button>
            </div>
        </form>
    </div>
</div>

<!-- نافذة نقل موظف إلى فرع -->
<div class="modal fade" id="transferUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="transferUserForm" method="POST" action="{{ $branches->first() ? route('admin.branches.transfer-user', $branches->first()->id) : '#' }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold">🔄 نقل موظف إلى فرع</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">اختر الموظف *</label>
                    <select name="user_id" class="form-select" required>
                        @foreach($allUsers as $u)
                            <option value="{{ $u->id }}">{{ $u->username }} ({{ $u->role }}) — الفرع الحالي: {{ $u->branch?->name ?? 'بدون فرع' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">الفرع الجديد *</label>
                    <select id="targetBranchSelect" class="form-select" required onchange="document.getElementById('transferUserForm').action = '/admin-panel/branches/' + this.value + '/transfer-user'">
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->city ?? '-' }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-primary fw-bold" {{ $branches->isEmpty() ? 'disabled' : '' }}>تأكيد النقل</button>
            </div>
        </form>
    </div>
</div>
@endsection

