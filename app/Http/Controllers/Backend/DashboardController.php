<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderTransaction;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $today      = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        // All-time stats (kept for backwards compat)
        $orders = Order::get();
        $data = [
            'sub_total'       => $orders->sum('sub_total'),
            'discount'        => $orders->sum('discount'),
            'total'           => $orders->sum('total'),
            'paid'            => $orders->sum('paid'),
            'due'             => $orders->sum('due'),
            'total_customer'  => Customer::count(),
            'total_order'     => $orders->count(),
            'total_product'   => Product::count(),
            'total_sale_item' => OrderProduct::sum('quantity'),
        ];

        // Today's KPIs
        $todayOrders = Order::whereDate('created_at', $today)->get();
        $data['todaySale']  = $todayOrders->sum('total');
        $data['todayOrder'] = $todayOrders->count();

        // This month's KPIs
        $monthOrders = Order::whereBetween('created_at', [$monthStart, Carbon::now()])->get();
        $data['thisMonthSale']  = $monthOrders->sum('total');
        $data['thisMonthOrder'] = $monthOrders->count();

        // Latest 10 orders
        $data['latestOrders'] = Order::with('customer')
            ->latest()
            ->take(10)
            ->get();

        // Weekly chart (last 7 days)
        $weekLabels = [];
        $weekData   = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $rusDay = ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'][$day->dayOfWeek];
            $weekLabels[] = $rusDay . ' ' . $day->format('d');
            $weekData[] = round(Order::whereDate('created_at', $day)->sum('total'), 2);
        }
        $data['weeklySalesLabels'] = $weekLabels;
        $data['weeklySalesData']   = $weekData;

        // Monthly chart (current year)
        $currentYear = now()->year;
        $monthNames  = ['Янв','Фев','Мар','Апр','Май','Июн','Июл','Авг','Сен','Окт','Ноя','Дек'];
        $monthSales  = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthSales[] = round(Order::whereYear('created_at', $currentYear)->whereMonth('created_at', $i)->sum('total'), 2);
        }
        $data['monthlySalesLabels'] = $monthNames;
        $data['monthlySalesData']   = $monthSales;

        // Legacy chart data (keep for any old templates)
        $startDate = Carbon::now()->subDays(30)->format('Y-m-d');
        $endDate   = Carbon::now()->format('Y-m-d');
        $dailyTotals = OrderTransaction::selectRaw('DATE(created_at) as date, SUM(amount) as total_amount')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')->orderBy('date', 'DESC')->get();
        $data['dates']       = $dailyTotals->pluck('date')->toArray();
        $data['totalAmounts'] = $dailyTotals->pluck('total_amount')->toArray();
        $data['dateRange']   = 'from ' . $startDate . ' to ' . $endDate;
        $data['currentYear'] = $currentYear;
        $transactions = OrderTransaction::whereYear('created_at', $currentYear)->get();
        $salesData = $transactions->groupBy(fn($i) => Carbon::parse($i->created_at)->format('Y-m'))
            ->map(fn($g) => $g->sum('amount'));
        $tempMonths = $tempTotalAmountMonth = [];
        for ($i = 1; $i <= 12; $i++) {
            $mk = Carbon::create($currentYear, $i, 1)->format('Y-m');
            $tempMonths[]          = $mk;
            $tempTotalAmountMonth[] = $salesData[$mk] ?? 0;
        }
        $data['months']           = $tempMonths;
        $data['totalAmountMonth'] = $tempTotalAmountMonth;

        return view('backend.index', $data);
    }

    public function profile()
    {
        $user = auth()->user();
        return view('backend.profile.index', compact('user'));
    }
}
