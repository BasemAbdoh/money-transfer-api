@extends('admin.layout')
@section('title', 'الموافقات المعلقة')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">✅ مركز الموافقات المزدوجة على الحوالات</h4>
        <small class="text-muted">مراجعة واعتماد أو رفض الحوالات التي تتجاوز سقف الصلاحية المباشر للموظف</small>
    </div>
</div>

<!-- جدول الحوالات بانتظار الموافقة -->
<div class="card table-card mb-4">
    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold">⏳ الحوالات المنتظرة للاعتماد ({{ $pendingTransfers->total() }})</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>رقم الحوالة</th>
                        <th>الموظف المرسل / الفرع</th>
                        <th>المبلغ</th>
                        <th>المستلم</th>
                        <th>الشركة / الوجهة</th>
                        <th>التاريخ</th>
                        <th class="text-center">قرار الإدارة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingTransfers as $transfer)
                    <tr>
                        <td><code class="fw-bold">{{ $transfer->trans_number }}</code></td>
                        <td>
                            <div class="fw-bold">{{ $transfer->created_by }}</div>
                            <small class="text-muted">{{ $transfer->branch?->name ?? 'بدون فرع' }}</small>
                        </td>
                        <td><span class="badge bg-warning text-dark fs-6">{{ number_format($transfer->amount, 2) }} {{ $transfer->currency }}</span></td>
                        <td>{{ $transfer->receiver_name }}</td>
                        <td>
                            <div>{{ $transfer->company_type }}</div>
                            <small class="text-muted">{{ $transfer->destination }}</small>
                        </td>
                        <td><small>{{ $transfer->created_at?->format('Y-m-d H:i') }}</small></td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-2">
                                <form action="{{ route('admin.approvals.action', $transfer->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-sm btn-success fw-bold">
                                        <i class="fas fa-check me-1"></i> اعتماد
                                    </button>
                                </form>
                                <button class="btn btn-sm btn-outline-danger fw-bold" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $transfer->id }}">
                                    <i class="fas fa-times me-1"></i> رفض واسترداد
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-success fw-bold">
                            <i class="fas fa-check-circle me-1"></i> لا توجد حوالات معلقة تنتظر الموافقة حالياً
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $pendingTransfers->links() }}</div>
    </div>
</div>

<!-- نوافذ رفض الحوالات (خارج الجدول تماماً) -->
@foreach($pendingTransfers as $transfer)
<div class="modal fade" id="rejectModal{{ $transfer->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.approvals.action', $transfer->id) }}" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="action" value="reject">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger">❌ رفض الحوالة: {{ $transfer->trans_number }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">عند رفض الحوالة، سيتم إلغاؤها وإعادة المبلغ ({{ number_format($transfer->amount, 2) }} {{ $transfer->currency }}) تلقائياً إلى رصيد المرسل وإشعاره بالسبب.</p>
                <div class="mb-3">
                    <label class="form-label fw-bold">سبب الرفض</label>
                    <textarea name="reason" rows="3" class="form-control" placeholder="اكتب سبب رفض الحوالة ليظهر للموظف..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">تراجع</button>
                <button type="submit" class="btn btn-danger fw-bold">تأكيد الرفض وإعادة الرصيد</button>
            </div>
        </form>
    </div>
</div>
@endforeach

<!-- سجل آخر القرارات المتخذة -->
<div class="card table-card">
    <div class="card-header bg-white border-0 pt-3">
        <span class="fw-bold">🕘 آخر الحوالات التي تمت مراجعتها</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>رقم الحوالة</th>
                        <th>المبلغ</th>
                        <th>المرسل</th>
                        <th>القرار</th>
                        <th>بواسطة</th>
                        <th>وقت القرار</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentlyReviewed as $item)
                    <tr>
                        <td><code>{{ $item->trans_number }}</code></td>
                        <td>{{ number_format($item->amount, 2) }} {{ $item->currency }}</td>
                        <td>{{ $item->created_by }}</td>
                        <td>
                            @if($item->approval_status === 'approved')
                                <span class="badge bg-success">معتمدة</span>
                            @else
                                <span class="badge bg-danger">مرفوضة ({{ $item->cancel_reason }})</span>
                            @endif
                        </td>
                        <td>{{ $item->approver?->username ?? 'الإدارة' }}</td>
                        <td><small>{{ $item->approved_at?->format('Y-m-d H:i') }}</small></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-3 text-muted">لا توجد قرارات سابقة</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

