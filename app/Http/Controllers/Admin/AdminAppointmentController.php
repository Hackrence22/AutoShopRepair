<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use App\Models\Notification;
use App\Services\SmsService;

class AdminAppointmentController extends Controller
{
    public function index()
    {
        $q = request('q');
        $appointments = Appointment::with(['user', 'service', 'shop', 'paymentMethod'])
            ->when(auth('admin')->user()?->isOwner(), function($query) {
                $adminId = auth('admin')->id();
                $adminName = auth('admin')->user()->name;
                $query->whereHas('shop', function($s) use ($adminId, $adminName) {
                    $s->where('admin_id', $adminId)
                      ->orWhere(function($ss) use ($adminName) { $ss->whereNull('admin_id')->where('owner_name', $adminName); });
                });
            })
            ->when($q, function($query) use ($q) {
                $query->where(function($sub) use ($q) {
                    $sub->where('customer_name', 'like', "%$q%")
                        ->orWhere('email', 'like', "%$q%")
                        ->orWhere('vehicle_model', 'like', "%$q%")
                        ->orWhere('reference_number', 'like', "%$q%")
                        ->orWhere('status', 'like', "%$q%")
                        ->orWhere('payment_status', 'like', "%$q%")
                        ->orWhereHas('service', function($s) use ($q) { $s->where('name', 'like', "%$q%"); })
                        ->orWhereHas('shop', function($s) use ($q) { $s->where('name', 'like', "%$q%"); })
                        ->orWhereHas('paymentMethod', function($s) use ($q) { $s->where('name', 'like', "%$q%"); });
                });
            })
            ->orderBy('appointment_date', 'asc')
            ->paginate(15)
            ->withQueryString();
        // Group current page items by shop for sectioned tables
        $appointmentsByShop = $appointments->getCollection()->groupBy(function($appointment) {
            return $appointment->shop ? $appointment->shop->name : 'No Shop';
        });
        return view('admin.appointments.index', compact('appointmentsByShop', 'appointments'));
    }

    public function create()
    {
        $servicesQuery = \App\Models\Service::query();
        if (auth('admin')->user()?->isOwner()) {
            $adminId = auth('admin')->id();
            $adminName = auth('admin')->user()->name;
            $servicesQuery->whereHas('shop', function($s) use ($adminId, $adminName) {
                $s->where('admin_id', $adminId)
                  ->orWhere(function($ss) use ($adminName) { $ss->whereNull('admin_id')->where('owner_name', $adminName); });
            });
        }
        $services = $servicesQuery->get();
        return view('admin.appointments.create', compact('services'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'vehicle_type' => 'required|string|max:50',
            'vehicle_model' => 'required|string|max:100',
            'vehicle_year' => 'required|string|max:4',
            'service_id' => 'required|exists:services,id',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'description' => 'nullable|string',
            'technician' => 'nullable|string|max:255',
        ]);

        // Get the service and set service_type
        $service = \App\Models\Service::find($validated['service_id']);
        if ($service) {
            $validated['service_type'] = $service->name;
        }

        $validated['status'] = 'pending';
        Appointment::create($validated);

        return redirect()->route('admin.appointments.index')
            ->with('success', 'Appointment created successfully!');
    }

    public function show(Appointment $appointment)
    {
        $appointment->load(['user', 'shop', 'service', 'paymentMethod', 'assignedTechnician']);
        $noShow = app(\App\Services\NoShowPredictionService::class)->predict($appointment);
        return view('admin.appointments.show', compact('appointment', 'noShow'));
    }

    public function edit(Appointment $appointment)
    {
        if (auth('admin')->user()?->isOwner()) {
            $shop = $appointment->shop;
            $ownerOk = ($shop && ($shop->admin_id === auth('admin')->id() || (!$shop->admin_id && $shop->owner_name === auth('admin')->user()->name)));
            if (!$ownerOk) { abort(403); }
        }
        $appointment->load(['user', 'shop', 'service', 'paymentMethod']);
        $servicesQuery = \App\Models\Service::query();
        if (auth('admin')->user()?->isOwner()) {
            $adminId = auth('admin')->id();
            $adminName = auth('admin')->user()->name;
            $servicesQuery->whereHas('shop', function($s) use ($adminId, $adminName) {
                $s->where('admin_id', $adminId)
                  ->orWhere(function($ss) use ($adminName) { $ss->whereNull('admin_id')->where('owner_name', $adminName); });
            });
        }
        $services = $servicesQuery->get();
        return view('admin.appointments.edit', compact('appointment', 'services'));
    }

    public function update(Request $request, Appointment $appointment)
    {
        if (auth('admin')->user()?->isOwner()) {
            $shop = $appointment->shop;
            $ownerOk = ($shop && ($shop->admin_id === auth('admin')->id() || (!$shop->admin_id && $shop->owner_name === auth('admin')->user()->name)));
            if (!$ownerOk) { abort(403); }
        }
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'vehicle_type' => 'required|string|max:50',
            'vehicle_model' => 'required|string|max:100',
            'vehicle_year' => 'required|string|max:4',
            'service_id' => 'required|exists:services,id',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'description' => 'nullable|string',
            'status' => 'required|in:pending,approved,confirmed,completed,cancelled',
            'technician' => 'nullable|string|max:255',
            'technician_id' => 'nullable|exists:technicians,id',
        ]);

        // Get the service and set service_type
        $service = \App\Models\Service::find($validated['service_id']);
        if ($service) {
            $validated['service_type'] = $service->name;
        }

        $appointment->update($validated);
        // Notify user of status change
        Notification::create([
            'user_id' => $appointment->user_id,
            'admin_id' => auth('admin')->id(),
            'type' => 'status',
            'title' => 'Appointment Status Updated',
            'message' => 'Your appointment #' . $appointment->id . ' status is now: ' . ucfirst($appointment->status) . '.',
            'data' => ['appointment_id' => $appointment->id],
        ]);
        // SMS: status change
        try {
            $sms = app(SmsService::class);
            $to = $sms->toE164($appointment->phone);
            if ($to) {
                $sms->send($to, 'Your appointment #' . $appointment->id . ' status is now: ' . ucfirst($appointment->status) . '.');
            }
        } catch (\Throwable $e) {}

        return redirect()->route('admin.appointments.index')
            ->with('success', 'Appointment updated successfully!');
    }

    public function destroy(Appointment $appointment)
    {
        $appointment->delete();
        return redirect()->route('admin.appointments.index')
            ->with('success', 'Appointment deleted successfully!');
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,confirmed,completed,cancelled'
        ]);

        $appointment->update($validated);

        if ($validated['status'] === 'cancelled') {
            $appointment->update(['cancelled_at' => now()]);
        }
        // Notify user of status change
        Notification::create([
            'user_id' => $appointment->user_id,
            'admin_id' => auth('admin')->id(),
            'type' => 'status',
            'title' => 'Appointment Status Updated',
            'message' => 'Your appointment #' . $appointment->id . ' status is now: ' . ucfirst($appointment->status) . '.',
            'data' => ['appointment_id' => $appointment->id],
        ]);
        // SMS: status change
        try {
            $sms = app(SmsService::class);
            $to = $sms->toE164($appointment->phone);
            if ($to) {
                $sms->send($to, 'Your appointment #' . $appointment->id . ' status is now: ' . ucfirst($appointment->status) . '.');
            }
        } catch (\Throwable $e) {}

        return redirect()->back()
            ->with('success', 'Appointment status updated successfully!');
    }

    public function approve(Appointment $appointment)
    {
        if (empty($appointment->technician) && empty($appointment->technician_id)) {
            return redirect()->back()->with('error', 'Please assign a technician before approving this appointment.');
        }
        $appointment->update(['status' => 'approved']);
        // Notify user of approval
        Notification::create([
            'user_id' => $appointment->user_id,
            'admin_id' => auth('admin')->id(),
            'type' => 'status',
            'title' => 'Appointment Approved',
            'message' => 'Your appointment #' . $appointment->id . ' has been approved!',
            'data' => ['appointment_id' => $appointment->id],
        ]);
        // SMS: approved
        try {
            $sms = app(SmsService::class);
            $to = $sms->toE164($appointment->phone);
            if ($to) {
                $sms->send($to, 'Your appointment #' . $appointment->id . ' has been approved.');
            }
        } catch (\Throwable $e) {}
        return redirect()->back()->with('success', 'Appointment approved successfully!');
    }

    public function reject(Appointment $appointment)
    {
        $appointment->update([
            'status' => 'cancelled',
            'cancelled_at' => now()
        ]);
        // Notify user of rejection
        Notification::create([
            'user_id' => $appointment->user_id,
            'admin_id' => auth('admin')->id(),
            'type' => 'status',
            'title' => 'Appointment Rejected',
            'message' => 'Your appointment #' . $appointment->id . ' has been rejected. Please contact us for more information.',
            'data' => ['appointment_id' => $appointment->id],
        ]);
        // SMS: rejected
        try {
            $sms = app(SmsService::class);
            $to = $sms->toE164($appointment->phone);
            if ($to) {
                $sms->send($to, 'Your appointment #' . $appointment->id . ' has been rejected.');
            }
        } catch (\Throwable $e) {}
        return redirect()->back()
            ->with('success', 'Appointment rejected successfully!');
    }
} 