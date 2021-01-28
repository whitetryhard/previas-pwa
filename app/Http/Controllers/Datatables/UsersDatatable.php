<?php

namespace App\Http\Controllers\Datatables;

use App\User;
use Carbon\Carbon;
use Yajra\DataTables\DataTables;

class UsersDatatable
{
    public function usersDatatable()
    {
        // sleep(5000);
        $users = User::with('roles', 'wallet');

        return Datatables::of($users)
            ->addColumn('role', function ($user) {
                return '<span class="badge badge-flat border-grey-800 text-default text-capitalize">' . implode(',', $user->roles->pluck('name')->toArray()) . '</span>';
            })
            ->addColumn('wallet', function ($user) {
                return config('settings.currencyFormat') . $user->balanceFloat;
            })
            ->addColumn('action', function ($user) {
                return '<a href="' . route('admin.get.editUser', $user->id) . '" class="btn btn-sm btn-primary"> View</a>';
            })
            ->editColumn('created_at', function ($user) {
                return '<span data-popup="tooltip" data-placement="left" title="' . $user->created_at->diffForHumans() . '">' . $user->created_at->format('Y-m-d - h:i A') . '</span>';
            })
            ->rawColumns(['role', 'action', 'created_at'])
            ->make(true);
    }
}
