<aside id="sidebar-left" class="sidebar-left">
  <div class="sidebar-header">
    <div class="sidebar-title pt-2" style="display: flex;justify-content: space-between;">
      <a href="/" class="logo col-11">						
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
          <li class="active">
            <a class="nav-link" href="/"> <i class="fa fa-home" aria-hidden="true"></i><span>Dashboard</span></a>    
          </li>

          @if(auth()->user()->can('user_roles.index') || auth()->user()->can('users.index'))
            <li class="nav-parent">
              <a class="nav-link" href="#"><i class="fa fa-user-shield"></i><span>User Management</span></a>
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

          @if(auth()->user()->can('coa.index') || auth()->user()->can('shoa.index'))
            <li class="nav-parent">
              <a class="nav-link" href="#"><i class="fa fa-book"></i><span>Accounts</span></a>
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

            <li class="nav-parent">
              <a class="nav-link" href="#"><i class="fa fa-book"></i><span>Products</span></a>
              <ul class="nav nav-children">
                <li><a class="nav-link" href="{{ route('product-categories.index') }}">Categories</a></li>
                <li><a class="nav-link" href="{{ route('attributes.index') }}">Attributes</a></li>
                <li><a class="nav-link" href="{{ route('products.index') }}">All Products</a></li>
              </ul>
            </li>

          <li>
            <a class="nav-link" href="/purchases">
              <i style="font-size:16px" class="fa fa-layer-group" aria-hidden="true"></i>
              <span>Purchases</span>
            </a>
          </li>

          <li>
            <a class="nav-link" href="/production">
              <i style="font-size:16px" class="fa fa-layer-group" aria-hidden="true"></i>
              <span>Production</span>
            </a>
          </li>

          <li>
            <a class="nav-link" href="/sales">
              <i style="font-size:16px" class="fa fa-layer-group" aria-hidden="true"></i>
              <span>Sales</span>
            </a>
          </li>

          <li>
            <a class="nav-link" href="#">
              <i style="font-size:16px" class="fa fa-layer-group" aria-hidden="true"></i>
              <span>Payments</span>
            </a>
          </li>
            
          <li>
            <a class="nav-link" href="#">
              <i style="font-size:16px" class="fa fa-layer-group" aria-hidden="true"></i>
              <span>Reports</span>
            </a>
          </li>
        </ul>	
      </nav>
		</div>

    <script>
      // Maintain Scroll Position
      if (typeof localStorage !== 'undefined') {
        if (localStorage.getItem('sidebar-left-position') !== null) {
          var initialPosition = localStorage.getItem('sidebar-left-position'),
          sidebarLeft = document.querySelector('#sidebar-left .nano-content');

          sidebarLeft.scrollTop = initialPosition;
        }
      }
    </script>
  </div>
</aside>