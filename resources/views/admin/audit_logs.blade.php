@extends('admin.layout')
@section('title', 'سجلات التدقيق')
@section('content')
<h4 class="fw-bold mb-4">🔍 سجلات التدقيق</h4>

<div class="card table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>الإجراء</th>
                        <th>المستخدم</th>
                        <th>نوع الكيان</th>
                        <th>عنوان IP</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                    <tr>
                        <td><code class="text-primary">{{ $log->action }}</code></td>
                        <td>{{ $log->user?->username ?? 'غير محدد' }}</td>
                        <td><small>{{ $log->entity_type }}</small></td>
                        <td><small class="text-muted">{{ $log->ip_address }}</small></td>
                        <td><small>{{ $log->created_at?->format('Y-m-d H:i') }}</small></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
