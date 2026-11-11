@extends('admin.layout')
@section('title', 'إدارة الحوالات')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1">💸 إدارة الحوالات والعمليات</h4>
        <small class="text-muted">البحث، عرض سجل تاريخ الحوالة، تعديل بيانات المستلم، إلغاء الحوالات المعلقة وتصدير التقارير</small>
    </div>
    <a href="{{ route('admin.transfers.export', request()->query()) }}" class="btn btn-success fw-bold">
        <i class="fas fa-file-csv me-1"></i> تصدير التقرير (CSV / Excel)
    </a>
</div>

<div class="card table-card mb-3">
    <div class="card-body">
        <form action="{{ route('admin.transfers') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="رقم الحوالة، المستلم، رقم الحساب، أو المرسل...">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">كل الحالات</option>
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>⏳ قيد الانتظار (Pending)</option>
                    <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>✅ مدفوعة (Paid)</option>
                    <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>❌ ملغاة (Cancelled)</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="company" class="form-select">
                    <option value="">كل الشركات</option>
                    @foreach($companies ?? [] as $comp)
                        <option value="{{ $comp }}" {{ ($company ?? '') === $comp ? 'selected' : '' }}>{{ $comp }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100">🔍 بحث</button>
                <a href="{{ route('admin.transfers') }}" class="btn btn-outline-secondary"><i class="fas fa-undo"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>رقم الحوالة</th>
                        <th>المبلغ</th>
                        <th>المرسل / الفرع</th>
                        <th>المستلم</th>
                        <th>الشركة / الوجهة</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transfers as $transfer)
                    <tr>
                        <td><code>{{ $transfer->trans_number }}</code></td>
                        <td><strong>{{ number_format($transfer->amount, 2) }} {{ $transfer->currency }}</strong></td>
                        <td>
                            <div>{{ $transfer->created_by }}</div>
                            <small class="text-muted">{{ $transfer->branch?->name ?? 'الرئيسي' }}</small>
                        </td>
                        <td>{{ $transfer->receiver_name }}</td>
                        <td>
                            <div><small class="fw-bold">{{ $transfer->company_type }}</small></div>
                            <small class="text-muted">{{ $transfer->destination }}</small>
                        </td>
                        <td>
                            @if($transfer->status === 'paid')
                                <span class="badge badge-paid">✅ مدفوع</span>
                            @elseif($transfer->status === 'cancelled')
                                <span class="badge badge-cancelled">❌ ملغاة</span>
                            @else
                                <span class="badge badge-pending">⏳ انتظار</span>
                            @endif
                            @if($transfer->approval_status === 'pending_approval')
                                <div><span class="badge bg-warning text-dark mt-1">بانتظار اعتماد</span></div>
                            @endif
                        </td>
                        <td><small>{{ $transfer->created_at?->format('Y-m-d H:i') }}</small></td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <!-- تفاصيل وسجل التاريخ -->
                                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#detailModal{{ $transfer->id }}" title="تفاصيل وسجل الحوالة">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <!-- طباعة إيصال وسند الحوالة -->
                                <a href="{{ route('admin.transfers.print', $transfer->id) }}" target="_blank" class="btn btn-sm btn-outline-dark" title="طباعة إيصال الحوالة الرسمي">
                                    <i class="fas fa-print"></i>
                                </a>
                                <!-- تعديل بيانات المستلم / ملاحظة إدارية -->
                                @if($transfer->status === 'pending')
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTransferModal{{ $transfer->id }}" title="تعديل المستلم أو الملاحظات">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <!-- إلغاء الحوالة واسترداد المبلغ -->
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelTransferModal{{ $transfer->id }}" title="إلغاء الحوالة واسترداد الرصيد">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">لا توجد حوالات مطابقة</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $transfers->appends(request()->query())->links() }}</div>
    </div>
</div>

<!-- نوافذ تفاصيل وتعديل وإلغاء الحوالات (خارج الجدول تماماً) -->
@foreach($transfers as $transfer)
<div class="modal fade" id="detailModal{{ $transfer->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">📄 تفاصيل الحوالة: {{ $transfer->trans_number }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>رقم الحساب:</strong> {{ $transfer->account_number }}</div>
                    <div class="col-md-4"><strong>المبلغ:</strong> {{ number_format($transfer->amount, 2) }} {{ $transfer->currency }}</div>
                    <div class="col-md-4"><strong>الشركة:</strong> {{ $transfer->company_type }}</div>
                    <div class="col-md-4"><strong>المستلم:</strong> {{ $transfer->receiver_name }}</div>
                    <div class="col-md-4"><strong>الوجهة:</strong> {{ $transfer->destination }}</div>
                    <div class="col-md-4"><strong>المرسل:</strong> {{ $transfer->created_by }}</div>
                    @if($transfer->notes)
                        <div class="col-12"><strong>ملاحظات المرسل:</strong> {{ $transfer->notes }}</div>
                    @endif
                    @if($transfer->internal_notes)
                        <div class="col-12 text-primary"><strong>ملاحظات الإدارة الداخلية:</strong> {{ $transfer->internal_notes }}</div>
                    @endif
                    @if($transfer->cancel_reason)
                        <div class="col-12 text-danger"><strong>سبب الإلغاء/الرفض:</strong> {{ $transfer->cancel_reason }}</div>
                    @endif
                </div>
                <hr>
                <h6 class="fw-bold mb-2"><i class="fas fa-history me-1"></i> سجل تاريخ الحوالة (History Logs)</h6>
                <ul class="list-group list-group-flush">
                    @forelse($transfer->historyLogs as $h)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <span class="badge bg-secondary me-2">{{ $h->action }}</span>
                                <span>{{ $h->description }}</span>
                                <small class="text-muted">({{ $h->user?->username ?? 'النظام' }})</small>
                            </div>
                            <small class="text-muted">{{ $h->created_at?->format('Y-m-d H:i') }}</small>
                        </li>
                    @empty
                        <li class="list-group-item text-muted px-0">لا توجد سجلات تاريخ إضافية</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

@if($transfer->status === 'pending')
<div class="modal fade" id="editTransferModal{{ $transfer->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.transfers.update', $transfer->id) }}" method="POST" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title fw-bold">✏️ تعديل بيانات الحوالة: {{ $transfer->trans_number }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">اسم المستلم *</label>
                    <input type="text" name="receiver_name" value="{{ $transfer->receiver_name }}" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">الوجهة *</label>
                    <input type="text" name="destination" value="{{ $transfer->destination }}" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">ملاحظات داخلية للإدارة</label>
                    <textarea name="internal_notes" rows="3" class="form-control" placeholder="ملاحظة إدارية داخلية...">{{ $transfer->internal_notes }}</textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-primary fw-bold">حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="cancelTransferModal{{ $transfer->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.transfers.cancel', $transfer->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger">🚫 إلغاء الحوالة: {{ $transfer->trans_number }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">سيتم إلغاء الحوالة وإعادة مبلغ <strong>{{ number_format($transfer->amount, 2) }} {{ $transfer->currency }}</strong> إلى رصيد المرسل ({{ $transfer->created_by }}) فوراً.</p>
                <div class="mb-3">
                    <label class="form-label fw-bold">سبب الإلغاء</label>
                    <input type="text" name="cancel_reason" class="form-control" placeholder="مثال: بناءً على طلب المرسل / خطأ في البيانات" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">تراجع</button>
                <button type="submit" class="btn btn-danger fw-bold">تأكيد الإلغاء واسترداد المبلغ</button>
            </div>
        </form>
    </div>
</div>
@endif
@endforeach
@endsection
