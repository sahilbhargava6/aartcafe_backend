<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PageView;
use App\Models\Product;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    // Reports are grouped by the shop's local day, not UTC
    private const TZ = 'Asia/Kolkata';

    /**
     * Public: record one storefront event (page view, add to bag, or leave ping).
     */
    public function track(Request $request)
    {
        $data = $request->validate([
            'visitor_id' => 'required|string|max:64|alpha_dash',
            'session_id' => 'required|string|max:64|alpha_dash',
            'event'      => 'nullable|in:view,add_to_bag,ping',
            'path'       => 'required|string|max:500',
            'product_id' => 'nullable|integer',
            'referrer'   => 'nullable|string|max:1000',
        ]);

        $userAgent = (string) $request->userAgent();
        if (str_starts_with($data['path'], '/dashboard') || preg_match('/bot|crawl|spider|slurp|headless|lighthouse/i', $userAgent)) {
            return response()->noContent();
        }

        $event = $data['event'] ?? 'view';
        $productId = null;
        if ($event === 'add_to_bag' && !empty($data['product_id'])) {
            $productId = Product::whereKey($data['product_id'])->value('id');
        } elseif ($event === 'view' && preg_match('#^/shop/([^/?\#]+)$#', $data['path'], $m) && $m[1] !== 'wedding-frames') {
            $productId = Product::where('slug', urldecode($m[1]))->value('id');
        }

        PageView::create([
            'visitor_id'    => $data['visitor_id'],
            'session_id'    => $data['session_id'],
            'event'         => $event,
            'path'          => $data['path'],
            'product_id'    => $productId,
            'referrer_host' => !empty($data['referrer']) ? (parse_url($data['referrer'], PHP_URL_HOST) ?: null) : null,
            'device'        => match (true) {
                (bool) preg_match('/iPad|Tablet/i', $userAgent) => 'tablet',
                (bool) preg_match('/Mobi|Android|iPhone/i', $userAgent) => 'mobile',
                default => 'desktop',
            },
        ]);

        return response()->noContent();
    }

    /**
     * Dashboard: visitors, page views, bounce rate, session duration, top pages,
     * traffic sources, devices and orders for the requested range.
     */
    public function website(Request $request)
    {
        [$from, $to, $bucket] = $this->range($request);
        $window = [$from->copy()->utc(), $to->copy()->utc()];

        // One row per session that started inside the range
        $sessions = PageView::whereBetween('created_at', $window)
            ->groupBy('session_id')
            ->selectRaw("session_id, MIN(visitor_id) AS visitor_id, MIN(device) AS device, MIN(created_at) AS started_at, MAX(created_at) AS ended_at, SUM(CASE WHEN event = 'view' THEN 1 ELSE 0 END) AS views")
            ->get()
            ->filter(fn ($s) => $s->views > 0)
            ->map(function ($s) {
                $started = Carbon::parse($s->started_at, 'UTC');
                return [
                    'visitor_id' => $s->visitor_id,
                    'device'     => $s->device ?: 'desktop',
                    'started'    => $started->copy()->tz(self::TZ),
                    'duration'   => max(0, Carbon::parse($s->ended_at, 'UTC')->diffInSeconds($started, true)),
                    'views'      => (int) $s->views,
                ];
            });

        $series = [];
        foreach ($this->buckets($from, $to, $bucket) as $key => $label) {
            $series[$key] = ['label' => $label, 'sessions' => []];
        }
        foreach ($sessions as $s) {
            $key = $this->bucketKey($s['started'], $bucket);
            if (isset($series[$key])) {
                $series[$key]['sessions'][] = $s;
            }
        }

        $orders = Order::whereBetween('created_at', $window)->where('status', '!=', 'cancelled');
        $orderCount = (clone $orders)->count();
        $summary = $this->summarise($sessions->all());

        $todayStart = now(self::TZ)->startOfDay()->utc();
        $weekStart = now(self::TZ)->subDays(6)->startOfDay()->utc();

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'bucket' => $bucket],
            'cards' => [
                'visitors_today' => PageView::where('event', 'view')->where('created_at', '>=', $todayStart)->distinct()->count('visitor_id'),
                'visitors_week'  => PageView::where('event', 'view')->where('created_at', '>=', $weekStart)->distinct()->count('visitor_id'),
            ],
            'summary' => $summary + [
                'orders'          => $orderCount,
                'revenue'         => round((float) (clone $orders)->sum('total_amount'), 2),
                'conversion_rate' => $summary['sessions'] > 0 ? round($orderCount / $summary['sessions'] * 100, 1) : 0,
            ],
            'series' => array_values(array_map(
                fn ($b) => ['label' => $b['label']] + $this->summarise($b['sessions']),
                $series
            )),
            'top_pages' => PageView::whereBetween('created_at', $window)->where('event', 'view')
                ->groupBy('path')->selectRaw('path, COUNT(*) AS views')
                ->orderByDesc('views')->limit(8)->get(),
            'top_referrers' => PageView::whereBetween('created_at', $window)->where('event', 'view')->whereNotNull('referrer_host')
                ->groupBy('referrer_host')->selectRaw('referrer_host AS source, COUNT(DISTINCT session_id) AS visits')
                ->orderByDesc('visits')->limit(6)->get(),
            'devices' => $sessions->countBy('device'),
        ]);
    }

    /**
     * Dashboard: views, add-to-bags, orders, units and revenue per product.
     */
    public function products(Request $request)
    {
        [$from, $to] = $this->range($request);
        $window = [$from->copy()->utc(), $to->copy()->utc()];

        $views = PageView::whereBetween('created_at', $window)->where('event', 'view')->whereNotNull('product_id')
            ->groupBy('product_id')
            ->selectRaw('product_id, COUNT(*) AS views, COUNT(DISTINCT visitor_id) AS visitors')
            ->get()->keyBy('product_id');

        $addToBags = PageView::whereBetween('created_at', $window)->where('event', 'add_to_bag')->whereNotNull('product_id')
            ->groupBy('product_id')
            ->selectRaw('product_id, COUNT(*) AS adds')
            ->pluck('adds', 'product_id');

        // Orders store their lines as JSON; older lines only have "id" like "12" or "12-1728000000"
        $sales = [];
        Order::whereBetween('created_at', $window)->where('status', '!=', 'cancelled')
            ->get(['id', 'items'])
            ->each(function ($order) use (&$sales) {
                foreach ($order->items ?? [] as $item) {
                    $productId = $item['product_id'] ?? (preg_match('/^\d+/', (string) ($item['id'] ?? ''), $m) ? (int) $m[0] : null);
                    if (!$productId) continue;
                    $qty = (int) ($item['quantity'] ?? 1);
                    $sales[$productId]['orders'][$order->id] = true;
                    $sales[$productId]['units'] = ($sales[$productId]['units'] ?? 0) + $qty;
                    $sales[$productId]['revenue'] = ($sales[$productId]['revenue'] ?? 0) + (float) ($item['price'] ?? 0) * $qty;
                }
            });

        $rows = Product::with('category:id,name')
            ->get(['id', 'title', 'slug', 'image', 'category_id', 'is_active'])
            ->map(function ($p) use ($views, $addToBags, $sales) {
                $productViews = (int) ($views[$p->id]->views ?? 0);
                $orders = count($sales[$p->id]['orders'] ?? []);
                return [
                    'id'              => $p->id,
                    'title'           => $p->title,
                    'slug'            => $p->slug,
                    'image'           => str_starts_with((string) $p->image, 'data:') ? null : $p->image,
                    'category'        => $p->category->name ?? 'Uncategorized',
                    'is_active'       => (bool) $p->is_active,
                    'views'           => $productViews,
                    'visitors'        => (int) ($views[$p->id]->visitors ?? 0),
                    'add_to_bag'      => (int) ($addToBags[$p->id] ?? 0),
                    'orders'          => $orders,
                    'units'           => (int) ($sales[$p->id]['units'] ?? 0),
                    'revenue'         => round($sales[$p->id]['revenue'] ?? 0, 2),
                    'conversion_rate' => $productViews > 0 ? round($orders / $productViews * 100, 1) : null,
                ];
            })
            ->sortByDesc(fn ($r) => [$r['views'], $r['revenue']])
            ->values();

        return response()->json([
            'range'    => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals'   => [
                'views'      => $rows->sum('views'),
                'add_to_bag' => $rows->sum('add_to_bag'),
                'orders'     => Order::whereBetween('created_at', $window)->where('status', '!=', 'cancelled')->count(),
                'units'      => $rows->sum('units'),
                'revenue'    => round($rows->sum('revenue'), 2),
            ],
            'products' => $rows,
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string} start, end (shop time) and chart bucket
     */
    private function range(Request $request): array
    {
        $now = now(self::TZ);

        return match ($request->query('range', 'week')) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'hour'],
            'month' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), 'day'],
            'year'  => [$now->copy()->subMonths(11)->startOfMonth(), $now->copy()->endOfDay(), 'month'],
            'custom' => $this->customRange($request, $now),
            default => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), 'day'],
        };
    }

    private function customRange(Request $request, Carbon $now): array
    {
        $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to'   => 'required|date_format:Y-m-d|after_or_equal:from',
        ]);
        $from = Carbon::createFromFormat('Y-m-d', $request->query('from'), self::TZ)->startOfDay();
        $to = Carbon::createFromFormat('Y-m-d', $request->query('to'), self::TZ)->endOfDay();

        return [$from, $to, $from->diffInDays($to) > 92 ? 'month' : 'day'];
    }

    /** Ordered chart buckets: key => label */
    private function buckets(Carbon $from, Carbon $to, string $bucket): array
    {
        $step = ['hour' => '1 hour', 'day' => '1 day', 'month' => '1 month'][$bucket];
        $start = $bucket === 'month' ? $from->copy()->startOfMonth() : $from->copy();
        $buckets = [];
        foreach (CarbonPeriod::create($start, $step, $to) as $point) {
            $buckets[$this->bucketKey($point, $bucket)] = match ($bucket) {
                'hour' => $point->format('H:00'),
                'day' => $point->format('d M'),
                'month' => $point->format('M Y'),
            };
        }
        return $buckets;
    }

    private function bucketKey(Carbon $time, string $bucket): string
    {
        return $time->format(['hour' => 'Y-m-d H', 'day' => 'Y-m-d', 'month' => 'Y-m'][$bucket]);
    }

    /** Visitors, sessions, page views, bounce rate (%) and average session length (s) */
    private function summarise(array $sessions): array
    {
        $count = count($sessions);
        return [
            'visitors'         => count(array_unique(array_column($sessions, 'visitor_id'))),
            'sessions'         => $count,
            'page_views'       => array_sum(array_column($sessions, 'views')),
            'bounce_rate'      => $count ? round(count(array_filter($sessions, fn ($s) => $s['views'] === 1)) / $count * 100, 1) : 0,
            'avg_session_secs' => $count ? (int) round(array_sum(array_column($sessions, 'duration')) / $count) : 0,
        ];
    }
}
