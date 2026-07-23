<?php

// v1.1 — 2026-07-23 | Authorization moved to Budget\BudgetOrderPolicy (app/Policies),
//                     replacing inline abort_unless(...) calls. Covered by
//                     tests/Feature/BudgetOrderControllerTest.php.

namespace App\Http\Controllers;

use App\Jobs\GenerateBudgetFiles;
use App\Models\Budget\BudgetOrder;
use App\Services\GoogleDocsService;
use App\Services\SpacesStorageService;
use Illuminate\Http\Request;

class BudgetOrderController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', BudgetOrder::class);

        $q      = trim((string) $request->input('q', ''));
        $status = $request->input('status', 'all');

        $query = BudgetOrder::query()->orderByDesc('created_at');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('customer_email', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('woo_order_id', 'like', "%{$q}%")
                    ->orWhere('header_data->title', 'like', "%{$q}%");
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->paginate(25)->withQueryString();

        return view('budget-orders.index', [
            'orders' => $orders,
            'q'      => $q,
            'status' => $status,
        ]);
    }

    public function show(BudgetOrder $budgetOrder)
    {
        $this->authorize('view', $budgetOrder);

        return view('budget-orders.show', [
            'order' => $budgetOrder,
        ]);
    }

    public function downloadPdf(BudgetOrder $budgetOrder, GoogleDocsService $docs, SpacesStorageService $spaces)
    {
        $this->authorize('download', $budgetOrder);

        if (! $budgetOrder->drive_pdf_id) {
            return back()->withErrors(['download' => 'No PDF available. Try regenerating first.']);
        }

        $bytes = $budgetOrder->spaces_pdf_path
            ? $spaces->get($budgetOrder->spaces_pdf_path)
            : $docs->downloadDriveFileBytes($budgetOrder->drive_pdf_id);
        $title = $budgetOrder->header_data['title'] ?? 'Budget';
        $safeTitle = preg_replace('/[^\w\s\-.]/', '', $title);
        $filename = "{$budgetOrder->woo_order_id} - SR Budget - {$safeTitle}.pdf";

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => strlen($bytes),
        ]);
    }

    public function downloadXlsx(BudgetOrder $budgetOrder, GoogleDocsService $docs, SpacesStorageService $spaces)
    {
        $this->authorize('download', $budgetOrder);

        if (! $budgetOrder->drive_xlsx_id) {
            return back()->withErrors(['download' => 'No Excel file available. Try regenerating first.']);
        }

        $bytes = $budgetOrder->spaces_xlsx_path
            ? $spaces->get($budgetOrder->spaces_xlsx_path)
            : $docs->downloadDriveFileBytes($budgetOrder->drive_xlsx_id);
        $title = $budgetOrder->header_data['title'] ?? 'Budget';
        $safeTitle = preg_replace('/[^\w\s\-.]/', '', $title);
        $filename = "{$budgetOrder->woo_order_id} - SR Budget - {$safeTitle}.xlsx";

        return response($bytes, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => strlen($bytes),
        ]);
    }

    public function bulkDestroy(Request $request)
    {
        $this->authorize('bulkDelete', BudgetOrder::class);

        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $count = BudgetOrder::whereIn('id', $data['ids'])->delete();

        return back()->with('success', $count . ' budget' . ($count === 1 ? '' : 's') . ' deleted.');
    }

    public function regenerate(BudgetOrder $budgetOrder)
    {
        $this->authorize('regenerate', $budgetOrder);

        $budgetOrder->update([
            'status'        => BudgetOrder::STATUS_PROCESSING,
            'error_message' => null,
        ]);

        GenerateBudgetFiles::dispatch($budgetOrder->id);

        return back()->with('success', 'Budget regeneration queued for order ' . $budgetOrder->woo_order_id . '.');
    }
}
