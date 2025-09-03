@if(session('success') || session('login_success') || session('register_success') || session('logout_success'))
    <div class="alert alert-success alert-dismissible fade show auto-dismiss mb-4" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        {{ session('success') ?? session('login_success') ?? session('register_success') ?? session('logout_success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show auto-dismiss mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show auto-dismiss mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<style>
.alert {
    position: relative;
    width: 100%;
    border-radius: 10px;
    border: none;
    text-align: left;
    padding: 1rem 1.25rem;
    animation: fadeIn 0.3s ease-in-out;
}

.alert-success {
    background: var(--gradient-success, linear-gradient(135deg, #28a745, #20c997));
    color: white;
}

.alert-danger {
    background: var(--gradient-danger, linear-gradient(135deg, #dc3545, #c82333));
    color: white;
}

.alert .btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.8;
    transition: opacity 0.3s ease;
    padding: 1.25rem;
}

.alert .btn-close:hover {
    opacity: 1;
}

.alert ul {
    list-style-type: none;
    padding-left: 0;
    margin-bottom: 0;
}

.alert ul li {
    margin-bottom: 0.25rem;
}

.alert ul li:last-child {
    margin-bottom: 0;
}

.alert.fade {
    transition: opacity 0.3s linear;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.auto-dismiss {
    animation: fadeIn 0.3s ease-in-out, fadeOut 0.3s ease-in-out 2.7s forwards;
}

@keyframes fadeOut {
    from { opacity: 1; transform: translateY(0); }
    to { opacity: 0; transform: translateY(-10px); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 3 seconds
    const alerts = document.querySelectorAll('.auto-dismiss');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.remove();
        }, 3000);
    });
});
</script> 