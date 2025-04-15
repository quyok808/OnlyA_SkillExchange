<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\User;
use Illuminate\Http\Request;
// use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;

// use function Laravel\Prompts\warning;

class ReportController extends Controller
{
    /**
     * Hiển thị danh sách các báo cáo (thường dành cho Admin).
     * GET /api/reports
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Report::class);

        /** @var Builder $query */
        $query = Report::query()
            ->with([
                'user:id,name,email',
                'reportedByUser:id,name,email'
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('reporter_id') && is_string($request->query('reporter_id'))) {
            $query->where('userId', $request->query('reporter_id'));
        }
        if ($request->filled('reported_user_id') && is_string($request->query('reported_user_id'))) {
            $query->where('reportedBy', $request->query('reported_user_id'));
        }

        $reports = $query->latest()->paginate(15)->withQueryString();
        return ReportResource::collection($reports);
    }

    /**
     * Lưu một báo cáo mới vào database.
     * POST /api/reports
     */
    public function store(StoreReportRequest $request)
    {
        Gate::authorize('create', Report::class);

        $validatedData = $request->validated();

        $report = Report::create([
            'userId' => Auth::id(),
            'reportedBy' => $validatedData['userId'],
            'reason' => $validatedData['reason'],
            'status' => 'Processing',
        ]);
        return new ReportResource($report);
    }

    /**
     * Hiển thị chi tiết một báo cáo (thường dành cho Admin).
     * GET /api/reports/{report}
     */
    public function show(Report $report)
    {
        Gate::authorize('view', $report);
        $report->load(['user:id,name,email', 'reportedByUser:id,name,email']);
        return new ReportResource($report);
    }

    /**
     * Cập nhật trạng thái của báo cáo (thường dành cho Admin).
     * PUT/PATCH /api/reports/{report}
     */
    public function update(UpdateReportRequest $request, Report $report)
    {
        Gate::authorize('update', $report);
        $validatedData = $request->validated();
        $report->update($validatedData);
        $report->load(['user:id,name,email', 'reportedByUser:id,name,email']);
        return new ReportResource($report);
    }

    /**
     * Xóa một báo cáo (thường dành cho Admin).
     * DELETE /api/reports/{report}
     */
    public function destroy(Report $report)
    {
        Gate::authorize('delete', $report);
        $report->delete();
        return response()->noContent();
    }

    /**
     * Thay đổi trạng thái của báo cáo (route riêng).
     * PUT /api/reports/change-status/{report}
     */
    public function changeStatus(Request $request, Report $report)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['Processing', 'Banned', 'Canceled', 'Warning', 'Warned'])],
        ]);
        $report->update(['status' => $validated['status']]);
        $report->load(['user:id,name,email', 'reportedByUser:id,name,email']);

        if ($report->user) {
            $report->user->lock = 1;
            $report->user->save();
        }

        return new ReportResource($report);
    }

    /**
     * Lấy thông tin cảnh báo cho người dùng hiện tại.
     * GET /api/reports/get-warning
     */
    public function getWarning(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $warningCount = $user->receivedReports()
            ->whereIn('status', ['Warning'])
            ->count();

        $reports = $user->receivedReports()
            ->whereIn('status', ['Warning'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'totalReports' => $warningCount,
                'reports' => $reports->isEmpty() ? [] : $reports
            ]
        ]);
    }
}
