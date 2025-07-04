@extends('layouts.app')

@section('title', 'Roles | Form')

@section('content')
    <div class="row">
        <div class="col">
            <form action="{{ isset($role) ? route('roles.update', $role->id) : route('roles.store') }}" method="POST">
                @csrf
                @if(isset($role))
                    @method('PUT')
                @endif
                <section class="card">
                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @elseif (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif
                    <header class="card-header" style="display: flex;justify-content: space-between;">
                        <h2 class="card-title">Add New Role</h2>
                    </header>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-3 mb-3">
                            <label for="name"><strong>Role Name</strong></label>
                            <input type="text" name="name" value="{{ $role->name ?? '' }}" class="form-control" required>
                            @error('name')<div class="text-danger">{{ $message }}</div>@enderror
                            </div>  
                        </div>
                    </div>
                </section>

                <section class="card">
                    <header class="card-header">
                        <div style="display: flex;justify-content: space-between;">
                            <h2 class="card-title">Assign Permissions</h2>
                        </div>
                        @if ($errors->has('error'))
                            <strong class="text-danger">{{ $errors->first('error') }}</strong>
                        @endif
                    </header>
                    <div class="card-body" style="max-height:600px; overflow-y:auto">
                        <table class="table table-bordered" id="myTable">
                            <thead style="position: sticky;" class="bg-primary text-white text-center">
                                <tr>
                                    <th>Module</th>
                                    <th>View</th>
                                    <th>Create</th>
                                    <th>Edit</th>
                                    <th>Delete</th>
                                    <th>Approval</th>
                                    <th>Print</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $groupedPermissions = [];

                                    foreach ($permissions as $permission) {
                                        $parts = explode('.', $permission->name);
                                        if (count($parts) === 2) {
                                            [$module, $action] = $parts;
                                            $groupedPermissions[$module][$action] = $permission->name;
                                        }
                                    }

                                    $actions = ['index' => 'Index', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete', 'approval' => 'Approval' , 'print' => 'Print'];
                                @endphp

                                @foreach($groupedPermissions as $module => $perms)
                                    <tr>
                                        <td><strong>{{ ucfirst($module) }}</strong></td>
                                        @foreach($actions as $actionKey => $actionLabel)
                                            <td class="text-center">
                                                @if(isset($perms[$actionKey]))
                                                    <input type="checkbox" checked
                                                        name="permissions[]"
                                                        value="{{ $perms[$actionKey] }}"
                                                        {{ isset($role) && $role->hasPermissionTo($perms[$actionKey]) ? 'checked' : '' }}>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <footer class="card-footer text-end mt-2">
                        <a class="btn btn-danger" href="{{ route('roles.index') }}">Discard</a>
                        <button type="submit" class="btn btn-primary">Create</button>
                    </footer>
                </section>
            </form>
        </div>
    </div>
@endsection
