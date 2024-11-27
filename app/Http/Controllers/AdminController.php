<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\Review;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminController extends Controller
{
    public function getAdminReport(Request $request)
    {
        try {
            // Doanh thu tổng từ tất cả các đơn hàng đã thanh toán
            $totalRevenue = Order::where('payment_status', 'paid')->sum('total_price');

            // Tổng số tiền đã được rút (payouts)
            $totalPayouts = PayoutRequest::where('status', 'completed')->sum('amount');

            // Doanh thu sau khi đã thanh toán (Net Revenue)
            $netRevenue = $totalRevenue - $totalPayouts;

            $totalUsers = User::count();

            $totalCourses = Course::count();

            $totalCategories = Category::count();

            $totalPayoutRequests = PayoutRequest::count();

            $completedPayoutRequests = PayoutRequest::where('status', 'completed')->count();

            $processingPayoutRequests = PayoutRequest::where('status', 'processing')->count();

            $failedPayoutRequests = PayoutRequest::where('status', 'failed')->count();

            return response()->json([
                'status' => 'OK',
                'data' => [
                    'total_revenue' => $totalRevenue,
                    'total_payouts' => $totalPayouts,
                    'net_revenue' => $netRevenue,
                    'total_users' => $totalUsers,
                    'total_courses' => $totalCourses,
                    'total_categories' => $totalCategories,
                    'total_payout_requests' => $totalPayoutRequests,
                    'completed_payout_requests' => $completedPayoutRequests,
                    'processing_payout_requests' => $processingPayoutRequests,
                    'failed_payout_requests' => $failedPayoutRequests,
                ],
                'message' => 'Get admin report successfully.',
                'code' => 200
            ], 200);
        } catch (\Exception $e) {
            // Xử lý lỗi
            return response()->json([
                'status' => 'FAIL',
                'message' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }


    public function getAdminLineChartData(Request $request)
    {
        try {
            // Lấy các tham số từ request
            $startDate = $request->input('start_date'); // Ngày bắt đầu
            $endDate = $request->input('end_date');     // Ngày kết thúc
            $filter = $request->input('filter', 'day'); // Lọc theo ngày, tháng, năm

            // Xử lý các tham số và thiết lập khoảng thời gian
            $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->subMonth()->startOfDay();
            $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

            // Xác định định dạng và tạo khoảng thời gian dựa trên filter
            switch ($filter) {
                case 'month':
                    $format = '%Y-%m';
                    $groupByFormat = "DATE_FORMAT(created_at, '{$format}') as period";
                    $carbonFormat = 'Y-m';
                    $step = '1 month';
                    break;
                case 'year':
                    $format = '%Y';
                    $groupByFormat = "DATE_FORMAT(created_at, '{$format}') as period";
                    $carbonFormat = 'Y';
                    $step = '1 year';
                    break;
                case 'day':
                default:
                    $format = '%Y-%m-%d';
                    $groupByFormat = "DATE_FORMAT(created_at, '{$format}') as period";
                    $carbonFormat = 'Y-m-d';
                    $step = '1 day';
                    break;
            }

            // Tạo khoảng thời gian sử dụng CarbonPeriod
            $period = CarbonPeriod::create($startDate, $step, $endDate);

            // Lấy dữ liệu doanh thu từ bảng orders
            $revenueData = Order::selectRaw("{$groupByFormat}, SUM(total_price) as revenue, COUNT(id) as total_sales")
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('period')
                ->orderBy('period', 'asc') // Đảm bảo ORDER BY cùng với GROUP BY
                ->get();

            // Chuyển dữ liệu thành dạng mảng [period => ['revenue' => ..., 'total_sales' => ...]]
            $data = $revenueData->pluck('revenue', 'period')->toArray();
            $sales = $revenueData->pluck('total_sales', 'period')->toArray();


            $result = [];
            foreach ($period as $date) {
                $formattedDate = $date->format($carbonFormat);
                $result[] = [
                    'period' => $formattedDate,
                    'revenue' => isset($data[$formattedDate]) ? (float)$data[$formattedDate] : 0,
                    'total_sales' => isset($sales[$formattedDate]) ? (int)$sales[$formattedDate] : 0,
                ];
            }

            // Tính tổng revenue và tổng sale
            $totalRevenue = array_sum($data);
            $totalSales = array_sum($sales);

            return response()->json([
                'status' => 'OK',
                'data' => $result,
                'total_revenue' => $totalRevenue,
                'total_sales' => $totalSales,
                'code' => 200
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => 'Error',
                'message' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    public function getUserRegistrationLineChart(Request $request)
    {
        try {
            // Lấy các tham số từ request
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $filter = $request->input('filter', 'day'); // 'day', 'month', 'year'

            // Xử lý các tham số và thiết lập khoảng thời gian
            $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->subMonth()->startOfDay();
            $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

            // Xác định định dạng và tạo khoảng thời gian dựa trên filter
            switch ($filter) {
                case 'month':
                    $format = '%Y-%m';
                    $groupByFormat = "DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '" . config('app.timezone') . "'), '{$format}') as period";
                    $carbonFormat = 'Y-m';
                    $step = '1 month';
                    break;
                case 'year':
                    $format = '%Y';
                    $groupByFormat = "DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '" . config('app.timezone') . "'), '{$format}') as period";
                    $carbonFormat = 'Y';
                    $step = '1 year';
                    break;
                case 'day':
                default:
                    $format = '%Y-%m-%d';
                    $groupByFormat = "DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '" . config('app.timezone') . "'), '{$format}') as period";
                    $carbonFormat = 'Y-m-d';
                    $step = '1 day';
                    break;
            }

            // Tạo khoảng thời gian sử dụng CarbonPeriod
            $period = CarbonPeriod::create($startDate, $step, $endDate);

            // Lấy dữ liệu đăng ký người dùng
            $registrationData = User::selectRaw("DATE_FORMAT(created_at, '{$format}') as period, COUNT(id) as registrations")
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('period')
                ->orderBy('period', 'asc')
                ->get();

            // Chuyển dữ liệu thành dạng mảng [period => registrations]
            $data = $registrationData->pluck('registrations', 'period')->toArray();

            // Chuẩn bị dữ liệu kết quả với các khoảng thời gian không có đăng ký hiển thị 0
            $result = [];
            foreach ($period as $date) {
                $formattedDate = $date->format($carbonFormat);
                $registrations = isset($data[$formattedDate]) ? (int)$data[$formattedDate] : 0;
                $result[] = [
                    'period' => $formattedDate,
                    'registrations' => $registrations,
                ];
            }
            // Tính tổng đăng ký
            $totalRegistrations = array_sum($data);

            return response()->json([
                'status' => 'OK',
                'data' => $result,
                'total_registrations' => $totalRegistrations,
                'code' => 200
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Error',
                'message' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    public function getOrderLineChartData(Request $request)
    {
        try {
            // Lấy các tham số từ request
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $filter = $request->input('filter', 'day'); // 'day', 'month', 'year'

            // Xử lý các tham số và thiết lập khoảng thời gian
            $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->subMonth()->startOfDay();
            $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

            // Xác định định dạng và tạo khoảng thời gian dựa trên filter
            switch ($filter) {
                case 'month':
                    $format = '%Y-%m';
                    $groupByFormat = "DATE_FORMAT(created_at, '{$format}') as period";
                    $carbonFormat = 'Y-m';
                    $step = '1 month';
                    break;
                case 'year':
                    $format = '%Y';
                    $groupByFormat = "DATE_FORMAT(created_at, '{$format}') as period";
                    $carbonFormat = 'Y';
                    $step = '1 year';
                    break;
                case 'day':
                default:
                    $format = '%Y-%m-%d';
                    $groupByFormat = "DATE_FORMAT(created_at, '{$format}') as period";
                    $carbonFormat = 'Y-m-d';
                    $step = '1 day';
                    break;
            }

            // Tạo khoảng thời gian sử dụng CarbonPeriod
            $period = CarbonPeriod::create($startDate, $step, $endDate);

            // Lấy dữ liệu đơn hàng đã thanh toán
            $orderData = Order::selectRaw("{$groupByFormat}, COUNT(id) as orders")
                ->where('payment_status', 'paid') // Chỉ lấy đơn hàng đã thanh toán
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('period')
                ->orderBy('period', 'asc')
                ->get();

            // Chuyển dữ liệu thành dạng mảng [period => orders]
            $data = $orderData->pluck('orders', 'period')->toArray();


            // Chuẩn bị dữ liệu kết quả với các khoảng thời gian không có đơn hàng hiển thị 0
            $result = [];
            foreach ($period as $date) {
                $formattedDate = $date->format($carbonFormat);
                $registrations = isset($data[$formattedDate]) ? (int) $data[$formattedDate] : 0;
                $result[] = [
                    'period' => $formattedDate,
                    'orders' => $registrations,
                ];
            }
            // Tính tổng đơn hàng
            $totalOrders = array_sum($data);

            return response()->json([
                'status' => 'OK',
                'data' => $result,
                'total_orders' => $totalOrders,
                'code' => 200
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => 'Error',
                'message' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

}
