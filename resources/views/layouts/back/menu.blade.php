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
                    @can('documents.read')
                        <li class="nav-item {{ request()->routeIs('documents.index', 'documents.show') ? 'active' : '' }}"><a class="nav-link" href="{{ route('documents.index') }}"><span class="nav-link-title">Register Dokumen</span></a></li>
                    @endcan
                    @can('documents.issue')
                        <li class="nav-item {{ request()->routeIs('documents.create', 'documents.issued') ? 'active' : '' }}"><a class="nav-link" href="{{ route('documents.create') }}"><span class="nav-link-title">Terbitkan Nomor</span></a></li>
                    @endcan
                    @can('quotations.read')
                        <li class="nav-item {{ request()->routeIs('quotations.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('quotations.index') }}"><span class="nav-link-title">Quotation</span></a>
                        </li>
                    @endcan
                    @can('contracts.read')
                        <li class="nav-item">
                            <span class="nav-link disabled" aria-disabled="true"><span class="nav-link-title">Kontrak</span></span>
                        </li>
                    @endcan
                    @can('document-types.read')
                        <li class="nav-item {{ request()->routeIs('document-types.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('document-types.index') }}"><span class="nav-link-title">Tipe Dokumen</span></a>
                        </li>
                    @endcan
                    @can('users.read')
                        <li class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('users.index') }}"><span class="nav-link-title">User</span></a>
                        </li>
                    @endcan
                    @can('roles.read')
                        <li class="nav-item {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('roles.index') }}"><span class="nav-link-title">Role</span></a>
                        </li>
                        <li class="nav-item {{ request()->routeIs('permissions.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('permissions.index') }}"><span class="nav-link-title">Permission</span></a>
                        </li>
                    @endcan
                </ul>
            </div>
        </div>
    </div>
</header>
