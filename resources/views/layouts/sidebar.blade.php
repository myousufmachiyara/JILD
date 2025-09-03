<aside id="sidebar-left" class="sidebar-left">
  <div class="sidebar-header">
    <div class="sidebar-title pt-2" style="display: flex; justify-content: space-between;">
      <a href="{{ route('dashboard') }}" class="logo col-11">
        <img src="/assets/img/logo.webp" class="sidebar-logo" alt="Brand Logo" height="12%" />
      </a>
      <div class="d-md-none toggle-sidebar-left col-1" data-toggle-class="sidebar-left-opened" data-target="html" data-fire-event="sidebar-left-opened">
        <i class="fas fa-times" aria-label="Toggle sidebar"></i>
      </div>
    </div>
    <div class="sidebar-toggle d-none d-md-block" data-toggle-class="sidebar-left-collapsed" data-target="html" data-fire-event="sidebar-left-toggle">
      <i class="fas fa-bars" aria-label="Toggle sidebar"></i>
    </div>
  </div>

  <div class="nano">
    <div class="nano-content">
      <nav id="menu" class="nav-main" role="navigation">
        <ul class="nav nav-main">

          <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('dashboard') }}">
              <i class="fa fa-home" aria-hidden="true"></i>
              <span>Dashboard</span>
            </a>
          </li>

          {{-- User Management --}}
          @if(auth()->user()->can('user_roles.index') || auth()->user()->can('users.index'))
          <li class="nav-parent">
            <a class="nav-link" href="#"><i class="fa fa-user-shield"></i> <span>User Management</span></a>
            <ul class="nav nav-children">
              @can('user_roles.index')
              <li><a class="nav-link" href="{{ route('roles.index') }}">Roles & Permissions</a></li>
              @endcan
              @can('users.index')
              <li><a class="nav-link" href="{{ route('users.index') }}">Users</a></li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- Accounts --}}
          @if(auth()->user()->can('coa.index') || auth()->user()->can('shoa.index'))
          <li class="nav-parent">
            <a class="nav-link" href="#"><i class="fa fa-book"></i> <span>Accounts</span></a>
            <ul class="nav nav-children">
              @can('coa.index')
              <li><a class="nav-link" href="{{ route('coa.index') }}">Chart of Accounts</a></li>
              @endcan
              @can('shoa.index')
              <li><a class="nav-link" href="{{ route('shoa.index') }}">Sub Heads</a></li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- Products --}}
          @if(auth()->user()->can('products.index'))
          <li class="nav-parent">
            <a class="nav-link" href="#"><i class="fa fa-layer-group"></i> <span>Products</span></a>
            <ul class="nav nav-children">
              <li><a class="nav-link" href="{{ route('product-categories.index') }}">Categories</a></li>
              <li><a class="nav-link" href="{{ route('attributes.index') }}">Attributes</a></li>
              <li><a class="nav-link" href="{{ route('products.index') }}">All Products</a></li>
            </ul>
          </li>
          @endif

          {{-- Stock Management --}}
          @can('products.index')
          <li class="nav-parent">
            <a class="nav-link" href="#"><i class="fa fa-cubes"></i> <span>Stock Management</span></a>
            <ul class="nav nav-children">
              <li><a class="nav-link">Locations</a></li>
              <li><a class="nav-link">Stock Transfer</a></li>
            </ul>
          </li>
          @endcan

          {{-- Purchase Invoices --}}
          @if(auth()->user()->can('purchase_invoices.index') || auth()->user()->can('purchase_return.index'))
          <li class="nav-parent">
            <a class="nav-link" href="#"><i class="fa fa-shopping-cart"></i> <span>Purchase Invoices</span></a>
            <ul class="nav nav-children">
              @can('purchase_invoices.index')
              <li><a class="nav-link" href="{{ route('purchase_invoices.index') }}">Purchases</a></li>
              @endcan
              @can('purchase_return.index')
              <li><a class="nav-link" href="{{ route('purchase_return.index') }}">Purchase Return</a></li>
              @endcan
            </ul>
          </li>
          @endif


          {{-- Production --}}
          @if(auth()->user()->can('production.index'))
          <li class="nav-parent">
            <a class="nav-link" href="#"><i class="fa fa-file-invoice"></i> <span>Production</span></a>
            <ul class="nav nav-children">
              <li><a class="nav-link" href="{{ route('production.index') }}">Order</a></li>
              <li><a class="nav-link" href="{{ route('production.receiving.index') }}">Receiving</a></li>
            </ul>
          </li>
          @endif
          

          {{-- Sale Invoices --}}
          @if(auth()->user()->can('sale_invoices.index') || auth()->user()->can('sale_return.index'))
          <li class="nav-parent">
            <a class="nav-link" href="#"><i class="fa fa-cash-register"></i> <span>Sale Invoices</span></a>
            <ul class="nav nav-children">
              @can('sale_invoices.index')
              <li><a class="nav-link" href="{{ route('sale_invoices.index') }}">Sale</a></li>
              @endcan
              @can('sale_return.index')
              <li><a class="nav-link" href="{{ route('sale_return.index') }}">Sale Return</a></li>
              @endcan
            </ul>
          </li>
          @endif
          
          {{-- Vouchers --}}
          @if(auth()->user()->can('payment_vouchers.index'))
            <li><a class="nav-link" href="{{ route('payment_vouchers.index') }}"><i class="fa fa-money-check"></i><span>Vouchers</span></a></li>
          @endcan

          <li class="nav-parent">
            <a class="nav-link" href="#">
              <i class="fa fa-chart-bar"></i>
              <span>Reports</span>
            </a>
            <ul class="nav nav-children">
              <li><a class="nav-link" href="{{ route('reports.inventory_reports') }}">Inventory</a></li>
              <li><a class="nav-link" href="{{ route('reports.purchase_reports') }}">Purchase</a></li>
              <li><a class="nav-link" href="{{ route('reports.production_reports') }}">Production</a></li>
              <li><a class="nav-link" href="{{ route('reports.sales_reports') }}">Sales</a></li>
              <li><a class="nav-link" href="{{ route('reports.accounts_reports') }}">Accounts</a></li>
              <li><a class="nav-link" href="{{ route('reports.business_reports') }}">Business</a></li>
            </ul>
          </li>

        </ul>
      </nav>
    </div>

    <script>
      if (typeof localStorage !== 'undefined') {
        if (localStorage.getItem('sidebar-left-position') !== null) {
          var sidebarLeft = document.querySelector('#sidebar-left .nano-content');
          sidebarLeft.scrollTop = localStorage.getItem('sidebar-left-position');
        }
      }
    </script>
  </div>
</aside>
