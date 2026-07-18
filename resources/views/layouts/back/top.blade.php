<header class="navbar navbar-expand-md d-print-none">
    <div class="container-xl">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu" aria-controls="navbar-menu" aria-expanded="false" aria-label="Buka navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand navbar-brand-autodark pe-0 pe-md-3" href="{{ route('office.home') }}">
            <img src="{{ asset('static/jblu.png') }}" width="42" height="42" alt="JBLU" class="me-2" style="object-fit: contain">
            <span>{{ config('app.name') }}</span>
        </a>
        <div class="navbar-nav flex-row order-md-last">
            <div class="nav-item dropdown">
                <button class="nav-link d-flex lh-1 text-reset p-0 border-0 bg-transparent" type="button" data-bs-toggle="dropdown" aria-label="Buka menu pengguna">
                    <span class="avatar avatar-sm">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
                    <span class="d-none d-xl-block ps-2 text-start">
                        <span class="d-block">{{ auth()->user()->name }}</span>
                        <span class="mt-1 small text-secondary">{{ auth()->user()->roles()->pluck('name')->join(', ') ?: 'Office User' }}</span>
                    </span>
                </button>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                    <div class="dropdown-item-text text-secondary">{{ auth()->user()->email }}</div>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('auth.logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">Keluar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
