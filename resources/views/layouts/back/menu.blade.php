<header class="navbar-expand-md">
    <div class="collapse navbar-collapse" id="navbar-menu">
        <div class="navbar">
            <div class="container-xl">
                <ul class="navbar-nav">
                    <li class="nav-item {{ request()->routeIs('office.home') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('office.home') }}">
                            <span class="nav-link-title">Dashboard</span>
                        </a>
                    </li>
                    @canany(['documents.read', 'documents.issue'])
                        <li class="nav-item">
                            <span class="nav-link disabled" aria-disabled="true"><span class="nav-link-title">Register Dokumen</span></span>
                        </li>
                    @endcanany
                    @can('quotations.read')
                        <li class="nav-item">
                            <span class="nav-link disabled" aria-disabled="true"><span class="nav-link-title">Quotation</span></span>
                        </li>
                    @endcan
                    @can('contracts.read')
                        <li class="nav-item">
                            <span class="nav-link disabled" aria-disabled="true"><span class="nav-link-title">Kontrak</span></span>
                        </li>
                    @endcan
                    @canany(['document-types.read', 'templates.read', 'users.read', 'roles.read'])
                        <li class="nav-item">
                            <span class="nav-link disabled" aria-disabled="true"><span class="nav-link-title">Administrasi</span></span>
                        </li>
                    @endcanany
                </ul>
            </div>
        </div>
    </div>
</header>
