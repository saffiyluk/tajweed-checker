@extends('layouts.admin')

@section('content')
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">User Details</h3>
                        <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Users
                        </a>
                    </div>

                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th>ID</th>
                                <td>{{ $user->id }}</td>
                            </tr>
                            <tr>
                                <th>Name</th>
                                <td>{{ $user->name }}</td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td>{{ $user->email }}</td>
                            </tr>
                            <tr>
                                <th>Role</th>
                                <td>
                                    <div class="badge {{ $user->is_admin ? 'bg-success' : 'bg-warning' }}">
                                        {{ $user->is_admin ? 'Admin' : 'User' }}
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>Email Verified</th>
                                <td>
                                    @if($user->email_verified_at)
                                        <span class="bg-success">
                                            Yes ({{ $user->email_verified_at->format('Y-m-d H:i') }})
                                        </span>
                                    @else
                                        <span class="bg-warning">Not Verified</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Created At</th>
                                <td>{{ $user->created_at->format('Y-m-d H:i:s') }}</td>
                            </tr>
                            <tr>
                                <th>Last Updated</th>
                                <td>{{ $user->updated_at->format('Y-m-d H:i:s') }}</td>
                            </tr>
                        </table>

                        <div class="mt-4 d-flex">
                            <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-primary mr-2">
                                <i class="fas fa-edit"></i> Edit User
                            </a>
                            <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST"
                                onsubmit="return confirm('Are you sure you want to delete this user?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash"></i> Delete User
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .table th {
            background-color: #f8f9fa;
            width: 30%;
        }

        .badge {
            font-size: 0.85em;
            padding: 0.35em 0.6em;
        }

        .btn+form {
            margin-left: 10px;
        }
    </style>
@endpush