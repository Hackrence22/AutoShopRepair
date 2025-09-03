<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Service;
use App\Models\PaymentMethod;
use App\Models\Feedback;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    protected $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index()
    {
        $isOwner = auth('admin')->user()?->isOwner();
        $ownerAdminId = auth('admin')->id();
        
        // Get notification data
        $unreadNotifications = $this->notificationService->getUnreadCount($ownerAdminId);
        $recentNotifications = $this->notificationService->getRecentNotifications($ownerAdminId, 5);
        
        // Basic appointment statistics
        $totalAppointments = Appointment::when($isOwner, function($q) use ($ownerAdminId) { 
            $q->where('shop_id', $ownerAdminId); 
        })->count();
        $pendingAppointments = Appointment::when($isOwner, function($q) use ($ownerAdminId) { 
            $q->where('shop_id', $ownerAdminId); 
        })->where('status', 'pending')->count();
        $confirmedAppointments = Appointment::when($isOwner, function($q) use ($ownerAdminId) { 
            $q->where('shop_id', $ownerAdminId); 
        })->where('status', 'confirmed')->count();
        $completedAppointments = Appointment::when($isOwner, function($q) use ($ownerAdminId) { 
            $q->where('shop_id', $ownerAdminId); 
        })->where('status', 'completed')->count();
        $cancelledAppointments = Appointment::when($isOwner, function($q) use ($ownerAdminId) { 
            $q->where('shop_id', $ownerAdminId); 
        })->where('status', 'cancelled')->count();
        $todayAppointments = Appointment::when($isOwner, function($q) use ($ownerAdminId) { 
            $q->where('shop_id', $ownerAdminId); 
        })->whereDate('appointment_date', now()->toDateString())->count();
        
        // Payment statistics
        $pendingPayments = Appointment::when($isOwner, function($q) use ($ownerAdminId) { 
            $q->where('shop_id', $ownerAdminId); 
        })->where('payment_status', 'unpaid')->where('payment_proof', '!=', null)->count();
        $confirmedPayments = Appointment::when($isOwner, function($q) use ($ownerAdminId) { 
            $q->where('shop_id', $ownerAdminId); 
        })->where('payment_status', 'paid')->count();
        $rejectedPayments = Appointment::when($isOwner, function($q) use ($ownerAdminId) { 
            $q->where('shop_id', $ownerAdminId); 
        })->where('payment_status', 'rejected')->count();
        
        // User statistics
        $totalUsers = User::count();
        $newUsersThisMonth = User::whereMonth('created_at', now()->month)->count();
        $activeUsers = User::whereHas('appointments', function($query) {
            $query->where('created_at', '>=', now()->subMonths(3));
        })->count();
        
        // Service statistics
        $totalServices = Service::when($isOwner, function($q) use ($ownerAdminId) { 
            $q->where('shop_id', $ownerAdminId); 
        })->count();
        $activeServices = Service::when($isOwner, function($q) use ($ownerAdminId) { 
            $q->where('shop_id', $ownerAdminId); 
        })->where('is_active', true)->count();
        
        // Payment method statistics
        $totalPaymentMethods = PaymentMethod::when($isOwner, function($q) use ($ownerAdminId) { 
            $q->whereHas('shop', function($s) use ($ownerAdminId) {
                $s->where('id', $ownerAdminId);
            }); 
        })->count();
        $activePaymentMethods = PaymentMethod::when($isOwner, function($q) use ($ownerAdminId) { 
            $q->whereHas('shop', function($s) use ($ownerAdminId) {
                $s->where('id', $ownerAdminId);
            }); 
        })->where('is_active', true)->count();
        
        // Feedback statistics
        // Owners should not see feedback metrics
        $totalFeedback = $isOwner ? 0 : Feedback::count();
        $recentFeedback = $isOwner ? 0 : Feedback::where('created_at', '>=', now()->subDays(30))->count();
        
        // Revenue analytics
        $totalRevenue = Service::join('appointments', 'services.id', '=', 'appointments.service_id')
            ->where('appointments.status', 'completed')
            ->when($isOwner, function($q) use ($ownerAdminId) { 
                $q->where('appointments.shop_id', $ownerAdminId); 
            })
            ->sum('services.price');
            
        $monthlyRevenue = Service::join('appointments', 'services.id', '=', 'appointments.service_id')
            ->where('appointments.status', 'completed')
            ->whereMonth('appointments.created_at', now()->month)
            ->when($isOwner, function($q) use ($ownerAdminId) { 
                $q->where('appointments.shop_id', $ownerAdminId); 
            })
            ->sum('services.price');
            
        $weeklyRevenue = Service::join('appointments', 'services.id', '=', 'appointments.service_id')
            ->where('appointments.status', 'completed')
            ->where('appointments.created_at', '>=', now()->subWeek())
            ->when($isOwner, function($q) use ($ownerAdminId) { 
                $q->where('appointments.shop_id', $ownerAdminId); 
            })
            ->sum('services.price');
        
        // Service popularity analytics
        $popularServices = Service::withCount(['appointments' => function($query) use ($isOwner, $ownerAdminId) {
            $query->where('status', 'completed');
            if ($isOwner) {
                $query->where('shop_id', $ownerAdminId);
            }
        }])
        ->when($isOwner, function($q) use ($ownerAdminId) { 
            $q->where('shop_id', $ownerAdminId); 
        })
        ->orderBy('appointments_count', 'desc')
        ->limit(5)
        ->get();
        
        // Monthly appointment trends
        $monthlyTrends = Appointment::selectRaw('MONTH(appointment_date) as month, COUNT(*) as count')
            ->whereYear('appointment_date', now()->year)
            ->when($isOwner, function($q) use ($ownerAdminId) { 
                $q->where('shop_id', $ownerAdminId); 
            })
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month')
            ->toArray();
        
        // Fill missing months with 0
        for ($i = 1; $i <= 12; $i++) {
            if (!isset($monthlyTrends[$i])) {
                $monthlyTrends[$i] = 0;
            }
        }
        ksort($monthlyTrends);
        
        // Vehicle type analytics
        $vehicleTypeStats = Appointment::selectRaw('vehicle_type, COUNT(*) as count')
            ->when($isOwner, function($q) use ($ownerAdminId) { 
                $q->where('shop_id', $ownerAdminId); 
            })
            ->groupBy('vehicle_type')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();
        
        // Payment method usage
        $paymentMethodUsage = PaymentMethod::withCount(['appointments' => function($query) use ($isOwner, $ownerAdminId) {
            $query->where('status', 'completed');
            if ($isOwner) {
                $query->where('shop_id', $ownerAdminId);
            }
        }])
        ->when($isOwner, function($q) use ($ownerAdminId) { 
            $q->whereHas('shop', function($s) use ($ownerAdminId) {
                $s->where('id', $ownerAdminId);
            }); 
        })
        ->orderBy('appointments_count', 'desc')
        ->get();
        
        // Recent activity
        $recentAppointments = Appointment::with('user')
            ->when($isOwner, function($q) use ($ownerAdminId) { 
                $q->where('shop_id', $ownerAdminId); 
            })
            ->orderBy('appointment_date', 'desc')
            ->limit(5)
            ->get();
            
        $recentUsers = User::when($isOwner, function($q) use ($ownerAdminId) {
            $q->whereHas('appointments', function($appt) use ($ownerAdminId) {
                $appt->where('shop_id', $ownerAdminId);
            });
        })->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
            
        $recentFeedback = $isOwner ? collect() : Feedback::orderBy('created_at', 'desc')->limit(5)->get();
        
        // Performance metrics
        $completionRate = $totalAppointments > 0 ? round(($completedAppointments / $totalAppointments) * 100, 2) : 0;
        $cancellationRate = $totalAppointments > 0 ? round(($cancelledAppointments / $totalAppointments) * 100, 2) : 0;
        $averageAppointmentsPerUser = $totalUsers > 0 ? round($totalAppointments / $totalUsers, 2) : 0;
        
        // Today's schedule
        $todaySchedule = Appointment::with(['user', 'service'])
            ->when($isOwner, function($q) use ($ownerAdminId) { 
                $q->where('shop_id', $ownerAdminId); 
            })
            ->whereDate('appointment_date', now()->toDateString())
            ->orderBy('appointment_time')
            ->get();
        
        // Upcoming appointments
        $upcomingAppointments = Appointment::with(['user', 'service'])
            ->when($isOwner, function($q) use ($ownerAdminId) { 
                $q->where('shop_id', $ownerAdminId); 
            })
            ->whereBetween('appointment_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->where('status', '!=', 'cancelled')
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->limit(10)
            ->get();
        
        return view('admin.dashboard', compact(
            'totalAppointments',
            'pendingAppointments',
            'confirmedAppointments',
            'completedAppointments',
            'cancelledAppointments',
            'todayAppointments',
            'pendingPayments',
            'confirmedPayments',
            'rejectedPayments',
            'totalUsers',
            'newUsersThisMonth',
            'activeUsers',
            'totalServices',
            'activeServices',
            'totalPaymentMethods',
            'activePaymentMethods',
            'totalFeedback',
            'recentFeedback',
            'totalRevenue',
            'monthlyRevenue',
            'weeklyRevenue',
            'popularServices',
            'monthlyTrends',
            'vehicleTypeStats',
            'paymentMethodUsage',
            'recentAppointments',
            'recentUsers',
            'recentFeedback',
            'completionRate',
            'cancellationRate',
            'averageAppointmentsPerUser',
            'todaySchedule',
            'upcomingAppointments',
            'unreadNotifications',
            'recentNotifications'
        ));
    }
} 