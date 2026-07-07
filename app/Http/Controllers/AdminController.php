<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// افترضت أسماء الموديلز بناء على كودك، يرجى استيرادها بشكل صحيح حسب مشروعك
use App\Models\Branch;
use App\Models\Pharmacist; // المالك / الصيدلاني
use App\Models\Ticket; // الشكاوى
use App\Models\JobPost; // الوظائف المعروضة
use App\Models\Employee; // الباحثين عن عمل (موظفين ومتدربين)

class AdminDashboardController extends Controller
{
    // 4. جلب فروع مالك معين مع البحث والفلترة حسب طلب الفرونت
    public function getOwnerBranches(Request $request, $owner_id)
    {
        $query = Branch::where('pharmacist_id', $owner_id);

        // الفلترة حسب الحالة إذا تم إرسالها بالـ Query Parameters
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // البحث بالاسم عبر الـ Query Parameters
        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $branches = $query->get();

        return response()->json($branches, 200);
    }

    // تعديل حالة الفرع بشكل مستقل مرن (Toggle Status)
    public function toggleBranchStatus($branch_id)
    {
        $branch = Branch::findOrFail($branch_id);

        // إذا كان محظور نرجعه نشط، والعكس صحيح
        $branch->status = ($branch->status === 'Blocked') ? 'Active' : 'Blocked';
        $branch->save();

        return response()->json([
            'message' => 'Branch status updated successfully',
            'current_status' => $branch->status
        ], 200);
    }

    // 5. جلب كافة الشكاوى (مع إعطاء الأولوية للمفتوحة Open أولاً)
    public function getAllTickets()
    {
        // رتبنا حسب الـ status لتظهر Open أولاً (حرف O يأتي قبل R أو بالترتيب المخصص)
        // أو استخدام الـ OrderByRaw لضمان ظهور Open بالقمة
        $tickets = Ticket::orderByRaw("CASE WHEN status = 'Open' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tickets, 200);
    }

    // استقبال الرد الإداري وتحويل الحالة فوراً إلى Resolved
    public function respondToTicket(Request $request, $ticket_id)
    {
        $request->validate([
            'response_text' => 'required|string'
        ]);

        $ticket = Ticket::findOrFail($ticket_id);
        $ticket->admin_response = $request->response_text;
        $ticket->status = 'Resolved';
        $ticket->save();

        return response()->json([
            'message' => 'Ticket resolved and responded successfully',
            'ticket' => $ticket
        ], 200);
    }

    // 6. إحصائيات الصيادلة الملاك والفروع والنسب
    public function getPharmaciesSummary()
    {
        // إجمالي الملاك
        $total_owners = Pharmacist::count();

        // إجمالي الفروع النشطة
        $total_active_branches = Branch::where('status', 'Active')->count();

        // حساب نسبة الملاك الذين لديهم فرع واحد مقابل متعدد بالفروع باستخدام SQL Aggregation
        $owners_branch_counts = Branch::select('pharmacist_id', DB::raw('count(*) as branch_count'))
            ->groupBy('pharmacist_id')
            ->get();

        $single_branch_owners = 0;
        $multiple_branch_owners = 0;

        foreach ($owners_branch_counts as $row) {
            if ($row->branch_count == 1) {
                $single_branch_owners++;
            } else if ($row->branch_count > 1) {
                $multiple_branch_owners++;
            }
        }

        // حساب النسب المئوية بدقة منعا لتقسيم على صفر
        $single_percentage = $total_owners > 0 ? round(($single_branch_owners / $total_owners) * 100, 2) : 0;
        $multiple_percentage = $total_owners > 0 ? round(($multiple_branch_owners / $total_owners) * 100, 2) : 0;

        return response()->json([
            'total_owners' => $total_owners,
            'total_active_branches' => $total_active_branches,
            'single_branch_owner_percentage' => $single_percentage . '%',
            'multiple_branch_owner_percentage' => $multiple_percentage . '%'
        ], 200);
    }

    // إحصائيات حيوية سوق العمل
    public function getJobMarketSummary()
    {
        // عدد الوظائف المعروضة حالياً
        $active_job_posts = JobPost::where('status', 'Active')->count();

        // إجمالي عدد الباحثين عن عمل (الموظفين والمتدربين المسجلين)
        $active_seekers_count = Employee::count();

        // نسبة عمليات التوظيف الناجحة (المحسوبة بناءً على الوظائف المغلقة والمستقرة)
        $total_jobs = JobPost::count();
        $closed_successful_jobs = JobPost::where('status', 'Closed_Filled')->count();

        $successful_hires_percentage = $total_jobs > 0 ? round(($closed_successful_jobs / $total_jobs) * 100, 2) : 0;

        return response()->json([
            'active_job_posts' => $active_job_posts,
            'active_seekers_count' => $active_seekers_count,
            'successful_hires_percentage' => $successful_hires_percentage . '%'
        ], 200);
    }

    public function getOnboardingTrends()
    {
        // نقوم بعمل Aggregation وجلب عدد التسجيلات مجمعة حسب الشهر لآخر سنة
        $registrations = DB::table('pharmacists') // يمكنك عمل Union مع الـ employees إذا كان المطلوب دمج يوزرات النظام بالكامل
        ->select(DB::raw("DATE_FORMAT(created_at, '%b') as month"), DB::raw('count(*) as registrations'))
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%b'), MONTH(created_at)"))
            ->orderBy(DB::raw("MONTH(created_at)"), 'asc')
            ->get();

        // تنسيق الخرج ليكون Array ممتلئ تماماً بالمصطلحات مثل شكل طلب الفرونت
        return response()->json($registrations, 200);
    }
}
